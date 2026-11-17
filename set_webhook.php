<?php
// C:\xampp\htdocs\genieacs\set_webhook.php
// Akses file ini via browser untuk mengaktifkan Bot di server baru
// Contoh: https://domain-anda.com/set_webhook.php

session_start();
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    http_response_code(401);
    die("Akses ditolak: Silakan login ke dashboard terlebih dahulu.");
}

define('GENIE_ACCESS', true);
require_once 'include/config.php';

// 1. Deteksi URL Server Baru Otomatis
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$domain = $_SERVER['HTTP_HOST'];
$path = dirname($_SERVER['REQUEST_URI']);
$webhookUrl = $protocol . "://" . $domain . $path . "/telegram_bot.php";

echo "<h3>Telegram Webhook Setup</h3>";
echo "Token: " . substr(TELEGRAM_BOT_TOKEN, 0, 10) . ".........<br>";
echo "Target URL: <b>" . $webhookUrl . "</b><br><br>";

if ($protocol !== 'https') {
    echo "<div style='color:red'>⚠️ PERINGATAN: Telegram Webhook WAJIB menggunakan HTTPS (SSL). Pastikan SSL aktif di HestiaCP.</div><br>";
}

// 2. Kirim Perintah ke Telegram
$apiUrl = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/setWebhook?url=" . urlencode($webhookUrl);
$response = @file_get_contents($apiUrl);

if ($response) {
    $json = json_decode($response, true);
    if ($json['ok']) {
        echo "<div style='color:green; font-weight:bold'>✅ SUKSES! Webhook berhasil di-update ke server ini.</div>";
        echo "<p>Silakan coba chat '/start' atau '/cek' ke bot Anda sekarang.</p>";
    } else {
        echo "<div style='color:red'>❌ GAGAL: " . $json['description'] . "</div>";
    }
} else {
    echo "<div style='color:red'>❌ GAGAL MENGHUBUNGI TELEGRAM. Cek koneksi internet server atau firewall.</div>";
}
?>