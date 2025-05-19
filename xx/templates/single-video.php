<?php
/**
 * Plantilla para la página de video individual
 * 
 * @package ADC Video Display
 */

// Asegurar que no se accede directamente
if (!defined('ABSPATH')) {
    exit;
}

// Extraemos las variables de $variables
// $video - Información del video actual
// $category - Información de la categoría actual
// $next_video - Información del siguiente video (o null si no hay)
// $related_videos - Videos relacionados
// $category_slug - Slug de la categoría actual
// $autoplay - Si el autoplay está habilitado ('1' o '0')
// $countdown - Tiempo de cuenta atrás para autoplay
// $show_view_more - Si se muestra el botón de "Ver más videos" ('1' o '0')
// $materials - Todos los videos de la categoría
?>

<div class="adc-video-container">
    <!-- Video title and back button container -->
    <div class="adc-video-header">
        <h1 class="adc-video-main-title"><?php echo ADC_Utilities::escape_html($video['title']); ?></h1>
        <a href="?categoria=<?php echo ADC_Utilities::escape_attr($category_slug); ?>" class="adc-back-program-button">Volver a <?php echo ADC_Utilities::escape_html($category['name']); ?></a>
    </div>
    
    <!-- Video.js -->
    <link href="https://unpkg.com/video.js@8.10.0/dist/video-js.min.css" rel="stylesheet">
    <script src="https://unpkg.com/video.js@8.10.0/dist/video.min.js"></script>
    
    <!-- Player with proper aspect ratio -->
    <div class="adc-video-player" style="position:relative; padding-top:56.25%;">
        <video id="adc-player" class="video-js vjs-default-skin vjs-big-play-centered" controls playsinline preload="auto" style="position:absolute; top:0; left:0; width:100%; height:100%;" data-setup="{}">
            <source src="<?php echo ADC_Utilities::escape_url($video['video']); ?>" type="video/mp4">
        </video>
        
        <?php if ($next_video && isset($next_url)): ?>
            <!-- Autoplay overlay -->
            <div id="adc-next-overlay" style="display: none;">
                <p>Siguiente video en <span id="adc-countdown"><?php echo intval($countdown); ?></span> segundos...</p>
                <a href="<?php echo ADC_Utilities::escape_url($next_url); ?>">Ver ahora</a><br>
                <button id="adc-cancel-autoplay">Cancelar</button>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if ($next_video && isset($next_url)): ?>
        <!-- Next button -->
        <div class="adc-next-button-container">
            <a href="<?php echo ADC_Utilities::escape_url($next_url); ?>" class="adc-view-all-button">Ver siguiente video</a>
        </div>
    <?php endif; ?>

    <!-- Related videos -->
    <h2 class="adc-related-videos-title">Más videos de <?php echo ADC_Utilities::escape_html($category['name']); ?></h2>
    <div class="adc-related-videos-grid">
        <div class="adc-videos-row" id="adc-related-videos-container">
            <?php foreach ($related_videos as $related_video): ?>
                <?php 
                $related_slug = ADC_Utilities::slugify($related_video['title']);
                $thumbnail_url = isset($api) ? $api->get_thumbnail_url($related_video['id']) : "https://s3apics.streamgates.net/TutorahTV_Thumbs/{$related_video['id']}_50.jpg";
                ?>
                <div class="adc-video-item adc-related-video-item">
                    <a href="?categoria=<?php echo ADC_Utilities::escape_attr($category_slug); ?>&video=<?php echo ADC_Utilities::escape_attr($related_slug); ?>" class="adc-video-link">
                        <div class="adc-video-thumbnail">
                            <img src="<?php echo ADC_Utilities::escape_url($thumbnail_url); ?>" alt="<?php echo ADC_Utilities::escape_attr($related_video['title']); ?>">
                            <div class="adc-video-play-icon"></div>
                        </div>
                        
                        <div class="adc-video-info">
                            <h3 class="adc-video-title"><?php echo ADC_Utilities::escape_html($related_video['title']); ?></h3>
                            <span class="adc-video-duration">Duración: <?php echo ADC_Utilities::escape_html($related_video['duration']); ?></span>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <?php if ($show_view_more == '1' && count($materials) > 9): ?>
        <!-- View more button -->
        <div class="adc-view-more-container">
            <button class="adc-view-more-button" id="adc-view-more-button">Ver más videos</button>
        </div>
        
        <!-- Hidden container for all videos -->
        <div id="adc-all-videos-container" style="display: none;">
            <div class="adc-videos-grid">
                <div class="adc-videos-row">
                    <?php foreach ($materials as $material): ?>
                        <?php if ($material['id'] == $video['id']) continue; ?>
                        <?php 
                        $material_slug = ADC_Utilities::slugify($material['title']);
                        $thumbnail_url = isset($api) ? $api->get_thumbnail_url($material['id']) : "https://s3apics.streamgates.net/TutorahTV_Thumbs/{$material['id']}_50.jpg";
                        ?>
                        <div class="adc-video-item adc-related-video-item">
                            <a href="?categoria=<?php echo ADC_Utilities::escape_attr($category_slug); ?>&video=<?php echo ADC_Utilities::escape_attr($material_slug); ?>" class="adc-video-link">
                                <div class="adc-video-thumbnail">
                                    <img src="<?php echo ADC_Utilities::escape_url($thumbnail_url); ?>" alt="<?php echo ADC_Utilities::escape_attr($material['title']); ?>">
                                    <div class="adc-video-play-icon"></div>
                                </div>
                                
                                <div class="adc-video-info">
                                    <h3 class="adc-video-title"><?php echo ADC_Utilities::escape_html($material['title']); ?></h3>
                                    <span class="adc-video-duration">Duración: <?php echo ADC_Utilities::escape_html($material['duration']); ?></span>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($next_video && isset($next_url) && $autoplay == '1'): ?>
        <!-- Inline script for autoplay functionality -->
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            var player = videojs("adc-player");
            var overlay = document.getElementById("adc-next-overlay");
            var countdownEl = document.getElementById("adc-countdown");
            var cancelBtn = document.getElementById("adc-cancel-autoplay");
            var interval = null;
            var seconds = <?php echo intval($countdown); ?>;
            var cancelled = false;
            
            player.ready(function() {
                player.volume(0.5);
                
                // Add custom buttons
                var Button = videojs.getComponent("Button");
                
                var rewindButton = videojs.extend(Button, {
                    constructor: function() {
                        Button.apply(this, arguments);
                        this.controlText("Rewind 10 seconds");
                        this.addClass("vjs-rewind-button");
                        this.el().innerHTML = "⏪ 10s";
                    },
                    handleClick: function() {
                        player.currentTime(player.currentTime() - 10);
                    }
                });
                videojs.registerComponent("RewindButton", rewindButton);
                player.getChild("controlBar").addChild("RewindButton", {}, 0);
                
                var forwardButton = videojs.extend(Button, {
                    constructor: function() {
                        Button.apply(this, arguments);
                        this.controlText("Forward 10 seconds");
                        this.addClass("vjs-forward-button");
                        this.el().innerHTML = "10s ⏩";
                    },
                    handleClick: function() {
                        player.currentTime(player.currentTime() + 10);
                    }
                });
                videojs.registerComponent("ForwardButton", forwardButton);
                player.getChild("controlBar").addChild("ForwardButton", {}, 2);
            });
            
            player.on("ended", function() {
                if (!overlay || cancelled) return;
                
                // Exit fullscreen if active
                if (player.isFullscreen()) {
                    player.exitFullscreen();
                }
                
                // Show overlay after small delay to ensure fullscreen exit
                setTimeout(function() {
                    overlay.style.display = "block";
                    seconds = <?php echo intval($countdown); ?>;
                    countdownEl.textContent = seconds;
                    interval = setInterval(function() {
                        seconds--;
                        countdownEl.textContent = seconds;
                        if (seconds <= 0 && !cancelled) {
                            clearInterval(interval);
                            window.location.href = "<?php echo ADC_Utilities::escape_url($next_url); ?>";
                        }
                    }, 1000);
                }, 300);
            });
            
            if (cancelBtn) {
                cancelBtn.addEventListener("click", function() {
                    cancelled = true;
                    if (overlay) {
                        overlay.innerHTML = '<p style="color:#aaa">Autoplay cancelado</p>';
                    }
                    clearInterval(interval);
                });
            }
        });
        </script>
    <?php endif; ?>
</div>