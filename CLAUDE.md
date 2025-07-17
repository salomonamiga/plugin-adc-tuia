# Plugin ADC TuTorah - Documentación

## Información General

**Nombre:** ADC Video Display  
**Versión:** 3.2  
**Autor:** TuTorah Development Team  
**Descripción:** Plugin de WordPress que muestra videos desde el sistema ADC en WordPress con soporte multiidioma (Español/Inglés) y URLs amigables.

## Arquitectura del Plugin

### Estructura de Archivos

```
plugin-adc-tuia/
├── adc-admin.php          # Panel de administración con interfaz moderna
├── adc-api.php            # Manejador de API con sistema de caché unificado
├── adc-menu.php           # Manejo de menús desplegables multiidioma
├── adc-search.php         # Sistema de búsqueda con fallback inteligente
├── adc-utils.php          # Utilidades compartidas y funciones comunes
├── adc-video-display.php  # Archivo principal del plugin
├── auto-commit.sh         # Script de auto-commit para Git
├── cache-clear-styles.css # Estilos para página de limpieza de caché
├── script.js              # JavaScript frontend
├── style.css              # Estilos CSS completos
└── CLAUDE.md             # Esta documentación
```

## Funcionalidades Principales

### 1. Sistema de Videos Multiidioma
- Soporte para Español (ES) e Inglés (EN)
- URLs amigables para SEO
- Redirecciones automáticas inteligentes
- Detección automática de idioma por URL

### 2. URLs Amigables
**Español:**
- `/programa/nombre-programa/` - Lista de videos del programa
- `/programa/nombre-programa/nombre-video/` - Video específico
- `/buscar/termino/` - Resultados de búsqueda

**Inglés:**
- `/en/program/program-name/` - Program video list
- `/en/program/program-name/video-name/` - Specific video
- `/en/search/term/` - Search results

### 3. Sistema de Caché Inteligente
- Caché unificado con duración configurable (30 min - 24 horas)
- Limpieza automática vía webhook desde ADC
- Soporte para WordPress transients y object cache
- URL amigable de limpieza manual: `/cache/clear`

### 4. Panel de Administración
- Interfaz moderna con diseño responsivo
- Configuración de API (token, URL)
- Gestión de caché con estadísticas
- Ordenamiento personalizado de programas
- Webhook automático preconfigurado
- Modo debug para desarrollo

## Componentes Técnicos

### Clases Principales

#### `ADC_Video_Display` (adc-video-display.php)
- **Propósito:** Clase principal del plugin
- **Funciones:**
  - Inicialización de hooks y shortcodes
  - Manejo de URLs amigables
  - Renderizado de contenido principal
  - Sistema de redirecciones 404

#### `ADC_API` (adc-api.php)
- **Propósito:** Comunicación con la API de TuTorah
- **Funciones:**
  - Requests HTTP con retry automático
  - Caché unificado optimizado
  - Bulk operations para mejor rendimiento
  - Manejo de errores y fallbacks

#### `ADC_Admin` (adc-admin.php)
- **Propósito:** Panel de administración
- **Funciones:**
  - Interfaz de configuración moderna
  - Estadísticas de sistema en tiempo real
  - Gestión de ordenamiento de programas
  - Webhook automático con token seguro

#### `ADC_Menu` (adc-menu.php)
- **Propósito:** Menús desplegables dinámicos
- **Funciones:**
  - Shortcodes para menús multiidioma
  - Integración con WordPress menus
  - Widget personalizable

#### `ADC_Search` (adc-search.php)
- **Propósito:** Sistema de búsqueda avanzado
- **Funciones:**
  - Búsqueda con fallback inteligente
  - Recomendaciones cuando no hay resultados
  - Agrupación de resultados por categoría

#### `ADC_Utils` (adc-utils.php)
- **Propósito:** Utilidades compartidas
- **Funciones:**
  - Funciones de URL y slug
  - Detección de idioma
  - Textos localizados
  - Cache keys y debug logging

### Sistema de Caché

El plugin implementa un sistema de caché de tres niveles:

1. **Caché interno PHP** - Para evitar requests múltiples en la misma carga
2. **WordPress Transients** - Persistencia entre requests
3. **Object Cache** - Para instalaciones con Redis/Memcached

**Configuración:**
- Duración configurable desde admin (30 min - 24 horas)
- Limpieza automática vía webhook
- Bulk operations para optimizar rendimiento

### Webhook Automático

**URL:** `https://tuia.tv/wp-admin/admin-ajax.php?action=adc_webhook_refresh&token=[TOKEN]`

**Características:**
- Token seguro generado automáticamente
- Activación solo para secciones IA (ES: sección 5, EN: sección 6)
- Limpieza inteligente solo cuando es necesario
- Logs de actividad en modo debug

## Shortcodes Disponibles

### Contenido Principal
```php
[adc_content]     // Contenido en español
[adc_content_en]  // Contenido en inglés
```

