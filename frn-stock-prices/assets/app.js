document.addEventListener('DOMContentLoaded', () => {
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
