// @ts-nocheck

function changeSort(sortValue) {
  const url = new URL(window.location);
  url.searchParams.set('sort', sortValue);
  window.location.href = url.toString();
}

function addToCart(productId) {
  // Simple add to cart - in real app would use AJAX
  alert('Add to cart functionality would be implemented here with AJAX calls to maintain search state.');
}

// Rating filter functionality
document.addEventListener('DOMContentLoaded', function() {
  const ratingStars = document.querySelectorAll('#ratingFilter .star');
  const ratingInput = document.getElementById('ratingInput');
  const currentRating = parseInt(ratingInput.value);

  // Set initial state
  ratingStars.forEach((star, index) => {
    if (index < currentRating) {
      star.classList.add('active');
    }
  });

  // Handle clicks
  ratingStars.forEach((star, index) => {
    star.addEventListener('click', function() {
      const rating = index + 1;
      ratingInput.value = rating;

      // Update visual state
      ratingStars.forEach((s, i) => {
        if (i < rating) {
          s.classList.add('active');
        } else {
          s.classList.remove('active');
        }
      });

      // Auto-submit form
      document.getElementById('searchForm').submit();
    });
  });
});