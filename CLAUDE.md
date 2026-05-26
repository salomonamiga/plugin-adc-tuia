# 🔌 Plugin ADC TUIA - WordPress Integration

## 📋 DESCRIPCIÓN
Plugin de WordPress que integra el sistema ADC con el sitio TUIA.tv, mostrando videos de TuTorah con soporte multiidioma (ES / EN / PT) y URLs amigables.

## 🚨🚨🚨 CRÍTICO: NOMBRE DEL PLUGIN EN SERVIDOR 🚨🚨🚨

**EL PLUGIN ACTIVO EN PRODUCCIÓN SE LLAMA `ADC-Radiant`, NO `ADC`**.

```
/home/customer/www/tuia.tv/public_html/wp-content/plugins/ADC-Radiant/   ← ESTE es el activo
/home/customer/www/tuia.tv/public_html/wp-content/plugins/ADC-Radiant/           ← INACTIVO (ignorar)
```

Verificable con:
```bash
ssh ... "wp plugin list --path=/home/customer/www/tuia.tv/public_html/ | grep -i adc"
# ADC-Radiant   active     5.1.3
# ADC           inactive   5.0.0
```

**SIEMPRE deployar a `ADC-Radiant/`**. Si subes a `ADC/` no pasa nada (porque está inactivo) y vas a perder horas debuggeando por qué los cambios no se ven. **Este error ya pasó el 2026-05-26**, por eso este warning.

## 🔐 CREDENCIALES Y SERVIDOR

### Ubicación Credenciales
**Archivo:** `/Users/mac/Dev/TuTorah/.tutorah-config/credentials/ssh/SSH_TUIA.md`

### Servidor
- **Host:** `ssh.tuia.tv`
- **Puerto:** `18765`
- **Usuario:** `u2551-h32rfkbi0mar`
- **Autenticación:** Clave SSH (NO contraseña)
- **Clave SSH (Mac Personal):** `~/.ssh/id_ed25519_tuia`
- **Clave SSH (Mac Oficina):** `~/.ssh/id_ed25519_tuia_mac_oficina`

### Path Remoto
- **Directorio web:** `/home/customer/www/tuia.tv/public_html/`
- **Plugin path:** `/home/customer/www/tuia.tv/public_html/wp-content/plugins/ADC-Radiant/` ← **OJO: ADC-Radiant, NO ADC**
- **URL Sitio:** `https://tuia.tv/`

## 💻 STACK TECNOLÓGICO

- **CMS:** WordPress 6.x
- **Lenguaje:** PHP 7.4+
- **Frontend:** JavaScript (ES6), CSS3
- **API:** API TuTorah REST (`https://api.tutorah.tv/v2`)
- **Arquitectura:** WordPress Plugin (OOP)
- **Caché:** WordPress Transients + Object Cache
- **Multiidioma:** Español / Inglés / Portugués integrado

## 📁 ESTRUCTURA DEL PLUGIN

```
ADC/
├── adc-video-display.php   # Plugin principal (clase base)
├── adc-api.php              # Comunicación con API TuTorah
├── adc-admin.php            # Panel de administración WordPress
├── adc-menu.php             # Menús desplegables dinámicos
├── adc-search.php           # Sistema de búsqueda
├── adc-utils.php            # Utilidades compartidas
├── script.js                # JavaScript frontend
├── style.css                # Estilos CSS
├── auto-commit.sh           # Script de auto-commit a GitHub
├── DOCUMENTATION.md         # Documentación técnica completa
└── CLAUDE.md               # Este archivo (instrucciones operacionales)
```

## 🔄 FLUJO DE TRABAJO

### 1. Antes de Trabajar
```bash
cd /Users/mac/Dev/TuTorah/repos/plugin-tuia/
git pull
```

### 2. Desarrollo Local
**IMPORTANTE:** El plugin se desarrolla localmente y se prueba en el servidor.

**Workflow recomendado:**
1. Modificar archivos localmente en `/Users/mac/Dev/TuTorah/repos/plugin-tuia/`
2. Subir al servidor para testing
3. Probar en `https://tuia.tv/`
4. Si funciona, commit y push a GitHub

### 3. Subir Plugin al Servidor

#### Opción A: Subir archivo específico
```bash
# Desde Mac Personal
scp -i ~/.ssh/id_ed25519_tuia -P 18765 \
  adc-video-display.php \
  u2551-h32rfkbi0mar@ssh.tuia.tv:/home/customer/www/tuia.tv/public_html/wp-content/plugins/ADC-Radiant/

# Desde Mac Oficina
scp -i ~/.ssh/id_ed25519_tuia_mac_oficina -P 18765 \
  adc-video-display.php \
  u2551-h32rfkbi0mar@ssh.tuia.tv:/home/customer/www/tuia.tv/public_html/wp-content/plugins/ADC-Radiant/
```

