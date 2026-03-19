// @ts-nocheck

document.addEventListener('DOMContentLoaded', function () {
  const fileInput = document.getElementById('profile-pic-input');
  const chooseBtn = document.getElementById('chooseAvatarBtn');
  const avatarPreview = document.getElementById('profileAvatarPreview');
  const avatarForm = document.getElementById('avatarUploadForm');
  const provinceSelect = document.getElementById('provinceSelect');
  const citySelect = document.getElementById('citySelect');
  const barangaySelect = document.getElementById('barangaySelect');
  const locationMap = (window.RBJ_ACCOUNT_INFO_CONFIG && window.RBJ_ACCOUNT_INFO_CONFIG.locationMap) || {};
  const selectedProvince = (window.RBJ_ACCOUNT_INFO_CONFIG && window.RBJ_ACCOUNT_INFO_CONFIG.selectedProvince) || "";
  const selectedCity = (window.RBJ_ACCOUNT_INFO_CONFIG && window.RBJ_ACCOUNT_INFO_CONFIG.selectedCity) || "";
  const selectedBarangay = (window.RBJ_ACCOUNT_INFO_CONFIG && window.RBJ_ACCOUNT_INFO_CONFIG.selectedBarangay) || "";

  if (chooseBtn && fileInput) {
    chooseBtn.addEventListener('click', function () {
      fileInput.click();
    });
  }

  if (fileInput && avatarPreview) {
    fileInput.addEventListener('change', function () {
      const file = this.files && this.files[0] ? this.files[0] : null;
      if (!file) return;
      const allowed = ['image/jpeg', 'image/png', 'image/webp'];
      if (!allowed.includes(file.type)) {
        alert('Only JPG, PNG, and WEBP images are allowed.');
        this.value = '';
        return;
      }

      const reader = new FileReader();
      reader.onload = function (e) {
        avatarPreview.src = e.target.result;
      };
      reader.readAsDataURL(file);
    });
  }

  if (avatarForm) {
    avatarForm.addEventListener('submit', function (e) {
      if (!fileInput || !fileInput.files || !fileInput.files.length) {
        e.preventDefault();
        alert('Please choose an image first.');
      }
    });
  }

  if (provinceSelect && citySelect && barangaySelect) {
    const populateCities = function (provinceName, selected) {
      citySelect.innerHTML = '';
      const defaultOpt = document.createElement('option');
      defaultOpt.value = '';
      defaultOpt.textContent = 'Select City';
      citySelect.appendChild(defaultOpt);

      const cityObject = locationMap[provinceName] || {};
      Object.keys(cityObject).forEach(function (cityName) {
        const opt = document.createElement('option');
        opt.value = cityName;
        opt.textContent = cityName;
        if (selected && selected === cityName) opt.selected = true;
        citySelect.appendChild(opt);
      });
    };

    const populateBarangays = function (cityName, selected) {
      barangaySelect.innerHTML = '';
      const defaultOpt = document.createElement('option');
      defaultOpt.value = '';
      defaultOpt.textContent = 'Select Barangay';
      barangaySelect.appendChild(defaultOpt);

      const currentProvince = provinceSelect.value;
      const list = ((locationMap[currentProvince] || {})[cityName]) || [];
      list.forEach(function (brgy) {
        const opt = document.createElement('option');
        opt.value = brgy;
        opt.textContent = brgy;
        if (selected && selected === brgy) opt.selected = true;
        barangaySelect.appendChild(opt);
      });
    };

    if (selectedProvince) {
      provinceSelect.value = selectedProvince;
    }
    populateCities(provinceSelect.value, selectedCity);
    populateBarangays(citySelect.value, selectedBarangay);

    provinceSelect.addEventListener('change', function () {
      populateCities(provinceSelect.value, '');
      populateBarangays('', '');
    });

    citySelect.addEventListener('change', function () {
      populateBarangays(citySelect.value, '');
    });
  }
});