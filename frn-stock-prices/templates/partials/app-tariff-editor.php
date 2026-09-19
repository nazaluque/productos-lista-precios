<?php
if (!defined('ABSPATH')) { exit; }

$scope = (string) ($tariff['catalog_scope'] ?? 'all');
$scopeLabel = $scope === 'carne' ? 'Carne' : ($scope === 'pescado-marisco' ? 'Pescado / Marisco' : 'Tarifa anterior');

$regularLines = array_values(array_filter($tariffLines, static fn(array $line): bool => (int)$line['incoming'] !== 1));
$incomingLines = array_values(array_filter($tariffLines, static fn(array $line): bool => (int)$line['incoming'] === 1));

$renderSelectionTools = static function(string $group, bool $withStockOnly = true): void {
    ?>
    <div class="frn-selection-tools">
        <span>Selección:</span>
        <button type="button" class="frn-mini-button" data-frn-select="all" data-group="<?php echo esc_attr($group); ?>">Tildar todos</button>
        <button type="button" class="frn-mini-button" data-frn-select="none" data-group="<?php echo esc_attr($group); ?>">Destildar todos</button>
        <?php if ($withStockOnly) : ?><button type="button" class="frn-mini-button" data-frn-select="stock" data-group="<?php echo esc_attr($group); ?>">Solo con stock</button><?php endif; ?>
        <span class="frn-tool-divider">Oferta:</span>
        <button type="button" class="frn-mini-button" data-frn-offer="all" data-group="<?php echo esc_attr($group); ?>">Marcar todas</button>
        <button type="button" class="frn-mini-button" data-frn-offer="none" data-group="<?php echo esc_attr($group); ?>">Quitar todas</button>
    </div>
    <?php
};

