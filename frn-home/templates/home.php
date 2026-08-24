<?php
if (!defined('ABSPATH')) { exit; }
$whatsapp = preg_replace('/\D+/', '', (string) get_option('frn_sp_whatsapp_number', '34624354950'));
$wa_message = rawurlencode('Hola FRN Atlántico, quiero consultar productos, stock y condiciones comerciales.');
$hero = FRN_HOME_URL . 'assets/frn-home-hero-v1.jpg';
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
        <a href="<?php echo esc_url(home_url('/')); ?>">Inicio</a>
        <a class="frn-home-nav-featured" href="<?php echo esc_url(home_url('/stock/')); ?>">Productos, stock y precios</a>
        <a href="<?php echo esc_url(home_url('/stock/pescado-marisco/')); ?>">Pescado / Marisco</a>
        <a href="<?php echo esc_url(home_url('/stock/carne/')); ?>">Carne</a>
        <a href="<?php echo esc_url('tel:+' . $whatsapp); ?>">+34 624 354 950</a>
        <a class="frn-home-nav-wa" href="https://wa.me/<?php echo esc_attr($whatsapp); ?>?text=<?php echo esc_attr($wa_message); ?>" target="_blank" rel="noopener">WhatsApp</a>
    </nav>
</header>

<main>
    <section class="frn-home-hero" style="--frn-home-hero:url('<?php echo esc_url($hero); ?>')">
        <div class="frn-home-hero-copy">
            <p>Distribución profesional B2B · Galicia · España</p>
            <h1>Pescado, marisco y carne para el mercado profesional.</h1>
            <span>Consulta la disponibilidad vigente, precios y condiciones comerciales de FRN Atlántico.</span>
            <div class="frn-home-actions">
                <a class="frn-home-primary" href="<?php echo esc_url(home_url('/stock/')); ?>">Ver productos, stock y precios</a>
                <a class="frn-home-secondary" href="https://wa.me/<?php echo esc_attr($whatsapp); ?>?text=<?php echo esc_attr($wa_message); ?>" target="_blank" rel="noopener">Consultar por WhatsApp</a>
            </div>
        </div>
        <a class="frn-home-scroll" href="#catalogos">Explorar catálogos <span>↓</span></a>
    </section>

    <section class="frn-home-catalogs" id="catalogos">
        <div class="frn-home-section-heading">
            <p>Catálogo profesional actualizado</p>
            <h2>Consulta lo que hay disponible hoy.</h2>
        </div>
        <div class="frn-home-cards">
            <a href="<?php echo esc_url(home_url('/stock/pescado-marisco/')); ?>">
                <small>Catálogo 01</small><h3>Pescado y marisco</h3><span>Ver stock y precios →</span>
            </a>
            <a href="<?php echo esc_url(home_url('/stock/carne/')); ?>">
                <small>Catálogo 02</small><h3>Carne</h3><span>Ver stock y precios →</span>
            </a>
        </div>
    </section>

    <section class="frn-home-contact">
        <div><p>Atención comercial directa</p><h2>¿Buscas una condición especial?</h2><span>Indícanos producto y cantidad. Confirmaremos disponibilidad y la mejor condición posible.</span></div>
        <div class="frn-home-contact-actions">
            <a href="<?php echo esc_url('tel:+' . $whatsapp); ?>">Llamar al +34 624 354 950</a>
            <a class="frn-home-primary" href="https://wa.me/<?php echo esc_attr($whatsapp); ?>?text=<?php echo esc_attr($wa_message); ?>" target="_blank" rel="noopener">Abrir WhatsApp</a>
        </div>
    </section>
</main>

<footer class="frn-home-footer"><span>FRN Atlántico · A Coruña · Galicia · España</span><a href="https://www.frnatlantico.com/">Web corporativa: frnatlantico.com</a></footer>
<?php wp_footer(); ?>
</body>
</html>
