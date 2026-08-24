<?php

if (!defined('ABSPATH')) { exit; }

final class FRN_Admin_Importer
{
    private const PREVIEW_PREFIX = 'frn_sp_preview_';

    public function boot(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_post_frn_sp_preview', [$this, 'preview']);
        add_action('admin_post_frn_sp_publish', [$this, 'publish']);
        add_action('admin_post_frn_sp_save_products', [$this, 'save_products']);
        add_action('admin_post_frn_sp_repair_routes', [$this, 'repair_routes']);
    }

    public function menu(): void
    {
        add_menu_page('FRN Stock & Prices', 'FRN Stock', 'manage_options', 'frn-stock-prices', [$this, 'page'], 'dashicons-chart-line', 58);
    }

    public function page(): void
    {
        if (!current_user_can('manage_options')) { return; }
        $token = sanitize_key($_GET['preview'] ?? '');
        $preview = $token ? get_transient(self::PREVIEW_PREFIX . $token) : null;
        ?>
        <div class="wrap">
            <h1>FRN Stock &amp; Prices <small style="font-size:13px;color:#646970">Plugin v<?php echo esc_html(FRN_SP_VERSION); ?></small></h1>
            <div class="notice notice-info inline"><p><strong>Una sola importación actualiza las dos categorías.</strong> El plugin leerá automáticamente las pestañas CARNE_IMPORT y PESCADO_IMPORT del mismo Excel.</p></div>
            <?php if (isset($_GET['published'])) : ?>
                <div class="notice notice-success"><p><strong>Catálogo actualizado:</strong> <?php echo esc_html((int) ($_GET['carne'] ?? 0)); ?> de Carne + <?php echo esc_html((int) ($_GET['pescado'] ?? 0)); ?> de Pescado / Marisco = <?php echo esc_html((int) $_GET['published']); ?> referencias.</p></div>
            <?php endif; ?>
            <?php if (isset($_GET['saved'])) : ?>
                <div class="notice notice-success"><p>Los cambios manuales se han guardado correctamente.</p></div>
            <?php endif; ?>
            <?php if (isset($_GET['routes_repaired'])) : ?>
                <div class="notice notice-success"><p>Los enlaces del catálogo se han reparado.</p></div>
            <?php endif; ?>
            <p><a class="button" href="<?php echo esc_url(home_url('/stock/')); ?>" target="_blank">Comprobar Productos y stock</a> <a class="button" href="<?php echo esc_url(home_url('/stock/pescado-marisco/')); ?>" target="_blank">Comprobar Pescado / Marisco</a> <a class="button" href="<?php echo esc_url(home_url('/stock/carne/')); ?>" target="_blank">Comprobar Carne</a></p>
            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="frn_sp_preview">
                <?php wp_nonce_field('frn_sp_preview'); ?>
                <table class="form-table"><tbody>
                    <tr><th><label for="catalog_file">1. Selecciona el Excel maestro</label></th><td><input id="catalog_file" type="file" name="catalog_file" accept=".xlsx,.xls" required><p class="description">Debe contener las pestañas CARNE_IMPORT y PESCADO_IMPORT. Publicar = SI/NO controla si cada producto se ve en la web.</p></td></tr>
                </tbody></table>
                <?php submit_button('2. Previsualizar las dos categorías'); ?>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:-54px;margin-left:330px"><input type="hidden" name="action" value="frn_sp_repair_routes"><?php wp_nonce_field('frn_sp_repair_routes'); ?><?php submit_button('Reparar enlaces del catálogo', 'secondary', 'submit', false); ?></form>
            <?php if (is_array($preview)) : $this->preview_table($token, $preview); endif; ?>
            <?php $this->product_editor(); ?>
        </div>
        <?php
    }

