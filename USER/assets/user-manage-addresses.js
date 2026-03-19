"use strict";
// @ts-nocheck
document.addEventListener('DOMContentLoaded', function () {
    const API_PROXY = 'psgc_proxy.php';
    const zoneRegions = {
        north: ['1400000000', '0100000000', '0200000000', '0300000000'],
        south: ['0400000000', '1700000000', '0500000000', '1300000000']
    };
    const zoneSelect = document.getElementById('zoneSelect');
    const provinceSelect = document.getElementById('provinceSelect');
    const citySelect = document.getElementById('citySelect');
    const barangaySelect = document.getElementById('barangaySelect');
    const cityManual = document.getElementById('cityManual');
    const barangayManual = document.getElementById('barangayManual');
    const provinceNote = document.getElementById('provinceNote');
    const mapFrame = document.getElementById('addressMapFrame');
    function resetSelect(selectEl, placeholder) {
        if (!selectEl)
            return;
        selectEl.innerHTML = '<option value="">' + placeholder + '</option>';
    }
    function updateMapPreview() {
        if (!mapFrame)
            return;
        const province = provinceSelect?.value || '';
        const city = citySelect && !citySelect.disabled ? citySelect.value : (cityManual?.value || '');
        const barangay = barangaySelect && !barangaySelect.disabled ? barangaySelect.value : (barangayManual?.value || '');
        const home = document.querySelector('textarea[name="home_address"]')?.value || '';
        const parts = [home, barangay, city, province].filter(Boolean).join(', ');
        const query = parts !== '' ? parts : (province || 'Luzon');
        mapFrame.src = 'https://www.google.com/maps?q=' + encodeURIComponent(query) + '&output=embed';
    }
    function enableManualInputs(useManual) {
        if (!citySelect || !barangaySelect || !cityManual || !barangayManual)
            return;
        if (useManual) {
            citySelect.disabled = true;
            barangaySelect.disabled = true;
            citySelect.style.display = 'none';
            barangaySelect.style.display = 'none';
            cityManual.style.display = 'block';
            barangayManual.style.display = 'block';
            cityManual.disabled = false;
            barangayManual.disabled = false;
            if (provinceNote)
                provinceNote.style.display = 'block';
        }
        else {
            citySelect.disabled = false;
            barangaySelect.disabled = false;
            citySelect.style.display = 'block';
            barangaySelect.style.display = 'block';
            cityManual.style.display = 'none';
            barangayManual.style.display = 'none';
            cityManual.disabled = true;
            barangayManual.disabled = true;
            if (provinceNote)
                provinceNote.style.display = 'none';
        }
    }
    function setLoading(selectEl, label) {
        if (!selectEl)
            return;
        selectEl.innerHTML = '<option value="">' + label + '</option>';
        selectEl.disabled = true;
    }
    function normalizeList(payload) {
        if (Array.isArray(payload))
            return payload;
        if (payload && Array.isArray(payload.data))
            return payload.data;
        if (payload && Array.isArray(payload.items))
            return payload.items;
        return [];
    }
    async function fetchJson(query) {
        const url = API_PROXY + '?' + query;
        const res = await fetch(url, { cache: 'no-store' });
        if (!res.ok)
            throw new Error('fetch failed');
        return res.json();
    }
    async function loadProvincesByZone(zone) {
        if (!zone || !zoneRegions[zone])
            return [];
        const regions = zoneRegions[zone];
        const results = await Promise.all(regions.map(code => fetchJson('type=provinces&region_code=' + encodeURIComponent(code))));
        return results.flatMap(r => normalizeList(r));
    }
    async function handleZoneChange() {
        if (!zoneSelect || !provinceSelect)
            return;
        const zone = zoneSelect.value;
        resetSelect(provinceSelect, 'Select Province');
        resetSelect(citySelect, 'Select City');
        resetSelect(barangaySelect, 'Select Barangay');
        enableManualInputs(false);
        if (!zone) {
            provinceSelect.disabled = false;
            updateMapPreview();
            return;
        }
        setLoading(provinceSelect, 'Loading provinces...');
        try {
            const provinces = await loadProvincesByZone(zone);
            resetSelect(provinceSelect, 'Select Province');
            const seenProv = new Set();
            const seenProvName = new Set();
            provinces
                .sort((a, b) => (a.name || '').localeCompare(b.name || ''))
                .forEach(p => {
                const code = p.code || '';
                const name = p.name || '';
                const keyName = name.toLowerCase();
                if (!code || !name || seenProv.has(code) || seenProvName.has(keyName))
                    return;
                seenProv.add(code);
                seenProvName.add(keyName);
                const opt = document.createElement('option');
                opt.value = code;
                opt.textContent = name;
                provinceSelect.appendChild(opt);
            });
            provinceSelect.disabled = false;
        }
        catch (e) {
            resetSelect(provinceSelect, 'Select Province');
            provinceSelect.disabled = false;
            enableManualInputs(true);
        }
        updateMapPreview();
    }
    if (zoneSelect && provinceSelect) {
        zoneSelect.addEventListener('change', handleZoneChange);
    }
    if (provinceSelect && citySelect) {
        provinceSelect.addEventListener('change', async function () {
            const provinceCode = this.value;
            resetSelect(citySelect, 'Select City');
            resetSelect(barangaySelect, 'Select Barangay');
            if (!provinceCode) {
                enableManualInputs(false);
                updateMapPreview();
                return;
            }
            setLoading(citySelect, 'Loading cities...');
            try {
                const cities = normalizeList(await fetchJson('type=cities&province_code=' + encodeURIComponent(provinceCode)));
                resetSelect(citySelect, 'Select City');
                const seenCity = new Set();
                cities
                    .sort((a, b) => (a.name || '').localeCompare(b.name || ''))
                    .forEach(c => {
                    const code = c.code || '';
                    const name = c.name || '';
                    if (!code || !name || seenCity.has(code))
                        return;
                    seenCity.add(code);
                    const opt = document.createElement('option');
                    opt.value = code;
                    opt.textContent = name;
                    citySelect.appendChild(opt);
                });
                citySelect.disabled = false;
                enableManualInputs(false);
            }
            catch (e) {
                resetSelect(citySelect, 'Select City');
                citySelect.disabled = false;
                enableManualInputs(true);
            }
            updateMapPreview();
        });
    }
    if (citySelect && barangaySelect) {
        citySelect.addEventListener('change', async function () {
            const localityCode = this.value;
            resetSelect(barangaySelect, 'Select Barangay');
            if (!localityCode) {
                updateMapPreview();
                return;
            }
            setLoading(barangaySelect, 'Loading barangays...');
            try {
                const barangays = normalizeList(await fetchJson('type=barangays&city_code=' + encodeURIComponent(localityCode)));
                resetSelect(barangaySelect, 'Select Barangay');
                const seenBrgy = new Set();
                barangays
                    .sort((a, b) => (a.name || '').localeCompare(b.name || ''))
                    .forEach(b => {
                    const name = b.name || '';
                    if (!name || seenBrgy.has(name))
                        return;
                    seenBrgy.add(name);
                    const opt = document.createElement('option');
                    opt.value = name;
                    opt.textContent = name;
                    barangaySelect.appendChild(opt);
                });
                barangaySelect.disabled = false;
            }
            catch (e) {
                resetSelect(barangaySelect, 'Select Barangay');
                barangaySelect.disabled = false;
                enableManualInputs(true);
            }
            updateMapPreview();
        });
    }
    if (cityManual) {
        cityManual.addEventListener('input', updateMapPreview);
    }
    if (barangayManual) {
        barangayManual.addEventListener('input', updateMapPreview);
    }
    const homeAddressEl = document.querySelector('textarea[name="home_address"]');
    if (homeAddressEl) {
        homeAddressEl.addEventListener('input', updateMapPreview);
    }
    updateMapPreview();
    if (zoneSelect && zoneSelect.value) {
        handleZoneChange();
    }
});
//# sourceMappingURL=user-manage-addresses.js.map