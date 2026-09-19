<?php
if (!defined('ABSPATH')) { exit; }

$currentUser = wp_get_current_user();
$whatsapp = preg_replace('/\D+/', '', (string) get_option('frn_sp_whatsapp_number', '34624354950'));
$logoutUrl = wp_logout_url(home_url('/'));
$postUrl = admin_url('admin-post.php');

$currentProducts = array_values(array_filter($products, static fn(array $p): bool => (int) $p['incoming'] !== 1));
$incomingProducts = array_values(array_filter($products, static fn(array $p): bool => (int) $p['incoming'] === 1));

function frn_front_rows(array $rows, string $section): void {
    if (!$rows) {
        echo '<div class="frn-empty">No hay referencias en esta sección.</div>';
        return;
    }
    ?>
    <div class="frn-app-table-wrap">
        <table class="frn-app-table">
            <thead>
            <tr>
                <th>Usar</th>
                <th>Oferta</th>
                <th>Categoría</th>
                <th>Código</th>
                <th>Marca</th>
                <th>Producto</th>
                <th>Stock kg</th>
                <th>Precio €/kg</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $product) : $id = (int) $product['id']; ?>
                <tr class="<?php echo $section === 'incoming' ? 'frn-incoming-row' : ''; ?>">
                    <td><input type="checkbox" name="products[<?php echo $id; ?>][visible]" value="1" <?php checked((int) $product['visible'], 1); ?>></td>
                    <td><input type="checkbox" name="products[<?php echo $id; ?>][featured]" value="1" <?php checked((int) $product['featured'], 1); ?>></td>
                    <td><?php echo esc_html($product['category'] === 'carne' ? 'Carne' : 'Pescado / Marisco'); ?></td>
                    <td><input type="text" name="products[<?php echo $id; ?>][code]" value="<?php echo esc_attr($product['product_code']); ?>"></td>
                    <td><input type="text" name="products[<?php echo $id; ?>][brand]" value="<?php echo esc_attr($product['brand']); ?>"></td>
                    <td><input class="frn-wide" type="text" name="products[<?php echo $id; ?>][name]" value="<?php echo esc_attr($product['product_name']); ?>"></td>
                    <td><input type="number" step="0.01" name="products[<?php echo $id; ?>][stock]" value="<?php echo esc_attr((float) $product['stock_kg']); ?>"></td>
                    <td><input type="number" min="0" step="0.01" name="products[<?php echo $id; ?>][price]" value="<?php echo esc_attr((float) $product['price_kg']); ?>"></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Herramienta interna · FRN Atlántico</title>
    <?php wp_head(); ?>
</head>
<body class="frn-catalog-body frn-app-body">
<header class="frn-top frn-app-top">
    <a href="<?php echo esc_url(home_url('/')); ?>" class="frn-brand"><strong>FRN</strong><span>ATLÁNTICO</span></a>
    <nav>
        <span class="frn-user">Hola, <?php echo esc_html($currentUser->display_name ?: $currentUser->user_login); ?></span>
        <a href="<?php echo esc_url(home_url('/')); ?>">Inicio .es</a>
        <a href="https://www.frnatlantico.com/" target="_blank" rel="noopener">Web oficial .com</a>
        <a href="<?php echo esc_url($logoutUrl); ?>">Cerrar sesión</a>
    </nav>
</header>

