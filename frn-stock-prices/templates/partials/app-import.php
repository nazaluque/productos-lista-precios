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
?>

<section class="frn-app-card frn-import-card">
    <div class="frn-card-heading">
        <div><small>Odoo · archivo semanal único</small><h2>Importar Excel semanal</h2></div>
        <p>Elige explícitamente si el archivo actualiza stock, precio, coste y/o categorías. Las hojas Resumen definen la estructura comercial; la hoja de datos actualiza únicamente los campos seleccionados.</p>
    </div>

    <?php if ($canEditStock || $canEditPrices) : ?>
        <form method="post" enctype="multipart/form-data" action="<?php echo esc_url($postUrl); ?>" class="frn-upload-form frn-upload-form-single">
            <input type="hidden" name="action" value="frn_front_preview_stock">
            <?php wp_nonce_field('frn_front_preview_stock'); ?>

            <div class="frn-import-fields">
                <strong>¿Qué contiene este archivo y qué quieres actualizar?</strong>
                <?php if ($canEditStock) : ?>
                    <label><input type="checkbox" name="import_fields[stock]" value="1" checked> Stock</label>
                    <label><input type="checkbox" name="import_fields[groups]" value="1" checked> Categorías / orden desde Resumen</label>
                <?php endif; ?>
                <?php if ($canEditPrices) : ?>
                    <label><input type="checkbox" name="import_fields[price]" value="1"> Precio</label>
                    <label><input type="checkbox" name="import_fields[cost]" value="1"> Coste promedio</label>
                <?php endif; ?>
                <small>Solo se modifican los campos marcados. Una columna ausente o una celda vacía no borra datos existentes.</small>
            </div>

            <label class="frn-dropzone">
                <span>Arrastra el Excel semanal</span>
                <small>XLSX / XLS · admite hojas Resumen + hoja maestra de datos</small>
                <input id="frn-stock-files" type="file" name="stock_files[]" accept=".xlsx,.xls" multiple required>
            </label>
            <div id="frn-file-status" class="frn-file-status" aria-live="polite">
                <strong>Ningún archivo seleccionado</strong>
                <span>Selecciona el Excel para poder previsualizarlo.</span>
            </div>
            <button type="submit">Analizar antes de importar</button>
        </form>
    <?php else : ?>
        <div class="frn-empty">Tu perfil puede consultar y exportar, pero no modificar datos del maestro.</div>
    <?php endif; ?>
</section>

<?php if (!empty($latestImport)) : ?>
<section class="frn-active-source">
    <div>
        <small>Semana activa</small>
        <strong><?php echo esc_html($latestImport['source_file'] ?: 'Excel semanal'); ?></strong>
    </div>
    <span>
        Publicada <?php echo esc_html(mysql2date('d/m/Y H:i', $latestImport['imported_at'])); ?>
        <?php if (!empty($latestImport['user_name'])) : ?>· por <?php echo esc_html($latestImport['user_name']); ?><?php endif; ?>
        · <?php echo (int)$latestImport['active_count']; ?> referencias activas
    </span>
</section>
<?php endif; ?>

<?php if (is_array($preview)) { require FRN_SP_PATH . 'templates/partials/app-preview.php'; } ?>

<?php if ($products) { require FRN_SP_PATH . 'templates/partials/app-stock-table.php'; } ?>
