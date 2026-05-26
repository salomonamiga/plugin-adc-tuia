/**
 * ADC Video Display - Frontend JavaScript
 * Version: 3.2 - URLs Amigables Dinámicas
 */

(function ($) {
    'use strict';

    // Main ADC Video object - Optimizado con URLs amigables
    window.ADCVideo = {

        // Configuration
        config: {
            debug: false,
            friendlyUrls: true
        },

        // Internal state
        state: {
            player: null,
            isInitialized: false,
            currentLanguage: 'es'
        },

        // Cache for DOM elements
        cache: {
            $document: $(document),
            $window: $(window),
            $body: $('body')
        },

        // Utility functions
        utils: {
            // Single unified slugify function
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

            // Detect language from URL (ES / EN / PT)
            detectLanguage: function () {
                var path = window.location.pathname;
                if (path.indexOf('/en/') !== -1 || path.startsWith('/en')) {
                    return 'en';
                }
                if (path.indexOf('/pt/') !== -1 || path.startsWith('/pt')) {
                    return 'pt';
                }
                return 'es';
            },

            // Build friendly program URL
            buildProgramUrl: function (programSlug, language) {
                language = language || ADCVideo.state.currentLanguage;
                var baseUrl = ADCVideo.utils.getBaseUrl(language);
                // EN usa "program", ES y PT usan "programa"
                var programKeyword = language === 'en' ? 'program' : 'programa';

                return baseUrl + programKeyword + '/' + programSlug + '/';
            },

            // Build friendly video URL
            buildVideoUrl: function (programSlug, videoSlug, language) {
                language = language || ADCVideo.state.currentLanguage;
                var baseUrl = ADCVideo.utils.getBaseUrl(language);
                var programKeyword = language === 'en' ? 'program' : 'programa';

                return baseUrl + programKeyword + '/' + programSlug + '/' + videoSlug + '/';
            },

            // Build friendly search URL
            buildSearchUrl: function (searchTerm, language) {
                language = language || ADCVideo.state.currentLanguage;
                var baseUrl = ADCVideo.utils.getBaseUrl(language);
                // EN usa "search", ES y PT usan "buscar"
                var searchKeyword = language === 'en' ? 'search' : 'buscar';

                return baseUrl + searchKeyword + '/' + encodeURIComponent(searchTerm) + '/';
            },

            // Debounce function
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

            // Get base URL for language
            getBaseUrl: function (language) {
                var baseUrl = window.location.origin + '/';
                if (language === 'en') {
                    baseUrl += 'en/';
                } else if (language === 'pt') {
                    baseUrl += 'pt/';
                }
                return baseUrl;
            },

            // Navigate to friendly URL
            navigateTo: function (url) {
                if (ADCVideo.config.debug) {
                    console.log('ADC: Navigating to friendly URL:', url);
                }
                window.location.href = url;
            }
        },

        // Initialize - Main entry point
        init: function (options) {
            if (this.state.isInitialized) {
                return;
            }

            // Detect current language
            this.state.currentLanguage = this.utils.detectLanguage();

            // Merge options with defaults
            if (options) {
                this.config = $.extend(this.config, options);
            }

            // Initialize all modules
            this.player.init();
            this.menu.init();
            this.search.init();
            this.events.init();

            this.state.isInitialized = true;
        },

        // Video Player Module
        // El VOD vive dentro de un iframe Radiant — el plugin no inicializa
        // ningun player. El bridge radiant-bridge.js escucha postMessage
        // 'goto-next' del iframe para redirigir al siguiente video.
        player: {
            init: function () {}
        },

        // Menu Module - OPTIMIZADO con URLs amigables
        menu: {
            initialized: false,
            observer: null,

            init: function () {
                if (this.initialized) return;

                this.initProgramsMenu();

                this.initialized = true;
            },

            initProgramsMenu: function () {
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

                    if (!$dropdown || !$arrow) {
                        return false;
                    }

                    if (!$.contains(document, $dropdown[0]) || !$.contains(document, $arrow[0])) {
                        return false;
                    }

                    return true;
                }

                // Función para configurar un elemento PROGRAMAS
                function setupProgramsElement($programasLink, language) {
                    var $parentLi = $programasLink.closest('li');
                    if (!$parentLi.length) {
                        $parentLi = $programasLink.parent();
                    }

                    // Limpiar configuración anterior si existe
                    $parentLi.find('.adc-wp-programs-dropdown').remove();
                    $programasLink.find('.dropdown-arrow').remove();
                    $programasLink.removeData('dropdown arrow programs-configured language');

                    // Configurar el contenedor padre
                    $parentLi.css({
                        'position': 'relative',
                        'z-index': '999'
                    });

                    // Crear el dropdown
                    var $dropdown = $('<div class="adc-wp-programs-dropdown" data-language="' + language + '"></div>');
                    $parentLi.append($dropdown);
                    $dropdown.html('<div class="adc-loading">Cargando programas...</div>');
                    $dropdown.hide();

                    // Añadir flecha CON TODOS LOS ESTILOS ORIGINALES
                    var $arrow = $('<span class="dropdown-arrow" style="color:#6EC1E4; margin-left:5px; vertical-align:middle; transition:transform 0.3s ease; display:inline-block;">▾</span>');
                    $programasLink.append($arrow);

                    // Guardar referencias en el elemento
                    $programasLink.data('dropdown', $dropdown);
                    $programasLink.data('arrow', $arrow);
                    $programasLink.data('programs-configured', true);
                    $programasLink.data('language', language);
                }

                // Función para cargar programas en el dropdown
                function loadPrograms($dropdown, language) {
                    if ($dropdown.data('programs-loaded')) {
                        return;
                    }

                    var ajaxUrl = typeof adc_config !== 'undefined' ? adc_config.ajax_url : '/wp-admin/admin-ajax.php';
                    var nonce = typeof adc_config !== 'undefined' ? adc_config.nonce : '';

                    $.ajax({
                        url: ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'adc_get_programs_menu',
                            language: language,
                            nonce: nonce
                        },
                        success: function (response) {
                            var programs = [];

                            // Compatibilidad con ambas estructuras
                            if (response.success) {
                                if (Array.isArray(response.data)) {
                                    programs = response.data;
                                } else if (response.data && Array.isArray(response.data.programs)) {
                                    programs = response.data.programs;
                                }
                            }

                            if (programs.length > 0) {
                                var html = '';

                                $.each(programs, function (i, program) {
                                    // Use friendly URLs for programs
                                    var programSlug = ADCVideo.utils.slugify(program.name);
                                    var url = ADCVideo.utils.buildProgramUrl(programSlug, language);

                                    html += '<a href="' + url + '" style="display:block !important; padding:12px 20px !important; color:#6EC1E4 !important; text-decoration:none !important; border-bottom:1px solid rgba(110, 193, 228, 0.1) !important; font-size:18px !important; line-height:1.3 !important; font-weight:500 !important; font-family:inherit !important; white-space:normal !important; word-wrap:break-word !important; max-width:300px !important; overflow-wrap:break-word !important;">' + program.name + '</a>';
                                });

                                $dropdown.html(html);
                                $dropdown.data('programs-loaded', true);
                            } else {
                                var fallbackMsgs = {
                                    es: 'No hay programas disponibles',
                                    en: 'No programs available',
                                    pt: 'Não há programas disponíveis'
                                };
                                var errorMsg = fallbackMsgs[language] || fallbackMsgs.es;
                                if (response.data && response.data.message) {
                                    errorMsg = response.data.message;
                                }
                                $dropdown.html('<div class="adc-error" style="padding:20px; color:red; text-align:center;">' + errorMsg + '</div>');
                            }
                        }
                    });
                }

                // Función para toggle del dropdown
                function toggleDropdown($programasLink) {
                    var $dropdown = $programasLink.data('dropdown');
                    var $arrow = $programasLink.data('arrow');
                    var language = $programasLink.data('language');

                    if (!$dropdown || !$arrow) {
                        return;
                    }

                    // Cerrar otros dropdowns primero
                    $('.adc-wp-programs-dropdown').not($dropdown).slideUp(200);
                    $('.dropdown-arrow').not($arrow).css('transform', 'rotate(0deg)');

                    var isVisible = $dropdown.is(':visible');

                    // Toggle del dropdown actual
                    $dropdown.slideToggle(200);

                    if (isVisible) {
                        $arrow.css('transform', 'rotate(0deg)');
                    } else {
                        $arrow.css('transform', 'rotate(180deg)');

                        if ($dropdown.find('.adc-loading, .adc-error').length) {
                            loadPrograms($dropdown, language);
                        }
                    }
                }

                // Configurar elementos existentes al inicializar - ES / EN / PT
                // Español
                $('a:contains("PROGRAMAS_ES"), .adc-programs-menu-trigger a').each(function () {
                    var $this = $(this);
                    if ($this.text().trim() === 'PROGRAMAS_ES' || $this.hasClass('adc-programs-menu-trigger')) {
                        $this.text('PROGRAMAS');
                        setupProgramsElement($this, 'es');
                    }
                });

                // Inglés
                $('a:contains("PROGRAMAS_EN"), .adc-programs-menu-trigger-en a').each(function () {
                    var $this = $(this);
                    if ($this.text().trim() === 'PROGRAMAS_EN' || $this.hasClass('adc-programs-menu-trigger-en')) {
                        $this.text('PROGRAMS');
                        setupProgramsElement($this, 'en');
                    }
                });

                // Portugués
                $('a:contains("PROGRAMAS_PT"), .adc-programs-menu-trigger-pt a').each(function () {
                    var $this = $(this);
                    if ($this.text().trim() === 'PROGRAMAS_PT' || $this.hasClass('adc-programs-menu-trigger-pt')) {
                        $this.text('PROGRAMAS');
                        setupProgramsElement($this, 'pt');
                    }
                });

                // Usar delegación de eventos para manejar clicks
                $(document).on('click.programs-menu', 'a', function (e) {
                    var $this = $(this);
                    var text = $this.text().trim();
                    var language = null;

                    // Detectar idioma por texto o clase - ES / EN / PT
                    if (text === 'PROGRAMAS_ES' || $this.parent().hasClass('adc-programs-menu-trigger')) {
                        language = 'es';
                    } else if (text === 'PROGRAMS' || text === 'PROGRAMAS_EN' || $this.parent().hasClass('adc-programs-menu-trigger-en')) {
                        language = 'en';
                    } else if (text === 'PROGRAMAS_PT' || $this.parent().hasClass('adc-programs-menu-trigger-pt')) {
                        language = 'pt';
                    } else if (text === 'PROGRAMAS') {
                        // Disambiguación por URL si solo dice "PROGRAMAS" (ES o PT)
                        language = ADCVideo.utils.detectLanguage() === 'pt' ? 'pt' : 'es';
                    }

                    if (language) {
                        e.preventDefault();
                        e.stopPropagation();

                        if (!$this.data('programs-configured') || !isProperlyConfigured($this)) {
                            // Actualizar texto si es necesario
                            if (text === 'PROGRAMAS_ES') $this.text('PROGRAMAS');
                            else if (text === 'PROGRAMAS_EN') $this.text('PROGRAMS');

                            setupProgramsElement($this, language);
                        }

                        toggleDropdown($this);
                    }
                });

                // Cerrar dropdowns al hacer click fuera
                $(document).on('click.programs-menu-outside', function (e) {
                    if (!$(e.target).closest('.adc-wp-programs-dropdown, a:contains("PROGRAMAS"), a:contains("PROGRAMS"), .adc-programs-menu-trigger, .adc-programs-menu-trigger-en').length) {
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

                // MutationObserver para detectar cambios en el DOM
                this.observer = new MutationObserver(function (mutations) {
                    mutations.forEach(function (mutation) {
                        if (mutation.type === 'childList') {
                            $(mutation.addedNodes).find('a').each(function () {
                                var $this = $(this);
                                var text = $this.text().trim();

                                if (text === 'PROGRAMAS_ES' || text === 'PROGRAMAS_EN') {
                                    var language = 'es';
                                    if (text === 'PROGRAMAS_EN') {
                                        language = 'en';
                                        $this.text('PROGRAMS');
                                    } else {
                                        $this.text('PROGRAMAS');
                                    }

                                    if (!$this.data('programs-configured')) {
                                        setupProgramsElement($this, language);
                                    }
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
            },

        },

        // Search Module - UPDATED with friendly URLs
        search: {
            initialized: false,

            init: function () {
                if (this.initialized) return;

                this.setupSearchIcon();
                this.removeAutofocus();
                this.bindSearchEvents();

                this.initialized = true;
            },

            setupSearchIcon: function () {
                var self = this;

                $('a').each(function () {
                    var $searchLink = $(this);
                    var text = $searchLink.text().trim();

                    if ($searchLink.data('search-initialized')) {
                        return;
                    }

                    var language = null;
                    var placeholderText = '';

                    // Detectar idioma - ES / EN / PT
                    if (text === 'BUSCADOR_ES' || $searchLink.parent().hasClass('adc-search-menu-trigger')) {
                        language = 'es';
                        placeholderText = 'Buscar...';
                    } else if (text === 'BUSCADOR_EN' || text === 'SEARCH' || $searchLink.parent().hasClass('adc-search-menu-trigger-en')) {
                        language = 'en';
                        placeholderText = 'Search...';
                    } else if (text === 'BUSCADOR_PT' || $searchLink.parent().hasClass('adc-search-menu-trigger-pt')) {
                        language = 'pt';
                        placeholderText = 'Pesquisar...';
                    } else if (text === 'BUSCADOR') {
                        // Disambiguación por URL si solo dice "BUSCADOR" (ES o PT)
                        language = ADCVideo.utils.detectLanguage() === 'pt' ? 'pt' : 'es';
                        placeholderText = language === 'pt' ? 'Pesquisar...' : 'Buscar...';
                    }

                    if (!language) {
                        return;
                    }

                    $searchLink.addClass('adc-search-menu-trigger-' + language);

                    var $parentLi = $searchLink.closest('li');
                    if (!$parentLi.length) {
                        $parentLi = $searchLink.parent();
                    }

                    var homeUrl = ADCVideo.utils.getBaseUrl(language);

                    // Create enhanced search form
                    var $searchContainer = $('<div class="adc-menu-search-container"></div>');
                    var $searchForm = $('<form class="adc-inline-search-form" action="' + homeUrl + '" method="get" data-language="' + language + '"></form>');
                    var $searchInput = $('<input type="text" name="adc_search_term" placeholder="' + placeholderText + '" class="adc-inline-search-input">');
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
            },

            removeAutofocus: function () {
                setTimeout(function () {
                    document.querySelectorAll('.adc-inline-search-input').forEach(function (input) {
                        if (document.activeElement === input) {
                            input.blur();
                        }
                        input.removeAttribute('autofocus');
                    });
                }, 100);
            },

            bindSearchEvents: function () {
                // Handle form submission with friendly URLs
                ADCVideo.cache.$document.on('submit', '.adc-search-form, .adc-inline-search-form', function (e) {
                    e.preventDefault();

                    var $form = $(this);
                    var $input = $form.find('input[name="adc_search_term"], input[name="adc_search"]');
                    var searchTerm = $input.val().trim();

                    if (searchTerm === '') {
                        $input.focus();
                        return false;
                    }

                    // Navigate to friendly search URL
                    var language = $form.attr('data-language') || ADCVideo.state.currentLanguage;
                    var searchUrl = ADCVideo.utils.buildSearchUrl(searchTerm, language);

                    if (ADCVideo.config.debug) {
                        console.log('ADC: Redirecting to search URL:', searchUrl);
                    }

                    ADCVideo.utils.navigateTo(searchUrl);
                    return false;
                });

                // Focus/blur styling effects
                ADCVideo.cache.$document.on('focus', '.adc-inline-search-input', function () {
                    var $form = $(this).closest('.adc-inline-search-form');
                    $form.addClass('adc-search-focused');
                });

                ADCVideo.cache.$document.on('blur', '.adc-inline-search-input', function () {
                    var $form = $(this).closest('.adc-inline-search-form');
                    $form.removeClass('adc-search-focused');
                });
            }
        },

        // Events Module
        events: {
            initialized: false,

            init: function () {
                if (this.initialized) return;

                this.bindKeyboardEvents();
                this.bindButtonEvents();
                this.bindGeneralEvents();
                this.bindFriendlyUrlEvents();

                this.initialized = true;
            },

            bindKeyboardEvents: function () {
                ADCVideo.cache.$document.on('keydown.adc-video', function (e) {
                    var player = ADCVideo.state.player;
                    if (!player) return;

                    if ($(e.target).is('input, textarea, select')) return;

                    switch (e.keyCode) {
                        case 37: // Left arrow
                            player.currentTime(Math.max(0, player.currentTime() - 10));
                            e.preventDefault();
                            break;

                        case 39: // Right arrow
                            player.currentTime(Math.min(player.duration(), player.currentTime() + 10));
                            e.preventDefault();
                            break;

                        case 32: // Spacebar
                            if (player.paused()) {
                                player.play();
                            } else {
                                player.pause();
                            }
                            e.preventDefault();
                            break;

                        case 70: // F key
                            if (e.ctrlKey || e.metaKey) return;

                            if (player.isFullscreen()) {
                                player.exitFullscreen();
                            } else {
                                player.requestFullscreen();
                            }
                            e.preventDefault();
                            break;

                        case 77: // M key
                            if (e.ctrlKey || e.metaKey) return;

                            player.muted(!player.muted());
                            e.preventDefault();
                            break;
                    }
                });

                ADCVideo.cache.$document.on('keydown.adc-escape', function (e) {
                    if (e.key === 'Escape' || e.keyCode === 27) {
                        $('.adc-wp-programs-dropdown').slideUp(200);
                        $('.dropdown-arrow').css('transform', 'rotate(0deg)');
                    }
                });
            },

            bindButtonEvents: function () {
                ADCVideo.cache.$document.on('click.adc-video', '.adc-nav-item', function (e) {
                    var href = this.getAttribute('href');
                    if (href && href.startsWith('#')) {
                        e.preventDefault();
                        var target = $(href);
                        if (target.length) {
                            $('html, body').animate({
                                scrollTop: target.offset().top - 100
                            }, 500);
                        }
                    }
                });

                ADCVideo.cache.$document.on('mouseenter.adc-video', '.adc-back-button, .adc-view-all-button, .adc-view-more-button', function () {
                    $(this).addClass('adc-button-hover');
                }).on('mouseleave.adc-video', '.adc-back-button, .adc-view-all-button, .adc-view-more-button', function () {
                    $(this).removeClass('adc-button-hover');
                });
            },

            bindGeneralEvents: function () {
                var resizeHandler = ADCVideo.utils.debounce(function () {
                    if (ADCVideo.state.player) {
                        ADCVideo.state.player.trigger('resize');
                    }

                    $('.adc-wp-programs-dropdown').slideUp(200);
                    $('.dropdown-arrow').css('transform', 'rotate(0deg)');
                }, 250);

                ADCVideo.cache.$window.on('resize.adc-video', resizeHandler);

                ADCVideo.events.visibilityHandler = function () {
                    if (document.hidden) {
                        if (ADCVideo.state.player && !ADCVideo.state.player.paused()) {
                            ADCVideo.state.player.pause();
                        }
                    }
                };
                document.addEventListener('visibilitychange', ADCVideo.events.visibilityHandler);

                ADCVideo.cache.$window.on('orientationchange.adc-video', function () {
                    setTimeout(function () {
                        $('.adc-wp-programs-dropdown').slideUp(200);
                        $('.dropdown-arrow').css('transform', 'rotate(0deg)');
                    }, 100);
                });
            },

            // Bind events for friendly URL navigation
            bindFriendlyUrlEvents: function () {
                // Intercept category card clicks to use friendly URLs
                ADCVideo.cache.$document.on('click.adc-friendly', '.adc-category-card', function (e) {
                    var href = this.getAttribute('href');
                    if (href && !href.startsWith('http') && !href.startsWith('//')) {
                        // It's already a friendly URL, let it proceed normally
                        return true;
                    }

                    // Handle old-style URLs if any exist
                    var urlParams = new URLSearchParams(href.split('?')[1] || '');
                    var categoria = urlParams.get('categoria');

                    if (categoria) {
                        e.preventDefault();
                        var friendlyUrl = ADCVideo.utils.buildProgramUrl(categoria, ADCVideo.state.currentLanguage);
                        ADCVideo.utils.navigateTo(friendlyUrl);
                        return false;
                    }
                });

                // Intercept video card clicks to use friendly URLs
                ADCVideo.cache.$document.on('click.adc-friendly', '.adc-video-link', function (e) {
                    var href = this.getAttribute('href');
                    if (href && !href.startsWith('http') && !href.startsWith('//')) {
                        // It's already a friendly URL, let it proceed normally
                        return true;
                    }

                    // Handle old-style URLs if any exist
                    var urlParams = new URLSearchParams(href.split('?')[1] || '');
                    var categoria = urlParams.get('categoria');
                    var video = urlParams.get('video');

                    if (categoria && video) {
                        e.preventDefault();
                        var friendlyUrl = ADCVideo.utils.buildVideoUrl(categoria, video, ADCVideo.state.currentLanguage);
                        ADCVideo.utils.navigateTo(friendlyUrl);
                        return false;
                    }
                });

                // Handle back button clicks to ensure friendly URLs
                ADCVideo.cache.$document.on('click.adc-friendly', '.adc-back-button, .adc-back-program-button', function (e) {
                    var href = this.getAttribute('href');

                    // If it's already a friendly URL or absolute URL, let it proceed
                    if (!href || href.startsWith('http') || href.startsWith('//') || href.indexOf('?') === -1) {
                        return true;
                    }

                    // Handle old-style URLs
                    var urlParams = new URLSearchParams(href.split('?')[1] || '');
                    var categoria = urlParams.get('categoria');

                    if (categoria) {
                        e.preventDefault();
                        var friendlyUrl = ADCVideo.utils.buildProgramUrl(categoria, ADCVideo.state.currentLanguage);
                        ADCVideo.utils.navigateTo(friendlyUrl);
                        return false;
                    } else {
                        // Back to home
                        e.preventDefault();
                        var homeUrl = ADCVideo.utils.getBaseUrl(ADCVideo.state.currentLanguage);
                        ADCVideo.utils.navigateTo(homeUrl);
                        return false;
                    }
                });

                // Handle "view all" and similar navigation buttons
                ADCVideo.cache.$document.on('click.adc-friendly', '.adc-view-all-button:not(.adc-view-next-video)', function (e) {
                    var href = this.getAttribute('href');

                    // If it's already a friendly URL or doesn't have query params, let it proceed
                    if (!href || href.indexOf('?categoria=') === -1) {
                        return true;
                    }

                    // Handle old-style category URLs
                    var urlParams = new URLSearchParams(href.split('?')[1] || '');
                    var categoria = urlParams.get('categoria');

                    if (categoria) {
                        e.preventDefault();
                        var friendlyUrl = ADCVideo.utils.buildProgramUrl(categoria, ADCVideo.state.currentLanguage);
                        ADCVideo.utils.navigateTo(friendlyUrl);
                        return false;
                    }
                });
            }
        },

        // Destroy method
        destroy: function () {
            if (this.state.player) {
                try {
                    this.state.player.dispose();
                    this.state.player = null;
                } catch (e) {
                    // Ignore errors
                }
            }

            ['.adc-video', '.programs-menu', '.programs-menu-outside', '.adc-friendly'].forEach(function (ns) {
                this.cache.$document.off(ns);
            }.bind(this));
            this.cache.$window.off('.adc-video');

            if (ADCVideo.events.visibilityHandler) {
                document.removeEventListener('visibilitychange', ADCVideo.events.visibilityHandler);
                ADCVideo.events.visibilityHandler = null;
            }

            if (this.menu.observer) {
                this.menu.observer.disconnect();
                this.menu.observer = null;
            }

            this.state.isInitialized = false;
            this.menu.initialized = false;
            this.search.initialized = false;
            this.events.initialized = false;
        }
    };


    // Initialize ADC Video
    function initializeADCVideo() {
        if (window.ADCVideoInitialized) {
            return;
        }

        var config = {};
        if (typeof adc_config !== 'undefined') {
            config = {
                debug: adc_config.debug === true || adc_config.debug === '1',
                friendlyUrls: adc_config.friendly_urls === true || adc_config.friendly_urls === '1'
            };
        }

        try {
            ADCVideo.init(config);
            window.ADCVideoInitialized = true;

            if (config.debug) {
                console.log('⚡ ADC Video.js: Initialized ✅');
                console.log('└── 🎯 Friendly URLs: Active for', ADCVideo.state.currentLanguage);
            }
        } catch (error) {
            if (config.debug) {
                console.error('ADC Video initialization error:', error);
            }
        }
    }

    $(document).ready(function () {
        if (!window.ADCVideoInitialized) {
            initializeADCVideo();
        }
    });

    // Expose ADC Video to global scope
    window.ADCVideo = ADCVideo;

})(jQuery);