<?php
if (!defined('ABSPATH')) { exit; }

$currentProducts = array_values(array_filter(
    $products,
    static fn(array $product): bool => (int) $product['incoming'] !== 1
));
$incomingProducts = array_values(array_filter(
    $products,
    static fn(array $product): bool => (int) $product['incoming'] === 1
));
$hasPreview = is_array($preview);
?>

<section class="frn-app-card frn-import-card">
    <div class="frn-card-heading">
        <div><small>Odoo · importación controlada</small><h2>Importar Excel semanal</h2></div>
        <p>Analizar no modifica la base. Primero eliges qué contiene el Excel, después revisas el resultado y solo al pulsar Publicar se aplican los cambios seleccionados.</p>
    </div>

    <div class="frn-import-steps">
        <span class="<?php echo $hasPreview ? 'is-done' : 'is-active'; ?>"><b>1</b> Seleccionar</span>
        <span class="<?php echo $hasPreview ? 'is-active' : ''; ?>"><b>2</b> Analizar</span>
        <span><b>3</b> Publicar</span>
    </div>

    <?php if ($hasPreview) : ?>
        <div class="frn-analysis-ready">
            <div>
                <small>Archivo analizado · todavía NO se ha publicado</small>
                <strong><?php echo esc_html((string)($preview['filename'] ?? 'Excel semanal')); ?></strong>
                <span>Revisa el análisis que aparece justo debajo. Si está correcto, pulsa “Publicar cambios seleccionados”.</span>
            </div>
            <a class="frn-secondary-link" href="<?php echo esc_url(add_query_arg('tab','importar',home_url('/stock/'))); ?>">Descartar y elegir otro archivo</a>
        </div>
    <?php elseif ($canEditStock || $canEditPrices) : ?>
        <form method="post" enctype="multipart/form-data" action="<?php echo esc_url($postUrl); ?>" class="frn-upload-form frn-upload-form-single">
            <input type="hidden" name="action" value="frn_front_preview_stock">
            <?php wp_nonce_field('frn_front_preview_stock'); ?>

            <div class="frn-import-fields">
                <strong>1. Marca únicamente lo que quieres actualizar</strong>
                <?php if ($canEditStock) : ?>
                    <label><input type="checkbox" name="import_fields[stock]" value="1" checked> Stock</label>
                    <label><input type="checkbox" name="import_fields[groups]" value="1" checked> Categorías / orden desde Resumen</label>
                <?php endif; ?>
                <?php if ($canEditPrices) : ?>
                    <label><input type="checkbox" name="import_fields[price]" value="1"> Precio</label>
                    <label><input type="checkbox" name="import_fields[cost]" value="1"> Coste promedio</label>
                <?php endif; ?>
                <small>Protección: un campo no marcado nunca se modifica. Una columna ausente o una celda vacía no borra un precio o coste existente.</small>
            </div>

            <label class="frn-dropzone">
                <span>2. Selecciona el Excel</span>
                <small>XLSX / XLS · admite Resumen Carne, Resumen Pescado y una hoja maestra de datos.</small>
                <input id="frn-stock-files" type="file" name="stock_files[]" accept=".xlsx,.xls" multiple required>
            </label>
            <div id="frn-file-status" class="frn-file-status" aria-live="polite">
                <strong>Selecciona un Excel para comenzar</strong>
                <span>El archivo no se publicará hasta que revises la previsualización.</span>
            </div>
            <button type="submit">Analizar archivo</button>
        </form>
    <?php else : ?>
        <div class="frn-empty">Tu perfil puede consultar y exportar, pero no modificar datos del maestro.</div>
    <?php endif; ?>
</section>

<?php if ($hasPreview) { require FRN_SP_PATH . 'templates/partials/app-preview.php'; } ?>

<?php if (!empty($latestImport)) : ?>
<section class="frn-active-source">
    <div>
        <small>Última importación publicada</small>
        <strong><?php echo esc_html($latestImport['source_file'] ?: 'Excel semanal'); ?></strong>
    </div>
    <span>
        <?php echo esc_html(mysql2date('d/m/Y H:i', $latestImport['imported_at'])); ?>
        <?php if (!empty($latestImport['user_name'])) : ?>· por <?php echo esc_html($latestImport['user_name']); ?><?php endif; ?>
        · <?php echo (int)$latestImport['active_count']; ?> referencias activas
        <?php $lastFields = (string)get_option('frn_sp_last_import_fields',''); ?>
        <?php if ($lastFields !== '') : ?>· actualizado: <?php echo esc_html($lastFields); ?><?php endif; ?>
    </span>
</section>
<?php endif; ?>

<?php if ($products) { require FRN_SP_PATH . 'templates/partials/app-stock-table.php'; } ?>
