<?php
if (!defined('ABSPATH')) { exit; }
?>
<section class="frn-app-card frn-orders-home">
  <div class="frn-card-heading">
    <div><small>Operaciones comerciales</small><h2>Pedidos / Proformas</h2></div>
    <p>Crea el documento desde el mismo maestro FRN. Los productos y precios se leen del catálogo actual; el pedido guarda una fotografía histórica de lo vendido.</p>
  </div>
  <div class="frn-order-launch">
    <a class="frn-order-launch-card" href="<?php echo esc_url(add_query_arg(['tab'=>'pedido','type'=>'pedido'],home_url('/stock/'))); ?>">
      <small>Operación cerrada</small><strong>+ Nuevo pedido</strong><span>Para administración, logística o carga del pedido.</span>
    </a>
    <a class="frn-order-launch-card" href="<?php echo esc_url(add_query_arg(['tab'=>'pedido','type'=>'proforma'],home_url('/stock/'))); ?>">
      <small>Oferta al cliente</small><strong>+ Nueva proforma</strong><span>Presupuesto comercial preparado para enviar al cliente.</span>
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
        </div>
        <div class="frn-order-status"><?php echo esc_html(strtoupper((string)$row['status'])); ?></div>
        <div class="frn-order-total"><?php echo esc_html(number_format_i18n((float)$row['subtotal'],2)); ?> €</div>
      </a>
    <?php endforeach; ?>
  </div>
</section>
