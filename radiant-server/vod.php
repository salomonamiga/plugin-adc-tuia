<?php
/**
 * Radiant Media Player endpoint para TUIA.tv
 *
 * Adaptado de TuTorah.tv (commit b1afd02 + e88ff5b + 8de4f0a + 201dafc).
 * Diferencia clave: TUIA NO usa BD compartida — todos los datos del video
 * llegan por query string desde el plugin WordPress (ADC Video Display Radiant).
 *
 * Parametros esperados:
 *   hls         URL HLS completa del video (obligatorio)
 *   title       Titulo del video
 *   poster      URL del poster/thumbnail
 *   next_url    URL friendly del siguiente video (opcional)
 *   next_title  Titulo del siguiente video (opcional)
 *   next_thumb  Thumbnail del siguiente video (opcional)
 *
 * El postMessage origin esperado es https://tuia.tv (donde vive el plugin).
 *
 * Settings: identicos a tutorah.tv. Licencia RMP `d3NhYnFqZGpsZUAxNTgwNzY0`
 * ya esta activa para tuia.tv. gaTrackingId G-GZJQ7CQEW7 es el tag puente
 * intencional a propiedad General GA4 331482565 (TUIA tambien manda hits
 * a su propiedad propia via Site Kit cargado en el HTML padre, no aqui).
 */

$hls_url    = isset($_GET["hls"])        ? $_GET["hls"]        : '';
$title      = isset($_GET["title"])      ? $_GET["title"]      : '';
$poster_url = isset($_GET["poster"])     ? $_GET["poster"]     : '';
$next_url   = isset($_GET["next_url"])   ? $_GET["next_url"]   : '';
$next_title = isset($_GET["next_title"]) ? $_GET["next_title"] : '';
$next_thumb = isset($_GET["next_thumb"]) ? $_GET["next_thumb"] : '';

