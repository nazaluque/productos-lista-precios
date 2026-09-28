<?php
if (!defined('ABSPATH')) { exit; }

final class FRN_Excel_Importer
{
    private const GROUP_COLORS = [
        '#17324D','#8A3B3B','#2F6659','#8A6426','#5B4B8A',
        '#A14E27','#276A78','#59636E','#7A5A72','#3D5A3B'
    ];

    public function parse_files(array $files, array $updates = []): array
    {
        return $this->parse($files, $this->normalize_updates($updates));
    }

    public function parse_stock_files(array $files, array $updates = []): array
    {
        if (!$updates) {
            $updates = ['stock' => true, 'price' => false, 'cost' => false, 'groups' => true];
        }
        return $this->parse($files, $this->normalize_updates($updates));
    }

    public function parse_price_files(array $files): array
    {
        return $this->parse($files, $this->normalize_updates([
            'stock' => false,
            'price' => true,
            'cost' => false,
            'groups' => false,
        ]));
    }

    public static function is_incoming_code(string $code): bool
    {
        $normalized = strtoupper(preg_replace('/[^A-Z0-9]/i', '', trim($code)));
        return (bool) preg_match('/^X{3,}$/', $normalized);
    }

    private function normalize_updates(array $updates): array
    {
        $out = ['stock' => false, 'price' => false, 'cost' => false, 'groups' => false];
        foreach ($out as $key => $_) {
            $out[$key] = !empty($updates[$key]);
        }
        if (!array_filter($out)) {
            throw new RuntimeException('Selecciona al menos un dato para importar: stock, precio, coste o categorías.');
        }
        return $out;
    }

    private function parse(array $files, array $updates): array
    {
        $normalized = $this->normalize_files($files);
        if (!$normalized) {
            throw new RuntimeException('No se recibió ningún Excel.');
        }

        $catalogs = ['carne' => [], 'pescado-marisco' => []];
        $groups = ['carne' => [], 'pescado-marisco' => []];
        $detected = ['stock' => false, 'price' => false, 'cost' => false, 'groups' => false];
        $filenames = [];
        $dataSheets = [];
        $summarySheets = [];

        foreach ($normalized as $file) {
            $filenames[] = sanitize_file_name($file['name']);
            $parsed = $this->parse_file($file['tmp_name'], $file['name'], $updates);

            foreach ($catalogs as $category => $_) {
                if (!empty($parsed['catalogs'][$category])) {
                    $catalogs[$category] = array_merge($catalogs[$category], $parsed['catalogs'][$category]);
                }
                if (!empty($parsed['groups'][$category])) {
                    foreach ($parsed['groups'][$category] as $group) {
                        $groups[$category][$group['key']] = $group;
                    }
                }
            }

            foreach ($detected as $key => $_) {
                $detected[$key] = $detected[$key] || !empty($parsed['detected'][$key]);
            }

            $dataSheets = array_merge($dataSheets, $parsed['data_sheets']);
            $summarySheets = array_merge($summarySheets, $parsed['summary_sheets']);
        }

        foreach ($catalogs as $category => $rows) {
            $catalogs[$category] = $this->dedupe_rows($rows);
        }

        $groupMap = ['carne' => [], 'pescado-marisco' => []];
        foreach ($groups as $category => $categoryGroups) {
            $groups[$category] = array_values($categoryGroups);
            usort($groups[$category], static fn(array $a, array $b): int => ($a['sort_order'] <=> $b['sort_order']));
            foreach ($groups[$category] as $group) {
                foreach ($group['products'] as $product) {
                    $groupMap[$category][strtoupper($product['code'])] = [
                        'commercial_group' => $group['label'],
                        'group_sort' => (int) $group['sort_order'],
                        'item_sort' => (int) $product['sort_order'],
                        'group_color' => $group['color'],
                        'group_es_es' => $group['es_es'],
                        'group_pt_pt' => $group['pt_pt'],
                        'group_en' => $group['en'],
                    ];
                }
            }
        }

        $ungrouped = ['carne' => [], 'pescado-marisco' => []];
        foreach ($catalogs as $category => &$rows) {
            foreach ($rows as &$row) {
                $codeKey = strtoupper(trim((string) ($row['code'] ?? '')));
                if (!empty($groupMap[$category][$codeKey])) {
                    $row = array_merge($row, $groupMap[$category][$codeKey]);
                } else {
                    $row += [
                        'commercial_group' => '',
                        'group_sort' => 999,
                        'item_sort' => 999,
                        'group_color' => '#59636E',
                        'group_es_es' => '',
                        'group_pt_pt' => '',
                        'group_en' => '',
                    ];
                    if ($updates['groups'] && $codeKey !== '' && !self::is_incoming_code($codeKey)) {
                        $ungrouped[$category][] = $codeKey;
                    }
                }

                $translations = $this->product_translations((string) ($row['name'] ?? ''), $category);
                $row += $translations;
            }
            unset($row);
        }
        unset($rows);

        foreach (['stock','price','cost'] as $field) {
            if ($updates[$field] && !$detected[$field]) {
                $label = $field === 'stock' ? 'stock' : ($field === 'price' ? 'precio' : 'coste promedio');
                throw new RuntimeException('Has marcado “' . $label . '”, pero el Excel no contiene una columna reconocible para ese dato.');
            }
        }
        if ($updates['groups'] && !$detected['groups']) {
            throw new RuntimeException('Has marcado “Categorías / orden”, pero no se encontraron hojas Resumen con categorías reconocibles.');
        }

        $total = count($catalogs['carne']) + count($catalogs['pescado-marisco']);
        if ($total === 0) {
            throw new RuntimeException('No se encontraron productos reconocibles en el Excel.');
        }

        return [
            'filename' => implode(' + ', $filenames),
            'updates' => $updates,
            'detected' => $detected,
            'data_sheets' => array_values(array_unique($dataSheets)),
            'summary_sheets' => array_values(array_unique($summarySheets)),
            'groups' => $groups,
            'ungrouped' => [
                'carne' => array_values(array_unique($ungrouped['carne'])),
                'pescado-marisco' => array_values(array_unique($ungrouped['pescado-marisco'])),
            ],
            'catalogs' => $catalogs,
        ];
    }

