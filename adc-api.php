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
     * NUEVOS MÉTODOS PARA ADMIN - Cache Management
     */

    /**
     * Get cache statistics for admin dashboard
     */
    public function get_cache_stats()
    {
        global $wpdb;
        
        // Count transients for this language
        $transient_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            '_transient_timeout_%' . $this->language . '%',
            '_transient_%' . $this->language . '%'
        ));

        // Calculate approximate cache size
        $cache_size = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_%' . $this->language . '%'
        ));

        // Determine environment
        $environment = (defined('WP_DEBUG') && WP_DEBUG) ? 'development' : 'production';

        return array(
            'transient_count' => intval($transient_count / 2), // Divide by 2 because each transient has timeout
            'cache_size_kb' => round(intval($cache_size) / 1024, 2),
            'environment' => $environment,
            'language' => $this->language,
            'last_update' => current_time('mysql')
        );
    }

    /**
     * Health check for system status
     */
    public function health_check()
    {
        $health = array(
            'overall' => 'healthy',
            'api_connection' => false,
            'programs_count' => 0,
            'materials_count' => 0,
            'cache_status' => 'ok',
            'last_check' => current_time('mysql')
        );

        try {
            // Test API connection
            $connection_test = $this->test_connection();
            $health['api_connection'] = $connection_test['success'];
            
            if ($connection_test['success']) {
                $health['programs_count'] = $connection_test['programs_count'];
                
                // Count total materials
                $programs = $this->get_programs();
                $total_materials = 0;
                foreach ($programs as $program) {
                    $materials = $this->get_materials($program['id']);
                    $total_materials += count($materials);
                }
                $health['materials_count'] = $total_materials;
            } else {
                $health['overall'] = 'unhealthy';
            }

            // Check cache status
            $cache_stats = $this->get_cache_stats();
            if ($cache_stats['transient_count'] === 0) {
                $health['cache_status'] = 'empty';
                $health['overall'] = 'degraded';
            }

        } catch (Exception $e) {
            $health['overall'] = 'unhealthy';
            $health['error'] = $e->getMessage();
        }

        return $health;
    }

    /**
     * Clear all cache for this language
     */
    public function clear_all_cache()
    {
        global $wpdb;
        
        // Clear WordPress transients for this language
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            '_transient_timeout_%' . $this->language . '%',
            '_transient_%' . $this->language . '%'
        ));

        // Clear internal cache
        $this->cache = array();

        // Clear object cache if available
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

        return true;
    }

    /**
     * Refresh specific cache type
     */
    public function refresh_cache($cache_type)
    {
        global $wpdb;
        
        switch ($cache_type) {
            case 'programs':
                // Clear programs cache
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    '_transient_programs_%' . $this->language . '%'
                ));
                break;
                
            case 'materials':
                // Clear materials cache
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    '_transient_materials_%' . $this->language . '%'
                ));
                break;
                
            case 'search':
                // Clear search cache
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    '_transient_search_%' . $this->language . '%'
                ));
                break;
                
            case 'menu':
                // Clear menu cache
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    '_transient_programs_menu_%' . $this->language . '%'
                ));
                break;
                
            default:
                // Clear all if type not recognized
                return $this->clear_all_cache();
        }

        // Clear related internal cache
        foreach ($this->cache as $key => $value) {
            if (strpos($key, $cache_type) !== false) {
                unset($this->cache[$key]);
            }
        }

        return true;
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
                'error' => 'API no configurada - Token o URL faltante',
                'error_type' => 'configuration'
            );
        }

        // Clear cache for testing
        $this->cache = array();

        $start_time = microtime(true);
        $programs = $this->get_programs();
        $end_time = microtime(true);

        if ($programs === false) {
            return array(
                'success' => false,
                'error' => 'Error al conectar con la API',
                'error_type' => 'connection'
            );
        }

        // Test materials endpoint with first program
        $materials_test = true;
        $materials_count = 0;
        if (!empty($programs)) {
            $first_program = $programs[0];
            $materials = $this->get_materials($first_program['id']);
            $materials_test = $materials !== false;
            $materials_count = is_array($materials) ? count($materials) : 0;
        }

        $response_time = round(($end_time - $start_time) * 1000); // Convert to milliseconds

        return array(
            'success' => true,
            'programs_count' => count($programs),
            'materials_test' => $materials_test,
            'materials_count' => $materials_count,
            'language' => $this->get_language_name(),
            'response_time' => $response_time,
            'cache_time' => time()
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

    /**
     * Enhanced cache management - Get cache key with language prefix
     */
    private function get_cache_key($base_key)
    {
        return $this->language . '_' . $base_key;
    }

    /**
     * Enhanced cache management - Set transient with language prefix
     */
    private function set_cache_transient($key, $data, $expiration = 300)
    {
        $cache_key = $this->get_cache_key($key);
        set_transient($cache_key, $data, $expiration);
        
        // Also store in internal cache
        $this->cache[$cache_key] = $data;
    }

    /**
     * Enhanced cache management - Get transient with language prefix
     */
    private function get_cache_transient($key)
    {
        $cache_key = $this->get_cache_key($key);
        
        // Check internal cache first
        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }
        
        // Check WordPress transient
        $data = get_transient($cache_key);
        if ($data !== false) {
            $this->cache[$cache_key] = $data;
            return $data;
        }
        
        return false;
    }

    /**
     * Enhanced cache management - Delete transient with language prefix
     */
    private function delete_cache_transient($key)
    {
        $cache_key = $this->get_cache_key($key);
        delete_transient($cache_key);
        unset($this->cache[$cache_key]);
    }

    /**
     * Get cache statistics for debugging
     */
    public function get_debug_cache_info()
    {
        if (!$this->debug_mode) {
            return array();
        }

        return array(
            'internal_cache_count' => count($this->cache),
            'internal_cache_keys' => array_keys($this->cache),
            'language' => $this->language,
            'section' => $this->section,
            'api_configured' => $this->is_configured()
        );
    }

    /**
     * Force refresh all data for this language
     */
    public function force_refresh_all()
    {
        // Clear all caches
        $this->clear_all_cache();
        
        // Pre-load essential data
        $programs = $this->get_programs();
        $menu_programs = $this->get_all_programs_for_menu();
        
        return array(
            'programs_loaded' => count($programs),
            'menu_programs_loaded' => count($menu_programs),
            'language' => $this->language,
            'timestamp' => current_time('mysql')
        );
    }

    /**
     * Get API status summary
     */
    public function get_api_status()
    {
        $status = array(
            'configured' => $this->is_configured(),
            'language' => $this->language,
            'section' => $this->section,
            'api_url' => $this->api_url,
            'has_token' => !empty($this->api_token),
            'cache_count' => count($this->cache)
        );

        if ($status['configured']) {
            $connection_test = $this->test_connection();
            $status['connection'] = $connection_test['success'];
            $status['programs_available'] = isset($connection_test['programs_count']) ? $connection_test['programs_count'] : 0;
        } else {
            $status['connection'] = false;
            $status['programs_available'] = 0;
        }

        return $status;
    }

    /**
     * Validate API response structure
     */
    private function validate_api_response($data, $required_fields = array('data'))
    {
        if (!is_array($data)) {
            return false;
        }

        foreach ($required_fields as $field) {
            if (!isset($data[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Log API errors if debug mode is enabled
     */
    private function log_api_error($message, $context = array())
    {
        if ($this->debug_mode && function_exists('error_log')) {
            $log_message = 'ADC API Error (' . $this->language . '): ' . $message;
            if (!empty($context)) {
                $log_message .= ' Context: ' . json_encode($context);
            }
            error_log($log_message);
        }
    }

    /**
     * Get formatted error message based on language
     */
    public function get_error_message($error_type)
    {
        $messages = array(
            'es' => array(
                'no_connection' => 'No se pudo conectar con la API',
                'no_programs' => 'No se encontraron programas',
                'no_materials' => 'No se encontraron videos',
                'invalid_response' => 'Respuesta inválida de la API',
                'not_configured' => 'API no configurada'
            ),
            'en' => array(
                'no_connection' => 'Could not connect to API',
                'no_programs' => 'No programs found',
                'no_materials' => 'No videos found',
                'invalid_response' => 'Invalid API response',
                'not_configured' => 'API not configured'
            ),
            'he' => array(
                'no_connection' => 'לא ניתן להתחבר ל-API',
                'no_programs' => 'לא נמצאו תוכניות',
                'no_materials' => 'לא נמצאו סרטונים',
                'invalid_response' => 'תגובת API לא תקפה',
                'not_configured' => 'API לא מוגדר'
            )
        );

        $lang_messages = isset($messages[$this->language]) ? $messages[$this->language] : $messages['es'];
        return isset($lang_messages[$error_type]) ? $lang_messages[$error_type] : 'Error desconocido';
    }
}