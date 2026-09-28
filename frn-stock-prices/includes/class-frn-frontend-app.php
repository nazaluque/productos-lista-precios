<?php

if (!defined('ABSPATH')) { exit; }

final class FRN_Frontend_App
{
    private const PREVIEW_PREFIX = 'frn_front_preview_';

    private FRN_Catalog_Repository $catalog;
    private FRN_Tariff_Repository $tariffs;
    private FRN_Price_List_Repository $priceLists;
    private FRN_Excel_Importer $excel;

    public function __construct()
    {
        $this->catalog = new FRN_Catalog_Repository();
        $this->tariffs = new FRN_Tariff_Repository();
        $this->priceLists = new FRN_Price_List_Repository();
        $this->excel = new FRN_Excel_Importer();
    }

    public function boot(): void
    {
        add_action('admin_post_frn_front_preview_stock', [$this, 'preview_stock']);
        add_action('admin_post_frn_front_publish_stock', [$this, 'publish_stock']);
        add_action('admin_post_frn_front_preview_prices', [$this, 'preview_prices']);
        add_action('admin_post_frn_front_publish_prices', [$this, 'publish_prices']);
        add_action('admin_post_frn_front_save_products', [$this, 'save_products']);
        add_action('admin_post_frn_front_tariff_create', [$this, 'tariff_create']);
        add_action('admin_post_frn_front_tariff_save', [$this, 'tariff_save']);
        add_action('admin_post_frn_front_tariff_pdf', [$this, 'tariff_pdf']);
        add_action('admin_post_frn_front_tariff_csv', [$this, 'tariff_csv']);
        add_action('admin_post_frn_front_settings', [$this, 'save_settings']);
        add_action('admin_post_frn_front_user_save', [$this, 'user_save']);
        add_action('admin_post_frn_front_pdf_branding', [$this, 'save_pdf_branding']);
        add_action('admin_post_frn_front_save_translations', [$this, 'save_translations']);
        add_action('admin_post_frn_front_save_groups', [$this, 'save_groups']);
    }

    public function render(): void
    {
        $this->guard_capability();

        $tab = sanitize_key($_GET['tab'] ?? 'importar');
        if (!in_array($tab, ['importar','tarifas','tarifa','traducciones','usuarios','diseno'], true)) {
            $tab = 'importar';
        }

        $previewToken = sanitize_key($_GET['preview'] ?? '');
        $preview = $previewToken ? get_transient(self::PREVIEW_PREFIX . $previewToken) : null;
        $tariffId = absint($_GET['id'] ?? 0);

        $data = [
            'tab' => $tab,
            'previewToken' => $previewToken,
            'preview' => is_array($preview) ? $preview : null,
            'products' => $this->catalog->all_combined(false),
            'translationProducts' => current_user_can('frn_edit_translations') ? $this->catalog->all_combined(true) : [],
            'commercialGroups' => current_user_can('frn_edit_translations') ? $this->catalog->all_groups() : ['carne'=>[],'pescado-marisco'=>[]],
            'latestImport' => $this->catalog->latest_import_meta(),
            'priceLists' => $this->priceLists->all(),
            'tariffs' => $this->tariffs->all_tariffs(),
            'tariff' => $tariffId ? $this->tariffs->get_tariff($tariffId) : null,
            'tariffLines' => $tariffId ? $this->tariffs->get_lines($tariffId) : [],
            'messages' => $this->messages(),
            'canEditStock' => current_user_can('frn_edit_stock'),
            'canEditPrices' => current_user_can('frn_edit_prices'),
            'canViewCost' => current_user_can('frn_view_cost'),
            'canExport' => current_user_can('frn_export_tariffs'),
            'canEditTranslations' => current_user_can('frn_edit_translations'),
            'canManageUsers' => current_user_can('frn_manage_users'),
            'frnUsers' => current_user_can('frn_manage_users') ? get_users(['role__in'=>['frn_administrator','frn_stock','frn_director_comercial','frn_comercial','frn_consulta'],'orderby'=>'display_name']) : [],
            'pdfBranding' => current_user_can('frn_manage_users') ? $this->pdf_branding_state() : [],
        ];

        extract($data, EXTR_SKIP);
        require FRN_SP_PATH . 'templates/app-v1.php';
    }

    public function preview_stock(): void
    {
        $this->guard_post('frn_front_preview_stock');

        $rawFields = is_array($_POST['import_fields'] ?? null) ? wp_unslash($_POST['import_fields']) : [];
        $updates = [
            'stock' => !empty($rawFields['stock']),
            'price' => !empty($rawFields['price']),
            'cost' => !empty($rawFields['cost']),
            'groups' => !empty($rawFields['groups']),
        ];

        if (($updates['stock'] || $updates['groups']) && !current_user_can('frn_edit_stock')) {
            $this->redirect(['tab'=>'importar','error'=>rawurlencode('Tu perfil no puede actualizar stock ni categorías.')]);
        }
        if (($updates['price'] || $updates['cost']) && !current_user_can('frn_edit_prices')) {
            $this->redirect(['tab'=>'importar','error'=>rawurlencode('Tu perfil no puede actualizar precios ni costes.')]);
        }

        try {
            $parsed = $this->excel->parse_files($_FILES['stock_files'] ?? [], $updates);

            foreach (['carne','pescado-marisco'] as $category) {
                foreach (($parsed['catalogs'][$category] ?? []) as $i => $row) {
                    $existing = $this->catalog->find_product(
                        $category,
                        (string)($row['code'] ?? ''),
                        (string)($row['name'] ?? ''),
                        !empty($row['incoming'])
                    );

                    $currentGroup = $existing ? trim((string)($existing['commercial_group'] ?? '')) : '';
                    $currentMeta = $currentGroup !== '' ? $this->catalog->group_meta($category, $currentGroup) : null;
                    $suggested = FRN_Catalog_Repository::suggested_group_name((string)($row['name'] ?? ''), $category);
                    $suggestedMeta = $this->catalog->group_meta($category, $suggested);

                    $parsed['catalogs'][$category][$i]['is_new'] = !$existing;
                    $parsed['catalogs'][$category][$i]['existing_product_id'] = $existing ? (int)$existing['id'] : 0;
                    $parsed['catalogs'][$category][$i]['existing_group'] = $currentMeta ? (string)$currentMeta['name_base'] : '';
                    $parsed['catalogs'][$category][$i]['suggested_group'] = $suggestedMeta ? (string)$suggestedMeta['name_base'] : '';
                    $parsed['catalogs'][$category][$i]['needs_group_assignment'] = !$currentMeta;
                    if ($currentMeta) {
                        $parsed['catalogs'][$category][$i]['commercial_group'] = (string)$currentMeta['name_base'];
                        $parsed['catalogs'][$category][$i]['group_sort'] = (int)$currentMeta['sort_order'];
                        $parsed['catalogs'][$category][$i]['group_color'] = (string)$currentMeta['color'];
                    } else {
                        $parsed['catalogs'][$category][$i]['commercial_group'] = '';
                    }
                }
            }
            $parsed['commercial_groups'] = $this->catalog->all_groups();
        } catch (Throwable $e) {
            $this->redirect(['tab' => 'importar', 'error' => rawurlencode($e->getMessage())]);
        }

        $parsed['preview_type'] = 'selective';
        $token = wp_generate_password(20, false, false);
        set_transient(self::PREVIEW_PREFIX . $token, $parsed, HOUR_IN_SECONDS);

        $this->redirect(['tab' => 'importar', 'preview' => $token]);
    }