// Fallback poster — logo TUIA del header del sitio
if (empty($poster_url)) {
    $poster_url = 'https://tuia.tv/wp-content/uploads/2023/06/logo-tuia-blanco.png';
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,minimum-scale=1,initial-scale=1">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>

  <style>
      html, body {
        height: 100%;
        width: 100%;
        overflow: hidden;
        margin: 0;
        padding: 0;
      }

      /* Fullscreen support para movil y desktop */
      #rmpPlayer:fullscreen,
      #rmpPlayer:-webkit-full-screen,
      #rmpPlayer:-moz-full-screen,
      #rmpPlayer:-ms-fullscreen {
        width: 100vw !important;
        height: 100vh !important;
      }

      #rmpPlayer:fullscreen *,
      #rmpPlayer:-webkit-full-screen *,
      #rmpPlayer:-moz-full-screen *,
      #rmpPlayer:-ms-fullscreen * {
        max-width: 100vw !important;
        max-height: 100vh !important;
      }

      #rmpPlayer:fullscreen video,
      #rmpPlayer:-webkit-full-screen video,
      #rmpPlayer:-moz-full-screen video,
      #rmpPlayer:-ms-fullscreen video {
        width: 100% !important;
        height: 100% !important;
        max-width: 100vw !important;
        max-height: 100vh !important;
        object-fit: contain !important;
        position: absolute !important;
        top: 0 !important;
        left: 0 !important;
      }

      /* ============================================================
         Overlay "A continuacion" estilo Netflix end-screen
         ============================================================ */
      #rmpNextOverlay {
        --accent: #82b1ff;
        --overlay-bg: rgba(20, 20, 20, 0.92);
        --overlay-border: rgba(255, 255, 255, 0.08);
        --thumb-fallback: linear-gradient(135deg, #2a2a2a, #1a1a1a);
        --text-primary: #ffffff;
        --text-secondary: #cccccc;
        --btn-secondary-border: rgba(255, 255, 255, 0.2);

        position: absolute;
        bottom: 24px;
        right: 16px;
        width: 380px;
        max-width: calc(100% - 32px);
        padding: 12px;
        background: var(--overlay-bg);
        border: 1px solid var(--overlay-border);
        border-radius: 12px;
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.6);
        color: var(--text-primary);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        z-index: 10;
        box-sizing: border-box;
        opacity: 0;
        transform: translateY(20px);
        pointer-events: none;
        transition: opacity 0.3s ease-out, transform 0.3s ease-out;
      }
      @supports (backdrop-filter: blur(20px)) or (-webkit-backdrop-filter: blur(20px)) {
        #rmpNextOverlay {
          -webkit-backdrop-filter: blur(20px);
          backdrop-filter: blur(20px);
        }
      }
      #rmpNextOverlay.show {
        opacity: 1;
        transform: translateY(0);
        pointer-events: auto;
      }
      #rmpNextOverlay.hiding {
        opacity: 0;
        transform: translateY(20px);
        transition: opacity 0.2s ease-in, transform 0.2s ease-in;
        pointer-events: none;
      }

      #rmpNextOverlay .rmp-next-label {
        color: var(--accent);
        text-transform: uppercase;
        font-weight: 600;
        font-size: 11px;
        letter-spacing: 1.5px;
        margin: 0 0 12px 0;
      }

      #rmpNextOverlay .rmp-next-thumb-wrap {
        position: relative;
        width: 100%;
        aspect-ratio: 21 / 9;
        margin-bottom: 10px;
        border-radius: 8px;
        overflow: hidden;
        background: var(--thumb-fallback);
      }
      #rmpNextOverlay .rmp-next-thumb {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
      }

      #rmpNextOverlay .rmp-next-countdown-circle {
        position: absolute;
        top: 12px;
        right: 12px;
        width: 56px;
        height: 56px;
        background: rgba(0, 0, 0, 0.75);
        border: 2px solid var(--accent);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        font-weight: 700;
        color: var(--text-primary);
        line-height: 1;
      }

      #rmpNextOverlay .rmp-next-title {
        color: var(--text-primary);
        font-size: 17px;
        font-weight: 700;
        line-height: 1.3;
        margin: 0 0 12px 0;
        overflow: hidden;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        text-overflow: ellipsis;
        word-break: break-word;
      }

      #rmpNextOverlay .rmp-next-actions {
        display: flex;
        gap: 10px;
      }
      #rmpNextOverlay button {
        font-family: inherit;
        cursor: pointer;
        border-radius: 8px;
        font-size: 14px;
        transition: background 0.2s, color 0.2s, border-color 0.2s;
      }
      #rmpNextOverlay button.rmp-next-play-now {
        flex: 1;
        background: var(--accent);
        color: #000;
        border: none;
        padding: 12px 16px;
        font-weight: 600;
      }
      #rmpNextOverlay button.rmp-next-play-now:hover {
        background: var(--text-primary);
      }
      #rmpNextOverlay button.rmp-next-cancel {
        flex: 0 0 auto;
        background: transparent;
        color: var(--text-secondary);
        border: 1px solid var(--btn-secondary-border);
        padding: 12px 18px;
        font-weight: 500;
      }
      #rmpNextOverlay button.rmp-next-cancel:hover {
        border-color: rgba(255, 255, 255, 0.5);
        color: var(--text-primary);
      }

      /* Tablet (max-width 768px) */
      @media (max-width: 768px) {
        #rmpNextOverlay {
          width: 360px;
          right: 12px;
          bottom: 16px;
        }
      }

      /* Mobile (max-width 600px) */
      @media (max-width: 600px) {
        #rmpNextOverlay {
          width: auto;
          max-width: none;
          left: 12px;
          right: 12px;
          bottom: 12px;
        }
      }

      /* Mobile chico (max-width 480px) — proporciones reducidas para
         iframes muy bajos. Mantiene thumbnail visible (no se esconde). */
      @media (max-width: 480px) {
        #rmpNextOverlay {
          padding: 10px;
          bottom: 8px;
        }
        #rmpNextOverlay .rmp-next-label {
          font-size: 9px;
          margin-bottom: 6px;
          letter-spacing: 1.2px;
        }
        #rmpNextOverlay .rmp-next-thumb-wrap {
          margin-bottom: 8px;
        }
        #rmpNextOverlay .rmp-next-countdown-circle {
          width: 36px;
          height: 36px;
          font-size: 15px;
          top: 8px;
          right: 8px;
        }
        #rmpNextOverlay .rmp-next-title {
          font-size: 14px;
          margin-bottom: 10px;
          -webkit-line-clamp: 2;
        }
        #rmpNextOverlay button {
          font-size: 12px;
          padding: 8px 12px;
        }
      }

      /* Mobile chico extremo (max-width 380px) — botones stack vertical */
      @media (max-width: 380px) {
        #rmpNextOverlay .rmp-next-actions {
          flex-direction: column;
          gap: 6px;
        }
        #rmpNextOverlay button.rmp-next-play-now,
        #rmpNextOverlay button.rmp-next-cancel {
          flex: 1 1 auto;
          width: 100%;
        }
      }

      /* Mobile vertical extremo (portrait con poco alto) — sin thumbnail.
         Patron YouTube: en player muy bajo el thumbnail no cabe. Mantenemos
         label, titulo, countdown y botones. Reglas separadas por
         incompatibilidad de orientation+max-height. */
      @media (max-width: 480px) and (orientation: portrait) {
        #rmpNextOverlay {
          padding-top: 30px;
        }
        #rmpNextOverlay .rmp-next-thumb-wrap {
          display: contents;
        }
        #rmpNextOverlay .rmp-next-thumb {
          display: none;
        }
        #rmpNextOverlay .rmp-next-countdown-circle {
          position: absolute;
          top: 8px;
          right: 8px;
          width: 30px;
          height: 30px;
          font-size: 13px;
          border-width: 1.5px;
        }
        #rmpNextOverlay .rmp-next-label {
          padding-right: 40px;
        }
      }
      @media (max-width: 480px) and (max-height: 250px) {
        #rmpNextOverlay {
          padding-top: 30px;
        }
        #rmpNextOverlay .rmp-next-thumb-wrap {
          display: contents;
        }
        #rmpNextOverlay .rmp-next-thumb {
          display: none;
        }
        #rmpNextOverlay .rmp-next-countdown-circle {
          position: absolute;
          top: 8px;
          right: 8px;
          width: 30px;
          height: 30px;
          font-size: 13px;
          border-width: 1.5px;
        }
        #rmpNextOverlay .rmp-next-label {
          padding-right: 40px;
        }
      }

      /* ============================================================
         Fullscreen — el overlay debe verse por encima de todo lo
         que RMP pone en fullscreen. z-index alto + posicion correcta.
         Reglas separadas porque CSS descarta TODA la lista si UN
         selector con prefijo vendor es invalido en el browser. */
      #rmpPlayer:fullscreen #rmpNextOverlay {
        z-index: 2147483647 !important;
        max-width: 380px !important;
        max-height: none !important;
      }
      #rmpPlayer:-webkit-full-screen #rmpNextOverlay {
        z-index: 2147483647 !important;
        max-width: 380px !important;
        max-height: none !important;
      }
      #rmpPlayer:-moz-full-screen #rmpNextOverlay {
        z-index: 2147483647 !important;
        max-width: 380px !important;
        max-height: none !important;
      }
      #rmpPlayer:fullscreen #rmpNextOverlay .rmp-next-thumb-wrap {
        max-height: none !important;
      }
      #rmpPlayer:-webkit-full-screen #rmpNextOverlay .rmp-next-thumb-wrap {
        max-height: none !important;
      }
      #rmpPlayer:fullscreen #rmpNextOverlay .rmp-next-thumb {
        max-width: 100% !important;
        max-height: 100% !important;
      }
      #rmpPlayer:-webkit-full-screen #rmpNextOverlay .rmp-next-thumb {
        max-width: 100% !important;
        max-height: 100% !important;
      }

    </style>