$renderTariffLines = static function(array $rows, string $group) use ($canViewCost): void {
    if (!$rows) {
        echo '<div class="frn-empty">Sin referencias.</div>';
        return;
    }
    ?>
    <div class="frn-app-table-wrap">
        <table class="frn-app-table frn-tariff-edit-table">
            <thead>
                <tr>
                    <th>Usar</th><th>Oferta</th><th>Orden</th><th>Código</th><th>Marca</th><th>Producto</th>
                    <th>Stock origen</th><th>Unidad</th><th>Stock PDF</th><th>Ver stock</th>
                    <th>Precio comercial</th><th>Precio PDF</th><th>Ver precio</th>
                    <?php if ($canViewCost) : ?><th>Coste promedio</th><th>Coste PDF</th><th>Ver coste</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $line) :
                $id=(int)$line['id'];
                $sourcePrice=(float)($line['source_price']??0);
                $displayPrice=(float)($line['display_price']??0);
                $sourceCost=(float)($line['source_cost']??0);
                $displayCost=(float)($line['display_cost']??0);
            ?>
                <tr data-stock="<?php echo esc_attr((float)$line['source_stock']); ?>" data-price="<?php echo esc_attr($displayPrice); ?>" data-cost="<?php echo esc_attr($displayCost); ?>">
                    <td><input class="frn-use-checkbox" data-group="<?php echo esc_attr($group); ?>" type="checkbox" name="lines[<?php echo $id; ?>][visible]" value="1" <?php checked((int)$line['visible'],1); ?>></td>
                    <td><input class="frn-offer-checkbox" data-group="<?php echo esc_attr($group); ?>" type="checkbox" name="lines[<?php echo $id; ?>][featured]" value="1" <?php checked((int)$line['featured'],1); ?>></td>
                    <td><input type="number" name="lines[<?php echo $id; ?>][sort_order]" value="<?php echo esc_attr((int)$line['sort_order']); ?>"></td>
                    <td><?php echo esc_html($line['product_code']); ?></td>
                    <td><?php echo esc_html($line['brand']); ?></td>
                    <td><?php echo esc_html($line['product_name']); ?></td>
                    <td><?php echo esc_html(number_format_i18n((float)$line['source_stock'],3)); ?></td>
                    <td><?php echo esc_html((string)($line['unit']??'')); ?></td>
                    <td><input type="number" min="0" step="0.001" name="lines[<?php echo $id; ?>][display_stock]" value="<?php echo esc_attr((float)$line['display_stock']); ?>"></td>
                    <td><input class="frn-line-stock" type="checkbox" name="lines[<?php echo $id; ?>][show_stock]" value="1" <?php checked((int)$line['show_stock'],1); ?>></td>
                    <td><?php echo $sourcePrice>0 ? esc_html(number_format_i18n($sourcePrice,2)).' €' : '—'; ?></td>
                    <td><input type="number" min="0" step="0.01" name="lines[<?php echo $id; ?>][display_price]" value="<?php echo $displayPrice>0 ? esc_attr($displayPrice) : ''; ?>"></td>
                    <td><input class="frn-line-price" type="checkbox" name="lines[<?php echo $id; ?>][show_price]" value="1" <?php checked((int)$line['show_price'],1); ?>></td>
                    <?php if ($canViewCost) : ?>
                        <td><?php echo $sourceCost>0 ? esc_html(number_format_i18n($sourceCost,2)).' €' : '—'; ?></td>
                        <td><input type="number" min="0" step="0.01" name="lines[<?php echo $id; ?>][display_cost]" value="<?php echo $displayCost>0 ? esc_attr($displayCost) : ''; ?>"></td>
                        <td><input class="frn-line-cost" type="checkbox" name="lines[<?php echo $id; ?>][show_cost]" value="1" <?php checked((int)($line['show_cost']??0),1); ?>></td>
                    <?php endif; ?>
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
        <div><small>Tarifa <?php echo esc_html($scopeLabel); ?></small><h2><?php echo esc_html($tariff['title']); ?></h2></div>
        <a class="frn-text-link" href="<?php echo esc_url(add_query_arg('tab','tarifas',home_url('/stock/'))); ?>">← Volver</a>
    </div>

    <form method="post" action="<?php echo esc_url($postUrl); ?>" id="frn-tariff-form">
        <input type="hidden" name="tariff_id" value="<?php echo (int)$tariff['id']; ?>">
        <?php wp_nonce_field('frn_front_tariff_save_' . (int)$tariff['id']); ?>

        <div class="frn-tariff-settings">
            <label>Título<input type="text" name="settings[title]" value="<?php echo esc_attr($tariff['title']); ?>"></label>
            <label>Fecha<input type="date" name="settings[tariff_date]" value="<?php echo esc_attr($tariff['tariff_date']); ?>"></label>
            <label>Formato
                <select name="settings[preset_mode]" id="frn-preset-mode">
                    <option value="general" <?php selected(($tariff['preset_mode']??'general'),'general'); ?>>General</option>
                    <option value="distribuidor" <?php selected(($tariff['preset_mode']??''),'distribuidor'); ?>>Distribuidor</option>
                    <option value="disponibilidad" <?php selected(($tariff['preset_mode']??''),'disponibilidad'); ?>>Disponibilidad</option>
                    <option value="personalizado" <?php selected(($tariff['preset_mode']??''),'personalizado'); ?>>Personalizado</option>
                </select>
            </label>
            <label class="frn-checkbox-label"><input id="frn-global-stock" type="checkbox" name="settings[show_stock]" value="1" <?php checked((int)$tariff['show_stock'],1); ?>> Mostrar stock</label>
            <label class="frn-checkbox-label"><input id="frn-global-price" type="checkbox" name="settings[show_price]" value="1" <?php checked((int)$tariff['show_price'],1); ?>> Mostrar precio</label>
            <?php if ($canViewCost) : ?>
                <label class="frn-checkbox-label"><input id="frn-global-cost" type="checkbox" name="settings[show_cost]" value="1" <?php checked((int)($tariff['show_cost']??0),1); ?>> Mostrar coste promedio</label>
            <?php endif; ?>
            <label>Stock
                <select name="settings[stock_mode]" id="frn-stock-mode">
                    <option value="exact" <?php selected($tariff['stock_mode'],'exact'); ?>>Exacto</option>
                    <option value="rounded" <?php selected($tariff['stock_mode'],'rounded'); ?>>Redondeado</option>
                    <option value="available" <?php selected($tariff['stock_mode'],'available'); ?>>Disponible</option>
                    <option value="hidden" <?php selected($tariff['stock_mode'],'hidden'); ?>>Oculto</option>
                </select>
            </label>
        </div>

        <div class="frn-preset-help">
            <strong>General:</strong> precio + “Disponible” · <strong>Distribuidor:</strong> precio + stock exacto · <strong>Disponibilidad:</strong> sin precio + “Disponible”.
            <?php if ($canViewCost) : ?><br>El coste promedio solo se incluirá si activas expresamente “Mostrar coste promedio”.<?php endif; ?>
        </div>

        <h3>Productos</h3>
        <?php $renderSelectionTools('tariff-current', true); ?>
        <?php $renderTariffLines($regularLines, 'tariff-current'); ?>

        <h3 class="frn-incoming-title">Próximos ingresos</h3>
        <?php $renderSelectionTools('tariff-incoming', false); ?>
        <?php $renderTariffLines($incomingLines, 'tariff-incoming'); ?>

        <div class="frn-tariff-actions">
            <button type="submit" name="action" value="frn_front_tariff_save">Guardar tarifa</button>
            <button type="submit" name="action" value="frn_front_tariff_pdf">Descargar PDF</button>
            <button type="submit" name="action" value="frn_front_tariff_csv" class="frn-secondary-button">Descargar CSV</button>
        </div>
    </form>
</section>
