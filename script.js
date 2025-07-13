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
            autoplayEnabled: true,
            autoplayCountdown: 5,
            playerVolume: 0.5,
            debug: false,
            cacheEnabled: true,
            friendlyUrls: true
        },

        // Internal state
        state: {
            player: null,
            countdownInterval: null,
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

            // Detect language from URL (only ES/EN)
            detectLanguage: function () {
                var path = window.location.pathname;
                if (path.indexOf('/en/') !== -1 || path.startsWith('/en')) {
                    return 'en';
                }
                return 'es';
            },

            // Validate language (only ES/EN)
            validateLanguage: function (language) {
                var validLanguages = ['es', 'en'];
                return validLanguages.indexOf(language) !== -1 ? language : 'es';
            },

            // NEW: Build friendly program URL
            buildProgramUrl: function (programSlug, language) {
                language = language || ADCVideo.state.currentLanguage;
                var baseUrl = ADCVideo.utils.getBaseUrl(language);
                var programKeyword = language === 'en' ? 'program' : 'programa';

                return baseUrl + programKeyword + '/' + programSlug + '/';
            },

            // NEW: Build friendly video URL
            buildVideoUrl: function (programSlug, videoSlug, language) {
                language = language || ADCVideo.state.currentLanguage;
                var baseUrl = ADCVideo.utils.getBaseUrl(language);
                var programKeyword = language === 'en' ? 'program' : 'programa';

                return baseUrl + programKeyword + '/' + programSlug + '/' + videoSlug + '/';
            },

            // NEW: Build friendly search URL
            buildSearchUrl: function (searchTerm, language) {
                language = language || ADCVideo.state.currentLanguage;
                var baseUrl = ADCVideo.utils.getBaseUrl(language);
                var searchKeyword = language === 'en' ? 'search' : 'buscar';

                return baseUrl + searchKeyword + '/' + encodeURIComponent(searchTerm) + '/';
            },

            // Format duration from seconds to MM:SS
            formatDuration: function (seconds) {
                if (!seconds) return '';
                var minutes = Math.floor(seconds / 60);
                var remainingSeconds = seconds % 60;
                return minutes + ':' + (remainingSeconds < 10 ? '0' : '') + remainingSeconds;
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

            // Get URL parameter (for legacy support if needed)
            getUrlParam: function (param) {
                var urlParams = new URLSearchParams(window.location.search);
                return urlParams.get(param);
            },

            // Get base URL for language
            getBaseUrl: function (language) {
                var baseUrl = window.location.origin + '/';
                if (language === 'en') {
                    baseUrl += 'en/';
                }
                return baseUrl;
            },

            // NEW: Parse current friendly URL
            parseCurrentUrl: function () {
                var path = window.location.pathname;
                var parts = path.split('/').filter(function (part) { return part.length > 0; });

                var result = {
                    language: 'es',
                    type: 'home',
                    program: null,
                    video: null,
                    search: null
                };

                var offset = 0;

                // Check for language
                if (parts[0] === 'en') {
                    result.language = 'en';
                    offset = 1;
                }

                // Check for type
                if (parts[offset]) {
                    var action = parts[offset];

                    if ((action === 'programa' && result.language === 'es') ||
                        (action === 'program' && result.language === 'en')) {
                        result.type = 'program';

                        if (parts[offset + 1]) {
                            result.program = parts[offset + 1];

                            if (parts[offset + 2]) {
                                result.type = 'video';
                                result.video = parts[offset + 2];
                            }
                        }
                    } else if ((action === 'buscar' && result.language === 'es') ||
                        (action === 'search' && result.language === 'en')) {
                        result.type = 'search';

                        if (parts[offset + 1]) {
                            result.search = decodeURIComponent(parts[offset + 1]);
                        }
                    }
                }

                return result;
            },

            // NEW: Navigate to friendly URL
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

            if (this.config.debug) {
                console.log('ADC Video initialized with friendly URLs for language:', this.state.currentLanguage);
            }
        },

        // Video Player Module
        player: {
            init: function () {
                if (typeof videojs === 'undefined' || !document.getElementById('adc-player')) {
                    return;
                }

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

                // Rewind button
                var RewindButton = videojs.extend(Button, {
                    constructor: function () {
                        Button.apply(this, arguments);
                        this.controlText('Retroceder 10 segundos');
                        this.addClass('vjs-rewind-button');
                    },
                    handleClick: function () {
                        var currentTime = player.currentTime();
                        player.currentTime(Math.max(0, currentTime - 10));
                    }
                });

                // Forward button
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

        // Autoplay Module - UPDATED with friendly URLs
        autoplay: {
            handleVideoEnded: function () {
                var nextUrl = this.getNextVideoUrl();
                if (!nextUrl) {
                    return;
                }

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
                        ADCVideo.utils.navigateTo(nextUrl);
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
            }
        },

        // Menu Module - OPTIMIZADO con URLs amigables
        menu: {
            initialized: false,
            observer: null,

            init: function () {
                if (this.initialized) return;

                this.initProgramsMenu();
                this.setupSearchReplacements();

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
                                    // NEW: Use friendly URLs for programs
                                    var programSlug = ADCVideo.utils.slugify(program.name);
                                    var url = ADCVideo.utils.buildProgramUrl(programSlug, language);

                                    html += '<a href="' + url + '" style="display:block !important; padding:12px 20px !important; color:#6EC1E4 !important; text-decoration:none !important; border-bottom:1px solid rgba(110, 193, 228, 0.1) !important; font-size:18px !important; line-height:1.3 !important; font-weight:500 !important; font-family:inherit !important; white-space:normal !important; word-wrap:break-word !important; max-width:300px !important; overflow-wrap:break-word !important;">' + program.name + '</a>';
                                });

                                $dropdown.html(html);
                                $dropdown.data('programs-loaded', true);
                            } else {
                                var errorMsg = 'No hay programas disponibles';
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

                // Configurar elementos existentes al inicializar - Solo ES/EN
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

                // Usar delegación de eventos para manejar clicks
                $(document).on('click.programs-menu', 'a', function (e) {
                    var $this = $(this);
                    var text = $this.text().trim();
                    var language = null;

                    // Detectar idioma por texto o clase - Solo ES/EN
                    if (text === 'PROGRAMAS' || text === 'PROGRAMAS_ES' || $this.parent().hasClass('adc-programs-menu-trigger')) {
                        language = 'es';
                    } else if (text === 'PROGRAMS' || text === 'PROGRAMAS_EN' || $this.parent().hasClass('adc-programs-menu-trigger-en')) {
                        language = 'en';
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

            setupSearchReplacements: function () {
                // Buscar elementos BUSCADOR y reemplazarlos con formulario de búsqueda - Solo ES/EN
                document.querySelectorAll('a').forEach(function (link) {
                    var text = link.textContent.trim();
                    var language = 'es';
                    var placeholderText = 'Buscar...';

                    // Detectar idioma y configurar texto - Solo ES/EN
                    if (text === 'BUSCADOR_ES' || link.classList.contains('adc-search-menu-trigger')) {
                        language = 'es';
                        placeholderText = 'Buscar...';
                    } else if (text === 'BUSCADOR_EN' || link.classList.contains('adc-search-menu-trigger-en')) {
                        language = 'en';
                        placeholderText = 'Search...';
                    } else {
                        return; // No es un link de búsqueda
                    }

                    var searchContainer = document.createElement('div');
                    searchContainer.className = 'adc-menu-search-container';

                    // NEW: Use friendly URL for search form action
                    var homeUrl = ADCVideo.utils.getBaseUrl(language);

                    searchContainer.innerHTML =
                        '<form class="adc-inline-search-form" action="' + homeUrl + '" method="get" data-language="' + language + '">' +
                        '<input type="text" name="adc_search_term" placeholder="' + placeholderText + '" class="adc-inline-search-input">' +
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
                });
            }
        },

        // Search Module - UPDATED with friendly URLs
        search: {
            initialized: false,

            init: function () {
                if (this.initialized) return;

                this.setupSearchForms();
                this.setupSearchIcon();
                this.removeAutofocus();
                this.bindSearchEvents();

                this.initialized = true;
            },

            setupSearchForms: function () {
                var forms = document.querySelectorAll('.adc-search-form, .adc-inline-search-form');

                forms.forEach(function (form) {
                    var input = form.querySelector('input[name="adc_search_term"], input[name="adc_search"]');
                    if (!input) return;

                    // Handle form submission with friendly URLs
                    form.addEventListener('submit', function (e) {
                        var searchTerm = input.value.trim();
                        if (searchTerm === '') {
                            e.preventDefault();
                            input.focus();
                            return false;
                        }

                        // NEW: Redirect to friendly search URL instead of form submission
                        e.preventDefault();
                        var language = form.getAttribute('data-language') || ADCVideo.state.currentLanguage;
                        var searchUrl = ADCVideo.utils.buildSearchUrl(searchTerm, language);
                        ADCVideo.utils.navigateTo(searchUrl);
                        return false;
                    });
                });
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

                    // Detectar idioma - Solo ES/EN
                    if (text === 'BUSCADOR_ES' || text === 'BUSCADOR' || $searchLink.parent().hasClass('adc-search-menu-trigger')) {
                        language = 'es';
                        placeholderText = 'Buscar...';
                    } else if (text === 'BUSCADOR_EN' || text === 'SEARCH' || $searchLink.parent().hasClass('adc-search-menu-trigger-en')) {
                        language = 'en';
                        placeholderText = 'Search...';
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
                // UPDATED: Handle form submission with friendly URLs
                ADCVideo.cache.$document.on('submit', '.adc-search-form, .adc-inline-search-form', function (e) {
                    e.preventDefault();

                    var $form = $(this);
                    var $input = $form.find('input[name="adc_search_term"], input[name="adc_search"]');
                    var searchTerm = $input.val().trim();

                    if (searchTerm === '') {
                        $input.focus();
                        return false;
                    }

                    // NEW: Navigate to friendly search URL
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

                        if (ADCVideo.state.countdownInterval) {
                            ADCVideo.autoplay.cancelAutoplay();
                        }
                    }
                });
            },

            bindButtonEvents: function () {
                ADCVideo.cache.$document.on('click.adc-video', '#adc-cancel-autoplay', function (e) {
                    e.preventDefault();
                    ADCVideo.autoplay.cancelAutoplay();
                });

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

                document.addEventListener('visibilitychange', function () {
                    if (document.hidden) {
                        if (ADCVideo.state.player && !ADCVideo.state.player.paused()) {
                            ADCVideo.state.player.pause();
                        }
                    }
                });

                ADCVideo.cache.$window.on('orientationchange.adc-video', function () {
                    setTimeout(function () {
                        $('.adc-wp-programs-dropdown').slideUp(200);
                        $('.dropdown-arrow').css('transform', 'rotate(0deg)');
                    }, 100);
                });
            },

            // NEW: Bind events for friendly URL navigation
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
            if (this.state.countdownInterval) {
                clearInterval(this.state.countdownInterval);
                this.state.countdownInterval = null;
            }

            if (this.state.player) {
                try {
                    this.state.player.dispose();
                    this.state.player = null;
                } catch (e) {
                    // Ignore errors
                }
            }

            this.cache.$document.off('.adc-video .programs-menu .programs-menu-outside .programs-menu-li .adc-friendly');
            this.cache.$window.off('.adc-video');

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
                autoplayEnabled: adc_config.autoplay === '1',
                autoplayCountdown: parseInt(adc_config.countdown) || 5,
                cacheEnabled: adc_config.cache_enabled === true || adc_config.cache_enabled === '1',
                debug: adc_config.debug === true || adc_config.debug === '1',
                friendlyUrls: adc_config.friendly_urls === true || adc_config.friendly_urls === '1'
            };
        }

        try {
            ADCVideo.init(config);
            window.ADCVideoInitialized = true;

            if (config.debug) {
                console.log('ADC Video Display initialized with friendly URLs support');
                console.log('Current language detected:', ADCVideo.state.currentLanguage);
                console.log('Current URL structure:', ADCVideo.utils.parseCurrentUrl());
            }
        } catch (error) {
            if (config.debug) {
                console.error('ADC Video initialization error:', error);
            }
        }
    };

    // Multiple initialization strategies
    function handleDOMReady() {
        if (document.readyState === "interactive" || document.readyState === "complete") {
            initializeADCVideo();
        } else {
            setTimeout(function () {
                if (document.readyState === "interactive" || document.readyState === "complete") {
                    initializeADCVideo();
                } else {
                    setTimeout(initializeADCVideo, 2000);
                }
            }, 50);
        }
    }

    // Initialize
    handleDOMReady();

    if (typeof $ !== 'undefined') {
        $(document).ready(function () {
            if (!window.ADCVideoInitialized) {
                initializeADCVideo();
            }
        });
    }

    if (document.addEventListener) {
        document.addEventListener('DOMContentLoaded', function () {
            if (!window.ADCVideoInitialized) {
                initializeADCVideo();
            }
        });
    }

    window.addEventListener('load', function () {
        if (!window.ADCVideoInitialized) {
            initializeADCVideo();
        }
    });

    // Expose ADC Video to global scope
    window.ADCVideo = ADCVideo;

})(jQuery);