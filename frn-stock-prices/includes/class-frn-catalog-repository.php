<?php
if (!defined('ABSPATH')) { exit; }

final class FRN_Catalog_Repository
{
    public static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'frn_catalog_products';
    }

    public static function history_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'frn_product_history';
    }

    public static function create_table(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $table = self::table();
        $history = self::history_table();

        dbDelta("CREATE TABLE {$table} (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            category varchar(40) NOT NULL,
            product_code varchar(80) NOT NULL DEFAULT '',
            brand varchar(160) NOT NULL DEFAULT '',
            product_name varchar(255) NOT NULL,
            model varchar(160) NOT NULL DEFAULT '',
            unit varchar(40) NOT NULL DEFAULT '',
            stock_kg decimal(14,3) NOT NULL DEFAULT 0,
            source_price_kg decimal(12,2) NULL,
            price_kg decimal(12,2) NULL,
            average_cost_kg decimal(12,2) NULL,
            featured tinyint(1) NOT NULL DEFAULT 0,
            visible tinyint(1) NOT NULL DEFAULT 1,
            incoming tinyint(1) NOT NULL DEFAULT 0,
            source_file varchar(255) NOT NULL DEFAULT '',
            published_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY category_code (category, product_code),
            KEY category_name (category, product_name),
            KEY incoming (incoming)
        ) {$charset};");

        dbDelta("CREATE TABLE {$history} (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint unsigned NOT NULL,
            category varchar(40) NOT NULL,
            product_code varchar(80) NOT NULL DEFAULT '',
            stock_kg decimal(14,3) NOT NULL DEFAULT 0,
            source_price_kg decimal(12,2) NULL,
            commercial_price_kg decimal(12,2) NULL,
            average_cost_kg decimal(12,2) NULL,
            source_file varchar(255) NOT NULL DEFAULT '',
            imported_at datetime NOT NULL,
            imported_by bigint unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY product_date (product_id, imported_at),
            KEY code_date (product_code, imported_at)
        ) {$charset};");
    }

    public function all(string $category, bool $include_hidden = false): array
    {
        global $wpdb;
        $visibility = $include_hidden ? '' : ' AND visible = 1';
        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . self::table() . ' WHERE category = %s' . $visibility .
                ' ORDER BY incoming ASC, product_name ASC, brand ASC, product_code ASC',
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
            'SELECT * FROM ' . self::table() . $visibility .
            ' ORDER BY category ASC, incoming ASC, product_name ASC, brand ASC, product_code ASC',
            ARRAY_A
        ) ?: [];
    }

    /**
     * Unified weekly import. Only stock-positive regular rows reach this
     * method. Existing products not present in the weekly file are kept in
     * the master with stock 0 / hidden. Manual commercial prices are
     * preserved; source price and average cost are refreshed from Odoo.
     */
    public function publish_stock(string $category, string $filename, array $rows): int
    {
        global $wpdb;
        $table = self::table();
        $history = self::history_table();
        $now = current_time('mysql');
        $userId = get_current_user_id();

        $wpdb->query('START TRANSACTION');

        try {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table}
                     SET stock_kg = 0, visible = 0
                     WHERE category = %s AND incoming = 0",
                    $category
                )
            );

            $count = 0;

            foreach ($rows as $row) {
                $incoming = !empty($row['incoming']) ||
                    FRN_Excel_Importer::is_incoming_code((string) ($row['code'] ?? ''));

                $stock = (float) ($row['stock'] ?? 0);
                if (!$incoming && $stock <= 0) { continue; }

                $sourcePrice = max(0, (float) ($row['price'] ?? 0));
                $averageCost = max(0, (float) ($row['cost'] ?? 0));
                $visible = $incoming ? (!empty($row['publish']) ? 1 : 0) : 1;

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
                    'model' => sanitize_text_field((string) ($row['model'] ?? '')),
                    'unit' => sanitize_text_field((string) ($row['unit'] ?? '')),
                    'stock_kg' => $stock,
                    'source_price_kg' => $sourcePrice,
                    'average_cost_kg' => $averageCost,
                    'visible' => $visible,
                    'incoming' => $incoming ? 1 : 0,
                    'source_file' => sanitize_file_name($filename),
                    'published_at' => $now,
                ];

                if ($existingId > 0) {
                    $existing = $wpdb->get_row(
                        $wpdb->prepare("SELECT price_kg FROM {$table} WHERE id = %d", $existingId),
                        ARRAY_A
                    );
                    if ((float) ($existing['price_kg'] ?? 0) <= 0 && $sourcePrice > 0) {
                        $data['price_kg'] = $sourcePrice;
                    }

                    $formats = [];
                    foreach ($data as $key => $value) {
                        $formats[] = in_array($key, ['visible','incoming'], true)
                            ? '%d'
                            : (in_array($key, ['stock_kg','source_price_kg','average_cost_kg','price_kg'], true) ? '%f' : '%s');
                    }
                    $result = $wpdb->update($table, $data, ['id' => $existingId], $formats, ['%d']);
                    $productId = $existingId;
                } else {
                    $data['price_kg'] = $sourcePrice;
                    $data['featured'] = 0;
                    $formats = ['%s','%s','%s','%s','%s','%s','%f','%f','%f','%d','%d','%s','%s','%f','%d'];
                    $result = $wpdb->insert($table, $data, $formats);
                    $productId = (int) $wpdb->insert_id;
                }

                if ($result === false || $wpdb->last_error) {
                    throw new RuntimeException($wpdb->last_error ?: 'No se pudo actualizar el catálogo semanal.');
                }

                $commercialPrice = (float) $wpdb->get_var(
                    $wpdb->prepare("SELECT price_kg FROM {$table} WHERE id = %d", $productId)
                );

                $ok = $wpdb->insert($history, [
                    'product_id' => $productId,
                    'category' => $category,
                    'product_code' => sanitize_text_field((string) ($row['code'] ?? '')),
                    'stock_kg' => $stock,
                    'source_price_kg' => $sourcePrice,
                    'commercial_price_kg' => $commercialPrice,
                    'average_cost_kg' => $averageCost,
                    'source_file' => sanitize_file_name($filename),
                    'imported_at' => $now,
                    'imported_by' => $userId,
                ], ['%d','%s','%s','%f','%f','%f','%f','%s','%s','%d']);

                if ($ok === false || $wpdb->last_error) {
                    throw new RuntimeException($wpdb->last_error ?: 'No se pudo guardar el histórico semanal.');
                }

                $count++;
            }

            $wpdb->query('COMMIT');
            return $count;
        } catch (Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }

    public function update_many(array $rows, bool $canEditStock = false, bool $canEditPrices = false): int
    {
        global $wpdb;
        $updated = 0;

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) { continue; }

            $data = [
                'featured' => !empty($row['featured']) ? 1 : 0,
                'visible' => !empty($row['visible']) ? 1 : 0,
                'published_at' => current_time('mysql'),
            ];

            if ($canEditStock) {
                $data['product_code'] = sanitize_text_field((string) ($row['code'] ?? ''));
                $data['brand'] = sanitize_text_field((string) ($row['brand'] ?? ''));
                $data['product_name'] = sanitize_text_field((string) ($row['name'] ?? ''));
                $data['stock_kg'] = max(0, (float) ($row['stock'] ?? 0));
                $data['incoming'] = FRN_Excel_Importer::is_incoming_code((string) ($row['code'] ?? '')) ? 1 : 0;
            }

            if ($canEditPrices) {
                $data['price_kg'] = max(0, (float) ($row['price'] ?? 0));
            }

            $formats = [];
            foreach ($data as $key => $value) {
                $formats[] = in_array($key, ['featured','visible','incoming'], true)
                    ? '%d'
                    : (in_array($key, ['stock_kg','price_kg'], true) ? '%f' : '%s');
            }

            $result = $wpdb->update(self::table(), $data, ['id' => $id], $formats, ['%d']);
            if ($result === false) {
                throw new RuntimeException($wpdb->last_error ?: 'No se pudo guardar el producto.');
            }
            $updated += (int) $result;
        }

        return $updated;
    }

    public function latest_import_meta(): array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            'SELECT source_file, imported_at, imported_by
             FROM ' . self::history_table() . '
             ORDER BY imported_at DESC, id DESC
             LIMIT 1',
            ARRAY_A
        ) ?: [];

        if (!$row) {
            return [];
        }

        $userName = '';
        $userId = (int) ($row['imported_by'] ?? 0);
        if ($userId > 0) {
            $user = get_user_by('id', $userId);
            if ($user) {
                $userName = (string) ($user->display_name ?: $user->user_login);
            }
        }

        $activeCount = (int) $wpdb->get_var(
            'SELECT COUNT(*) FROM ' . self::table() . ' WHERE visible = 1'
        );

        return [
            'source_file' => (string) ($row['source_file'] ?? ''),
            'imported_at' => (string) ($row['imported_at'] ?? ''),
            'imported_by' => $userId,
            'user_name' => $userName,
            'active_count' => $activeCount,
        ];
    }

    public function history_for_product(int $productId, int $limit = 30): array
    {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . self::history_table() .
                ' WHERE product_id = %d ORDER BY imported_at DESC, id DESC LIMIT %d',
                $productId,
                max(1, $limit)
            ),
            ARRAY_A
        ) ?: [];
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
                    $category, $code, $name
                )
            );
        }

        if ($code !== '') {
            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$table}
                     WHERE category = %s AND UPPER(product_code) = UPPER(%s)
                     ORDER BY id DESC LIMIT 1",
                    $category, $code
                )
            );
        }

        if ($name !== '') {
            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$table}
                     WHERE category = %s AND product_name = %s
                     ORDER BY id DESC LIMIT 1",
                    $category, $name
                )
            );
        }

        return 0;
    }
}
