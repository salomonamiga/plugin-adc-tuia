<?php
/**
 * ADC Video Display - Utilities Class
 * Version: 3.0 - Funciones compartidas para eliminar duplicación
 * 
 * Contiene todas las funciones utilitarias compartidas entre los módulos del plugin
 * Soporte para Español e Inglés únicamente
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ADC_Utils
{
    /**
     * Convert title to slug
     * Esta función estaba duplicada en 4 archivos diferentes
     */
    public static function slugify($text)
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

    /**
     * Detect language from URL
     * Esta función estaba duplicada en múltiples archivos
     * Solo soporta ES e EN
     */
    public static function detect_language()
    {
        $uri = $_SERVER['REQUEST_URI'];
        
        if (strpos($uri, '/en/') !== false) {
            return 'en';
        }
        
        return 'es';
    }

    /**
     * Validate language parameter
     * Esta validación se repetía en varios lugares
     * Solo soporta ES e EN
     */
    public static function validate_language($language)
    {
        $valid_languages = array('es', 'en');
        
        if (!in_array($language, $valid_languages)) {
            return 'es'; // Default fallback
        }
        
        return $language;
    }

    /**
     * Get language name by code
     * Para mostrar nombres legibles de idiomas
     */
    public static function get_language_name($language)
    {
        $names = array(
            'es' => 'Español',
            'en' => 'English'
        );

        return isset($names[$language]) ? $names[$language] : 'Español';
    }

    /**
     * Get base URL for language
     * Para construir URLs correctas según el idioma
     */
    public static function get_base_url($language = 'es')
    {
        $base_url = home_url('/');
        if ($language !== 'es') {
            $base_url .= $language . '/';
        }
        return $base_url;
    }

    /**
     * Format video duration from seconds or HH:MM:SS to human readable
     * Unifica el formateo de duraciones
     */
    public static function format_duration($duration)
    {
        if (empty($duration)) {
            return '';
        }

        // Si viene en formato HH:MM:SS
        if (strpos($duration, ':') !== false) {
            $parts = explode(':', $duration);
            
            if (count($parts) == 3) {
                $hours = intval($parts[0]);
                $minutes = intval($parts[1]);
                
                if ($hours > 0) {
                    return $hours . 'h ' . $minutes . 'min';
                } else {
                    return $minutes . ' min';
                }
            }
        }

        // Si viene en segundos
        if (is_numeric($duration)) {
            $minutes = floor($duration / 60);
            $seconds = $duration % 60;
            
            if ($minutes > 0) {
                return $minutes . ' min';
            } else {
                return $seconds . ' seg';
            }
        }

        return $duration;
    }

    /**
     * Get localized text by key and language
     * Para centralizar todas las traducciones
     * Solo ES e EN
     */
    public static function get_text($key, $language = 'es')
    {
        $texts = array(
            'programs' => array(
                'es' => 'Programas',
                'en' => 'Programs'
            ),
            'search' => array(
                'es' => 'Buscar',
                'en' => 'Search'
            ),
            'search_placeholder' => array(
                'es' => 'Buscar videos...',
                'en' => 'Search videos...'
            ),
            'back_to' => array(
                'es' => 'Volver a',
                'en' => 'Back to'
            ),
            'back_to_programs' => array(
                'es' => 'Volver a Programas',
                'en' => 'Back to Programs'
            ),
            'duration' => array(
                'es' => 'Duración',
                'en' => 'Duration'
            ),
            'program' => array(
                'es' => 'Programa',
                'en' => 'Program'
            ),
            'no_programs' => array(
                'es' => 'No hay programas disponibles',
                'en' => 'No programs available'
            ),
            'no_videos' => array(
                'es' => 'No se encontraron videos',
                'en' => 'No videos found'
            ),
            'category_not_found' => array(
                'es' => 'Categoría no encontrada',
                'en' => 'Category not found'
            ),
            'video_not_found' => array(
                'es' => 'Video no encontrado',
                'en' => 'Video not found'
            ),
            'search_results_for' => array(
                'es' => 'Resultados de búsqueda para',
                'en' => 'Search results for'
            ),
            'no_results_found' => array(
                'es' => 'No encontramos resultados para',
                'en' => 'No results found for'
            ),
            'recommended_videos' => array(
                'es' => 'Quizás te interesen estos videos:',
                'en' => 'You might be interested in these videos:'
            ),
            'more_videos_from' => array(
                'es' => 'Más videos de',
                'en' => 'More videos from'
            ),
            'watch_next_video' => array(
                'es' => 'Ver siguiente video',
                'en' => 'Watch next video'
            ),
            'next_video_in' => array(
                'es' => 'Siguiente video en',
                'en' => 'Next video in'
            ),
            'seconds' => array(
                'es' => 'segundos',
                'en' => 'seconds'
            ),
            'watch_now' => array(
                'es' => 'Ver ahora',
                'en' => 'Watch now'
            ),
            'cancel' => array(
                'es' => 'Cancelar',
                'en' => 'Cancel'
            ),
            'coming_soon' => array(
                'es' => 'Próximamente',
                'en' => 'Coming Soon'
            )
        );

        if (isset($texts[$key]) && isset($texts[$key][$language])) {
            return $texts[$key][$language];
        }

        // Fallback to Spanish if not found
        if (isset($texts[$key]) && isset($texts[$key]['es'])) {
            return $texts[$key]['es'];
        }

        return $key; // Return key if nothing found
    }

    /**
     * Sanitize search term
     * Para limpiar términos de búsqueda de forma consistente
     */
    public static function sanitize_search_term($term)
    {
        $term = sanitize_text_field($term);
        $term = trim($term);
        
        // Remove extra spaces
        $term = preg_replace('/\s+/', ' ', $term);
        
        return $term;
    }

    /**
     * Check if string is valid language code
     * Verificación rápida de códigos de idioma
     * Solo ES e EN
     */
    public static function is_valid_language($language)
    {
        return in_array($language, array('es', 'en'));
    }

    /**
     * Get cache key with language prefix
     * Para generar claves de caché consistentes
     */
    public static function get_cache_key($base_key, $language = 'es')
    {
        return $language . '_' . $base_key;
    }

    /**
     * Log debug message if debug mode is enabled
     * Para logging consistente en todo el plugin
     */
    public static function debug_log($message, $context = array())
    {
        $options = get_option('adc-video-display');
        $debug_mode = isset($options['debug_mode']) && $options['debug_mode'] === '1';
        
        if ($debug_mode && function_exists('error_log')) {
            $log_message = 'ADC Plugin Debug: ' . $message;
            if (!empty($context)) {
                $log_message .= ' Context: ' . json_encode($context);
            }
            error_log($log_message);
        }
    }

    /**
     * Check if we're on mobile device
     * Para optimizaciones específicas de móvil
     */
    public static function is_mobile()
    {
        return wp_is_mobile();
    }

    /**
     * Get thumbnail URL for video
     * Centraliza la generación de URLs de thumbnails
     */
    public static function get_thumbnail_url($video_id)
    {
        return "https://s3apics.streamgates.net/TutorahTV_Thumbs/{$video_id}_50.jpg";
    }

    /**
     * Build video URL with proper language prefix
     * Para construir URLs de videos de forma consistente
     */
    public static function build_video_url($category_slug, $video_slug, $language = 'es')
    {
        $base_url = self::get_base_url($language);
        return $base_url . '?categoria=' . urlencode($category_slug) . '&video=' . urlencode($video_slug);
    }

    /**
     * Build category URL with proper language prefix
     * Para construir URLs de categorías de forma consistente
     */
    public static function build_category_url($category_slug, $language = 'es')
    {
        $base_url = self::get_base_url($language);
        return $base_url . '?categoria=' . urlencode($category_slug);
    }

    /**
     * Get valid languages array
     * Para uso en loops y validaciones
     */
    public static function get_valid_languages()
    {
        return array('es', 'en');
    }
}