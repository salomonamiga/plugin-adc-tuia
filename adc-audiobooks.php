<?php
/**
 * ADC Video Display - Audiobooks Module
 * Version: 1.0
 *
 * Maneja la funcionalidad de audiolibros consumiendo datos de rabanidjar.com
 * Solo español - Sin PDF ni video promo
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ADC_Audiobooks
{
    private static $instance = null;
    private $options;
    private $json_url = 'https://rabanidjar.com/assets/data/libros.json';
    private $base_url = 'https://rabanidjar.com/';

    /**
     * Singleton instance
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->options = get_option('adc-video-display');

        // Register shortcode
        add_shortcode('adc_audiobooks', array($this, 'render_audiobooks'));

        // Enqueue assets when needed
        add_action('wp_enqueue_scripts', array($this, 'maybe_enqueue_assets'));
    }

    /**
     * Check if cache is enabled
     */
    private function is_cache_enabled()
    {
        return isset($this->options['enable_cache']) && $this->options['enable_cache'] === '1';
    }

    /**
     * Get cache duration from admin settings
     */
    private function get_cache_duration()
    {
        if (!$this->is_cache_enabled()) {
            return 0;
        }

        $hours = isset($this->options['cache_duration']) ? floatval($this->options['cache_duration']) : 6;
        $hours = max(0.5, min(24, $hours));

        return intval($hours * HOUR_IN_SECONDS);
    }

    /**
     * Fetch audiobooks from rabanidjar.com JSON
     * Filters only books that have audioCapitulos
     */
    public function get_audiobooks()
    {
        $cache_key = 'adc_audiobooks_data';

        // Check cache first
        if ($this->is_cache_enabled()) {
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }

        // Fetch fresh data
        $response = wp_remote_get($this->json_url, array(
            'timeout' => 15,
            'sslverify' => true,
            'headers' => array(
                'User-Agent' => 'ADC-WordPress-Plugin/1.0'
            )
        ));

        if (is_wp_error($response)) {
            ADC_Utils::debug_log('Audiobooks fetch error: ' . $response->get_error_message());
            return array();
        }

        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            ADC_Utils::debug_log('Audiobooks HTTP error: ' . $http_code);
            return array();
        }

        $body = wp_remote_retrieve_body($response);
        $all_books = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            ADC_Utils::debug_log('Audiobooks JSON error: ' . json_last_error_msg());
            return array();
        }

        // Filter only books with audioCapitulos
        $audiobooks = array_filter($all_books, function($book) {
            return isset($book['audioCapitulos']) && !empty($book['audioCapitulos']);
        });

        $audiobooks = array_values($audiobooks);

        // Cache the result
        if ($this->is_cache_enabled() && !empty($audiobooks)) {
            set_transient($cache_key, $audiobooks, $this->get_cache_duration());
        }

        return $audiobooks;
    }

    /**
     * Get a single audiobook by slug
     */
    public function get_audiobook_by_slug($slug)
    {
        $audiobooks = $this->get_audiobooks();

        foreach ($audiobooks as $book) {
            if ($book['id'] === $slug || ADC_Utils::slugify($book['titulo']['es']) === $slug) {
                return $book;
            }
        }

        return null;
    }

    /**
     * Main shortcode handler
     */
    public function render_audiobooks($atts)
    {
        // Check for detail view
        $audiobook_slug = get_query_var('adc_audiobook');

        if (!empty($audiobook_slug)) {
            return $this->render_audiobook_detail($audiobook_slug);
        }

        return $this->render_audiobooks_grid();
    }

    /**
     * Render audiobooks grid
     */
    public function render_audiobooks_grid()
    {
        $audiobooks = $this->get_audiobooks();

        if (empty($audiobooks)) {
            return '<div class="adc-audiobooks-empty">
                <p>' . ADC_Utils::get_text('no_audiobooks', 'es') . '</p>
            </div>';
        }

        $output = '<div class="adc-audiobooks-container">';
        $output .= '<div class="adc-audiobooks-header">';
        $output .= '<h1 class="adc-audiobooks-title">' . ADC_Utils::get_text('audiobooks', 'es') . '</h1>';
        $output .= '</div>';

        $output .= '<div class="adc-audiobooks-grid">';

        foreach ($audiobooks as $book) {
            $slug = $book['id'];
            $title = $book['titulo']['es'];
            $author = $book['autor'];
            $cover = $this->base_url . $book['portada'];
            $chapters_count = count($book['audioCapitulos']);
            $url = home_url('/audiolibros/' . $slug . '/');

            $output .= '<div class="adc-audiobook-card">';
            $output .= '<a href="' . esc_url($url) . '" class="adc-audiobook-link">';
            $output .= '<div class="adc-audiobook-cover">';
            $output .= '<img src="' . esc_url($cover) . '" alt="' . esc_attr($title) . '" loading="lazy" referrerpolicy="no-referrer">';
            $output .= '<div class="adc-audiobook-overlay">';
            $output .= '<span class="adc-audiobook-play-icon">&#9658;</span>';
            $output .= '</div>';
            $output .= '</div>';
            $output .= '<div class="adc-audiobook-info">';
            $output .= '<h3 class="adc-audiobook-title">' . esc_html($title) . '</h3>';
            $output .= '<p class="adc-audiobook-author">' . esc_html($author) . '</p>';
            $output .= '<span class="adc-audiobook-chapters">' . $chapters_count . ' ' . ADC_Utils::get_text('chapters', 'es') . '</span>';
            $output .= '</div>';
            $output .= '</a>';
            $output .= '</div>';
        }

        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Render audiobook detail page
     */
    public function render_audiobook_detail($slug)
    {
        $book = $this->get_audiobook_by_slug($slug);

        if (!$book) {
            return '<div class="adc-audiobook-error">
                <h2>' . ADC_Utils::get_text('audiobook_not_found', 'es') . '</h2>
                <p>' . ADC_Utils::get_text('audiobook_not_found_desc', 'es') . '</p>
                <a href="' . home_url('/audiolibros/') . '" class="adc-back-button">' . ADC_Utils::get_text('back_to_audiobooks', 'es') . '</a>
            </div>';
        }

        $title = $book['titulo']['es'];
        $author = $book['autor'];
        $description = $book['descripcion']['es'];
        $cover = $this->base_url . $book['portada'];
        $chapters = $book['audioCapitulos'];
        $book_id = $book['id'];

        $output = '<div class="adc-audiobook-detail" data-book-id="' . esc_attr($book_id) . '">';

        // Header with back button
        $output .= '<div class="adc-audiobook-header">';
        $output .= '<a href="' . home_url('/audiolibros/') . '" class="adc-back-link">';
        $output .= '<span class="adc-back-arrow">&larr;</span> ' . ADC_Utils::get_text('back_to_audiobooks', 'es');
        $output .= '</a>';
        $output .= '</div>';

        // Main content grid
        $output .= '<div class="adc-audiobook-content">';

        // Left column: Cover
        $output .= '<div class="adc-audiobook-cover-container">';
        $output .= '<img src="' . esc_url($cover) . '" alt="' . esc_attr($title) . '" class="adc-audiobook-cover-large" referrerpolicy="no-referrer">';
        $output .= '</div>';

        // Right column: Info + Chapters
        $output .= '<div class="adc-audiobook-details">';
        $output .= '<h1 class="adc-audiobook-main-title">' . esc_html($title) . '</h1>';
        $output .= '<p class="adc-audiobook-author-large">' . ADC_Utils::get_text('by', 'es') . ' <strong>' . esc_html($author) . '</strong></p>';
        $output .= '<p class="adc-audiobook-description">' . esc_html($description) . '</p>';

        // Continue listening banner (populated by JS) - después de descripción, antes de capítulos
        $output .= '<div id="adc-continue-listening" class="adc-continue-listening" style="display:none;">';
        $output .= '<div class="adc-continue-icon"><svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M8 5v14l11-7z"/></svg></div>';
        $output .= '<div class="adc-continue-info">';
        $output .= '<p class="adc-continue-chapter"></p>';
        $output .= '<p class="adc-continue-title"></p>';
        $output .= '</div>';
        $output .= '<button class="adc-continue-btn" id="adc-continue-btn">' . ADC_Utils::get_text('continue_listening', 'es') . '</button>';
        $output .= '</div>';

        // Chapters list
        $output .= '<div class="adc-audiobook-chapters-section">';
        $output .= '<h2 class="adc-chapters-title">';
        $output .= '<svg viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>';
        $output .= ADC_Utils::get_text('audiobook_label', 'es') . ' <span class="adc-chapters-count">(' . count($chapters) . ' ' . ADC_Utils::get_text('chapters', 'es') . ')</span>';
        $output .= '</h2>';

        $output .= '<div class="adc-chapters-list">';

        foreach ($chapters as $index => $chapter) {
            $chapter_num = $index + 1;
            $chapter_title = $chapter['titulo'];
            $audio_id = $chapter['id'];
            $audio_url = 'https://rabanidjar.com/audio-proxy.php?id=' . $audio_id;

            $output .= '<div class="adc-chapter-item" data-chapter="' . $chapter_num . '" data-libro="' . esc_attr($book_id) . '">';
            $output .= '<div class="adc-chapter-header">';
            $output .= '<span class="adc-chapter-number">' . sprintf('%02d', $chapter_num) . '</span>';
            $output .= '<span class="adc-chapter-title">' . esc_html($chapter_title) . '</span>';
            $output .= '<span class="adc-chapter-badge"></span>';
            $output .= '</div>';
            $output .= '<audio controls class="adc-chapter-audio" data-chapter="' . $chapter_num . '" data-libro="' . esc_attr($book_id) . '" preload="none">';
            $output .= '<source src="' . esc_url($audio_url) . '" type="audio/mp4">';
            $output .= '</audio>';
            $output .= '</div>';
        }

        $output .= '</div>'; // .adc-chapters-list
        $output .= '</div>'; // .adc-audiobook-chapters-section
        $output .= '</div>'; // .adc-audiobook-details
        $output .= '</div>'; // .adc-audiobook-content

        $output .= '</div>'; // .adc-audiobook-detail

        return $output;
    }

    /**
     * Enqueue CSS and JS only when needed
     */
    public function maybe_enqueue_assets()
    {
        $adc_type = get_query_var('adc_type');

        if ($adc_type === 'audiobooks' || $adc_type === 'audiobook') {
            wp_enqueue_style(
                'adc-audiobooks-style',
                ADC_PLUGIN_URL . 'assets/css/audiobooks.css',
                array(),
                '1.4'
            );

            wp_enqueue_script(
                'adc-audiobooks-script',
                ADC_PLUGIN_URL . 'assets/js/audiobooks.js',
                array('jquery'),
                '1.5',
                true
            );

            // Pass data to JS
            wp_localize_script('adc-audiobooks-script', 'adc_audiobooks_config', array(
                'storage_key' => 'adc_audiobook_progress',
                'texts' => array(
                    'chapter' => ADC_Utils::get_text('chapter', 'es'),
                    'minute' => ADC_Utils::get_text('minute', 'es'),
                    'listened' => ADC_Utils::get_text('listened', 'es'),
                    'continue_from' => ADC_Utils::get_text('continue_from', 'es')
                )
            ));
        }
    }

    /**
     * Clear audiobooks cache
     */
    public function clear_cache()
    {
        delete_transient('adc_audiobooks_data');
    }
}

// Initialize the class
ADC_Audiobooks::get_instance();
