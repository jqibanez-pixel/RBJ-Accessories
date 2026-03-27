<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: ../login.php");
  exit();
}

include '../config.php';
$cart_count = 0;
$user_id = (int)$_SESSION['user_id'];
$stmt = $conn->prepare("SELECT COALESCE(SUM(quantity), 0) as total FROM cart WHERE user_id = ?");
if ($stmt) {
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $cart_count = (int)($row['total'] ?? 0);
  $stmt->close();
}

$seat_texture_candidates = [];
$texture_sources = [
  ['abs' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'texture', 'rel' => '../texture/'],
  ['abs' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'textures', 'rel' => '../textures/']
];
$allowed_extensions = ['png', 'jpg', 'jpeg', 'webp', 'bmp', 'gif', 'avif', 'jfif'];

foreach ($texture_sources as $source) {
  if (!is_dir($source['abs'])) {
    continue;
  }
  $files = scandir($source['abs']);
  if ($files === false) {
    continue;
  }
  foreach ($files as $file) {
    if ($file === '.' || $file === '..') {
      continue;
    }
    $full_path = $source['abs'] . DIRECTORY_SEPARATOR . $file;
    if (!is_file($full_path)) {
      continue;
    }
    $ext = strtolower((string)pathinfo($file, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_extensions, true)) {
      continue;
    }
    $key = strtolower((string)pathinfo($file, PATHINFO_FILENAME));
    if ($key === '') {
      continue;
    }
    $relative = $source['rel'] . $file;
    if (!isset($seat_texture_candidates[$key])) {
      $seat_texture_candidates[$key] = [];
    }
    if (!in_array($relative, $seat_texture_candidates[$key], true)) {
      $seat_texture_candidates[$key][] = $relative;
    }
  }
}

$required_texture_keys = ['sand_tela'];
foreach ($required_texture_keys as $required_key) {
  if (isset($seat_texture_candidates[$required_key])) {
    continue;
  }
  foreach ($texture_sources as $source) {
    foreach ($allowed_extensions as $ext) {
      $filename = $required_key . '.' . $ext;
      $full_path = $source['abs'] . DIRECTORY_SEPARATOR . $filename;
      if (is_file($full_path)) {
        $seat_texture_candidates[$required_key] = [$source['rel'] . $filename];
        break 2;
      }
    }
  }
}

ksort($seat_texture_candidates, SORT_NATURAL | SORT_FLAG_CASE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Test Customize - RBJ Accessories</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">

<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

<!-- THREE JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/GLTFLoader.js"></script>

<style>
:root {
  --bg-0: #0c1017;
  --bg-1: #151d29;
  --panel: rgba(255, 255, 255, 0.055);
  --panel-strong: rgba(255, 255, 255, 0.11);
  --border-soft: rgba(255, 255, 255, 0.18);
  --text-main: #f7f8fb;
  --text-muted: #c9cfde;
  --accent: #2ac676;
  --accent-2: #1e9f5e;
  --accent-3: #48d48f;
  --danger-1: #ff5d5d;
  --danger-2: #ff7b7b;
}

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
  font-family: "Manrope", sans-serif;
}

body {
  min-height: 100vh;
  background:
    radial-gradient(1200px 560px at 10% -14%, rgba(42, 198, 118, 0.17), transparent 60%),
    radial-gradient(920px 520px at 102% -4%, rgba(53, 120, 255, 0.14), transparent 58%),
    radial-gradient(700px 380px at 50% 110%, rgba(255, 255, 255, 0.03), transparent 70%),
    linear-gradient(145deg, var(--bg-0), var(--bg-1));
  color: var(--text-main);
  padding-top: 90px;
  margin: 0;
}

.navbar {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 40px;
  background: rgba(10, 13, 20, 0.78);
  backdrop-filter: blur(14px);
  z-index: 1000;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  box-shadow: 0 12px 24px rgba(0, 0, 0, 0.28);
}

.logo {
  display: flex;
  gap: 10px;
  align-items: center;
  color: #fff;
  text-decoration: none;
  font-family: "Sora", sans-serif;
  font-weight: 700;
  letter-spacing: 0.25px;
}

.logo img {
  height: 60px;
  width: auto;
  background: transparent;
}

.nav-links {
  display: flex;
  align-items: center;
  gap: 15px;
}

.nav-links a {
  color: #fff;
  text-decoration: none;
  font-weight: 500;
  margin-left: 15px;
  transition: color 0.3s;
}

.nav-links a:hover {
  color: #27ae60;
  text-decoration: underline;
}

/* Account Dropdown */
.account-dropdown {
  position: relative;
  display: flex;
  align-items: center;
  margin-left: 15px;
}

.account-trigger {
  background: none;
  border: none;
  color: white;
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  transition: opacity 0.3s;
}

.account-trigger:hover {
  opacity: 0.8;
}

.account-icon {
  width: 40px;
  height: 40px;
  background: #27ae60;
  border-radius: 50%;
  display: flex;
  justify-content: center;
  align-items: center;
  font-weight: bold;
}

.account-username {
  font-weight: 600;
  margin-left: 5px;
  color: white;
}

.account-menu {
  position: absolute;
  top: 110%;
  right: 0;
  background: #1e1e1e;
  border-radius: 10px;
  min-width: 200px;
  padding: 8px 0;
  display: none;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
  z-index: 999;
}

.account-menu a {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 15px;
  color: white;
  text-decoration: none;
  font-size: 14px;
  transition: background 0.3s;
}

.account-menu a:hover {
  background: rgba(255, 255, 255, 0.08);
}

.account-dropdown.active .account-menu {
  display: block;
}

.wrapper {
  max-width: 1280px;
  margin: auto;
  padding: 24px;
}

h1 {
  text-align: center;
  margin-bottom: 20px;
  font-size: 36px;
  font-family: "Sora", sans-serif;
  color: var(--text-main);
  letter-spacing: 0.4px;
  text-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
}

#motor-viewer-section,
#seat-customization,
#preview-section {
  position: relative;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.06), rgba(255, 255, 255, 0.035));
  border: 1px solid var(--border-soft);
  border-radius: 18px;
  box-shadow:
    0 18px 40px rgba(0, 0, 0, 0.38),
    inset 0 1px 0 rgba(255, 255, 255, 0.08);
  padding: 20px 18px 24px;
  margin-bottom: 18px;
  overflow: hidden;
}

#motor-viewer-section::before,
#seat-customization::before,
#preview-section::before {
  content: "";
  position: absolute;
  top: -120px;
  right: -90px;
  width: 260px;
  height: 260px;
  background: radial-gradient(circle, rgba(42, 198, 118, 0.2), transparent 65%);
  pointer-events: none;
}

#seat-viewer, #motor-viewer, #preview-viewer {
  width: 100%;
  height: 420px;
  background:
    linear-gradient(180deg, rgba(255, 255, 255, 0.05), rgba(255, 255, 255, 0.02)),
    #171b22;
  border-radius: 14px;
  border: 1px solid rgba(255, 255, 255, 0.14);
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.08),
    0 10px 26px rgba(0, 0, 0, 0.35);
}

#preview-viewer {
  height: 680px;
  min-height: 560px;
}

.options {
  margin-top: 18px;
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 15px;
  padding: 14px;
  border-radius: 14px;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.065), rgba(255, 255, 255, 0.038));
  border: 1px solid var(--border-soft);
}

.options h3 {
  width: 100%;
  text-align: center;
  margin-bottom: 10px;
  margin-top: 4px;
  font-size: 18px;
  color: var(--accent-3);
  letter-spacing: 0.25px;
  text-transform: uppercase;
}

.options button {
  padding: 10px 18px;
  border: 1px solid transparent;
  border-radius: 12px;
  font-weight: 700;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.22s ease;
  background: rgba(255, 255, 255, 0.09);
  color: var(--text-main);
}

.options button:hover {
  background: rgba(255, 255, 255, 0.16);
  transform: translateY(-1px);
  border-color: rgba(39, 174, 96, 0.65);
  box-shadow: 0 6px 14px rgba(39, 174, 96, 0.18);
}

.options button:focus-visible {
  outline: 2px solid rgba(72, 212, 143, 0.7);
  outline-offset: 2px;
}

.seat-part-buttons {
  width: 100%;
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 10px;
  margin-bottom: 12px;
}

