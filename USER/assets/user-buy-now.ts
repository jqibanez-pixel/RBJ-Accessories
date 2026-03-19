// @ts-nocheck

const toastMessage = (window.RBJ_BUY_NOW_CONFIG && window.RBJ_BUY_NOW_CONFIG.toastMessage) || null;
const toastType = (window.RBJ_BUY_NOW_CONFIG && window.RBJ_BUY_NOW_CONFIG.toastType) || null;
if (toastMessage) {
  const toast = document.createElement('div');
  toast.className = 'toast ' + (toastType === 'error' ? 'error' : 'success');
  toast.textContent = toastMessage;
  document.body.appendChild(toast);
  requestAnimationFrame(() => toast.classList.add('show'));
  setTimeout(() => {
    toast.classList.remove('show');
    setTimeout(() => toast.remove(), 260);
  }, 2600);
}

// Quantity management for Buy Now
function updateItemQuantity(index, change) {
  const itemRow = document.querySelector(`.order-item[data-item-index="${index}"]`);
  if (!itemRow) return;
  
  const qtyInput = itemRow.querySelector('.qty-input');
  const availableStock = parseInt(itemRow.dataset.availableStock) || 99;
  const currentQty = parseInt(qtyInput.value) || 1;
  const maxQty = Math.min(99, availableStock);
  
  let newQty = currentQty + change;
  if (newQty < 1) newQty = 1;
  if (newQty > maxQty) newQty = maxQty;
  
  qtyInput.value = newQty;
  updateItemSubtotal(index);
}

function validateItemQuantity(input, index) {
  const itemRow = document.querySelector(`.order-item[data-item-index="${index}"]`);
  if (!itemRow) return;
  
  const availableStock = parseInt(itemRow.dataset.availableStock) || 99;
  const maxQty = Math.min(99, availableStock);
  let qty = parseInt(input.value) || 1;
  
  if (qty < 1) qty = 1;
  if (qty > maxQty) qty = maxQty;
  
  input.value = qty;
  updateItemSubtotal(index);
}

function updateItemSubtotal(index) {
  const itemRow = document.querySelector(`.order-item[data-item-index="${index}"]`);
  if (!itemRow) return;
  
  const price = parseFloat(itemRow.dataset.price) || 0;
  const qtyInput = itemRow.querySelector('.qty-input');
  const qty = parseInt(qtyInput.value) || 1;
  const subtotalCell = itemRow.querySelector('.item-subtotal');
  
  if (subtotalCell) {
    subtotalCell.innerHTML = '&#8369;' + (price * qty).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
  }
  
  updateOrderSummary();
}

function updateOrderSummary() {
  const itemRows = document.querySelectorAll('.order-item');
  let merchandiseSubtotal = 0;
  
  itemRows.forEach((row, index) => {
    const price = parseFloat(row.dataset.price) || 0;
    const qtyInput = row.querySelector('.qty-input');
    const qty = parseInt(qtyInput?.value) || 1;
    merchandiseSubtotal += price * qty;
  });
  
  // Update merchandise subtotal display
  const merchSubtotalEl = document.getElementById('merchandiseSubtotal');
  if (merchSubtotalEl) {
    merchSubtotalEl.innerHTML = '&#8369;' + Math.round(merchandiseSubtotal).toLocaleString('en-PH');
  }
  
  // Update total amount (recalculate with shipping and discounts)
  recalculateTotal(merchandiseSubtotal);
}

