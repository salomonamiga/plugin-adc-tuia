<?php
/**
 * Plugin Name: ADC Video Display
 * Description: Muestra videos desde el sistema ADC en WordPress – Multiidioma (ES/EN) con URLs Amigables
 * Version:     3.2
 * Author:      TuTorah Development Team
 */

// Evita el canonical redirect **antes** de todo
add_filter( 'redirect_canonical', function( $redirect_url ) {
    $uri = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
    // Captura /programa/... y /en/programa/...
    if ( preg_match( '#^/(en/)?programa/#', $uri ) ) {
        return false;
    }
    return $redirect_url;
}, PHP_INT_MAX );

// Quita cualquier <link rel="prefetch"> que WP o tu tema inyecten
add_filter( 'wp_resource_hints', function( $hints, $relation_type ) {
    if ( $relation_type === 'prefetch' ) {
        return [];
    }
    return $hints;
}, 10, 2 );



// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define('ADC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ADC_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once ADC_PLUGIN_DIR . 'adc-utils.php';
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
    private $current_url_params;

    /**
     * Constructor
     */
    public function __construct($language = 'es')
    {
        $this->options = get_option('adc-video-display');
        $this->language = ADC_Utils::validate_language($language);
        $this->api = new ADC_API($this->language);
        $this->current_url_params = array();

        // Initialize URL routing first
        add_action('init', array($this, 'init_url_routing'), 5);
        
        // Register hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        // Register shortcodes for each language (only ES and EN)
        add_shortcode('adc_content', array($this, 'display_content_es'));
        add_shortcode('adc_content_en', array($this, 'display_content_en'));

        // AJAX handlers
        add_action('wp_ajax_adc_search', array($this, 'handle_ajax_search'));
        add_action('wp_ajax_nopriv_adc_search', array($this, 'handle_ajax_search'));

        add_action('wp_ajax_adc_get_programs_menu', array($this, 'handle_ajax_get_programs_menu'));
        add_action('wp_ajax_nopriv_adc_get_programs_menu', array($this, 'handle_ajax_get_programs_menu'));

        // NEW: Webhook endpoint for cache refresh
        add_action('wp_ajax_adc_webhook_refresh', array($this, 'handle_webhook_cache_refresh'));
        add_action('wp_ajax_nopriv_adc_webhook_refresh', array($this, 'handle_webhook_cache_refresh'));

        // NEW: URL routing and redirection
        add_action('template_redirect', array($this, 'handle_url_routing'), 1);
        add_action('template_redirect', array($this, 'handle_smart_404_redirects'), 1);
        add_action('wp', array($this, 'handle_smart_404_redirects_early'), 1);
        add_filter('request', array($this, 'handle_custom_urls'));
        
        // NEW: Force correct page display for friendly URLs
        add_action('pre_get_posts', array($this, 'modify_main_query'), 1);
    }

/**
 * NEW: Initialize URL routing system
 */
