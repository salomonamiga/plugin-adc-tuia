<?php
/**
 * ADC Video Display - Search Handler
 * Version: 3.1 - Sistema de Caché Inteligente + Fallback mejorado
 * 
 * Maneja la funcionalidad de búsqueda para los 2 idiomas con retry automático
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ADC_Search
{

    private $cache = array();
    private $options;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->options = get_option('adc-video-display');

        // Register shortcodes for each language (only ES and EN)
        add_shortcode('adc_search_form', array($this, 'render_search_form'));
        add_shortcode('adc_search_form_en', array($this, 'render_search_form_en'));

        // AJAX handlers
        add_action('wp_ajax_adc_search_videos', array($this, 'ajax_search_videos'));
        add_action('wp_ajax_nopriv_adc_search_videos', array($this, 'ajax_search_videos'));

        // Enhanced AJAX search handler
        add_action('wp_ajax_adc_search', array($this, 'ajax_search'));
        add_action('wp_ajax_nopriv_adc_search', array($this, 'ajax_search'));

        // Content filter for search results
        add_filter('the_content', array($this, 'show_search_results'), 15);

        // Search query modifications
        add_action('pre_get_posts', array($this, 'modify_search_query'));
    }

    /**
     * Show search results when adc_search parameter is present
     */
    public function show_search_results($content)
    {
        if (!isset($_GET['adc_search']) || empty($_GET['adc_search'])) {
            return $content;
        }

        // Prevent duplication
        if (strpos($content, 'adc-search-results-container') !== false) {
            return $content;
        }

        // Detect language from URL
        $language = ADC_Utils::detect_language();

        // Add search results to content
        $search_results = $this->display_search_results($language);
        return $content . $search_results;
    }

    /**
     * Render search form for Spanish
     */
    public function render_search_form($atts)
    {
        return $this->render_search_form_generic('es', $atts);
    }

    /**
     * Render search form for English
     */
    public function render_search_form_en($atts)
    {
        return $this->render_search_form_generic('en', $atts);
    }

    /**
     * Generic render search form
     */
    private function render_search_form_generic($language, $atts)
    {
        $atts = shortcode_atts(array(
            'placeholder' => ADC_Utils::get_text('search_placeholder', $language),
            'button_text' => ADC_Utils::get_text('search', $language),
            'class' => 'adc-search-form',
            'results_page' => $this->get_results_page_url($language),
            'show_suggestions' => 'false',
            'autocomplete' => 'true'
        ), $atts);

        // Check if search is enabled
        if (!$this->is_search_enabled()) {
            return '<!-- ADC Search disabled in settings -->';
        }

        $form_id = 'adc-search-form-' . $language . '-' . uniqid();

        $output = '<div class="adc-search-container adc-search-' . $language . '">';
        $output .= '<form class="' . esc_attr($atts['class']) . '" id="' . esc_attr($form_id) . '" method="get" action="' . esc_url($atts['results_page']) . '" role="search" data-language="' . esc_attr($language) . '">';

        // Add search input
        $output .= '<div class="adc-search-input-container">';
        $output .= '<input type="search" name="adc_search" class="adc-search-input" ';
        $output .= 'placeholder="' . esc_attr($atts['placeholder']) . '" ';
        $output .= 'aria-label="' . esc_attr($atts['placeholder']) . '" ';
        $output .= 'autocomplete="' . ($atts['autocomplete'] === 'true' ? 'on' : 'off') . '" ';
        $output .= 'required>';

        // Add suggestions container if enabled
        if ($atts['show_suggestions'] === 'true') {
            $output .= '<div class="adc-search-suggestions" id="suggestions-' . esc_attr($form_id) . '" role="listbox" aria-hidden="true"></div>';
        }

        $output .= '</div>';

        // Add search button with icon
        $output .= '<button type="submit" class="adc-search-button" aria-label="' . esc_attr($atts['button_text']) . '">';
        $output .= '<span class="adc-search-button-text">' . esc_html($atts['button_text']) . '</span>';
        $output .= '<span class="adc-search-icon" aria-hidden="true">🔍</span>';
        $output .= '</button>';

        $output .= '</form>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Enhanced AJAX search handler with retry and fallback
     */
    public function ajax_search()
    {
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

        $search_term = isset($_POST['search']) ? ADC_Utils::sanitize_search_term($_POST['search']) : '';
        $language = isset($_POST['language']) ? ADC_Utils::validate_language($_POST['language']) : 'es';

        if (empty($search_term) || strlen($search_term) < 2) {
            wp_send_json_error(array(
                'message' => 'Search term too short',
                'code' => 'TERM_TOO_SHORT',
                'min_length' => 2
            ));
            return;
        }

        try {
            // Check if search is enabled
            if (!$this->is_search_enabled()) {
                wp_send_json_error(array(
                    'message' => 'Search disabled',
                    'code' => 'SEARCH_DISABLED'
                ));
                return;
            }

            // NEW: Get search results with automatic retry and fallback
            $results = $this->get_search_results_with_fallback($search_term, $language);

            // Enhanced response data structure
            $response_data = array(
                'results' => $results['data'],
                'total' => count($results['data']),
                'search_term' => $search_term,
                'language' => $language,
                'grouped_results' => $this->group_results_by_category($results['data']),
                'cache_time' => current_time('timestamp'),
                'is_fallback' => $results['is_fallback'],
                'fallback_reason' => $results['fallback_reason'],
                'success' => true,
                'version' => '3.1'
            );

            wp_send_json_success($response_data);

        } catch (Exception $e) {
            // Last resort fallback
            $fallback_videos = $this->get_fallback_videos($language);

            wp_send_json_success(array(
                'results' => $fallback_videos,
                'total' => count($fallback_videos),
                'search_term' => $search_term,
                'language' => $language,
                'is_fallback' => true,
                'fallback_reason' => 'Exception: ' . $e->getMessage(),
                'message' => ADC_Utils::get_text('search_fallback_message', $language),
                'version' => '3.1'
            ));
        }
    }

    /**
     * NEW: Get search results with automatic retry and intelligent fallback
     */
    private function get_search_results_with_fallback($search_term, $language)
    {
        // Try regular search first
        $results = $this->get_cached_search_results($search_term, $language);

        if (!empty($results)) {
            return array(
                'data' => $results,
                'is_fallback' => false,
                'fallback_reason' => null
            );
        }

        // Search failed or returned empty - use intelligent fallback
        $fallback_videos = $this->get_smart_fallback_videos($search_term, $language);

        return array(
            'data' => $fallback_videos,
            'is_fallback' => true,
            'fallback_reason' => 'search_failed_or_empty'
        );
    }

    /**
     * NEW: Get smart fallback videos based on search term
     */
    private function get_smart_fallback_videos($search_term, $language, $limit = 8)
    {
        try {
            $api = new ADC_API($language);
            $programs = $api->get_programs();

            if (empty($programs)) {
                return array();
            }

            $all_videos = array();
            $search_keywords = $this->extract_search_keywords($search_term);

            // Collect videos and score them based on relevance to search term
            foreach ($programs as $program) {
                $videos = $api->get_materials($program['id']);
                if (!empty($videos)) {
                    foreach ($videos as $video) {
                        $video['category'] = $program['name'];
                        $video['relevance_score'] = $this->calculate_relevance_score($video, $search_keywords);
                        $all_videos[] = $video;
                    }
                }

                // Stop if we have enough videos to choose from
                if (count($all_videos) >= $limit * 4) {
                    break;
                }
            }

            if (empty($all_videos)) {
                return array();
            }

            // Sort by relevance score (highest first)
            usort($all_videos, function ($a, $b) {
                return $b['relevance_score'] - $a['relevance_score'];
            });

            // Take top results
            $smart_results = array_slice($all_videos, 0, $limit);

            // If no relevant results found, fallback to random popular videos
            if (empty($smart_results) || $smart_results[0]['relevance_score'] === 0) {
                return $this->get_fallback_videos($language, $limit);
            }

            return $smart_results;

        } catch (Exception $e) {
            // Final fallback
            return $this->get_fallback_videos($language, $limit);
        }
    }

    /**
     * NEW: Extract keywords from search term for relevance matching
     */
    private function extract_search_keywords($search_term)
    {
        // Clean and split search term
        $search_term = strtolower(trim($search_term));
        $keywords = preg_split('/[\s,.-]+/', $search_term, -1, PREG_SPLIT_NO_EMPTY);

        // Filter out very short words
        $keywords = array_filter($keywords, function ($word) {
            return strlen($word) >= 2;
        });

        return array_unique($keywords);
    }

    /**
     * NEW: Calculate relevance score for video based on search keywords
     */
    private function calculate_relevance_score($video, $keywords)
    {
        if (empty($keywords)) {
            return 0;
        }

        $score = 0;
        $searchable_text = strtolower(
            $video['title'] . ' ' .
            (isset($video['category']) ? $video['category'] : '') . ' ' .
            (isset($video['description']) ? $video['description'] : '')
        );

        foreach ($keywords as $keyword) {
            // Exact match in title gets highest score
            if (strpos(strtolower($video['title']), $keyword) !== false) {
                $score += 10;
            }

            // Match in category gets medium score
            if (isset($video['category']) && strpos(strtolower($video['category']), $keyword) !== false) {
                $score += 5;
            }

            // Match anywhere gets base score
            if (strpos($searchable_text, $keyword) !== false) {
                $score += 1;
            }
        }

        return $score;
    }

    /**
     * Legacy AJAX search videos handler (for backwards compatibility)
     */
    public function ajax_search_videos()
    {
        $this->ajax_search();
    }

    /**
     * Display search results with enhanced fallback support - ARREGLADO COMPLETAMENTE
     */
    private function display_search_results($language = 'es')
    {
        $search_term = ADC_Utils::sanitize_search_term($_GET['adc_search']);

        if (empty($search_term)) {
            return '<div class="adc-search-error">' . ADC_Utils::get_text('invalid_search_term', $language) . '</div>';
        }

        $output = '<div class="adc-search-results-container" data-search-term="' . esc_attr($search_term) . '" data-language="' . esc_attr($language) . '">';

        // NUEVA LÓGICA CORREGIDA: Usar el método que incluye detección de fallback
        $search_results_data = $this->get_search_results_with_fallback($search_term, $language);

        // Si no hay resultados reales (es fallback) O los resultados están vacíos
        if ($search_results_data['is_fallback'] || empty($search_results_data['data'])) {
            // Mostrar mensaje de "no results" + videos sugeridos
            $output .= $this->render_no_results($search_term, $language);
        } else {
            // Mostrar resultados reales normales
            $output .= $this->render_search_results($search_term, $search_results_data['data'], $language, false);
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Render no results message - FUNCIONA PERFECTAMENTE
     */
    private function render_no_results($search_term, $language)
    {
        $no_results_texts = array(
            'es' => array(
                'title' => 'No encontramos resultados para',
                'recommended_title' => 'Quizás te interesen estos videos:'
            ),
            'en' => array(
                'title' => 'No results found for',
                'recommended_title' => 'You might be interested in these videos:'
            )
        );

        $texts = $no_results_texts[$language];

        $output = '<div class="adc-no-results-section">';
        $output .= '<h2 class="adc-no-results-title">' . $texts['title'] . ' "' . esc_html($search_term) . '"</h2>';
        $output .= '</div>';

        // Add recommended videos using fallback system
        $recommended_videos = $this->get_fallback_videos($language);
        if (!empty($recommended_videos)) {
            $output .= '<h2 class="adc-recommended-title">' . $texts['recommended_title'] . '</h2>';
            $output .= '<div class="adc-recommended-videos">';

            foreach ($recommended_videos as $video) {
                $output .= $this->render_video_card($video, $language);
            }

            $output .= '</div>';
        }

        return $output;
    }

    /**
     * Render search results with enhanced fallback messaging
     */
    private function render_search_results($search_term, $results, $language, $is_fallback = false)
    {
        $results_texts = array(
            'es' => array(
                'results_for' => 'Resultados para',
                'found' => 'Se encontraron',
                'results' => 'resultado(s)'
            ),
            'en' => array(
                'results_for' => 'Results for',
                'found' => 'Found',
                'results' => 'result(s)'
            )
        );

        $texts = $results_texts[$language];

        $output = '<h1 class="adc-search-results-title">' . $texts['results_for'] . ': "' . esc_html($search_term) . '"</h1>';
        $output .= '<div class="adc-search-results-meta">' . $texts['found'] . ' ' . count($results) . ' ' . $texts['results'] . '</div>';

        // Group results by category if there are many results
        if (count($results) > 6) {
            $grouped_results = $this->group_results_by_category($results);
            $output .= $this->render_grouped_results($grouped_results, $language);
        } else {
            $output .= $this->render_simple_results($results, $language);
        }

        return $output;
    }

    /**
     * Render grouped results by category
     */
    private function render_grouped_results($grouped_results, $language)
    {
        $output = '<div class="adc-grouped-results">';

        foreach ($grouped_results as $category => $videos) {
            $output .= '<div class="adc-category-group">';
            $output .= '<h3 class="adc-category-group-title">' . esc_html($category) . ' (' . count($videos) . ')</h3>';
            $output .= '<div class="adc-category-videos">';

            foreach ($videos as $video) {
                $output .= $this->render_video_card($video, $language);
            }

            $output .= '</div>';
            $output .= '</div>';
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Render simple results grid
     */
    private function render_simple_results($results, $language)
    {
        $output = '<div class="adc-recommended-videos">';

        foreach ($results as $video) {
            $output .= $this->render_video_card($video, $language);
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Render a single video card
     */
    private function render_video_card($video, $language)
    {
        $category_slug = ADC_Utils::slugify($video['category']);
        $video_slug = ADC_Utils::slugify($video['title']);

        $url = ADC_Utils::build_video_url($category_slug, $video_slug, $language);

        $output = '<div class="adc-search-video-item" data-video-id="' . esc_attr($video['id']) . '">';
        $output .= '<a href="' . esc_url($url) . '" class="adc-search-video-link">';

        // Thumbnail with lazy loading
        $output .= '<div class="adc-search-thumbnail">';
        $thumbnail_url = ADC_Utils::get_thumbnail_url($video['id']);
        $output .= '<img src="' . esc_url($thumbnail_url) . '" alt="' . esc_attr($video['title']) . '" loading="lazy">';
        $output .= '<div class="adc-search-play-icon" aria-hidden="true"></div>';
        $output .= '</div>';

        // Video info
        $output .= '<div class="adc-search-info">';
        $output .= '<h3 class="adc-search-title">' . esc_html($video['title']) . '</h3>';
        $output .= '<div class="adc-search-program">' . ADC_Utils::get_text('program', $language) . ': ' . esc_html($video['category']) . '</div>';

        // Enhanced duration display
        $formatted_duration = ADC_Utils::format_duration($video['duration']);
        $output .= '<div class="adc-search-duration">' . ADC_Utils::get_text('duration', $language) . ': ' . esc_html($formatted_duration) . '</div>';

        $output .= '</div>';
        $output .= '</a>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Get fallback videos for empty search results - IMPROVED
     */
    private function get_fallback_videos($language, $limit = 8)
    {
        $cache_key = ADC_Utils::get_cache_key('fallback_videos_' . $limit, $language);

        // Check cache
        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }

        $cached_fallback = get_transient($cache_key);
        if ($cached_fallback !== false) {
            $this->cache[$cache_key] = $cached_fallback;
            return $cached_fallback;
        }

        try {
            // Create API instance for the language
            $api = new ADC_API($language);

            // Get programs
            $programs = $api->get_programs();

            if (empty($programs)) {
                return array();
            }

            // Collect videos from multiple programs for better variety
            $all_videos = array();
            $programs_checked = 0;

            foreach ($programs as $program) {
                $videos = $api->get_materials($program['id']);
                if (!empty($videos)) {
                    foreach ($videos as $video) {
                        $video['category'] = $program['name'];
                        $all_videos[] = $video;
                    }
                }

                $programs_checked++;

                // Stop after checking enough programs or having enough videos
                if ($programs_checked >= 4 || count($all_videos) >= $limit * 3) {
                    break;
                }
            }

            if (empty($all_videos)) {
                return array();
            }

            // Shuffle for variety and take limited amount
            shuffle($all_videos);
            $fallback_videos = array_slice($all_videos, 0, $limit);

            // Cache for 1 hour (shorter than other caches since it's fallback data)
            set_transient($cache_key, $fallback_videos, HOUR_IN_SECONDS);
            $this->cache[$cache_key] = $fallback_videos;

            return $fallback_videos;

        } catch (Exception $e) {
            if (isset($this->options['debug_mode']) && $this->options['debug_mode'] === '1') {
                ADC_Utils::debug_log('Fallback videos error: ' . $e->getMessage());
            }
            return array();
        }
    }

    /**
     * Get search results with enhanced caching and retry logic
     */
    private function get_cached_search_results($search_term, $language)
    {
        $cache_key = ADC_Utils::get_cache_key('search_' . md5($search_term), $language);

        // Check internal cache
        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }

        // Check WordPress transient
        $cached_results = get_transient($cache_key);
        if ($cached_results !== false) {
            $this->cache[$cache_key] = $cached_results;
            return $cached_results;
        }

        // Create API instance for the language - this handles retry automatically
        $api = new ADC_API($language);

        // Perform search with automatic retry (handled by ADC_API)
        $results = $api->search_materials($search_term);

        // Cache successful results
        if (is_array($results)) {
            // Get cache duration from settings
            $cache_duration = $this->get_search_cache_duration();
            set_transient($cache_key, $results, $cache_duration);
            $this->cache[$cache_key] = $results;
        }

        return is_array($results) ? $results : array();
    }

    /**
     * NEW: Get search cache duration from settings
     */
    private function get_search_cache_duration()
    {
        // Search results cache for shorter time than other data
        $general_cache_enabled = isset($this->options['enable_cache']) && $this->options['enable_cache'] === '1';

        if (!$general_cache_enabled) {
            return 0; // No cache
        }

        $hours = isset($this->options['cache_duration']) ? floatval($this->options['cache_duration']) : 6;
        $hours = max(0.5, min(24, $hours));

        // Search cache is half the general cache duration (for fresher results)
        return intval(($hours / 2) * HOUR_IN_SECONDS);
    }

    /**
     * Group search results by category
     */
    private function group_results_by_category($results)
    {
        $grouped = array();

        foreach ($results as $result) {
            $category = isset($result['category']) ? $result['category'] : 'Sin categoría';

            if (!isset($grouped[$category])) {
                $grouped[$category] = array();
            }

            $grouped[$category][] = $result;
        }

        // Sort categories by number of results (most results first)
        uasort($grouped, function ($a, $b) {
            return count($b) - count($a);
        });

        return $grouped;
    }

    /**
     * Helper methods
     */
    private function is_search_enabled()
    {
        return isset($this->options['enable_search']) ? $this->options['enable_search'] === '1' : true;
    }

    private function get_results_page_url($language)
    {
        return ADC_Utils::get_base_url($language);
    }

    /**
     * Modify search query for better WordPress integration
     */
    public function modify_search_query($query)
    {
        // Only modify main query on search pages
        if (!is_admin() && $query->is_main_query() && $query->is_search()) {
            $search_term = get_search_query();

            // If it looks like an ADC search, redirect to ADC search
            if (!empty($search_term) && !isset($_GET['adc_search'])) {
                $language = ADC_Utils::detect_language();
                $redirect_url = $this->get_results_page_url($language);
                $redirect_url = add_query_arg('adc_search', urlencode($search_term), $redirect_url);
                wp_redirect($redirect_url, 302);
                exit;
            }
        }
    }

    /**
     * Clear search cache
     */
    public function clear_cache()
    {
        // Clear all search-related transients
        global $wpdb;

        $languages = ADC_Utils::get_valid_languages();
        foreach ($languages as $lang) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_' . $lang . '_search_%',
                '_transient_' . $lang . '_fallback_videos_%'
            ));
        }

        // Clear internal cache
        $this->cache = array();
    }
}

// Initialize search functionality
new ADC_Search();