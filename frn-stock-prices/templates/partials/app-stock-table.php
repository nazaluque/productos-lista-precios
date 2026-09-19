<?php
if (!defined('ABSPATH')) { exit; }

$renderMasterRows = static function(array $rows, string $group) use ($canEditStock, $canEditPrices, $canViewCost): void {
    if (!$rows) {
        echo '<div class="frn-empty">No hay referencias en esta sección.</div>';
        return;
    }
    ?>
    <div class="frn-app-table-wrap">
        <table class="frn-app-table frn-master-table">
            <thead>
                <tr>
                    <th>Usar</th><th>Oferta</th><th>Categoría</th><th>Código</th><th>Marca</th><th>Producto</th>
                    <th>Stock</th><th>Unidad</th><th>Precio origen</th><th>Precio comercial</th>
                    <?php if ($canViewCost) : ?><th>Coste promedio</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $product) :
                $id = (int) $product['id'];
                $stock = (float) $product['stock_kg'];
                $incoming = (int) $product['incoming'] === 1;
                $sourcePrice = (float) ($product['source_price_kg'] ?? 0);
                $commercialPrice = (float) ($product['price_kg'] ?? 0);
                $cost = (float) ($product['average_cost_kg'] ?? 0);
            ?>
                <tr class="<?php echo $incoming ? 'frn-incoming-row' : ''; ?>" data-stock="<?php echo esc_attr($stock); ?>">
                    <td><input class="frn-use-checkbox" data-group="<?php echo esc_attr($group); ?>" type="checkbox" name="products[<?php echo $id; ?>][visible]" value="1" <?php checked((int)$product['visible'],1); ?>></td>
                    <td><input class="frn-offer-checkbox" data-group="<?php echo esc_attr($group); ?>" type="checkbox" name="products[<?php echo $id; ?>][featured]" value="1" <?php checked((int)$product['featured'],1); ?>></td>
                    <td><?php echo esc_html($product['category'] === 'carne' ? 'Carne' : 'Pescado / Marisco'); ?></td>
                    <td>
                        <?php if ($canEditStock) : ?>
                            <input type="text" name="products[<?php echo $id; ?>][code]" value="<?php echo esc_attr($product['product_code']); ?>">
                        <?php else : echo esc_html($product['product_code']); endif; ?>
                    </td>
                    <td>
                        <?php if ($canEditStock) : ?>
                            <input type="text" name="products[<?php echo $id; ?>][brand]" value="<?php echo esc_attr($product['brand']); ?>">
                        <?php else : echo esc_html($product['brand']); endif; ?>
                    </td>
                    <td>
                        <?php if ($canEditStock) : ?>
                            <input class="frn-wide" type="text" name="products[<?php echo $id; ?>][name]" value="<?php echo esc_attr($product['product_name']); ?>">
                        <?php else : echo esc_html($product['product_name']); endif; ?>
                    </td>
                    <td>
                        <?php if ($canEditStock) : ?>
                            <input type="number" min="0" step="0.001" name="products[<?php echo $id; ?>][stock]" value="<?php echo esc_attr($stock); ?>">
                        <?php else : echo esc_html(number_format_i18n($stock,3)); endif; ?>
                    </td>
                    <td><?php echo esc_html((string)($product['unit'] ?? '')); ?></td>
                    <td><?php echo $sourcePrice > 0 ? esc_html(number_format_i18n($sourcePrice,2)) . ' €' : '—'; ?></td>
                    <td>
                        <?php if ($canEditPrices) : ?>
                            <input type="number" min="0" step="0.01" name="products[<?php echo $id; ?>][price]" value="<?php echo $commercialPrice > 0 ? esc_attr($commercialPrice) : ''; ?>" placeholder="Pendiente">
                        <?php else : ?>
                            <?php echo $commercialPrice > 0 ? esc_html(number_format_i18n($commercialPrice,2)) . ' €' : 'Pendiente'; ?>
                        <?php endif; ?>
                    </td>
                    <?php if ($canViewCost) : ?>
                        <td><?php echo $cost > 0 ? esc_html(number_format_i18n($cost,2)) . ' €' : '—'; ?></td>
                    <?php endif; ?>
                </tr>
                <?php if (!$canEditStock) : ?>
                    <input type="hidden" name="products[<?php echo $id; ?>][code]" value="<?php echo esc_attr($product['product_code']); ?>">
                    <input type="hidden" name="products[<?php echo $id; ?>][brand]" value="<?php echo esc_attr($product['brand']); ?>">
                    <input type="hidden" name="products[<?php echo $id; ?>][name]" value="<?php echo esc_attr($product['product_name']); ?>">
                    <input type="hidden" name="products[<?php echo $id; ?>][stock]" value="<?php echo esc_attr($stock); ?>">
                <?php endif; ?>
                <?php if (!$canEditPrices) : ?>
                    <input type="hidden" name="products[<?php echo $id; ?>][price]" value="<?php echo esc_attr($commercialPrice); ?>">
                <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
};
?>

<section class="frn-app-card">
    <div class="frn-card-heading">
        <div><small>Maestro FRN</small><h2>Productos de la semana</h2></div>
        <p>El stock vigente viene de Odoo. El precio comercial se conserva entre semanas y puede ser ajustado por los perfiles autorizados. El coste promedio solo se muestra a usuarios con permiso específico.</p>
    </div>

    <form method="post" action="<?php echo esc_url($postUrl); ?>">
        <input type="hidden" name="action" value="frn_front_save_products">
        <?php wp_nonce_field('frn_front_save_products'); ?>

        <h3>Productos</h3>
        <div class="frn-selection-tools">
            <span>Selección:</span>
            <button type="button" class="frn-mini-button" data-frn-select="all" data-group="current">Tildar todos</button>
            <button type="button" class="frn-mini-button" data-frn-select="none" data-group="current">Destildar todos</button>
            <button type="button" class="frn-mini-button" data-frn-select="stock" data-group="current">Solo con stock</button>
            <span class="frn-tool-divider">Oferta:</span>
            <button type="button" class="frn-mini-button" data-frn-offer="all" data-group="current">Marcar todas</button>
            <button type="button" class="frn-mini-button" data-frn-offer="none" data-group="current">Quitar todas</button>
        </div>
        <?php $renderMasterRows($currentProducts, 'current'); ?>

        <h3 class="frn-incoming-title">Próximos ingresos <small>códigos XXX, XXXX, XXXXX…</small></h3>
        <?php $renderMasterRows($incomingProducts, 'incoming'); ?>

        <?php if ($canEditStock || $canEditPrices) : ?>
            <div class="frn-inline-action"><button type="submit">Guardar cambios</button></div>
        <?php endif; ?>
    </form>
</section>
