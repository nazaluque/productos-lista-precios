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
        <p>Se importan únicamente referencias con stock positivo. Los productos ya conocidos que dejen de venir se conservan en el maestro e histórico, pero quedan con stock 0 y fuera de la disponibilidad actual.</p>
    </div>

    <?php if ($canEditStock) : ?>
        <form method="post" enctype="multipart/form-data" action="<?php echo esc_url($postUrl); ?>" class="frn-upload-form frn-upload-form-single">
            <input type="hidden" name="action" value="frn_front_preview_stock">
            <?php wp_nonce_field('frn_front_preview_stock'); ?>
            <label class="frn-dropzone">
                <span>Arrastra el Excel semanal</span>
                <small>XLSX / XLS · una o varias hojas · uno o varios archivos</small>
                <input type="file" name="stock_files[]" accept=".xlsx,.xls" multiple required>
            </label>
            <button type="submit">Previsualizar importación</button>
        </form>
    <?php else : ?>
        <div class="frn-empty">Tu perfil puede consultar y exportar, pero no importar stock.</div>
    <?php endif; ?>
</section>

<?php if (is_array($preview)) { require FRN_SP_PATH . 'templates/partials/app-preview.php'; } ?>

<?php if ($products) { require FRN_SP_PATH . 'templates/partials/app-stock-table.php'; } ?>
