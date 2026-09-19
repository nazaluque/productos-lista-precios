<?php

if (!defined('ABSPATH')) { exit; }

final class FRN_Tariff_Manager
{
    private FRN_Tariff_Repository $repository;

    public function __construct()
    {
        $this->repository = new FRN_Tariff_Repository();
    }

    public function boot(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_post_frn_tariff_create', [$this, 'create']);
        add_action('admin_post_frn_tariff_save', [$this, 'save']);
        add_action('admin_post_frn_tariff_pdf', [$this, 'pdf']);
        add_action('admin_post_frn_tariff_csv', [$this, 'csv']);
        add_action('admin_post_frn_tariff_settings', [$this, 'save_settings']);
    }

    public function menu(): void
    {
        add_submenu_page(
            'frn-stock-prices',
            'Tarifas semanales',
            'Tarifas semanales',
            'frn_manage_stock',
            'frn-tariffs',
            [$this, 'page']
        );
    }

    public function page(): void
    {
        if (!current_user_can('frn_manage_stock')) { return; }

        $tariffId = absint($_GET['tariff'] ?? 0);
        echo '<div class="wrap"><h1>Tarifas semanales FRN <small style="font-size:13px;color:#646970">v' . esc_html(FRN_SP_VERSION) . '</small></h1>';

        if (isset($_GET['created'])) {
            echo '<div class="notice notice-success"><p>Tarifa creada desde el stock y precios actuales.</p></div>';
        }
        if (isset($_GET['saved'])) {
            echo '<div class="notice notice-success"><p>Tarifa guardada correctamente.</p></div>';
        }
        if (isset($_GET['settings_saved'])) {
            echo '<div class="notice notice-success"><p>Datos comerciales guardados.</p></div>';
        }

        $this->styles();

        if ($tariffId > 0) {
            $this->editor($tariffId);
        } else {
            $this->dashboard();
        }

        echo '</div>';
    }

    private function dashboard(): void
    {
        $today = current_time('Y-m-d');
        $defaultTitle = 'Tarifa semanal FRN · ' . wp_date('d/m/Y', strtotime($today));
        $tariffs = $this->repository->all_tariffs();
        ?>
        <div class="frn-tariff-grid">
            <section class="frn-card">
                <h2>Nueva tarifa desde el catálogo actual</h2>
                <p>Usa los datos que ya has importado desde el Excel semanal. Crea una copia editable: los cambios que hagas aquí no modifican el Excel ni el catálogo base.</p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="frn_tariff_create">
                    <?php wp_nonce_field('frn_tariff_create'); ?>
                    <p><label><strong>Título</strong><br><input class="regular-text" type="text" name="title" value="<?php echo esc_attr($defaultTitle); ?>" required></label></p>
                    <p><label><strong>Fecha</strong><br><input type="date" name="tariff_date" value="<?php echo esc_attr($today); ?>" required></label></p>
                    <?php submit_button('Crear tarifa editable', 'primary', 'submit', false); ?>
                </form>
            </section>

            <section class="frn-card">
                <h2>Datos del PDF</h2>
                <p>Estos datos se aplican automáticamente a todas las tarifas exportadas.</p>
                <?php $this->settings_form(); ?>
            </section>
        </div>

        <section class="frn-card" style="margin-top:22px">
            <h2>Histórico de tarifas</h2>
            <?php if (!$tariffs) : ?>
                <p>Todavía no has creado ninguna tarifa.</p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead><tr><th>Fecha</th><th>Título</th><th>Estado</th><th>Origen</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($tariffs as $tariff) : ?>
                        <tr>
                            <td><?php echo esc_html(mysql2date('d/m/Y', $tariff['tariff_date'] . ' 00:00:00')); ?></td>
                            <td><strong><?php echo esc_html($tariff['title']); ?></strong></td>
                            <td><?php echo $tariff['status'] === 'final' ? 'Final' : 'Borrador'; ?></td>
                            <td><?php echo esc_html($tariff['source_file'] ?: 'Catálogo actual'); ?></td>
                            <td><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=frn-tariffs&tariff=' . (int) $tariff['id'])); ?>">Editar / exportar</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
        <?php
    }

