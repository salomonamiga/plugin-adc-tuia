<?php
/**
 * ADC Video Display - Utilities
 * 
 * Funciones de utilidad comunes para todo el plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ADC_Utilities {
    
    /**
     * Convertir un texto a formato slug (URL amigable)
     *
     * @param string $text El texto a convertir
     * @return string El texto convertido en formato slug
     */
    public static function slugify($text) {
        // Convertir a minúsculas
        $text = strtolower($text);
        
        // Reemplazar caracteres especiales y acentos
        $text = remove_accents($text);
        
        // Reemplazar cualquier caracter que no sea alfanumérico, espacios o guiones por nada
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        
        // Reemplazar espacios con guiones
        $text = preg_replace('/[\s]+/', '-', $text);
        
        // Reemplazar múltiples guiones con uno solo
        $text = preg_replace('/[-]+/', '-', $text);
        
        // Eliminar guiones del principio y final
        $text = trim($text, '-');
        
        return $text;
    }
    
    /**
     * Escapar y formatear HTML de forma segura
     *
     * @param string $text El texto a escapar
     * @return string El texto escapado
     */
    public static function escape_html($text) {
        return esc_html($text);
    }
    
    /**
     * Escapar atributos HTML de forma segura
     * 
     * @param string $text El texto a escapar
     * @return string El texto escapado para usar en atributos
     */
    public static function escape_attr($text) {
        return esc_attr($text);
    }
    
    /**
     * Escapar URLs de forma segura
     * 
     * @param string $url La URL a escapar
     * @return string La URL escapada
     */
    public static function escape_url($url) {
        return esc_url($url);
    }
    
    /**
     * Incluir una plantilla con variables
     * 
     * @param string $template_name Nombre de la plantilla a incluir
     * @param array $variables Variables para pasar a la plantilla
     * @return string El contenido HTML generado
     */
    public static function get_template($template_name, $variables = array()) {
        // Obtener la ruta completa a la plantilla
        $template_file = plugin_dir_path(dirname(__FILE__)) . 'templates/' . $template_name . '.php';
        
        // Convertir el array de variables en variables individuales
        extract($variables);
        
        // Iniciar el búfer de salida
        ob_start();
        
        // Verificar si el archivo existe
        if (file_exists($template_file)) {
            include($template_file);
        } else {
            echo '<!-- Plantilla no encontrada: ' . $template_name . ' -->';
        }
        
        // Obtener el contenido del búfer y limpiarlo
        $content = ob_get_clean();
        
        return $content;
    }
    
    /**
     * Depurar variables de forma segura (solo en modo debug)
     * 
     * @param mixed $var Variable a depurar
     * @param bool $return Si es true, retorna el resultado en lugar de imprimirlo
     * @param bool $exit Si es true, termina la ejecución después de imprimir
     * @return string|void
     */
    public static function debug($var, $return = false, $exit = false) {
        // Obtener opciones
        $options = get_option('adc-video-display');
        $debug_mode = isset($options['debug_mode']) ? $options['debug_mode'] : '0';
        
        if ($debug_mode != '1') {
            return;
        }
        
        $output = '<pre style="background: #f5f5f5; color: #333; padding: 15px; border: 1px solid #ddd; border-radius: 3px; font-family: monospace; font-size: 14px; line-height: 1.6; overflow: auto; margin: 15px 0;">';
        $output .= print_r($var, true);
        $output .= '</pre>';
        
        if ($return) {
            return $output;
        }
        
        echo $output;
        
        if ($exit) {
            exit;
        }
    }
    
    /**
     * Truncar un texto a una longitud determinada
     * 
     * @param string $text El texto a truncar
     * @param int $length Longitud máxima
     * @param string $append Texto a añadir al final si se trunca
     * @return string El texto truncado
     */
    public static function truncate($text, $length = 100, $append = '...') {
        if (strlen($text) <= $length) {
            return $text;
        }
        
        $text = substr($text, 0, $length);
        $text = substr($text, 0, strrpos($text, ' '));
        
        return $text . $append;
    }
    
    /**
     * Formatear duración de video de HH:MM:SS a formato legible
     * 
     * @param string $duration Duración en formato HH:MM:SS
     * @return string Duración formateada
     */
    public static function format_duration($duration) {
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
        
        return $duration;
    }
    
    /**
     * Obtener la URL base del plugin
     * 
     * @return string URL base
     */
    public static function get_plugin_url() {
        return plugin_dir_url(dirname(__FILE__));
    }
    
    /**
     * Obtener la ruta base del plugin
     * 
     * @return string Ruta base
     */
    public static function get_plugin_path() {
        return plugin_dir_path(dirname(__FILE__));
    }
    
    /**
     * Crear un archivo de caché
     * 
     * @param string $key Clave única para el archivo
     * @param mixed $data Datos a almacenar
     * @param int $expiry Tiempo de expiración en segundos (0 = sin expiración)
     * @return bool Si se pudo crear el archivo
     */
    public static function set_cache($key, $data, $expiry = 3600) {
        $cache_data = array(
            'data' => $data,
            'expiry' => $expiry > 0 ? time() + $expiry : 0
        );
        
        $file = self::get_cache_file($key);
        
        // Crear directorio si no existe
        if (!file_exists(dirname($file))) {
            wp_mkdir_p(dirname($file));
        }
        
        return file_put_contents($file, serialize($cache_data)) !== false;
    }
    
    /**
     * Obtener datos de caché
     * 
     * @param string $key Clave única del archivo
     * @return mixed|false Los datos o false si no existe o expiró
     */
    public static function get_cache($key) {
        $file = self::get_cache_file($key);
        
        if (!file_exists($file)) {
            return false;
        }
        
        $cache_data = unserialize(file_get_contents($file));
        
        // Verificar expiración
        if ($cache_data['expiry'] > 0 && $cache_data['expiry'] < time()) {
            // Borrar archivo expirado
            @unlink($file);
            return false;
        }
        
        return $cache_data['data'];
    }
    
    /**
     * Eliminar un archivo de caché
     * 
     * @param string $key Clave única del archivo
     * @return bool Si se pudo eliminar
     */
    public static function delete_cache($key) {
        $file = self::get_cache_file($key);
        
        if (file_exists($file)) {
            return @unlink($file);
        }
        
        return true;
    }
    
    /**
     * Eliminar toda la caché del plugin
     * 
     * @return void
     */
    public static function clear_all_cache() {
        $cache_dir = WP_CONTENT_DIR . '/cache/adc-video-display/';
        
        if (file_exists($cache_dir) && is_dir($cache_dir)) {
            self::delete_directory_contents($cache_dir);
        }
    }
    
    /**
     * Obtener la ruta de un archivo de caché
     * 
     * @param string $key Clave única
     * @return string Ruta del archivo
     */
    private static function get_cache_file($key) {
        $key = md5($key);
        $cache_dir = WP_CONTENT_DIR . '/cache/adc-video-display/';
        
        return $cache_dir . $key . '.cache';
    }
    
    /**
     * Eliminar recursivamente los contenidos de un directorio
     * 
     * @param string $dir Directorio a limpiar
     * @return void
     */
    private static function delete_directory_contents($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = glob($dir . '*', GLOB_MARK);
        
        foreach ($files as $file) {
            if (is_dir($file)) {
                self::delete_directory_contents($file);
                rmdir($file);
            } else {
                unlink($file);
            }
        }
    }
    
    /**
     * Generar un ID único para la caché de API
     * 
     * @param string $endpoint Endpoint de API
     * @param array $params Parámetros de la petición
     * @return string ID único
     */
    public static function generate_api_cache_key($endpoint, $params = array()) {
        $key = 'adc_api_' . $endpoint;
        
        if (!empty($params)) {
            $key .= '_' . md5(serialize($params));
        }
        
        return $key;
    }
    
    /**
     * Registrar acciones de limpieza de caché
     */
    public static function register_cache_hooks() {
        // Limpiar caché al activar/desactivar plugins
        add_action('activated_plugin', array(__CLASS__, 'clear_all_cache'));
        add_action('deactivated_plugin', array(__CLASS__, 'clear_all_cache'));
        
        // Limpiar caché al guardar opciones del plugin
        add_action('update_option_adc-video-display', array(__CLASS__, 'clear_all_cache'));
    }
}

// Registrar hooks de caché
ADC_Utilities::register_cache_hooks();