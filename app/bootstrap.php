<?php

declare(strict_types=1);

/**
 * Bootstrap — load all foundations before routing begins.
 * This file is included by the front controller (public/index.php).
 */

// ─── Paths ───
define('APP_ROOT', dirname(__DIR__));
define('PUBLIC_DIR', __DIR__ . '/public');
define('MEDIA_ROOT', APP_ROOT . '/media');
define('BACKUP_DIR', APP_ROOT . '/storage/backups');
define('CACHE_DIR', APP_ROOT . '/storage/cache');
@is_dir(BACKUP_DIR) or @mkdir(BACKUP_DIR, 0750, true);
@is_dir(CACHE_DIR) or @mkdir(CACHE_DIR, 0750, true);
define('UPLOADS_DIR', APP_ROOT . '/storage/uploads');
@is_dir(UPLOADS_DIR) or @mkdir(UPLOADS_DIR, 0750, true);

// ─── Helpers ───
require_once APP_ROOT . '/app/helpers.php';

// ─── Database Connection ───
require_once APP_ROOT . '/app/Database/Connection.php';

// ─── Models ───
foreach (glob(APP_ROOT . '/app/Models/*.php') as $file) {
    require_once $file;
}

// ─── Services ───
foreach (glob(APP_ROOT . '/app/Services/*.php') as $file) {
    require_once $file;
}

// ─── HTTP Helpers ───
require_once APP_ROOT . '/app/Http/Request.php';
require_once APP_ROOT . '/app/Http/Response.php';

// ─── Middleware ───
foreach (glob(APP_ROOT . '/app/Middleware/*.php') as $file) {
    require_once $file;
}

// ─── Controllers ───
foreach (glob(APP_ROOT . '/controllers/*.php') as $file) {
    require_once $file;
}

// ─── Session (hardened) ───
$isHttps = !empty($_SERVER['HTTPS']) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

$cookieParams = [
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Strict',
];
session_name('ARSIP_SID');
session_set_cookie_params($cookieParams);
// Skip session for CLI (job runner, migrations) — no web session needed
if (php_sapi_name() !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Session-hijack guard
if (empty($_SESSION['_ua_seed'])) {
    $_SESSION['_ua_seed'] = bin2hex(random_bytes(8));
}
$_ua_now = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . $_SESSION['_ua_seed']);
if (!empty($_SESSION['_ua_bind']) && !hash_equals($_SESSION['_ua_bind'], $_ua_now)) {
    session_destroy();
    http_response_code(440);
    exit('Sesi kedaluwarsa, silakan masuk ulang.');
}
$_SESSION['_ua_bind'] = $_ua_now;

// Session idle timeout (admin: 30 min)
$_SESSION['_last_activity'] = time();
if (!empty($_SESSION['admin_id'])) {
    $idle_limit = 30 * 60;
    if (isset($_SESSION['_idle_ts']) && (time() - (int) $_SESSION['_idle_ts']) > $idle_limit) {
        $_SESSION = [];
        session_destroy();
        if (php_sapi_name() !== 'cli') {
            header('Location: ?page=login&timeout=1');
            exit;
        }
    }
    $_SESSION['_idle_ts'] = time();
}

// ─── Request ID (for tracing) ───
$_SERVER['REQUEST_ID'] = bin2hex(random_bytes(8));
header('X-Request-ID: ' . $_SERVER['REQUEST_ID']);

// ─── Security Headers ───
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb()');
header('Cross-Origin-Opener-Policy: same-origin');
if ($isHttps) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
header("Content-Security-Policy: default-src 'self'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'; object-src 'none'; img-src 'self' data: blob: https://api.qrserver.com https://cdn.plyr.io https://cdn.jsdelivr.net; media-src 'self' blob:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com data:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://unpkg.com https://cdn.tailwindcss.com https://cdn.jsdelivr.net https://app.sandbox.midtrans.com https://app.midtrans.com; frame-src 'self' https://app.sandbox.midtrans.com https://app.midtrans.com; connect-src 'self'");

// ─── Database singleton ───
$conn = Connection::getInstance();
$db = $conn->db(); // backward-compat: $db used by old helper functions

// ─── CSRF double-submit cookie ───
// Set on every request so JS can read it for API calls
if (empty($_COOKIE['csrf_double']) || $_COOKIE['csrf_double'] !== ($_SESSION['csrf'] ?? '')) {
    CsrfMiddleware::setCookie();
}

// ─── Timeout message ───
if (isset($_GET['timeout']) && $_GET['timeout'] === '1') {
    http_response_code(440);
}

// ─── Constants for routes ───
const ROUTES_WEB = APP_ROOT . '/routes/web.php';
const ROUTES_API = APP_ROOT . '/routes/api.php';
const ROUTES_WEBHOOK = APP_ROOT . '/routes/webhook.php';
const VIEWS_DIR = APP_ROOT . '/views';
