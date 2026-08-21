<?php

declare(strict_types=1);

/**
 * Core helpers — pure functions, no class dependencies.
 * Extracted from the original config.php for reuse across the app.
 */

// ─── HTML Escaping ───
function e(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

// ─── CSRF ───
function csrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }

    return $_SESSION['csrf'];
}

function check_csrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419);
        exit('Token tidak valid.');
    }
}

// ─── Auth helpers ───
function admin(): bool
{
    return !empty($_SESSION['admin_id']);
}
function need_admin(): void
{
    if (!admin()) {
        header('Location: ?page=login');
        exit;
    }
}

// ─── Redirect ───
function go(string $url): never
{
    header('Location: ' . $url);
    exit;
}

// ─── Client IP ───
function client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    return substr($ip, 0, 64);
}

// ─── Settings (cached in-memory) ───
// Settings cache — shared between setting() and set_setting()
$_settings_cache = [];
$_settings_cache_loaded = [];

function setting(mysqli $db, string $key, string $fallback = ''): string
{
    global $_settings_cache, $_settings_cache_loaded;
    if (array_key_exists($key, $_settings_cache)) {
        return $_settings_cache[$key];
    }
    $s = $db->prepare('SELECT value FROM settings WHERE name=?');
    $s->bind_param('s', $key);
    $s->execute();
    $r = $s->get_result()->fetch_assoc();
    $_settings_cache_loaded[$key] = true;

    return $_settings_cache[$key] = ($r['value'] ?? $fallback);
}

function set_setting(mysqli $db, string $key, string $value): void
{
    global $_settings_cache;
    $s = $db->prepare('INSERT INTO settings(name,value) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)');
    $s->bind_param('ss', $key, $value);
    $s->execute();
    // Invalidate in-memory cache so subsequent setting() calls return fresh value
    $_settings_cache[$key] = $value;
}

// ─── Activity & Login Logging ───
function log_activity(mysqli $db, ?int $admin_id, string $action, string $detail = ''): void
{
    $ip = client_ip();
    $s = $db->prepare('INSERT INTO activity_log(admin_id,action,detail,ip) VALUES(?,?,?,?)');
    $s->bind_param('isss', $admin_id, $action, $detail, $ip);
    $s->execute();
    // Structured log to PHP error log for monitoring
    error_log(json_encode([
        'ts' => date('c'),
        'level' => 'audit',
        'action' => $action,
        'admin' => $admin_id,
        'detail' => $detail,
        'ip' => $ip,
        'req_id' => $_SERVER['REQUEST_ID'] ?? '',
    ], JSON_UNESCAPED_UNICODE));
}

/** Log activity with before/after diff for audit trail. */
function log_activity_diff(mysqli $db, ?int $admin_id, string $action, string $detail, array $old = [], array $new = []): void
{
    $ip = client_ip();
    $oldJson = !empty($old) ? json_encode($old, JSON_UNESCAPED_UNICODE) : null;
    $newJson = !empty($new) ? json_encode($new, JSON_UNESCAPED_UNICODE) : null;
    $s = $db->prepare('INSERT INTO activity_log(admin_id,action,detail,ip,old_values,new_values) VALUES(?,?,?,?,?,?)');
    $s->bind_param('isssss', $admin_id, $action, $detail, $ip, $oldJson, $newJson);
    $s->execute();
}

function log_login_attempt(mysqli $db, string $email, bool $ok, string $reason = ''): void
{
    $ip = client_ip();
    $s_ok = $ok ? 1 : 0;
    $s = $db->prepare('INSERT INTO login_attempts(ip,email,success,reason) VALUES(?,?,?,?)');
    $s->bind_param('ssis', $ip, $email, $s_ok, $reason);
    $s->execute();
}

function recent_failed_logins(mysqli $db, string $ip, int $minutes = 15): int
{
    $s = $db->prepare('SELECT COUNT(*) c FROM login_attempts WHERE ip=? AND success=0 AND created_at > (NOW() - INTERVAL ? MINUTE)');
    $s->bind_param('si', $ip, $minutes);
    $s->execute();

    return (int) ($s->get_result()->fetch_assoc()['c'] ?? 0);
}

// ─── Argon2id Password Hashing ───
function password_new(string $plain): string
{
    $opts = ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2];

    return password_hash($plain, PASSWORD_ARGON2ID, $opts);
}

function password_needs_upgrade(string $hash): bool
{
    return password_needs_rehash($hash, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2]);
}

// ─── TOTP (RFC 6238) ───
function base32_encode(string $bin): string
{
    $alph = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $out = '';
    $buf = 0;
    $bits = 0;
    for ($i = 0, $n = strlen($bin); $i < $n; $i++) {
        $buf = ($buf << 8) | ord($bin[$i]);
        $bits += 8;
        while ($bits >= 5) {
            $bits -= 5;
            $out .= $alph[($buf >> $bits) & 31];
        }
    }
    if ($bits > 0) {
        $out .= $alph[($buf << (5 - $bits)) & 31];
    }

    return $out;
}

