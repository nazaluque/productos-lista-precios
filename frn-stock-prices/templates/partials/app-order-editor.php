<?php
if (!defined('ABSPATH')) { exit; }

$isExisting = is_array($order ?? null);
$type = $isExisting ? (string)$order['document_type'] : (sanitize_key($_GET['type'] ?? 'pedido') === 'proforma' ? 'proforma' : 'pedido');
$title = $type === 'proforma' ? 'Proforma' : 'Pedido';
$currentCustomerId = $isExisting ? (int)$order['customer_id'] : 0;
$currentItems = is_array($orderItems ?? null) ? $orderItems : [];

$customerPayload = [];
foreach ($customers as $c) {
    $customerPayload[(int)$c['id']] = [
        'company_name'=>(string)$c['company_name'],
        'vat_number'=>(string)$c['vat_number'],
        'contact_name'=>(string)$c['contact_name'],
        'phone'=>(string)$c['phone'],
        'email'=>(string)$c['email'],
        'billing_address'=>trim(implode(', ', array_filter([
            (string)$c['billing_address'], (string)$c['billing_postcode'], (string)$c['billing_city'],
            (string)$c['billing_province'], (string)$c['billing_country']
        ]))),
        'delivery_address'=>trim(implode(', ', array_filter([
            (string)$c['delivery_address'], (string)$c['delivery_postcode'], (string)$c['delivery_city'],
            (string)$c['delivery_province'], (string)$c['delivery_country']
        ]))),
    ];
}

