<?php

if (!defined('ABSPATH')) { exit; }

final class FRN_Frontend_App
{
    private const PREVIEW_PREFIX = 'frn_front_preview_';

    private FRN_Catalog_Repository $catalog;
    private FRN_Tariff_Repository $tariffs;
    private FRN_Excel_Importer $excel;

    public function __construct()
    {
        $this->catalog = new FRN_Catalog_Repository();
        $this->tariffs = new FRN_Tariff_Repository();
        $this->excel = new FRN_Excel_Importer();
    }

    public function boot(): void
    {
        add_action('admin_post_frn_front_preview', [$this, 'preview']);
        add_action('admin_post_frn_front_publish', [$this, 'publish']);
        add_action('admin_post_frn_front_save_products', [$this, 'save_products']);
        add_action('admin_post_frn_front_tariff_create', [$this, 'tariff_create']);
        add_action('admin_post_frn_front_tariff_save', [$this, 'tariff_save']);
        add_action('admin_post_frn_front_tariff_pdf', [$this, 'tariff_pdf']);
        add_action('admin_post_frn_front_tariff_csv', [$this, 'tariff_csv']);
        add_action('admin_post_frn_front_settings', [$this, 'save_settings']);
    }

    public function render(): void
    {
        $this->guard_capability();

        $tab = sanitize_key($_GET['tab'] ?? 'importar');
        if (!in_array($tab, ['importar','tarifas','tarifa'], true)) {
            $tab = 'importar';
        }

        $previewToken = sanitize_key($_GET['preview'] ?? '');
        $preview = $previewToken ? get_transient(self::PREVIEW_PREFIX . $previewToken) : null;
        $tariffId = absint($_GET['id'] ?? 0);

        $data = [
            'tab' => $tab,
            'previewToken' => $previewToken,
            'preview' => is_array($preview) ? $preview : null,
            'products' => $this->catalog->all_combined(true),
            'tariffs' => $this->tariffs->all_tariffs(),
            'tariff' => $tariffId ? $this->tariffs->get_tariff($tariffId) : null,
            'tariffLines' => $tariffId ? $this->tariffs->get_lines($tariffId) : [],
            'messages' => $this->messages(),
        ];

        extract($data, EXTR_SKIP);
        require FRN_SP_PATH . 'templates/app.php';
    }

    public function preview(): void
    {
        $this->guard_post('frn_front_preview');

        try {
            $parsed = $this->excel->parse_files($_FILES['catalog_files'] ?? []);
        } catch (Throwable $e) {
            $this->redirect(['tab' => 'importar', 'error' => rawurlencode($e->getMessage())]);
        }

        $token = wp_generate_password(20, false, false);
        set_transient(self::PREVIEW_PREFIX . $token, $parsed, HOUR_IN_SECONDS);

        $this->redirect(['tab' => 'importar', 'preview' => $token]);
    }

    public function publish(): void
    {
        $token = sanitize_key($_POST['preview'] ?? '');
        $this->guard_post('frn_front_publish_' . $token);

        $preview = get_transient(self::PREVIEW_PREFIX . $token);
        if (!$preview || !is_array($preview)) {
            $this->redirect(['tab' => 'importar', 'error' => rawurlencode('La previsualización ha caducado.')]);
        }

        $all = array_merge(
            $preview['catalogs']['carne'] ?? [],
            $preview['catalogs']['pescado-marisco'] ?? []
        );

        $invalid = array_filter($all, static fn(array $row): bool => empty($row['valid']));
        if ($invalid) {
            $this->redirect([
                'tab' => 'importar',
                'preview' => $token,
                'error' => rawurlencode('Hay filas con errores. Corrige el Excel antes de publicar.'),
            ]);
        }

        $counts = ['carne' => 0, 'pescado-marisco' => 0];

        try {
            foreach ($counts as $category => $_) {
                $rows = array_values($preview['catalogs'][$category] ?? []);
                if (!$rows) { continue; }

                $counts[$category] = $this->catalog->publish(
                    $category,
                    (string) ($preview['filename'] ?? 'Excel semanal'),
                    $rows
                );
            }
        } catch (Throwable $e) {
            $this->redirect([
                'tab' => 'importar',
                'preview' => $token,
                'error' => rawurlencode($e->getMessage()),
            ]);
        }

        delete_transient(self::PREVIEW_PREFIX . $token);

        $this->redirect([
            'tab' => 'importar',
            'published' => $counts['carne'] + $counts['pescado-marisco'],
            'carne' => $counts['carne'],
            'pescado' => $counts['pescado-marisco'],
        ]);
    }

