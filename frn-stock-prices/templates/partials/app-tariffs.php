<?php
if (!defined('ABSPATH')) { exit; }
?>

<section class="frn-app-grid-two">
    <article class="frn-app-card">
        <div class="frn-card-heading">
            <div><small>Paso 02</small><h2>Crear tarifa PDF</h2></div>
            <p>Combina el stock vigente con la tarifa comercial que corresponda al cliente, zona o campaña.</p>
        </div>

        <form method="post" action="<?php echo esc_url($postUrl); ?>" class="frn-simple-form frn-create-tariff-form">
            <input type="hidden" name="action" value="frn_front_tariff_create">
            <?php wp_nonce_field('frn_front_tariff_create'); ?>

            <label>Fecha
                <input type="date" name="tariff_date" value="<?php echo esc_attr(current_time('Y-m-d')); ?>" required>
            </label>

            <label>Tarifa de precios
                <select name="price_list_id">
                    <option value="0">Sin precios · solo disponibilidad</option>
                    <?php foreach ($priceLists as $list) : ?>
                        <option value="<?php echo (int) $list['id']; ?>"><?php echo esc_html($list['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>Salida inicial
                <select name="preset">
                    <option value="general">General · Precio + Disponible</option>
                    <option value="distribuidor">Distribuidor · Precio + stock exacto</option>
                    <option value="disponibilidad">Disponibilidad · Sin precio + Disponible</option>
                    <option value="personalizado">Personalizado</option>
                </select>
            </label>

            <div class="frn-create-buttons">
                <button type="submit" name="scope" value="carne">Crear tarifa Carne</button>
                <button type="submit" name="scope" value="pescado-marisco" class="frn-secondary-button">Crear tarifa Pescado / Marisco</button>
            </div>
        </form>
    </article>

    <article class="frn-app-card">
        <div class="frn-card-heading">
            <div><small>Template FRN</small><h2>Datos del PDF</h2></div>
            <p>Datos fijos que salen en todas las tarifas.</p>
        </div>

        <form method="post" action="<?php echo esc_url($postUrl); ?>" class="frn-simple-form">
            <input type="hidden" name="action" value="frn_front_settings">
            <?php wp_nonce_field('frn_front_settings'); ?>

            <label>Empresa<input type="text" name="frn_tariff_company" value="<?php echo esc_attr((string)get_option('frn_tariff_company','FRN Atlántico')); ?>"></label>
            <label>Dirección<input type="text" name="frn_tariff_address" value="<?php echo esc_attr((string)get_option('frn_tariff_address','')); ?>"></label>
            <label>Teléfono<input type="text" name="frn_tariff_phone" value="<?php echo esc_attr((string)get_option('frn_tariff_phone','')); ?>"></label>
            <label>Email<input type="text" name="frn_tariff_email" value="<?php echo esc_attr((string)get_option('frn_tariff_email','')); ?>"></label>
            <label>Web<input type="text" name="frn_tariff_web" value="<?php echo esc_attr((string)get_option('frn_tariff_web','www.frnatlantico.com')); ?>"></label>

            <button type="submit" class="frn-secondary-button">Guardar datos</button>
        </form>
    </article>
</section>

<section class="frn-app-card">
    <div class="frn-card-heading">
        <div><small>Histórico</small><h2>Tarifas guardadas</h2></div>
    </div>

    <div class="frn-history">
        <?php if (!$tariffs) : ?><p>Todavía no hay tarifas guardadas.</p><?php endif; ?>

        <?php foreach ($tariffs as $row) :
            $scope = (string) ($row['catalog_scope'] ?? 'all');
            $scopeLabel = $scope === 'carne'
                ? 'Carne'
                : ($scope === 'pescado-marisco' ? 'Pescado / Marisco' : 'Mixta · versión anterior');
        ?>
            <a href="<?php echo esc_url(add_query_arg(['tab'=>'tarifa','id'=>(int)$row['id']],home_url('/stock/'))); ?>">
                <strong><?php echo esc_html($row['title']); ?></strong>
                <span>
                    <?php echo esc_html($scopeLabel); ?> ·
                    <?php echo esc_html(mysql2date('d/m/Y',$row['tariff_date'].' 00:00:00')); ?>
                    <?php echo !empty($row['price_list_name']) ? ' · ' . esc_html($row['price_list_name']) : ''; ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
