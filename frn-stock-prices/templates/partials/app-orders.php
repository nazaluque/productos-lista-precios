<?php
if (!defined('ABSPATH')) { exit; }
?>
<section class="frn-app-card frn-orders-home">
  <div class="frn-card-heading">
    <div><small>Operaciones comerciales</small><h2>Pedidos / Proformas</h2></div>
    <p>Carga el pedido desde el catálogo FRN y envíalo a administración o logística. El correo y el PDF son para uso interno.</p>
  </div>
  <div class="frn-order-launch">
    <a class="frn-order-launch-card" href="<?php echo esc_url(add_query_arg(['tab'=>'pedido','type'=>'pedido'],home_url('/stock/'))); ?>">
      <small>Operación cerrada</small><strong>+ Nuevo pedido</strong><span>Para administración, logística o carga del pedido.</span>
    </a>
    <a class="frn-order-launch-card" href="<?php echo esc_url(add_query_arg(['tab'=>'pedido','type'=>'proforma'],home_url('/stock/'))); ?>">
      <small>Propuesta interna</small><strong>+ Nueva proforma</strong><span>Prepara una proforma para revisar con el equipo FRN.</span>
    </a>
  </div>
</section>

<section class="frn-app-card">
  <div class="frn-card-heading">
    <div><small>Histórico</small><h2>Documentos guardados</h2></div>
    <p><?php echo count($orders); ?> documentos recientes.</p>
  </div>
  <div class="frn-order-history">
    <?php if (!$orders) : ?><div class="frn-empty">Todavía no hay pedidos ni proformas.</div><?php endif; ?>
    <?php foreach ($orders as $row) : ?>
      <a href="<?php echo esc_url(add_query_arg(['tab'=>'pedido','id'=>(int)$row['id']],home_url('/stock/'))); ?>">
        <div>
          <small><?php echo $row['document_type'] === 'proforma' ? 'PROFORMA' : 'PEDIDO'; ?></small>
          <strong><?php echo esc_html((string)$row['document_number']); ?></strong>
        </div>
        <div>
          <strong><?php echo esc_html((string)$row['customer_company']); ?></strong>
          <span><?php echo esc_html(mysql2date('d/m/Y H:i',(string)$row['created_at'])); ?></span>
          <?php if (!empty($row['pending_weight_count'])) : ?><span class="frn-order-pending-note">Faltan kg en <?php echo (int)$row['pending_weight_count']; ?> línea(s)</span><?php endif; ?>
        </div>
        <div class="frn-order-status"><?php echo esc_html(strtoupper((string)$row['status'])); ?></div>
        <div class="frn-order-total"><?php echo !empty($row['pending_weight_count']) ? 'Parcial: ' : ''; ?><?php echo esc_html(number_format_i18n((float)$row['subtotal'],2)); ?> €</div>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<?php if ($canManageUsers) : ?>
<section class="frn-app-card">
  <div class="frn-card-heading">
    <div><small>Configuración</small><h2>Correo de pedidos</h2></div>
    <p>Estos datos controlan el remitente visible, las respuestas y los destinatarios internos que aparecen por defecto.</p>
  </div>
  <form method="post" action="<?php echo esc_url($postUrl); ?>" class="frn-form-grid frn-order-email-settings">
    <input type="hidden" name="action" value="frn_front_order_email_settings">
    <?php wp_nonce_field('frn_front_order_email_settings'); ?>
    <label>Nombre remitente
      <input type="text" name="from_name" value="<?php echo esc_attr((string)$orderEmailSettings['from_name']); ?>" placeholder="FRN Atlántico">
    </label>
    <label>Email remitente
      <input type="email" name="from_email" value="<?php echo esc_attr((string)$orderEmailSettings['from_email']); ?>" placeholder="nl@frnatlantico.es">
    </label>
    <label>Responder a
      <input type="email" name="reply_to" value="<?php echo esc_attr((string)$orderEmailSettings['reply_to']); ?>" placeholder="nl@frnatlantico.es">
    </label>
    <label>Destinatarios internos por defecto
      <input type="text" name="internal_recipients" value="<?php echo esc_attr((string)$orderEmailSettings['internal_recipients']); ?>" placeholder="gc@frnatlantico.com; rg@frnatlantico.com; nl@frnatlantico.com">
    </label>
    <div class="frn-span-2 frn-form-actions"><button type="submit">Guardar configuración de correo</button></div>
  </form>
  <p class="frn-save-note">El pedido sale como FRN Atlántico desde la cuenta .es configurada. Esta pantalla no guarda contraseñas SMTP.</p>
</section>
<?php endif; ?>
