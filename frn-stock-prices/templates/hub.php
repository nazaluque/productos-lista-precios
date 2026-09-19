<?php
if (!defined('ABSPATH')) { exit; }

$currentUser = wp_get_current_user();
$whatsapp = preg_replace('/\D+/', '', (string) get_option('frn_sp_whatsapp_number', '34624354950'));
$importUrl = admin_url('admin.php?page=frn-stock-prices');
$tariffsUrl = admin_url('admin.php?page=frn-tariffs');
$logoutUrl = wp_logout_url(home_url('/'));
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Herramienta interna · FRN Atlántico</title>
    <?php wp_head(); ?>
</head>
<body class="frn-catalog-body">
<header class="frn-top">
    <a href="<?php echo esc_url(home_url('/')); ?>" class="frn-brand"><strong>FRN</strong><span>ATLÁNTICO</span></a>
    <nav>
        <span class="frn-user">Hola, <?php echo esc_html($currentUser->display_name ?: $currentUser->user_login); ?></span>
        <a href="<?php echo esc_url(home_url('/')); ?>">Inicio .es</a>
        <a href="https://www.frnatlantico.com/" target="_blank" rel="noopener">Web oficial .com</a>
        <a href="<?php echo esc_url($logoutUrl); ?>">Cerrar sesión</a>
    </nav>
</header>

<main class="frn-hub frn-internal-hub">
    <section class="frn-hub-intro">
        <p>Herramienta interna FRN</p>
        <h1>Stock, precios<br>y tarifas.</h1>
        <span>Importa el Excel semanal, revisa los datos y genera la tarifa comercial sin modificar el archivo original.</span>
    </section>

    <section class="frn-internal-grid">
        <a href="<?php echo esc_url($importUrl); ?>">
            <small>Paso 01</small>
            <h2>Importar<br>Excel semanal</h2>
            <p>Actualiza stock y precios desde el archivo recibido.</p>
            <span>Abrir importador →</span>
        </a>

        <a href="<?php echo esc_url($tariffsUrl); ?>">
            <small>Paso 02</small>
            <h2>Crear<br>tarifa PDF</h2>
            <p>Edita precios, stock visible, ofertas y genera el documento comercial.</p>
            <span>Abrir tarifas →</span>
        </a>

        <a href="<?php echo esc_url(home_url('/stock/pescado-marisco/')); ?>">
            <small>Vista interna</small>
            <h2>Pescado<br>y marisco</h2>
            <p>Comprueba cómo están actualmente las referencias de mar.</p>
            <span>Ver catálogo →</span>
        </a>

        <a href="<?php echo esc_url(home_url('/stock/carne/')); ?>">
            <small>Vista interna</small>
            <h2>Carne</h2>
            <p>Comprueba cómo están actualmente las referencias de carne.</p>
            <span>Ver catálogo →</span>
        </a>
    </section>

    <section class="frn-internal-note">
        <strong>Flujo de los lunes</strong>
        <span>Excel Odoo → previsualizar → publicar datos → crear tarifa → ajustar → descargar PDF.</span>
    </section>
</main>

<footer class="frn-footer">
    <span>FRN Atlántico · Herramienta interna</span>
    <a href="https://wa.me/<?php echo esc_attr($whatsapp); ?>" target="_blank" rel="noopener">WhatsApp comercial</a>
    <a href="<?php echo esc_url($logoutUrl); ?>">Cerrar sesión</a>
</footer>
<?php wp_footer(); ?>
</body>
</html>
