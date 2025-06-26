<?php
/**
 * ADC Video Display - Menu Handler
 * Version: 3.0 - Multiidioma (ES/EN únicamente)
 * 
 * Maneja la funcionalidad del menú desplegable para los 2 idiomas
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ADC_Menu {
    
    private $cache = array();
    
    /**
     * Constructor
     */
    public function __construct() {
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
     * Generic render programs dropdown menu
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
        
        // Get programs with caching
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
     * AJAX handler to get programs
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
            
            // Get programs with enhanced caching
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
                'success' => true,
                'version' => '3.0'
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
     * Get programs with caching
     */
    private function get_cached_programs_for_menu($language) {
        $cache_key = ADC_Utils::get_cache_key('programs_menu', $language);
        
        // Check internal cache first
        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }
        
        // Check WordPress transient cache
        $cached_programs = get_transient($cache_key);
        if ($cached_programs !== false) {
            $this->cache[$cache_key] = $cached_programs;
            return $cached_programs;
        }
        
        // Fetch fresh data
        $api = new ADC_API($language);
        $programs = $api->get_all_programs_for_menu();
        
        // Cache for 5 minutes
        if (!empty($programs)) {
            set_transient($cache_key, $programs, 5 * MINUTE_IN_SECONDS);
            $this->cache[$cache_key] = $programs;
        }
        
        return $programs;
    }
    
    /**
     * Get programs count
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
     * Clear menu cache
     */
    public function clear_cache() {
        $languages = ADC_Utils::get_valid_languages();
        
        foreach ($languages as $language) {
            $cache_key = ADC_Utils::get_cache_key('programs_menu', $language);
            delete_transient($cache_key);
            unset($this->cache[$cache_key]);
        }
    }
    
    /**
     * Get cache statistics for debugging
     */
    public function get_cache_stats() {
        $stats = array(
            'internal_cache_entries' => count($this->cache),
            'transient_cache_keys' => array()
        );
        
        $languages = ADC_Utils::get_valid_languages();
        foreach ($languages as $language) {
            $cache_key = ADC_Utils::get_cache_key('programs_menu', $language);
            $transient_exists = get_transient($cache_key) !== false;
            $stats['transient_cache_keys'][$cache_key] = $transient_exists;
        }
        
        return $stats;
    }
}

/**
 * Programs Menu Widget Class - Enhanced for multilanguage (ES/EN only)
 */
class ADC_Programs_Menu_Widget extends WP_Widget {
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(
            'adc_programs_menu_widget',
            'ADC Programs Menu',
            array(
                'description' => 'Menú desplegable de programas ADC - Multiidioma (ES/EN)',
                'classname' => 'adc-programs-menu-widget'
            )
        );
    }
    
    /**
     * Widget frontend
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
        <?php
    }
    
    /**
     * Update widget
     */
    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        $instance['menu_text'] = (!empty($new_instance['menu_text'])) ? sanitize_text_field($new_instance['menu_text']) : 'Programas';
        $instance['show_count'] = (!empty($new_instance['show_count'])) ? '1' : '0';
        $instance['custom_class'] = (!empty($new_instance['custom_class'])) ? sanitize_html_class($new_instance['custom_class']) : '';
        $instance['language'] = (!empty($new_instance['language']) && ADC_Utils::is_valid_language($new_instance['language'])) 
                                ? $new_instance['language'] : 'es';
        
        // Clear cache when widget is updated
        if (class_exists('ADC_Menu')) {
            $menu_instance = new ADC_Menu();
            $menu_instance->clear_cache();
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