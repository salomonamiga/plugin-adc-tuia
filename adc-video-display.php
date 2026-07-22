<?php

/**
 * Plugin Name: ADC Video Display Radiant
 * Description: Muestra videos desde el sistema ADC en WordPress con Radiant Media Player – Multiidioma (ES/EN/PT) con URLs Amigables
 * Version:     5.1.5
 * Author:      TuTorah Development Team
 */

// Evita el canonical redirect **antes** de todo
add_filter('redirect_canonical', function ($redirect_url) {
    $request_uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';
    $uri = parse_url($request_uri, PHP_URL_PATH);
    // Captura /programa/... y /en/program/... y /pt/programa/... Y /buscar/... y /en/search/... y /pt/buscar/... Y /audiolibros/... Y /rabino-anidjar/ Y /festividad/...
    if (preg_match('#^/(en/|pt/)?(programa|program|buscar|search|audiolibros|rabino-anidjar|festividad)/#', $uri) ||
        preg_match('#^/(audiolibros|rabino-anidjar)/?$#', $uri)) {
        return false;
    }
    return $redirect_url;
}, PHP_INT_MAX);

// Quita cualquier <link rel="prefetch"> que WP o tu tema inyecten
add_filter('wp_resource_hints', function ($hints, $relation_type) {
    if ($relation_type === 'prefetch') {
        return [];
    }
    return $hints;
}, 10, 2);



