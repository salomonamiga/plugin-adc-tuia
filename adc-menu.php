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
        
        // Add inline JavaScript for dropdown functionality
        $output .= '<script>
        (function() {
            var dropdown = document.querySelector(".adc-dropdown-menu");
            var button = dropdown.querySelector(".adc-dropdown-toggle");
            var content = dropdown.querySelector(".adc-dropdown-content");
            
            button.addEventListener("click", function(e) {
                e.preventDefault();
                dropdown.classList.toggle("active");
            });
            
            // Close dropdown when clicking outside
            document.addEventListener("click", function(e) {
                if (!dropdown.contains(e.target)) {
                    dropdown.classList.remove("active");
                }
            });
            
            // Close dropdown when selecting an option
            var links = content.querySelectorAll("a");
            links.forEach(function(link) {
                link.addEventListener("click", function() {
                    dropdown.classList.remove("active");
                });
            });
        })();
        </script>';
        
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
            
            // Add JavaScript to populate the menu
            $menu_item .= '<script>
            document.addEventListener("DOMContentLoaded", function() {
                // Only run if we have the submenu
                var submenu = document.getElementById("adc-programs-submenu-wp");
                if (!submenu) return;
                
                // Fetch programs
                var xhr = new XMLHttpRequest();
                xhr.open("POST", "' . admin_url('admin-ajax.php') . '", true);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        var response = JSON.parse(xhr.responseText);
                        if (response.success && response.data) {
                            submenu.innerHTML = "";
                            
                            response.data.forEach(function(program) {
                                var li = document.createElement("li");
                                var a = document.createElement("a");
                                
                                // Create slug
                                var slug = program.name.toLowerCase()
                                    .replace(/[^a-z0-9]+/g, "-")
                                    .replace(/(^-|-$)/g, "");
                                
                                // Use simplified URL for IA site
                                a.href = "' . home_url('/') . '?categoria=" + slug;
                                
                                a.textContent = program.name;
                                li.appendChild(a);
                                submenu.appendChild(li);
                            });
                        }
                    }
                };
                
                var params = "action=adc_get_programs_menu&nonce=' . wp_create_nonce('adc_nonce') . '";
                xhr.send(params);
            });
            </script>';
            
            $items .= $menu_item;
        }
        
        return $items;
    }
}

// Initialize the menu class
new ADC_Menu();

// Add WordPress menu integration
ADC_Menu::create_menu_integration();