.seat-part-buttons button.active {
  border-color: var(--accent);
  background: rgba(42, 198, 118, 0.28);
  box-shadow: 0 8px 18px rgba(42, 198, 118, 0.2);
}

.seat-part-status {
  width: 100%;
  text-align: center;
  color: #ffd26e;
  font-size: 14px;
  margin-bottom: 6px;
}

.seat-summary-bar {
  width: 100%;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 10px;
  background: rgba(7, 11, 18, 0.42);
  border: 1px solid rgba(255, 255, 255, 0.15);
  margin-bottom: 12px;
}

.seat-summary-text {
  flex: 1 1 320px;
  text-align: left;
  font-size: 13px;
  color: var(--text-muted);
}

.seat-summary-actions {
  display: flex;
  gap: 8px;
}

.seat-summary-actions button {
  padding: 8px 12px;
  border-radius: 10px;
  font-size: 12px;
}

.stitch-controls {
  width: 100%;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-top: 4px;
  margin-bottom: 8px;
}

.stitch-controls button.active {
  border-color: var(--accent);
  background: rgba(42, 198, 118, 0.28);
  box-shadow: 0 8px 18px rgba(42, 198, 118, 0.2);
}

.stitch-apply-all {
  width: 100%;
  text-align: center;
  color: var(--text-muted);
  font-size: 13px;
  margin-top: 4px;
}

.seat-texture-scroll {
  width: 100%;
  display: flex;
  flex-wrap: nowrap;
  gap: 10px;
  overflow-x: auto;
  padding: 8px 4px 14px;
  scrollbar-width: thin;
  scrollbar-color: rgba(39, 174, 96, 0.9) rgba(255, 255, 255, 0.12);
}

.seat-texture-scroll button {
  flex: 0 0 auto;
  white-space: nowrap;
}

.seat-texture-scroll button.texture-hidden {
  display: none;
}

.seat-texture-scroll button.active {
  border-color: var(--accent);
  background: rgba(42, 198, 118, 0.28);
  box-shadow: 0 8px 18px rgba(42, 198, 118, 0.2);
}

.seat-texture-scroll.is-hovered {
  outline: 1px solid rgba(39, 174, 96, 0.45);
  border-radius: 10px;
}

.seat-action-buttons {
  width: 100%;
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 12px;
  margin-top: 12px;
}

#preview-section {
  display: none;
  text-align: center;
}

#preview-section img {
  max-width: 100%;
  border-radius: 14px;
  margin-top: 20px;
}

.button-main {
  padding: 12px 20px;
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 12px;
  font-weight: 700;
  background: linear-gradient(135deg, var(--danger-1), var(--danger-2));
  color: #fff;
  cursor: pointer;
  margin-top: 8px;
  transition: all 0.3s ease;
  box-shadow: 0 8px 20px rgba(255, 77, 77, 0.22);
}

.button-main:hover {
  transform: translateY(-1px);
  box-shadow: 0 12px 24px rgba(255, 77, 77, 0.28);
}

#toggle-textures-btn {
  background: linear-gradient(135deg, rgba(42, 198, 118, 0.28), rgba(42, 198, 118, 0.18));
  border-color: rgba(72, 212, 143, 0.45);
}

#toggle-textures-btn:hover {
  border-color: rgba(72, 212, 143, 0.8);
}

/* Loading Animation */
.loading {
  display: inline-block;
  width: 20px;
  height: 20px;
  border: 3px solid rgba(255, 255, 255, 0.3);
  border-radius: 50%;
  border-top-color: #27ae60;
  animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Responsive */
@media (max-width: 768px) {
  .wrapper {
    padding: 14px;
  }
  #motor-viewer-section,
  #seat-customization,
  #preview-section {
    padding: 14px 12px 16px;
  }
  #seat-viewer, #motor-viewer, #preview-viewer {
    height: 300px;
  }
  #preview-viewer {
    min-height: 340px;
  }
  .options {
    gap: 10px;
  }
  .options button {
    padding: 9px 14px;
    font-size: 13px;
  }
  .wrapper h1 {
    font-size: 25px;
  }
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
    <a href="customize.php" class="active">Customize</a>
    <a href="cart.php" class="nav-cart-link" title="Cart" aria-label="Cart">
      <i class='bx bx-cart'></i>
      <span class="cart-count" data-cart-count style="<?php echo $cart_count > 0 ? '' : 'display:none;'; ?>">
        <?php echo (int)$cart_count; ?>
      </span>
    </a>
    <?php include __DIR__ . '/partials/account_menu.php'; ?>
  </div>
</nav>

<div class="wrapper">
  <!-- ================= SIMPLE 3D MOTOR VIEWER ================= -->
  <div id="motor-viewer-section">
    <h1>3D Motorcycle Viewer - Test</h1>
    <div id="motor-viewer"></div>
    <div class="options">
      <h3>Debug Info</h3>
      <p id="debug-info" style="color:#ffcc00;font-size:14px;text-align:center;">Loading model...</p>
      <button onclick="reloadModel()">Reload Model</button>
      <button onclick="showSeat()">Show Seat Customization</button>
      <h3>Body Color</h3>
      <button onclick="changeBody('red')">Red</button>
      <button onclick="changeBody('black')">Black</button>
      <button onclick="changeBody('blue')">Blue</button>
    </div>
  </div>

  <!-- ================= SEAT CUSTOMIZATION ================= -->
  <div id="seat-customization" style="display:none">
    <h1>Customize Your Motorcycle Seat</h1>
    <div id="seat-viewer"></div>
    <div class="options">
      <h3>Seat Parts</h3>
      <p id="seat-part-status" class="seat-part-status">Selected part: EXT</p>
      <div class="seat-summary-bar">
        <div id="seat-summary-text" class="seat-summary-text">Seat style summary: none</div>
        <div class="seat-summary-actions">
          <button type="button" id="seat-undo-btn" onclick="undoSeatStyle()" disabled>Undo</button>
          <button type="button" id="seat-reset-btn" onclick="resetSeatStyles()">Reset</button>
        </div>
      </div>
      <div class="seat-part-buttons">
        <button type="button" data-seat-part="EXT" onclick="selectSeatPart('EXT')">EXT</button>
        <button type="button" data-seat-part="HALF OBR" onclick="selectSeatPart('HALF OBR')">Back Passenger</button>
        <button type="button" data-seat-part="HALF RIDER" onclick="selectSeatPart('HALF RIDER')">Front Rider</button>
        <button type="button" data-seat-part="palikpik" onclick="selectSeatPart('palikpik')">Palikpik</button>
        <button type="button" data-seat-part="ULO" onclick="selectSeatPart('ULO')">Ulo</button>
      </div>
      <h3>Seat Texture</h3>
      <div id="seat-texture-scroll" class="seat-texture-scroll">
        <?php if (!empty($seat_texture_candidates)): ?>
          <?php $texture_button_index = 0; ?>
          <?php foreach ($seat_texture_candidates as $texture_key => $texture_paths): ?>
            <button
              type="button"
              class="seat-texture-item<?php echo $texture_button_index >= 8 ? ' texture-hidden' : ''; ?>"
              data-seat-texture="<?php echo htmlspecialchars($texture_key, ENT_QUOTES, 'UTF-8'); ?>"
              onclick="applySeatTexture('<?php echo htmlspecialchars($texture_key, ENT_QUOTES, 'UTF-8'); ?>')"
            >
              <?php echo htmlspecialchars(ucwords(str_replace(['_', '-'], ' ', $texture_key)), ENT_QUOTES, 'UTF-8'); ?>
            </button>
            <?php $texture_button_index++; ?>
          <?php endforeach; ?>
        <?php else: ?>
          <span style="opacity:0.8;color:#ffcc00;padding:8px 4px;">
            No textures found in root <code>texture</code> folder.
          </span>
        <?php endif; ?>
      </div>
      <button type="button" id="toggle-textures-btn" onclick="toggleMoreTextures()">Show More Textures</button>
      <h3>Stitch Color</h3>
      <div class="stitch-controls">
        <button type="button" data-stitch-color="white" onclick="changeStitch('white')">White</button>
        <button type="button" data-stitch-color="black" onclick="changeStitch('black')">Black</button>
        <button type="button" data-stitch-color="red" onclick="changeStitch('red')">Red</button>
        <button type="button" data-stitch-color="blue" onclick="changeStitch('blue')">Blue</button>
        <button type="button" data-stitch-color="gold" onclick="changeStitch('gold')">Gold</button>
      </div>
      <div class="stitch-apply-all">
        <label>
          <input type="checkbox" id="stitch-apply-all-checkbox">
          Apply stitch color to all seat parts
        </label>
      </div>
      <div class="seat-action-buttons">
        <button class="button-main" onclick="backToMotor()">Back to Motor</button>
        <button class="button-main" onclick="goToPreview()">Confirm & Preview</button>
      </div>
    </div>
  </div>

  <!-- ================= PREVIEW SECTION ================= -->
  <div id="preview-section" style="display:none">
    <h1>Preview Your Motorcycle</h1>
    <div id="preview-viewer"></div>
    <div class="options">
      <button class="button-main" onclick="backToSeat()">Back to Seat</button>
      <button class="button-main" onclick="finalConfirm()">Place Order</button>
    </div>
  </div>