// Prevent direct access
if (! defined('ABSPATH')) {
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
require_once ADC_PLUGIN_DIR . 'adc-audiobooks.php';

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

        // Register shortcodes for each language (ES / EN / PT)
        add_shortcode('adc_content', array($this, 'display_content_es'));
        add_shortcode('adc_content_en', array($this, 'display_content_en'));
        add_shortcode('adc_content_pt', array($this, 'display_content_pt'));

        // AJAX handlers
        add_action('wp_ajax_adc_search', array($this, 'handle_ajax_search'));
        add_action('wp_ajax_nopriv_adc_search', array($this, 'handle_ajax_search'));

        add_action('wp_ajax_adc_get_programs_menu', array($this, 'handle_ajax_get_programs_menu'));
        add_action('wp_ajax_nopriv_adc_get_programs_menu', array($this, 'handle_ajax_get_programs_menu'));

        // Webhook endpoint for cache refresh
        add_action('wp_ajax_adc_webhook_refresh', array($this, 'handle_webhook_cache_refresh'));
        add_action('wp_ajax_nopriv_adc_webhook_refresh', array($this, 'handle_webhook_cache_refresh'));

        // URL routing and redirection
        add_action('template_redirect', array($this, 'handle_url_routing'), 1);
        add_action('template_redirect', array($this, 'handle_smart_404_redirects'), 1);
        add_action('wp', array($this, 'handle_smart_404_redirects_early'), 1);
        add_filter('request', array($this, 'handle_custom_urls'));

        // Force correct page display for friendly URLs
        add_action('parse_query', array($this, 'modify_main_query'), 1);
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
        if (get_option('adc_rewrite_rules_flushed') !== '5.1') {
            flush_rewrite_rules();
            update_option('adc_rewrite_rules_flushed', '5.1');
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

        // Portuguese URLs
        add_rewrite_rule(
            '^pt/programa/([^/]+)/([^/]+)/?$',
            'index.php?adc_language=pt&adc_type=video&adc_program=$matches[1]&adc_video=$matches[2]',
            'top'
        );

        add_rewrite_rule(
            '^pt/programa/([^/]+)/?$',
            'index.php?adc_language=pt&adc_type=program&adc_program=$matches[1]',
            'top'
        );

        add_rewrite_rule(
            '^pt/buscar/([^/]+)/?$',
            'index.php?adc_language=pt&adc_type=search&adc_search=$matches[1]',
            'top'
        );

        // Audiobooks URLs (Spanish only)
        add_rewrite_rule(
            '^audiolibros/?$',
            'index.php?adc_language=es&adc_type=audiobooks',
            'top'
        );

        add_rewrite_rule(
            '^audiolibros/([^/]+)/?$',
            'index.php?adc_language=es&adc_type=audiobook&adc_audiobook=$matches[1]',
            'top'
        );

        // Rabino Anidjar AI Chat (KAL-AI)
        add_rewrite_rule(
            '^rabino-anidjar/?$',
            'index.php?adc_language=es&adc_type=kalai',
            'top'
        );

        // Festividad URLs (Spanish only)
        add_rewrite_rule(
            '^festividad/([^/]+)/?$',
            'index.php?adc_language=es&adc_type=festival&adc_festival=$matches[1]',
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
        $vars[] = 'adc_audiobook';
        $vars[] = 'adc_festival';

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
     * MOMENTO B — Redirección 301 automática para material RENOMBRADO.
     *
     * Cuando una URL de video no resuelve (el slug ya no coincide con ningún
     * título actual, típicamente por un renombre), se busca ese slug en la tabla
     * de aliases (wp_adc_slug_aliases). Si existe, se obtiene el material por su
     * ID y se reconstruye su slug ACTUAL vía get_materials() (misma fuente que la
     * validación, refleja el nombre nuevo). Si difiere, se hace 301 a la URL nueva.
     *
     * La tabla se llena sola en display_video() (MOMENTO A). Devuelve true si
     * redirigió (y hace exit); false si no había alias o el slug ya era el actual.
     */
    private function try_alias_redirect($lang, $video_slug)
    {
        if (empty($video_slug)) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'adc_slug_aliases';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT material_id, category_slug FROM {$table} WHERE lang = %s AND video_slug = %s",
            $lang,
            $video_slug
        ));
        if (!$row) {
            return false;
        }

        // Reconstruir el slug ACTUAL del material desde la API (fuente consistente
        // con validate_friendly_url_params: get_programs + get_materials).
        $api = new ADC_API($lang);
        $programs = $api->get_programs();
        $program_id = null;
        foreach ($programs as $p) {
            if (isset($p['name']) && ADC_Utils::slugify($p['name']) === $row->category_slug) {
                $program_id = $p['id'];
                break;
            }
        }
        if (!$program_id) {
            return false;
        }

        $materials = $api->get_materials($program_id);
        foreach ($materials as $m) {
            if (isset($m['id']) && (string) $m['id'] === (string) $row->material_id) {
                $new_cat_slug   = ADC_Utils::slugify($m['category']);
                $new_video_slug = ADC_Utils::slugify($m['title']);

                // Solo redirigir si el slug realmente cambió (evita loops).
                if ($new_video_slug !== $video_slug || $new_cat_slug !== $row->category_slug) {
                    wp_redirect($this->alias_build_video_url($lang, $new_cat_slug, $new_video_slug), 301);
                    exit;
                }
                return false;
            }
        }

        return false;
    }

    /**
     * Construye una URL friendly de video para un idioma explícito (sin depender
     * de $this->language). Usado por el fallback de aliases.
     */
    private function alias_build_video_url($lang, $program_slug, $video_slug)
    {
        $base = home_url('/');
        if ($lang === 'en') {
            return $base . 'en/program/' . $program_slug . '/' . $video_slug . '/';
        } elseif ($lang === 'pt') {
            return $base . 'pt/programa/' . $program_slug . '/' . $video_slug . '/';
        }
        return $base . 'programa/' . $program_slug . '/' . $video_slug . '/';
    }

    /**
     * MOMENTO A — Registra/actualiza el slug de un video que SÍ existe, para poder
     * redirigir si algún día se renombra. Upsert por (lang, video_slug). Un guard
     * por transient evita escribir en cada pageview (solo ~1 vez al día por slug).
     */
    private function register_slug_alias($lang, $video_slug, $material_id, $category_slug)
    {
        if (empty($video_slug) || empty($material_id) || empty($category_slug)) {
            return;
        }

        $guard_key = 'adc_alias_' . $lang . '_' . md5($video_slug . '|' . $material_id);
        if (get_transient($guard_key)) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'adc_slug_aliases';
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (lang, video_slug, material_id, category_slug, updated_at)
             VALUES (%s, %s, %d, %s, NOW())
             ON DUPLICATE KEY UPDATE material_id = VALUES(material_id), category_slug = VALUES(category_slug), updated_at = NOW()",
            $lang,
            $video_slug,
            $material_id,
            $category_slug
        ));

        set_transient($guard_key, 1, DAY_IN_SECONDS);
    }

    /**
     * NEW: Handle legacy URL redirects (301 redirects to friendly URLs)
     */
    private function handle_legacy_redirects()
    {
        // Si la REQUEST_URI coincide con /programa/... o /en/program/... o /pt/programa/... o /buscar/... o /audiolibros/...,
        // es un friendly URL y no debemos redirigir:
        $uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        if (
            preg_match('#^/(en/|pt/)?(programa|program)/#', $uri) ||
            preg_match('#^/(en/|pt/)?(buscar|search)/#',   $uri) ||
            preg_match('#^/audiolibros(/|$)#',         $uri) ||
            preg_match('#^/rabino-anidjar(/|$)#',      $uri) ||
            preg_match('#^/festividad(/|$)#',           $uri)
        ) {
            return;
        }

        // A partir de aquí, sólo legacy URLs query-based
        $categoria  = isset($_GET['categoria'])  ? sanitize_text_field($_GET['categoria'])  : '';
        $video      = isset($_GET['video'])      ? sanitize_text_field($_GET['video'])      : '';
        $adc_search = isset($_GET['adc_search']) ? sanitize_text_field($_GET['adc_search']) : '';

        // Si no hay parámetros legacy, no redirijas:
        if (! $categoria && ! $video && ! $adc_search) {
            return;
        }

        // Detectar idioma
        $lang   = ADC_Utils::detect_language();
        if ($lang === 'en') {
            $prefix = 'en/';
            $kw_search = 'search';
            $kw_program = 'program';
        } elseif ($lang === 'pt') {
            $prefix = 'pt/';
            $kw_search = 'buscar';
            $kw_program = 'programa';
        } else {
            $prefix = '';
            $kw_search = 'buscar';
            $kw_program = 'programa';
        }

        // Legacy search → friendly URL
        if ($adc_search) {
            wp_redirect(home_url("/{$prefix}{$kw_search}/" . urlencode($adc_search) . "/"), 301);
            exit;
        }

        // Legacy program/video → friendly URL
        if ($categoria) {
            $base    = home_url("/{$prefix}{$kw_program}/{$categoria}/");
            $url     = $video ? "{$base}{$video}/" : $base;
            wp_redirect($url, 301);
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
            'search' => get_query_var('adc_search'),
            'audiobook' => get_query_var('adc_audiobook'),
            'festival' => get_query_var('adc_festival')
        );

        // Festival validation: if festival not active, redirect to home
        if ($adc_type === 'festival') {
            $slug = $this->current_url_params['festival'];
            $api = new ADC_API($this->current_url_params['language']);
            if (empty($slug) || !$api->is_festival_active($slug)) {
                wp_redirect(home_url('/'), 302);
                exit;
            }
            return;
        }

        // Validate the friendly URL parameters
        if (!$this->validate_friendly_url_params()) {
            // MOMENTO B: si es un video cuyo slug ya no existe (renombrado), intentar
            // un 301 al slug nuevo vía la tabla de aliases ANTES de caer al home.
            if (($this->current_url_params['type'] ?? '') === 'video') {
                $this->try_alias_redirect(
                    $this->current_url_params['language'],
                    $this->current_url_params['video']
                );
            }

            // Invalid parameters, redirect to home
            // 302 (no 301): la causa puede ser caché de plugin desactualizada al subir un video nuevo;
            // un 301 lo cachean los navegadores y deja la URL "pegada" al home aunque luego sí exista.
            $lang = $this->current_url_params['language'];
            if ($lang === 'en') {
                $home_url = home_url('/en/');
            } elseif ($lang === 'pt') {
                $home_url = home_url('/pt/');
            } else {
                $home_url = home_url('/');
            }
            wp_redirect($home_url, 302);
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

            case 'audiobooks':
                // Audiobooks grid - always valid
                return true;

            case 'audiobook':
                // Validate audiobook exists
                if (empty($this->current_url_params['audiobook'])) {
                    return false;
                }
                // Let ADC_Audiobooks handle validation
                return true;

            case 'kalai':
                // Rabino Anidjar AI Chat - always valid
                return true;

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
        $current_uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';
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

            if (preg_match('#^/en/(program|search)/#', $clean_uri)) {
                $debug_info['reason'] = 'Invalid plugin URL in English';
            } elseif ($clean_uri === '/en') {
                $debug_info['reason'] = 'Exact /en - let WordPress handle';
                $this->output_404_debug('ADC 404 Handler', $debug_info);
                return;
            }
        } elseif (strpos($clean_uri, '/pt') === 0) {
            // URLs que empiezan con /pt
            $detected_language = 'pt';
            $redirect_url = home_url('/pt/');
            $debug_info['reason'] = 'URL starts with /pt';

            if (preg_match('#^/pt/(programa|buscar)/#', $clean_uri)) {
                $debug_info['reason'] = 'Invalid plugin URL in Portuguese';
            } elseif ($clean_uri === '/pt') {
                $debug_info['reason'] = 'Exact /pt - let WordPress handle';
                $this->output_404_debug('ADC 404 Handler', $debug_info);
                return;
            }
        } else {
            // URLs en español (no empiezan con /en ni /pt)
            $detected_language = 'es';
            $redirect_url = home_url('/');
            $debug_info['reason'] = 'Spanish URL or default';

            if (preg_match('#^/(programa|buscar)/#', $clean_uri)) {
                $debug_info['reason'] = 'Invalid plugin URL in Spanish';
            }
        }

        // VALIDACIONES ADICIONALES

        // No redirigir si es un archivo físico (imágenes, CSS, JS, etc.)
        if (preg_match('#\.(jpg|jpeg|png|gif|webp|css|js|ico|pdf|zip|txt|xml)$#i', $clean_uri)) {
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
        $pt_home_path = parse_url(home_url('/pt/'), PHP_URL_PATH);

        if ($clean_uri === rtrim($home_path, '/') || $clean_uri === rtrim($en_home_path, '/') || $clean_uri === rtrim($pt_home_path, '/')) {
            $debug_info['reason'] = 'Already on home page - not redirecting';
            $this->output_404_debug('ADC 404 Handler', $debug_info);
            return;
        }

        // EJECUTAR REDIRECCIÓN
        $debug_info['detected_language'] = $detected_language;
        $debug_info['redirect_url'] = $redirect_url;

        // Debug output antes de redireccionar
        $this->output_404_debug('ADC 404 Handler', $debug_info);

        // Redirección con JavaScript (más confiable para 404s)
        echo '<script>
        window.location.href = "' . esc_js($redirect_url) . '";
        </script>';

        // También intentar redirección por meta refresh como backup
        echo '<meta http-equiv="refresh" content="0; url=' . esc_url($redirect_url) . '">';
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
        add_action('wp_head', function () use ($title, $json_data) {
            echo '<script>
            (function() {
                var debugData = ' . $json_data . ';
                console.group("🚨 ' . esc_js($title) . '");
                console.log("404 Detection Info:", debugData);
                console.groupEnd();
            })();
            </script>';
        }, 1);
    }

    /**
     * NEW: Modify main query to show correct page for friendly URLs
     */
    public function modify_main_query($query)
    {
        if (!$query->is_main_query() || is_admin()) {
            return;
        }

        // Con parse_query, las query vars ya están procesadas
        $adc_type = isset($query->query_vars['adc_type']) ? $query->query_vars['adc_type'] : '';
        $adc_language = isset($query->query_vars['adc_language']) ? $query->query_vars['adc_language'] : 'es';

        if (!$adc_type) {
            return;
        }


        // Determine which page to show based on language - CORREGIDO
        if ($adc_language === 'en') {
            $target_page_slug = 'en';
        } elseif ($adc_language === 'pt') {
            $target_page_slug = 'pt';
        } else {
            $target_page_slug = 'home';
        }

        // Find the page with the appropriate shortcode
        $target_page = get_page_by_path($target_page_slug);


        if (!$target_page) {
            // Fallback: find page by title
            if ($adc_language === 'en') {
                $page_title = 'Home Ingles';
            } elseif ($adc_language === 'pt') {
                $page_title = 'Home Portugues';
            } else {
                $page_title = 'Home';
            }
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
        wp_enqueue_style(
            'adc-style',
            ADC_PLUGIN_URL . 'style.css',
            array(),
            '5.1.5'
        );

        wp_enqueue_script(
            'adc-script',
            ADC_PLUGIN_URL . 'script.js',
            array('jquery'),
            '5.1.5',
            true
        );

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

        // Bridge para postMessage 'goto-next' desde el iframe Radiant
        wp_enqueue_script(
            'adc-radiant-bridge',
            ADC_PLUGIN_URL . 'assets/js/radiant-bridge.js',
            array(),
            '5.1.5',
            true
        );
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
                'version' => '4.0'
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
            $msgs = array(
                'en' => 'No search term provided',
                'pt' => 'Nenhum termo de busca fornecido',
                'es' => 'No se proporcionó término de búsqueda'
            );
            wp_send_json_error(array(
                'message' => isset($msgs[$language]) ? $msgs[$language] : $msgs['es']
            ));
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
        // Verificar nonce obligatorio para consistencia de seguridad
        check_ajax_referer('adc_nonce', 'nonce');

        $language = isset($_POST['language']) ? ADC_Utils::validate_language($_POST['language']) : 'es';

        try {
            // Create API instance for the specific language
            $api = new ADC_API($language);

            // Verificar que la API esté configurada
            if (!$api->is_configured()) {
                $msgs = array(
                    'en' => 'API not configured',
                    'pt' => 'API não configurada',
                    'es' => 'API no configurada'
                );
                wp_send_json_error(array(
                    'message' => isset($msgs[$language]) ? $msgs[$language] : $msgs['es']
                ));
                return;
            }

            // Get programs for menu
            $programs = $api->get_all_programs_for_menu();

            if (empty($programs)) {
                wp_send_json_error(array(
                    'message' => ADC_Utils::get_text('no_programs', $language)
                ));
                return;
            }

            wp_send_json_success($programs);
        } catch (Exception $e) {
            $msgs = array(
                'en' => 'Internal server error',
                'pt' => 'Erro interno do servidor',
                'es' => 'Error interno del servidor'
            );
            wp_send_json_error(array(
                'message' => isset($msgs[$language]) ? $msgs[$language] : $msgs['es']
            ));
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

    public function display_content_pt($atts)
    {
        $this->language = 'pt';
        $this->api = new ADC_API('pt');
        return $this->display_content($atts);
    }

    /**
     * Main content display handler - UPDATED for friendly URLs
     */
    public function display_content($atts)
    {
        // DEBUG CONDICIONAL - Solo mostrar si debug_mode está activado
        if (isset($this->options['debug_mode']) && $this->options['debug_mode'] === '1') {
            $api_status = $this->api->is_configured() ? '✅' : '❌';
            $content_path = get_query_var('adc_program') . '/' . get_query_var('adc_video');
            echo '<script>
            console.log("🚀 ADC SYSTEM STATUS");
            console.log("├── 🔌 API: Configured ' . $api_status . '");
            console.log("├── 🌐 Language: ' . $this->language . '");
            console.log("├── 📺 Content: ' . $content_path . '");
            console.log("├── 📊 URL Params:", ' . json_encode($this->current_url_params) . ');
            console.log("└── 🎯 Current URL:", window.location.href);
            </script>';
        }

        // Check if API is configured
        if (!$this->api->is_configured()) {
            $cfg_msgs = array(
                'es' => 'El plugin ADC Video Display no está configurado. Por favor configura la API en el panel de administración.',
                'en' => 'The ADC Video Display plugin is not configured. Please configure the API in the admin panel.',
                'pt' => 'O plugin ADC Video Display não está configurado. Por favor, configure a API no painel de administração.'
            );
            $msg = isset($cfg_msgs[$this->language]) ? $cfg_msgs[$this->language] : $cfg_msgs['es'];
            return '<div class="adc-error">' . $msg . '</div>';
        }

        // Check for friendly URL parameters first
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
                'search' => get_query_var('adc_search'),
                'audiobook' => get_query_var('adc_audiobook'),
                'festival' => get_query_var('adc_festival')
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

            case 'audiobooks':
            case 'audiobook':
                // Handled by ADC_Audiobooks class via shortcode
                return do_shortcode('[adc_audiobooks]');

            case 'kalai':
                // Rabino Anidjar AI Chat
                return $this->render_kalai_page();

            case 'festival':
                return $this->display_festival_videos($this->current_url_params['festival']);

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
            $empty_msgs = array(
                'es' => 'Por favor ingresa un término de búsqueda.',
                'en' => 'Please enter a search term.',
                'pt' => 'Por favor, insira um termo de busca.'
            );
            $msg = isset($empty_msgs[$this->language]) ? $empty_msgs[$this->language] : $empty_msgs['es'];
            return '<div class="adc-error">' . $msg . '</div>';
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

                // Use friendly URLs
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
            ),
            'pt' => array(
                'title' => 'Não encontramos resultados para',
                'recommended_title' => 'Talvez você se interesse por estes vídeos:'
            )
        );

        $texts = isset($no_results_texts[$language]) ? $no_results_texts[$language] : $no_results_texts['es'];

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
            $empty_msgs = array(
                'es' => 'No hay recomendaciones disponibles en este momento.',
                'en' => 'No recommendations available at this time.',
                'pt' => 'Não há recomendações disponíveis no momento.'
            );
            $msg = isset($empty_msgs[$this->language]) ? $empty_msgs[$this->language] : $empty_msgs['es'];
            return '<div class="adc-recommended-empty">' . $msg . '</div>';
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

            // Use friendly URLs
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
        } elseif ($this->language === 'pt') {
            return $base_url . 'pt/programa/' . $program_slug . '/' . $video_slug . '/';
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
        } elseif ($this->language === 'pt') {
            return $base_url . 'pt/programa/' . $program_slug . '/';
        } else {
            return $base_url . 'programa/' . $program_slug . '/';
        }
    }

    /**
     * Render a single video card - UPDATED for friendly URLs
     */
    /**
     * ¿El video es "nuevo"? (publicado en los últimos $days días)
     */
    private function is_video_new($video, $days = 30)
    {
        if (empty($video['releaseDate'])) return false;
        $ts = strtotime($video['releaseDate']);
        if (!$ts) return false;
        return $ts >= (current_time('timestamp') - ($days * DAY_IN_SECONDS));
    }

    /**
     * Fecha de publicación en formato "21-julio-26" (día-mes-año), localizado.
     */
    private function format_publish_date($video)
    {
        if (empty($video['releaseDate'])) return '';
        $ts = strtotime($video['releaseDate']);
        if (!$ts) return '';

        $meses_por_idioma = array(
            'es' => array(1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'),
            'en' => array(1 => 'january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'),
            'pt' => array(1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'),
        );
        $lang = isset($meses_por_idioma[$this->language]) ? $this->language : 'es';
        $meses = $meses_por_idioma[$lang];

        return intval(date('j', $ts)) . '-' . ucfirst($meses[intval(date('n', $ts))]) . '-' . date('y', $ts);
    }

    /**
     * Texto del badge "NUEVO" localizado.
     */
    private function get_new_badge_text()
    {
        if ($this->language === 'en') return 'NEW';
        if ($this->language === 'pt') return 'NOVO';
        return 'NUEVO';
    }

    /**
     * Etiqueta "Publicado" localizada.
     */
    private function get_published_label()
    {
        if ($this->language === 'en') return 'Published';
        return 'Publicado';
    }

    /**
     * Comparador de orden para listas de videos:
     *   1) Si $pin_substr viene, el video cuyo título lo contenga va PRIMERO.
     *   2) Por fecha de publicación (día) descendente — más nuevo primero.
     *   3) A igual fecha (mismo día), orden alfabético por título.
     */
    private function compare_videos_for_display($a, $b, $pin_substr = null)
    {
        if (!empty($pin_substr)) {
            $pa = (stripos($a['title'] ?? '', $pin_substr) !== false) ? 0 : 1;
            $pb = (stripos($b['title'] ?? '', $pin_substr) !== false) ? 0 : 1;
            if ($pa !== $pb) return $pa - $pb;
        }
        $fecha_a = substr($a['releaseDate'] ?? '', 0, 10); // Y-m-d
        $fecha_b = substr($b['releaseDate'] ?? '', 0, 10);
        if ($fecha_a !== $fecha_b) return strcmp($fecha_b, $fecha_a); // fecha desc
        return strcasecmp($a['title'] ?? '', $b['title'] ?? '');       // alfabético asc
    }

    private function render_video_card($video, $url, $show_meta = false)
    {
        $output = '<div class="adc-search-video-item">';
        $output .= '<a href="' . esc_url($url) . '" class="adc-search-video-link">';
        $output .= '<div class="adc-search-thumbnail">';

        // Use thumbnail from API
        $thumbnail_url = ADC_Utils::get_thumbnail_url($video['thumbnail']);
        $output .= '<img src="' . esc_url($thumbnail_url) . '" alt="' . esc_attr($video['title']) . '" loading="lazy">';
        $output .= '<div class="adc-search-play-icon"></div>';
        if ($show_meta && $this->is_video_new($video)) {
            $output .= '<span class="adc-new-badge">' . esc_html($this->get_new_badge_text()) . '</span>';
        }
        $output .= '</div>';

        $output .= '<div class="adc-search-info">';
        $output .= '<h3 class="adc-search-title">' . esc_html($video['title']) . '</h3>';
        $output .= '<div class="adc-search-program">' . ADC_Utils::get_text('program', $this->language) . ': ' . esc_html($video['category']) . '</div>';
        $output .= '<div class="adc-search-duration">' . ADC_Utils::get_text('duration', $this->language) . ': ' . esc_html($video['duration']) . '</div>';
        if ($show_meta) {
            $fecha = $this->format_publish_date($video);
            if ($fecha !== '') {
                $output .= '<div class="adc-search-published">' . esc_html($this->get_published_label()) . ': ' . esc_html($fecha) . '</div>';
            }
        }
        $output .= '</div>';
        $output .= '</a>';
        $output .= '</div>';

        return $output;
    }

    // ================================================================
    // FESTIVIDADES - Banner y página de videos por festividad
    // ================================================================

    /**
     * Render festival banner above the programs grid
     */
    private function render_festival_banner($festival)
    {
        if ($this->language === 'en') {
            $texto = $festival['texto_en'];
        } elseif ($this->language === 'pt') {
            $texto = isset($festival['texto_pt']) ? $festival['texto_pt'] : $festival['texto_es'];
        } else {
            $texto = $festival['texto_es'];
        }
        $url = home_url('/festividad/' . $festival['slug'] . '/');

        $output = '<div class="adc-festival-banner">';
        $output .= '<a href="' . esc_url($url) . '" class="adc-festival-banner-link">';
        $output .= '<span class="adc-festival-banner-text">' . esc_html($texto) . '</span>';
        $output .= '<span class="adc-festival-banner-arrow">&rarr;</span>';
        $output .= '</a>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Display festival videos page (grid de videos filtrados por tags)
     */
    private function display_festival_videos($slug)
    {
        // Seguridad: si no hay slug o festividad no activa, mostrar grid normal
        $festival = !empty($slug) ? $this->api->get_festival_by_slug($slug) : null;
        if (!$festival) {
            return $this->display_categories_grid();
        }

        // Buscar videos por tags
        $videos = $this->api->search_by_tags($festival['tags']);

        // Solo mostrar videos cuya categoría sea un PROGRAMA navegable en IA
        // (categorías con portada IA). Evita que salgan videos que listan pero
        // no abren (p.ej. clips sin portada, que redirigen a la home al hacer clic).
        $programs = $this->api->get_programs();
        $valid_category_slugs = array();
        foreach ($programs as $p) {
            if (isset($p['name'])) {
                $valid_category_slugs[ADC_Utils::slugify($p['name'])] = true;
            }
        }
        $videos = array_values(array_filter($videos, function ($v) use ($valid_category_slugs) {
            return isset($v['category']) && isset($valid_category_slugs[ADC_Utils::slugify($v['category'])]);
        }));

        // Ordenar: "Viviendo la destruccion de los templos" primero, luego más
        // nuevo primero, y a igual fecha (mismo día) en orden alfabético.
        usort($videos, function ($a, $b) {
            return $this->compare_videos_for_display($a, $b, 'Viviendo la destruccion de los templos');
        });

        if ($this->language === 'en') {
            $texto = $festival['texto_en'];
        } elseif ($this->language === 'pt') {
            $texto = isset($festival['texto_pt']) ? $festival['texto_pt'] : $festival['texto_es'];
        } else {
            $texto = $festival['texto_es'];
        }

        $output = '<div class="adc-festival-page">';

        // Botón volver
        if ($this->language === 'en') {
            $back_text = '&larr; Back';
            $back_url = home_url('/en/');
        } elseif ($this->language === 'pt') {
            $back_text = '&larr; Voltar';
            $back_url = home_url('/pt/');
        } else {
            $back_text = '&larr; Volver';
            $back_url = home_url('/');
        }
        $output .= '<a href="' . esc_url($back_url) . '" class="adc-back-button">' . $back_text . '</a>';

        // Título
        $output .= '<h1 class="adc-festival-title">' . esc_html($texto) . '</h1>';

        if (empty($videos)) {
            if ($this->language === 'en') {
                $no_videos_text = 'No videos available for this festival yet.';
            } elseif ($this->language === 'pt') {
                $no_videos_text = 'Ainda não há vídeos disponíveis para este feriado.';
            } else {
                $no_videos_text = 'Aún no hay videos disponibles para esta festividad.';
            }
            $output .= '<div class="adc-error">' . $no_videos_text . '</div>';
        } else {
            $output .= '<div class="adc-recommended-videos">';

            foreach ($videos as $video) {
                // Omitir categoría "Nuestras Canciones" en páginas de festividad
                if (isset($video['category']) && strcasecmp($video['category'], 'Nuestras Canciones') === 0) {
                    continue;
                }
                $category_slug = ADC_Utils::slugify($video['category']);
                $video_slug = ADC_Utils::slugify($video['title']);
                $url = $this->build_friendly_video_url($category_slug, $video_slug);
                $output .= $this->render_video_card($video, $url, true);
            }

            $output .= '</div>';
        }

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

        // Banner de festividad (solo si hay festividad activa Y tiene videos)
        $banner_html = '';
        $festival = $this->api->get_active_festival();
        if ($festival) {
            $festival_videos = $this->api->search_by_tags($festival['tags']);
            if (!empty($festival_videos)) {
                $banner_html = $this->render_festival_banner($festival);
            }
        }

        $output = '<div class="adc-categories-grid">';
        $output .= '<div class="adc-categories-row">';

        foreach ($programs as $program) {
            $output .= $this->render_category_card($program, $programs_with_videos);
        }

        $output .= '</div></div>';

        return $banner_html . $output;
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
            // Use friendly URLs for category links
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

        // Solo la página de "Películas en IA" (categoría 282) ordena por más nuevo
        // y muestra badge NUEVO + fecha de publicación. Las demás quedan igual.
        $is_peliculas = (isset($category['id']) && intval($category['id']) === 282);

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
            // Películas: más nuevo primero, y a igual fecha (mismo día) alfabético
            if ($is_peliculas) {
                usort($season_videos, function ($a, $b) {
                    return $this->compare_videos_for_display($a, $b);
                });
            }
            $season_name = $this->api->get_season_name($season_num);
            $output .= '<h2 class="adc-season-header"><span>' . esc_html($season_name) . '</span></h2>';

            $output .= '<div class="adc-videos-grid">';
            $output .= '<div class="adc-videos-row cols-' . $videos_per_row . '">';

            foreach ($season_videos as $video) {
                $video_slug = ADC_Utils::slugify($video['title']);

                // Use thumbnail from API
                $thumbnail_url = ADC_Utils::get_thumbnail_url($video['thumbnail']);

                // Use friendly URLs for video links
                $video_url = $this->build_friendly_video_url($category_slug, $video_slug);

                $output .= '<div class="adc-video-item">';
                $output .= '<a href="' . esc_url($video_url) . '" class="adc-video-link">';
                $output .= '<div class="adc-video-thumbnail">';
                $output .= '<img src="' . esc_url($thumbnail_url) . '" alt="' . esc_attr($video['title']) . '" loading="lazy">';
                $output .= '<div class="adc-video-play-icon"></div>';
                if ($is_peliculas && $this->is_video_new($video)) {
                    $output .= '<span class="adc-new-badge">' . esc_html($this->get_new_badge_text()) . '</span>';
                }
                $output .= '</div>';

                $output .= '<div class="adc-video-info">';
                $output .= '<h3 class="adc-video-title">' . esc_html($video['title']) . '</h3>';
                $output .= '<span class="adc-video-duration">' . ADC_Utils::get_text('duration', $this->language) . ': ' . esc_html($video['duration']) . '</span>';
                if ($is_peliculas) {
                    $fecha = $this->format_publish_date($video);
                    if ($fecha !== '') {
                        $output .= '<span class="adc-video-published">' . esc_html($this->get_published_label()) . ': ' . esc_html($fecha) . '</span>';
                    }
                }
                $output .= '</div>';
                $output .= '</a>';
                $output .= '</div>';
            }

            $output .= '</div></div>';
        }

        return $output;
    }

    /**
     * Render promotional clip for category.
     * MP4 corto (no HLS) embebido como <video> HTML5 simple — no necesita
     * Radiant Media Player. El player VOD principal va via iframe a /radiant/vod.php.
     */
    private function render_promotional_clip($category)
    {
        $output = '<div class="adc-promotional-clip-section">';

        $clip_id = 'adc-promo-player-' . uniqid();

        // Poster: use $category['cover'] when available (only image field the
        // ADC API exposes at category level). If absent, omit the attribute
        // rather than emitting an empty one.
        $poster_attr = '';
        if (!empty($category['cover'])) {
            $poster_attr = ' poster="' . esc_url($category['cover']) . '"';
        }

        $output .= '<div class="adc-promotional-video-player" style="position:relative; padding-top:56.25%; margin-bottom:30px;">';
        $output .= '<video id="' . esc_attr($clip_id) . '" class="adc-promo-video" controls playsinline preload="metadata"' . $poster_attr . ' style="position:absolute; top:0; left:0; width:100%; height:100%; background:#000;">';
        $output .= '<source src="' . esc_url($category['clip']) . '" type="video/mp4">';
        $output .= '</video>';
        $output .= '</div>';

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

        // MOMENTO A: registrar el slug actual de este video para poder redirigir
        // (301) si algún día se renombra. Se auto-alimenta con cada vista.
        $this->register_slug_alias($this->language, $video_slug, $video['id'], $category_slug);

        // Find next video
        $next_video = null;
        $next_url = '';
        if ($video_index < count($materials) - 1) {
            $next_video = $materials[$video_index + 1];
            $next_slug = ADC_Utils::slugify($next_video['title']);

            // Use friendly URL for next video
            $next_url = $this->build_friendly_video_url($category_slug, $next_slug);
        }

        $output = '<div class="adc-video-container">';

        // Video title and back button container
        $output .= '<div class="adc-video-header">';
        $output .= '<h1 class="adc-video-main-title">' . esc_html($video['title']) . '</h1>';

        // Use friendly URL for back button
        $back_url = $this->build_friendly_program_url($category_slug);
        $output .= '<a href="' . esc_url($back_url) . '" class="adc-back-program-button">' .
            ADC_Utils::get_text('back_to', $this->language) . ' ' . esc_html($category['name']) . '</a>';
        $output .= '</div>';

        // Player Radiant via iframe. Toda la logica del player vive en
        // /radiant/vod.php del servidor TUIA. El plugin solo pasa los
        // parametros necesarios por query string y escucha postMessage
        // 'goto-next' para redirigir al siguiente video.
        $poster_url  = ADC_Utils::get_thumbnail_url($video["thumbnail"]);
        $hls_url     = preg_replace('#/\d+/(\d+)\.mp4$#', '/$1.smil/playlist.m3u8', $video['video']);
        $next_title  = $next_video ? $next_video['title'] : '';
        $next_thumb  = $next_video ? ADC_Utils::get_thumbnail_url($next_video['thumbnail']) : '';

        $iframe_params = array(
            'hls'        => $hls_url,
            'title'      => $video['title'],
            'poster'     => $poster_url,
        );
        if ($next_url) {
            $iframe_params['next_url']   = $next_url;
            $iframe_params['next_title'] = $next_title;
            if ($next_thumb) {
                $iframe_params['next_thumb'] = $next_thumb;
            }
        }
        $iframe_src = home_url('/radiant/vod.php') . '?' . http_build_query($iframe_params);

        $output .= '<div class="adc-video-player" style="position:relative; padding-top:56.25%;">';
        $output .= '<iframe src="' . esc_url($iframe_src) . '" frameborder="0" allowfullscreen allow="autoplay; fullscreen; picture-in-picture; encrypted-media" style="position:absolute; top:0; left:0; width:100%; height:100%; border:0;"></iframe>';
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

            // Use friendly URLs for related videos
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

        $output .= '</div>';

        return $output;
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

    /**
     * Render KAL-AI Chat Widget (Rabino Anidjar)
     * Appears as floating button on all pages
     */
    public function render_kalai_widget()
    {
        // Only show on frontend, not admin
        if (is_admin()) {
            return;
        }
        ?>
        <script>
            window.Base44CollectionWidgetConfig = {
                collectionId: 'cmiw119mm05azqe7395rm140y',
                title: 'Rabino Anidjar',
                primaryColor: '#85C1E2',
                theme: 'light',
                widgetImage: 'https://kal-ai.com/api/collection-image?collectionId=cmiw119mm05azqe7395rm140y'
            };
        </script>
        <script src="https://kal-ai.com/collection-widget.js" async></script>
        <?php
    }

    /**
     * Render KAL-AI full page (Rabino Anidjar AI Chat)
     * For /rabino-anidjar/ URL - full page iframe experience
     */
    public function render_kalai_page()
    {
        $output = '<div class="adc-kalai-container">';
        $output .= '<style>
            .adc-kalai-container {
                width: 100%;
                max-width: 1200px;
                margin: 0 auto;
                padding: 20px;
            }
            .adc-kalai-header {
                text-align: center;
                margin-bottom: 20px;
            }
            .adc-kalai-header h1 {
                font-size: 1.8em;
                margin-bottom: 10px;
                color: #333;
            }
            .adc-kalai-header p {
                color: #666;
                font-size: 1.1em;
            }
            .adc-kalai-wrapper {
                width: 100%;
                border-radius: 12px;
                overflow: hidden;
                box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            }
            @media (max-width: 768px) {
                .adc-kalai-container {
                    padding: 10px;
                }
                .adc-kalai-header h1 {
                    font-size: 1.4em;
                }
            }
        </style>';

        $output .= '<div class="adc-kalai-header">';
        $output .= '<h1>Rabino Anidjar AI</h1>';
        $output .= '<p>Consulta con IA basada en los shiurim del Rabino Anidjar</p>';
        $output .= '</div>';

        $output .= '<div class="adc-kalai-wrapper">';
        $output .= '<iframe
            src="https://kal-ai.com/collection-chat/cmiw119mm05azqe7395rm140y?embed=true&theme=light&initialMessage=Shalom!%20que%20quieres%20preguntar%3F"
            style="border:none; width:100%; height:600px;"
            title="Chat with Rabino Anidjar"
            allow="clipboard-write; autoplay; fullscreen; picture-in-picture"
            sandbox="allow-same-origin allow-scripts allow-forms allow-popups allow-modals"
            loading="lazy"
            referrerpolicy="strict-origin-when-cross-origin">
        </iframe>';
        $output .= '</div>';
        $output .= '</div>';

        return $output;
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
        'api_url' => 'https://api.tutorah.tv/v2',
        'videos_per_row' => '4',
        'enable_autoplay' => '1',
        'autoplay_countdown' => '5',
        'related_videos_count' => '8',
        'debug_mode' => '0',
        'enable_cache' => '1',
        'cache_duration' => '6',
        'webhook_token' => 'adc_' . wp_generate_password(32, false, false)
    );

    add_option('adc-video-display', $default_options);

    // Ensure webhook_token exists even on re-activation with old options
    $current_options = get_option('adc-video-display', array());
    if (empty($current_options['webhook_token'])) {
        $current_options['webhook_token'] = 'adc_' . wp_generate_password(32, false, false);
        update_option('adc-video-display', $current_options);
    }

    // Initialize program order options for each language (only ES and EN now)
    add_option('adc_programs_order_es', array());
    add_option('adc_programs_order_en', array());
    add_option('adc_programs_order_pt', array());

    // Force rewrite rules flush on activation
    update_option('adc_rewrite_rules_flushed', '0');

    // Asegurar la tabla de aliases de slug al activar.
    adc_ensure_slug_aliases_table();
}

// ============================================================
// Tabla de aliases de slug — redirección 301 de material RENOMBRADO.
// Guarda slug_viejo => material_id (+ category_slug). Se llena sola desde
// display_video() y se consulta en try_alias_redirect(). Creación idempotente.
// ============================================================
if (!defined('ADC_SLUG_ALIASES_DB_VERSION')) {
    define('ADC_SLUG_ALIASES_DB_VERSION', '1.0');
}

add_action('plugins_loaded', 'adc_ensure_slug_aliases_table');
function adc_ensure_slug_aliases_table()
{
    if (get_option('adc_slug_aliases_db_version') === ADC_SLUG_ALIASES_DB_VERSION) {
        return;
    }

    global $wpdb;
    $table   = $wpdb->prefix . 'adc_slug_aliases';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        lang VARCHAR(5) NOT NULL DEFAULT 'es',
        video_slug VARCHAR(191) NOT NULL,
        material_id BIGINT UNSIGNED NOT NULL,
        category_slug VARCHAR(191) NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_lang_slug (lang, video_slug),
        KEY idx_material (material_id)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    // Seed: renombres previos al sistema de aliases (no se auto-aprendieron porque
    // el nombre ya había cambiado). Idempotente por el UNIQUE KEY (lang, video_slug).
    $seed = array(
        // lang, slug_viejo, material_id, category_slug
        array('es', 'viviendo-la-destruccion-de-los-templos', 5461512322, 'peliculas-en-ia'),
    );
    foreach ($seed as $s) {
        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$table} (lang, video_slug, material_id, category_slug, updated_at)
             VALUES (%s, %s, %d, %s, NOW())",
            $s[0],
            $s[1],
            $s[2],
            $s[3]
        ));
    }

    update_option('adc_slug_aliases_db_version', ADC_SLUG_ALIASES_DB_VERSION);
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
function adc_add_cache_clear_endpoint()
{
    add_rewrite_rule('^cache/clear/?$', 'index.php?adc_cache_clear=1', 'top');
}

// Registrar query var
add_filter('query_vars', 'adc_add_cache_clear_query_var');
function adc_add_cache_clear_query_var($vars)
{
    $vars[] = 'adc_cache_clear';
    return $vars;
}

// Manejar la request de cache clear
add_action('template_redirect', 'adc_handle_cache_clear_request');
function adc_handle_cache_clear_request()
{
    if (get_query_var('adc_cache_clear')) {
        adc_display_cache_clear_page();
        exit;
    }
}

// Mostrar página de limpieza de caché
function adc_display_cache_clear_page()
{
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

        // Limpiar también la tabla api_cache de la API (caché de lectura de 2h)
        // para que los cambios de contenido se reflejen al instante desde aquí.
        $opts = get_option('adc-video-display');
        if (!empty($opts['api_url']) && !empty($opts['api_token'])) {
            wp_remote_get(rtrim($opts['api_url'], '/') . '/cache/clear', array(
                'headers' => array('Authorization' => $opts['api_token']),
                'timeout' => 15,
            ));
        }

        $success = true;
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }

    // Detectar idioma actual
    $current_language = ADC_Utils::detect_language();

    $i18n = array(
        'es' => array(
            'title' => 'Caché Limpiado',
            'success_title' => '¡Caché Limpiado Exitosamente!',
            'success_msg' => 'El caché del sitio web ha sido limpiado exitosamente. Todo el contenido ahora mostrará las actualizaciones más recientes inmediatamente.',
            'redirect' => 'Redirigiendo al inicio en',
            'seconds' => 'segundos',
            'go_home_now' => 'Ir al Inicio Ahora',
            'error_title' => 'Error al Limpiar Caché',
            'error_msg' => 'Hubo un error al limpiar el caché del sitio web. Por favor intenta nuevamente o contacta soporte.',
            'error_details' => 'Detalles del Error:',
            'go_home' => 'Ir al Inicio'
        ),
        'en' => array(
            'title' => 'Cache Cleared',
            'success_title' => 'Cache Cleared Successfully!',
            'success_msg' => 'The website cache has been cleared successfully. All content will now display the latest updates immediately.',
            'redirect' => 'Redirecting to home in',
            'seconds' => 'seconds',
            'go_home_now' => 'Go to Home Now',
            'error_title' => 'Cache Clear Failed',
            'error_msg' => 'There was an error clearing the website cache. Please try again or contact support.',
            'error_details' => 'Error Details:',
            'go_home' => 'Go to Home'
        ),
        'pt' => array(
            'title' => 'Cache Limpo',
            'success_title' => 'Cache Limpo com Sucesso!',
            'success_msg' => 'O cache do site foi limpo com sucesso. Todo o conteúdo agora exibirá as atualizações mais recentes imediatamente.',
            'redirect' => 'Redirecionando para o início em',
            'seconds' => 'segundos',
            'go_home_now' => 'Ir para o Início Agora',
            'error_title' => 'Erro ao Limpar Cache',
            'error_msg' => 'Houve um erro ao limpar o cache do site. Por favor, tente novamente ou entre em contato com o suporte.',
            'error_details' => 'Detalhes do Erro:',
            'go_home' => 'Ir para o Início'
        )
    );
    $t = isset($i18n[$current_language]) ? $i18n[$current_language] : $i18n['es'];

?>
    <!DOCTYPE html>
    <html lang="<?php echo $current_language; ?>">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $t['title']; ?> - TuIA</title>
        <link rel="stylesheet" href="<?php echo ADC_PLUGIN_URL; ?>cache-clear-styles.css">
    </head>

    <body>
        <div class="container">
            <?php if ($success): ?>
                <div class="success-icon">✅</div>
                <h1 class="title success-title">
                    <?php echo $t['success_title']; ?>
                </h1>
                <p class="message">
                    <?php echo $t['success_msg']; ?>
                </p>

                <div class="countdown">
                    <?php echo $t['redirect']; ?>
                    <span class="countdown-number" id="countdown">5</span>
                    <?php echo $t['seconds']; ?>
                </div>

                <a href="<?php echo home_url('/'); ?>" class="home-button" id="homeButton">
                    <?php echo $t['go_home_now']; ?>
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

                    homeButton.addEventListener('click', () => {
                        clearInterval(timer);
                    });
                </script>

            <?php else: ?>
                <div class="error-icon">❌</div>
                <h1 class="title error-title">
                    <?php echo $t['error_title']; ?>
                </h1>
                <p class="message">
                    <?php echo $t['error_msg']; ?>
                </p>

                <?php if ($error_message): ?>
                    <div class="error-details">
                        <strong><?php echo $t['error_details']; ?></strong><br>
                        <?php echo esc_html($error_message); ?>
                    </div>
                <?php endif; ?>

                <a href="<?php echo home_url('/'); ?>" class="home-button">
                    <?php echo $t['go_home']; ?>
                </a>
            <?php endif; ?>
        </div>
    </body>

    </html>
<?php
}

// Flush rewrite rules on plugin activation (agregar al activation hook existente)
register_activation_hook(__FILE__, 'adc_flush_rewrite_rules_on_activation');
function adc_flush_rewrite_rules_on_activation()
{
    adc_add_cache_clear_endpoint();
    flush_rewrite_rules();
}