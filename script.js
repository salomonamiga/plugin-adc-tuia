/**
 * ADC Video Display - Frontend JavaScript
 * Version: 2.0 - FIX FINAL PROGRAMAS
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

            // Initialize programs menu - FIX FINAL
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
            if (window.location.search.indexOf('adc_search=') !== -1) {
                setTimeout(function() {
                    var searchContainers = document.querySelectorAll('.adc-search-results-container');
                    if (searchContainers.length > 1) {
                        for (var i = 1; i < searchContainers.length; i++) {
                            if (searchContainers[i].parentNode) {
                                searchContainers[i].parentNode.removeChild(searchContainers[i]);
                            }
                        }
                    }
                    
                    var recommendedTitles = document.querySelectorAll('.adc-recommended-title');
                    if (recommendedTitles.length > 1) {
                        for (var j = 1; j < recommendedTitles.length; j++) {
                            if (recommendedTitles[j].parentNode) {
                                recommendedTitles[j].parentNode.removeChild(recommendedTitles[j]);
                            }
                        }
                    }
                    
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

        // Initialize search replacements
        initSearchReplacements: function() {
            var self = this;
            
            var searchTitles = document.querySelectorAll('.adc-search-results-title, .adc-recommended-title');
            if (searchTitles.length) {
                searchTitles.forEach(function(title) {
                    title.style.color = '#6EC1E4';
                });
            }
            
            document.querySelectorAll('a').forEach(function(link) {
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
            
            var searchContainers = document.querySelectorAll('.adc-search-results-container');
            if (searchContainers.length > 1) {
                for (var i = 1; i < searchContainers.length; i++) {
                    searchContainers[i].remove();
                }
            }
        },

        // FIX FINAL PROGRAMAS - Más específico y robusto
        initProgramsMenu: function() {
            var self = this;
            console.log('🎯 FIX FINAL PROGRAMAS iniciando...');
            
            // Ejecutar múltiples veces
            setTimeout(function() { self.fixProgramasMenuFinal(); }, 1000);
            setTimeout(function() { self.fixProgramasMenuFinal(); }, 3000);
            setTimeout(function() { self.fixProgramasMenuFinal(); }, 6000);
        },

        fixProgramasMenuFinal: function() {
            console.log('🎯 Ejecutando fixProgramasMenuFinal...');
            
            // SELECTORES MÁS ESPECÍFICOS - Solo elementos clickeables
            var found = [];
            
            // 1. Solo enlaces que contengan exactamente "PROGRAMAS"
            $('a').each(function() {
                var text = $(this).text().trim();
                if (text === 'PROGRAMAS' || text === 'Programas') {
                    found.push(this);
                    console.log('✅ Enlace exacto encontrado:', text);
                }
            });
            
            // 2. Solo elementos de menú Elementor
            $('.elementor-item').each(function() {
                var text = $(this).text().trim();
                if (text === 'PROGRAMAS' || text === 'Programas') {
                    found.push(this);
                    console.log('✅ Elemento Elementor encontrado:', text);
                }
            });
            
            // 3. Solo elementos nav que sean clickeables
            $('nav a, .nav a, .menu a').each(function() {
                var text = $(this).text().trim();
                if (text === 'PROGRAMAS' || text === 'Programas') {
                    found.push(this);
                    console.log('✅ Elemento de navegación encontrado:', text);
                }
            });
            
            if (found.length === 0) {
                console.log('❌ NO SE ENCONTRARON ELEMENTOS PROGRAMAS VÁLIDOS');
                
                // Debug: Listar todos los enlaces del menú
                console.log('🔍 Debug: Listando todos los enlaces del menú:');
                $('.elementor-nav-menu a, nav a, .nav a, .menu a').each(function() {
                    var text = $(this).text().trim();
                    if (text.length > 0 && text.length < 50) { // Evitar CSS y otros elementos largos
                        console.log('   - "' + text + '"');
                    }
                });
                return;
            }
            
            console.log('🎯 Elementos PROGRAMAS válidos encontrados:', found.length);
            
            var self = this;
            
            // Procesar cada elemento encontrado
            $(found).each(function(index) {
                var $element = $(this);
                var text = $element.text().trim();
                
                console.log('📍 Procesando elemento válido #' + index + ':', text);
                
                // Verificar que sea un elemento HTML válido
                if (!this.tagName || this.tagName === 'STYLE' || this.tagName === 'SCRIPT') {
                    console.log('⚠️ Elemento no válido, saltando...');
                    return;
                }
                
                // Si ya tiene dropdown, saltar
                if ($element.data('adc-fixed') || $element.closest('li').find('.adc-dropdown').length > 0) {
                    console.log('⚠️ Ya procesado, saltando...');
                    return;
                }
                
                // Encontrar el contenedor padre correcto
                var $container = $element.closest('li.elementor-item');
                if ($container.length === 0) {
                    $container = $element.closest('li');
                }
                if ($container.length === 0) {
                    $container = $element.parent();
                }
                
                // Verificar que el contenedor sea válido
                if (!$container.length || !$container[0] || !$container[0].tagName) {
                    console.log('❌ Contenedor no válido, saltando...');
                    return;
                }
                
                console.log('📦 Contenedor válido encontrado:', $container[0].tagName);
                
                // Agregar estilos necesarios al contenedor
                $container.css({
                    'position': 'relative',
                    'z-index': '9999'
                });
                
                // Crear el dropdown
                var dropdownId = 'adc-dropdown-final-' + index;
                var $dropdown = $('<div id="' + dropdownId + '" class="adc-dropdown-final" style="display:none; position:absolute; top:100%; left:0; width:250px; background:#000; border:2px solid #6EC1E4; border-radius:5px; z-index:99999; box-shadow:0 5px 15px rgba(0,0,0,0.3);"><div style="padding:15px; color:#6EC1E4; text-align:center; font-size:14px;">⏳ Cargando programas...</div></div>');
                
                // Agregar el dropdown al contenedor de forma segura
                try {
                    $container.append($dropdown);
                    console.log('✅ Dropdown creado con ID:', dropdownId);
                } catch (e) {
                    console.log('❌ Error agregando dropdown:', e);
                    return;
                }
                
                // Agregar flecha al texto (sin romper el estilo)
                if (!$element.find('.adc-arrow-final').length) {
                    try {
                        $element.append(' <span class="adc-arrow-final" style="color:#6EC1E4; font-size:12px; margin-left:3px; transition:transform 0.3s;">▾</span>');
                        console.log('✅ Flecha agregada');
                    } catch (e) {
                        console.log('❌ Error agregando flecha:', e);
                    }
                }
                
                var $arrow = $element.find('.adc-arrow-final');
                
                // EVENT HANDLER SIMPLIFICADO Y ROBUSTO
                var clickHandler = function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    
                    console.log('🚀 CLICK FINAL DETECTADO en elemento:', text);
                    
                    // Cerrar todos los otros dropdowns
                    $('.adc-dropdown-final').not($dropdown).slideUp(200);
                    $('.adc-arrow-final').not($arrow).css('transform', 'rotate(0deg)');
                    
                    // Toggle el dropdown actual
                    if ($dropdown.is(':visible')) {
                        $dropdown.slideUp(200);
                        $arrow.css('transform', 'rotate(0deg)');
                        console.log('🔒 Cerrando dropdown final');
                    } else {
                        $dropdown.slideDown(200);
                        $arrow.css('transform', 'rotate(180deg)');
                        console.log('🔓 Abriendo dropdown final');
                        
                        // Cargar programas si es necesario
                        self.loadProgramsFinal($dropdown);
                    }
                };
                
                // SOLO jQuery click - Más simple y confiable
                $element.off('.adc-final').on('click.adc-final touchstart.adc-final', clickHandler);
                
                console.log('✅ Event handler agregado');
                
                // Marcar como procesado
                $element.data('adc-fixed', true);
            });
            
            // Click fuera para cerrar
            $(document).off('click.adc-final-outside').on('click.adc-final-outside', function(e) {
                if (!$(e.target).closest('.adc-dropdown-final, [data-adc-fixed="true"]').length) {
                    $('.adc-dropdown-final').slideUp(200);
                    $('.adc-arrow-final').css('transform', 'rotate(0deg)');
                    console.log('🚪 Cerrando dropdowns finales por click fuera');
                }
            });
            
            console.log('🏁 FIX FINAL PROGRAMAS completado');
        },

        // Función para cargar programas - Simplificada
        loadProgramsFinal: function($dropdown) {
            if (!$dropdown.find('div').text().includes('Cargando')) {
                return;
            }
            
            console.log('📡 Cargando programas desde API...');
            
            var ajaxUrl = '/wp-admin/admin-ajax.php';
            if (typeof adc_config !== 'undefined' && adc_config.ajax_url) {
                ajaxUrl = adc_config.ajax_url;
            }
            
            var nonce = '';
            if (typeof adc_config !== 'undefined' && adc_config.nonce) {
                nonce = adc_config.nonce;
            }
            
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'adc_get_programs_menu',
                    nonce: nonce
                },
                success: function (response) {
                    console.log('📨 Respuesta AJAX recibida:', response);
                    
                    if (response && response.success && response.data && response.data.length > 0) {
                        var html = '';
                        
                        response.data.forEach(function(program) {
                            var slug = slugify(program.name);
                            html += '<a href="/?categoria=' + slug + '" style="display:block; padding:12px 16px; color:#6EC1E4; text-decoration:none; border-bottom:1px solid rgba(110,193,228,0.1); font-size:14px; transition:all 0.2s;" onmouseover="this.style.backgroundColor=\'rgba(110,193,228,0.1)\'; this.style.color=\'#fff\';" onmouseout="this.style.backgroundColor=\'transparent\'; this.style.color=\'#6EC1E4\';">' + program.name + '</a>';
                        });
                        
                        $dropdown.html(html);
                        console.log('✅ Programas cargados exitosamente:', response.data.length);
                    } else {
                        console.log('❌ Respuesta inválida o sin datos');
                        $dropdown.html('<div style="padding:15px; color:#ff6b6b; text-align:center; font-size:12px;">No hay programas disponibles</div>');
                    }
                },
                error: function (xhr, status, error) {
                    console.log('❌ Error AJAX:', status, error);
                    $dropdown.html('<div style="padding:15px; color:#ff6b6b; text-align:center; font-size:12px;">Error al cargar programas</div>');
                }
            });
        },

        // Initialize search icon
        initSearchIcon: function () {
            console.log('Inicializando icono de búsqueda');

            $('a:contains("BUSCADOR"), a:contains("Buscar"), a.search-toggle, .search-toggle, .search-icon, .fa-search').each(function () {
                var $searchLink = $(this);
                console.log('Elemento BUSCADOR encontrado');

                if ($searchLink.data('search-initialized')) {
                    return;
                }

                $searchLink.addClass('adc-search-menu-trigger');

                var $parentLi = $searchLink.closest('li');
                if (!$parentLi.length) {
                    $parentLi = $searchLink.parent();
                }

                var $searchContainer = $('<div class="adc-menu-search-container"></div>');
                var $searchForm = $('<form class="adc-inline-search-form" action="' + window.location.origin + '/" method="get"></form>');
                var $searchInput = $('<input type="text" name="adc_search" placeholder="Buscar..." class="adc-inline-search-input">');
                var $searchButton = $('<button type="submit" class="adc-inline-search-button" style="background:transparent !important; border:none !important; color:#ffffff !important; box-shadow:none !important; outline:none !important;"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg></button>');

                $searchForm.append($searchInput).append($searchButton);
                $searchContainer.append($searchForm);

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

            this.ensureFontAwesomeLoaded();
        },

        // Ensure Font Awesome is loaded
        ensureFontAwesomeLoaded: function () {
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

        // Initialize video player - SIMPLIFIED
        initPlayer: function () {
            if (typeof videojs === 'undefined' || !document.getElementById('adc-player')) {
                return;
            }

            var self = this;
            
            try {
                var player = videojs('adc-player', {
                    controls: true,
                    preload: 'auto',
                    fluid: true,
                    responsive: true
                });

                player.ready(function () {
                    player.volume(self.config.playerVolume);
                    console.log('Video player initialized without custom buttons');

                    if (self.config.autoplayEnabled) {
                        player.on('ended', function () {
                            self.handleVideoEnded();
                        });
                    }
                });

                this.player = player;
            } catch (error) {
                console.log('Error initializing video player:', error);
            }
        },

        removeSearchAutofocus: function () {
            setTimeout(function () {
                document.querySelectorAll('.adc-inline-search-input').forEach(function (input) {
                    if (document.activeElement === input) {
                        input.blur();
                    }
                });
            }, 100);
        },

        handleVideoEnded: function () {
            var nextUrl = this.getNextVideoUrl();
            if (!nextUrl) return;

            var self = this;
            var overlay = document.getElementById('adc-next-overlay');
            var countdownEl = document.getElementById('adc-countdown');

            if (!overlay || !countdownEl) return;

            overlay.style.display = 'block';

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

        getNextVideoUrl: function () {
            var nextBtn = document.querySelector('.adc-next-button-container a');
            if (nextBtn) {
                return nextBtn.href;
            }

            var overlayLink = document.querySelector('#adc-next-overlay a');
            if (overlayLink && overlayLink.href !== '#') {
                return overlayLink.href;
            }

            return null;
        },

        cancelAutoplay: function () {
            if (this.countdownInterval) {
                clearInterval(this.countdownInterval);
            }

            var overlay = document.getElementById('adc-next-overlay');
            if (overlay) {
                overlay.innerHTML = '<p style="color:#aaa">Autoplay cancelado</p>';
            }
        },

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

                document.addEventListener('click', function (e) {
                    if (!dropdown.contains(e.target)) {
                        dropdown.classList.remove('active');
                    }
                });
            });
        },

        initSearchForm: function () {
            var forms = document.querySelectorAll('.adc-search-form, .adc-inline-search-form');

            forms.forEach(function (form) {
                var input = form.querySelector('input[name="adc_search"]');
                if (!input) return;

                form.addEventListener('submit', function (e) {
                    if (input.value.trim() === '') {
                        e.preventDefault();
                        input.focus();
                    }
                });
            });
        },

        bindEvents: function () {
            var self = this;

            $(document).on('click', '#adc-cancel-autoplay', function (e) {
                e.preventDefault();
                self.cancelAutoplay();
            });

            $(document).on('keydown', function (e) {
                if (!self.player) return;

                switch (e.keyCode) {
                    case 37:
                        self.player.currentTime(self.player.currentTime() - 10);
                        e.preventDefault();
                        break;
                    case 39:
                        self.player.currentTime(self.player.currentTime() + 10);
                        e.preventDefault();
                        break;
                    case 32:
                        if (self.player.paused()) {
                            self.player.play();
                        } else {
                            self.player.pause();
                        }
                        e.preventDefault();
                        break;
                    case 27:
                        $('.adc-dropdown-final').slideUp(200);
                        $('.adc-arrow-final').css('transform', 'rotate(0deg)');
                        if (self.countdownInterval) {
                            self.cancelAutoplay();
                        }
                        break;
                }
            });

            $(document).on('keydown', function (e) {
                if (e.key === 'Escape') {
                    var searchBox = document.getElementById('adc-search-box');
                    if (searchBox && searchBox.style.display !== 'none') {
                        searchBox.style.display = 'none';
                    }

                    $('.adc-dropdown-final').slideUp(200);
                    $('.adc-search-popup').fadeOut(200);
                }
            });

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

            this.initLazyLoad();
        },

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
                var lazyImages = document.querySelectorAll('img.lazy');
                lazyImages.forEach(function (img) {
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.classList.remove('lazy');
                    }
                });
            }
        },

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

        displaySearchResults: function (results) {
            console.log('Search results:', results);
        },

        trackEvent: function (category, action, label) {
            if (typeof gtag !== 'undefined') {
                gtag('event', action, {
                    'event_category': category,
                    'event_label': label
                });
            }
        },

        utils: {
            formatDuration: function (seconds) {
                var minutes = Math.floor(seconds / 60);
                var remainingSeconds = seconds % 60;
                return minutes + ':' + (remainingSeconds < 10 ? '0' : '') + remainingSeconds;
            },

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

            getUrlParam: function (param) {
                var urlParams = new URLSearchParams(window.location.search);
                return urlParams.get(param);
            }
        }
    };

    // Initialize ADC Video
    var initADCMenu = function () {
        if (document.readyState === "interactive" || document.readyState === "complete") {
            console.log('ADC Menu: Inicializando...');

            var config = {};
            if (typeof adc_config !== 'undefined') {
                config = {
                    autoplayEnabled: adc_config.autoplay === '1',
                    autoplayCountdown: parseInt(adc_config.countdown) || 5
                };
            } else {
                window.adc_config = {
                    ajax_url: '/wp-admin/admin-ajax.php',
                    nonce: '',
                    autoplay: '1',
                    countdown: '5'
                };
            }

            ADCVideo.init(config);
        } else {
            setTimeout(initADCMenu, 50);
        }
    };

    initADCMenu();

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

        // Cerrar dropdowns de programas
        jQuery('.adc-dropdown-final').slideUp(200);
        jQuery('.adc-arrow-final').css('transform', 'rotate(0deg)');
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