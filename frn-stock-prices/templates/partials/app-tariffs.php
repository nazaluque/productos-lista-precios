<?php
if (!defined('ABSPATH')) { exit; }
?>

<section class="frn-app-grid-two">
    <article class="frn-app-card">
        <div class="frn-card-heading">
            <div><small>Paso 02</small><h2>Crear tarifa PDF</h2></div>
            <p>Se genera desde el maestro vigente. Carne y Pescado / Marisco salen siempre por separado.</p>
        </div>
        <?php if (!empty($latestImport)) : ?>
            <div class="frn-source-chip"><span>Base semanal activa</span><strong><?php echo esc_html($latestImport['source_file']); ?></strong><small><?php echo esc_html(mysql2date('d/m/Y H:i',$latestImport['imported_at'])); ?> · <?php echo (int)$latestImport['active_count']; ?> referencias</small></div>
        <?php endif; ?>

        <?php if ($canExport) : ?>
        <form method="post" action="<?php echo esc_url($postUrl); ?>" class="frn-simple-form frn-create-tariff-form">
            <input type="hidden" name="action" value="frn_front_tariff_create">
            <?php wp_nonce_field('frn_front_tariff_create'); ?>

            <label>Fecha
                <input type="date" name="tariff_date" value="<?php echo esc_attr(current_time('Y-m-d')); ?>" required>
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
        <?php else : ?>
            <div class="frn-empty">Tu perfil no tiene permiso para exportar tarifas.</div>
        <?php endif; ?>
    </article>

    <article class="frn-app-card">
        <div class="frn-card-heading">
            <div><small>Pie de página</small><h2>Datos del PDF</h2></div>
            <p>Estos datos aparecen automáticamente en el footer de todas las tarifas.</p>
        </div>

        <?php if ($canEditPrices) : ?>
        <form method="post" action="<?php echo esc_url($postUrl); ?>" class="frn-simple-form">
            <input type="hidden" name="action" value="frn_front_settings">
            <?php wp_nonce_field('frn_front_settings'); ?>
            <label>Empresa
                <input type="text" name="frn_tariff_company" value="<?php echo esc_attr((string)get_option('frn_tariff_company','FRN Atlántico')); ?>">
            </label>
            <label>Dirección
                <input type="text" name="frn_tariff_address" value="<?php echo esc_attr((string)get_option('frn_tariff_address','')); ?>">
            </label>
            <label>Teléfono
                <input type="text" name="frn_tariff_phone" value="<?php echo esc_attr((string)get_option('frn_tariff_phone','')); ?>">
            </label>
            <label>Email
                <input type="text" name="frn_tariff_email" value="<?php echo esc_attr((string)get_option('frn_tariff_email','')); ?>">
            </label>
            <label>Página web
                <input type="text" name="frn_tariff_web" value="<?php echo esc_attr((string)get_option('frn_tariff_web','www.frnatlantico.com')); ?>">
            </label>
            <button type="submit" class="frn-secondary-button">Guardar datos del PDF</button>
        </form>
        <?php else : ?>
            <div class="frn-empty">Tu perfil puede exportar tarifas, pero no modificar los datos corporativos del PDF.</div>
        <?php endif; ?>
    </article>
</section>

<section class="frn-app-card">
    <div class="frn-card-heading">
        <div><small>Opciones de salida</small><h2>Qué puede aparecer</h2></div>
        <p>Al abrir una tarifa podrás decidir si mostrar precio, stock exacto/redondeado/“Disponible” y, si tu perfil tiene permiso, coste promedio.</p>
    </div>
    <?php if ($canViewCost) : ?>
        <div class="frn-preview-label">Tu usuario tiene permiso para ver y exportar <strong>Coste promedio</strong>.</div>
    <?php else : ?>
        <div class="frn-preview-label">El <strong>Coste promedio</strong> está oculto para tu perfil y no puede exportarse.</div>
    <?php endif; ?>
</section>

<section class="frn-app-card">
    <div class="frn-card-heading">
        <div><small>Histórico</small><h2>Tarifas guardadas</h2></div>
    </div>

    <div class="frn-history">
        <?php if (!$tariffs) : ?><p class="frn-empty">Todavía no hay tarifas guardadas.</p><?php endif; ?>
        <?php foreach ($tariffs as $row) :
            $scope = (string) ($row['catalog_scope'] ?? 'all');
            $scopeLabel = $scope === 'carne' ? 'Carne' : ($scope === 'pescado-marisco' ? 'Pescado / Marisco' : 'Mixta · versión anterior');
        ?>
            <a href="<?php echo esc_url(add_query_arg(['tab'=>'tarifa','id'=>(int)$row['id']],home_url('/stock/'))); ?>">
                <strong><?php echo esc_html($row['title']); ?></strong>
                <span><?php echo esc_html($scopeLabel); ?> · <?php echo esc_html(mysql2date('d/m/Y',$row['tariff_date'].' 00:00:00')); ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
