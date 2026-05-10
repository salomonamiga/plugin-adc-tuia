jQuery(document).ready(function ($) {
    var ajaxUrl = (typeof adc_admin_config !== 'undefined' && adc_admin_config.ajax_url) ? adc_admin_config.ajax_url : ajaxurl;
    var nonce = (typeof adc_admin_config !== 'undefined' && adc_admin_config.nonce) ? adc_admin_config.nonce : '';

    var $sortable = $("#sortable-programs");
    if (!$sortable.length) {
        return;
    }
    var language = $sortable.data("language") || 'es';

    $sortable.sortable({
        handle: ".program-handle",
        update: function () {
            $("#order-save-status").removeClass("hidden success error");
            $("#order-save-status .message").text("Guardando...");

            var programOrder = [];
            $(".program-item").each(function () {
                programOrder.push($(this).data("id"));
            });

            $.ajax({
                url: ajaxUrl,
                type: "POST",
                data: {
                    action: "adc_update_program_order",
                    program_order: programOrder,
                    language: language,
                    nonce: nonce
                },
                success: function (response) {
                    if (response.success) {
                        $("#order-save-status").addClass("success");
                        $("#order-save-status .message").text("¡Orden guardado exitosamente!");

                        setTimeout(function () {
                            $("#order-save-status").addClass("hidden");
                        }, 3000);
                    } else {
                        $("#order-save-status").addClass("error");
                        $("#order-save-status .message").text("Error al guardar orden.");
                    }
                },
                error: function () {
                    $("#order-save-status").addClass("error");
                    $("#order-save-status .message").text("Error al guardar orden.");
                }
            });
        }
    });
});