    public function save_products(): void
    {
        $this->guard_post('frn_front_save_products');

        $raw = is_array($_POST['products'] ?? null) ? wp_unslash($_POST['products']) : [];
        $rows = [];

        foreach ($raw as $id => $row) {
            if (!is_array($row)) { continue; }

            $rows[] = [
                'id' => (int) $id,
                'code' => sanitize_text_field((string) ($row['code'] ?? '')),
                'brand' => sanitize_text_field((string) ($row['brand'] ?? '')),
                'name' => sanitize_text_field((string) ($row['name'] ?? '')),
                'stock' => $this->number($row['stock'] ?? 0),
                'price' => max(0, $this->number($row['price'] ?? 0)),
                'featured' => !empty($row['featured']),
                'visible' => !empty($row['visible']),
            ];
        }

        try {
            $this->catalog->update_many($rows);
        } catch (Throwable $e) {
            $this->redirect(['tab' => 'importar', 'error' => rawurlencode($e->getMessage())]);
        }

        $this->redirect(['tab' => 'importar', 'saved' => 1]);
    }

    public function tariff_create(): void
    {
        $this->guard_post('frn_front_tariff_create');

        $scope = sanitize_key((string) ($_POST['scope'] ?? ''));
        if (!in_array($scope, ['carne','pescado-marisco'], true)) {
            $this->redirect(['tab' => 'tarifas', 'error' => rawurlencode('Selecciona Carne o Pescado y marisco.')]);
        }

        $preset = sanitize_key((string) ($_POST['preset'] ?? 'general'));
        $date = sanitize_text_field((string) ($_POST['tariff_date'] ?? current_time('Y-m-d')));
        $label = $scope === 'carne' ? 'Carne' : 'Pescado y marisco';
        $title = 'Tarifa ' . $label . ' · ' . wp_date('d/m/Y', strtotime($date));

        try {
            $id = $this->tariffs->create_from_catalog($title, $date, $scope, $preset);
        } catch (Throwable $e) {
            $this->redirect(['tab' => 'tarifas', 'error' => rawurlencode($e->getMessage())]);
        }

        $this->redirect(['tab' => 'tarifa', 'id' => $id, 'created' => 1]);
    }

    public function tariff_save(): void
    {
        $id = absint($_POST['tariff_id'] ?? 0);
        $this->guard_post('frn_front_tariff_save_' . $id);

        $settings = is_array($_POST['settings'] ?? null) ? wp_unslash($_POST['settings']) : [];
        $lines = is_array($_POST['lines'] ?? null) ? wp_unslash($_POST['lines']) : [];

        try {
            $this->tariffs->save_tariff($id, $settings, $lines);
        } catch (Throwable $e) {
            $this->redirect(['tab' => 'tarifa', 'id' => $id, 'error' => rawurlencode($e->getMessage())]);
        }

        $this->redirect(['tab' => 'tarifa', 'id' => $id, 'saved' => 1]);
    }

    public function save_settings(): void
    {
        $this->guard_post('frn_front_settings');

        $fields = [
            'frn_tariff_company' => 'FRN Atlántico',
            'frn_tariff_address' => '',
            'frn_tariff_phone' => '',
            'frn_tariff_email' => '',
            'frn_tariff_web' => 'www.frnatlantico.com',
        ];

        foreach ($fields as $key => $default) {
            update_option(
                $key,
                sanitize_text_field((string) ($_POST[$key] ?? $default)),
                false
            );
        }

        $this->redirect(['tab' => 'tarifas', 'settings_saved' => 1]);
    }

    public function tariff_pdf(): void
    {
        $id = absint($_GET['tariff_id'] ?? 0);
        $this->guard_get('frn_front_tariff_pdf_' . $id);

        $tariff = $this->tariffs->get_tariff($id);
        $lines = $this->tariffs->get_lines($id, true);

        if (!$tariff) { wp_die('Tarifa no encontrada.'); }

        $autoload = FRN_SP_PATH . 'vendor/autoload.php';
        if (!file_exists($autoload)) {
            wp_die('Falta la librería PDF en el paquete instalado.');
        }

        require_once $autoload;

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($this->pdf_html($tariff, $lines), 'UTF-8');
        $dompdf->render();

        $scope = ($tariff['catalog_scope'] ?? '') === 'carne' ? 'Carne' : 'Pescado-Marisco';
        $dompdf->stream(
            sanitize_file_name('FRN-Tarifa-' . $scope . '-' . $tariff['tariff_date'] . '.pdf'),
            ['Attachment' => true]
        );
        exit;
    }

