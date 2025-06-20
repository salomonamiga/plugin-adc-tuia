<?php
/**
 * Plugin Name: ADC Video Display
 * Description: Muestra videos desde el sistema ADC en WordPress - Multiidioma
 * Version: 3.0
 * Author: TuTorah Development Team
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ADC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ADC_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once ADC_PLUGIN_DIR . 'adc-api.php';
require_once ADC_PLUGIN_DIR . 'adc-admin.php';
require_once ADC_PLUGIN_DIR . 'adc-menu.php';
require_once ADC_PLUGIN_DIR . 'adc-search.php';

/**
 * Main plugin class
 */
class ADC_Video_Display
{
    private $api;
    private $options;
    private $language;

    /**
     * Constructor
     */
    public function __construct($language = 'es')
    {
        $this->options = get_option('adc-video-display');
        $this->language = $language;
        $this->api = new ADC_API($language);

        // Register hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        // Register shortcodes for each language
        add_shortcode('adc_content', array($this, 'display_content_es'));
        add_shortcode('adc_content_en', array($this, 'display_content_en'));
        add_shortcode('adc_content_he', array($this, 'display_content_he'));

        // AJAX handlers
        add_action('wp_ajax_adc_search', array($this, 'handle_ajax_search'));
        add_action('wp_ajax_nopriv_adc_search', array($this, 'handle_ajax_search'));

        add_action('wp_ajax_adc_get_programs_menu', array($this, 'handle_ajax_get_programs_menu'));
        add_action('wp_ajax_nopriv_adc_get_programs_menu', array($this, 'handle_ajax_get_programs_menu'));

        // Handle custom URLs
        add_filter('request', array($this, 'handle_custom_urls'));
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts()
    {
        // Enqueue CSS
        wp_enqueue_style(
            'adc-style',
            ADC_PLUGIN_URL . 'style.css',
            array(),
            '3.0'
        );

        // Enqueue JavaScript
        wp_enqueue_script(
            'adc-script',
            ADC_PLUGIN_URL . 'script.js',
            array('jquery'),
            '3.0',
            true
        );

        // Localize script with options
        wp_localize_script('adc-script', 'adc_config', array(
            'autoplay' => isset($this->options['enable_autoplay']) ? $this->options['enable_autoplay'] : '1',
            'countdown' => isset($this->options['autoplay_countdown']) ? $this->options['autoplay_countdown'] : '5',
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('adc_nonce'),
            'search_page' => home_url('/'),
            'debug' => isset($this->options['debug_mode']) && $this->options['debug_mode'] === '1'
        ));
    }

    /**
     * Handle AJAX search
     */
    public function handle_ajax_search()
    {
        check_ajax_referer('adc_nonce', 'nonce');

        $search_term = sanitize_text_field($_POST['search']);
        $language = isset($_POST['language']) ? sanitize_text_field($_POST['language']) : 'es';

        if (empty($search_term)) {
            wp_send_json_error('No search term provided');
        }

        // Create API instance for the specific language
        $api = new ADC_API($language);
        $results = $api->search_materials($search_term);

        wp_send_json_success($results);
    }

    /**
     * Handle AJAX get programs menu
     */
    public function handle_ajax_get_programs_menu()
    {
        // Verificar nonce si existe
        if (isset($_POST['nonce']) && !empty($_POST['nonce'])) {
            if (!wp_verify_nonce($_POST['nonce'], 'adc_nonce')) {
                wp_send_json_error('Invalid nonce');
                return;
            }
        }

        $language = isset($_POST['language']) ? sanitize_text_field($_POST['language']) : 'es';

        try {
            // Create API instance for the specific language
            $api = new ADC_API($language);

            // Verificar que la API esté configurada
            if (!$api->is_configured()) {
                wp_send_json_error('API not configured');
                return;
            }

            // Get programs for menu
            $programs = $api->get_all_programs_for_menu();

            if (empty($programs)) {
                wp_send_json_error('No programs found');
                return;
            }

            wp_send_json_success($programs);

        } catch (Exception $e) {
            wp_send_json_error('Internal server error');
        }
    }

    /**
     * Handle custom URLs
     */
    public function handle_custom_urls($vars)
    {
        return $vars;
    }

    /**
     * Shortcode handlers for each language
     */
    public function display_content_es($atts)
    {
        $this->language = 'es';
        $this->api = new ADC_API('es');
        return $this->display_content($atts);
    }

    public function display_content_en($atts)
    {
        $this->language = 'en';
        $this->api = new ADC_API('en');
        return $this->display_content($atts);
    }

    public function display_content_he($atts)
    {
        $this->language = 'he';
        $this->api = new ADC_API('he');
        return $this->display_content($atts);
    }

    /**
     * Main content display handler
     */
    public function display_content($atts)
    {
        // Check if API is configured
        if (!$this->api->is_configured()) {
            return '<div class="adc-error">El plugin ADC Video Display no está configurado. Por favor configura la API en el panel de administración.</div>';
        }

        // Check for search results
        if (isset($_GET['adc_search'])) {
            // Ensure we don't duplicate search results when they already exist in the content
            global $post;
            $search_results_exist = false;

            if ($post && $post->post_content) {
                $search_results_exist = (strpos($post->post_content, 'adc-search-results-container') !== false);
            }

            if (!$search_results_exist) {
                return $this->display_search_results();
            } else {
                return ''; // Don't add more search results if they're already there
            }
        }

        // Determine what to display based on URL parameters
        $category_slug = isset($_GET['categoria']) ? sanitize_text_field($_GET['categoria']) : '';
        $video_slug = isset($_GET['video']) ? sanitize_text_field($_GET['video']) : '';

        // Display appropriate content
        if (!empty($video_slug) && !empty($category_slug)) {
            return $this->display_video($category_slug, $video_slug);
        } elseif (!empty($category_slug)) {
            return $this->display_category_videos($category_slug);
        } else {
            return $this->display_categories_grid();
        }
    }

    /**
     * Display search results
     */
    private function display_search_results()
    {
        $search_term = sanitize_text_field($_GET['adc_search']);

        if (empty($search_term)) {
            return '<div class="adc-error">Por favor ingresa un término de búsqueda.</div>';
        }

        $results = $this->api->search_materials($search_term);

        $output = '<div class="adc-search-results-container">';

        if (empty($results)) {
            // Get language-specific text
            $no_results_text = array(
                'es' => 'No encontramos lo que buscabas, pero quizás te interesen estos videos:',
                'en' => 'We couldn\'t find what you were looking for, but you might be interested in these videos:',
                'he' => 'לא מצאנו את מה שחיפשת, אבל אולי הסרטונים האלה יעניינו אותך:'
            );

            $output .= '<h2 class="adc-recommended-title">' . $no_results_text[$this->language] . '</h2>';
            $output .= $this->get_recommended_videos();
        } else {
            $results_text = array(
                'es' => 'Resultados de búsqueda para',
                'en' => 'Search results for',
                'he' => 'תוצאות חיפוש עבור'
            );

            $output .= '<h1 class="adc-search-results-title">' . $results_text[$this->language] . ': "' . esc_html($search_term) . '"</h1>';
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
     * Get recommended videos for empty search results
     */
    private function get_recommended_videos()
    {
        $programs = $this->api->get_programs();

        if (empty($programs)) {
            return '<div class="adc-recommended-empty">No hay recomendaciones disponibles en este momento.</div>';
        }

        // Get videos from all programs
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

        // Shuffle and take only 8
        shuffle($all_videos);
        $recommended_videos = array_slice($all_videos, 0, 8);

        $output = '<div class="adc-recommended-videos">';

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
     * Render a single video card
     */
    private function render_video_card($video, $url)
    {
        $program_text = array(
            'es' => 'Programa',
            'en' => 'Program',
            'he' => 'תוכנית'
        );

        $duration_text = array(
            'es' => 'Duración',
            'en' => 'Duration',
            'he' => 'משך'
        );

        $output = '<div class="adc-search-video-item">';
        $output .= '<a href="' . esc_url($url) . '" class="adc-search-video-link">';
        $output .= '<div class="adc-search-thumbnail">';
        $output .= '<img src="' . esc_url($this->api->get_thumbnail_url($video['id'])) . '" alt="' . esc_attr($video['title']) . '">';
        $output .= '<div class="adc-search-play-icon"></div>';
        $output .= '</div>';

        $output .= '<div class="adc-search-info">';
        $output .= '<h3 class="adc-search-title">' . esc_html($video['title']) . '</h3>';
        $output .= '<div class="adc-search-program">' . $program_text[$this->language] . ': ' . esc_html($video['category']) . '</div>';
        $output .= '<div class="adc-search-duration">' . $duration_text[$this->language] . ': ' . esc_html($video['duration']) . '</div>';
        $output .= '</div>';
        $output .= '</a>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Display categories grid with coming soon functionality
     */
    private function display_categories_grid()
    {
        // Get programs with custom order
        $programs = $this->api->get_programs_with_custom_order();

        if (empty($programs)) {
            $no_programs_text = array(
                'es' => 'No se encontraron programas disponibles.',
                'en' => 'No programs available.',
                'he' => 'אין תוכניות זמינות.'
            );
            return '<div class="adc-error">' . $no_programs_text[$this->language] . '</div>';
        }

        $output = '<div class="adc-categories-grid">';
        $output .= '<div class="adc-categories-row">';

        foreach ($programs as $program) {
            $output .= $this->render_category_card($program);
        }

        $output .= '</div></div>';

        return $output;
    }

    /**
     * Render a single category card (regular or coming soon)
     */
    private function render_category_card($program)
    {
        $slug = $this->slugify($program['name']);

        // Check if this program has videos
        $has_videos = $this->api->program_has_videos($program['id']);
        $is_coming_soon = !$has_videos && isset($program['cover']) && !empty($program['cover']);

        $output = '<div class="adc-category-card-wrapper">';

        if ($is_coming_soon) {
            // Coming soon - no link, special styling
            $output .= '<div class="adc-category-card adc-coming-soon-card">';
        } else {
            // Regular clickable card
            $output .= '<a class="adc-category-card" href="?categoria=' . esc_attr($slug) . '">';
        }

        $output .= '<div class="adc-category-image-circle">';

        if (isset($program['cover'])) {
            $output .= '<img src="' . esc_url($program['cover']) . '" alt="' . esc_attr($program['name']) . '">';
        } else {
            $output .= '<img src="' . ADC_PLUGIN_URL . 'assets/img/no-cover.jpg" alt="' . esc_attr($program['name']) . '">';
        }

        // Add coming soon overlay
        if ($is_coming_soon) {
            $coming_soon_text = $this->api->get_coming_soon_text();

            $output .= '<div class="adc-coming-soon-overlay">';
            $output .= '<span class="adc-coming-soon-text">' . esc_html($coming_soon_text) . '</span>';
            $output .= '<div class="adc-coming-soon-lock">🔒</div>';
            $output .= '</div>';
        }

        $output .= '</div>';
        $output .= '<div class="adc-category-name">' . esc_html($program['name']) . '</div>';

        if ($is_coming_soon) {
            $output .= '</div>'; // Close div
        } else {
            $output .= '</a>'; // Close anchor
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Display videos from a category - MEJORADO CON SOPORTE PARA CLIP PROMOCIONAL
     */
    private function display_category_videos($category_slug)
    {
        // Find category by slug
        $programs = $this->api->get_programs();
        $category = null;

        foreach ($programs as $program) {
            if ($this->slugify($program['name']) == $category_slug) {
                $category = $program;
                break;
            }
        }

        if (!$category) {
            $not_found_text = array(
                'es' => 'Categoría no encontrada.',
                'en' => 'Category not found.',
                'he' => 'קטגוריה לא נמצאה.'
            );
            return '<div class="adc-error">' . $not_found_text[$this->language] . '</div>';
        }

        // Get materials
        $materials = $this->api->get_materials($category['id']);

        if (empty($materials)) {
            $no_videos_text = array(
                'es' => 'No se encontraron videos en esta categoría.',
                'en' => 'No videos found in this category.',
                'he' => 'לא נמצאו סרטונים בקטגוריה זו.'
            );
            return '<div class="adc-error">' . $no_videos_text[$this->language] . '</div>';
        }

        // Group by season
        $seasons = $this->api->group_materials_by_season($materials);

        $home_url = home_url('/');
        if ($this->language !== 'es') {
            $home_url .= $this->language . '/';
        }

        $back_text = array(
            'es' => 'Volver a Programas',
            'en' => 'Back to Programs',
            'he' => 'חזרה לתוכניות'
        );

        $output = '<div class="adc-category-header">';
        $output .= '<h1 class="adc-category-title">' . esc_html($category['name']) . '</h1>';
        $output .= '<a href="' . esc_url($home_url) . '" class="adc-back-button">' . $back_text[$this->language] . '</a>';
        $output .= '</div>';

        // NUEVO: Mostrar clip promocional si existe
        if (isset($category['clip']) && !empty($category['clip'])) {
            $output .= $this->render_promotional_clip($category);
        }
        // TEMPORAL: Para probar el clip promocional - REMOVER DESPUÉS
        else if ($category['name'] === 'Historias del Tanaj') {
            // Crear datos temporales para el clip
            $temp_category = $category;
            $temp_category['clip'] = 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4';
            $temp_category['description'] = 'Esta es una descripción temporal del programa para mostrar cómo se ve el clip promocional.';
            $output .= $this->render_promotional_clip($temp_category);
        }

        // Videos per row setting
        $videos_per_row = isset($this->options['videos_per_row']) ? $this->options['videos_per_row'] : '4';

        foreach ($seasons as $season_num => $season_videos) {
            $season_name = $this->api->get_season_name($season_num);
            $output .= '<h2 class="adc-season-header"><span>' . esc_html($season_name) . '</span></h2>';

            $output .= '<div class="adc-videos-grid">';
            $output .= '<div class="adc-videos-row cols-' . $videos_per_row . '">';

            foreach ($season_videos as $video) {
                $video_slug = $this->slugify($video['title']);
                $thumbnail_url = $this->api->get_thumbnail_url($video['id']);

                $duration_text = array(
                    'es' => 'Duración',
                    'en' => 'Duration',
                    'he' => 'משך'
                );

                $output .= '<div class="adc-video-item">';
                $output .= '<a href="?categoria=' . esc_attr($category_slug) . '&video=' . esc_attr($video_slug) . '" class="adc-video-link">';
                $output .= '<div class="adc-video-thumbnail">';
                $output .= '<img src="' . esc_url($thumbnail_url) . '" alt="' . esc_attr($video['title']) . '">';
                $output .= '<div class="adc-video-play-icon"></div>';
                $output .= '</div>';

                $output .= '<div class="adc-video-info">';
                $output .= '<h3 class="adc-video-title">' . esc_html($video['title']) . '</h3>';
                $output .= '<span class="adc-video-duration">' . $duration_text[$this->language] . ': ' . esc_html($video['duration']) . '</span>';
                $output .= '</div>';
                $output .= '</a>';
                $output .= '</div>';
            }

            $output .= '</div></div>';
        }

        return $output;
    }

    /**
     * NUEVO: Render promotional clip for category
     */
    private function render_promotional_clip($category)
    {
        $promo_text = array(
            'es' => 'Acerca de este programa',
            'en' => 'About this program',
            'he' => 'אודות התוכנית הזו'
        );

        $output = '<div class="adc-promotional-clip-section">';
        $output .= '<h2 class="adc-promotional-clip-title">' . $promo_text[$this->language] . '</h2>';

        // Video.js for promotional clip
        $output .= '<link href="https://unpkg.com/video.js@8.10.0/dist/video-js.min.css" rel="stylesheet">';
        $output .= '<script src="https://unpkg.com/video.js@8.10.0/dist/video.min.js"></script>';

        $clip_id = 'adc-promo-player-' . uniqid();

        $output .= '<div class="adc-promotional-video-player" style="position:relative; padding-top:56.25%; margin-bottom:30px;">';
        $output .= '<video id="' . $clip_id . '" class="video-js vjs-default-skin vjs-big-play-centered" controls playsinline preload="auto" style="position:absolute; top:0; left:0; width:100%; height:100%;" data-setup="{}">';
        $output .= '<source src="' . esc_url($category['clip']) . '" type="video/mp4">';
        $output .= '</video>';
        $output .= '</div>';

        // JavaScript for promotional clip player
        $output .= '<script>
        document.addEventListener("DOMContentLoaded", function() {
            if (typeof videojs !== "undefined" && document.getElementById("' . $clip_id . '")) {
                var promoPlayer = videojs("' . $clip_id . '");
                promoPlayer.ready(function() {
                    promoPlayer.volume(0.7);
                });
            }
        });
        </script>';

        // Add description if available
        if (isset($category['description']) && !empty($category['description'])) {
            $output .= '<div class="adc-category-description">';
            $output .= '<p>' . esc_html($category['description']) . '</p>';
            $output .= '</div>';
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Display single video
     */
    private function display_video($category_slug, $video_slug)
    {
        // Find category
        $programs = $this->api->get_programs();
        $category = null;

        foreach ($programs as $program) {
            if ($this->slugify($program['name']) == $category_slug) {
                $category = $program;
                break;
            }
        }

        if (!$category) {
            $not_found_text = array(
                'es' => 'Categoría no encontrada.',
                'en' => 'Category not found.',
                'he' => 'קטגוריה לא נמצאה.'
            );
            return '<div class="adc-error">' . $not_found_text[$this->language] . '</div>';
        }

        // Find video
        $materials = $this->api->get_materials($category['id']);
        $video = null;
        $video_index = -1;

        for ($i = 0; $i < count($materials); $i++) {
            if ($this->slugify($materials[$i]['title']) == $video_slug) {
                $video = $materials[$i];
                $video_index = $i;
                break;
            }
        }

        if (!$video) {
            $video_not_found_text = array(
                'es' => 'Video no encontrado.',
                'en' => 'Video not found.',
                'he' => 'הסרטון לא נמצא.'
            );
            return '<div class="adc-error">' . $video_not_found_text[$this->language] . '</div>';
        }

        // Find next video
        $next_video = null;
        $next_url = '';
        if ($video_index < count($materials) - 1) {
            $next_video = $materials[$video_index + 1];
            $next_slug = $this->slugify($next_video['title']);
            $base_url = home_url('/');
            if ($this->language !== 'es') {
                $base_url .= $this->language . '/';
            }
            $next_url = $base_url . '?categoria=' . $category_slug . '&video=' . $next_slug;
        }

        // Back button text
        $back_text = array(
            'es' => 'Volver a',
            'en' => 'Back to',
            'he' => 'חזרה ל'
        );

        $output = '<div class="adc-video-container">';

        // Video title and back button container
        $output .= '<div class="adc-video-header">';
        $output .= '<h1 class="adc-video-main-title">' . esc_html($video['title']) . '</h1>';
        $output .= '<a href="?categoria=' . esc_attr($category_slug) . '" class="adc-back-program-button">' .
            $back_text[$this->language] . ' ' . esc_html($category['name']) . '</a>';
        $output .= '</div>';

        // Video.js
        $output .= '<link href="https://unpkg.com/video.js@8.10.0/dist/video-js.min.css" rel="stylesheet">';
        $output .= '<script src="https://unpkg.com/video.js@8.10.0/dist/video.min.js"></script>';

        // Player with proper aspect ratio
        $output .= '<div class="adc-video-player" style="position:relative; padding-top:56.25%;">';
        $output .= '<video id="adc-player" class="video-js vjs-default-skin vjs-big-play-centered" controls playsinline preload="auto" style="position:absolute; top:0; left:0; width:100%; height:100%;" data-setup="{}">';
        $output .= '<source src="' . esc_url($video['video']) . '" type="video/mp4">';
        $output .= '</video>';

        // Autoplay overlay
        if ($next_url) {
            $next_text = array(
                'es' => 'Siguiente video en',
                'en' => 'Next video in',
                'he' => 'הסרטון הבא בעוד'
            );

            $seconds_text = array(
                'es' => 'segundos',
                'en' => 'seconds',
                'he' => 'שניות'
            );

            $watch_now_text = array(
                'es' => 'Ver ahora',
                'en' => 'Watch now',
                'he' => 'צפה עכשיו'
            );

            $cancel_text = array(
                'es' => 'Cancelar',
                'en' => 'Cancel',
                'he' => 'ביטול'
            );

            $output .= '<div id="adc-next-overlay">';
            $output .= '<p>' . $next_text[$this->language] . ' <span id="adc-countdown">5</span> ' . $seconds_text[$this->language] . '...</p>';
            $output .= '<a href="' . esc_url($next_url) . '">' . $watch_now_text[$this->language] . '</a><br>';
            $output .= '<button id="adc-cancel-autoplay">' . $cancel_text[$this->language] . '</button>';
            $output .= '</div>';
        }

        $output .= '</div>';

        // Next button
        if ($next_url) {
            $next_button_text = array(
                'es' => 'Ver siguiente video',
                'en' => 'Watch next video',
                'he' => 'צפה בסרטון הבא'
            );

            $output .= '<div class="adc-next-button-container">';
            $output .= '<a href="' . esc_url($next_url) . '" class="adc-view-all-button">' . $next_button_text[$this->language] . '</a>';
            $output .= '</div>';
        }

        // Related videos
        $related_videos = $this->get_smart_related_videos($materials, $video_index, 8);

        $more_videos_text = array(
            'es' => 'Más videos de',
            'en' => 'More videos from',
            'he' => 'סרטונים נוספים מ'
        );

        $output .= '<h2 class="adc-related-videos-title">' . $more_videos_text[$this->language] . ' ' . esc_html($category['name']) . '</h2>';
        $output .= '<div class="adc-related-videos-grid">';
        $output .= '<div class="adc-videos-row" id="adc-related-videos-container">';

        foreach ($related_videos as $index => $related_video) {
            $related_slug = $this->slugify($related_video['title']);

            $duration_text = array(
                'es' => 'Duración',
                'en' => 'Duration',
                'he' => 'משך'
            );

            $output .= '<div class="adc-video-item adc-related-video-item">';
            $output .= '<a href="?categoria=' . esc_attr($category_slug) . '&video=' . esc_attr($related_slug) . '" class="adc-video-link">';
            $output .= '<div class="adc-video-thumbnail">';
            $output .= '<img src="' . esc_url($this->api->get_thumbnail_url($related_video['id'])) . '" alt="' . esc_attr($related_video['title']) . '">';
            $output .= '<div class="adc-video-play-icon"></div>';
            $output .= '</div>';

            $output .= '<div class="adc-video-info">';
            $output .= '<h3 class="adc-video-title">' . esc_html($related_video['title']) . '</h3>';
            $output .= '<span class="adc-video-duration">' . $duration_text[$this->language] . ': ' . esc_html($related_video['duration']) . '</span>';
            $output .= '</div>';
            $output .= '</a>';
            $output .= '</div>';
        }

        $output .= '</div></div>';

        // Video player configuration
        $autoplay = isset($this->options['enable_autoplay']) ? $this->options['enable_autoplay'] : '1';
        $countdown = isset($this->options['autoplay_countdown']) ? $this->options['autoplay_countdown'] : '5';

        if ($next_url && $autoplay == '1') {
            $output .= $this->generate_video_player_script($next_url, $countdown);
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Generate video player script
     */
    private function generate_video_player_script($next_url, $countdown)
    {
        return '<script>
        document.addEventListener("DOMContentLoaded", function() {
            if (typeof videojs !== "undefined" && document.getElementById("adc-player")) {
                var player = videojs("adc-player");
                var overlay = document.getElementById("adc-next-overlay");
                var countdownEl = document.getElementById("adc-countdown");
                var cancelBtn = document.getElementById("adc-cancel-autoplay");
                var interval = null;
                var seconds = ' . intval($countdown) . ';
                var cancelled = false;
                
                player.ready(function() {
                    player.volume(0.5);
                    
                    // Add custom buttons
                    var Button = videojs.getComponent("Button");
                    
                    var rewindButton = videojs.extend(Button, {
                        constructor: function() {
                            Button.apply(this, arguments);
                            this.controlText("Rewind 10 seconds");
                            this.addClass("vjs-rewind-button");
                            this.el().innerHTML = "⏪ 10s";
                        },
                        handleClick: function() {
                            player.currentTime(player.currentTime() - 10);
                        }
                    });
                    videojs.registerComponent("RewindButton", rewindButton);
                    player.getChild("controlBar").addChild("RewindButton", {}, 0);
                    
                    var forwardButton = videojs.extend(Button, {
                        constructor: function() {
                            Button.apply(this, arguments);
                            this.controlText("Forward 10 seconds");
                            this.addClass("vjs-forward-button");
                            this.el().innerHTML = "10s ⏩";
                        },
                        handleClick: function() {
                            player.currentTime(player.currentTime() + 10);
                        }
                    });
                    videojs.registerComponent("ForwardButton", forwardButton);
                    player.getChild("controlBar").addChild("ForwardButton", {}, 2);
                });
                
                player.on("ended", function() {
                    if (!overlay || cancelled) return;
                    
                    if (player.isFullscreen()) {
                        player.exitFullscreen();
                    }
                    
                    setTimeout(function() {
                        overlay.style.display = "block";
                        seconds = ' . intval($countdown) . ';
                        countdownEl.textContent = seconds;
                        interval = setInterval(function() {
                            seconds--;
                            countdownEl.textContent = seconds;
                            if (seconds <= 0 && !cancelled) {
                                clearInterval(interval);
                                window.location.href = "' . $next_url . '";
                            }
                        }, 1000);
                    }, 300);
                });
                
                if (cancelBtn) {
                    cancelBtn.addEventListener("click", function() {
                        cancelled = true;
                        if (overlay) {
                            overlay.innerHTML = \'<p style="color:#aaa">Autoplay cancelado</p>\';
                        }
                        clearInterval(interval);
                    });
                }
            }
        });
        </script>';
    }

    /**
     * Get smart related videos
     */
    private function get_smart_related_videos($materials, $current_index, $limit = 8)
    {
        $related = array();
        $total_videos = count($materials);

        if ($total_videos <= $limit + 1) {
            for ($i = 0; $i < $total_videos; $i++) {
                if ($i != $current_index) {
                    $materials[$i]['original_index'] = $i;
                    $related[] = $materials[$i];
                }
            }
            return $related;
        }

        $added = 0;
        $position = $current_index + 1;

        while ($added < $limit) {
            $index = $position % $total_videos;

            if ($index == $current_index) {
                $position++;
                continue;
            }

            $materials[$index]['original_index'] = $index;
            $related[] = $materials[$index];
            $added++;
            $position++;
        }

        return $related;
    }

    /**
     * Convert title to slug
     */
    private function slugify($text)
    {
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
}

// Initialize plugin
function adc_video_display_init()
{
    // Don't initialize multiple times
    if (defined('ADC_VIDEO_DISPLAY_INITIALIZED')) {
        return;
    }

    define('ADC_VIDEO_DISPLAY_INITIALIZED', true);

    // Create main instance (for backward compatibility)
    new ADC_Video_Display();
}
add_action('plugins_loaded', 'adc_video_display_init');

// Activation hook
register_activation_hook(__FILE__, 'adc_video_display_activate');
function adc_video_display_activate()
{
    // Create default options
    $default_options = array(
        'api_token' => '',
        'api_url' => 'https://api.tutorah.tv/v1',
        'videos_per_row' => '4',
        'enable_autoplay' => '1',
        'autoplay_countdown' => '5',
        'enable_search' => '1',
        'related_videos_count' => '8',
        'debug_mode' => '0'
    );

    add_option('adc-video-display', $default_options);

    // Initialize program order options for each language
    add_option('adc_programs_order_es', array());
    add_option('adc_programs_order_en', array());
    add_option('adc_programs_order_he', array());
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'adc_video_display_deactivate');
function adc_video_display_deactivate()
{
    // Clean up if needed
}