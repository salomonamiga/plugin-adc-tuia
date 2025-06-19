<?php
/**
 * ADC Video Display - API Handler
 * Version: 3.0 - Multiidioma
 * 
 * Maneja todas las peticiones API a TuTorah TV
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ADC_API
{
    private $api_token;
    private $api_url;
    private $language;
    private $section;
    private $debug_mode = false;
    private $cache = array();

    /**
     * Constructor
     */
    public function __construct($language = 'es')
    {
        $options = get_option('adc-video-display');

        $this->api_token = isset($options['api_token']) ? $options['api_token'] : '';
        $this->api_url = isset($options['api_url']) ? $options['api_url'] : 'https://api.tutorah.tv/v1';
        $this->debug_mode = isset($options['debug_mode']) ? $options['debug_mode'] : false;
        
        // Set language and corresponding section
        $this->language = $language;
        $this->section = $this->get_section_by_language($language);
    }

    /**
     * Get section ID by language
     */
    private function get_section_by_language($language)
    {
        $sections = array(
            'es' => '5', // Español - IA
            'en' => '6', // Inglés
            'he' => '7'  // Hebreo
        );

        return isset($sections[$language]) ? $sections[$language] : '5';
    }

    /**
     * Make API request with improved error handling and caching
     */
    private function make_request($endpoint, $params = array(), $cache_key = null)
    {
        // Check cache first
        if ($cache_key && isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }

        $url = $this->api_url . $endpoint;

        if (!empty($params)) {
            $url = add_query_arg($params, $url);
        }

        $args = array(
            'headers' => array(
                'Authorization' => $this->api_token,
                'User-Agent' => 'ADC-WordPress-Plugin/3.0'
            ),
            'timeout' => 30,
            'sslverify' => true
        );

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            return false;
        }

        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        if (isset($data['error']) && $data['error']) {
            return false;
        }

        // Cache successful response
        if ($cache_key) {
            $this->cache[$cache_key] = $data;
        }

        return $data;
    }

    /**
     * Get programs/categories based on language
     */
    public function get_programs()
    {
        $cache_key = 'programs_' . $this->language . '_' . $this->section;
        $endpoint = '/ia/categories';
        $data = $this->make_request($endpoint, array(), $cache_key);

        if (!$data || !isset($data['data'])) {
            return array();
        }

        return $this->filter_programs_by_section($data['data']);
    }

    /**
     * Get ALL programs from API without filtering
     */
    public function get_all_programs_from_api()
    {
        $cache_key = 'all_programs_' . $this->language . '_' . $this->section;
        $endpoint = '/ia/categories/all';
        $data = $this->make_request($endpoint, array(), $cache_key);

        if (!$data || !isset($data['data'])) {
            return array();
        }

        return $data['data'];
    }

    /**
     * Filter programs based on section
     */
    private function filter_programs_by_section($programs)
    {
        $section_suffix = $this->get_section_suffix();
        
        $filtered = array_filter($programs, function ($program) use ($section_suffix) {
            return isset($program['cover']) && strpos($program['cover'], $section_suffix) !== false;
        });

        return array_values($filtered);
    }

    /**
     * Get section suffix for filtering
     */
    private function get_section_suffix()
    {
        $suffixes = array(
            '5' => '_ia.png',     // Español
            '6' => '_en.png',     // Inglés
            '7' => '_he.png'      // Hebreo
        );

        return isset($suffixes[$this->section]) ? $suffixes[$this->section] : '_ia.png';
    }

    /**
     * Get materials for a specific program
     */
    public function get_materials($program_id)
    {
        $cache_key = 'materials_' . $this->language . '_' . $program_id;
        $endpoint = '/ia/categories/materials';
        $params = array('category' => $program_id);

        $data = $this->make_request($endpoint, $params, $cache_key);

        if (!$data || !isset($data['data'])) {
            return array();
        }

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
        $cache_key = 'material_' . $this->language . '_' . $material_id;
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
        $programs = $this->filter_programs_by_section(
            $this->get_all_programs_from_api()
        );

        // Get custom order from WordPress option based on language
        $order_option = 'adc_programs_order_' . $this->language;
        $order = get_option($order_option, array());

        if (!empty($order)) {
            $programs = $this->apply_custom_order($programs, $order);
        }

        return $programs;
    }

    /**
     * Apply custom order to programs array
     */
    private function apply_custom_order($programs, $order)
    {
        $order_lookup = array_flip($order);

        usort($programs, function ($a, $b) use ($order_lookup) {
            $a_order = isset($order_lookup[$a['id']]) ? $order_lookup[$a['id']] : PHP_INT_MAX;
            $b_order = isset($order_lookup[$b['id']]) ? $order_lookup[$b['id']] : PHP_INT_MAX;

            if ($a_order == $b_order) {
                return strcmp($a['name'], $b['name']);
            }

            return $a_order - $b_order;
        });

        return $programs;
    }

    /**
     * Get current language
     */
    public function get_language()
    {
        return $this->language;
    }

    /**
     * Get current section
     */
    public function get_section()
    {
        return $this->section;
    }

    /**
     * Get language name
     */
    public function get_language_name()
    {
        $names = array(
            'es' => 'Español',
            'en' => 'English',
            'he' => 'עברית'
        );

        return isset($names[$this->language]) ? $names[$this->language] : 'Español';
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

    /** ESTO LO ARREGLO CODEX, SI NO FUNCIONA, REVISAR */

    /**
     * Get season names mapping
     */
    public function get_season_names()
    {
        static $season_names = null;

        if ($season_names === null) {
            $season_names = array(
                'es' => array(
                    1  => 'Temporada 1',
                    2  => 'Temporada 2',
                    3  => 'Temporada 3',
                    4  => 'Temporada 4',
                    5  => 'Temporada 5',
                    6  => 'Bereshit',
                    7  => 'Shemot',
                    8  => 'Vaikra',
                    9  => 'Bamidbar',
                    10 => 'Debarim',
                    11 => 'Pesaj',
                    12 => 'Lag Baomer',
                    13 => 'Shabuot',
                    14 => 'Rosh Hashana',
                    15 => 'Kipur',
                    16 => 'Sucot',
                    17 => 'Simjat Torah',
                    18 => 'Januca',
                    19 => 'Tu Bishvat',
                    20 => 'Purim',
                    21 => 'Ayunos',
                    22 => 'Bereshit - Español',
                    23 => 'Bereshit - Hebreo',
                    24 => 'Bereshit - Ingles',
                    25 => 'Shemot - Español',
                    26 => 'Shemot - Hebreo',
                    27 => 'Shemot - Ingles',
                    28 => 'Vaikra - Español',
                    29 => 'Vaikra - Hebreo',
                    30 => 'Vaikra - Ingles',
                    31 => 'Bamidbar - Español',
                    32 => 'Bamidbar - Hebreo',
                    33 => 'Bamidbar - Ingles',
                    34 => 'Debarim - Español',
                    35 => 'Debarim - Hebreo',
                    36 => 'Debarim - Ingles',
                    37 => 'Jaguim - Español',
                    38 => 'Jaguim - Hebreo',
                    39 => 'Jaguim - Ingles',
                    40 => 'Jaguim'
                ),
                'en' => array(
                    1  => 'Season 1',
                    2  => 'Season 2',
                    3  => 'Season 3',
                    4  => 'Season 4',
                    5  => 'Season 5',
                    6  => 'Bereshit',
                    7  => 'Shemot',
                    8  => 'Vaikra',
                    9  => 'Bamidbar',
                    10 => 'Debarim',
                    11 => 'Pesaj',
                    12 => 'Lag Baomer',
                    13 => 'Shabuot',
                    14 => 'Rosh Hashana',
                    15 => 'Kipur',
                    16 => 'Sucot',
                    17 => 'Simjat Torah',
                    18 => 'Januca',
                    19 => 'Tu Bishvat',
                    20 => 'Purim',
                    21 => 'Ayunos',
                    22 => 'Bereshit - Español',
                    23 => 'Bereshit - Hebreo',
                    24 => 'Bereshit - Ingles',
                    25 => 'Shemot - Español',
                    26 => 'Shemot - Hebreo',
                    27 => 'Shemot - Ingles',
                    28 => 'Vaikra - Español',
                    29 => 'Vaikra - Hebreo',
                    30 => 'Vaikra - Ingles',
                    31 => 'Bamidbar - Español',
                    32 => 'Bamidbar - Hebreo',
                    33 => 'Bamidbar - Ingles',
                    34 => 'Debarim - Español',
                    35 => 'Debarim - Hebreo',
                    36 => 'Debarim - Ingles',
                    37 => 'Jaguim - Español',
                    38 => 'Jaguim - Hebreo',
                    39 => 'Jaguim - Ingles',
                    40 => 'Jaguim'
                ),
                'he' => array(
                    1  => 'עונה 1',
                    2  => 'עונה 2',
                    3  => 'עונה 3',
                    4  => 'עונה 4',
                    5  => 'עונה 5',
                    6  => 'Bereshit',
                    7  => 'Shemot',
                    8  => 'Vaikra',
                    9  => 'Bamidbar',
                    10 => 'Debarim',
                    11 => 'Pesaj',
                    12 => 'Lag Baomer',
                    13 => 'Shabuot',
                    14 => 'Rosh Hashana',
                    15 => 'Kipur',
                    16 => 'Sucot',
                    17 => 'Simjat Torah',
                    18 => 'Januca',
                    19 => 'Tu Bishvat',
                    20 => 'Purim',
                    21 => 'Ayunos',
                    22 => 'Bereshit - Español',
                    23 => 'Bereshit - Hebreo',
                    24 => 'Bereshit - Ingles',
                    25 => 'Shemot - Español',
                    26 => 'Shemot - Hebreo',
                    27 => 'Shemot - Ingles',
                    28 => 'Vaikra - Español',
                    29 => 'Vaikra - Hebreo',
                    30 => 'Vaikra - Ingles',
                    31 => 'Bamidbar - Español',
                    32 => 'Bamidbar - Hebreo',
                    33 => 'Bamidbar - Ingles',
                    34 => 'Debarim - Español',
                    35 => 'Debarim - Hebreo',
                    36 => 'Debarim - Ingles',
                    37 => 'Jaguim - Español',
                    38 => 'Jaguim - Hebreo',
                    39 => 'Jaguim - Ingles',
                    40 => 'Jaguim'
                )
            );
        }

        return $season_names;
    }

    /**
     * Get season name by number
     */
    public function get_season_name($season_number)
    {
        $all_seasons = $this->get_season_names();
        $language = $this->language;

        $seasons = isset($all_seasons[$language]) ? $all_seasons[$language] : $all_seasons['es'];

        if (isset($seasons[$season_number])) {
            return $seasons[$season_number];
        }

        $default = array(
            'es' => 'Temporada ',
            'en' => 'Season ',
            'he' => 'עונה '
        );

        $prefix = isset($default[$language]) ? $default[$language] : 'Temporada ';

        return $prefix . intval($season_number);
    }

    /**
     * Group materials by season
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

    /** hasta aqui arreglo */


    /**
     * Group search results by category
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
     * Test API connection
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
                'error' => 'Error al conectar con la API'
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
            'language' => $this->get_language_name()
        );
    }

    /**
     * Clear internal cache
     */
    public function clear_cache()
    {
        $this->cache = array();
    }

    /**
     * Get coming soon text by language
     */
    public function get_coming_soon_text()
    {
        $texts = array(
            'es' => 'Próximamente',
            'en' => 'Coming Soon',
            'he' => 'בקרוב'
        );

        return isset($texts[$this->language]) ? $texts[$this->language] : 'Próximamente';
    }
}