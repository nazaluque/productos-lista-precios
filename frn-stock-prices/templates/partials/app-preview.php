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

$stockTotals = [
    'carne' => ['kg'=>0.0,'units'=>0.0],
    'pescado-marisco' => ['kg'=>0.0,'units'=>0.0],
];
$negativeStock = 0;
$zeroPrice = 0;
$belowCost = 0;
foreach ($previewRows as $row) {
    if (empty($row['stock_present'])) { continue; }
    $category = (string)($row['category'] ?? '');
    if (!isset($stockTotals[$category])) { continue; }
    $unit = strtolower(remove_accents(trim((string)($row['unit'] ?? ''))));
    $bucket = str_contains($unit, 'unidad') || in_array($unit, ['ud','uds'], true) ? 'units' : 'kg';
    $stockValue = (float)($row['stock'] ?? 0);
    $stockTotals[$category][$bucket] += $stockValue;
    if (!empty($row['stock_present']) && $stockValue < 0) { $negativeStock++; }

    $priceValue = !empty($row['price_present']) ? (float)($row['price'] ?? 0) : null;
    $costValue = !empty($row['cost_present']) ? (float)($row['cost'] ?? 0) : null;
    if ($priceValue !== null && $priceValue == 0.0) { $zeroPrice++; }
    if ($priceValue !== null && $costValue !== null && $priceValue > 0 && $priceValue < $costValue) { $belowCost++; }
}
?>
<section class="frn-app-card frn-preview-card">
    <div class="frn-card-heading">
        <div><small>Previsualización semanal</small><h2>Antes de guardar</h2></div>
        <?php $pendingCategories = count(array_filter($previewRows, static fn(array $row): bool => !empty($row['needs_group_assignment']))); ?>
        <p><?php echo esc_html(count($previewRows)); ?> referencias · <?php echo esc_html(count($previewIncoming)); ?> próximos ingresos · <?php echo esc_html($invalid); ?> errores · <?php echo esc_html($pendingCategories); ?> categoría(s) por confirmar.</p>
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

    <?php if (!empty($detected['stock'])) : ?>
        <div class="frn-stock-audit">
            <strong>Totales detectados por unidad</strong>
            <span>Carne: <?php echo esc_html(number_format_i18n($stockTotals['carne']['kg'],3)); ?> kg · <?php echo esc_html(number_format_i18n($stockTotals['carne']['units'],0)); ?> unidades</span>
            <span>Pescado/Marisco: <?php echo esc_html(number_format_i18n($stockTotals['pescado-marisco']['kg'],3)); ?> kg · <?php echo esc_html(number_format_i18n($stockTotals['pescado-marisco']['units'],0)); ?> unidades</span>
            <small>No se mezclan kg y unidades en un único total.</small>
        </div>
    <?php endif; ?>

    <?php if ($negativeStock || $zeroPrice || $belowCost) : ?>
        <div class="frn-import-warnings">
            <strong>Controles comerciales</strong>
            <?php if ($negativeStock) : ?><span>⚠ <?php echo (int)$negativeStock; ?> referencia(s) con stock negativo · se publicarán como 0 / no disponible.</span><?php endif; ?>
            <?php if ($zeroPrice) : ?><span>⚠ <?php echo (int)$zeroPrice; ?> referencia(s) con precio 0 · el PDF mostrará “Consultar precio”.</span><?php endif; ?>
            <?php if ($belowCost) : ?><span>⚠ <?php echo (int)$belowCost; ?> referencia(s) con precio de venta inferior al coste.</span><?php endif; ?>
        </div>
    <?php endif; ?>

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

    <?php if ($pendingCategories > 0) : ?>
        <div class="frn-category-assignment-alert">
            <strong>Productos con categoría pendiente</strong>
            <span>Selecciona la categoría en la columna “Grupo comercial”. La publicación no continuará si alguno queda sin asignar.</span>
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
                    <td>
                        <?php if (!empty($row['needs_group_assignment'])) :
                            $categoryKey = (string)($row['category'] ?? '');
                            $suggestedGroup = (string)($row['suggested_group'] ?? '');
                            $groupsForCategory = (array)(($preview['commercial_groups'][$categoryKey] ?? []));
                            $codeKey = strtoupper(trim((string)($row['code'] ?? '')));
                        ?>
                            <select
                                name="group_assignments[<?php echo esc_attr($categoryKey); ?>][<?php echo esc_attr($codeKey); ?>]"
                                form="frn-publish-import"
                                required
                                class="frn-preview-group-select"
                            >
                                <option value="">Seleccionar categoría…</option>
                                <?php foreach ($groupsForCategory as $group) : ?>
                                    <option value="<?php echo esc_attr((string)$group['name_base']); ?>" <?php selected($suggestedGroup, (string)$group['name_base']); ?>>
                                        <?php echo esc_html((string)$group['name_base']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="frn-category-required"><?php echo !empty($row['is_new']) ? 'Producto nuevo · confirmar categoría' : 'Categoría pendiente'; ?></small>
                        <?php else :
                            $groupName = trim((string)($row['commercial_group'] ?? ''));
                            echo esc_html($groupName !== '' ? $groupName : 'SIN CATEGORÍA');
                        endif; ?>
                    </td>
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
        <form id="frn-publish-import" method="post" action="<?php echo esc_url($postUrl); ?>" class="frn-inline-action">
            <input type="hidden" name="action" value="frn_front_publish_stock">
            <input type="hidden" name="preview" value="<?php echo esc_attr($previewToken); ?>">
            <?php wp_nonce_field('frn_front_publish_stock_' . $previewToken); ?>
            <button type="submit">Publicar cambios seleccionados</button>
        </form>
    <?php endif; ?>
</section>
