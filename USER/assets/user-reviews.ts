// @ts-nocheck

document.addEventListener('DOMContentLoaded', function () {
  // Rating system
  document.querySelectorAll('.rating-form').forEach(function(form) {
    const ratingInput = form.querySelector('.rating-input');
    if (!ratingInput) return;
    const stars = ratingInput.querySelectorAll('.star');
    const ratingHidden = ratingInput.querySelector('input[type="hidden"]');

    stars.forEach(function(star, index) {
      star.addEventListener('click', function() {
        const rating = index + 1;
        ratingHidden.value = rating;

        // Update star display
        stars.forEach(function(s, i) {
          if (i < rating) {
            s.classList.add('active');
          } else {
            s.classList.remove('active');
          }
        });
      });
    });

    form.addEventListener('submit', function (e) {
      const current = Number(ratingHidden ? ratingHidden.value : 0);
      if (current < 1 || current > 5) {
        e.preventDefault();
        alert('Please select a star rating before submitting your review.');
      }
    });
  });
});