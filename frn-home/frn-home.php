<?php
/**
 * Plugin Name: FRN Home
 * Description: Portada comercial de FRN Atlántico con acceso al catálogo de stock y precios.
 * Version: 1.0.0
 * Author: FRN Atlántico
 * Requires at least: 6.4
 * Requires PHP: 8.1
 */

if (!defined('ABSPATH')) { exit; }

define('FRN_HOME_VERSION', '1.0.0');
define('FRN_HOME_PATH', plugin_dir_path(__FILE__));
define('FRN_HOME_URL', plugin_dir_url(__FILE__));

add_filter('template_include', static function (string $template): string {
    if (!is_front_page()) { return $template; }
    status_header(200);
    return FRN_HOME_PATH . 'templates/home.php';
}, 99999);

add_action('wp_enqueue_scripts', static function (): void {
    if (!is_front_page()) { return; }
    wp_enqueue_style('frn-home', FRN_HOME_URL . 'assets/home.css', [], FRN_HOME_VERSION);
});
