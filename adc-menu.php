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
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api = new ADC_API();
        
        // Register shortcode
        add_shortcode('adc_programs_menu', array($this, 'render_programs_menu'));
        
        // AJAX handler
        add_action('wp_ajax_adc_get_programs_menu', array($this, 'ajax_get_programs'));
        add_action('wp_ajax_nopriv_adc_get_programs_menu', array($this, 'ajax_get_programs'));
    }
    
    /**
     * Render programs dropdown menu
     */
    public function render_programs_menu($atts) {
        $atts = shortcode_atts(array(
            'text' => 'Programas',
            'class' => 'adc-dropdown-menu'
        ), $atts);
        
        $section = $this->api->get_section();
        $section_name = $this->api->get_section_name();
        
        // Start output
        $output = '<div class="' . esc_attr($atts['class']) . '">';
        $output .= '<button class="adc-dropdown-toggle">' . esc_html($atts['text']) . '</button>';
        $output .= '<div class="adc-dropdown-content" id="adc-programs-dropdown">';
        
        // Get programs
        $programs = $this->api->get_all_programs_for_menu();
        
        if (empty($programs)) {
            $output .= '<div class="adc-dropdown-empty">No hay programas disponibles</div>';
        } else {
            foreach ($programs as $program) {
                $slug = $this->slugify($program['name']);
                
                // Use simplified URL for IA site
                $url = home_url('/?categoria=' . $slug);
                
                $output .= '<a href="' . esc_url($url) . '">';
                $output .= esc_html($program['name']);
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
        check_ajax_referer('adc_nonce', 'nonce');
        
        $programs = $this->api->get_all_programs_for_menu();
        
        wp_send_json_success($programs);
    }
    
    /**
     * Convert title to slug
     */
    private function slugify($text) {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = trim($text, '-');
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = strtolower($text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        return $text;
    }
    
    /**
     * Create WordPress menu integration
     */
    public static function create_menu_integration() {
        add_action('wp_nav_menu_items', array(__CLASS__, 'add_programs_to_menu'), 10, 2);
    }
    
    /**
     * Add programs dropdown to WordPress menu
     */
    public static function add_programs_to_menu($items, $args) {
        // Check if this is the main menu
        if ($args->theme_location == 'primary' || $args->menu->slug == 'main-menu') {
            $menu_item = '<li class="menu-item menu-item-has-children adc-programs-menu-item">';
            $menu_item .= '<a href="#">Programas</a>';
            $menu_item .= '<ul class="sub-menu adc-programs-submenu" id="adc-programs-submenu-wp">';
            
            // This will be populated via JavaScript
            $menu_item .= '<li class="adc-loading">Cargando programas...</li>';
            
            $menu_item .= '</ul>';
            $menu_item .= '</li>';
            
            $items .= $menu_item;
        }
        
        return $items;
    }
}

// Initialize the menu class
new ADC_Menu();

// Add WordPress menu integration
ADC_Menu::create_menu_integration();