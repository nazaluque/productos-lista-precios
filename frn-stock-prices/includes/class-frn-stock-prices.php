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
        FRN_Tariff_Repository::create_tables();

        self::instance()->register_routes();
        flush_rewrite_rules();
    }

    public static function ensure_roles(): void
    {
        $role = get_role('frn_comercial');
        if (!$role) {
            $role = add_role('frn_comercial', 'FRN Comercial', [
                'read' => true,
                'frn_manage_stock' => true,
            ]);
        } elseif (!$role->has_cap('frn_manage_stock')) {
            $role->add_cap('frn_manage_stock');
        }

        $admin = get_role('administrator');
        if ($admin && !$admin->has_cap('frn_manage_stock')) {
            $admin->add_cap('frn_manage_stock');
        }
    }

    public function boot(): void
    {
        $installed = (string) get_option('frn_sp_version', '');

        if ($installed !== FRN_SP_VERSION) {
            FRN_Catalog_Repository::create_table();
            FRN_Tariff_Repository::create_tables();
            self::ensure_roles();

            if ($installed === '' || version_compare($installed, '0.7.0', '<')) {
                update_option('frn_sp_catalog_protection_enabled', true, false);
            }

            update_option('frn_sp_version', FRN_SP_VERSION, false);

            add_action('init', function (): void {
                $this->register_routes();
                flush_rewrite_rules();
            }, 99);
        }

        add_action('init', [$this, 'register_routes']);
        add_action('parse_request', [$this, 'recognize_catalog_path'], 1);
        add_filter('query_vars', [$this, 'query_vars']);
        add_filter('redirect_canonical', [$this, 'prevent_catalog_redirect']);
        add_filter('template_include', [$this, 'template_include']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_filter('wp_robots', [$this, 'robots']);
        add_shortcode('frn_home_buttons', [$this, 'home_buttons']);

        if (is_admin()) {
            (new FRN_Admin_Importer())->boot();
            (new FRN_Tariff_Manager())->boot();
        }
    }

    public function register_routes(): void
    {
        add_rewrite_rule('^stock/acceso/?$', 'index.php?frn_catalog=login', 'top');
        add_rewrite_rule('^stock/?$', 'index.php?frn_catalog=hub', 'top');
        add_rewrite_rule('^stock/pescado-marisco/?$', 'index.php?frn_catalog=pescado-marisco', 'top');
        add_rewrite_rule('^stock/carne/?$', 'index.php?frn_catalog=carne', 'top');
        add_rewrite_tag('%frn_catalog%', '([^&]+)');
    }

    public function query_vars(array $vars): array
    {
        $vars[] = 'frn_catalog';
        return $vars;
    }

    public function recognize_catalog_path(WP $wp): void
    {
        $path = trim((string) parse_url(wp_unslash($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/');
        $home_path = trim((string) parse_url(home_url('/'), PHP_URL_PATH), '/');

        if ($home_path !== '' && ($path === $home_path || str_starts_with($path, $home_path . '/'))) {
            $path = trim(substr($path, strlen($home_path)), '/');
        }

        $routes = [
            'stock/acceso' => 'login',
            'stock' => 'hub',
            'stock/pescado-marisco' => 'pescado-marisco',
            'stock/carne' => 'carne',
        ];

        if (!isset($routes[$path])) { return; }

        $wp->query_vars['frn_catalog'] = $routes[$path];
        unset($wp->query_vars['error'], $wp->query_vars['name'], $wp->query_vars['pagename'], $wp->query_vars['page']);
    }

    public function prevent_catalog_redirect(string|false $redirect): string|false
    {
        return get_query_var('frn_catalog') ? false : $redirect;
    }

    public function robots(array $robots): array
    {
        if (get_query_var('frn_catalog')) {
            $robots['noindex'] = true;
            $robots['nofollow'] = true;
            $robots['noarchive'] = true;
        }

        return $robots;
    }

    public function template_include(string $template): string
    {
        $catalog = get_query_var('frn_catalog');

        if (!in_array($catalog, ['login', 'hub', 'pescado-marisco', 'carne'], true)) {
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

        if ($catalog === 'login') {
            if (is_user_logged_in() && current_user_can('frn_manage_stock')) {
                wp_safe_redirect(home_url('/stock/'));
                exit;
            }
            return FRN_SP_PATH . 'templates/login.php';
        }

        if ((bool) get_option('frn_sp_catalog_protection_enabled', true)) {
            if (!is_user_logged_in()) {
                $requested = home_url('/' . trim((string) parse_url(wp_unslash($_SERVER['REQUEST_URI'] ?? '/stock/'), PHP_URL_PATH), '/') . '/');
                $login = add_query_arg('redirect_to', $requested, home_url('/stock/acceso/'));
                wp_safe_redirect($login);
                exit;
            }

            if (!current_user_can('frn_manage_stock')) {
                wp_die('Este usuario no tiene acceso a la herramienta interna FRN.', 'Acceso restringido', ['response' => 403]);
            }
        }

        return FRN_SP_PATH . ($catalog === 'hub' ? 'templates/hub.php' : 'templates/catalog.php');
    }

    public function enqueue_assets(): void
    {
        if (!in_array(get_query_var('frn_catalog'), ['login', 'hub', 'pescado-marisco', 'carne'], true)) { return; }

        wp_enqueue_style('frn-stock-prices', FRN_SP_URL . 'assets/catalog.css', [], FRN_SP_VERSION);

        if (in_array(get_query_var('frn_catalog'), ['pescado-marisco', 'carne'], true)) {
            wp_enqueue_script('frn-stock-prices', FRN_SP_URL . 'assets/catalog.js', [], FRN_SP_VERSION, true);
        }
    }

    public function home_buttons(): string
    {
        return sprintf(
            '<div class="frn-home-stock-links"><a href="%s">Productos, stock y precios</a></div>',
            esc_url(home_url('/stock/'))
        );
    }
}
