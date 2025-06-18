/**
 * ADC Video Display - Frontend JavaScript
 * Version: 2.1 - Optimized with modular structure
 */

(function ($) {
    'use strict';

    // Main ADC Video object - Modularized and optimized
    window.ADCVideo = {

        // Configuration
        config: {
            autoplayEnabled: true,
            autoplayCountdown: 5,
            playerVolume: 0.5,
            debug: false
        },

        // Internal state
        state: {
            player: null,
            countdownInterval: null,
            isInitialized: false
        },

        // Cache for DOM elements
        cache: {
            $document: $(document),
            $window: $(window),
            $body: $('body')
        },

        // Utility functions - Consolidated
        utils: {
            // Single slugify function (no more duplication)
            slugify: function (text) {
                if (!text) return '';

                // Remove accents and normalize
                var from = "áàäâéèëêíìïîóòöôúùüûñç·/_,:;";
                var to = "aaaaeeeeiiiioooouuuunc------";

                for (var i = 0, l = from.length; i < l; i++) {
                    text = text.replace(new RegExp(from.charAt(i), 'g'), to.charAt(i));
                }

                return text
                    .toString()
                    .toLowerCase()
                    .trim()
                    .replace(/\s+/g, '-')
                    .replace(/&/g, '-y-')
                    .replace(/[^\w\-]+/g, '')
                    .replace(/\-\-+/g, '-')
                    .replace(/^-+/, '')
                    .replace(/-+$/, '');
            },

            // Format duration from seconds to MM:SS
            formatDuration: function (seconds) {
                if (!seconds) return '';
                var minutes = Math.floor(seconds / 60);
                var remainingSeconds = seconds % 60;
                return minutes + ':' + (remainingSeconds < 10 ? '0' : '') + remainingSeconds;
            },

            // Debounce function - Optimized
            debounce: function (func, wait) {
                var timeout;
                return function () {
                    var context = this, args = arguments;
                    clearTimeout(timeout);
                    timeout = setTimeout(function () {
                        func.apply(context, args);
                    }, wait);
                };
            },

            // Get URL parameter
            getUrlParam: function (param) {
                var urlParams = new URLSearchParams(window.location.search);
                return urlParams.get(param);
            },

            // Log function for debugging
            log: function (message, type) {
                if (ADCVideo.config.debug && window.console) {
                    var logType = type || 'log';
                    console[logType]('[ADC Video] ' + message);
                }
            }
        },

        // Initialize - Main entry point
        init: function (options) {
            if (this.state.isInitialized) {
                this.utils.log('Already initialized, skipping');
                return;
            }

            this.utils.log('Initializing ADC Video Display v2.1');

            // Merge options with defaults
            if (options) {
                this.config = $.extend(this.config, options);
            }

            // Initialize all modules
            this.player.init();
            this.menu.init();
            this.search.init();
            this.events.init();
            this.cleanup.init();

            this.state.isInitialized = true;
            this.utils.log('Initialization complete');
        },

        // Video Player Module - Optimized
        player: {
            init: function () {
                if (typeof videojs === 'undefined' || !document.getElementById('adc-player')) {
                    ADCVideo.utils.log('Video.js not found or no player element');
                    return;
                }

                ADCVideo.utils.log('Initializing video player');
                this.setupPlayer();
            },

            setupPlayer: function () {
                var self = this;
                var player = videojs('adc-player', {
                    controls: true,
                    preload: 'auto',
                    fluid: true,
                    responsive: true
                });

                player.ready(function () {
                    ADCVideo.utils.log('Player ready');

                    // Set initial volume
                    player.volume(ADCVideo.config.playerVolume);

                    // Add custom buttons
                    self.addCustomButtons(player);

                    // Handle ended event for autoplay
                    if (ADCVideo.config.autoplayEnabled) {
                        player.on('ended', function () {
                            ADCVideo.autoplay.handleVideoEnded();
                        });
                    }
                });

                // Store player reference
                ADCVideo.state.player = player;
            },

            addCustomButtons: function (player) {
                var Button = videojs.getComponent('Button');

                // Rewind button - Optimized
                var RewindButton = videojs.extend(Button, {
                    constructor: function () {
                        Button.apply(this, arguments);
                        this.controlText('Retroceder 10 segundos');
                        this.addClass('vjs-rewind-button');
                    },
                    handleClick: function () {
                        var currentTime = player.currentTime();
                        player.currentTime(Math.max(0, currentTime - 10));
                        ADCVideo.utils.log('Rewound 10 seconds');
                    }
                });

                // Forward button - Optimized  
                var ForwardButton = videojs.extend(Button, {
                    constructor: function () {
                        Button.apply(this, arguments);
                        this.controlText('Adelantar 10 segundos');
                        this.addClass('vjs-forward-button');
                    },
                    handleClick: function () {
                        var currentTime = player.currentTime();
                        var duration = player.duration();
                        player.currentTime(Math.min(duration, currentTime + 10));
                        ADCVideo.utils.log('Fast-forwarded 10 seconds');
                    }
                });

                // Register and add buttons
                videojs.registerComponent('RewindButton', RewindButton);
                videojs.registerComponent('ForwardButton', ForwardButton);

                player.controlBar.addChild('RewindButton', {}, 0);
                player.controlBar.addChild('ForwardButton', {}, 2);

                // Style the buttons
                this.styleCustomButtons(player);
            },

            styleCustomButtons: function (player) {
                setTimeout(function () {
                    var rewindBtn = player.controlBar.getChild('RewindButton');
                    var forwardBtn = player.controlBar.getChild('ForwardButton');

                    if (rewindBtn && rewindBtn.el()) {
                        rewindBtn.el().innerHTML = '<span>⏪ 10s</span>';
                    }

                    if (forwardBtn && forwardBtn.el()) {
                        forwardBtn.el().innerHTML = '<span>10s ⏩</span>';
                    }
                }, 100);
            }
        },

        // Autoplay Module - Enhanced
        autoplay: {
            handleVideoEnded: function () {
                var nextUrl = this.getNextVideoUrl();
                if (!nextUrl) {
                    ADCVideo.utils.log('No next video found');
                    return;
                }

                ADCVideo.utils.log('Video ended, starting autoplay countdown');
                this.showOverlay(nextUrl);
            },

            getNextVideoUrl: function () {
                // Try next button first
                var nextBtn = document.querySelector('.adc-next-button-container a');
                if (nextBtn && nextBtn.href) {
                    return nextBtn.href;
                }

                // Fallback: check in overlay
                var overlayLink = document.querySelector('#adc-next-overlay a');
                if (overlayLink && overlayLink.href && overlayLink.href !== '#') {
                    return overlayLink.href;
                }

                return null;
            },

            showOverlay: function (nextUrl) {
                var overlay = document.getElementById('adc-next-overlay');
                var countdownEl = document.getElementById('adc-countdown');

                if (!overlay || !countdownEl) {
                    ADCVideo.utils.log('Overlay elements not found');
                    return;
                }

                // Exit fullscreen if active
                if (ADCVideo.state.player && ADCVideo.state.player.isFullscreen()) {
                    ADCVideo.state.player.exitFullscreen();
                }

                // Show overlay after delay to ensure fullscreen exit
                setTimeout(function () {
                    overlay.style.display = 'block';
                    ADCVideo.autoplay.startCountdown(nextUrl, countdownEl);
                }, 300);
            },

            startCountdown: function (nextUrl, countdownEl) {
                var seconds = ADCVideo.config.autoplayCountdown;
                var cancelled = false;

                countdownEl.textContent = seconds;

                ADCVideo.state.countdownInterval = setInterval(function () {
                    seconds--;
                    countdownEl.textContent = seconds;

                    if (seconds <= 0 && !cancelled) {
                        clearInterval(ADCVideo.state.countdownInterval);
                        ADCVideo.utils.log('Autoplay countdown finished, redirecting');
                        window.location.href = nextUrl;
                    }
                }, 1000);

                // Handle cancel button
                var cancelBtn = document.getElementById('adc-cancel-autoplay');
                if (cancelBtn) {
                    cancelBtn.onclick = function () {
                        cancelled = true;
                        ADCVideo.autoplay.cancelAutoplay();
                    };
                }
            },

            cancelAutoplay: function () {
                if (ADCVideo.state.countdownInterval) {
                    clearInterval(ADCVideo.state.countdownInterval);
                    ADCVideo.state.countdownInterval = null;
                }

                var overlay = document.getElementById('adc-next-overlay');
                if (overlay) {
                    overlay.innerHTML = '<p style="color:#aaa">Autoplay cancelado</p>';
                }

                ADCVideo.utils.log('Autoplay cancelled by user');
            }
        },

        // Menu Module - RESTORED WORKING LOGIC FROM v2.0
        menu: {
            initialized: false,
            observer: null,

            init: function () {
                if (this.initialized) return;

                console.log('Inicializando menú PROGRAMAS - Lógica v2.0 restaurada');

                this.initProgramsMenu();
                this.setupSearchReplacements();

                this.initialized = true;
            },

            initProgramsMenu: function () {
                console.log('Inicializando menú PROGRAMAS - Versión completa');
                var self = this;

                // Limpiar eventos anteriores para evitar duplicaciones
                $(document).off('click.programs-menu');
                $(document).off('click.programs-menu-outside');
                $(document).off('keydown.programs-menu');
                $('.dropdown-arrow').remove();
                $('.adc-wp-programs-dropdown').remove();

                // Función para verificar si un elemento está correctamente configurado
                function isProperlyConfigured($programasLink) {
                    var $dropdown = $programasLink.data('dropdown');
                    var $arrow = $programasLink.data('arrow');

                    // Verificar que existen y están en el DOM
                    if (!$dropdown || !$arrow) {
                        return false;
                    }

                    // Verificar que los elementos están realmente en el DOM
                    if (!$.contains(document, $dropdown[0]) || !$.contains(document, $arrow[0])) {
                        console.log('⚠️ Referencias rotas detectadas, necesita reconfiguración');
                        return false;
                    }

                    return true;
                }

                // Función para configurar un elemento PROGRAMAS
                function setupProgramsElement($programasLink) {
                    console.log('Configurando elemento PROGRAMAS:', $programasLink.text());

                    var $parentLi = $programasLink.closest('li');
                    if (!$parentLi.length) {
                        $parentLi = $programasLink.parent();
                    }

                    // Limpiar configuración anterior si existe
                    $parentLi.find('.adc-wp-programs-dropdown').remove();
                    $programasLink.find('.dropdown-arrow').remove();
                    $programasLink.removeData('dropdown arrow programs-configured');

                    // Configurar el contenedor padre
                    $parentLi.css({
                        'position': 'relative',
                        'z-index': '999'
                    });

                    // Crear el dropdown
                    var $dropdown = $('<div class="adc-wp-programs-dropdown"></div>');
                    $parentLi.append($dropdown);
                    $dropdown.html('<div class="adc-loading">Cargando programas...</div>');
                    $dropdown.hide(); // Ocultar inicialmente

                    // Añadir flecha CON TODOS LOS ESTILOS ORIGINALES
                    var $arrow = $('<span class="dropdown-arrow" style="color:#6EC1E4; margin-left:5px; vertical-align:middle; transition:transform 0.3s ease; display:inline-block;">▾</span>');
                    $programasLink.append($arrow);

                    // Guardar referencias en el elemento para fácil acceso
                    $programasLink.data('dropdown', $dropdown);
                    $programasLink.data('arrow', $arrow);
                    $programasLink.data('programs-configured', true);

                    console.log('✅ Elemento PROGRAMAS configurado correctamente');
                }

                // Función para cargar programas en el dropdown
                function loadPrograms($dropdown) {
                    if ($dropdown.data('programs-loaded')) {
                        console.log('Programas ya cargados, saltando...');
                        return; // Ya están cargados
                    }

                    console.log('📡 Cargando programas desde API...');

                    var ajaxUrl = typeof adc_config !== 'undefined' ? adc_config.ajax_url : '/wp-admin/admin-ajax.php';
                    var nonce = typeof adc_config !== 'undefined' ? adc_config.nonce : '';

                    $.ajax({
                        url: ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'adc_get_programs_menu',
                            nonce: nonce
                        },
                        success: function (response) {
                            console.log('✅ Respuesta API completa:', response);

                            if (response.success && response.data && response.data.length > 0) {
                                var html = '';

                                $.each(response.data, function (i, program) {
                                    var slug = self.slugify(program.name);
                                    // ESTILOS MEJORADOS CON SOPORTE PARA 2 LÍNEAS
                                    html += '<a href="/?categoria=' + slug + '" style="display:block !important; padding:12px 20px !important; color:#6EC1E4 !important; text-decoration:none !important; border-bottom:1px solid rgba(110, 193, 228, 0.1) !important; font-size:18px !important; line-height:1.3 !important; font-weight:500 !important; font-family:inherit !important; white-space:normal !important; word-wrap:break-word !important; max-width:300px !important; overflow-wrap:break-word !important;">' + program.name + '</a>';
                                });

                                $dropdown.html(html);
                                $dropdown.data('programs-loaded', true);
                                console.log('✅ Programas cargados exitosamente:', response.data.length);

                                // Efectos hover IGUALES A LOS ORIGINALES
                                $dropdown.find('a').hover(
                                    function () {
                                        $(this).css({
                                            'background-color': 'rgba(110, 193, 228, 0.1)',
                                            'color': '#FFFFFF',
                                            'padding-left': '25px'
                                        });
                                    },
                                    function () {
                                        $(this).css({
                                            'background-color': 'transparent',
                                            'color': '#6EC1E4',
                                            'padding-left': '20px'
                                        });
                                    }
                                );
                            } else {
                                console.log('❌ Error: respuesta sin programas válidos', response);
                                var errorMsg = 'No hay programas disponibles';
                                if (response.data && response.data.message) {
                                    errorMsg = response.data.message;
                                } else if (!response.success && response.data) {
                                    errorMsg = response.data;
                                }
                                $dropdown.html('<div class="adc-error" style="padding:20px; color:red; text-align:center;">' + errorMsg + '</div>');
                            }
                        },
                        error: function (xhr, status, error) {
                            console.log('❌ Error AJAX completo:', {
                                status: status,
                                error: error,
                                responseText: xhr.responseText,
                                xhr: xhr
                            });
                            $dropdown.html('<div class="adc-error" style="padding:20px; color:red; text-align:center;">Error al cargar programas: ' + error + '</div>');
                        }
                    });
                }

                // Función para toggle del dropdown
                function toggleDropdown($programasLink) {
                    var $dropdown = $programasLink.data('dropdown');
                    var $arrow = $programasLink.data('arrow');

                    if (!$dropdown || !$arrow) {
                        console.log('❌ No se encontraron referencias del dropdown');
                        return;
                    }

                    // Cerrar otros dropdowns primero
                    $('.adc-wp-programs-dropdown').not($dropdown).slideUp(200);
                    $('.dropdown-arrow').not($arrow).css('transform', 'rotate(0deg)');

                    // Verificar si el dropdown está visible ANTES de cambiarlo (LÓGICA ORIGINAL)
                    var isVisible = $dropdown.is(':visible');

                    // Toggle del dropdown actual
                    $dropdown.slideToggle(200);

                    // Actualizar flecha al abrir/cerrar (LÓGICA ORIGINAL CORREGIDA)
                    if (isVisible) {
                        // Si ya estaba visible, lo estamos cerrando
                        $arrow.css('transform', 'rotate(0deg)');
                        console.log('🔽 Dropdown cerrado');
                    } else {
                        // Si estaba oculto, lo estamos abriendo
                        $arrow.css('transform', 'rotate(180deg)');
                        console.log('🔼 Dropdown abierto');

                        // Cargar programas si no están cargados (CONDICIÓN ORIGINAL)
                        if ($dropdown.find('.adc-loading, .adc-error').length) {
                            loadPrograms($dropdown);
                        }
                    }
                }

                // Configurar elementos existentes al inicializar - SELECTOR ORIGINAL + NUEVO
                $('a:contains("PROGRAMAS"), .adc_programs_menu_text, .adc-programs-menu-trigger a').each(function () {
                    setupProgramsElement($(this));
                });

                // Usar delegación de eventos para manejar clicks (funciona incluso cuando el DOM cambia)
                $(document).on('click.programs-menu', 'a:contains("PROGRAMAS"), .adc_programs_menu_text, .adc-programs-menu-trigger a', function (e) {
                    e.preventDefault();
                    e.stopPropagation();

                    console.log('🖱️ Click en PROGRAMAS detectado');

                    var $this = $(this);

                    // SOLUCIÓN MÓVIL: Verificar si está correctamente configurado o necesita reconfiguración
                    if (!$this.data('programs-configured') || !isProperlyConfigured($this)) {
                        console.log('🔄 Reconfigurando elemento (móvil o referencias rotas)');
                        setupProgramsElement($this);
                    }

                    toggleDropdown($this);
                });

                // Cerrar dropdowns al hacer click fuera (IGUAL AL ORIGINAL)
                $(document).on('click.programs-menu-outside', function (e) {
                    if (!$(e.target).closest('.adc-wp-programs-dropdown, a:contains("PROGRAMAS"), .adc_programs_menu_text, .adc-programs-menu-trigger').length) {
                        $('.adc-wp-programs-dropdown').slideUp(200);
                        $('.dropdown-arrow').css('transform', 'rotate(0deg)');
                    }
                });

                // Manejar tecla Escape
                $(document).on('keydown.programs-menu', function (e) {
                    if (e.key === 'Escape') {
                        $('.adc-wp-programs-dropdown').slideUp(200);
                        $('.dropdown-arrow').css('transform', 'rotate(0deg)');
                    }
                });

                // SOLUCIÓN MÓVIL ADICIONAL: Reconfigurar cuando el menú de Elementor se abre
                // Detectar cambios en el DOM que puedan afectar nuestros elementos
                this.observer = new MutationObserver(function (mutations) {
                    mutations.forEach(function (mutation) {
                        if (mutation.type === 'childList') {
                            // Buscar elementos PROGRAMAS que puedan haberse añadido/modificado
                            $(mutation.addedNodes).find('a:contains("PROGRAMAS"), .adc_programs_menu_text, .adc-programs-menu-trigger a').each(function () {
                                var $this = $(this);
                                if (!$this.data('programs-configured') || !isProperlyConfigured($this)) {
                                    console.log('🆕 Nuevo elemento PROGRAMAS detectado por MutationObserver');
                                    setupProgramsElement($this);
                                }
                            });
                        }
                    });
                });

                // Observar cambios en el body
                this.observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });

                console.log('✅ Menú PROGRAMAS inicializado correctamente con soluciones para desktop y móvil');
            },

            // Función slugify - IGUAL A LA ORIGINAL
            slugify: function (text) {
                // Primera conversión: eliminar acentos
                var from = "áàäâéèëêíìïîóòöôúùüûñç·/_,:;";
                var to = "aaaaeeeeiiiioooouuuunc------";

                for (var i = 0, l = from.length; i < l; i++) {
                    text = text.replace(new RegExp(from.charAt(i), 'g'), to.charAt(i));
                }

                // Normalizar a ASCII
                return text
                    .toString()                      // Convertir a string
                    .toLowerCase()                   // Convertir a minúsculas
                    .trim()                          // Eliminar espacios al inicio y final
                    .replace(/\s+/g, '-')            // Reemplazar espacios con guiones
                    .replace(/&/g, '-y-')            // Reemplazar & con 'y'
                    .replace(/[^\w\-]+/g, '')        // Eliminar todos los caracteres no-alfanuméricos
                    .replace(/\-\-+/g, '-')          // Reemplazar múltiples guiones con uno solo
                    .replace(/^-+/, '')              // Eliminar guiones del inicio
                    .replace(/-+$/, '');             // Eliminar guiones del final
            },

            setupSearchReplacements: function () {
                // Buscar elementos BUSCADOR y reemplazarlos con formulario de búsqueda
                document.querySelectorAll('a').forEach(function (link) {
                    if (link.textContent.trim() === 'BUSCADOR') {
                        var searchContainer = document.createElement('div');
                        searchContainer.className = 'adc-menu-search-container';

                        var homeUrl = window.location.origin + '/';

                        searchContainer.innerHTML =
                            '<form class="adc-inline-search-form" action="' + homeUrl + '" method="get">' +
                            '<input type="text" name="adc_search" placeholder="Buscar..." class="adc-inline-search-input">' +
                            '<button type="submit" class="adc-inline-search-button">' +
                            '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                            '<circle cx="11" cy="11" r="8"></circle>' +
                            '<line x1="21" y1="21" x2="16.65" y2="16.65"></line>' +
                            '</svg>' +
                            '</button>' +
                            '</form>';

                        // Reemplazar el elemento del menú
                        var menuItem = link.closest('li');
                        if (menuItem) {
                            menuItem.innerHTML = '';
                            menuItem.appendChild(searchContainer);
                            menuItem.style.display = 'flex';
                            menuItem.style.alignItems = 'center';
                            menuItem.style.marginLeft = '40px';
                        }
                    }
                });
            }
        },

        // Search Module - Enhanced and optimized
        search: {
            initialized: false,

            init: function () {
                if (this.initialized) return;

                ADCVideo.utils.log('Initializing search system');

                this.setupSearchForms();
                this.setupSearchIcon();
                this.removeAutofocus();
                this.bindSearchEvents();

                this.initialized = true;
            },

            setupSearchForms: function () {
                var forms = document.querySelectorAll('.adc-search-form, .adc-inline-search-form');

                forms.forEach(function (form) {
                    var input = form.querySelector('input[name="adc_search"]');
                    if (!input) return;

                    // Prevent empty searches
                    form.addEventListener('submit', function (e) {
                        var searchTerm = input.value.trim();
                        if (searchTerm === '') {
                            e.preventDefault();
                            input.focus();
                            ADCVideo.utils.log('Empty search prevented');
                        } else {
                            ADCVideo.utils.log('Search submitted: ' + searchTerm);
                        }
                    });
                });
            },

            setupSearchIcon: function () {
                ADCVideo.utils.log('Setting up search icons');

                var self = this;

                $('a:contains("BUSCADOR"), a:contains("Buscar"), a.search-toggle, .search-toggle, .search-icon, .fa-search, .adc-search-menu-trigger').each(function () {
                    var $searchLink = $(this);

                    if ($searchLink.data('search-initialized')) {
                        return;
                    }

                    ADCVideo.utils.log('Search element found, configuring');

                    $searchLink.addClass('adc-search-menu-trigger');

                    var $parentLi = $searchLink.closest('li');
                    if (!$parentLi.length) {
                        $parentLi = $searchLink.parent();
                    }

                    // Create enhanced search form
                    var $searchContainer = $('<div class="adc-menu-search-container"></div>');
                    var $searchForm = $('<form class="adc-inline-search-form" action="' + window.location.origin + '/" method="get"></form>');
                    var $searchInput = $('<input type="text" name="adc_search" placeholder="Buscar..." class="adc-inline-search-input">');
                    var $searchButton = $('<button type="submit" class="adc-inline-search-button" style="background:transparent !important; border:none !important; color:#ffffff !important; box-shadow:none !important; outline:none !important;"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg></button>');

                    $searchForm.append($searchInput).append($searchButton);
                    $searchContainer.append($searchForm);

                    // Apply enhanced styling
                    $searchForm.css({
                        'background': 'transparent',
                        'border': '1px solid #ffffff',
                        'border-radius': '6px',
                        'transition': 'all 0.3s ease'
                    });

                    $searchInput.css({
                        'width': '150px',
                        'transition': 'all 0.3s ease'
                    });

                    // Enhanced hover effects
                    $searchButton.hover(
                        function () {
                            $(this).css({
                                'color': '#6EC1E4',
                                'background-color': 'rgba(0, 0, 0, 0.8)',
                                'border': '1px solid #6EC1E4',
                                'border-radius': '4px',
                                'transform': 'scale(1.05)'
                            });
                        },
                        function () {
                            $(this).css({
                                'color': '#ffffff',
                                'background-color': 'transparent',
                                'border': 'none',
                                'transform': 'scale(1)'
                            });
                        }
                    );

                    // Replace element
                    if ($parentLi.length) {
                        $parentLi.html('').append($searchContainer);
                        $parentLi.css({
                            'display': 'flex',
                            'align-items': 'center'
                        });
                    } else {
                        $searchLink.replaceWith($searchContainer);
                    }

                    $searchLink.data('search-initialized', true);
                });

                this.ensureFontAwesome();
            },

            ensureFontAwesome: function () {
                if ($('link[href*="font-awesome"]').length) {
                    return;
                }

                $('<link>').attr({
                    rel: 'stylesheet',
                    href: 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css',
                    integrity: 'sha512-1ycn6IcaQQ40/MKBW2W4Rhis/DbILU74C1vSrLJxCq57o941Ym01SwNsOMqvEBFlcgUa6xLiPY/NS5R+E6ztJQ==',
                    crossorigin: 'anonymous'
                }).appendTo('head');
            },

            removeAutofocus: function () {
                // Remove autofocus from search inputs to prevent unwanted keyboard popups
                setTimeout(function () {
                    document.querySelectorAll('.adc-inline-search-input').forEach(function (input) {
                        if (document.activeElement === input) {
                            input.blur();
                        }
                        // Remove autofocus attribute if present
                        input.removeAttribute('autofocus');
                    });
                }, 100);
            },

            bindSearchEvents: function () {
                // Enhanced search form handling
                ADCVideo.cache.$document.on('submit', '.adc-search-form, .adc-inline-search-form', function (e) {
                    var $form = $(this);
                    var $input = $form.find('input[name="adc_search"]');
                    var searchTerm = $input.val().trim();

                    if (searchTerm === '') {
                        e.preventDefault();
                        $input.focus();
                        ADCVideo.utils.log('Empty search prevented');
                        return false;
                    }

                    ADCVideo.utils.log('Search form submitted: ' + searchTerm);
                });

                // Handle search input focus/blur effects
                ADCVideo.cache.$document.on('focus', '.adc-inline-search-input', function () {
                    var $form = $(this).closest('.adc-inline-search-form');
                    $form.addClass('adc-search-focused');
                });

                ADCVideo.cache.$document.on('blur', '.adc-inline-search-input', function () {
                    var $form = $(this).closest('.adc-inline-search-form');
                    $form.removeClass('adc-search-focused');
                });
            },

            // AJAX search functionality (if needed)
            performAjaxSearch: function (query, callback) {
                if (!query || query.trim() === '') {
                    ADCVideo.utils.log('Empty search query', 'warn');
                    return;
                }

                ADCVideo.utils.log('Performing AJAX search: ' + query);

                var ajaxUrl = typeof adc_config !== 'undefined' ? adc_config.ajax_url : '/wp-admin/admin-ajax.php';
                var nonce = typeof adc_config !== 'undefined' ? adc_config.nonce : '';

                $.ajax({
                    url: ajaxUrl,
                    type: 'GET',
                    data: {
                        action: 'adc_search',
                        search: query,
                        nonce: nonce
                    },
                    success: function (response) {
                        ADCVideo.utils.log('Search results received');
                        if (response.success && callback) {
                            callback(response.data);
                        }
                    },
                    error: function (xhr, status, error) {
                        ADCVideo.utils.log('Search AJAX error: ' + error, 'error');
                    }
                });
            }
        },

        // Events Module - Consolidated event handling
        events: {
            initialized: false,

            init: function () {
                if (this.initialized) return;

                ADCVideo.utils.log('Initializing event handlers');

                this.bindKeyboardEvents();
                this.bindButtonEvents();
                this.bindGeneralEvents();
                this.bindLazyLoading();

                this.initialized = true;
            },

            bindKeyboardEvents: function () {
                // Enhanced keyboard shortcuts
                ADCVideo.cache.$document.on('keydown.adc-video', function (e) {
                    var player = ADCVideo.state.player;
                    if (!player) return;

                    // Only apply shortcuts when not in input fields
                    if ($(e.target).is('input, textarea, select')) return;

                    switch (e.keyCode) {
                        case 37: // Left arrow - Rewind
                            player.currentTime(Math.max(0, player.currentTime() - 10));
                            e.preventDefault();
                            ADCVideo.utils.log('Keyboard rewind');
                            break;

                        case 39: // Right arrow - Fast forward
                            player.currentTime(Math.min(player.duration(), player.currentTime() + 10));
                            e.preventDefault();
                            ADCVideo.utils.log('Keyboard fast forward');
                            break;

                        case 32: // Spacebar - Play/Pause
                            if (player.paused()) {
                                player.play();
                                ADCVideo.utils.log('Keyboard play');
                            } else {
                                player.pause();
                                ADCVideo.utils.log('Keyboard pause');
                            }
                            e.preventDefault();
                            break;

                        case 70: // F key - Fullscreen
                            if (e.ctrlKey || e.metaKey) return; // Allow Ctrl+F for find

                            if (player.isFullscreen()) {
                                player.exitFullscreen();
                            } else {
                                player.requestFullscreen();
                            }
                            e.preventDefault();
                            ADCVideo.utils.log('Keyboard fullscreen toggle');
                            break;

                        case 77: // M key - Mute
                            if (e.ctrlKey || e.metaKey) return; // Allow Ctrl+M

                            player.muted(!player.muted());
                            e.preventDefault();
                            ADCVideo.utils.log('Keyboard mute toggle');
                            break;
                    }
                });

                // Global escape key handler
                ADCVideo.cache.$document.on('keydown.adc-escape', function (e) {
                    if (e.key === 'Escape' || e.keyCode === 27) {
                        // Close dropdowns
                        $('.adc-wp-programs-dropdown').slideUp(200);
                        $('.dropdown-arrow').css('transform', 'rotate(0deg)');

                        // Cancel autoplay
                        if (ADCVideo.state.countdownInterval) {
                            ADCVideo.autoplay.cancelAutoplay();
                        }

                        // Close any search boxes
                        var searchBox = document.getElementById('adc-search-box');
                        if (searchBox && searchBox.style.display !== 'none') {
                            searchBox.style.display = 'none';
                        }

                        ADCVideo.utils.log('Escape key pressed - closed overlays');
                    }
                });
            },

            bindButtonEvents: function () {
                // Autoplay cancel button
                ADCVideo.cache.$document.on('click.adc-video', '#adc-cancel-autoplay', function (e) {
                    e.preventDefault();
                    ADCVideo.autoplay.cancelAutoplay();
                });

                // Smooth scroll for navigation links
                ADCVideo.cache.$document.on('click.adc-video', '.adc-nav-item', function (e) {
                    var href = this.getAttribute('href');
                    if (href && href.startsWith('#')) {
                        e.preventDefault();
                        var target = $(href);
                        if (target.length) {
                            $('html, body').animate({
                                scrollTop: target.offset().top - 100
                            }, 500);
                            ADCVideo.utils.log('Smooth scroll to: ' + href);
                        }
                    }
                });

                // Enhanced button hover effects
                ADCVideo.cache.$document.on('mouseenter.adc-video', '.adc-back-button, .adc-view-all-button, .adc-view-more-button', function () {
                    $(this).addClass('adc-button-hover');
                }).on('mouseleave.adc-video', '.adc-back-button, .adc-view-all-button, .adc-view-more-button', function () {
                    $(this).removeClass('adc-button-hover');
                });
            },

            bindGeneralEvents: function () {
                // Window resize handler - debounced
                var resizeHandler = ADCVideo.utils.debounce(function () {
                    ADCVideo.utils.log('Window resized, updating layout');

                    // Update player if exists
                    if (ADCVideo.state.player) {
                        ADCVideo.state.player.trigger('resize');
                    }

                    // Close mobile dropdowns on orientation change
                    $('.adc-wp-programs-dropdown').slideUp(200);
                    $('.dropdown-arrow').css('transform', 'rotate(0deg)');
                }, 250);

                ADCVideo.cache.$window.on('resize.adc-video', resizeHandler);

                // Page visibility change handler
                document.addEventListener('visibilitychange', function () {
                    if (document.hidden) {
                        // Pause video when page becomes hidden
                        if (ADCVideo.state.player && !ADCVideo.state.player.paused()) {
                            ADCVideo.state.player.pause();
                            ADCVideo.utils.log('Video paused - page hidden');
                        }
                    }
                });

                // Handle orientation change on mobile
                ADCVideo.cache.$window.on('orientationchange.adc-video', function () {
                    setTimeout(function () {
                        // Close dropdowns after orientation change
                        $('.adc-wp-programs-dropdown').slideUp(200);
                        $('.dropdown-arrow').css('transform', 'rotate(0deg)');

                        ADCVideo.utils.log('Orientation changed - reset UI');
                    }, 100);
                });
            },

            bindLazyLoading: function () {
                // Enhanced lazy loading with Intersection Observer
                if ('IntersectionObserver' in window) {
                    var imageObserver = new IntersectionObserver(function (entries, observer) {
                        entries.forEach(function (entry) {
                            if (entry.isIntersecting) {
                                var image = entry.target;
                                if (image.dataset.src) {
                                    image.src = image.dataset.src;
                                    image.classList.remove('lazy');
                                    image.classList.add('lazy-loaded');
                                    imageObserver.unobserve(image);
                                    ADCVideo.utils.log('Lazy loaded image: ' + image.dataset.src);
                                }
                            }
                        });
                    }, {
                        rootMargin: '50px 0px',
                        threshold: 0.1
                    });

                    // Observe all lazy images
                    var lazyImages = document.querySelectorAll('img.lazy');
                    lazyImages.forEach(function (img) {
                        imageObserver.observe(img);
                    });

                    ADCVideo.utils.log('Lazy loading initialized for ' + lazyImages.length + ' images');
                } else {
                    // Fallback for older browsers
                    var lazyImages = document.querySelectorAll('img.lazy');
                    lazyImages.forEach(function (img) {
                        if (img.dataset.src) {
                            img.src = img.dataset.src;
                            img.classList.remove('lazy');
                            img.classList.add('lazy-loaded');
                        }
                    });
                    ADCVideo.utils.log('Lazy loading fallback applied');
                }
            }
        },

        // Cleanup Module - Handle duplicates and cleanup
        cleanup: {
            initialized: false,

            init: function () {
                if (this.initialized) return;

                ADCVideo.utils.log('Initializing cleanup system');

                this.cleanupDuplicatedResults();
                this.cleanupOldElements();

                this.initialized = true;
            },

            cleanupDuplicatedResults: function () {
                // Check if we're on a search page
                if (window.location.search.indexOf('adc_search=') === -1) return;

                setTimeout(function () {
                    // Remove duplicated search result containers
                    var searchContainers = document.querySelectorAll('.adc-search-results-container');
                    if (searchContainers.length > 1) {
                        ADCVideo.utils.log('Removing ' + (searchContainers.length - 1) + ' duplicate search containers');
                        for (var i = 1; i < searchContainers.length; i++) {
                            if (searchContainers[i].parentNode) {
                                searchContainers[i].parentNode.removeChild(searchContainers[i]);
                            }
                        }
                    }

                    // Remove duplicate recommendation titles
                    var recommendedTitles = document.querySelectorAll('.adc-recommended-title');
                    if (recommendedTitles.length > 1) {
                        ADCVideo.utils.log('Removing ' + (recommendedTitles.length - 1) + ' duplicate recommendation titles');
                        for (var j = 1; j < recommendedTitles.length; j++) {
                            if (recommendedTitles[j].parentNode) {
                                recommendedTitles[j].parentNode.removeChild(recommendedTitles[j]);
                            }
                        }
                    }

                    // Remove redundant "no results" messages
                    var noResultsElements = document.querySelectorAll('.adc-search-no-results');
                    if (noResultsElements.length > 0) {
                        ADCVideo.utils.log('Removing ' + noResultsElements.length + ' redundant no-results messages');
                        noResultsElements.forEach(function (element) {
                            if (element.parentNode) {
                                element.parentNode.removeChild(element);
                            }
                        });
                    }
                }, 500);
            },

            cleanupOldElements: function () {
                // Remove old dropdown arrows that might be orphaned
                $('.dropdown-arrow').each(function () {
                    var $this = $(this);
                    var $parent = $this.closest('li');
                    if (!$parent.length || !$parent.hasClass('adc-programs-menu-trigger')) {
                        $this.remove();
                        ADCVideo.utils.log('Removed orphaned dropdown arrow');
                    }
                });

                // Remove old dropdown containers without proper parent references
                $('.adc-wp-programs-dropdown').each(function () {
                    var $this = $(this);
                    var $parentLi = $this.closest('li');

                    if (!$parentLi.length || !$parentLi.hasClass('adc-programs-menu-trigger')) {
                        $this.remove();
                        ADCVideo.utils.log('Removed orphaned dropdown container');
                    }
                });
            }
        },

        // Analytics Module - Optional tracking
        analytics: {
            track: function (category, action, label) {
                if (typeof gtag !== 'undefined') {
                    gtag('event', action, {
                        'event_category': category,
                        'event_label': label
                    });
                    ADCVideo.utils.log('Analytics tracked: ' + category + ' > ' + action + ' > ' + label);
                }
            },

            trackVideoPlay: function (videoTitle) {
                this.track('Video', 'play', videoTitle);
            },

            trackVideoComplete: function (videoTitle) {
                this.track('Video', 'complete', videoTitle);
            },

            trackSearch: function (searchTerm) {
                this.track('Search', 'search', searchTerm);
            },

            trackProgramView: function (programName) {
                this.track('Program', 'view', programName);
            }
        },

        // Performance Module - Monitor and optimize performance
        performance: {
            startTime: null,

            init: function () {
                this.startTime = performance.now();
                ADCVideo.utils.log('Performance monitoring started');
            },

            logInitTime: function () {
                if (this.startTime) {
                    var elapsed = performance.now() - this.startTime;
                    ADCVideo.utils.log('Total initialization time: ' + elapsed.toFixed(2) + 'ms');
                }
            },

            measureFunction: function (fn, name) {
                return function () {
                    var start = performance.now();
                    var result = fn.apply(this, arguments);
                    var elapsed = performance.now() - start;
                    ADCVideo.utils.log(name + ' executed in ' + elapsed.toFixed(2) + 'ms');
                    return result;
                };
            }
        },

        // Destroy method - Clean shutdown
        destroy: function () {
            ADCVideo.utils.log('Destroying ADC Video instance');

            // Clear intervals
            if (this.state.countdownInterval) {
                clearInterval(this.state.countdownInterval);
                this.state.countdownInterval = null;
            }

            // Dispose video player
            if (this.state.player) {
                try {
                    this.state.player.dispose();
                    this.state.player = null;
                } catch (e) {
                    ADCVideo.utils.log('Error disposing player: ' + e.message, 'warn');
                }
            }

            // Remove event listeners
            this.cache.$document.off('.adc-video .programs-menu .programs-menu-outside .programs-menu-li');
            this.cache.$window.off('.adc-video');

            // Reset state
            this.state.isInitialized = false;
            this.menu.initialized = false;
            this.search.initialized = false;
            this.events.initialized = false;
            this.cleanup.initialized = false;

            ADCVideo.utils.log('ADC Video destroyed successfully');
        }
    };

    // Global utility functions (maintained for backwards compatibility)

    /**
     * Toggle search box function - Enhanced
     */
    window.toggleSearchBox = function () {
        var searchBox = document.getElementById('adc-search-box');
        if (searchBox) {
            if (searchBox.style.display === 'none' || searchBox.style.display === '') {
                searchBox.style.display = 'block';
                var input = searchBox.querySelector('.adc-search-input');
                if (input) {
                    setTimeout(function () { input.focus(); }, 100);
                }
                ADCVideo.utils.log('Search box opened');
            } else {
                searchBox.style.display = 'none';
                ADCVideo.utils.log('Search box closed');
            }
        }
    };

    /**
     * Global slugify function (for external use) - Optimized
     */
    window.slugify = function (text) {
        return ADCVideo.utils.slugify(text);
    };

    /**
     * Enhanced error handling for the entire application
     */
    window.addEventListener('error', function (e) {
        if (e.error) {
            ADCVideo.utils.log('Global error caught: ' + e.error.message, 'error');
        }

        // Don't break the application on errors
        e.preventDefault();
        return true;
    });

    /**
     * Initialize ADC Video - Multiple initialization strategies
     */
    function initializeADCVideo() {
        // Performance monitoring
        ADCVideo.performance.init();

        // Prevent multiple initializations
        if (window.ADCVideoInitialized) {
            ADCVideo.utils.log('Already initialized globally, skipping');
            return;
        }

        console.log('🚀 Starting ADC Video initialization');

        // Get configuration from localized script
        var config = {};
        if (typeof adc_config !== 'undefined') {
            config = {
                autoplayEnabled: adc_config.autoplay === '1',
                autoplayCountdown: parseInt(adc_config.countdown) || 5,
                debug: window.location.search.indexOf('adc_debug=1') !== -1
            };
            ADCVideo.utils.log('Configuration loaded from adc_config');
        } else {
            // Fallback configuration
            config = {
                autoplayEnabled: true,
                autoplayCountdown: 5,
                debug: window.location.search.indexOf('adc_debug=1') !== -1
            };
            ADCVideo.utils.log('Using fallback configuration');

            // Create fallback adc_config for compatibility
            window.adc_config = {
                ajax_url: '/wp-admin/admin-ajax.php',
                nonce: '',
                autoplay: '1',
                countdown: '5'
            };
        }

        // Initialize ADC Video with configuration
        try {
            ADCVideo.init(config);
            window.ADCVideoInitialized = true;
            ADCVideo.performance.logInitTime();
            console.log('✅ ADC Video initialized successfully');
        } catch (error) {
            console.error('❌ Initialization error:', error.message);
            ADCVideo.utils.log('Initialization error: ' + error.message, 'error');
        }
    }

    /**
     * DOM Ready initialization - Enhanced with multiple fallbacks
     */
    function handleDOMReady() {
        if (document.readyState === "interactive" || document.readyState === "complete") {
            console.log('✅ DOM ready, initializing immediately');
            initializeADCVideo();
        } else {
            console.log('⏳ DOM not ready, waiting...');
            // Try again after a short delay
            setTimeout(function () {
                if (document.readyState === "interactive" || document.readyState === "complete") {
                    initializeADCVideo();
                } else {
                    // Final fallback - force init after 2 seconds
                    setTimeout(initializeADCVideo, 2000);
                }
            }, 50);
        }
    }

    /**
     * Multiple initialization strategies for maximum compatibility
     */

    // Strategy 1: Immediate initialization if DOM is already ready
    handleDOMReady();

    // Strategy 2: jQuery document ready (if jQuery is available)
    if (typeof $ !== 'undefined') {
        $(document).ready(function () {
            if (!window.ADCVideoInitialized) {
                console.log('📚 jQuery document ready triggered');
                initializeADCVideo();
            }
        });
    }

    // Strategy 3: Native DOMContentLoaded event
    if (document.addEventListener) {
        document.addEventListener('DOMContentLoaded', function () {
            if (!window.ADCVideoInitialized) {
                console.log('📄 DOMContentLoaded event triggered');
                initializeADCVideo();
            }
        });
    }

    // Strategy 4: Window load event (final fallback)
    window.addEventListener('load', function () {
        if (!window.ADCVideoInitialized) {
            console.log('🔄 Window load event triggered (fallback)');
            initializeADCVideo();
        }
    });

    /**
     * Handle page unload - Cleanup
     */
    window.addEventListener('beforeunload', function () {
        if (window.ADCVideoInitialized && ADCVideo.destroy) {
            ADCVideo.destroy();
        }
    });

    /**
     * Developer tools - Available in console when debug is enabled
     */
    if (window.location.search.indexOf('adc_debug=1') !== -1) {
        window.ADCVideoDebug = {
            getState: function () {
                return ADCVideo.state;
            },
            getConfig: function () {
                return ADCVideo.config;
            },
            getCache: function () {
                return ADCVideo.cache;
            },
            reinit: function () {
                if (ADCVideo.destroy) {
                    ADCVideo.destroy();
                }
                window.ADCVideoInitialized = false;
                initializeADCVideo();
            },
            testMenu: function () {
                ADCVideo.menu.setupProgramsMenu();
            },
            testSearch: function (query) {
                ADCVideo.search.performAjaxSearch(query || 'test', function (results) {
                    console.log('Search results:', results);
                });
            },
            trackEvent: function (category, action, label) {
                ADCVideo.analytics.track(category, action, label);
            },
            forceMenuSetup: function () {
                console.log('🔧 Forcing menu setup...');
                ADCVideo.menu.setupProgramsMenu();
                ADCVideo.menu.configureExistingElements();
                console.log('✅ Menu setup complete');
            }
        };

        console.log('%cADC Video Debug Mode Enabled', 'color: #6EC1E4; font-size: 16px; font-weight: bold;');
        console.log('Available debug functions:', Object.keys(window.ADCVideoDebug));
        console.log('Use ADCVideoDebug.reinit() to reinitialize');
        console.log('Use ADCVideoDebug.getState() to inspect current state');
        console.log('Use ADCVideoDebug.forceMenuSetup() to force menu configuration');
    }

    /**
     * Expose ADC Video to global scope for external access
     */
    window.ADCVideo = ADCVideo;

    /**
     * Legacy support - Maintain backwards compatibility
     */
    window.ADCVideoLegacy = {
        // Legacy function names that might be used elsewhere
        initPlayer: function () {
            if (ADCVideo.player && ADCVideo.player.init) {
                ADCVideo.player.init();
            }
        },
        initMenu: function () {
            if (ADCVideo.menu && ADCVideo.menu.init) {
                ADCVideo.menu.init();
            }
        },
        cancelAutoplay: function () {
            if (ADCVideo.autoplay && ADCVideo.autoplay.cancelAutoplay) {
                ADCVideo.autoplay.cancelAutoplay();
            }
        }
    };

})(jQuery);

