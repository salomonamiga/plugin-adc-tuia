<?php
/**
 * ADC Video Display - Search Handler
 * Version: 3.0 - Multiidioma (ES/EN únicamente)
 * 
 * Maneja la funcionalidad de búsqueda para los 2 idiomas
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ADC_Search {
    
    private $cache = array();
    private $options;
    
    /**
     * Constructor
     */
    public function __construct() {
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
    public function show_search_results($content) {
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
    public function render_search_form($atts) {
        return $this->render_search_form_generic('es', $atts);
    }
    
    /**
     * Render search form for English
     */
    public function render_search_form_en($atts) {
        return $this->render_search_form_generic('en', $atts);
    }
    
    /**
     * Generic render search form
     */
    private function render_search_form_generic($language, $atts) {
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
     * Enhanced AJAX search handler
     */
    public function ajax_search() {
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
            
            // Get search results with caching
            $results = $this->get_cached_search_results($search_term, $language);
            
            // Enhanced response data structure
            $response_data = array(
                'results' => $results,
                'total' => count($results),
                'search_term' => $search_term,
                'language' => $language,
                'grouped_results' => $this->group_results_by_category($results),
                'cache_time' => current_time('timestamp'),
                'success' => true,
                'version' => '3.0'
            );
            
            wp_send_json_success($response_data);
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Search failed',
                'code' => 'SEARCH_ERROR',
                'debug' => WP_DEBUG ? $e->getMessage() : null
            ));
        }
    }
    
    /**
     * Legacy AJAX search videos handler (for backwards compatibility)
     */
    public function ajax_search_videos() {
        $this->ajax_search();
    }
    
    /**
     * Display search results
     */
    private function display_search_results($language = 'es') {
        $search_term = ADC_Utils::sanitize_search_term($_GET['adc_search']);
        
        if (empty($search_term)) {
            return '<div class="adc-search-error">' . ADC_Utils::get_text('invalid_search_term', $language) . '</div>';
        }
        
        // Get results with caching
        $results = $this->get_cached_search_results($search_term, $language);
        
        $output = '<div class="adc-search-results-container" data-search-term="' . esc_attr($search_term) . '" data-language="' . esc_attr($language) . '">';
        
        if (empty($results)) {
            $output .= $this->render_no_results($search_term, $language);
        } else {
            $output .= $this->render_search_results($search_term, $results, $language);
        }
        
        $output .= '</div>';
        
        return $output;
    }

    /**
     * Render no results message with recommendations
     */
    private function render_no_results($search_term, $language) {
        $no_results_texts = array(
            'es' => array(
                'title' => 'No encontramos resultados para',
                'suggestions_title' => 'Sugerencias para mejorar tu búsqueda:',
                'suggestion_1' => 'Verifica que no haya errores de ortografía',
                'suggestion_2' => 'Intenta con palabras más generales',
                'suggestion_3' => 'Usa sinónimos o términos relacionados',
                'recommended_title' => 'Quizás te interesen estos videos:'
            ),
            'en' => array(
                'title' => 'No results found for',
                'suggestions_title' => 'Suggestions to improve your search:',
                'suggestion_1' => 'Check for spelling errors',
                'suggestion_2' => 'Try more general words',
                'suggestion_3' => 'Use synonyms or related terms',
                'recommended_title' => 'You might be interested in these videos:'
            )
        );
        
        $texts = $no_results_texts[$language];
        
        $output = '<div class="adc-no-results-section">';
        $output .= '<h2 class="adc-no-results-title">' . $texts['title'] . ' "' . esc_html($search_term) . '"</h2>';
        $output .= '<div class="adc-search-tips">';
        $output .= '<h3>' . $texts['suggestions_title'] . '</h3>';
        $output .= '<ul>';
        $output .= '<li>' . $texts['suggestion_1'] . '</li>';
        $output .= '<li>' . $texts['suggestion_2'] . '</li>';
        $output .= '<li>' . $texts['suggestion_3'] . '</li>';
        $output .= '</ul>';
        $output .= '</div>';
        $output .= '</div>';
        
        // Add recommended videos
        $recommended_videos = $this->get_recommended_videos($language);
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
     * Render search results with grouping options
     */
    private function render_search_results($search_term, $results, $language) {
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
    private function render_grouped_results($grouped_results, $language) {
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
    private function render_simple_results($results, $language) {
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
    private function render_video_card($video, $language) {
        $category_slug = ADC_Utils::slugify($video['category']);
        $video_slug = ADC_Utils::slugify($video['title']);
        
        $url = ADC_Utils::build_video_url($category_slug, $video_slug, $language);
        
        $api = new ADC_API($language);
        
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
     * Get recommended videos for empty search results
     */
    private function get_recommended_videos($language, $limit = 8) {
        $cache_key = ADC_Utils::get_cache_key('recommended_videos_' . $limit, $language);
        
        // Check cache
        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }
        
        $cached_recommendations = get_transient($cache_key);
        if ($cached_recommendations !== false) {
            $this->cache[$cache_key] = $cached_recommendations;
            return $cached_recommendations;
        }
        
        // Create API instance for the language
        $api = new ADC_API($language);
        
        // Get programs
        $programs = $api->get_programs();
        
        if (empty($programs)) {
            return array();
        }
        
        // Collect videos from all programs
        $all_videos = array();
        foreach ($programs as $program) {
            $videos = $api->get_materials($program['id']);
            if (!empty($videos)) {
                foreach ($videos as $video) {
                    $video['program'] = $program['name'];
                    $all_videos[] = $video;
                }
            }
            
            // Stop if we have enough videos to choose from
            if (count($all_videos) >= $limit * 3) {
                break;
            }
        }
        
        if (empty($all_videos)) {
            return array();
        }
        
        // Shuffle and take limited amount
        shuffle($all_videos);
        $recommended_videos = array_slice($all_videos, 0, $limit);
        
        // Cache for 30 minutes
        set_transient($cache_key, $recommended_videos, 30 * MINUTE_IN_SECONDS);
        $this->cache[$cache_key] = $recommended_videos;
        
        return $recommended_videos;
    }
    
    /**
     * Get search results with caching
     */
    private function get_cached_search_results($search_term, $language) {
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
        
        // Create API instance for the language
        $api = new ADC_API($language);
        
        // Perform actual search
        $results = $api->search_materials($search_term);
        
        // Cache for 10 minutes
        if (is_array($results)) {
            set_transient($cache_key, $results, 10 * MINUTE_IN_SECONDS);
            $this->cache[$cache_key] = $results;
        }
        
        return is_array($results) ? $results : array();
    }
    
    /**
     * Group search results by category
     */
    private function group_results_by_category($results) {
        $grouped = array();
        
        foreach ($results as $result) {
            $category = isset($result['category']) ? $result['category'] : 'Sin categoría';
            
            if (!isset($grouped[$category])) {
                $grouped[$category] = array();
            }
            
            $grouped[$category][] = $result;
        }
        
        // Sort categories by number of results (most results first)
        uasort($grouped, function($a, $b) {
            return count($b) - count($a);
        });
        
        return $grouped;
    }
    
    /**
     * Helper methods
     */
    private function is_search_enabled() {
        return isset($this->options['enable_search']) ? $this->options['enable_search'] === '1' : true;
    }
    
    private function get_results_page_url($language) {
        return ADC_Utils::get_base_url($language);
    }
    
    /**
     * Modify search query for better WordPress integration
     */
    public function modify_search_query($query) {
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
    public function clear_cache() {
        // Clear all search-related transients
        global $wpdb;
        
        $languages = ADC_Utils::get_valid_languages();
        foreach ($languages as $lang) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_' . $lang . '_search_%',
                '_transient_' . $lang . '_recommended_videos_%'
            ));
        }
        
        // Clear internal cache
        $this->cache = array();
    }
}

// Initialize search functionality
new ADC_Search();