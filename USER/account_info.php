<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
$errors = [];
$password_errors = [];
$avatar_errors = [];
$avatar_feature_available = false;

// Ensure account profile columns exist.
$profile_columns = [
    "first_name" => "VARCHAR(100) DEFAULT NULL",
    "middle_name" => "VARCHAR(100) DEFAULT NULL",
    "last_name" => "VARCHAR(100) DEFAULT NULL",
    "suffix_name" => "VARCHAR(20) DEFAULT NULL",
    "date_of_birth" => "DATE DEFAULT NULL",
    "gender" => "VARCHAR(20) DEFAULT NULL",
    "province" => "VARCHAR(100) DEFAULT NULL",
    "city" => "VARCHAR(150) DEFAULT NULL",
    "barangay" => "VARCHAR(150) DEFAULT NULL",
    "home_address" => "VARCHAR(255) DEFAULT NULL",
    "recovery_email" => "VARCHAR(255) DEFAULT NULL",
    "contact_number" => "VARCHAR(30) DEFAULT NULL",
    "profile_update_count" => "INT NOT NULL DEFAULT 0"
];
foreach ($profile_columns as $col_name => $col_sql) {
    $escaped_col = $conn->real_escape_string($col_name);
    $check_sql = "
        SELECT COUNT(*) AS total
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = '{$escaped_col}'
    ";
    $check_result = $conn->query($check_sql);
    $check_row = $check_result ? $check_result->fetch_assoc() : null;
    $exists = (int)($check_row['total'] ?? 0) > 0;
    if (!$exists) {
        $conn->query("ALTER TABLE users ADD COLUMN {$col_name} {$col_sql}");
    }
}

