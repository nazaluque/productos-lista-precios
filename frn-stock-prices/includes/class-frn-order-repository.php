<?php

if (!defined('ABSPATH')) { exit; }

final class FRN_Order_Repository
{
    public static function customers_table(): string { global $wpdb; return $wpdb->prefix . 'frn_customers'; }
    public static function orders_table(): string { global $wpdb; return $wpdb->prefix . 'frn_orders'; }
    public static function items_table(): string { global $wpdb; return $wpdb->prefix . 'frn_order_items'; }
    public static function log_table(): string { global $wpdb; return $wpdb->prefix . 'frn_order_log'; }

    public static function create_tables(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();

        dbDelta("CREATE TABLE " . self::customers_table() . " (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            company_name varchar(255) NOT NULL,
            vat_number varchar(80) NOT NULL DEFAULT '',
            contact_name varchar(190) NOT NULL DEFAULT '',
            phone varchar(80) NOT NULL DEFAULT '',
            email varchar(190) NOT NULL DEFAULT '',
            billing_address text NOT NULL,
            billing_postcode varchar(30) NOT NULL DEFAULT '',
            billing_city varchar(120) NOT NULL DEFAULT '',
            billing_province varchar(120) NOT NULL DEFAULT '',
            billing_country varchar(120) NOT NULL DEFAULT 'España',
            delivery_address text NOT NULL,
            delivery_postcode varchar(30) NOT NULL DEFAULT '',
            delivery_city varchar(120) NOT NULL DEFAULT '',
            delivery_province varchar(120) NOT NULL DEFAULT '',
            delivery_country varchar(120) NOT NULL DEFAULT 'España',
            notes text NOT NULL,
            active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            created_by bigint unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY company_name (company_name),
            KEY vat_number (vat_number),
            KEY email (email)
        ) {$charset};");

        dbDelta("CREATE TABLE " . self::orders_table() . " (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            document_number varchar(40) NOT NULL DEFAULT '',
            document_type varchar(20) NOT NULL DEFAULT 'pedido',
            status varchar(20) NOT NULL DEFAULT 'borrador',
            customer_id bigint unsigned NOT NULL DEFAULT 0,
            customer_company varchar(255) NOT NULL DEFAULT '',
            customer_vat varchar(80) NOT NULL DEFAULT '',
            customer_contact varchar(190) NOT NULL DEFAULT '',
            customer_phone varchar(80) NOT NULL DEFAULT '',
            customer_email varchar(190) NOT NULL DEFAULT '',
            billing_address text NOT NULL,
            delivery_address text NOT NULL,
            requested_date date NULL,
            delivery_notes text NOT NULL,
            notes text NOT NULL,
            currency varchar(10) NOT NULL DEFAULT 'EUR',
            subtotal decimal(14,2) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            created_by bigint unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY document_number (document_number),
            KEY customer_id (customer_id),
            KEY document_type (document_type),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset};");

        dbDelta("CREATE TABLE " . self::items_table() . " (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint unsigned NOT NULL,
            product_id bigint unsigned NOT NULL DEFAULT 0,
            product_code varchar(80) NOT NULL DEFAULT '',
            brand varchar(160) NOT NULL DEFAULT '',
            product_name varchar(255) NOT NULL DEFAULT '',
            source_unit varchar(40) NOT NULL DEFAULT '',
            sale_unit varchar(20) NOT NULL DEFAULT 'kg',
            quantity decimal(14,3) NOT NULL DEFAULT 0,
            boxes decimal(14,3) NOT NULL DEFAULT 0,
            pallets decimal(14,3) NOT NULL DEFAULT 0,
            total_kg decimal(14,3) NOT NULL DEFAULT 0,
            unit_price decimal(12,4) NOT NULL DEFAULT 0,
            line_total decimal(14,2) NOT NULL DEFAULT 0,
            line_notes text NOT NULL,
            sort_order int NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY product_id (product_id),
            KEY product_code (product_code)
        ) {$charset};");

        dbDelta("CREATE TABLE " . self::log_table() . " (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint unsigned NOT NULL,
            event_type varchar(40) NOT NULL,
            event_text text NOT NULL,
            created_at datetime NOT NULL,
            created_by bigint unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY created_at (created_at)
        ) {$charset};");
    }

