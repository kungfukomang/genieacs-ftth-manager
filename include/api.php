<?php
ob_start(); // Mulai menangkap semua output agar tidak merusak JSON
// C:\xampp\htdocs\genieacs\include\api.php
// Matikan semua output error PHP agar tidak merusak format JSON
error_reporting(0);
ini_set('display_errors', 0);
ini_set('memory_limit', '1024M'); // Set memory limit ke 1024MB
set_time_limit(300); // Set waktu eksekusi maksimal ke 300 detik
header('Content-Type: application/json');

// --- KEAMANAN: VERIFIKASI SESSION LOGIN ---
session_start();
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    http_response_code(401);
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Silakan login terlebih dahulu.']);
    exit;
}

// 1. Load Konfigurasi
if (!file_exists(__DIR__ . '/config.php')) {
    ob_clean();
    die(json_encode(['status'=>'error', 'message'=>'Config not found']));
}
define('GENIE_ACCESS', true);
require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// --- DATABASE JSON LOKAL (Tags) ---
$jsonFile = __DIR__ . '/../data/tags.json';

// Fungsi Baca Tag Lokal
function getLocalTags() {
    global $jsonFile;
    if (!file_exists($jsonFile)) return [];
    $content = file_get_contents($jsonFile);
    return json_decode($content, true) ?? [];
}

// Fungsi Simpan Tag Lokal
function saveLocalTag($id, $tag) {
    global $jsonFile;
    $data = getLocalTags();
    
    // Jika tag kosong, hapus dari database. Jika ada, simpan/update.
    if (trim($tag) === '') {
        if (isset($data[$id])) unset($data[$id]);
    } else {
        $data[$id] = $tag;
    }
    
    // Buat folder data jika belum ada
    $dir = dirname($jsonFile);
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    
    file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT));
}

// Fungsi Kirim Telegram
function sendTelegram($chatId, $message) {
    if (!defined('TELEGRAM_ENABLED') || !TELEGRAM_ENABLED) return;
    if (!$chatId) $chatId = defined('TELEGRAM_ADMIN_ID') ? TELEGRAM_ADMIN_ID : '';
    if (!$chatId) return;

    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'Markdown'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Penting buat localhost
    curl_exec($ch);
    // curl_close($ch); // Deprecated in PHP 8.0+
}

// --- HELPER: Fungsi Komunikasi ke GenieACS ---
function callGenie($endpoint, $method = 'GET', $data = null, $timeout = null) {
    $ch = curl_init(GENIE_URL . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout ? $timeout : GENIE_TIMEOUT);
    curl_setopt($ch, CURLOPT_USERPWD, GENIE_USER . ':' . GENIE_PASS);
    
    // Bypass SSL (Penting untuk localhost/IP)
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    if ($method !== 'GET') {
        if ($method === 'POST') curl_setopt($ch, CURLOPT_POST, true);
        else curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    // curl_close($ch); // Deprecated in PHP 8.0+

    if ($err) return ['error' => $err];
    
    // Cek HTTP Code (Jika bukan 200 OK, berarti error dari GenieACS)
    if ($httpCode >= 400) {
        return ['error' => "GenieACS Error ($httpCode)", 'details' => strip_tags($response)];
    }
    
    $decoded = json_decode($response, true);
    // Jika gagal decode JSON (misal server error HTML), kembalikan error
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'Invalid JSON from GenieACS', 'raw_response' => substr($response, 0, 200)];
    }
    return $decoded;
}

// Helper untuk mencari perangkat GenieACS dengan toleransi karakter khusus (_id dengan %2D vs '-')
function getGenieDevice($id, $projection = null) {
    if (!$id) return null;
    
    $possibleIds = [$id, rawurldecode($id)];
    $parts = explode('-', $id);
    if (count($parts) >= 3) {
        $oui = array_shift($parts);
        $sn = array_pop($parts);
        $middle = implode('-', $parts);
        $possibleIds[] = $oui . '-' . str_replace('-', '%2D', $middle) . '-' . $sn;
        $possibleIds[] = $oui . '-' . str_replace('%2D', '-', $middle) . '-' . $sn;
    }
    $possibleIds = array_values(array_unique(array_filter($possibleIds)));

    $query = ['_id' => count($possibleIds) === 1 ? $possibleIds[0] : ['$in' => $possibleIds]];
    $endpoint = "/devices/?query=" . urlencode(json_encode($query));
    if ($projection) {
        $endpoint .= "&projection=" . urlencode($projection);
    }
    $res = callGenie($endpoint);
    if (is_array($res) && !isset($res['error']) && count($res) > 0 && isset($res[0]['_id'])) {
        return $res[0];
    }

    // Fallback: Jika belum ketemu, coba pencarian regex dengan OUI dan Serial Number
    if (isset($oui) && isset($sn)) {
        $regex = '^' . preg_quote($oui, '/') . '.*' . preg_quote($sn, '/') . '$';
        $endpointRegex = "/devices/?query=" . urlencode(json_encode(['_id' => ['$regex' => $regex]]));
        if ($projection) {
            $endpointRegex .= "&projection=" . urlencode($projection);
        }
        $resRegex = callGenie($endpointRegex);
        if (is_array($resRegex) && !isset($resRegex['error']) && count($resRegex) > 0 && isset($resRegex[0]['_id'])) {
            return $resRegex[0];
        }
    }

    return null;
}