    private function preview_table(string $token, array $preview): void
    {
        $all_rows = array_merge($preview['catalogs']['carne'], $preview['catalogs']['pescado-marisco']);
        $visible = array_filter($all_rows, static fn(array $row): bool => $row['publish']);
        $hidden = count($all_rows) - count($visible);
        $invalid = count(array_filter($all_rows, static fn(array $row): bool => !$row['valid']));
        ?>
        <hr><h2>Previsualización</h2>
        <p><strong><?php echo esc_html(count($preview['catalogs']['carne'])); ?></strong> Carne + <strong><?php echo esc_html(count($preview['catalogs']['pescado-marisco'])); ?></strong> Pescado / Marisco = <strong><?php echo esc_html(count($all_rows)); ?></strong> referencias · <strong><?php echo esc_html(count($visible)); ?></strong> visibles · <strong><?php echo esc_html($hidden); ?></strong> ocultas · <strong><?php echo esc_html($invalid); ?></strong> con errores · Archivo: <?php echo esc_html($preview['filename']); ?></p>
        <div style="max-height:480px;overflow:auto"><table class="widefat striped"><thead><tr><th>Publicar</th><th>Estado</th><th>Código</th><th>Marca</th><th>Producto</th><th>Stock kg</th><th>Precio €/kg</th><th>Oferta</th></tr></thead><tbody>
        <?php foreach ($preview['catalogs'] as $category => $rows) : ?><tr><td colspan="8"><strong><?php echo $category === 'carne' ? 'CARNE' : 'PESCADO / MARISCO'; ?></strong></td></tr><?php foreach ($rows as $row) : ?><tr><td><strong><?php echo $row['publish'] ? 'Sí' : 'No'; ?></strong></td><td><?php echo !$row['valid'] ? '⚠ ' . esc_html(implode(', ', $row['errors'])) : esc_html($row['status'] ?: 'OK'); ?></td><td><?php echo esc_html($row['code']); ?></td><td><?php echo esc_html($row['brand']); ?></td><td><?php echo esc_html($row['name']); ?></td><td><?php echo esc_html(number_format_i18n($row['stock'], 2)); ?></td><td><?php echo (float) $row['price'] <= 0 ? 'Sin precio' : esc_html(number_format_i18n($row['price'], 2)); ?></td><td><?php echo $row['featured'] ? 'Sí' : 'No'; ?></td></tr><?php endforeach; endforeach; ?>
        </tbody></table></div>
        <?php if ($invalid === 0 && count($all_rows) > 0) : ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:20px"><input type="hidden" name="action" value="frn_sp_publish"><input type="hidden" name="preview" value="<?php echo esc_attr($token); ?>"><?php wp_nonce_field('frn_sp_publish_' . $token); ?><?php submit_button('3. Publicar Carne y Pescado / Marisco', 'primary'); ?></form><?php endif; ?>
        <?php
    }

    public function preview(): void
    {
        $this->guard('frn_sp_preview');
        if (empty($_FILES['catalog_file']['tmp_name'])) { wp_die('No se recibió ningún archivo.'); }
        $catalogs = $this->read_workbook($_FILES['catalog_file']['tmp_name'], $_FILES['catalog_file']['name']);
        $token = wp_generate_password(20, false, false);
        set_transient(self::PREVIEW_PREFIX . $token, ['filename'=>sanitize_file_name($_FILES['catalog_file']['name']),'catalogs'=>$catalogs], HOUR_IN_SECONDS);
        wp_safe_redirect(admin_url('admin.php?page=frn-stock-prices&preview=' . rawurlencode($token))); exit;
    }

    public function publish(): void
    {
        $token = sanitize_key($_POST['preview'] ?? '');
        $this->guard('frn_sp_publish_' . $token);
        $preview = get_transient(self::PREVIEW_PREFIX . $token);
        if (!$preview) { wp_die('La previsualización ha caducado.'); }
        $carne = array_values($preview['catalogs']['carne'] ?? []);
        $pescado = array_values($preview['catalogs']['pescado-marisco'] ?? []);
        $all_rows = array_merge($carne, $pescado);
        if (!$carne || !$pescado || array_filter($all_rows, static fn(array $row): bool => !$row['valid'])) { wp_die('Falta una de las dos categorías o existen filas no válidas.'); }
        $repository = new FRN_Catalog_Repository();
        $carne_count = $repository->publish('carne', $preview['filename'], $carne);
        $pescado_count = $repository->publish('pescado-marisco', $preview['filename'], $pescado);
        $count = $carne_count + $pescado_count;
        delete_transient(self::PREVIEW_PREFIX . $token);
        wp_safe_redirect(admin_url('admin.php?page=frn-stock-prices&published=' . $count . '&carne=' . $carne_count . '&pescado=' . $pescado_count)); exit;
    }

