/**
 * ADC Video Display - Frontend JavaScript
 * Version: 2.0
 */

(function ($) {
    'use strict';

    // Main ADC Video object
    window.ADCVideo = {

        // Configuration
        config: {
            autoplayEnabled: true,
            autoplayCountdown: 5,
            playerVolume: 0.5
        },

        // Initialize
        init: function (options) {
            // Merge options with defaults
            if (options) {
                this.config = Object.assign(this.config, options);
            }

            // Initialize components
            this.initPlayer();
            this.bindEvents();
            this.initDropdownMenu();
            this.initSearchForm();

            // Initialize programs menu
            this.initProgramsMenu();

            // Initialize search icon
            this.initSearchIcon();

            // Cleanup duplicated search results
            this.cleanupDuplicatedResults();

            this.removeSearchAutofocus();

            // Initialize search replacements
            this.initSearchReplacements();
        },

        // Cleanup duplicated search results
        cleanupDuplicatedResults: function () {
            // Check if we're on a search page
            if (window.location.search.indexOf('adc_search=') !== -1) {
                setTimeout(function () {
                    // Look for duplicated search result containers
                    var searchContainers = document.querySelectorAll('.adc-search-results-container');
                    if (searchContainers.length > 1) {
                        // Keep only the first one
                        for (var i = 1; i < searchContainers.length; i++) {
                            if (searchContainers[i].parentNode) {
                                searchContainers[i].parentNode.removeChild(searchContainers[i]);
                            }
                        }
                    }

                    // Asegurarse de que solo hay un título de recomendaciones
                    var recommendedTitles = document.querySelectorAll('.adc-recommended-title');
                    if (recommendedTitles.length > 1) {
                        for (var j = 1; j < recommendedTitles.length; j++) {
                            if (recommendedTitles[j].parentNode) {
                                recommendedTitles[j].parentNode.removeChild(recommendedTitles[j]);
                            }
                        }
                    }

                    // Eliminar mensajes redundantes "No se encontraron resultados"
                    var noResultsElements = document.querySelectorAll('.adc-search-no-results');
                    if (noResultsElements.length > 0) {
                        for (var k = 0; k < noResultsElements.length; k++) {
                            if (noResultsElements[k].parentNode) {
                                noResultsElements[k].parentNode.removeChild(noResultsElements[k]);
                            }
                        }
                    }
                }, 500);
            }
        },

        // Initialize search replacements - consolidated from adc-search.php
        initSearchReplacements: function () {
            var self = this;

            // Asegurar que los títulos de búsqueda tengan el estilo correcto
            var searchTitles = document.querySelectorAll('.adc-search-results-title, .adc-recommended-title');
            if (searchTitles.length) {
                searchTitles.forEach(function (title) {
                    title.style.color = '#6EC1E4';
                });
            }

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

            // Eliminar posibles búsquedas duplicadas
            var searchContainers = document.querySelectorAll('.adc-search-results-container');
            if (searchContainers.length > 1) {
                for (var i = 1; i < searchContainers.length; i++) {
                    searchContainers[i].remove();
                }
            }
        },

        // Función initProgramsMenu - Solución completa para desktop y móvil
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
                        if (response.success && response.data) {
                            var html = '';

                            $.each(response.data, function (i, program) {
                                var slug = slugify(program.name);
                                // ESTILOS MEJORADOS CON SOPORTE PARA 2 LÍNEAS
                                html += '<a href="/?categoria=' + slug + '" style="display:block !important; padding:12px 20px !important; color:#6EC1E4 !important; text-decoration:none !important; border-bottom:1px solid rgba(110, 193, 228, 0.1) !important; font-size:18px !important; line-height:1.3 !important; font-weight:500 !important; font-family:inherit !important; white-space:normal !important; word-wrap:break-word !important; max-width:300px !important; overflow-wrap:break-word !important;">' + program.name + '</a>';
                            });

                            $dropdown.html(html);
                            $dropdown.data('programs-loaded', true);
                            console.log('✅ Programas cargados:', response.data.length);

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
                            console.log('❌ Error en respuesta API');
                            $dropdown.html('<div class="adc-error" style="padding:20px; color:red; text-align:center;">No hay programas disponibles</div>');
                        }
                    },
                    error: function () {
                        console.log('❌ Error AJAX');
                        $dropdown.html('<div class="adc-error" style="padding:20px; color:red; text-align:center;">Error al cargar programas</div>');
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

            // Configurar elementos existentes al inicializar
            $('a:contains("PROGRAMAS"), .adc_programs_menu_text').each(function () {
                setupProgramsElement($(this));
            });

            // Usar delegación de eventos para manejar clicks (funciona incluso cuando el DOM cambia)
            $(document).on('click.programs-menu', 'a:contains("PROGRAMAS"), .adc_programs_menu_text', function (e) {
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
                if (!$(e.target).closest('.adc-wp-programs-dropdown, a:contains("PROGRAMAS"), .adc_programs_menu_text').length) {
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
            var observer = new MutationObserver(function (mutations) {
                mutations.forEach(function (mutation) {
                    if (mutation.type === 'childList') {
                        // Buscar elementos PROGRAMAS que puedan haberse añadido/modificado
                        $(mutation.addedNodes).find('a:contains("PROGRAMAS"), .adc_programs_menu_text').each(function () {
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
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });

            console.log('✅ Menú PROGRAMAS inicializado correctamente con soluciones para desktop y móvil');
        },

        // Initialize search icon with improved functionality
        initSearchIcon: function () {
            console.log('Inicializando icono de búsqueda');

            // Buscar elementos "BUSCADOR" o íconos de búsqueda y reemplazarlos
            $('a:contains("BUSCADOR"), a:contains("Buscar"), a.search-toggle, .search-toggle, .search-icon, .fa-search').each(function () {
                var $searchLink = $(this);
                console.log('Elemento BUSCADOR encontrado');

                // No continuar si ya está inicializado
                if ($searchLink.data('search-initialized')) {
                    return;
                }

                // Agregar clase para el ícono
                $searchLink.addClass('adc-search-menu-trigger');

                var $parentLi = $searchLink.closest('li');
                if (!$parentLi.length) {
                    $parentLi = $searchLink.parent();
                }

                // Crear el formulario de búsqueda con el diseño mejorado
                var $searchContainer = $('<div class="adc-menu-search-container"></div>');
                var $searchForm = $('<form class="adc-inline-search-form" action="' + window.location.origin + '/" method="get"></form>');
                var $searchInput = $('<input type="text" name="adc_search" placeholder="Buscar..." class="adc-inline-search-input">');
                // Lupa de color blanco
                var $searchButton = $('<button type="submit" class="adc-inline-search-button" style="background:transparent !important; border:none !important; color:#ffffff !important; box-shadow:none !important; outline:none !important;"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg></button>');

                $searchForm.append($searchInput).append($searchButton);
                $searchContainer.append($searchForm);

                // Aplicar estilos directamente con jQuery
                $searchForm.css({
                    'background': 'transparent',
                    'border': '1px solid #ffffff'
                });

                $searchInput.css({
                    'width': '150px',
                    'transition': 'color 0.3s ease'
                });

                $searchButton.css({
                    'color': '#ffffff'
                });

                // Agregar eventos de hover para el botón
                $searchButton.hover(
                    function () {
                        $(this).css({
                            'color': '#6EC1E4',
                            'background-color': '#000000',
                            'border': '1px solid #6EC1E4',
                            'border-radius': '4px'
                        });
                    },
                    function () {
                        $(this).css({
                            'color': '#ffffff',
                            'background-color': 'transparent',
                            'border': 'none'
                        });
                    }
                );

                // Reemplazar el enlace con el formulario
                if ($parentLi.length) {
                    $parentLi.html('').append($searchContainer);
                    $parentLi.css({
                        'display': 'flex',
                        'align-items': 'center'
                    });
                } else {
                    $searchLink.replaceWith($searchContainer);
                }

                // Marcar como inicializado
                $searchLink.data('search-initialized', true);
            });

            // Asegurar Font Awesome
            this.ensureFontAwesome();
        },

        // Ensure Font Awesome is loaded
        ensureFontAwesome: function () {
            if ($('link[href*="font-awesome"]').length) {
                return; // Ya está cargado
            }

            $('<link>').attr({
                rel: 'stylesheet',
                href: 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css',
                integrity: 'sha512-1ycn6IcaQQ40/MKBW2W4Rhis/DbILU74C1vSrLJxCq57o941Ym01SwNsOMqvEBFlcgUa6xLiPY/NS5R+E6ztJQ==',
                crossorigin: 'anonymous'
            }).appendTo('head');
        },

        // Initialize video player
        initPlayer: function () {
            if (typeof videojs === 'undefined' || !document.getElementById('adc-player')) {
                return;
            }

            var self = this;
            var player = videojs('adc-player', {
                controls: true,
                preload: 'auto',
                fluid: true,
                responsive: true
            });

            player.ready(function () {
                // Set initial volume
                player.volume(self.config.playerVolume);

                // Add custom buttons
                self.addCustomButtons(player);

                // Handle ended event for autoplay
                if (self.config.autoplayEnabled) {
                    player.on('ended', function () {
                        self.handleVideoEnded();
                    });
                }
            });

            this.player = player;
        },

        // Función para quitar el autofocus de los campos de búsqueda
        removeSearchAutofocus: function () {
            // Ejecutar con un pequeño retraso para asegurar que se ejecute después de otros scripts
            setTimeout(function () {
                // Encontrar todos los inputs de búsqueda y quitar el foco
                document.querySelectorAll('.adc-inline-search-input').forEach(function (input) {
                    if (document.activeElement === input) {
                        input.blur();
                    }
                });
            }, 100);
        },

        // Add custom buttons to player
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
                    player.currentTime(Math.max(0, player.currentTime() - 10));
                }
            });

            // Add icon to rewind button
            RewindButton.prototype.buildCSSClass = function () {
                return 'vjs-rewind-button vjs-control vjs-button';
            };

            // Create rewind button element
            videojs.registerComponent('RewindButton', RewindButton);
            player.controlBar.addChild('RewindButton', {}, 0);

            // Forward button
            var ForwardButton = videojs.extend(Button, {
                constructor: function () {
                    Button.apply(this, arguments);
                    this.controlText('Adelantar 10 segundos');
                    this.addClass('vjs-forward-button');
                },
                handleClick: function () {
                    player.currentTime(Math.min(player.duration(), player.currentTime() + 10));
                }
            });

            // Add icon to forward button
            ForwardButton.prototype.buildCSSClass = function () {
                return 'vjs-forward-button vjs-control vjs-button';
            };

            // Create forward button element
            videojs.registerComponent('ForwardButton', ForwardButton);
            player.controlBar.addChild('ForwardButton', {}, 2);

            // Style the buttons
            var rewindBtn = player.controlBar.getChild('RewindButton').el();
            var forwardBtn = player.controlBar.getChild('ForwardButton').el();

            rewindBtn.innerHTML = '<span>⏪ 10s</span>';
            forwardBtn.innerHTML = '<span>10s ⏩</span>';
        },

        // Handle video ended event
        handleVideoEnded: function () {
            var nextUrl = this.getNextVideoUrl();
            if (!nextUrl) return;

            var self = this;
            var overlay = document.getElementById('adc-next-overlay');
            var countdownEl = document.getElementById('adc-countdown');

            if (!overlay || !countdownEl) return;

            // Show overlay
            overlay.style.display = 'block';

            // Start countdown
            var seconds = this.config.autoplayCountdown;
            countdownEl.textContent = seconds;

            this.countdownInterval = setInterval(function () {
                seconds--;
                countdownEl.textContent = seconds;

                if (seconds <= 0) {
                    clearInterval(self.countdownInterval);
                    window.location.href = nextUrl;
                }
            }, 1000);
        },

        // Get next video URL
        getNextVideoUrl: function () {
            var nextBtn = document.querySelector('.adc-next-button-container a');
            if (nextBtn) {
                return nextBtn.href;
            }

            // Fallback: check in overlay
            var overlayLink = document.querySelector('#adc-next-overlay a');
            if (overlayLink && overlayLink.href !== '#') {
                return overlayLink.href;
            }

            return null;
        },

        // Cancel autoplay
        cancelAutoplay: function () {
            if (this.countdownInterval) {
                clearInterval(this.countdownInterval);
            }

            var overlay = document.getElementById('adc-next-overlay');
            if (overlay) {
                overlay.innerHTML = '<p style="color:#aaa">Autoplay cancelado</p>';
            }
        },

        // Initialize dropdown menu
        initDropdownMenu: function () {
            var dropdowns = document.querySelectorAll('.adc-dropdown-menu');

            dropdowns.forEach(function (dropdown) {
                var toggle = dropdown.querySelector('.adc-dropdown-toggle');
                var content = dropdown.querySelector('.adc-dropdown-content');

                if (!toggle || !content) return;

                toggle.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    dropdown.classList.toggle('active');
                });

                // Close when clicking outside
                document.addEventListener('click', function (e) {
                    if (!dropdown.contains(e.target)) {
                        dropdown.classList.remove('active');
                    }
                });
            });
        },

        // Initialize search form with improved functionality
        initSearchForm: function () {
            var forms = document.querySelectorAll('.adc-search-form, .adc-inline-search-form');

            forms.forEach(function (form) {
                var input = form.querySelector('input[name="adc_search"]');
                if (!input) return;

                // Prevent empty searches
                form.addEventListener('submit', function (e) {
                    if (input.value.trim() === '') {
                        e.preventDefault();
                        input.focus();
                    }
                });
            });
        },

        // Bind events
        bindEvents: function () {
            var self = this;

            // Cancel autoplay button
            $(document).on('click', '#adc-cancel-autoplay', function (e) {
                e.preventDefault();
                self.cancelAutoplay();
            });

            // Keyboard shortcuts
            $(document).on('keydown', function (e) {
                if (!self.player) return;

                switch (e.keyCode) {
                    case 37: // Left arrow
                        self.player.currentTime(self.player.currentTime() - 10);
                        e.preventDefault();
                        break;
                    case 39: // Right arrow
                        self.player.currentTime(self.player.currentTime() + 10);
                        e.preventDefault();
                        break;
                    case 32: // Spacebar
                        // Solo aplicar si no estamos en un input
                        if (!$(e.target).is('input, textarea')) {
                            if (self.player.paused()) {
                                self.player.play();
                            } else {
                                self.player.pause();
                            }
                            e.preventDefault();
                        }
                        break;
                    case 27: // Escape
                        // Close all dropdowns and overlays when pressing Escape
                        $('.adc-wp-programs-dropdown').slideUp(200);
                        $('.dropdown-arrow').css('transform', 'rotate(0deg)');
                        if (self.countdownInterval) {
                            self.cancelAutoplay();
                        }
                        break;
                }
            });

            // Close search function
            $(document).on('keydown', function (e) {
                if (e.key === 'Escape') {
                    var searchBox = document.getElementById('adc-search-box');
                    if (searchBox && searchBox.style.display !== 'none') {
                        searchBox.style.display = 'none';
                    }

                    // También cerrar otros elementos
                    $('.adc-wp-programs-dropdown').slideUp(200);
                    $('.adc-search-popup').fadeOut(200);
                }
            });

            // Smooth scroll for navigation
            $('.adc-nav-item').on('click', function (e) {
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

            // Lazy load images
            this.initLazyLoad();
        },

        // Initialize lazy loading for thumbnails
        initLazyLoad: function () {
            if ('IntersectionObserver' in window) {
                var imageObserver = new IntersectionObserver(function (entries, observer) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) {
                            var image = entry.target;
                            if (image.dataset.src) {
                                image.src = image.dataset.src;
                                image.classList.remove('lazy');
                                imageObserver.unobserve(image);
                            }
                        }
                    });
                });

                var lazyImages = document.querySelectorAll('img.lazy');
                lazyImages.forEach(function (img) {
                    imageObserver.observe(img);
                });
            } else {
                // Fallback for older browsers
                var lazyImages = document.querySelectorAll('img.lazy');
                lazyImages.forEach(function (img) {
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.classList.remove('lazy');
                    }
                });
            }
        },

        // AJAX Search functionality
        performSearch: function (query) {
            var self = this;
            var ajaxUrl = typeof adc_config !== 'undefined' ? adc_config.ajax_url : '/wp-admin/admin-ajax.php';
            var nonce = typeof adc_config !== 'undefined' ? adc_config.nonce : '';

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'adc_search',
                    search: query,
                    nonce: nonce
                },
                success: function (response) {
                    if (response.success) {
                        self.displaySearchResults(response.data);
                    }
                },
                error: function () {
                    console.error('Search error');
                }
            });
        },

        // Display search results
        displaySearchResults: function (results) {
            // This would be implemented based on your UI requirements
            console.log('Search results:', results);
        },

        // Analytics tracking (if needed)
        trackEvent: function (category, action, label) {
            if (typeof gtag !== 'undefined') {
                gtag('event', action, {
                    'event_category': category,
                    'event_label': label
                });
            }
        },

        // Utility functions
        utils: {
            // Format duration from seconds to MM:SS
            formatDuration: function (seconds) {
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

            // Get URL parameter
            getUrlParam: function (param) {
                var urlParams = new URLSearchParams(window.location.search);
                return urlParams.get(param);
            }
        }
    };

    // Inicializar inmediatamente para evitar problemas con temas o plugins que retrasen el ready
    var initADCMenu = function () {
        // Initialize ADC Video when DOM is ready
        if (document.readyState === "interactive" || document.readyState === "complete") {
            console.log('ADC Menu: Inicializando...');

            // Get configuration
            var config = {};
            if (typeof adc_config !== 'undefined') {
                config = {
                    autoplayEnabled: adc_config.autoplay === '1',
                    autoplayCountdown: parseInt(adc_config.countdown) || 5
                };
            } else {
                // Fallback si no existe adc_config
                window.adc_config = {
                    ajax_url: '/wp-admin/admin-ajax.php',
                    nonce: '',
                    autoplay: '1',
                    countdown: '5'
                };
            }

            // Initialize ADC Video
            ADCVideo.init(config);
        } else {
            // Reintentar si el DOM no está listo
            setTimeout(initADCMenu, 50);
        }
    };

    // Iniciar cuanto antes
    initADCMenu();

    // Iniciar también en el documento ready normal para compatibilidad
    $(document).ready(function () {
        if (!window.ADCVideoInitialized) {
            var config = {};
            if (typeof adc_config !== 'undefined') {
                config = {
                    autoplayEnabled: adc_config.autoplay === '1',
                    autoplayCountdown: parseInt(adc_config.countdown) || 5
                };
            }
            ADCVideo.init(config);
            window.ADCVideoInitialized = true;
        }
    });

})(jQuery);

// Función para toggle de búsqueda
function toggleSearchBox() {
    var searchBox = document.getElementById('adc-search-box');
    if (searchBox) {
        if (searchBox.style.display === 'none' || searchBox.style.display === '') {
            searchBox.style.display = 'block';
            var input = searchBox.querySelector('.adc-search-input');
            if (input) input.focus();
        } else {
            searchBox.style.display = 'none';
        }
    }
}

// Función para salir usando Escape
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        var searchBox = document.getElementById('adc-search-box');
        if (searchBox && searchBox.style.display !== 'none') {
            searchBox.style.display = 'none';
        }

        // También cerrar otros elementos
        jQuery('.adc-wp-programs-dropdown').slideUp(200);
        jQuery('.dropdown-arrow').css('transform', 'rotate(0deg)');
        jQuery('.adc-search-popup').fadeOut(200);
    }
});

// Función para convertir textos a slugs (URLs amigables)
function slugify(text) {
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
}