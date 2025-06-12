<?php
/**
 * ADC Video Display - Admin Settings
 * 
 * Handles all admin configuration for the plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
  
class ADC_Admin {
    
    private $plugin_name = 'adc-video-display';
    private $options;
    private $api;
    
    public function __construct() {
        $this->options = get_option($this->plugin_name);
        $this->api = new ADC_API();
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // Add scripts and styles for admin
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_adc_update_program_order', array($this, 'ajax_update_program_order'));
        add_action('wp_ajax_adc_refresh_coming_soon_list', array($this, 'ajax_refresh_coming_soon_list'));
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
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
            time()
        );
        
        // Admin specific JavaScript
        wp_enqueue_script(
            'adc-admin-script',
            ADC_PLUGIN_URL . 'admin.js',
            array('jquery', 'jquery-ui-sortable'),
            time(),
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
    private function is_adc_admin_page($hook) {
        $adc_pages = array(
            'toplevel_page_' . $this->plugin_name,
            'adc-videos_page_' . $this->plugin_name . '-order',
            'adc-videos_page_' . $this->plugin_name . '-coming-soon'
        );
        
        return in_array($hook, $adc_pages);
    }
    
    /**
     * AJAX handler to update program order
     */
    public function ajax_update_program_order() {
        check_ajax_referer('adc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $program_order = isset($_POST['program_order']) ? $_POST['program_order'] : array();
        
        // Sanitize - ensure we only have integers
        $sanitized_order = array_map('intval', $program_order);
        
        update_option('adc_programs_order', $sanitized_order);
        
        wp_send_json_success('Order updated successfully');
    }
    
    /**
     * AJAX handler to refresh coming soon programs list
     */
    public function ajax_refresh_coming_soon_list() {
        check_ajax_referer('adc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        // Clear API cache to get fresh data
        $this->api->clear_cache();
        
        $programs_without_videos = $this->api->get_programs_without_videos();
        
        if (empty($programs_without_videos)) {
            wp_send_json_success(array(
                'html' => '<p>Todos los programas actualmente tienen videos disponibles.</p>',
                'count' => 0
            ));
            return;
        }
        
        // Generate HTML for the checkboxes
        $html = '<div class="adc-coming-soon-programs-list">';
        $selected_programs = get_option('adc_coming_soon_programs', array());
        
        foreach ($programs_without_videos as $program) {
            $checked = in_array($program['id'], $selected_programs) ? 'checked' : '';
            $cover_url = isset($program['cover']) ? $program['cover'] : '';
            
            $html .= '<div class="adc-program-checkbox-item">';
            $html .= '<label>';
            $html .= '<input type="checkbox" name="coming_soon_programs[]" value="' . esc_attr($program['id']) . '" ' . $checked . '>';
            
            if ($cover_url) {
                $html .= '<img src="' . esc_url($cover_url) . '" alt="' . esc_attr($program['name']) . '" class="adc-program-thumbnail">';
            }
            
            $html .= '<span class="adc-program-name">' . esc_html($program['name']) . '</span>';
            $html .= '<span class="adc-program-id">(ID: ' . esc_html($program['id']) . ')</span>';
            $html .= '</label>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        wp_send_json_success(array(
            'html' => $html,
            'count' => count($programs_without_videos)
        ));
    }
    
    /**
     * Add menu item to WordPress admin
     */
    public function add_admin_menu() {
        add_menu_page(
            'ADC Videos',
            'ADC Videos',
            'manage_options',
            $this->plugin_name,
            array($this, 'display_settings_page'),
            'dashicons-video-alt3',
            30
        );
        
        // Add submenu for program ordering
        add_submenu_page(
            $this->plugin_name,
            'Ordenar Programas',
            'Ordenar Programas',
            'manage_options',
            $this->plugin_name . '-order',
            array($this, 'display_program_order_page')
        );
        
        // Add submenu for coming soon programs
        add_submenu_page(
            $this->plugin_name,
            'Programas Próximamente',
            'Próximamente',
            'manage_options',
            $this->plugin_name . '-coming-soon',
            array($this, 'display_coming_soon_page')
        );
    }
    
    /**
     * Initialize plugin settings
     */
    public function init_settings() {
        register_setting(
            $this->plugin_name . '_group',
            $this->plugin_name,
            array($this, 'sanitize_settings')
        );
        
        // Register coming soon settings
        register_setting(
            $this->plugin_name . '_coming_soon_group',
            'adc_coming_soon_programs',
            array($this, 'sanitize_coming_soon_programs')
        );
        
        $this->register_api_settings();
        $this->register_display_settings();
        $this->register_search_settings();
        $this->register_menu_settings();
        $this->register_advanced_settings();
    }
    
    /**
     * Register API settings section
     */
    private function register_api_settings() {
        add_settings_section(
            'api_settings',
            'Configuración de API',
            array($this, 'api_settings_section_callback'),
            $this->plugin_name
        );
        
        add_settings_field('api_token', 'Token de API', array($this, 'api_token_callback'), $this->plugin_name, 'api_settings');
        add_settings_field('api_url', 'URL Base de API', array($this, 'api_url_callback'), $this->plugin_name, 'api_settings');
        add_settings_field('section', 'Sección a Mostrar', array($this, 'section_callback'), $this->plugin_name, 'api_settings');
    }
    
    /**
     * Register display settings section
     */
    private function register_display_settings() {
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
    private function register_search_settings() {
        add_settings_section(
            'search_settings',
            'Configuración de Búsqueda',
            array($this, 'search_settings_section_callback'),
            $this->plugin_name
        );
        
        add_settings_field('enable_search', 'Habilitar Búsqueda', array($this, 'enable_search_callback'), $this->plugin_name, 'search_settings');
        add_settings_field('search_results_page', 'Página de Resultados', array($this, 'search_results_page_callback'), $this->plugin_name, 'search_settings');
        add_settings_field('search_placeholder', 'Placeholder de Búsqueda', array($this, 'search_placeholder_callback'), $this->plugin_name, 'search_settings');
    }
    
    /**
     * Register menu settings section
     */
    private function register_menu_settings() {
        add_settings_section(
            'menu_settings',
            'Configuración de Menú',
            array($this, 'menu_settings_section_callback'),
            $this->plugin_name
        );
        
        add_settings_field('enable_menu', 'Habilitar Menú Desplegable', array($this, 'enable_menu_callback'), $this->plugin_name, 'menu_settings');
        add_settings_field('menu_text', 'Texto del Menú', array($this, 'menu_text_callback'), $this->plugin_name, 'menu_settings');
    }
    
    /**
     * Register advanced settings section
     */
    private function register_advanced_settings() {
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
    public function api_settings_section_callback() {
        echo '<p>Configura los datos de conexión a la API de TuTorah TV.</p>';
    }
    
    public function display_settings_section_callback() {
        echo '<p>Configura las opciones de visualización del contenido.</p>';
    }
    
    public function search_settings_section_callback() {
        echo '<p>Configura las opciones de búsqueda.</p>';
    }
    
    public function menu_settings_section_callback() {
        echo '<p>Configura el menú desplegable de programas.</p>';
    }
    
    public function advanced_settings_section_callback() {
        echo '<p>Opciones avanzadas para desarrolladores.</p>';
    }
    
    /**
     * Field callbacks (optimized with helper method)
     */
    public function api_token_callback() {
        $this->render_text_field('api_token', 'Tu token de API', 'El token de autenticación para la API.');
    }
    
    public function api_url_callback() {
        $this->render_url_field('api_url', 'https://api.tutorah.tv/v1', 'URL base de la API (sin slash final).');
    }
    
    public function section_callback() {
        $value = isset($this->options['section']) ? $this->options['section'] : '2';
        echo '<select name="' . $this->plugin_name . '[section]">';
        echo '<option value="2"' . selected($value, '2', false) . '>Kids (Infantil)</option>';
        echo '<option value="5"' . selected($value, '5', false) . '>IA</option>';
        echo '</select>';
        echo '<p class="description">Selecciona qué sección mostrar en el frontend.</p>';
    }
    
    public function videos_per_row_callback() {
        $value = isset($this->options['videos_per_row']) ? $this->options['videos_per_row'] : '4';
        echo '<select name="' . $this->plugin_name . '[videos_per_row]">';
        for ($i = 3; $i <= 6; $i++) {
            echo '<option value="' . $i . '"' . selected($value, $i, false) . '>' . $i . ' videos</option>';
        }
        echo '</select>';
        echo '<p class="description">Número de videos a mostrar por fila.</p>';
    }
    
    public function enable_autoplay_callback() {
        $this->render_checkbox_field('enable_autoplay', 'Activar reproducción automática del siguiente video');
    }
    
    public function autoplay_countdown_callback() {
        $this->render_number_field('autoplay_countdown', 3, 30, 'Segundos antes de reproducir el siguiente video (3-30).');
    }
    
    public function enable_search_callback() {
        $this->render_checkbox_field('enable_search', 'Activar funcionalidad de búsqueda');
    }
    
    public function search_results_page_callback() {
        $value = isset($this->options['search_results_page']) ? $this->options['search_results_page'] : '';
        $pages = get_pages();
        
        echo '<select name="' . $this->plugin_name . '[search_results_page]">';
        echo '<option value="">-- Misma página --</option>';
        foreach ($pages as $page) {
            echo '<option value="' . $page->ID . '"' . selected($value, $page->ID, false) . '>' . esc_html($page->post_title) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">Página donde se mostrarán los resultados de búsqueda.</p>';
    }
    
    public function search_placeholder_callback() {
        $this->render_text_field('search_placeholder', 'Buscar videos...', 'Texto placeholder para el campo de búsqueda.');
    }
    
    public function enable_menu_callback() {
        $this->render_checkbox_field('enable_menu', 'Activar menú desplegable de programas');
    }
    
    public function menu_text_callback() {
        $this->render_text_field('menu_text', 'Programas', 'Texto del botón del menú desplegable.');
    }
    
    public function related_videos_count_callback() {
        $this->render_number_field('related_videos_count', 4, 20, 'Cantidad de videos relacionados a mostrar (4-20).');
    }
    
    public function debug_mode_callback() {
        $this->render_checkbox_field('debug_mode', 'Activar modo debug (muestra información adicional en la consola)');
    }
    
    /**
     * Helper methods for rendering fields
     */
    private function render_text_field($field_name, $placeholder = '', $description = '') {
        $value = isset($this->options[$field_name]) ? $this->options[$field_name] : '';
        echo '<input type="text" name="' . $this->plugin_name . '[' . $field_name . ']" value="' . esc_attr($value) . '" class="regular-text" placeholder="' . esc_attr($placeholder) . '" />';
        if ($description) {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
    }
    
    private function render_url_field($field_name, $placeholder = '', $description = '') {
        $value = isset($this->options[$field_name]) ? $this->options[$field_name] : $placeholder;
        echo '<input type="url" name="' . $this->plugin_name . '[' . $field_name . ']" value="' . esc_attr($value) . '" class="regular-text" placeholder="' . esc_attr($placeholder) . '" />';
        if ($description) {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
    }
    
    private function render_checkbox_field($field_name, $label) {
        $checked = isset($this->options[$field_name]) ? $this->options[$field_name] : '0';
        echo '<label><input type="checkbox" name="' . $this->plugin_name . '[' . $field_name . ']" value="1"' . checked($checked, '1', false) . ' /> ' . esc_html($label) . '</label>';
    }
    
    private function render_number_field($field_name, $min, $max, $description = '') {
        $value = isset($this->options[$field_name]) ? $this->options[$field_name] : $min;
        echo '<input type="number" name="' . $this->plugin_name . '[' . $field_name . ']" value="' . esc_attr($value) . '" min="' . $min . '" max="' . $max . '" class="small-text" />';
        if ($description) {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
    }
    
    /**
     * Sanitize settings before saving
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // API Settings
        $sanitized['api_token'] = isset($input['api_token']) ? sanitize_text_field($input['api_token']) : '';
        $sanitized['api_url'] = isset($input['api_url']) ? rtrim(esc_url_raw($input['api_url']), '/') : '';
        $sanitized['section'] = isset($input['section']) && in_array($input['section'], array('2', '5')) ? $input['section'] : '2';
        
        // Display Settings
        $sanitized['videos_per_row'] = isset($input['videos_per_row']) && in_array($input['videos_per_row'], array('3', '4', '5', '6')) ? $input['videos_per_row'] : '4';
        $sanitized['enable_autoplay'] = isset($input['enable_autoplay']) ? '1' : '0';
        $sanitized['autoplay_countdown'] = isset($input['autoplay_countdown']) ? max(3, min(30, intval($input['autoplay_countdown']))) : 5;
        
        // Search Settings
        $sanitized['enable_search'] = isset($input['enable_search']) ? '1' : '0';
        $sanitized['search_results_page'] = isset($input['search_results_page']) ? intval($input['search_results_page']) : 0;
        $sanitized['search_placeholder'] = isset($input['search_placeholder']) ? sanitize_text_field($input['search_placeholder']) : 'Buscar videos...';
        
        // Menu Settings
        $sanitized['enable_menu'] = isset($input['enable_menu']) ? '1' : '0';
        $sanitized['menu_text'] = isset($input['menu_text']) ? sanitize_text_field($input['menu_text']) : 'Programas';
        
        // Advanced Settings
        $sanitized['related_videos_count'] = isset($input['related_videos_count']) ? max(4, min(20, intval($input['related_videos_count']))) : 8;
        $sanitized['debug_mode'] = isset($input['debug_mode']) ? '1' : '0';
        
        // Legacy fields
        $sanitized['show_view_more'] = isset($input['show_view_more']) ? '1' : '0';
        
        return $sanitized;
    }
    
    /**
     * Sanitize coming soon programs
     */
    public function sanitize_coming_soon_programs($input) {
        if (!is_array($input)) {
            return array();
        }
        
        return array_map('intval', $input);
    }
    
    /**
     * Display admin notices
     */
    public function admin_notices() {
        if (!$this->is_adc_admin_page($_GET['page'] ?? '')) {
            return;
        }
        
        if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
            echo '<div class="notice notice-success is-dismissible"><p>¡Configuración guardada exitosamente!</p></div>';
        }
        
        if (isset($_GET['order-updated']) && $_GET['order-updated']) {
            echo '<div class="notice notice-success is-dismissible"><p>¡Orden de programas actualizado exitosamente!</p></div>';
        }
        
        if (isset($_GET['coming-soon-updated']) && $_GET['coming-soon-updated']) {
            echo '<div class="notice notice-success is-dismissible"><p>¡Programas próximamente actualizados exitosamente!</p></div>';
        }
    }

    /**
     * Display the settings page
     */
    public function display_settings_page() {
        $api_status = $this->test_api_connection();
        
        echo '<div class="wrap">';
        echo '<h1>ADC Video Display - Configuración</h1>';
        
        $this->render_api_status($api_status);
        
        echo '<form method="post" action="options.php">';
        settings_fields($this->plugin_name . '_group');
        do_settings_sections($this->plugin_name);
        submit_button();
        echo '</form>';
        
        $this->render_usage_info($api_status);
        
        echo '</div>';
    }
    
    /**
     * Display the program order page
     */
    public function display_program_order_page() {
        $api = new ADC_API();
        $programs = $api->get_programs();
        $saved_order = get_option('adc_programs_order', array());
        
        // Apply saved order
        if (!empty($saved_order)) {
            $programs = $this->apply_saved_order($programs, $saved_order);
        }
        
        echo '<div class="wrap">';
        echo '<h1>Ordenar Programas</h1>';
        echo '<div class="notice notice-info"><p>Arrastra y suelta los programas para cambiar su orden de visualización en la página principal. El orden se guardará automáticamente.</p></div>';
        
        if (empty($programs)) {
            echo '<div class="notice notice-error"><p>No se pudieron cargar los programas. Verifica la conexión a la API.</p></div>';
        } else {
            $this->render_sortable_programs($programs);
        }
        
        echo '</div>';
    }
    
    /**
     * Display the coming soon page
     */
    public function display_coming_soon_page() {
        $programs_without_videos = $this->api->get_programs_without_videos();
        $selected_programs = get_option('adc_coming_soon_programs', array());
        
        echo '<div class="wrap">';
        echo '<h1>Programas Próximamente</h1>';
        echo '<div class="notice notice-info">';
        echo '<p>Selecciona los programas que quieres mostrar como "Próximamente" cuando no tengan videos disponibles.</p>';
        echo '<p><strong>Nota:</strong> Solo se muestran programas que actualmente NO tienen videos. Si un programa obtiene videos, automáticamente dejará de mostrarse como "Próximamente".</p>';
        echo '</div>';
        
        if (!$this->api->is_configured()) {
            echo '<div class="notice notice-error"><p>La API no está configurada. Ve a la configuración principal para configurar el token y URL de la API.</p></div>';
            echo '</div>';
            return;
        }
        
        echo '<form method="post" action="options.php">';
        settings_fields($this->plugin_name . '_coming_soon_group');
        
        echo '<div class="adc-coming-soon-controls">';
        echo '<button type="button" id="adc-refresh-coming-soon" class="button">🔄 Actualizar Lista</button>';
        echo '<span id="adc-refresh-status"></span>';
        echo '</div>';
        
        echo '<div id="adc-coming-soon-container">';
        
        if (empty($programs_without_videos)) {
            echo '<div class="notice notice-warning"><p>Todos los programas actualmente tienen videos disponibles. No hay programas para mostrar como "Próximamente".</p></div>';
        } else {
            echo '<h3>Programas sin videos disponibles (' . count($programs_without_videos) . '):</h3>';
            echo '<div class="adc-coming-soon-programs-list">';
            
            foreach ($programs_without_videos as $program) {
                $checked = in_array($program['id'], $selected_programs) ? 'checked' : '';
                $cover_url = isset($program['cover']) ? $program['cover'] : '';
                
                echo '<div class="adc-program-checkbox-item">';
                echo '<label>';
                echo '<input type="checkbox" name="adc_coming_soon_programs[]" value="' . esc_attr($program['id']) . '" ' . $checked . '>';
                
                if ($cover_url) {
                    echo '<img src="' . esc_url($cover_url) . '" alt="' . esc_attr($program['name']) . '" class="adc-program-thumbnail">';
                }
                
                echo '<span class="adc-program-name">' . esc_html($program['name']) . '</span>';
                echo '<span class="adc-program-id">(ID: ' . esc_html($program['id']) . ')</span>';
                echo '</label>';
                echo '</div>';
            }
            
            echo '</div>';
        }
        
        echo '</div>';
        
        submit_button('Guardar Programas Próximamente');
        echo '</form>';
        echo '</div>';
    }
    
    /**
     * Helper methods for rendering
     */
    private function render_api_status($api_status) {
        if ($api_status['connection']) {
            echo '<div class="notice notice-success">';
            echo '<p><strong>Estado de API:</strong> Conexión exitosa ✓</p>';
            if (isset($api_status['programs_count'])) {
                echo '<p>Programas disponibles: ' . $api_status['programs_count'] . '</p>';
            }
            echo '</div>';
        } else {
            echo '<div class="notice notice-error">';
            echo '<p><strong>Estado de API:</strong> Error de conexión ✗</p>';
            if (isset($api_status['error'])) {
                echo '<p>Error: ' . esc_html($api_status['error']) . '</p>';
            }
            echo '</div>';
        }
    }
    
    private function render_usage_info($api_status) {
        echo '<div class="card" style="margin-top: 30px;">';
        echo '<h2>Información de Uso</h2>';
        
        echo '<h3>Shortcodes disponibles:</h3>';
        echo '<ul>';
        echo '<li><code>[adc_content]</code> - Muestra el contenido principal</li>';
        echo '<li><code>[adc_search_form]</code> - Muestra el formulario de búsqueda</li>';
        echo '<li><code>[adc_programs_menu]</code> - Muestra el menú desplegable de programas</li>';
        echo '</ul>';
        
        $section = isset($this->options['section']) ? $this->options['section'] : '2';
        $section_name = $section == '5' ? 'IA' : 'Kids';
        
        echo '<h3>URLs del Sistema:</h3>';
        echo '<p>Este sitio está configurado para la sección: <strong>' . $section_name . '</strong></p>';
        echo '<ul>';
        echo '<li>Listado de programas: <code>/?</code></li>';
        echo '<li>Ver programa: <code>/?categoria=nombre-programa</code></li>';
        echo '<li>Ver video: <code>/?categoria=nombre-programa&video=nombre-video</code></li>';
        echo '<li>Búsqueda: <code>/?adc_search=término</code></li>';
        echo '</ul>';
        
        if ($api_status['connection'] && isset($api_status['programs'])) {
            echo '<h3>Programas Disponibles:</h3>';
            echo '<table class="widefat">';
            echo '<thead><tr><th>ID</th><th>Nombre</th><th>Portada</th><th>Videos</th></tr></thead>';
            echo '<tbody>';
            
            foreach ($api_status['programs'] as $program) {
                $has_videos = $this->api->program_has_videos($program['id']) ? '✓' : '✗';
                echo '<tr>';
                echo '<td>' . esc_html($program['id']) . '</td>';
                echo '<td>' . esc_html($program['name']) . '</td>';
                echo '<td>';
                if (isset($program['cover'])) {
                    echo '<img src="' . esc_url($program['cover']) . '" alt="' . esc_attr($program['name']) . '" style="width: 50px; height: 50px; object-fit: cover;">';
                } else {
                    echo 'Sin portada';
                }
                echo '</td>';
                echo '<td>' . $has_videos . '</td>';
                echo '</tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
        }
        
        echo '</div>';
    }
    
    private function render_sortable_programs($programs) {
        echo '<div id="program-order-container">';
        echo '<ul id="sortable-programs" class="programs-order-list">';
        
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
        
        $this->render_sortable_script();
    }
    
    private function render_sortable_script() {
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
    
    private function apply_saved_order($programs, $saved_order) {
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
     * Test API connection (improved with detailed info)
     */
    private function test_api_connection() {
        if (!$this->api->is_configured()) {
            return array(
                'connection' => false,
                'error' => 'API no configurada - Token o URL faltante'
            );
        }
        
        $result = $this->api->test_connection();
        
        // Add programs list for admin display
        if ($result['success']) {
            $programs = $this->api->get_programs();
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
    public static function get_options() {
        return get_option('adc-video-display');
    }
}

// Initialize admin if in admin area
if (is_admin()) {
    new ADC_Admin();
}

// DEBUG TEMPORAL - Agregar antes del cierre final
if (isset($_GET['debug_coming_soon'])) {
    echo '<div style="background: #f0f0f0; padding: 20px; margin: 20px 0; border: 1px solid #ccc;">';
    echo '<h3>🔍 DEBUG: Programas IA</h3>';
    
    $api = new ADC_API();
    $api->enable_debug();
    
    echo '<h4>1. Todos los programas de la API:</h4>';
    $all_programs = $api->get_all_programs_from_api();
    echo 'Total encontrados: ' . count($all_programs) . '<br>';
    
    echo '<h4>2. Programas filtrados (solo con portada IA):</h4>';
    $filtered_programs = $api->get_programs();
    echo 'Con portada IA: ' . count($filtered_programs) . '<br>';
    
    echo '<h4>3. Programas sin videos:</h4>';
    $programs_without_videos = $api->get_programs_without_videos();
    echo 'Sin videos: ' . count($programs_without_videos) . '<br>';
    
    echo '<h4>4. Debug info:</h4>';
    echo '<pre>' . print_r($api->get_debug_info(), true) . '</pre>';
    
    echo '</div>';
}