</div>

<script>
/* ================= GLOBAL STATE ================= */
let customization = {
  motor: null,
  bodyColor: null,
  seatColor: null
};
var activeView = 'motor';
const pageParams = new URLSearchParams(window.location.search);
const requestedStepRaw = (pageParams.get('step') || 'motor').toLowerCase();
const requestedStep = ['motor', 'seat', 'preview'].includes(requestedStepRaw) ? requestedStepRaw : 'motor';

function syncStepInUrl(step) {
  try {
    const params = new URLSearchParams(window.location.search);
    params.set('step', step);
    const url = window.location.pathname + '?' + params.toString();
    window.history.replaceState({}, '', url);
  } catch (e) {}
}

function setActiveStep(step) {
  const motorSection = document.getElementById("motor-viewer-section");
  const seatSection = document.getElementById("seat-customization");
  const previewSection = document.getElementById("preview-section");
  if (!motorSection || !seatSection || !previewSection) return;

  motorSection.style.display = step === 'motor' ? 'block' : 'none';
  seatSection.style.display = step === 'seat' ? 'block' : 'none';
  previewSection.style.display = step === 'preview' ? 'block' : 'none';
  activeView = step;
  syncStepInUrl(step);
}

let motorMeshes = {};
const MODEL_PATH = '../hondabeat.glb';
const MOTOR_NODE_NAME = 'MOTOR';
const SEAT_NODE_NAME = 'seatclick';
const SEAT_PART_NAMES = ['EXT', 'HALF OBR', 'HALF RIDER', 'palikpik', 'ULO'];
const SEAT_TEXTURE_CANDIDATES = <?php echo json_encode($seat_texture_candidates, JSON_UNESCAPED_SLASHES); ?>;
const SEAT_PART_ALIASES = {
  ext: 'EXT',
  halfobr: 'HALF OBR',
  halfrider: 'HALF RIDER',
  palikpik: 'palikpik',
  ulo: 'ULO'
};
const SEAT_PART_DISPLAY = {
  'EXT': 'EXT',
  'HALF OBR': 'Back Passenger',
  'HALF RIDER': 'Front Rider',
  'palikpik': 'Palikpik',
  'ULO': 'Ulo'
};
const STITCH_NAME_TOKENS = ['stitch', 'tahi', 'seam', 'thread', 'burda', 'tast'];
const GLB_POINTER_SIGNATURE = 'version https://git-lfs.github.com/spec/v1';

function updateDebugInfo(message) {
  const debugInfo = document.getElementById('debug-info');
  if (debugInfo) {
    debugInfo.textContent = message;
  }
}

function getFriendlyModelLoadMessage(error) {
  const message = String((error && error.message) || error || 'Unknown model loading error.');
  if (message.includes('Git LFS pointer')) {
    return 'The 3D model file is still a Git LFS pointer. Download the real .glb asset first.';
  }
  if (message.includes('Not a valid GLB file')) {
    return 'The loaded model response is not a real .glb file.';
  }
  return message;
}

async function fetchValidModelBuffer(modelPath) {
  const response = await fetch(modelPath, { cache: 'no-store' });
  if (!response.ok) {
    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
  }

  const buffer = await response.arrayBuffer();
  if (buffer.byteLength < 12) {
    throw new Error(`Model file is too small (${buffer.byteLength} bytes).`);
  }

  const headerText = new TextDecoder('utf-8').decode(buffer.slice(0, Math.min(buffer.byteLength, 160)));
  if (headerText.startsWith(GLB_POINTER_SIGNATURE)) {
    throw new Error('Git LFS pointer detected instead of a real GLB file.');
  }

  const view = new DataView(buffer);
  const magic = String.fromCharCode(view.getUint8(0), view.getUint8(1), view.getUint8(2), view.getUint8(3));
  if (magic !== 'glTF') {
    throw new Error('Not a valid GLB file.');
  }

  return buffer;
}

async function loadModelScene(modelPath) {
  const buffer = await fetchValidModelBuffer(modelPath);
  return await new Promise((resolve, reject) => {
    const loader = new THREE.GLTFLoader();
    loader.parse(buffer, '', (gltf) => resolve(gltf.scene), (error) => reject(error));
  });
}

function getNodeByName(root, targetName) {
  const needle = String(targetName || '').trim().toLowerCase();
  let found = null;
  root.traverse((obj) => {
    if (!found && String(obj.name || '').trim().toLowerCase() === needle) {
      found = obj;
    }
  });
  return found;
}

function isDescendantOf(node, ancestor) {
  let current = node;
  while (current) {
    if (current === ancestor) return true;
    current = current.parent;
  }
  return false;
}

function centerAndScaleObject(object3d, targetSize = 8) {
  const box = new THREE.Box3().setFromObject(object3d);
  const size = box.getSize(new THREE.Vector3());
  const center = box.getCenter(new THREE.Vector3());
  object3d.position.sub(center);

  const maxDim = Math.max(size.x, size.y, size.z);
  if (maxDim > 0) {
    object3d.scale.multiplyScalar(targetSize / maxDim);
  }
}

function normalizePartName(name) {
  return String(name || '').trim().replace(/\s+/g, ' ').toLowerCase();
}

function resolveSeatPartName(name) {
  const normalized = normalizePartName(name);
  const token = normalized.replace(/[^a-z0-9]/g, '');
  if (SEAT_PART_ALIASES[token]) return SEAT_PART_ALIASES[token];

  for (const key of Object.keys(SEAT_PART_ALIASES)) {
    if (token.includes(key) || key.includes(token)) {
      return SEAT_PART_ALIASES[key];
    }
  }
  return null;
}

function resolveSeatPartForMesh(mesh, seatRoot) {
  let current = mesh;
  while (current) {
    const part = resolveSeatPartName(current.name);
    if (part) return part;
    if (current === seatRoot) break;
    current = current.parent;
  }
  return null;
}

function getSeatPartLabel(partName) {
  return SEAT_PART_DISPLAY[partName] || partName;
}

function normalizeToken(value) {
  return String(value || '').trim().toLowerCase();
}

function getStitchHex(color) {
  const key = normalizeToken(color);
  if (key === 'white') return 0xf8f8f8;
  if (key === 'black') return 0x111111;
  if (key === 'red') return 0xc71f1f;
  if (key === 'blue') return 0x1b56c7;
  if (key === 'gold') return 0xd4af37;
  return 0xf8f8f8;
}

function isStitchMeshObject(mesh) {
  const meshName = normalizeToken(mesh && mesh.name);
  const hitByMeshName = STITCH_NAME_TOKENS.some((token) => meshName.includes(token));
  if (hitByMeshName) return true;

  const materials = Array.isArray(mesh.material) ? mesh.material : [mesh.material];
  return materials.some((mat) => {
    const matName = normalizeToken(mat && mat.name);
    return STITCH_NAME_TOKENS.some((token) => matName.includes(token));
  });
}

function getAvailableSeatParts() {
  const fromMeshMap = [...seatMeshPartByUuid.values()];
  return SEAT_PART_NAMES.filter((part) => fromMeshMap.includes(part));
}

