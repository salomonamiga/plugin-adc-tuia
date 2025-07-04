<?php
/**
 * ADC Video Display - Menu Handler
 * Version: 3.2 - Cache Unificado - RESPETA configuración admin
 * 
 * Maneja la funcionalidad del menú desplegable para los 2 idiomas
 * ARREGLADO: Cache de menú usa misma duración configurada (6 horas)
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ADC_Menu {
    
    private $cache = array();
    private $options;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->options = get_option('adc-video-display');
        
        // Register shortcodes for menus in different languages (only ES and EN)
        add_shortcode('adc_programs_menu', array($this, 'render_programs_menu'));
        add_shortcode('adc_programs_menu_en', array($this, 'render_programs_menu_en'));
        
        // AJAX handlers for each language
        add_action('wp_ajax_adc_get_programs_menu', array($this, 'ajax_get_programs'));
        add_action('wp_ajax_nopriv_adc_get_programs_menu', array($this, 'ajax_get_programs'));
        
        // WordPress menu integration hook
        add_action('init', array($this, 'setup_menu_integration'));
    }
    
    /**
     * ARREGLADO: Check if cache is enabled from admin settings
     */
    private function is_cache_enabled()
    {
        return isset($this->options['enable_cache']) && $this->options['enable_cache'] === '1';
    }

    /**
     * ARREGLADO: Get UNIFIED cache duration - SAME as API cache
     */
    private function get_cache_duration()
    {
        if (!$this->is_cache_enabled()) {
            return 0; // No cache
        }

        $hours = isset($this->options['cache_duration']) ? floatval($this->options['cache_duration']) : 6;
        $hours = max(0.5, min(24, $hours)); // Clamp between 30 minutes and 24 hours

        return intval($hours * HOUR_IN_SECONDS);
    }
    
    /**
     * Render programs dropdown menu for Spanish
     */
    public function render_programs_menu($atts) {
        return $this->render_programs_menu_generic('es', $atts);
    }
    
    /**
     * Render programs dropdown menu for English
     */
    public function render_programs_menu_en($atts) {
        return $this->render_programs_menu_generic('en', $atts);
    }
    
    /**
     * OPTIMIZADO: Generic render programs dropdown menu con cache unificado
     */
    private function render_programs_menu_generic($language, $atts) {
        $atts = shortcode_atts(array(
            'text' => ADC_Utils::get_text('programs', $language),
            'class' => 'adc-dropdown-menu',
            'show_count' => false
        ), $atts);
        
        // Create API instance for the language
        $api = new ADC_API($language);
        
        // Check if API is configured
        if (!$api->is_configured()) {
            return '<div class="adc-error">API no configurada</div>';
        }
        
        // Start output
        $output = '<div class="' . esc_attr($atts['class']) . ' adc-menu-' . $language . '" data-language="' . esc_attr($language) . '">';
        $output .= '<button class="adc-dropdown-toggle" aria-expanded="false" aria-haspopup="true">';
        $output .= esc_html($atts['text']);
        
        // Add count if requested
        if ($atts['show_count']) {
            $programs_count = $this->get_programs_count($language);
            if ($programs_count > 0) {
                $output .= ' <span class="adc-programs-count">(' . $programs_count . ')</span>';
            }
        }
        
        $output .= ' <span class="adc-dropdown-arrow">▾</span>';
        $output .= '</button>';
        
        $output .= '<div class="adc-dropdown-content" id="adc-programs-dropdown-' . $language . '" role="menu">';
        
        // ARREGLADO: Get programs with UNIFIED caching duration
        $programs = $this->get_cached_programs_for_menu($language);
        
        if (empty($programs)) {
            $output .= '<div class="adc-dropdown-empty" role="menuitem">' . ADC_Utils::get_text('no_programs', $language) . '</div>';
        } else {
            $base_url = ADC_Utils::get_base_url($language);
            
            foreach ($programs as $program) {
                $slug = ADC_Utils::slugify($program['name']);
                $url = ADC_Utils::build_category_url($slug, $language);
                
                $output .= '<a href="' . esc_url($url) . '" role="menuitem" class="adc-dropdown-item">';
                $output .= '<span class="adc-program-name">' . esc_html($program['name']) . '</span>';
                $output .= '</a>';
            }
        }
        
        $output .= '</div>';
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * OPTIMIZADO: AJAX handler to get programs con cache unificado
     */
    public function ajax_get_programs() {
        // Enhanced nonce verification
        if (isset($_POST['nonce']) && !empty($_POST['nonce'])) {
            if (!wp_verify_nonce($_POST['nonce'], 'adc_nonce')) {
                wp_send_json_error(array(
                    'message' => 'Invalid nonce',
                    'code' => 'INVALID_NONCE'
                ));
                return;
            }
        }
        
        $language = isset($_POST['language']) ? ADC_Utils::validate_language($_POST['language']) : 'es';
        
        try {
            // Create API instance for the language
            $api = new ADC_API($language);
            
            // Check if API is configured
            if (!$api->is_configured()) {
                wp_send_json_error(array(
                    'message' => 'API not configured',
                    'code' => 'API_NOT_CONFIGURED'
                ));
                return;
            }
            
            // ARREGLADO: Get programs with UNIFIED cache (NO más cache diferente)
            $programs = $this->get_cached_programs_for_menu($language);
            
            if (empty($programs)) {
                wp_send_json_error(array(
                    'message' => 'No programs found',
                    'code' => 'NO_PROGRAMS',
                    'programs' => []
                ));
                return;
            }
            
            // Enhanced response data structure
            $response_data = array(
                'programs' => $programs,
                'count' => count($programs),
                'language' => $language,
                'cache_time' => current_time('timestamp'),
                'cache_duration' => $this->get_cache_duration(),
                'success' => true,
                'version' => '3.2'
            );
            
            wp_send_json_success($response_data);
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Internal server error',
                'code' => 'SERVER_ERROR',
                'debug' => WP_DEBUG ? $e->getMessage() : null
            ));
        }
    }
    
    /**
     * CRÍTICO ARREGLADO: Get programs with UNIFIED cache duration
     */
    private function get_cached_programs_for_menu($language) {
        $cache_key = ADC_Utils::get_cache_key('programs_menu', $language);
        
        // Check internal cache first
        if (isset($this->cache[$cache_key])) {
            if (isset($this->options['debug_mode']) && $this->options['debug_mode'] === '1') {
                ADC_Utils::debug_log("MENU CACHE HIT (internal): {$cache_key}");
            }
            return $this->cache[$cache_key];
        }
        
        // Check WordPress transient cache
        $cached_programs = get_transient($cache_key);
        if ($cached_programs !== false) {
            $this->cache[$cache_key] = $cached_programs;
            if (isset($this->options['debug_mode']) && $this->options['debug_mode'] === '1') {
                ADC_Utils::debug_log("MENU CACHE HIT (transient): {$cache_key}");
            }
            return $cached_programs;
        }
        
        // CACHE MISS - Fetch fresh data
        if (isset($this->options['debug_mode']) && $this->options['debug_mode'] === '1') {
            ADC_Utils::debug_log("MENU CACHE MISS - Getting fresh data: {$cache_key}");
        }
        
        $api = new ADC_API($language);
        $programs = $api->get_all_programs_for_menu();
        
        // ARREGLADO: Cache con duración UNIFICADA (NO más 5 minutos diferentes)
        if (!empty($programs) && $this->is_cache_enabled()) {
            $cache_duration = $this->get_cache_duration();
            set_transient($cache_key, $programs, $cache_duration);
            $this->cache[$cache_key] = $programs;
            
            if (isset($this->options['debug_mode']) && $this->options['debug_mode'] === '1') {
                ADC_Utils::debug_log("MENU CACHE STORED: {$cache_key} for {$cache_duration} seconds");
            }
        }
        
        return $programs;
    }
    
    /**
     * OPTIMIZADO: Get programs count usando cache existente
     */
    private function get_programs_count($language) {
        $programs = $this->get_cached_programs_for_menu($language);
        return count($programs);
    }

    /**
     * WordPress menu integration
     */
    public function setup_menu_integration() {
        // Nothing to do here anymore - menu integration handled by JavaScript
    }
    
    /**
     * ARREGLADO: Clear menu cache con debug logging
     */
    public function clear_cache() {
        $languages = ADC_Utils::get_valid_languages();
        
        foreach ($languages as $language) {
            $cache_key = ADC_Utils::get_cache_key('programs_menu', $language);
            delete_transient($cache_key);
            unset($this->cache[$cache_key]);
            
            if (isset($this->options['debug_mode']) && $this->options['debug_mode'] === '1') {
                ADC_Utils::debug_log("MENU CACHE CLEARED: {$cache_key}");
            }
        }
    }
    
    /**
     * MEJORADO: Get cache statistics for debugging
     */
    public function get_cache_stats() {
        $stats = array(
            'internal_cache_entries' => count($this->cache),
            'transient_cache_keys' => array(),
            'cache_enabled' => $this->is_cache_enabled(),
            'cache_duration_hours' => $this->get_cache_duration() / HOUR_IN_SECONDS
        );
        
        $languages = ADC_Utils::get_valid_languages();
        foreach ($languages as $language) {
            $cache_key = ADC_Utils::get_cache_key('programs_menu', $language);
            $transient_exists = get_transient($cache_key) !== false;
            $stats['transient_cache_keys'][$cache_key] = $transient_exists;
        }
        
        return $stats;
    }

    /**
     * NUEVO: Force refresh menu cache for specific language
     */
    public function force_refresh_menu_cache($language) {
        $cache_key = ADC_Utils::get_cache_key('programs_menu', $language);
        
        // Clear existing cache
        delete_transient($cache_key);
        unset($this->cache[$cache_key]);
        
        // Force fresh load
        $programs = $this->get_cached_programs_for_menu($language);
        
        return array(
            'success' => !empty($programs),
            'programs_count' => count($programs),
            'language' => $language,
            'cache_key' => $cache_key,
            'cache_duration' => $this->get_cache_duration(),
            'timestamp' => current_time('mysql')
        );
    }

    /**
     * NUEVO: Get menu performance info
     */
    public function get_menu_performance_info() {
        $info = array(
            'cache_enabled' => $this->is_cache_enabled(),
            'cache_duration_hours' => $this->get_cache_duration() / HOUR_IN_SECONDS,
            'internal_cache_entries' => count($this->cache)
        );

        if ($this->is_cache_enabled()) {
            $cache_stats = $this->get_cache_stats();
            $info['transient_count'] = count(array_filter($cache_stats['transient_cache_keys']));
            $info['performance_mode'] = 'optimized';
        } else {
            $info['performance_mode'] = 'real-time';
            $info['warning'] = 'Menu cache disabled - AJAX requests on every dropdown';
        }

        return $info;
    }

    /**
     * NUEVO: Warm up menu cache for all languages
     */
    public function warm_up_menu_cache() {
        if (!$this->is_cache_enabled()) {
            return array('success' => false, 'reason' => 'cache_disabled');
        }

        try {
            $warmed_languages = array();
            $start_time = microtime(true);

            foreach (ADC_Utils::get_valid_languages() as $language) {
                $programs = $this->get_cached_programs_for_menu($language);
                if (!empty($programs)) {
                    $warmed_languages[] = $language . ' (' . count($programs) . ' programs)';
                }
            }

            $end_time = microtime(true);
            $duration = round(($end_time - $start_time) * 1000);

            return array(
                'success' => true,
                'languages_warmed' => $warmed_languages,
                'duration_ms' => $duration,
                'cache_duration' => $this->get_cache_duration(),
                'timestamp' => current_time('mysql')
            );

        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * NUEVO: Check if menu cache needs refresh
     */
    public function needs_menu_cache_refresh() {
        if (!$this->is_cache_enabled()) {
            return true; // Always needs refresh if cache disabled
        }

        $languages = ADC_Utils::get_valid_languages();
        foreach ($languages as $language) {
            $cache_key = ADC_Utils::get_cache_key('programs_menu', $language);
            if (get_transient($cache_key) === false) {
                return true; // At least one language cache is missing
            }
        }

        return false; // All caches are present
    }
}

/**
 * Programs Menu Widget Class - OPTIMIZADO con cache unificado
 */
class ADC_Programs_Menu_Widget extends WP_Widget {
    
    private $options;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->options = get_option('adc-video-display');
        
        parent::__construct(
            'adc_programs_menu_widget',
            'ADC Programs Menu',
            array(
                'description' => 'Menú desplegable de programas ADC - Multiidioma (ES/EN) - Cache Unificado',
                'classname' => 'adc-programs-menu-widget'
            )
        );
    }
    
    /**
     * OPTIMIZADO: Widget frontend con cache unificado
     */
    public function widget($args, $instance) {
        echo $args['before_widget'];
        
        if (!empty($instance['title'])) {
            echo $args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title'];
        }
        
        // Get language
        $language = !empty($instance['language']) ? ADC_Utils::validate_language($instance['language']) : 'es';
        
        // Configure shortcode attributes
        $shortcode_atts = array();
        
        if (!empty($instance['menu_text'])) {
            $shortcode_atts[] = 'text="' . esc_attr($instance['menu_text']) . '"';
        }
        
        if (!empty($instance['show_count']) && $instance['show_count'] === '1') {
            $shortcode_atts[] = 'show_count="true"';
        }
        
        if (!empty($instance['custom_class'])) {
            $shortcode_atts[] = 'class="' . esc_attr($instance['custom_class']) . '"';
        }
        
        // Build shortcode based on language
        $shortcode_name = 'adc_programs_menu';
        if ($language === 'en') {
            $shortcode_name = 'adc_programs_menu_en';
        }
        
        $shortcode = '[' . $shortcode_name;
        if (!empty($shortcode_atts)) {
            $shortcode .= ' ' . implode(' ', $shortcode_atts);
        }
        $shortcode .= ']';
        
        echo do_shortcode($shortcode);
        
        echo $args['after_widget'];
    }
    
    /**
     * Widget backend
     */
    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : 'Programas';
        $menu_text = !empty($instance['menu_text']) ? $instance['menu_text'] : 'Programas';
        $show_count = !empty($instance['show_count']) ? $instance['show_count'] : '0';
        $custom_class = !empty($instance['custom_class']) ? $instance['custom_class'] : '';
        $language = !empty($instance['language']) ? $instance['language'] : 'es';
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">Título del Widget:</label>
            <input class="widefat" 
                   id="<?php echo esc_attr($this->get_field_id('title')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>" 
                   type="text" 
                   value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('language')); ?>">Idioma:</label>
            <select class="widefat" 
                    id="<?php echo esc_attr($this->get_field_id('language')); ?>" 
                    name="<?php echo esc_attr($this->get_field_name('language')); ?>">
                <option value="es" <?php selected($language, 'es'); ?>>Español</option>
                <option value="en" <?php selected($language, 'en'); ?>>English</option>
            </select>
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('menu_text')); ?>">Texto del Menú:</label>
            <input class="widefat" 
                   id="<?php echo esc_attr($this->get_field_id('menu_text')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('menu_text')); ?>" 
                   type="text" 
                   value="<?php echo esc_attr($menu_text); ?>">
        </p>
        <p>
            <input class="checkbox" 
                   type="checkbox" 
                   <?php checked($show_count, '1'); ?> 
                   id="<?php echo esc_attr($this->get_field_id('show_count')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('show_count')); ?>" 
                   value="1">
            <label for="<?php echo esc_attr($this->get_field_id('show_count')); ?>">Mostrar cantidad de programas</label>
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('custom_class')); ?>">Clase CSS personalizada:</label>
            <input class="widefat" 
                   id="<?php echo esc_attr($this->get_field_id('custom_class')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('custom_class')); ?>" 
                   type="text" 
                   value="<?php echo esc_attr($custom_class); ?>"
                   placeholder="adc-dropdown-menu">
        </p>
        
        <?php if (isset($this->options['enable_cache']) && $this->options['enable_cache'] === '1'): ?>
        <p style="background: #e8f4fd; padding: 10px; border-left: 4px solid #2196f3; font-size: 12px;">
            <strong>ℹ️ Cache Unificado:</strong> Este menú usa cache de <?php echo isset($this->options['cache_duration']) ? $this->options['cache_duration'] : '6'; ?> horas configurado en el admin.
        </p>
        <?php endif; ?>
        <?php
    }
    
    /**
     * ARREGLADO: Update widget with cache clearing
     */
    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        $instance['menu_text'] = (!empty($new_instance['menu_text'])) ? sanitize_text_field($new_instance['menu_text']) : 'Programas';
        $instance['show_count'] = (!empty($new_instance['show_count'])) ? '1' : '0';
        $instance['custom_class'] = (!empty($new_instance['custom_class'])) ? sanitize_html_class($new_instance['custom_class']) : '';
        $instance['language'] = (!empty($new_instance['language']) && ADC_Utils::is_valid_language($new_instance['language'])) 
                                ? $new_instance['language'] : 'es';
        
        // ARREGLADO: Clear cache when widget is updated (usando instancia correcta)
        if (class_exists('ADC_Menu')) {
            $menu_instance = new ADC_Menu();
            $menu_instance->clear_cache();
            
            if (isset($this->options['debug_mode']) && $this->options['debug_mode'] === '1') {
                ADC_Utils::debug_log("Menu widget updated - cache cleared");
            }
        }
        
        return $instance;
    }
}

// Initialize the menu class
new ADC_Menu();

// Create menu widget
add_action('widgets_init', function() {
    register_widget('ADC_Programs_Menu_Widget');
});