function base32_decode(string $s): string
{
    $alph = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $s = strtoupper(preg_replace('/[^A-Z2-7]/', '', $s));
    $out = '';
    $buf = 0;
    $bits = 0;
    for ($i = 0, $n = strlen($s); $i < $n; $i++) {
        $v = strpos($alph, $s[$i]);
        if ($v === false) {
            continue;
        }
        $buf = ($buf << 5) | $v;
        $bits += 5;
        if ($bits >= 8) {
            $bits -= 8;
            $out .= chr(($buf >> $bits) & 0xFF);
        }
    }

    return $out;
}

function totp_code(string $secretB32, ?int $t = null, int $period = 30, int $digits = 6): string
{
    $t = $t ?? time();
    $counter = intdiv($t, $period);
    $bin = pack('N*', 0) . pack('N*', $counter);
    $hash = hash_hmac('sha1', $bin, base32_decode($secretB32), true);
    $off = ord($hash[19]) & 0x0F;
    $code = ((ord($hash[$off]) & 0x7F) << 24) | ((ord($hash[$off + 1]) & 0xFF) << 16)
          | ((ord($hash[$off + 2]) & 0xFF) << 8) | (ord($hash[$off + 3]) & 0xFF);

    return str_pad((string) ($code % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
}

function totp_verify(string $secretB32, string $code, int $window = 1): bool
{
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) !== 6) {
        return false;
    }
    for ($i = -$window; $i <= $window; $i++) {
        if (hash_equals(totp_code($secretB32, time() + $i * 30), $code)) {
            return true;
        }
    }

    return false;
}

// ─── Token Generation ───
function generate_token(int $length = 12): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $token = '';
    for ($i = 0; $i < $length; $i++) {
        $token .= $chars[random_int(0, strlen($chars) - 1)];
    }

    return $token;
}

// ─── Access Token Session Helpers ───
function has_access(): bool
{
    return !empty($_SESSION['access_granted']);
}
function grant_access(): void
{
    $_SESSION['access_granted'] = true;
}
/** Store token metadata in session so the nav can display owner info. */
function grant_access_with_token(mysqli $db, int $tokenId): void
{
    grant_access();
    $s = $db->prepare('SELECT label, created_at, expires_at FROM access_tokens WHERE id=?');
    $s->bind_param('i', $tokenId);
    $s->execute();
    $token = $s->get_result()->fetch_assoc();
    if ($token) {
        $_SESSION['access_token_id'] = $tokenId;
        $_SESSION['access_token_label'] = $token['label'];
        $_SESSION['access_token_created_at'] = $token['created_at'];
        $_SESSION['access_token_expires_at'] = $token['expires_at'];
    }
}
function revoke_access(): void
{
    unset($_SESSION['access_granted']);
    unset($_SESSION['access_token_id']);
    unset($_SESSION['access_token_label']);
    unset($_SESSION['access_token_created_at']);
    unset($_SESSION['access_token_expires_at']);
}

// ─── URL Generators ───
function protected_media_url(string $path): string
{
    $relative = ltrim(preg_replace('#^media/#', '', $path), '/');

    return '/protected-media/' . implode('/', array_map('rawurlencode', explode('/', $relative)));
}

function poster_url(string $path): string
{
    return '?page=poster&path=' . rawurlencode(ltrim(preg_replace('#^media/#', '', $path), '/'));
}

function preview_url(string $path): string
{
    $relative = ltrim(preg_replace('#^media/#', '', $path), '/');
    $previewPath = preg_replace('#/source\.mp4$#', '/preview.mp4', $relative);

    return '?page=preview&path=' . rawurlencode($previewPath);
}

// ─── Midtrans Endpoints ───
function midtrans_endpoint(string $mode): string
{
    return $mode === 'production'
        ? 'https://app.midtrans.com/snap/v1/transactions'
        : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
}

function midtrans_snap_url(string $mode): string
{
    return $mode === 'production'
        ? 'https://app.midtrans.com/snap/snap.js'
        : 'https://app.sandbox.midtrans.com/snap/snap.js';
}

// ─── Maintenance Guard ───
function maintenance_guard(mysqli $db): void
{
    if (setting($db, 'maintenance_mode', '0') !== '1') {
        return;
    }
    if (admin()) {
        return;
    }
    $page = $_GET['page'] ?? '';
    if ($page === 'login' || $page === 'logout' || $page === 'midtrans-notify') {
        return;
    }
    http_response_code(503);
    header('Retry-After: 300');
    echo '<!doctype html><meta charset=utf-8><title>Sedang dirapikan</title><body style="background:#111;color:#e8dfcf;font:16px system-ui;padding:12vh 8vw"><h1 style="font:600 56px \'Cormorant Garamond\',serif">Sedang dirapikan.</h1><p>Situs sementara dalam mode perawatan. Silakan kembali beberapa saat lagi.</p></body>';
    exit;
}

// ─── CSP Nonce ───
$csp_nonce = bin2hex(random_bytes(16));
function csp_nonce(): string
{
    global $csp_nonce;

    return $csp_nonce ?? '';
}
