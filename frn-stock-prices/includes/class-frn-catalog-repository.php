<?php

if (!defined('ABSPATH')) { exit; }

final class FRN_Catalog_Repository
{
    public static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'frn_catalog_products';
    }

    public static function create_table(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $table = self::table();

        dbDelta("CREATE TABLE {$table} (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            category varchar(40) NOT NULL,
            product_code varchar(80) NOT NULL DEFAULT '',
            brand varchar(160) NOT NULL DEFAULT '',
            product_name varchar(255) NOT NULL,
            stock_kg decimal(14,2) NOT NULL DEFAULT 0,
            price_kg decimal(12,2) NULL,
            featured tinyint(1) NOT NULL DEFAULT 0,
            visible tinyint(1) NOT NULL DEFAULT 1,
            incoming tinyint(1) NOT NULL DEFAULT 0,
            source_file varchar(255) NOT NULL DEFAULT '',
            published_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY category_code (category, product_code),
            KEY category_name (category, product_name),
            KEY incoming (incoming)
        ) {$charset};");

        $indexes = $wpdb->get_results("SHOW INDEX FROM {$table}", ARRAY_A) ?: [];
        foreach ($indexes as $index) {
            if (($index['Key_name'] ?? '') === 'category_code' && (int) ($index['Non_unique'] ?? 1) === 0) {
                $wpdb->query("ALTER TABLE {$table} DROP INDEX category_code");
                $wpdb->query("ALTER TABLE {$table} ADD KEY category_code (category, product_code)");
                break;
            }
        }
    }

    public function all(string $category, bool $include_hidden = false): array
    {
        global $wpdb;
        $visibility = $include_hidden ? '' : ' AND visible = 1';

        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . self::table() . ' WHERE category = %s' . $visibility . ' ORDER BY incoming ASC, featured DESC, product_name ASC',
                $category
            ),
            ARRAY_A
        ) ?: [];
    }

    public function all_combined(bool $include_hidden = true): array
    {
        global $wpdb;
        $visibility = $include_hidden ? '' : ' WHERE visible = 1';

        return $wpdb->get_results(
            'SELECT * FROM ' . self::table() . $visibility . ' ORDER BY incoming ASC, category ASC, featured DESC, product_name ASC',
            ARRAY_A
        ) ?: [];
    }

    public function publish(string $category, string $filename, array $rows): int
    {
        global $wpdb;
        $table = self::table();

        $wpdb->query('START TRANSACTION');

        try {
            $wpdb->delete($table, ['category' => $category], ['%s']);

            foreach ($rows as $row) {
                $incoming = !empty($row['incoming']) || FRN_Excel_Importer::is_incoming_code((string) ($row['code'] ?? ''));

                $wpdb->insert($table, [
                    'category' => $category,
                    'product_code' => (string) ($row['code'] ?? ''),
                    'brand' => (string) ($row['brand'] ?? ''),
                    'product_name' => (string) ($row['name'] ?? ''),
                    'stock_kg' => (float) ($row['stock'] ?? 0),
                    'price_kg' => (float) ($row['price'] ?? 0),
                    'featured' => !empty($row['featured']) ? 1 : 0,
                    'visible' => !empty($row['publish']) ? 1 : 0,
                    'incoming' => $incoming ? 1 : 0,
                    'source_file' => sanitize_file_name($filename),
                    'published_at' => current_time('mysql'),
                ], ['%s','%s','%s','%s','%f','%f','%d','%d','%d','%s','%s']);

                if ($wpdb->last_error) {
                    throw new RuntimeException($wpdb->last_error);
                }
            }

            $wpdb->query('COMMIT');
            return count($rows);
        } catch (Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    public function update_many(array $rows): int
    {
        global $wpdb;
        $updated = 0;

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) { continue; }

            $code = sanitize_text_field((string) ($row['code'] ?? ''));
            $incoming = FRN_Excel_Importer::is_incoming_code($code);

            $result = $wpdb->update(
                self::table(),
                [
                    'product_code' => $code,
                    'brand' => sanitize_text_field((string) ($row['brand'] ?? '')),
                    'product_name' => sanitize_text_field((string) ($row['name'] ?? '')),
                    'stock_kg' => (float) ($row['stock'] ?? 0),
                    'price_kg' => max(0, (float) ($row['price'] ?? 0)),
                    'featured' => !empty($row['featured']) ? 1 : 0,
                    'visible' => !empty($row['visible']) ? 1 : 0,
                    'incoming' => $incoming ? 1 : 0,
                    'published_at' => current_time('mysql'),
                ],
                ['id' => $id],
                ['%s','%s','%s','%f','%f','%d','%d','%d','%s'],
                ['%d']
            );

            if ($result === false) {
                throw new RuntimeException($wpdb->last_error ?: 'No se pudo guardar el producto.');
            }

            $updated += (int) $result;
        }

        return $updated;
    }
}