// Ensure profile_picture column exists for avatar upload.
try {
    $check_sql = "
        SELECT COUNT(*) AS total
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'profile_picture'
    ";
    $check_result = $conn->query($check_sql);
    $check_row = $check_result ? $check_result->fetch_assoc() : null;
    $avatar_feature_available = (int)($check_row['total'] ?? 0) > 0;

    if (!$avatar_feature_available) {
        $conn->query("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL");
        $recheck_result = $conn->query($check_sql);
        $recheck_row = $recheck_result ? $recheck_result->fetch_assoc() : null;
        $avatar_feature_available = (int)($recheck_row['total'] ?? 0) > 0;
    }
} catch (Throwable $e) {
    $avatar_feature_available = false;
}

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$calabarzon_map = [
    'Laguna' => [
        'Alaminos' => ['San Agustin', 'San Andres', 'San Benito', 'San Gregorio', 'San Juan', 'San Ildefonso', 'San Roque', 'Santa Rosa'],
        'Bay' => ['Bitin', 'Dila', 'Maitim', 'Paciano Rizal', 'Puypuy', 'San Antonio', 'San Isidro', 'Tranca'],
        'Binan' => ['Canlalay', 'Casile', 'De La Paz', 'Ganado', 'Langkiwa', 'Malaban', 'Mampalasan', 'Platero', 'Poblacion', 'Timbao'],
        'Cabuyao' => ['Baclaran', 'Banaybanay', 'Bigaa', 'Butong', 'Diezmo', 'Mamatid', 'Marinig', 'Niugan', 'Pulo', 'Sala'],
        'Calamba' => ['Bagong Kalsada', 'Banadero', 'Banlic', 'Barangay 1', 'Barangay 2', 'Barangay 3', 'Barangay 4', 'Barangay 5', 'Barangay 6', 'Barangay 7', 'Bubuyan', 'Bucal', 'Halang', 'Hornalan', 'Lamesa', 'Lecheria', 'Lingga', 'Majada In', 'Makiling', 'Mapagong', 'Masili', 'Maunong', 'Mayapa', 'Milagrosa', 'Paciano Rizal', 'Palingon', 'Parian', 'Punta', 'Real', 'Saimsim'],
        'Calauan' => ['Balayhangin', 'Bangyas', 'Dayap', 'Hanggan', 'Imok', 'Labuin', 'Lamot 1', 'Lamot 2', 'Mabacan', 'Pob.'],
        'Cavinti' => ['Anglas', 'Bangco', 'Bukal', 'Bulajo', 'Cansuso', 'Duhat', 'Inao-awan', 'Layasin', 'Paowin', 'Tuking'],
        'Famy' => ['Asana', 'Bacong-Sigsigan', 'Bagong Pag-asa', 'Balitoc', 'Banaba', 'Batuhan', 'Mayatba', 'Minayutan', 'Pob.'],
        'Kalayaan' => ['Longos', 'Lucban', 'San Antonio', 'San Juan', 'San Pablo Norte', 'San Pablo Sur', 'Santo Domingo'],
        'Liliw' => ['Bagong Anyo', 'Bayate', 'Bongkol', 'Bukal', 'Lagaslas', 'Masikap', 'Novaliches', 'San Isidro', 'Santa Cruz'],
        'Los Banos' => ['Anos', 'Bagong Silang', 'Bambang', 'Batong Malake', 'Baybayin', 'Lalakay', 'Maahas', 'Mayondon', 'Tuntungin-Putho'],
        'Luisiana' => ['De La Paz', 'Poblacion Zone 1', 'Poblacion Zone 2', 'San Antonio', 'San Buenaventura', 'San Diego', 'San Isidro'],
        'Lumban' => ['Bagong Silang', 'Balubad', 'Concepcion', 'Lewin', 'Maracta', 'Maytalang I', 'Primera Parang', 'Wawa'],
        'Mabitac' => ['Amuyong', 'Lambac', 'Libis ng Nayon', 'Matalatala', 'Paagahan', 'Sinagtala', 'San Antonio', 'Nanguma'],
        'Magdalena' => ['Alipit', 'Baanan', 'Balanac', 'Bucal', 'Buenavista', 'Bungkol', 'Cigaras', 'Halayhayin', 'Malaking Ambling', 'Pob.'],
        'Majayjay' => ['Amonoy', 'Bakia', 'Botocan', 'Buo', 'Coralao', 'Gagalot', 'Ibabang Banga', 'Panglan', 'San Miguel', 'Suba'],
        'Nagcarlan' => ['Abo', 'Alibungbungan', 'Balayong', 'Balinacon', 'Bambang', 'Kanluran Kabubuhayan', 'Labangan', 'Lalakay', 'Silangan Napapatid'],
        'Paete' => ['Bagumbayan', 'Bangkusay', 'Ermita', 'Ibaba del Norte', 'Ibaba del Sur', 'Ilaya del Norte', 'Ilaya del Sur', 'Maytoong'],
        'Pagsanjan' => ['Anibong', 'Biñan', 'Buboy', 'Calusiche', 'Dingin', 'Lambac', 'Layugan', 'Magdapio', 'Pinagsanjan', 'Sabang'],
        'Pakil' => ['Banilan', 'Burgos', 'Casa Real', 'Casinsin', 'Dorado', 'Gonzales', 'Matikiw', 'Rizal', 'Saray', 'Taft'],
        'Pangil' => ['Balian', 'Dambo', 'Galalan', 'Isla', 'Natividad', 'San Jose', 'Sulib', 'Mabato-Azufre', 'Pob.'],
        'Pila' => ['Aplaya', 'Bagong Pook', 'Bukal', 'Bulilan Norte', 'Bulilan Sur', 'Labuin', 'Linga', 'Masico', 'Mojon', 'Tubuan'],
        'Rizal (Laguna)' => ['Antipolo', 'East Poblacion', 'Entablado', 'Laguan', 'Pauli 1', 'Pauli 2', 'Pook', 'Tala', 'Talaga'],
        'San Pablo' => ['Bagong Pook', 'Bautista', 'Concepcion', 'Del Remedio', 'Dolores', 'San Antonio', 'San Buenaventura', 'San Cristobal', 'San Gabriel', 'San Jose'],
        'San Pedro' => ['Bagong Silang', 'Calendola', 'Chrysanthemum', 'Cuyab', 'Estrella', 'Fatima', 'Landayan', 'Laram', 'Maharlika', 'Narra', 'Nueva', 'Pacita 1', 'Pacita 2', 'Poblacion', 'Riverside', 'Rosario', 'Sampaguita Village', 'San Antonio', 'San Roque', 'San Vicente', 'United Bayanihan'],
        'Santa Cruz' => ['Alipit', 'Bubukal', 'Calios', 'Duhat', 'Gatid', 'Jasaan', 'Labuin', 'Malinao', 'Pagsawitan', 'Patimbao'],
        'Santa Maria' => ['Adia', 'Bagong Pook', 'Bubucal', 'Calangay', 'Coralan', 'Cueva', 'Inayapan', 'Jose Laurel', 'Mataling-Ting', 'Poblacion'],
        'Santa Rosa' => ['Aplaya', 'Balibago', 'Caingin', 'Dila', 'Dita', 'Don Jose', 'Ibaba', 'Kanluran', 'Labas', 'Macabling', 'Malitlit', 'Malusak', 'Market Area', 'Pooc', 'Pulong Santa Cruz', 'Santo Domingo', 'Sinalhan', 'Tagapo'],
        'Siniloan' => ['Acevida', 'Bagumbarangay', 'Burgos', 'G. Redor', 'Laguio', 'Liyang', 'Macatad', 'Magsaysay', 'Pob. Wawa'],
        'Victoria' => ['Banca-banca', 'Daniw', 'Masapang', 'Nanhaya', 'Pagalangan', 'San Benito', 'San Felix', 'San Francisco', 'San Roque', 'Santol']
    ],
    'Batangas' => [
        'Agoncillo' => ['Adia', 'Balangon', 'Banyaga', 'Bilibinwang', 'Coral na Munti', 'Poblacion', 'Pook', 'Subic Ilaya'],
        'Alitagtag' => ['Concepcion', 'Dalipit East', 'Dalipit West', 'Dominador East', 'Dominador West', 'Munlawin Norte', 'Ping-as', 'Poblacion East', 'Poblacion West'],
        'Balayan' => ['Baclaran', 'Barangay 1', 'Barangay 2', 'Barangay 3', 'Calan', 'Dalig', 'Gumamela', 'Patugo', 'Poblacion', 'Sampaga'],
        'Balete' => ['Alangilan', 'Calawit', 'Looc', 'Maalas-as', 'Magapi', 'Palsara', 'Poblacion', 'Sala', 'Sampalocan'],
        'Batangas City' => ['Alangilan', 'Balagtas', 'Balete', 'Banaba Center', 'Bolbok', 'Concepcion', 'Cuta', 'Dalig', 'Dela Paz', 'Kumintang Ibaba', 'Kumintang Ilaya', 'Libjo', 'Maapas', 'Mahabang Dahilig', 'Mahabang Parang', 'Paharang Silangan', 'Paharang Kanluran', 'Pallocan East', 'Pallocan West', 'Pinamucan Ibaba', 'Pinamucan Silangan', 'Santa Clara', 'Sorosoro Ilaya', 'Sorosoro Karsada', 'San Isidro', 'San Jose Sico', 'Wawa'],
        'Bauan' => ['Alagao', 'As-is', 'Baguilawa', 'Balayong', 'Barangay II', 'Manghinao', 'Poblacion', 'Sampaguita', 'Sinala'],
        'Calaca' => ['Baclas', 'Bagong Tubig', 'Balisong', 'Coral ni Bacal', 'Dacanlao', 'Loma', 'Munting Coral', 'Poblacion', 'Talisay'],
        'Calatagan' => ['Balibago', 'Biga', 'Carlosa', 'Carretunan', 'Lucsuhin', 'Pantalan', 'Poblacion 1', 'Poblacion 2', 'Talisay', 'Tatlong Pook'],
        'Cuenca' => ['Balagbag', 'Don Leon', 'Dita', 'Ibabao', 'Labac', 'Munting Bato', 'Poblacion', 'San Felipe', 'Santo Tomas'],
        'Ibaan' => ['Bago', 'Balanga', 'Bungahan', 'Calamias', 'Lapu-lapu', 'Mabalor', 'Palindan', 'Poblacion', 'Santo Nino'],
        'Laurel' => ['Asisan', 'Balakilong', 'Berinayan', 'Bugaan East', 'Bugaan West', 'Dayap Itaas', 'Gulod', 'Leviste', 'Poblacion'],
        'Lemery' => ['Anak-Dagat', 'Bagong Pook', 'Bukal', 'Cahilan 1', 'Cahilan 2', 'District 1', 'District 2', 'Nonong Casto', 'Payapa Ilaya', 'Sinala'],
        'Lian' => ['Bagong Pook', 'Binubusan', 'Bungahan', 'Luyahan', 'Malaruhatan', 'Matabungkay', 'Prenza', 'Poblacion', 'San Diego'],
        'Lipa' => ['Anilao', 'Antipolo del Norte', 'Antipolo del Sur', 'Bagong Pook', 'Balintawak', 'Banaybanay', 'Bolbok', 'Bulacnin', 'Bugtong na Pulo', 'Dagatan', 'Duhatan', 'Halang', 'Inosluban', 'Kayumanggi', 'Lodlod', 'Mabini', 'Malitlit', 'Marauoy', 'Marawoy', 'Mataas na Lupa', 'Munting Pulo', 'Pangao', 'Pinagkawitan', 'Poblacion', 'Sabang', 'San Benito', 'San Carlos', 'San Jose', 'Sico', 'Tambo'],
        'Lobo' => ['Apar', 'Biga', 'Bulihan', 'Calumpit', 'Fabrica', 'Jaybanga', 'Mabilog na Bundok', 'Malabrigo', 'Nagtaluntong', 'Poblacion'],
        'Mabini' => ['Anilao East', 'Anilao Proper', 'Bagalangit', 'Bulacan', 'Mainaga', 'Mainit', 'Poblacion', 'San Jose', 'Solo'],
        'Malvar' => ['Bagong Pook', 'Bilucao', 'Bulihan', 'Luta del Norte', 'Poblacion', 'San Andres', 'San Isidro', 'Santiago', 'Talisay'],
        'Mataasnakahoy' => ['Bayorbor', 'Bubuyan', 'Calingatan', 'Kinalaglagan', 'Loob', 'Lumang Lipa', 'Poblacion 1', 'Poblacion 2', 'Sinala'],
        'Nasugbu' => ['Aga', 'Banilad', 'Bilaran', 'Bucana', 'Bulihan', 'Kaylaway', 'Lumbangan', 'Mataas na Pulo', 'Poblacion', 'Wawa'],
        'Rosario' => ['Alupay', 'Bagong Pook', 'Bulihan', 'Leviste', 'Mabato', 'Nasi', 'Poblacion A', 'Poblacion B', 'San Carlos', 'Tiquiwan'],
        'San Jose' => ['Aguila', 'Anus', 'Aya', 'Balagtasin', 'Calansayan', 'Janao-janao', 'Poblacion', 'Santo Cristo', 'Taysan'],
        'San Juan' => ['Abung', 'Balagbag', 'Barualte', 'Bataan', 'Calitcalit', 'Lipahan', 'Muzon', 'Poblacion', 'Quipot', 'Ticalan'],
        'San Pascual' => ['Alalum', 'Balimbing', 'Banaba', 'Duhatan', 'Gelerang Kawayan', 'Malaking Pook', 'Pook ni Banal', 'Sambat', 'San Mariano'],
        'Santo Tomas' => ['Poblacion 1', 'Poblacion 2', 'San Agustin', 'San Antonio', 'San Bartolome', 'San Felix', 'San Fernando', 'San Jose', 'San Pedro'],
        'Taal' => ['Balisong', 'Bihis', 'Bolbok', 'Cawit', 'Iba', 'Pansol', 'Poblacion 1', 'Poblacion 2', 'Tumbaga', 'Zona'],
        'Talisay' => ['Aya', 'Balas', 'Buco', 'Leynes', 'Quisumbing', 'Sampaloc', 'San Guillermo', 'Tumaway', 'Zone 1', 'Zone 2'],
        'Tanauan' => ['Altura Bata', 'Ambulong', 'Bagbag', 'Darasa', 'Janopol', 'Mabini', 'Pagaspas', 'Poblacion', 'Sambat', 'Santor'],
        'Tuy' => ['Acle', 'Balagbag', 'Bolboc', 'Dalima', 'Guinhawa', 'Luna', 'Luntal', 'Mataywanac', 'Palincaro', 'Talon']
    ],
    'Cavite' => [
        'Alfonso' => ['Amuyong', 'Bilog', 'Buck Estate', 'Esperanza Ibaba', 'Esperanza Ilaya', 'Marahan I', 'Poblacion', 'Sinaliw na Malaki', 'Upli'],
        'Amadeo' => ['Banaybanay', 'Bucal', 'Dagatan', 'Halang', 'Loma', 'Minantok East', 'Pangil', 'Poblacion', 'Talon'],
        'Bacoor' => ['Alima', 'Aniban I', 'Aniban II', 'Aniban III', 'Aniban IV', 'Aniban V', 'Banalo', 'Bayanan', 'Daang Bukid', 'Digman', 'Kaingin', 'Mabolo I', 'Mabolo II', 'Mabolo III', 'Mambog I', 'Mambog II', 'Mambog III', 'Mambog IV', 'Mambog V', 'Molino I', 'Molino II', 'Molino III', 'Molino IV', 'Molino V', 'Molino VI', 'Molino VII', 'Niog I', 'Niog II', 'Niog III', 'Panapaan I', 'Panapaan II', 'Panapaan III', 'Panapaan IV', 'Panapaan V', 'Real I', 'Real II', 'Salinas I', 'Salinas II', 'Salinas III', 'Salinas IV', 'San Nicolas I', 'San Nicolas II', 'San Nicolas III', 'Talaba I', 'Talaba II', 'Talaba III', 'Talaba IV', 'Talaba V', 'Zapote I', 'Zapote II', 'Zapote III', 'Zapote IV', 'Zapote V'],
        'Carmona' => ['Bancal', 'Cabilang Baybay', 'Lantic', 'Mabuhay', 'Maduya', 'Poblacion', 'Milagrosa', 'Sampaloc'],
        'Cavite City' => ['Barangay 1', 'Barangay 2', 'Barangay 3', 'Barangay 4', 'Barangay 5', 'Barangay 6', 'Barangay 7', 'Barangay 8'],
        'Dasmarinas' => ['Burol', 'Burol I', 'Burol II', 'Burol III', 'Datu Esmael', 'Emmanuel Bergado I', 'Emmanuel Bergado II', 'Fatima I', 'Fatima II', 'H-2', 'Langkaan I', 'Langkaan II', 'Luzviminda I', 'Luzviminda II', 'Paliparan I', 'Paliparan II', 'Paliparan III', 'Sabang', 'Sampaloc I', 'Sampaloc II', 'Sampaloc III', 'Sampaloc IV', 'Sampaloc V', 'San Agustin I', 'San Agustin II', 'San Agustin III', 'Salawag', 'Salitran I', 'Salitran II', 'Salitran III', 'Salitran IV', 'Santo Cristo', 'Tala'],
        'General Emilio Aguinaldo' => ['A. Dalusag', 'Bailen', 'Kaymisas', 'Lukban', 'Narvaez', 'Poblacion I', 'Poblacion II', 'Tabora'],
        'General Mariano Alvarez' => ['Aldiano Olaes', 'Barangay 1', 'Barangay 2', 'Barangay 3', 'Barangay 4', 'Barangay 5', 'Barangay 6', 'Barangay 7'],
        'General Trias' => ['Bagumbayan', 'Biclatan', 'Buenavista I', 'Corregidor', 'Dulong Bayan', 'Governor Ferrer', 'Manggahan', 'Panungyanan', 'San Francisco', 'Santiago'],
        'Imus' => ['Alapan I-A', 'Alapan I-B', 'Alapan I-C', 'Alapan II-A', 'Alapan II-B', 'Anabu I-A', 'Anabu I-B', 'Anabu I-C', 'Anabu I-D', 'Anabu I-E', 'Anabu I-F', 'Anabu II-A', 'Anabu II-B', 'Anabu II-C', 'Anabu II-D', 'Anabu II-E', 'Bucandala I', 'Bucandala II', 'Bucandala III', 'Carsadang Bago I', 'Carsadang Bago II', 'Malagasang I-A', 'Malagasang I-B', 'Malagasang I-C', 'Malagasang I-D', 'Malagasang II-A', 'Malagasang II-B', 'Medicion I-A', 'Medicion I-B', 'Medicion II-A', 'Medicion II-B', 'Poblacion I-A', 'Poblacion I-B', 'Poblacion I-C', 'Poblacion II-A', 'Poblacion II-B', 'Poblacion III-A', 'Poblacion III-B', 'Poblacion IV-A', 'Poblacion IV-B', 'Tanzang Luma I', 'Tanzang Luma II', 'Tanzang Luma III', 'Toclong I-A', 'Toclong I-B', 'Toclong I-C', 'Toclong II-A', 'Toclong II-B', 'Wakas I-A', 'Wakas I-B', 'Wakas II-A', 'Wakas II-B'],
        'Indang' => ['Alulod', 'Bancod', 'Calumpang Cerca', 'Kayquit II', 'Limbon', 'Lumampong Balagbag', 'Mataas na Lupa', 'Poblacion 1', 'Pulo', 'Tuyon-tuyon'],
        'Kawit' => ['Batong Dalig', 'Binakayan-Aplaya', 'Gahak', 'Kaingen', 'Maguikay', 'Marulas', 'Panamitan', 'Poblacion', 'Tabon'],
        'Magallanes' => ['Baliwag', 'Barangay 1', 'Barangay 2', 'Bendita I', 'Bendita II', 'Medina', 'Pacheco', 'Poblacion'],
        'Maragondon' => ['Bucal I', 'Bucal II', 'Caingin', 'Layong Mabilog', 'Mabato', 'Poblacion 1', 'Pantihan 1', 'Tulay A', 'Tulay B'],
        'Mendez' => ['Anuling Cerca I', 'Anuling Lejos I', 'Asis I', 'Bukal', 'Galicia I', 'Mabuhay', 'Poblacion I', 'Upli'],
        'Naic' => ['Bagong Kalsada', 'Bucana Malaki', 'Bucana Sasahan', 'Calubcob', 'Ibayo Silangan', 'Kanluran', 'Labac', 'Muzon', 'Poblacion', 'Sabang'],
        'Noveleta' => ['Magdiwang', 'Poblacion', 'San Antonio I', 'San Antonio II', 'San Juan I', 'San Juan II', 'San Rafael I', 'Santa Rosa II'],
        'Rosario' => ['Bagbag I', 'Bagbag II', 'Kanluran', 'Ligtong I', 'Ligtong II', 'Muzon I', 'Poblacion', 'Sapa I', 'Tejeros Convention'],
        'Silang' => ['Adlas', 'Anahaw I', 'Balite I', 'Biga I', 'Buho', 'Hoyo', 'Lalaan I', 'Magtalisay', 'Poblacion', 'Tubuan I'],
        'Tagaytay' => ['Asisan', 'Bagong Tubig', 'Calabuso', 'Dapdap East', 'Francisco', 'Iruhin East', 'Maharlika East', 'Maitim 2nd East', 'San Jose', 'Silang Junction North'],
        'Tanza' => ['Amaya I', 'Bagtas', 'Bucal', 'Daang Amaya I', 'Julugan I', 'Paradahan I', 'Poblacion I-A', 'Sahud-Ulan', 'Santol', 'Tres Cruses'],
        'Ternate' => ['Bucana', 'Poblacion I', 'Poblacion II', 'San Jose', 'Sapang I', 'Sapang II'],
        'Trece Martires' => ['Aguado', 'Cabezas', 'Conchu', 'De Ocampo', 'Gregorio', 'Inocencio', 'Lapidario', 'Osorio', 'Perez', 'San Agustin']
    ],
    'Rizal' => [
        'Angono' => ['Bagumbayan', 'Kalayaan', 'Mahabang Parang', 'San Isidro', 'San Pedro', 'Santo Nino', 'Pob. Itaas', 'Pob. Ibaba'],
        'Antipolo' => ['Bagong Nayon', 'Beverly Hills', 'Calawis', 'Cupang', 'Dalig', 'Dela Paz', 'Inarawan', 'Mambugan', 'Mayamot', 'Muntindilaw', 'San Isidro', 'San Jose', 'San Juan', 'San Luis', 'San Roque', 'Santa Cruz'],
        'Baras' => ['Concepcion', 'Evangelista', 'Mabini', 'Pinugay', 'Rizal', 'San Jose', 'San Juan', 'Santiago', 'Pob.'],
        'Binangonan' => ['Batingan', 'Bilibiran', 'Binitagan', 'Gulod', 'Janosa', 'Kalinawan', 'Libid', 'Limbon-limbon', 'Pantok', 'Tatala'],
        'Cainta' => ['San Andres', 'San Isidro', 'San Juan', 'Santo Domingo', 'Santo Nino', 'San Roque', 'Santa Rosa', 'San Pedro', 'San Nicolas', 'Poblacion'],
        'Cardona' => ['Balibago', 'Boor', 'Calahan', 'Dalig', 'Looc', 'Malanggam-Calubacan', 'Navotas', 'Patunhay', 'San Roque'],
        'Jala-Jala' => ['Bagumbong', 'Bayugo', 'Lubo', 'Paalaman', 'Pagkalinawan', 'Second District', 'Sipsipin', 'Special District'],
        'Morong' => ['Bombongan', 'Caniogan', 'Lagundi', 'Maybancal', 'San Guillermo', 'San Jose', 'San Juan', 'San Pedro', 'San Roque'],
        'Pililla' => ['Bagumbayan', 'Halayhayin', 'Hulo', 'Imatong', 'Malaya', 'Niogan', 'Quisao', 'Takungan'],
        'Rodriguez' => ['Balite', 'Burgos', 'Geronimo', 'Manggahan', 'Mascap', 'Rosario', 'San Isidro', 'San Jose', 'San Rafael', 'Puray'],
        'San Mateo' => ['Ampid I', 'Banaba', 'Dulong Bayan II', 'Guitnang Bayan II', 'Malanday', 'Maly', 'Silangan', 'Santa Ana'],
        'Tanay' => ['Cayabu', 'Daraitan', 'Katipunan-Bayan', 'Laiban', 'Mamuyao', 'Plaza Aldea', 'Sampaloc', 'San Andres', 'Tandang Kutyo', 'Wawa'],
        'Taytay' => ['Dolores', 'Muzon', 'San Isidro', 'San Juan', 'Santa Ana', 'Tiklimg', 'Bagumbayan', 'San Roque', 'Sta. Cruz', 'Lupang Areno'],
        'Teresa' => ['Bagumbayan', 'Calumpang Santo Cristo', 'Dalig', 'Dulumbayan', 'May-Iba', 'Poblacion', 'San Gabriel', 'Prinza']
    ],
    'Quezon' => [
        'Agdangan' => ['Binagbag', 'Dayap', 'Ibabang Kinagunan', 'Kanlurang Calutan', 'Poblacion', 'Silangang Calutan'],
        'Alabat' => ['Angeles', 'Balungay', 'Camagong', 'Pambilan Norte', 'Poblacion', 'Villa Norte'],
        'Atimonan' => ['Balibago', 'Inaclagan', 'Kilait', 'Malinao Ilaya', 'Magsaysay', 'Poblacion', 'Talaba', 'Villa Ibaba'],
        'Candelaria' => ['Bukal Norte', 'Bukal Sur', 'Kinatihan I', 'Kinatihan II', 'Mangilag Norte', 'Masin Norte', 'Pahinga Norte', 'Poblacion', 'Taguan', 'Mayabobo'],
        'Catanauan' => ['Ajos', 'Catumbo', 'Magsaysay', 'Matandang Sabang Silangan', 'Poblacion', 'San Isidro', 'San Roque', 'Tagbacan Ibaba'],
        'General Luna' => ['Bagong Anyo', 'Bacong Ibaba', 'Magsaysay', 'Malaya', 'Poblacion', 'San Ignacio'],
        'Guinayangan' => ['Bagong Silang', 'Calimpak', 'Danlagan', 'Dulangan', 'Magsino', 'Poblacion', 'Salakan'],
        'Gumaca' => ['Adia Bitaog', 'Batong Dalig', 'Biga', 'Bucal', 'Butaguin', 'Poblacion', 'San Diego'],
        'Infanta' => ['Abiawin', 'Amolongin', 'Antikin', 'Comon', 'Dinahican', 'Lual', 'Poblacion 1', 'Poblacion 38'],
        'Lopez' => ['Bacungan', 'Burgos', 'Danlagan', 'Del Pilar', 'Magsaysay', 'Poblacion', 'San Francisco B', 'Villa Aurora'],
        'Lucban' => ['Abang', 'Aliliw', 'Kulapi', 'May-It', 'Piis', 'Poblacion 1', 'Samil', 'Tinamnan'],
        'Lucena City' => ['Barra', 'Bocohan', 'Cotta', 'Dalahican', 'Gulang-gulang', 'Ibabang Dupay', 'Ilayang Dupay', 'Ilayang Iyam', 'Ibabang Iyam', 'Isabang', 'Market View', 'Mayao Crossing', 'Mayao Kanluran', 'Mayao Silangan', 'Ransohan', 'Salinas', 'Talao-Talao', 'Talao-Talao East', 'Talao-Talao West'],
        'Mauban' => ['Abo-Abo', 'Bagong Bayan', 'Liwayway', 'Lucutan', 'Polo', 'Poblacion', 'San Isidro', 'Santo Angel'],
        'Mulanay' => ['Bagong Silang', 'Cambuga', 'Canuyep', 'F. Nanadiego', 'Poblacion', 'Sta. Rosa', 'Villa Bota', 'Villa Magsino'],
        'Pagbilao' => ['Alupaye', 'Antipolo', 'Bukal', 'Daungan', 'Ibabang Palsabangon', 'Ilayang Palsabangon', 'Kanlurang Malicboy', 'Poblacion'],
        'Pitogo' => ['Amontay', 'Cometa', 'Manggahan', 'Poblacion', 'San Isidro', 'Talon', 'Villa Magsaysay'],
        'Sampaloc' => ['Alupay', 'Bataan', 'Bayongon', 'Ibabang Owain', 'Ilayang Owain', 'Poblacion', 'San Buenaventura'],
        'San Antonio (Quezon)' => ['Bulihan', 'Magsaysay', 'Poblacion', 'Sampaga', 'San Jose', 'Tagumpay'],
        'San Francisco (Aurora)' => ['Butanguiad', 'Don Juan Vercelos', 'Mabuhay', 'Poblacion', 'Silongin', 'Pag-Asa'],
        'Sariaya' => ['Bignay I', 'Bignay II', 'Concepcion Banahaw', 'Concepcion Pinagbakuran', 'Gibanga', 'Manggalang I', 'Manggalang Tulo-Tulo', 'Manggalang Bantilan', 'Sampaloc I', 'Sampaloc II', 'Tumbaga I', 'Tumbaga II', 'Mamala I', 'Mamala II', 'Castanas', 'Lutucan I', 'Lutucan Bata', 'Lutucan Malabag', 'Talaan Aplaya', 'Talaan Pantoc', 'Barangay 1', 'Barangay 2', 'Barangay 3', 'Barangay 4', 'Barangay 5'],
        'Tagkawayan' => ['Aliji', 'Bagong Silang', 'Cabibihan', 'Cagascas', 'Mansilay', 'Poblacion', 'San Diego', 'Tikay'],
        'Tayabas' => ['Alitao', 'Alsam', 'Angeles Zone I', 'Angeles Zone II', 'Baguio', 'Dapdap', 'Isabang', 'Lakawan', 'Lita', 'Mateuna'],
        'Tiaong' => ['Behia', 'Bula', 'Lagalag', 'Lusacan', 'Paiisa', 'Poblacion 1', 'San Agustin', 'Tagbakin'],
        'Unisan' => ['Alibijaban', 'Bonot', 'Kalilayan Ilaya', 'Mabini', 'Panaon', 'Poblacion', 'Tubas']
    ],
    'Pasig (NCR)' => [
        'Pasig City' => ['Bagong Ilog', 'Bambang', 'Buting', 'Caniogan', 'Kalawaan', 'Kapitolyo', 'Manggahan', 'Maybunga', 'Ortigas Center', 'Pineda', 'Rosario', 'San Antonio', 'San Joaquin', 'Santolan', 'Ugong']
    ]
];

