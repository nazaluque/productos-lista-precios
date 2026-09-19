<?php

if (!defined('ABSPATH')) { exit; }

final class FRN_Price_List_Repository
{
    public static function lists_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'frn_price_lists';
    }

    public static function items_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'frn_price_list_items';
    }

    public static function create_tables(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $lists = self::lists_table();
        $items = self::items_table();

        dbDelta("CREATE TABLE {$lists} (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            source_file varchar(255) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY created_at (created_at)
        ) {$charset};");

        dbDelta("CREATE TABLE {$items} (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            price_list_id bigint unsigned NOT NULL,
            category varchar(40) NOT NULL,
            product_code varchar(80) NOT NULL DEFAULT '',
            brand varchar(160) NOT NULL DEFAULT '',
            product_name varchar(255) NOT NULL DEFAULT '',
            price_kg decimal(12,2) NULL,
            featured tinyint(1) NOT NULL DEFAULT 0,
            visible tinyint(1) NOT NULL DEFAULT 1,
            sort_order int NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY price_list_id (price_list_id),
            KEY list_category (price_list_id, category),
            KEY list_code (price_list_id, product_code)
        ) {$charset};");
    }

    public function create(string $name, string $filename, array $catalogs): int
    {
        global $wpdb;

        $name = trim(sanitize_text_field($name));
        if ($name === '') {
            throw new RuntimeException('Indica un nombre para la tarifa de precios.');
        }

        $rows = [];
        foreach (['carne','pescado-marisco'] as $category) {
            foreach (($catalogs[$category] ?? []) as $row) {
                $rows[] = [$category, $row];
            }
        }

        if (!$rows) {
            throw new RuntimeException('El archivo de tarifas no contiene precios reconocibles.');
        }

        $now = current_time('mysql');
        $wpdb->query('START TRANSACTION');

        try {
            $ok = $wpdb->insert(self::lists_table(), [
                'name' => $name,
                'source_file' => sanitize_file_name($filename),
                'created_at' => $now,
                'updated_at' => $now,
            ], ['%s','%s','%s','%s']);

            if (!$ok) {
                throw new RuntimeException($wpdb->last_error ?: 'No se pudo crear la tarifa de precios.');
            }

            $listId = (int) $wpdb->insert_id;
            $order = 0;

            foreach ($rows as [$category, $row]) {
                $order++;
                $price = isset($row['price']) ? (float) $row['price'] : 0.0;
                $incoming = FRN_Excel_Importer::is_incoming_code((string) ($row['code'] ?? ''));

                $wpdb->insert(self::items_table(), [
                    'price_list_id' => $listId,
                    'category' => $category,
                    'product_code' => sanitize_text_field((string) ($row['code'] ?? '')),
                    'brand' => sanitize_text_field((string) ($row['brand'] ?? '')),
                    'product_name' => sanitize_text_field((string) ($row['name'] ?? '')),
                    'price_kg' => $price > 0 ? $price : 0,
                    'featured' => !empty($row['featured']) ? 1 : 0,
                    'visible' => (!empty($row['publish']) || $incoming) ? 1 : 0,
                    'sort_order' => $order,
                ], ['%d','%s','%s','%s','%s','%f','%d','%d','%d']);

                if ($wpdb->last_error) {
                    throw new RuntimeException($wpdb->last_error);
                }
            }

            $wpdb->query('COMMIT');
            return $listId;
        } catch (Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }

    public function all(): array
    {
        global $wpdb;

        return $wpdb->get_results(
            'SELECT l.*, COUNT(i.id) AS item_count
             FROM ' . self::lists_table() . ' l
             LEFT JOIN ' . self::items_table() . ' i ON i.price_list_id = l.id
             GROUP BY l.id
             ORDER BY l.created_at DESC, l.id DESC',
            ARRAY_A
        ) ?: [];
    }

    public function get(int $id): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . self::lists_table() . ' WHERE id = %d', $id),
            ARRAY_A
        );

        return $row ?: null;
    }

    public function items(int $listId, ?string $category = null): array
    {
        global $wpdb;

        if ($listId <= 0) { return []; }

        if ($category) {
            return $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT * FROM ' . self::items_table() . ' WHERE price_list_id = %d AND category = %s ORDER BY featured DESC, sort_order ASC, product_name ASC',
                    $listId,
                    $category
                ),
                ARRAY_A
            ) ?: [];
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . self::items_table() . ' WHERE price_list_id = %d ORDER BY category ASC, featured DESC, sort_order ASC, product_name ASC',
                $listId
            ),
            ARRAY_A
        ) ?: [];
    }

    public function item_map(int $listId, string $category): array
    {
        $map = [];

        foreach ($this->items($listId, $category) as $item) {
            $key = self::match_key(
                (string) $item['category'],
                (string) $item['product_code'],
                (string) $item['product_name']
            );
            $map[$key] = $item;
        }

        return $map;
    }

    public static function match_key(string $category, string $code, string $name = ''): string
    {
        $code = strtoupper(trim($code));
        $base = strtolower(trim($category)) . '|' . $code;

        if (FRN_Excel_Importer::is_incoming_code($code)) {
            $normalizedName = strtolower(remove_accents(trim($name)));
            $normalizedName = preg_replace('/\s+/', ' ', $normalizedName);
            return $base . '|' . $normalizedName;
        }

        return $base;
    }
}
