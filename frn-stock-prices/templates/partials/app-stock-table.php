<?php
if (!defined('ABSPATH')) { exit; }

$renderMasterRows = static function(array $rows, string $group): void {
    if (!$rows) {
        echo '<div class="frn-empty">No hay referencias en esta sección.</div>';
        return;
    }
    ?>
    <div class="frn-app-table-wrap">
        <table class="frn-app-table frn-master-table">
            <thead>
                <tr>
                    <th>Usar</th>
                    <th>Categoría</th>
                    <th>Código</th>
                    <th>Marca</th>
                    <th>Producto</th>
                    <th>Stock</th><th>Unidad</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $product) :
                $id = (int) $product['id'];
                $stock = (float) $product['stock_kg'];
                $incoming = (int) $product['incoming'] === 1;
            ?>
                <tr class="<?php echo $incoming ? 'frn-incoming-row' : ''; ?>" data-stock="<?php echo esc_attr($stock); ?>">
                    <td>
                        <input class="frn-use-checkbox"
                               data-group="<?php echo esc_attr($group); ?>"
                               type="checkbox"
                               name="products[<?php echo $id; ?>][visible]"
                               value="1"
                               <?php checked((int) $product['visible'], 1); ?>>
                    </td>
                    <td><?php echo esc_html($product['category'] === 'carne' ? 'Carne' : 'Pescado / Marisco'); ?></td>
                    <td><input type="text" name="products[<?php echo $id; ?>][code]" value="<?php echo esc_attr($product['product_code']); ?>"></td>
                    <td><input type="text" name="products[<?php echo $id; ?>][brand]" value="<?php echo esc_attr($product['brand']); ?>"></td>
                    <td><input class="frn-wide" type="text" name="products[<?php echo $id; ?>][name]" value="<?php echo esc_attr($product['product_name']); ?>"></td>
                    <td><input type="number" step="0.01" name="products[<?php echo $id; ?>][stock]" value="<?php echo esc_attr($stock); ?>"></td><td><?php echo esc_html((string)($product['unit'] ?? '')); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
};
?>

<section class="frn-app-card">
    <div class="frn-card-heading">
        <div><small>Stock vigente</small><h2>Preparar la semana</h2></div>
        <p>Los precios ya no se guardan aquí. Esta tabla representa solamente disponibilidad real y el maestro de productos.</p>
    </div>

    <form method="post" action="<?php echo esc_url($postUrl); ?>">
        <input type="hidden" name="action" value="frn_front_save_products">
        <?php wp_nonce_field('frn_front_save_products'); ?>

        <h3>Productos disponibles</h3>
        <div class="frn-selection-tools">
            <span>Selección:</span>
            <button type="button" class="frn-mini-button" data-frn-select="all" data-group="current">Tildar todos</button>
            <button type="button" class="frn-mini-button" data-frn-select="none" data-group="current">Destildar todos</button>
            <button type="button" class="frn-mini-button" data-frn-select="stock" data-group="current">Solo con stock</button>
        </div>
        <?php $renderMasterRows($currentProducts, 'current'); ?>

        <h3 class="frn-incoming-title">Próximos ingresos <small>códigos XXX, XXXX, XXXXX…</small></h3>
        <div class="frn-selection-tools">
            <span>Selección:</span>
            <button type="button" class="frn-mini-button" data-frn-select="all" data-group="incoming">Tildar todos</button>
            <button type="button" class="frn-mini-button" data-frn-select="none" data-group="incoming">Destildar todos</button>
        </div>
        <?php $renderMasterRows($incomingProducts, 'incoming'); ?>

        <div class="frn-inline-action">
            <button type="submit">Guardar stock y selección</button>
        </div>
    </form>
</section>
