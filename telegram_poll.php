<?php
// C:\xampp\htdocs\genieacs\telegram_poll.php
// Jalankan file ini melalui CMD: php telegram_poll.php

// --- PENTING: PENGATURAN ZONA WAKTU ---
// Sesuaikan dengan zona waktu Anda (WIB: Asia/Jakarta, WITA: Asia/Makassar, WIT: Asia/Jayapura)
date_default_timezone_set('Asia/Jakarta');
// ------------------------------------

define('GENIE_ACCESS', true);
require_once __DIR__ . '/include/config.php';

// Validasi apakah konstanta sudah terdefinisi
if (!defined('TELEGRAM_BOT_TOKEN')) {
    die("ERROR: Konstanta TELEGRAM_BOT_TOKEN tidak ditemukan. Pastikan include/config.php sudah benar.\n");
}

// Matikan limit waktu eksekusi agar script berjalan selamanya
set_time_limit(0);
ini_set('memory_limit', '512M');

echo "--- TELEGRAM BOT POLLING MODE STARTED ---\n";
echo "Menunggu pesan masuk... (Tekan Ctrl+C untuk berhenti)\n\n";

$offsetFile = __DIR__ . '/data/telegram_offset.txt';
if (!is_dir(__DIR__ . '/data')) mkdir(__DIR__ . '/data', 0777, true);

while (true) {
    $lastOffset = file_exists($offsetFile) ? intval(file_get_contents($offsetFile)) : 0;
    
    // Ambil update dari Telegram (Long Polling)
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/getUpdates?offset=" . ($lastOffset + 1) . "&timeout=30";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Bypass SSL untuk koneksi ke Telegram
    curl_setopt($ch, CURLOPT_TIMEOUT, 40);
    $response = curl_exec($ch);
    // curl_close($ch);
    
    $data = json_decode($response, true);
    
    if (isset($data['ok']) && $data['ok'] && !empty($data['result'])) {
        foreach ($data['result'] as $update) {
            $updateId = $update['update_id'];
            file_put_contents($offsetFile, $updateId); // Simpan offset agar pesan tidak diproses ulang
            
            if (!isset($update['message'])) continue;
            
            $message = $update['message'];
            $chatId = $message['chat']['id'];
            $text = $message['text'] ?? '';
            $user = $message['from']['first_name'] ?? 'User';
            
            echo "[" . date('H:i:s') . "] Pesan dari $user: $text\n";
            
            processCommand($chatId, $text);
        }
    }
    
    // Jeda singkat untuk menghindari overload CPU
    usleep(500000); 
}

