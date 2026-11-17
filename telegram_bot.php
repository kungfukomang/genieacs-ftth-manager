<?php
// C:\xampp\htdocs\genieacs\telegram_bot.php
// Script Webhook Telegram

// --- PENTING: PENGATURAN ZONA WAKTU ---
// Sesuaikan dengan zona waktu Anda (WIB: Asia/Jakarta, WITA: Asia/Makassar, WIT: Asia/Jayapura)
date_default_timezone_set('Asia/Jakarta');
// ------------------------------------

define('GENIE_ACCESS', true);
require_once __DIR__ . '/include/config.php';

// 1. Tangkap Update dari Telegram
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) exit;

$message = isset($update['message']) ? $update['message'] : null;
if (!$message) exit;

$chatId = $message['chat']['id'];
$text = isset($message['text']) ? $message['text'] : '';

$menu = [
    [['text' => '🔍 Cek Status'], ['text' => '🔄 Reboot Device']],
    [['text' => '📉 Cek Offline'], ['text' => '🆔 My ID']],
    [['text' => '❓ Bantuan']]
];

// 2. Pengecekan ID Pengguna (My ID / Start)
if ($text === '/myid' || $text === '🆔 My ID' || $text === '/start') {
    $myIdMsg = "👋 Halo!\n\n🆔 *Chat ID Anda:* `$chatId`\n\n";
    $adminIds = array_filter(array_map('trim', explode(',', defined('TELEGRAM_ADMIN_ID') ? TELEGRAM_ADMIN_ID : '')));
    if (in_array((string)$chatId, $adminIds)) {
        $myIdMsg .= "✅ *Status:* Akun Anda terdaftar sebagai **Admin**.";
    } else {
        $myIdMsg .= "⚠️ *Status:* Akun Anda **belum terdaftar** sebagai Admin.\nMasukkan ID ini ke menu **Settings ➔ Telegram Admin ID** di Dashboard web.";
    }
    sendTelegramReply($chatId, $myIdMsg, $menu);
    exit;
}

// 3. Verifikasi Hak Akses Admin (Hanya Admin yang dapat eksekusi perintah bot)
$adminIds = array_filter(array_map('trim', explode(',', defined('TELEGRAM_ADMIN_ID') ? TELEGRAM_ADMIN_ID : '')));
if (!empty($adminIds) && !in_array('123456789', $adminIds)) {
    if (!in_array((string)$chatId, $adminIds)) {
        sendTelegramReply($chatId, "⛔ *Akses Ditolak!*\nChat ID Anda (`$chatId`) tidak memiliki hak akses Admin.", $menu);
        exit;
    }
}