public function init_url_routing()
{
    // Add rewrite rules for friendly URLs
    $this->add_rewrite_rules();
    
    // Add query vars
    add_filter('query_vars', array($this, 'add_query_vars'));
    
    // Check if rewrite rules need to be flushed
    if ( get_option('adc_rewrite_rules_flushed') !== '3.2' ) {
        flush_rewrite_rules();
        update_option('adc_rewrite_rules_flushed', '3.2');
    }

}


    /**
     * NEW: Add rewrite rules for friendly URLs
     */
    private function add_rewrite_rules()
    {
        // Spanish URLs
        add_rewrite_rule(
            '^programa/([^/]+)/([^/]+)/?$',
            'index.php?adc_language=es&adc_type=video&adc_program=$matches[1]&adc_video=$matches[2]',
            'top'
        );
        
        add_rewrite_rule(
            '^programa/([^/]+)/?$',
            'index.php?adc_language=es&adc_type=program&adc_program=$matches[1]',
            'top'
        );
        
        add_rewrite_rule(
            '^buscar/([^/]+)/?$',
            'index.php?adc_language=es&adc_type=search&adc_search=$matches[1]',
            'top'
        );

        // English URLs
        add_rewrite_rule(
            '^en/program/([^/]+)/([^/]+)/?$',
            'index.php?adc_language=en&adc_type=video&adc_program=$matches[1]&adc_video=$matches[2]',
            'top'
        );
        
        add_rewrite_rule(
            '^en/program/([^/]+)/?$',
            'index.php?adc_language=en&adc_type=program&adc_program=$matches[1]',
            'top'
        );
        
        add_rewrite_rule(
            '^en/search/([^/]+)/?$',
            'index.php?adc_language=en&adc_type=search&adc_search=$matches[1]',
            'top'
        );
    }

    /**
     * NEW: Add custom query vars
     */
    public function add_query_vars($vars)
    {
        $vars[] = 'adc_language';
        $vars[] = 'adc_type';
        $vars[] = 'adc_program';
        $vars[] = 'adc_video';
        $vars[] = 'adc_search';
        
        return $vars;
    }

    /**
     * NEW: Handle URL routing and redirections
     */
    public function handle_url_routing()
    {
        // Check for old-style URLs and redirect to friendly URLs
        $this->handle_legacy_redirects();
        
        // Check for friendly URLs and set up proper page display
        $this->handle_friendly_url_routing();
    }

    /**
     * NEW: Handle legacy URL redirects (301 redirects to friendly URLs)
     */
    private function handle_legacy_redirects()
{
    // Si la REQUEST_URI coincide con /programa/... o /en/program/... o /buscar/...,
    // es un friendly URL y no debemos redirigir:
    $uri = $_SERVER['REQUEST_URI'];
    if ( preg_match('#^/(en/)?(programa|program)/#', $uri) ||
         preg_match('#^/(en/)?(buscar|search)/#',   $uri) ) {
        return;
    }

    // A partir de aquí, sólo legacy URLs query-based
    $categoria  = isset($_GET['categoria'])  ? sanitize_text_field($_GET['categoria'])  : '';
    $video      = isset($_GET['video'])      ? sanitize_text_field($_GET['video'])      : '';
    $adc_search = isset($_GET['adc_search']) ? sanitize_text_field($_GET['adc_search']) : '';

    // Si no hay parámetros legacy, no redirijas:
    if ( ! $categoria && ! $video && ! $adc_search ) {
        return;
    }

    // Detectar idioma
    $lang   = ADC_Utils::detect_language();
    $prefix = $lang === 'en' ? 'en/' : '';

    // Legacy search → friendly /buscar/ ó /en/search/
    if ( $adc_search ) {
        $keyword = $lang === 'en' ? 'search' : 'buscar';
        wp_redirect( home_url("/{$prefix}{$keyword}/" . urlencode($adc_search) . "/"), 301 );
        exit;
    }

    // Legacy program/video → friendly /programa/slug[/video]/ ó /en/program/.../
    if ( $categoria ) {
        $keyword = $lang === 'en' ? 'program' : 'programa';
        $base    = home_url("/{$prefix}{$keyword}/{$categoria}/");
        $url     = $video ? "{$base}{$video}/" : $base;
        wp_redirect( $url, 301 );
        exit;
    }
}


    /**
     * NEW: Handle friendly URL routing
     */
    private function handle_friendly_url_routing()
    {
        $adc_type = get_query_var('adc_type');
        
        if (!$adc_type) {
            return;
        }

        // Extract parameters from URL
        $this->current_url_params = array(
            'language' => get_query_var('adc_language') ?: 'es',
            'type' => $adc_type,
            'program' => get_query_var('adc_program'),
            'video' => get_query_var('adc_video'),
            'search' => get_query_var('adc_search')
        );

        // Validate the friendly URL parameters
        if (!$this->validate_friendly_url_params()) {
            // Invalid parameters, redirect to home
            $home_url = $this->current_url_params['language'] === 'en' ? home_url('/en/') : home_url('/');
            wp_redirect($home_url, 301);
            exit;
        }
    }

    /**
     * NEW: Validate friendly URL parameters against API data
     */
    private function validate_friendly_url_params()
    {
        if (empty($this->current_url_params['type'])) {
            return false;
        }

        $api = new ADC_API($this->current_url_params['language']);
        
        switch ($this->current_url_params['type']) {
            case 'program':
            case 'video':
                if (empty($this->current_url_params['program'])) {
                    return false;
                }
                
                // Check if program exists
                $programs = $api->get_programs();
                $program_found = false;
                
                foreach ($programs as $program) {
                    if (ADC_Utils::slugify($program['name']) === $this->current_url_params['program']) {
                        $program_found = $program;
                        break;
                    }
                }
                
                if (!$program_found) {
                    return false;
                }
                
                // If it's a video URL, validate the video exists
                if ($this->current_url_params['type'] === 'video' && !empty($this->current_url_params['video'])) {
                    $materials = $api->get_materials($program_found['id']);
                    $video_found = false;
                    
                    foreach ($materials as $material) {
                        if (ADC_Utils::slugify($material['title']) === $this->current_url_params['video']) {
                            $video_found = true;
                            break;
                        }
                    }
                    
                    if (!$video_found) {
                        return false;
                    }
                }
                
                return true;
                
            case 'search':
                return !empty($this->current_url_params['search']);
                
            default:
                return false;
        }
    }

    /**
     * NEW: Handle 404 errors with smart language-based redirects
     */
    public function handle_smart_404_redirects()
    {
        // Solo actuar en 404s reales
        if (!is_404()) {
            return;
        }

        // Obtener la URL actual
        $current_uri = $_SERVER['REQUEST_URI'] ?? '';
        $parsed_uri = parse_url($current_uri, PHP_URL_PATH);
        
        // Limpiar la URI (remover query strings y trailing slashes)
        $clean_uri = rtrim($parsed_uri, '/');
        
        // Detectar idioma basado en la URL
        $detected_language = 'es'; // Default
        $redirect_url = home_url('/'); // Default redirect
        
        // Debug info si está activado
        $debug_info = array(
            'original_uri' => $current_uri,
            'clean_uri' => $clean_uri,
            'is_404' => is_404(),
            'detected_language' => null,
            'redirect_url' => null,
            'reason' => null
        );

        // DETECCIÓN DE IDIOMA POR URL
        if (strpos($clean_uri, '/en') === 0) {
            // URLs que empiezan con /en
            $detected_language = 'en';
            $redirect_url = home_url('/en/');
            $debug_info['reason'] = 'URL starts with /en';
            
            // Verificar si es una URL válida del plugin que no debería redirigir
            if (preg_match('#^/en/(program|search)/#', $clean_uri)) {
                // Es una URL del plugin pero dio 404, probablemente programa/video inexistente
                // Redirigir al home inglés
                $debug_info['reason'] = 'Invalid plugin URL in English';
            } elseif ($clean_uri === '/en') {
                // /en exacto - no redirigir, dejar que WordPress maneje
                $debug_info['reason'] = 'Exact /en - let WordPress handle';
                $this->output_404_debug('ADC 404 Handler', $debug_info);
                return;
            }
        } else {
            // URLs en español (no empiezan con /en)
            $detected_language = 'es';
            $redirect_url = home_url('/');
            $debug_info['reason'] = 'Spanish URL or default';
            
            // Verificar si es una URL válida del plugin que no debería redirigir
            if (preg_match('#^/(programa|buscar)/#', $clean_uri)) {
                // Es una URL del plugin pero dio 404, probablemente programa/video inexistente
                // Redirigir al home español
                $debug_info['reason'] = 'Invalid plugin URL in Spanish';
            }
        }

        // VALIDACIONES ADICIONALES
        
        // No redirigir si es un archivo físico (imágenes, CSS, JS, etc.)
        if (preg_match('#\.(jpg|jpeg|png|gif|css|js|ico|pdf|zip|txt|xml)$#i', $clean_uri)) {
            $debug_info['reason'] = 'File extension detected - not redirecting';
            $this->output_404_debug('ADC 404 Handler', $debug_info);
            return;
        }
        
        // No redirigir si es wp-admin, wp-content, etc.
        if (preg_match('#^/(wp-admin|wp-content|wp-includes)/#', $clean_uri)) {
            $debug_info['reason'] = 'WordPress system path - not redirecting';
            $this->output_404_debug('ADC 404 Handler', $debug_info);
            return;
        }

        // No redirigir si ya estamos en la página home
        $home_path = parse_url(home_url('/'), PHP_URL_PATH);
        $en_home_path = parse_url(home_url('/en/'), PHP_URL_PATH);
        
        if ($clean_uri === rtrim($home_path, '/') || $clean_uri === rtrim($en_home_path, '/')) {
            $debug_info['reason'] = 'Already on home page - not redirecting';
            $this->output_404_debug('ADC 404 Handler', $debug_info);
            return;
        }

        // EJECUTAR REDIRECCIÓN
        $debug_info['detected_language'] = $detected_language;
        $debug_info['redirect_url'] = $redirect_url;
        
        // Debug output antes de redireccionar
        $this->output_404_debug('ADC 404 Handler', $debug_info);
        
        // Redirección con código 302 (temporal) para evitar problemas de SEO
        wp_redirect($redirect_url, 302);
        exit;
    }

    /**
     * Early 404 handler - runs before template_redirect
     */
    public function handle_smart_404_redirects_early()
    {
        if (is_404()) {
            $this->handle_smart_404_redirects();
        }
    }

    /**
     * Output debug information for 404 handling
     */
    private function output_404_debug($title, $data)
    {
        // Solo si debug mode está activado
        if (!isset($this->options['debug_mode']) || $this->options['debug_mode'] !== '1') {
            return;
        }

        // Escapar datos para JavaScript
        $json_data = wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        // Agregar script para mostrar en consola usando diferentes métodos
        add_action('wp_head', function() use ($title, $json_data) {
            echo '<script>
            (function() {
                var debugData = ' . $json_data . ';
                console.group("🚨 ' . esc_js($title) . '");
                console.log("404 Detection Info:", debugData);
                console.groupEnd();
            })();
            </script>';
        }, 1);
        
        // También intentar output directo para casos donde wp_head no se ejecuta
        echo '<script>
        console.group("🚨 ' . esc_js($title) . ' (direct)");
        console.log(' . $json_data . ');
        console.groupEnd();
        </script>';
    }

    /**
     * NEW: Modify main query to show correct page for friendly URLs
     */
    public function modify_main_query($query)
    {
        if (!$query->is_main_query() || is_admin()) {
            return;
        }

        $adc_type = get_query_var('adc_type');
        
        if (!$adc_type) {
            return;
        }

        // Determine which page to show based on language - CORREGIDO
        $target_page_slug = $this->current_url_params['language'] === 'en' ? 'home-en' : 'home';
        
        // Find the page with the appropriate shortcode
        $target_page = get_page_by_path($target_page_slug);
        
        if (!$target_page) {
            // Fallback: find page by title
            $page_title = $this->current_url_params['language'] === 'en' ? 'Home Inglés' : 'Home';
            $pages = get_pages(array(
                'title' => $page_title,
                'post_status' => 'publish'
            ));
            
            if (!empty($pages)) {
                $target_page = $pages[0];
            }
        }
        
        if ($target_page) {
            // Force WordPress to show the target page
            $query->set('page_id', $target_page->ID);
            $query->set('post_type', 'page');
            $query->is_page = true;
            $query->is_singular = true;
            $query->is_home = false;
            $query->is_front_page = true;
        }
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
            '3.2'
        );

        // Enqueue JavaScript
        wp_enqueue_script(
            'adc-script',
            ADC_PLUGIN_URL . 'script.js',
            array('jquery'),
            '3.2',
            true
        );

        // Localize script with options
        wp_localize_script('adc-script', 'adc_config', array(
            'autoplay' => isset($this->options['enable_autoplay']) ? $this->options['enable_autoplay'] : '1',
            'countdown' => isset($this->options['autoplay_countdown']) ? $this->options['autoplay_countdown'] : '5',
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('adc_nonce'),
            'search_page' => home_url('/'),
            'debug' => isset($this->options['debug_mode']) && $this->options['debug_mode'] === '1',
            'cache_enabled' => $this->is_cache_enabled(),
            'friendly_urls' => true
        ));
    }

    /**
     * NEW: Handle webhook cache refresh from ADC
     */
    public function handle_webhook_cache_refresh()
    {
        // Verify token
        $provided_token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
        $stored_token = isset($this->options['webhook_token']) ? $this->options['webhook_token'] : '';

        if (empty($provided_token) || empty($stored_token) || !hash_equals($stored_token, $provided_token)) {
            wp_send_json_error(array(
                'message' => 'Invalid or missing token',
                'code' => 'INVALID_TOKEN'
            ), 401);
            return;
        }

        // Log webhook call if debug mode is enabled
        if (isset($this->options['debug_mode']) && $this->options['debug_mode'] === '1') {
            error_log('ADC Webhook: Cache refresh triggered by ADC at ' . current_time('mysql'));
        }

        try {
            $cleared_languages = array();
            $total_cleared = 0;

            // Clear cache for all languages
            foreach (ADC_Utils::get_valid_languages() as $language) {
                $api = new ADC_API($language);
                $result = $api->clear_all_cache();

                if ($result) {
                    $cleared_languages[] = $language;
                    $total_cleared++;
                }
            }

            // Clear WordPress object cache if available
            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }

            // Success response
            wp_send_json_success(array(
                'message' => 'Cache cleared successfully',
                'languages_cleared' => $cleared_languages,
                'total_languages' => $total_cleared,
                'timestamp' => current_time('mysql'),
                'version' => '3.2'
            ));
        } catch (Exception $e) {
            // Error response
            wp_send_json_error(array(
                'message' => 'Error clearing cache',
                'error' => $e->getMessage(),
                'code' => 'CACHE_CLEAR_ERROR'
            ), 500);
        }
    }

    /**
     * Handle AJAX search
     */
    public function handle_ajax_search()
    {
        check_ajax_referer('adc_nonce', 'nonce');

        $search_term = sanitize_text_field($_POST['search']);
        $language = isset($_POST['language']) ? ADC_Utils::validate_language($_POST['language']) : 'es';

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

        $language = isset($_POST['language']) ? ADC_Utils::validate_language($_POST['language']) : 'es';

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
     * Handle custom URLs (legacy support)
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

    /**
     * Main content display handler - UPDATED for friendly URLs
     */
    public function display_content($atts)
    {
        // DEBUG TEMPORAL - Ver en consola del navegador
        echo '<script>
        console.log("=== ADC DEBUG START ===");
        console.log("API configurada:", ' . ($this->api->is_configured() ? 'true' : 'false') . ');
        console.log("adc_type:", "' . get_query_var('adc_type') . '");
        console.log("adc_language:", "' . get_query_var('adc_language') . '");
        console.log("adc_program:", "' . get_query_var('adc_program') . '");
        console.log("adc_video:", "' . get_query_var('adc_video') . '");
        console.log("current_url_params:", ' . json_encode($this->current_url_params) . ');
        console.log("current language:", "' . $this->language . '");
        console.log("Current URL:", window.location.href);
        console.log("=== ADC DEBUG END ===");
        </script>';

        // Check if API is configured
        if (!$this->api->is_configured()) {
            return '<div class="adc-error">El plugin ADC Video Display no está configurado. Por favor configura la API en el panel de administración.</div>';
        }

        // NEW: Check for friendly URL parameters first
        $adc_type = get_query_var('adc_type');
        
        if ($adc_type) {
            return $this->handle_friendly_url_content();
        }

        // Legacy support: Check for search results
        if (isset($_GET['adc_search'])) {
            return $this->display_search_results();
        }

        // Legacy support: Determine what to display based on URL parameters
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
     * NEW: Handle content display for friendly URLs
     */
    private function handle_friendly_url_content()
    {
        if (empty($this->current_url_params)) {
            // Extract parameters if not already set
            $this->current_url_params = array(
                'language' => get_query_var('adc_language') ?: 'es',
                'type' => get_query_var('adc_type'),
                'program' => get_query_var('adc_program'),
                'video' => get_query_var('adc_video'),
                'search' => get_query_var('adc_search')
            );
        }

        // Update API language if needed
        if ($this->language !== $this->current_url_params['language']) {
            $this->language = $this->current_url_params['language'];
            $this->api = new ADC_API($this->language);
        }

        switch ($this->current_url_params['type']) {
            case 'search':
                return $this->display_search_results($this->current_url_params['search']);
                
            case 'video':
                return $this->display_video(
                    $this->current_url_params['program'], 
                    $this->current_url_params['video']
                );
                
            case 'program':
                return $this->display_category_videos($this->current_url_params['program']);
                
            default:
                return $this->display_categories_grid();
        }
    }

    /**
     * Display search results - UPDATED for friendly URLs
     */
    private function display_search_results($search_term = null)
    {
        if ($search_term === null) {
            $search_term = ADC_Utils::sanitize_search_term($_GET['adc_search'] ?? '');
        } else {
            $search_term = ADC_Utils::sanitize_search_term($search_term);
        }

        if (empty($search_term)) {
            return '<div class="adc-error">Por favor ingresa un término de búsqueda.</div>';
        }

        // Try to get actual search results first
        $results = $this->api->search_materials($search_term);

        $output = '<div class="adc-search-results-container">';

        // If no results, show message + recommendations
        if (empty($results)) {
            $output .= $this->render_no_results_message($search_term, $this->language);
        } else {
            // Show actual results found
            $output .= '<h1 class="adc-search-results-title">' . ADC_Utils::get_text('search_results_for', $this->language) . ': "' . esc_html($search_term) . '"</h1>';
            $output .= '<div class="adc-recommended-videos">';

            foreach ($results as $video) {
                $category_slug = ADC_Utils::slugify($video['category']);
                $video_slug = ADC_Utils::slugify($video['title']);
                
                // NEW: Use friendly URLs
                $url = $this->build_friendly_video_url($category_slug, $video_slug);
                $output .= $this->render_video_card($video, $url);
            }

            $output .= '</div>';
        }

        $output .= '</div>';
        return $output;
    }

    /**
     * NEW: Render no results message with recommended videos
     */
    private function render_no_results_message($search_term, $language)
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

        // Main "no results" message
        $output = '<div class="adc-no-results-section">';
        $output .= '<h2 class="adc-no-results-title">' . $texts['title'] . ' "' . esc_html($search_term) . '"</h2>';
        $output .= '</div>';

        // Recommended videos
        $recommended_videos = $this->get_recommended_videos();
        if (!empty($recommended_videos)) {
            $output .= '<h2 class="adc-recommended-title">' . $texts['recommended_title'] . '</h2>';
            $output .= $recommended_videos;
        }

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
            $program_slug = ADC_Utils::slugify($video['category']);
            $video_slug = ADC_Utils::slugify($video['title']);
            
            // NEW: Use friendly URLs
            $url = $this->build_friendly_video_url($program_slug, $video_slug);
            $output .= $this->render_video_card($video, $url);
        }

        $output .= '</div>';
        return $output;
    }

    /**
     * NEW: Build friendly URL for videos
     */
    private function build_friendly_video_url($program_slug, $video_slug)
    {
        $base_url = home_url('/');
        
        if ($this->language === 'en') {
            return $base_url . 'en/program/' . $program_slug . '/' . $video_slug . '/';
        } else {
            return $base_url . 'programa/' . $program_slug . '/' . $video_slug . '/';
        }
    }

    /**
     * NEW: Build friendly URL for programs
     */
    private function build_friendly_program_url($program_slug)
    {
        $base_url = home_url('/');
        
        if ($this->language === 'en') {
            return $base_url . 'en/program/' . $program_slug . '/';
        } else {
            return $base_url . 'programa/' . $program_slug . '/';
        }
    }

    /**
     * Render a single video card - UPDATED for friendly URLs
     */
    private function render_video_card($video, $url)
    {
        $output = '<div class="adc-search-video-item">';
        $output .= '<a href="' . esc_url($url) . '" class="adc-search-video-link">';
        $output .= '<div class="adc-search-thumbnail">';

        // Use thumbnail from API
        $thumbnail_url = ADC_Utils::get_thumbnail_url($video['thumbnail']);
        $output .= '<img src="' . esc_url($thumbnail_url) . '" alt="' . esc_attr($video['title']) . '" loading="lazy">';
        $output .= '<div class="adc-search-play-icon"></div>';
        $output .= '</div>';

        $output .= '<div class="adc-search-info">';
        $output .= '<h3 class="adc-search-title">' . esc_html($video['title']) . '</h3>';
        $output .= '<div class="adc-search-program">' . ADC_Utils::get_text('program', $this->language) . ': ' . esc_html($video['category']) . '</div>';
        $output .= '<div class="adc-search-duration">' . ADC_Utils::get_text('duration', $this->language) . ': ' . esc_html($video['duration']) . '</div>';
        $output .= '</div>';
        $output .= '</a>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Display categories grid - UPDATED for friendly URLs
     */
    private function display_categories_grid()
    {
        // Get programs with custom order
        $programs = $this->api->get_programs_with_custom_order();

        if (empty($programs)) {
            return '<div class="adc-error">' . ADC_Utils::get_text('no_programs', $this->language) . '</div>';
        }

        // Pre-load video information in one operation
        $programs_with_videos = $this->api->bulk_check_programs_with_videos($programs);

        $output = '<div class="adc-categories-grid">';
        $output .= '<div class="adc-categories-row">';

        foreach ($programs as $program) {
            $output .= $this->render_category_card($program, $programs_with_videos);
        }

        $output .= '</div></div>';

        return $output;
    }

    /**
     * Render a single category card - UPDATED for friendly URLs
     */
    private function render_category_card($program, $programs_with_videos = null)
    {
        $slug = ADC_Utils::slugify($program['name']);

        // Use bulk check if available
        if ($programs_with_videos !== null && isset($programs_with_videos[$program['id']])) {
            $has_videos = $programs_with_videos[$program['id']];
        } else {
            $has_videos = $this->api->program_has_videos($program['id']);
        }

        $is_coming_soon = !$has_videos && isset($program['cover']) && !empty($program['cover']);

        $output = '<div class="adc-category-card-wrapper">';

        if ($is_coming_soon) {
            // Coming soon - no link, special styling
            $output .= '<div class="adc-category-card adc-coming-soon-card">';
        } else {
            // NEW: Use friendly URLs for category links
            $friendly_url = $this->build_friendly_program_url($slug);
            $output .= '<a class="adc-category-card" href="' . esc_url($friendly_url) . '">';
        }

        $output .= '<div class="adc-category-image-circle">';

        if (isset($program['cover'])) {
            $output .= '<img src="' . esc_url($program['cover']) . '" alt="' . esc_attr($program['name']) . '" loading="lazy">';
        } else {
            $output .= '<img src="' . ADC_PLUGIN_URL . 'assets/img/no-cover.jpg" alt="' . esc_attr($program['name']) . '" loading="lazy">';
        }

        // Add coming soon overlay
        if ($is_coming_soon) {
            $output .= '<div class="adc-coming-soon-overlay">';
            $output .= '<span class="adc-coming-soon-text">' . esc_html(ADC_Utils::get_text('coming_soon', $this->language)) . '</span>';
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
     * Display videos from a category - UPDATED for friendly URLs
     */
    private function display_category_videos($category_slug)
    {
        // Find category by slug
        $programs = $this->api->get_programs();
        $category = null;

        foreach ($programs as $program) {
            if (ADC_Utils::slugify($program['name']) == $category_slug) {
                $category = $program;
                break;
            }
        }

        if (!$category) {
            return '<div class="adc-error">' . ADC_Utils::get_text('category_not_found', $this->language) . '</div>';
        }

        // Get materials
        $materials = $this->api->get_materials($category['id']);

        if (empty($materials)) {
            return '<div class="adc-error">' . ADC_Utils::get_text('no_videos', $this->language) . '</div>';
        }

        // Group by season
        $seasons = $this->api->group_materials_by_season($materials);

        $home_url = ADC_Utils::get_base_url($this->language);

        $output = '<div class="adc-category-header">';
        $output .= '<h1 class="adc-category-title">' . esc_html($category['name']) . '</h1>';
        $output .= '<a href="' . esc_url($home_url) . '" class="adc-back-button">' . ADC_Utils::get_text('back_to_programs', $this->language) . '</a>';
        $output .= '</div>';

        // Show promotional clip if exists
        if (isset($category['clip']) && !empty($category['clip'])) {
            $output .= $this->render_promotional_clip($category);
        }

        // Videos per row setting
        $videos_per_row = isset($this->options['videos_per_row']) ? $this->options['videos_per_row'] : '4';

        foreach ($seasons as $season_num => $season_videos) {
            $season_name = $this->api->get_season_name($season_num);
            $output .= '<h2 class="adc-season-header"><span>' . esc_html($season_name) . '</span></h2>';

            $output .= '<div class="adc-videos-grid">';
            $output .= '<div class="adc-videos-row cols-' . $videos_per_row . '">';

            foreach ($season_videos as $video) {
                $video_slug = ADC_Utils::slugify($video['title']);

                // Use thumbnail from API
                $thumbnail_url = ADC_Utils::get_thumbnail_url($video['thumbnail']);

                // NEW: Use friendly URLs for video links
                $video_url = $this->build_friendly_video_url($category_slug, $video_slug);

                $output .= '<div class="adc-video-item">';
                $output .= '<a href="' . esc_url($video_url) . '" class="adc-video-link">';
                $output .= '<div class="adc-video-thumbnail">';
                $output .= '<img src="' . esc_url($thumbnail_url) . '" alt="' . esc_attr($video['title']) . '" loading="lazy">';
                $output .= '<div class="adc-video-play-icon"></div>';
                $output .= '</div>';

                $output .= '<div class="adc-video-info">';
                $output .= '<h3 class="adc-video-title">' . esc_html($video['title']) . '</h3>';
                $output .= '<span class="adc-video-duration">' . ADC_Utils::get_text('duration', $this->language) . ': ' . esc_html($video['duration']) . '</span>';
                $output .= '</div>';
                $output .= '</a>';
                $output .= '</div>';
            }

            $output .= '</div></div>';
        }

        return $output;
    }

    /**
     * Render promotional clip for category
     */
    private function render_promotional_clip($category)
    {
        $output = '<div class="adc-promotional-clip-section">';

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
     * Display single video - UPDATED for friendly URLs
     */
    private function display_video($category_slug, $video_slug)
    {
        // Find category
        $programs = $this->api->get_programs();
        $category = null;

        foreach ($programs as $program) {
            if (ADC_Utils::slugify($program['name']) == $category_slug) {
                $category = $program;
                break;
            }
        }

        if (!$category) {
            return '<div class="adc-error">' . ADC_Utils::get_text('category_not_found', $this->language) . '</div>';
        }

        // Find video
        $materials = $this->api->get_materials($category['id']);
        $video = null;
        $video_index = -1;

        for ($i = 0; $i < count($materials); $i++) {
            if (ADC_Utils::slugify($materials[$i]['title']) == $video_slug) {
                $video = $materials[$i];
                $video_index = $i;
                break;
            }
        }

        if (!$video) {
            return '<div class="adc-error">' . ADC_Utils::get_text('video_not_found', $this->language) . '</div>';
        }

        // Find next video
        $next_video = null;
        $next_url = '';
        if ($video_index < count($materials) - 1) {
            $next_video = $materials[$video_index + 1];
            $next_slug = ADC_Utils::slugify($next_video['title']);
            
            // NEW: Use friendly URL for next video
            $next_url = $this->build_friendly_video_url($category_slug, $next_slug);
        }

        $output = '<div class="adc-video-container">';

        // Video title and back button container
        $output .= '<div class="adc-video-header">';
        $output .= '<h1 class="adc-video-main-title">' . esc_html($video['title']) . '</h1>';
        
        // NEW: Use friendly URL for back button
        $back_url = $this->build_friendly_program_url($category_slug);
        $output .= '<a href="' . esc_url($back_url) . '" class="adc-back-program-button">' .
            ADC_Utils::get_text('back_to', $this->language) . ' ' . esc_html($category['name']) . '</a>';
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
            $output .= '<div id="adc-next-overlay">';
            $output .= '<p>' . ADC_Utils::get_text('next_video_in', $this->language) . ' <span id="adc-countdown">5</span> ' . ADC_Utils::get_text('seconds', $this->language) . '...</p>';
            $output .= '<a href="' . esc_url($next_url) . '">' . ADC_Utils::get_text('watch_now', $this->language) . '</a><br>';
            $output .= '<button id="adc-cancel-autoplay">' . ADC_Utils::get_text('cancel', $this->language) . '</button>';
            $output .= '</div>';
        }

        $output .= '</div>';

        // Next button
        if ($next_url) {
            $output .= '<div class="adc-next-button-container">';
            $output .= '<a href="' . esc_url($next_url) . '" class="adc-view-all-button">' . ADC_Utils::get_text('watch_next_video', $this->language) . '</a>';
            $output .= '</div>';
        }

        // Related videos
        $related_videos = $this->get_smart_related_videos($materials, $video_index, 8);

        $output .= '<h2 class="adc-related-videos-title">' . ADC_Utils::get_text('more_videos_from', $this->language) . ' ' . esc_html($category['name']) . '</h2>';
        $output .= '<div class="adc-related-videos-grid">';
        $output .= '<div class="adc-videos-row" id="adc-related-videos-container">';

        foreach ($related_videos as $index => $related_video) {
            $related_slug = ADC_Utils::slugify($related_video['title']);

            // NEW: Use friendly URLs for related videos
            $related_url = $this->build_friendly_video_url($category_slug, $related_slug);

            $output .= '<div class="adc-video-item adc-related-video-item">';
            $output .= '<a href="' . esc_url($related_url) . '" class="adc-video-link">';
            $output .= '<div class="adc-video-thumbnail">';

            // Use thumbnail from API for related videos
            $thumbnail_url = ADC_Utils::get_thumbnail_url($related_video['thumbnail']);
            $output .= '<img src="' . esc_url($thumbnail_url) . '" alt="' . esc_attr($related_video['title']) . '" loading="lazy">';
            $output .= '<div class="adc-video-play-icon"></div>';
            $output .= '</div>';

            $output .= '<div class="adc-video-info">';
            $output .= '<h3 class="adc-video-title">' . esc_html($related_video['title']) . '</h3>';
            $output .= '<span class="adc-video-duration">' . ADC_Utils::get_text('duration', $this->language) . ': ' . esc_html($related_video['duration']) . '</span>';
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
     * Check if cache is enabled in settings
     */
    private function is_cache_enabled()
    {
        return isset($this->options['enable_cache']) && $this->options['enable_cache'] === '1';
    }

    /**
     * Get cache duration in hours from settings
     */
    private function get_cache_duration_hours()
    {
        $duration = isset($this->options['cache_duration']) ? floatval($this->options['cache_duration']) : 6;
        return max(0.5, min(24, $duration)); // Clamp between 30 minutes and 24 hours
    }

    /**
     * Get cache duration in seconds for WordPress transients
     */
    public function get_cache_duration_seconds()
    {
        return $this->get_cache_duration_hours() * HOUR_IN_SECONDS;
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
    // Create default options with cache settings
    $default_options = array(
        'api_token' => '',
        'api_url' => 'https://api.tutorah.tv/v1',
        'videos_per_row' => '4',
        'enable_autoplay' => '1',
        'autoplay_countdown' => '5',
        'enable_search' => '1',
        'related_videos_count' => '8',
        'debug_mode' => '0',
        'enable_cache' => '1',
        'cache_duration' => '6',
        'webhook_token' => 'adc_' . wp_generate_password(32, false, false)
    );

    add_option('adc-video-display', $default_options);

    // Initialize program order options for each language (only ES and EN now)
    add_option('adc_programs_order_es', array());
    add_option('adc_programs_order_en', array());
    
    // Force rewrite rules flush on activation
    update_option('adc_rewrite_rules_flushed', '0');
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'adc_video_display_deactivate');
function adc_video_display_deactivate()
{
    // Clean up rewrite rules
    flush_rewrite_rules();
}

/**
 * URL Amigable para limpiar caché: https://tuia.tv/cache/clear
 */

// Agregar rewrite rule para URL amigable
add_action('init', 'adc_add_cache_clear_endpoint');
function adc_add_cache_clear_endpoint() {
    add_rewrite_rule('^cache/clear/?$', 'index.php?adc_cache_clear=1', 'top');
}

// Registrar query var
add_filter('query_vars', 'adc_add_cache_clear_query_var');
function adc_add_cache_clear_query_var($vars) {
    $vars[] = 'adc_cache_clear';
    return $vars;
}

// Manejar la request de cache clear
add_action('template_redirect', 'adc_handle_cache_clear_request');
function adc_handle_cache_clear_request() {
    if (get_query_var('adc_cache_clear')) {
        adc_display_cache_clear_page();
        exit;
    }
}

// Mostrar página de limpieza de caché
function adc_display_cache_clear_page() {
    $success = false;
    $error_message = '';
    
    try {
        // Limpiar caché usando las funciones existentes del plugin
        $languages = ADC_Utils::get_valid_languages();
        
        foreach ($languages as $lang) {
            $api = new ADC_API($lang);
            $api->clear_all_cache();
        }
        
        // Clear WordPress object cache
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        $success = true;
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
    
    // Detectar idioma actual
    $current_language = ADC_Utils::detect_language();
    $is_english = ($current_language === 'en');
    
    ?>
    <!DOCTYPE html>
    <html lang="<?php echo $current_language; ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $is_english ? 'Cache Cleared' : 'Caché Limpiado'; ?> - TuIA</title>
        <link rel="stylesheet" href="<?php echo ADC_PLUGIN_URL; ?>cache-clear-styles.css">
    </head>
    <body>
        <div class="container">
            <?php if ($success): ?>
                <div class="success-icon">✅</div>
                <h1 class="title success-title">
                    <?php echo $is_english ? 'Cache Cleared Successfully!' : '¡Caché Limpiado Exitosamente!'; ?>
                </h1>
                <p class="message">
                    <?php echo $is_english 
                        ? 'The website cache has been cleared successfully. All content will now display the latest updates immediately.' 
                        : 'El caché del sitio web ha sido limpiado exitosamente. Todo el contenido ahora mostrará las actualizaciones más recientes inmediatamente.'; ?>
                </p>
                
                <div class="countdown">
                    <?php echo $is_english ? 'Redirecting to home in' : 'Redirigiendo al inicio en'; ?>
                    <span class="countdown-number" id="countdown">5</span>
                    <?php echo $is_english ? 'seconds' : 'segundos'; ?>
                </div>
                
                <a href="<?php echo home_url('/'); ?>" class="home-button" id="homeButton">
                    <?php echo $is_english ? 'Go to Home Now' : 'Ir al Inicio Ahora'; ?>
                </a>
                
                <script>
                    let timeLeft = 5;
                    const countdownElement = document.getElementById('countdown');
                    const homeButton = document.getElementById('homeButton');
                    
                    const timer = setInterval(() => {
                        timeLeft--;
                        countdownElement.textContent = timeLeft;
                        
                        if (timeLeft <= 0) {
                            clearInterval(timer);
                            window.location.href = '<?php echo home_url('/'); ?>';
                        }
                    }, 500);
                    
                    // Allow immediate redirect on button click
                    homeButton.addEventListener('click', () => {
                        clearInterval(timer);
                    });
                </script>
                
            <?php else: ?>
                <div class="error-icon">❌</div>
                <h1 class="title error-title">
                    <?php echo $is_english ? 'Cache Clear Failed' : 'Error al Limpiar Caché'; ?>
                </h1>
                <p class="message">
                    <?php echo $is_english 
                        ? 'There was an error clearing the website cache. Please try again or contact support.' 
                        : 'Hubo un error al limpiar el caché del sitio web. Por favor intenta nuevamente o contacta soporte.'; ?>
                </p>
                
                <?php if ($error_message): ?>
                    <div class="error-details">
                        <strong><?php echo $is_english ? 'Error Details:' : 'Detalles del Error:'; ?></strong><br>
                        <?php echo esc_html($error_message); ?>
                    </div>
                <?php endif; ?>
                
                <a href="<?php echo home_url('/'); ?>" class="home-button">
                    <?php echo $is_english ? 'Go to Home' : 'Ir al Inicio'; ?>
                </a>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
}

// Flush rewrite rules on plugin activation (agregar al activation hook existente)
register_activation_hook(__FILE__, 'adc_flush_rewrite_rules_on_activation');
function adc_flush_rewrite_rules_on_activation() {
    adc_add_cache_clear_endpoint();
    flush_rewrite_rules();
}