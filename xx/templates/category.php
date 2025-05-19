<?php
/**
 * Plantilla para la página de categoría/programa
 * 
 * @package ADC Video Display
 */

// Asegurar que no se accede directamente
if (!defined('ABSPATH')) {
    exit;
}

// Extraemos las variables de $variables
// $category - Información de la categoría actual
// $seasons - Temporadas y sus videos
// $category_slug - Slug de la categoría actual
// $videos_per_row - Número de videos por fila
// $home_url - URL de la página principal
?>

<div class="adc-category-header">
    <h1 class="adc-category-title"><?php echo ADC_Utilities::escape_html($category['name']); ?></h1>
    <a href="<?php echo ADC_Utilities::escape_url($home_url); ?>" class="adc-back-button">Volver a Programas</a>
</div>

<?php if (empty($seasons)): ?>
    <div class="adc-error">No se encontraron videos en esta categoría.</div>
<?php else: ?>
    <?php foreach ($seasons as $season_num => $season_videos): ?>
        <?php 
        $season_name = isset($season_names[$season_num]) ? $season_names[$season_num] : 'Temporada ' . intval($season_num);
        ?>
        <h2 class="adc-season-header"><span><?php echo ADC_Utilities::escape_html($season_name); ?></span></h2>
        
        <div class="adc-videos-grid">
            <div class="adc-videos-row cols-<?php echo ADC_Utilities::escape_attr($videos_per_row); ?>">
                <?php foreach ($season_videos as $video): ?>
                    <?php 
                    $video_slug = ADC_Utilities::slugify($video['title']); 
                    $thumbnail_url = isset($api) ? $api->get_thumbnail_url($video['id']) : "https://s3apics.streamgates.net/TutorahTV_Thumbs/{$video['id']}_50.jpg";
                    ?>
                    <div class="adc-video-item">
                        <a href="?categoria=<?php echo ADC_Utilities::escape_attr($category_slug); ?>&video=<?php echo ADC_Utilities::escape_attr($video_slug); ?>" class="adc-video-link">
                            <div class="adc-video-thumbnail">
                                <img src="<?php echo ADC_Utilities::escape_url($thumbnail_url); ?>" alt="<?php echo ADC_Utilities::escape_attr($video['title']); ?>">
                                <div class="adc-video-play-icon"></div>
                            </div>
                            
                            <div class="adc-video-info">
                                <h3 class="adc-video-title"><?php echo ADC_Utilities::escape_html($video['title']); ?></h3>
                                <span class="adc-video-duration">Duración: <?php echo ADC_Utilities::escape_html($video['duration']); ?></span>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>