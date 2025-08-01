# 🔧 PLAN DE ARREGLOS FINALES - Plugin TUIA
## 01 Agosto 2025 - Post Restauración SiteGround

### 📋 ESTADO ACTUAL DETECTADO

#### **❌ PROBLEMAS CONFIRMADOS QUE REGRESARON:**

1. **Error JavaScript #1: playbackRates vacío**
   ```javascript
   // LÍNEA PROBLEMÁTICA (ACTUAL):
   playbackRates: [],  // DESHABILITAR control nativo
   
   // SOLUCIÓN:
   // Remover esta línea completamente
   ```

2. **Error JavaScript #2: Comentario inválido**
   ```javascript
   // LÍNEA PROBLEMÁTICA (ACTUAL):
   // "playbackRateMenuButton",  // ELIMINAR control nativo
   
   // SOLUCIÓN: 
   // Remover esta línea completamente
   ```

3. **Error CSS: time() problemático**
   ```php
   // LÍNEA PROBLEMÁTICA (ACTUAL):
   '3.2.8-' . time()
   
   // SOLUCIÓN:
   '4.0'
   ```

4. **Preload problemático regresó**
   ```html
   <!-- LÍNEAS PROBLEMÁTICAS (ACTUAL): -->
   preload="auto"  // Aparece en 2 lugares
   
   <!-- SOLUCIÓN: -->
   preload="none"  // En ambos lugares
   ```

