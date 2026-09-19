<?php
if (!defined('ABSPATH')) { exit; }

final class FRN_Excel_Importer
{
    public function parse_files(array $files): array
    {
        return $this->parse($files, 'stock');
    }

    public function parse_stock_files(array $files): array
    {
        return $this->parse($files, 'stock');
    }

    /**
     * Legacy import retained for backwards compatibility. The normal 1.1
     * workflow uses the weekly unified stock file, which may already contain
     * source selling price and average cost.
     */
    public function parse_price_files(array $files): array
    {
        return $this->parse($files, 'price');
    }

    public static function is_incoming_code(string $code): bool
    {
        $normalized = strtoupper(preg_replace('/[^A-Z0-9]/i', '', trim($code)));
        return (bool) preg_match('/^X{3,}$/', $normalized);
    }

    private function parse(array $files, string $mode): array
    {
        $normalized = $this->normalize_files($files);
        if (!$normalized) {
            throw new RuntimeException('No se recibió ningún Excel.');
        }

        $catalogs = ['carne' => [], 'pescado-marisco' => []];
        $filenames = [];

        foreach ($normalized as $file) {
            $filenames[] = sanitize_file_name($file['name']);
            $parsed = $this->parse_file($file['tmp_name'], $file['name'], $mode);

            foreach ($catalogs as $category => $_) {
                if (!empty($parsed[$category])) {
                    $catalogs[$category] = array_merge($catalogs[$category], $parsed[$category]);
                }
            }
        }

        foreach ($catalogs as $category => $rows) {
            $catalogs[$category] = $this->dedupe_rows($rows);
        }

        $total = count($catalogs['carne']) + count($catalogs['pescado-marisco']);
        if ($total === 0) {
            $label = $mode === 'price' ? 'precios' : 'productos con stock';
            throw new RuntimeException('No se encontraron ' . $label . ' reconocibles en el Excel.');
        }

        return [
            'filename' => implode(' + ', $filenames),
            'mode' => $mode,
            'catalogs' => $catalogs,
        ];
    }

    private function parse_file(string $path, string $name, string $mode): array
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

        foreach ($workbook->getWorksheetIterator() as $sheet) {
            $raw = $sheet->toArray(null, true, false, false);
            if (count($raw) < 2) { continue; }

            $sheetCategory = $this->detect_category($sheet->getTitle(), $name, $raw, $mode);
            $rows = $this->rows_from_raw($raw, $sheetCategory, $mode);

            foreach ($rows as $row) {
                $category = (string) ($row['category'] ?? '');
                if (!isset($catalogs[$category])) { continue; }
                $catalogs[$category][] = $row;
            }
        }