function applyStitchMaterialOverrides(material, stitchColor) {
  if (!material) return;
  const hex = getStitchHex(stitchColor);
  material.map = null;
  material.color.setHex(hex);
  if ('emissive' in material && material.emissive) {
    material.emissive.setHex(hex);
    material.emissiveIntensity = 0.08;
  }
  if ('roughness' in material) {
    material.roughness = 0.4;
  }
}

function logSeatNodeDiagnostics(seatRoot, label) {
  const meshNames = new Set();
  const materialNames = new Set();
  const stitchCandidates = [];

  seatRoot.traverse((obj) => {
    if (!obj.isMesh) return;
    meshNames.add(String(obj.name || '(unnamed mesh)'));
    const mats = Array.isArray(obj.material) ? obj.material : [obj.material];
    mats.forEach((mat) => materialNames.add(String((mat && mat.name) || '(unnamed material)')));
    if (isStitchMeshObject(obj)) {
      stitchCandidates.push(String(obj.name || '(unnamed stitch mesh)'));
    }
  });

  console.log(`[Seat Scan:${label}] Mesh names:`, [...meshNames]);
  console.log(`[Seat Scan:${label}] Material names:`, [...materialNames]);
  console.log(`[Seat Scan:${label}] Stitch candidates:`, stitchCandidates.length ? stitchCandidates : 'none');
}

function cloneNodeWithWorldTransform(node) {
  node.updateWorldMatrix(true, false);
  const cloned = node.clone(true);
  cloned.applyMatrix4(node.matrixWorld);
  cloned.updateMatrixWorld(true);
  return cloned;
}

function alignSeatPreviewToStockSeat(motorNode, seatNode) {
  const stockSeat = getNodeByName(motorNode, 'seatwithmotor');
  if (!stockSeat || !seatNode) return false;

  // 1) Match orientation to stock seat orientation.
  stockSeat.updateMatrixWorld(true);
  const stockQuat = new THREE.Quaternion();
  stockSeat.getWorldQuaternion(stockQuat);
  seatNode.quaternion.copy(stockQuat);
  seatNode.updateMatrixWorld(true);

  // 2) Match size by bbox ratio.
  const stockBox = new THREE.Box3().setFromObject(stockSeat);
  let seatBox = new THREE.Box3().setFromObject(seatNode);
  if (stockBox.isEmpty() || seatBox.isEmpty()) return false;

  const stockSize = stockBox.getSize(new THREE.Vector3());
  const seatSize = seatBox.getSize(new THREE.Vector3());
  const sx = seatSize.x > 0 ? stockSize.x / seatSize.x : 1;
  const sy = seatSize.y > 0 ? stockSize.y / seatSize.y : 1;
  const sz = seatSize.z > 0 ? stockSize.z / seatSize.z : 1;
  seatNode.scale.multiply(new THREE.Vector3(sx, sy, sz));
  seatNode.updateMatrixWorld(true);

  // 3) Match center position to stock seat center.
  seatBox = new THREE.Box3().setFromObject(seatNode);
  const stockCenter = stockBox.getCenter(new THREE.Vector3());
  const seatCenter = seatBox.getCenter(new THREE.Vector3());
  const delta = stockCenter.sub(seatCenter);
  seatNode.position.add(delta);
  seatNode.updateMatrixWorld(true);

  return true;
}

function getColorHex(color, tone = 'default') {
  if (tone === 'seat-preview') {
    if (color === 'red') return 0xff3333;
    if (color === 'black') return 0x0d0d0d;
    if (color === 'brown') return 0xa0522d;
  }
  if (color === 'red') return 0xff0000;
  if (color === 'black') return 0x000000;
  if (color === 'brown') return 0x8B4513;
  if (color === 'blue') return 0x0000ff;
  return null;
}

function applyRenderableMaterial(mesh) {
  const applyOne = (mat) => {
    if (!mat) return;
    mat.side = THREE.DoubleSide;
    if (mat.transparent && mat.opacity === 0) {
      mat.opacity = 1;
      mat.transparent = false;
    }
    mat.needsUpdate = true;
  };

  if (Array.isArray(mesh.material)) {
    mesh.material.forEach(applyOne);
  } else {
    applyOne(mesh.material);
  }
  mesh.visible = true;
  mesh.frustumCulled = false;
}

function fitCameraToObject(camera, controls, object3d, offset = 1.35) {
  const box = new THREE.Box3().setFromObject(object3d);
  if (box.isEmpty()) return;

  const size = box.getSize(new THREE.Vector3());
  const center = box.getCenter(new THREE.Vector3());
  const maxDim = Math.max(size.x, size.y, size.z);
  const fov = THREE.MathUtils.degToRad(camera.fov);
  let distance = (maxDim * 0.5) / Math.tan(fov * 0.5);
  distance *= offset;

  const viewDir = new THREE.Vector3(1, -0.25, 1).normalize();
  camera.position.copy(center).add(viewDir.multiplyScalar(distance));
  camera.near = Math.max(distance / 100, 0.01);
  camera.far = Math.max(distance * 100, 1000);
  camera.updateProjectionMatrix();

  controls.target.copy(center);
  controls.minDistance = Math.max(distance * 0.3, 0.5);
  controls.maxDistance = Math.max(distance * 5, controls.minDistance + 10);
  controls.update();
}

function isElementVisible(el) {
  return !!el && el.offsetParent !== null && getComputedStyle(el).display !== 'none';
}

function createRenderer(container, width, height, options) {
  const opts = Object.assign({ antialias: false, alpha: false, powerPreference: 'low-power' }, options || {});
  try {
    const r = new THREE.WebGLRenderer(opts);
    r.setSize(width, height);
    r.setPixelRatio(Math.min(window.devicePixelRatio, 1.25));
    r.outputEncoding = THREE.sRGBEncoding;
    r.physicallyCorrectLights = true;
    if (container) container.appendChild(r.domElement);
    return r;
  } catch (e) {
    try {
      const fallbackOpts = Object.assign({}, opts, { antialias: false, powerPreference: 'low-power' });
      const r = new THREE.WebGLRenderer(fallbackOpts);
      r.setSize(width, height);
      r.setPixelRatio(Math.min(window.devicePixelRatio, 1.25));
      r.outputEncoding = THREE.sRGBEncoding;
      r.physicallyCorrectLights = true;
      if (container) container.appendChild(r.domElement);
      return r;
    } catch (e2) {
      return null;
    }
  }
}

/* ================= STEP 1: MOTOR SELECTION ================= */
const motorContainer = document.getElementById("motor-viewer");
const motorScene = new THREE.Scene();
motorScene.background = new THREE.Color(0x1a1a1a);
const motorCamera = new THREE.PerspectiveCamera(45,motorContainer.clientWidth/motorContainer.clientHeight,0.1,1000);
motorCamera.position.set(0,-1,10);
let motorModel = null;
let motorRenderer = null;
try {
  motorRenderer = new THREE.WebGLRenderer({antialias:true});
} catch (e) {
  try {
    motorRenderer = new THREE.WebGLRenderer({antialias:false});
  } catch (e2) {
    motorRenderer = null;
  }
}
if (motorRenderer) {
  motorRenderer.setSize(motorContainer.clientWidth,motorContainer.clientHeight);
  motorRenderer.setPixelRatio(Math.min(window.devicePixelRatio, 1.25));
  motorRenderer.outputEncoding = THREE.sRGBEncoding;
  motorRenderer.physicallyCorrectLights = true;
  motorContainer.appendChild(motorRenderer.domElement);
}
if (!motorRenderer) {
  console.error('Motor renderer init failed: WebGL context could not be created.');
}
motorScene.add(new THREE.AmbientLight(0xffffff,1.1));
const motorLight1 = new THREE.DirectionalLight(0xffffff,2.2); motorLight1.position.set(8,10,8); motorScene.add(motorLight1);
const motorLight2 = new THREE.DirectionalLight(0xffffff,1.5); motorLight2.position.set(-8,5,5); motorScene.add(motorLight2);
let motorControls = null;
if (motorRenderer) {
  motorControls = new THREE.OrbitControls(motorCamera,motorRenderer.domElement);
  motorControls.enableDamping = true;
  motorControls.dampingFactor = 0.05;
  motorControls.autoRotate = false;
  motorControls.minDistance = 5;
  motorControls.maxDistance = 20;
  motorControls.zoomSpeed = 1.2;
} else {
  const debugInfo = document.getElementById('debug-info');
  if (debugInfo) debugInfo.textContent = 'WebGL context unavailable for motor viewer.';
}