function processCommand($chatId, $text) {
    global $menu;
    $menu = [
        [['text' => '🔍 Cek Status'], ['text' => '🔄 Reboot Device']],
        [['text' => '📉 Cek Offline'], ['text' => '🆔 My ID']],
        [['text' => '❓ Bantuan']]
    ];

    // Pengecekan ID Pengguna (My ID / Start)
    if ($text === '/myid' || $text === '🆔 My ID' || $text === '/start') {
        $myIdMsg = "👋 Halo!\n\n🆔 *Chat ID Anda:* `$chatId`\n\n";
        $adminIds = array_filter(array_map('trim', explode(',', defined('TELEGRAM_ADMIN_ID') ? TELEGRAM_ADMIN_ID : '')));
        if (in_array((string)$chatId, $adminIds)) {
            $myIdMsg .= "✅ *Status:* Akun Anda terdaftar sebagai **Admin**.";
        } else {
            $myIdMsg .= "⚠️ *Status:* Akun Anda **belum terdaftar** sebagai Admin.\nMasukkan ID ini ke menu **Settings ➔ Telegram Admin ID** di Dashboard web.";
        }
        sendReply($chatId, $myIdMsg, $menu);
        return;
    }

    // Verifikasi Hak Akses Admin (Hanya Admin yang dapat eksekusi perintah bot)
    $adminIds = array_filter(array_map('trim', explode(',', defined('TELEGRAM_ADMIN_ID') ? TELEGRAM_ADMIN_ID : '')));
    if (!empty($adminIds) && !in_array('123456789', $adminIds)) {
        if (!in_array((string)$chatId, $adminIds)) {
            sendReply($chatId, "⛔ *Akses Ditolak!*\nChat ID Anda (`$chatId`) tidak memiliki hak akses Admin.", $menu);
            return;
        }
    }

    if (strpos($text, '/cek') === 0 || $text === '🔍 Cek Status') {
        if ($text === '🔍 Cek Status') {
            sendReply($chatId, "Silakan ketik: `/cek [username/sn]`", $menu);
            return;
        }
        $keyword = trim(substr($text, 4));
        if (empty($keyword)) {
            sendReply($chatId, "❌ Format salah. Ketik: `/cek [username/ip/sn]`", $menu);
            return;
        }
        
        sendReply($chatId, "🔍 Mencari data '$keyword'...", $menu);
        $result = searchGenie($keyword);
        
        if ($result) {
            $id = $result['_id'];
            $vp = $result['VirtualParameters'] ?? [];
            $igd = $result['InternetGatewayDevice'] ?? $result['Device'] ?? [];
            
            $rx = clean($vp['RXPower'] ?? '', '-');
            $status = (floatval($rx) < -27) ? 'JELEK 🔴' : 'BAGUS 🟢';
            $clients = clean($vp['activedevices'] ?? '', '0');

            // --- Get SSID ---
            $ssid = clean($vp['SSID'] ?? '', '');
            if (!$ssid || $ssid === '-') {
                $ssid = clean($vp['SSID_ALL'] ?? '', '');
            }
            if (!$ssid || $ssid === '-') {
                $ssid = clean($igd['LANDevice'][1]['WLANConfiguration'][1]['SSID'] ?? '', '-');
            }

            $scan = scanAllIps($igd);
            
            $reply = "✅ *DEVICE DITEMUKAN*\n";
            $reply .= "🆔 ID: `" . $id . "`\n";
            $reply .= "👤 User: " . ($scan['user'] ?: clean($vp['pppoeUsername'] ?? '-', '-')) . "\n";
            $reply .= "📡 Sinyal: *$rx dBm* ($status)\n";
            $reply .= "🌐 SSID: `$ssid`\n";
            $reply .= "�️ Suhu: " . clean($vp['gettemp'] ?? '', '-') . " °C\n";
            $reply .= "📱 WiFi Clients: " . $clients . "\n";
            $reply .= "📶 IP: " . ($scan['ip'] ?: clean($vp['pppoeIP'] ?? '', '-')) . "\n";
            sendReply($chatId, $reply, $menu);
        } else {
            sendReply($chatId, "❌ Device tidak ditemukan.", $menu);
        }
    } elseif (strpos($text, '/reboot') === 0 || $text === '🔄 Reboot Device') {
        if ($text === '🔄 Reboot Device') {
            sendReply($chatId, "Silakan ketik: `/reboot [username/sn]`", $menu);
            return;
        }
        $keyword = trim(substr($text, 8));
        $result = searchGenie($keyword);
        if ($result) {
            if (rebootGenie($result['_id'])) {
                sendReply($chatId, "✅ Reboot berhasil dikirim ke `" . $result['_id'] . "`", $menu);
            } else {
                sendReply($chatId, "❌ Gagal mengirim perintah reboot.", $menu);
            }
        } else {
            sendReply($chatId, "❌ Device tidak ditemukan.", $menu);
        }
    } elseif (strpos($text, '/wifipass') === 0) {
        $args = trim(substr($text, 9));
        $parts = preg_split('/\s+/', $args);
        if (count($parts) < 2) {
            sendReply($chatId, "❌ Format salah. Ketik: `/wifipass [keyword] [pass_baru]`", $menu);
            return;
        }
        $keyword = $parts[0]; $pass = $parts[1];
        sendReply($chatId, "⚙️ Memproses ganti Password untuk '$keyword'...", $menu);
        $result = searchGenie($keyword);
        if ($result) {
            if (setWifiGenie($result['_id'], null, $pass)) {
                sendReply($chatId, "✅ Password berhasil diubah menjadi `$pass`", $menu);
            } else {
                sendReply($chatId, "❌ Gagal mengubah Password.", $menu);
            }
        } else {
            sendReply($chatId, "❌ Device tidak ditemukan.", $menu);
        }
    } elseif (strpos($text, '/wifi') === 0) {
        $args = trim(substr($text, 6));
        $parts = preg_split('/\s+/', $args);
        if (count($parts) < 2) {
            sendReply($chatId, "❌ Format salah. Ketik: `/wifi [keyword] [ssid_baru]`", $menu);
            return;
        }
        $keyword = $parts[0]; $ssid = $parts[1];
        sendReply($chatId, "⚙️ Memproses ganti SSID untuk '$keyword'...", $menu);
        $result = searchGenie($keyword);
        if ($result) {
            if (setWifiGenie($result['_id'], $ssid, null)) {
                sendReply($chatId, "✅ SSID berhasil diubah menjadi `$ssid`", $menu);
            } else {
                sendReply($chatId, "❌ Gagal mengubah SSID.", $menu);
            }
        } else {
            sendReply($chatId, "❌ Device tidak ditemukan.", $menu);
        }
    } elseif ($text == '/offline' || $text === '📉 Cek Offline') {
        sendReply($chatId, "⏳ Sedang mengambil data device offline (lebih dari 10 menit)...", null);

        $offlineDevices = getOfflineDevices(10); // 10 minutes threshold

        if (empty($offlineDevices)) {
            sendReply($chatId, "✅ Semua device terpantau online.", $menu);
        } else {
            $count = count($offlineDevices);
            $reply = "📉 *DITEMUKAN $count DEVICE OFFLINE*\n";
            $reply .= "--------------------------\n";
            
            $limit = 15; // Batasi jumlah yang ditampilkan agar tidak spam
            $i = 0;
            foreach ($offlineDevices as $dev) {
                if ($i >= $limit) {
                    $reply .= "\n... dan " . ($count - $limit) . " device lainnya.";
                    break;
                }
                $id = $dev['_id'];
                $user = clean($dev['VirtualParameters']['pppoeUsername'] ?? '', $id);
                $lastInform = $dev['_lastInform'] ? date('d/m/y H:i', strtotime($dev['_lastInform'])) : 'N/A';
                
                $reply .= "👤 *$user*\n";
                $reply .= "   └─ Terakhir online: `$lastInform`\n";
                $i++;
            }
            
            sendReply($chatId, $reply, $menu);
        }
    } elseif ($text == '/id' || $text === '🆔 My ID') {
        sendReply($chatId, "🆔 ID Chat Anda: `" . $chatId . "`", $menu);
    } elseif ($text == '/start' || $text == '/help' || $text === '❓ Bantuan') {
        $helpMsg = "🤖 *PANDUAN BOT GENIEACS (POLLING)*\n\n";
        $helpMsg .= "Gunakan tombol menu di bawah atau ketik perintah manual:\n\n";
        $helpMsg .= "1️⃣ *Cek Status Device*\n";
        $helpMsg .= "Ketik: `/cek [keyword]`\n";
        $helpMsg .= "Contoh: `/cek user123`\n";
        $helpMsg .= "_Menampilkan sinyal, suhu, IP, dll._\n\n";
        $helpMsg .= "2️⃣ *Reboot Device*\n";
        $helpMsg .= "Ketik: `/reboot [keyword]`\n";
        $helpMsg .= "Contoh: `/reboot user123`\n";
        $helpMsg .= "_Merestart modem jarak jauh._\n\n";
        $helpMsg .= "3️⃣ *Ganti WiFi SSID*\n";
        $helpMsg .= "Ketik: `/wifi [keyword] [ssid_baru]`\n\n";
        $helpMsg .= "4️⃣ *Ganti WiFi Password*\n";
        $helpMsg .= "Ketik: `/wifipass [keyword] [pass_baru]`\n\n";
        $helpMsg .= "5️⃣ *Cek Device Offline*\n";
        $helpMsg .= "Ketik: `/offline`\n";
        $helpMsg .= "_Menampilkan daftar device yang offline._\n\n";
        $helpMsg .= "6️⃣ *Cek ID Chat*\n";
        $helpMsg .= "Ketik: `/id`\n";
        $helpMsg .= "_Menampilkan ID Chat Anda._\n\n";
        $helpMsg .= "💡 *Tips:* Klik tombol menu di bawah untuk akses cepat.";
        
        sendReply($chatId, $helpMsg, $menu);
    } else {
        sendReply($chatId, "Perintah tidak dikenal. Gunakan menu di bawah.", $menu);
    }
}

