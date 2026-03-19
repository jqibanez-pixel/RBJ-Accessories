"use strict";
// @ts-nocheck
const toastMessage = (window.RBJ_CART_CONFIG && window.RBJ_CART_CONFIG.toastMessage) || null;
const toastType = (window.RBJ_CART_CONFIG && window.RBJ_CART_CONFIG.toastType) || null;
if (toastMessage) {
    const toast = document.createElement('div');
    let toastClass = 'success';
    if (toastType === 'error')
        toastClass = 'error';
    else if (toastType === 'warning')
        toastClass = 'warning';
    toast.className = 'toast ' + toastClass;
    toast.textContent = toastMessage;
    document.body.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('show'));
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 260);
    }, 2400);
}
// Toggle all checkboxes
function toggleAllItems(source) {
    const checkboxes = document.querySelectorAll('input[name="selected_items[]"]');
    checkboxes.forEach(cb => cb.checked = source.checked);
    updateSelectedCount();
    updateOrderSummary();
}
// Update selected count display and recalculate totals
function updateSelectedCount() {
    const checked = document.querySelectorAll('input[name="selected_items[]"]:checked').length;
    const total = document.querySelectorAll('input[name="selected_items[]"]').length;
    const countSpan = document.getElementById('selected-count');
    if (countSpan) {
        countSpan.textContent = checked + ' of ' + total + ' selected';
    }
    updateOrderSummary();
}
// Update order summary based on selected items
function updateOrderSummary() {
    const checkedItems = document.querySelectorAll('input[name="selected_items[]"]:checked');
    const allItems = document.querySelectorAll('.cart-item');
    let selectedItemsCount = 0;
    let selectedSubtotal = 0;
    checkedItems.forEach(checkbox => {
        const cartItem = checkbox.closest('.cart-item');
        if (cartItem) {
            const priceText = cartItem.querySelector('p:nth-of-type(2)')?.textContent || '';
            const qtyInput = cartItem.querySelector('.quantity-controls input');
            const qty = parseInt(qtyInput?.value) || 1;
            // Extract price from text like "₱3800.00 each"
            const priceMatch = priceText.match(/₱?([\d,]+\.?\d*)/);
            if (priceMatch) {
                const price = parseFloat(priceMatch[1].replace(/,/g, ''));
                selectedSubtotal += price * qty;
                selectedItemsCount += qty;
            }
        }
    });
    // Update the Order Summary display
    const itemsLabel = document.querySelector('.summary-label');
    const itemsValue = document.getElementById('summary-items-value');
    const totalValue = document.getElementById('summary-total-value');
    // Total is just the subtotal
    const total = selectedSubtotal;
    // Update the summary elements
    if (itemsLabel) {
        itemsLabel.textContent = `Items (${selectedItemsCount}):`;
    }
    if (itemsValue) {
        itemsValue.innerHTML = `&#8369;${selectedSubtotal.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }
    // Update total
    if (totalValue) {
        totalValue.innerHTML = `&#8369;${total.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }
}
// Initialize count on page load
document.addEventListener('DOMContentLoaded', function () {
    updateSelectedCount();
    updateOrderSummary();
});
function postForm(fields) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'cart.php';
    const tokenInput = document.createElement('input');
    tokenInput.type = 'hidden';
    tokenInput.name = 'csrf_token';
    tokenInput.value = (window.RBJ_CART_CONFIG && window.RBJ_CART_CONFIG.csrfToken) || '';
    form.appendChild(tokenInput);
    Object.keys(fields).forEach(key => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = fields[key];
        form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
}
function changeQuantity(cartId, newQuantity) {
    if (Number(newQuantity) < 1)
        return;
    updateOrderSummary(); // Update totals before form submission
    postForm({ cart_id: cartId, quantity: newQuantity, update_quantity: 1 });
}
function updateQuantity(cartId, quantity) {
    changeQuantity(cartId, quantity);
}
function removeItem(cartId) {
    if (!confirm('Are you sure you want to remove this item from your cart?'))
        return;
    postForm({ cart_id: cartId, remove_item: 1 });
}
// Variant selector functions
function toggleVariantDropdown(cartId) {
    const dropdown = document.getElementById('variant-dropdown-' + cartId);
    const btn = dropdown.previousElementSibling;
    if (dropdown.classList.contains('show')) {
        dropdown.classList.remove('show');
        btn.classList.remove('active');
    }
    else {
        // Close all other dropdowns first
        document.querySelectorAll('.variant-dropdown.show').forEach(d => d.classList.remove('show'));
        document.querySelectorAll('.variant-btn.active').forEach(b => b.classList.remove('active'));
        dropdown.classList.add('show');
        btn.classList.add('active');
    }
}
function selectVariant(cartId, templateId, choiceLabel, choiceKey, choiceImageUrl) {
    // Create a form to update the cart item
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'cart.php';
    const tokenInput = document.createElement('input');
    tokenInput.type = 'hidden';
    tokenInput.name = 'csrf_token';
    tokenInput.value = (window.RBJ_CART_CONFIG && window.RBJ_CART_CONFIG.csrfToken) || '';
    form.appendChild(tokenInput);
    // Add fields to update variant
    const updateInput = document.createElement('input');
    updateInput.type = 'hidden';
    updateInput.name = 'update_variant';
    updateInput.value = '1';
    form.appendChild(updateInput);
    const cartIdInput = document.createElement('input');
    cartIdInput.type = 'hidden';
    cartIdInput.name = 'cart_id';
    cartIdInput.value = cartId;
    form.appendChild(cartIdInput);
    const templateIdInput = document.createElement('input');
    templateIdInput.type = 'hidden';
    templateIdInput.name = 'template_id';
    templateIdInput.value = templateId;
    form.appendChild(templateIdInput);
    const customizationsInput = document.createElement('input');
    customizationsInput.type = 'hidden';
    customizationsInput.name = 'customizations';
    customizationsInput.value = choiceLabel;
    form.appendChild(customizationsInput);
    const choiceKeyInput = document.createElement('input');
    choiceKeyInput.type = 'hidden';
    choiceKeyInput.name = 'choice_key';
    choiceKeyInput.value = choiceKey;
    form.appendChild(choiceKeyInput);
    const choiceImageInput = document.createElement('input');
    choiceImageInput.type = 'hidden';
    choiceImageInput.name = 'choice_image_url';
    choiceImageInput.value = choiceImageUrl;
    form.appendChild(choiceImageInput);
    document.body.appendChild(form);
    form.submit();
}
// Close dropdowns when clicking outside
document.addEventListener('click', function (e) {
    if (!e.target.closest('.variant-selector')) {
        document.querySelectorAll('.variant-dropdown.show').forEach(d => d.classList.remove('show'));
        document.querySelectorAll('.variant-btn.active').forEach(b => b.classList.remove('active'));
    }
});
// Checkout function - only selected items go to buy_now
function proceedToCheckout() {
    const checkedItems = document.querySelectorAll('input[name="selected_items[]"]:checked');
    if (checkedItems.length === 0) {
        alert('Please select at least one item to checkout.');
        return;
    }
    // Get message for seller
    const messageForSeller = document.getElementById('message_for_seller')?.value || '';
    // Build URL for checkout - pass selected item IDs
    const selectedIds = Array.from(checkedItems).map(cb => cb.value);
    let url = 'buy_now.php?source=cart&selected_ids=' + encodeURIComponent(selectedIds.join(','));
    if (messageForSeller.trim() !== '') {
        url += '&message_for_seller=' + encodeURIComponent(messageForSeller);
    }
    // Redirect to buy_now with selected items
    window.location.href = url;
}
//# sourceMappingURL=user-cart.js.map