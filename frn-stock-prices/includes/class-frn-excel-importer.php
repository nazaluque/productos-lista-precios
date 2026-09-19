<?php
if (!defined('ABSPATH')) { exit; }

final class FRN_Excel_Importer
{
    public function parse_files(array $files): array
    {
        return $this->parse($files, 'combined');
    }

    public function parse_stock_files(array $files): array
    {
        return $this->parse($files, 'stock');
    }

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
            $label = $mode === 'stock' ? 'stocks' : ($mode === 'price' ? 'precios' : 'datos');
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

        $known = [
            'carne' => $workbook->getSheetByName('CARNE_IMPORT'),
            'pescado-marisco' => $workbook->getSheetByName('PESCADO_IMPORT'),
        ];

        $usedKnown = false;
        foreach ($known as $category => $sheet) {
            if (!$sheet) { continue; }

            $usedKnown = true;
            $catalogs[$category] = array_merge(
                $catalogs[$category],
                $this->rows_from_raw($sheet->toArray(null, true, false, false), $category, $mode)
            );
        }

        if ($usedKnown) {
            return $catalogs;
        }

        foreach ($workbook->getWorksheetIterator() as $sheet) {
            $raw = $sheet->toArray(null, true, false, false);
            if (count($raw) < 2) { continue; }

            $category = $this->detect_category($sheet->getTitle(), $name, $raw, $mode);
            if (!$category) { continue; }

            $catalogs[$category] = array_merge(
                $catalogs[$category],
                $this->rows_from_raw($raw, $category, $mode)
            );
        }

        return $catalogs;
    }

    private function rows_from_raw(array $raw, string $category, string $mode): array
    {
        if (count($raw) < 2) { return []; }

        $headerIndex = $this->find_header_row($raw, $mode);
        if ($headerIndex === null) { return []; }

        $headers = array_map([$this, 'normalize_header'], $raw[$headerIndex]);
        $rows = [];

        foreach (array_slice($raw, $headerIndex + 1) as $index => $values) {
            $values = array_pad($values, count($headers), '');
            $item = array_combine($headers, array_slice($values, 0, count($headers))) ?: [];

            $code = trim((string) ($item['code'] ?? ''));
            $nameValue = trim((string) ($item['product'] ?? ''));

            if ($mode === 'price') {
                $sectionMarker = strtolower(remove_accents($nameValue));
                if ($sectionMarker === 'contacto') {
                    break;
                }
            }

            if ($code === '' && $nameValue === '') { continue; }

            // Native Odoo stock export arrives as:
            // [P00663] FRN: PULPO T3 LIMPIO...
            // Extract the stable SKU from the product label automatically.
            if ($code === '' && preg_match('/^\\[([A-Z0-9]+)\\]\\s*(.+)$/i', $nameValue, $match)) {
                $code = strtoupper(trim($match[1]));
                $nameValue = trim($match[2]);
            }

            $stock = $this->number($item['stock'] ?? null, true);
            $price = $this->number($item['price'] ?? null, true);
            $incoming = self::is_incoming_code($code);
            $sourcePublish = !array_key_exists('publish', $item) || $this->truthy($item['publish']);

            if ($mode === 'stock') {
                $publish = $incoming
                    ? $sourcePublish
                    : ($sourcePublish && (($stock ?? 0) > 0));
            } elseif ($mode === 'price') {
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
                    : 'no se pudo leer el código Odoo';
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

            $rows[] = [
                'category' => $category,
                'code' => sanitize_text_field($code),
                'brand' => sanitize_text_field((string) ($item['brand'] ?? '')),
                'name' => sanitize_text_field($nameValue),
                'stock' => $stock ?? 0,
                'price' => $price ?? 0,
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
        if ($codePos === false) { return null; }

        $c = 0;
        $p = 0;

        foreach (array_slice($raw, $headerIndex + 1, 50) as $row) {
            $code = strtoupper(trim((string) ($row[$codePos] ?? '')));
            if (preg_match('/^C\d+/i', $code)) { $c++; }
            if (preg_match('/^P\d+/i', $code)) { $p++; }
        }

        if ($c > $p && $c > 0) { return 'carne'; }
        if ($p > $c && $p > 0) { return 'pescado-marisco'; }

        return null;
    }

    private function find_header_row(array $raw, string $mode): ?int
    {
        foreach (array_slice($raw, 0, 20, true) as $index => $row) {
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

            if ($mode === 'stock') {
                if ($hasStock && ($hasCode || $hasProduct)) {
                    return (int) $index;
                }
                continue;
            }

            if (($hasStock || $hasPrice) && ($hasCode || $hasProduct)) {
                return (int) $index;
            }
        }

        return null;
    }

    private function normalize_header(mixed $value): string
    {
        $value = remove_accents(strtolower(trim((string) $value)));
        $value = preg_replace('/\s+/', ' ', $value);

        return match (true) {
            in_array($value, ['codigo','código','id','referencia','ref','sku','cod','cod.'], true) => 'code',
            str_contains($value, 'marca') || str_contains($value, 'brand') => 'brand',
            in_array($value, ['producto','productos','nombre','descripcion','descripción','product','products','description','articulo','artículo'], true) => 'product',
            str_contains($value, 'stock') || str_contains($value, 'cantidad') || str_contains($value, 'disponible') || str_contains($value, 'existencia') => 'stock',
            str_contains($value, 'precio') || str_contains($value, 'price') || str_contains($value, 'tarifa') || str_contains($value, '€/kg') || str_contains($value, 'eur/kg') => 'price',
            in_array($value, ['oferta','destacado','featured','promocion','promoción'], true) => 'featured',
            in_array($value, ['publicar','publicado','visible','usar','activo'], true) => 'publish',
            in_array($value, ['estado','status'], true) => 'status',
            default => sanitize_key($value),
        };
    }

    private function truthy(mixed $value): bool
    {
        return in_array(
            strtolower(trim(remove_accents((string) $value))),
            ['si','sí','1','true','x','yes','y','ok'],
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
