<?php
if (!defined('ABSPATH')) { exit; }

$previewRows = array_merge(
    $preview['catalogs']['carne'] ?? [],
    $preview['catalogs']['pescado-marisco'] ?? []
);
$previewIncoming = array_values(array_filter($previewRows, static fn(array $row): bool => !empty($row['incoming'])));
$previewRegular = array_values(array_filter($previewRows, static fn(array $row): bool => empty($row['incoming'])));
$invalid = count(array_filter($previewRows, static fn(array $row): bool => empty($row['valid'])));
?>

<section class="frn-app-card frn-preview-card">
    <div class="frn-card-heading">
        <div><small>Previsualización semanal</small><h2>Antes de guardar</h2></div>
        <p><?php echo esc_html(count($previewRows)); ?> referencias con stock · <?php echo esc_html(count($previewIncoming)); ?> próximos ingresos · <?php echo esc_html($invalid); ?> errores.</p>
    </div>

    <div class="frn-app-table-wrap">
        <table class="frn-app-table frn-preview-table">
            <thead>
                <tr>
                    <th>Estado</th><th>Categoría</th><th>Código</th><th>Marca</th><th>Producto</th>
                    <th>Stock</th><th>Precio origen</th>
                    <?php if ($canViewCost) : ?><th>Coste promedio</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach (array_merge($previewRegular, $previewIncoming) as $row) : ?>
                <tr class="<?php echo !empty($row['incoming']) ? 'frn-incoming-row' : ''; ?>">
                    <td><?php echo empty($row['valid']) ? '⚠ ' . esc_html(implode(', ', $row['errors'])) : (!empty($row['incoming']) ? 'Próximo ingreso' : 'Disponible'); ?></td>
                    <td><?php echo esc_html($row['category'] === 'carne' ? 'Carne' : 'Pescado / Marisco'); ?></td>
                    <td><?php echo esc_html($row['code']); ?></td>
                    <td><?php echo esc_html($row['brand']); ?></td>
                    <td><?php echo esc_html($row['name']); ?></td>
                    <td><?php echo esc_html(number_format_i18n((float)$row['stock'], 3)); ?> <?php echo esc_html((string)$row['unit']); ?></td>
                    <td><?php echo (float)$row['price'] > 0 ? esc_html(number_format_i18n((float)$row['price'], 2)) . ' €' : '—'; ?></td>
                    <?php if ($canViewCost) : ?>
                        <td><?php echo (float)$row['cost'] > 0 ? esc_html(number_format_i18n((float)$row['cost'], 2)) . ' €' : '—'; ?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($invalid === 0 && $previewRows && $canEditStock) : ?>
        <form method="post" action="<?php echo esc_url($postUrl); ?>" class="frn-inline-action">
            <input type="hidden" name="action" value="frn_front_publish_stock">
            <input type="hidden" name="preview" value="<?php echo esc_attr($previewToken); ?>">
            <?php wp_nonce_field('frn_front_publish_stock_' . $previewToken); ?>
            <button type="submit">Guardar semana</button>
        </form>
    <?php endif; ?>
</section>
