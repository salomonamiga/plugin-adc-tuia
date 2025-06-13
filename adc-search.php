<?php
/**
 * ADC Video Display - Search Handler
 * 
 * Handles search functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ADC_Search {
    
    private $api;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api = new ADC_API();
        
        // Register shortcode
        add_shortcode('adc_search_form', array($this, 'render_search_form'));
        
        // AJAX handlers
        add_action('wp_ajax_adc_search_videos', array($this, 'ajax_search_videos'));
        add_action('wp_ajax_nopriv_adc_search_videos', array($this, 'ajax_search_videos'));
        
        // Override the main content for search results - without affecting layout
        add_filter('the_content', array($this, 'show_search_results'));
    }
    
    /**
     * Show search results when adc_search parameter is present
     */
    public function show_search_results($content) {
        if (isset($_GET['adc_search']) && !empty($_GET['adc_search'])) {
            // Si ya hay resultados mostrados, no los duplicar
            if (strpos($content, 'adc-search-results-container') !== false) {
                return $content;
            }
            
            // Si estamos en una página de búsqueda, agregar nuestros resultados
            $search_results = $this->display_search_results();
            return $content . $search_results;
        }
        
        return $content;
    }
    
    /**
     * Render search form
     */
    public function render_search_form($atts) {
        $atts = shortcode_atts(array(
            'placeholder' => 'Buscar videos...',
            'button_text' => 'Buscar',
            'class' => 'adc-search-form',
            'results_page' => ''
        ), $atts);
        
        // Determine results page URL
        $results_url = !empty($atts['results_page']) ? get_permalink($atts['results_page']) : home_url('/');
        
        $output = '<form class="' . esc_attr($atts['class']) . '" method="get" action="' . esc_url($results_url) . '">';
        $output .= '<input type="text" name="adc_search" class="adc-search-input" placeholder="' . esc_attr($atts['placeholder']) . '" required>';
        $output .= '<button type="submit" class="adc-search-button">' . esc_html($atts['button_text']) . '</button>';
        $output .= '</form>';
        
        return $output;
    }
    
    /**
     * AJAX search handler
     */
    public function ajax_search_videos() {
        check_ajax_referer('adc_nonce', 'nonce');
        
        $search_term = sanitize_text_field($_POST['search']);
        
        if (empty($search_term)) {
            wp_send_json_error('No search term provided');
        }
        
        // Search using API
        $results = $this->api->search_materials($search_term);
        
        // Group results by category
        $grouped_results = array();
        foreach ($results as $video) {
            $category = $video['category'];
            if (!isset($grouped_results[$category])) {
                $grouped_results[$category] = array(
                    'name' => $category,
                    'videos' => array()
                );
            }
            $grouped_results[$category]['videos'][] = $video;
        }
        
        wp_send_json_success(array(
            'results' => array_values($grouped_results),
            'total' => count($results)
        ));
    }
    
    /**
     * Display search results
     */
    private function display_search_results() {
        $search_term = sanitize_text_field($_GET['adc_search']);
        
        if (empty($search_term)) {
            return '<div class="adc-search-no-results">Por favor ingresa un término de búsqueda.</div>';
        }
        
        $results = $this->api->search_materials($search_term);
        
        $output = '<div class="adc-search-results-container">';
        $output .= '<h1 class="adc-search-results-title">Resultados de búsqueda para: "' . esc_html($search_term) . '"</h1>';
        
        if (empty($results)) {
            $output .= '<div class="adc-search-no-results">No se encontraron resultados para "' . esc_html($search_term) . '"</div>';
            
            // Añadir videos recomendados cuando no hay resultados
            $output .= $this->get_recommended_videos();
        } else {
            // Display search results in a grid
            $output .= '<div class="adc-recommended-videos">';
            
            foreach ($results as $video) {
                $category_slug = $this->slugify($video['category']);
                $video_slug = $this->slugify($video['title']);
                
                $url = '?categoria=' . $category_slug . '&video=' . $video_slug;
                
                $output .= $this->render_video_card($video, $url);
            }
            
            $output .= '</div>';
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Render a single video card
     */
    private function render_video_card($video, $url) {
        $output = '<div class="adc-search-video-item">';
        $output .= '<a href="' . esc_url($url) . '" class="adc-search-video-link">';
        $output .= '<div class="adc-search-thumbnail">';
        $output .= '<img src="' . esc_url($this->api->get_thumbnail_url($video['id'])) . '" alt="' . esc_attr($video['title']) . '">';
        $output .= '<div class="adc-search-play-icon"></div>';
        $output .= '</div>';
        
        $output .= '<div class="adc-search-info">';
        $output .= '<h3 class="adc-search-title">' . esc_html($video['title']) . '</h3>';
        $output .= '<div class="adc-search-program">Programa: ' . esc_html($video['category']) . '</div>';
        $output .= '<div class="adc-search-duration">Duración: ' . esc_html($video['duration']) . '</div>';
        $output .= '</div>';
        $output .= '</a>';
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Get recommended videos for when no search results are found
     */
    private function get_recommended_videos() {
        // Obtener todos los programas disponibles
        $programs = $this->api->get_programs();
        
        if (empty($programs)) {
            return '<div class="adc-recommended-empty">No hay recomendaciones disponibles en este momento.</div>';
        }
        
        // Obtener videos de todos los programas
        $all_videos = array();
        foreach ($programs as $program) {
            $videos = $this->api->get_materials($program['id']);
            if (!empty($videos)) {
                foreach ($videos as $video) {
                    $video['program'] = $program['name'];
                    $all_videos[] = $video;
                }
            }
        }
        
        if (empty($all_videos)) {
            return '';
        }
        
        // Barajar todos los videos
        shuffle($all_videos);
        
        // Tomar solo 8 videos
        $recommended_videos = array_slice($all_videos, 0, 8);
        
        $output = '<h2 class="adc-recommended-title">No encontramos lo que buscabas, pero quizás te interesen estos videos:</h2>';
        
        $output .= '<div class="adc-recommended-videos">';
        
        foreach ($recommended_videos as $video) {
            $program_slug = $this->slugify($video['category']);
            $video_slug = $this->slugify($video['title']);
            
            $url = '?categoria=' . $program_slug . '&video=' . $video_slug;
            
            $output .= $this->render_video_card($video, $url);
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Convert title to slug
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
     * Create search widget
     */
    public static function create_search_widget() {
        add_action('widgets_init', function() {
            register_widget('ADC_Search_Widget');
        });
    }
}

/**
 * Search Widget Class
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
                'description' => 'Formulario de búsqueda para videos ADC'
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
        
        // Use shortcode
        echo do_shortcode('[adc_search_form placeholder="' . esc_attr($instance['placeholder']) . '"]');
        
        echo $args['after_widget'];
    }
    
    /**
     * Widget backend
     */
    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : 'Buscar Videos';
        $placeholder = !empty($instance['placeholder']) ? $instance['placeholder'] : 'Buscar...';
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
        <?php
    }
    
    /**
     * Update widget
     */
    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        $instance['placeholder'] = (!empty($new_instance['placeholder'])) ? sanitize_text_field($new_instance['placeholder']) : '';
        return $instance;
    }
}

// Initialize search functionality
new ADC_Search();

// Create search widget
ADC_Search::create_search_widget();