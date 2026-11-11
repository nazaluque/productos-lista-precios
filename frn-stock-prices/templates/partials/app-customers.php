<?php
if (!defined('ABSPATH')) { exit; }
$editing = is_array($customer ?? null) ? $customer : [];
?>
<section class="frn-app-grid-two">
  <article class="frn-app-card">
    <div class="frn-card-heading">
      <div><small>Base comercial</small><h2><?php echo $editing ? 'Editar cliente' : 'Nuevo cliente'; ?></h2></div>
      <p>Los clientes se guardan una sola vez. Después, al crear un pedido o proforma, sus datos se rellenan automáticamente.</p>
    </div>
    <form method="post" action="<?php echo esc_url($postUrl); ?>" class="frn-customer-form">
      <input type="hidden" name="action" value="frn_front_customer_save">
      <input type="hidden" name="customer_id" value="<?php echo (int)($editing['id'] ?? 0); ?>">
      <?php wp_nonce_field('frn_front_customer_save'); ?>
      <div class="frn-form-grid">
        <label class="frn-span-2">Empresa / razón social
          <input type="text" name="company_name" required value="<?php echo esc_attr((string)($editing['company_name'] ?? '')); ?>">
        </label>
        <label>CIF / NIF / VAT
          <input type="text" name="vat_number" value="<?php echo esc_attr((string)($editing['vat_number'] ?? '')); ?>">
        </label>
        <label>Persona de contacto
          <input type="text" name="contact_name" value="<?php echo esc_attr((string)($editing['contact_name'] ?? '')); ?>">
        </label>
        <label>Teléfono
          <input type="tel" name="phone" value="<?php echo esc_attr((string)($editing['phone'] ?? '')); ?>">
        </label>
        <label>Email
          <input type="email" name="email" value="<?php echo esc_attr((string)($editing['email'] ?? '')); ?>">
        </label>
      </div>
      <h3>Dirección fiscal</h3>
      <div class="frn-form-grid">
        <label class="frn-span-2">Dirección
          <input type="text" name="billing_address" value="<?php echo esc_attr((string)($editing['billing_address'] ?? '')); ?>">
        </label>
        <label>CP<input type="text" name="billing_postcode" value="<?php echo esc_attr((string)($editing['billing_postcode'] ?? '')); ?>"></label>
        <label>Población<input type="text" name="billing_city" value="<?php echo esc_attr((string)($editing['billing_city'] ?? '')); ?>"></label>
        <label>Provincia<input type="text" name="billing_province" value="<?php echo esc_attr((string)($editing['billing_province'] ?? '')); ?>"></label>
        <label>País<input type="text" name="billing_country" value="<?php echo esc_attr((string)($editing['billing_country'] ?? 'España')); ?>"></label>
      </div>
      <div class="frn-delivery-heading">
        <h3>Dirección de entrega</h3>
        <button type="button" class="frn-mini-button" data-frn-copy-address>Copiar dirección fiscal</button>
      </div>
      <div class="frn-form-grid">
        <label class="frn-span-2">Dirección
          <input type="text" name="delivery_address" value="<?php echo esc_attr((string)($editing['delivery_address'] ?? '')); ?>">
        </label>
        <label>CP<input type="text" name="delivery_postcode" value="<?php echo esc_attr((string)($editing['delivery_postcode'] ?? '')); ?>"></label>
        <label>Población<input type="text" name="delivery_city" value="<?php echo esc_attr((string)($editing['delivery_city'] ?? '')); ?>"></label>
        <label>Provincia<input type="text" name="delivery_province" value="<?php echo esc_attr((string)($editing['delivery_province'] ?? '')); ?>"></label>
        <label>País<input type="text" name="delivery_country" value="<?php echo esc_attr((string)($editing['delivery_country'] ?? 'España')); ?>"></label>
        <label class="frn-span-2">Observaciones
          <textarea name="notes" rows="4"><?php echo esc_textarea((string)($editing['notes'] ?? '')); ?></textarea>
        </label>
      </div>
      <input type="hidden" name="active" value="1">
      <div class="frn-form-actions">
        <button type="submit"><?php echo $editing ? 'Guardar cliente' : 'Crear cliente'; ?></button>
        <?php if ($editing) : ?><a class="frn-secondary-button" href="<?php echo esc_url(add_query_arg('tab','clientes',home_url('/stock/'))); ?>">Cancelar</a><?php endif; ?>
      </div>
    </form>
  </article>

  <article class="frn-app-card">
    <div class="frn-card-heading">
      <div><small>Directorio</small><h2>Clientes</h2></div>
      <p><?php echo count($customers); ?> clientes guardados.</p>
    </div>
    <input class="frn-directory-search" type="search" placeholder="Buscar empresa, CIF, contacto o email…" data-frn-customer-search>
    <div class="frn-customer-list" data-frn-customer-list>
      <?php if (!$customers) : ?><div class="frn-empty">Todavía no hay clientes.</div><?php endif; ?>
      <?php foreach ($customers as $row) :
        $search = strtolower(implode(' ', [(string)$row['company_name'],(string)$row['vat_number'],(string)$row['contact_name'],(string)$row['email']]));
      ?>
        <a class="frn-customer-card" data-search="<?php echo esc_attr($search); ?>" href="<?php echo esc_url(add_query_arg(['tab'=>'clientes','customer_id'=>(int)$row['id']],home_url('/stock/'))); ?>">
          <strong><?php echo esc_html((string)$row['company_name']); ?></strong>
          <span><?php echo esc_html((string)$row['vat_number']); ?><?php echo $row['contact_name'] ? ' · ' . esc_html((string)$row['contact_name']) : ''; ?></span>
          <small><?php echo esc_html((string)$row['email']); ?><?php echo $row['phone'] ? ' · ' . esc_html((string)$row['phone']) : ''; ?></small>
        </a>
      <?php endforeach; ?>
    </div>
  </article>
</section>