    private function editor(int $tariffId): void
    {
        $tariff = $this->repository->get_tariff($tariffId);
        if (!$tariff) {
            echo '<div class="notice notice-error"><p>La tarifa no existe.</p></div>';
            return;
        }

        $lines = $this->repository->get_lines($tariffId);
        ?>
        <p><a href="<?php echo esc_url(admin_url('admin.php?page=frn-tariffs')); ?>">← Volver a tarifas</a></p>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="frn_tariff_save">
            <input type="hidden" name="tariff_id" value="<?php echo esc_attr($tariffId); ?>">
            <?php wp_nonce_field('frn_tariff_save_' . $tariffId); ?>

            <section class="frn-card">
                <div class="frn-editor-head">
                    <div>
                        <label><strong>Título</strong><br><input class="large-text" type="text" name="settings[title]" value="<?php echo esc_attr($tariff['title']); ?>"></label>
                    </div>
                    <div>
                        <label><strong>Fecha</strong><br><input type="date" name="settings[tariff_date]" value="<?php echo esc_attr($tariff['tariff_date']); ?>"></label>
                    </div>
                    <div>
                        <label><strong>Estado</strong><br>
                            <select name="settings[status]">
                                <option value="draft" <?php selected($tariff['status'], 'draft'); ?>>Borrador</option>
                                <option value="final" <?php selected($tariff['status'], 'final'); ?>>Final</option>
                            </select>
                        </label>
                    </div>
                </div>

                <div class="frn-toggle-row">
                    <label><input type="checkbox" name="settings[show_stock]" value="1" <?php checked((int) $tariff['show_stock'], 1); ?>> Mostrar columna de stock</label>
                    <label><input type="checkbox" name="settings[show_price]" value="1" <?php checked((int) $tariff['show_price'], 1); ?>> Mostrar columna de precio</label>
                    <label>Stock:
                        <select name="settings[stock_mode]">
                            <option value="exact" <?php selected($tariff['stock_mode'], 'exact'); ?>>Exacto</option>
                            <option value="rounded" <?php selected($tariff['stock_mode'], 'rounded'); ?>>Redondeado</option>
                            <option value="available" <?php selected($tariff['stock_mode'], 'available'); ?>>Texto “Disponible”</option>
                            <option value="hidden" <?php selected($tariff['stock_mode'], 'hidden'); ?>>Ocultar</option>
                        </select>
                    </label>
                </div>
            </section>

            <section class="frn-card" style="margin-top:20px">
                <h2>Productos de esta tarifa</h2>
                <p><strong>Origen</strong> = valor importado. <strong>Tarifa</strong> = valor que verá el cliente en este PDF. Puedes cambiarlo sin tocar el dato original.</p>
                <div class="frn-table-scroll">
                    <table class="widefat striped frn-tariff-table">
                        <thead>
                            <tr>
                                <th>✓</th><th>Oferta</th><th>Orden</th><th>Código</th><th>Marca</th><th>Producto</th>
                                <th>Stock origen</th><th>Stock tarifa</th><th>Ver stock</th>
                                <th>Precio origen</th><th>Precio tarifa</th><th>Ver precio</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $lastCategory = '';
                        foreach ($lines as $line) :
                            if ($lastCategory !== $line['category']) :
                                $lastCategory = $line['category'];
                                ?>
                                <tr class="frn-category-row"><td colspan="12"><?php echo $lastCategory === 'carne' ? 'CARNE' : 'PESCADO / MARISCO'; ?></td></tr>
                            <?php endif; ?>
                            <tr>
                                <td><input type="checkbox" name="lines[<?php echo (int) $line['id']; ?>][visible]" value="1" <?php checked((int) $line['visible'], 1); ?>></td>
                                <td><input type="checkbox" name="lines[<?php echo (int) $line['id']; ?>][featured]" value="1" <?php checked((int) $line['featured'], 1); ?>></td>
                                <td><input class="small-text" type="number" name="lines[<?php echo (int) $line['id']; ?>][sort_order]" value="<?php echo esc_attr((int) $line['sort_order']); ?>"></td>
                                <td><input class="regular-text" style="width:105px" type="text" name="lines[<?php echo (int) $line['id']; ?>][product_code]" value="<?php echo esc_attr($line['product_code']); ?>"></td>
                                <td><input class="regular-text" style="width:120px" type="text" name="lines[<?php echo (int) $line['id']; ?>][brand]" value="<?php echo esc_attr($line['brand']); ?>"></td>
                                <td><input class="regular-text" style="width:320px" type="text" name="lines[<?php echo (int) $line['id']; ?>][product_name]" value="<?php echo esc_attr($line['product_name']); ?>"></td>
                                <td><?php echo esc_html(number_format_i18n((float) $line['source_stock'], 2)); ?></td>
                                <td><input style="width:110px" type="number" step="0.01" name="lines[<?php echo (int) $line['id']; ?>][display_stock]" value="<?php echo esc_attr((float) $line['display_stock']); ?>"></td>
                                <td><input type="checkbox" name="lines[<?php echo (int) $line['id']; ?>][show_stock]" value="1" <?php checked((int) $line['show_stock'], 1); ?>></td>
                                <td><?php echo esc_html(number_format_i18n((float) $line['source_price'], 2)); ?> €</td>
                                <td><input style="width:100px" type="number" min="0" step="0.01" name="lines[<?php echo (int) $line['id']; ?>][display_price]" value="<?php echo esc_attr((float) $line['display_price']); ?>"></td>
                                <td><input type="checkbox" name="lines[<?php echo (int) $line['id']; ?>][show_price]" value="1" <?php checked((int) $line['show_price'], 1); ?>></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="frn-actions">
                    <?php submit_button('Guardar tarifa', 'primary', 'submit', false); ?>
                    <a class="button button-secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=frn_tariff_pdf&tariff_id=' . $tariffId), 'frn_tariff_pdf_' . $tariffId)); ?>">Descargar PDF</a>
                    <a class="button button-secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=frn_tariff_csv&tariff_id=' . $tariffId), 'frn_tariff_csv_' . $tariffId)); ?>">Descargar CSV</a>
                </div>
            </section>
        </form>
        <?php
    }

