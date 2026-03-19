<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

include '../config.php';

$user_id = (int)$_SESSION['user_id'];

// Create addresses table if not exists
$conn->query("
    CREATE TABLE IF NOT EXISTS user_addresses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        label VARCHAR(50) DEFAULT 'Home',
        receiver_name VARCHAR(100) NOT NULL,
        contact_number VARCHAR(20) NOT NULL,
        province VARCHAR(100) NOT NULL,
        city VARCHAR(100) NOT NULL,
        barangay VARCHAR(150) NOT NULL,
        home_address TEXT NOT NULL,
        is_default TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user_addresses_user (user_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )"
);

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = trim((string)($_POST['csrf_token'] ?? ''));
    if ($posted_token === '' || !hash_equals($csrf_token, $posted_token)) {
        $message = 'Invalid request token. Please refresh and try again.';
        $message_type = 'error';
    } elseif (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_address') {
            $label = trim($_POST['label'] ?? 'Home');
            $receiver_name = trim($_POST['receiver_name'] ?? '');
            $contact_number = trim($_POST['contact_number'] ?? '');
            $province = trim($_POST['province'] ?? '');
            $city = trim($_POST['city'] ?? ($_POST['city_manual'] ?? ''));
            $barangay = trim($_POST['barangay'] ?? ($_POST['barangay_manual'] ?? ''));
            $home_address = trim($_POST['home_address'] ?? '');
            $is_default = isset($_POST['is_default']) ? 1 : 0;
            
            if (empty($receiver_name) || empty($contact_number) || empty($province) || empty($city) || empty($barangay) || empty($home_address)) {
                $message = 'Please fill in all required fields.';
                $message_type = 'error';
            } else {
                // If setting as default, unset other defaults
                if ($is_default) {
                    $stmt = $conn->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $stmt->close();
                }
                
                $stmt = $conn->prepare("INSERT INTO user_addresses (user_id, label, receiver_name, contact_number, province, city, barangay, home_address, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isssssssi", $user_id, $label, $receiver_name, $contact_number, $province, $city, $barangay, $home_address, $is_default);
                $stmt->execute();
                $stmt->close();
                
                $message = 'Address added successfully!';
                $message_type = 'success';
            }
        }
        
        if ($_POST['action'] === 'delete_address' && isset($_POST['address_id'])) {
            $address_id = (int)$_POST['address_id'];
            $stmt = $conn->prepare("DELETE FROM user_addresses WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $address_id, $user_id);
            $stmt->execute();
            $stmt->close();
            
            $message = 'Address deleted successfully!';
            $message_type = 'success';
        }
        
        if ($_POST['action'] === 'set_default' && isset($_POST['address_id'])) {
            $address_id = (int)$_POST['address_id'];
            
            // Unset all defaults
            $stmt = $conn->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
            
            // Set new default
            $stmt = $conn->prepare("UPDATE user_addresses SET is_default = 1 WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $address_id, $user_id);
            $stmt->execute();
            $stmt->close();
            
            $message = 'Default address updated!';
            $message_type = 'success';
        }
    }
}

// Get user addresses
$addresses = [];
$stmt = $conn->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $addresses[] = $row;
}
$stmt->close();