    private function parse_file(string $path, string $name, array $updates): array
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($extension, ['xlsx', 'xls'], true)) {
            throw new RuntimeException('Solo se admiten archivos XLSX o XLS.');
        }

        $autoload = FRN_SP_PATH . 'vendor/autoload.php';
        if (!file_exists($autoload)) {
            throw new RuntimeException('Faltan las dependencias Excel del plugin.');
        }

        require_once $autoload;

        $workbook = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $catalogs = ['carne' => [], 'pescado-marisco' => []];
        $groups = ['carne' => [], 'pescado-marisco' => []];
        $detected = ['stock' => false, 'price' => false, 'cost' => false, 'groups' => false];
        $dataSheets = [];
        $summarySheets = [];

        foreach ($workbook->getWorksheetIterator() as $sheet) {
            $title = trim((string) $sheet->getTitle());
            $normalizedTitle = strtolower(remove_accents($title));

            if (str_starts_with($normalizedTitle, 'resumen')) {
                $raw = $sheet->toArray(null, true, false, false);
                $summary = $this->parse_summary_sheet($raw, $title);
                if ($summary) {
                    $groups[$summary['category']] = array_merge($groups[$summary['category']], $summary['groups']);
                    $detected['groups'] = true;
                    $summarySheets[] = $title;
                }
                continue;
            }

            $raw = $sheet->toArray(null, true, false, false);
            if (count($raw) < 2) { continue; }

            $headerIndex = $this->find_header_row($raw);
            if ($headerIndex === null) { continue; }

            $headers = array_map([$this, 'normalize_header'], $raw[$headerIndex]);
            $sheetDetected = [
                'stock' => in_array('stock', $headers, true),
                'price' => in_array('price', $headers, true),
                'cost' => in_array('cost', $headers, true),
            ];
            foreach ($sheetDetected as $key => $value) {
                $detected[$key] = $detected[$key] || $value;
            }

            $sheetCategory = $this->detect_category($title, $name, $raw);
            $rows = $this->rows_from_raw($raw, $sheetCategory, $updates);
            if ($rows) { $dataSheets[] = $title; }

            foreach ($rows as $row) {
                $category = (string) ($row['category'] ?? '');
                if (isset($catalogs[$category])) {
                    $catalogs[$category][] = $row;
                }
            }
        }

        return compact('catalogs','groups','detected','dataSheets','summarySheets') + [
            'data_sheets' => $dataSheets,
            'summary_sheets' => $summarySheets,
        ];
    }

    private function parse_summary_sheet(array $raw, string $title): ?array
    {
        $normalizedTitle = strtolower(remove_accents($title));
        $category = str_contains($normalizedTitle, 'carne')
            ? 'carne'
            : ((str_contains($normalizedTitle, 'pesc') || str_contains($normalizedTitle, 'marisc')) ? 'pescado-marisco' : null);
        if (!$category) { return null; }

        $groups = [];
        $groupSort = 0;
        $count = count($raw);

        for ($i = 0; $i < $count; $i++) {
            $label = trim((string) ($raw[$i][0] ?? ''));
            if ($label === '') { continue; }

            $next = strtolower(remove_accents(trim((string) ($raw[$i + 1][0] ?? ''))));
            $normalized = strtolower(remove_accents($label));

            if ($next !== 'producto') { continue; }
            if (str_starts_with($normalized, 'resumen') || str_starts_with($normalized, 'total')) { continue; }

            $groupSort++;
            $translations = $this->group_translations($label, $category);
            $products = [];
            $itemSort = 0;

            for ($j = $i + 2; $j < $count; $j++) {
                $productCell = trim((string) ($raw[$j][0] ?? ''));
                if ($productCell === '') { break; }
                if (strtoupper($productCell) === 'TOTAL' || str_starts_with(strtoupper($productCell), 'TOTAL ')) { break; }

                if (preg_match('/^\[([^\]]+)\]\s*(.*)$/u', $productCell, $m)) {
                    $itemSort++;
                    $products[] = [
                        'code' => strtoupper(trim($m[1])),
                        'name' => trim($m[2]),
                        'sort_order' => $itemSort,
                    ];
                }
            }

            $groups[] = [
                'key' => sanitize_key(remove_accents($label)),
                'label' => sanitize_text_field($label),
                'sort_order' => $groupSort,
                'color' => self::GROUP_COLORS[($groupSort - 1) % count(self::GROUP_COLORS)],
                'es_es' => $translations['es_es'],
                'pt_pt' => $translations['pt_pt'],
                'en' => $translations['en'],
                'products' => $products,
            ];
        }

        return ['category' => $category, 'groups' => $groups];
    }

    private function rows_from_raw(array $raw, ?string $fallbackCategory, array $updates): array
    {
        $headerIndex = $this->find_header_row($raw);
        if ($headerIndex === null) { return []; }

        $headers = array_map([$this, 'normalize_header'], $raw[$headerIndex]);
        $rows = [];

        foreach (array_slice($raw, $headerIndex + 1) as $values) {
            $values = array_pad($values, count($headers), '');
            if ($this->is_repeated_header($values)) { continue; }

            $item = [];
            foreach ($headers as $idx => $header) {
                if ($header === '') { continue; }
                $item[$header] = $values[$idx] ?? '';
            }

            $code = trim((string) ($item['code'] ?? ''));
            $nameValue = trim((string) ($item['product'] ?? ''));

            if ($code === '' && preg_match('/^\[([A-Z0-9]+)\]\s*(.+)$/iu', $nameValue, $match)) {
                $code = strtoupper(trim($match[1]));
                $nameValue = trim($match[2]);
            }

            $brand = trim((string) ($item['brand'] ?? ''));
            if ($brand !== '' && preg_match('/^' . preg_quote($brand, '/') . '\s*:\s*(.+)$/iu', $nameValue, $m)) {
                $nameValue = trim($m[1]);
            } elseif (preg_match('/^[A-Z0-9 .&\-]+:\s*(.+)$/u', $nameValue, $m)) {
                $nameValue = trim($m[1]);
            }

            if ($code === '' && $nameValue === '') { continue; }
            if ($this->is_summary_row($code, $nameValue)) { continue; }

            $stockPresent = array_key_exists('stock', $item) && trim((string) $item['stock']) !== '';
            $pricePresent = array_key_exists('price', $item) && trim((string) $item['price']) !== '';
            $costPresent = array_key_exists('cost', $item) && trim((string) $item['cost']) !== '';

            $stock = $stockPresent ? $this->number($item['stock'], true) : null;
            $price = $pricePresent ? $this->number($item['price'], true) : null;
            $cost = $costPresent ? $this->number($item['cost'], true) : null;

            $incoming = self::is_incoming_code($code);
            $category = $this->row_category($item, $code, $fallbackCategory);
            if (!$category) { continue; }

            $meaningful = $incoming
                || ($updates['stock'] && $stockPresent && (($stock ?? 0) > 0))
                || ($updates['price'] && $pricePresent)
                || ($updates['cost'] && $costPresent)
                || $updates['groups'];
            if (!$meaningful) { continue; }

            $errors = [];
            if ($code === '') { $errors[] = 'falta código de producto'; }
            if ($updates['stock'] && $stockPresent && $stock === null) { $errors[] = 'stock no válido'; }
            if ($updates['price'] && $pricePresent && $price === null) { $errors[] = 'precio no válido'; }
            if ($updates['cost'] && $costPresent && $cost === null) { $errors[] = 'coste no válido'; }
            if (($price ?? 0) < 0) { $errors[] = 'precio inválido'; }
            if (($cost ?? 0) < 0) { $errors[] = 'coste promedio inválido'; }

            $sourcePublish = !array_key_exists('publish', $item) || $this->truthy($item['publish']);
            $publish = $incoming ? $sourcePublish : (($stock ?? 0) > 0);

            $rows[] = [
                'category' => $category,
                'code' => sanitize_text_field($code),
                'brand' => sanitize_text_field($brand),
                'name' => sanitize_text_field($nameValue),
                'model' => sanitize_text_field((string) ($item['model'] ?? '')),
                'unit' => sanitize_text_field((string) ($item['unit'] ?? '')),
                'stock' => $stock,
                'price' => $price,
                'cost' => $cost,
                'stock_present' => $stockPresent,
                'price_present' => $pricePresent,
                'cost_present' => $costPresent,
                'featured' => $this->truthy($item['featured'] ?? ''),
                'publish' => $publish,
                'incoming' => $incoming,
                'status' => sanitize_text_field((string) ($item['status'] ?? '')),
                'valid' => !$errors,
                'errors' => $errors,
            ];
        }

        return $rows;
    }

    private function group_translations(string $label, string $category): array
    {
        $key = strtoupper(trim(remove_accents($label)));

        if ($category === 'carne') {
            $map = [
                'BIFE ANCHO / TAPA DE BIFE ANCHO' => ['LOMO ALTO / TAPA DE LOMO ALTO','LOMBO ALTO / CAPA DO LOMBO ALTO','RIBEYE / RIBEYE CAP'],
                'BIFE ANGOSTO' => ['LOMO BAJO / ENTRECOT','VAZIA','STRIPLOIN'],
                'LOMO' => ['SOLOMILLO','LOMBO','TENDERLOIN'],
                'CUADRIL (TAPA Y CORAZON)' => ['CADERA (PICAÑA Y CENTRO)','ALCATRA (PICANHA E CORAÇÃO)','RUMP (RUMP CAP & HEART)'],
                'ASADO / COSTILLA' => ['COSTILLAR / COSTILLA','COSTELA / ENTRECOSTO','RIBS / SHORT RIBS'],
                'CORTES CON HUESO BLUESMOKE' => ['CORTES CON HUESO BLUESMOKE','CORTES COM OSSO BLUESMOKE','BLUESMOKE BONE-IN CUTS'],
                'VACIO' => ['FALDA / VACÍO','FRALDINHA','FLANK / THIN FLANK'],
                'OTROS CORTES' => ['OTROS CORTES','OUTROS CORTES','OTHER CUTS'],
            ];
        } else {
            $map = [
                'ALETA / MANTO / CHOCO (ROTACION RAPIDA)' => ['ALETA / MANTO / CHOCO (ROTACIÓN RÁPIDA)','ASA / MANTO / CHOCO (ROTAÇÃO RÁPIDA)','WINGS / MANTLE / CUTTLEFISH (FAST MOVERS)'],
                'PULPO ENTERO CON VISCERAS' => ['PULPO ENTERO CON VÍSCERAS','POLVO INTEIRO COM VÍSCERAS','WHOLE OCTOPUS WITH VISCERA'],
                'PULPO ENTERO LIMPIO' => ['PULPO ENTERO LIMPIO','POLVO INTEIRO LIMPO','WHOLE CLEANED OCTOPUS'],
                'PULPO CRUDO SIN CABEZA' => ['PULPO CRUDO SIN CABEZA','POLVO CRU SEM CABEÇA','RAW OCTOPUS WITHOUT HEAD'],
                'PULPO COCIDO' => ['PULPO COCIDO','POLVO COZIDO','COOKED OCTOPUS'],
                'GAMBON' => ['GAMBÓN','CAMARÃO / GAMBÃO','KING PRAWN'],
                'VIEIRA' => ['VIEIRA','VIEIRA','SCALLOP'],
                'VANNAMEI' => ['VANNAMEI','VANNAMEI','VANNAMEI SHRIMP'],
            ];
        }

        $row = $map[$key] ?? [$label, $label, $label];
        return ['es_es' => $row[0], 'pt_pt' => $row[1], 'en' => $row[2]];
    }

    private function product_translations(string $name, string $category): array
    {
        $original = trim($name);
        if ($original === '') {
            return ['name_es_ar'=>'','name_es_es'=>'','name_pt_pt'=>'','name_en'=>'','translations_reviewed'=>0];
        }

        if ($category !== 'carne') {
            $pt = str_ireplace(
                ['COLA GAMBON PELADA DEVENADA','CLUSTER DE CANGREJO','BOGAVANTE AZUL','ALETA POTON','CHOCO LIMPIO','PULPO','COCIDO','COCIDA','CRUDO','SIN CABEZA','CABEZA DE','PATA ','VIEIRA','VANNAMEI ENTERO CRUDO'],
                ['CAUDA DE GAMBÃO DESCASCADA E DESVENADA','CLUSTER DE CARANGUEJO','LAVAGANTE AZUL','ASA DE POTA','CHOCO LIMPO','POLVO','COZIDO','COZIDA','CRU','SEM CABEÇA','CABEÇA DE','TENTÁCULO ','VIEIRA','CAMARÃO VANNAMEI INTEIRO CRU'],
                $original
            );
            $en = str_ireplace(
                ['COLA GAMBON PELADA DEVENADA','CLUSTER DE CANGREJO','BOGAVANTE AZUL','ALETA POTON','CHOCO LIMPIO','PULPO','COCIDO','COCIDA','CRUDO','SIN CABEZA','CABEZA DE','PATA ','VIEIRA','VANNAMEI ENTERO CRUDO'],
                ['PEELED DEVEINED KING PRAWN TAIL','CRAB CLUSTER','BLUE LOBSTER','GIANT SQUID FIN','CLEAN CUTTLEFISH','OCTOPUS','COOKED','COOKED','RAW','WITHOUT HEAD','HEAD OF','TENTACLE ','SCALLOP','WHOLE RAW VANNAMEI SHRIMP'],
                $original
            );
            return [
                'name_es_ar' => $original,
                'name_es_es' => $original,
                'name_pt_pt' => $pt,
                'name_en' => $en,
                'translations_reviewed' => 0,
            ];
        }

        $rules = [
            ['BIFE ANGOSTO SIN CORDON','LOMO BAJO SIN CORDÓN','VAZIA SEM CORDÃO','STRIPLOIN CHAIN OFF'],
            ['BIFE ANGOSTO PORCIONADO','LOMO BAJO PORCIONADO / ENTRECOT PORCIONADO','VAZIA FATIADA / BIFE DA VAZIA','PORTIONED STRIPLOIN / STRIP STEAK'],
            ['BIFE ANGOSTO','LOMO BAJO / ENTRECOT','VAZIA','STRIPLOIN / NEW YORK STRIP'],
            ['BIFE ANCHO SIN TAPA','LOMO ALTO SIN TAPA','BIFE DO LOMBO ALTO SEM CAPA','CUBE ROLL / RIBEYE ROLL CAP OFF'],
            ['TAPA DE BIFE ANCHO','TAPA DE LOMO ALTO','CAPA DO LOMBO ALTO','RIBEYE CAP / CUBE ROLL COVER'],
            ['LOMO SIN CORDON','SOLOMILLO SIN CORDÓN','LOMBO SEM CORDÃO','TENDERLOIN CHAIN OFF'],
            ['CORAZON DE CUADRIL','CENTRO DE CADERA','CORAÇÃO DA ALCATRA','HEART OF RUMP / EYE OF RUMP'],
            ['TAPA DE CUADRIL','PICAÑA / TAPILLA DE CADERA','PICANHA','RUMP CAP / PICANHA'],
            ['ASADO CON HUESO 3 COSTILLAS','COSTILLAR / TIRA DE ASADO CON HUESO 3 COSTILLAS','TIRA DE ENTRECOSTO COM OSSO 3 COSTELAS','BONE-IN SHORT RIBS 3 RIBS'],
            ['COSTILLA RECORTADA SELECCION','COSTILLA DE VACUNO RECORTADA','COSTELA DE BOVINO APARADA','TRIMMED BEEF RIB / SHORT RIB'],
            ['VACIO PORCIONADO A','FALDA / VACÍO PORCIONADO','FRALDINHA PORCIONADA','THIN FLANK / FLANK STEAK PORTIONED'],
            ['MARUCHA','CAÑÓN DE ESPALDILLA / ESPALDILLA','COBERTA DA PÁ / RAQUETE','OYSTER BLADE'],
            ['BABILLA','BABILLA','RABADILHA','KNUCKLE / SIRLOIN TIP'],
            ['CARRILLERA DE VACUNO S/HUESO AL VACIO NZ','CARRILLERA DE VACUNO SIN HUESO AL VACÍO NZ','BOCHECHA DE BOVINO SEM OSSO EM VÁCUO NZ','BONELESS BEEF CHEEK VACUUM PACKED NZ'],
            ['AGUJA SIN TAPA','AGUJA DE VACUNO SIN TAPA','ACÉM SEM CAPA','CHUCK EYE ROLL / CHUCK ROLL STEAK'],
            ['ENTRAÑA','ENTRAÑA / DIAFRAGMA','ENTRANHA / DIAFRAGMA','SKIRT STEAK / THIN SKIRT'],
        ];

        foreach ($rules as [$needle,$es,$pt,$en]) {
            if (stripos(remove_accents($original), remove_accents($needle)) !== false) {
                $suffix = preg_replace('/^.*?' . preg_quote($needle, '/') . '/iu', '', $original, 1);
                return [
                    'name_es_ar' => $original,
                    'name_es_es' => trim($es . ' ' . $suffix),
                    'name_pt_pt' => trim($pt . ' ' . $suffix),
                    'name_en' => trim($en . ' ' . $suffix),
                    'translations_reviewed' => 0,
                ];
            }
        }

        $unchanged = ['TOMAHAWK','T-BONE','COWBOY','CLUB STEAK','HAMBURGUESA'];
        foreach ($unchanged as $term) {
            if (stripos($original, $term) !== false) {
                return [
                    'name_es_ar' => $original,
                    'name_es_es' => $original,
                    'name_pt_pt' => $original,
                    'name_en' => $original,
                    'translations_reviewed' => 0,
                ];
            }
        }

        return [
            'name_es_ar' => $original,
            'name_es_es' => $original,
            'name_pt_pt' => '',
            'name_en' => '',
            'translations_reviewed' => 0,
        ];
    }

    private function row_category(array $item, string $code, ?string $fallback): ?string
    {
        $labels = [(string) ($item['category'] ?? ''), (string) ($item['model'] ?? '')];

        foreach ($labels as $label) {
            $value = strtolower(remove_accents(trim($label)));
            if ($value === '') { continue; }
            if (str_contains($value, 'carne') || str_contains($value, 'vacuno') || str_contains($value, 'bovino') || str_contains($value, 'beef')) {
                return 'carne';
            }
            if (str_contains($value, 'pesc') || str_contains($value, 'marisc') || str_contains($value, 'seafood')) {
                return 'pescado-marisco';
            }
        }

        $normalizedCode = strtoupper(trim($code));
        if (preg_match('/^C\d+/i', $normalizedCode)) { return 'carne'; }
        if (preg_match('/^P\d+/i', $normalizedCode)) { return 'pescado-marisco'; }
        return $fallback;
    }

    private function detect_category(string $sheet, string $filename, array $raw): ?string
    {
        $haystack = remove_accents(strtolower($sheet . ' ' . $filename));
        if (str_contains($haystack, 'carne') || str_contains($haystack, 'vacuno') || str_contains($haystack, 'beef')) { return 'carne'; }
        if (str_contains($haystack, 'pesc') || str_contains($haystack, 'marisc') || str_contains($haystack, 'seafood')) { return 'pescado-marisco'; }

        $headerIndex = $this->find_header_row($raw);
        if ($headerIndex === null) { return null; }
        $headers = array_map([$this, 'normalize_header'], $raw[$headerIndex]);
        $codePos = array_search('code', $headers, true);
        $categoryPos = array_search('category', $headers, true);
        $c = 0; $p = 0;

        foreach (array_slice($raw, $headerIndex + 1, 100) as $row) {
            if ($categoryPos !== false) {
                $value = strtolower(remove_accents(trim((string) ($row[$categoryPos] ?? ''))));
                if (str_contains($value, 'carne') || str_contains($value, 'vacuno') || str_contains($value, 'bovino')) { $c++; }
                if (str_contains($value, 'pesc') || str_contains($value, 'marisc')) { $p++; }
            }
            if ($codePos !== false) {
                $code = strtoupper(trim((string) ($row[$codePos] ?? '')));
                if (preg_match('/^C\d+/i', $code)) { $c++; }
                if (preg_match('/^P\d+/i', $code)) { $p++; }
            } else {
                $first = trim((string) ($row[0] ?? ''));
                if (preg_match('/^\[C\d+\]/i', $first)) { $c++; }
                if (preg_match('/^\[P\d+\]/i', $first)) { $p++; }
            }
        }

        if ($c > 0 && $p === 0) { return 'carne'; }
        if ($p > 0 && $c === 0) { return 'pescado-marisco'; }
        return null;
    }

    private function find_header_row(array $raw): ?int
    {
        foreach (array_slice($raw, 0, 30, true) as $index => $row) {
            $headers = array_map([$this, 'normalize_header'], $row);
            $hasProduct = in_array('product', $headers, true);
            $hasCode = in_array('code', $headers, true);
            $hasData = in_array('stock', $headers, true)
                || in_array('price', $headers, true)
                || in_array('cost', $headers, true)
                || in_array('category', $headers, true);
            if (($hasProduct || $hasCode) && $hasData) { return (int) $index; }
        }
        return null;
    }

    private function normalize_header(mixed $value): string
    {
        $value = remove_accents(strtolower(trim((string) $value)));
        $value = preg_replace('/\s+/', ' ', $value);

        return match (true) {
            in_array($value, ['codigo','id','referencia','referencia interna','ref','sku','cod','cod.'], true) => 'code',
            str_contains($value, 'marca') || str_contains($value, 'brand') => 'brand',
            in_array($value, ['producto','productos','nombre','nombre del producto','descripcion','product','products','description','articulo'], true) => 'product',
            str_contains($value, 'stock') || str_contains($value, 'cantidad pronosticada') || str_contains($value, 'cantidad a la mano') || $value === 'cantidad' || str_contains($value, 'disponible') || str_contains($value, 'existencia') => 'stock',
            str_contains($value, 'precio de venta') || $value === 'precio' || str_contains($value, 'price') || str_contains($value, 'tarifa') || str_contains($value, '€/kg') || str_contains($value, 'eur/kg') => 'price',
            in_array($value, ['costo','coste','costo promedio','coste promedio','costo medio','coste medio','average cost','avg cost'], true) || str_contains($value, 'coste promedio') || str_contains($value, 'costo promedio') => 'cost',
            str_contains($value, 'categoria del producto') || $value === 'categoria' || str_contains($value, 'familia') => 'category',
            in_array($value, ['modelo','model'], true) => 'model',
            str_contains($value, 'unidad de medida') || $value === 'unidad' || $value === 'unit' || $value === 'udm' => 'unit',
            in_array($value, ['oferta','destacado','featured','promocion'], true) => 'featured',
            in_array($value, ['publicar','publicado','visible','usar','activo'], true) => 'publish',
            in_array($value, ['estado','status'], true) => 'status',
            default => sanitize_key($value),
        };
    }

    private function is_summary_row(string $code, string $name): bool
    {
        if (trim($code) !== '') { return false; }
        $value = strtolower(remove_accents(trim($name)));
        return (bool) preg_match('/^(total(?: general)?|subtotal)(\b|\s|:|-)/', $value);
    }

    private function is_repeated_header(array $row): bool
    {
        $normalized = array_map([$this, 'normalize_header'], $row);
        return (in_array('product', $normalized, true) || in_array('code', $normalized, true))
            && (in_array('stock', $normalized, true) || in_array('price', $normalized, true) || in_array('cost', $normalized, true));
    }

    private function truthy(mixed $value): bool
    {
        return in_array(strtolower(trim(remove_accents((string) $value))), ['si','1','true','x','yes','y','ok'], true);
    }

    private function number(mixed $value, bool $nullable = false): ?float
    {
        if (is_int($value) || is_float($value)) { return (float) $value; }
        if ($nullable && ($value === null || trim((string) $value) === '')) { return null; }

        $clean = preg_replace('/[^0-9,.-]/', '', (string) $value);
        if ($clean === '' || $clean === '-' || $clean === null) { return $nullable ? null : 0.0; }

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

        return is_numeric($clean) ? (float) $clean : null;
    }

    private function normalize_files(array $files): array
    {
        if (!isset($files['name'])) { return []; }
        if (!is_array($files['name'])) {
            return !empty($files['tmp_name']) ? [$files] : [];
        }

        $out = [];
        foreach ($files['name'] as $i => $name) {
            if (empty($files['tmp_name'][$i]) || (int) ($files['error'][$i] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) { continue; }
            $out[] = [
                'name' => $name,
                'tmp_name' => $files['tmp_name'][$i],
                'type' => $files['type'][$i] ?? '',
                'size' => $files['size'][$i] ?? 0,
                'error' => $files['error'][$i] ?? UPLOAD_ERR_OK,
            ];
        }
        return $out;
    }

    private function dedupe_rows(array $rows): array
    {
        $seen = [];
        $out = [];

        foreach ($rows as $row) {
            $code = strtoupper(trim((string) ($row['code'] ?? '')));
            $name = strtolower(remove_accents(trim((string) ($row['name'] ?? ''))));
            $key = self::is_incoming_code($code) ? $code . '|' . $name : ($code !== '' ? $code : $name);
            if ($key === '') { continue; }

            if (isset($seen[$key])) {
                $old = $out[$seen[$key]];
                foreach (['stock','price','cost'] as $field) {
                    $present = $field . '_present';
                    if (empty($row[$present]) && !empty($old[$present])) {
                        $row[$field] = $old[$field];
                        $row[$present] = true;
                    }
                }
                $out[$seen[$key]] = array_merge($old, $row);
            } else {
                $seen[$key] = count($out);
                $out[] = $row;
            }
        }

        return array_values($out);
    }
}
