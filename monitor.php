<?php
// C:\xampp\htdocs\genieacs\monitor.php
// Script Monitoring Otomatis untuk device offline.

// HANYA BISA DIJALANKAN DARI TERMINAL / CLI / CRON JOB
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Akses ditolak: Script ini hanya dapat dijalankan melalui CLI / Cron job.\n");
}

// --- PENTING: PENGATURAN ZONA WAKTU ---
// Sesuaikan dengan zona waktu Anda (WIB: Asia/Jakarta, WITA: Asia/Makassar, WIT: Asia/Jayapura)
date_default_timezone_set('Asia/Jakarta');
// ------------------------------------

// --- PENGECEKAN WAJIB ---
if (!function_exists('curl_init')) {
    die("FATAL ERROR: PHP extension 'curl' is not installed or enabled. Please run: sudo apt install php-curl\n");
}
if (!function_exists('json_decode')) {
    die("FATAL ERROR: PHP extension 'json' is not installed or enabled. Please run: sudo apt install php-json\n");
}

// Script ini dijalankan secara berkala menggunakan Cron Job.
// Contoh Cron Job (setiap 1 menit): * * * * * /usr/bin/php /var/www/html/monitor.php

define('GENIE_ACCESS', true);
require_once __DIR__ . '/include/config.php';

// --- KONFIGURASI MONITORING ---
// Batas waktu device dianggap offline (dalam menit). Anda bisa mengubah angka ini.
$offlineMinutes = 1;
// -----------------------------

echo "--- MONITORING SCRIPT STARTED [" . date('Y-m-d H:i:s') . "] ---\n";
echo "Mengecek device yang offline lebih dari $offlineMinutes menit...\n";

$dataDir = __DIR__ . '/data';
$offlineThreshold = $offlineMinutes * 60;
$stateFile = $dataDir . '/offline_state.json';

// --- Pengecekan Izin Folder ---
if (!is_dir($dataDir)) {
    // Coba buat direktori, jika gagal, script berhenti dengan error jelas.
    if (!@mkdir($dataDir, 0775, true)) {
        die("FATAL ERROR: Gagal membuat direktori '$dataDir'. Cek izin folder induk (" . dirname($dataDir) . ").\n");
    }
}
if (!is_writable($dataDir)) {
    die("FATAL ERROR: Direktori '$dataDir' tidak bisa ditulis (not writable). Jalankan: sudo chmod -R 775 '$dataDir'\n");
}
// -----------------------------
 
// 1. Ambil semua device dari GenieACS
$projection = "_id,_lastInform,VirtualParameters.pppoeUsername,_tags";
$endpoint = "/devices/?query=%7B%7D&projection=" . rawurlencode($projection) . "&limit=5000"; // Ambil hingga 5000 device

$ch = curl_init(GENIE_URL . $endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, GENIE_USER . ':' . GENIE_PASS);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 120); // Timeout 2 menit untuk query data besar
$res = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

$devices = json_decode($res, true);

if (!is_array($devices) || $err) {
    die("ERROR: Gagal mengambil data dari GenieACS. " . ($err ? "cURL Error: $err" : "Response bukan JSON valid.") . "\n");
}

echo "Ditemukan " . count($devices) . " total device.\n";

// 2. Load state sebelumnya (device yang sudah dinotifikasi)
$previouslyNotifiedIds = [];
if (file_exists($stateFile)) {
    $content = file_get_contents($stateFile);
    $previouslyNotifiedIds = $content ? json_decode($content, true) : [];
}
if (!is_array($previouslyNotifiedIds)) $previouslyNotifiedIds = [];

$currentOfflineIds = [];
$newlyOfflineCount = 0;
$now = time();

