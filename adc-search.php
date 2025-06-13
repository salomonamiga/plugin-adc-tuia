<?php
/**
 * ADC Video Display - Search Handler
 * 
 * Handles search functionality with enhanced performance and UX
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ADC_Search {
    
    private $api;
    private $cache = array(); // Internal caching
    private $options;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api = new ADC_API();
        $this->options = get_option('adc-video-display');
        
        // Register shortcode
        add_shortcode('adc_search_form', array($this, 'render_search_form'));
        
        // AJAX handlers
        add_action('wp_ajax_adc_search_videos', array($this, 'ajax_search_videos'));
        add_action('wp_ajax_nopriv_adc_search_videos', array($this, 'ajax_search_videos'));
        
        // Enhanced AJAX search handler (consolidated)
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
        
        // Add search results to content
        $search_results = $this->display_search_results();
        return $content . $search_results;
    }
    
    /**
     * Render search form with enhanced options
     */
    public function render_search_form($atts) {
        $atts = shortcode_atts(array(
            'placeholder' => $this->get_placeholder_text(),
            'button_text' => 'Buscar',
            'class' => 'adc-search-form',
            'results_page' => $this->get_results_page_url(),
            'show_suggestions' => 'false',
            'autocomplete' => 'true'
        ), $atts);
        
        // Check if search is enabled
        if (!$this->is_search_enabled()) {
            return '<!-- ADC Search disabled in settings -->';
        }
        
        $form_id = 'adc-search-form-' . uniqid();
        
        $output = '<div class="adc-search-container">';
        $output .= '<form class="' . esc_attr($atts['class']) . '" id="' . esc_attr($form_id) . '" method="get" action="' . esc_url($atts['results_page']) . '" role="search">';
        
        // Add search input with enhanced attributes
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
     * Enhanced AJAX search handler - CORREGIDO con mejor estructura de respuesta
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
        
        $search_term = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
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
            $results = $this->get_cached_search_results($search_term);
            
            // Enhanced response data structure for better compatibility
            $response_data = array(
                'results' => $results,
                'total' => count($results),
                'search_term' => $search_term,
                'grouped_results' => $this->group_results_by_category($results),
                'cache_time' => current_time('timestamp'),
                'section' => $this->api->get_section_name(),
                'success' => true,
                'version' => '2.1'
            );
            
            wp_send_json_success($response_data);
            
        } catch (Exception $e) {
            error_log('ADC Search Error: ' . $e->getMessage());
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
     * Display search results with enhanced layout
     */
    private function display_search_results() {
        $search_term = sanitize_text_field($_GET['adc_search']);
        
        if (empty($search_term)) {
            return '<div class="adc-search-error">Por favor ingresa un término de búsqueda válido.</div>';
        }
        
        // Get results with caching
        $results = $this->get_cached_search_results($search_term);
        
        $output = '<div class="adc-search-results-container" data-search-term="' . esc_attr($search_term) . '">';
        
        if (empty($results)) {
            $output .= $this->render_no_results($search_term);
        } else {
            $output .= $this->render_search_results($search_term, $results);
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Render no results message with recommendations
     */
    private function render_no_results($search_term) {
        $output = '<div class="adc-no-results-section">';
        $output .= '<h2 class="adc-no-results-title">No encontramos resultados para "' . esc_html($search_term) . '"</h2>';
        $output .= '<div class="adc-search-tips">';
        $output .= '<h3>Sugerencias para mejorar tu búsqueda:</h3>';
        $output .= '<ul>';
        $output .= '<li>Verifica que no haya errores de ortografía</li>';
        $output .= '<li>Intenta con palabras más generales</li>';
        $output .= '<li>Usa sinónimos o términos relacionados</li>';
        $output .= '</ul>';
        $output .= '</div>';
        $output .= '</div>';
        
        // Add recommended videos
        $recommended_videos = $this->get_recommended_videos();
        if (!empty($recommended_videos)) {
            $output .= '<h2 class="adc-recommended-title">Quizás te interesen estos videos:</h2>';
            $output .= '<div class="adc-recommended-videos">';
            
            foreach ($recommended_videos as $video) {
                $output .= $this->render_video_card($video);
            }
            
            $output .= '</div>';
        }
        
        return $output;
    }
    
    /**
     * Render search results with grouping options
     */
    private function render_search_results($search_term, $results) {
        $output = '<h1 class="adc-search-results-title">Resultados para: "' . esc_html($search_term) . '"</h1>';
        $output .= '<div class="adc-search-results-meta">Se encontraron ' . count($results) . ' resultado(s)</div>';
        
        // Group results by category if there are many results
        if (count($results) > 6) {
            $grouped_results = $this->group_results_by_category($results);
            $output .= $this->render_grouped_results($grouped_results);
        } else {
            $output .= $this->render_simple_results($results);
        }
        
        return $output;
    }
    
    /**
     * Render grouped results by category
     */
    private function render_grouped_results($grouped_results) {
        $output = '<div class="adc-grouped-results">';
        
        foreach ($grouped_results as $category => $videos) {
            $output .= '<div class="adc-category-group">';
            $output .= '<h3 class="adc-category-group-title">' . esc_html($category) . ' (' . count($videos) . ')</h3>';
            $output .= '<div class="adc-category-videos">';
            
            foreach ($videos as $video) {
                $output .= $this->render_video_card($video);
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
    private function render_simple_results($results) {
        $output = '<div class="adc-recommended-videos">';
        
        foreach ($results as $video) {
            $output .= $this->render_video_card($video);
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Render a single video card - Enhanced
     */
    private function render_video_card($video) {
        $category_slug = $this->slugify($video['category']);
        $video_slug = $this->slugify($video['title']);
        $url = '?categoria=' . $category_slug . '&video=' . $video_slug;
        
        $output = '<div class="adc-search-video-item" data-video-id="' . esc_attr($video['id']) . '">';
        $output .= '<a href="' . esc_url($url) . '" class="adc-search-video-link">';
        
        // Thumbnail with lazy loading
        $output .= '<div class="adc-search-thumbnail">';
        $thumbnail_url = $this->api->get_thumbnail_url($video['id']);
        $output .= '<img src="' . esc_url($thumbnail_url) . '" alt="' . esc_attr($video['title']) . '" loading="lazy">';
        $output .= '<div class="adc-search-play-icon" aria-hidden="true"></div>';
        $output .= '</div>';
        
        // Video info
        $output .= '<div class="adc-search-info">';
        $output .= '<h3 class="adc-search-title">' . esc_html($video['title']) . '</h3>';
        $output .= '<div class="adc-search-program">Programa: ' . esc_html($video['category']) . '</div>';
        
        // Enhanced duration display
        $formatted_duration = $this->api->format_duration($video['duration']);
        $output .= '<div class="adc-search-duration">Duración: ' . esc_html($formatted_duration) . '</div>';
        
        $output .= '</div>';
        $output .= '</a>';
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Get recommended videos for empty search results - Enhanced
     */
    private function get_recommended_videos($limit = 8) {
        $cache_key = 'recommended_videos_' . $this->api->get_section() . '_' . $limit;
        
        // Check cache
        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }
        
        $cached_recommendations = get_transient($cache_key);
        if ($cached_recommendations !== false) {
            $this->cache[$cache_key] = $cached_recommendations;
            return $cached_recommendations;
        }
        
        // Get programs
        $programs = $this->api->get_programs();
        
        if (empty($programs)) {
            return array();
        }
        
        // Collect videos from all programs
        $all_videos = array();
        foreach ($programs as $program) {
            $videos = $this->api->get_materials($program['id']);
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
     * Get search results with caching - Enhanced
     */
    private function get_cached_search_results($search_term) {
        $cache_key = 'search_' . md5($search_term . '_' . $this->api->get_section());
        
        // Check internal cache
        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }
        
        // Check WordPress transient (shorter cache for search results)
        $cached_results = get_transient($cache_key);
        if ($cached_results !== false) {
            $this->cache[$cache_key] = $cached_results;
            return $cached_results;
        }
        
        // Perform actual search
        $results = $this->api->search_materials($search_term);
        
        // Cache for 10 minutes
        if (is_array($results)) {
            set_transient($cache_key, $results, 10 * MINUTE_IN_SECONDS);
            $this->cache[$cache_key] = $results;
        }
        
        return is_array($results) ? $results : array();
    }
    
    /**
     * Group search results by category - Enhanced
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
     * Helper methods
     */
    private function is_search_enabled() {
        return isset($this->options['enable_search']) ? $this->options['enable_search'] === '1' : true;
    }
    
    private function get_placeholder_text() {
        return isset($this->options['search_placeholder']) ? $this->options['search_placeholder'] : 'Buscar videos...';
    }
    
    private function get_results_page_url() {
        $results_page_id = isset($this->options['search_results_page']) ? $this->options['search_results_page'] : 0;
        
        if ($results_page_id > 0) {
            return get_permalink($results_page_id);
        }
        
        return home_url('/');
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
                $redirect_url = add_query_arg('adc_search', urlencode($search_term), home_url('/'));
                wp_redirect($redirect_url, 302);
                exit;
            }
        }
    }
    
    /**
     * Create search widget
     */
    public static function create_search_widget() {
        add_action('widgets_init', function() {
            register_widget('ADC_Search_Widget');
        });
    }
    
    /**
     * Clear search cache
     */
    public function clear_cache() {
        // Clear all search-related transients
        global $wpdb;
        
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_search_%' OR option_name LIKE '_transient_recommended_videos_%'");
        
        // Clear internal cache
        $this->cache = array();
    }
    
    /**
     * Get search statistics for admin
     */
    public function get_search_stats() {
        return array(
            'cache_entries' => count($this->cache),
            'search_enabled' => $this->is_search_enabled(),
            'results_page' => $this->get_results_page_url(),
            'placeholder_text' => $this->get_placeholder_text()
        );
    }
}

/**
 * Enhanced Search Widget Class
 */
class ADC_Search_Widget extends WP_Widget {
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(
            'adc_search_widget',
            'ADC Video Search',
            array(
                'description' => 'Formulario de búsqueda para videos ADC con opciones avanzadas',
                'classname' => 'adc-search-widget'
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
        
        // Build shortcode attributes
        $shortcode_atts = array();
        
        if (!empty($instance['placeholder'])) {
            $shortcode_atts[] = 'placeholder="' . esc_attr($instance['placeholder']) . '"';
        }
        
        if (!empty($instance['button_text'])) {
            $shortcode_atts[] = 'button_text="' . esc_attr($instance['button_text']) . '"';
        }
        
        if (!empty($instance['show_suggestions']) && $instance['show_suggestions'] === '1') {
            $shortcode_atts[] = 'show_suggestions="true"';
        }
        
        if (!empty($instance['custom_class'])) {
            $shortcode_atts[] = 'class="' . esc_attr($instance['custom_class']) . '"';
        }
        
        // Build and execute shortcode
        $shortcode = '[adc_search_form';
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
        $title = !empty($instance['title']) ? $instance['title'] : 'Buscar Videos';
        $placeholder = !empty($instance['placeholder']) ? $instance['placeholder'] : 'Buscar...';
        $button_text = !empty($instance['button_text']) ? $instance['button_text'] : 'Buscar';
        $show_suggestions = !empty($instance['show_suggestions']) ? $instance['show_suggestions'] : '0';
        $custom_class = !empty($instance['custom_class']) ? $instance['custom_class'] : '';
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">Título:</label>
            <input class="widefat" 
                   id="<?php echo esc_attr($this->get_field_id('title')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>" 
                   type="text" 
                   value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('placeholder')); ?>">Placeholder:</label>
            <input class="widefat" 
                   id="<?php echo esc_attr($this->get_field_id('placeholder')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('placeholder')); ?>" 
                   type="text" 
                   value="<?php echo esc_attr($placeholder); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('button_text')); ?>">Texto del Botón:</label>
            <input class="widefat" 
                   id="<?php echo esc_attr($this->get_field_id('button_text')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('button_text')); ?>" 
                   type="text" 
                   value="<?php echo esc_attr($button_text); ?>">
        </p>
        <p>
            <input class="checkbox" 
                   type="checkbox" 
                   <?php checked($show_suggestions, '1'); ?> 
                   id="<?php echo esc_attr($this->get_field_id('show_suggestions')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('show_suggestions')); ?>" 
                   value="1">
            <label for="<?php echo esc_attr($this->get_field_id('show_suggestions')); ?>">Mostrar sugerencias</label>
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('custom_class')); ?>">Clase CSS personalizada:</label>
            <input class="widefat" 
                   id="<?php echo esc_attr($this->get_field_id('custom_class')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('custom_class')); ?>" 
                   type="text" 
                   value="<?php echo esc_attr($custom_class); ?>"
                   placeholder="adc-search-form">
        </p>
        <?php
    }
    
    /**
     * Update widget
     */
    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        $instance['placeholder'] = (!empty($new_instance['placeholder'])) ? sanitize_text_field($new_instance['placeholder']) : '';
        $instance['button_text'] = (!empty($new_instance['button_text'])) ? sanitize_text_field($new_instance['button_text']) : '';
        $instance['show_suggestions'] = (!empty($new_instance['show_suggestions'])) ? '1' : '0';
        $instance['custom_class'] = (!empty($new_instance['custom_class'])) ? sanitize_html_class($new_instance['custom_class']) : '';
        
        return $instance;
    }
}

// Initialize search functionality
new ADC_Search();

// Create search widget
ADC_Search::create_search_widget();