// 4. Logika Command
if (strpos($text, '/cek') === 0 || $text === '🔍 Cek Status') {
    if ($text === '🔍 Cek Status') {
        sendTelegramReply($chatId, "Silakan ketik: `/cek [username/sn]`", $menu);
        exit;
    }
    $keyword = trim(substr($text, 4)); // Ambil kata setelah /cek
    
    if (empty($keyword)) {
        sendTelegramReply($chatId, "❌ Format salah.\nKetik: `/cek [username/ip/sn]`", $menu);
        exit;
    }

    sendTelegramReply($chatId, "🔍 Sedang mencari data '$keyword'...", $menu);

    // Cari ke GenieACS (Logic mirip api.php)
    $result = searchGenie($keyword);

    if ($result) {
        $id = $result['_id'];
        $vp = $result['VirtualParameters'] ?? [];
        $igd = $result['InternetGatewayDevice'] ?? $result['Device'] ?? [];
        $tags = $result['_tags'] ?? [];

        // --- MERGE LOCAL TAGS ---
        $jsonFile = __DIR__ . '/data/tags.json';
        if (file_exists($jsonFile)) {
            $localTagsData = json_decode(file_get_contents($jsonFile), true);
            if (is_array($localTagsData) && isset($localTagsData[$id])) {
                $tags[] = $localTagsData[$id];
            }
        }
        $tagDisplay = !empty($tags) ? implode(', ', $tags) : '-';

        // Cek Koordinat di Tags (GPS:lat,lng)
        $lat = null; $lng = null;
        if (!empty($tags)) {
            foreach ($tags as $t) {
                if (preg_match('/(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)/', $t, $m)) {
                    $lat = $m[1]; $lng = $m[2];
                    break;
                }
            }
        }

        // Clean Data (Handle _value object from GenieACS)
        $rx = clean($vp['RXPower'] ?? '', '-');
        $temp = clean($vp['gettemp'] ?? '', '-');
        $uptime = clean($vp['getdeviceuptime'] ?? '', '-');
        $clients = clean($vp['activedevices'] ?? '', '0');
        
        // --- Get SSID ---
        $ssid = clean($vp['SSID'] ?? '', '');
        if (!$ssid || $ssid === '-') {
            $ssid = clean($vp['SSID_ALL'] ?? '', '');
        }
        if (!$ssid || $ssid === '-') {
            $ssid = clean($igd['LANDevice'][1]['WLANConfiguration'][1]['SSID'] ?? '', '-');
        }

        // IP Logic (Scan Recursive)
        $scan = scanAllIps($igd);
        $ip = $scan['ip'];
        if (!$ip) $ip = clean($vp['pppoeIP'] ?? '', '-');

        $status = (floatval($rx) < -27) ? 'JELEK 🔴' : 'BAGUS 🟢';
        
        $reply = "✅ *DEVICE DITEMUKAN*\n";
        $reply .= "--------------------------\n";
        $reply .= "🆔 ID: `" . $id . "`\n";
        $reply .= "👤 User: " . ($scan['user'] ?: clean($vp['pppoeUsername'] ?? '-', '-')) . "\n";
        $reply .= "📡 Sinyal: *$rx dBm* ($status)\n";
        $reply .= "🌐 SSID: `$ssid`\n";
        $reply .= "🌡️ Suhu: " . $temp . " °C\n";
        $reply .= "📱 WiFi Clients: " . $clients . "\n";
        $reply .= "📶 IP: " . $ip . "\n";
        $reply .= "⏱️ Uptime: " . $uptime . "\n";
        $reply .= "🏷️ Tags: " . $tagDisplay . "\n";
        $reply .= "--------------------------\n";
        $reply .= "_Balas otomatis by GenieACS Bot_";
        
        sendTelegramReply($chatId, $reply, $menu);
        
        if ($lat && $lng) {
            sendTelegramLocation($chatId, $lat, $lng);
        }
    } else {
        sendTelegramReply($chatId, "❌ Device dengan kata kunci '$keyword' tidak ditemukan.", $menu);
    }
} elseif (strpos($text, '/reboot') === 0 || $text === '🔄 Reboot Device') {
    if ($text === '🔄 Reboot Device') {
        sendTelegramReply($chatId, "Silakan ketik: `/reboot [username/sn]`", $menu);
        exit;
    }
    $keyword = trim(substr($text, 8)); // Ambil kata setelah /reboot
    
    if (empty($keyword)) {
        sendTelegramReply($chatId, "❌ Format salah.\nKetik: `/reboot [username/ip/sn]`", $menu);
        exit;
    }

    sendTelegramReply($chatId, "🔄 Sedang mencari device '$keyword'...", $menu);
    $result = searchGenie($keyword);

    if ($result) {
        $id = $result['_id'];
        if (rebootGenie($id)) {
            sendTelegramReply($chatId, "✅ Perintah Reboot BERHASIL dikirim ke device `$id`.\nMohon tunggu beberapa saat hingga device online kembali.", $menu);
        } else {
            sendTelegramReply($chatId, "❌ Gagal mengirim perintah reboot ke server.", $menu);
        }
    } else {
        sendTelegramReply($chatId, "❌ Device tidak ditemukan.", $menu);
    }
} elseif (strpos($text, '/wifipass') === 0) {
    $args = trim(substr($text, 9));
    $parts = preg_split('/\s+/', $args);
    if (count($parts) < 2) {
        sendTelegramReply($chatId, "❌ Format salah.\nKetik: `/wifipass [keyword] [pass_baru]`", $menu);
        exit;
    }
    $keyword = $parts[0]; $pass = $parts[1];
    sendTelegramReply($chatId, "⚙️ Memproses ganti Password untuk '$keyword'...", $menu);
    $result = searchGenie($keyword);
    if ($result) {
        if (setWifiGenie($result['_id'], null, $pass)) {
            sendTelegramReply($chatId, "✅ Password berhasil diubah menjadi `$pass`", $menu);
        } else {
            sendTelegramReply($chatId, "❌ Gagal mengubah Password.", $menu);
        }
    } else {
        sendTelegramReply($chatId, "❌ Device tidak ditemukan.", $menu);
    }
} elseif (strpos($text, '/wifi') === 0) {
    $args = trim(substr($text, 6));
    $parts = preg_split('/\s+/', $args);
    if (count($parts) < 2) {
        sendTelegramReply($chatId, "❌ Format salah.\nKetik: `/wifi [keyword] [ssid_baru]`", $menu);
        exit;
    }
    $keyword = $parts[0]; $ssid = $parts[1];
    sendTelegramReply($chatId, "⚙️ Memproses ganti SSID untuk '$keyword'...", $menu);
    $result = searchGenie($keyword);
    if ($result) {
        if (setWifiGenie($result['_id'], $ssid, null)) {
            sendTelegramReply($chatId, "✅ SSID berhasil diubah menjadi `$ssid`", $menu);
        } else {
            sendTelegramReply($chatId, "❌ Gagal mengubah SSID.", $menu);
        }
    } else {
        sendTelegramReply($chatId, "❌ Device tidak ditemukan.", $menu);
    }
} elseif ($text == '/help' || $text === '❓ Bantuan') {
    $helpMsg = "🤖 *PANDUAN BOT GENIEACS*\n\n";
    $helpMsg .= "Berikut adalah daftar perintah yang tersedia:\n\n";
    $helpMsg .= "1️⃣ *Cek Status Device*\n";
    $helpMsg .= "Ketik: `/cek [keyword]`\n";
    $helpMsg .= "Contoh: `/cek user123`\n";
    $helpMsg .= "_Menampilkan sinyal, suhu, uptime, dll._\n\n";
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
    $helpMsg .= "_Menampilkan daftar device yang offline lebih dari 10 menit._\n\n";
    $helpMsg .= "6️⃣ *Cek ID Chat*\n";
    $helpMsg .= "Ketik: `/id`\n";
    $helpMsg .= "_Menampilkan ID Chat untuk config._\n\n";
    $helpMsg .= "7️⃣ *Bantuan*\n";
    $helpMsg .= "Ketik: `/help`\n";
    $helpMsg .= "_Menampilkan pesan ini._";
    
    sendTelegramReply($chatId, $helpMsg, $menu);
} elseif ($text == '/start') {
    sendTelegramReply($chatId, "Halo! Saya GenieACS Bot.\nGunakan tombol menu di bawah untuk akses cepat.", $menu);
} elseif ($text == '/id' || $text === '🆔 My ID') {
    sendTelegramReply($chatId, "🆔 ID Chat ini: `" . $chatId . "`", $menu);
} elseif ($text == '/offline' || $text === '📉 Cek Offline') {
    sendTelegramReply($chatId, "⏳ Sedang mengambil data device offline (lebih dari 10 menit)...", null);

    $offlineDevices = getOfflineDevices(10); // 10 minutes threshold

    if (empty($offlineDevices)) {
        sendTelegramReply($chatId, "✅ Semua device terpantau online.", $menu);
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
        
        sendTelegramReply($chatId, $reply, $menu);
    }
} else {
    sendTelegramReply($chatId, "Perintah tidak dikenal. Gunakan menu di bawah.", $menu);
}