    public function publish_stock(): void
    {
        $token = sanitize_key($_POST['preview'] ?? '');
        $this->guard_post('frn_front_publish_stock_' . $token);

        $preview = get_transient(self::PREVIEW_PREFIX . $token);
        if (!$preview || !is_array($preview) || !in_array(($preview['preview_type'] ?? ''), ['stock','selective'], true)) {
            $this->redirect(['tab' => 'importar', 'error' => rawurlencode('La previsualización ha caducado.')]);
        }

        $updates = array_merge(
            ['stock'=>false,'price'=>false,'cost'=>false,'groups'=>false],
            is_array($preview['updates'] ?? null) ? $preview['updates'] : []
        );

        if (($updates['stock'] || $updates['groups']) && !current_user_can('frn_edit_stock')) {
            $this->redirect(['tab'=>'importar','error'=>rawurlencode('Tu perfil no puede publicar stock ni categorías.')]);
        }
        if (($updates['price'] || $updates['cost']) && !current_user_can('frn_edit_prices')) {
            $this->redirect(['tab'=>'importar','error'=>rawurlencode('Tu perfil no puede publicar precios ni costes.')]);
        }

        $assignments = is_array($_POST['group_assignments'] ?? null)
            ? wp_unslash($_POST['group_assignments'])
            : [];

        foreach (['carne','pescado-marisco'] as $category) {
            foreach (($preview['catalogs'][$category] ?? []) as $i => $row) {
                if (empty($row['needs_group_assignment'])) { continue; }

                $code = strtoupper(trim((string)($row['code'] ?? '')));
                $chosen = sanitize_text_field((string)($assignments[$category][$code] ?? ''));
                $group = $this->catalog->group_meta($category, $chosen);

                if (!$group) {
                    $this->redirect([
                        'tab'=>'importar',
                        'preview'=>$token,
                        'error'=>rawurlencode('Asigna una categoría comercial válida a ' . ($code ?: (string)($row['name'] ?? 'producto nuevo')) . ' antes de publicar.'),
                    ]);
                }

                $preview['catalogs'][$category][$i]['commercial_group'] = (string)$group['name_base'];
                $preview['catalogs'][$category][$i]['group_sort'] = (int)$group['sort_order'];
                $preview['catalogs'][$category][$i]['group_color'] = (string)$group['color'];
                $preview['catalogs'][$category][$i]['needs_group_assignment'] = false;
                $preview['catalogs'][$category][$i]['force_group_assignment'] = true;
            }
        }

        $all = array_merge(
            $preview['catalogs']['carne'] ?? [],
            $preview['catalogs']['pescado-marisco'] ?? []
        );
        $invalid = array_filter($all, static fn(array $row): bool => empty($row['valid']));
        if ($invalid) {
            $this->redirect([
                'tab'=>'importar','preview'=>$token,
                'error'=>rawurlencode('Hay filas con errores. Corrige el Excel antes de publicar.'),
            ]);
        }

        $counts = ['carne'=>0,'pescado-marisco'=>0];
        try {
            foreach ($counts as $category => $_) {
                $rows = array_values($preview['catalogs'][$category] ?? []);
                if (!$rows) { continue; }
                $counts[$category] = $this->catalog->publish_import(
                    $category,
                    (string) ($preview['filename'] ?? 'Datos semanales'),
                    $rows,
                    $updates
                );
            }
        } catch (Throwable $e) {
            $this->redirect([
                'tab'=>'importar','preview'=>$token,
                'error'=>rawurlencode($e->getMessage()),
            ]);
        }

        delete_transient(self::PREVIEW_PREFIX . $token);

        $labels = [];
        if ($updates['stock']) { $labels[] = 'stock'; }
        if ($updates['price']) { $labels[] = 'precio'; }
        if ($updates['cost']) { $labels[] = 'coste'; }
        if ($updates['groups']) { $labels[] = 'categorías/orden'; }

        update_option('frn_sp_last_import_fields', implode(', ', $labels), false);

        $this->redirect([
            'tab'=>'importar',
            'stock_published'=>$counts['carne'] + $counts['pescado-marisco'],
            'updated_fields'=>rawurlencode(implode(', ', $labels)),
        ]);
    }

    public function preview_prices(): void
    {
        $this->guard_post('frn_front_preview_prices');

        $name = sanitize_text_field((string) ($_POST['price_list_name'] ?? ''));
        if (trim($name) === '') {
            $this->redirect(['tab' => 'importar', 'error' => rawurlencode('Pon un nombre a la tarifa de precios.')]);
        }

        try {
            $parsed = $this->excel->parse_price_files($_FILES['price_files'] ?? []);
        } catch (Throwable $e) {
            $this->redirect(['tab' => 'importar', 'error' => rawurlencode($e->getMessage())]);
        }

        $parsed['preview_type'] = 'prices';
        $parsed['price_list_name'] = $name;

        $token = wp_generate_password(20, false, false);
        set_transient(self::PREVIEW_PREFIX . $token, $parsed, HOUR_IN_SECONDS);

        $this->redirect(['tab' => 'importar', 'preview' => $token]);
    }

    public function publish_prices(): void
    {
        $token = sanitize_key($_POST['preview'] ?? '');
        $this->guard_post('frn_front_publish_prices_' . $token);

        $preview = get_transient(self::PREVIEW_PREFIX . $token);

        if (!$preview || !is_array($preview) || ($preview['preview_type'] ?? '') !== 'prices') {
            $this->redirect(['tab' => 'importar', 'error' => rawurlencode('La previsualización de precios ha caducado.')]);
        }

        try {
            foreach (['carne','pescado-marisco'] as $category) {
                $rows = array_values($preview['catalogs'][$category] ?? []);
                if (!$rows) { continue; }

                $this->catalog->ensure_from_price_rows(
                    $category,
                    (string) ($preview['filename'] ?? 'Tarifa comercial'),
                    $rows
                );
            }

            $listId = $this->priceLists->create(
                (string) ($preview['price_list_name'] ?? 'Tarifa comercial'),
                (string) ($preview['filename'] ?? ''),
                (array) ($preview['catalogs'] ?? [])
            );
        } catch (Throwable $e) {
            $this->redirect([
                'tab' => 'importar',
                'preview' => $token,
                'error' => rawurlencode($e->getMessage()),
            ]);
        }

        delete_transient(self::PREVIEW_PREFIX . $token);

        $this->redirect([
            'tab' => 'importar',
            'prices_published' => $listId,
        ]);
    }

