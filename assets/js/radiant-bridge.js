/**
 * ADC Radiant Bridge
 *
 * El player Radiant Media Player vive dentro de un <iframe> servido por
 * /radiant/vod.php. Cuando el countdown del overlay "A continuacion"
 * llega a 0 (o el usuario hace click en "Reproducir ahora"), el iframe
 * envia un postMessage al padre solicitando navegar al siguiente video.
 *
 * Este bridge escucha ese mensaje y hace window.location.href, manteniendo
 * la URL friendly del plugin (no la URL HLS interna).
 */
(function () {
    'use strict';

    var EXPECTED_ORIGIN = 'https://tuia.tv';

    window.addEventListener('message', function (event) {
        if (event.origin !== EXPECTED_ORIGIN) return;
        if (!event.data || typeof event.data !== 'object') return;
        if (event.data.action !== 'goto-next') return;
        if (typeof event.data.url !== 'string' || !event.data.url) return;

        try {
            var u = new URL(event.data.url, window.location.origin);
            if (u.origin !== window.location.origin) return;
            window.location.href = u.href;
        } catch (e) {
            // URL invalida — ignorar silenciosamente
        }
    });
})();
