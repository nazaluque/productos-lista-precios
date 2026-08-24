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
            source_file varchar(255) NOT NULL DEFAULT '',
            published_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY category_code (category, product_code),
            KEY category_name (category, product_name)
        ) {$charset};");
    }

    public function all(string $category): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE category = %s ORDER BY featured DESC, product_name ASC', $category), ARRAY_A) ?: [];
    }

    public function publish(string $category, string $filename, array $rows): int
    {
        global $wpdb;
        $table = self::table();
        $wpdb->query('START TRANSACTION');
        try {
            $wpdb->delete($table, ['category' => $category], ['%s']);
            foreach ($rows as $row) {
                $wpdb->insert($table, [
                    'category' => $category,
                    'product_code' => $row['code'],
                    'brand' => $row['brand'],
                    'product_name' => $row['name'],
                    'stock_kg' => $row['stock'],
                    'price_kg' => $row['price'],
                    'featured' => $row['featured'] ? 1 : 0,
                    'source_file' => sanitize_file_name($filename),
                    'published_at' => current_time('mysql'),
                ], ['%s','%s','%s','%s','%f','%f','%d','%s','%s']);
                if ($wpdb->last_error) { throw new RuntimeException($wpdb->last_error); }
            }
            $wpdb->query('COMMIT');
            return count($rows);
        } catch (Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    public function update_many(string $category, array $rows): int
    {
        global $wpdb;
        $updated = 0;
        foreach ($rows as $row) {
            $result = $wpdb->update(
                self::table(),
                [
                    'product_code' => $row['code'],
                    'brand' => $row['brand'],
                    'product_name' => $row['name'],
                    'stock_kg' => $row['stock'],
                    'price_kg' => $row['price'],
                    'published_at' => current_time('mysql'),
                ],
                ['id' => $row['id'], 'category' => $category],
                ['%s','%s','%s','%f','%f','%s'],
                ['%d','%s']
            );
            if ($result === false) { throw new RuntimeException($wpdb->last_error ?: 'No se pudo guardar el producto.'); }
            $updated += (int) $result;
        }
        return $updated;
    }
}