    public function create(): void
    {
        $this->guard('frn_tariff_create');
        $title = sanitize_text_field((string) ($_POST['title'] ?? 'Tarifa semanal FRN'));
        $date = sanitize_text_field((string) ($_POST['tariff_date'] ?? current_time('Y-m-d')));

        try {
            $id = $this->repository->create_from_catalog($title, $date);
        } catch (Throwable $e) {
            wp_die(esc_html($e->getMessage()));
        }

        wp_safe_redirect(admin_url('admin.php?page=frn-tariffs&tariff=' . $id . '&created=1'));
        exit;
    }

    public function save(): void
    {
        $id = absint($_POST['tariff_id'] ?? 0);
        $this->guard('frn_tariff_save_' . $id);

        $settings = is_array($_POST['settings'] ?? null) ? wp_unslash($_POST['settings']) : [];
        $lines = is_array($_POST['lines'] ?? null) ? wp_unslash($_POST['lines']) : [];

        try {
            $this->repository->save_tariff($id, $settings, $lines);
        } catch (Throwable $e) {
            wp_die(esc_html($e->getMessage()));
        }

        wp_safe_redirect(admin_url('admin.php?page=frn-tariffs&tariff=' . $id . '&saved=1'));
        exit;
    }

    public function save_settings(): void
    {
        $this->guard('frn_tariff_settings');
        $fields = [
            'frn_tariff_company' => 'FRN Atlántico',
            'frn_tariff_address' => '',
            'frn_tariff_phone' => '',
            'frn_tariff_email' => '',
            'frn_tariff_web' => 'www.frnatlantico.com',
        ];

        foreach ($fields as $key => $default) {
            update_option($key, sanitize_text_field((string) ($_POST[$key] ?? $default)), false);
        }

        wp_safe_redirect(admin_url('admin.php?page=frn-tariffs&settings_saved=1'));
        exit;
    }

    public function pdf(): void
    {
        $id = absint($_GET['tariff_id'] ?? 0);
        $this->guard_get('frn_tariff_pdf_' . $id);

        $tariff = $this->repository->get_tariff($id);
        $lines = $this->repository->get_lines($id, true);
        if (!$tariff) { wp_die('Tarifa no encontrada.'); }

        $autoload = FRN_SP_PATH . 'vendor/autoload.php';
        if (!file_exists($autoload)) {
            wp_die('Falta la librería PDF en el paquete instalado. Actualiza FRN Stock & Prices.');
        }
        require_once $autoload;

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($this->pdf_html($tariff, $lines), 'UTF-8');
        $dompdf->render();

        $filename = sanitize_file_name('FRN-Tarifa-' . $tariff['tariff_date'] . '.pdf');
        $dompdf->stream($filename, ['Attachment' => true]);
        exit;
    }

