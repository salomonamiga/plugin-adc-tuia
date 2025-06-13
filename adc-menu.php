<?php
/**
 * ADC Video Display - Menu Handler
 * 
 * Handles the dropdown menu functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ADC_Menu {
    
    private $api;
    private $cache = array(); // Add caching
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api = new ADC_API();
        
        // Register shortcode
        add_shortcode('adc_programs_menu', array($this, 'render_programs_menu'));
        
        // AJAX handlers
        add_action('wp_ajax_adc_get_programs_menu', array($this, 'ajax_get_programs'));
        add_action('wp_ajax_nopriv_adc_get_programs_menu', array($this, 'ajax_get_programs'));
        
        // WordPress menu integration hook
        add_action('init', array($this, 'maybe_integrate_with_wp_menu'));
    }
    
    /**
     * Render programs dropdown menu
     */
    public function render_programs_menu($atts) {
        $atts = shortcode_atts(array(
            'text' => 'Programas',
            'class' => 'adc-dropdown-menu',
            'show_count' => false
        ), $atts);
        
        // Check if API is configured
        if (!$this->api->is_configured()) {
            return '<div class="adc-error">API no configurada</div>';
        }
        
        $section_name = $this->api->get_section_name();
        
        // Start output with enhanced structure
        $output = '<div class="' . esc_attr($atts['class']) . '" data-section="' . esc_attr($section_name) . '">';
        $output .= '<button class="adc-dropdown-toggle" aria-expanded="false" aria-haspopup="true">';
        $output .= esc_html($atts['text']);
        
        // Add count if requested
        if ($atts['show_count']) {
            $programs_count = $this->get_programs_count();
            if ($programs_count > 0) {
                $output .= ' <span class="adc-programs-count">(' . $programs_count . ')</span>';
            }
        }
        
        $output .= ' <span class="adc-dropdown-arrow">▾</span>';
        $output .= '</button>';
        
        $output .= '<div class="adc-dropdown-content" id="adc-programs-dropdown" role="menu">';
        
        // Get programs with caching
        $programs = $this->get_cached_programs_for_menu();
        
        if (empty($programs)) {
            $output .= '<div class="adc-dropdown-empty" role="menuitem">No hay programas disponibles</div>';
        } else {
            foreach ($programs as $program) {
                $slug = $this->slugify($program['name']);
                $url = home_url('/?categoria=' . $slug);
                
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
 * AJAX handler to get programs - Enhanced with error handling and compatibility
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
    
    try {
        // Check if API is configured
        if (!$this->api->is_configured()) {
            wp_send_json_error(array(
                'message' => 'API not configured',
                'code' => 'API_NOT_CONFIGURED'
            ));
            return;
        }
        
        // Get programs with enhanced caching
        $programs = $this->get_cached_programs_for_menu();
        
        if (empty($programs)) {
            wp_send_json_error(array(
                'message' => 'No programs found',
                'code' => 'NO_PROGRAMS',
                'programs' => []
            ));
            return;
        }
        
        // Enhanced response data structure for better compatibility
        $response_data = array(
            'programs' => $programs,
            'count' => count($programs),
            'section' => $this->api->get_section_name(),
            'cache_time' => current_time('timestamp'),
            'success' => true,
            'version' => '2.1'
        );
        
        wp_send_json_success($response_data);
        
    } catch (Exception $e) {
        error_log('ADC Menu AJAX Error: ' . $e->getMessage());
        wp_send_json_error(array(
            'message' => 'Internal server error',
            'code' => 'SERVER_ERROR',
            'debug' => WP_DEBUG ? $e->getMessage() : null
        ));
    }
}
    
    /**
     * Get programs with caching - Optimized
     */
    private function get_cached_programs_for_menu() {
        $cache_key = 'programs_menu_' . $this->api->get_section();
        
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
        $programs = $this->api->get_all_programs_for_menu();
        
        // Cache for 5 minutes
        if (!empty($programs)) {
            set_transient($cache_key, $programs, 5 * MINUTE_IN_SECONDS);
            $this->cache[$cache_key] = $programs;
        }
        
        return $programs;
    }
    
    /**
     * Get programs count - Optimized
     */
    private function get_programs_count() {
        $programs = $this->get_cached_programs_for_menu();
        return count($programs);
    }
    
    /**
     * Convert title to slug - Consolidated (no more duplication)
     */
    private function slugify($text) {
        // Remove accents
        $text = remove_accents($text);
        // Convert to lowercase
        $text = strtolower($text);
        // Remove any character that is not alphanumeric, space, or dash
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        // Replace spaces with dashes
        $text = preg_replace('/[\s]+/', '-', $text);
        // Replace multiple dashes with a single dash
        $text = preg_replace('/[-]+/', '-', $text);
        // Trim dashes from beginning and end
        $text = trim($text, '-');
        
        return $text;
    }
    
    /**
     * WordPress menu integration - Enhanced
     */
    public function maybe_integrate_with_wp_menu() {
        // Only integrate if enabled in settings
        $options = get_option('adc-video-display');
        $enable_menu = isset($options['enable_menu']) ? $options['enable_menu'] : '1';
        
        if ($enable_menu === '1') {
            add_filter('wp_nav_menu_items', array($this, 'add_programs_to_menu'), 10, 2);
        }
    }
    
    /**
     * Add programs dropdown to WordPress menu - Enhanced
     */
    public function add_programs_to_menu($items, $args) {
        // Check if this is a main navigation menu
        $target_locations = array('primary', 'main', 'header', 'top');
        $is_main_menu = false;
        
        if (isset($args->theme_location) && in_array($args->theme_location, $target_locations)) {
            $is_main_menu = true;
        } elseif (isset($args->menu) && is_object($args->menu)) {
            $menu_name = strtolower($args->menu->name);
            $is_main_menu = (strpos($menu_name, 'main') !== false || 
                            strpos($menu_name, 'primary') !== false ||
                            strpos($menu_name, 'header') !== false);
        }
        
        if (!$is_main_menu) {
            return $items;
        }
        
        // Get menu text from settings
        $options = get_option('adc-video-display');
        $menu_text = isset($options['menu_text']) ? $options['menu_text'] : 'Programas';
        
        $menu_item = '<li class="menu-item menu-item-has-children adc-programs-menu-item">';
        $menu_item .= '<a href="#" class="adc_programs_menu_text" aria-expanded="false" aria-haspopup="true">' . esc_html($menu_text) . '</a>';
        $menu_item .= '<ul class="sub-menu adc-programs-submenu" id="adc-programs-submenu-wp" role="menu">';
        
        // Show loading state initially
        $menu_item .= '<li class="adc-loading" role="menuitem">Cargando programas...</li>';
        
        $menu_item .= '</ul>';
        $menu_item .= '</li>';
        
        return $items . $menu_item;
    }
    
    /**
     * Create standalone menu widget
     */
    public static function create_menu_widget() {
        add_action('widgets_init', function() {
            register_widget('ADC_Programs_Menu_Widget');
        });
    }
    
    /**
     * Clear menu cache
     */
    public function clear_cache() {
        $sections = array('2', '5'); // Kids and IA
        
        foreach ($sections as $section) {
            $cache_key = 'programs_menu_' . $section;
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
        
        $sections = array('2', '5');
        foreach ($sections as $section) {
            $cache_key = 'programs_menu_' . $section;
            $transient_exists = get_transient($cache_key) !== false;
            $stats['transient_cache_keys'][$cache_key] = $transient_exists;
        }
        
        return $stats;
    }
}

/**
 * Programs Menu Widget Class - Enhanced
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
                'description' => 'Menú desplegable de programas ADC',
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
        
        // Build shortcode
        $shortcode = '[adc_programs_menu';
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
ADC_Menu::create_menu_widget();