$created_at = !empty($user['created_at']) ? new DateTime($user['created_at']) : new DateTime();
$update_deadline = (clone $created_at)->modify('+1 month');
$now = new DateTime();
$profile_update_count = (int)($user['profile_update_count'] ?? 0);
$within_update_window = $now <= $update_deadline;
$can_update_profile = $profile_update_count < 1 && $within_update_window;
$update_deadline_display = $update_deadline->format('F j, Y');
$policy_reason = '';
if ($profile_update_count >= 1) {
    $policy_reason = 'You have already used your one-time profile update.';
} elseif (!$within_update_window) {
    $policy_reason = 'Your one-month profile update window has ended.';
}

// Handle form submission for updating profile
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrf_token, $posted_token)) {
        http_response_code(400);
        die("Invalid request token.");
    }
    if (!$can_update_profile) {
        $errors[] = $policy_reason !== '' ? $policy_reason : "Profile updates are currently disabled.";
    }

    $new_username = trim($_POST['username']);
    $new_email = trim($_POST['email']);
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $suffix_name = trim($_POST['suffix_name'] ?? '');
    $date_of_birth = trim($_POST['date_of_birth'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $province = trim($_POST['province'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    $home_address = trim($_POST['home_address'] ?? '');
    $recovery_email = trim($_POST['recovery_email'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');

    // Validate username
    if (empty($new_username)) {
        $errors[] = "Username is required";
    } elseif (strlen($new_username) < 3) {
        $errors[] = "Username must be at least 3 characters";
    } elseif ($new_username !== $user['username']) {
        // Check if username already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->bind_param("si", $new_username, $user_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = "Username already taken";
        }
        $stmt->close();
    }

    // Validate email
    if (empty($new_email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    } elseif ($new_email !== $user['email']) {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $new_email, $user_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = "Email already registered";
        }
        $stmt->close();
    }

    if ($recovery_email !== '' && !filter_var($recovery_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Recovery email format is invalid";
    }

    $allowed_genders = ['Male', 'Female', 'Other', 'Prefer not to say'];
    if ($gender !== '' && !in_array($gender, $allowed_genders, true)) {
        $errors[] = "Please select a valid gender";
    }

    $allowed_suffixes = ['', 'Jr', 'Sr', 'II', 'III', 'IV'];
    if (!in_array($suffix_name, $allowed_suffixes, true)) {
        $errors[] = "Please select a valid suffix";
    }

    if ($province !== '' && !array_key_exists($province, $calabarzon_map)) {
        $errors[] = "Please select a valid province";
    }
    if ($province !== '' && $city !== '') {
        $cities = array_keys($calabarzon_map[$province] ?? []);
        if (!in_array($city, $cities, true)) {
            $errors[] = "Selected city does not belong to the selected province";
        }
    }
    if ($province !== '' && $city !== '' && $barangay !== '') {
        $barangays = $calabarzon_map[$province][$city] ?? [];
        if (!in_array($barangay, $barangays, true)) {
            $errors[] = "Selected barangay does not belong to the selected city";
        }
    }

    if ($date_of_birth !== '') {
        $dob_obj = DateTime::createFromFormat('Y-m-d', $date_of_birth);
        if (!$dob_obj || $dob_obj->format('Y-m-d') !== $date_of_birth) {
            $errors[] = "Date of birth is invalid";
        }
    }

    if (empty($errors)) {
        $date_of_birth_db = $date_of_birth !== '' ? $date_of_birth : null;
        // Update user data
        $stmt = $conn->prepare("
            UPDATE users
            SET username = ?, email = ?, first_name = ?, middle_name = ?, last_name = ?, suffix_name = ?,
                date_of_birth = ?, gender = ?, province = ?, city = ?, barangay = ?, home_address = ?, recovery_email = ?, contact_number = ?,
                profile_update_count = COALESCE(profile_update_count, 0) + 1
            WHERE id = ?
        ");
        $stmt->bind_param(
            "ssssssssssssssi",
            $new_username,
            $new_email,
            $first_name,
            $middle_name,
            $last_name,
            $suffix_name,
            $date_of_birth_db,
            $gender,
            $province,
            $city,
            $barangay,
            $home_address,
            $recovery_email,
            $contact_number,
            $user_id
        );
        if ($stmt->execute()) {
            $success = "Profile updated successfully!";
            // Update session
            $_SESSION['username'] = $new_username;
            $username = $new_username;
            // Refresh user data
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();
            $profile_update_count = (int)($user['profile_update_count'] ?? 0);
            $can_update_profile = false;
            $policy_reason = 'You have already used your one-time profile update.';
        } else {
            $errors[] = "Failed to update profile";
        }
    }
}

// Handle profile picture upload
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['upload_profile_picture'])) {
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrf_token, $posted_token)) {
        http_response_code(400);
        die("Invalid request token.");
    }

    if (!$avatar_feature_available) {
        $avatar_errors[] = "Avatar upload is currently unavailable because the database column is missing.";
    } elseif (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
        $avatar_errors[] = "Please choose a valid image file.";
    } else {
        $file = $_FILES['profile_picture'];
        $max_size = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $max_size) {
            $avatar_errors[] = "Image size must not exceed 5MB.";
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            $allowed = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp'
            ];

            if (!isset($allowed[$mime])) {
                $avatar_errors[] = "Only JPG, PNG, and WEBP images are allowed.";
            } elseif (@getimagesize($file['tmp_name']) === false) {
                $avatar_errors[] = "Uploaded file is not a valid image.";
            } else {
                $ext = $allowed[$mime];
                $uploads_dir = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . 'uploads';
                if (!is_dir($uploads_dir) && !mkdir($uploads_dir, 0755, true)) {
                    $avatar_errors[] = "Could not create upload directory.";
                } else {
                    $new_filename = 'avatar_' . $user_id . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                    $destination = $uploads_dir . DIRECTORY_SEPARATOR . $new_filename;
                    if (!move_uploaded_file($file['tmp_name'], $destination)) {
                        $avatar_errors[] = "Failed to upload image. Please try again.";
                    } else {
                        $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                        $stmt->bind_param("si", $new_filename, $user_id);
                        if ($stmt->execute()) {
                            if (!empty($user['profile_picture'])) {
                                $old_file = $uploads_dir . DIRECTORY_SEPARATOR . basename($user['profile_picture']);
                                if (is_file($old_file) && basename($old_file) !== basename($new_filename)) {
                                    @unlink($old_file);
                                }
                            }
                            $user['profile_picture'] = $new_filename;
                            $_SESSION['profile_picture'] = $new_filename;
                            $avatar_success = "Profile picture updated successfully.";
                        } else {
                            @unlink($destination);
                            $avatar_errors[] = "Could not save image in your profile.";
                        }
                        $stmt->close();
                    }
                }
            }
        }
    }
}