    public function tariff_csv(): void
    {
        $id = absint($_GET['tariff_id'] ?? 0);
        $this->guard_get('frn_front_tariff_csv_' . $id);

        $tariff = $this->tariffs->get_tariff($id);
        $lines = $this->tariffs->get_lines($id, true);

        if (!$tariff) { wp_die('Tarifa no encontrada.'); }

        $scope = ($tariff['catalog_scope'] ?? '') === 'carne' ? 'Carne' : 'Pescado-Marisco';

        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header(
            'Content-Disposition: attachment; filename="' .
            sanitize_file_name('FRN-Tarifa-' . $scope . '-' . $tariff['tariff_date'] . '.csv') .
            '"'
        );

        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Sección','Código','Marca','Producto','Stock','Precio'], ';');

        foreach ($lines as $line) {
            fputcsv($out, [
                (int) $line['incoming'] === 1 ? 'Próximos ingresos' : 'Productos',
                $line['product_code'],
                $line['brand'],
                $line['product_name'],
                ((int) $tariff['show_stock'] && (int) $line['show_stock'])
                    ? $this->stock_text((float) $line['display_stock'], (string) $tariff['stock_mode'])
                    : '',
                ((int) $tariff['show_price'] && (int) $line['show_price'])
                    ? number_format((float) $line['display_price'], 2, ',', '.') . ' €/kg'
                    : '',
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

        $showStock = (int) ($tariff['show_stock'] ?? 0) === 1;
        $showPrice = (int) ($tariff['show_price'] ?? 0) === 1;
        $columnCount = 3 + ($showStock ? 1 : 0) + ($showPrice ? 1 : 0);

        $regular = array_values(array_filter(
            $lines,
            static fn(array $line): bool => (int) $line['incoming'] !== 1
        ));
        $incoming = array_values(array_filter(
            $lines,
            static fn(array $line): bool => (int) $line['incoming'] === 1
        ));

        $rowsHtml = $this->pdf_rows($regular, $tariff);
        if ($incoming) {
            $rowsHtml .= '<tr class="incoming"><td colspan="' . $columnCount . '">PRÓXIMOS INGRESOS</td></tr>';
            $rowsHtml .= $this->pdf_rows($incoming, $tariff);
        }

        $headers = '<th style="width:12%">Código</th><th>Producto</th><th style="width:16%">Marca</th>';
        if ($showStock) {
            $headers .= '<th style="width:16%;text-align:right">Stock</th>';
        }
        if ($showPrice) {
            $headers .= '<th style="width:15%;text-align:right">Precio</th>';
        }

        $contact = implode(' · ', array_filter([$address, $phone, $email, $web]));
        $date = mysql2date('d/m/Y', $tariff['tariff_date'] . ' 00:00:00');
        $scopeLabel = ($tariff['catalog_scope'] ?? '') === 'carne' ? 'Carne' : 'Pescado y marisco';

        return '<!doctype html><html><head><meta charset="UTF-8"><style>
            @page{margin:26px 28px 40px}
            body{font-family:DejaVu Sans,Arial,sans-serif;color:#1b1e22;font-size:10px}
            .header{background:#080a0c;color:#fff;padding:22px 24px;border-bottom:4px solid #a9823f}
            .wordmark{font-family:DejaVu Serif,serif;color:#d6b36a;font-size:30px;font-weight:bold;letter-spacing:2px}
            .eyebrow{margin-top:12px;color:#d6b36a;font-size:9px;text-transform:uppercase;letter-spacing:1.2px}
            .title{margin-top:8px;font-family:DejaVu Serif,serif;font-size:26px;line-height:1.05}
            .meta{margin-top:8px;color:#d3d3d3;font-size:9px}
            table{width:100%;border-collapse:collapse;margin-top:20px}
            th{background:#202733;color:#fff;padding:8px 7px;text-align:left;font-size:8px;text-transform:uppercase}
            td{padding:7px;border-bottom:1px solid #e1e1e1;vertical-align:top}
            .incoming td{background:#0d1115;color:#d6b36a;font-weight:bold;letter-spacing:1.2px;padding:9px}
            .num{text-align:right;white-space:nowrap}.price{font-weight:bold}
            .offer{display:inline-block;background:#a9823f;color:#fff;padding:2px 5px;font-size:7px}
            .footer{position:fixed;left:0;right:0;bottom:-22px;border-top:1px solid #d6d0c5;padding-top:7px;color:#777;font-size:8px}
            .terms{margin-top:14px;color:#666;font-size:8px}
        </style></head><body>
        <div class="header">' .
            $logoHtml .
            '<div class="eyebrow">' . esc_html($scopeLabel) . '</div>' .
            '<div class="title">' . esc_html($tariff['title']) . '</div>' .
            '<div class="meta">Fecha: ' . esc_html($date) . ' · ' . esc_html($company) . '</div>' .
        '</div>
        <table>
            <thead><tr>' . $headers . '</tr></thead>
            <tbody>' . $rowsHtml . '</tbody>
        </table>
        <div class="terms">Stock sujeto a disponibilidad en el momento de confirmación. Precios y condiciones sujetos a validación comercial.</div>
        <div class="footer">' . esc_html($contact) . '</div>
        </body></html>';
    }

    private function pdf_rows(array $lines, array $tariff): string
    {
        $html = '';
        $showStock = (int) ($tariff['show_stock'] ?? 0) === 1;
        $showPrice = (int) ($tariff['show_price'] ?? 0) === 1;

        foreach ($lines as $line) {
            $offer = (int) $line['featured'] === 1
                ? '<span class="offer">OFERTA</span> '
                : '';

            $html .= '<tr>'
                . '<td>' . esc_html($line['product_code']) . '</td>'
                . '<td>' . $offer . '<strong>' . esc_html($line['product_name']) . '</strong></td>'
                . '<td>' . esc_html($line['brand']) . '</td>';

            if ($showStock) {
                $stock = (int) $line['show_stock']
                    ? $this->stock_text((float) $line['display_stock'], (string) $tariff['stock_mode'])
                    : '—';
                $html .= '<td class="num">' . esc_html($stock) . '</td>';
            }

            if ($showPrice) {
                $price = (int) $line['show_price']
                    ? number_format((float) $line['display_price'], 2, ',', '.') . ' €/kg'
                    : '—';
                $html .= '<td class="num price">' . esc_html($price) . '</td>';
            }

            $html .= '</tr>';
        }

        return $html;
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
            'available' => $stock > 0 ? 'Disponible' : 'Próximamente',
            'hidden' => '',
            default => $stock > 0 ? number_format($stock, 2, ',', '.') . ' kg' : 'Próximamente',
        };
    }

    private function messages(): array
    {
        $messages = [];

        if (isset($_GET['published'])) {
            $messages[] = [
                'success',
                'Excel publicado: ' . (int) $_GET['published'] . ' referencias actualizadas.',
            ];
        }
        if (isset($_GET['saved'])) {
            $messages[] = ['success', 'Cambios guardados.'];
        }
        if (isset($_GET['created'])) {
            $messages[] = ['success', 'Tarifa creada. Ya puedes revisarla y descargar el PDF.'];
        }
        if (isset($_GET['settings_saved'])) {
            $messages[] = ['success', 'Datos comerciales del PDF guardados.'];
        }
        if (!empty($_GET['error'])) {
            $messages[] = ['error', sanitize_text_field(wp_unslash($_GET['error']))];
        }

        return $messages;
    }

    private function guard_capability(): void
    {
        if (!is_user_logged_in() || !current_user_can('frn_manage_stock')) {
            wp_die('No autorizado.', 403);
        }
    }

    private function guard_post(string $nonce): void
    {
        $this->guard_capability();
        check_admin_referer($nonce);
    }

    private function guard_get(string $nonce): void
    {
        $this->guard_capability();
        check_admin_referer($nonce);
    }

    private function redirect(array $args): never
    {
        wp_safe_redirect(add_query_arg($args, home_url('/stock/')));
        exit;
    }

    private function number(mixed $value): float
    {
        if (is_int($value) || is_float($value)) { return (float) $value; }

        $clean = preg_replace('/[^0-9,.-]/', '', (string) $value);
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

        return is_numeric($clean) ? (float) $clean : 0.0;
    }
}