#### **✅ LO QUE FUNCIONA PERFECTO:**
- Control de velocidad personalizado (2x, 1.75x, 1.5x, 1.25x, 1x)
- Auto-advance entre videos (5 segundos)
- Diseño completo Elementor
- Plugin ADC funcional
- Colores TUIA (#6EC1E4) correctos

---

### 🎯 PLAN DE EJECUCIÓN DETALLADO

#### **ARCHIVO: adc-video-display.php**

**CAMBIO 1: Remover playbackRates vacío**
```javascript
// BUSCAR (línea ~1198):
                var player = videojs("adc-player", {
                    controls: true,
                    fluid: true,
                    responsive: true,
                    playbackRates: [],  // DESHABILITAR control nativo  ← REMOVER ESTA LÍNEA
                    language: "' . $this->language . '",

// REEMPLAZAR CON:
                var player = videojs("adc-player", {
                    controls: true,
                    fluid: true,
                    responsive: true,
                    language: "' . $this->language . '",
```

**CAMBIO 2: Remover comentario inválido**
```javascript
// BUSCAR (línea ~1205):
                            "customControlSpacer", 
                            // "playbackRateMenuButton",  // ELIMINAR control nativo  ← REMOVER ESTA LÍNEA
                            "chaptersButton", "descriptionsButton", "subsCapsButton",

// REEMPLAZAR CON:
                            "customControlSpacer", 
                            "chaptersButton", "descriptionsButton", "subsCapsButton",
```

**CAMBIO 3: Arreglar CSS time()**
```php
// BUSCAR (línea ~529):
            '3.2.8-' . time()

// REEMPLAZAR CON:
            '4.0'
```

**CAMBIO 4: Cambiar preload a none (1er lugar)**
```html
// BUSCAR (línea ~1163):
        $output .= '<video id="' . $clip_id . '" class="video-js vjs-default-skin vjs-big-play-centered" controls playsinline preload="auto" style="position:absolute; top:0; left:0; width:100%; height:100%;">';

// REEMPLAZAR CON:
        $output .= '<video id="' . $clip_id . '" class="video-js vjs-default-skin vjs-big-play-centered" controls playsinline preload="none" style="position:absolute; top:0; left:0; width:100%; height:100%;">';
```

**CAMBIO 5: Cambiar preload a none (2do lugar)**
```html
// BUSCAR (línea ~1257):
        $output .= '<video id="adc-player" class="video-js vjs-default-skin vjs-big-play-centered" controls playsinline preload="auto" style="position:absolute; top:0; left:0; width:100%; height:100%;">';

// REEMPLAZAR CON:
        $output .= '<video id="adc-player" class="video-js vjs-default-skin vjs-big-play-centered" controls playsinline preload="none" style="position:absolute; top:0; left:0; width:100%; height:100%;">';
```

---

### 🔍 VERIFICACIONES POST-CAMBIOS

#### **PASO 1: Verificar sintaxis JavaScript**
```bash
# Buscar errores de sintaxis:
ssh tuia.tv "grep -A20 -B5 'controlBar.*children' /path/to/adc-video-display.php"
```

#### **PASO 2: Verificar preload cambió**
```bash
# Confirmar que no hay preload="auto":
ssh tuia.tv "grep 'preload=' /path/to/adc-video-display.php"
# Debe mostrar solo: preload="none"
```

#### **PASO 3: Verificar CSS sin time()**
```bash
# Confirmar que no hay time():
ssh tuia.tv "grep 'time()' /path/to/adc-video-display.php"
# No debe retornar nada
```

#### **PASO 4: Probar funcionalidad**
1. ✅ Control de velocidad sigue funcionando
2. ✅ Video se reproduce sin autoplay automático
3. ✅ Auto-advance entre videos funciona (5 seg)
4. ✅ Sin errores JavaScript en consola
5. ✅ CSS carga una sola versión

---

### 📊 RESULTADOS ESPERADOS

#### **ANTES (PROBLEMÁTICO):**
```
- playbackRates: [] → Error JavaScript
- // "playbackRateMenuButton" → Sintaxis inválida
- '3.2.8-' . time() → CSS duplicado
- preload="auto" → Autoplay indeseado
- JavaScript errors en consola
```

#### **DESPUÉS (ARREGLADO):**
```
- Sin playbackRates → Video.js inicializa bien
- Sin comentarios inválidos → Sin errores sintaxis
- '4.0' → CSS versión fija, sin duplicados
- preload="none" → Sin autoplay automático
- Control velocidad funciona perfecto
```

---

### 🚨 PRINCIPIOS DE SEGURIDAD

#### **ANTES DE CADA CAMBIO:**
```bash
# 1. Backup git
git add . && git commit -m "BACKUP antes de arreglos finales"
git tag backup-antes-arreglos-$(date +%Y%m%d-%H%M)

# 2. Backup archivo específico
cp adc-video-display.php adc-video-display.php.backup-$(date +%Y%m%d-%H%M)
```

#### **DESPUÉS DE CADA CAMBIO:**
1. ✅ **Verificar sitio carga** (curl -s https://tuia.tv/)
2. ✅ **Probar un video** en el sitio
3. ✅ **Verificar consola JavaScript** sin errores
4. ✅ **Confirmar control velocidad funciona**

#### **SI ALGO SALE MAL:**
```bash
# Restaurar desde backup local
cp adc-video-display.php.backup-YYYYMMDD-HHMM adc-video-display.php

# Subir al servidor
scp adc-video-display.php user@server:/path/
```

---

### 📝 REGISTRO DE CAMBIOS

#### **CAMBIO 1: ✅/❌ Remover playbackRates**
- **Línea modificada:** ~1198
- **Status:** [PENDIENTE]
- **Verificado:** [PENDIENTE]

#### **CAMBIO 2: ✅/❌ Remover comentario inválido**
- **Línea modificada:** ~1205  
- **Status:** [PENDIENTE]
- **Verificado:** [PENDIENTE]

#### **CAMBIO 3: ✅/❌ Arreglar CSS time()**
- **Línea modificada:** ~529
- **Status:** [PENDIENTE] 
- **Verificado:** [PENDIENTE]

#### **CAMBIO 4: ✅/❌ Preload none (lugar 1)**
- **Línea modificada:** ~1163
- **Status:** [PENDIENTE]
- **Verificado:** [PENDIENTE]

#### **CAMBIO 5: ✅/❌ Preload none (lugar 2)**
- **Línea modificada:** ~1257
- **Status:** [PENDIENTE]
- **Verificado:** [PENDIENTE]

---

### 🎯 OBJETIVO FINAL

**UN SOLO ARCHIVO MODIFICADO:** `adc-video-display.php`
**CINCO CAMBIOS MÍNIMOS Y SEGUROS**
**RESULTADO:** Plugin funcionando perfectamente sin errores

---

**LISTO PARA EJECUTAR** ✅
**DOCUMENTADO COMPLETAMENTE** ✅
**PLAN DE ROLLBACK DEFINIDO** ✅