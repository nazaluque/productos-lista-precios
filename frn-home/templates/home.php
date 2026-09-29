<?php
if (!defined('ABSPATH')) { exit; }

$whatsapp = preg_replace('/\D+/', '', (string) get_option('frn_sp_whatsapp_number', '34624354950'));
$wa_message = rawurlencode('Hola FRN Atlántico, quiero consultar una condición comercial.');
$hero = FRN_HOME_URL . 'assets/frn-home-hero-v1.jpg';

$official = [
    'inicio' => 'https://www.frnatlantico.com/',
    'pescado' => 'https://www.frnatlantico.com/linea/pescado/',
    'marisco' => 'https://www.frnatlantico.com/linea/marisco/',
    'carne' => 'https://www.frnatlantico.com/linea/carne/',
];
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>FRN Atlántico · Pescado, marisco y carne para profesionales</title>
    <?php wp_head(); ?>
</head>
<body class="frn-home-body">
<header class="frn-home-header">
    <a href="<?php echo esc_url(home_url('/')); ?>" class="frn-home-brand" aria-label="FRN Atlántico, inicio"><strong>FRN</strong><span>ATLÁNTICO</span></a>
    <nav aria-label="Navegación principal">
        <a href="<?php echo esc_url($official['inicio']); ?>" target="_blank" rel="noopener">Inicio</a>
        <a href="<?php echo esc_url($official['pescado']); ?>" target="_blank" rel="noopener">Pescado</a>
        <a href="<?php echo esc_url($official['marisco']); ?>" target="_blank" rel="noopener">Marisco</a>
        <a href="<?php echo esc_url($official['carne']); ?>" target="_blank" rel="noopener">Carne</a>
        <a href="<?php echo esc_url('tel:+' . $whatsapp); ?>">+34 624 354 950</a>
        <a class="frn-home-nav-wa" href="https://wa.me/<?php echo esc_attr($whatsapp); ?>?text=<?php echo esc_attr($wa_message); ?>" target="_blank" rel="noopener">WhatsApp</a>
    </nav>
</header>

<main>
    <section class="frn-home-hero" style="--frn-home-hero:url('<?php echo esc_url($hero); ?>')">
        <div class="frn-home-hero-copy">
            <p>Distribución profesional B2B · Galicia · España</p>
            <h1>Pescado, marisco y carne para el mercado profesional.</h1>
            <span>FRN Atlántico conecta producto, disponibilidad y atención comercial directa para el canal profesional.</span>
            <div class="frn-home-actions">
                <a class="frn-home-primary" href="<?php echo esc_url(home_url('/stock/')); ?>">Ver productos, stock y precios</a>
            </div>
        </div>
    </section>

    <section class="frn-home-contact frn-home-contact-alt">
        <div>
            <p>Atención comercial directa</p>
            <h2>¿Buscas una condición especial?</h2>
            <span>Indícanos producto y cantidad. Confirmaremos disponibilidad y la mejor condición posible.</span>
        </div>
        <div class="frn-home-contact-actions">
            <a href="<?php echo esc_url('tel:+' . $whatsapp); ?>">Llamar al +34 624 354 950</a>
            <a class="frn-home-primary" href="https://wa.me/<?php echo esc_attr($whatsapp); ?>?text=<?php echo esc_attr($wa_message); ?>" target="_blank" rel="noopener">Abrir WhatsApp</a>
        </div>
    </section>
</main>

<footer class="frn-home-footer">
    <span>FRN Atlántico · A Coruña · Galicia · España</span>
    <a href="https://www.frnatlantico.com/" target="_blank" rel="noopener">Web corporativa: frnatlantico.com</a>
</footer>
<?php wp_footer(); ?>
</body>
</html>