function clean($d, $def = '-') {
    if ($d === null || $d === '') return $def;
    if (is_array($d)) return $d['_value'] ?? $d['value'] ?? $def;
    return $d;
}

function scanAllIps($igd) {
    $result = ['ip' => '', 'type' => '', 'user' => ''];
    $wanDevices = $igd['WANDevice'] ?? [];
    if (!is_array($wanDevices)) return $result;
    foreach ($wanDevices as $wKey => $wVal) {
        if ($wKey === '_object') continue;
        $connDevices = $wVal['WANConnectionDevice'] ?? [];
        foreach ($connDevices as $cKey => $cVal) {
            if ($cKey === '_object') continue;
            $ppps = $cVal['WANPPPConnection'] ?? [];
            if (is_array($ppps)) {
                foreach ($ppps as $pKey => $pObj) {
                    $ip = clean($pObj['ExternalIPAddress'] ?? '', '');
                    if ($ip && $ip !== '0.0.0.0' && $ip !== '-') 
                        return ['ip' => $ip, 'type' => 'PPPoE', 'user' => clean($pObj['Username'] ?? '', '')];
                }
            }
        }
    }
    return $result;
}

function sendReply($chatId, $msg, $keyboard = null) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
    $data = ['chat_id' => $chatId, 'text' => $msg, 'parse_mode' => 'Markdown'];
    if ($keyboard) {
        $data['reply_markup'] = json_encode([
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ]);
    }
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch);
    // curl_close($ch);
}