/**
 * Standalone functions that don't require jQuery (for compatibility)
 */

/**
 * Enhanced global escape key handler
 */
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' || e.keyCode === 27) {
        // Close search box
        var searchBox = document.getElementById('adc-search-box');
        if (searchBox && searchBox.style.display !== 'none') {
            searchBox.style.display = 'none';
        }

        // Close dropdowns (if jQuery is available)
        if (typeof jQuery !== 'undefined') {
            jQuery('.adc-wp-programs-dropdown').slideUp(200);
            jQuery('.dropdown-arrow').css('transform', 'rotate(0deg)');
        }

        // Close any modal overlays
        var overlays = document.querySelectorAll('.adc-modal-overlay, .adc-popup-overlay');
        overlays.forEach(function (overlay) {
            overlay.style.display = 'none';
        });
    }
});

/**
 * Enhanced console information for developers
 */
if (window.console && window.console.log) {
    console.log('%cADC Video Display v2.1', 'color: #6EC1E4; font-size: 14px; font-weight: bold;');
    console.log('🎥 Enhanced video player with autoplay and custom controls');
    console.log('🔍 Optimized search functionality');
    console.log('📱 Mobile-responsive dropdown menus');
    console.log('⚡ Performance optimized and modular architecture');
    console.log('🔧 Add ?adc_debug=1 to URL for debug mode');
}