// --- FUNGSI BANTUAN ---

function clean($d, $def = '-') {
    if ($d === null || $d === '') return $def;
    if (is_array($d)) {
        if (isset($d['_value'])) return $d['_value'];
        if (isset($d['value'])) return $d['value'];
        return $def;
    }
    return $d;
}

function scanAllIps($igd) {
    $result = ['ip' => '', 'type' => '', 'user' => ''];
    $wanDevices = $igd['WANDevice'] ?? [];
    if (!is_array($wanDevices)) return $result;

    foreach ($wanDevices as $wKey => $wVal) {
        if ($wKey === '_object') continue;
        $connDevices = $wVal['WANConnectionDevice'] ?? [];
        if (!is_array($connDevices)) continue;

        foreach ($connDevices as $cKey => $cVal) {
            if ($cKey === '_object') continue;
            
            // Check PPPoE
            $ppps = $cVal['WANPPPConnection'] ?? [];
            if (is_array($ppps)) {
                foreach ($ppps as $pKey => $pObj) {
                    $ip = clean($pObj['ExternalIPAddress'] ?? '', '');
                    if ($ip && $ip !== '0.0.0.0' && $ip !== '-') return ['ip' => $ip, 'type' => 'PPPoE', 'user' => clean($pObj['Username'] ?? '', '')];
                }
            }
            // Check IP/Static
            $ips = $cVal['WANIPConnection'] ?? [];
            if (is_array($ips)) {
                foreach ($ips as $iKey => $iObj) {
                    $ip = clean($iObj['ExternalIPAddress'] ?? '', '');
                    if ($ip && $ip !== '0.0.0.0' && $ip !== '-') {
                        if (!$result['ip']) $result = ['ip' => $ip, 'type' => 'Static/DHCP', 'user' => ''];
                    }
                }
            }
        }
    }
    return $result;
}

function sendTelegramReply($chatId, $msg, $keyboard = null) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
    $data = ['chat_id' => $chatId, 'text' => $msg, 'parse_mode' => 'Markdown'];
    if ($keyboard) {
        $data['reply_markup'] = json_encode([
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ]);
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch);
    // curl_close($ch);
}

function sendTelegramLocation($chatId, $lat, $lng) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendLocation";
    $data = ['chat_id' => $chatId, 'latitude' => $lat, 'longitude' => $lng];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch);
    // curl_close($ch);
}

function rebootGenie($id) {
    $endpoint = "/devices/" . rawurlencode($id) . "/tasks?timeout=3000&connection_request";
    $ch = curl_init(GENIE_URL . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['name' => 'reboot']));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_USERPWD, GENIE_USER . ':' . GENIE_PASS);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // curl_close($ch);
    return ($code >= 200 && $code < 300);
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

function searchGenie($k) {
    // 1. Cari di Tag Lokal (data/tags.json)
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

    // 2. Bangun Query
    if ($targetId) {
        $query = ['_id' => $targetId];
    } else {
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
    
    $qJson = json_encode($query);
    $endpoint = "/devices/?query=" . urlencode($qJson) . "&projection=_id,VirtualParameters,InternetGatewayDevice,Device,_tags&limit=1";
    
    $ch = curl_init(GENIE_URL . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, GENIE_USER . ':' . GENIE_PASS);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $res = curl_exec($ch);
    // curl_close($ch);
    
    $data = json_decode($res, true);
    if (is_array($data) && count($data) > 0) return $data[0];
    return null;
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
?>