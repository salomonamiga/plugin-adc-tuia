<?php
/**
 * ADC Video Display - Admin Settings
 * Version: 3.3 - Interfaz Moderna y Organizada
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
    private $languages;

    public function __construct()
    {
        $this->options = get_option($this->plugin_name);
        $this->languages = ADC_Utils::get_valid_languages();

        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_notices', array($this, 'admin_notices'));

        // Add scripts and styles for admin
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // AJAX handlers
        add_action('wp_ajax_adc_update_program_order', array($this, 'ajax_update_program_order'));
        add_action('wp_ajax_adc_clear_all_cache', array($this, 'ajax_clear_all_cache'));
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
            '4.0'
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
            'adc-videos_page_' . $this->plugin_name . '-order-en'
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
        $language = isset($_POST['language']) ? ADC_Utils::validate_language($_POST['language']) : 'es';

        // Sanitize - ensure we only have integers
        $sanitized_order = array_map('intval', $program_order);

        // Save order for specific language
        update_option('adc_programs_order_' . $language, $sanitized_order);

        wp_send_json_success('Order updated successfully');
    }

    /**
     * AJAX handler to clear ALL cache
     */
    public function ajax_clear_all_cache()
    {
        check_ajax_referer('adc_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        try {
            // Clear cache for all languages
            foreach ($this->languages as $lang) {
                $api = new ADC_API($lang);
                $api->clear_all_cache();
            }

            // Clear any WordPress object cache
            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }

            wp_send_json_success(array(
                'message' => 'Todo el caché ha sido limpiado exitosamente',
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

        $language = isset($_POST['language']) ? ADC_Utils::validate_language($_POST['language']) : 'es';

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

        // Add submenu for program ordering - one for each language (only ES and EN)
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
        $this->register_cache_settings();
        $this->register_display_settings();
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
     * Register cache settings section
     */
    private function register_cache_settings()
    {
        add_settings_section(
            'cache_settings',
            'Sistema de Caché',
            array($this, 'cache_settings_section_callback'),
            $this->plugin_name
        );

        add_settings_field('enable_cache', 'Activar Caché', array($this, 'enable_cache_callback'), $this->plugin_name, 'cache_settings');
        add_settings_field('cache_duration', 'Duración del Caché', array($this, 'cache_duration_callback'), $this->plugin_name, 'cache_settings');
        add_settings_field('debug_mode', 'Modo Debug', array($this, 'debug_mode_callback'), $this->plugin_name, 'cache_settings');
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
        add_settings_field('related_videos_count', 'Videos Relacionados', array($this, 'related_videos_count_callback'), $this->plugin_name, 'display_settings');
        add_settings_field('enable_autoplay', 'Autoplay', array($this, 'enable_autoplay_callback'), $this->plugin_name, 'display_settings');
        add_settings_field('autoplay_countdown', 'Segundos Autoplay', array($this, 'autoplay_countdown_callback'), $this->plugin_name, 'display_settings');
    }

    /**
     * Register advanced settings section (webhook)
     */
    private function register_advanced_settings()
    {
        add_settings_section(
            'webhook_settings',
            'Webhook Automático',
            array($this, 'webhook_settings_section_callback'),
            $this->plugin_name
        );

        add_settings_field('webhook_token', 'Token del Webhook', array($this, 'webhook_token_callback'), $this->plugin_name, 'webhook_settings');
        add_settings_field('webhook_url', 'URL del Webhook', array($this, 'webhook_url_callback'), $this->plugin_name, 'webhook_settings');
    }

    /**
     * Section callbacks
     */
    public function api_settings_section_callback()
    {
        echo '<p>Configura los datos de conexión a la API de TuTorah TV.</p>';
    }

    public function cache_settings_section_callback()
    {
        echo '<p>Sistema de caché inteligente para optimizar el rendimiento del sitio.</p>';
    }

    public function display_settings_section_callback()
    {
        echo '<p>Configura las opciones de visualización y reproducción del contenido.</p>';
    }

    public function webhook_settings_section_callback()
    {
        echo '<p>Webhook automático para limpieza de caché cuando ADC sincroniza contenido nuevo.</p>';
    }

    /**
     * Field callbacks - API
     */
    public function api_token_callback()
    {
        $this->render_text_field('api_token', 'Tu token de API', 'El token de autenticación para la API.');
    }

    public function api_url_callback()
    {
        $this->render_url_field('api_url', 'https://api.tutorah.tv/v1', 'URL base de la API (sin slash final).');
    }

    /**
     * Field callbacks - CACHE
     */
    public function enable_cache_callback()
    {
        $this->render_checkbox_field('enable_cache', 'Activar sistema de caché (recomendado para mejor rendimiento)');
    }

    public function cache_duration_callback()
    {
        $value = isset($this->options['cache_duration']) ? $this->options['cache_duration'] : '6';
        $options = array(
            '0.5' => '30 minutos',
            '1' => '1 hora',
            '3' => '3 horas',
            '6' => '6 horas (recomendado)',
            '12' => '12 horas',
            '24' => '24 horas'
        );

        echo '<select name="' . $this->plugin_name . '[cache_duration]">';
        foreach ($options as $hours => $label) {
            echo '<option value="' . $hours . '"' . selected($value, $hours, false) . '>' . $label . '</option>';
        }
        echo '</select>';
        echo '<p class="description">Tiempo que los datos se mantienen en caché antes de actualizarse.</p>';
    }

    public function debug_mode_callback()
    {
        $this->render_checkbox_field('debug_mode', 'Activar modo debug (solo para desarrollo - muestra información en consola)');
    }

    /**
     * Field callbacks - Display
     */
    public function videos_per_row_callback()
    {
        $value = isset($this->options['videos_per_row']) ? $this->options['videos_per_row'] : '4';
        echo '<select name="' . $this->plugin_name . '[videos_per_row]">';
        for ($i = 3; $i <= 6; $i++) {
            echo '<option value="' . $i . '"' . selected($value, $i, false) . '>' . $i . ' videos</option>';
        }
        echo '</select>';
        echo '<p class="description">Número de videos a mostrar por fila en desktop.</p>';
    }

    public function related_videos_count_callback()
    {
        $this->render_number_field('related_videos_count', 4, 20, 'Cantidad de videos relacionados a mostrar (4-20).');
    }

    public function enable_autoplay_callback()
    {
        $this->render_checkbox_field('enable_autoplay', 'Activar reproducción automática del siguiente video');
    }

    public function autoplay_countdown_callback()
    {
        $this->render_number_field('autoplay_countdown', 3, 30, 'Segundos antes de reproducir el siguiente video (3-30).');
    }

    /**
     * Field callbacks - Webhook
     */
    public function webhook_token_callback()
    {
        // Asegurar que siempre hay un token
        $token = isset($this->options['webhook_token']) ? $this->options['webhook_token'] : '';

        if (empty($token)) {
            $token = $this->generate_secure_token();
            $current_options = get_option($this->plugin_name, array());
            $current_options['webhook_token'] = $token;
            update_option($this->plugin_name, $current_options);
            // Actualizar la instancia actual para mostrar el token inmediatamente
            $this->options['webhook_token'] = $token;
        }

        echo '<div style="margin-bottom: 15px;">';
        echo '<div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">';
        echo '<input type="text" id="adc-current-token" value="' . esc_attr($token) . '" class="regular-text" readonly style="background: #f9f9f9; font-family: monospace; font-size: 12px;">';
        echo '<button type="button" id="adc-copy-token" class="button button-small">📋 Copiar</button>';
        echo '</div>';
        echo '<p class="description">Token de seguridad permanente para el webhook automático. <strong>No necesita cambios.</strong></p>';
        echo '</div>';

        // Información sobre el webhook automático
        echo '<div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 12px; border-radius: 4px; margin-top: 10px;">';
        echo '<h4 style="margin-top: 0; color: #155724;">✅ Webhook Automático Configurado</h4>';
        echo '<ul style="margin-bottom: 0; color: #155724; font-size: 13px;">';
        echo '<li><strong>Funcionamiento:</strong> ADC llama automáticamente cuando sincroniza videos de IA</li>';
        echo '<li><strong>Filtro inteligente:</strong> Solo se activa para secciones IA (Español/Inglés)</li>';
        echo '<li><strong>Sin intervención:</strong> El caché se limpia automáticamente cuando es necesario</li>';
        echo '<li><strong>Seguridad:</strong> Token permanente protege el webhook</li>';
        echo '</ul>';
        echo '</div>';
    }

    public function webhook_url_callback()
    {
        // Asegurar que siempre hay un token y mostrar URL inmediatamente
        $token = isset($this->options['webhook_token']) ? $this->options['webhook_token'] : '';
        
        if (empty($token)) {
            $token = $this->generate_secure_token();
            $current_options = get_option($this->plugin_name, array());
            $current_options['webhook_token'] = $token;
            update_option($this->plugin_name, $current_options);
            $this->options['webhook_token'] = $token;
        }
        
        $webhook_url = $this->get_webhook_url($token);

        echo '<div style="background: #f0f8f0; padding: 15px; border: 1px solid #46b450; border-radius: 4px; margin-bottom: 15px;">';
        echo '<h4 style="margin-top: 0; color: #46b450;">🔗 URL del Webhook</h4>';
        echo '<textarea id="adc-webhook-url" class="large-text" readonly style="background: white; margin-bottom: 10px; font-family: monospace; font-size: 11px; height: 60px; resize: vertical;">' . esc_attr($webhook_url) . '</textarea>';
        echo '<button type="button" id="adc-copy-webhook" class="button button-small">📋 Copiar URL</button>';
        echo '<p style="margin: 10px 0 0 0;"><strong>Estado:</strong> Esta URL está configurada automáticamente en el sistema ADC.</p>';
        echo '</div>';
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

        // Cache Settings
        $sanitized['enable_cache'] = isset($input['enable_cache']) ? '1' : '0';
        $sanitized['cache_duration'] = isset($input['cache_duration']) && in_array($input['cache_duration'], array('0.5', '1', '3', '6', '12', '24')) ? $input['cache_duration'] : '6';
        $sanitized['debug_mode'] = isset($input['debug_mode']) ? '1' : '0';
        
        // CRÍTICO: Preservar el token webhook existente (no viene en el formulario)
        $current_options = get_option($this->plugin_name, array());
        $sanitized['webhook_token'] = isset($current_options['webhook_token']) ? $current_options['webhook_token'] : '';

        // Display Settings
        $sanitized['videos_per_row'] = isset($input['videos_per_row']) && in_array($input['videos_per_row'], array('3', '4', '5', '6')) ? $input['videos_per_row'] : '4';
        $sanitized['related_videos_count'] = isset($input['related_videos_count']) ? max(4, min(20, intval($input['related_videos_count']))) : 8;
        $sanitized['enable_autoplay'] = isset($input['enable_autoplay']) ? '1' : '0';
        $sanitized['autoplay_countdown'] = isset($input['autoplay_countdown']) ? max(3, min(30, intval($input['autoplay_countdown']))) : 5;

        // Legacy settings (mantener compatibilidad)
        $sanitized['enable_search'] = '1'; // Siempre activo

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
     * Display the settings page - NUEVA INTERFAZ MODERNA
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

        echo '<div class="wrap adc-admin-wrap">';
        
        // Header moderno
        echo '<div class="adc-admin-header">';
        echo '<h1>🎬 ADC Video Display</h1>';
        echo '<p>Sistema de gestión de videos multiidioma con URLs amigables v4.0</p>';
        echo '</div>';

        echo '<div class="adc-admin-content">';

        // Save Button Top
        echo '<div class="adc-save-container">';
        echo '<button type="submit" form="adc-settings-form" class="button button-primary adc-save-button">💾 Guardar Configuración</button>';
        echo '</div>';

        // Form start
        echo '<form method="post" action="options.php" id="adc-settings-form">';
        settings_fields($this->plugin_name . '_group');

        // SECCIÓN 1: Configuración Esencial (Grid 2x2)
        echo '<div class="adc-section">';
        echo '<div class="adc-section-header">';
        echo '<h2><span class="adc-icon">⚙️</span> Configuración Esencial</h2>';
        echo '</div>';

        echo '<div class="adc-grid-container">';
        
        // API Configuration
        echo '<div class="adc-card">';
        echo '<h3><span class="adc-icon">🔌</span> API</h3>';
        echo '<div class="adc-form-fields">';
        $this->api_token_callback();
        $this->api_url_callback();
        echo '</div>';
        echo '</div>';

        // Cache Configuration
        echo '<div class="adc-card">';
        echo '<h3><span class="adc-icon">⚡</span> Caché</h3>';
        echo '<div class="adc-form-fields">';
        $this->enable_cache_callback();
        $this->cache_duration_callback();
        $this->debug_mode_callback();
        echo '</div>';
        echo '</div>';

        // Display Configuration (spans 2 columns)
        echo '<div class="adc-card adc-card-wide">';
        echo '<h3><span class="adc-icon">🎨</span> Visualización</h3>';
        echo '<div class="adc-form-fields adc-form-grid">';
        echo '<div>';
        $this->videos_per_row_callback();
        $this->related_videos_count_callback();
        echo '</div>';
        echo '<div>';
        $this->enable_autoplay_callback();
        $this->autoplay_countdown_callback();
        echo '</div>';
        echo '</div>';
        echo '</div>';

        echo '</div>'; // Close grid container
        echo '</div>'; // Close section

        // SECCIÓN 2: Estado del Sistema
        echo '<div class="adc-section">';
        echo '<div class="adc-section-header">';
        echo '<h2><span class="adc-icon">📊</span> Estado del Sistema</h2>';
        echo '</div>';

        echo '<div class="adc-status-grid">';
        
        // API Status
        echo '<div class="adc-card">';
        echo '<h3><span class="adc-icon">📡</span> Conexión API</h3>';
        echo '<div id="api-status-container">';
        foreach ($api_status as $lang => $status) {
            $this->render_api_status($status, $lang);
        }
        echo '</div>';
        echo '<div class="adc-quick-actions">';
        foreach ($this->languages as $lang) {
            echo '<button type="button" class="button button-secondary adc-test-connection" data-language="' . $lang . '">Probar ' . strtoupper($lang) . '</button>';
        }
        echo '</div>';
        echo '<div id="adc-connection-status" class="adc-status-message"></div>';
        echo '</div>';

        // Cache Stats
        echo '<div class="adc-card">';
        echo '<h3><span class="adc-icon">💾</span> Estadísticas de Caché</h3>';
        echo '<div class="adc-stats-container">';
        $total_entries = 0;
        $total_size = 0;
        foreach ($cache_stats as $lang => $stats) {
            $total_entries += $stats['transient_count'];
            $total_size += $stats['cache_size_kb'];
        }
        echo '<div class="adc-stat-item">';
        echo '<span class="adc-stat-number">' . $total_entries . '</span>';
        echo '<span class="adc-stat-label">Entradas</span>';
        echo '</div>';
        echo '<div class="adc-stat-item">';
        echo '<span class="adc-stat-number">' . round($total_size, 1) . 'KB</span>';
        echo '<span class="adc-stat-label">Tamaño</span>';
        echo '</div>';
        echo '<div class="adc-stat-item">';
        $cache_duration = isset($this->options['cache_duration']) ? $this->options['cache_duration'] : '6';
        echo '<span class="adc-stat-number">' . $cache_duration . 'h</span>';
        echo '<span class="adc-stat-label">Duración</span>';
        echo '</div>';
        echo '</div>';
        echo '<div class="adc-quick-actions">';
        echo '<button type="button" id="adc-clear-all-cache" class="button button-secondary adc-clear-cache">🗑️ Limpiar Caché</button>';
        echo '</div>';
        echo '<div id="adc-cache-status" class="adc-status-message"></div>';
        echo '</div>';

        echo '</div>'; // Close status grid
        echo '</div>'; // Close section

        // SECCIÓN 3: Webhook
        echo '<div class="adc-section">';
        echo '<div class="adc-section-header">';
        echo '<h2><span class="adc-icon">🤖</span> Webhook Automático</h2>';
        echo '</div>';
        echo '<div class="adc-webhook-container">';
        $this->webhook_token_callback();
        $this->webhook_url_callback();
        echo '</div>';
        echo '</div>';

        // SECCIÓN 4: Información de Uso
        echo '<div class="adc-section">';
        echo '<div class="adc-section-header">';
        echo '<h2><span class="adc-icon">📚</span> Información de Uso</h2>';
        echo '</div>';
        echo '<div class="adc-usage-container">';
        $this->render_usage_info_modern();
        echo '</div>';
        echo '</div>';

        echo '</form>';

        // Save Button Bottom
        echo '<div class="adc-save-container">';
        echo '<button type="submit" form="adc-settings-form" class="button button-primary adc-save-button">💾 Guardar Configuración</button>';
        echo '</div>';

        echo '</div>'; // Close content

        // Add modern styles and JavaScript
        $this->render_modern_admin_styles_and_scripts();

        echo '</div>'; // Close wrap
    }

    /**
     * Render modern usage information
     */
    private function render_usage_info_modern()
    {
        echo '<div class="adc-usage-highlight">';
        echo '<h4>🚀 URLs Amigables Implementadas</h4>';
        echo '<p><strong>¡Sistema actualizado!</strong> Ahora usa URLs optimizadas para SEO con redirecciones inteligentes por idioma.</p>';
        echo '</div>';

        echo '<div class="adc-usage-grid">';
        
        // Shortcodes
        echo '<div class="adc-usage-item">';
        echo '<h4>📝 Shortcodes</h4>';
        echo '<div class="adc-code-block">';
        echo '[adc_content] <span class="adc-comment"># Español</span><br>';
        echo '[adc_content_en] <span class="adc-comment"># English</span>';
        echo '</div>';
        echo '</div>';

        // URLs Structure
        echo '<div class="adc-usage-item">';
        echo '<h4>🔗 Estructura de URLs</h4>';
        echo '<div class="adc-code-block">';
        echo 'tuia.tv/ <span class="adc-comment"># Home español</span><br>';
        echo 'tuia.tv/en/ <span class="adc-comment"># Home inglés</span><br>';
        echo 'tuia.tv/programa/nombre/ <span class="adc-comment"># Programa ES</span><br>';
        echo 'tuia.tv/programa/nombre/video/ <span class="adc-comment"># Video ES</span><br>';
        echo 'tuia.tv/en/program/name/ <span class="adc-comment"># Program EN</span><br>';
        echo 'tuia.tv/en/program/name/video/ <span class="adc-comment"># Video EN</span><br>';
        echo 'tuia.tv/buscar/término/ <span class="adc-comment"># Búsqueda ES</span><br>';
        echo 'tuia.tv/en/search/term/ <span class="adc-comment"># Search EN</span>';
        echo '</div>';
        echo '</div>';

        // Menu Setup
        echo '<div class="adc-usage-item">';
        echo '<h4>🍔 Configuración de Menús</h4>';
        echo '<div class="adc-code-block">';
        echo '<span class="adc-comment"># PROGRAMAS (Dropdown automático)</span><br>';
        echo 'Español:<br>';
        echo '  Texto: PROGRAMAS_ES<br>';
        echo '  Clase: adc-programs-menu-trigger<br><br>';
        echo 'English:<br>';
        echo '  Texto: PROGRAMAS_EN<br>';
        echo '  Clase: adc-programs-menu-trigger-en<br><br>';
        echo '<span class="adc-comment"># BÚSQUEDA (Formulario automático)</span><br>';
        echo 'Español:<br>';
        echo '  Texto: BUSCADOR_ES<br>';
        echo '  Clase: adc-search-menu-trigger<br><br>';
        echo 'English:<br>';
        echo '  Texto: BUSCADOR_EN<br>';
        echo '  Clase: adc-search-menu-trigger-en';
        echo '</div>';
        echo '</div>';

        // System Features
        echo '<div class="adc-usage-item">';
        echo '<h4>🛠️ Características del Sistema</h4>';
        echo '<div class="adc-code-block">';
        echo '<span class="adc-comment"># Cache Management</span><br>';
        echo 'Manual: tuia.tv/cache/clear<br>';
        echo 'Automático: ✅ Configurado<br><br>';
        echo '<span class="adc-comment"># 404 Redirects</span><br>';
        echo '/en/invalid → /en/<br>';
        echo '/invalid → /<br><br>';
        echo '<span class="adc-comment"># SEO Optimized</span><br>';
        echo '✅ URLs amigables<br>';
        echo '✅ Meta tags automáticos<br>';
        echo '✅ Redirecciones 301<br><br>';
        echo '<span class="adc-comment"># Performance</span><br>';
        echo '✅ Cache inteligente<br>';
        echo '✅ Lazy loading<br>';
        echo '✅ Optimización API';
        echo '</div>';
        echo '</div>';

        echo '</div>'; // Close usage grid

        // Webhook info
        echo '<div class="adc-webhook-info">';
        echo '<h4>🤖 Webhook Automático - Funcionamiento</h4>';
        echo '<ul>';
        echo '<li><strong>Activación:</strong> ADC llama automáticamente cuando sincroniza videos de IA</li>';
        echo '<li><strong>Filtro inteligente:</strong> Solo se activa para secciones 5 (IA Español) y 6 (IA Inglés)</li>';
        echo '<li><strong>Sin intervención:</strong> El caché se limpia automáticamente cuando es necesario</li>';
        echo '<li><strong>Seguridad:</strong> Token permanente protege el webhook de accesos no autorizados</li>';
        echo '<li><strong>Tiempo real:</strong> El sitio se actualiza inmediatamente después de la sincronización</li>';
        echo '</ul>';
        echo '</div>';
    }

    /**
     * Render API status
     */
    private function render_api_status($api_status, $language)
    {
        $language_names = array(
            'es' => 'Español',
            'en' => 'English'
        );

        $status_class = $api_status['connection'] ? 'adc-status-healthy' : 'adc-status-unhealthy';
        $status_icon = $api_status['connection'] ? '✓' : '✗';

        echo '<div class="adc-api-status-item">';
        echo '<div class="adc-status-header">';
        echo '<span class="adc-status-indicator ' . $status_class . '">' . $status_icon . '</span>';
        echo '<span class="adc-status-language">' . $language_names[$language] . '</span>';
        echo '</div>';

        if ($api_status['connection']) {
            echo '<div class="adc-status-details">';
            if (isset($api_status['programs_count'])) {
                echo '<span>✓ ' . $api_status['programs_count'] . ' programas</span>';
            }
            if (isset($api_status['materials_count'])) {
                echo '<span>✓ ' . $api_status['materials_count'] . ' videos</span>';
            }
            if (isset($api_status['response_time'])) {
                echo '<span>' . $api_status['response_time'] . 'ms</span>';
            }
            echo '</div>';
        } else {
            echo '<div class="adc-status-error">';
            echo '<span>Error: ' . esc_html($api_status['error']) . '</span>';
            echo '</div>';
        }
        echo '</div>';
    }

    /**
     * Render modern admin styles and scripts
     */
    private function render_modern_admin_styles_and_scripts()
    {
        echo '<style>
        .adc-admin-wrap {
            background: #f1f1f1;
            margin: 20px 0 0 -20px;
            padding: 0;
        }

        .adc-admin-header {
            background: linear-gradient(135deg, #6EC1E4, #4A90E2);
            color: white;
            padding: 30px;
            text-align: center;
            margin: 0;
        }

        .adc-admin-header h1 {
            margin: 0 0 10px 0;
            font-size: 28px;
            font-weight: 600;
        }

        .adc-admin-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 16px;
        }

        .adc-admin-content {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .adc-save-container {
            text-align: center;
            padding: 25px 30px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
        }

        .adc-save-button {
            font-size: 16px !important;
            padding: 15px 40px !important;
            height: auto !important;
        }

        .adc-section {
            border-bottom: 1px solid #eee;
        }

        .adc-section:last-child {
            border-bottom: none;
        }

        .adc-section-header {
            background: #f8f9fa;
            padding: 20px 30px;
            margin: 0;
        }

        .adc-section-header h2 {
            margin: 0;
            color: #2c3e50;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .adc-icon {
            font-size: 22px;
        }

        .adc-grid-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            padding: 30px;
        }

        .adc-status-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            padding: 30px;
        }

        .adc-card {
            background: #f9f9f9;
            border-radius: 8px;
            padding: 20px;
            border-left: 4px solid #6EC1E4;
        }

        .adc-card-wide {
            grid-column: span 2;
        }

        .adc-card h3 {
            margin: 0 0 15px 0;
            color: #2c3e50;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .adc-form-fields {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .adc-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .adc-quick-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }

        .adc-stats-container {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }

        .adc-stat-item {
            text-align: center;
            padding: 15px;
            background: white;
            border-radius: 6px;
            border: 1px solid #eee;
        }

        .adc-stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #6EC1E4;
            display: block;
        }

        .adc-stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .adc-api-status-item {
            margin-bottom: 15px;
            padding: 10px;
            background: white;
            border-radius: 6px;
            border: 1px solid #eee;
        }

        .adc-status-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 5px;
        }

        .adc-status-indicator {
            font-weight: bold;
            font-size: 16px;
        }

        .adc-status-healthy {
            color: #27ae60;
        }

        .adc-status-unhealthy {
            color: #e74c3c;
        }

        .adc-status-language {
            font-weight: 600;
            color: #2c3e50;
        }

        .adc-status-details {
            display: flex;
            gap: 15px;
            font-size: 14px;
            color: #666;
            margin-left: 26px;
        }

        .adc-status-error {
            font-size: 14px;
            color: #e74c3c;
            margin-left: 26px;
        }

        .adc-status-message {
            margin-top: 15px;
            padding: 10px;
            border-radius: 4px;
            display: none;
        }

        .adc-status-message.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .adc-status-message.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .adc-status-message.loading {
            background: #cce7ff;
            border: 1px solid #99d6ff;
            color: #0066cc;
        }

        .adc-webhook-container, .adc-usage-container {
            padding: 30px;
        }

        .adc-usage-highlight {
            background: #e8f5e8;
            border: 1px solid #c3e6cb;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 25px;
        }

        .adc-usage-highlight h4 {
            margin: 0 0 10px 0;
            color: #155724;
        }

        .adc-usage-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 25px;
        }

        .adc-usage-item h4 {
            margin: 0 0 15px 0;
            color: #2c3e50;
        }

        .adc-code-block {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 15px;
            border-radius: 6px;
            font-family: "Courier New", monospace;
            font-size: 13px;
            overflow-x: auto;
            line-height: 1.5;
        }

        .adc-comment {
            color: #95a5a6;
        }

        .adc-webhook-info {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 6px;
            padding: 20px;
        }

        .adc-webhook-info h4 {
            margin: 0 0 15px 0;
            color: #155724;
        }

        .adc-webhook-info ul {
            margin: 0;
            padding-left: 20px;
            color: #155724;
        }

        .adc-webhook-info li {
            margin-bottom: 8px;
            line-height: 1.4;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .adc-admin-wrap {
                margin: 20px 0 0 0;
            }
            
            .adc-grid-container, .adc-status-grid, .adc-usage-grid, .adc-form-grid {
                grid-template-columns: 1fr;
            }
            
            .adc-card-wide {
                grid-column: span 1;
            }
            
            .adc-stats-container {
                grid-template-columns: 1fr;
            }
            
            .adc-quick-actions {
                flex-direction: column;
            }
            
            .adc-status-details {
                flex-direction: column;
                gap: 5px;
            }
        }
        </style>';

        echo '<script>
        jQuery(document).ready(function($) {
            // Clear ALL cache handler
            $("#adc-clear-all-cache").on("click", function(e) {
                e.preventDefault();
                var button = $(this);
                var originalText = button.text();
                
                button.prop("disabled", true).text("🗑️ Limpiando...");
                $("#adc-cache-status").removeClass("success error").addClass("loading").text("Limpiando todo el caché...").show();
                
                $.ajax({
                    url: ajaxurl,
                    type: "POST",
                    data: {
                        action: "adc_clear_all_cache",
                        nonce: "' . wp_create_nonce('adc_admin_nonce') . '"
                    },
                    success: function(response) {
                        if (response.success) {
                            $("#adc-cache-status").removeClass("loading error").addClass("success").text("✅ " + response.data.message);
                            setTimeout(function() { location.reload(); }, 2000);
                        } else {
                            $("#adc-cache-status").removeClass("loading success").addClass("error").text("❌ Error: " + response.data);
                        }
                    },
                    error: function() {
                        $("#adc-cache-status").removeClass("loading success").addClass("error").text("❌ Error de conexión");
                    },
                    complete: function() {
                        button.prop("disabled", false).text(originalText);
                    }
                });
            });
            
            // Copy token to clipboard
            $("#adc-copy-token").on("click", function(e) {
                e.preventDefault();
                var token = $("#adc-current-token").val();
                copyToClipboard(token, $(this));
            });
            
            // Copy webhook URL to clipboard
            $("#adc-copy-webhook").on("click", function(e) {
                e.preventDefault();
                var webhookUrl = $("#adc-webhook-url").val();
                copyToClipboard(webhookUrl, $(this));
            });
            
            // Copy to clipboard function
            function copyToClipboard(text, button) {
                var originalText = button.text();
                
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text).then(function() {
                        button.text("✅ Copiado!");
                        setTimeout(function() {
                            button.text(originalText);
                        }, 2000);
                    }).catch(function() {
                        fallbackCopyToClipboard(text, button, originalText);
                    });
                } else {
                    fallbackCopyToClipboard(text, button, originalText);
                }
            }
            
            // Fallback copy method
            function fallbackCopyToClipboard(text, button, originalText) {
                var textArea = document.createElement("textarea");
                textArea.value = text;
                textArea.style.position = "fixed";
                textArea.style.left = "-999999px";
                textArea.style.top = "-999999px";
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                
                try {
                    document.execCommand("copy");
                    button.text("✅ Copiado!");
                    setTimeout(function() {
                        button.text(originalText);
                    }, 2000);
                } catch (err) {
                    button.text("❌ Error");
                    setTimeout(function() {
                        button.text(originalText);
                    }, 2000);
                }
                
                document.body.removeChild(textArea);
            }
            
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
                            $("#adc-connection-status").removeClass("loading error").addClass("success").text("✅ Conexión exitosa para " + language.toUpperCase() + " - " + response.data.programs_count + " programas encontrados");
                        } else {
                            $("#adc-connection-status").removeClass("loading success").addClass("error").text("❌ Error en " + language.toUpperCase() + ": " + response.data.error);
                        }
                    },
                    error: function() {
                        $("#adc-connection-status").removeClass("loading success").addClass("error").text("❌ Error de conexión para " + language.toUpperCase());
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
     * Display program order pages for each language (only ES and EN)
     */
    public function display_program_order_page_es()
    {
        $this->display_program_order_page('es');
    }

    public function display_program_order_page_en()
    {
        $this->display_program_order_page('en');
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
            'en' => 'English'
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
     * Generate secure token for webhook
     */
    private function generate_secure_token()
    {
        return 'adc_' . wp_generate_password(32, false, false);
    }

    /**
     * Get webhook URL with token
     */
    private function get_webhook_url($token)
    {
        return admin_url('admin-ajax.php?action=adc_webhook_refresh&token=' . urlencode($token));
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