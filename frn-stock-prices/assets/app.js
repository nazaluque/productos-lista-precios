document.addEventListener('DOMContentLoaded', () => {
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
