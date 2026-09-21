<?php

if (!defined('ABSPATH')) {
    exit;
}

final class FRN_Stock_Prices
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public static function activate(): void
    {
        add_option('frn_sp_whatsapp_number', '34624354950', '', false);
        add_option('frn_tariff_company', 'FRN Atlántico', '', false);
        add_option('frn_tariff_address', '', '', false);
        add_option('frn_tariff_phone', '', '', false);
        add_option('frn_tariff_email', '', '', false);
        add_option('frn_tariff_web', 'www.frnatlantico.com', '', false);

        self::ensure_roles();
        update_option('frn_sp_catalog_protection_enabled', true, false);
        update_option('frn_sp_version', FRN_SP_VERSION, false);

        FRN_Catalog_Repository::create_table();
        FRN_Price_List_Repository::create_tables();
        FRN_Tariff_Repository::create_tables();

        self::instance()->register_routes();
        flush_rewrite_rules();
    }

    public static function ensure_roles(): void
    {
        $roles = [
            'frn_administrator' => [
                'label' => 'FRN Administrador',
                'caps' => ['frn_access_tool','frn_manage_stock','frn_edit_stock','frn_edit_prices','frn_view_cost','frn_export_tariffs','frn_manage_users'],
            ],
            'frn_stock' => [
                'label' => 'FRN Stock',
                'caps' => ['frn_access_tool','frn_manage_stock','frn_edit_stock','frn_export_tariffs'],
            ],
            'frn_director_comercial' => [
                'label' => 'FRN Director Comercial',
                'caps' => ['frn_access_tool','frn_manage_stock','frn_edit_prices','frn_view_cost','frn_export_tariffs'],
            ],
            'frn_comercial' => [
                'label' => 'FRN Comercial',
                'caps' => ['frn_access_tool','frn_manage_stock','frn_export_tariffs'],
            ],
            'frn_consulta' => [
                'label' => 'FRN Consulta',
                'caps' => ['frn_access_tool','frn_manage_stock','frn_export_tariffs'],
            ],
        ];

        foreach ($roles as $slug => $definition) {
            $role = get_role($slug);
            if (!$role) {
                $role = add_role($slug, $definition['label'], ['read' => true]);
            }
            if (!$role) { continue; }

            $role->add_cap('read');
            foreach ($definition['caps'] as $cap) {
                $role->add_cap($cap);
            }
        }

        $admin = get_role('administrator');
        if ($admin) {
            foreach (['frn_access_tool','frn_manage_stock','frn_edit_stock','frn_edit_prices','frn_view_cost','frn_export_tariffs','frn_manage_users'] as $cap) {
                $admin->add_cap($cap);
            }
        }
    }

    public function boot(): void
    {
        $installed = (string) get_option('frn_sp_version', '');

        if ($installed !== FRN_SP_VERSION) {
            FRN_Catalog_Repository::create_table();
            FRN_Price_List_Repository::create_tables();
            FRN_Tariff_Repository::create_tables();
            self::ensure_roles();

            if ($installed === '' || version_compare($installed, '0.9.0', '<')) {
                global $wpdb;
                $wpdb->query('UPDATE ' . FRN_Catalog_Repository::table() . ' SET visible = 0 WHERE incoming = 0 AND stock_kg <= 0');
            }

            update_option('frn_sp_catalog_protection_enabled', true, false);
            update_option('frn_sp_version', FRN_SP_VERSION, false);

            add_action('init', function (): void {
                $this->register_routes();
                flush_rewrite_rules();
            }, 99);
        }

        add_action('init', [$this, 'register_routes']);
        add_action('parse_request', [$this, 'recognize_routes'], 1);
        add_filter('query_vars', [$this, 'query_vars']);
        add_filter('redirect_canonical', [$this, 'prevent_redirect']);
        add_filter('template_include', [$this, 'template_include']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_filter('wp_robots', [$this, 'robots']);

        add_filter('show_admin_bar', [$this, 'hide_admin_bar_for_commercial']);
        add_action('admin_init', [$this, 'block_backend_for_commercial']);

        (new FRN_Frontend_App())->boot();
    }

    public function register_routes(): void
    {
        add_rewrite_rule('^stock/acceso/?$', 'index.php?frn_tool=login', 'top');
        add_rewrite_rule('^stock/?$', 'index.php?frn_tool=app', 'top');
        add_rewrite_tag('%frn_tool%', '([^&]+)');
    }

    public function query_vars(array $vars): array
    {
        $vars[] = 'frn_tool';
        return $vars;
    }

    public function recognize_routes(WP $wp): void
    {
        $path = trim((string) parse_url(wp_unslash($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/');
        $homePath = trim((string) parse_url(home_url('/'), PHP_URL_PATH), '/');

        if ($homePath !== '' && ($path === $homePath || str_starts_with($path, $homePath . '/'))) {
            $path = trim(substr($path, strlen($homePath)), '/');
        }

        if (in_array($path, ['stock/pescado-marisco','stock/carne'], true)) {
            wp_safe_redirect(home_url('/stock/'));
            exit;
        }

        $routes = [
            'stock/acceso' => 'login',
            'stock' => 'app',
        ];

        if (!isset($routes[$path])) { return; }

        $wp->query_vars['frn_tool'] = $routes[$path];
        unset($wp->query_vars['error'], $wp->query_vars['name'], $wp->query_vars['pagename'], $wp->query_vars['page']);
    }

    public function prevent_redirect(string|false $redirect): string|false
    {
        return get_query_var('frn_tool') ? false : $redirect;
    }

    public function robots(array $robots): array
    {
        if (get_query_var('frn_tool')) {
            $robots['noindex'] = true;
            $robots['nofollow'] = true;
            $robots['noarchive'] = true;
        }

        return $robots;
    }

    public function template_include(string $template): string
    {
        $tool = get_query_var('frn_tool');

        if (!in_array($tool, ['login','app'], true)) {
            return $template;
        }

        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        nocache_headers();

        global $wp_query;
        $wp_query->is_404 = false;
        $wp_query->is_home = false;
        status_header(200);

        if ($tool === 'login') {
            if (is_user_logged_in() && current_user_can('frn_access_tool')) {
                wp_safe_redirect(home_url('/stock/'));
                exit;
            }

            return FRN_SP_PATH . 'templates/login.php';
        }

        if (!is_user_logged_in()) {
            $requested = home_url('/stock/');
            $login = add_query_arg('redirect_to', $requested, home_url('/stock/acceso/'));
            wp_safe_redirect($login);
            exit;
        }

        if (!current_user_can('frn_access_tool')) {
            wp_die('Este usuario no tiene acceso a la herramienta interna FRN.', 'Acceso restringido', ['response' => 403]);
        }

        return FRN_SP_PATH . 'templates/app-loader.php';
    }

    public function enqueue_assets(): void
    {
        if (!in_array(get_query_var('frn_tool'), ['login','app'], true)) { return; }

        wp_enqueue_style('frn-stock-prices', FRN_SP_URL . 'assets/catalog.css', [], FRN_SP_VERSION);

        if (get_query_var('frn_tool') === 'app') {
            wp_enqueue_script('frn-stock-prices-app', FRN_SP_URL . 'assets/app.js', [], FRN_SP_VERSION, true);
        }
    }

    public function hide_admin_bar_for_commercial(bool $show): bool
    {
        if (is_user_logged_in() && current_user_can('frn_access_tool') && !current_user_can('manage_options')) {
            return false;
        }

        return $show;
    }

    public function block_backend_for_commercial(): void
    {
        if (!is_user_logged_in() || !current_user_can('frn_access_tool') || current_user_can('manage_options')) {
            return;
        }

        global $pagenow;
        $allowed = ['admin-post.php', 'admin-ajax.php', 'async-upload.php'];

        if (in_array((string) $pagenow, $allowed, true)) {
            return;
        }

        wp_safe_redirect(home_url('/stock/'));
        exit;
    }
}