function loadMotor(modelName){
  if (!motorRenderer || !motorControls) return;
  if(motorModel) motorScene.remove(motorModel);
  updateDebugInfo('Loading model...');

  loadModelScene(MODEL_PATH)
    .then((sourceScene) => {
      sourceScene.updateMatrixWorld(true);
      let meshCount = 0;
      motorMeshes = {};
      const motorMaterialNames = new Set();

      const motorRootSource = getNodeByName(sourceScene, MOTOR_NODE_NAME);
      if (!motorRootSource) {
        throw new Error(`Node "${MOTOR_NODE_NAME}" not found in model.`);
      }

      // Render only the MOTOR node as requested, preserving world transform.
      motorModel = cloneNodeWithWorldTransform(motorRootSource);
      motorModel.updateMatrixWorld(true);
      const motorRoot = motorModel;
      motorRoot.traverse(obj=>{
        if(obj.isMesh) {
          console.log('Motor mesh found:', obj.name);
          motorMeshes[obj.name] = obj;
          const mats = Array.isArray(obj.material) ? obj.material : [obj.material];
          mats.forEach((mat) => motorMaterialNames.add(String((mat && mat.name) || '(unnamed material)')));
          applyRenderableMaterial(obj);
          meshCount++;
        }
      });

      motorScene.add(motorModel);
      fitCameraToObject(motorCamera, motorControls, motorRoot);
      console.log('Motor material names:', [...motorMaterialNames]);
      console.log('Motor model loaded and centered. Rendering only MOTOR node.');
      updateDebugInfo(`Model loaded successfully! Found ${meshCount} motor mesh(es) under "${MOTOR_NODE_NAME}".`);
    })
    .catch(error => {
      console.error('Fetch error:', error);
      updateDebugInfo('Model load error: ' + getFriendlyModelLoadMessage(error));
    });
}

function selectMotor(type){
  customization.motor = type;
  loadMotor(type);
  // Move to seat customization after small delay
  setTimeout(()=>{
    setActiveStep('seat');
    initSeatCustomization();
  },1000);
}

function animateMotor(){
  requestAnimationFrame(animateMotor);
  if(!motorRenderer || !motorControls) return;
  if(activeView !== 'motor') return;
  try {
    motorControls.update();
    motorRenderer.render(motorScene,motorCamera);
  } catch (e) {
    console.error('Motor render loop error:', e);
  }
}
animateMotor();
window.addEventListener("resize",()=>{ if(!motorRenderer) return; motorCamera.aspect=motorContainer.clientWidth/motorContainer.clientHeight; motorCamera.updateProjectionMatrix(); motorRenderer.setSize(motorContainer.clientWidth,motorContainer.clientHeight); });

/* ================= STEP 2: SEAT CUSTOMIZATION ================= */
let seatContainer = null;
let seatScene = null;
let seatCamera = null;
let seatRenderer = null;
let seatControls = null;
var seatModel = null;
let seatBaseMaterials = new Map();
let seatMeshPartByUuid = new Map();
var seatPartStyles = {};
let seatStitchMeshPartByUuid = new Map();
var seatStitchStyles = {};
var selectedSeatPart = 'EXT';
let seatStyleHistory = [];
let seatDiagnosticsLogged = false;
let seatAnimationId = null;
let previewRendererRef = null;
let previewAnimationId = null;
let previewResizeHandler = null;
let previewCameraRef = null;
let previewControlsRef = null;
let previewSceneRef = null;
let previewContainerRef = null;
const textureLoader = new THREE.TextureLoader();
const textureCache = {};

function updateSeatPartButtons() {
  const statusEl = document.getElementById('seat-part-status');
  const buttons = document.querySelectorAll('[data-seat-part]');
  buttons.forEach((btn) => {
    const part = btn.getAttribute('data-seat-part');
    const hasPart = [...seatMeshPartByUuid.values()].includes(part);
    btn.classList.toggle('active', part === selectedSeatPart);
    btn.style.opacity = hasPart ? '1' : '0.7';
    btn.title = hasPart ? `Customize ${part}` : `${part} may use alias matching`;
  });
  if (statusEl) {
    statusEl.textContent = `Selected part: ${getSeatPartLabel(selectedSeatPart)}`;
  }
  updateSeatTextureButtons();
  updateStitchButtons();
  updateSeatSummaryBar();
}

function updateSeatTextureButtons() {
  const selectedStyle = seatPartStyles[selectedSeatPart];
  const selectedTextureKey = (selectedStyle && selectedStyle.type === 'texture') ? selectedStyle.value : null;
  const buttons = document.querySelectorAll('[data-seat-texture]');
  buttons.forEach((btn) => {
    const textureKey = btn.getAttribute('data-seat-texture');
    btn.classList.toggle('active', textureKey === selectedTextureKey);
  });
}

function updateStitchButtons() {
  const selectedStitch = seatStitchStyles[selectedSeatPart] || null;
  const buttons = document.querySelectorAll('[data-stitch-color]');
  buttons.forEach((btn) => {
    const stitchColor = btn.getAttribute('data-stitch-color');
    btn.classList.toggle('active', stitchColor === selectedStitch);
  });
}

function initializeSeatTextureWheelScroll() {
  const textureScroll = document.getElementById('seat-texture-scroll');
  if (!textureScroll || textureScroll.dataset.wheelBound === '1') return;

  textureScroll.addEventListener('mouseenter', () => {
    textureScroll.classList.add('is-hovered');
  });
  textureScroll.addEventListener('mouseleave', () => {
    textureScroll.classList.remove('is-hovered');
  });

  textureScroll.addEventListener('wheel', (event) => {
    const hasHorizontalOverflow = textureScroll.scrollWidth > textureScroll.clientWidth;
    if (!hasHorizontalOverflow) return;

    const dominantDelta = Math.abs(event.deltaY) > Math.abs(event.deltaX) ? event.deltaY : event.deltaX;
    if (dominantDelta === 0) return;

    event.preventDefault();
    textureScroll.scrollLeft += dominantDelta;
  }, { passive: false });

  textureScroll.dataset.wheelBound = '1';
}

function initializeSeatTextureVisibility() {
  const textureButtons = document.querySelectorAll('.seat-texture-item');
  const toggleBtn = document.getElementById('toggle-textures-btn');
  if (!toggleBtn) return;
  if (textureButtons.length <= 8) {
    toggleBtn.style.display = 'none';
    return;
  }
  toggleBtn.style.display = 'inline-block';
  toggleBtn.textContent = 'Show More Textures';
}

function toggleMoreTextures() {
  const textureButtons = [...document.querySelectorAll('.seat-texture-item')];
  const hidden = textureButtons.filter((btn) => btn.classList.contains('texture-hidden'));
  const toggleBtn = document.getElementById('toggle-textures-btn');
  if (!toggleBtn || textureButtons.length <= 8) return;

  const isCollapsed = hidden.length > 0;
  textureButtons.forEach((btn, index) => {
    if (index < 8) {
      btn.classList.remove('texture-hidden');
      return;
    }
    btn.classList.toggle('texture-hidden', !isCollapsed);
  });
  toggleBtn.textContent = isCollapsed ? 'Show Less Textures' : 'Show More Textures';
}

function cloneSeatState() {
  return {
    partStyles: JSON.parse(JSON.stringify(seatPartStyles || {})),
    stitchStyles: JSON.parse(JSON.stringify(seatStitchStyles || {}))
  };
}

function pushSeatHistory() {
  seatStyleHistory.push(cloneSeatState());
  if (seatStyleHistory.length > 30) {
    seatStyleHistory.shift();
  }
}

function updateSeatSummaryBar() {
  const summaryEl = document.getElementById('seat-summary-text');
  const undoBtn = document.getElementById('seat-undo-btn');
  if (!summaryEl) return;

  const entries = Object.entries(seatPartStyles)
    .filter(([, style]) => style && style.value)
    .map(([part, style]) => `${getSeatPartLabel(part)}: ${String(style.value).replace(/[_-]/g, ' ')}`);
  const stitchEntries = Object.entries(seatStitchStyles)
    .filter(([, color]) => !!color)
    .map(([part, color]) => `${getSeatPartLabel(part)} stitch: ${String(color).toUpperCase()}`);
  const mergedEntries = [...entries, ...stitchEntries];

  summaryEl.textContent = mergedEntries.length > 0
    ? `Seat style summary: ${mergedEntries.join(' | ')}`
    : 'Seat style summary: none';

  if (undoBtn) {
    undoBtn.disabled = seatStyleHistory.length === 0;
  }
}

