<?php
/**
 * ADC Video Display - Admin Settings
 * Version: 3.0 - Multiidioma con caché optimizado y mejoras UX
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
        add_action('wp_ajax_adc_clear_cache', array($this, 'ajax_clear_cache'));
        add_action('wp_ajax_adc_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_adc_health_check', array($this, 'ajax_health_check'));
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

        // Localize for inline scripts
        wp_localize_script('jquery', 'adc_admin_config', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('adc_admin_nonce'),
            'strings' => array(
                'clearing_cache' => 'Limpiando caché...',
                'cache_cleared' => 'Caché limpiado exitosamente',
                'cache_error' => 'Error al limpiar caché',
                'testing_connection' => 'Probando conexión...',
                'connection_success' => 'Conexión exitosa',
                'connection_error' => 'Error de conexión',
                'health_checking' => 'Verificando estado del sistema...',
                'health_success' => 'Sistema funcionando correctamente',
                'health_error' => 'Se detectaron problemas en el sistema'
            )
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
     * AJAX handler to clear cache
     */
    public function ajax_clear_cache()
    {
        check_ajax_referer('adc_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $cache_type = isset($_POST['cache_type']) ? sanitize_text_field($_POST['cache_type']) : 'all';

        try {
            // Clear cache based on type
            foreach ($this->languages as $lang) {
                $api = new ADC_API($lang);
                
                switch ($cache_type) {
                    case 'all':
                        $api->clear_all_cache();
                        break;
                    case 'programs':
                        $api->refresh_cache('programs');
                        break;
                    case 'materials':
                        $api->refresh_cache('materials');
                        break;
                    case 'search':
                        $api->refresh_cache('search');
                        break;
                    case 'menu':
                        $api->refresh_cache('menu');
                        break;
                    default:
                        $api->clear_all_cache();
                }
            }

            wp_send_json_success(array(
                'message' => 'Cache cleared successfully',
                'type' => $cache_type,
                'timestamp' => current_time('mysql')
            ));

        } catch (Exception $e) {
            wp_send_json_error('Error clearing cache: ' . $e->getMessage());
        }
    }

    /**
     * AJAX handler to test API connection
     */
    public function ajax_test_connection()
    {
        check_ajax_referer('adc_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $language = isset($_POST['language']) ? sanitize_text_field($_POST['language']) : 'es';

        if (!in_array($language, $this->languages)) {
            wp_send_json_error('Invalid language');
            return;
        }

        $api = new ADC_API($language);
        $result = $api->test_connection();

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX handler for health check
     */
    public function ajax_health_check()
    {
        check_ajax_referer('adc_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        try {
            $health_results = array();
            
            foreach ($this->languages as $lang) {
                $api = new ADC_API($lang);
                $health_results[$lang] = $api->health_check();
            }

            wp_send_json_success($health_results);

        } catch (Exception $e) {
            wp_send_json_error('Error performing health check: ' . $e->getMessage());
        }
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
        if (!isset($_GET['page']) || !$this->is_adc_admin_page($_GET['page'])) {
            return;
        }

        if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
            echo '<div class="notice notice-success is-dismissible"><p>¡Configuración guardada exitosamente!</p></div>';
        }

        if (isset($_GET['order-updated']) && $_GET['order-updated']) {
            echo '<div class="notice notice-success is-dismissible"><p>¡Orden de programas actualizado exitosamente!</p></div>';
        }

        if (isset($_GET['cache-cleared']) && $_GET['cache-cleared']) {
            echo '<div class="notice notice-success is-dismissible"><p>¡Caché limpiado exitosamente!</p></div>';
        }
    }

    /**
     * Display the settings page - SIMPLIFICADO
     */
    public function display_settings_page()
    {
        // Test API connection for each language
        $api_status = array();
        $cache_stats = array();
        
        foreach ($this->languages as $lang) {
            $api = new ADC_API($lang);
            $api_status[$lang] = $this->test_api_connection($api);
            $cache_stats[$lang] = $api->get_cache_stats();
        }

        echo '<div class="wrap">';
        echo '<h1>ADC Video Display - Configuración</h1>';

        // Add cache management section
        echo '<div class="card" style="margin-bottom: 20px;">';
        echo '<h2>Gestión de Caché y Sistema</h2>';
        
        // Cache controls
        echo '<div style="margin-bottom: 15px;">';
        echo '<button id="adc-clear-all-cache" class="button button-secondary" data-cache-type="all">Limpiar Todo el Caché</button> ';
        echo '<button id="adc-clear-programs-cache" class="button button-secondary" data-cache-type="programs">Limpiar Caché de Programas</button> ';
        echo '<button id="adc-clear-search-cache" class="button button-secondary" data-cache-type="search">Limpiar Caché de Búsquedas</button> ';
        echo '<button id="adc-health-check" class="button button-secondary">Verificar Estado del Sistema</button>';
        echo '</div>';

        // Cache statistics
        echo '<div id="cache-stats">';
        echo '<h3>Estadísticas de Caché</h3>';
        echo '<table class="widefat" style="max-width: 600px;">';
        echo '<thead><tr><th>Idioma</th><th>Entradas</th><th>Tamaño (KB)</th><th>Ambiente</th></tr></thead>';
        echo '<tbody>';
        foreach ($cache_stats as $lang => $stats) {
            echo '<tr>';
            echo '<td>' . strtoupper($lang) . '</td>';
            echo '<td>' . $stats['transient_count'] . '</td>';
            echo '<td>' . $stats['cache_size_kb'] . '</td>';
            echo '<td><span class="' . ($stats['environment'] === 'development' ? 'adc-dev-badge' : 'adc-prod-badge') . '">' . ucfirst($stats['environment']) . '</span></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</div>';

        echo '<div id="adc-cache-status" style="margin-top: 15px;"></div>';
        echo '</div>';

        // Show API status for all languages
        echo '<div class="card">';
        echo '<h2>Estado de Conexión API por Idioma</h2>';
        echo '<div id="api-status-container">';
        foreach ($api_status as $lang => $status) {
            $this->render_api_status($status, $lang);
        }
        echo '</div>';
        
        // Test connection buttons
        echo '<div style="margin-top: 15px;">';
        foreach ($this->languages as $lang) {
            echo '<button class="button button-secondary adc-test-connection" data-language="' . $lang . '" style="margin-right: 10px;">Probar ' . strtoupper($lang) . '</button>';
        }
        echo '</div>';
        
        echo '<div id="adc-connection-status" style="margin-top: 15px;"></div>';
        echo '</div>';

        echo '<form method="post" action="options.php">';
        settings_fields($this->plugin_name . '_group');
        do_settings_sections($this->plugin_name);
        submit_button();
        echo '</form>';

        $this->render_usage_info();

        // Add inline CSS and JavaScript
        $this->render_admin_styles_and_scripts();

        echo '</div>';
    }

    /**
     * Render admin styles and scripts inline
     */
    private function render_admin_styles_and_scripts()
    {
        echo '<style>
        .adc-dev-badge { background: #ff9800; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px; }
        .adc-prod-badge { background: #4caf50; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px; }
        .adc-status-healthy { color: #4caf50; font-weight: bold; }
        .adc-status-unhealthy { color: #f44336; font-weight: bold; }
        .adc-status-degraded { color: #ff9800; font-weight: bold; }
        #adc-cache-status, #adc-connection-status { padding: 10px; border-radius: 4px; display: none; }
        #adc-cache-status.success, #adc-connection-status.success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        #adc-cache-status.error, #adc-connection-status.error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        #adc-cache-status.loading, #adc-connection-status.loading { background: #cce7ff; border: 1px solid #99d6ff; color: #0066cc; }
        </style>';

        echo '<script>
        jQuery(document).ready(function($) {
            // Clear cache handlers
            $("[id^=adc-clear-]").on("click", function(e) {
                e.preventDefault();
                var button = $(this);
                var cacheType = button.data("cache-type");
                var originalText = button.text();
                
                button.prop("disabled", true).text("Limpiando...");
                $("#adc-cache-status").removeClass("success error").addClass("loading").text("Limpiando caché...").show();
                
                $.ajax({
                    url: ajaxurl,
                    type: "POST",
                    data: {
                        action: "adc_clear_cache",
                        cache_type: cacheType,
                        nonce: "' . wp_create_nonce('adc_admin_nonce') . '"
                    },
                    success: function(response) {
                        if (response.success) {
                            $("#adc-cache-status").removeClass("loading error").addClass("success").text("Caché limpiado exitosamente");
                            setTimeout(function() { location.reload(); }, 1500);
                        } else {
                            $("#adc-cache-status").removeClass("loading success").addClass("error").text("Error: " + response.data);
                        }
                    },
                    error: function() {
                        $("#adc-cache-status").removeClass("loading success").addClass("error").text("Error de conexión");
                    },
                    complete: function() {
                        button.prop("disabled", false).text(originalText);
                    }
                });
            });
            
            // Test connection handlers
            $(".adc-test-connection").on("click", function(e) {
                e.preventDefault();
                var button = $(this);
                var language = button.data("language");
                var originalText = button.text();
                
                button.prop("disabled", true).text("Probando...");
                $("#adc-connection-status").removeClass("success error").addClass("loading").text("Probando conexión para " + language.toUpperCase() + "...").show();
                
                $.ajax({
                    url: ajaxurl,
                    type: "POST",
                    data: {
                        action: "adc_test_connection",
                        language: language,
                        nonce: "' . wp_create_nonce('adc_admin_nonce') . '"
                    },
                    success: function(response) {
                        if (response.success) {
                            $("#adc-connection-status").removeClass("loading error").addClass("success").text("Conexión exitosa para " + language.toUpperCase() + " - " + response.data.programs_count + " programas encontrados");
                        } else {
                            $("#adc-connection-status").removeClass("loading success").addClass("error").text("Error en " + language.toUpperCase() + ": " + response.data.error);
                        }
                    },
                    error: function() {
                        $("#adc-connection-status").removeClass("loading success").addClass("error").text("Error de conexión para " + language.toUpperCase());
                    },
                    complete: function() {
                        button.prop("disabled", false).text(originalText);
                    }
                });
            });
            
            // Health check handler
            $("#adc-health-check").on("click", function(e) {
                e.preventDefault();
                var button = $(this);
                var originalText = button.text();
                
                button.prop("disabled", true).text("Verificando...");
                $("#adc-connection-status").removeClass("success error").addClass("loading").text("Realizando verificación completa del sistema...").show();
                
                $.ajax({
                    url: ajaxurl,
                    type: "POST",
                    data: {
                        action: "adc_health_check",
                        nonce: "' . wp_create_nonce('adc_admin_nonce') . '"
                    },
                    success: function(response) {
                        if (response.success) {
                            var healthData = response.data;
                            var message = "Health Check completado:<br>";
                            
                            for (var lang in healthData) {
                                var health = healthData[lang];
                                message += lang.toUpperCase() + ": " + health.overall + "<br>";
                            }
                            
                            $("#adc-connection-status").removeClass("loading error").addClass("success").html(message);
                        } else {
                            $("#adc-connection-status").removeClass("loading success").addClass("error").text("Error en health check: " + response.data);
                        }
                    },
                    error: function() {
                        $("#adc-connection-status").removeClass("loading success").addClass("error").text("Error de conexión en health check");
                    },
                    complete: function() {
                        button.prop("disabled", false).text(originalText);
                    }
                });
            });
        });
        </script>';
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

        $status_class = $api_status['connection'] ? 'adc-status-healthy' : 'adc-status-unhealthy';
        $status_icon = $api_status['connection'] ? '✓' : '✗';

        echo '<div style="margin-bottom: 20px; padding: 15px; border-left: 4px solid ' . ($api_status['connection'] ? '#46b450' : '#dc3232') . '; background: ' . ($api_status['connection'] ? '#f0f8f0' : '#fdf0f0') . ';">';
        echo '<h3 style="margin-top: 0; display: flex; align-items: center;"><span class="' . $status_class . '">' . $status_icon . '</span> <span style="margin-left: 8px;">' . $language_names[$language] . '</span></h3>';

        if ($api_status['connection']) {
            echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin-top: 10px;">';
            
            if (isset($api_status['programs_count'])) {
                echo '<div><strong>Programas:</strong> ' . $api_status['programs_count'] . '</div>';
            }
            if (isset($api_status['materials_count'])) {
                echo '<div><strong>Videos:</strong> ' . $api_status['materials_count'] . '</div>';
            }
            if (isset($api_status['response_time'])) {
                echo '<div><strong>Tiempo:</strong> ' . $api_status['response_time'] . 'ms</div>';
            }
            if (isset($api_status['cache_time'])) {
                echo '<div><strong>Caché:</strong> ' . round($api_status['cache_time'] / 60) . ' min</div>';
            }
            
            echo '</div>';
        } else {
            echo '<p><strong>Error:</strong> ' . esc_html($api_status['error']) . '</p>';
            if (isset($api_status['error_type'])) {
                echo '<p><strong>Tipo:</strong> ' . esc_html($api_status['error_type']) . '</p>';
            }
        }
        echo '</div>';
    }

    private function render_usage_info()
    {
        echo '<div class="card" style="margin-top: 30px;">';
        echo '<h2>Información de Uso</h2>';

        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">';
        
        // Shortcodes
        echo '<div>';
        echo '<h3>Shortcodes disponibles:</h3>';
        echo '<ul>';
        echo '<li><code>[adc_content]</code> - Muestra el contenido en Español</li>';
        echo '<li><code>[adc_content_en]</code> - Muestra el contenido en Inglés</li>';
        echo '<li><code>[adc_content_he]</code> - Muestra el contenido en Hebreo</li>';
        echo '</ul>';
        echo '</div>';

        // Menu configuration
        echo '<div>';
        echo '<h3>Configuración de Menús:</h3>';
        echo '<h4>Para PROGRAMAS:</h4>';
        echo '<ul style="font-size: 12px;">';
        echo '<li>Español: Texto "PROGRAMAS_ES" + Clase "adc-programs-menu-trigger"</li>';
        echo '<li>Inglés: Texto "PROGRAMAS_EN" + Clase "adc-programs-menu-trigger-en"</li>';
        echo '<li>Hebreo: Texto "PROGRAMAS_HE" + Clase "adc-programs-menu-trigger-he"</li>';
        echo '</ul>';

        echo '<h4>Para BUSCADOR:</h4>';
        echo '<ul style="font-size: 12px;">';
        echo '<li>Español: Texto "BUSCADOR_ES" + Clase "adc-search-menu-trigger"</li>';
        echo '<li>Inglés: Texto "BUSCADOR_EN" + Clase "adc-search-menu-trigger-en"</li>';
        echo '<li>Hebreo: Texto "BUSCADOR_HE" + Clase "adc-search-menu-trigger-he"</li>';
        echo '</ul>';
        echo '</div>';

        echo '</div>';

        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">';
        
        // URLs
        echo '<div>';
        echo '<h3>URLs del Sistema:</h3>';
        echo '<ul>';
        echo '<li>Español: <code>https://tuia.tv/</code></li>';
        echo '<li>Inglés: <code>https://tuia.tv/en/</code></li>';
        echo '<li>Hebreo: <code>https://tuia.tv/he/</code></li>';
        echo '</ul>';
        echo '</div>';

        // URL Structure
        echo '<div>';
        echo '<h3>Estructura de URLs:</h3>';
        echo '<ul style="font-size: 12px;">';
        echo '<li>Listado: <code>/?</code> o <code>/en/?</code> o <code>/he/?</code></li>';
        echo '<li>Programa: <code>/?categoria=nombre-programa</code></li>';
        echo '<li>Video: <code>/?categoria=programa&video=nombre-video</code></li>';
        echo '<li>Búsqueda: <code>/?adc_search=término</code></li>';
        echo '</ul>';
        echo '</div>';

        echo '</div>';

        // Clip promocional info
        echo '<div style="background: #e8f4fd; padding: 15px; border-left: 4px solid #2196f3; margin-top: 15px;">';
        echo '<h3 style="margin-top: 0; color: #1976d2;"><span style="font-size: 18px;">🎬</span> Clip Promocional</h3>';
        echo '<p><strong>¡Nuevo!</strong> El plugin ahora soporta clips promocionales de programas:</p>';
        echo '<ul>';
        echo '<li>Se muestran automáticamente cuando el campo <code>clip</code> está disponible en la API</li>';
        echo '<li>Aparecen en la página del programa antes de los videos de temporadas</li>';
        echo '<li>Funcionan para los 3 idiomas (ES, EN, HE)</li>';
        echo '<li>Incluyen reproductor Video.js integrado</li>';
        echo '</ul>';
        echo '<p><em>Nota: Los clips se configuran en el sistema ADC y aparecerán automáticamente cuando estén listos.</em></p>';
        echo '</div>';

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
                'error' => 'API no configurada - Token o URL faltante',
                'error_type' => 'configuration'
            );
        }

        $result = $api->test_connection();

        return array(
            'connection' => $result['success'],
            'error' => isset($result['error']) ? $result['error'] : null,
            'error_type' => isset($result['error_type']) ? $result['error_type'] : null,
            'programs_count' => isset($result['programs_count']) ? $result['programs_count'] : 0,
            'materials_count' => isset($result['materials_count']) ? $result['materials_count'] : 0,
            'response_time' => isset($result['response_time']) ? $result['response_time'] : null,
            'cache_time' => isset($result['cache_time']) ? $result['cache_time'] : null
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