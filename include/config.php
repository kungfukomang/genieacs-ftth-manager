<?php
// GenieACS Manager - Configuration File
// Protected by GENIE_ACCESS constant

if (!defined('GENIE_ACCESS')) {
    die('Direct access forbidden.');
}

// --- DYNAMIC SETTINGS FROM JSON ---
$settingsFile = __DIR__ . '/../data/settings.json';
$conf = [];
if (file_exists($settingsFile)) {
    $conf = json_decode(file_get_contents($settingsFile), true) ?? [];
}

// Helper to get configuration with default fallback
function get_conf($key, $default) {
    global $conf;
    return isset($conf[$key]) && $conf[$key] !== '' ? $conf[$key] : $default;
}

// --- GENIEACS SERVER NBI CONNECTION ---
define('GENIE_HOST', get_conf('genie_host', '127.0.0.1')); 
define('GENIE_PORT', get_conf('genie_port', '7557'));
define('GENIE_URL', 'http://' . GENIE_HOST . ':' . GENIE_PORT);
define('GENIE_TIMEOUT', intval(get_conf('genie_timeout', 10)));

// GenieACS NBI Authentication
define('GENIE_USER', get_conf('genie_user', 'admin')); 
define('GENIE_PASS', get_conf('genie_pass', 'admin')); 

// --- WEB DASHBOARD AUTHENTICATION ---
define('DASH_USER', get_conf('dash_user', 'admin'));     
define('DASH_PASS', get_conf('dash_pass', 'admin123'));    

// --- MAP DEFAULT CENTER (HQ / Central Office Coordinates) ---
define('SERVER_LAT', floatval(get_conf('server_lat', -6.2088)));  
define('SERVER_LNG', floatval(get_conf('server_lng', 106.8456))); 
define('MAP_INITIAL_ZOOM', intval(get_conf('map_zoom', 13))); 

// --- TELEGRAM BOT INTEGRATION ---
define('TELEGRAM_ENABLED', filter_var(get_conf('telegram_enabled', false), FILTER_VALIDATE_BOOLEAN));
define('TELEGRAM_BOT_TOKEN', get_conf('telegram_token', '')); 
define('TELEGRAM_ADMIN_ID', get_conf('telegram_admin_id', '')); 
?>