/**
 * Feature detection and polyfills
 */
(function () {
    // Check for required features
    var missingFeatures = [];

    if (!window.addEventListener) {
        missingFeatures.push('addEventListener');
    }

    if (!window.JSON) {
        missingFeatures.push('JSON');
    }

    if (!Array.prototype.forEach) {
        missingFeatures.push('Array.forEach');
    }

    if (missingFeatures.length > 0) {
        console.warn('[ADC Video] Missing features detected:', missingFeatures.join(', '));
        console.warn('[ADC Video] Some functionality may not work properly in this browser');
    }

    // Simple polyfill for Array.forEach if missing
    if (!Array.prototype.forEach) {
        Array.prototype.forEach = function (callback, thisArg) {
            for (var i = 0; i < this.length; i++) {
                callback.call(thisArg, this[i], i, this);
            }
        };
    }
})();

/**
 * Performance monitoring (if enabled)
 */
if (window.performance && window.performance.mark) {
    performance.mark('adc-video-script-end');

    // Log script loading time after initialization
    setTimeout(function () {
        try {
            performance.measure('adc-video-script-load', 'adc-video-script-start', 'adc-video-script-end');
            var measure = performance.getEntriesByName('adc-video-script-load')[0];
            if (measure && window.ADCVideo && window.ADCVideo.utils) {
                window.ADCVideo.utils.log('Script loading time: ' + measure.duration.toFixed(2) + 'ms');
            }
        } catch (e) {
            // Ignore performance measurement errors
        }
    }, 1000);
}