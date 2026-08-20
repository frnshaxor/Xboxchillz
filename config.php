<?php
declare(strict_types=1);

// --------- Security headers ---------
$isHttps = !empty($_SERVER['HTTPS']) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()');
header('Cross-Origin-Opener-Policy: same-origin');
if ($isHttps) header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
header("Content-Security-Policy: default-src 'self'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'; object-src 'none'; img-src 'self' data: blob: https://api.qrserver.com https://cdn.plyr.io https://cdn.jsdelivr.net; media-src 'self' blob:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com data:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://unpkg.com https://cdn.tailwindcss.com https://cdn.jsdelivr.net https://app.sandbox.midtrans.com https://app.midtrans.com; frame-src 'self' https://app.sandbox.midtrans.com https://app.midtrans.com; connect-src 'self'");

// --------- Session (hardened) ---------
$cookieParams = [
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Strict',
];
session_name('ARSIP_SID');
session_set_cookie_params($cookieParams);
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// Session-hijack guard: bind to a per-session token derived from UA
if (empty($_SESSION['_ua_seed'])) { $_SESSION['_ua_seed'] = bin2hex(random_bytes(8)); }
$_ua_now = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . $_SESSION['_ua_seed']);
if (!empty($_SESSION['_ua_bind']) && !hash_equals($_SESSION['_ua_bind'], $_ua_now)) {
    session_destroy(); http_response_code(440); exit('Sesi kedaluwarsa, silakan masuk ulang.');
}
$_SESSION['_ua_bind'] = $_ua_now;

// --------- DB ---------
$db = new mysqli(getenv('DB_HOST') ?: '127.0.0.1',
                 getenv('DB_USER') ?: 'arsip',
                 getenv('DB_PASS') ?: '',
                 getenv('DB_NAME') ?: 'arsip_layar');
if ($db->connect_error) { http_response_code(500); exit('Database belum siap.'); }
$db->set_charset('utf8mb4');
mysqli_report(MYSQLI_REPORT_OFF);

const APP_ROOT   = __DIR__;
const MEDIA_ROOT = __DIR__ . '/media';
const BACKUP_DIR = __DIR__ . '/storage/backups';
const CACHE_DIR  = __DIR__ . '/storage/cache';
@is_dir(BACKUP_DIR) or @mkdir(BACKUP_DIR, 0750, true);
@is_dir(CACHE_DIR)  or @mkdir(CACHE_DIR, 0750, true);

// --------- Helpers ---------
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function csrf(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24)); return $_SESSION['csrf']; }
function check_csrf(): void { if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(419); exit('Token tidak valid.'); } }
function admin(): bool { return !empty($_SESSION['admin_id']); }
function need_admin(): void { if (!admin()) { header('Location: ?page=login'); exit; } }
function go(string $url): never { header('Location: ' . $url); exit; }
function client_ip(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return substr($ip, 0, 64);
}

function setting(mysqli $db, string $key, string $fallback = ''): string {
    static $cache = [];
    if (array_key_exists($key, $cache)) return $cache[$key];
    $s = $db->prepare('SELECT value FROM settings WHERE name=?'); $s->bind_param('s', $key); $s->execute();
    $r = $s->get_result()->fetch_assoc();
    return $cache[$key] = ($r['value'] ?? $fallback);
}
function set_setting(mysqli $db, string $key, string $value): void {
    $s = $db->prepare('INSERT INTO settings(name,value) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)');
    $s->bind_param('ss', $key, $value); $s->execute();
}
function log_activity(mysqli $db, ?int $admin_id, string $action, string $detail = ''): void {
    $ip = client_ip();
    $s = $db->prepare('INSERT INTO activity_log(admin_id,action,detail,ip) VALUES(?,?,?,?)');
    $s->bind_param('isss', $admin_id, $action, $detail, $ip); $s->execute();
}
function log_login_attempt(mysqli $db, string $email, bool $ok, string $reason = ''): void {
    $ip = client_ip(); $s_ok = $ok ? 1 : 0;
    $s = $db->prepare('INSERT INTO login_attempts(ip,email,success,reason) VALUES(?,?,?,?)');
    $s->bind_param('ssis', $ip, $email, $s_ok, $reason); $s->execute();
}
function recent_failed_logins(mysqli $db, string $ip, int $minutes = 15): int {
    $s = $db->prepare('SELECT COUNT(*) c FROM login_attempts WHERE ip=? AND success=0 AND created_at > (NOW() - INTERVAL ? MINUTE)');
    $s->bind_param('si', $ip, $minutes); $s->execute();
    return (int)($s->get_result()->fetch_assoc()['c'] ?? 0);
}