</head>
<body>
  <div id="rmpPlayer">
    <?php if (!empty($next_url)): ?>
    <div id="rmpNextOverlay" role="dialog" aria-label="Siguiente video">
      <div class="rmp-next-label">A continuación</div>
      <div class="rmp-next-thumb-wrap">
        <?php if (!empty($next_thumb)): ?>
          <img class="rmp-next-thumb" src="<?= htmlspecialchars($next_thumb, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy" onerror="this.style.display='none'">
        <?php endif; ?>
        <div class="rmp-next-countdown-circle" aria-live="polite"><span id="rmpNextSeconds">10</span></div>
      </div>
      <div class="rmp-next-title"><?= htmlspecialchars($next_title, ENT_QUOTES, 'UTF-8') ?></div>
      <div class="rmp-next-actions">
        <button type="button" class="rmp-next-play-now" id="rmpNextPlayNow">Reproducir ahora</button>
        <button type="button" class="rmp-next-cancel" id="rmpNextCancel">Cancelar</button>
      </div>
    </div>
    <?php endif; ?>
  </div>
  <script>
    var src = {
      hls: <?= json_encode($hls_url) ?>,
      contentTitle: <?= json_encode($title) ?>
    };

    var labels = {
      ads: {
        controlBarCustomMessage: 'Ad',
        skipMessage: 'Skip ad',
        skipWaitingMessage: 'Skip ad in',
        textForClickUIOnMobile: 'Learn more',
        textForClickUI: 'Learn more'
      }
    };

    var settings = {
      licenseKey: 'd3NhYnFqZGpsZUAxNTgwNzY0',
      src: src,
      contentMetadata: {
        poster: [<?= json_encode($poster_url) ?>]
      },
      autoHeightMode: false,
      detectAutoplayCapabilities: true,
      autoplay: false,
      pip: true,
      muted: false,
      initialVolume: 0.5,
      forceInitialVolume: true,
      airplay: true,
      googleCast: true,
      quickRewind: 10,
      quickForward: 10,
      capLevelToPlayerSize: false,
      forceHlsJSOnMacOSSafari: true,
      forceHlsJSOnIpadOS: true,
      speed: true,
      speedRates: [0.25, 0.5, 0.75, 1, 1.25, 1.5, 1.75, 2],
      iframeMode: true,
      iframeAllowed: true,
      gaTrackingId: 'G-GZJQ7CQEW7',
      gaCategory: 'TUIA',
      gaLabel: 'VOD',
      gaEvents: ['context', 'ready', 'playerstart', 'error', 'adimpression', 'adloadererror', 'aderror'],
      sharing: false,
      preload: 'auto',
      // Settings de Osher (Multix) — buffer y robustez en red lenta
      hlsJSMaxBufferAhead: 60,
      hlsJSMaxBufferBehind: 30,
      hlsJSCustomConfig: {
        startLevel: -1,
        abrEwmaDefaultEstimate: 1000000,
        abrEwmaFastVoD: 3.0,
        abrEwmaSlowVoD: 9.0,
        manifestLoadingTimeOut: 15000,
        manifestLoadingMaxRetry: 4,
        manifestLoadingRetryDelay: 1000,
        levelLoadingTimeOut: 15000,
        levelLoadingMaxRetry: 4,
        levelLoadingRetryDelay: 1000,
        fragLoadingTimeOut: 20000,
        fragLoadingMaxRetry: 6,
        fragLoadingRetryDelay: 1000
      },
      // Sin pre-rolls — TUIA no tiene GAM activo
      ads: false,
      labels: labels,
      skin: 's2',
      asyncElementID: 'rmpPlayer'
    };

    if (typeof window.rmpAsyncPlayers === 'undefined') {
      window.rmpAsyncPlayers = [];
    }
    window.rmpAsyncPlayers.push(settings);
  </script>
  <script async src="https://cdn.radiantmediatechs.com/rmp/8.4.10/js/rmp.min.js"></script>

  <?php if (!empty($next_url)): ?>
  <script>
  (function() {
    var nextUrl = <?= json_encode($next_url) ?>;
    var PARENT_ORIGIN = 'https://tuia.tv';
    // Timing: overlay aparece cuando faltan 5s del final, countdown
    // empieza en 10 y decrementa cada 1000ms. A los 5s el video termina
    // naturalmente y el frame queda congelado; el countdown sigue de
    // 5 → 0 sin reiniciar. Cuando llega a 0, redirect.
    // El countdown se pausa/reanuda junto con el video durante la
    // reproduccion normal (no afecta el tramo post-ended).
    var COUNTDOWN_FROM = 10;
    var TRIGGER_REMAINING = 5;
    var overlay = document.getElementById('rmpNextOverlay');
    var secondsEl = document.getElementById('rmpNextSeconds');
    var btnCancel = document.getElementById('rmpNextCancel');
    var btnPlayNow = document.getElementById('rmpNextPlayNow');
    if (!overlay || !secondsEl) return;

    var inst = null;
    var cancelled = false;
    var redirecting = false;
    var countdownInterval = null;
    var secondsLeft = COUNTDOWN_FROM;
    var shown = false;

    function gotoNext() {
      if (redirecting) return;
      redirecting = true;
      stopCountdown();
      // Si el iframe esta embebido, parent !== window y postMessage redirige al padre.
      // Si es standalone (acceso directo), parent === window: redirect local.
      try {
        if (window.parent && window.parent !== window) {
          window.parent.postMessage({ action: 'goto-next', url: nextUrl }, PARENT_ORIGIN);
        } else {
          window.location.href = nextUrl;
        }
      } catch (err) {
        window.location.href = nextUrl;
      }
    }

    function stopCountdown() {
      if (countdownInterval) {
        clearInterval(countdownInterval);
        countdownInterval = null;
      }
    }

    function tickCountdown() {
      secondsLeft--;
      if (secondsLeft <= 0) {
        secondsEl.textContent = 0;
        gotoNext();
        return;
      }
      secondsEl.textContent = secondsLeft;
    }

    function startCountdownTicker() {
      if (countdownInterval || cancelled || redirecting) return;
      countdownInterval = setInterval(tickCountdown, 1000);
    }

    function showOverlay(initialSeconds) {
      if (cancelled || redirecting || shown) return;
      shown = true;
      secondsLeft = initialSeconds;
      secondsEl.textContent = secondsLeft;
      overlay.classList.add('show');
      startCountdownTicker();
    }

    function hideOverlay() {
      cancelled = true;
      stopCountdown();
      overlay.classList.add('hiding');
      overlay.classList.remove('show');
      setTimeout(function() { overlay.classList.remove('hiding'); }, 250);
    }

    btnCancel.addEventListener('click', hideOverlay);
    btnPlayNow.addEventListener('click', gotoNext);

    function tryAttach() {
      if (window.rmpAsyncPlayerInstances && window.rmpAsyncPlayerInstances[0]) {
        inst = window.rmpAsyncPlayerInstances[0];
        attachPlayerEvents();
      } else {
        setTimeout(tryAttach, 200);
      }
    }

    function onTimeUpdate() {
      if (cancelled || redirecting || shown || !inst) return;
      var dur = inst.getDuration ? inst.getDuration() : 0;
      var cur = inst.getCurrentTime ? inst.getCurrentTime() : 0;
      if (!dur || dur <= 0) return;
      // Tiempos en milisegundos en RMP v8
      var remainingMs = dur - cur;
      var remainingSec = Math.ceil(remainingMs / 1000);
      if (remainingSec <= TRIGGER_REMAINING && remainingSec > 0) {
        // Mostrar overlay con countdown desde COUNTDOWN_FROM (10).
        // Edge case video muy corto: limitar al floor(duration).
        var durSec = Math.floor(dur / 1000);
        var initial = Math.min(COUNTDOWN_FROM, durSec > 0 ? durSec : COUNTDOWN_FROM);
        showOverlay(initial);
      }
    }

    function onPause() {
      // Pausar countdown junto con el video. NO incluye el 'pause' que
      // dispara RMP al llegar al ended natural — para ese caso el video
      // ya termino y queremos que el countdown llegue a 0 igual. RMP v8
      // dispara 'pause' justo antes de 'ended', asi que solo pausamos
      // si el video no esta cerca del final (durante reproduccion normal).
      if (!inst) return;
      var dur = inst.getDuration ? inst.getDuration() : 0;
      var cur = inst.getCurrentTime ? inst.getCurrentTime() : 0;
      if (dur > 0 && (dur - cur) < 250) return; // 250ms = ended inminente
      stopCountdown();
    }

    function onPlay() {
      if (!shown || cancelled || redirecting) return;
      startCountdownTicker();
    }

    function attachPlayerEvents() {
      var rmpEl = document.getElementById('rmpPlayer');
      if (!rmpEl) return;
      rmpEl.addEventListener('timeupdate', onTimeUpdate);
      rmpEl.addEventListener('pause', onPause);
      rmpEl.addEventListener('play', onPlay);
      rmpEl.addEventListener('playing', onPlay);
      // NO listener de 'ended': el countdown sigue corriendo independiente
      // del estado del video al terminar. Si llega a 0 antes/despues del
      // fin natural, gotoNext() se dispara igual.
    }

    tryAttach();
  })();
  </script>
  <?php endif; ?>
</body>
</html>