function recalculateTotal(merchandiseSubtotal) {
  const shippingAmountEl = document.getElementById('shippingAmount');
  const totalAmountEl = document.getElementById('totalAmount');
  const shopVoucherSelect = document.getElementById('shop_voucher');
  const shippingVoucherSelect = document.getElementById('shipping_voucher');
  
  if (!shippingAmountEl || !totalAmountEl) return;
  
  const shippingFee = parseFloat(shippingAmountEl.dataset.shipping) || 0;
  
  let voucherDiscount = 0;
  let shippingDiscount = 0;
  
  if (shopVoucherSelect) {
    const selectedOpt = shopVoucherSelect.options[shopVoucherSelect.selectedIndex];
    if (selectedOpt && selectedOpt.dataset.type === 'fixed_discount') {
      const minSpend = parseFloat(selectedOpt.dataset.minSpend) || 0;
      if (merchandiseSubtotal >= minSpend) {
        voucherDiscount = parseFloat(selectedOpt.dataset.amount) || 0;
        voucherDiscount = Math.min(voucherDiscount, merchandiseSubtotal);
      }
    }
  }
  
  if (shippingVoucherSelect) {
    const selectedOpt = shippingVoucherSelect.options[shippingVoucherSelect.selectedIndex];
    if (selectedOpt && selectedOpt.dataset.type === 'free_shipping') {
      const minSpend = parseFloat(selectedOpt.dataset.minSpend) || 0;
      if (merchandiseSubtotal >= minSpend) {
        shippingDiscount = shippingFee;
      }
    }
  }
  
  const totalDiscount = voucherDiscount + shippingDiscount;
  const totalPayment = Math.max(0, (merchandiseSubtotal + shippingFee) - totalDiscount);
  
  totalAmountEl.textContent = '&#8369;' + Math.round(totalPayment).toLocaleString('en-PH');
  totalAmountEl.dataset.merchandise = merchandiseSubtotal;
  
  // Update discount displays
  const voucherDiscountRow = document.getElementById('voucherDiscountRow');
  const voucherDiscountAmount = document.getElementById('voucherDiscountAmount');
  if (voucherDiscountRow && voucherDiscountAmount) {
    if (voucherDiscount > 0) {
      voucherDiscountRow.style.display = '';
      voucherDiscountAmount.textContent = '-&#8369;' + Math.round(voucherDiscount).toLocaleString('en-PH');
    } else {
      voucherDiscountRow.style.display = 'none';
    }
  }
  
  const shippingDiscountRow = document.getElementById('shippingDiscountRow');
  const shippingDiscountAmount = document.getElementById('shippingDiscountAmount');
  if (shippingDiscountRow && shippingDiscountAmount) {
    if (shippingDiscount > 0) {
      shippingDiscountRow.style.display = '';
      shippingDiscountAmount.textContent = '-&#8369;' + Math.round(shippingDiscount).toLocaleString('en-PH');
    } else {
      shippingDiscountRow.style.display = 'none';
    }
  }
}

const requestInvoiceBtn = document.getElementById('requestInvoiceBtn');
if (requestInvoiceBtn) {
  requestInvoiceBtn.addEventListener('click', function () {
    const toast = document.createElement('div');
    toast.className = 'toast success';
    toast.textContent = 'E-Invoice request submitted. We will attach it to your order records.';
    document.body.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('show'));
    setTimeout(() => {
      toast.classList.remove('show');
      setTimeout(() => toast.remove(), 260);
    }, 2300);
  });
}

function formatPeso(amount) {
  const n = Number(amount);
  if (!Number.isFinite(n)) {
    return '0';
  }
  return Math.round(n).toLocaleString('en-PH');
}

function syncCourierSummary() {
  const checkedCourier = document.querySelector('input[name="shipping_courier"]:checked');
  const shopVoucherSelect = document.getElementById('shop_voucher');
  const shippingVoucherSelect = document.getElementById('shipping_voucher');
  const shippingLabel = document.getElementById('shippingLabel');
  const shippingAmount = document.getElementById('shippingAmount');
  const totalAmount = document.getElementById('totalAmount');
  const voucherDiscountRow = document.getElementById('voucherDiscountRow');
  const voucherDiscountAmount = document.getElementById('voucherDiscountAmount');
  const shippingDiscountRow = document.getElementById('shippingDiscountRow');
  const shippingDiscountAmount = document.getElementById('shippingDiscountAmount');
  if (!checkedCourier || !shopVoucherSelect || !shippingVoucherSelect || !shippingLabel || !shippingAmount || !totalAmount) {
    return;
  }

  const shipMeta = checkedCourier.closest('.ship-opt')?.querySelector('.ship-meta-value');
  if (!shipMeta) {
    return;
  }

  const courierLabel = shipMeta.dataset.label || 'Courier';
  const shippingFee = Number(shipMeta.dataset.fee || '0');
  const merchandise = Number(totalAmount.dataset.merchandise || '0');
  const selectedShopVoucherOpt = shopVoucherSelect.options[shopVoucherSelect.selectedIndex];
  const selectedShippingVoucherOpt = shippingVoucherSelect.options[shippingVoucherSelect.selectedIndex];
  const shopVoucherAmount = Number(selectedShopVoucherOpt?.dataset.amount || '0');
  const shopVoucherMinSpend = Number(selectedShopVoucherOpt?.dataset.minSpend || '0');
  const shippingVoucherMinSpend = Number(selectedShippingVoucherOpt?.dataset.minSpend || '0');
  const shopVoucherEligible = merchandise >= shopVoucherMinSpend;
  const shippingVoucherEligible = merchandise >= shippingVoucherMinSpend;

  let voucherDiscount = 0;
  let shippingDiscount = 0;
  if (shopVoucherEligible && (selectedShopVoucherOpt?.dataset.type || 'none') === 'fixed_discount') {
    voucherDiscount = Math.min(shopVoucherAmount, merchandise);
  }
  if (shippingVoucherEligible && (selectedShippingVoucherOpt?.dataset.type || 'none') === 'free_shipping') {
    shippingDiscount = shippingFee;
  }
  const totalDiscount = voucherDiscount + shippingDiscount;
  const total = Math.max(0, (merchandise + shippingFee) - totalDiscount);

  shippingLabel.textContent = 'Shipping Subtotal (' + courierLabel + ')';
  shippingAmount.textContent = 'PHP ' + formatPeso(shippingFee);
  totalAmount.textContent = 'PHP ' + formatPeso(total);

  if (voucherDiscountRow && voucherDiscountAmount) {
    if (voucherDiscount > 0) {
      voucherDiscountRow.style.display = '';
      voucherDiscountAmount.textContent = '-PHP ' + formatPeso(voucherDiscount);
    } else {
      voucherDiscountRow.style.display = 'none';
    }
  }

  if (shippingDiscountRow && shippingDiscountAmount) {
    if (shippingDiscount > 0) {
      shippingDiscountRow.style.display = '';
      shippingDiscountAmount.textContent = '-PHP ' + formatPeso(shippingDiscount);
    } else {
      shippingDiscountRow.style.display = 'none';
    }
  }
}