// --- 1. ACTION: SEARCH (Pencarian Device) ---
if ($action === 'search') {
    $k = $_GET['keyword'] ?? '';
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    $query = new stdClass();
    
    if ($k) {
        // Cari berdasarkan ID, User, IP, atau SSID (Case Insensitive)
        $regex = ['$regex' => $k, '$options' => 'i'];
        $query = ['$or' => [
            ['_id' => $regex],
            ['_tags' => $regex],
            ['VirtualParameters.pppoeUsername' => $regex],
            ['VirtualParameters.SSID' => $regex],
            ['VirtualParameters.pppoeIP' => $regex],
            ['_ip' => $regex]
        ]];
    }
    
    // Ambil field seperlunya agar ringan
    $projection = implode(',', [
        '_id', '_tags', '_lastInform', '_ip',
        'VirtualParameters', 
        'InternetGatewayDevice.WANDevice', 
        'InternetGatewayDevice.LANDevice',
        'Device.WANDevice', 
        'Device.LANDevice'
    ]);

    // Ubah format sort menjadi JSON Object agar diterima oleh GenieACS
    $sort = json_encode(['_lastInform' => -1]);
    // Tambahkan urlencode pada projection agar lebih aman
    $endpoint = "/devices/?query=" . urlencode(json_encode($query)) . "&projection=" . urlencode($projection) . "&sort=" . urlencode($sort) . "&limit=" . $limit;
    
    // Gunakan timeout 300 detik (5 menit) khusus untuk search agar tidak error saat load 500+ data
    $res = callGenie($endpoint, 'GET', null, 300);
    
    // GABUNGKAN DENGAN TAG LOKAL
    if (is_array($res) && !isset($res['error'])) {
        $localTags = getLocalTags();
        foreach ($res as &$dev) {
            if (isset($localTags[$dev['_id']])) {
                // Tambahkan tag lokal ke array _tags agar muncul di tabel/peta
                if (!isset($dev['_tags'])) $dev['_tags'] = [];
                $dev['_tags'][] = $localTags[$dev['_id']];
                $dev['_local_tag'] = $localTags[$dev['_id']]; // Kirim info tag lokal spesifik untuk diedit
            }
        }
    }
    
    ob_clean(); // Hapus output tak terduga (seperti warning) sebelum kirim JSON
    echo json_encode($res);
    exit;
}

// --- 2. ACTION: GET DETAIL ---
if ($action === 'get_detail') {
    $id = $_GET['id'] ?? '';
    if (!$id) { ob_clean(); echo json_encode(['error' => 'No ID']); exit; }
    
    $dev = getGenieDevice($id);

    // Ambil item pertama dari hasil pencarian
    if ($dev) {
        // Inject Tag Lokal
        $localTags = getLocalTags();
        if (isset($localTags[$dev['_id']])) {
            if (!isset($dev['_tags'])) $dev['_tags'] = [];
            $dev['_tags'][] = $localTags[$dev['_id']];
            $dev['_local_tag'] = $localTags[$dev['_id']];
        }
        ob_clean();
        echo json_encode($dev);
    } else {
        ob_clean();
        echo json_encode(['error' => 'Device Not Found']);
    }
    
    exit;
}