// Get user info for prefill
$stmt = $conn->prepare("SELECT first_name, last_name, contact_number FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user_info = $user_result->fetch_assoc();
$stmt->close();

// Get cart count
$cart_count = 0;
$stmt = $conn->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$cart_count = $result['total'] ?? 0;
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Addresses - MotoFit</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<style>
body { min-height:100vh; background: linear-gradient(135deg,#1b1b1b,#111); color:white; padding-top:100px; font-family:"Montserrat",sans-serif; }
.navbar { position:fixed; top:0; left:0; right:0; display:flex; justify-content:space-between; align-items:center; padding:10px 50px; background: rgba(0,0,0,0.8); z-index:999; }
.navbar .logo { display:flex; align-items:center; gap:10px; color:white; text-decoration:none; font-size:22px; font-weight:700; }
.navbar .logo img { height: 60px; width:auto; }
.navbar .nav-links { display:flex; align-items:center; gap:15px; }
.navbar .nav-links a { color:white; text-decoration:none; font-weight:500; margin-left:15px; }
.navbar .nav-links a:hover { text-decoration:underline; }

.account-dropdown { display:flex; align-items:center; margin-left:15px; position: relative; }
.account-trigger { background: none; border: none; color: white; display: flex; align-items: center; gap: 8px; cursor: pointer; }
.account-menu { position: absolute; top: 110%; right: 0; background: #1e1e1e; border-radius: 10px; min-width: 200px; padding: 8px 0; display: none; box-shadow: 0 10px 30px rgba(0,0,0,0.4); z-index: 999; }
.account-menu a { display: flex; align-items: center; gap: 10px; padding: 10px 15px; color: white; text-decoration: none; font-size: 14px; }
.account-menu a:hover { background: rgba(255,255,255,0.08); }
.account-dropdown.active .account-menu { display: block; }

.wrapper { max-width:900px; margin:auto; padding:20px; }
.page-title { text-align:center; margin-bottom:20px; font-size:32px; }
.page-subtitle { text-align:center; color: rgba(255,255,255,0.7); margin-bottom:30px; }

.address-card {
    background: rgba(0,0,0,0.6);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 14px;
    padding: 20px;
    margin-bottom: 15px;
    position: relative;
}
.address-card.default { border-color: #27ae60; }
.default-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: #27ae60;
    color: white;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}
.address-label { font-size: 14px; color: rgba(255,255,255,0.6); margin-bottom:5px; }
.address-name { font-size: 18px; font-weight: 700; margin-bottom:5px; }
.address-contact { font-size: 14px; color: rgba(255,255,255,0.8); margin-bottom:10px; }
.address-full { font-size: 14px; color: rgba(255,255,255,0.7); line-height: 1.5; }

.address-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}
.btn { padding: 8px 16px; border:none; border-radius:8px; font-weight:600; cursor:pointer; text-decoration:none; display:inline-block; font-size:13px; }
.btn-primary { background: linear-gradient(45deg,#27ae60,#2ecc71); color:white; }
.btn-outline { background: transparent; border:1px solid rgba(255,255,255,0.3); color:white; }
.btn-danger { background: rgba(231,76,60,0.2); border:1px solid rgba(231,76,60,0.4); color:#ff8b8b; }
.btn:hover { opacity: 0.9; }

.add-address-form {
    background: rgba(0,0,0,0.6);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 14px;
    padding: 25px;
    margin-top: 30px;
}
.form-title { font-size: 20px; margin-bottom: 20px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
.form-group { margin-bottom: 15px; }
.form-group label { display: block; margin-bottom: 6px; font-size: 13px; color: rgba(255,255,255,0.8); }
.form-group input, .form-group select, .form-group textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: 10px;
    background: rgba(255,255,255,0.05);
    color: white;
    font-size: 14px;
}
.form-group input[disabled],
.form-group select[disabled],
.form-group textarea[disabled] {
    opacity: 0.6;
    cursor: not-allowed;
}
.inline-note { font-size: 12px; color: rgba(255,255,255,0.7); margin-top: 6px; }
.map-preview {
    margin-top: 14px;
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 12px;
    overflow: hidden;
    background: rgba(0,0,0,0.5);
}
.map-preview iframe { width: 100%; height: 220px; border: 0; display: block; }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus {
    outline: none;
    border-color: #27ae60;
}
.form-group select option { background: #1b1b1b; }
.checkbox-group { display: flex; align-items: center; gap: 8px; }
.checkbox-group input { width: auto; }

.message { padding: 12px 16px; border-radius: 10px; margin-bottom: 20px; }
.message.success { background: rgba(39,174,96,0.2); color: #82e9b0; border: 1px solid rgba(39,174,96,0.4); }
.message.error { background: rgba(231,76,60,0.2); color: #ff8b8b; border: 1px solid rgba(231,76,60,0.4); }

.empty-state { text-align: center; padding: 40px; color: rgba(255,255,255,0.6); }

.back-link { display: inline-flex; align-items: center; gap: 5px; color: rgba(255,255,255,0.8); text-decoration: none; margin-bottom: 20px; }
.back-link:hover { color: #27ae60; }

@media(max-width:768px){
    .form-row { grid-template-columns: 1fr; }
    .navbar { padding: 10px 20px; }
}
</style>
<?php include __DIR__ . '/partials/user_navbar_theme.php'; ?>
</head>
<body>

<nav class="navbar">
  <a href="index.php" class="logo">
    <img src="../rbjlogo.png" alt="RBJ Accessories Logo">
    <span>RBJ Accessories</span>
  </a>
  <div class="nav-links">
    <a href="index.php">Home</a>
    <a href="catalog.php">Shop</a>
    <a href="customize.php">Customize</a>
    <a href="cart.php" class="nav-cart-link"><i class='bx bx-cart'></i><span class="cart-count" data-cart-count style="<?php echo $cart_count > 0 ? '' : 'display:none;'; ?>"><?php echo (int)$cart_count; ?></span></a>
    <?php include __DIR__ . '/partials/account_menu.php'; ?>
  </div>
</nav>

<div class="wrapper">
    <a href="buy_now.php" class="back-link"><i class='bx bx-left-arrow-alt'></i> Back to Checkout</a>
    
    <h1 class="page-title">My Addresses</h1>
    <p class="page-subtitle">Manage your delivery addresses for faster checkout</p>
    
    <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if (empty($addresses)): ?>
        <div class="empty-state">
            <i class='bx bx-map' style="font-size: 48px; margin-bottom: 15px;"></i>
            <p>No addresses saved yet. Add your first address below.</p>
        </div>
    <?php else: ?>
        <?php foreach ($addresses as $addr): ?>
            <div class="address-card <?php echo $addr['is_default'] ? 'default' : ''; ?>">
                <?php if ($addr['is_default']): ?>
                    <span class="default-badge">Default</span>
                <?php endif; ?>
                <div class="address-label"><?php echo htmlspecialchars($addr['label']); ?></div>
                <div class="address-name"><?php echo htmlspecialchars($addr['receiver_name']); ?></div>
                <div class="address-contact"><?php echo htmlspecialchars($addr['contact_number']); ?></div>
                <div class="address-full">
                    <?php echo htmlspecialchars($addr['home_address']); ?>, 
                    <?php echo htmlspecialchars($addr['barangay']); ?>, 
                    <?php echo htmlspecialchars($addr['city']); ?>, 
                    <?php echo htmlspecialchars($addr['province']); ?>
                </div>
                <div class="address-actions">
                    <?php if (!$addr['is_default']): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="action" value="set_default">
                            <input type="hidden" name="address_id" value="<?php echo (int)$addr['id']; ?>">
                            <button type="submit" class="btn btn-outline">Set as Default</button>
                        </form>
                    <?php endif; ?>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this address?');">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="action" value="delete_address">
                        <input type="hidden" name="address_id" value="<?php echo (int)$addr['id']; ?>">
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <div class="add-address-form">
        <h2 class="form-title">Add New Address</h2>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="action" value="add_address">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Address Label</label>
                    <input type="text" name="label" placeholder="Home, Work, etc." value="Home">
                </div>
                <div class="form-group">
                    <label>Contact Number *</label>
                    <input type="text" name="contact_number" placeholder="09xxxxxxxxx" value="<?php echo htmlspecialchars($user_info['contact_number'] ?? ''); ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>Receiver Name *</label>
                <input type="text" name="receiver_name" placeholder="Full name of receiver" value="<?php echo htmlspecialchars(($user_info['first_name'] ?? '') . ' ' . ($user_info['last_name'] ?? '')); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Zone *</label>
                <select name="zone" id="zoneSelect" required>
                    <option value="">Select Zone</option>
                    <option value="north">North Luzon</option>
                    <option value="south">South Luzon</option>
                </select>
            </div>

            <div class="form-group">
                <label>Province *</label>
                <select name="province" id="provinceSelect" required>
                    <option value="">Select Province</option>
                </select>
                <div class="inline-note" id="provinceNote" style="display:none;">City/Barangay list not available. Please type manually below.</div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>City *</label>
                    <select name="city" id="citySelect" required>
                        <option value="">Select City</option>
                    </select>
                    <input type="text" id="cityManual" name="city_manual" placeholder="Type City" style="display:none;" disabled>
                </div>
                <div class="form-group">
                    <label>Barangay *</label>
                    <select name="barangay" id="barangaySelect" required>
                        <option value="">Select Barangay</option>
                    </select>
                    <input type="text" id="barangayManual" name="barangay_manual" placeholder="Type Barangay" style="display:none;" disabled>
                </div>
            </div>
            
            <div class="form-group">
                <label>Home Address *</label>
                <textarea name="home_address" placeholder="House number, street name, subdivision, etc." required></textarea>
            </div>
            
            <div class="form-group checkbox-group">
                <input type="checkbox" name="is_default" id="is_default">
                <label for="is_default">Set as default address</label>
            </div>
            
            <button type="submit" class="btn btn-primary">Save Address</button>
        </form>

        <div class="map-preview">
            <iframe id="addressMapFrame" src="https://www.google.com/maps?q=Laguna&output=embed" title="Address Map Preview"></iframe>
        </div>
    </div>
</div>

<script>
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
    if (!selectEl) return;
    selectEl.innerHTML = '<option value="">' + placeholder + '</option>';
}

function updateMapPreview() {
    if (!mapFrame) return;
    const province = provinceSelect?.value || '';
    const city = citySelect && !citySelect.disabled ? citySelect.value : (cityManual?.value || '');
    const barangay = barangaySelect && !barangaySelect.disabled ? barangaySelect.value : (barangayManual?.value || '');
    const home = document.querySelector('textarea[name="home_address"]')?.value || '';
    const parts = [home, barangay, city, province].filter(Boolean).join(', ');
    const query = parts !== '' ? parts : (province || 'Luzon');
    mapFrame.src = 'https://www.google.com/maps?q=' + encodeURIComponent(query) + '&output=embed';
}

function enableManualInputs(useManual) {
    if (!citySelect || !barangaySelect || !cityManual || !barangayManual) return;
    if (useManual) {
        citySelect.disabled = true;
        barangaySelect.disabled = true;
        citySelect.style.display = 'none';
        barangaySelect.style.display = 'none';
        cityManual.style.display = 'block';
        barangayManual.style.display = 'block';
        cityManual.disabled = false;
        barangayManual.disabled = false;
        if (provinceNote) provinceNote.style.display = 'block';
    } else {
        citySelect.disabled = false;
        barangaySelect.disabled = false;
        citySelect.style.display = 'block';
        barangaySelect.style.display = 'block';
        cityManual.style.display = 'none';
        barangayManual.style.display = 'none';
        cityManual.disabled = true;
        barangayManual.disabled = true;
        if (provinceNote) provinceNote.style.display = 'none';
    }
}

function setLoading(selectEl, label) {
    if (!selectEl) return;
    selectEl.innerHTML = '<option value="">' + label + '</option>';
    selectEl.disabled = true;
}

function normalizeList(payload) {
    if (Array.isArray(payload)) return payload;
    if (payload && Array.isArray(payload.data)) return payload.data;
    if (payload && Array.isArray(payload.items)) return payload.items;
    return [];
}

async function fetchJson(query) {
    const url = API_PROXY + '?' + query;
    const res = await fetch(url, { cache: 'no-store' });
    if (!res.ok) throw new Error('fetch failed');
    return res.json();
}

async function loadProvincesByZone(zone) {
    if (!zone || !zoneRegions[zone]) return [];
    const regions = zoneRegions[zone];
    const results = await Promise.all(regions.map(code =>
        fetchJson('type=provinces&region_code=' + encodeURIComponent(code))
    ));
    return results.flatMap(r => normalizeList(r));
}

async function handleZoneChange() {
    if (!zoneSelect || !provinceSelect) return;
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
                if (!code || !name || seenProv.has(code) || seenProvName.has(keyName)) return;
                seenProv.add(code);
                seenProvName.add(keyName);
                const opt = document.createElement('option');
                opt.value = code;
                opt.textContent = name;
                provinceSelect.appendChild(opt);
            });
        provinceSelect.disabled = false;
    } catch (e) {
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
    provinceSelect.addEventListener('change', async function() {
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
                    if (!code || !name || seenCity.has(code)) return;
                    seenCity.add(code);
                    const opt = document.createElement('option');
                    opt.value = code;
                    opt.textContent = name;
                    citySelect.appendChild(opt);
                });
            citySelect.disabled = false;
            enableManualInputs(false);
        } catch (e) {
            resetSelect(citySelect, 'Select City');
            citySelect.disabled = false;
            enableManualInputs(true);
        }
        updateMapPreview();
    });
}

if (citySelect && barangaySelect) {
    citySelect.addEventListener('change', async function() {
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
                    if (!name || seenBrgy.has(name)) return;
                    seenBrgy.add(name);
                    const opt = document.createElement('option');
                    opt.value = name;
                    opt.textContent = name;
                    barangaySelect.appendChild(opt);
                });
            barangaySelect.disabled = false;
        } catch (e) {
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
</script>

<?php include __DIR__ . '/partials/user_footer.php'; ?>
</body>
</html>





