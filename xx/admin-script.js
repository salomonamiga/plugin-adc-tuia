/**
 * ADC Video Display - Admin JavaScript
 */
(function($) {
    'use strict';
    
    // DOM Ready
    $(function() {
        // Manejar el botón de limpiar caché
        $('#adc-clear-cache').on('click', function(e) {
            e.preventDefault();
            
            var button = $(this);
            var resultSpan = $('#adc-clear-cache-result');
            var nonce = button.data('nonce');
            
            // Deshabilitar botón durante la operación
            button.prop('disabled', true);
            
            // Mostrar mensaje de espera
            resultSpan.text(adc_admin.clear_cache_message)
                      .css('color', 'orange')
                      .show();
            
            // Realizar la petición AJAX para limpiar caché
            $.ajax({
                url: adc_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'adc_clear_cache',
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        resultSpan.text(adc_admin.clear_cache_success)
                                  .css('color', 'green');
                    } else {
                        resultSpan.text(adc_admin.clear_cache_error + ': ' + 
                                  (response.data && response.data.message ? response.data.message : 'Error desconocido'))
                                  .css('color', 'red');
                    }
                },
                error: function() {
                    resultSpan.text(adc_admin.clear_cache_error)
                              .css('color', 'red');
                },
                complete: function() {
                    // Habilitar botón nuevamente
                    button.prop('disabled', false);
                    
                    // Ocultar mensaje después de 3 segundos
                    setTimeout(function() {
                        resultSpan.fadeOut(300, function() {
                            resultSpan.text('')
                                      .show()
                                      .css('color', '');
                        });
                    }, 3000);
                }
            });
        });
        
        // Mostrar/ocultar opciones dependientes
        function toggleDependentFields() {
            // Campo de segundos para autoplay
            var autoplayEnabled = $('input[name="adc-video-display[enable_autoplay]"]').is(':checked');
            var countdownField = $('input[name="adc-video-display[autoplay_countdown]"]').closest('tr');
            
            if (autoplayEnabled) {
                countdownField.show();
            } else {
                countdownField.hide();
            }
            
            // Campo de tiempo de expiración de caché
            var cacheEnabled = $('input[name="adc-video-display[enable_cache]"]').is(':checked');
            var cacheExpirationField = $('select[name="adc-video-display[cache_expiration]"]').closest('tr');
            
            if (cacheEnabled) {
                cacheExpirationField.show();
            } else {
                cacheExpirationField.hide();
            }
        }
        
        // Ejecutar al cargar
        toggleDependentFields();
        
        // Ejecutar cuando cambian los checkboxes
        $('input[name="adc-video-display[enable_autoplay]"], input[name="adc-video-display[enable_cache]"]').on('change', function() {
            toggleDependentFields();
        });
        
        // Previsualización de opciones en tiempo real
        function updatePreview() {
            // Actualizar previsualización de videos por fila
            var videosPerRow = $('select[name="adc-video-display[videos_per_row]"]').val();
            $('#preview-videos-per-row').text(videosPerRow);
            
            // Actualizar previsualización de sección
            var section = $('select[name="adc-video-display[section]"]').val();
            var sectionName = section === '5' ? 'IA' : 'Kids';
            $('#preview-section').text(sectionName);
        }
        
        // Solo inicializar la previsualización si existen los elementos
        if ($('#preview-videos-per-row').length && $('#preview-section').length) {
            // Ejecutar al cargar
            updatePreview();
            
            // Ejecutar cuando cambian los selects
            $('select[name="adc-video-display[videos_per_row]"], select[name="adc-video-display[section]"]').on('change', function() {
                updatePreview();
            });
        }
    });
    
})(jQuery);