function undoSeatStyle() {
  if (!seatModel || seatStyleHistory.length === 0) return;
  const prev = seatStyleHistory.pop() || {};
  seatPartStyles = prev.partStyles || {};
  seatStitchStyles = prev.stitchStyles || {};
  applySeatStylesToSeatModel()
    .then(() => {
      updateSeatPartButtons();
      updateStitchButtons();
      updateSeatSummaryBar();
    })
    .catch((error) => {
      console.error('Failed to undo seat style:', error);
    });
}

function resetSeatStyles() {
  if (!seatModel) return;
  pushSeatHistory();
  seatPartStyles = {};
  seatStitchStyles = {};
  customization.seatColor = null;
  applySeatStylesToSeatModel()
    .then(() => {
      updateSeatPartButtons();
      updateStitchButtons();
      updateSeatSummaryBar();
    })
    .catch((error) => {
      console.error('Failed to reset seat styles:', error);
    });
}

function loadTextureFromPaths(paths) {
  return new Promise((resolve, reject) => {
    let index = 0;
    const tryNext = () => {
      if (index >= paths.length) {
        reject(new Error('Texture file not found in expected paths.'));
        return;
      }
      const path = paths[index++];
      textureLoader.load(
        path,
        (texture) => {
          texture.flipY = false;
          texture.encoding = THREE.sRGBEncoding;
          texture.wrapS = THREE.RepeatWrapping;
          texture.wrapT = THREE.RepeatWrapping;
          texture.repeat.set(1, 1);
          resolve(texture);
        },
        undefined,
        () => tryNext()
      );
    };
    tryNext();
  });
}

async function getTextureByKey(textureKey) {
  if (textureCache[textureKey]) return textureCache[textureKey];
  const candidates = SEAT_TEXTURE_CANDIDATES[textureKey] || [];
  if (candidates.length === 0) {
    throw new Error(`No texture candidates configured for "${textureKey}".`);
  }
  const texture = await loadTextureFromPaths(candidates);
  textureCache[textureKey] = texture;
  return texture;
}

async function buildSeatTextureMapFromStyles() {
  const textureMap = {};
  const entries = Object.entries(seatPartStyles).filter(([, style]) => style && style.type === 'texture');
  await Promise.all(entries.map(async ([partName, style]) => {
    try {
      textureMap[partName] = await getTextureByKey(style.value);
    } catch (error) {
      console.warn(`Texture load failed for ${partName}:`, error.message);
    }
  }));
  return textureMap;
}

async function createCombinedPreviewModelFromGLB() {
  try {
    const sourceScene = await loadModelScene(MODEL_PATH);
    sourceScene.updateMatrixWorld(true);

    const motorRootSource = getNodeByName(sourceScene, MOTOR_NODE_NAME);
    const seatRootSource = getNodeByName(sourceScene, SEAT_NODE_NAME);
    if (!motorRootSource) throw new Error(`Node "${MOTOR_NODE_NAME}" not found in model.`);
    if (!seatRootSource) throw new Error(`Node "${SEAT_NODE_NAME}" not found in model.`);

    const motorPreview = cloneNodeWithWorldTransform(motorRootSource);
    const seatPreview = cloneNodeWithWorldTransform(seatRootSource);
    const textureByPart = await buildSeatTextureMapFromStyles();
    const stockSeatMesh = getNodeByName(motorPreview, 'seatwithmotor');
    if (stockSeatMesh) stockSeatMesh.visible = false;

    motorPreview.traverse((obj) => {
      if (!obj.isMesh) return;
      applyRenderableMaterial(obj);
      if (String(obj.name || '').trim().toLowerCase() === 'seatwithmotor') {
        obj.visible = false;
        return;
      }
      const newMat = obj.material.clone();
      if (customization.bodyColor === 'red') {
        newMat.color.setHex(0xff0000);
      } else if (customization.bodyColor === 'black') {
        newMat.color.setHex(0x1a1a1a);
      } else if (customization.bodyColor === 'blue') {
        newMat.color.setHex(0x0066ff);
      }
      newMat.needsUpdate = true;
      obj.material = newMat;
      obj.castShadow = true;
      obj.receiveShadow = true;
    });

    seatPreview.traverse((obj) => {
      if (!obj.isMesh) return;
      applyRenderableMaterial(obj);

      const newMat = obj.material.clone();
      const partName = resolveSeatPartForMesh(obj, seatPreview);
      const style = partName ? seatPartStyles[partName] : null;
      if (isStitchMeshObject(obj)) {
        const stitchColor = partName ? seatStitchStyles[partName] : null;
        applyStitchMaterialOverrides(newMat, stitchColor || 'white');
      } else {
        if (style && style.type === 'color') {
          const hex = getColorHex(style.value);
          if (hex !== null) newMat.color.setHex(hex);
          newMat.map = null;
        } else if (style && style.type === 'texture') {
          const texture = textureByPart[partName];
          if (texture) {
            newMat.map = texture;
            newMat.color.setHex(0xffffff);
          } else {
            newMat.map = null;
          }
        }
      }

      newMat.needsUpdate = true;
      obj.material = newMat;
      obj.castShadow = true;
      obj.receiveShadow = true;
    });

    alignSeatPreviewToStockSeat(motorPreview, seatPreview);

    const combinedModel = new THREE.Group();
    combinedModel.add(motorPreview);
    combinedModel.add(seatPreview);
    return combinedModel;
  } catch (error) {
    throw error;
  }
}

async function applySeatStylesToSeatModel() {
  if (!seatModel) return;
  const textureByPart = await buildSeatTextureMapFromStyles();

  seatModel.traverse((obj) => {
    if (!obj.isMesh) return;
    const base = seatBaseMaterials.get(obj.uuid);
    if (!base) return;

    const partName = seatMeshPartByUuid.get(obj.uuid);
    const newMat = base.clone();
    const style = partName ? seatPartStyles[partName] : null;
    const stitchPartName = seatStitchMeshPartByUuid.get(obj.uuid) || partName;

    if (seatStitchMeshPartByUuid.has(obj.uuid) || isStitchMeshObject(obj)) {
      const stitchColor = stitchPartName ? seatStitchStyles[stitchPartName] : null;
      applyStitchMaterialOverrides(newMat, stitchColor || 'white');
    } else if (style && style.type === 'color') {
      const hex = getColorHex(style.value);
      if (hex !== null) newMat.color.setHex(hex);
      newMat.map = null;
    } else if (style && style.type === 'texture') {
      const texture = textureByPart[partName];
      if (texture) {
        newMat.map = texture;
        newMat.color.setHex(0xffffff);
      } else {
        newMat.map = null;
      }
    }

    newMat.needsUpdate = true;
    obj.material = newMat;
  });
}

function selectSeatPart(partName) {
  selectedSeatPart = resolveSeatPartName(partName) || selectedSeatPart;
  updateSeatPartButtons();
  updateStitchButtons();
  console.log('Selected seat part:', selectedSeatPart);
}

function changeStitch(color) {
  if (!seatModel || seatBaseMaterials.size === 0) return;
  if (!selectedSeatPart) {
    alert('No seat part selected.');
    return;
  }
  const applyAll = !!document.getElementById('stitch-apply-all-checkbox')?.checked;
  const targetParts = applyAll ? getAvailableSeatParts() : [selectedSeatPart];

  pushSeatHistory();
  targetParts.forEach((part) => {
    seatStitchStyles[part] = color;
  });

  applySeatStylesToSeatModel()
    .then(() => {
      updateStitchButtons();
      updateSeatSummaryBar();
      console.log(`✓ Stitch color changed: ${applyAll ? 'ALL PARTS' : selectedSeatPart} -> ${color}`);
    })
    .catch((error) => {
      console.error('Failed to apply stitch color:', error);
    });
}