// --------- Argon2id password helpers ---------
function password_new(string $plain): string {
    $opts = ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2];
    return password_hash($plain, PASSWORD_ARGON2ID, $opts);
}
function password_needs_upgrade(string $hash): bool {
    return password_needs_rehash($hash, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2]);
}

// --------- TOTP (RFC 6238) minimal impl ---------
function base32_encode(string $bin): string {
    $alph = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $out = ''; $buf = 0; $bits = 0;
    for ($i = 0, $n = strlen($bin); $i < $n; $i++) {
        $buf = ($buf << 8) | ord($bin[$i]); $bits += 8;
        while ($bits >= 5) { $bits -= 5; $out .= $alph[($buf >> $bits) & 31]; }
    }
    if ($bits > 0) $out .= $alph[($buf << (5 - $bits)) & 31];
    return $out;
}
function base32_decode(string $s): string {
    $alph = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $s = strtoupper(preg_replace('/[^A-Z2-7]/', '', $s));
    $out = ''; $buf = 0; $bits = 0;
    for ($i = 0, $n = strlen($s); $i < $n; $i++) {
        $v = strpos($alph, $s[$i]); if ($v === false) continue;
        $buf = ($buf << 5) | $v; $bits += 5;
        if ($bits >= 8) { $bits -= 8; $out .= chr(($buf >> $bits) & 0xFF); }
    }
    return $out;
}
function totp_code(string $secretB32, ?int $t = null, int $period = 30, int $digits = 6): string {
    $t = $t ?? time(); $counter = intdiv($t, $period);
    $bin = pack('N*', 0) . pack('N*', $counter);
    $hash = hash_hmac('sha1', $bin, base32_decode($secretB32), true);
    $off = ord($hash[19]) & 0x0F;
    $code = ((ord($hash[$off]) & 0x7F) << 24) | ((ord($hash[$off + 1]) & 0xFF) << 16)
          | ((ord($hash[$off + 2]) & 0xFF) << 8) | (ord($hash[$off + 3]) & 0xFF);
    return str_pad((string)($code % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
}
function totp_verify(string $secretB32, string $code, int $window = 1): bool {
    $code = preg_replace('/\D/', '', $code); if (strlen($code) !== 6) return false;
    for ($i = -$window; $i <= $window; $i++) {
        if (hash_equals(totp_code($secretB32, time() + $i * 30), $code)) return true;
    }
    return false;
}

// --------- Maintenance mode ---------
function maintenance_guard(mysqli $db): void {
    if (setting($db, 'maintenance_mode', '0') !== '1') return;
    if (admin()) return; // admins bypass
    $page = $_GET['page'] ?? '';
    if ($page === 'login' || $page === 'logout' || $page === 'midtrans-notify') return;
    http_response_code(503);
    header('Retry-After: 300');
    echo '<!doctype html><meta charset=utf-8><title>Sedang dirapikan</title><body style="background:#111;color:#e8dfcf;font:16px system-ui;padding:12vh 8vw"><h1 style="font:600 56px \'Cormorant Garamond\',serif">Sedang dirapikan.</h1><p>Situs sementara dalam mode perawatan. Silakan kembali beberapa saat lagi.</p></body>';
    exit;
}

// --------- Access Token helpers ---------
function has_access(): bool {
    return !empty($_SESSION['access_granted']);
}
function grant_access(): void {
    $_SESSION['access_granted'] = true;
}
function revoke_access(): void {
    unset($_SESSION['access_granted']);
}
function generate_token(int $length = 12): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // ambiguous chars removed (I, O, 0, 1)
    $token = '';
    for ($i = 0; $i < $length; $i++) {
        $token .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $token;
}

/** Generate an internal URL for media that must pass the session access check. */
function protected_media_url(string $path): string {
    $relative = ltrim(preg_replace('#^media/#', '', $path), '/');
    return '/protected-media/' . implode('/', array_map('rawurlencode', explode('/', $relative)));
}
/** Posters remain public metadata, while video files and HLS assets require access. */
function poster_url(string $path): string {
    return '?page=poster&path=' . rawurlencode(ltrim(preg_replace('#^media/#', '', $path), '/'));
}
function midtrans_endpoint(string $mode): string {
    return $mode === 'production'
        ? 'https://app.midtrans.com/snap/v1/transactions'
        : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
}
function midtrans_snap_url(string $mode): string {
    return $mode === 'production'
        ? 'https://app.midtrans.com/snap/snap.js'
        : 'https://app.sandbox.midtrans.com/snap/snap.js';
}
