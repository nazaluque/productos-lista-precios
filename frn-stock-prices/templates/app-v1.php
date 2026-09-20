<?php
if (!defined('ABSPATH')) { exit; }

$currentUser = wp_get_current_user();
$whatsapp = preg_replace('/\D+/', '', (string) get_option('frn_sp_whatsapp_number', '34624354950'));
$logoutUrl = wp_logout_url(home_url('/'));
$postUrl = admin_url('admin-post.php');
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Herramienta interna · FRN Atlántico</title>
    <?php wp_head(); ?>
</head>
<body class="frn-catalog-body frn-app-body">
<header class="frn-top frn-app-top">
    <a href="<?php echo esc_url(home_url('/')); ?>" class="frn-brand"><strong>FRN</strong><span>ATLÁNTICO</span></a>
    <nav>
        <span class="frn-user">Hola, <?php echo esc_html($currentUser->display_name ?: $currentUser->user_login); ?></span>
        <span class="frn-version">v<?php echo esc_html(FRN_SP_VERSION); ?></span>
        <a href="<?php echo esc_url(home_url('/')); ?>">Inicio .es</a>
        <a href="<?php echo esc_url($logoutUrl); ?>">Cerrar sesión</a>
    </nav>
</header>

<main class="frn-app-shell">
    <section class="frn-app-hero">
        <div>
            <p>Herramienta interna FRN · versión <?php echo esc_html(FRN_SP_VERSION); ?></p>
            <h1>Stock, precios<br>y tarifas.</h1>
        </div>
        <span>Un único Excel semanal actualiza disponibilidad, precio de origen y coste promedio. El precio comercial se revisa aquí antes de exportar Carne o Pescado / Marisco.</span>
    </section>

    <nav class="frn-app-tabs" aria-label="Herramienta">
        <a class="<?php echo $tab === 'importar' ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('tab','importar',home_url('/stock/'))); ?>">1. Datos semanales</a>
        <a class="<?php echo in_array($tab,['tarifas','tarifa'],true) ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('tab','tarifas',home_url('/stock/'))); ?>">2. Crear PDF</a>
        <?php if ($canManageUsers) : ?>
            <a class="<?php echo $tab === 'diseno' ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('tab','diseno',home_url('/stock/'))); ?>">Diseño PDF</a>
            <a class="<?php echo $tab === 'usuarios' ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('tab','usuarios',home_url('/stock/'))); ?>">Usuarios</a>
        <?php endif; ?>
    </nav>

    <?php foreach ($messages as [$kind,$message]) : ?>
        <div class="frn-app-message <?php echo esc_attr($kind); ?>"><?php echo esc_html($message); ?></div>
    <?php endforeach; ?>

    <?php
    if ($tab === 'importar') {
        require FRN_SP_PATH . 'templates/partials/app-import.php';
    } elseif ($tab === 'tarifas') {
        require FRN_SP_PATH . 'templates/partials/app-tariffs.php';
    } elseif ($tab === 'tarifa' && $tariff) {
        require FRN_SP_PATH . 'templates/partials/app-tariff-editor.php';
    } elseif ($tab === 'diseno' && $canManageUsers) {
        require FRN_SP_PATH . 'templates/partials/app-pdf-design.php';
    } elseif ($tab === 'usuarios' && $canManageUsers) {
        require FRN_SP_PATH . 'templates/partials/app-users.php';
    }
    ?>
</main>

<footer class="frn-footer">
    <span>FRN Atlántico · Herramienta interna · v<?php echo esc_html(FRN_SP_VERSION); ?></span>
    <a href="https://wa.me/<?php echo esc_attr($whatsapp); ?>" target="_blank" rel="noopener">WhatsApp comercial</a>
    <a href="<?php echo esc_url($logoutUrl); ?>">Cerrar sesión</a>
</footer>
<?php wp_footer(); ?>
</body>
</html>