// --- 3. ACTION: ADD TAG (YANG ANDA MINTA) ---
if ($action === 'add_tag' && $method === 'POST') {
    $id = $_POST['device_id'] ?? ''; // Jangan di-trim, biarkan apa adanya (jika ada spasi di ID)
    $sn = trim($_POST['sn'] ?? '');
    $tag = trim($_POST['tag'] ?? '');
    
    // Jika SN ada, cari ID asli dari database GenieACS (Override ID dari frontend)
    $realId = '';
    if ($sn) {
        $query = [
            '$or' => [
                ['VirtualParameters.getSerialNumber' => $sn],
                ['InternetGatewayDevice.DeviceInfo.SerialNumber' => $sn],
                ['Device.DeviceInfo.SerialNumber' => $sn]
            ]
        ];
        $searchUrl = "/devices/?query=" . urlencode(json_encode($query)) . "&projection=_id";
        $searchRes = callGenie($searchUrl);
        if (is_array($searchRes) && count($searchRes) > 0 && isset($searchRes[0]['_id'])) {
            $realId = $searchRes[0]['_id'];
        }
    }
    if (!$realId && $id) {
        $found = getGenieDevice($id, '_id');
        if ($found) $realId = $found['_id'];
    }
    if (!$realId) $realId = $id;

    if ($realId) {
        // SIMPAN KE JSON LOKAL (Bypass GenieACS API)
        saveLocalTag($realId, $tag);
        ob_clean();
        echo json_encode(['status' => 'success', 'message' => 'Tag saved locally', 'id' => $realId]);
    } else {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'ID or Tag missing']);
    }
    exit;
}

// --- 4. ACTION: REBOOT ---
if ($action === 'reboot' && $method === 'POST') {
    $id = $_POST['device_id'] ?? '';
    if ($id) {
        $found = getGenieDevice($id, '_id');
        $realId = $found ? $found['_id'] : $id;
        $endpoint = "/devices/" . rawurlencode($realId) . "/tasks?timeout=3000&connection_request";
        $res = callGenie($endpoint, 'POST', ['name' => 'reboot']);
        
        // Kirim Notif Telegram
        sendTelegram(TELEGRAM_ADMIN_ID, "⚠️ *DEVICE REBOOT*\n\n🆔 ID: `$realId`\n🕒 Waktu: " . date('Y-m-d H:i:s'));
        
        ob_clean();
        echo json_encode($res);
    }
    exit;
}

// --- 5. ACTION: UPDATE WIFI (Opsional) ---
if ($action === 'update_wifi' && $method === 'POST') {
    $id = $_POST['device_id'] ?? '';
    $ssid = $_POST['ssid'] ?? null;
    $pass = $_POST['pass'] ?? $_POST['password'] ?? null;

    if (!$id) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Device ID missing']);
        exit;
    }

    $dev = getGenieDevice($id);
    if (!$dev) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Device Not Found']);
        exit;
    }
    $realId = $dev['_id'];

    $params = [];
    // 1. Deteksi Root Path (IGD atau Device)
    $root = "InternetGatewayDevice";
    if (isset($dev['Device'])) {
        $root = "Device";
    }

    if ($root === "InternetGatewayDevice") {
        if ($ssid !== null) $params[] = ['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID', (string)$ssid, 'xsd:string'];
        if ($pass !== null) {
            // Deteksi parameter password yang tepat (KeyPassphrase vs PreSharedKey)
            $wlan = $dev['InternetGatewayDevice']['LANDevice'][1]['WLANConfiguration'][1] ?? [];
            $passParam = 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey'; // Default
            
            if (isset($wlan['KeyPassphrase'])) {
                $passParam = 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.KeyPassphrase';
            } elseif (isset($wlan['PreSharedKey']) && is_array($wlan['PreSharedKey']) && isset($wlan['PreSharedKey']['1'])) {
                $passParam = 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.PreSharedKey';
            }
            $params[] = [$passParam, (string)$pass, 'xsd:string'];
        }
    } else {
        // TR-181 (Modem Baru)
        if ($ssid !== null) $params[] = ['Device.WiFi.SSID.1.SSID', (string)$ssid, 'xsd:string'];
        if ($pass !== null) $params[] = ['Device.WiFi.AccessPoint.1.Security.KeyPassphrase', (string)$pass, 'xsd:string'];
    }

    if (empty($params)) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'No parameters to update']);
        exit;
    }

    // Kirim tugas ke GenieACS dengan connection_request agar langsung dieksekusi
    $endpoint = "/devices/" . rawurlencode($realId) . "/tasks?connection_request";
    $res = callGenie($endpoint, 'POST', ['name' => 'setParameterValues', 'parameterValues' => $params]);

    if (isset($res['error'])) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => $res['error'], 'details' => $res['details'] ?? '']);
    } else {
        ob_clean();
        echo json_encode(['status' => 'success']);
    }
    exit;
}

