<?php

/**
 * ADC Video Display - API Handler
 * Version: 3.1 - Sistema de Caché Unificado Optimizado
 * 
 * Maneja todas las peticiones API a TuTorah TV
 * OPTIMIZADO: Cache unificado para eliminar bursts de requests
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
    private $options;

    /**
     * Constructor
     */
    public function __construct($language = 'es')
    {
        $this->options = get_option('adc-video-display');

        $this->api_token = isset($this->options['api_token']) ? $this->options['api_token'] : '';
        $this->api_url = isset($this->options['api_url']) ? $this->options['api_url'] : 'https://api.tutorah.tv/v1';
        $this->debug_mode = isset($this->options['debug_mode']) ? $this->options['debug_mode'] : false;

        // Set language and corresponding section
        $this->language = ADC_Utils::validate_language($language);
        $this->section = $this->get_section_by_language($this->language);
    }

    /**
     * Get section ID by language
     */
    private function get_section_by_language($language)
    {
        $sections = array(
            'es' => '5', // Español - IA
            'en' => '6'  // Inglés
        );

        return isset($sections[$language]) ? $sections[$language] : '5';
    }

    /**
     * Get endpoint prefix based on language
     */
    private function get_endpoint_prefix()
    {
        return $this->language === 'en' ? '/ia_en' : '/ia';
    }

    /**
     * Check if cache is enabled from admin settings
     */
    private function is_cache_enabled()
    {
        return isset($this->options['enable_cache']) && $this->options['enable_cache'] === '1';
    }

    /**
     * UNIFICADO: Get cache duration from admin settings (in seconds) - MISMA DURACIÓN PARA TODO
     */
    private function get_unified_cache_duration()
    {
        if (!$this->is_cache_enabled()) {
            return 0; // No cache
        }

        $hours = isset($this->options['cache_duration']) ? floatval($this->options['cache_duration']) : 6;
        $hours = max(0.5, min(24, $hours)); // Clamp between 30 minutes and 24 hours

        return intval($hours * HOUR_IN_SECONDS);
    }

    /**
     * OPTIMIZADO: Make API request with retry logic and unified caching
     */
    private function make_request($endpoint, $params = array(), $cache_key = null, $max_retries = 3)
    {
        // Check cache first if enabled and cache_key provided
        if ($cache_key && $this->is_cache_enabled()) {
            $cached_data = get_transient($cache_key);
            if ($cached_data !== false) {
                return $cached_data;
            }
        }

        $url = $this->api_url . $endpoint;

        if (!empty($params)) {
            $url = add_query_arg($params, $url);
        }

        $args = array(
            'headers' => array(
                'Authorization' => $this->api_token,
                'User-Agent' => 'ADC-WordPress-Plugin/3.1'
            ),
            'timeout' => 30,
            'sslverify' => true
        );

        $last_error = null;

        // Retry logic - attempt up to $max_retries times
        for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
            $response = wp_remote_get($url, $args);

            if (is_wp_error($response)) {
                $last_error = $response->get_error_message();

                if ($this->debug_mode) {
                    ADC_Utils::debug_log("API Request Failed (Attempt {$attempt}/{$max_retries}): " . $last_error, array(
                        'url' => $url,
                        'language' => $this->language
                    ));
                }

                // Wait before retrying (exponential backoff)
                if ($attempt < $max_retries) {
                    sleep($attempt); // Wait 1s, then 2s, then 3s
                }
                continue;
            }

            $http_code = wp_remote_retrieve_response_code($response);
            if ($http_code !== 200) {
                $last_error = "HTTP Error: {$http_code}";

                if ($this->debug_mode) {
                    ADC_Utils::debug_log("API HTTP Error (Attempt {$attempt}/{$max_retries}): {$http_code}", array(
                        'url' => $url,
                        'language' => $this->language
                    ));
                }

                // Wait before retrying
                if ($attempt < $max_retries) {
                    sleep($attempt);
                }
                continue;
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $last_error = "JSON Decode Error: " . json_last_error_msg();

                if ($this->debug_mode) {
                    ADC_Utils::debug_log("API JSON Error (Attempt {$attempt}/{$max_retries}): " . json_last_error_msg(), array(
                        'url' => $url,
                        'language' => $this->language,
                        'body_preview' => substr($body, 0, 200)
                    ));
                }

                // Wait before retrying
                if ($attempt < $max_retries) {
                    sleep($attempt);
                }
                continue;
            }

            if (isset($data['error']) && $data['error']) {
                $last_error = "API Error: " . (isset($data['message']) ? $data['message'] : 'Unknown error');

                if ($this->debug_mode) {
                    ADC_Utils::debug_log("API Logical Error (Attempt {$attempt}/{$max_retries}): " . $last_error, array(
                        'url' => $url,
                        'language' => $this->language
                    ));
                }

                // Don't retry logical errors - they won't change
                break;
            }

            // Success! Cache the result if caching is enabled
            if ($cache_key && $this->is_cache_enabled()) {
                $cache_duration = $this->get_unified_cache_duration();
                if ($cache_duration > 0) {
                    set_transient($cache_key, $data, $cache_duration);
                }
            }

            if ($this->debug_mode && $attempt > 1) {
                ADC_Utils::debug_log("API Request Succeeded after {$attempt} attempts", array(
                    'url' => $url,
                    'language' => $this->language
                ));
            }

            return $data;
        }

        // All retries failed
        if ($this->debug_mode) {
            ADC_Utils::debug_log("API Request Failed after {$max_retries} attempts. Last error: {$last_error}", array(
                'url' => $url,
                'language' => $this->language
            ));
        }

        return false;
    }

    /**
     * Get programs/categories based on language - UPDATED with dynamic endpoints
     */
    public function get_programs()
    {
        $cache_key = ADC_Utils::get_cache_key('programs_' . $this->section, $this->language);
        $endpoint = $this->get_endpoint_prefix() . '/categories';
        $data = $this->make_request($endpoint, array(), $cache_key);

        if (!$data || !isset($data['data'])) {
            return array();
        }

        return $this->filter_programs_by_section($data['data']);
    }

    /**
     * Get ALL programs from API without filtering - UPDATED with dynamic endpoints
     */
    public function get_all_programs_from_api()
    {
        $cache_key = ADC_Utils::get_cache_key('all_programs_' . $this->section, $this->language);
        $endpoint = $this->get_endpoint_prefix() . '/categories/all';
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
     * Get section suffix for filtering - UPDATED for correct suffixes
     */
    private function get_section_suffix()
    {
        $suffixes = array(
            '5' => '_ia.png',        // Español
            '6' => '_ia_en.png'      // Inglés - CORREGIDO
        );

        return isset($suffixes[$this->section]) ? $suffixes[$this->section] : '_ia.png';
    }

    /**
     * Get materials for a specific program - UPDATED with dynamic endpoints
     */
    public function get_materials($program_id)
    {
        $cache_key = ADC_Utils::get_cache_key('materials_' . $program_id, $this->language);
        $endpoint = $this->get_endpoint_prefix() . '/categories/materials';
        $params = array('category' => $program_id);

        $data = $this->make_request($endpoint, $params, $cache_key);

        if (!$data || !isset($data['data'])) {
            return array();
        }

        return $data['data'];
    }

    /**
     * NUEVO: Bulk check which programs have videos - ELIMINA BURSTS DE REQUESTS
     */
    public function bulk_check_programs_with_videos($programs)
    {
        if (empty($programs)) {
            return array();
        }

        $cache_key = ADC_Utils::get_cache_key('bulk_programs_videos_' . md5(serialize(array_column($programs, 'id'))), $this->language);

        // Check cache first
        if ($this->is_cache_enabled()) {
            $cached_result = get_transient($cache_key);
            if ($cached_result !== false) {
                return $cached_result;
            }
        }

        // If not cached, check each program (but cache the result)
        $programs_with_videos = array();

        foreach ($programs as $program) {
            $materials = $this->get_materials($program['id']);
            $programs_with_videos[$program['id']] = !empty($materials);
        }

        // Cache the bulk result with unified duration
        if ($this->is_cache_enabled()) {
            $cache_duration = $this->get_unified_cache_duration();
            if ($cache_duration > 0) {
                set_transient($cache_key, $programs_with_videos, $cache_duration);
            }
        }

        return $programs_with_videos;
    }

    /**
     * OPTIMIZADO: Check if a program has videos (usa bulk cache)
     */
    public function program_has_videos($program_id)
    {
        // Try to get from bulk cache first
        $all_programs = $this->get_programs();
        $bulk_result = $this->bulk_check_programs_with_videos($all_programs);

        if (isset($bulk_result[$program_id])) {
            return $bulk_result[$program_id];
        }

        // Fallback to individual check
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
        $cache_key = ADC_Utils::get_cache_key('material_' . $material_id, $this->language);
        $endpoint = '/advanced-search/materials';
        $params = array('id' => $material_id);

        $data = $this->make_request($endpoint, $params, $cache_key);

        if (!$data || !isset($data['data']) || empty($data['data'])) {
            return null;
        }

        return $data['data'][0];
    }

    /**
     * UNIFICADO: Search materials by text with unified cache duration
     */
    public function search_materials($search_text)
    {
        // Normalize search text for consistent caching
        $normalized_text = strtolower(trim($search_text));
        $cache_key = ADC_Utils::get_cache_key('search_' . md5($normalized_text), $this->language);
        
        // Debug logging para entender el caché
        $options = get_option('adc-video-display');
        if (isset($options['debug_mode']) && $options['debug_mode'] === '1') {
            error_log("ADC Search Debug: Original='$search_text', Normalized='$normalized_text', Cache Key='$cache_key'");
        }
        $endpoint = '/advanced-search/materials';
        $params = array(
            'section' => $this->section,
            'text' => $search_text
        );

        $data = $this->make_request($endpoint, $params, $cache_key);

        if (!$data || !isset($data['data'])) {
            // FALLBACK: If search fails, return recommended videos instead of empty
            return $this->get_fallback_videos();
        }

        return $data['data'];
    }

    /**
     * Get fallback videos when search fails - UNIFIED CACHE
     */
    private function get_fallback_videos($limit = 8)
    {
        try {
            $programs = $this->get_programs();

            if (empty($programs)) {
                return array();
            }

            $all_videos = array();

            // Get videos from first few programs to avoid long processing
            $programs_to_check = array_slice($programs, 0, 3);

            foreach ($programs_to_check as $program) {
                $videos = $this->get_materials($program['id']);
                if (!empty($videos)) {
                    foreach ($videos as $video) {
                        $video['category'] = $program['name'];
                        $all_videos[] = $video;
                    }
                }

                // Stop if we have enough
                if (count($all_videos) >= $limit * 2) {
                    break;
                }
            }

            if (empty($all_videos)) {
                return array();
            }

            // Shuffle and return limited amount
            shuffle($all_videos);
            return array_slice($all_videos, 0, $limit);
        } catch (Exception $e) {
            if ($this->debug_mode) {
                ADC_Utils::debug_log("Fallback videos failed: " . $e->getMessage());
            }
            return array();
        }
    }

    /**
     * UNIFICADO: Get all programs for menu (unified cache duration)
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
     * Apply custom order to programs array - CORREGIDO: Coming Soon al final
     */
    private function apply_custom_order($programs, $order)
    {
        // NUEVO: Separar programas con videos vs sin videos
        $programs_with_videos = array();
        $coming_soon_programs = array();

        // Usar bulk check para determinar qué programas tienen videos
        $bulk_videos_check = $this->bulk_check_programs_with_videos($programs);

        foreach ($programs as $program) {
            $has_videos = isset($bulk_videos_check[$program['id']]) ? $bulk_videos_check[$program['id']] : false;

            if ($has_videos) {
                $programs_with_videos[] = $program;
            } else {
                $coming_soon_programs[] = $program;
            }
        }

        // Aplicar orden personalizado SOLO a programas con videos
        if (!empty($order)) {
            $order_lookup = array_flip($order);

            usort($programs_with_videos, function ($a, $b) use ($order_lookup) {
                $a_order = isset($order_lookup[$a['id']]) ? $order_lookup[$a['id']] : PHP_INT_MAX;
                $b_order = isset($order_lookup[$b['id']]) ? $order_lookup[$b['id']] : PHP_INT_MAX;

                if ($a_order == $b_order) {
                    return strcmp($a['name'], $b['name']);
                }

                return $a_order - $b_order;
            });
        } else {
            // Si no hay orden personalizado, ordenar alfabéticamente
            usort($programs_with_videos, function ($a, $b) {
                return strcmp($a['name'], $b['name']);
            });
        }

        // Ordenar Coming Soon alfabéticamente
        usort($coming_soon_programs, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        // RESULTADO: Programas con videos primero, Coming Soon al final
        return array_merge($programs_with_videos, $coming_soon_programs);
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
        return ADC_Utils::get_language_name($this->language);
    }

    /**
     * Format video duration from HH:MM:SS to human readable
     */
    public function format_duration($duration)
    {
        return ADC_Utils::format_duration($duration);
    }

    /**
     * Get video thumbnail URL
     */
    public function get_thumbnail_url($video_id)
    {
        return ADC_Utils::get_thumbnail_url($video_id);
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
                    1 => 'Temporada 1',
                    2 => 'Temporada 2',
                    3 => 'Temporada 3',
                    4 => 'Temporada 4',
                    5 => 'Temporada 5',
                    6 => 'Bereshit',
                    7 => 'Shemot',
                    8 => 'Vaikra',
                    9 => 'Bamidbar',
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
                    1 => 'Season 1',
                    2 => 'Season 2',
                    3 => 'Season 3',
                    4 => 'Season 4',
                    5 => 'Season 5',
                    6 => 'Bereshit',
                    7 => 'Shemot',
                    8 => 'Vaikra',
                    9 => 'Bamidbar',
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
            'en' => 'Season '
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
     * CACHE MANAGEMENT METHODS - UNIFIED
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
            'cache_enabled' => $this->is_cache_enabled(),
            'cache_duration_hours' => $this->get_unified_cache_duration() / HOUR_IN_SECONDS,
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
            'cache_enabled' => $this->is_cache_enabled(),
            'cache_duration' => $this->get_unified_cache_duration(),
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
            if (!$this->is_cache_enabled()) {
                $health['cache_status'] = 'disabled';
                $health['overall'] = 'degraded';
            } else {
                $cache_stats = $this->get_cache_stats();
                if ($cache_stats['transient_count'] === 0) {
                    $health['cache_status'] = 'empty';
                    $health['overall'] = 'degraded';
                }
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
                    '_transient_%' . $this->language . '_programs_%'
                ));
                break;

            case 'materials':
                // Clear materials cache
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    '_transient_%' . $this->language . '_materials_%'
                ));
                break;

            case 'search':
                // Clear search cache
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    '_transient_%' . $this->language . '_search_%'
                ));
                break;

            case 'menu':
                // Clear menu cache
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    '_transient_%' . $this->language . '_programs_menu_%'
                ));
                break;

            default:
                // Clear all if type not recognized
                return $this->clear_all_cache();
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
     * Test API connection with retry support
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

        $start_time = microtime(true);
        $programs = $this->get_programs();
        $end_time = microtime(true);

        if ($programs === false) {
            return array(
                'success' => false,
                'error' => 'Error al conectar con la API después de varios intentos',
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
            'cache_enabled' => $this->is_cache_enabled(),
            'cache_duration' => $this->get_unified_cache_duration(),
            'cache_time' => time()
        );
    }

    /**
     * Get coming soon text by language
     */
    public function get_coming_soon_text()
    {
        return ADC_Utils::get_text('coming_soon', $this->language);
    }

    /**
     * Get cache performance info for admin
     */
    public function get_cache_performance_info()
    {
        $info = array(
            'cache_enabled' => $this->is_cache_enabled(),
            'cache_duration_hours' => $this->get_unified_cache_duration() / HOUR_IN_SECONDS,
            'language' => $this->language
        );

        if ($this->is_cache_enabled()) {
            $cache_stats = $this->get_cache_stats();
            $info['transient_count'] = $cache_stats['transient_count'];
            $info['cache_size_kb'] = $cache_stats['cache_size_kb'];
            $info['performance_mode'] = 'optimized';
        } else {
            $info['performance_mode'] = 'real-time';
            $info['warning'] = 'Cache disabled - all requests go directly to API';
        }

        return $info;
    }

    /**
     * Warm up cache by pre-loading essential data
     */
    public function warm_up_cache()
    {
        if (!$this->is_cache_enabled()) {
            return array('success' => false, 'reason' => 'cache_disabled');
        }

        try {
            $warmed_items = array();
            $start_time = microtime(true);

            // Pre-load programs
            $programs = $this->get_programs();
            if (!empty($programs)) {
                $warmed_items[] = 'programs (' . count($programs) . ')';

                // OPTIMIZADO: Pre-load bulk videos check
                $bulk_videos_check = $this->bulk_check_programs_with_videos($programs);
                if (!empty($bulk_videos_check)) {
                    $warmed_items[] = 'bulk videos check (' . count($bulk_videos_check) . ' programs)';
                }

                // Pre-load materials for first few programs
                $programs_to_warm = array_slice($programs, 0, 3);
                foreach ($programs_to_warm as $program) {
                    $materials = $this->get_materials($program['id']);
                    if (!empty($materials)) {
                        $warmed_items[] = $program['name'] . ' (' . count($materials) . ' videos)';
                    }
                }
            }

            // Pre-load menu data
            $menu_programs = $this->get_all_programs_for_menu();
            if (!empty($menu_programs)) {
                $warmed_items[] = 'menu programs (' . count($menu_programs) . ')';
            }

            $end_time = microtime(true);
            $duration = round(($end_time - $start_time) * 1000);

            return array(
                'success' => true,
                'items_warmed' => $warmed_items,
                'duration_ms' => $duration,
                'language' => $this->language,
                'timestamp' => current_time('mysql')
            );
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'language' => $this->language
            );
        }
    }

    /**
     * Check if cache needs refresh based on age
     */
    public function needs_cache_refresh()
    {
        if (!$this->is_cache_enabled()) {
            return false;
        }

        $cache_stats = $this->get_cache_stats();
        return $cache_stats['transient_count'] === 0;
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
                'not_configured' => 'API no configurada',
                'cache_disabled' => 'Caché desactivado - rendimiento puede ser más lento',
                'retry_failed' => 'Error después de varios intentos'
            ),
            'en' => array(
                'no_connection' => 'Could not connect to API',
                'no_programs' => 'No programs found',
                'no_materials' => 'No videos found',
                'invalid_response' => 'Invalid API response',
                'not_configured' => 'API not configured',
                'cache_disabled' => 'Cache disabled - performance may be slower',
                'retry_failed' => 'Error after multiple attempts'
            )
        );

        $lang_messages = isset($messages[$this->language]) ? $messages[$this->language] : $messages['es'];
        return isset($lang_messages[$error_type]) ? $lang_messages[$error_type] : 'Error desconocido';
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
            'cache_enabled' => $this->is_cache_enabled(),
            'cache_duration' => $this->get_unified_cache_duration()
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
        if ($this->debug_mode) {
            $context['cache_enabled'] = $this->is_cache_enabled();
            $context['cache_duration'] = $this->get_unified_cache_duration();
            ADC_Utils::debug_log('API Error (' . $this->language . '): ' . $message, $context);
        }
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
            'cache_enabled' => $this->is_cache_enabled(),
            'timestamp' => current_time('mysql')
        );
    }
}
