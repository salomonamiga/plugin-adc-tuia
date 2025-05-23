<?php
/**
 * Plugin Name: ADC Video Display
 * Description: Muestra videos desde el sistema ADC en WordPress
 * Version: 2.0
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
class ADC_Video_Display {
    
    private $api;
    private $options;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->options = get_option('adc-video-display');
        $this->api = new ADC_API();
        
        // Register hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('adc_content', array($this, 'display_content'));
        
        // AJAX handlers
        add_action('wp_ajax_adc_search', array($this, 'handle_ajax_search'));
        add_action('wp_ajax_nopriv_adc_search', array($this, 'handle_ajax_search'));
        
        // Handle custom URLs
        add_filter('request', array($this, 'handle_custom_urls'));
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        // Enqueue CSS with forced reload
        wp_enqueue_style(
            'adc-style',
            ADC_PLUGIN_URL . 'style.css',
            array(),
            time() // Force reload CSS
        );
        
        // Enqueue JavaScript with forced reload
        wp_enqueue_script(
            'adc-script',
            ADC_PLUGIN_URL . 'script.js',
            array('jquery'),
            time(), // Force reload JS
            true
        );
        
        // Localize script with options
        wp_localize_script('adc-script', 'adc_config', array(
            'autoplay' => isset($this->options['enable_autoplay']) ? $this->options['enable_autoplay'] : '1',
            'countdown' => isset($this->options['autoplay_countdown']) ? $this->options['autoplay_countdown'] : '5',
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('adc_nonce'),
            'search_page' => home_url('/')
        ));
    }
    
    /**
     * Handle AJAX search
     */
    public function handle_ajax_search() {
        check_ajax_referer('adc_nonce', 'nonce');
        
        $search_term = sanitize_text_field($_POST['search']);
        
        if (empty($search_term)) {
            wp_send_json_error('No search term provided');
        }
        
        $results = $this->api->search_materials($search_term);
        
        wp_send_json_success($results);
    }
    
    /**
     * Handle custom URLs
     */
    public function handle_custom_urls($vars) {
        return $vars;
    }
    
    /**
     * Convert title to slug
     */
    private function slugify($text) {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = trim($text, '-');
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = strtolower($text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        return $text;
    }

    /**
     * Main shortcode handler
     */
    public function display_content($atts) {
        // Check if API is configured
        if (!$this->api->is_configured()) {
            return '<div class="adc-error">El plugin ADC Video Display no está configurado. Por favor configura la API en el panel de administración.</div>';
        }
        
        // Get current parameters
        $section = $this->api->get_section();
        $section_name = $this->api->get_section_name();
        
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
        // Since this site is IA exclusive, we'll use simplified URLs
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
    private function display_search_results() {
        $search_term = sanitize_text_field($_GET['adc_search']);
        
        if (empty($search_term)) {
            return '<div class="adc-error">Por favor ingresa un término de búsqueda.</div>';
        }
        
        $results = $this->api->search_materials($search_term);
        
        $output = '<div class="adc-search-results-container">';
        
        if (empty($results)) {
            // Mostrar solo un mensaje para resultados vacíos
            $output .= '<h2 class="adc-recommended-title">No encontramos lo que buscabas, pero quizás te interesen estos videos:</h2>';
            
            // Agregar vídeos recomendados
            $output .= $this->get_recommended_videos();
        } else {
            // Mostrar el título de resultados encontrados
            $output .= '<h1 class="adc-search-results-title">Resultados de búsqueda para: "' . esc_html($search_term) . '"</h1>';
            
            // Display results in grid
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
    private function get_recommended_videos() {
        // Get all programs
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
     * Display categories grid
     */
    private function display_categories_grid() {
        // Usar el nuevo método que respeta el orden personalizado
        $programs = $this->api->get_programs_with_custom_order();
        
        if (empty($programs)) {
            return '<div class="adc-error">No se encontraron programas disponibles.</div>';
        }
        
        $section = $this->api->get_section();
        $section_name = $this->api->get_section_name();
        
        $output = '<div class="adc-categories-grid">';
        
        $output .= '<div class="adc-categories-row">';
        
        foreach ($programs as $program) {
            $slug = $this->slugify($program['name']);
            
            $output .= '<div class="adc-category-card-wrapper">';
            $output .= '<a class="adc-category-card" href="?categoria=' . esc_attr($slug) . '">';
            
            $output .= '<div class="adc-category-image-circle">';
            if (isset($program['cover'])) {
                $output .= '<img src="' . esc_url($program['cover']) . '" alt="' . esc_attr($program['name']) . '">';
            } else {
                $output .= '<img src="' . ADC_PLUGIN_URL . 'assets/img/no-cover.jpg" alt="' . esc_attr($program['name']) . '">';
            }
            $output .= '</div>';
            
            $output .= '<div class="adc-category-name">' . esc_html($program['name']) . '</div>';
            $output .= '</a>';
            $output .= '</div>';
        }
        
        $output .= '</div></div>';
        
        return $output;
    }

    /**
     * Display videos from a category
     */
    private function display_category_videos($category_slug) {
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
            return '<div class="adc-error">Categoría no encontrada.</div>';
        }
        
        // Get materials
        $materials = $this->api->get_materials($category['id']);
        
        if (empty($materials)) {
            return '<div class="adc-error">No se encontraron videos en esta categoría.</div>';
        }
        
        // Group by season
        $seasons = $this->api->group_materials_by_season($materials);
        
        $section = $this->api->get_section();
        $home_url = home_url('/');
        
        $output = '<div class="adc-category-header">';
        $output .= '<h1 class="adc-category-title">' . esc_html($category['name']) . '</h1>';
        $output .= '<a href="' . esc_url($home_url) . '" class="adc-back-button">Volver a Programas</a>';
        $output .= '</div>';
        
        // Videos per row setting
        $videos_per_row = isset($this->options['videos_per_row']) ? $this->options['videos_per_row'] : '4';
        
        foreach ($seasons as $season_num => $season_videos) {
            $season_name = $this->api->get_season_name($season_num);
            $output .= '<h2 class="adc-season-header"><span>' . esc_html($season_name) . '</span></h2>';
            
            $output .= '<div class="adc-videos-grid">';
            $output .= '<div class="adc-videos-row cols-' . $videos_per_row . '">';
            
            foreach ($season_videos as $video) {
                $video_slug = $this->slugify($video['title']);
                
                // Generate proper thumbnail URL with quality suffix
                $thumbnail_url = $this->api->get_thumbnail_url($video['id']);
                
                $output .= '<div class="adc-video-item">';
                $output .= '<a href="?categoria=' . esc_attr($category_slug) . '&video=' . esc_attr($video_slug) . '" class="adc-video-link">';
                $output .= '<div class="adc-video-thumbnail">';
                $output .= '<img src="' . esc_url($thumbnail_url) . '" alt="' . esc_attr($video['title']) . '">';
                $output .= '<div class="adc-video-play-icon"></div>';
                $output .= '</div>';
                
                $output .= '<div class="adc-video-info">';
                $output .= '<h3 class="adc-video-title">' . esc_html($video['title']) . '</h3>';
                $output .= '<span class="adc-video-duration">Duración: ' . esc_html($video['duration']) . '</span>';
                $output .= '</div>';
                $output .= '</a>';
                $output .= '</div>';
            }
            
            $output .= '</div></div>';
        }
        
        return $output;
    }
    
    /**
     * Display single video
     */
    private function display_video($category_slug, $video_slug) {
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
            return '<div class="adc-error">Categoría no encontrada.</div>';
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
            return '<div class="adc-error">Video no encontrado.</div>';
        }
        
        // Find next video
        $next_video = null;
        $next_url = '';
        if ($video_index < count($materials) - 1) {
            $next_video = $materials[$video_index + 1];
            $next_slug = $this->slugify($next_video['title']);
            
            $next_url = home_url('/?categoria=' . $category_slug . '&video=' . $next_slug);
        }
        
        // Start output
        $section = $this->api->get_section();
        $home_url = home_url('/');
        
        $output = '<div class="adc-video-container">';
        
        // Video title and back button container
        $output .= '<div class="adc-video-header">';
        $output .= '<h1 class="adc-video-main-title">' . esc_html($video['title']) . '</h1>';
        $output .= '<a href="?categoria=' . esc_attr($category_slug) . '" class="adc-back-program-button">Volver a ' . esc_html($category['name']) . '</a>';
        $output .= '</div>';
        
        // Video.js
        $output .= '<link href="https://unpkg.com/video.js@8.10.0/dist/video-js.min.css" rel="stylesheet">';
        $output .= '<script src="https://unpkg.com/video.js@8.10.0/dist/video.min.js"></script>';

        // Cargar nuestro CSS DESPUÉS del CDN para que tenga prioridad
        $output .= '<style>
        /* MÁXIMA ESPECIFICIDAD - ANULAR VIDEO.JS CDN */
        .video-js.vjs-default-skin .vjs-progress-holder .vjs-play-progress,
        .video-js.vjs-default-skin .vjs-progress-holder .vjs-play-progress:before,
        .video-js.vjs-default-skin .vjs-slider-bar,
        .video-js.vjs-default-skin .vjs-slider-bar:before,
        .vjs-default-skin .vjs-progress-holder .vjs-play-progress,
        .vjs-default-skin .vjs-slider-bar,
        #adc-player .vjs-play-progress,
        #adc-player .vjs-slider-bar,
        .vjs-play-progress,
        .vjs-slider-bar { 
            background: #6EC1E4 !important; 
            background-color: #6EC1E4 !important; 
        }
        .video-js .vjs-control .vjs-icon-placeholder:before,
        .video-js .vjs-play-control .vjs-icon-placeholder:before,
        .video-js .vjs-big-play-button .vjs-icon-placeholder:before { 
            color: #6EC1E4 !important; 
        }
        .video-js .vjs-volume-bar .vjs-volume-level { 
            background: #6EC1E4 !important; 
            background-color: #6EC1E4 !important; 
        }
        </style>';
        
        // Player with proper aspect ratio
        $output .= '<div class="adc-video-player" style="position:relative; padding-top:56.25%;">';
        $output .= '<video id="adc-player" class="video-js vjs-default-skin vjs-big-play-centered" controls playsinline preload="auto" style="position:absolute; top:0; left:0; width:100%; height:100%;" data-setup="{}">';
        $output .= '<source src="' . esc_url($video['video']) . '" type="video/mp4">';
        $output .= '</video>';
        
        // Autoplay overlay
        if ($next_url) {
            $output .= '<div id="adc-next-overlay">';
            $output .= '<p>Siguiente video en <span id="adc-countdown">5</span> segundos...</p>';
            $output .= '<a href="' . esc_url($next_url) . '">Ver ahora</a><br>';
            $output .= '<button id="adc-cancel-autoplay">Cancelar</button>';
            $output .= '</div>';
        }
        
        $output .= '</div>';
        
        // Next button
        if ($next_url) {
            $output .= '<div class="adc-next-button-container">';
            $output .= '<a href="' . esc_url($next_url) . '" class="adc-view-all-button">Ver siguiente video</a>';
            $output .= '</div>';
        }

        // Related videos - Smart logic implementation
        $related_videos = $this->get_smart_related_videos($materials, $video_index, 8);
        
        $output .= '<h2 class="adc-related-videos-title">Más videos de ' . esc_html($category['name']) . '</h2>';
        $output .= '<div class="adc-related-videos-grid">';
        $output .= '<div class="adc-videos-row" id="adc-related-videos-container">';
        
        foreach ($related_videos as $index => $related_video) {
            $related_slug = $this->slugify($related_video['title']);
            
            $output .= '<div class="adc-video-item adc-related-video-item">';
            $output .= '<a href="?categoria=' . esc_attr($category_slug) . '&video=' . esc_attr($related_slug) . '" class="adc-video-link">';
            $output .= '<div class="adc-video-thumbnail">';
            $output .= '<img src="' . esc_url($this->api->get_thumbnail_url($related_video['id'])) . '" alt="' . esc_attr($related_video['title']) . '">';
            $output .= '<div class="adc-video-play-icon"></div>';
            $output .= '</div>';
            
            $output .= '<div class="adc-video-info">';
            $output .= '<h3 class="adc-video-title">' . esc_html($related_video['title']) . '</h3>';
            $output .= '<span class="adc-video-duration">Duración: ' . esc_html($related_video['duration']) . '</span>';
            $output .= '</div>';
            $output .= '</a>';
            $output .= '</div>';
        }
        
        $output .= '</div></div>';
        
        // Configuration for JavaScript (SOLO lo que necesita datos PHP dinámicos)
        $autoplay = isset($this->options['enable_autoplay']) ? $this->options['enable_autoplay'] : '1';
        $countdown = isset($this->options['autoplay_countdown']) ? $this->options['autoplay_countdown'] : '5';
        
        // Add inline script SOLO para configuración específica del video que usa datos PHP
        if ($next_url && $autoplay == '1') {
            $output .= '<script>
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
                        
                        // Exit fullscreen if active
                        if (player.isFullscreen()) {
                            player.exitFullscreen();
                        }
                        
                        // Show overlay after small delay to ensure fullscreen exit
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
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Get smart related videos
     */
    private function get_smart_related_videos($materials, $current_index, $limit = 8) {
        $related = array();
        $total_videos = count($materials);
        
        // If we have fewer videos than the limit, show all except current
        if ($total_videos <= $limit + 1) {
            for ($i = 0; $i < $total_videos; $i++) {
                if ($i != $current_index) {
                    $materials[$i]['original_index'] = $i;
                    $related[] = $materials[$i];
                }
            }
            return $related;
        }
        
        // Otherwise, use the smart logic
        $added = 0;
        $position = $current_index + 1;
        
        while ($added < $limit) {
            $index = $position % $total_videos;
            
            // Skip if it's the current video
            if ($index == $current_index) {
                $position++;
                continue;
            }
            
            // Add original index for loop detection
            $materials[$index]['original_index'] = $index;
            $related[] = $materials[$index];
            $added++;
            $position++;
        }
        
        return $related;
    }
}

// Initialize plugin
function adc_video_display_init() {
    new ADC_Video_Display();
}
add_action('plugins_loaded', 'adc_video_display_init');

// Activation hook
register_activation_hook(__FILE__, 'adc_video_display_activate');
function adc_video_display_activate() {
    // Create default options
    $default_options = array(
        'api_token' => '',
        'api_url' => 'https://api.tutorah.tv/v1',
        'section' => '2',
        'videos_per_row' => '4',
        'enable_autoplay' => '1',
        'autoplay_countdown' => '5',
        'show_view_more' => '1'
    );
    
    add_option('adc-video-display', $default_options);
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'adc_video_display_deactivate');
function adc_video_display_deactivate() {
    // Clean up if needed
}