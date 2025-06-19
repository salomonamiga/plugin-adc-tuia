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

class ADC_API
{

    private $api_token;
    private $api_url;
    private $section;
    private $debug_mode = false;
    private $debug_info = array();
    private $cache = array(); // Add simple caching

    /**
     * Constructor
     */
    public function __construct()
    {
        $options = get_option('adc-video-display');

        $this->api_token = isset($options['api_token']) ? $options['api_token'] : '';
        $this->api_url = isset($options['api_url']) ? $options['api_url'] : 'https://api.tutorah.tv/v1';
        $this->section = isset($options['section']) ? $options['section'] : '2';
        $this->debug_mode = isset($options['debug_mode']) ? $options['debug_mode'] : false;
    }

    /**
     * Enable debug mode
     */
    public function enable_debug()
    {
        $this->debug_mode = true;
    }

    /**
     * Get debug info
     */
    public function get_debug_info()
    {
        return $this->debug_info;
    }

    /**
     * Add debug message
     */
    private function add_debug($message)
    {
        if ($this->debug_mode) {
            $this->debug_info[] = '[' . date('H:i:s') . '] ' . $message;
        }
    }

    /**
     * Make API request with improved error handling and caching
     */
    private function make_request($endpoint, $params = array(), $cache_key = null)
    {
        // Check cache first
        if ($cache_key && isset($this->cache[$cache_key])) {
            $this->add_debug('Cache hit for: ' . $cache_key);
            return $this->cache[$cache_key];
        }

        $url = $this->api_url . $endpoint;

        if (!empty($params)) {
            $url = add_query_arg($params, $url);
        }

        $this->add_debug('Request URL: ' . $url);

        $args = array(
            'headers' => array(
                'Authorization' => $this->api_token,
                'User-Agent' => 'ADC-WordPress-Plugin/2.1'
            ),
            'timeout' => 30, // Increased timeout
            'sslverify' => true
        );

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->add_debug('WP Error: ' . $error_message);
            return false;
        }

        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            $this->add_debug('HTTP Error: ' . $http_code);
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->add_debug('JSON Error: ' . json_last_error_msg());
            return false;
        }

        if (isset($data['error']) && $data['error']) {
            $this->add_debug('API Error: ' . (isset($data['message']) ? $data['message'] : 'Unknown error'));
            return false;
        }

        // Cache successful response
        if ($cache_key) {
            $this->cache[$cache_key] = $data;
        }

        return $data;
    }

    /**
     * Get programs/categories based on section (with filtering)
     */
    public function get_programs()
    {
        $cache_key = 'programs_' . $this->section;
        $endpoint = $this->section == '5' ? '/ia/categories' : '/programs';
        $data = $this->make_request($endpoint, array(), $cache_key);

        if (!$data || !isset($data['data'])) {
            return array();
        }

        return $this->filter_programs_by_section($data['data']);
    }

    /**
     * Get ALL programs from API (without filtering, for admin use)
     */
    public function get_all_programs_from_api()
    {
        $cache_key = 'all_programs_' . $this->section;
        $endpoint = $this->section == '5' ? '/ia/categories/all' : '/programs';
        $data = $this->make_request($endpoint, array(), $cache_key);

        if (!$data || !isset($data['data'])) {
            return array();
        }

        return $data['data'];
    }

    /**
     * Get programs that don't have videos (for coming soon selection) - FIXED with new endpoint
     */
    public function get_programs_without_videos()
    {
        // Usar el nuevo endpoint que obtiene TODAS las categorías IA con portada
        $cache_key = 'all_ia_categories_with_covers';
        $endpoint = '/ia/categories/all'; // Nuevo endpoint específico

        $data = $this->make_request($endpoint, array(), $cache_key);

        if (!$data || !isset($data['data'])) {
            $this->add_debug('No data from /ia/categories/all endpoint');
            return array();
        }

        $all_categories_with_covers = $data['data'];
        $programs_without_videos = array();

        $this->add_debug('Found ' . count($all_categories_with_covers) . ' categories with covers from new endpoint');

        // Verificar cuáles NO tienen videos
        foreach ($all_categories_with_covers as $program) {
            $materials = $this->get_materials($program['id']);
            if (empty($materials)) {
                $programs_without_videos[] = $program;
                $this->add_debug('Program without videos: ' . $program['name'] . ' (ID: ' . $program['id'] . ')');
            } else {
                $this->add_debug('Program with videos: ' . $program['name'] . ' (ID: ' . $program['id'] . ') - ' . count($materials) . ' videos');
            }
        }

        // Sort alphabetically
        usort($programs_without_videos, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        $this->add_debug('Total programs without videos: ' . count($programs_without_videos));

        return $programs_without_videos;
    }

    /**
     * Filter programs based on section (optimized)
     */
    private function filter_programs_by_section($programs)
    {
        if ($this->section == '5') {
            // Filter IA programs (those with _ia.png in cover)
            $filtered = array_filter($programs, function ($program) {
                $has_ia_cover = isset($program['cover']) && strpos($program['cover'], '_ia.png') !== false;
                if ($has_ia_cover) {
                    $this->add_debug('IA Program found: ' . $program['name'] . ' (ID: ' . $program['id'] . ')');
                }
                return $has_ia_cover;
            });
        } else {
            // Filter Kids programs (those with _infantil.png in cover)
            $filtered = array_filter($programs, function ($program) {
                $has_kids_cover = isset($program['cover']) && strpos($program['cover'], '_infantil.png') !== false;
                if ($has_kids_cover) {
                    $this->add_debug('Kids Program found: ' . $program['name'] . ' (ID: ' . $program['id'] . ')');
                }
                return $has_kids_cover;
            });
        }

        return array_values($filtered);
    }

    /**
     * Get materials for a specific program
     */
    public function get_materials($program_id)
    {
        $cache_key = 'materials_' . $program_id;

        if ($this->section == '5') {
            $endpoint = '/ia/categories/materials';
            $params = array('category' => $program_id);
        } else {
            $endpoint = '/programs/materials';
            $params = array('program' => $program_id);
        }

        $data = $this->make_request($endpoint, $params, $cache_key);

        if (!$data || !isset($data['data'])) {
            $this->add_debug('No materials found for program ID: ' . $program_id);
            return array();
        }

        $this->add_debug('Materials found for program ID ' . $program_id . ': ' . count($data['data']));
        return $data['data'];
    }

    /**
     * Check if a program has videos
     */
    public function program_has_videos($program_id)
    {
        $materials = $this->get_materials($program_id);
        return !empty($materials);
    }

    /**
     * Get a single program by ID
     */
    public function get_program_by_id($program_id)
    {
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
    public function get_materials_by_season($program_id, $season)
    {
        $materials = $this->get_materials($program_id);

        if (empty($materials)) {
            return array();
        }

        // Filter by season if specified
        if ($season !== null) {
            $materials = array_filter($materials, function ($material) use ($season) {
                return isset($material['season']) && $material['season'] == $season;
            });
        }

        return array_values($materials);
    }

    /**
     * Search material by ID
     */
    public function get_material_by_id($material_id)
    {
        $cache_key = 'material_' . $material_id;
        $endpoint = '/advanced-search/materials';
        $params = array('id' => $material_id);

        $data = $this->make_request($endpoint, $params, $cache_key);

        if (!$data || !isset($data['data']) || empty($data['data'])) {
            return null;
        }

        return $data['data'][0];
    }

    /**
     * Search materials by text
     */
    public function search_materials($search_text)
    {
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
     * Get all programs for menu (alphabetically sorted)
     */
    public function get_all_programs_for_menu()
    {
        $programs = $this->get_programs();

        // Sort programs alphabetically
        usort($programs, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return $programs;
    }

    /**
     * Get all programs with custom order for home display
     */
    public function get_programs_with_custom_order()
    {
        $programs = $this->get_programs();

        // Get custom order from WordPress option
        $order = get_option('adc_programs_order', array());

        if (!empty($order)) {
            $programs = $this->apply_custom_order($programs, $order);
        }

        return $programs;
    }

    /**
     * Apply custom order to programs array (optimized)
     */
    private function apply_custom_order($programs, $order)
    {
        // Create a lookup array with program IDs as keys and sort order as values
        $order_lookup = array_flip($order);

        // Sort the programs based on the custom order
        usort($programs, function ($a, $b) use ($order_lookup) {
            $a_order = isset($order_lookup[$a['id']]) ? $order_lookup[$a['id']] : PHP_INT_MAX;
            $b_order = isset($order_lookup[$b['id']]) ? $order_lookup[$b['id']] : PHP_INT_MAX;

            if ($a_order == $b_order) {
                // If both have same order (or no order), use alphabetical
                return strcmp($a['name'], $b['name']);
            }

            return $a_order - $b_order;
        });

        return $programs;
    }

    /**
     * Get current section
     */
    public function get_section()
    {
        return $this->section;
    }

    /**
     * Get section name
     */
    public function get_section_name()
    {
        return $this->section == '5' ? 'IA' : 'Kids';
    }

    /**
     * Format video duration from HH:MM:SS to human readable
     */
    public function format_duration($duration)
    {
        if (empty($duration)) {
            return '';
        }

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
    public function get_thumbnail_url($video_id)
    {
        return "https://s3apics.streamgates.net/TutorahTV_Thumbs/{$video_id}_50.jpg";
    }

    /**
     * Get video player URL
     */
    public function get_video_url($video)
    {
        return isset($video['video']) ? $video['video'] : '';
    }

    /**
     * Get season names mapping (optimized with static)
     */
    public function get_season_names()
    {
        static $season_names = null;

        if ($season_names === null) {
            $season_names = array(
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

        return $season_names;
    }

    /**
     * Get season name by number
     */
    public function get_season_name($season_number)
    {
        $seasons = $this->get_season_names();

        return isset($seasons[$season_number])
            ? $seasons[$season_number]
            : 'Temporada ' . intval($season_number);
    }

    /**
     * Group materials by season (optimized)
     */
    public function group_materials_by_season($materials)
    {
        $grouped = array();

        foreach ($materials as $material) {
            $season = isset($material['season']) ? $material['season'] : 0;

            if (!isset($grouped[$season])) {
                $grouped[$season] = array();
            }

            $grouped[$season][] = $material;
        }

        // Sort seasons numerically
        ksort($grouped, SORT_NUMERIC);

        return $grouped;
    }

    /**
     * Group search results by category (optimized)
     */
    public function group_search_results_by_category($results)
    {
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
    public function is_configured()
    {
        return !empty($this->api_token) && !empty($this->api_url);
    }

    /**
     * Test API connection (improved with detailed info)
     */
    public function test_connection()
    {
        if (!$this->is_configured()) {
            return array(
                'success' => false,
                'error' => 'API no configurada - Token o URL faltante'
            );
        }

        // Clear cache for testing
        $this->cache = array();

        $programs = $this->get_programs();

        if ($programs === false) {
            return array(
                'success' => false,
                'error' => 'Error al conectar con la API',
                'debug_info' => $this->get_debug_info()
            );
        }

        // Test materials endpoint with first program
        $materials_test = true;
        if (!empty($programs)) {
            $first_program = $programs[0];
            $materials = $this->get_materials($first_program['id']);
            $materials_test = $materials !== false;
        }

        return array(
            'success' => true,
            'programs_count' => count($programs),
            'materials_test' => $materials_test,
            'section' => $this->get_section_name(),
            'debug_info' => $this->get_debug_info()
        );
    }

    /**
     * Clear internal cache
     */
    public function clear_cache()
    {
        $this->cache = array();
        $this->add_debug('Cache cleared');
    }

    /**
     * Get cache statistics
     */
    public function get_cache_stats()
    {
        return array(
            'cache_entries' => count($this->cache),
            'cache_keys' => array_keys($this->cache)
        );
    }

    /**
     * Batch get materials for multiple programs (optimized for coming soon check)
     */
    public function batch_check_programs_with_videos($program_ids)
    {
        $programs_with_videos = array();

        foreach ($program_ids as $program_id) {
            if ($this->program_has_videos($program_id)) {
                $programs_with_videos[] = $program_id;
            }
        }

        return $programs_with_videos;
    }
}