document.querySelectorAll('input[name="shipping_courier"]').forEach(function (input) {
  input.addEventListener('change', syncCourierSummary);
});
const shopVoucherSelectEl = document.getElementById('shop_voucher');
if (shopVoucherSelectEl) {
  shopVoucherSelectEl.addEventListener('change', syncCourierSummary);
}
const shippingVoucherSelectEl = document.getElementById('shipping_voucher');
if (shippingVoucherSelectEl) {
  shippingVoucherSelectEl.addEventListener('change', syncCourierSummary);
}
syncCourierSummary();

function syncQrPaymentPanel() {
  const checkedPayment = document.querySelector('input[name="payment_method"]:checked');
  const qrPanel = document.getElementById('qrPaymentPanel');
  const qrImage = document.getElementById('qrImage');
  const qrTitle = document.getElementById('qrTitle');
  const qrEmptyMsg = document.getElementById('qrEmptyMsg');
  const digitalFields = document.getElementById('digitalPaymentFields');
  if (!checkedPayment || !qrPanel || !qrImage || !qrTitle || !qrEmptyMsg) {
    return;
  }

  const method = checkedPayment.value;
  if (method !== 'gcash' && method !== 'gotime') {
    qrPanel.style.display = 'none';
    if (digitalFields) {
      digitalFields.style.display = 'none';
    }
    return;
  }

  const qrSrc = method === 'gcash' ? qrImage.dataset.gcashSrc : qrImage.dataset.gotimeSrc;
  qrTitle.textContent = method === 'gcash' ? 'Scan this GCash QR to pay' : 'Scan this GoTyme QR to pay';
  qrPanel.style.display = 'block';

  if (qrSrc) {
    qrImage.src = qrSrc;
    qrImage.style.display = 'block';
    qrEmptyMsg.style.display = 'none';
  } else {
    qrImage.removeAttribute('src');
    qrImage.style.display = 'none';
    qrEmptyMsg.style.display = 'block';
  }

  if (digitalFields) {
    digitalFields.style.display = 'block';
  }
}

document.querySelectorAll('input[name="payment_method"]').forEach(function (input) {
  input.addEventListener('change', syncQrPaymentPanel);
});
syncQrPaymentPanel();

// Address picker sync
(function () {
  const picker = document.getElementById('addressPicker');
  if (!picker) return;
  const nameEl = document.getElementById('buyerNameText');
  const contactEl = document.getElementById('buyerContactLine');
  const addressEl = document.getElementById('buyerAddressLine');
  const badgeEl = document.getElementById('buyerLabelBadge');

  picker.querySelectorAll('input[type="radio"][name="address_id"]').forEach(function (input) {
    input.addEventListener('change', function () {
      picker.querySelectorAll('.address-option').forEach(function (opt) {
        opt.classList.remove('selected');
      });
      const option = input.closest('.address-option');
      if (!option) return;
      option.classList.add('selected');
      if (nameEl) nameEl.textContent = option.dataset.name || 'Receiver';
      if (contactEl) contactEl.textContent = option.dataset.contact || 'No contact number';
      if (addressEl) addressEl.textContent = option.dataset.address || 'No complete address found.';
      if (badgeEl) {
        const label = option.dataset.label || '';
        badgeEl.textContent = label;
        badgeEl.style.display = label !== '' ? 'inline-flex' : 'none';
      }
    });
  });
})();

