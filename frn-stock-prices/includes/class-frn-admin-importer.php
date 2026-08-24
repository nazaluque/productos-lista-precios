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
            <h1>FRN Stock &amp; Prices</h1>
            <p>Importa el Excel, comprueba el resumen y publica únicamente cuando stock y precios sean correctos.</p>
            <?php if (isset($_GET['published'])) : ?>
                <div class="notice notice-success"><p><?php echo esc_html((int) $_GET['published']); ?> referencias publicadas.</p></div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="frn_sp_preview">
                <?php wp_nonce_field('frn_sp_preview'); ?>
                <table class="form-table"><tbody>
                    <tr><th><label for="category">Landing</label></th><td><select id="category" name="category"><option value="pescado-marisco">Pescado / Marisco</option><option value="carne">Carne</option></select></td></tr>
                    <tr><th><label for="catalog_file">Excel</label></th><td><input id="catalog_file" type="file" name="catalog_file" accept=".xlsx,.xls,.csv" required><p class="description">Columnas admitidas: Código, Marca, Producto, Stock, Precio, Oferta.</p></td></tr>
                </tbody></table>
                <?php submit_button('Previsualizar importación'); ?>
            </form>
            <?php if (is_array($preview)) : $this->preview_table($token, $preview); endif; ?>
        </div>
        <?php
    }

    private function preview_table(string $token, array $preview): void
    {
        $invalid = count(array_filter($preview['rows'], static fn(array $row): bool => !$row['valid']));
        ?>
        <hr><h2>Previsualización</h2>
        <p><strong><?php echo esc_html(count($preview['rows'])); ?></strong> filas detectadas · <strong><?php echo esc_html($invalid); ?></strong> con errores · Archivo: <?php echo esc_html($preview['filename']); ?></p>
        <div style="max-height:480px;overflow:auto"><table class="widefat striped"><thead><tr><th>Estado</th><th>Código</th><th>Marca</th><th>Producto</th><th>Stock kg</th><th>Precio €/kg</th><th>Oferta</th></tr></thead><tbody>
        <?php foreach ($preview['rows'] as $row) : ?><tr><td><?php echo $row['valid'] ? '✓' : '⚠ ' . esc_html(implode(', ', $row['errors'])); ?></td><td><?php echo esc_html($row['code']); ?></td><td><?php echo esc_html($row['brand']); ?></td><td><?php echo esc_html($row['name']); ?></td><td><?php echo esc_html(number_format_i18n($row['stock'], 2)); ?></td><td><?php echo $row['price'] === null ? 'Consultar' : esc_html(number_format_i18n($row['price'], 2)); ?></td><td><?php echo $row['featured'] ? 'Sí' : 'No'; ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
        <?php if ($invalid === 0) : ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:20px"><input type="hidden" name="action" value="frn_sp_publish"><input type="hidden" name="preview" value="<?php echo esc_attr($token); ?>"><?php wp_nonce_field('frn_sp_publish_' . $token); ?><?php submit_button('Publicar en la landing', 'primary'); ?></form><?php endif; ?>
        <?php
    }

    public function preview(): void
    {
        $this->guard('frn_sp_preview');
        if (empty($_FILES['catalog_file']['tmp_name'])) { wp_die('No se recibió ningún archivo.'); }
        $category = in_array($_POST['category'] ?? '', ['pescado-marisco','carne'], true) ? $_POST['category'] : 'pescado-marisco';
        $rows = $this->read_file($_FILES['catalog_file']['tmp_name'], $_FILES['catalog_file']['name']);
        $token = wp_generate_password(20, false, false);
        set_transient(self::PREVIEW_PREFIX . $token, ['category'=>$category,'filename'=>sanitize_file_name($_FILES['catalog_file']['name']),'rows'=>$rows], HOUR_IN_SECONDS);
        wp_safe_redirect(admin_url('admin.php?page=frn-stock-prices&preview=' . rawurlencode($token))); exit;
    }

    public function publish(): void
    {
        $token = sanitize_key($_POST['preview'] ?? '');
        $this->guard('frn_sp_publish_' . $token);
        $preview = get_transient(self::PREVIEW_PREFIX . $token);
        if (!$preview || array_filter($preview['rows'], static fn(array $r): bool => !$r['valid'])) { wp_die('La previsualización ha caducado o contiene errores.'); }
        $count = (new FRN_Catalog_Repository())->publish($preview['category'], $preview['filename'], $preview['rows']);
        delete_transient(self::PREVIEW_PREFIX . $token);
        wp_safe_redirect(admin_url('admin.php?page=frn-stock-prices&published=' . $count)); exit;
    }

    private function guard(string $nonce): void
    {
        if (!current_user_can('manage_options')) { wp_die('No autorizado.', 403); }
        check_admin_referer($nonce);
    }

    private function read_file(string $path, string $name): array
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($extension === 'csv') {
            $handle = fopen($path, 'rb'); $raw = [];
            while (($line = fgetcsv($handle, 0, ';')) !== false) { $raw[] = $line; }
            fclose($handle);
        } else {
            $autoload = FRN_SP_PATH . 'vendor/autoload.php';
            if (!file_exists($autoload)) { wp_die('Falta instalar las dependencias del plugin con Composer.'); }
            require_once $autoload;
            $raw = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet()->toArray(null, true, true, false);
        }
        if (count($raw) < 2) { return []; }
        $headers = array_map([$this, 'normalize_header'], array_shift($raw));
        $rows = [];
        foreach ($raw as $index => $values) {
            $item = array_combine($headers, array_pad($values, count($headers), '')) ?: [];
            $nameValue = trim((string) ($item['product'] ?? ''));
            if ($nameValue === '') { continue; }
            $stock = $this->number($item['stock'] ?? null); $price = $this->number($item['price'] ?? null, true);
            $errors = [];
            if ($stock === null || $stock < 0) { $errors[] = 'stock inválido'; }
            if ($price !== null && $price < 0) { $errors[] = 'precio inválido'; }
            $code = trim((string)($item['code'] ?? '')) ?: (string)($index + 1);
            $rows[] = ['code'=>sanitize_text_field($code),'brand'=>sanitize_text_field((string)($item['brand'] ?? 'FRN')),'name'=>sanitize_text_field($nameValue),'stock'=>$stock ?? 0,'price'=>$price,'featured'=>in_array(strtolower(trim((string)($item['featured'] ?? ''))),['sí','si','1','true','x'],true),'valid'=>!$errors,'errors'=>$errors];
        }
        return $rows;
    }

    private function normalize_header(mixed $value): string
    {
        $value = remove_accents(strtolower(trim((string)$value)));
        return match (true) { in_array($value,['codigo','id','referencia'],true) => 'code', $value === 'marca' => 'brand', in_array($value,['producto','nombre','descripcion'],true) => 'product', str_contains($value,'stock') || str_contains($value,'cantidad') => 'stock', str_contains($value,'precio') => 'price', in_array($value,['oferta','destacado'],true) => 'featured', default => sanitize_key($value) };
    }

    private function number(mixed $value, bool $nullable = false): ?float
    {
        if ($nullable && ($value === null || trim((string)$value) === '')) { return null; }
        $clean = preg_replace('/[^0-9,.-]/', '', (string)$value);
        if (str_contains($clean, ',') && str_contains($clean, '.')) { $clean = str_replace('.', '', $clean); }
        $clean = str_replace(',', '.', $clean);
        return is_numeric($clean) ? (float)$clean : null;
    }
}