function initSeatViewer(){
  // Initialize seat viewer only once or when needed
  if(seatRenderer) return; // Already initialized
  
  seatContainer = document.getElementById("seat-viewer");
  
  // Ensure container has dimensions
  const width = seatContainer.clientWidth || window.innerWidth;
  const height = seatContainer.clientHeight || 420;
  
  seatScene = new THREE.Scene();
  seatScene.background = new THREE.Color(0x1a1a1a);
  
  seatCamera = new THREE.PerspectiveCamera(45, width / height, 0.1, 1000);
  seatCamera.position.set(0, -1, 10);
  
  try {
    seatRenderer = new THREE.WebGLRenderer({antialias:true});
  } catch (e) {
    try {
      seatRenderer = new THREE.WebGLRenderer({antialias:false});
    } catch (e2) {
      seatRenderer = null;
    }
  }
  if(!seatRenderer) {
    console.error('Seat renderer init failed: WebGL context could not be created.');
    seatContainer.innerHTML = '<p style="padding:16px;color:#ffcc00;text-align:center;">WebGL unavailable for seat viewer.</p>';
    return;
  }
  seatRenderer.setSize(width, height);
  seatRenderer.setPixelRatio(Math.min(window.devicePixelRatio, 1.25));
  seatRenderer.outputEncoding = THREE.sRGBEncoding;
  seatRenderer.physicallyCorrectLights = true;
  
  // Clear container and add renderer
  seatContainer.innerHTML = '';
  seatContainer.appendChild(seatRenderer.domElement);
  
  // Add lights
  seatScene.add(new THREE.AmbientLight(0xffffff, 1.1));
  const seatKeyLight = new THREE.DirectionalLight(0xffffff, 2.2);
  seatKeyLight.position.set(8, 10, 8);
  seatScene.add(seatKeyLight);
  const seatFillLight = new THREE.DirectionalLight(0xffffff, 1.5);
  seatFillLight.position.set(-8, 5, 5);
  seatScene.add(seatFillLight);
  
  // Setup controls
  seatControls = new THREE.OrbitControls(seatCamera, seatRenderer.domElement);
  seatControls.enableDamping = true;
  seatControls.dampingFactor = 0.05;
  seatControls.autoRotate = false;
  seatControls.minDistance = 3;
  seatControls.maxDistance = 25;
  seatControls.zoomSpeed = 1.2;
  
  console.log('✓ Seat viewer initialized with dimensions:', width, 'x', height);
}

function initSeatCustomization(){
  // Initialize viewer if not already done
  if(!seatRenderer) {
    initSeatViewer();
  }
  if(!seatRenderer || !seatControls || !seatScene || !seatCamera) return;
  
  loadModelScene(MODEL_PATH).then((fullModel)=>{
    const seatRoot = getNodeByName(fullModel, SEAT_NODE_NAME);
    if(seatRoot){
      // Preserve world transform so preview alignment matches MOTOR coordinates.
      const clonedSeat = cloneNodeWithWorldTransform(seatRoot);
      seatModel = clonedSeat;
      seatBaseMaterials = new Map();
      seatMeshPartByUuid = new Map();
      seatStitchMeshPartByUuid = new Map();
      seatPartStyles = {};
      seatStitchStyles = {};
      seatStyleHistory = [];

      clonedSeat.traverse((obj) => {
        if(!obj.isMesh) return;
        const material = obj.material.clone();
        material.side = THREE.DoubleSide;
        obj.material = material;
        seatBaseMaterials.set(obj.uuid, material.clone());

        const matchedPart = resolveSeatPartForMesh(obj, clonedSeat);
        if (matchedPart) {
          seatMeshPartByUuid.set(obj.uuid, matchedPart);
          if (isStitchMeshObject(obj)) {
            seatStitchMeshPartByUuid.set(obj.uuid, matchedPart);
          }
        }
      });

      if (!seatDiagnosticsLogged) {
        logSeatNodeDiagnostics(clonedSeat, 'seat-viewer');
        seatDiagnosticsLogged = true;
      }

      // Clear scene and add only the seat
      seatScene.clear();
      seatScene.add(new THREE.AmbientLight(0xffffff, 1.1));
      const seatKeyLight = new THREE.DirectionalLight(0xffffff, 2.2);
      seatKeyLight.position.set(8, 10, 8);
      seatScene.add(seatKeyLight);
      const seatFillLight = new THREE.DirectionalLight(0xffffff, 1.5);
      seatFillLight.position.set(-8, 5, 5);
      seatScene.add(seatFillLight);
      
      seatScene.add(clonedSeat);
      console.log('✓ seatclick group loaded. Mesh count:', seatBaseMaterials.size);
      const firstAvailablePart = SEAT_PART_NAMES.find((part) =>
        [...seatMeshPartByUuid.values()].includes(part)
      );
      selectedSeatPart = firstAvailablePart || 'EXT';
      updateSeatPartButtons();
      updateStitchButtons();
      updateSeatSummaryBar();
      
      // Auto-frame the seat so it appears at a proper, closer distance.
      fitCameraToObject(seatCamera, seatControls, clonedSeat, 1.1);
      console.log('✓ Seat scene ready for customization using seatclick');
      
      // Start animation loop if not already running
      if(!seatAnimationId) {
        animateSeat();
      }
    } else {
      console.error(`❌ Seat group "${SEAT_NODE_NAME}" not found in model.`);
      alert(`Seat group "${SEAT_NODE_NAME}" not found in hondabeat.glb.`);
    }
  }).catch((error)=>{
    console.error('❌ Error loading seat model:', error);
    alert(getFriendlyModelLoadMessage(error));
  });
}

async function applySeatTexture(textureKey){
  if(!seatModel || seatBaseMaterials.size === 0) return;
  if(!selectedSeatPart) {
    alert('No seat part selected.');
    return;
  }

  const partExists = [...seatMeshPartByUuid.values()].includes(selectedSeatPart);
  if(!partExists) {
    alert(`Seat part "${selectedSeatPart}" not found in seatclick.`);
    return;
  }

  try {
    await getTextureByKey(textureKey);
    pushSeatHistory();
    seatPartStyles[selectedSeatPart] = { type: 'texture', value: textureKey };
    customization.seatColor = customization.seatColor || 'custom';
    updateSeatTextureButtons();
    await applySeatStylesToSeatModel();
    updateSeatSummaryBar();
    console.log(`✓ Seat texture applied: ${selectedSeatPart} -> ${textureKey}`);
  } catch (error) {
    console.error('❌ Texture load error:', error);
    alert(`Texture "${textureKey}" not found. Add the file in root texture folder.`);
  }
}

function animateSeat(){
  seatAnimationId = requestAnimationFrame(animateSeat);
  if(activeView !== 'seat') return;
  if(seatControls) seatControls.update();
  if(seatRenderer && seatScene && seatCamera) {
    try {
      seatRenderer.render(seatScene, seatCamera);
    } catch (e) {
      console.error('Seat render loop error:', e);
    }
  }
}

window.addEventListener("resize", ()=>{
  if(!seatRenderer) return;
  const width = seatContainer.clientWidth;
  const height = seatContainer.clientHeight;
  seatCamera.aspect = width / height;
  seatCamera.updateProjectionMatrix();
  seatRenderer.setSize(width, height);
});

function goToPreview(){
  // Validate that colors are selected
  if(!customization.bodyColor) {
    alert("Please select a body color first!");
    return;
  }
  if(Object.keys(seatPartStyles).length === 0 && Object.keys(seatStitchStyles).length === 0) {
    alert("Please select at least one seat style (texture/color/stitch) first!");
    return;
  }
  
  // Hide seat customization and show preview
  setActiveStep('preview');
  
  // Wait for layout to settle so preview container gets real dimensions.
  requestAnimationFrame(() => requestAnimationFrame(() => {
    showPreview();
  }));
}

function backToSeat(){
  setActiveStep('seat');
}

