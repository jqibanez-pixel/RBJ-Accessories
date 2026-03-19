"use strict";
// @ts-nocheck
document.addEventListener('DOMContentLoaded', function () {
    const toastMessage = (window.RBJ_PRODUCT_CONFIG && window.RBJ_PRODUCT_CONFIG.toastMessage) || null;
    const toastType = (window.RBJ_PRODUCT_CONFIG && window.RBJ_PRODUCT_CONFIG.toastType) || null;
    if (toastMessage) {
        const toast = document.createElement('div');
        toast.className = 'toast ' + (toastType === 'error' ? 'error' : 'success');
        toast.textContent = toastMessage;
        document.body.appendChild(toast);
        requestAnimationFrame(() => toast.classList.add('show'));
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 260);
        }, 2400);
    }
    const qtyInput = document.getElementById('qtyInput');
    const qtyMinus = document.getElementById('qtyMinus');
    const qtyPlus = document.getElementById('qtyPlus');
    const mainProductImage = document.getElementById('mainProductImage');
    const choiceInput = document.getElementById('choiceInput');
    const choiceKeyInput = document.getElementById('choiceKeyInput');
    const choiceButtons = Array.from(document.querySelectorAll('.choice-option[data-choice-index]'));
    const thumbs = Array.from(document.querySelectorAll('.thumb[data-image-url]'));
    function setChoiceByIndex(index) {
        const selectedBtn = choiceButtons[index] || null;
        if (selectedBtn && selectedBtn.disabled) {
            return;
        }
        choiceButtons.forEach((btn, btnIndex) => {
            if (btnIndex === index) {
                btn.classList.add('is-active');
            }
            else {
                btn.classList.remove('is-active');
            }
        });
        if (choiceInput && selectedBtn) {
            choiceInput.value = selectedBtn.getAttribute('data-choice-label') || 'Standard package';
        }
        if (choiceKeyInput && selectedBtn) {
            choiceKeyInput.value = selectedBtn.getAttribute('data-choice-key') || '';
        }
        if (mainProductImage && selectedBtn) {
            const imageUrl = selectedBtn.getAttribute('data-image-url') || '';
            if (imageUrl) {
                mainProductImage.src = imageUrl;
            }
        }
        const selectedStock = Number(selectedBtn ? (selectedBtn.getAttribute('data-choice-stock') || '0') : '0') || 0;
        if (qtyInput) {
            const maxQty = selectedStock > 0 ? Math.min(99, selectedStock) : 1;
            qtyInput.max = String(maxQty);
            const currentQty = parseInt(qtyInput.value, 10) || 1;
            if (currentQty > maxQty) {
                qtyInput.value = maxQty;
            }
        }
    }
    function setActiveByIndex(index) {
        thumbs.forEach((thumb, thumbIndex) => {
            if (thumbIndex === index) {
                thumb.classList.add('is-active');
            }
            else {
                thumb.classList.remove('is-active');
            }
        });
    }
    choiceButtons.forEach((button, index) => {
        button.addEventListener('click', function () {
            if (button.disabled)
                return;
            setChoiceByIndex(index);
            setActiveByIndex(index);
        });
    });
    thumbs.forEach((thumb, index) => {
        thumb.addEventListener('click', function () {
            const imageUrl = thumb.getAttribute('data-image-url') || '';
            if (mainProductImage && imageUrl) {
                mainProductImage.src = imageUrl;
            }
            setChoiceByIndex(index);
            setActiveByIndex(index);
        });
    });
    if (choiceButtons.length > 0) {
        const initialIndex = choiceButtons.findIndex(btn => btn.classList.contains('is-active') && !btn.disabled);
        if (initialIndex >= 0) {
            setChoiceByIndex(initialIndex);
            setActiveByIndex(initialIndex);
        }
        else {
            setActiveByIndex(0);
        }
    }
    if (qtyInput && qtyMinus && qtyPlus) {
        qtyMinus.addEventListener('click', function () {
            const n = Math.max(1, (parseInt(qtyInput.value, 10) || 1) - 1);
            qtyInput.value = n;
        });
        qtyPlus.addEventListener('click', function () {
            const maxQty = Math.max(1, parseInt(qtyInput.max, 10) || 99);
            const n = Math.min(maxQty, (parseInt(qtyInput.value, 10) || 1) + 1);
            qtyInput.value = n;
        });
        qtyInput.addEventListener('input', function () {
            let n = parseInt(qtyInput.value, 10) || 1;
            if (n < 1)
                n = 1;
            const maxQty = Math.max(1, parseInt(qtyInput.max, 10) || 99);
            if (n > maxQty)
                n = maxQty;
            qtyInput.value = n;
        });
    }
});
//# sourceMappingURL=user-product.js.map