#### Opción B: Subir plugin completo
```bash
# Desde Mac Personal
rsync -avz --exclude='.git' --exclude='*.log' --exclude='*.backup*' \
  -e "ssh -i ~/.ssh/id_ed25519_tuia -p 18765" \
  ./ u2551-h32rfkbi0mar@ssh.tuia.tv:/home/customer/www/tuia.tv/public_html/wp-content/plugins/ADC-Radiant/

# Desde Mac Oficina
rsync -avz --exclude='.git' --exclude='*.log' --exclude='*.backup*' \
  -e "ssh -i ~/.ssh/id_ed25519_tuia_mac_oficina -p 18765" \
  ./ u2551-h32rfkbi0mar@ssh.tuia.tv:/home/customer/www/tuia.tv/public_html/wp-content/plugins/ADC-Radiant/
```

### 4. Probar en TUIA.tv
- **Frontend:** `https://tuia.tv/programa/nombre-programa/`
- **Admin:** `https://tuia.tv/wp-admin/admin.php?page=adc-videos`
- **Limpiar caché:** `https://tuia.tv/cache/clear`

### 5. Finalizar (Git)
```bash
git add .
git commit -m "Descripción del cambio en plugin"
git push
```

## ⚡ COMANDOS RÁPIDOS

### Conectar al servidor
```bash
# Desde Mac Personal
ssh -i ~/.ssh/id_ed25519_tuia -p 18765 u2551-h32rfkbi0mar@ssh.tuia.tv

# Desde Mac Oficina
ssh -i ~/.ssh/id_ed25519_tuia_mac_oficina -p 18765 u2551-h32rfkbi0mar@ssh.tuia.tv
```

### Ver logs de WordPress
```bash
# Desde Mac Personal
ssh -i ~/.ssh/id_ed25519_tuia -p 18765 u2551-h32rfkbi0mar@ssh.tuia.tv \
  "tail -f /home/customer/www/tuia.tv/public_html/wp-content/debug.log"

# Desde Mac Oficina
ssh -i ~/.ssh/id_ed25519_tuia_mac_oficina -p 18765 u2551-h32rfkbi0mar@ssh.tuia.tv \
  "tail -f /home/customer/www/tuia.tv/public_html/wp-content/debug.log"
```

### Limpiar caché del plugin
```bash
# Vía URL
curl https://tuia.tv/cache/clear

# Vía webhook (con token del admin)
curl "https://tuia.tv/wp-admin/admin-ajax.php?action=adc_webhook_refresh&token=TOKEN"

# Vía SSH
ssh -i ~/.ssh/id_ed25519_tuia_mac_oficina -p 18765 u2551-h32rfkbi0mar@ssh.tuia.tv \
  "wp transient delete --all --path=/home/customer/www/tuia.tv/public_html/"
```

### Verificar estado del plugin
```bash
# Listar plugins instalados
ssh -i ~/.ssh/id_ed25519_tuia_mac_oficina -p 18765 u2551-h32rfkbi0mar@ssh.tuia.tv \
  "wp plugin list --path=/home/customer/www/tuia.tv/public_html/"

# Ver info del plugin ADC
ssh -i ~/.ssh/id_ed25519_tuia_mac_oficina -p 18765 u2551-h32rfkbi0mar@ssh.tuia.tv \
  "wp plugin get ADC --path=/home/customer/www/tuia.tv/public_html/"
```

### Backup del plugin
```bash
# Backup completo
rsync -avz --exclude='.git' \
  -e "ssh -i ~/.ssh/id_ed25519_tuia_mac_oficina -p 18765" \
  u2551-h32rfkbi0mar@ssh.tuia.tv:/home/customer/www/tuia.tv/public_html/wp-content/plugins/ADC-Radiant/ \
  ~/Dev/_backups/Plugin_TUIA_backup_$(date +%Y%m%d_%H%M%S)/
```

## 🚨 REGLAS IMPORTANTES

1. **NUNCA usar `sshpass`** - Las claves SSH NO tienen contraseña
2. **SIEMPRE probar** cambios en TUIA.tv antes de commit
3. **LIMPIAR caché** después de subir cambios
4. **BACKUP antes** de cambios importantes
5. **NO modificar** el plugin desde WordPress admin (solo vía SSH/SFTP)
6. **DOCUMENTAR** cambios importantes en DOCUMENTATION.md
7. **VERSIONAR** - Actualizar versión en header del plugin principal

## 📝 CONFIGURACIÓN DEL PLUGIN

### Settings en WordPress Admin
**Ubicación:** `WP Admin > ADC Videos > Configuración`

**Configuración recomendada:**
- **API URL:** `https://api.tutorah.tv/v2`
- **API Token:** `[Ver en .tutorah-config/credentials/apis/]`
- **Caché:** Activado
- **Duración caché:** 6 horas
- **Videos por fila:** 4
- **Autoplay:** Activado (5 seg countdown)
- **Debug mode:** Desactivado (activar solo para troubleshooting)

### Webhook Automático
**URL configurada:** `https://tuia.tv/wp-admin/admin-ajax.php?action=adc_webhook_refresh&token=[TOKEN]`

