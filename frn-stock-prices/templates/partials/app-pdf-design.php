<?php
if (!defined('ABSPATH')) { exit; }

$labels = [
    'carne' => ['title' => 'Foto Carne', 'field' => 'frn_pdf_header_carne', 'help' => 'Imagen horizontal limpia, sin fecha ni textos incrustados.'],
    'pescado' => ['title' => 'Foto Pescado / Marisco', 'field' => 'frn_pdf_header_pescado', 'help' => 'Imagen horizontal limpia, sin fecha ni textos incrustados.'],
    'logo' => ['title' => 'Logo oficial FRN', 'field' => 'frn_pdf_logo', 'help' => 'PNG preferentemente con fondo transparente.'],
];
?>
<section class="frn-app-card">
    <div class="frn-card-heading">
        <div>
            <small>Branding del PDF</small>
            <h2>Diseño PDF</h2>
        </div>
        <p>Estas imágenes se guardan en la biblioteca de medios de WordPress y son las únicas que usa el generador PDF. Si falta alguna o el archivo está dañado, el PDF no se podrá exportar.</p>
    </div>

    <form method="post" enctype="multipart/form-data" action="<?php echo esc_url($postUrl); ?>" class="frn-simple-form">
        <input type="hidden" name="action" value="frn_front_pdf_branding">
        <?php wp_nonce_field('frn_front_pdf_branding'); ?>

        <div class="frn-branding-grid">
            <?php foreach ($labels as $key => $item) :
                $state = $pdfBranding[$key] ?? ['valid'=>false,'url'=>''];
            ?>
                <div class="frn-branding-item">
                    <strong><?php echo esc_html($item['title']); ?></strong>
                    <span class="<?php echo !empty($state['valid']) ? 'frn-branding-ok' : 'frn-branding-missing'; ?>">
                        <?php echo !empty($state['valid']) ? '✓ Cargada y validada' : '⚠ Falta configurar'; ?>
                    </span>
                    <?php if (!empty($state['valid']) && !empty($state['url'])) : ?>
                        <img src="<?php echo esc_url($state['url']); ?>" alt="" style="display:block;max-width:360px;max-height:150px;margin:12px 0;border:1px solid rgba(255,255,255,.12);object-fit:contain">
                    <?php endif; ?>
                    <label>
                        <?php echo esc_html($item['help']); ?>
                        <input type="file" name="<?php echo esc_attr($item['field']); ?>" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
                    </label>
                </div>
            <?php endforeach; ?>
        </div>

        <p class="frn-save-note">No hace falta volver a subir una imagen que ya aparece como “Cargada y validada”. Solo selecciona el archivo que quieras sustituir.</p>
        <button type="submit">Guardar y validar diseño PDF</button>
    </form>
</section>
