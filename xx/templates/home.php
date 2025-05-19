<?php
/**
 * Plantilla para la página principal (grid de categorías)
 * 
 * @package ADC Video Display
 */

// Asegurar que no se accede directamente
if (!defined('ABSPATH')) {
    exit;
}

// Extraemos las variables de $variables
// $programs - Lista de programas/categorías disponibles
// $section - ID de la sección actual
// $section_name - Nombre de la sección actual
?>

<div class="adc-categories-grid">
    <div class="adc-categories-row">
        <?php if (empty($programs)): ?>
            <div class="adc-error">No se encontraron programas disponibles.</div>
        <?php else: ?>
            <?php foreach ($programs as $program): ?>
                <?php 
                $slug = ADC_Utilities::slugify($program['name']);
                $cover_image = isset($program['cover']) ? $program['cover'] : ADC_PLUGIN_URL . 'assets/img/no-cover.jpg';
                ?>
                <div class="adc-category-card-wrapper">
                    <a class="adc-category-card" href="?categoria=<?php echo ADC_Utilities::escape_attr($slug); ?>">
                        <div class="adc-category-image-circle">
                            <img src="<?php echo ADC_Utilities::escape_url($cover_image); ?>" alt="<?php echo ADC_Utilities::escape_attr($program['name']); ?>">
                        </div>
                        <div class="adc-category-name"><?php echo ADC_Utilities::escape_html($program['name']); ?></div>
                    </a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>