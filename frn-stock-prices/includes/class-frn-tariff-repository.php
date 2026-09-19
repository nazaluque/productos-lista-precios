<?php

if (!defined('ABSPATH')) { exit; }

final class FRN_Tariff_Repository
{
    public static function tariffs_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'frn_tariffs';
    }

    public static function lines_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'frn_tariff_lines';
    }

    public static function create_tables(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $tariffs = self::tariffs_table();
        $lines = self::lines_table();

        dbDelta("CREATE TABLE {$tariffs} (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            tariff_date date NOT NULL,
            catalog_scope varchar(40) NOT NULL DEFAULT 'all',
            price_list_id bigint unsigned NOT NULL DEFAULT 0,
            price_list_name varchar(255) NOT NULL DEFAULT '',
            preset_mode varchar(30) NOT NULL DEFAULT 'general',
            show_stock tinyint(1) NOT NULL DEFAULT 1,
            show_price tinyint(1) NOT NULL DEFAULT 1,
            stock_mode varchar(20) NOT NULL DEFAULT 'available',
            status varchar(20) NOT NULL DEFAULT 'final',
            source_file varchar(255) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY tariff_date (tariff_date),
            KEY catalog_scope (catalog_scope),
            KEY price_list_id (price_list_id),
            KEY status (status)
        ) {$charset};");

        dbDelta("CREATE TABLE {$lines} (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            tariff_id bigint unsigned NOT NULL,
            category varchar(40) NOT NULL,
            product_code varchar(80) NOT NULL DEFAULT '',
            brand varchar(160) NOT NULL DEFAULT '',
            product_name varchar(255) NOT NULL,
            source_stock decimal(14,2) NOT NULL DEFAULT 0,
            source_price decimal(12,2) NULL,
            display_stock decimal(14,2) NOT NULL DEFAULT 0,
            display_price decimal(12,2) NULL,
            show_stock tinyint(1) NOT NULL DEFAULT 1,
            show_price tinyint(1) NOT NULL DEFAULT 1,
            visible tinyint(1) NOT NULL DEFAULT 1,
            featured tinyint(1) NOT NULL DEFAULT 0,
            incoming tinyint(1) NOT NULL DEFAULT 0,
            sort_order int NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY tariff_id (tariff_id),
            KEY tariff_category (tariff_id, category),
            KEY incoming (incoming)
        ) {$charset};");
    }

    public function create_from_catalog(
        string $title,
        string $date,
        string $scope,
        string $preset = 'general',
        int $priceListId = 0
    ): int {
        global $wpdb;

        if (!in_array($scope, ['carne','pescado-marisco'], true)) {
            throw new RuntimeException('Tipo de tarifa no válido.');
        }

        $preset = in_array($preset, ['general','distribuidor','disponibilidad','personalizado'], true)
            ? $preset
            : 'general';

        [$showStock, $showPrice, $stockMode] = $this->preset_values($preset);

        $catalog = new FRN_Catalog_Repository();
        $products = $catalog->all($scope, true);

        if (!$products) {
            throw new RuntimeException('No hay productos importados para esta familia.');
        }

        $priceRepo = new FRN_Price_List_Repository();
        $priceList = $priceListId > 0 ? $priceRepo->get($priceListId) : null;

        if ($priceListId > 0 && !$priceList) {
            throw new RuntimeException('La tarifa de precios seleccionada ya no existe.');
        }

        $priceMap = $priceList ? $priceRepo->item_map($priceListId, $scope) : [];
        $sourceFile = '';

        foreach ($products as $product) {
            if ($sourceFile === '' && !empty($product['source_file'])) {
                $sourceFile = (string) $product['source_file'];
            }
        }

        $now = current_time('mysql');

        $ok = $wpdb->insert(self::tariffs_table(), [
            'title' => sanitize_text_field($title),
            'tariff_date' => $date,
            'catalog_scope' => $scope,
            'price_list_id' => $priceList ? $priceListId : 0,
            'price_list_name' => $priceList ? (string) $priceList['name'] : '',
            'preset_mode' => $preset,
            'show_stock' => $showStock,
            'show_price' => $showPrice,
            'stock_mode' => $stockMode,
            'status' => 'final',
            'source_file' => sanitize_file_name($sourceFile),
            'created_at' => $now,
            'updated_at' => $now,
        ], ['%s','%s','%s','%d','%s','%s','%d','%d','%s','%s','%s','%s','%s']);

        if (!$ok) {
            throw new RuntimeException($wpdb->last_error ?: 'No se pudo crear la tarifa.');
        }

        $tariffId = (int) $wpdb->insert_id;
        $sort = 0;

        foreach ($products as $product) {
            $sort++;

            $incoming = (int) $product['incoming'] === 1;
            $stock = (float) $product['stock_kg'];
            $key = FRN_Price_List_Repository::match_key(
                (string) $product['category'],
                (string) $product['product_code'],
                (string) $product['product_name']
            );
            $priceItem = $priceMap[$key] ?? null;
            $price = $priceItem && (float) ($priceItem['price_kg'] ?? 0) > 0
                ? (float) $priceItem['price_kg']
                : 0.0;

            $visible = $incoming
                ? (
                    $priceItem
                        ? (int) $priceItem['visible'] === 1
                        : (int) $product['visible'] === 1
                )
                : ((int) $product['visible'] === 1 && $stock > 0);

            $featured = $priceItem
                ? ((int) $priceItem['featured'] === 1 ? 1 : 0)
                : ((int) $product['featured'] === 1 ? 1 : 0);

            $brand = $priceItem && trim((string) $priceItem['brand']) !== ''
                ? (string) $priceItem['brand']
                : (string) $product['brand'];

            $name = $priceItem && trim((string) $priceItem['product_name']) !== ''
                ? (string) $priceItem['product_name']
                : (string) $product['product_name'];

            $wpdb->insert(self::lines_table(), [
                'tariff_id' => $tariffId,
                'category' => (string) $product['category'],
                'product_code' => (string) $product['product_code'],
                'brand' => $brand,
                'product_name' => $name,
                'source_stock' => $stock,
                'source_price' => $price > 0 ? $price : null,
                'display_stock' => $stock,
                'display_price' => $price > 0 ? $price : null,
                'show_stock' => $showStock,
                'show_price' => ($showPrice && $price > 0) ? 1 : 0,
                'visible' => $visible ? 1 : 0,
                'featured' => $featured,
                'incoming' => $incoming ? 1 : 0,
                'sort_order' => $sort,
            ], ['%d','%s','%s','%s','%s','%f','%f','%f','%f','%d','%d','%d','%d','%d','%d']);

            if ($wpdb->last_error) {
                throw new RuntimeException($wpdb->last_error);
            }
        }

        return $tariffId;
    }

    public function all_tariffs(): array
    {
        global $wpdb;

        return $wpdb->get_results(
            'SELECT * FROM ' . self::tariffs_table() . ' ORDER BY tariff_date DESC, id DESC',
            ARRAY_A
        ) ?: [];
    }

    public function get_tariff(int $id): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . self::tariffs_table() . ' WHERE id = %d', $id),
            ARRAY_A
        );

        return $row ?: null;
    }

    public function get_lines(int $tariffId, bool $onlyVisible = false): array
    {
        global $wpdb;

        $visible = $onlyVisible ? ' AND visible = 1' : '';

        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . self::lines_table() .
                ' WHERE tariff_id = %d' . $visible .
                ' ORDER BY incoming ASC, featured DESC, sort_order ASC, product_name ASC',
                $tariffId
            ),
            ARRAY_A
        ) ?: [];
    }

    public function save_tariff(int $id, array $settings, array $lines): void
    {
        global $wpdb;

        $showStock = !empty($settings['show_stock']) ? 1 : 0;
        $showPrice = !empty($settings['show_price']) ? 1 : 0;
        $preset = in_array(($settings['preset_mode'] ?? ''), ['general','distribuidor','disponibilidad','personalizado'], true)
            ? (string) $settings['preset_mode']
            : 'personalizado';
        $stockMode = in_array(($settings['stock_mode'] ?? ''), ['exact','rounded','available','hidden'], true)
            ? (string) $settings['stock_mode']
            : 'available';

        $wpdb->update(self::tariffs_table(), [
            'title' => sanitize_text_field((string) ($settings['title'] ?? 'Tarifa FRN')),
            'tariff_date' => sanitize_text_field((string) ($settings['tariff_date'] ?? current_time('Y-m-d'))),
            'preset_mode' => $preset,
            'show_stock' => $showStock,
            'show_price' => $showPrice,
            'stock_mode' => $stockMode,
            'status' => 'final',
            'updated_at' => current_time('mysql'),
        ], ['id' => $id], ['%s','%s','%s','%d','%d','%s','%s','%s'], ['%d']);

        if ($wpdb->last_error) {
            throw new RuntimeException($wpdb->last_error);
        }

        foreach ($lines as $lineId => $line) {
            $lineId = (int) $lineId;
            if ($lineId <= 0 || !is_array($line)) { continue; }

            $code = sanitize_text_field((string) ($line['product_code'] ?? ''));
            $incoming = FRN_Excel_Importer::is_incoming_code($code);
            $displayPrice = isset($line['display_price']) && $line['display_price'] !== ''
                ? max(0, (float) $line['display_price'])
                : 0.0;

            $wpdb->update(self::lines_table(), [
                'product_code' => $code,
                'brand' => sanitize_text_field((string) ($line['brand'] ?? '')),
                'product_name' => sanitize_text_field((string) ($line['product_name'] ?? '')),
                'display_stock' => (float) ($line['display_stock'] ?? 0),
                'display_price' => $displayPrice > 0 ? $displayPrice : null,
                'show_stock' => $showStock ? (!empty($line['show_stock']) ? 1 : 0) : 0,
                'show_price' => ($showPrice && $displayPrice > 0 && !empty($line['show_price'])) ? 1 : 0,
                'visible' => !empty($line['visible']) ? 1 : 0,
                'featured' => !empty($line['featured']) ? 1 : 0,
                'incoming' => $incoming ? 1 : 0,
                'sort_order' => (int) ($line['sort_order'] ?? 0),
            ], ['id' => $lineId, 'tariff_id' => $id], ['%s','%s','%s','%f','%f','%d','%d','%d','%d','%d','%d'], ['%d','%d']);

            if ($wpdb->last_error) {
                throw new RuntimeException($wpdb->last_error);
            }
        }
    }

    public function scope_label(string $scope): string
    {
        return $scope === 'carne' ? 'Carne' : 'Pescado y marisco';
    }

    private function preset_values(string $preset): array
    {
        return match ($preset) {
            'distribuidor' => [1, 1, 'exact'],
            'disponibilidad' => [1, 0, 'available'],
            'personalizado' => [1, 1, 'available'],
            default => [1, 1, 'available'],
        };
    }
}