    public function save_products(): void
    {
        $this->guard_post('frn_front_save_products');

        $raw = is_array($_POST['products'] ?? null) ? wp_unslash($_POST['products']) : [];
        $rows = [];

        foreach ($raw as $id => $row) {
            if (!is_array($row)) { continue; }

            $rows[] = [
                'id' => (int) $id,
                'code' => sanitize_text_field((string) ($row['code'] ?? '')),
                'brand' => sanitize_text_field((string) ($row['brand'] ?? '')),
                'name' => sanitize_text_field((string) ($row['name'] ?? '')),
                'stock' => $this->number($row['stock'] ?? 0),
                'featured' => !empty($row['featured']),
                'visible' => !empty($row['visible']),
                'price' => $this->number($row['price'] ?? 0),
            ];
        }

        try {
            $this->catalog->update_many($rows, current_user_can('frn_edit_stock'), current_user_can('frn_edit_prices'));
        } catch (Throwable $e) {
            $this->redirect(['tab' => 'importar', 'error' => rawurlencode($e->getMessage())]);
        }

        $this->redirect(['tab' => 'importar', 'saved' => 1]);
    }

    public function save_translations(): void
    {
        $this->guard_post('frn_front_save_translations', 'frn_edit_translations');

        $raw = is_array($_POST['translations'] ?? null) ? wp_unslash($_POST['translations']) : [];
        $rows = [];

        foreach ($raw as $id => $row) {
            if (!is_array($row)) { continue; }
            $rows[] = [
                'id' => (int)$id,
                'commercial_group' => sanitize_text_field((string)($row['commercial_group'] ?? '')),
                'name_es_es' => sanitize_text_field((string)($row['name_es_es'] ?? '')),
                'name_pt_pt' => sanitize_text_field((string)($row['name_pt_pt'] ?? '')),
                'name_en' => sanitize_text_field((string)($row['name_en'] ?? '')),
                'translations_reviewed' => !empty($row['translations_reviewed']),
            ];
        }

        try {
            $this->catalog->update_translations($rows);
        } catch (Throwable $e) {
            $this->redirect(['tab'=>'traducciones','error'=>rawurlencode($e->getMessage())]);
        }

        $this->redirect(['tab'=>'traducciones','translations_saved'=>1]);
    }

    public function save_groups(): void
    {
        $this->guard_post('frn_front_save_groups', 'frn_edit_translations');

        $rows = is_array($_POST['groups'] ?? null) ? wp_unslash($_POST['groups']) : [];
        $new = is_array($_POST['new_group'] ?? null) ? wp_unslash($_POST['new_group']) : [];

        $normalized = [];
        foreach ($rows as $id => $row) {
            if (!is_array($row)) { continue; }
            $normalized[] = [
                'id'=>(int)$id,
                'name_base'=>sanitize_text_field((string)($row['name_base'] ?? '')),
                'name_es_es'=>sanitize_text_field((string)($row['name_es_es'] ?? '')),
                'name_pt_pt'=>sanitize_text_field((string)($row['name_pt_pt'] ?? '')),
                'name_en'=>sanitize_text_field((string)($row['name_en'] ?? '')),
                'color'=>sanitize_hex_color((string)($row['color'] ?? '')) ?: '#59636E',
                'sort_order'=>(int)($row['sort_order'] ?? 999),
            ];
        }

        try {
            $this->catalog->save_groups($normalized, [
                'category'=>sanitize_key((string)($new['category'] ?? '')),
                'name_base'=>sanitize_text_field((string)($new['name_base'] ?? '')),
                'name_es_es'=>sanitize_text_field((string)($new['name_es_es'] ?? '')),
                'name_pt_pt'=>sanitize_text_field((string)($new['name_pt_pt'] ?? '')),
                'name_en'=>sanitize_text_field((string)($new['name_en'] ?? '')),
                'color'=>sanitize_hex_color((string)($new['color'] ?? '')) ?: '#59636E',
                'sort_order'=>(int)($new['sort_order'] ?? 999),
            ]);
        } catch (Throwable $e) {
            $this->redirect(['tab'=>'traducciones','error'=>rawurlencode($e->getMessage())]);
        }

        $this->redirect(['tab'=>'traducciones','groups_saved'=>1]);
    }

    public function user_save(): void
    {
        $this->guard_post('frn_front_user_save', 'frn_manage_users');

        $userId = absint($_POST['user_id'] ?? 0);
        $login = sanitize_user((string) ($_POST['user_login'] ?? ''));
        $email = sanitize_email((string) ($_POST['user_email'] ?? ''));
        $displayName = sanitize_text_field((string) ($_POST['display_name'] ?? ''));
        $password = (string) ($_POST['user_password'] ?? '');
        $role = sanitize_key((string) ($_POST['frn_role'] ?? 'frn_consulta'));

        $allowedRoles = ['frn_administrator','frn_stock','frn_director_comercial','frn_comercial','frn_consulta'];
        if (!in_array($role, $allowedRoles, true)) {
            $this->redirect(['tab'=>'usuarios','error'=>rawurlencode('Perfil FRN no válido.')]);
        }

        if ($userId > 0) {
            $user = get_user_by('id', $userId);
            if (!$user) {
                $this->redirect(['tab'=>'usuarios','error'=>rawurlencode('Usuario no encontrado.')]);
            }
            $payload = ['ID'=>$userId, 'display_name'=>$displayName ?: $user->display_name];
            if ($email !== '') { $payload['user_email'] = $email; }
            if ($password !== '') { $payload['user_pass'] = $password; }
            $result = wp_update_user($payload);
            if (is_wp_error($result)) {
                $this->redirect(['tab'=>'usuarios','error'=>rawurlencode($result->get_error_message())]);
            }
            $user->set_role($role);
        } else {
            if ($login === '' || $email === '' || $password === '') {
                $this->redirect(['tab'=>'usuarios','error'=>rawurlencode('Usuario, email y contraseña son obligatorios.')]);
            }
            $result = wp_insert_user([
                'user_login'=>$login,
                'user_email'=>$email,
                'display_name'=>$displayName ?: $login,
                'user_pass'=>$password,
                'role'=>$role,
            ]);
            if (is_wp_error($result)) {
                $this->redirect(['tab'=>'usuarios','error'=>rawurlencode($result->get_error_message())]);
            }
        }

        $this->redirect(['tab'=>'usuarios','user_saved'=>1]);
    }

