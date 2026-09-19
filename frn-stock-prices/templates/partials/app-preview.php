<?php
if (!defined('ABSPATH')) { exit; }

$previewType = (string) ($preview['preview_type'] ?? 'stock');
$previewRows = array_merge(
    $preview['catalogs']['carne'] ?? [],
    $preview['catalogs']['pescado-marisco'] ?? []
);
$previewIncoming = array_values(array_filter(
    $previewRows,
    static fn(array $row): bool => !empty($row['incoming'])
));
$previewRegular = array_values(array_filter(
    $previewRows,
    static fn(array $row): bool => empty($row['incoming'])
));
$invalid = count(array_filter(
    $previewRows,
    static fn(array $row): bool => empty($row['valid'])
));
?>

<section class="frn-app-card frn-preview-card">
    <div class="frn-card-heading">
        <div>
            <small>Previsualización · <?php echo $previewType === 'prices' ? 'Tarifa de precios' : 'Stock semanal'; ?></small>
            <h2>Antes de guardar</h2>
        </div>
        <p><?php echo esc_html(count($previewRows)); ?> referencias · <?php echo esc_html(count($previewIncoming)); ?> próximos ingresos · <?php echo esc_html($invalid); ?> errores.</p>
    </div>

    <?php if ($previewType === 'prices') : ?>
        <div class="frn-preview-label">Tarifa: <strong><?php echo esc_html((string) ($preview['price_list_name'] ?? '')); ?></strong></div>
    <?php endif; ?>

    <div class="frn-app-table-wrap">
        <table class="frn-app-table frn-preview-table">
            <thead>
                <tr>
                    <th>Estado</th>
                    <th>Categoría</th>
                    <th>Código</th>
                    <th>Marca</th>
                    <th>Producto</th>
                    <?php if ($previewType === 'stock') : ?>
                        <th>Stock</th>
                    <?php else : ?>
                        <th>Precio</th>
                        <th>Oferta</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($previewRegular as $row) : ?>
                <tr>
                    <td>
                        <?php
                        if (empty($row['valid'])) {
                            echo '⚠ ' . esc_html(implode(', ', $row['errors']));
                        } elseif ($previewType === 'stock') {
                            echo (float) $row['stock'] > 0 ? 'Disponible' : 'Stock 0 · fuera';
                        } else {
                            echo (float) $row['price'] > 0 ? 'Precio OK' : 'Sin precio';
                        }
                        ?>
                    </td>
                    <td><?php echo esc_html($row['category'] === 'carne' ? 'Carne' : 'Pescado / Marisco'); ?></td>
                    <td><?php echo esc_html($row['code']); ?></td>
                    <td><?php echo esc_html($row['brand']); ?></td>
                    <td><?php echo esc_html($row['name']); ?></td>
                    <?php if ($previewType === 'stock') : ?>
                        <td><?php echo esc_html(number_format_i18n((float) $row['stock'], 2)); ?></td>
                    <?php else : ?>
                        <td><?php echo (float) $row['price'] > 0 ? esc_html(number_format_i18n((float) $row['price'], 2)) . ' €' : '—'; ?></td>
                        <td><?php echo !empty($row['featured']) ? 'Sí' : 'No'; ?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>

            <?php foreach ($previewIncoming as $row) : ?>
                <tr class="frn-incoming-row">
                    <td>Próximo ingreso</td>
                    <td><?php echo esc_html($row['category'] === 'carne' ? 'Carne' : 'Pescado / Marisco'); ?></td>
                    <td><?php echo esc_html($row['code']); ?></td>
                    <td><?php echo esc_html($row['brand']); ?></td>
                    <td><?php echo esc_html($row['name']); ?></td>
                    <?php if ($previewType === 'stock') : ?>
                        <td><?php echo esc_html(number_format_i18n((float) $row['stock'], 2)); ?></td>
                    <?php else : ?>
                        <td><?php echo (float) $row['price'] > 0 ? esc_html(number_format_i18n((float) $row['price'], 2)) . ' €' : '—'; ?></td>
                        <td><?php echo !empty($row['featured']) ? 'Sí' : 'No'; ?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($invalid === 0 && $previewRows) : ?>
        <form method="post" action="<?php echo esc_url($postUrl); ?>" class="frn-inline-action">
            <?php if ($previewType === 'prices') : ?>
                <input type="hidden" name="action" value="frn_front_publish_prices">
                <?php wp_nonce_field('frn_front_publish_prices_' . $previewToken); ?>
                <button type="submit">Guardar tarifa de precios</button>
            <?php else : ?>
                <input type="hidden" name="action" value="frn_front_publish_stock">
                <?php wp_nonce_field('frn_front_publish_stock_' . $previewToken); ?>
                <button type="submit">Actualizar stock semanal</button>
            <?php endif; ?>
            <input type="hidden" name="preview" value="<?php echo esc_attr($previewToken); ?>">
        </form>
    <?php endif; ?>
</section>
