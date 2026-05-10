jQuery(document).ready(function ($) {
    var ajaxUrl = (typeof adc_admin_config !== 'undefined' && adc_admin_config.ajax_url) ? adc_admin_config.ajax_url : ajaxurl;
    var nonce = (typeof adc_admin_config !== 'undefined' && adc_admin_config.nonce) ? adc_admin_config.nonce : '';

    // Clear ALL cache handler
    $("#adc-clear-all-cache").on("click", function (e) {
        e.preventDefault();
        var button = $(this);
        var originalText = button.text();

        button.prop("disabled", true).text("🗑️ Limpiando...");
        $("#adc-cache-status").removeClass("success error").addClass("loading").text("Limpiando todo el caché...").show();

        $.ajax({
            url: ajaxUrl,
            type: "POST",
            data: {
                action: "adc_clear_all_cache",
                nonce: nonce
            },
            success: function (response) {
                if (response.success) {
                    $("#adc-cache-status").removeClass("loading error").addClass("success").text("✅ " + response.data.message);
                    setTimeout(function () { location.reload(); }, 2000);
                } else {
                    $("#adc-cache-status").removeClass("loading success").addClass("error").text("❌ Error: " + response.data);
                }
            },
            error: function () {
                $("#adc-cache-status").removeClass("loading success").addClass("error").text("❌ Error de conexión");
            },
            complete: function () {
                button.prop("disabled", false).text(originalText);
            }
        });
    });

    // Copy token to clipboard
    $("#adc-copy-token").on("click", function (e) {
        e.preventDefault();
        var token = $("#adc-current-token").val();
        copyToClipboard(token, $(this));
    });

    // Copy webhook URL to clipboard
    $("#adc-copy-webhook").on("click", function (e) {
        e.preventDefault();
        var webhookUrl = $("#adc-webhook-url").val();
        copyToClipboard(webhookUrl, $(this));
    });

    function copyToClipboard(text, button) {
        var originalText = button.text();

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(function () {
                button.text("✅ Copiado!");
                setTimeout(function () {
                    button.text(originalText);
                }, 2000);
            }).catch(function () {
                fallbackCopyToClipboard(text, button, originalText);
            });
        } else {
            fallbackCopyToClipboard(text, button, originalText);
        }
    }

    function fallbackCopyToClipboard(text, button, originalText) {
        var textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.style.position = "fixed";
        textArea.style.left = "-999999px";
        textArea.style.top = "-999999px";
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();

        try {
            document.execCommand("copy");
            button.text("✅ Copiado!");
            setTimeout(function () {
                button.text(originalText);
            }, 2000);
        } catch (err) {
            button.text("❌ Error");
            setTimeout(function () {
                button.text(originalText);
            }, 2000);
        }

        document.body.removeChild(textArea);
    }

    // Test connection handlers
    $(".adc-test-connection").on("click", function (e) {
        e.preventDefault();
        var button = $(this);
        var language = button.data("language");
        var originalText = button.text();

        button.prop("disabled", true).text("Probando...");
        $("#adc-connection-status").removeClass("success error").addClass("loading").text("Probando conexión para " + language.toUpperCase() + "...").show();

        $.ajax({
            url: ajaxUrl,
            type: "POST",
            data: {
                action: "adc_test_connection",
                language: language,
                nonce: nonce
            },
            success: function (response) {
                if (response.success) {
                    $("#adc-connection-status").removeClass("loading error").addClass("success").text("✅ Conexión exitosa para " + language.toUpperCase() + " - " + response.data.programs_count + " programas encontrados");
                } else {
                    $("#adc-connection-status").removeClass("loading success").addClass("error").text("❌ Error en " + language.toUpperCase() + ": " + response.data.error);
                }
            },
            error: function () {
                $("#adc-connection-status").removeClass("loading success").addClass("error").text("❌ Error de conexión para " + language.toUpperCase());
            },
            complete: function () {
                button.prop("disabled", false).text(originalText);
            }
        });
    });
});