    public function save_pdf_branding(): void
    {
        $this->guard_post('frn_front_pdf_branding', 'frn_manage_users');

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $fields = [
            'frn_pdf_header_carne' => 'frn_pdf_header_carne_id',
            'frn_pdf_header_pescado' => 'frn_pdf_header_pescado_id',
            'frn_pdf_logo' => 'frn_pdf_logo_id',
        ];

        foreach ($fields as $field => $option) {
            if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) { continue; }

            $error = (int) ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) { continue; }
            if ($error !== UPLOAD_ERR_OK) {
                $this->redirect(['tab'=>'diseno','error'=>rawurlencode('No se pudo subir uno de los archivos de diseño.')]);
            }

            $size = (int) ($_FILES[$field]['size'] ?? 0);
            if ($size <= 0 || $size > 8 * MB_IN_BYTES) {
                $this->redirect(['tab'=>'diseno','error'=>rawurlencode('Cada imagen debe pesar menos de 8 MB.')]);
            }

            $tmp = (string) ($_FILES[$field]['tmp_name'] ?? '');
            $name = (string) ($_FILES[$field]['name'] ?? '');
            $checked = wp_check_filetype_and_ext($tmp, $name);
            $mime = (string) ($checked['type'] ?? '');

            if (!in_array($mime, ['image/jpeg','image/png'], true) || !@getimagesize($tmp)) {
                $this->redirect(['tab'=>'diseno','error'=>rawurlencode('Solo se admiten JPG o PNG válidos para el diseño del PDF.')]);
            }

            $attachmentId = media_handle_upload($field, 0, [], ['test_form' => false]);
            if (is_wp_error($attachmentId)) {
                $this->redirect(['tab'=>'diseno','error'=>rawurlencode($attachmentId->get_error_message())]);
            }

            $path = get_attached_file((int) $attachmentId);
            if (!$path || !is_readable($path) || !@getimagesize($path)) {
                wp_delete_attachment((int) $attachmentId, true);
                $this->redirect(['tab'=>'diseno','error'=>rawurlencode('WordPress guardó la imagen, pero no puede leerla desde el servidor.')]);
            }

