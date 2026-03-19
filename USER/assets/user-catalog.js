"use strict";
// @ts-nocheck
document.addEventListener('DOMContentLoaded', function () {
    const toastMessage = (window.RBJ_CATALOG_CONFIG && window.RBJ_CATALOG_CONFIG.toastMessage) || null;
    const toastType = (window.RBJ_CATALOG_CONFIG && window.RBJ_CATALOG_CONFIG.toastType) || null;
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
    // Advanced filters toggle
    const toggleAdvanced = document.getElementById('toggleAdvanced');
    const advancedFilters = document.getElementById('advancedFilters');
    const ratingInput = document.getElementById('ratingInput');
    if (toggleAdvanced && advancedFilters) {
        if (advancedFilters.querySelector('input[name="min_price"]').value ||
            advancedFilters.querySelector('input[name="max_price"]').value ||
            (ratingInput && Number(ratingInput.value) > 0)) {
            advancedFilters.classList.add('show');
            toggleAdvanced.textContent = 'Hide Advanced Filters';
        }
        toggleAdvanced.addEventListener('click', function () {
            advancedFilters.classList.toggle('show');
            toggleAdvanced.textContent = advancedFilters.classList.contains('show') ? 'Hide Advanced Filters' : 'Show Advanced Filters';
        });
    }
    const filterForm = document.getElementById('filterForm');
    const sortSelect = filterForm ? filterForm.querySelector('select[name="sort"]') : null;
    const categorySelect = filterForm ? filterForm.querySelector('select[name="category"]') : null;
    if (sortSelect && filterForm) {
        sortSelect.addEventListener('change', function () {
            filterForm.submit();
        });
    }
    if (categorySelect && filterForm) {
        categorySelect.addEventListener('change', function () {
            filterForm.submit();
        });
    }
    // Rating filter functionality
    const ratingStars = document.querySelectorAll('#ratingFilter .star');
    const currentRating = parseInt(ratingInput.value);
    // Set initial state
    ratingStars.forEach((star, index) => {
        if (index < currentRating) {
            star.classList.add('active');
        }
    });
    // Handle clicks
    ratingStars.forEach((star, index) => {
        star.addEventListener('click', function () {
            const rating = index + 1;
            ratingInput.value = rating;
            // Update visual state
            ratingStars.forEach((s, i) => {
                if (i < rating) {
                    s.classList.add('active');
                }
                else {
                    s.classList.remove('active');
                }
            });
            // Auto-submit form
            document.getElementById('filterForm').submit();
        });
    });
});
//# sourceMappingURL=user-catalog.js.map