function searchGenie($k) {
    // 1. Cari di Tag Lokal (data/tags.json) terlebih dahulu
    $jsonFile = __DIR__ . '/data/tags.json';
    $targetId = null;
    if (file_exists($jsonFile)) {
        $localTags = json_decode(file_get_contents($jsonFile), true);
        if (is_array($localTags)) {
            foreach ($localTags as $id => $tag) {
                if (stripos($tag, $k) !== false) {
                    $targetId = $id;
                    break;
                }
            }
        }
    }

    // 2. Bangun Query GenieACS
    if ($targetId) {
        // Jika ditemukan di tag lokal, cari berdasarkan ID spesifik
        $query = ['_id' => $targetId];
    } else {
        // Jika tidak, cari menggunakan regex di berbagai field
        $regex = ['$regex' => $k, '$options' => 'i'];
        $query = ['$or' => [
            ['_id' => $regex],
            ['_tags' => $regex],
            ['VirtualParameters.pppoeUsername' => $regex],
            ['VirtualParameters.pppoeIP' => $regex],
            ['VirtualParameters.SSID' => $regex],
            ['InternetGatewayDevice.DeviceInfo.SerialNumber' => $regex],
            ['Device.DeviceInfo.SerialNumber' => $regex]
        ]];
    }

    $endpoint = "/devices/?query=" . urlencode(json_encode($query)) . "&projection=_id,VirtualParameters,InternetGatewayDevice,Device,_tags&limit=1";
    $ch = curl_init(GENIE_URL . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, GENIE_USER . ':' . GENIE_PASS);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $res = curl_exec($ch);
    // curl_close($ch);
    $data = json_decode($res, true);
    return (is_array($data) && count($data) > 0) ? $data[0] : null;
}