            update_option($option, (int) $attachmentId, false);
        }

        foreach (['carne','pescado'] as $scope) {
            $position = sanitize_key((string)($_POST['header_position_' . $scope] ?? 'center'));
            if (!in_array($position, ['top','center','bottom'], true)) { $position = 'center'; }
            update_option('frn_pdf_header_position_' . $scope, $position, false);
        }

        $this->redirect(['tab'=>'diseno','branding_saved'=>1]);
    }

    public function tariff_create(): void
    {
        $this->guard_post('frn_front_tariff_create', 'frn_export_tariffs');

        $scope = sanitize_key((string) ($_POST['scope'] ?? ''));
        if (!in_array($scope, ['carne','pescado-marisco'], true)) {
            $this->redirect(['tab' => 'tarifas', 'error' => rawurlencode('Selecciona Carne o Pescado y marisco.')]);
        }

        $preset = sanitize_key((string) ($_POST['preset'] ?? 'general'));
        $market = sanitize_key((string) ($_POST['market'] ?? 'es'));
        if (!in_array($market, ['es','pt','en'], true)) { $market = 'es'; }
        $priceListId = absint($_POST['price_list_id'] ?? 0);
        $date = sanitize_text_field((string) ($_POST['tariff_date'] ?? current_time('Y-m-d')));
        $label = $scope === 'carne' ? 'Carne' : 'Pescado y marisco';

        if ($scope === 'carne') {
            $primaryLang = 'es_ar';
            $secondaryLang = $market === 'pt' ? 'pt_pt' : ($market === 'en' ? 'en' : 'es_es');
        } else {
            $primaryLang = 'es_es';
            $secondaryLang = $market === 'pt' ? 'pt_pt' : ($market === 'en' ? 'en' : '');
        }

        $priceList = $priceListId > 0 ? $this->priceLists->get($priceListId) : null;
        $priceSuffix = $priceList ? ' · ' . $priceList['name'] : '';
        $title = 'Tarifa ' . $label . $priceSuffix . ' · ' . wp_date('d/m/Y', strtotime($date));

        try {
            $id = $this->tariffs->create_from_catalog(
                $title,
                $date,
                $scope,
                $preset,
                $priceListId,
                $primaryLang,
                $secondaryLang
            );
        } catch (Throwable $e) {
            $this->redirect(['tab' => 'tarifas', 'error' => rawurlencode($e->getMessage())]);
        }

        $this->redirect(['tab' => 'tarifa', 'id' => $id, 'created' => 1]);
    }

    public function tariff_save(): void
    {
        $id = absint($_POST['tariff_id'] ?? 0);
        $this->guard_post('frn_front_tariff_save_' . $id, 'frn_export_tariffs');

        try {
            $this->save_tariff_request($id);
        } catch (Throwable $e) {
            $this->redirect(['tab' => 'tarifa', 'id' => $id, 'error' => rawurlencode($e->getMessage())]);
        }

        $this->redirect(['tab' => 'tarifa', 'id' => $id, 'saved' => 1]);
    }

    public function save_settings(): void
    {
        $this->guard_post('frn_front_settings', 'frn_edit_prices');

        $fields = [
            'frn_tariff_company' => 'FRN Atlántico',
            'frn_tariff_address' => '',
            'frn_tariff_phone' => '',
            'frn_tariff_email' => '',
            'frn_tariff_web' => 'www.frnatlantico.com',
        ];

        foreach ($fields as $key => $default) {
            update_option(
                $key,
                sanitize_text_field((string) ($_POST[$key] ?? $default)),
                false
            );
        }

        $this->redirect(['tab' => 'tarifas', 'settings_saved' => 1]);
    }

    public function tariff_pdf(): void
    {
        $id = absint($_POST['tariff_id'] ?? $_GET['tariff_id'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->guard_post('frn_front_tariff_save_' . $id, 'frn_export_tariffs');
            try {
                $this->save_tariff_request($id);
            } catch (Throwable $e) {
                $this->redirect(['tab' => 'tarifa', 'id' => $id, 'error' => rawurlencode($e->getMessage())]);
            }
        } else {
            $this->guard_get('frn_front_tariff_pdf_' . $id);
        }

        $tariff = $this->tariffs->get_tariff($id);
        $lines = array_values(array_filter(
            $this->tariffs->get_lines($id, true),
            static fn(array $line): bool => (int) ($line['visible'] ?? 0) === 1
        ));

        if (!$tariff) { wp_die('Tarifa no encontrada.'); }

        $scopeKey = ($tariff['catalog_scope'] ?? '') === 'carne' ? 'carne' : 'pescado';
        $branding = $this->pdf_branding_state();
        if (empty($branding['logo']['valid']) || empty($branding[$scopeKey]['valid'])) {
            wp_die(
                'El diseño PDF no está configurado correctamente. Entra en Stock > Diseño PDF y sube la foto de ' .
                ($scopeKey === 'carne' ? 'Carne' : 'Pescado / Marisco') .
                ' y el logo oficial FRN antes de exportar.',
                'Diseño PDF incompleto',
                ['response' => 422]
            );
        }

        $autoload = FRN_SP_PATH . 'vendor/autoload.php';
        if (!file_exists($autoload)) {
            wp_die('Falta la librería PDF en el paquete instalado.');
        }

        require_once $autoload;

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isFontSubsettingEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->loadHtml($this->pdf_html($tariff, $lines), 'UTF-8');
        $dompdf->render();

        // Watermark and page numbering are drawn by Dompdf's canvas, outside
        // the HTML layout. This makes them deterministic on every page.
        $canvas = $dompdf->getCanvas();
        $watermarkPath = $this->pdf_branding_path('logo');
        if ($watermarkPath !== '') {
            $watermarkSize = @getimagesize($watermarkPath);
            $watermarkRatio = ($watermarkSize && !empty($watermarkSize[0]))
                ? ((float) $watermarkSize[1] / (float) $watermarkSize[0])
                : 0.42;
            $watermarkWidth = 300.0;
            $watermarkHeight = $watermarkWidth * $watermarkRatio;
            $watermarkX = (841.89 - $watermarkWidth) / 2;
            $watermarkY = (595.28 - $watermarkHeight) / 2;

            $canvas->page_script(
                static function ($pageNumber, $pageCount, $canvas, $fontMetrics) use ($watermarkPath, $watermarkX, $watermarkY, $watermarkWidth, $watermarkHeight): void {
                    $canvas->set_opacity(0.055);
                    $canvas->image($watermarkPath, $watermarkX, $watermarkY, $watermarkWidth, $watermarkHeight);
                    $canvas->set_opacity(1.0);
                }
            );
        }

        $fontMetrics = $dompdf->getFontMetrics();
        $pageFont = $fontMetrics->getFont('DejaVu Sans', 'normal');
        if ($pageFont) {
            $canvas->page_text(
                748,
                572,
                'Página {PAGE_NUM} de {PAGE_COUNT}',
                $pageFont,
                7,
                [0.38, 0.38, 0.38]
            );
        }

        $scope = ($tariff['catalog_scope'] ?? '') === 'carne' ? 'Carne' : 'Pescado-Marisco';

        $dompdf->stream(
            sanitize_file_name('FRN-Tarifa-' . $scope . '-' . $tariff['tariff_date'] . '.pdf'),
            ['Attachment' => true]
        );
        exit;
    }

    public function tariff_csv(): void
    {
        $id = absint($_POST['tariff_id'] ?? $_GET['tariff_id'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->guard_post('frn_front_tariff_save_' . $id, 'frn_export_tariffs');
            try {
                $this->save_tariff_request($id);
            } catch (Throwable $e) {
                $this->redirect(['tab' => 'tarifa', 'id' => $id, 'error' => rawurlencode($e->getMessage())]);
            }
        } else {
            $this->guard_get('frn_front_tariff_csv_' . $id);
        }

        $tariff = $this->tariffs->get_tariff($id);
        $lines = array_values(array_filter(
            $this->tariffs->get_lines($id, true),
            static fn(array $line): bool => (int) ($line['visible'] ?? 0) === 1
        ));

        if (!$tariff) { wp_die('Tarifa no encontrada.'); }

        $scope = ($tariff['catalog_scope'] ?? '') === 'carne' ? 'Carne' : 'Pescado-Marisco';

        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header(
            'Content-Disposition: attachment; filename="' .
            sanitize_file_name('FRN-Tarifa-' . $scope . '-' . $tariff['tariff_date'] . '.csv') .
            '"'
        );

        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        $showCost = current_user_can('frn_view_cost') && (int) ($tariff['show_cost'] ?? 0) === 1;
        $primaryLang = (string)($tariff['primary_lang'] ?? 'es_es');
        $secondaryLang = (string)($tariff['secondary_lang'] ?? '');
        if ($secondaryLang === $primaryLang) { $secondaryLang = ''; }

        $headers = ['Sección','Categoría comercial','Código',$this->language_label($primaryLang)];
        if ($secondaryLang !== '') { $headers[] = $this->language_label($secondaryLang); }
        $headers[] = 'Marca';
        if ($showCost) { $headers[] = 'Coste promedio'; }
        $headers[] = 'Stock';
        $headers[] = 'Precio';
        fputcsv($out, $headers, ';');

        foreach ($lines as $line) {
            $price = (float) ($line['display_price'] ?? 0);
            $csvRow = [
                (int) $line['incoming'] === 1 ? 'Próximos ingresos' : 'Productos',
                trim((string)($line['commercial_group'] ?? '')) !== '' ? (string)$line['commercial_group'] : 'SIN CATEGORÍA',
                $line['product_code'],
                $this->line_name_for_locale($line, $primaryLang),
            ];
            if ($secondaryLang !== '') {
                $csvRow[] = $this->line_name_for_locale($line, $secondaryLang);
            }
            $csvRow[] = $line['brand'];

            if ($showCost) {
                $cost = (float) ($line['display_cost'] ?? 0);
                $csvRow[] = ((int) ($line['show_cost'] ?? 0) === 1 && $cost > 0)
                    ? number_format($cost, 2, ',', '.') . ' €/kg'
                    : '';
            }

            $csvRow[] = ((int) $tariff['show_stock'] && (int) $line['show_stock'])
                ? $this->stock_text((float) $line['display_stock'], (string) $tariff['stock_mode'], (int) $line['incoming'] === 1, (string) ($line['unit'] ?? ''))
                : '';

            $csvRow[] = ((int) $tariff['show_price'])
                ? (((int) $line['show_price'] === 1 && $price > 0)
                    ? number_format($price, 2, ',', '.') . ' €/kg'
                    : 'Consultar precio')
                : '';

            fputcsv($out, $csvRow, ';');
        }

        fclose($out);
        exit;
    }

    private function save_tariff_request(int $id): void
    {
        $settings = is_array($_POST['settings'] ?? null) ? wp_unslash($_POST['settings']) : [];
        $lines = is_array($_POST['lines'] ?? null) ? wp_unslash($_POST['lines']) : [];
        $this->tariffs->save_tariff($id, $settings, $lines);
    }

    private function pdf_html(array $tariff, array $lines): string
    {
        $company = get_option('frn_tariff_company', 'FRN Atlántico');
        $address = get_option('frn_tariff_address', '');
        $phone = get_option('frn_tariff_phone', '');
        $email = get_option('frn_tariff_email', '');
        $web = get_option('frn_tariff_web', 'www.frnatlantico.com');
        $scopeKey = ($tariff['catalog_scope'] ?? '') === 'carne' ? 'carne' : 'pescado';
        $headerImage = $this->pdf_branding_data_uri($scopeKey);
        $logo = $this->pdf_branding_data_uri('logo');

        $showStock = (int) ($tariff['show_stock'] ?? 0) === 1;
        $showPrice = (int) ($tariff['show_price'] ?? 0) === 1;
        $showCost = current_user_can('frn_view_cost') && (int) ($tariff['show_cost'] ?? 0) === 1;
        $primaryLang = (string) ($tariff['primary_lang'] ?? ($scopeKey === 'carne' ? 'es_ar' : 'es_es'));
        $secondaryLang = (string) ($tariff['secondary_lang'] ?? '');
        if ($secondaryLang === $primaryLang) { $secondaryLang = ''; }
        $hasSecondary = $secondaryLang !== '';

        $columnCount = 3 + ($hasSecondary ? 1 : 0)
            + ($showCost ? 1 : 0) + ($showStock ? 1 : 0) + ($showPrice ? 1 : 0);

        $regular = array_values(array_filter(
            $lines,
            static fn(array $line): bool =>
                (int) $line['incoming'] !== 1 &&
                (int) $line['visible'] === 1
        ));
        $incoming = array_values(array_filter(
            $lines,
            static fn(array $line): bool =>
                (int) $line['incoming'] === 1 &&
                (int) $line['visible'] === 1
        ));

        $rowsHtml = $this->pdf_rows($regular, $tariff);

        $rowsHtml .= '<tr class="incoming-title"><td colspan="' . $columnCount . '">PRÓXIMOS INGRESOS</td></tr>';
        if ($incoming) {
            $rowsHtml .= $this->pdf_rows($incoming, $tariff);
        } else {
            $rowsHtml .= '<tr class="incoming-empty"><td colspan="' . $columnCount . '">Actualmente no hay próximos ingresos informados.</td></tr>';
        }

        $headers = '<th class="code">Código</th>'
            . '<th class="product">' . esc_html($this->language_label($primaryLang)) . '</th>';
        if ($hasSecondary) {
            $headers .= '<th class="product">' . esc_html($this->language_label($secondaryLang)) . '</th>';
        }
        $headers .= '<th class="brand">Marca</th>';

        if ($showCost) { $headers .= '<th class="num-head">Coste promedio</th>'; }
        if ($showStock) { $headers .= '<th class="num-head">Stock</th>'; }
        if ($showPrice) { $headers .= '<th class="num-head">Precio</th>'; }

        $contact = implode(' · ', array_filter([$address, $phone, $email, $web]));
        $date = mysql2date('d/m/Y', $tariff['tariff_date'] . ' 00:00:00');
        $productWidth = $hasSecondary ? 25 : 48;
        $headerPosition = (string)get_option('frn_pdf_header_position_' . $scopeKey, 'center');
        $headerTop = match ($headerPosition) {
            'top' => '0px',
            'bottom' => '-122px',
            default => '-61px',
        };

        return '<!doctype html><html><head><meta charset="UTF-8"><style>
            @page{margin:16px 18px 50px}
            body{font-family:DejaVu Sans,Arial,sans-serif;color:#161a1e;font-size:7.7pt}
            .header{position:relative;height:128px;border-bottom:3px solid #b28a42;background-color:#07131a;overflow:hidden}
            .header-photo{position:absolute;left:0;top:' . $headerTop . ';width:100%;height:auto}
            .header-shade{position:absolute;left:0;top:0;width:100%;height:128px;background:rgba(0,0,0,.30)}
            .header-logo{position:absolute;left:16px;top:8px;width:128px;height:auto}
            .header-title{position:absolute;left:20px;top:68px;color:#fff;font-family:DejaVu Serif,serif;font-size:21pt;line-height:1}
            .header-subtitle{position:absolute;left:21px;top:102px;color:#fff;font-family:DejaVu Sans,Arial,sans-serif;font-size:7.5pt}
            .header-scope{position:absolute;right:20px;top:13px;color:#e1bd70;font-size:11.5pt;font-weight:bold;letter-spacing:.8px;text-transform:uppercase}
            .header-date-dynamic{position:absolute;right:20px;top:34px;color:#fff;font-size:8pt;font-weight:600;text-align:right;white-space:nowrap}
            table{width:100%;border-collapse:collapse;table-layout:fixed;margin-top:10px}
            th{background:#172535;color:#fff;padding:5px 5px;text-align:left;font-size:6.2pt;text-transform:uppercase;letter-spacing:.12px}
            th.code{width:8%}
            th.product{width:' . $productWidth . '%}
            th.brand{width:11%}
            th.num-head{width:10%;text-align:right}
            td{padding:4px 5px;border-bottom:.4px solid #d5d9dc;vertical-align:middle;line-height:1.14;font-size:7.1pt}
            tr.product-row.row-light td{background:#ffffff}
            tr.product-row.row-dark td{background:#edf0f2}
            td.code-cell{white-space:nowrap}
            td.num{text-align:right;white-space:nowrap}
            td.price{font-weight:bold;white-space:nowrap}
            td.product-cell{font-weight:700;word-wrap:break-word}
            td.translation-cell{font-weight:600;word-wrap:break-word}
            td.brand-cell{word-wrap:break-word}
            .group-title td{color:#fff!important;font-weight:700;font-size:7.3pt;letter-spacing:.35px;padding:5px 7px;border-bottom:0}
            .incoming-title td{background:#111820!important;color:#d9b563;font-weight:bold;letter-spacing:1px;padding:6px}
            .incoming-empty td{background:#f4f0e8;color:#777;font-style:italic;padding:7px}
            .offer-badge{display:inline-block;width:47px;height:11px;vertical-align:middle;margin-left:3px}
            .terms{margin-top:8px;color:#666;font-size:6pt;font-style:italic;text-align:center}
            .footer{position:fixed;left:0;right:0;bottom:-36px;height:34px;border-top:1px solid #b28a42;color:#172535;text-align:center;line-height:1.16;padding-top:4px}
            .footer-brand{font-size:9pt;font-weight:bold;letter-spacing:1.5px}
            .footer-contact{margin-top:2px;font-size:6.8pt;font-weight:500;color:#303943}
        </style></head><body>
        <div class="header">
            ' . ($headerImage ? '<img class="header-photo ' . esc_attr($scopeKey) . '" src="' . esc_attr($headerImage) . '" alt="">' : '') . '
            <div class="header-shade"></div>
            <img class="header-logo" src="' . esc_attr($logo) . '" alt="FRN">
            <div class="header-scope">' . esc_html($scopeKey === 'carne' ? 'CARNE' : 'PESCADO Y MARISCO') . '</div>
            <div class="header-date-dynamic">Fecha: ' . esc_html($date) . '</div>
            <div class="header-title">' . esc_html($scopeKey === 'carne' ? 'Tarifa Carne' : 'Tarifa Pescado y marisco') . '</div>
            <div class="header-subtitle">' . esc_html($scopeKey === 'carne' ? 'Productos de calidad para tu negocio' : 'Los mejores productos del mar, siempre a tu alcance') . '</div>
        </div>
        <table>
            <thead><tr>' . $headers . '</tr></thead>
            <tbody>' . $rowsHtml . '</tbody>
        </table>
        <div class="terms">Stock sujeto a disponibilidad en el momento de confirmación. Precios y condiciones sujetos a validación comercial.</div>
        <div class="footer">
            <div class="footer-brand">' . esc_html($company) . '</div>
            <div class="footer-contact">' . esc_html($contact) . '</div>
        </div>
        </body></html>';
    }

    private function pdf_rows(array $lines, array $tariff): string
    {
        $html = '';
        $showStock = (int) ($tariff['show_stock'] ?? 0) === 1;
        $showPrice = (int) ($tariff['show_price'] ?? 0) === 1;
        $showCost = current_user_can('frn_view_cost') && (int) ($tariff['show_cost'] ?? 0) === 1;
        $primaryLang = (string) ($tariff['primary_lang'] ?? 'es_es');
        $secondaryLang = (string) ($tariff['secondary_lang'] ?? '');
        if ($secondaryLang === $primaryLang) { $secondaryLang = ''; }
        $hasSecondary = $secondaryLang !== '';
        $columnCount = 3 + ($hasSecondary ? 1 : 0)
            + ($showCost ? 1 : 0) + ($showStock ? 1 : 0) + ($showPrice ? 1 : 0);

        $index = 0;
        $lastGroup = null;

        foreach ($lines as $line) {
            if ((int) ($line['visible'] ?? 0) !== 1) { continue; }

            $group = trim((string) ($line['commercial_group'] ?? ''));
            $groupKey = $group !== '' ? $group : '__ungrouped__';
            if ((int)($line['incoming'] ?? 0) !== 1 && $groupKey !== $lastGroup) {
                if ($group === '') {
                    $color = '#59636E';
                    $groupTitle = 'SIN CATEGORÍA';
                } else {
                    $color = sanitize_hex_color((string)($line['group_color'] ?? '')) ?: '#59636E';
                    $groupTitle = $this->group_display_name($group, $primaryLang);
                    if ($hasSecondary) {
                        $secondaryGroup = $this->group_display_name($group, $secondaryLang);
                        if ($secondaryGroup !== '' && strcasecmp($secondaryGroup, $groupTitle) !== 0) {
                            $groupTitle .= ' · ' . $secondaryGroup;
                        }
                    }
                }
                $html .= '<tr class="group-title"><td colspan="' . $columnCount . '" style="background:' .
                    esc_attr($color) . '">' . esc_html($groupTitle) . '</td></tr>';
                $lastGroup = $groupKey;
            }

            $class = $index % 2 === 0 ? 'row-light' : 'row-dark';
            $index++;

            $offer = (int) ($line['featured'] ?? 0) === 1
                ? ' <img class="offer-badge" src="' . esc_attr($this->offer_badge_data_uri()) . '" alt="OFERTA">'
                : '';

            $primaryName = $this->line_name_for_locale($line, $primaryLang);
            $secondaryName = $hasSecondary ? $this->line_name_for_locale($line, $secondaryLang) : '';

            $html .= '<tr class="product-row ' . $class . '">'
                . '<td class="code-cell">' . esc_html(wp_check_invalid_utf8((string) $line['product_code'], true)) . '</td>'
                . '<td class="product-cell">' . esc_html($primaryName) . $offer . '</td>';

            if ($hasSecondary) {
                $html .= '<td class="translation-cell">' . esc_html($secondaryName) . '</td>';
            }

            $html .= '<td class="brand-cell">' . esc_html(wp_check_invalid_utf8((string) $line['brand'], true)) . '</td>';

            if ($showCost) {
                $costValue = (float) ($line['display_cost'] ?? 0);
                $cost = ((int) ($line['show_cost'] ?? 0) === 1 && $costValue > 0)
                    ? number_format($costValue, 2, ',', '.') . ' €/kg'
                    : '';
                $html .= '<td class="num">' . esc_html($cost) . '</td>';
            }

            if ($showStock) {
                $stock = (int) ($line['show_stock'] ?? 0)
                    ? $this->stock_text(
                        (float) ($line['display_stock'] ?? 0),
                        (string) ($tariff['stock_mode'] ?? 'available'),
                        (int) ($line['incoming'] ?? 0) === 1,
                        (string) ($line['unit'] ?? '')
                    )
                    : '';
                $html .= '<td class="num">' . esc_html($stock) . '</td>';
            }

            if ($showPrice) {
                $priceValue = (float) ($line['display_price'] ?? 0);
                $price = ((int) ($line['show_price'] ?? 0) === 1 && $priceValue > 0)
                    ? number_format($priceValue, 2, ',', '.') . ' €/kg'
                    : 'Consultar precio';
                $html .= '<td class="num price">' . esc_html($price) . '</td>';
            }

            $html .= '</tr>';
        }

        return $html;
    }

    private function language_label(string $locale): string
    {
        return match ($locale) {
            'es_ar' => 'Argentina / original',
            'es_es' => 'España',
            'pt_pt' => 'Português',
            'en' => 'English',
            default => 'Producto',
        };
    }

    private function line_name_for_locale(array $line, string $locale): string
    {
        $field = match ($locale) {
            'es_ar' => 'name_es_ar',
            'es_es' => 'name_es_es',
            'pt_pt' => 'name_pt_pt',
            'en' => 'name_en',
            default => 'product_name',
        };

        $value = trim((string) ($line[$field] ?? ''));
        if ($value === '') {
            $value = trim((string) ($line['product_name'] ?? ''));
        }
        return wp_check_invalid_utf8($value, true);
    }

    private function group_display_name(string $group, string $locale): string
    {
        $category = '';
        foreach (['carne','pescado-marisco'] as $candidate) {
            if ($this->catalog->group_meta($candidate, $group)) {
                $category = $candidate;
                break;
            }
        }
        return $category !== ''
            ? $this->catalog->group_label($category, $group, $locale)
            : $group;
    }

    private function offer_badge_data_uri(): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="180" height="42" viewBox="0 0 180 42">'
            . '<rect x="0.5" y="0.5" width="179" height="41" rx="8" fill="#d72e27" stroke="#9f1f1a"/>'
            . '<path d="M22 34c-8-4-10-11-6-17 2-3 5-5 6-10 5 5 8 9 6 15 3-2 5-5 5-8 5 5 6 12 2 17-3 4-8 6-13 3z" fill="#ff9f1c"/>'
            . '<path d="M24 32c-4-2-5-5-3-8 1-2 3-3 3-6 3 3 4 6 3 9 2-1 3-2 4-4 2 4 1 8-2 10-2 1-4 1-5-1z" fill="#fff4cf"/>'
            . '<circle cx="25" cy="28" r="2.4" fill="#ffd15a"/>'
            . '<text x="47" y="28" font-family="DejaVu Sans,Arial,sans-serif" font-size="22" font-weight="700" fill="#fff">OFERTA</text>'
            . '</svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    private function pdf_branding_state(): array
    {
        $map = [
            'carne' => 'frn_pdf_header_carne_id',
            'pescado' => 'frn_pdf_header_pescado_id',
            'logo' => 'frn_pdf_logo_id',
        ];

        $state = [];
        foreach ($map as $key => $option) {
            $id = (int) get_option($option, 0);
            $path = $id > 0 ? get_attached_file($id) : '';
            $image = ($path && is_readable($path)) ? @getimagesize($path) : false;
            $mime = $id > 0 ? (string) get_post_mime_type($id) : '';
            $valid = (bool) $image && in_array($mime, ['image/jpeg','image/png'], true);

            $state[$key] = [
                'id' => $id,
                'path' => $valid ? (string) $path : '',
                'url' => $id > 0 ? (string) wp_get_attachment_image_url($id, 'medium') : '',
                'mime' => $mime,
                'valid' => $valid,
            ];
        }

        return $state;
    }

    private function pdf_branding_path(string $key): string
    {
        $state = $this->pdf_branding_state();
        return !empty($state[$key]['valid']) ? (string) $state[$key]['path'] : '';
    }

    private function pdf_branding_data_uri(string $key): string
    {
        $state = $this->pdf_branding_state();
        if (empty($state[$key]['valid'])) { return ''; }

        $path = (string) $state[$key]['path'];
        $mime = (string) $state[$key]['mime'];
        $bytes = @file_get_contents($path);
        if ($bytes === false || $bytes === '') { return ''; }

        return 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }

    private function stock_text(float $stock, string $mode, bool $incoming = false, string $unit = ''): string
    {
        if ($incoming && $stock <= 0) {
            return 'Próximamente';
        }

        $unit = trim($unit);
        $unitLabel = '';
        if ($unit !== '') {
            $normalized = strtolower(remove_accents($unit));
            $unitLabel = str_contains($normalized, 'unidad') || in_array($normalized, ['ud','uds'], true)
                ? ' ud'
                : (str_contains($normalized, 'kg') ? ' kg' : ' ' . $unit);
        }

        return match ($mode) {
            'rounded' => $stock > 0 ? number_format(round($stock), 0, ',', '.') . $unitLabel : '',
            'available' => $stock > 0 ? 'Disponible' : '',
            'hidden' => '',
            default => $stock > 0 ? number_format($stock, 2, ',', '.') . $unitLabel : '',
        };
    }

    private function messages(): array
    {
        $messages = [];

        if (isset($_GET['stock_published'])) {
            $messages[] = [
                'success',
                'Importación publicada: ' . (int) $_GET['stock_published'] . ' referencias. Campos actualizados: ' .
                (!empty($_GET['updated_fields']) ? sanitize_text_field(wp_unslash($_GET['updated_fields'])) : 'según selección') . '.',
            ];
        }
        if (isset($_GET['prices_published'])) {
            $messages[] = [
                'success',
                'Tarifa de precios guardada. Ya puedes seleccionarla al crear un PDF.',
            ];
        }
        if (isset($_GET['saved'])) {
            $messages[] = ['success', 'Cambios guardados.'];
        }
        if (isset($_GET['created'])) {
            $messages[] = ['success', 'Tarifa creada. Ya puedes revisarla y descargar el PDF.'];
        }
        if (isset($_GET['settings_saved'])) {
            $messages[] = ['success', 'Datos comerciales del PDF guardados.'];
        }
        if (isset($_GET['user_saved'])) {
            $messages[] = ['success', 'Usuario FRN guardado.'];
        }
        if (isset($_GET['branding_saved'])) {
            $messages[] = ['success', 'Diseño PDF guardado y validado.'];
        }
        if (isset($_GET['translations_saved'])) {
            $messages[] = ['success', 'Traducciones y asignaciones de categoría guardadas.'];
        }
        if (isset($_GET['groups_saved'])) {
            $messages[] = ['success', 'Maestro de categorías guardado.'];
        }
        if (!empty($_GET['error'])) {
            $messages[] = ['error', sanitize_text_field(wp_unslash($_GET['error']))];
        }

        return $messages;
    }

    private function guard_capability(): void
    {
        if (!is_user_logged_in() || !current_user_can('frn_access_tool')) {
            wp_die('No autorizado.', 403);
        }
    }

    private function guard_post(string $nonce, string $capability = 'frn_access_tool'): void
    {
        $this->guard_capability();
        if (!current_user_can($capability)) { wp_die('No autorizado.', 403); }
        check_admin_referer($nonce);
    }

    private function guard_get(string $nonce, string $capability = 'frn_export_tariffs'): void
    {
        $this->guard_capability();
        if (!current_user_can($capability)) { wp_die('No autorizado.', 403); }
        check_admin_referer($nonce);
    }

    private function redirect(array $args): never
    {
        wp_safe_redirect(add_query_arg($args, home_url('/stock/')));
        exit;
    }

    private function number(mixed $value): float
    {
        if (is_int($value) || is_float($value)) { return (float) $value; }

        $clean = preg_replace('/[^0-9,.-]/', '', (string) $value);

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

        return is_numeric($clean) ? (float) $clean : 0.0;
    }
}