<main class="frn-app-shell">
    <section class="frn-app-hero">
        <div>
            <p>Herramienta interna FRN</p>
            <h1>Stock, precios<br>y tarifas.</h1>
        </div>
        <span>Importa el Excel semanal, ajusta los datos comerciales y genera el PDF que vas a enviar a clientes.</span>
    </section>

    <nav class="frn-app-tabs" aria-label="Herramienta">
        <a class="<?php echo $tab === 'importar' ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('tab','importar',home_url('/stock/'))); ?>">1. Importar Excel semanal</a>
        <a class="<?php echo in_array($tab,['tarifas','tarifa'],true) ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('tab','tarifas',home_url('/stock/'))); ?>">2. Crear PDF</a>
    </nav>

    <?php foreach ($messages as [$kind,$message]) : ?>
        <div class="frn-app-message <?php echo esc_attr($kind); ?>"><?php echo esc_html($message); ?></div>
    <?php endforeach; ?>

    <?php if ($tab === 'importar') : ?>
        <section class="frn-app-card frn-import-card">
            <div class="frn-card-heading">
                <div><small>Paso 01</small><h2>Importar Excel semanal</h2></div>
                <p>Puedes subir uno o varios Excel. El sistema intenta reconocer Carne y Pescado / Marisco por pestaña, nombre del archivo o código.</p>
            </div>

            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url($postUrl); ?>" class="frn-upload-form">
                <input type="hidden" name="action" value="frn_front_preview">
                <?php wp_nonce_field('frn_front_preview'); ?>
                <label class="frn-dropzone">
                    <span>Arrastra aquí los Excel de la semana</span>
                    <small>o haz clic para seleccionarlos · XLSX / XLS</small>
                    <input type="file" name="catalog_files[]" accept=".xlsx,.xls" multiple required>
                </label>
                <button type="submit">Previsualizar importación</button>
            </form>
        </section>

        <?php if (is_array($preview)) :
            $previewRows = array_merge($preview['catalogs']['carne'] ?? [], $preview['catalogs']['pescado-marisco'] ?? []);
            $previewIncoming = array_values(array_filter($previewRows, static fn(array $r): bool => !empty($r['incoming'])));
            $previewRegular = array_values(array_filter($previewRows, static fn(array $r): bool => empty($r['incoming'])));
            $invalid = count(array_filter($previewRows, static fn(array $r): bool => empty($r['valid'])));
        ?>
            <section class="frn-app-card">
                <div class="frn-card-heading">
                    <div><small>Previsualización</small><h2>Antes de publicar</h2></div>
                    <p><?php echo esc_html(count($previewRegular)); ?> productos · <?php echo esc_html(count($previewIncoming)); ?> próximos ingresos · <?php echo esc_html($invalid); ?> errores.</p>
                </div>

                <div class="frn-preview-groups">
                    <h3>Productos</h3>
                    <div class="frn-app-table-wrap">
                        <table class="frn-app-table frn-preview-table"><thead><tr><th>Estado</th><th>Categoría</th><th>Código</th><th>Marca</th><th>Producto</th><th>Stock</th><th>Precio</th><th>Oferta</th></tr></thead><tbody>
                        <?php foreach ($previewRegular as $row) : ?>
                            <tr><td><?php echo empty($row['valid']) ? '⚠ ' . esc_html(implode(', ', $row['errors'])) : 'OK'; ?></td><td><?php echo esc_html($row['category'] === 'carne' ? 'Carne' : 'Pescado / Marisco'); ?></td><td><?php echo esc_html($row['code']); ?></td><td><?php echo esc_html($row['brand']); ?></td><td><?php echo esc_html($row['name']); ?></td><td><?php echo esc_html(number_format_i18n((float)$row['stock'],2)); ?></td><td><?php echo (float)$row['price'] > 0 ? esc_html(number_format_i18n((float)$row['price'],2)) . ' €' : '—'; ?></td><td><?php echo !empty($row['featured']) ? 'Sí' : 'No'; ?></td></tr>
                        <?php endforeach; ?>
                        </tbody></table>
                    </div>

                    <h3 class="frn-incoming-title">Próximos ingresos <small>códigos formados solo por XXX…</small></h3>
                    <div class="frn-app-table-wrap">
                        <table class="frn-app-table frn-preview-table"><thead><tr><th>Estado</th><th>Categoría</th><th>Código</th><th>Marca</th><th>Producto</th><th>Stock</th><th>Precio</th><th>Oferta</th></tr></thead><tbody>
                        <?php if (!$previewIncoming) : ?><tr><td colspan="8">No se detectaron próximos ingresos en estos archivos.</td></tr><?php endif; ?>
                        <?php foreach ($previewIncoming as $row) : ?>
                            <tr class="frn-incoming-row"><td><?php echo empty($row['valid']) ? '⚠ ' . esc_html(implode(', ', $row['errors'])) : 'Próximo ingreso'; ?></td><td><?php echo esc_html($row['category'] === 'carne' ? 'Carne' : 'Pescado / Marisco'); ?></td><td><?php echo esc_html($row['code']); ?></td><td><?php echo esc_html($row['brand']); ?></td><td><?php echo esc_html($row['name']); ?></td><td><?php echo esc_html(number_format_i18n((float)$row['stock'],2)); ?></td><td><?php echo (float)$row['price'] > 0 ? esc_html(number_format_i18n((float)$row['price'],2)) . ' €' : '—'; ?></td><td><?php echo !empty($row['featured']) ? 'Sí' : 'No'; ?></td></tr>
                        <?php endforeach; ?>
                        </tbody></table>
                    </div>
                </div>

                <?php if ($invalid === 0 && $previewRows) : ?>
                    <form method="post" action="<?php echo esc_url($postUrl); ?>" class="frn-inline-action">
                        <input type="hidden" name="action" value="frn_front_publish">
                        <input type="hidden" name="preview" value="<?php echo esc_attr($previewToken); ?>">
                        <?php wp_nonce_field('frn_front_publish_' . $previewToken); ?>
                        <button type="submit">Publicar estos datos</button>
                    </form>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($products) : ?>
            <section class="frn-app-card">
                <div class="frn-card-heading">
                    <div><small>Edición comercial</small><h2>Preparar lo que saldrá en la tarifa</h2></div>
                    <p>Aquí puedes cambiar precio, stock, nombre, marca, marcar oferta o quitar una referencia. Los códigos XXX se separan automáticamente como próximos ingresos.</p>
                </div>

                <form method="post" action="<?php echo esc_url($postUrl); ?>">
                    <input type="hidden" name="action" value="frn_front_save_products">
                    <?php wp_nonce_field('frn_front_save_products'); ?>

                    <h3>Productos</h3>
                    <?php frn_front_rows($currentProducts, 'current'); ?>

                    <h3 class="frn-incoming-title">Próximos ingresos <small>productos nuevos todavía sin código definitivo</small></h3>
                    <?php frn_front_rows($incomingProducts, 'incoming'); ?>

                    <div class="frn-inline-action"><button type="submit">Guardar cambios</button></div>
                </form>
            </section>
        <?php endif; ?>

    <?php elseif ($tab === 'tarifas') : ?>
        <section class="frn-app-grid-two">
            <article class="frn-app-card">
                <div class="frn-card-heading">
                    <div><small>Paso 02</small><h2>Crear tarifa PDF</h2></div>
                    <p>Genera una copia de los datos actuales para poder retocar precios y stock sin alterar la importación original.</p>
                </div>
                <form method="post" action="<?php echo esc_url($postUrl); ?>" class="frn-simple-form">
                    <input type="hidden" name="action" value="frn_front_tariff_create">
                    <?php wp_nonce_field('frn_front_tariff_create'); ?>
                    <label>Título<input type="text" name="title" value="<?php echo esc_attr('Tarifa semanal FRN · ' . wp_date('d/m/Y')); ?>" required></label>
                    <label>Fecha<input type="date" name="tariff_date" value="<?php echo esc_attr(current_time('Y-m-d')); ?>" required></label>
                    <button type="submit">Crear tarifa editable</button>
                </form>
            </article>

            <article class="frn-app-card">
                <div class="frn-card-heading">
                    <div><small>Template FRN</small><h2>Datos del PDF</h2></div>
                    <p>Estos datos se imprimen automáticamente en las tarifas.</p>
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
            <div class="frn-card-heading"><div><small>Histórico</small><h2>Tarifas guardadas</h2></div></div>
            <div class="frn-history">
                <?php if (!$tariffs) : ?><p>Todavía no hay tarifas guardadas.</p><?php endif; ?>
                <?php foreach ($tariffs as $row) : ?>
                    <a href="<?php echo esc_url(add_query_arg(['tab'=>'tarifa','id'=>(int)$row['id']],home_url('/stock/'))); ?>">
                        <strong><?php echo esc_html($row['title']); ?></strong>
                        <span><?php echo esc_html(mysql2date('d/m/Y',$row['tariff_date'].' 00:00:00')); ?> · <?php echo $row['status'] === 'final' ? 'Final' : 'Borrador'; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

    <?php elseif ($tab === 'tarifa' && $tariff) : ?>
        <section class="frn-app-card">
            <div class="frn-card-heading">
                <div><small>Tarifa editable</small><h2><?php echo esc_html($tariff['title']); ?></h2></div>
                <a class="frn-text-link" href="<?php echo esc_url(add_query_arg('tab','tarifas',home_url('/stock/'))); ?>">← Volver</a>
            </div>

            <form method="post" action="<?php echo esc_url($postUrl); ?>">
                <input type="hidden" name="action" value="frn_front_tariff_save">
                <input type="hidden" name="tariff_id" value="<?php echo (int)$tariff['id']; ?>">
                <?php wp_nonce_field('frn_front_tariff_save_' . (int)$tariff['id']); ?>

                <div class="frn-tariff-settings">
                    <label>Título<input type="text" name="settings[title]" value="<?php echo esc_attr($tariff['title']); ?>"></label>
                    <label>Fecha<input type="date" name="settings[tariff_date]" value="<?php echo esc_attr($tariff['tariff_date']); ?>"></label>
                    <label>Estado<select name="settings[status]"><option value="draft" <?php selected($tariff['status'],'draft'); ?>>Borrador</option><option value="final" <?php selected($tariff['status'],'final'); ?>>Final</option></select></label>
                    <label><input type="checkbox" name="settings[show_stock]" value="1" <?php checked((int)$tariff['show_stock'],1); ?>> Mostrar stock</label>
                    <label><input type="checkbox" name="settings[show_price]" value="1" <?php checked((int)$tariff['show_price'],1); ?>> Mostrar precio</label>
                    <label>Stock<select name="settings[stock_mode]"><option value="exact" <?php selected($tariff['stock_mode'],'exact'); ?>>Exacto</option><option value="rounded" <?php selected($tariff['stock_mode'],'rounded'); ?>>Redondeado</option><option value="available" <?php selected($tariff['stock_mode'],'available'); ?>>Disponible</option><option value="hidden" <?php selected($tariff['stock_mode'],'hidden'); ?>>Oculto</option></select></label>
                </div>

                <?php
                $regularLines = array_values(array_filter($tariffLines, static fn(array $l): bool => (int)$l['incoming'] !== 1));
                $incomingLines = array_values(array_filter($tariffLines, static fn(array $l): bool => (int)$l['incoming'] === 1));

                $renderTariffLines = static function(array $rows): void {
                    if (!$rows) { echo '<div class="frn-empty">Sin referencias.</div>'; return; }
                    ?>
                    <div class="frn-app-table-wrap"><table class="frn-app-table frn-tariff-edit-table"><thead><tr><th>Usar</th><th>Oferta</th><th>Orden</th><th>Código</th><th>Marca</th><th>Producto</th><th>Stock origen</th><th>Stock PDF</th><th>Ver</th><th>Precio origen</th><th>Precio PDF</th><th>Ver</th></tr></thead><tbody>
                    <?php foreach($rows as $line): $id=(int)$line['id']; ?>
                        <tr>
                            <td><input type="checkbox" name="lines[<?php echo $id;?>][visible]" value="1" <?php checked((int)$line['visible'],1); ?>></td>
                            <td><input type="checkbox" name="lines[<?php echo $id;?>][featured]" value="1" <?php checked((int)$line['featured'],1); ?>></td>
                            <td><input type="number" name="lines[<?php echo $id;?>][sort_order]" value="<?php echo esc_attr((int)$line['sort_order']); ?>"></td>
                            <td><input type="text" name="lines[<?php echo $id;?>][product_code]" value="<?php echo esc_attr($line['product_code']); ?>"></td>
                            <td><input type="text" name="lines[<?php echo $id;?>][brand]" value="<?php echo esc_attr($line['brand']); ?>"></td>
                            <td><input class="frn-wide" type="text" name="lines[<?php echo $id;?>][product_name]" value="<?php echo esc_attr($line['product_name']); ?>"></td>
                            <td><?php echo esc_html(number_format_i18n((float)$line['source_stock'],2)); ?></td>
                            <td><input type="number" step="0.01" name="lines[<?php echo $id;?>][display_stock]" value="<?php echo esc_attr((float)$line['display_stock']); ?>"></td>
                            <td><input type="checkbox" name="lines[<?php echo $id;?>][show_stock]" value="1" <?php checked((int)$line['show_stock'],1); ?>></td>
                            <td><?php echo esc_html(number_format_i18n((float)$line['source_price'],2)); ?> €</td>
                            <td><input type="number" min="0" step="0.01" name="lines[<?php echo $id;?>][display_price]" value="<?php echo esc_attr((float)$line['display_price']); ?>"></td>
                            <td><input type="checkbox" name="lines[<?php echo $id;?>][show_price]" value="1" <?php checked((int)$line['show_price'],1); ?>></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody></table></div>
                    <?php
                };
                ?>

                <h3>Productos</h3>
                <?php $renderTariffLines($regularLines); ?>

                <h3 class="frn-incoming-title">Próximos ingresos</h3>
                <?php $renderTariffLines($incomingLines); ?>

                <div class="frn-tariff-actions">
                    <button type="submit">Guardar tarifa</button>
                    <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action'=>'frn_front_tariff_pdf','tariff_id'=>(int)$tariff['id']],$postUrl),'frn_front_tariff_pdf_' . (int)$tariff['id'])); ?>">Descargar PDF</a>
                    <a class="frn-secondary-button" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action'=>'frn_front_tariff_csv','tariff_id'=>(int)$tariff['id']],$postUrl),'frn_front_tariff_csv_' . (int)$tariff['id'])); ?>">Descargar CSV</a>
                </div>
            </form>
        </section>
    <?php endif; ?>
</main>

<footer class="frn-footer">
    <span>FRN Atlántico · Herramienta interna</span>
    <a href="https://wa.me/<?php echo esc_attr($whatsapp); ?>" target="_blank" rel="noopener">WhatsApp comercial</a>
    <a href="<?php echo esc_url($logoutUrl); ?>">Cerrar sesión</a>
</footer>
<?php wp_footer(); ?>
</body>
</html>
