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
$hasPreview = is_array($preview) && in_array(($preview['preview_type'] ?? ''), ['stock','selective'], true);
$importUrl = add_query_arg('tab', 'importar', home_url('/stock/'));
$readyToPublish = false;
if ($hasPreview) {
    $previewRows = array_merge(
        $preview['catalogs']['carne'] ?? [],
        $preview['catalogs']['pescado-marisco'] ?? []
    );
    $previewIncoming = array_values(array_filter($previewRows, static fn(array $row): bool => !empty($row['incoming'])));
    $previewRegular = array_values(array_filter($previewRows, static fn(array $row): bool => empty($row['incoming'])));
    $invalid = count(array_filter($previewRows, static fn(array $row): bool => empty($row['valid'])));
    $updates = array_merge(['stock'=>false,'price'=>false,'cost'=>false,'groups'=>false], $preview['updates'] ?? []);
    $detected = array_merge(['stock'=>false,'price'=>false,'cost'=>false,'groups'=>false], $preview['detected'] ?? []);
    $readyToPublish = $invalid === 0 && $previewRows;
    $fieldLabels = ['stock'=>'Stock','price'=>'Precio','cost'=>'Coste promedio','groups'=>'Categorías / orden'];
    $selectedFields = implode(', ', array_intersect_key($fieldLabels, array_filter($updates)));
}
$importStep = $publishedImport ? 4 : ($readyToPublish ? 3 : (($hasPreview || $importError !== '') ? 2 : 1));
?>

<section class="frn-app-card frn-import-card">
    <div class="frn-card-heading">
        <div><small>Odoo · importación controlada</small><h2>Importar Excel semanal</h2></div>
        <p>Analizar no modifica la base. Primero eliges qué contiene el Excel, después revisas el resultado y solo al pulsar Publicar se aplican los cambios seleccionados.</p>
    </div>

    <div class="frn-import-steps" aria-label="Pasos de importación" data-frn-import-step="<?php echo $importStep; ?>">
        <?php foreach ([1=>'Seleccionar', 2=>'Analizar', 3=>'Publicar'] as $step => $label) : ?>
            <span class="<?php echo $step < $importStep ? 'is-done' : ($step === $importStep ? 'is-active' : ''); ?>" <?php if ($step === $importStep) { echo 'aria-current="step"'; } ?>>
                <b aria-hidden="true"><?php echo $step < $importStep ? '✓' : $step; ?></b> <?php echo esc_html($label); ?>
                <small class="screen-reader-text" data-frn-step-status><?php echo $step < $importStep ? 'Completado' : ''; ?></small>
            </span>
        <?php endforeach; ?>
    </div>

    <?php if ($importError !== '') : ?>
        <div class="frn-import-result is-error" role="alert" tabindex="-1" data-frn-import-result>
            <strong><?php echo $publishFailed ? 'No se completó la publicación' : 'No se pudo analizar el archivo'; ?></strong>
            <p><?php echo esc_html($importError); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($publishedImport) : ?>
        <div class="frn-import-result is-success" role="status" tabindex="-1" data-frn-import-result>
            <strong>✓ Publicación completada</strong>
            <p><?php echo (int)$publishedImport['count']; ?> referencias procesadas. Campos actualizados: <?php echo esc_html($publishedImport['fields']); ?>.</p>
            <a class="frn-secondary-link" href="<?php echo esc_url($importUrl); ?>">Importar otro Excel</a>
        </div>
    <?php elseif ($hasPreview) : ?>
        <div class="frn-import-result <?php echo $readyToPublish ? 'is-success' : 'is-error'; ?>" role="<?php echo $readyToPublish ? 'status' : 'alert'; ?>" tabindex="-1" data-frn-import-result>
            <strong><?php echo $readyToPublish ? '✓ Análisis completado. Listo para revisar y publicar' : 'Análisis con errores'; ?></strong>
            <p><?php echo esc_html((string)($preview['filename'] ?? 'Excel semanal')); ?> · <?php echo count($previewRows); ?> referencias leídas · <?php echo $invalid; ?> filas con errores.</p>
            <?php if ($readyToPublish) : ?>
                <p>Campos seleccionados y detectados: <?php echo esc_html($selectedFields); ?>. Revisa el detalle de abajo antes de publicar.</p>
            <?php else : ?>
                <p>Revisa las filas señaladas abajo, corrige el Excel y vuelve a analizarlo.</p>
            <?php endif; ?>
            <?php if ($readyToPublish) : ?>
                <form id="frn-publish-import" method="post" action="<?php echo esc_url($postUrl); ?>" class="frn-inline-action frn-import-publish">
                    <input type="hidden" name="action" value="frn_front_publish_stock">
                    <input type="hidden" name="preview" value="<?php echo esc_attr($previewToken); ?>">
                    <?php wp_nonce_field('frn_front_publish_stock_' . $previewToken); ?>
                    <?php if ($updates['stock']) : ?>
                        <label><input type="checkbox" name="reset_missing_stock" value="1"> Poner a cero las referencias que no aparecen en el Excel</label>
                    <?php endif; ?>
                    <button type="submit" data-frn-busy="Publicando…">Publicar cambios seleccionados</button>
                </form>
            <?php endif; ?>
            <a class="frn-secondary-link" href="<?php echo esc_url($importUrl); ?>">Descartar y elegir otro archivo</a>
        </div>
    <?php elseif ($canEditStock || $canEditPrices) : ?>
        <form method="post" enctype="multipart/form-data" action="<?php echo esc_url($postUrl); ?>" class="frn-upload-form frn-upload-form-single">
            <input type="hidden" name="action" value="frn_front_preview_stock">
            <?php wp_nonce_field('frn_front_preview_stock'); ?>

            <div class="frn-import-fields">
                <strong>Marca únicamente lo que quieres actualizar</strong>
                <?php if ($canEditStock) : ?>
                    <label><input type="checkbox" name="import_fields[stock]" value="1" checked> Stock</label>
                    <label><input type="checkbox" name="import_fields[groups]" value="1"> Categorías / orden comercial</label>
                <?php endif; ?>
                <?php if ($canEditPrices) : ?>
                    <label><input type="checkbox" name="import_fields[price]" value="1" checked> Precio</label>
                    <label><input type="checkbox" name="import_fields[cost]" value="1" checked> Coste promedio</label>
                <?php endif; ?>
                <small>Si el Excel trae solo existencias, deja marcado únicamente Stock.</small>
            </div>

            <label class="frn-dropzone">
                <span>Selecciona el Excel</span>
                <small>XLSX / XLS · formato semanal recomendado: una hoja con Referencia interna, Precio de venta, Coste, Categoría, Pronosticado y Unidades.</small>
                <input id="frn-stock-files" type="file" name="stock_files[]" accept=".xlsx,.xls" multiple required>
            </label>
            <div id="frn-file-status" class="frn-file-status" aria-live="polite">
                <strong>Selecciona un Excel para comenzar</strong>
                <span>El archivo no se publicará hasta que revises la previsualización.</span>
            </div>
            <button type="submit" data-frn-busy="Analizando…">Analizar archivo</button>
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
