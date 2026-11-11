document.addEventListener('DOMContentLoaded', () => {
  const sentNotice = document.querySelector('.frn-order-send-confirmation');
  if (sentNotice) {
    sentNotice.focus({ preventScroll: true });
    sentNotice.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  const fileInput = document.querySelector('#frn-stock-files');
  const fileStatus = document.querySelector('#frn-file-status');

  const refreshFileStatus = () => {
    if (!fileInput || !fileStatus) return;
    const files = Array.from(fileInput.files || []);
    const strong = fileStatus.querySelector('strong');
    const span = fileStatus.querySelector('span');

    if (!files.length) {
      fileStatus.classList.remove('is-ready');
      if (strong) strong.textContent = 'Selecciona un Excel para comenzar';
      if (span) span.textContent = 'El archivo no se publicará hasta que revises la previsualización.';
      return;
    }

    fileStatus.classList.add('is-ready');
    if (strong) strong.textContent = files.length === 1 ? files[0].name : files.length + ' archivos seleccionados';
    if (span) span.textContent = 'Archivo seleccionado · listo para analizar';
  };

  if (fileInput) {
    fileInput.addEventListener('change', refreshFileStatus);
    refreshFileStatus();
  }

  const setChecked = (selector, checked) => {
    document.querySelectorAll(selector).forEach((el) => { el.checked = checked; });
  };

  document.querySelectorAll('[data-frn-select]').forEach((button) => {
    button.addEventListener('click', () => {
      const group = button.dataset.group;
      const mode = button.dataset.frnSelect;
      const checkboxes = document.querySelectorAll('.frn-use-checkbox[data-group="' + group + '"]');

      checkboxes.forEach((checkbox) => {
        if (mode === 'all') {
          checkbox.checked = true;
        } else if (mode === 'none') {
          checkbox.checked = false;
        } else if (mode === 'stock') {
          const row = checkbox.closest('tr');
          const stock = Number.parseFloat(row?.dataset.stock || '0');
          checkbox.checked = stock > 0;
        }
      });
    });
  });

  document.querySelectorAll('[data-frn-offer]').forEach((button) => {
    button.addEventListener('click', () => {
      const group = button.dataset.group;
      const checked = button.dataset.frnOffer === 'all';
      setChecked('.frn-offer-checkbox[data-group="' + group + '"]', checked);
    });
  });

  const globalStock = document.querySelector('#frn-global-stock');
  const globalPrice = document.querySelector('#frn-global-price');
  const stockMode = document.querySelector('#frn-stock-mode');
  const globalCost = document.querySelector('#frn-global-cost');
  const preset = document.querySelector('#frn-preset-mode');

  const syncStockRows = () => {
    if (!globalStock) return;
    setChecked('.frn-line-stock', globalStock.checked);
    if (stockMode) {
      stockMode.disabled = !globalStock.checked;
    }
  };

  const syncPriceRows = () => {
    if (!globalPrice) return;
    document.querySelectorAll('.frn-line-price').forEach((checkbox) => {
      const row = checkbox.closest('tr');
      const price = Number.parseFloat(row?.dataset.price || '0');
      checkbox.checked = globalPrice.checked && price > 0;
    });
  };

  if (globalStock) {
    globalStock.addEventListener('change', syncStockRows);
    if (!globalStock.checked && stockMode) stockMode.disabled = true;
  }

  if (globalPrice) {
    globalPrice.addEventListener('change', syncPriceRows);
  }

  const syncCostRows = () => {
    if (!globalCost) return;
    document.querySelectorAll('.frn-line-cost').forEach((checkbox) => {
      const row = checkbox.closest('tr');
      const cost = Number.parseFloat(row?.dataset.cost || '0');
      checkbox.checked = globalCost.checked && cost > 0;
    });
  };

  if (globalCost) {
    globalCost.addEventListener('change', syncCostRows);
  }

  if (preset) {
    preset.addEventListener('change', () => {
      switch (preset.value) {
        case 'general':
          if (globalStock) globalStock.checked = true;
          if (globalPrice) globalPrice.checked = true;
          if (stockMode) stockMode.value = 'available';
          syncStockRows();
          syncPriceRows();
          break;
        case 'distribuidor':
          if (globalStock) globalStock.checked = true;
          if (globalPrice) globalPrice.checked = true;
          if (stockMode) stockMode.value = 'exact';
          syncStockRows();
          syncPriceRows();
          break;
        case 'disponibilidad':
          if (globalStock) globalStock.checked = true;
          if (globalPrice) globalPrice.checked = false;
          if (stockMode) stockMode.value = 'available';
          syncStockRows();
          syncPriceRows();
          break;
        default:
          break;
      }
    });
  }
});


/* FRN 1.1.16 · translations filters */
(() => {
  const search = document.querySelector('#frn-translation-search');
  const category = document.querySelector('#frn-translation-category');
  const pending = document.querySelector('#frn-translation-pending');
  const rows = Array.from(document.querySelectorAll('[data-translation-row]'));
  if (!rows.length) return;

  const normalize = (v) => (v || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  const refresh = () => {
    const q = normalize(search?.value || '');
    const cat = category?.value || '';
    const onlyPending = !!pending?.checked;
    rows.forEach((row) => {
      const okSearch = !q || normalize(row.dataset.search).includes(q);
      const okCategory = !cat || row.dataset.category === cat;
      const okPending = !onlyPending || row.dataset.pending === '1';
      row.style.display = okSearch && okCategory && okPending ? '' : 'none';
    });
  };
  search?.addEventListener('input', refresh);
  category?.addEventListener('change', refresh);
  pending?.addEventListener('change', refresh);
})();


/* FRN 1.3.0 · flujo interno de pedidos */
(() => {
  const copyButton = document.querySelector('[data-frn-copy-address]');
  if (copyButton) {
    copyButton.addEventListener('click', () => {
      const pairs = [
        ['billing_address','delivery_address'],
        ['billing_postcode','delivery_postcode'],
        ['billing_city','delivery_city'],
        ['billing_province','delivery_province'],
        ['billing_country','delivery_country'],
      ];
      pairs.forEach(([from,to]) => {
        const source = document.querySelector('[name="' + from + '"]');
        const target = document.querySelector('[name="' + to + '"]');
        if (source && target) target.value = source.value;
      });
    });
  }

  const customerSearch = document.querySelector('[data-frn-customer-search]');
  if (customerSearch) {
    customerSearch.addEventListener('input', () => {
      const q = customerSearch.value.trim().toLowerCase();
      document.querySelectorAll('[data-frn-customer-list] [data-search]').forEach((card) => {
        card.hidden = q !== '' && !String(card.dataset.search || '').includes(q);
      });
    });
  }

  const form = document.querySelector('[data-frn-order-form]');
  if (!form) return;

  const parseJson = (id) => {
    try { return JSON.parse(document.getElementById(id)?.textContent || '{}'); }
    catch (_) { return {}; }
  };
  const customers = parseJson('frn-order-customers-data');
  const products = parseJson('frn-order-products-data');
  const existingItems = parseJson('frn-order-items-data');
  const customerSelect = form.querySelector('[data-frn-order-customer]');
  const linesWrap = form.querySelector('[data-frn-order-lines]');
  const template = form.querySelector('[data-frn-order-line-template]');
  const totalEl = form.querySelector('[data-frn-order-grand-total]');
  let lineIndex = 0;

  const money = (n) => new Intl.NumberFormat('es-ES',{style:'currency',currency:'EUR'}).format(Number(n || 0));

  const refreshCustomer = () => {
    const data = customers[customerSelect?.value || ''] || {};
    const summary = form.querySelector('[data-frn-customer-summary]');
    if (!summary) return;
    const fields = {
      company:data.company_name || '—',
      vat:data.vat_number || '—',
      contact:data.contact_name || '—',
      email:data.email || '—',
      delivery:data.delivery_address || '—',
    };
    Object.entries(fields).forEach(([key,value]) => {
      const el = summary.querySelector('[data-field="' + key + '"]');
      if (el) el.textContent = value;
    });
  };

  const refreshGrandTotal = () => {
    let sum = 0;
    let pendingWeightCount = 0;
    linesWrap.querySelectorAll('[data-order-line]').forEach((line) => {
      sum += Number(line.dataset.lineTotal || 0);
      if (line.dataset.needsWeight === '1') pendingWeightCount += 1;
    });
    if (totalEl) totalEl.textContent = money(sum);
    const pending = form.querySelector('[data-frn-order-pending]');
    if (pending) {
      pending.hidden = pendingWeightCount === 0;
      pending.textContent = pendingWeightCount ? 'Importe parcial · faltan los kg de ' + pendingWeightCount + ' línea(s)' : '';
    }
  };

  const refreshLine = (line) => {
    const productId = line.querySelector('[data-line-field="product_id"]')?.value || '';
    const product = products[productId] || {};
    const priceInput = line.querySelector('[data-line-field="unit_price"]');
    const quantity = Number(line.querySelector('[data-line-field="quantity"]')?.value || 0);
    const totalKg = Number(line.querySelector('[data-line-field="total_kg"]')?.value || 0);
    const saleUnit = line.querySelector('[data-line-field="sale_unit"]')?.value || 'kg';
    const price = Number(priceInput?.value || 0);
    const basis = saleUnit === 'kg' ? quantity : totalKg;
    const lineTotal = basis * price;

    const code = line.querySelector('[data-product-code]');
    const name = line.querySelector('[data-product-name]');
    const brand = line.querySelector('[data-product-brand]');
    const stock = line.querySelector('[data-product-stock]');
    if (code) code.textContent = product.code || '—';
    if (name) name.textContent = product.name || 'Selecciona un producto';
    if (brand) brand.textContent = product.brand ? product.brand + ' · ' : '';
    if (stock) stock.textContent = productId ? 'Stock ref. ' + Number(product.stock || 0).toLocaleString('es-ES') + ' ' + (product.unit || '') : '';
    line.dataset.lineTotal = String(lineTotal);
    line.dataset.needsWeight = saleUnit !== 'kg' && totalKg <= 0 ? '1' : '0';
    const lineTotalEl = line.querySelector('[data-line-total]');
    if (lineTotalEl) lineTotalEl.textContent = line.dataset.needsWeight === '1' ? 'Pendiente de kg' : money(lineTotal);
    const weightLabel = line.querySelector('[data-frn-weight-label]');
    if (weightLabel) weightLabel.textContent = saleUnit === 'kg' ? 'Kg totales' : 'Kg totales (si los conoces)';
    refreshGrandTotal();
  };

  const wireLine = (line) => {
    line.querySelectorAll('[data-line-field]').forEach((field) => {
      field.name = 'items[' + lineIndex + '][' + field.dataset.lineField + ']';
      field.addEventListener('input', () => refreshLine(line));
      field.addEventListener('change', () => {
        if (field.dataset.lineField === 'product_id') {
          const product = products[field.value] || {};
          const price = line.querySelector('[data-line-field="unit_price"]');
          const quantity = line.querySelector('[data-line-field="quantity"]');
          const totalKg = line.querySelector('[data-line-field="total_kg"]');
          const saleUnit = line.querySelector('[data-line-field="sale_unit"]');
          if (price && Number(price.value || 0) === 0) price.value = Number(product.price || 0).toFixed(2);
          if (product.unit && /unidad/i.test(product.unit) && saleUnit) saleUnit.value = 'unidades';
          if (quantity && Number(quantity.value || 0) === 0) quantity.value = '1';
          if (totalKg && saleUnit?.value === 'kg') totalKg.value = quantity?.value || '1';
        }
        if (field.dataset.lineField === 'sale_unit') {
          const quantity = line.querySelector('[data-line-field="quantity"]');
          const quantityLabel = line.querySelector('[data-frn-quantity-label]');
          const quantityWrap = line.querySelector('[data-frn-quantity-wrap]');
          const boxes = line.querySelector('[data-line-field="boxes"]');
          const pallets = line.querySelector('[data-line-field="pallets"]');
          const halfWrap = line.querySelector('[data-frn-half-pallet-wrap]');
          const halfToggle = line.querySelector('[data-frn-half-pallet]');
          const selectedUnit = field.value;
          if (quantityLabel) quantityLabel.textContent = selectedUnit === 'cajas' ? 'Cantidad (cajas)' : selectedUnit === 'pallets' ? 'Pallets completos' : selectedUnit === 'unidades' ? 'Cantidad (unidades)' : 'Cantidad (kg)';
          if (quantity) quantity.step = selectedUnit === 'pallets' ? '1' : '0.001';
          if (halfWrap) halfWrap.hidden = selectedUnit !== 'pallets';
          if (quantityWrap) quantityWrap.hidden = false;
          const weightLabel = line.querySelector('[data-frn-weight-label]');
          const weightInput = line.querySelector('[data-line-field="total_kg"]');
          const weightField = weightInput?.closest('label');
          if (weightLabel) weightLabel.textContent = selectedUnit === 'kg' ? 'Kg totales' : 'Kg totales (si los conoces)';
          if (weightField) weightField.hidden = selectedUnit === 'kg';
          if (weightInput) weightInput.value = selectedUnit === 'kg' ? (quantity?.value || '0') : '0';
          if (selectedUnit === 'cajas' && boxes && quantity) boxes.value = quantity.value;
          if (selectedUnit === 'pallets' && quantity && pallets) {
            if (halfToggle) halfToggle.checked = false;
            const whole = Math.max(0, Math.floor(Number(quantity.value || 0)));
            quantity.value = String(whole);
            pallets.value = String(whole);
          }
        }
        refreshLine(line);
      });
      field.addEventListener('input', () => {
        const selectedUnit = line.querySelector('[data-line-field="sale_unit"]')?.value;
        const quantity = line.querySelector('[data-line-field="quantity"]');
        const boxes = line.querySelector('[data-line-field="boxes"]');
        const pallets = line.querySelector('[data-line-field="pallets"]');
        if (field.dataset.lineField === 'quantity' && selectedUnit === 'kg') {
          const totalKg = line.querySelector('[data-line-field="total_kg"]');
          if (totalKg) totalKg.value = field.value;
        }
        if (field.dataset.lineField === 'quantity' && selectedUnit === 'cajas' && boxes) boxes.value = field.value;
        if (field.dataset.lineField === 'quantity' && selectedUnit === 'pallets' && pallets) {
          const half = line.querySelector('[data-frn-half-pallet]')?.checked ? 0.5 : 0;
          pallets.value = String(Math.max(0, Math.floor(Number(field.value || 0))) + half);
        }
        if (field.dataset.lineField === 'boxes' && selectedUnit === 'cajas' && quantity) quantity.value = field.value;
        if (field.dataset.lineField === 'pallets' && selectedUnit === 'pallets' && quantity) quantity.value = String(Math.floor(Number(field.value || 0)));
        refreshLine(line);
      });
    });
    const halfPallet = line.querySelector('[data-frn-half-pallet]');
    halfPallet?.addEventListener('change', () => {
      const quantity = line.querySelector('[data-line-field="quantity"]');
      const pallets = line.querySelector('[data-line-field="pallets"]');
      if (quantity && pallets) pallets.value = String(Math.max(0, Math.floor(Number(quantity.value || 0))) + (halfPallet.checked ? 0.5 : 0));
      refreshLine(line);
    });
    line.querySelector('[data-frn-remove-order-line]')?.addEventListener('click', () => {
      line.remove();
      refreshGrandTotal();
    });
    lineIndex += 1;
  };

  const addLine = (values = {}) => {
    const fragment = template.content.cloneNode(true);
    const line = fragment.querySelector('[data-order-line]');
    linesWrap.appendChild(fragment);
    const inserted = linesWrap.lastElementChild;
    wireLine(inserted);
    Object.entries(values || {}).forEach(([key,value]) => {
      const field = inserted.querySelector('[data-line-field="' + key + '"]');
      if (field && value !== null && value !== undefined) field.value = value;
    });
    const unitField = inserted.querySelector('[data-line-field="sale_unit"]');
    if (unitField) {
      const selectedUnit = unitField.value;
      const quantityLabel = inserted.querySelector('[data-frn-quantity-label]');
      if (quantityLabel) quantityLabel.textContent = selectedUnit === 'cajas' ? 'Cantidad (cajas)' : selectedUnit === 'pallets' ? 'Pallets completos' : selectedUnit === 'unidades' ? 'Cantidad (unidades)' : 'Cantidad (kg)';
      const quantity = inserted.querySelector('[data-line-field="quantity"]');
      const pallets = inserted.querySelector('[data-line-field="pallets"]');
      const halfToggle = inserted.querySelector('[data-frn-half-pallet]');
      const halfWrap = inserted.querySelector('[data-frn-half-pallet-wrap]');
      if (quantity) quantity.step = selectedUnit === 'pallets' ? '1' : '0.001';
      if (halfWrap) halfWrap.hidden = selectedUnit !== 'pallets';
      if (selectedUnit === 'pallets' && quantity && pallets) {
        const palletValue = Number(pallets.value || quantity.value || 0);
        quantity.value = String(Math.floor(palletValue));
        if (halfToggle) halfToggle.checked = palletValue % 1 >= 0.5;
        pallets.value = String(Math.floor(palletValue) + (halfToggle?.checked ? 0.5 : 0));
      }
      const weightField = inserted.querySelector('[data-line-field="total_kg"]')?.closest('label');
      if (weightField) weightField.hidden = selectedUnit === 'kg';
    }
    refreshLine(inserted);
    return inserted;
  };

  form.querySelector('[data-frn-add-order-line]')?.addEventListener('click', () => addLine());
  customerSelect?.addEventListener('change', refreshCustomer);

  if (Array.isArray(existingItems) && existingItems.length) {
    existingItems.forEach((item) => addLine({
      product_id:item.product_id,
      sale_unit:item.sale_unit,
      quantity:item.quantity,
      boxes:item.boxes,
      pallets:item.pallets,
      total_kg:item.total_kg,
      unit_price:item.unit_price,
      line_notes:item.line_notes,
    }));
  } else {
    addLine();
  }
  refreshCustomer();

  document.querySelectorAll('[data-frn-share-pdf]').forEach((button) => {
    button.addEventListener('click', async () => {
      const originalText = button.textContent;
      button.disabled = true;
      button.textContent = 'Preparando PDF…';
      try {
        const response = await fetch(button.dataset.pdfUrl, { credentials: 'same-origin' });
        if (!response.ok) throw new Error('No se pudo descargar el PDF.');
        const blob = await response.blob();
        const file = new File([blob], button.dataset.pdfName || 'FRN-pedido.pdf', { type: 'application/pdf' });
        if (navigator.share && navigator.canShare && navigator.canShare({ files: [file] })) {
          await navigator.share({ files: [file], title: 'Pedido FRN' });
        } else {
          const objectUrl = URL.createObjectURL(blob);
          const link = document.createElement('a');
          link.href = objectUrl;
          link.download = file.name;
          link.click();
          URL.revokeObjectURL(objectUrl);
          const text = encodeURIComponent('Te comparto el pedido FRN en PDF. Adjúntalo desde Descargas en WhatsApp.');
          window.open('https://wa.me/?text=' + text, '_blank', 'noopener');
        }
      } catch (error) {
        if (error.name !== 'AbortError') window.alert(error.message || 'No se pudo compartir el PDF. Descárgalo y adjúntalo en WhatsApp.');
      } finally {
        button.disabled = false;
        button.textContent = originalText;
      }
    });
  });
})();