// Handle password change
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrf_token, $posted_token)) {
        http_response_code(400);
        die("Invalid request token.");
    }
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password)) {
        $password_errors[] = "Current password is required";
    } elseif (!password_verify($current_password, $user['password'])) {
        $password_errors[] = "Current password is incorrect";
    }

    if (empty($new_password)) {
        $password_errors[] = "New password is required";
    } elseif (strlen($new_password) < 6) {
        $password_errors[] = "New password must be at least 6 characters";
    } elseif ($new_password !== $confirm_password) {
        $password_errors[] = "New passwords do not match";
    }

    if (empty($password_errors)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        if ($stmt->execute()) {
            $password_success = "Password changed successfully!";
        } else {
            $password_errors[] = "Failed to change password";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Account Info - MotoFit</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<style>
* { box-sizing: border-box; }
body { min-height:100vh; background: linear-gradient(135deg,#1b1b1b,#111); color:white; padding-top:100px; font-family:"Montserrat",sans-serif; }
.navbar { position:fixed; top:0; left:0; right:0; display:flex; justify-content:space-between; align-items:center; padding:10px 50px; background: rgba(0,0,0,0.8); z-index:999; }
.navbar .logo { display:flex; align-items:center; gap:10px; color:white; text-decoration:none; font-size:22px; font-weight:700; }
.navbar .logo img { height: 60px; width:auto; }
.navbar .nav-links { display:flex; align-items:center; gap:15px; }
.navbar .nav-links a { color:white; text-decoration:none; font-weight:500; margin-left:15px; }
.navbar .nav-links a:hover { text-decoration:underline; }

/* Account icon in navbar */
.account-dropdown { display:flex; align-items:center; margin-left:15px; }
.account-icon { width:40px; height:40px; background:#27ae60; border-radius:50%; display:flex; justify-content:center; align-items:center; font-weight:bold; margin-right:5px; }
.account-username { font-weight:600; color:white; }

/* ===== ACCOUNT DROPDOWN (FIX) ===== */
.account-dropdown {
  position: relative;
  display: flex;
  align-items: center;
}

.account-trigger {
  background: none;
  border: none;
  color: white;
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
}

.account-icon {
  width: 40px;
  height: 40px;
  background: #27ae60;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
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
  box-shadow: 0 10px 30px rgba(0,0,0,0.4);
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
}

.account-menu a:hover {
  background: rgba(255,255,255,0.08);
}

/* SHOW MENU */
.account-dropdown.active .account-menu {
  display: block;
}


/* Wrapper */
.wrapper { max-width:940px; margin:auto; padding:24px 20px 28px; }
.wrapper h1 { text-align:center; margin-bottom:20px; font-size:32px; }
.account-info p { margin:10px 0; font-size:16px; }

/* Profile Picture */
.profile-picture { text-align: center; margin-bottom: 30px; }
.profile-picture img { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 3px solid #27ae60; }
.profile-picture .upload-btn { display: inline-block; margin-top: 10px; padding: 8px 16px; background: #27ae60; color: white; border-radius: 20px; cursor: pointer; font-size: 14px; border: none; }
.profile-picture .upload-btn:hover { background: #2ecc71; }
.profile-picture input[type="file"] { display: none; }
.avatar-actions { display: flex; justify-content: center; gap: 10px; margin-top: 12px; flex-wrap: wrap; }
.avatar-note { font-size: 12px; color: rgba(255,255,255,0.7); margin-top: 8px; }
.avatar-success { color: #27ae60; text-align: center; margin-top: 8px; }
.avatar-error { color: #ef233c; text-align: center; margin-top: 8px; }

html[data-theme="light"] .avatar-note {
  color: var(--rbj-muted, #9f4b43);
}

/* Edit Profile Form */
.edit-profile {
  width: 100%;
  background: rgba(0,0,0,0.6);
  border: 1px solid rgba(255,255,255,0.10);
  padding: 28px;
  border-radius: 14px;
  margin-top: 24px;
}
.edit-profile h2 { text-align: center; margin-bottom: 20px; color: #fff; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 14px; }
.input-box { position: relative; width: 100%; margin: 14px 0; }
.input-box input {
  display: block;
  width: 100%;
  max-width: 100%;
  height: 52px;
  background: transparent;
  border: 2px solid rgba(255,255,255,0.45);
  border-radius: 14px;
  padding: 0 16px;
  font-size: 15px;
  color: white;
}
.input-box select,
.input-box textarea {
  display: block;
  width: 100%;
  max-width: 100%;
  min-height: 52px;
  background: transparent;
  border: 2px solid rgba(255,255,255,0.45);
  border-radius: 14px;
  padding: 12px 16px;
  font-size: 15px;
  color: white;
}
.input-box select option { color: #111; background: #fff; }
.input-box textarea { resize: vertical; min-height: 94px; }
.input-box input::placeholder { color: rgba(255,255,255,0.7); }
.input-box select::placeholder,
.input-box textarea::placeholder { color: rgba(255,255,255,0.7); }
.btn {
  width: 100%;
  height: 46px;
  background: #fff;
  color: #333;
  border: none;
  border-radius: 14px;
  font-weight: 600;
  cursor: pointer;
  margin-top: 6px;
}
.btn:hover { background: #e6e6e6; }
.success { color: #27ae60; text-align: center; margin-bottom: 20px; }
.error { color: #ef233c; text-align: center; margin-bottom: 20px; }
.policy-note {
  background: rgba(255,255,255,0.06);
  border: 1px solid rgba(255,255,255,0.12);
  border-left: 4px solid #f1c40f;
  border-radius: 10px;
  padding: 12px 14px;
  margin-bottom: 16px;
  font-size: 14px;
  line-height: 1.55;
}
.policy-note p { margin: 4px 0; }
.policy-note .lock { color: #f39c12; font-weight: 700; }

@media(max-width:640px){
  .navbar{padding:10px 20px;}
  .navbar .nav-links a{margin-left:10px;font-size:14px;}
  .wrapper { padding: 20px 12px 24px; }
  .edit-profile { padding: 18px 14px; border-radius: 12px; }
  .input-box input { height: 48px; font-size: 14px; }
  .input-box select { min-height: 48px; font-size: 14px; }
  .form-grid { grid-template-columns: 1fr; }
}
</style>
<?php include __DIR__ . '/partials/user_navbar_theme.php'; ?>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
  <a href="index.php" class="logo">
    <img src="../rbjlogo.png" alt="RBJ Accessories Logo">
    <span>RBJ Accessories</span>
  </a>
  <div class="nav-links">
    <a href="index.php">Home</a>
    <a href="catalog.php">Shop</a>
    <a href="customize.php">Customize</a>
    <a href="cart.php" class="nav-cart-link" title="Cart" aria-label="Cart"><i class='bx bx-cart'></i></a>
    <?php include __DIR__ . '/partials/account_menu.php'; ?>

</nav>

<div class="wrapper">
  <h1>Account Info</h1>

  <!-- Profile Picture Section -->
  <div class="profile-picture">
    <img id="profileAvatarPreview" src="<?php echo ($avatar_feature_available && !empty($user['profile_picture'])) ? '../uploads/' . htmlspecialchars($user['profile_picture']) : 'https://via.placeholder.com/150x150/27ae60/ffffff?text=' . strtoupper(substr($user['username'], 0, 1)); ?>" alt="Profile Picture">
    <form method="POST" action="account_info.php" enctype="multipart/form-data" id="avatarUploadForm">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
      <input type="file" id="profile-pic-input" name="profile_picture" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
      <div class="avatar-actions">
        <button type="button" class="upload-btn" id="chooseAvatarBtn"><i class='bx bx-image-add'></i> Choose Image</button>
        <button type="submit" name="upload_profile_picture" class="upload-btn"><i class='bx bx-upload'></i> Upload Avatar</button>
      </div>
    </form>
    <p class="avatar-note">Allowed: JPG, PNG, WEBP (max 5MB)</p>
    <?php if (!$avatar_feature_available): ?>
      <p class="avatar-error">Avatar upload is disabled because the `profile_picture` database column is unavailable.</p>
    <?php endif; ?>
    <?php if (isset($avatar_success)): ?>
      <p class="avatar-success"><?php echo htmlspecialchars($avatar_success); ?></p>
    <?php endif; ?>
    <?php if (!empty($avatar_errors)): ?>
      <p class="avatar-error"><?php echo htmlspecialchars(implode(' ', $avatar_errors)); ?></p>
    <?php endif; ?>
  </div>

  

  <!-- Edit Profile Form -->
  <div class="edit-profile">
    <h2>Edit Profile</h2>

    <div class="policy-note">
      <p>*Please proceed to the <strong>RBJ Support Team</strong> for any changes to disabled fields.</p>
      <p>*Profile updating is allowed only within one month from account creation (until <strong><?php echo htmlspecialchars($update_deadline_display); ?></strong>).</p>
      <p>*You can only update once.</p>
      <?php if (!$can_update_profile): ?>
        <p class="lock">Update is locked: <?php echo htmlspecialchars($policy_reason !== '' ? $policy_reason : 'Profile update is currently disabled.'); ?></p>
      <?php endif; ?>
    </div>

    <?php if (isset($success)): ?>
      <p class="success"><?php echo $success; ?></p>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <p class="error"><?php echo implode('<br>', $errors); ?></p>
    <?php endif; ?>

    <form method="POST" action="account_info.php">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
      <div class="form-grid">
        <div class="input-box">
          <input type="text" name="first_name" placeholder="First Name (ex. Angel)" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" <?php echo !$can_update_profile ? 'disabled' : ''; ?>>
        </div>
        <div class="input-box">
          <input type="text" name="middle_name" placeholder="Middle Name (optional)" value="<?php echo htmlspecialchars($user['middle_name'] ?? ''); ?>" <?php echo !$can_update_profile ? 'disabled' : ''; ?>>
        </div>

        <div class="input-box">
          <input type="text" name="last_name" placeholder="Last Name (ex. Domingo)" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" <?php echo !$can_update_profile ? 'disabled' : ''; ?>>
        </div>
        <div class="input-box">
          <?php $suffix_val = $user['suffix_name'] ?? ''; ?>
          <select name="suffix_name" <?php echo !$can_update_profile ? 'disabled' : ''; ?>>
            <option value="">Suffix Name (optional)</option>
            <option value="Jr" <?php echo $suffix_val === 'Jr' ? 'selected' : ''; ?>>Jr</option>
            <option value="Sr" <?php echo $suffix_val === 'Sr' ? 'selected' : ''; ?>>Sr</option>
            <option value="II" <?php echo $suffix_val === 'II' ? 'selected' : ''; ?>>II</option>
            <option value="III" <?php echo $suffix_val === 'III' ? 'selected' : ''; ?>>III</option>
            <option value="IV" <?php echo $suffix_val === 'IV' ? 'selected' : ''; ?>>IV</option>
          </select>
        </div>

        <div class="input-box">
          <input type="date" name="date_of_birth" value="<?php echo htmlspecialchars($user['date_of_birth'] ?? ''); ?>" <?php echo !$can_update_profile ? 'disabled' : ''; ?>>
        </div>
        <div class="input-box">
          <select name="gender" <?php echo !$can_update_profile ? 'disabled' : ''; ?>>
            <?php $gender_val = $user['gender'] ?? ''; ?>
            <option value="">Select Gender</option>
            <option value="Male" <?php echo $gender_val === 'Male' ? 'selected' : ''; ?>>Male</option>
            <option value="Female" <?php echo $gender_val === 'Female' ? 'selected' : ''; ?>>Female</option>
            <option value="Other" <?php echo $gender_val === 'Other' ? 'selected' : ''; ?>>Other</option>
            <option value="Prefer not to say" <?php echo $gender_val === 'Prefer not to say' ? 'selected' : ''; ?>>Prefer not to say</option>
          </select>
        </div>

        <div class="input-box">
          <?php $province_val = $user['province'] ?? ''; ?>
          <select name="province" id="provinceSelect" <?php echo !$can_update_profile ? 'disabled' : ''; ?>>
            <option value="">Select Province/Area</option>
            <?php foreach (array_keys($calabarzon_map) as $province_name): ?>
              <option value="<?php echo htmlspecialchars($province_name); ?>" <?php echo $province_val === $province_name ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($province_name); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="input-box">
          <?php $city_val = $user['city'] ?? ''; ?>
          <select name="city" id="citySelect" <?php echo !$can_update_profile ? 'disabled' : ''; ?>>
            <option value="">Select City</option>
          </select>
        </div>
        <div class="input-box">
          <?php $barangay_val = $user['barangay'] ?? ''; ?>
          <select name="barangay" id="barangaySelect" <?php echo !$can_update_profile ? 'disabled' : ''; ?>>
            <option value="">Select Barangay</option>
          </select>
        </div>
      </div>

      <div class="input-box">
        <textarea name="home_address" placeholder="Home Address (Complete Address)" <?php echo !$can_update_profile ? 'disabled' : ''; ?>><?php echo htmlspecialchars($user['home_address'] ?? ''); ?></textarea>
      </div>

      <div class="form-grid">
        <div class="input-box">
          <input type="email" name="recovery_email" placeholder="Recovery Email (ex. domingo@gmail.com)" value="<?php echo htmlspecialchars($user['recovery_email'] ?? ''); ?>" <?php echo !$can_update_profile ? 'disabled' : ''; ?>>
        </div>
        <div class="input-box">
          <input type="text" name="contact_number" placeholder="Contact Number (any format)" value="<?php echo htmlspecialchars($user['contact_number'] ?? ''); ?>" <?php echo !$can_update_profile ? 'disabled' : ''; ?>>
        </div>
      </div>

      <div class="form-grid">
        <div class="input-box">
          <input type="text" name="username" placeholder="Username" value="<?php echo htmlspecialchars($user['username']); ?>" required <?php echo !$can_update_profile ? 'disabled' : ''; ?>>
        </div>
        <div class="input-box">
          <input type="email" name="email" placeholder="Email" value="<?php echo htmlspecialchars($user['email']); ?>" required <?php echo !$can_update_profile ? 'disabled' : ''; ?>>
        </div>
      </div>

      <button type="submit" name="update_profile" class="btn" <?php echo !$can_update_profile ? 'disabled' : ''; ?>>
        <?php echo $can_update_profile ? 'Update Profile' : 'Profile Update Locked'; ?>
      </button>
    </form>
  </div>

  <!-- Change Password Form -->
  <div class="edit-profile">
    <h2>Change Password</h2>

    <?php if (isset($password_success)): ?>
      <p class="success"><?php echo $password_success; ?></p>
    <?php endif; ?>

    <?php if (!empty($password_errors)): ?>
      <p class="error"><?php echo implode('<br>', $password_errors); ?></p>
    <?php endif; ?>

    <form method="POST" action="account_info.php">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
      <div class="input-box">
        <input type="password" name="current_password" placeholder="Current Password" required>
      </div>

      <div class="input-box">
        <input type="password" name="new_password" placeholder="New Password" required>
      </div>

      <div class="input-box">
        <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
      </div>

      <button type="submit" name="change_password" class="btn">Change Password</button>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const fileInput = document.getElementById('profile-pic-input');
  const chooseBtn = document.getElementById('chooseAvatarBtn');
  const avatarPreview = document.getElementById('profileAvatarPreview');
  const avatarForm = document.getElementById('avatarUploadForm');
  const provinceSelect = document.getElementById('provinceSelect');
  const citySelect = document.getElementById('citySelect');
  const barangaySelect = document.getElementById('barangaySelect');
  const locationMap = <?php echo json_encode($calabarzon_map, JSON_UNESCAPED_UNICODE); ?>;
  const selectedProvince = <?php echo json_encode($user['province'] ?? ''); ?>;
  const selectedCity = <?php echo json_encode($user['city'] ?? ''); ?>;
  const selectedBarangay = <?php echo json_encode($user['barangay'] ?? ''); ?>;

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
</script>

<?php $conn->close(); ?>


<?php include __DIR__ . '/partials/user_footer.php'; ?>

</body>
</html>