// --- 6. ACTION: SETTINGS (GET & SAVE) ---
if ($action === 'get_settings') {
    ob_clean();
    echo json_encode([
        'genie_host' => GENIE_HOST,
        'genie_port' => GENIE_PORT,
        'genie_user' => GENIE_USER,
        'genie_pass' => GENIE_PASS,
        'genie_timeout' => GENIE_TIMEOUT,
        'dash_user' => DASH_USER,
        'dash_pass' => DASH_PASS,
        'server_lat' => SERVER_LAT,
        'server_lng' => SERVER_LNG,
        'map_zoom' => MAP_INITIAL_ZOOM,
        'telegram_token' => TELEGRAM_BOT_TOKEN,
        'telegram_admin_id' => TELEGRAM_ADMIN_ID,
    ]);
    exit;
}

if ($action === 'save_settings' && $method === 'POST') {
    $settingsFile = __DIR__ . '/../data/settings.json';
    
    // Pastikan folder data ada
    $dir = dirname($settingsFile);
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    
    // Simpan semua input POST ke JSON
    if (file_put_contents($settingsFile, json_encode($_POST, JSON_PRETTY_PRINT))) {
        ob_clean();
        echo json_encode(['status' => 'success']);
    } else {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Gagal menulis file settings.json']);
    }
    exit;
}

// --- 7. ACTION: GET & SAVE FTTH DATA ---
$ftthFile = __DIR__ . '/../data/ftth.json';
$routeFile = __DIR__ . '/../data/route.json';

// Endpoint untuk mengambil data tiang, ODC, ODP, dll.
if ($action === 'get_ftth_data') {
    ob_clean();
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    if (file_exists($ftthFile) && filesize($ftthFile) > 0) {
        readfile($ftthFile);
    } else {
        echo '[]';
    }
    exit;
}

// Endpoint untuk menyimpan data tiang, ODC, ODP, dll.
if ($action === 'save_ftth_data' && $method === 'POST') {
    // Ambil data JSON mentah dari body request
    $postData = file_get_contents('php://input');
    if ($postData === false) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Gagal membaca data POST.']);
        exit;
    }
    $decodedData = json_decode($postData, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Data JSON yang diterima tidak valid. Error: ' . json_last_error_msg()]);
        exit;
    }

    $dir = dirname($ftthFile);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0777, true)) {
            ob_clean();
            echo json_encode(['status' => 'error', 'message' => 'Gagal membuat direktori data. Cek izin folder.']);
            exit;
        }
    }

    // Cek apakah file ada dan bisa ditulis, atau jika tidak ada, apakah direktori bisa ditulis.
    if ((file_exists($ftthFile) && !is_writable($ftthFile)) || (!file_exists($ftthFile) && !is_writable($dir))) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'File ftth.json tidak dapat ditulis. Cek izin file/folder.']);
        exit;
    }

    if (file_put_contents($ftthFile, json_encode($decodedData, JSON_PRETTY_PRINT))) {
        ob_clean();
        echo json_encode(['status' => 'success', 'message' => 'Data FTTH berhasil disimpan.']);
    } else {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Gagal menulis ke file ftth.json karena alasan yang tidak diketahui.']);
    }
    exit;
}

// --- 8. ACTION: GET & SAVE ROUTE DATA ---

// Endpoint untuk mengambil data route jalan
if ($action === 'get_route_data') {
    ob_clean();
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    if (file_exists($routeFile) && filesize($routeFile) > 0) {
        readfile($routeFile);
    } else {
        echo '{"automatic":{}}';
    }
    exit;
}

// Endpoint untuk menyimpan data route jalan
if ($action === 'save_route_data' && $method === 'POST') {
    $postData = file_get_contents('php://input');
    $decodedData = json_decode($postData, true);

    // Validate the structure of the received data
    if (json_last_error() === JSON_ERROR_NONE && is_array($decodedData) && isset($decodedData['automatic'])) {
        $dir = dirname($routeFile);
        if (!is_dir($dir)) mkdir($dir, 0777, true);

        // Check for write permissions
        if ((file_exists($routeFile) && !is_writable($routeFile)) || (!file_exists($routeFile) && !is_writable($dir))) {
            ob_clean();
            echo json_encode(['status' => 'error', 'message' => 'File route.json tidak dapat ditulis. Cek izin file/folder.']);
            exit;
        }

        if (file_put_contents($routeFile, json_encode($decodedData, JSON_PRETTY_PRINT))) {
            ob_clean();
            echo json_encode(['status' => 'success', 'message' => 'Data Route berhasil disimpan.']);
        } else {
            ob_clean();
            echo json_encode(['status' => 'error', 'message' => 'Gagal menulis ke file route.json karena alasan yang tidak diketahui.']);
        }
    } else {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Data JSON yang diterima tidak valid atau formatnya salah.']);
    }
    exit;
}