/* ================= STEP 3: PREVIEW + AI ================= */
function showPreview(){
  const previewContainer=document.getElementById("preview-viewer");
  activeView = 'preview';
  if (previewAnimationId) {
    cancelAnimationFrame(previewAnimationId);
    previewAnimationId = null;
  }

  const fallbackWidth = (motorContainer && motorContainer.clientWidth) ? motorContainer.clientWidth : window.innerWidth;
  const width = Math.max(previewContainer.clientWidth || 0, Math.min(fallbackWidth, window.innerWidth), 280);
  const height = Math.max(previewContainer.clientHeight || 0, window.matchMedia('(max-width: 768px)').matches ? 340 : 560);
  previewContainer.style.minHeight = `${height}px`;

  const previewScene=new THREE.Scene();
  previewScene.background=new THREE.Color(0x1a1a1a);
  
  const previewCamera=new THREE.PerspectiveCamera(45, width/height, 0.1, 1000);
  previewCamera.position.set(0, -0.5, 12);

  if (!previewRendererRef) {
    try {
      previewRendererRef = new THREE.WebGLRenderer({antialias:true, alpha: true});
    } catch (e) {
      try {
        previewRendererRef = new THREE.WebGLRenderer({antialias:false, alpha: true});
      } catch (e2) {
        previewRendererRef = null;
      }
    }
    if(!previewRendererRef) {
      console.error('Preview renderer init failed: WebGL context could not be created.');
      previewContainer.innerHTML = '<p style="padding:16px;color:#ffcc00;text-align:center;">WebGL unavailable for preview.</p>';
      return;
    }
    previewRendererRef.setSize(width, height);
    previewRendererRef.outputEncoding=THREE.sRGBEncoding;
    previewRendererRef.physicallyCorrectLights=true;
    previewRendererRef.shadowMap.enabled = false;
    previewRendererRef.setPixelRatio(Math.min(window.devicePixelRatio, 1.25));
  }
  previewRendererRef.setSize(width, height);
  previewContainer.innerHTML = '';
  previewContainer.appendChild(previewRendererRef.domElement);
  const previewRenderer = previewRendererRef;
  previewSceneRef = previewScene;
  previewCameraRef = previewCamera;
  previewContainerRef = previewContainer;
  previewRenderer.setClearColor(0x1a1a1a, 1);
  
  // Enhanced lighting for better visibility of both body and seat
  // Ambient light
  const ambientLight = new THREE.AmbientLight(0xffffff, 1.2);
  previewScene.add(ambientLight);
  
  // Key light (main light from front-top)
  const keyLight = new THREE.DirectionalLight(0xffffff, 2.5);
  keyLight.position.set(10, 12, 10);
  keyLight.castShadow = true;
  keyLight.shadow.mapSize.width = 2048;
  keyLight.shadow.mapSize.height = 2048;
  previewScene.add(keyLight);
  
  // Fill light (from side-bottom to illuminate seat)
  const fillLight = new THREE.DirectionalLight(0xffffff, 1.8);
  fillLight.position.set(-8, 6, 8);
  fillLight.castShadow = true;
  previewScene.add(fillLight);
  
  // Back light (highlights the rear)
  const backLight = new THREE.DirectionalLight(0xff9999, 1.0);
  backLight.position.set(0, 5, -10);
  previewScene.add(backLight);
  
  // OrbitControls for interaction
  const controls=new THREE.OrbitControls(previewCamera, previewRenderer.domElement);
  controls.enableDamping = true;
  controls.dampingFactor = 0.06;
  controls.enableRotate = true;
  controls.enableZoom = true;
  controls.enablePan = true;
  controls.minDistance = 5;
  controls.maxDistance = 25;
  controls.zoomSpeed = 1.5;
  controls.autoRotate = false;
  controls.target.set(0, -0.5, 0);
  previewControlsRef = controls;

  createCombinedPreviewModelFromGLB().then((combinedModel) => {
    if (activeView !== 'preview') return;

    previewScene.add(combinedModel);
    const combinedBounds = new THREE.Box3().setFromObject(combinedModel);
    if (!combinedBounds.isEmpty()) {
      const combinedSize = combinedBounds.getSize(new THREE.Vector3());
      const maxDim = Math.max(combinedSize.x, combinedSize.y, combinedSize.z);
      if (!Number.isFinite(maxDim) || maxDim < 0.01) {
        centerAndScaleObject(combinedModel, 8);
      }
    }

    fitCameraToObject(previewCamera, controls, combinedModel, 1.05);
    controls.update();
    try {
      previewRenderer.render(previewScene, previewCamera);
    } catch (e) {
      console.error('Preview initial render error:', e);
    }
    console.log(`✓ Preview combined from current MOTOR + customized seatclick. (${width}x${height})`);

    // Animation loop for smooth rendering
    function animatePreview(){ 
      previewAnimationId = requestAnimationFrame(animatePreview); 
      if(activeView !== 'preview') return;
      controls.update(); 
      try {
        previewRenderer.render(previewScene, previewCamera);
      } catch (e) {
        console.error('Preview render loop error:', e);
      }
    }
    animatePreview();
  }).catch((error) => {
    console.error('Preview combine error:', error);
    alert('Preview failed to build combined model. Check MODEL nodes MOTOR and seatclick.');
  });

  // Handle window resize
  if (!previewResizeHandler) {
    previewResizeHandler = () => { 
      if (!previewContainerRef || !previewCameraRef || !previewRendererRef) return;
      const width = previewContainerRef.clientWidth;
      const height = previewContainerRef.clientHeight;
      previewCameraRef.aspect = width / height; 
      previewCameraRef.updateProjectionMatrix(); 
      previewRendererRef.setSize(width, height); 
    };
    window.addEventListener("resize", previewResizeHandler);
  }
}

function finalConfirm(){
  const seatStyleSummary = Object.entries(seatPartStyles).map(([part, style]) => {
    const value = (style && style.value) ? String(style.value).toUpperCase() : 'N/A';
    return `${part}: ${value}`;
  }).join(', ') || 'NONE';
  const stitchSummary = Object.entries(seatStitchStyles).map(([part, color]) => {
    return `${part}: ${String(color || 'N/A').toUpperCase()}`;
  }).join(', ') || 'NONE';
  const message = `✓ ORDER CONFIRMED!\n\nMotor Type: ${customization.motor || 'PCX'}\nBody Color: ${(customization.bodyColor || 'N/A').toUpperCase()}\nSeat Styles: ${seatStyleSummary}\nStitch Colors: ${stitchSummary}\n\nYour custom motorcycle is being prepared!`;
  alert(message);
  // Optionally redirect to order confirmation page
  // window.location.href = 'order_confirmation.php?motor=' + customization.motor + '&bodyColor=' + customization.bodyColor + '&seatColor=' + customization.seatColor;
}

function changeBody(color){
  customization.bodyColor = color;
  if(!motorModel) {
    console.log('Motor model not loaded yet.');
    return;
  }

  const motorRoot = getNodeByName(motorModel, MOTOR_NODE_NAME) || motorModel;
  let changed = 0;
  motorRoot.traverse((obj) => {
    if(!obj.isMesh) return;
    const newMat = obj.material.clone();
    if(color === 'red'){
      newMat.color.setHex(0xff0000);
    } else if(color === 'black'){
      newMat.color.setHex(0x000000);
    } else if(color === 'blue'){
      newMat.color.setHex(0x0000ff);
    }
    newMat.needsUpdate = true;
    obj.material = newMat;
    changed++;
  });
  console.log(`Body color changed to: ${color}. Updated ${changed} mesh(es) in "${MOTOR_NODE_NAME}".`);
}

function reloadModel(){
  document.getElementById('debug-info').textContent = 'Reloading model...';
  loadMotor('hondabeat');
}

function showSeat(){
  setActiveStep('seat');
  
  // Initialize viewer when showing
  setTimeout(()=>{
    initSeatViewer();
    initSeatCustomization();
  }, 50);
}

function backToMotor(){
  setActiveStep('motor');
}

// Initialize the motor viewer on page load
window.addEventListener('load', function() {
  loadMotor('hondabeat');
  initializeSeatTextureWheelScroll();
  initializeSeatTextureVisibility();
  updateSeatSummaryBar();

  if (requestedStep === 'seat') {
    showSeat();
  } else if (requestedStep === 'preview') {
    // Keep preview requests safe and deterministic by opening seat step first.
    showSeat();
  } else {
    setActiveStep('motor');
  }
});

</script>


<?php
if (isset($conn) && $conn instanceof mysqli) {
  $conn->close();
}
include __DIR__ . '/partials/user_footer.php';
?>

</body>
</html>