    public function csv(): void
    {
        $id = absint($_GET['tariff_id'] ?? 0);
        $this->guard_get('frn_tariff_csv_' . $id);

        $tariff = $this->repository->get_tariff($id);
        $lines = $this->repository->get_lines($id, true);
        if (!$tariff) { wp_die('Tarifa no encontrada.'); }

        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name('FRN-Tarifa-' . $tariff['tariff_date'] . '.csv') . '"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Categoría','Código','Marca','Producto','Stock','Precio'], ';');

        foreach ($lines as $line) {
            fputcsv($out, [
                $line['category'] === 'carne' ? 'Carne' : 'Pescado / Marisco',
                $line['product_code'],
                $line['brand'],
                $line['product_name'],
                ((int) $tariff['show_stock'] && (int) $line['show_stock']) ? $this->stock_text((float) $line['display_stock'], $tariff['stock_mode']) : '',
                ((int) $tariff['show_price'] && (int) $line['show_price']) ? number_format((float) $line['display_price'], 2, ',', '.') . ' €/kg' : '',
            ], ';');
        }
        fclose($out);
        exit;
    }

    private function pdf_html(array $tariff, array $lines): string
    {
        $company = get_option('frn_tariff_company', 'FRN Atlántico');
        $address = get_option('frn_tariff_address', '');
        $phone = get_option('frn_tariff_phone', '');
        $email = get_option('frn_tariff_email', '');
        $web = get_option('frn_tariff_web', 'www.frnatlantico.com');

        $logo = $this->logo_data_uri();
        $logoHtml = $logo
            ? '<img src="' . esc_attr($logo) . '" style="max-height:58px;max-width:220px">'
            : '<div class="wordmark">FRN ATLÁNTICO</div>';

        $rowsHtml = '';
        $currentCategory = '';

        foreach ($lines as $line) {
            if ($currentCategory !== $line['category']) {
                $currentCategory = $line['category'];
                $label = $currentCategory === 'carne' ? 'CARNE' : 'PESCADO Y MARISCO';
                $rowsHtml .= '<tr class="category"><td colspan="5">' . esc_html($label) . '</td></tr>';
            }

            $stock = ((int) $tariff['show_stock'] && (int) $line['show_stock'])
                ? $this->stock_text((float) $line['display_stock'], $tariff['stock_mode'])
                : '—';
            $price = ((int) $tariff['show_price'] && (int) $line['show_price'])
                ? number_format((float) $line['display_price'], 2, ',', '.') . ' €/kg'
                : '—';
            $offer = (int) $line['featured'] === 1 ? '<span class="offer">OFERTA</span> ' : '';

            $rowsHtml .= '<tr>'
                . '<td>' . esc_html($line['product_code']) . '</td>'
                . '<td>' . $offer . '<strong>' . esc_html($line['product_name']) . '</strong></td>'
                . '<td>' . esc_html($line['brand']) . '</td>'
                . '<td class="num">' . esc_html($stock) . '</td>'
                . '<td class="num price">' . esc_html($price) . '</td>'
                . '</tr>';
        }

        $contact = implode(' · ', array_filter([$address, $phone, $email, $web]));
        $date = mysql2date('d/m/Y', $tariff['tariff_date'] . ' 00:00:00');

        return '<!doctype html><html><head><meta charset="UTF-8"><style>
            @page{margin:26px 28px 40px}
            body{font-family:DejaVu Sans,Arial,sans-serif;color:#1b1e22;font-size:10px}
            .header{background:#080a0c;color:#fff;padding:22px 24px;border-bottom:4px solid #a9823f}
            .wordmark{font-family:DejaVu Serif,serif;color:#d6b36a;font-size:30px;font-weight:bold;letter-spacing:2px}
            .title{margin-top:16px;font-family:DejaVu Serif,serif;font-size:26px;line-height:1.05}
            .meta{margin-top:8px;color:#d3d3d3;font-size:9px}
            table{width:100%;border-collapse:collapse;margin-top:20px}
            th{background:#202733;color:#fff;padding:8px 7px;text-align:left;font-size:8px;text-transform:uppercase}
            td{padding:7px;border-bottom:1px solid #e1e1e1;vertical-align:top}
            .category td{background:#f1ece3;color:#6e5421;font-weight:bold;letter-spacing:1px;padding:8px}
            .num{text-align:right;white-space:nowrap}.price{font-weight:bold}
            .offer{display:inline-block;background:#a9823f;color:#fff;padding:2px 5px;font-size:7px}
            .footer{position:fixed;left:0;right:0;bottom:-22px;border-top:1px solid #d6d0c5;padding-top:7px;color:#777;font-size:8px}
            .terms{margin-top:14px;color:#666;font-size:8px}
        </style></head><body>
        <div class="header">' . $logoHtml . '<div class="title">' . esc_html($tariff['title']) . '</div><div class="meta">Fecha: ' . esc_html($date) . ' · ' . esc_html($company) . '</div></div>
        <table><thead><tr><th style="width:12%">Código</th><th>Producto</th><th style="width:16%">Marca</th><th style="width:16%;text-align:right">Stock</th><th style="width:15%;text-align:right">Precio</th></tr></thead><tbody>' . $rowsHtml . '</tbody></table>
        <div class="terms">Stock sujeto a disponibilidad en el momento de confirmación. Precios y condiciones sujetos a validación comercial.</div>
        <div class="footer">' . esc_html($contact) . '</div>
        </body></html>';
    }

    private function logo_data_uri(): string
    {
        $logoId = (int) get_theme_mod('custom_logo');
        if ($logoId <= 0) { return ''; }
        $path = get_attached_file($logoId);
        if (!$path || !is_readable($path)) { return ''; }
        $mime = get_post_mime_type($logoId);
        if (!in_array($mime, ['image/png','image/jpeg'], true)) { return ''; }
        return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($path));
    }

    private function stock_text(float $stock, string $mode): string
    {
        return match ($mode) {
            'rounded' => number_format(round($stock), 0, ',', '.') . ' kg',
            'available' => $stock > 0 ? 'Disponible' : 'Consultar',
            'hidden' => '',
            default => number_format($stock, 2, ',', '.') . ' kg',
        };
    }

    private function settings_form(): void
    {
        $values = [
            'frn_tariff_company' => get_option('frn_tariff_company', 'FRN Atlántico'),
            'frn_tariff_address' => get_option('frn_tariff_address', ''),
            'frn_tariff_phone' => get_option('frn_tariff_phone', ''),
            'frn_tariff_email' => get_option('frn_tariff_email', ''),
            'frn_tariff_web' => get_option('frn_tariff_web', 'www.frnatlantico.com'),
        ];
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="frn_tariff_settings">
            <?php wp_nonce_field('frn_tariff_settings'); ?>
            <?php foreach ($values as $key => $value) : ?>
                <p><label><strong><?php echo esc_html(match ($key) {
                    'frn_tariff_company' => 'Empresa',
                    'frn_tariff_address' => 'Dirección',
                    'frn_tariff_phone' => 'Teléfono',
                    'frn_tariff_email' => 'Email',
                    default => 'Web',
                }); ?></strong><br><input class="regular-text" type="text" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr((string) $value); ?>"></label></p>
            <?php endforeach; ?>
            <?php submit_button('Guardar datos comerciales', 'secondary', 'submit', false); ?>
        </form>
        <?php
    }

    private function guard(string $nonce): void
    {
        if (!current_user_can('frn_manage_stock')) { wp_die('No autorizado.', 403); }
        check_admin_referer($nonce);
    }

    private function guard_get(string $nonce): void
    {
        if (!current_user_can('frn_manage_stock')) { wp_die('No autorizado.', 403); }
        check_admin_referer($nonce);
    }

    private function styles(): void
    {
        echo '<style>
            .frn-tariff-grid{display:grid;grid-template-columns:1.2fr .8fr;gap:22px;max-width:1320px}
            .frn-card{background:#fff;border:1px solid #dcdcde;padding:22px}
            .frn-card h2{margin-top:0}
            .frn-editor-head{display:grid;grid-template-columns:1fr 180px 150px;gap:18px;align-items:end}
            .frn-toggle-row{display:flex;gap:24px;align-items:center;flex-wrap:wrap;margin-top:20px;padding-top:18px;border-top:1px solid #eee}
            .frn-table-scroll{overflow:auto;max-height:620px;border:1px solid #dcdcde}
            .frn-tariff-table{min-width:1500px}
            .frn-tariff-table td,.frn-tariff-table th{vertical-align:middle}
            .frn-category-row td{background:#202733!important;color:#fff!important;font-weight:700;letter-spacing:.08em}
            .frn-actions{display:flex;gap:10px;align-items:center;margin-top:18px}
            @media(max-width:900px){.frn-tariff-grid,.frn-editor-head{grid-template-columns:1fr}}
        </style>';
    }
}
