<?php
/**
 * Plugin Name: FRN Stock & Prices
 * Description: Stock, precios y tarifas semanales con importación Excel y exportación PDF para FRN Atlántico.
 * Version: 0.6.0
 * Author: FRN Atlántico
 * Requires at least: 6.4
 * Requires PHP: 8.1
 */

if (!defined('ABSPATH')) {
    exit;
}

define('FRN_SP_VERSION', '0.6.0');
define('FRN_SP_PATH', plugin_dir_path(__FILE__));
define('FRN_SP_URL', plugin_dir_url(__FILE__));

require_once FRN_SP_PATH . 'includes/class-frn-stock-prices.php';
require_once FRN_SP_PATH . 'includes/class-frn-catalog-repository.php';
require_once FRN_SP_PATH . 'includes/class-frn-tariff-repository.php';
require_once FRN_SP_PATH . 'includes/class-frn-admin-importer.php';
require_once FRN_SP_PATH . 'includes/class-frn-tariff-manager.php';

register_activation_hook(__FILE__, ['FRN_Stock_Prices', 'activate']);
add_action('plugins_loaded', static function (): void {
    FRN_Stock_Prices::instance()->boot();
});