        return $catalogs;
    }

    private function rows_from_raw(array $raw, ?string $fallbackCategory, string $mode): array
    {
        if (count($raw) < 2) { return []; }

        $headerIndex = $this->find_header_row($raw, $mode);
        if ($headerIndex === null) { return []; }

        $headers = array_map([$this, 'normalize_header'], $raw[$headerIndex]);
        $rows = [];

        foreach (array_slice($raw, $headerIndex + 1) as $values) {
            $values = array_pad($values, count($headers), '');
            $item = array_combine($headers, array_slice($values, 0, count($headers))) ?: [];

            if ($this->is_repeated_header($values)) { continue; }

            $code = trim((string) ($item['code'] ?? ''));
            $nameValue = trim((string) ($item['product'] ?? ''));

            if ($code === '' && preg_match('/^\\[([A-Z0-9]+)\\]\\s*(.+)$/i', $nameValue, $match)) {
                $code = strtoupper(trim($match[1]));
                $nameValue = trim($match[2]);
            }

            if ($code === '' && $nameValue === '') { continue; }
            if ($this->is_summary_row($code, $nameValue)) { continue; }

            if ($mode === 'price') {
                $sectionMarker = strtolower(remove_accents($nameValue));
                if ($sectionMarker === 'contacto') { break; }
            }

            $stock = $this->number($item['stock'] ?? null, true);
            $price = $this->number($item['price'] ?? null, true);
            $cost = $this->number($item['cost'] ?? null, true);
            $incoming = self::is_incoming_code($code);
            $category = $this->row_category($item, $code, $fallbackCategory);

            // Weekly FRN rule: regular rows with stock 0 / blank are not imported.
            // Existing catalogue records are reset to stock 0 by the repository,
            // preserving the product master and historical record.
            if ($mode !== 'price' && !$incoming && (($stock ?? 0) <= 0)) {
                continue;
            }

            $sourcePublish = !array_key_exists('publish', $item) || $this->truthy($item['publish']);
            if ($mode === 'price') {
                $publish = $incoming
                    ? $sourcePublish
                    : ($sourcePublish && (($price ?? 0) > 0));
            } else {
                $publish = $incoming
                    ? $sourcePublish
                    : ($sourcePublish && (($stock ?? 0) > 0));
            }

            $errors = [];

            if ($code === '') {
                $errors[] = $mode === 'price'
                    ? 'falta código de producto'
                    : 'producto con stock sin Referencia Interna/código';
            }

            if (!$category) {
                $errors[] = 'no se pudo determinar Carne o Pescado/Marisco';
                continue;
            }

            if ($incoming && $nameValue === '') {
                $errors[] = 'próximo ingreso sin nombre';
            }

            if ($mode !== 'price' && !$incoming && $stock === null) {
                $errors[] = 'stock no válido';
            }

            if ($price !== null && $price < 0) {
                $errors[] = 'precio inválido';
            }

            if ($cost !== null && $cost < 0) {
                $errors[] = 'coste promedio inválido';
            }

            $rows[] = [
                'category' => $category,
                'code' => sanitize_text_field($code),
                'brand' => sanitize_text_field((string) ($item['brand'] ?? '')),
                'name' => sanitize_text_field($nameValue),
                'model' => sanitize_text_field((string) ($item['model'] ?? '')),
                'stock' => $stock ?? 0,
                'price' => $price ?? 0,
                'cost' => $cost ?? 0,
                'unit' => sanitize_text_field((string) ($item['unit'] ?? '')),
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

    private function row_category(array $item, string $code, ?string $fallback): ?string
    {
        $labels = [
            (string) ($item['category'] ?? ''),
            (string) ($item['model'] ?? ''),
        ];

        foreach ($labels as $label) {
            $value = strtolower(remove_accents(trim($label)));
            if ($value === '') { continue; }

            if (
                str_contains($value, 'carne') ||
                str_contains($value, 'vacuno') ||
                str_contains($value, 'bovino') ||
                str_contains($value, 'beef')
            ) {
                return 'carne';
            }

            if (
                str_contains($value, 'pesc') ||
                str_contains($value, 'marisc') ||
                str_contains($value, 'seafood')
            ) {
                return 'pescado-marisco';
            }
        }

        $normalizedCode = strtoupper(trim($code));
        if (preg_match('/^C\\d+/i', $normalizedCode)) { return 'carne'; }
        if (preg_match('/^P\\d+/i', $normalizedCode)) { return 'pescado-marisco'; }

        return $fallback;
    }

    private function detect_category(string $sheet, string $filename, array $raw, string $mode): ?string
    {
        $haystack = remove_accents(strtolower($sheet . ' ' . $filename));

        if (
            str_contains($haystack, 'carne') ||
            str_contains($haystack, 'vacuno') ||
            str_contains($haystack, 'beef')
        ) {
            return 'carne';
        }

        if (
            str_contains($haystack, 'pesc') ||
            str_contains($haystack, 'marisc') ||
            str_contains($haystack, 'seafood')
        ) {
            return 'pescado-marisco';
        }

        $headerIndex = $this->find_header_row($raw, $mode);
        if ($headerIndex === null) { return null; }

        $headers = array_map([$this, 'normalize_header'], $raw[$headerIndex]);
        $codePos = array_search('code', $headers, true);
        $categoryPos = array_search('category', $headers, true);

        $c = 0;
        $p = 0;

        foreach (array_slice($raw, $headerIndex + 1, 80) as $row) {
            if ($categoryPos !== false) {
                $categoryValue = strtolower(remove_accents(trim((string) ($row[$categoryPos] ?? ''))));
                if (
                    str_contains($categoryValue, 'carne') ||
                    str_contains($categoryValue, 'vacuno') ||
                    str_contains($categoryValue, 'bovino')
                ) {
                    $c++;
                }
                if (str_contains($categoryValue, 'pesc') || str_contains($categoryValue, 'marisc')) {
                    $p++;
                }
            }

            if ($codePos !== false) {
                $code = strtoupper(trim((string) ($row[$codePos] ?? '')));
                if (preg_match('/^C\\d+/i', $code)) { $c++; }
                if (preg_match('/^P\\d+/i', $code)) { $p++; }
            }
        }

        if ($c > 0 && $p === 0) { return 'carne'; }
        if ($p > 0 && $c === 0) { return 'pescado-marisco'; }

        // Mixed sheets are intentionally left without a sheet-level category;
        // each row will be classified using Categoria del producto / code.
        return null;
    }

    private function find_header_row(array $raw, string $mode): ?int
    {
        foreach (array_slice($raw, 0, 30, true) as $index => $row) {
            $headers = array_map([$this, 'normalize_header'], $row);

            $hasProduct = in_array('product', $headers, true);
            $hasCode = in_array('code', $headers, true);
            $hasStock = in_array('stock', $headers, true);
            $hasPrice = in_array('price', $headers, true);

            if ($mode === 'price') {
                if ($hasPrice && ($hasCode || $hasProduct)) {
                    return (int) $index;
                }
                continue;
            }

            if ($hasStock && ($hasCode || $hasProduct)) {
                return (int) $index;
            }
        }

        return null;
    }

    private function normalize_header(mixed $value): string
    {
        $value = remove_accents(strtolower(trim((string) $value)));
        $value = preg_replace('/\\s+/', ' ', $value);

        return match (true) {
            in_array($value, [
                'codigo','id','referencia','referencia interna','ref','sku','cod','cod.'
            ], true) => 'code',

            str_contains($value, 'marca') || str_contains($value, 'brand') => 'brand',

            in_array($value, [
                'producto','productos','nombre','nombre del producto','descripcion','product',
                'products','description','articulo'
            ], true) => 'product',

            str_contains($value, 'stock') ||
                str_contains($value, 'cantidad a la mano') ||
                str_contains($value, 'cantidad') ||
                str_contains($value, 'disponible') ||
                str_contains($value, 'existencia') => 'stock',

            str_contains($value, 'precio de venta') ||
                str_contains($value, 'precio') ||
                str_contains($value, 'price') ||
                str_contains($value, 'tarifa') ||
                str_contains($value, '€/kg') ||
                str_contains($value, 'eur/kg') => 'price',

            in_array($value, [
                'costo','coste','costo promedio','coste promedio','costo medio','coste medio',
                'average cost','avg cost'
            ], true) ||
                str_contains($value, 'coste promedio') ||
                str_contains($value, 'costo promedio') => 'cost',

            str_contains($value, 'categoria del producto') ||
                str_contains($value, 'categoria') ||
                str_contains($value, 'familia') => 'category',

            in_array($value, ['modelo','model'], true) => 'model',

            str_contains($value, 'unidad de medida') ||
                str_contains($value, 'unidad') ||
                $value === 'unit' ||
                $value === 'udm' => 'unit',

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
        return (bool) preg_match('/^(total(?: general)?|subtotal)(\\b|\\s|:|-)/', $value);
    }

    private function is_repeated_header(array $row): bool
    {
        $normalized = array_map([$this, 'normalize_header'], $row);
        $hasProduct = in_array('product', $normalized, true);
        $hasCode = in_array('code', $normalized, true);
        $hasStock = in_array('stock', $normalized, true);
        $hasPrice = in_array('price', $normalized, true);

        return ($hasProduct || $hasCode) && ($hasStock || $hasPrice);
    }

    private function truthy(mixed $value): bool
    {
        return in_array(
            strtolower(trim(remove_accents((string) $value))),
            ['si','1','true','x','yes','y','ok'],
            true
        );
    }

    private function number(mixed $value, bool $nullable = false): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if ($nullable && ($value === null || trim((string) $value) === '')) {
            return null;
        }

        $clean = preg_replace('/[^0-9,.-]/', '', (string) $value);
        if ($clean === '' || $clean === '-' || $clean === null) {
            return $nullable ? null : 0.0;
        }

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
            if (
                empty($files['tmp_name'][$i]) ||
                (int) ($files['error'][$i] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK
            ) {
                continue;
            }

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

            $key = self::is_incoming_code($code)
                ? $code . '|' . $name
                : ($code !== '' ? $code : $name);

            if ($key === '') { continue; }

            if (isset($seen[$key])) {
                $out[$seen[$key]] = $row;
            } else {
                $seen[$key] = count($out);
                $out[] = $row;
            }
        }

        return array_values($out);
    }
}
