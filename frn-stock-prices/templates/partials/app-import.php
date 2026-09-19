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

<section class="frn-app-grid-two frn-import-grid">
    <article class="frn-app-card">
        <div class="frn-card-heading">
            <div><small>Stock · Odoo</small><h2>Importar STOCKS</h2></div>
        </div>
        <p class="frn-card-copy">Archivo semanal de Odoo. Lo que no venga o venga con stock 0 queda destildado para esa semana.</p>
        <form method="post" enctype="multipart/form-data" action="<?php echo esc_url($postUrl); ?>" class="frn-upload-form frn-upload-form-single">
            <input type="hidden" name="action" value="frn_front_preview_stock">
            <?php wp_nonce_field('frn_front_preview_stock'); ?>
            <label class="frn-dropzone">
                <span>Arrastra el Excel de STOCKS</span>
                <small>XLSX / XLS · puedes seleccionar varios</small>
                <input type="file" name="stock_files[]" accept=".xlsx,.xls" multiple required>
            </label>
            <button type="submit">Previsualizar stocks</button>
        </form>
    </article>

    <article class="frn-app-card">
        <div class="frn-card-heading">
            <div><small>Comercial</small><h2>Importar TARIFA</h2></div>
        </div>
        <p class="frn-card-copy">Archivo independiente de precios. Puedes guardar listas distintas: General, Valdepeice, Madrid, HORECA, etc.</p>
        <form method="post" enctype="multipart/form-data" action="<?php echo esc_url($postUrl); ?>" class="frn-price-upload">
            <input type="hidden" name="action" value="frn_front_preview_prices">
            <?php wp_nonce_field('frn_front_preview_prices'); ?>
            <label class="frn-price-name">Nombre de la tarifa
                <input type="text" name="price_list_name" placeholder="Ej. General 21/09/2026" required>
            </label>
            <label class="frn-dropzone">
                <span>Arrastra el Excel de PRECIOS</span>
                <small>XLSX / XLS · código + precio</small>
                <input type="file" name="price_files[]" accept=".xlsx,.xls" multiple required>
            </label>
            <button type="submit">Previsualizar precios</button>
        </form>
    </article>
</section>

<?php if (is_array($preview)) { require FRN_SP_PATH . 'templates/partials/app-preview.php'; } ?>

<?php if ($products) { require FRN_SP_PATH . 'templates/partials/app-stock-table.php'; } ?>

<section class="frn-app-card">
    <div class="frn-card-heading">
        <div><small>Precios guardados</small><h2>Tarifas comerciales</h2></div>
        <p>Estas listas no alteran el stock. Al crear un PDF eliges cuál aplicar.</p>
    </div>

    <div class="frn-price-list-history">
        <?php if (!$priceLists) : ?>
            <p class="frn-empty">Todavía no hay tarifas de precios guardadas.</p>
        <?php endif; ?>

        <?php foreach ($priceLists as $list) : ?>
            <div class="frn-price-list-item">
                <strong><?php echo esc_html($list['name']); ?></strong>
                <span><?php echo esc_html((string) $list['item_count']); ?> referencias · <?php echo esc_html(mysql2date('d/m/Y H:i', $list['created_at'])); ?></span>
                <small><?php echo esc_html($list['source_file']); ?></small>
            </div>
        <?php endforeach; ?>
    </div>
</section>
