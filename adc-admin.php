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
    
    public function __construct() {
        $this->options = get_option($this->plugin_name);
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // Add scripts and styles for admin
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX handler for program ordering
        add_action('wp_ajax_adc_update_program_order', array($this, 'ajax_update_program_order'));
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our settings page or program order page
        if ($hook != 'toplevel_page_' . $this->plugin_name && $hook != 'adc-videos_page_' . $this->plugin_name . '-order') {
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
    }
    
    /**
     * AJAX handler to update program order
     */
    public function ajax_update_program_order() {
        // Check nonce for security
        check_ajax_referer('adc_admin_nonce', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        // Get program order
        $program_order = isset($_POST['program_order']) ? $_POST['program_order'] : array();
        
        // Sanitize - ensure we only have integers
        $sanitized_order = array();
        foreach ($program_order as $position => $program_id) {
            $sanitized_order[] = intval($program_id);
        }
        
        // Update option
        update_option('adc_programs_order', $sanitized_order);
        
        wp_send_json_success('Order updated successfully');
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
        
        // API Settings Section
        add_settings_section(
            'api_settings',
            'Configuración de API',
            array($this, 'api_settings_section_callback'),
            $this->plugin_name
        );
        
        // API Token
        add_settings_field(
            'api_token',
            'Token de API',
            array($this, 'api_token_callback'),
            $this->plugin_name,
            'api_settings'
        );
        
        // API Base URL
        add_settings_field(
            'api_url',
            'URL Base de API',
            array($this, 'api_url_callback'),
            $this->plugin_name,
            'api_settings'
        );
        
        // Section Selection
        add_settings_field(
            'section',
            'Sección a Mostrar',
            array($this, 'section_callback'),
            $this->plugin_name,
            'api_settings'
        );
        
        // Display Settings Section
        add_settings_section(
            'display_settings',
            'Configuración de Visualización',
            array($this, 'display_settings_section_callback'),
            $this->plugin_name
        );
        
        // Videos per row
        add_settings_field(
            'videos_per_row',
            'Videos por Fila',
            array($this, 'videos_per_row_callback'),
            $this->plugin_name,
            'display_settings'
        );
        
        // Enable autoplay
        add_settings_field(
            'enable_autoplay',
            'Habilitar Autoplay',
            array($this, 'enable_autoplay_callback'),
            $this->plugin_name,
            'display_settings'
        );
        
        // Autoplay countdown
        add_settings_field(
            'autoplay_countdown',
            'Segundos para Autoplay',
            array($this, 'autoplay_countdown_callback'),
            $this->plugin_name,
            'display_settings'
        );
        
        // Search Settings Section
        add_settings_section(
            'search_settings',
            'Configuración de Búsqueda',
            array($this, 'search_settings_section_callback'),
            $this->plugin_name
        );
        
        // Enable search
        add_settings_field(
            'enable_search',
            'Habilitar Búsqueda',
            array($this, 'enable_search_callback'),
            $this->plugin_name,
            'search_settings'
        );
        
        // Search results page
        add_settings_field(
            'search_results_page',
            'Página de Resultados',
            array($this, 'search_results_page_callback'),
            $this->plugin_name,
            'search_settings'
        );
        
        // Search placeholder
        add_settings_field(
            'search_placeholder',
            'Placeholder de Búsqueda',
            array($this, 'search_placeholder_callback'),
            $this->plugin_name,
            'search_settings'
        );
        
        // Menu Settings Section
        add_settings_section(
            'menu_settings',
            'Configuración de Menú',
            array($this, 'menu_settings_section_callback'),
            $this->plugin_name
        );
        
        // Enable menu
        add_settings_field(
            'enable_menu',
            'Habilitar Menú Desplegable',
            array($this, 'enable_menu_callback'),
            $this->plugin_name,
            'menu_settings'
        );
        
        // Menu text
        add_settings_field(
            'menu_text',
            'Texto del Menú',
            array($this, 'menu_text_callback'),
            $this->plugin_name,
            'menu_settings'
        );
        
        // Advanced Settings Section
        add_settings_section(
            'advanced_settings',
            'Configuración Avanzada',
            array($this, 'advanced_settings_section_callback'),
            $this->plugin_name
        );
        
        // Related videos count
        add_settings_field(
            'related_videos_count',
            'Cantidad de Videos Relacionados',
            array($this, 'related_videos_count_callback'),
            $this->plugin_name,
            'advanced_settings'
        );
        
        // Enable debug mode
        add_settings_field(
            'debug_mode',
            'Modo Debug',
            array($this, 'debug_mode_callback'),
            $this->plugin_name,
            'advanced_settings'
        );
    }

    /**
     * API Settings section description
     */
    public function api_settings_section_callback() {
        echo '<p>Configura los datos de conexión a la API de TuTorah TV.</p>';
    }
    
    /**
     * Display Settings section description
     */
    public function display_settings_section_callback() {
        echo '<p>Configura las opciones de visualización del contenido.</p>';
    }
    
    /**
     * Search Settings section description
     */
    public function search_settings_section_callback() {
        echo '<p>Configura las opciones de búsqueda.</p>';
    }
    
    /**
     * Menu Settings section description
     */
    public function menu_settings_section_callback() {
        echo '<p>Configura el menú desplegable de programas.</p>';
    }
    
    /**
     * Advanced Settings section description
     */
    public function advanced_settings_section_callback() {
        echo '<p>Opciones avanzadas para desarrolladores.</p>';
    }
    
    /**
     * API Token field
     */
    public function api_token_callback() {
        $value = isset($this->options['api_token']) ? $this->options['api_token'] : '';
        ?>
        <input type="text" 
               name="<?php echo $this->plugin_name; ?>[api_token]" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text" 
               placeholder="Tu token de API" />
        <p class="description">El token de autenticación para la API.</p>
        <?php
    }
    
    /**
     * API URL field
     */
    public function api_url_callback() {
        $value = isset($this->options['api_url']) ? $this->options['api_url'] : 'https://api.tutorah.tv/v1';
        ?>
        <input type="url" 
               name="<?php echo $this->plugin_name; ?>[api_url]" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text" 
               placeholder="https://api.tutorah.tv/v1" />
        <p class="description">URL base de la API (sin slash final).</p>
        <?php
    }
    
    /**
     * Section selection field
     */
    public function section_callback() {
        $value = isset($this->options['section']) ? $this->options['section'] : '2';
        ?>
        <select name="<?php echo $this->plugin_name; ?>[section]">
            <option value="2" <?php selected($value, '2'); ?>>Kids (Infantil)</option>
            <option value="5" <?php selected($value, '5'); ?>>IA</option>
        </select>
        <p class="description">Selecciona qué sección mostrar en el frontend.</p>
        <?php
    }
    
    /**
     * Videos per row field
     */
    public function videos_per_row_callback() {
        $value = isset($this->options['videos_per_row']) ? $this->options['videos_per_row'] : '4';
        ?>
        <select name="<?php echo $this->plugin_name; ?>[videos_per_row]">
            <option value="3" <?php selected($value, '3'); ?>>3 videos</option>
            <option value="4" <?php selected($value, '4'); ?>>4 videos</option>
            <option value="5" <?php selected($value, '5'); ?>>5 videos</option>
            <option value="6" <?php selected($value, '6'); ?>>6 videos</option>
        </select>
        <p class="description">Número de videos a mostrar por fila.</p>
        <?php
    }
    
    /**
     * Enable autoplay field
     */
    public function enable_autoplay_callback() {
        $checked = isset($this->options['enable_autoplay']) ? $this->options['enable_autoplay'] : '1';
        ?>
        <label>
            <input type="checkbox" 
                   name="<?php echo $this->plugin_name; ?>[enable_autoplay]" 
                   value="1" 
                   <?php checked($checked, '1'); ?> />
            Activar reproducción automática del siguiente video
        </label>
        <?php
    }
    
    /**
     * Autoplay countdown field
     */
    public function autoplay_countdown_callback() {
        $value = isset($this->options['autoplay_countdown']) ? $this->options['autoplay_countdown'] : '5';
        ?>
        <input type="number" 
               name="<?php echo $this->plugin_name; ?>[autoplay_countdown]" 
               value="<?php echo esc_attr($value); ?>" 
               min="3" 
               max="30" 
               class="small-text" />
        <p class="description">Segundos antes de reproducir el siguiente video (3-30).</p>
        <?php
    }
    
    /**
     * Show view more button field
     */
    public function show_view_more_callback() {
        $checked = isset($this->options['show_view_more']) ? $this->options['show_view_more'] : '1';
        ?>
        <label>
            <input type="checkbox" 
                   name="<?php echo $this->plugin_name; ?>[show_view_more]" 
                   value="1" 
                   <?php checked($checked, '1'); ?> />
            Mostrar botón "Ver más videos" en la página de video individual
        </label>
        <p class="description">Si está desactivado, solo se mostrarán los 8 videos relacionados sin opción de expandir.</p>
        <?php
    }
    
    /**
     * Enable search field
     */
    public function enable_search_callback() {
        $checked = isset($this->options['enable_search']) ? $this->options['enable_search'] : '1';
        ?>
        <label>
            <input type="checkbox" 
                   name="<?php echo $this->plugin_name; ?>[enable_search]" 
                   value="1" 
                   <?php checked($checked, '1'); ?> />
            Activar funcionalidad de búsqueda
        </label>
        <?php
    }
    
    /**
     * Search results page field
     */
    public function search_results_page_callback() {
        $value = isset($this->options['search_results_page']) ? $this->options['search_results_page'] : '';
        $pages = get_pages();
        ?>
        <select name="<?php echo $this->plugin_name; ?>[search_results_page]">
            <option value="">-- Misma página --</option>
            <?php foreach ($pages as $page): ?>
                <option value="<?php echo $page->ID; ?>" <?php selected($value, $page->ID); ?>>
                    <?php echo esc_html($page->post_title); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">Página donde se mostrarán los resultados de búsqueda.</p>
        <?php
    }
    
    /**
     * Search placeholder field
     */
    public function search_placeholder_callback() {
        $value = isset($this->options['search_placeholder']) ? $this->options['search_placeholder'] : 'Buscar videos...';
        ?>
        <input type="text" 
               name="<?php echo $this->plugin_name; ?>[search_placeholder]" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text" />
        <p class="description">Texto placeholder para el campo de búsqueda.</p>
        <?php
    }
    
    /**
     * Enable menu field
     */
    public function enable_menu_callback() {
        $checked = isset($this->options['enable_menu']) ? $this->options['enable_menu'] : '1';
        ?>
        <label>
            <input type="checkbox" 
                   name="<?php echo $this->plugin_name; ?>[enable_menu]" 
                   value="1" 
                   <?php checked($checked, '1'); ?> />
            Activar menú desplegable de programas
        </label>
        <?php
    }
    
    /**
     * Menu text field
     */
    public function menu_text_callback() {
        $value = isset($this->options['menu_text']) ? $this->options['menu_text'] : 'Programas';
        ?>
        <input type="text" 
               name="<?php echo $this->plugin_name; ?>[menu_text]" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text" />
        <p class="description">Texto del botón del menú desplegable.</p>
        <?php
    }
    
    /**
     * Related videos count field
     */
    public function related_videos_count_callback() {
        $value = isset($this->options['related_videos_count']) ? $this->options['related_videos_count'] : '8';
        ?>
        <input type="number" 
               name="<?php echo $this->plugin_name; ?>[related_videos_count]" 
               value="<?php echo esc_attr($value); ?>" 
               min="4" 
               max="20" 
               class="small-text" />
        <p class="description">Cantidad de videos relacionados a mostrar (4-20).</p>
        <?php
    }
    
    /**
     * Debug mode field
     */
    public function debug_mode_callback() {
        $checked = isset($this->options['debug_mode']) ? $this->options['debug_mode'] : '0';
        ?>
        <label>
            <input type="checkbox" 
                   name="<?php echo $this->plugin_name; ?>[debug_mode]" 
                   value="1" 
                   <?php checked($checked, '1'); ?> />
            Activar modo debug (muestra información adicional en la consola)
        </label>
        <?php
    }
    
    /**
     * Sanitize settings before saving
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // API Token
        if (isset($input['api_token'])) {
            $sanitized['api_token'] = sanitize_text_field($input['api_token']);
        }
        
        // API URL
        if (isset($input['api_url'])) {
            $sanitized['api_url'] = trailingslashit(esc_url_raw(rtrim($input['api_url'], '/')));
            // Remove trailing slash for consistency
            $sanitized['api_url'] = rtrim($sanitized['api_url'], '/');
        }
        
        // Section
        if (isset($input['section'])) {
            $sanitized['section'] = in_array($input['section'], array('2', '5')) ? $input['section'] : '2';
        }
        
        // Videos per row
        if (isset($input['videos_per_row'])) {
            $sanitized['videos_per_row'] = in_array($input['videos_per_row'], array('3', '4', '5', '6')) ? $input['videos_per_row'] : '4';
        }
        
        // Enable autoplay
        $sanitized['enable_autoplay'] = isset($input['enable_autoplay']) ? '1' : '0';
        
        // Autoplay countdown
        if (isset($input['autoplay_countdown'])) {
            $countdown = intval($input['autoplay_countdown']);
            $sanitized['autoplay_countdown'] = max(3, min(30, $countdown));
        }
        
        // Show view more
        $sanitized['show_view_more'] = isset($input['show_view_more']) ? '1' : '0';
        
        // Enable search
        $sanitized['enable_search'] = isset($input['enable_search']) ? '1' : '0';
        
        // Search results page
        if (isset($input['search_results_page'])) {
            $sanitized['search_results_page'] = intval($input['search_results_page']);
        }
        
        // Search placeholder
        if (isset($input['search_placeholder'])) {
            $sanitized['search_placeholder'] = sanitize_text_field($input['search_placeholder']);
        }
        
        // Enable menu
        $sanitized['enable_menu'] = isset($input['enable_menu']) ? '1' : '0';
        
        // Menu text
        if (isset($input['menu_text'])) {
            $sanitized['menu_text'] = sanitize_text_field($input['menu_text']);
        }
        
        // Related videos count
        if (isset($input['related_videos_count'])) {
            $count = intval($input['related_videos_count']);
            $sanitized['related_videos_count'] = max(4, min(20, $count));
        }
        
        // Debug mode
        $sanitized['debug_mode'] = isset($input['debug_mode']) ? '1' : '0';
        
        return $sanitized;
    }
    
    /**
     * Display admin notices
     */
    public function admin_notices() {
        if (!isset($_GET['page']) || ($_GET['page'] !== $this->plugin_name && $_GET['page'] !== $this->plugin_name . '-order')) {
            return;
        }
        
        if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p>¡Configuración guardada exitosamente!</p>
            </div>
            <?php
        }
        
        if (isset($_GET['order-updated']) && $_GET['order-updated']) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p>¡Orden de programas actualizado exitosamente!</p>
            </div>
            <?php
        }
    }

    /**
     * Display the settings page
     */
    public function display_settings_page() {
        // Test API connection
        $api_status = $this->test_api_connection();
        
        ?>
        <div class="wrap">
            <h1>ADC Video Display - Configuración</h1>
            
            <?php if ($api_status['connection']): ?>
                <div class="notice notice-success">
                    <p><strong>Estado de API:</strong> Conexión exitosa ✓</p>
                    <?php if (isset($api_status['programs_count'])): ?>
                        <p>Programas disponibles: <?php echo $api_status['programs_count']; ?></p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="notice notice-error">
                    <p><strong>Estado de API:</strong> Error de conexión ✗</p>
                    <?php if (isset($api_status['error'])): ?>
                        <p>Error: <?php echo esc_html($api_status['error']); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields($this->plugin_name . '_group');
                do_settings_sections($this->plugin_name);
                submit_button();
                ?>
            </form>
            
            <div class="card" style="margin-top: 30px;">
                <h2>Información de Uso</h2>
                
                <h3>Shortcodes disponibles:</h3>
                <ul>
                    <li><code>[adc_content]</code> - Muestra el contenido principal</li>
                    <li><code>[adc_search_form]</code> - Muestra el formulario de búsqueda</li>
                    <li><code>[adc_programs_menu]</code> - Muestra el menú desplegable de programas</li>
                </ul>
                
                <h3>URLs del Sistema:</h3>
                <p>Este sitio está configurado para la sección: <strong><?php echo (isset($this->options['section']) && $this->options['section'] == '5') ? 'IA' : 'Kids'; ?></strong></p>
                <ul>
                    <li>Listado de programas: <code>/?</code></li>
                    <li>Ver programa: <code>/?categoria=nombre-programa</code></li>
                    <li>Ver video: <code>/?categoria=nombre-programa&video=nombre-video</code></li>
                    <li>Búsqueda: <code>/?adc_search=término</code></li>
                </ul>
                
                <h3>Endpoints Actuales:</h3>
                <?php 
                $section = isset($this->options['section']) ? $this->options['section'] : '2';
                $section_name = $section == '5' ? 'IA' : 'Kids';
                ?>
                <p>Sección activa: <strong><?php echo $section_name; ?></strong></p>
                
                <ul>
                    <?php if ($section == '5'): ?>
                        <li><code>/ia/categories</code> - Lista de categorías IA</li>
                        <li><code>/ia/categories/materials</code> - Materiales de categorías IA</li>
                    <?php else: ?>
                        <li><code>/programs</code> - Lista de programas</li>
                        <li><code>/programs/materials</code> - Materiales de programas</li>
                    <?php endif; ?>
                    <li><code>/advanced-search/materials</code> - Búsqueda de materiales</li>
                </ul>
                
                <?php if ($api_status['connection'] && isset($api_status['programs'])): ?>
                    <h3>Programas Disponibles:</h3>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Portada</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($api_status['programs'] as $program): ?>
                                <tr>
                                    <td><?php echo esc_html($program['id']); ?></td>
                                    <td><?php echo esc_html($program['name']); ?></td>
                                    <td>
                                        <?php if (isset($program['cover'])): ?>
                                            <img src="<?php echo esc_url($program['cover']); ?>" 
                                                 alt="<?php echo esc_attr($program['name']); ?>" 
                                                 style="width: 50px; height: 50px; object-fit: cover;">
                                        <?php else: ?>
                                            Sin portada
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Display the program order page
     */
    public function display_program_order_page() {
        // Include the necessary API class
        $api = new ADC_API();
        $programs = $api->get_programs();
        
        // Get saved order
        $saved_order = get_option('adc_programs_order', array());
        
        // If we have a saved order, reorder the programs array
        if (!empty($saved_order)) {
            // Create a lookup array for quick access
            $programs_lookup = array();
            foreach ($programs as $program) {
                $programs_lookup[$program['id']] = $program;
            }
            
            // Create a new ordered array
            $ordered_programs = array();
            
            // First add all programs that are in the saved order
            foreach ($saved_order as $program_id) {
                if (isset($programs_lookup[$program_id])) {
                    $ordered_programs[] = $programs_lookup[$program_id];
                    unset($programs_lookup[$program_id]);
                }
            }
            
            // Then add any remaining programs
            foreach ($programs_lookup as $program) {
                $ordered_programs[] = $program;
            }
            
            // Replace the original array
            $programs = $ordered_programs;
        }
        
        ?>
        <div class="wrap">
            <h1>Ordenar Programas</h1>
            
            <div class="notice notice-info">
                <p>Arrastra y suelta los programas para cambiar su orden de visualización en la página principal. El orden se guardará automáticamente.</p>
            </div>
            
            <?php if (empty($programs)): ?>
                <div class="notice notice-error">
                    <p>No se pudieron cargar los programas. Verifica la conexión a la API.</p>
                </div>
            <?php else: ?>
                <div id="program-order-container">
                    <ul id="sortable-programs" class="programs-order-list">
                        <?php foreach ($programs as $program): ?>
                            <li class="program-item" data-id="<?php echo esc_attr($program['id']); ?>">
                                <div class="program-handle dashicons dashicons-move"></div>
                                <?php if (isset($program['cover'])): ?>
                                    <img src="<?php echo esc_url($program['cover']); ?>" 
                                         alt="<?php echo esc_attr($program['name']); ?>" 
                                         class="program-thumbnail">
                                <?php else: ?>
                                    <div class="program-thumbnail-placeholder"></div>
                                <?php endif; ?>
                                <span class="program-name"><?php echo esc_html($program['name']); ?></span>
                                <span class="program-id">(ID: <?php echo esc_html($program['id']); ?>)</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <div id="order-save-status" class="hidden">
                    <div class="spinner is-active"></div>
                    <span class="message">Guardando...</span>
                </div>
                
                <script>
                jQuery(document).ready(function($) {
                    // Initialize sortable
                    $('#sortable-programs').sortable({
                        handle: '.program-handle',
                        update: function(event, ui) {
                            // Show saving indicator
                            $('#order-save-status').removeClass('hidden').removeClass('success').removeClass('error');
                            $('#order-save-status .message').text('Guardando...');
                            
                            // Get the new order
                            var programOrder = [];
                            $('.program-item').each(function() {
                                programOrder.push($(this).data('id'));
                            });
                            
                            // Save the new order via AJAX
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'adc_update_program_order',
                                    program_order: programOrder,
                                    nonce: '<?php echo wp_create_nonce('adc_admin_nonce'); ?>'
                                },
                                success: function(response) {
                                    if (response.success) {
                                        $('#order-save-status').addClass('success');
                                        $('#order-save-status .message').text('¡Orden guardado exitosamente!');
                                        
                                        // Hide message after a delay
                                        setTimeout(function() {
                                            $('#order-save-status').addClass('hidden');
                                        }, 3000);
                                    } else {
                                        $('#order-save-status').addClass('error');
                                        $('#order-save-status .message').text('Error al guardar orden.');
                                    }
                                },
                                error: function() {
                                    $('#order-save-status').addClass('error');
                                    $('#order-save-status .message').text('Error al guardar orden.');
                                }
                            });
                        }
                    });
                });
                </script>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Test API connection
     */
    private function test_api_connection() {
        $result = array(
            'connection' => false,
            'error' => null,
            'programs' => array(),
            'programs_count' => 0
        );
        
        if (!isset($this->options['api_token']) || empty($this->options['api_token'])) {
            $result['error'] = 'Token de API no configurado';
            return $result;
        }
        
        $api_url = isset($this->options['api_url']) ? $this->options['api_url'] : 'https://api.tutorah.tv/v1';
        $section = isset($this->options['section']) ? $this->options['section'] : '2';
        
        // Determine endpoint based on section
        $endpoint = $section == '5' ? '/ia/categories' : '/programs';
        
        $response = wp_remote_get($api_url . $endpoint, array(
            'headers' => array(
                'Authorization' => $this->options['api_token']
            ),
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            $result['error'] = $response->get_error_message();
            return $result;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error']) && $data['error']) {
            $result['error'] = isset($data['message']) ? $data['message'] : 'Error desconocido';
            return $result;
        }
        
        $result['connection'] = true;
        if (isset($data['data']) && is_array($data['data'])) {
            $result['programs'] = $data['data'];
            $result['programs_count'] = count($data['data']);
        }
        
        return $result;
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