// 3. Proses setiap device
foreach ($devices as $d) {
    $id = $d['_id'];
    $lastInformStr = $d['_lastInform'] ?? null;
    $lastInformTs = $lastInformStr ? strtotime($lastInformStr) : 0;

    // Cek apakah device offline
    if (($now - $lastInformTs) > $offlineThreshold) {
        // Device ini offline. Tambahkan ke daftar offline saat ini.
        $currentOfflineIds[] = $id;

        // Cek apakah device ini BARU saja offline (belum ada di daftar notifikasi)
        if (!in_array($id, $previouslyNotifiedIds)) {
            $newlyOfflineCount++;
            
            $user = clean($d['VirtualParameters']['pppoeUsername'] ?? '', $id);
            $tags = isset($d['_tags']) ? implode(', ', $d['_tags']) : 'N/A';
            $lastInformDate = $lastInformTs > 0 ? date('d M Y, H:i:s', $lastInformTs) : 'Tidak diketahui';

            $msg = "‼️ *DEVICE OFFLINE*\n\n";
            $msg .= "Perangkat terdeteksi offline lebih dari *$offlineMinutes menit*.\n\n";
            $msg .= "👤 *User:* `$user`\n";
            $msg .= "🏷️ *Tags:* `$tags`\n";
            $msg .= "🕒 *Terakhir Online:* `$lastInformDate`\n";
            $msg .= "🆔 *ID:* `$id`";
            
            echo "-> Mengirim notifikasi untuk device baru offline: $user ($id)\n";
            sendTelegram(TELEGRAM_ADMIN_ID, $msg);
            
            sleep(1); // Beri jeda 1 detik agar tidak kena limit API Telegram
        }
    }
}

// --- LOGIKA BARU: Cek device yang kembali online ---
$backOnlineIds = array_diff($previouslyNotifiedIds, $currentOfflineIds);
$backOnlineCount = 0;

if (!empty($backOnlineIds)) {
    // Buat lookup table agar pencarian data device lebih cepat
    $devicesById = array_column($devices, null, '_id');

    foreach ($backOnlineIds as $onlineId) {
        // Cari detail device dari data yang sudah kita ambil di awal
        $dev = $devicesById[$onlineId] ?? null;
        if ($dev) {
            $backOnlineCount++;
            $user = clean($dev['VirtualParameters']['pppoeUsername'] ?? '', $onlineId);
            $tags = isset($dev['_tags']) ? implode(', ', $dev['_tags']) : 'N/A';

            $msg = "✅ *DEVICE KEMBALI ONLINE*\n\n";
            $msg .= "Perangkat yang sebelumnya offline kini telah terhubung kembali.\n\n";
            $msg .= "👤 *User:* `$user`\n";
            $msg .= "🏷️ *Tags:* `$tags`\n";
            $msg .= "🆔 *ID:* `$onlineId`";

            echo "-> Mengirim notifikasi untuk device kembali online: $user ($onlineId)\n";
            sendTelegram(TELEGRAM_ADMIN_ID, $msg);
            sleep(1);
        }
    }
}

// 4. Simpan state baru ke file (hanya device yang saat ini benar-benar offline)
if (file_put_contents($stateFile, json_encode($currentOfflineIds, JSON_PRETTY_PRINT)) === false) {
    die("FATAL ERROR: Gagal menulis ke file state '$stateFile'. Cek izin file.\n");
}

echo "Selesai. Total offline: " . count($currentOfflineIds) . ". | Baru Offline: $newlyOfflineCount. | Kembali Online: $backOnlineCount.\n";

// --- Helper Functions (diambil dari file bot lain) ---
function clean($d, $def = '-') { if ($d === null || $d === '') return $def; if (is_array($d)) return $d['_value'] ?? $d['value'] ?? $def; return $d; }
function sendTelegram($chatId, $message) {
    if (!defined('TELEGRAM_ENABLED') || !TELEGRAM_ENABLED || !defined('TELEGRAM_BOT_TOKEN') || !defined('TELEGRAM_ADMIN_ID')) { echo "Peringatan: Telegram dinonaktifkan atau Token/Admin ID tidak di-set.\n"; return; }
    if (!$chatId) $chatId = TELEGRAM_ADMIN_ID;
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
    $data = ['chat_id' => $chatId, 'text' => $message, 'parse_mode' => 'Markdown'];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url); curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $data); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch); $error = curl_error($ch);
    curl_close($ch);

    if ($error) { echo "   -> Telegram cURL Error: " . $error . "\n"; }
    else { $responseData = json_decode($response, true); if (!$responseData || !$responseData['ok']) { echo "   -> Telegram API Error: " . ($responseData['description'] ?? 'Unknown error') . "\n"; } }
}

?>