    private function guard(string $nonce): void
    {
        if (!current_user_can('manage_options')) { wp_die('No autorizado.', 403); }
        check_admin_referer($nonce);
    }

    private function read_workbook(string $path, string $name): array
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($extension, ['xlsx', 'xls'], true)) { wp_die('Sube el Excel maestro en formato XLSX o XLS.'); }
        $autoload = FRN_SP_PATH . 'vendor/autoload.php';
        if (!file_exists($autoload)) { wp_die('Falta instalar las dependencias del plugin con Composer.'); }
        require_once $autoload;
        $workbook = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $catalogs = [];
        foreach (['carne' => 'CARNE_IMPORT', 'pescado-marisco' => 'PESCADO_IMPORT'] as $category => $sheet_name) {
            $sheet = $workbook->getSheetByName($sheet_name);
            if (!$sheet) { wp_die('El Excel no contiene la pestaña obligatoria ' . esc_html($sheet_name) . '.'); }
            // Read the underlying numeric values, not locale-formatted strings.
            // This prevents 3,252.56 kg becoming 3.25 kg during import.
            $raw = $sheet->toArray(null, true, false, false);
            $catalogs[$category] = $this->rows_from_raw($raw);
        }
        return $catalogs;
    }

    private function rows_from_raw(array $raw): array
    {
        if (count($raw) < 2) { return []; }
        $headers = array_map([$this, 'normalize_header'], array_shift($raw));
        $rows = [];
        foreach ($raw as $index => $values) {
            $item = array_combine($headers, array_pad($values, count($headers), '')) ?: [];
            $nameValue = trim((string) ($item['product'] ?? ''));
            if ($nameValue === '') { continue; }
            $stock = $this->number($item['stock'] ?? null); $price = $this->number($item['price'] ?? null, true);
            $publish = !array_key_exists('publish', $item) || $this->truthy($item['publish']);
            $errors = [];
            $code = trim((string)($item['code'] ?? '')) ?: (string)($index + 1);
            if ($stock === null) { $errors[] = 'stock no válido'; }
            if ($price !== null && $price < 0) { $errors[] = 'precio inválido'; }
            if ($code === '') { $errors[] = 'código obligatorio'; }
            $rows[] = [
                'code'=>sanitize_text_field($code),
                'brand'=>sanitize_text_field((string)($item['brand'] ?? 'FRN')),
                'name'=>sanitize_text_field($nameValue),
                'stock'=>$stock ?? 0,
                'price'=>$price ?? 0,
                'featured'=>$this->truthy($item['featured'] ?? ''),
                'publish'=>$publish,
                'status'=>sanitize_text_field((string)($item['status'] ?? '')),
                'valid'=>!$errors,
                'errors'=>$errors,
            ];
        }
        return $rows;
    }

    public function repair_routes(): void
    {
        $this->guard('frn_sp_repair_routes');
        FRN_Stock_Prices::instance()->register_routes();
        flush_rewrite_rules();
        wp_safe_redirect(admin_url('admin.php?page=frn-stock-prices&routes_repaired=1')); exit;
    }

    private function normalize_header(mixed $value): string
    {
        $value = remove_accents(strtolower(trim((string)$value)));
        return match (true) { in_array($value,['codigo','id','referencia'],true) => 'code', $value === 'marca' => 'brand', in_array($value,['producto','nombre','descripcion'],true) => 'product', str_contains($value,'stock') || str_contains($value,'cantidad') => 'stock', str_contains($value,'fuente') => 'source', str_contains($value,'precio') => 'price', in_array($value,['oferta','destacado'],true) => 'featured', in_array($value,['publicar','publicado','visible'],true) => 'publish', $value === 'estado' => 'status', default => sanitize_key($value) };
    }

    private function truthy(mixed $value): bool
    {
        return in_array(strtolower(trim(remove_accents((string)$value))), ['si','1','true','x'], true);
    }

    private function number(mixed $value, bool $nullable = false): ?float
    {
        if (is_int($value) || is_float($value)) { return (float) $value; }
        if ($nullable && ($value === null || trim((string)$value) === '')) { return null; }
        $clean = preg_replace('/[^0-9,.-]/', '', (string)$value);
        if (str_contains($clean, ',') && str_contains($clean, '.')) {
            if (strrpos($clean, ',') > strrpos($clean, '.')) {
                $clean = str_replace('.', '', $clean);
                $clean = str_replace(',', '.', $clean);
            } else {
                $clean = str_replace(',', '', $clean);
            }
        } else {
            $clean = str_replace(',', '.', $clean);
        }
        return is_numeric($clean) ? (float)$clean : null;
    }

    private function product_editor(): void
    {
        $repository = new FRN_Catalog_Repository();
        ?>
        <hr style="margin:40px 0 24px"><h2>Editar productos publicados</h2>
        <p>Corrige manualmente código, marca, nombre, kilos o precio. El selector <strong>Visible</strong> controla qué referencias aparecen. Un precio 0 se mostrará como <strong>Consultar precio</strong>.</p>
        <?php foreach (['carne' => 'Carne', 'pescado-marisco' => 'Pescado / Marisco'] as $category => $label) : $products = $repository->all($category, true); ?>
            <h3 style="margin-top:30px"><?php echo esc_html($label); ?></h3>
            <?php if (!$products) : ?><p>Todavía no hay referencias publicadas en esta categoría.</p><?php else : ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="frn_sp_save_products">
                <input type="hidden" name="category" value="<?php echo esc_attr($category); ?>">
                <?php wp_nonce_field('frn_sp_save_products_' . $category); ?>
                <div style="overflow:auto;max-height:560px;border:1px solid #dcdcde"><table class="widefat striped" style="min-width:1160px"><thead><tr><th>Visible</th><th>Código</th><th>Marca</th><th style="width:38%">Producto</th><th>Stock kg</th><th>Precio €/kg</th></tr></thead><tbody>
                <?php foreach ($products as $product) : $id = (int) $product['id']; ?>
                    <tr>
                        <td><select name="products[<?php echo esc_attr($id); ?>][visible]"><option value="1" <?php selected((int) $product['visible'], 1); ?>>Sí</option><option value="0" <?php selected((int) $product['visible'], 0); ?>>No</option></select></td>
                        <td><input type="text" name="products[<?php echo esc_attr($id); ?>][code]" value="<?php echo esc_attr($product['product_code']); ?>" style="width:110px"></td>
                        <td><input type="text" name="products[<?php echo esc_attr($id); ?>][brand]" value="<?php echo esc_attr($product['brand']); ?>" style="width:150px"></td>
                        <td><input type="text" name="products[<?php echo esc_attr($id); ?>][name]" value="<?php echo esc_attr($product['product_name']); ?>" style="width:100%"></td>
                        <td><input type="number" step="0.01" name="products[<?php echo esc_attr($id); ?>][stock]" value="<?php echo esc_attr((float) $product['stock_kg']); ?>" style="width:120px"></td>
                        <td><input type="number" min="0" step="0.01" name="products[<?php echo esc_attr($id); ?>][price]" value="<?php echo esc_attr((float) $product['price_kg']); ?>" style="width:120px"></td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table></div>
                <?php submit_button('Guardar cambios de ' . $label); ?>
            </form>
            <?php endif; ?>
        <?php endforeach;
    }

    public function save_products(): void
    {
        $category = in_array($_POST['category'] ?? '', ['pescado-marisco','carne'], true) ? $_POST['category'] : '';
        if (!$category) { wp_die('Categoría no válida.'); }
        $this->guard('frn_sp_save_products_' . $category);
        $rawRows = is_array($_POST['products'] ?? null) ? wp_unslash($_POST['products']) : [];
        $rows = [];
        foreach ($rawRows as $id => $raw) {
            if (!is_array($raw)) { continue; }
            $rows[] = [
                'id' => (int) $id,
                'code' => sanitize_text_field((string) ($raw['code'] ?? '')),
                'brand' => sanitize_text_field((string) ($raw['brand'] ?? '')),
                'name' => sanitize_text_field((string) ($raw['name'] ?? '')),
                'stock' => $this->number($raw['stock'] ?? 0) ?? 0,
                'price' => max(0, $this->number($raw['price'] ?? 0) ?? 0),
                'visible' => !isset($raw['visible']) || (int) $raw['visible'] === 1,
            ];
        }
        (new FRN_Catalog_Repository())->update_many($category, $rows);
        wp_safe_redirect(admin_url('admin.php?page=frn-stock-prices&saved=1')); exit;
    }
}
