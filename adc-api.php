<?php
/**
 * ADC Video Display - API Handler
 * 
 * Handles all API requests to TuTorah TV
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ADC_API {
    
    private $api_token;
    private $api_url;
    private $section;
    private $debug_mode = false;
    private $debug_info = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $options = get_option('adc-video-display');
        
        $this->api_token = isset($options['api_token']) ? $options['api_token'] : '';
        $this->api_url = isset($options['api_url']) ? $options['api_url'] : 'https://api.tutorah.tv/v1';
        $this->section = isset($options['section']) ? $options['section'] : '2';
    }
    
    /**
     * Enable debug mode
     */
    public function enable_debug() {
        $this->debug_mode = true;
    }
    
    /**
     * Get debug info
     */
    public function get_debug_info() {
        return $this->debug_info;
    }
    
    /**
     * Add debug message
     */
    private function add_debug($message) {
        if ($this->debug_mode) {
            $this->debug_info[] = $message;
        }
    }
    
    /**
     * Make API request
     */
    private function make_request($endpoint, $params = array()) {
        $url = $this->api_url . $endpoint;
        
        if (!empty($params)) {
            $url = add_query_arg($params, $url);
        }
        
        $this->add_debug('Request URL: ' . $url);
        
        $args = array(
            'headers' => array(
                'Authorization' => $this->api_token
            ),
            'timeout' => 20
        );
        
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            $this->add_debug('WP Error: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error']) && $data['error']) {
            $this->add_debug('API Error: ' . $data['message']);
            return false;
        }
        
        return $data;
    }
    
    /**
     * Get programs/categories based on section
     */
    public function get_programs() {
        $endpoint = $this->section == '5' ? '/ia/categories' : '/programs';
        $data = $this->make_request($endpoint);
        
        if (!$data || !isset($data['data'])) {
            return array();
        }
        
        // Filter programs based on section
        $programs = $data['data'];
        
        if ($this->section == '5') {
            // Filter IA programs (those with _ia.png in cover)
            $programs = array_filter($programs, function($program) {
                return isset($program['cover']) && strpos($program['cover'], '_ia.png') !== false;
            });
        } else {
            // Filter Kids programs (those with _infantil.png in cover)
            $programs = array_filter($programs, function($program) {
                return isset($program['cover']) && strpos($program['cover'], '_infantil.png') !== false;
            });
        }
        
        return array_values($programs);
    }
    
    /**
     * Get materials for a specific program
     */
    public function get_materials($program_id) {
        if ($this->section == '5') {
            $endpoint = '/ia/categories/materials';
            $params = array('category' => $program_id);
        } else {
            $endpoint = '/programs/materials';
            $params = array('program' => $program_id);
        }
        
        $data = $this->make_request($endpoint, $params);
        
        if (!$data || !isset($data['data'])) {
            return array();
        }
        
        return $data['data'];
    }
    
    /**
     * Get a single program by ID
     */
    public function get_program_by_id($program_id) {
        $programs = $this->get_programs();
        
        foreach ($programs as $program) {
            if ($program['id'] == $program_id) {
                return $program;
            }
        }
        
        return null;
    }
    
    /**
     * Get materials for a specific program and season
     */
    public function get_materials_by_season($program_id, $season) {
        $materials = $this->get_materials($program_id);
        
        if (empty($materials)) {
            return array();
        }
        
        // Filter by season if specified
        if ($season !== null) {
            $materials = array_filter($materials, function($material) use ($season) {
                return isset($material['season']) && $material['season'] == $season;
            });
        }
        
        return array_values($materials);
    }
    
    /**
     * Search material by ID
     */
    public function get_material_by_id($material_id) {
        $endpoint = '/advanced-search/materials';
        $params = array('id' => $material_id);
        
        $data = $this->make_request($endpoint, $params);
        
        if (!$data || !isset($data['data']) || empty($data['data'])) {
            return null;
        }
        
        return $data['data'][0];
    }
    
    /**
     * Search materials by text
     */
    public function search_materials($search_text) {
        $endpoint = '/advanced-search/materials';
        $params = array(
            'section' => $this->section,
            'text' => $search_text
        );
        
        $data = $this->make_request($endpoint, $params);
        
        if (!$data || !isset($data['data'])) {
            return array();
        }
        
        return $data['data'];
    }
    
    /**
     * Get all programs for menu
     */
    public function get_all_programs_for_menu() {
        $programs = $this->get_programs();
        
        // Sort programs alphabetically
        usort($programs, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });
        
        return $programs;
    }
    
    /**
     * Get all programs with custom order for home display
     */
    public function get_programs_with_custom_order() {
        $programs = $this->get_programs();
        
        // Get custom order from WordPress option
        $order = get_option('adc_programs_order', array());
        
        if (!empty($order)) {
            // Create a lookup array with program IDs as keys and sort order as values
            $order_lookup = array();
            foreach ($order as $index => $program_id) {
                $order_lookup[$program_id] = $index;
            }
            
            // Sort the programs based on the custom order
            usort($programs, function($a, $b) use ($order_lookup) {
                // If both programs have custom order
                if (isset($order_lookup[$a['id']]) && isset($order_lookup[$b['id']])) {
                    return $order_lookup[$a['id']] - $order_lookup[$b['id']];
                }
                // If only first program has custom order, it comes first
                elseif (isset($order_lookup[$a['id']])) {
                    return -1;
                }
                // If only second program has custom order, it comes first
                elseif (isset($order_lookup[$b['id']])) {
                    return 1;
                }
                // If neither has custom order, use alphabetical order as fallback
                else {
                    return strcmp($a['name'], $b['name']);
                }
            });
        }
        
        return $programs;
    }
    
    /**
     * Get current section
     */
    public function get_section() {
        return $this->section;
    }
    
    /**
     * Get section name
     */
    public function get_section_name() {
        return $this->section == '5' ? 'IA' : 'Kids';
    }
    
    /**
     * Format video duration from HH:MM:SS to human readable
     */
    public function format_duration($duration) {
        $parts = explode(':', $duration);
        
        if (count($parts) == 3) {
            $hours = intval($parts[0]);
            $minutes = intval($parts[1]);
            
            if ($hours > 0) {
                return $hours . 'h ' . $minutes . 'min';
            } else {
                return $minutes . ' min';
            }
        }
        
        return $duration;
    }
    
    /**
     * Get video thumbnail URL
     */
    public function get_thumbnail_url($video_id) {
        return "https://s3apics.streamgates.net/TutorahTV_Thumbs/{$video_id}_50.jpg";
    }
    
    /**
     * Get video player URL
     */
    public function get_video_url($video) {
        // The video URL comes directly from the API
        return isset($video['video']) ? $video['video'] : '';
    }
    
    /**
     * Get season names mapping
     */
    public function get_season_names() {
        return array(
            1 => "Temporada 1",
            2 => "Temporada 2",
            3 => "Temporada 3",
            4 => "Temporada 4",
            5 => "Temporada 5",
            6 => "Bereshit",
            7 => "Shemot",
            8 => "Vaikra",
            9 => "Bamidbar",
            10 => "Debarim",
            11 => "Pesaj",
            12 => "Lag Baomer",
            13 => "Shabuot",
            14 => "Rosh Hashana",
            15 => "Kipur",
            16 => "Sucot",
            17 => "Simjat Torah",
            18 => "Januca",
            19 => "Tu Bishvat",
            20 => "Purim",
            21 => "Ayunos",
            22 => "Bereshit - Español",
            23 => "Bereshit - Hebreo",
            24 => "Bereshit - Ingles",
            25 => "Shemot - Español",
            26 => "Shemot - Hebreo",
            27 => "Shemot - Ingles",
            28 => "Vaikra - Español",
            29 => "Vaikra - Hebreo",
            30 => "Vaikra - Ingles",
            31 => "Bamidbar - Español",
            32 => "Bamidbar - Hebreo",
            33 => "Bamidbar - Ingles",
            34 => "Debarim - Español",
            35 => "Debarim - Hebreo",
            36 => "Debarim - Ingles",
            37 => "Jaguim - Español",
            38 => "Jaguim - Hebreo",
            39 => "Jaguim - Ingles",
            40 => "Jaguim"
        );
    }
    
    /**
     * Get season name by number
     */
    public function get_season_name($season_number) {
        $seasons = $this->get_season_names();
        
        return isset($seasons[$season_number]) 
            ? $seasons[$season_number] 
            : 'Temporada ' . intval($season_number);
    }
    
    /**
     * Group materials by season
     */
    public function group_materials_by_season($materials) {
        $grouped = array();
        
        foreach ($materials as $material) {
            $season = isset($material['season']) ? $material['season'] : 0;
            
            if (!isset($grouped[$season])) {
                $grouped[$season] = array();
            }
            
            $grouped[$season][] = $material;
        }
        
        // Sort seasons
        ksort($grouped);
        
        return $grouped;
    }
    
    /**
     * Group search results by category
     */
    public function group_search_results_by_category($results) {
        $grouped = array();
        
        foreach ($results as $result) {
            $category = isset($result['category']) ? $result['category'] : 'Sin categoría';
            
            if (!isset($grouped[$category])) {
                $grouped[$category] = array();
            }
            
            $grouped[$category][] = $result;
        }
        
        // Sort categories alphabetically
        ksort($grouped);
        
        return $grouped;
    }
    
    /**
     * Check if API is configured
     */
    public function is_configured() {
        return !empty($this->api_token) && !empty($this->api_url);
    }
    
    /**
     * Test API connection
     */
    public function test_connection() {
        if (!$this->is_configured()) {
            return array(
                'success' => false,
                'error' => 'API no configurada'
            );
        }
        
        $programs = $this->get_programs();
        
        if ($programs === false) {
            return array(
                'success' => false,
                'error' => 'Error al conectar con la API'
            );
        }
        
        return array(
            'success' => true,
            'programs_count' => count($programs)
        );
    }
}