**Uso:** ADC llama este webhook cuando actualiza contenido para limpiar caché automáticamente.

## 🔌 SHORTCODES DISPONIBLES

```php
// Contenido principal
[adc_content]      // Español
[adc_content_en]   // Inglés
[adc_content_pt]   // Portugués

// Menús de programas
[adc_programs_menu text="PROGRAMAS"]        // Español
[adc_programs_menu_en text="PROGRAMS"]      // Inglés
[adc_programs_menu_pt text="PROGRAMAS"]     // Portugués

// Búsqueda
[adc_search_form]     // Español
[adc_search_form_en]  // Inglés
[adc_search_form_pt]  // Portugués
```

### URLs friendly por idioma

| Idioma | Home | Programa | Video | Búsqueda |
|--------|------|----------|-------|----------|
| ES | `/` | `/programa/<slug>/` | `/programa/<slug>/<video>/` | `/buscar/<termino>/` |
| EN | `/en/` | `/en/program/<slug>/` | `/en/program/<slug>/<video>/` | `/en/search/<termino>/` |
| PT | `/pt/` | `/pt/programa/<slug>/` | `/pt/programa/<slug>/<video>/` | `/pt/buscar/<termino>/` |

Ver más en `DOCUMENTATION.md`

## ❌ NUNCA HACER

- Desactivar el plugin desde WordPress (desconecta todo el sitio)
- Modificar archivos desde WordPress File Editor
- Eliminar caché sin avisar (afecta performance)
- Subir sin probar (puede romper el sitio)
- Ignorar errores de PHP
- Exponer API token en código JavaScript

## 🆘 SOLUCIÓN RÁPIDA DE PROBLEMAS

### Videos no cargan
1. Verificar configuración API en `WP Admin > ADC Videos`
2. Limpiar caché: `https://tuia.tv/cache/clear`
3. Revisar logs: `wp-content/debug.log`
4. Verificar que API responda: `curl https://api.tutorah.tv/v2/health`

### URLs no funcionan (404)
1. Ir a `WP Admin > Ajustes > Enlaces permanentes`
2. Hacer clic en "Guardar cambios" (flush rewrite rules)
3. Verificar .htaccess tiene reglas de WordPress
4. Revisar en navegador modo incógnito

### Caché no se limpia
1. Verificar token del webhook es correcto
2. Revisar logs de debug del plugin
3. Limpiar manualmente vía SSH: `wp transient delete --all`
4. Verificar Object Cache funciona (si usa Redis/Memcached)

### Plugin no aparece en admin
1. Verificar permisos: `chmod 755` en directorio, `644` en archivos
2. Verificar sintaxis PHP: `php -l adc-video-display.php`
3. Activar WP_DEBUG en wp-config.php
4. Revisar logs de error de PHP

## 📚 DOCUMENTACIÓN ADICIONAL

- **Documentación técnica completa:** `DOCUMENTATION.md`
- **API TuTorah:** Ver repo `API_Tutorah/CLAUDE.md`
- **ADC System:** Ver repo `ADC/CLAUDE.md`
- **WordPress Codex:** https://developer.wordpress.org/

## 🔄 INTEGRACIÓN

El plugin se integra con:
- **API TuTorah** - Consume endpoints de videos/programas
- **ADC** - Recibe webhook de actualizaciones
- **WordPress** - Hooks, shortcodes, admin
- **Theme TUIA** - Estilos y layouts personalizados

---

## 🌐 Soporte multiidioma (ES / EN / PT)

El plugin soporta 3 idiomas con secciones aisladas:

| Idioma | Slug URL | Sección BD | Endpoint API | Sufijo imagen |
|--------|----------|-----------|--------------|---------------|
| Español | `/` | 5 (IA) | `/v2/ia/categories` | `_ia.png` |
| Inglés | `/en/` | 6 (IA_en) | `/v2/ia_en/categories` | `_ia_en.png` |
| Portugués | `/pt/` | 7 (IA_pt) | `/v2/ia_pt/categories` | `_ia_pt.png` |

**Nombres de categoría:**
- ES → columna `categorias.nombre`
- EN → comparte `categorias.nombre` (no hay traducción de nombres a inglés)
- PT → columna **`categorias.nombreIA_pt`** (sí tiene traducción propia)

**Cache buster:** El plugin enqueue scripts/styles con `?ver=<version>`. SiteGround cachea estos archivos 10 años en el navegador del usuario. Al hacer cambios en `script.js` / `style.css`, **siempre bumpea la versión** en `adc-video-display.php`:
- Header `Version: X.Y.Z`
- 3 llamadas `wp_enqueue_*` con `'X.Y.Z'`

---

**Última actualización:** 2026-05-26
**Versión plugin:** 5.1.3 (con soporte PT completo)
**Repositorio:** https://github.com/salomonamiga/plugin-adc-tuia.git
**Mantenedor:** Salomón Amiga
