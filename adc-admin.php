<?php
/**
 * ADC Video Display - Admin Settings
 * Version: 3.0 - Multiidioma
 * 
 * Maneja toda la configuración de administración del plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ADC_Admin
{
    private $plugin_name = 'adc-video-display';
    private $options;
    private $languages = array('es', 'en', 'he');

    public function __construct()
    {
        $this->options = get_option($this->plugin_name);

        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_notices', array($this, 'admin_notices'));

        // Add scripts and styles for admin
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // AJAX handlers
        add_action('wp_ajax_adc_update_program_order', array($this, 'ajax_update_program_order'));
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook)
    {
        // Only load on our settings pages
        if (!$this->is_adc_admin_page($hook)) {
            return;
        }

        // Enqueue jQuery UI for sortable
        wp_enqueue_script('jquery-ui-sortable');

        // Enqueue our consolidated CSS for admin styles
        wp_enqueue_style(
            'adc-admin-style',
            ADC_PLUGIN_URL . 'style.css',
            array(),
            '3.0'
        );

        // Admin specific JavaScript
        wp_enqueue_script(
            'adc-admin-script',
            ADC_PLUGIN_URL . 'admin.js',
            array('jquery', 'jquery-ui-sortable'),
            '3.0',
            true
        );

        // Localize admin script
        wp_localize_script('adc-admin-script', 'adc_admin_config', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('adc_admin_nonce')
        ));
    }

    /**
     * Check if current page is an ADC admin page
     */
    private function is_adc_admin_page($hook)
    {
        $adc_pages = array(
            'toplevel_page_' . $this->plugin_name,
            'adc-videos_page_' . $this->plugin_name . '-order-es',
            'adc-videos_page_' . $this->plugin_name . '-order-en',
            'adc-videos_page_' . $this->plugin_name . '-order-he'
        );

        return in_array($hook, $adc_pages);
    }

    /**
     * AJAX handler to update program order
     */
    public function ajax_update_program_order()
    {
        check_ajax_referer('adc_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $program_order = isset($_POST['program_order']) ? $_POST['program_order'] : array();
        $language = isset($_POST['language']) ? sanitize_text_field($_POST['language']) : 'es';

        // Validate language
        if (!in_array($language, $this->languages)) {
            wp_send_json_error('Invalid language');
            return;
        }

        // Sanitize - ensure we only have integers
        $sanitized_order = array_map('intval', $program_order);

        // Save order for specific language
        update_option('adc_programs_order_' . $language, $sanitized_order);

        wp_send_json_success('Order updated successfully');
    }

    /**
     * Add menu item to WordPress admin
     */
    public function add_admin_menu()
    {
        add_menu_page(
            'ADC Videos',
            'ADC Videos',
            'manage_options',
            $this->plugin_name,
            array($this, 'display_settings_page'),
            'dashicons-video-alt3',
            30
        );

        // Add submenu for program ordering - one for each language
        add_submenu_page(
            $this->plugin_name,
            'Ordenar Programas (Español)',
            'Ordenar (ES)',
            'manage_options',
            $this->plugin_name . '-order-es',
            array($this, 'display_program_order_page_es')
        );

        add_submenu_page(
            $this->plugin_name,
            'Ordenar Programas (English)',
            'Ordenar (EN)',
            'manage_options',
            $this->plugin_name . '-order-en',
            array($this, 'display_program_order_page_en')
        );

        add_submenu_page(
            $this->plugin_name,
            'Ordenar Programas (עברית)',
            'Ordenar (HE)',
            'manage_options',
            $this->plugin_name . '-order-he',
            array($this, 'display_program_order_page_he')
        );
    }

    /**
     * Initialize plugin settings
     */
    public function init_settings()
    {
        register_setting(
            $this->plugin_name . '_group',
            $this->plugin_name,
            array($this, 'sanitize_settings')
        );

        $this->register_api_settings();
        $this->register_display_settings();
        $this->register_search_settings();
        $this->register_advanced_settings();
    }

    /**
     * Register API settings section
     */
    private function register_api_settings()
    {
        add_settings_section(
            'api_settings',
            'Configuración de API',
            array($this, 'api_settings_section_callback'),
            $this->plugin_name
        );

        add_settings_field('api_token', 'Token de API', array($this, 'api_token_callback'), $this->plugin_name, 'api_settings');
        add_settings_field('api_url', 'URL Base de API', array($this, 'api_url_callback'), $this->plugin_name, 'api_settings');
    }

    /**
     * Register display settings section
     */
    private function register_display_settings()
    {
        add_settings_section(
            'display_settings',
            'Configuración de Visualización',
            array($this, 'display_settings_section_callback'),
            $this->plugin_name
        );

        add_settings_field('videos_per_row', 'Videos por Fila', array($this, 'videos_per_row_callback'), $this->plugin_name, 'display_settings');
        add_settings_field('enable_autoplay', 'Habilitar Autoplay', array($this, 'enable_autoplay_callback'), $this->plugin_name, 'display_settings');
        add_settings_field('autoplay_countdown', 'Segundos para Autoplay', array($this, 'autoplay_countdown_callback'), $this->plugin_name, 'display_settings');
    }

    /**
     * Register search settings section
     */
    private function register_search_settings()
    {
        add_settings_section(
            'search_settings',
            'Configuración de Búsqueda',
            array($this, 'search_settings_section_callback'),
            $this->plugin_name
        );

        add_settings_field('enable_search', 'Habilitar Búsqueda', array($this, 'enable_search_callback'), $this->plugin_name, 'search_settings');
    }

    /**
     * Register advanced settings section
     */
    private function register_advanced_settings()
    {
        add_settings_section(
            'advanced_settings',
            'Configuración Avanzada',
            array($this, 'advanced_settings_section_callback'),
            $this->plugin_name
        );

        add_settings_field('related_videos_count', 'Cantidad de Videos Relacionados', array($this, 'related_videos_count_callback'), $this->plugin_name, 'advanced_settings');
        add_settings_field('debug_mode', 'Modo Debug', array($this, 'debug_mode_callback'), $this->plugin_name, 'advanced_settings');
    }

    /**
     * Section callbacks
     */
    public function api_settings_section_callback()
    {
        echo '<p>Configura los datos de conexión a la API de TuTorah TV.</p>';
    }

    public function display_settings_section_callback()
    {
        echo '<p>Configura las opciones de visualización del contenido.</p>';
    }

    public function search_settings_section_callback()
    {
        echo '<p>Configura las opciones de búsqueda.</p>';
    }

    public function advanced_settings_section_callback()
    {
        echo '<p>Opciones avanzadas para desarrolladores.</p>';
    }

    /**
     * Field callbacks
     */
    public function api_token_callback()
    {
        $this->render_text_field('api_token', 'Tu token de API', 'El token de autenticación para la API.');
    }

    public function api_url_callback()
    {
        $this->render_url_field('api_url', 'https://api.tutorah.tv/v1', 'URL base de la API (sin slash final).');
    }

    public function videos_per_row_callback()
    {
        $value = isset($this->options['videos_per_row']) ? $this->options['videos_per_row'] : '4';
        echo '<select name="' . $this->plugin_name . '[videos_per_row]">';
        for ($i = 3; $i <= 6; $i++) {
            echo '<option value="' . $i . '"' . selected($value, $i, false) . '>' . $i . ' videos</option>';
        }
        echo '</select>';
        echo '<p class="description">Número de videos a mostrar por fila.</p>';
    }

    public function enable_autoplay_callback()
    {
        $this->render_checkbox_field('enable_autoplay', 'Activar reproducción automática del siguiente video');
    }

    public function autoplay_countdown_callback()
    {
        $this->render_number_field('autoplay_countdown', 3, 30, 'Segundos antes de reproducir el siguiente video (3-30).');
    }

    public function enable_search_callback()
    {
        $this->render_checkbox_field('enable_search', 'Activar funcionalidad de búsqueda');
    }

    public function related_videos_count_callback()
    {
        $this->render_number_field('related_videos_count', 4, 20, 'Cantidad de videos relacionados a mostrar (4-20).');
    }

    public function debug_mode_callback()
    {
        $this->render_checkbox_field('debug_mode', 'Activar modo debug (muestra información adicional en la consola)');
    }

    /**
     * Helper methods for rendering fields
     */
    private function render_text_field($field_name, $placeholder = '', $description = '')
    {
        $value = isset($this->options[$field_name]) ? $this->options[$field_name] : '';
        echo '<input type="text" name="' . $this->plugin_name . '[' . $field_name . ']" value="' . esc_attr($value) . '" class="regular-text" placeholder="' . esc_attr($placeholder) . '" />';
        if ($description) {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
    }

    private function render_url_field($field_name, $placeholder = '', $description = '')
    {
        $value = isset($this->options[$field_name]) ? $this->options[$field_name] : $placeholder;
        echo '<input type="url" name="' . $this->plugin_name . '[' . $field_name . ']" value="' . esc_attr($value) . '" class="regular-text" placeholder="' . esc_attr($placeholder) . '" />';
        if ($description) {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
    }

    private function render_checkbox_field($field_name, $label)
    {
        $checked = isset($this->options[$field_name]) ? $this->options[$field_name] : '0';
        echo '<label><input type="checkbox" name="' . $this->plugin_name . '[' . $field_name . ']" value="1"' . checked($checked, '1', false) . ' /> ' . esc_html($label) . '</label>';
    }

    private function render_number_field($field_name, $min, $max, $description = '')
    {
        $value = isset($this->options[$field_name]) ? $this->options[$field_name] : $min;
        echo '<input type="number" name="' . $this->plugin_name . '[' . $field_name . ']" value="' . esc_attr($value) . '" min="' . $min . '" max="' . $max . '" class="small-text" />';
        if ($description) {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
    }

    /**
     * Sanitize settings before saving
     */
    public function sanitize_settings($input)
    {
        $sanitized = array();

        // API Settings
        $sanitized['api_token'] = isset($input['api_token']) ? sanitize_text_field($input['api_token']) : '';
        $sanitized['api_url'] = isset($input['api_url']) ? rtrim(esc_url_raw($input['api_url']), '/') : '';

        // Display Settings
        $sanitized['videos_per_row'] = isset($input['videos_per_row']) && in_array($input['videos_per_row'], array('3', '4', '5', '6')) ? $input['videos_per_row'] : '4';
        $sanitized['enable_autoplay'] = isset($input['enable_autoplay']) ? '1' : '0';
        $sanitized['autoplay_countdown'] = isset($input['autoplay_countdown']) ? max(3, min(30, intval($input['autoplay_countdown']))) : 5;

        // Search Settings
        $sanitized['enable_search'] = isset($input['enable_search']) ? '1' : '0';

        // Advanced Settings
        $sanitized['related_videos_count'] = isset($input['related_videos_count']) ? max(4, min(20, intval($input['related_videos_count']))) : 8;
        $sanitized['debug_mode'] = isset($input['debug_mode']) ? '1' : '0';

        return $sanitized;
    }

    /**
     * Display admin notices
     */
    public function admin_notices()
    {
        if (!$this->is_adc_admin_page($_GET['page'] ?? '')) {
            return;
        }

        if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
            echo '<div class="notice notice-success is-dismissible"><p>¡Configuración guardada exitosamente!</p></div>';
        }

        if (isset($_GET['order-updated']) && $_GET['order-updated']) {
            echo '<div class="notice notice-success is-dismissible"><p>¡Orden de programas actualizado exitosamente!</p></div>';
        }
    }

    /**
     * Display the settings page
     */
    public function display_settings_page()
    {
        // Test API connection for each language
        $api_status = array();
        foreach ($this->languages as $lang) {
            $api = new ADC_API($lang);
            $api_status[$lang] = $this->test_api_connection($api);
        }

        echo '<div class="wrap">';
        echo '<h1>ADC Video Display - Configuración</h1>';

        // Show API status for all languages
        echo '<div class="card">';
        echo '<h2>Estado de Conexión API por Idioma</h2>';
        foreach ($api_status as $lang => $status) {
            $this->render_api_status($status, $lang);
        }
        echo '</div>';

        echo '<form method="post" action="options.php">';
        settings_fields($this->plugin_name . '_group');
        do_settings_sections($this->plugin_name);
        submit_button();
        echo '</form>';

        $this->render_usage_info();

        echo '</div>';
    }

    /**
     * Display program order pages for each language
     */
    public function display_program_order_page_es()
    {
        $this->display_program_order_page('es');
    }

    public function display_program_order_page_en()
    {
        $this->display_program_order_page('en');
    }

    public function display_program_order_page_he()
    {
        $this->display_program_order_page('he');
    }

    /**
     * Generic display program order page
     */
    private function display_program_order_page($language)
    {
        $api = new ADC_API($language);
        $programs = $api->get_programs();
        $saved_order = get_option('adc_programs_order_' . $language, array());

        // Apply saved order
        if (!empty($saved_order)) {
            $programs = $this->apply_saved_order($programs, $saved_order);
        }

        $language_names = array(
            'es' => 'Español',
            'en' => 'English',
            'he' => 'עברית'
        );

        echo '<div class="wrap">';
        echo '<h1>Ordenar Programas - ' . $language_names[$language] . '</h1>';
        echo '<div class="notice notice-info"><p>Arrastra y suelta los programas para cambiar su orden de visualización. El orden se guardará automáticamente.</p></div>';

        if (empty($programs)) {
            echo '<div class="notice notice-error"><p>No se pudieron cargar los programas. Verifica la conexión a la API.</p></div>';
        } else {
            $this->render_sortable_programs($programs, $language);
        }

        echo '</div>';
    }

    /**
     * Helper methods for rendering
     */
    private function render_api_status($api_status, $language)
    {
        $language_names = array(
            'es' => 'Español',
            'en' => 'English',
            'he' => 'עברית'
        );

        echo '<div style="margin-bottom: 20px; padding: 10px; border-left: 4px solid ' . ($api_status['connection'] ? '#46b450' : '#dc3232') . ';">';
        echo '<h3 style="margin-top: 0;">' . $language_names[$language] . '</h3>';

        if ($api_status['connection']) {
            echo '<p><strong>Estado:</strong> Conexión exitosa ✓</p>';
            if (isset($api_status['programs_count'])) {
                echo '<p>Programas disponibles: ' . $api_status['programs_count'] . '</p>';
            }
        } else {
            echo '<p><strong>Estado:</strong> Error de conexión ✗</p>';
            if (isset($api_status['error'])) {
                echo '<p>Error: ' . esc_html($api_status['error']) . '</p>';
            }
        }
        echo '</div>';
    }

    private function render_usage_info()
    {
        echo '<div class="card" style="margin-top: 30px;">';
        echo '<h2>Información de Uso</h2>';

        echo '<h3>Shortcodes disponibles:</h3>';
        echo '<ul>';
        echo '<li><code>[adc_content]</code> - Muestra el contenido en Español</li>';
        echo '<li><code>[adc_content_en]</code> - Muestra el contenido en Inglés</li>';
        echo '<li><code>[adc_content_he]</code> - Muestra el contenido en Hebreo</li>';
        echo '</ul>';

        echo '<h3>Configuración de Menús:</h3>';
        echo '<h4>Para PROGRAMAS:</h4>';
        echo '<ul>';
        echo '<li>Español: Texto "PROGRAMAS_ES" + Clase CSS "adc-programs-menu-trigger"</li>';
        echo '<li>Inglés: Texto "PROGRAMAS_EN" + Clase CSS "adc-programs-menu-trigger-en"</li>';
        echo '<li>Hebreo: Texto "PROGRAMAS_HE" + Clase CSS "adc-programs-menu-trigger-he"</li>';
        echo '</ul>';

        echo '<h4>Para BUSCADOR:</h4>';
        echo '<ul>';
        echo '<li>Español: Texto "BUSCADOR_ES" + Clase CSS "adc-search-menu-trigger"</li>';
        echo '<li>Inglés: Texto "BUSCADOR_EN" + Clase CSS "adc-search-menu-trigger-en"</li>';
        echo '<li>Hebreo: Texto "BUSCADOR_HE" + Clase CSS "adc-search-menu-trigger-he"</li>';
        echo '</ul>';

        echo '<h3>URLs del Sistema:</h3>';
        echo '<ul>';
        echo '<li>Español: <code>https://tuia.tv/</code></li>';
        echo '<li>Inglés: <code>https://tuia.tv/en/</code></li>';
        echo '<li>Hebreo: <code>https://tuia.tv/he/</code></li>';
        echo '</ul>';

        echo '<h3>Estructura de URLs:</h3>';
        echo '<ul>';
        echo '<li>Listado de programas: <code>/?</code> o <code>/en/?</code> o <code>/he/?</code></li>';
        echo '<li>Ver programa: <code>/?categoria=nombre-programa</code></li>';
        echo '<li>Ver video: <code>/?categoria=nombre-programa&video=nombre-video</code></li>';
        echo '<li>Búsqueda: <code>/?adc_search=término</code></li>';
        echo '</ul>';

        echo '</div>';
    }

    private function render_sortable_programs($programs, $language)
    {
        echo '<div id="program-order-container">';
        echo '<ul id="sortable-programs" class="programs-order-list" data-language="' . esc_attr($language) . '">';

        foreach ($programs as $program) {
            echo '<li class="program-item" data-id="' . esc_attr($program['id']) . '">';
            echo '<div class="program-handle dashicons dashicons-move"></div>';

            if (isset($program['cover'])) {
                echo '<img src="' . esc_url($program['cover']) . '" alt="' . esc_attr($program['name']) . '" class="program-thumbnail">';
            } else {
                echo '<div class="program-thumbnail-placeholder"></div>';
            }

            echo '<span class="program-name">' . esc_html($program['name']) . '</span>';
            echo '<span class="program-id">(ID: ' . esc_html($program['id']) . ')</span>';
            echo '</li>';
        }

        echo '</ul>';
        echo '</div>';

        echo '<div id="order-save-status" class="hidden">';
        echo '<div class="spinner is-active"></div>';
        echo '<span class="message">Guardando...</span>';
        echo '</div>';

        $this->render_sortable_script($language);
    }

    private function render_sortable_script($language)
    {
        echo '<script>
        jQuery(document).ready(function($) {
            $("#sortable-programs").sortable({
                handle: ".program-handle",
                update: function(event, ui) {
                    $("#order-save-status").removeClass("hidden").removeClass("success").removeClass("error");
                    $("#order-save-status .message").text("Guardando...");
                    
                    var programOrder = [];
                    $(".program-item").each(function() {
                        programOrder.push($(this).data("id"));
                    });
                    
                    $.ajax({
                        url: ajaxurl,
                        type: "POST",
                        data: {
                            action: "adc_update_program_order",
                            program_order: programOrder,
                            language: "' . esc_js($language) . '",
                            nonce: "' . wp_create_nonce('adc_admin_nonce') . '"
                        },
                        success: function(response) {
                            if (response.success) {
                                $("#order-save-status").addClass("success");
                                $("#order-save-status .message").text("¡Orden guardado exitosamente!");
                                
                                setTimeout(function() {
                                    $("#order-save-status").addClass("hidden");
                                }, 3000);
                            } else {
                                $("#order-save-status").addClass("error");
                                $("#order-save-status .message").text("Error al guardar orden.");
                            }
                        },
                        error: function() {
                            $("#order-save-status").addClass("error");
                            $("#order-save-status .message").text("Error al guardar orden.");
                        }
                    });
                }
            });
        });
        </script>';
    }

    private function apply_saved_order($programs, $saved_order)
    {
        $programs_lookup = array();
        foreach ($programs as $program) {
            $programs_lookup[$program['id']] = $program;
        }

        $ordered_programs = array();

        // Add programs according to saved order
        foreach ($saved_order as $program_id) {
            if (isset($programs_lookup[$program_id])) {
                $ordered_programs[] = $programs_lookup[$program_id];
                unset($programs_lookup[$program_id]);
            }
        }

        // Add remaining programs
        foreach ($programs_lookup as $program) {
            $ordered_programs[] = $program;
        }

        return $ordered_programs;
    }

    /**
     * Test API connection
     */
    private function test_api_connection($api)
    {
        if (!$api->is_configured()) {
            return array(
                'connection' => false,
                'error' => 'API no configurada - Token o URL faltante'
            );
        }

        $result = $api->test_connection();

        // Add programs list for admin display
        if ($result['success']) {
            $programs = $api->get_programs();
            $result['programs'] = $programs;
        }

        return array(
            'connection' => $result['success'],
            'error' => isset($result['error']) ? $result['error'] : null,
            'programs_count' => isset($result['programs_count']) ? $result['programs_count'] : 0,
            'programs' => isset($result['programs']) ? $result['programs'] : array()
        );
    }

    /**
     * Get plugin options
     */
    public static function get_options()
    {
        return get_option('adc-video-display');
    }
}

// Initialize admin if in admin area
if (is_admin()) {
    new ADC_Admin();
}