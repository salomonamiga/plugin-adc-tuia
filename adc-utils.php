<?php
/**
 * ADC Video Display - Utilities Class
 * Version: 3.1 - Soporte multiidioma ES / EN / PT
 *
 * Contiene todas las funciones utilitarias compartidas entre los módulos del plugin
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
     * Detect language from URL (ES / EN / PT)
     */
    public static function detect_language()
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';

        if (strpos($uri, '/en/') !== false || preg_match('#/en$#', $uri)) {
            return 'en';
        }

        if (strpos($uri, '/pt/') !== false || preg_match('#/pt$#', $uri)) {
            return 'pt';
        }

        return 'es';
    }

    /**
     * Validate language parameter (ES / EN / PT)
     */
    public static function validate_language($language)
    {
        $valid_languages = array('es', 'en', 'pt');

        if (!in_array($language, $valid_languages)) {
            return 'es';
        }

        return $language;
    }

    /**
     * Get language name by code
     */
    public static function get_language_name($language)
    {
        $names = array(
            'es' => 'Español',
            'en' => 'English',
            'pt' => 'Português'
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
     * Get localized text by key and language (ES / EN / PT)
     */
    public static function get_text($key, $language = 'es')
    {
        $texts = array(
            'programs' => array(
                'es' => 'Programas',
                'en' => 'Programs',
                'pt' => 'Programas'
            ),
            'search' => array(
                'es' => 'Buscar',
                'en' => 'Search',
                'pt' => 'Buscar'
            ),
            'search_placeholder' => array(
                'es' => 'Buscar videos...',
                'en' => 'Search videos...',
                'pt' => 'Pesquisar vídeos...'
            ),
            'back_to' => array(
                'es' => 'Volver a',
                'en' => 'Back to',
                'pt' => 'Voltar para'
            ),
            'back_to_programs' => array(
                'es' => 'Volver a Programas',
                'en' => 'Back to Programs',
                'pt' => 'Voltar aos Programas'
            ),
            'duration' => array(
                'es' => 'Duración',
                'en' => 'Duration',
                'pt' => 'Duração'
            ),
            'program' => array(
                'es' => 'Programa',
                'en' => 'Program',
                'pt' => 'Programa'
            ),
            'no_programs' => array(
                'es' => 'No hay programas disponibles',
                'en' => 'No programs available',
                'pt' => 'Não há programas disponíveis'
            ),
            'no_videos' => array(
                'es' => 'No se encontraron videos',
                'en' => 'No videos found',
                'pt' => 'Nenhum vídeo encontrado'
            ),
            'category_not_found' => array(
                'es' => 'Categoría no encontrada',
                'en' => 'Category not found',
                'pt' => 'Categoria não encontrada'
            ),
            'video_not_found' => array(
                'es' => 'Video no encontrado',
                'en' => 'Video not found',
                'pt' => 'Vídeo não encontrado'
            ),
            'search_results_for' => array(
                'es' => 'Resultados de búsqueda para',
                'en' => 'Search results for',
                'pt' => 'Resultados da busca para'
            ),
            'no_results_found' => array(
                'es' => 'No encontramos resultados para',
                'en' => 'No results found for',
                'pt' => 'Não encontramos resultados para'
            ),
            'recommended_videos' => array(
                'es' => 'Quizás te interesen estos videos:',
                'en' => 'You might be interested in these videos:',
                'pt' => 'Talvez você se interesse por estes vídeos:'
            ),
            'more_videos_from' => array(
                'es' => 'Más videos de',
                'en' => 'More videos from',
                'pt' => 'Mais vídeos de'
            ),
            'watch_next_video' => array(
                'es' => 'Ver siguiente video',
                'en' => 'Watch next video',
                'pt' => 'Ver próximo vídeo'
            ),
            'next_video_in' => array(
                'es' => 'Siguiente video en',
                'en' => 'Next video in',
                'pt' => 'Próximo vídeo em'
            ),
            'seconds' => array(
                'es' => 'segundos',
                'en' => 'seconds',
                'pt' => 'segundos'
            ),
            'watch_now' => array(
                'es' => 'Ver ahora',
                'en' => 'Watch now',
                'pt' => 'Assistir agora'
            ),
            'cancel' => array(
                'es' => 'Cancelar',
                'en' => 'Cancel',
                'pt' => 'Cancelar'
            ),
            'coming_soon' => array(
                'es' => 'Próximamente',
                'en' => 'Coming Soon',
                'pt' => 'Em breve'
            ),
            // Audiobooks texts (PT no usa audiolibros pero se dejan por consistencia)
            'audiobooks' => array(
                'es' => 'Audio Libros',
                'en' => 'Audiobooks',
                'pt' => 'Audiolivros'
            ),
            'audiobooks_subtitle' => array(
                'es' => 'Escucha los libros del Rabino Amram Anidjar',
                'en' => 'Listen to Rabbi Amram Anidjar\'s books',
                'pt' => 'Ouça os livros do Rabino Amram Anidjar'
            ),
            'audiobook_label' => array(
                'es' => 'Audiolibro',
                'en' => 'Audiobook',
                'pt' => 'Audiolivro'
            ),
            'chapters' => array(
                'es' => 'capítulos',
                'en' => 'chapters',
                'pt' => 'capítulos'
            ),
            'chapter' => array(
                'es' => 'Capítulo',
                'en' => 'Chapter',
                'pt' => 'Capítulo'
            ),
            'minute' => array(
                'es' => 'Minuto',
                'en' => 'Minute',
                'pt' => 'Minuto'
            ),
            'listened' => array(
                'es' => 'Escuchado',
                'en' => 'Listened',
                'pt' => 'Ouvido'
            ),
            'continue_listening' => array(
                'es' => 'Continuar',
                'en' => 'Continue',
                'pt' => 'Continuar'
            ),
            'continue_from' => array(
                'es' => 'Continuar desde',
                'en' => 'Continue from',
                'pt' => 'Continuar de'
            ),
            'by' => array(
                'es' => 'por',
                'en' => 'by',
                'pt' => 'por'
            ),
            'no_audiobooks' => array(
                'es' => 'No hay audiolibros disponibles en este momento.',
                'en' => 'No audiobooks available at this time.',
                'pt' => 'Não há audiolivros disponíveis no momento.'
            ),
            'audiobook_not_found' => array(
                'es' => 'Audiolibro no encontrado',
                'en' => 'Audiobook not found',
                'pt' => 'Audiolivro não encontrado'
            ),
            'audiobook_not_found_desc' => array(
                'es' => 'El audiolibro que buscas no existe o ha sido movido.',
                'en' => 'The audiobook you are looking for does not exist or has been moved.',
                'pt' => 'O audiolivro que você procura não existe ou foi movido.'
            ),
            'back_to_audiobooks' => array(
                'es' => 'Volver a Audio Libros',
                'en' => 'Back to Audiobooks',
                'pt' => 'Voltar aos Audiolivros'
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
     * Check if string is valid language code (ES / EN / PT)
     */
    public static function is_valid_language($language)
    {
        return in_array($language, array('es', 'en', 'pt'));
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
     * Get thumbnail URL for video.
     * Recibe la URL completa del thumbnail desde la API (no hardcodeada).
     */
    public static function get_thumbnail_url($thumbnail_url)
    {
        // Validar que la URL no esté vacía
        if (empty($thumbnail_url)) {
            // Fallback solo si realmente no viene thumbnail de la API (caso extremo)
            return '';
        }

        // Sanitizar la URL para seguridad
        $thumbnail_url = esc_url($thumbnail_url);
        
        // Validar que sea una URL válida
        if (!filter_var($thumbnail_url, FILTER_VALIDATE_URL)) {
            return '';
        }

        return $thumbnail_url;
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
     * Get valid languages array (ES / EN / PT)
     */
    public static function get_valid_languages()
    {
        return array('es', 'en', 'pt');
    }
}