$productPayload = [];
foreach ($products as $p) {
    $productPayload[(int)$p['id']] = [
        'id'=>(int)$p['id'],
        'code'=>(string)$p['product_code'],
        'brand'=>(string)$p['brand'],
        'name'=>(string)($p['name_es_es'] ?: $p['product_name']),
        'original_name'=>(string)$p['product_name'],
        'unit'=>(string)$p['unit'],
        'price'=>(float)$p['price_kg'],
        'stock'=>(float)$p['stock_kg'],
        'category'=>(string)$p['category'],
    ];
}
?>
<section class="frn-app-card">
  <div class="frn-card-heading">
    <div>
      <small><?php echo $isExisting ? esc_html((string)$order['document_number']) : 'Nuevo documento'; ?></small>
      <h2><?php echo esc_html($title); ?></h2>
    </div>
    <p><?php echo $type === 'proforma' ? 'Prepara una propuesta comercial para el cliente.' : 'Registra una operación cerrada y déjala lista para enviar a administración o logística.'; ?></p>
  </div>

  <form method="post" action="<?php echo esc_url($postUrl); ?>" class="frn-order-form" data-frn-order-form>
    <input type="hidden" name="action" value="frn_front_order_save">
    <input type="hidden" name="order_id" value="<?php echo (int)($order['id'] ?? 0); ?>">
    <input type="hidden" name="document_type" value="<?php echo esc_attr($type); ?>">
    <?php wp_nonce_field('frn_front_order_save'); ?>

    <section class="frn-order-section">
      <div class="frn-order-section-title">
        <div><small>01</small><h3>Cliente</h3></div>
        <a href="<?php echo esc_url(add_query_arg('tab','clientes',home_url('/stock/'))); ?>">+ Nuevo cliente</a>
      </div>

      <label class="frn-order-customer-select">Buscar / seleccionar cliente
        <select name="customer_id" required data-frn-order-customer>
          <option value="">Seleccionar cliente…</option>
          <?php foreach ($customers as $c) : ?>
            <option value="<?php echo (int)$c['id']; ?>" <?php selected($currentCustomerId,(int)$c['id']); ?>>
              <?php echo esc_html((string)$c['company_name'] . ((string)$c['vat_number'] !== '' ? ' · ' . (string)$c['vat_number'] : '')); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <div class="frn-order-customer-summary" data-frn-customer-summary>
        <div><small>Empresa</small><strong data-field="company"><?php echo esc_html((string)($order['customer_company'] ?? '—')); ?></strong></div>
        <div><small>CIF / VAT</small><strong data-field="vat"><?php echo esc_html((string)($order['customer_vat'] ?? '—')); ?></strong></div>
        <div><small>Contacto</small><strong data-field="contact"><?php echo esc_html((string)($order['customer_contact'] ?? '—')); ?></strong></div>
        <div><small>Email</small><strong data-field="email"><?php echo esc_html((string)($order['customer_email'] ?? '—')); ?></strong></div>
        <div class="frn-span-2"><small>Entrega</small><strong data-field="delivery"><?php echo esc_html((string)($order['delivery_address'] ?? '—')); ?></strong></div>
      </div>
    </section>

    <section class="frn-order-section">
      <div class="frn-order-section-title">
        <div><small>02</small><h3>Productos</h3></div>
        <button type="button" class="frn-mini-button" data-frn-add-order-line>+ Añadir producto</button>
      </div>

      <div class="frn-order-lines" data-frn-order-lines></div>

      <template data-frn-order-line-template>
        <article class="frn-order-line" data-order-line>
          <div class="frn-order-line-head">
            <label>Producto
              <select data-line-field="product_id" class="frn-order-product">
                <option value="">Seleccionar referencia…</option>
                <?php
                $lastCat = '';
                foreach ($products as $p) :
                  $cat = (string)$p['category'];
                  if ($lastCat !== $cat) {
                    if ($lastCat !== '') { echo '</optgroup>'; }
                    echo '<optgroup label="' . esc_attr($cat === 'carne' ? 'CARNE' : 'PESCADO / MARISCO') . '">';
                    $lastCat = $cat;
                  }
                ?>
                  <option value="<?php echo (int)$p['id']; ?>">
                    <?php echo esc_html(trim((string)$p['product_code'] . ' · ' . (string)$p['brand'] . ' · ' . (string)($p['name_es_es'] ?: $p['product_name']))); ?>
                  </option>
                <?php endforeach; if ($lastCat !== '') echo '</optgroup>'; ?>
              </select>
            </label>
            <button type="button" class="frn-order-remove" data-frn-remove-order-line aria-label="Eliminar producto">×</button>
          </div>

          <div class="frn-order-product-meta">
            <span data-product-code>—</span>
            <strong data-product-name>Selecciona un producto</strong>
            <small><span data-product-brand></span><span data-product-stock></span></small>
          </div>

          <div class="frn-order-line-grid">
            <label>Vender por
              <select data-line-field="sale_unit">
                <option value="kg">Kg</option>
                <option value="cajas">Cajas</option>
                <option value="pallets">Pallets</option>
                <option value="unidades">Unidades</option>
              </select>
            </label>
            <label>Cantidad
              <input type="number" min="0" step="0.001" data-line-field="quantity" value="0">
            </label>
            <label>Cajas
              <input type="number" min="0" step="0.001" data-line-field="boxes" value="0">
            </label>
            <label>Pallets
              <input type="number" min="0" step="0.001" data-line-field="pallets" value="0">
            </label>
            <label>Kg totales
              <input type="number" min="0" step="0.001" data-line-field="total_kg" value="0">
            </label>
            <label>Precio venta
              <div class="frn-price-input"><input type="number" min="0" step="0.01" data-line-field="unit_price" value="0"><span>€</span></div>
            </label>
          </div>
          <label class="frn-order-line-note">Observación de línea
            <input type="text" data-line-field="line_notes" placeholder="Opcional">
          </label>
          <div class="frn-order-line-total"><span>Importe estimado</span><strong data-line-total>0,00 €</strong></div>
        </article>
      </template>
    </section>

    <section class="frn-order-section">
      <div class="frn-order-section-title"><div><small>03</small><h3>Entrega y observaciones</h3></div></div>
      <div class="frn-form-grid">
        <label>Fecha solicitada
          <input type="date" name="requested_date" value="<?php echo esc_attr((string)($order['requested_date'] ?? '')); ?>">
        </label>
        <label class="frn-span-2">Instrucciones de entrega
          <textarea name="delivery_notes" rows="3"><?php echo esc_textarea((string)($order['delivery_notes'] ?? '')); ?></textarea>
        </label>
        <label class="frn-span-2">Observaciones generales
          <textarea name="notes" rows="4"><?php echo esc_textarea((string)($order['notes'] ?? '')); ?></textarea>
        </label>
      </div>
    </section>

    <div class="frn-order-summary-bar">
      <div><small><?php echo esc_html(strtoupper($title)); ?></small><strong data-frn-order-grand-total><?php echo esc_html(number_format_i18n((float)($order['subtotal'] ?? 0),2)); ?> €</strong></div>
      <div class="frn-order-actions">
        <a class="frn-secondary-button" href="<?php echo esc_url(add_query_arg('tab','pedidos',home_url('/stock/'))); ?>">Volver</a>
        <button type="submit">Guardar <?php echo esc_html(strtolower($title)); ?></button>
      </div>
    </div>
  </form>

  <?php if ($isExisting) :
    $pdfUrl = wp_nonce_url(
      add_query_arg(['action'=>'frn_front_order_pdf','order_id'=>(int)$order['id']], admin_url('admin-post.php')),
      'frn_front_order_pdf_' . (int)$order['id']
    );
    $defaultRecipients = $type === 'proforma'
      ? (string)($order['customer_email'] ?? '')
      : (string)($orderEmailSettings['internal_recipients'] ?? '');
    $defaultSubject = ($type === 'proforma' ? 'Proforma ' : 'Pedido ')
      . (string)$order['document_number'] . ' · ' . (string)$order['customer_company'];
  ?>
  <section class="frn-order-send-panel">
    <div class="frn-card-heading">
      <div><small>Documento listo</small><h2>PDF y envío</h2></div>
      <p>Descarga el documento o envíalo por correo con el PDF adjunto. El envío queda registrado en el histórico.</p>
    </div>

    <div class="frn-order-send-actions">
      <a class="frn-pdf-button" href="<?php echo esc_url($pdfUrl); ?>">Descargar PDF</a>

      <?php if ($canSendOrders) : ?>
      <form method="post" action="<?php echo esc_url($postUrl); ?>" class="frn-order-email-form">
        <input type="hidden" name="action" value="frn_front_order_send">
        <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
        <?php wp_nonce_field('frn_front_order_send_' . (int)$order['id']); ?>
        <label>Destinatarios
          <input type="text" name="recipients" required value="<?php echo esc_attr($defaultRecipients); ?>" placeholder="correo@empresa.com; otro@empresa.com">
          <small>Separa varios correos con coma o punto y coma.</small>
        </label>
        <label>Asunto
          <input type="text" name="subject" value="<?php echo esc_attr($defaultSubject); ?>">
        </label>
        <label>Mensaje
          <textarea name="message" rows="5"><?php echo esc_textarea("Se adjunta " . ($type === 'proforma' ? 'la proforma comercial' : 'el pedido comercial') . " " . (string)$order['document_number'] . ".\n\nCliente: " . (string)$order['customer_company'] . "\n\nUn saludo,\nFRN Atlántico"); ?></textarea>
        </label>
        <button type="submit">Enviar PDF por email</button>
      </form>
      <?php endif; ?>
    </div>
  </section>
  <?php endif; ?>
</section>

<script type="application/json" id="frn-order-customers-data"><?php echo wp_json_encode($customerPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
<script type="application/json" id="frn-order-products-data"><?php echo wp_json_encode($productPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
<script type="application/json" id="frn-order-items-data"><?php echo wp_json_encode($currentItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
