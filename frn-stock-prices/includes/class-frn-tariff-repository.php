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
            show_stock tinyint(1) NOT NULL DEFAULT 1,
            show_price tinyint(1) NOT NULL DEFAULT 1,
            stock_mode varchar(20) NOT NULL DEFAULT 'exact',
            status varchar(20) NOT NULL DEFAULT 'draft',
            source_file varchar(255) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY tariff_date (tariff_date),
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
            sort_order int NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY tariff_id (tariff_id),
            KEY tariff_category (tariff_id, category)
        ) {$charset};");
    }

    public function create_from_catalog(string $title, string $date): int
    {
        global $wpdb;
        $catalog = new FRN_Catalog_Repository();
        $sourceFile = '';
        $all = [];

        foreach (['pescado-marisco', 'carne'] as $category) {
            $products = $catalog->all($category, true);
            foreach ($products as $product) {
                if ($sourceFile === '' && !empty($product['source_file'])) {
                    $sourceFile = (string) $product['source_file'];
                }
                $all[] = [$category, $product];
            }
        }

        if (!$all) {
            throw new RuntimeException('No hay productos en el catálogo actual. Importa primero el Excel semanal.');
        }

        $now = current_time('mysql');
        $ok = $wpdb->insert(self::tariffs_table(), [
            'title' => sanitize_text_field($title),
            'tariff_date' => $date,
            'show_stock' => 1,
            'show_price' => 1,
            'stock_mode' => 'exact',
            'status' => 'draft',
            'source_file' => sanitize_file_name($sourceFile),
            'created_at' => $now,
            'updated_at' => $now,
        ], ['%s','%s','%d','%d','%s','%s','%s','%s','%s']);

        if (!$ok) {
            throw new RuntimeException($wpdb->last_error ?: 'No se pudo crear la tarifa.');
        }

        $tariffId = (int) $wpdb->insert_id;
        $sort = 0;

        foreach ($all as [$category, $product]) {
            $sort++;
            $wpdb->insert(self::lines_table(), [
                'tariff_id' => $tariffId,
                'category' => $category,
                'product_code' => (string) $product['product_code'],
                'brand' => (string) $product['brand'],
                'product_name' => (string) $product['product_name'],
                'source_stock' => (float) $product['stock_kg'],
                'source_price' => (float) $product['price_kg'],
                'display_stock' => (float) $product['stock_kg'],
                'display_price' => (float) $product['price_kg'],
                'show_stock' => 1,
                'show_price' => 1,
                'visible' => (int) $product['visible'] === 1 ? 1 : 0,
                'featured' => (int) $product['featured'] === 1 ? 1 : 0,
                'sort_order' => $sort,
            ], ['%d','%s','%s','%s','%s','%f','%f','%f','%f','%d','%d','%d','%d','%d']);

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
                'SELECT * FROM ' . self::lines_table() . ' WHERE tariff_id = %d' . $visible . ' ORDER BY category ASC, featured DESC, sort_order ASC, product_name ASC',
                $tariffId
            ),
            ARRAY_A
        ) ?: [];
    }

    public function save_tariff(int $id, array $settings, array $lines): void
    {
        global $wpdb;

        $wpdb->update(self::tariffs_table(), [
            'title' => sanitize_text_field((string) ($settings['title'] ?? 'Tarifa FRN')),
            'tariff_date' => sanitize_text_field((string) ($settings['tariff_date'] ?? current_time('Y-m-d'))),
            'show_stock' => !empty($settings['show_stock']) ? 1 : 0,
            'show_price' => !empty($settings['show_price']) ? 1 : 0,
            'stock_mode' => in_array(($settings['stock_mode'] ?? ''), ['exact','rounded','available','hidden'], true) ? $settings['stock_mode'] : 'exact',
            'status' => in_array(($settings['status'] ?? ''), ['draft','final'], true) ? $settings['status'] : 'draft',
            'updated_at' => current_time('mysql'),
        ], ['id' => $id], ['%s','%s','%d','%d','%s','%s','%s'], ['%d']);

        if ($wpdb->last_error) {
            throw new RuntimeException($wpdb->last_error);
        }

        foreach ($lines as $lineId => $line) {
            $lineId = (int) $lineId;
            if ($lineId <= 0 || !is_array($line)) { continue; }

            $wpdb->update(self::lines_table(), [
                'product_code' => sanitize_text_field((string) ($line['product_code'] ?? '')),
                'brand' => sanitize_text_field((string) ($line['brand'] ?? '')),
                'product_name' => sanitize_text_field((string) ($line['product_name'] ?? '')),
                'display_stock' => (float) ($line['display_stock'] ?? 0),
                'display_price' => max(0, (float) ($line['display_price'] ?? 0)),
                'show_stock' => !empty($line['show_stock']) ? 1 : 0,
                'show_price' => !empty($line['show_price']) ? 1 : 0,
                'visible' => !empty($line['visible']) ? 1 : 0,
                'featured' => !empty($line['featured']) ? 1 : 0,
                'sort_order' => (int) ($line['sort_order'] ?? 0),
            ], ['id' => $lineId, 'tariff_id' => $id], ['%s','%s','%s','%f','%f','%d','%d','%d','%d','%d'], ['%d','%d']);
        }
    }
}