function rebootGenie($id) {
    $endpoint = "/devices/" . rawurlencode($id) . "/tasks?connection_request";
    $ch = curl_init(GENIE_URL . $endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['name' => 'reboot']));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_USERPWD, GENIE_USER . ':' . GENIE_PASS);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // curl_close($ch);
    return ($code >= 200 && $code < 300);
}

function getOfflineDevices($minutes = 10) {
    $threshold = time() - ($minutes * 60);
    // Format tanggal yang diterima GenieACS/MongoDB
    $thresholdDate = date('Y-m-d\TH:i:s.v\Z', $threshold);

    // Query untuk device yang _lastInform nya lebih kecil dari (less than) threshold
    $query = ['_lastInform' => ['$lt' => $thresholdDate]];
    
    $projection = '_id,_lastInform,VirtualParameters.pppoeUsername';
    
    $qJson = json_encode($query);
    // Urutkan dari yang paling lama offline (ascending) dan batasi hasilnya
    $sort = json_encode(['_lastInform' => 1]);
    $endpoint = "/devices/?query=" . urlencode($qJson) . "&projection=" . urlencode($projection) . "&sort=" . urlencode($sort) . "&limit=200";
    
    $ch = curl_init(GENIE_URL . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, GENIE_USER . ':' . GENIE_PASS);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $res = curl_exec($ch);
    $data = json_decode($res, true);
    return is_array($data) ? $data : [];
}

function setWifiGenie($id, $ssid = null, $pass = null) {
    $params = [];
    
    // Deteksi Root Path
    $ch = curl_init(GENIE_URL . "/devices/?query=" . urlencode(json_encode(['_id' => $id])) . "&projection=Device");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, GENIE_USER . ':' . GENIE_PASS);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $checkRoot = json_decode(curl_exec($ch), true);
    // curl_close($ch);

    $root = (is_array($checkRoot) && count($checkRoot) > 0 && isset($checkRoot[0]['Device'])) ? "Device" : "InternetGatewayDevice";

    if ($root === "InternetGatewayDevice") {
        if ($ssid !== null) $params[] = ['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID', (string)$ssid, 'xsd:string'];
        if ($pass !== null) {
            $ch = curl_init(GENIE_URL . "/devices/?query=" . urlencode(json_encode(['_id' => $id])) . "&projection=InternetGatewayDevice.LANDevice.1.WLANConfiguration.1");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERPWD, GENIE_USER . ':' . GENIE_PASS);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $devData = json_decode(curl_exec($ch), true);
            // curl_close($ch);

            $passParam = 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey';
            if (is_array($devData) && count($devData) > 0) {
                $wlan = $devData[0]['InternetGatewayDevice']['LANDevice'][1]['WLANConfiguration'][1] ?? [];
                if (isset($wlan['KeyPassphrase'])) {
                    $passParam = 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.KeyPassphrase';
                } elseif (isset($wlan['PreSharedKey']) && is_array($wlan['PreSharedKey']) && isset($wlan['PreSharedKey']['1'])) {
                    $passParam = 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.PreSharedKey';
                }
            }
            $params[] = [$passParam, (string)$pass, 'xsd:string'];
        }
    } else {
        if ($ssid !== null) $params[] = ['Device.WiFi.SSID.1.SSID', (string)$ssid, 'xsd:string'];
        if ($pass !== null) $params[] = ['Device.WiFi.AccessPoint.1.Security.KeyPassphrase', (string)$pass, 'xsd:string'];
    }

    if (empty($params)) return false;

    $endpoint = "/devices/" . rawurlencode($id) . "/tasks?connection_request";
    $ch = curl_init(GENIE_URL . $endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['name' => 'setParameterValues', 'parameterValues' => $params]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_USERPWD, GENIE_USER . ':' . GENIE_PASS);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // curl_close($ch);
    return ($code >= 200 && $code < 300);
}