<?php
if (!defined('ABSPATH')) { exit; }

$previewRows = array_merge(
    $preview['catalogs']['carne'] ?? [],
    $preview['catalogs']['pescado-marisco'] ?? []
);
$previewIncoming = array_values(array_filter($previewRows, static fn(array $row): bool => !empty($row['incoming'])));
$previewRegular = array_values(array_filter($previewRows, static fn(array $row): bool => empty($row['incoming'])));
$invalid = count(array_filter($previewRows, static fn(array $row): bool => empty($row['valid'])));
$updates = array_merge(['stock'=>false,'price'=>false,'cost'=>false,'groups'=>false], $preview['updates'] ?? []);
$detected = array_merge(['stock'=>false,'price'=>false,'cost'=>false,'groups'=>false], $preview['detected'] ?? []);
$ungrouped = $preview['ungrouped'] ?? ['carne'=>[],'pescado-marisco'=>[]];
?>
<section class="frn-app-card frn-preview-card">
    <div class="frn-card-heading">
        <div><small>Previsualización semanal</small><h2>Antes de guardar</h2></div>
        <p><?php echo esc_html(count($previewRows)); ?> referencias · <?php echo esc_html(count($previewIncoming)); ?> próximos ingresos · <?php echo esc_html($invalid); ?> errores.</p>
    </div>

    <div class="frn-import-audit">
        <div><small>Archivo</small><strong><?php echo esc_html((string)($preview['filename'] ?? '')); ?></strong></div>
        <div><small>Hoja(s) de datos</small><strong><?php echo esc_html(implode(', ', (array)($preview['data_sheets'] ?? [])) ?: '—'); ?></strong></div>
        <div><small>Resumen(es)</small><strong><?php echo esc_html(implode(', ', (array)($preview['summary_sheets'] ?? [])) ?: '—'); ?></strong></div>
        <?php foreach (['stock'=>'Stock','price'=>'Precio','cost'=>'Coste','groups'=>'Categorías / orden'] as $key=>$label) : ?>
            <div>
                <small><?php echo esc_html($label); ?></small>
                <strong class="<?php echo !empty($updates[$key]) ? 'is-update' : ''; ?>">
                    <?php echo !empty($updates[$key]) ? 'SE ACTUALIZARÁ' : 'NO SE TOCA'; ?>
                    · <?php echo !empty($detected[$key]) ? 'detectado' : 'no detectado'; ?>
                </strong>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($updates['groups'])) : ?>
        <div class="frn-group-audit">
            <strong>Categorías comerciales detectadas</strong>
            <span>Carne: <?php echo count((array)($preview['groups']['carne'] ?? [])); ?> grupos · Pescado/Marisco: <?php echo count((array)($preview['groups']['pescado-marisco'] ?? [])); ?> grupos</span>
            <?php $missing = array_merge((array)($ungrouped['carne']??[]),(array)($ungrouped['pescado-marisco']??[])); ?>
            <?php if ($missing) : ?>
                <span class="frn-warning">⚠ Sin categoría de Resumen: <?php echo esc_html(implode(', ', $missing)); ?></span>
            <?php else : ?>
                <span class="frn-ok">✓ Todas las referencias tienen categoría comercial.</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="frn-app-table-wrap">
        <table class="frn-app-table frn-preview-table">
            <thead><tr>
                <th>Estado</th><th>Categoría</th><th>Grupo comercial</th><th>Código</th><th>Marca</th><th>Producto</th>
                <th>Stock</th><th>Precio</th><?php if ($canViewCost) : ?><th>Coste</th><?php endif; ?>
            </tr></thead>
            <tbody>
            <?php foreach (array_merge($previewRegular, $previewIncoming) as $row) : ?>
                <tr class="<?php echo !empty($row['incoming']) ? 'frn-incoming-row' : ''; ?>">
                    <td><?php echo empty($row['valid']) ? '⚠ ' . esc_html(implode(', ', $row['errors'])) : (!empty($row['incoming']) ? 'Próximo ingreso' : 'OK'); ?></td>
                    <td><?php echo esc_html($row['category'] === 'carne' ? 'Carne' : 'Pescado / Marisco'); ?></td>
                    <td><?php echo esc_html((string)($row['commercial_group'] ?? '—')); ?></td>
                    <td><?php echo esc_html($row['code']); ?></td>
                    <td><?php echo esc_html($row['brand']); ?></td>
                    <td><?php echo esc_html($row['name']); ?></td>
                    <td><?php echo !empty($row['stock_present']) ? esc_html(number_format_i18n((float)$row['stock'],3)).' '.esc_html((string)$row['unit']) : '—'; ?></td>
                    <td><?php echo !empty($row['price_present']) ? esc_html(number_format_i18n((float)$row['price'],2)).' €' : '—'; ?></td>
                    <?php if ($canViewCost) : ?><td><?php echo !empty($row['cost_present']) ? esc_html(number_format_i18n((float)$row['cost'],2)).' €' : '—'; ?></td><?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($invalid === 0 && $previewRows) : ?>
        <form method="post" action="<?php echo esc_url($postUrl); ?>" class="frn-inline-action">
            <input type="hidden" name="action" value="frn_front_publish_stock">
            <input type="hidden" name="preview" value="<?php echo esc_attr($previewToken); ?>">
            <?php wp_nonce_field('frn_front_publish_stock_' . $previewToken); ?>
            <button type="submit">Publicar solo los campos seleccionados</button>
        </form>
    <?php endif; ?>
</section>
