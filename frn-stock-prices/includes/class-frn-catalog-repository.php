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

    /**
     * Weekly stock snapshot.
     * Products missing from the new stock file remain in the master catalogue,
     * but normal products are reset to stock 0 / unchecked.
     */
    public function publish_stock(string $category, string $filename, array $rows): int
    {
        global $wpdb;
        $table = self::table();
        $now = current_time('mysql');

        $wpdb->query('START TRANSACTION');

        try {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table}
                     SET stock_kg = 0, visible = 0, source_file = %s, published_at = %s
                     WHERE category = %s AND incoming = 0",
                    sanitize_file_name($filename),
                    $now,
                    $category
                )
            );

            foreach ($rows as $row) {
                $incoming = !empty($row['incoming']) ||
                    FRN_Excel_Importer::is_incoming_code((string) ($row['code'] ?? ''));

                $stock = (float) ($row['stock'] ?? 0);
                $visible = $incoming
                    ? (!empty($row['publish']) ? 1 : 0)
                    : (!empty($row['publish']) && $stock > 0 ? 1 : 0);

                $existingId = $this->find_existing_id(
                    $category,
                    (string) ($row['code'] ?? ''),
                    (string) ($row['name'] ?? ''),
                    $incoming
                );

                $data = [
                    'category' => $category,
                    'product_code' => sanitize_text_field((string) ($row['code'] ?? '')),
                    'brand' => sanitize_text_field((string) ($row['brand'] ?? '')),
                    'product_name' => sanitize_text_field((string) ($row['name'] ?? '')),
                    'stock_kg' => $stock,
                    'visible' => $visible,
                    'incoming' => $incoming ? 1 : 0,
                    'source_file' => sanitize_file_name($filename),
                    'published_at' => $now,
                ];

                if ($existingId > 0) {
                    // Preserve commercial-only fields (legacy price / offer) here.
                    $result = $wpdb->update(
                        $table,
                        $data,
                        ['id' => $existingId],
                        ['%s','%s','%s','%s','%f','%d','%d','%s','%s'],
                        ['%d']
                    );
                } else {
                    $data['price_kg'] = 0;
                    $data['featured'] = 0;
                    $result = $wpdb->insert(
                        $table,
                        $data,
                        ['%s','%s','%s','%s','%f','%d','%d','%s','%s','%f','%d']
                    );
                }

                if ($result === false || $wpdb->last_error) {
                    throw new RuntimeException($wpdb->last_error ?: 'No se pudo actualizar el stock.');
                }
            }

            $wpdb->query('COMMIT');
            return count($rows);
        } catch (Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }

    /**
     * Ensures products referenced by a commercial price list exist in the
     * product master without altering live stock for regular products.
     */
    public function ensure_from_price_rows(string $category, string $filename, array $rows): int
    {
        global $wpdb;
        $table = self::table();
        $now = current_time('mysql');
        $touched = 0;

        $wpdb->query('START TRANSACTION');

        try {
            foreach ($rows as $row) {
                $code = sanitize_text_field((string) ($row['code'] ?? ''));
                $name = sanitize_text_field((string) ($row['name'] ?? ''));
                $brand = sanitize_text_field((string) ($row['brand'] ?? ''));
                $incoming = !empty($row['incoming']) || FRN_Excel_Importer::is_incoming_code($code);

                $existingId = $this->find_existing_id($category, $code, $name, $incoming);

                if ($existingId > 0) {
                    $update = [
                        'incoming' => $incoming ? 1 : 0,
                    ];

                    if ($name !== '') { $update['product_name'] = $name; }
                    if ($brand !== '') { $update['brand'] = $brand; }

                    if ($incoming) {
                        $update['visible'] = !empty($row['publish']) ? 1 : 0;
                        $update['source_file'] = sanitize_file_name($filename);
                        $update['published_at'] = $now;
                    }

                    $formats = [];
                    foreach ($update as $key => $value) {
                        $formats[] = in_array($key, ['incoming','visible'], true) ? '%d' : '%s';
                    }

                    $result = $wpdb->update(
                        $table,
                        $update,
                        ['id' => $existingId],
                        $formats,
                        ['%d']
                    );

                    if ($result === false || $wpdb->last_error) {
                        throw new RuntimeException($wpdb->last_error ?: 'No se pudo actualizar el maestro de productos.');
                    }

                    $touched++;
                    continue;
                }

                $productName = $name !== '' ? $name : ($code !== '' ? $code : 'Producto sin nombre');

                $result = $wpdb->insert($table, [
                    'category' => $category,
                    'product_code' => $code,
                    'brand' => $brand,
                    'product_name' => $productName,
                    'stock_kg' => 0,
                    'price_kg' => 0,
                    'featured' => 0,
                    'visible' => $incoming && !empty($row['publish']) ? 1 : 0,
                    'incoming' => $incoming ? 1 : 0,
                    'source_file' => sanitize_file_name($filename),
                    'published_at' => $now,
                ], ['%s','%s','%s','%s','%f','%f','%d','%d','%d','%s','%s']);

                if ($result === false || $wpdb->last_error) {
                    throw new RuntimeException($wpdb->last_error ?: 'No se pudo añadir el producto al maestro.');
                }

                $touched++;
            }

            $wpdb->query('COMMIT');
            return $touched;
        } catch (Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }

    /**
     * Legacy entry point retained for older installs.
     */
    public function publish(string $category, string $filename, array $rows): int
    {
        return $this->publish_stock($category, $filename, $rows);
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

            $data = [
                'product_code' => $code,
                'brand' => sanitize_text_field((string) ($row['brand'] ?? '')),
                'product_name' => sanitize_text_field((string) ($row['name'] ?? '')),
                'stock_kg' => (float) ($row['stock'] ?? 0),
                'featured' => !empty($row['featured']) ? 1 : 0,
                'visible' => !empty($row['visible']) ? 1 : 0,
                'incoming' => $incoming ? 1 : 0,
                'published_at' => current_time('mysql'),
            ];

            if (array_key_exists('price', $row)) {
                $data['price_kg'] = max(0, (float) $row['price']);
            }

            $formats = [];
            foreach ($data as $key => $value) {
                $formats[] = in_array($key, ['featured','visible','incoming'], true)
                    ? '%d'
                    : (in_array($key, ['stock_kg','price_kg'], true) ? '%f' : '%s');
            }

            $result = $wpdb->update(
                self::table(),
                $data,
                ['id' => $id],
                $formats,
                ['%d']
            );

            if ($result === false) {
                throw new RuntimeException($wpdb->last_error ?: 'No se pudo guardar el producto.');
            }

            $updated += (int) $result;
        }

        return $updated;
    }

    private function find_existing_id(string $category, string $code, string $name, bool $incoming): int
    {
        global $wpdb;
        $table = self::table();

        $code = trim($code);
        $name = trim($name);

        if ($incoming) {
            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$table}
                     WHERE category = %s AND UPPER(product_code) = UPPER(%s) AND product_name = %s
                     ORDER BY id DESC LIMIT 1",
                    $category,
                    $code,
                    $name
                )
            );
        }

        if ($code !== '') {
            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$table}
                     WHERE category = %s AND UPPER(product_code) = UPPER(%s)
                     ORDER BY id DESC LIMIT 1",
                    $category,
                    $code
                )
            );
        }

        if ($name !== '') {
            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$table}
                     WHERE category = %s AND product_name = %s
                     ORDER BY id DESC LIMIT 1",
                    $category,
                    $name
                )
            );
        }

        return 0;
    }
}