### Menús de Programas
```php
[adc_programs_menu text="PROGRAMAS" show_count="false"]     // Español
[adc_programs_menu_en text="PROGRAMS" show_count="false"]   // Inglés
```

### Formularios de Búsqueda
```php
[adc_search_form placeholder="Buscar videos..."]     // Español
[adc_search_form_en placeholder="Search videos..."]  // Inglés
```

## Configuración de Menús WordPress

### Dropdown Automático de Programas
Para crear un dropdown automático en el menú:

1. **Español:**
   - Texto del enlace: `PROGRAMAS_ES`
   - Clase CSS: `adc-programs-menu-trigger`

2. **Inglés:**
   - Texto del enlace: `PROGRAMAS_EN`
   - Clase CSS: `adc-programs-menu-trigger-en`

### Formulario de Búsqueda en Menú
1. **Español:**
   - Texto del enlace: `BUSCADOR_ES`
   - Clase CSS: `adc-search-menu-trigger`

2. **Inglés:**
   - Texto del enlace: `BUSCADOR_EN`
   - Clase CSS: `adc-search-menu-trigger-en`

## Configuración Recomendada

### Settings Principales
- **Videos por fila:** 4 (Desktop)
- **Caché:** Activado
- **Duración caché:** 6 horas
- **Autoplay:** Activado
- **Countdown autoplay:** 5 segundos

### API Configuration
- **URL Base:** `https://api.tutorah.tv/v1`
- **Token:** [Proporcionado por TuTorah]

## Comandos de Desarrollo

### Limpieza de Caché Manual
```bash
# Vía URL amigable
curl https://tuia.tv/cache/clear

# Vía webhook (requiere token)
curl "https://tuia.tv/wp-admin/admin-ajax.php?action=adc_webhook_refresh&token=TOKEN"
```

### Auto-commit Git
```bash
# El script auto-commit.sh ejecuta:
cd /home/customer/www/tuia.tv/public_html/wp-content/plugins/ADC
git add -A
git commit -m "Auto commit $(date '+%Y-%m-%d %H:%M:%S')"
git push github main
```

## Optimizaciones Implementadas

### Performance
- **Bulk operations** para evitar múltiples requests API
- **Caché unificado** con duración configurable
- **Lazy loading** de imágenes
- **Grid CSS** optimizado para diferentes tamaños

### SEO
- **URLs amigables** para todos los tipos de contenido
- **Meta tags** automáticos
- **Redirecciones 301** de URLs legacy
- **Canonical URLs** correctas

### UX/UI
- **Responsive design** completo
- **Loading states** informativos
- **Error handling** graceful con fallbacks
- **Coming Soon** para programas sin videos

## Troubleshooting

### Problemas Comunes

1. **Videos no cargan:**
   - Verificar configuración API en admin
   - Limpiar caché manualmente
   - Revisar logs en modo debug

2. **URLs no funcionan:**
   - Ir a Ajustes > Enlaces permanentes
   - Hacer clic en "Guardar cambios"
   - Verificar reglas de rewrite

3. **Menús no aparecen:**
   - Verificar clases CSS exactas
   - Confirmar configuración API
   - Revisar caché de menús

### Debug Mode
Activar en Admin > ADC Videos > Sistema de Caché > Modo Debug

**Características:**
- Logs detallados en consola del navegador
- Información de requests API
- Estados de caché y transients
- Debugging de redirects 404

## Versiones y Changelog

### v3.2 (Actual)
- URLs amigables implementadas
- Sistema de redirecciones 404 inteligente
- Webhook automático optimizado
- Panel de admin modernizado

### v3.1
- Sistema de caché unificado
- Bulk operations para mejor performance
- Fallback inteligente en búsquedas
- Mejoras en manejo de errores

### v3.0
- Soporte multiidioma (ES/EN)
- Refactoring completo de arquitectura
- Eliminación de código duplicado
- Nuevo sistema de utilidades

## Seguridad

### Características Implementadas
- **Nonce verification** en todos los AJAX calls
- **Token seguro** para webhook (32 caracteres)
- **Sanitización** completa de inputs
- **Escape** de outputs para prevenir XSS
- **Capability checks** para funciones admin

### Best Practices
- Never log or expose API tokens
- Validate all user inputs
- Use WordPress security functions
- Implement proper error handling

## Mantenimiento

### Tareas Regulares
1. **Monitoreo de caché** - Verificar estadísticas en admin
2. **Revisión de logs** - En caso de errores o performance issues  
3. **Actualizaciones API** - Coordinar con equipo TuTorah
4. **Backup de configuración** - Exportar settings antes de cambios

### Contacto y Soporte
- **Desarrollo:** TuTorah Development Team
- **Repositorio:** GitHub (configurado en auto-commit.sh)
- **Documentación:** Este archivo CLAUDE.md

---

*Documentación generada automáticamente para el Plugin ADC TuTorah v3.2*