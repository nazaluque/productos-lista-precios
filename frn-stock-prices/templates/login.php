<?php
if (!defined('ABSPATH')) { exit; }

$error = '';
$redirectTo = wp_validate_redirect(
    isset($_REQUEST['redirect_to']) ? wp_unslash((string) $_REQUEST['redirect_to']) : '',
    home_url('/stock/')
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['frn_login_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['frn_login_nonce'])), 'frn_stock_login')) {
        $error = 'La sesión ha caducado. Vuelve a intentarlo.';
    } else {
        $credentials = [
            'user_login' => sanitize_user(wp_unslash((string) ($_POST['user_login'] ?? ''))),
            'user_password' => (string) ($_POST['user_password'] ?? ''),
            'remember' => !empty($_POST['rememberme']),
        ];

        $user = wp_signon($credentials, is_ssl());

        if (is_wp_error($user)) {
            $error = 'Usuario o contraseña incorrectos.';
        } elseif (!user_can($user, 'frn_manage_stock')) {
            wp_logout();
            $error = 'Este usuario no tiene permiso para acceder a la herramienta FRN.';
        } else {
            wp_safe_redirect($redirectTo);
            exit;
        }
    }
}

$whatsapp = preg_replace('/\D+/', '', (string) get_option('frn_sp_whatsapp_number', '34624354950'));
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Acceso interno · FRN Atlántico</title>
    <?php wp_head(); ?>
</head>
<body class="frn-catalog-body frn-login-body">
    <main class="frn-login-shell">
        <section class="frn-login-brand">
            <a href="<?php echo esc_url(home_url('/')); ?>" class="frn-brand"><strong>FRN</strong><span>ATLÁNTICO</span></a>
            <p>Herramienta interna</p>
            <h1>Stock, precios<br>y tarifas.</h1>
            <span>Acceso restringido al equipo comercial de FRN Atlántico.</span>
        </section>

        <section class="frn-login-panel">
            <div class="frn-login-card">
                <small>Área comercial FRN</small>
                <h2>Acceso</h2>

                <?php if ($error) : ?>
                    <div class="frn-login-error"><?php echo esc_html($error); ?></div>
                <?php endif; ?>

                <form method="post" action="<?php echo esc_url(home_url('/stock/acceso/')); ?>">
                    <?php wp_nonce_field('frn_stock_login', 'frn_login_nonce'); ?>
                    <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirectTo); ?>">

                    <label>Usuario
                        <input type="text" name="user_login" autocomplete="username" required autofocus>
                    </label>

                    <label>Contraseña
                        <input type="password" name="user_password" autocomplete="current-password" required>
                    </label>

                    <label class="frn-remember">
                        <input type="checkbox" name="rememberme" value="1"> Mantener sesión iniciada
                    </label>

                    <button type="submit">Entrar a la herramienta</button>
                </form>

                <a class="frn-login-back" href="<?php echo esc_url(home_url('/')); ?>">← Volver a FRN Atlántico</a>
            </div>
        </section>
    </main>
    <?php wp_footer(); ?>
</body>
</html>