    public function all_customers(bool $activeOnly = true): array
    {
        global $wpdb;
        $where = $activeOnly ? ' WHERE active = 1' : '';
        return $wpdb->get_results(
            'SELECT * FROM ' . self::customers_table() . $where . ' ORDER BY company_name ASC, id DESC',
            ARRAY_A
        ) ?: [];
    }

    public function get_customer(int $id): ?array
    {
        global $wpdb;
        if ($id <= 0) { return null; }
        return $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . self::customers_table() . ' WHERE id = %d', $id),
            ARRAY_A
        ) ?: null;
    }

    public function save_customer(array $data, int $id = 0): int
    {
        global $wpdb;
        $now = current_time('mysql');
        $row = [
            'company_name' => (string)($data['company_name'] ?? ''),
            'vat_number' => (string)($data['vat_number'] ?? ''),
            'contact_name' => (string)($data['contact_name'] ?? ''),
            'phone' => (string)($data['phone'] ?? ''),
            'email' => (string)($data['email'] ?? ''),
            'billing_address' => (string)($data['billing_address'] ?? ''),
            'billing_postcode' => (string)($data['billing_postcode'] ?? ''),
            'billing_city' => (string)($data['billing_city'] ?? ''),
            'billing_province' => (string)($data['billing_province'] ?? ''),
            'billing_country' => (string)($data['billing_country'] ?? 'España'),
            'delivery_address' => (string)($data['delivery_address'] ?? ''),
            'delivery_postcode' => (string)($data['delivery_postcode'] ?? ''),
            'delivery_city' => (string)($data['delivery_city'] ?? ''),
            'delivery_province' => (string)($data['delivery_province'] ?? ''),
            'delivery_country' => (string)($data['delivery_country'] ?? 'España'),
            'notes' => (string)($data['notes'] ?? ''),
            'active' => !empty($data['active']) ? 1 : 0,
            'updated_at' => $now,
        ];

        if ($id > 0) {
            $ok = $wpdb->update(self::customers_table(), $row, ['id'=>$id]);
            if ($ok === false) { throw new RuntimeException($wpdb->last_error ?: 'No se pudo guardar el cliente.'); }
            return $id;
        }

        $row['created_at'] = $now;
        $row['created_by'] = get_current_user_id();
        $ok = $wpdb->insert(self::customers_table(), $row);
        if ($ok === false) { throw new RuntimeException($wpdb->last_error ?: 'No se pudo crear el cliente.'); }
        return (int)$wpdb->insert_id;
    }

    public function all_orders(int $limit = 200): array
    {
        global $wpdb;
        $limit = max(1, min(500, $limit));
        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . self::orders_table() . ' ORDER BY created_at DESC, id DESC LIMIT %d',
                $limit
            ),
            ARRAY_A
        ) ?: [];
    }

    public function get_order(int $id): ?array
    {
        global $wpdb;
        if ($id <= 0) { return null; }
        return $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . self::orders_table() . ' WHERE id = %d', $id),
            ARRAY_A
        ) ?: null;
    }

    public function get_items(int $orderId): array
    {
        global $wpdb;
        if ($orderId <= 0) { return []; }
        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . self::items_table() . ' WHERE order_id = %d ORDER BY sort_order ASC, id ASC',
                $orderId
            ),
            ARRAY_A
        ) ?: [];
    }

    public function save_order(array $header, array $items, int $orderId = 0): int
    {
        global $wpdb;
        $orders = self::orders_table();
        $lines = self::items_table();
        $now = current_time('mysql');

        $isNew = $orderId <= 0;
        $wpdb->query('START TRANSACTION');
        try {
            $subtotal = 0.0;
            foreach ($items as $item) { $subtotal += (float)($item['line_total'] ?? 0); }

            $row = [
                'document_type' => (string)($header['document_type'] ?? 'pedido'),
                'status' => (string)($header['status'] ?? 'borrador'),
                'customer_id' => (int)($header['customer_id'] ?? 0),
                'customer_company' => (string)($header['customer_company'] ?? ''),
                'customer_vat' => (string)($header['customer_vat'] ?? ''),
                'customer_contact' => (string)($header['customer_contact'] ?? ''),
                'customer_phone' => (string)($header['customer_phone'] ?? ''),
                'customer_email' => (string)($header['customer_email'] ?? ''),
                'billing_address' => (string)($header['billing_address'] ?? ''),
                'delivery_address' => (string)($header['delivery_address'] ?? ''),
                'requested_date' => !empty($header['requested_date']) ? (string)$header['requested_date'] : null,
                'delivery_notes' => (string)($header['delivery_notes'] ?? ''),
                'notes' => (string)($header['notes'] ?? ''),
                'currency' => 'EUR',
                'subtotal' => round($subtotal, 2),
                'updated_at' => $now,
            ];

            if ($orderId > 0) {
                $ok = $wpdb->update($orders, $row, ['id'=>$orderId]);
                if ($ok === false) { throw new RuntimeException($wpdb->last_error ?: 'No se pudo actualizar el pedido.'); }
                $wpdb->delete($lines, ['order_id'=>$orderId], ['%d']);
            } else {
                $row['created_at'] = $now;
                $row['created_by'] = get_current_user_id();
                $row['document_number'] = '';
                $ok = $wpdb->insert($orders, $row);
                if ($ok === false) { throw new RuntimeException($wpdb->last_error ?: 'No se pudo crear el pedido.'); }
                $orderId = (int)$wpdb->insert_id;
                $prefix = $row['document_type'] === 'proforma' ? 'PRO' : 'PED';
                $number = sprintf('%s-%s-%06d', $prefix, wp_date('Y'), $orderId);
                $wpdb->update($orders, ['document_number'=>$number], ['id'=>$orderId], ['%s'], ['%d']);
            }

            foreach ($items as $index => $item) {
                $line = [
                    'order_id' => $orderId,
                    'product_id' => (int)($item['product_id'] ?? 0),
                    'product_code' => (string)($item['product_code'] ?? ''),
                    'brand' => (string)($item['brand'] ?? ''),
                    'product_name' => (string)($item['product_name'] ?? ''),
                    'source_unit' => (string)($item['source_unit'] ?? ''),
                    'sale_unit' => (string)($item['sale_unit'] ?? 'kg'),
                    'quantity' => (float)($item['quantity'] ?? 0),
                    'boxes' => (float)($item['boxes'] ?? 0),
                    'pallets' => (float)($item['pallets'] ?? 0),
                    'total_kg' => (float)($item['total_kg'] ?? 0),
                    'unit_price' => (float)($item['unit_price'] ?? 0),
                    'line_total' => (float)($item['line_total'] ?? 0),
                    'line_notes' => (string)($item['line_notes'] ?? ''),
                    'sort_order' => (int)$index,
                ];
                $ok = $wpdb->insert($lines, $line);
                if ($ok === false) { throw new RuntimeException($wpdb->last_error ?: 'No se pudo guardar una línea del pedido.'); }
            }

            $this->log($orderId, $isNew ? 'creado' : 'guardado', $isNew ? 'Documento creado.' : 'Documento guardado.');
            $wpdb->query('COMMIT');
            return $orderId;
        } catch (Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }

    public function update_status(int $orderId, string $status): void
    {
        global $wpdb;
        if ($orderId <= 0) { return; }
        $allowed = ['borrador','listo','enviado','anulado'];
        if (!in_array($status, $allowed, true)) { return; }

        $wpdb->update(
            self::orders_table(),
            ['status'=>$status,'updated_at'=>current_time('mysql')],
            ['id'=>$orderId],
            ['%s','%s'],
            ['%d']
        );
    }

    public function log(int $orderId, string $event, string $text): void
    {
        global $wpdb;
        if ($orderId <= 0) { return; }
        $wpdb->insert(self::log_table(), [
            'order_id'=>$orderId,
            'event_type'=>$event,
            'event_text'=>$text,
            'created_at'=>current_time('mysql'),
            'created_by'=>get_current_user_id(),
        ], ['%d','%s','%s','%s','%d']);
    }
}
