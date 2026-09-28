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

    public static function groups_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'frn_commercial_groups';
    }

    public static function create_table(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $table = self::table();
        $history = self::history_table();
        $groups = self::groups_table();

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
            commercial_group varchar(190) NOT NULL DEFAULT '',
            group_sort int NOT NULL DEFAULT 999,
            item_sort int NOT NULL DEFAULT 999,
            group_color varchar(20) NOT NULL DEFAULT '#59636E',
            name_es_ar varchar(255) NOT NULL DEFAULT '',
            name_es_es varchar(255) NOT NULL DEFAULT '',
            name_pt_pt varchar(255) NOT NULL DEFAULT '',
            name_en varchar(255) NOT NULL DEFAULT '',
            translations_reviewed tinyint(1) NOT NULL DEFAULT 0,
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

        dbDelta("CREATE TABLE {$groups} (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            category varchar(40) NOT NULL,
            group_key varchar(160) NOT NULL,
            name_base varchar(190) NOT NULL,
            name_es_es varchar(190) NOT NULL DEFAULT '',
            name_pt_pt varchar(190) NOT NULL DEFAULT '',
            name_en varchar(190) NOT NULL DEFAULT '',
            color varchar(20) NOT NULL DEFAULT '#59636E',
            sort_order int NOT NULL DEFAULT 999,
            active tinyint(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY category_key (category, group_key),
            KEY category_sort (category, sort_order)
        ) {$charset};");

        self::seed_default_groups();
    }

    private static function seed_default_groups(): void
    {
        global $wpdb;
        $table = self::groups_table();

        $defaults = [
            ['carne','bife-ancho','BIFE ANCHO / TAPA DE BIFE ANCHO','LOMO ALTO / TAPA DE LOMO ALTO','LOMBO ALTO / CAPA DO LOMBO ALTO','RIBEYE / RIBEYE CAP','#17324D',10],
            ['carne','bife-angosto','BIFE ANGOSTO','LOMO BAJO / ENTRECOT','VAZIA','STRIPLOIN','#8A3B3B',20],
            ['carne','lomo','LOMO','SOLOMILLO','LOMBO','TENDERLOIN','#2F6659',30],
            ['carne','cuadril','CUADRIL (TAPA Y CORAZÓN)','CADERA (PICAÑA Y CROCA)','ALCATRA (PICANHA E CORAÇÃO)','RUMP (RUMP CAP & HEART)','#8A6426',40],
            ['carne','asado-costilla','ASADO / COSTILLA','COSTILLAR / COSTILLA','COSTELA / ENTRECOSTO','RIBS / SHORT RIBS','#5B4B8A',50],
            ['carne','hueso-bluesmoke','CORTES CON HUESO BLUESMOKE','CORTES CON HUESO BLUESMOKE','CORTES COM OSSO BLUESMOKE','BLUESMOKE BONE-IN CUTS','#A14E27',60],
            ['carne','vacio','VACÍO','FALDA / VACÍO','FRALDINHA','FLANK / THIN FLANK','#276A78',70],
            ['carne','otros','OTROS CORTES','OTROS CORTES','OUTROS CORTES','OTHER CUTS','#59636E',80],

            ['pescado-marisco','aleta-manto-choco','ALETA / MANTO / CHOCO (ROTACIÓN RÁPIDA)','ALETA / MANTO / CHOCO (ROTACIÓN RÁPIDA)','ASA / MANTO / CHOCO (ROTAÇÃO RÁPIDA)','WINGS / MANTLE / CUTTLEFISH (FAST MOVERS)','#17324D',10],
            ['pescado-marisco','pulpo-visceras','PULPO ENTERO CON VÍSCERAS','PULPO ENTERO CON VÍSCERAS','POLVO INTEIRO COM VÍSCERAS','WHOLE OCTOPUS WITH VISCERA','#8A3B3B',20],
            ['pescado-marisco','pulpo-limpio','PULPO ENTERO LIMPIO','PULPO ENTERO LIMPIO','POLVO INTEIRO LIMPO','WHOLE CLEANED OCTOPUS','#2F6659',30],
            ['pescado-marisco','pulpo-sin-cabeza','PULPO CRUDO SIN CABEZA','PULPO CRUDO SIN CABEZA','POLVO CRU SEM CABEÇA','RAW OCTOPUS WITHOUT HEAD','#8A6426',40],
            ['pescado-marisco','pulpo-cocido','PULPO COCIDO','PULPO COCIDO','POLVO COZIDO','COOKED OCTOPUS','#5B4B8A',50],
            ['pescado-marisco','gambon','GAMBÓN','GAMBÓN','CAMARÃO / GAMBÃO','KING PRAWN','#A14E27',60],
            ['pescado-marisco','vieira','VIEIRA','VIEIRA','VIEIRA','SCALLOP','#276A78',70],
            ['pescado-marisco','vannamei','VANNAMEI','VANNAMEI','VANNAMEI','VANNAMEI SHRIMP','#59636E',80],
            ['pescado-marisco','gamba-blanca','GAMBA BLANCA','GAMBA BLANCA','CAMARÃO BRANCO','WHITE PRAWN','#7A5A72',90],
            ['pescado-marisco','cangrejo','CANGREJO','CANGREJO','CARANGUEJO','CRAB','#3D5A3B',100],
            ['pescado-marisco','bogavante','BOGAVANTE','BOGAVANTE','LAVAGANTE','LOBSTER','#A86F2A',110],
            ['pescado-marisco','otros','OTROS PESCADOS / MARISCOS','OTROS PESCADOS / MARISCOS','OUTROS PEIXES / MARISCOS','OTHER FISH / SEAFOOD','#4E5D6C',120],
        ];

        foreach ($defaults as $row) {
            [$category,$key,$base,$es,$pt,$en,$color,$sort] = $row;
            $exists = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE category = %s AND group_key = %s LIMIT 1",
                $category,
                $key
            ));
            if ($exists > 0) { continue; }

            $wpdb->insert($table, [
                'category'=>$category,
                'group_key'=>$key,
                'name_base'=>$base,
                'name_es_es'=>$es,
                'name_pt_pt'=>$pt,
                'name_en'=>$en,
                'color'=>$color,
                'sort_order'=>$sort,
                'active'=>1,
            ], ['%s','%s','%s','%s','%s','%s','%s','%d','%d']);
        }
    }

    public function all_groups(string $category = ''): array
    {
        global $wpdb;
        $table = self::groups_table();

        if ($category !== '') {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE category = %s AND active = 1 ORDER BY sort_order ASC, name_base ASC",
                    $category
                ),
                ARRAY_A
            ) ?: [];
        }

        $rows = $wpdb->get_results(
            "SELECT * FROM {$table} WHERE active = 1 ORDER BY category ASC, sort_order ASC, name_base ASC",
            ARRAY_A
        ) ?: [];

        $out = ['carne'=>[], 'pescado-marisco'=>[]];
        foreach ($rows as $row) {
            $cat = (string)$row['category'];
            if (isset($out[$cat])) { $out[$cat][] = $row; }
        }
        return $out;
    }

    public function group_by_name(string $category, string $name): ?array
    {
        global $wpdb;
        $table = self::groups_table();
        $normalized = self::normalize_group_name($name);
        if ($normalized === '') { return null; }

        $groups = $this->all_groups($category);
        foreach ($groups as $group) {
            if (self::normalize_group_name((string)$group['name_base']) === $normalized) {
                return $group;
            }
        }
        return null;
    }

    public function group_label(string $category, string $name, string $locale): string
    {
        $group = $this->group_by_name($category, $name);
        if (!$group) { return $name; }

        $field = match ($locale) {
            'es_es' => 'name_es_es',
            'pt_pt' => 'name_pt_pt',
            'en' => 'name_en',
            default => 'name_base',
        };
        $value = trim((string)($group[$field] ?? ''));
        return $value !== '' ? $value : (string)$group['name_base'];
    }

    public function group_meta(string $category, string $name): ?array
    {
        return $this->group_by_name($category, $name);
    }

    public function find_product(string $category, string $code, string $name = '', bool $incoming = false): ?array
    {
        global $wpdb;
        $id = $this->find_existing_id($category, $code, $name, $incoming);
        if ($id <= 0) { return null; }
        return $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id = %d', $id),
            ARRAY_A
        ) ?: null;
    }

    public function save_groups(array $rows, array $new = []): int
    {
        global $wpdb;
        $table = self::groups_table();
        $products = self::table();
        $updated = 0;

        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) { continue; }

            $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
            if (!$existing) { continue; }

            $nameBase = sanitize_text_field((string)($row['name_base'] ?? ''));
            if ($nameBase === '') { throw new RuntimeException('La categoría no puede quedar sin nombre.'); }

            $data = [
                'name_base'=>$nameBase,
                'name_es_es'=>sanitize_text_field((string)($row['name_es_es'] ?? '')),
                'name_pt_pt'=>sanitize_text_field((string)($row['name_pt_pt'] ?? '')),
                'name_en'=>sanitize_text_field((string)($row['name_en'] ?? '')),
                'color'=>sanitize_hex_color((string)($row['color'] ?? '')) ?: '#59636E',
                'sort_order'=>(int)($row['sort_order'] ?? 999),
            ];

            $result = $wpdb->update($table, $data, ['id'=>$id], ['%s','%s','%s','%s','%s','%d'], ['%d']);
            if ($result === false) {
                throw new RuntimeException($wpdb->last_error ?: 'No se pudo guardar la categoría.');
            }

            if ((string)$existing['name_base'] !== $nameBase) {
                $wpdb->update(
                    $products,
                    ['commercial_group'=>$nameBase],
                    ['category'=>(string)$existing['category'], 'commercial_group'=>(string)$existing['name_base']],
                    ['%s'],
                    ['%s','%s']
                );
            }
            $this->sync_group_metadata((string)$existing['category'], $nameBase);
            $updated += (int)$result;
        }

        if (!empty($new['name_base'])) {
            $category = sanitize_key((string)($new['category'] ?? ''));
            if (!in_array($category, ['carne','pescado-marisco'], true)) {
                throw new RuntimeException('Selecciona Carne o Pescado / Marisco para la nueva categoría.');
            }
            $nameBase = sanitize_text_field((string)$new['name_base']);
            $key = sanitize_title(remove_accents($nameBase));
            if ($key === '') { throw new RuntimeException('El nombre de la nueva categoría no es válido.'); }

            $existingId = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE category = %s AND (group_key = %s OR name_base = %s) LIMIT 1",
                $category,$key,$nameBase
            ));
            if ($existingId > 0) {
                throw new RuntimeException('Esa categoría ya existe.');
            }

            $ok = $wpdb->insert($table, [
                'category'=>$category,
                'group_key'=>$key,
                'name_base'=>$nameBase,
                'name_es_es'=>sanitize_text_field((string)($new['name_es_es'] ?? $nameBase)),
                'name_pt_pt'=>sanitize_text_field((string)($new['name_pt_pt'] ?? '')),
                'name_en'=>sanitize_text_field((string)($new['name_en'] ?? '')),
                'color'=>sanitize_hex_color((string)($new['color'] ?? '')) ?: '#59636E',
                'sort_order'=>(int)($new['sort_order'] ?? 999),
                'active'=>1,
            ], ['%s','%s','%s','%s','%s','%s','%s','%d','%d']);

            if ($ok === false) {
                throw new RuntimeException($wpdb->last_error ?: 'No se pudo crear la categoría.');
            }
            $updated++;
        }

        return $updated;
    }

    public function assign_group(int $productId, string $category, string $groupName): void
    {
        global $wpdb;
        $group = $this->group_by_name($category, $groupName);
        if (!$group) {
            throw new RuntimeException('La categoría comercial seleccionada no existe.');
        }

        $result = $wpdb->update(self::table(), [
            'commercial_group'=>(string)$group['name_base'],
            'group_sort'=>(int)$group['sort_order'],
            'group_color'=>(string)$group['color'],
            'published_at'=>current_time('mysql'),
        ], ['id'=>$productId], ['%s','%d','%s','%s'], ['%d']);

        if ($result === false) {
            throw new RuntimeException($wpdb->last_error ?: 'No se pudo asignar la categoría.');
        }
    }

    public function sync_group_metadata(string $category, string $groupName): void
    {
        global $wpdb;
        $group = $this->group_by_name($category, $groupName);
        if (!$group) { return; }

        $wpdb->update(
            self::table(),
            [
                'group_sort'=>(int)$group['sort_order'],
                'group_color'=>(string)$group['color'],
            ],
            [
                'category'=>$category,
                'commercial_group'=>(string)$group['name_base'],
            ],
            ['%d','%s'],
            ['%s','%s']
        );
    }

    public static function suggested_group_name(string $name, string $category): string
    {
        $n = strtoupper(remove_accents($name));

        if ($category === 'carne') {
            if (str_contains($n, 'BIFE ANCHO') || str_contains($n, 'TAPA DE BIFE ANCHO')) { return 'BIFE ANCHO / TAPA DE BIFE ANCHO'; }
            if (str_contains($n, 'BIFE ANGOSTO')) { return 'BIFE ANGOSTO'; }
            if (str_contains($n, 'LOMO SIN CORDON')) { return 'LOMO'; }
            if (str_contains($n, 'CUADRIL')) { return 'CUADRIL (TAPA Y CORAZÓN)'; }
            if (str_contains($n, 'ASADO') || str_contains($n, 'COSTILLA')) { return 'ASADO / COSTILLA'; }
            if (str_contains($n, 'TOMAHAWK') || str_contains($n, 'T-BONE') || str_contains($n, 'COWBOY') || str_contains($n, 'CLUB STEAK')) { return 'CORTES CON HUESO BLUESMOKE'; }
            if (str_contains($n, 'VACIO')) { return 'VACÍO'; }
            return 'OTROS CORTES';
        }

        if (str_contains($n, 'ALETA POTON') || str_contains($n, 'CHOCO') || str_contains($n, 'MANTO')) { return 'ALETA / MANTO / CHOCO (ROTACIÓN RÁPIDA)'; }
        if (str_contains($n, 'PULPO') && str_contains($n, 'COCID')) { return 'PULPO COCIDO'; }
        if (str_contains($n, 'PULPO SIN CABEZA')) { return 'PULPO CRUDO SIN CABEZA'; }
        if (str_contains($n, 'PULPO') && (str_contains($n, 'LIMPIO') || str_contains($n, 'BLOQUE'))) { return 'PULPO ENTERO LIMPIO'; }
        if (str_contains($n, 'PULPO')) { return 'PULPO ENTERO CON VÍSCERAS'; }
        if (str_contains($n, 'GAMBON')) { return 'GAMBÓN'; }
        if (str_contains($n, 'GAMBA BLANCA')) { return 'GAMBA BLANCA'; }
        if (str_contains($n, 'VIEIRA')) { return 'VIEIRA'; }
        if (str_contains($n, 'VANNAMEI')) { return 'VANNAMEI'; }
        if (str_contains($n, 'CANGREJO')) { return 'CANGREJO'; }
        if (str_contains($n, 'BOGAVANTE')) { return 'BOGAVANTE'; }

        return 'OTROS PESCADOS / MARISCOS';
    }

    private static function normalize_group_name(string $name): string
    {
        return strtoupper(trim(preg_replace('/\s+/', ' ', remove_accents($name))));
    }

    public function all(string $category, bool $include_hidden = false): array
    {
        global $wpdb;
        $visibility = $include_hidden ? '' : ' AND visible = 1';
        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . self::table() . ' WHERE category = %s' . $visibility .
                ' ORDER BY incoming ASC, group_sort ASC, item_sort ASC, product_name ASC, brand ASC, product_code ASC',
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
            ' ORDER BY category ASC, incoming ASC, group_sort ASC, item_sort ASC, product_name ASC, brand ASC, product_code ASC',
            ARRAY_A
        ) ?: [];
    }

    /**
     * Selective weekly import for FRN 1.1.14.
     * Only fields explicitly enabled in $updates are allowed to mutate.
     * Missing columns/cells never clear existing price or cost values.
     */
    public function publish_import(string $category, string $filename, array $rows, array $updates): int
    {
        global $wpdb;
        $table = self::table();
        $history = self::history_table();
        $now = current_time('mysql');
        $userId = get_current_user_id();

        $updates = array_merge(
            ['stock'=>false,'price'=>false,'cost'=>false,'groups'=>false],
            array_intersect_key($updates, ['stock'=>1,'price'=>1,'cost'=>1,'groups'=>1])
        );

        $wpdb->query('START TRANSACTION');

        try {
            // Only a stock synchronization is allowed to zero products that
            // disappeared from the current weekly stock source.
            if (!empty($updates['stock'])) {
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$table}
                         SET stock_kg = 0, visible = 0
                         WHERE category = %s AND incoming = 0",
                        $category
                    )
                );
            }

            $count = 0;

            foreach ($rows as $row) {
                $incoming = !empty($row['incoming']) ||
                    FRN_Excel_Importer::is_incoming_code((string) ($row['code'] ?? ''));

                $existingId = $this->find_existing_id(
                    $category,
                    (string) ($row['code'] ?? ''),
                    (string) ($row['name'] ?? ''),
                    $incoming
                );

                $existing = $existingId > 0
                    ? ($wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $existingId), ARRAY_A) ?: [])
                    : [];

                $data = [
                    'category' => $category,
                    'product_code' => sanitize_text_field((string) ($row['code'] ?? '')),
                    'brand' => sanitize_text_field((string) ($row['brand'] ?? '')),
                    'product_name' => sanitize_text_field((string) ($row['name'] ?? '')),
                    'model' => sanitize_text_field((string) ($row['model'] ?? '')),
                    'unit' => sanitize_text_field((string) ($row['unit'] ?? '')),
                    'source_file' => sanitize_file_name($filename),
                    'published_at' => $now,
                    'incoming' => $incoming ? 1 : 0,
                ];

                if (!empty($updates['stock']) && !empty($row['stock_present'])) {
                    $stock = max(0, (float) ($row['stock'] ?? 0));
                    $data['stock_kg'] = $stock;
                    $data['visible'] = $incoming
                        ? (!empty($row['publish']) ? 1 : 0)
                        : ($stock > 0 ? 1 : 0);
                }

                if (!empty($updates['price']) && !empty($row['price_present'])) {
                    // Explicit numeric zero clears the price; an absent/blank
                    // cell never reaches this branch and therefore preserves it.
                    $price = max(0, (float) ($row['price'] ?? 0));
                    $data['source_price_kg'] = $price;
                    $data['price_kg'] = $price;
                }

                if (!empty($updates['cost']) && !empty($row['cost_present'])) {
                    $data['average_cost_kg'] = max(0, (float) ($row['cost'] ?? 0));
                }

                if (!empty($updates['groups'])) {
                    $data['commercial_group'] = sanitize_text_field((string) ($row['commercial_group'] ?? ''));
                    $data['group_sort'] = (int) ($row['group_sort'] ?? 999);
                    $data['item_sort'] = (int) ($row['item_sort'] ?? 999);
                    $color = sanitize_hex_color((string) ($row['group_color'] ?? ''));
                    $data['group_color'] = $color ?: '#59636E';
                }

                // Translation seeds are populated only when a field is empty.
                // Future manual review is therefore never overwritten weekly.
                foreach (['name_es_ar','name_es_es','name_pt_pt','name_en'] as $translationField) {
                    $seed = sanitize_text_field((string) ($row[$translationField] ?? ''));
                    if ($seed !== '' && (!$existing || trim((string) ($existing[$translationField] ?? '')) === '')) {
                        $data[$translationField] = $seed;
                    }
                }

                if (!$existing) {
                    $seedGroup = sanitize_text_field((string)($row['commercial_group'] ?? ''));
                    $seedColor = sanitize_hex_color((string)($row['group_color'] ?? '')) ?: '#59636E';
                    $data += [
                        'stock_kg' => 0,
                        'source_price_kg' => null,
                        'price_kg' => null,
                        'average_cost_kg' => null,
                        'commercial_group' => $seedGroup,
                        'group_sort' => (int)($row['group_sort'] ?? 999),
                        'item_sort' => (int)($row['item_sort'] ?? 999),
                        'group_color' => $seedColor,
                        'name_es_ar' => sanitize_text_field((string) ($row['name_es_ar'] ?? '')),
                        'name_es_es' => sanitize_text_field((string) ($row['name_es_es'] ?? '')),
                        'name_pt_pt' => sanitize_text_field((string) ($row['name_pt_pt'] ?? '')),
                        'name_en' => sanitize_text_field((string) ($row['name_en'] ?? '')),
                        'translations_reviewed' => 0,
                        'featured' => 0,
                        'visible' => (!empty($updates['stock']) && !empty($row['stock_present']) && ((float)($row['stock'] ?? 0) > 0)) ? 1 : 0,
                    ];
                }

                $formats = [];
                foreach ($data as $key => $value) {
                    if (in_array($key, ['visible','incoming','translations_reviewed','group_sort','item_sort','featured'], true)) {
                        $formats[] = '%d';
                    } elseif (in_array($key, ['stock_kg','source_price_kg','average_cost_kg','price_kg'], true)) {
                        $formats[] = '%f';
                    } else {
                        $formats[] = '%s';
                    }
                }

                if ($existingId > 0) {
                    $result = $wpdb->update($table, $data, ['id'=>$existingId], $formats, ['%d']);
                    $productId = $existingId;
                } else {
                    $result = $wpdb->insert($table, $data, $formats);
                    $productId = (int) $wpdb->insert_id;
                }

                if ($result === false || $wpdb->last_error) {
                    throw new RuntimeException($wpdb->last_error ?: 'No se pudo actualizar el catálogo semanal.');
                }

                $current = $wpdb->get_row(
                    $wpdb->prepare("SELECT stock_kg,source_price_kg,price_kg,average_cost_kg FROM {$table} WHERE id = %d", $productId),
                    ARRAY_A
                ) ?: [];

                $ok = $wpdb->insert($history, [
                    'product_id' => $productId,
                    'category' => $category,
                    'product_code' => sanitize_text_field((string) ($row['code'] ?? '')),
                    'stock_kg' => (float) ($current['stock_kg'] ?? 0),
                    'source_price_kg' => (float) ($current['source_price_kg'] ?? 0),
                    'commercial_price_kg' => (float) ($current['price_kg'] ?? 0),
                    'average_cost_kg' => (float) ($current['average_cost_kg'] ?? 0),
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

    public function publish_stock(string $category, string $filename, array $rows): int
    {
        return $this->publish_import($category, $filename, $rows, [
            'stock'=>true,'price'=>false,'cost'=>false,'groups'=>true
        ]);
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

    public function update_translations(array $rows): int
    {
        global $wpdb;
        $updated = 0;

        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) { continue; }

            $data = [
                'commercial_group' => sanitize_text_field((string)($row['commercial_group'] ?? '')),
                'name_es_es' => sanitize_text_field((string)($row['name_es_es'] ?? '')),
                'name_pt_pt' => sanitize_text_field((string)($row['name_pt_pt'] ?? '')),
                'name_en' => sanitize_text_field((string)($row['name_en'] ?? '')),
                'translations_reviewed' => !empty($row['translations_reviewed']) ? 1 : 0,
                'published_at' => current_time('mysql'),
            ];

            $result = $wpdb->update(
                self::table(),
                $data,
                ['id'=>$id],
                ['%s','%s','%s','%s','%d','%s'],
                ['%d']
            );

            if ($result === false) {
                throw new RuntimeException($wpdb->last_error ?: 'No se pudieron guardar las traducciones.');
            }
            $updated += (int)$result;
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
