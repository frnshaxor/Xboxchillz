<?php
require __DIR__ . '/config.php';
maintenance_guard($db);

$page = $_GET['page'] ?? 'home';

// -------- LOGIN with rate-limit + optional TOTP --------
if ($page === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $ip = client_ip();
    $email = trim((string)($_POST['email'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');
    $code2fa = preg_replace('/\D/', '', (string)($_POST['totp'] ?? ''));

    if (recent_failed_logins($db, $ip, 15) >= 8) {
        log_login_attempt($db, $email, false, 'rate_limited');
        $error = 'Terlalu banyak percobaan gagal. Coba lagi dalam 15 menit.';
    } else {
        $s = $db->prepare('SELECT id,password,name,totp_secret,totp_enabled FROM admins WHERE email=? AND active=1');
        $s->bind_param('s', $email); $s->execute();
        $a = $s->get_result()->fetch_assoc();
        if ($a && password_verify($pass, $a['password'])) {
            if ((int)$a['totp_enabled'] === 1) {
                if (!$code2fa || !totp_verify((string)$a['totp_secret'], $code2fa)) {
                    log_login_attempt($db, $email, false, 'totp_failed');
                    $error = 'Kode 2FA salah atau kedaluwarsa.';
                    $need_totp = true;
                }
            }
            if (empty($error)) {
                if (password_needs_upgrade($a['password'])) {
                    $newHash = password_new($pass);
                    $u = $db->prepare('UPDATE admins SET password=? WHERE id=?');
                    $u->bind_param('si', $newHash, $a['id']); $u->execute();
                }
                session_regenerate_id(true);
                $_SESSION['admin_id']   = (int)$a['id'];
                $_SESSION['admin_name'] = $a['name'];
                $u = $db->prepare('UPDATE admins SET last_login_at=NOW(), last_login_ip=? WHERE id=?');
                $u->bind_param('si', $ip, $a['id']); $u->execute();
                log_login_attempt($db, $email, true, 'ok');
                log_activity($db, (int)$a['id'], 'login', 'ok');
                go('?page=admin');
            }
        } else {
            log_login_attempt($db, $email, false, 'bad_credentials');
            $error = $error ?? 'Email atau kata sandi salah.';
        }
    }
}
if ($page === 'logout') { log_activity($db, $_SESSION['admin_id'] ?? null, 'logout', ''); $_SESSION = []; session_destroy(); go('?page=login'); }
if ($page === 'admin') need_admin();

// -------- Admin: save settings (site identity) --------
if ($page === 'save-settings' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    need_admin(); check_csrf();
    foreach (['site_name', 'site_description'] as $k) {
        $v = trim((string)($_POST[$k] ?? ''));
        set_setting($db, $k, $v);
    }
    log_activity($db, (int)$_SESSION['admin_id'], 'settings_save', '');
    go('?page=admin&saved=1');
}

// -------- Admin: account update (name + email) --------
if ($page === 'account-update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    need_admin(); check_csrf();
    $aid = (int)$_SESSION['admin_id'];
    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $current = (string)($_POST['current_password'] ?? '');
    // Verify current password
    $s = $db->prepare('SELECT password FROM admins WHERE id=?'); $s->bind_param('i', $aid); $s->execute();
    $r = $s->get_result()->fetch_assoc();
    if (!$r || !password_verify($current, $r['password'])) { go('?page=admin&tab=account&err=pw'); }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($name) < 2) { go('?page=admin&tab=account&err=input'); }
    // Uniqueness
    $s = $db->prepare('SELECT id FROM admins WHERE email=? AND id<>?'); $s->bind_param('si', $email, $aid); $s->execute();
    if ($s->get_result()->fetch_assoc()) { go('?page=admin&tab=account&err=dupe'); }
    $u = $db->prepare('UPDATE admins SET name=?, email=? WHERE id=?');
    $u->bind_param('ssi', $name, $email, $aid); $u->execute();
    $_SESSION['admin_name'] = $name;
    log_activity($db, $aid, 'account_update', 'name/email');
    go('?page=admin&tab=account&saved=1');
}

// -------- Admin: password change --------
if ($page === 'password-change' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    need_admin(); check_csrf();
    $aid = (int)$_SESSION['admin_id'];
    $current = (string)($_POST['current_password'] ?? '');
    $new = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');
    $s = $db->prepare('SELECT password FROM admins WHERE id=?'); $s->bind_param('i', $aid); $s->execute();
    $r = $s->get_result()->fetch_assoc();
    if (!$r || !password_verify($current, $r['password'])) { go('?page=admin&tab=account&err=pw'); }
    if (strlen($new) < 10) { go('?page=admin&tab=account&err=short'); }
    if ($new !== $confirm) { go('?page=admin&tab=account&err=mismatch'); }
    $hash = password_new($new);
    $u = $db->prepare('UPDATE admins SET password=? WHERE id=?');
    $u->bind_param('si', $hash, $aid); $u->execute();
    session_regenerate_id(true);
    log_activity($db, $aid, 'password_change', 'ok');
    go('?page=admin&tab=account&pwsaved=1');
}

// -------- Admin: categories --------
if ($page === 'add-category' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    need_admin(); check_csrf();
    $n = trim((string)($_POST['name'] ?? ''));
    if ($n) { $s = $db->prepare('INSERT IGNORE INTO categories(name) VALUES(?)'); $s->bind_param('s', $n); $s->execute(); log_activity($db, (int)$_SESSION['admin_id'], 'category_add', $n); }
    go('?page=admin');
}
if ($page === 'delete-category' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    need_admin(); check_csrf();
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $u = $db->prepare('UPDATE videos SET category_id=0 WHERE category_id=?'); $u->bind_param('i', $id); $u->execute();
        $d = $db->prepare('DELETE FROM categories WHERE id=?'); $d->bind_param('i', $id); $d->execute();
        log_activity($db, (int)$_SESSION['admin_id'], 'category_delete', (string)$id);
    }
    go('?page=admin');
}

// -------- Admin: delete video --------
if ($page === 'delete-video' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    need_admin(); check_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $s = $db->prepare('SELECT slug FROM videos WHERE id=?'); $s->bind_param('i', $id); $s->execute();
    $r = $s->get_result()->fetch_assoc();
    if ($r) {
        $dir = MEDIA_ROOT . '/' . $r['slug'];
        if (is_dir($dir) && strpos(realpath($dir) ?: '', realpath(MEDIA_ROOT)) === 0) {
            foreach (glob($dir . '/*') ?: [] as $f) @unlink($f);
            @rmdir($dir);
        }
        $del = $db->prepare('DELETE FROM videos WHERE id=?'); $del->bind_param('i', $id); $del->execute();
        log_activity($db, (int)$_SESSION['admin_id'], 'video_delete', (string)$id);
    }
    go('?page=admin');
}

// -------- Admin: upload video --------
if ($page === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    need_admin(); check_csrf();
    $title = trim((string)($_POST['title'] ?? ''));
    $cat = (int)($_POST['category_id'] ?? 0);
    $files = $_FILES['video'] ?? null;
    $limit_mb = (int)setting($db, 'upload_max_mb', '2048');

    // Normalize: support both single file and multi-file upload
    if ($files && is_array($files['name'] ?? null)) {
        $fileList = [];
        $count = count($files['name']);
        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $fileList[] = [
                    'name' => $files['name'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'size' => $files['size'][$i],
                ];
            }
        }
    } elseif ($files && ($files['error'] ?? -1) === UPLOAD_ERR_OK) {
        $fileList = [['name' => $files['name'], 'tmp_name' => $files['tmp_name'], 'size' => $files['size']]];
    } else {
        $fileList = [];
    }

    if (!$fileList) { go('?page=admin&err=upload'); }

    $uploaded = 0;
    foreach ($fileList as $f) {
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if ($ext !== 'mp4') continue;
        if ($f['size'] > $limit_mb * 1024 * 1024) continue;
        $fi = new finfo(FILEINFO_MIME_TYPE);
        $mime = $fi->file($f['tmp_name']) ?: '';
        $isMp4 = in_array($mime, ['video/mp4', 'application/mp4'], true);
        if (!$isMp4 && $mime === 'application/octet-stream') {
            $handle = fopen($f['tmp_name'], 'rb');
            if ($handle) { $header = fread($handle, 12); fclose($handle); $isMp4 = str_contains($header, 'ftyp'); }
        }
        if (!$isMp4) continue;

        $fileTitle = $title ?: pathinfo($f['name'], PATHINFO_FILENAME);
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($fileTitle));
        $slug = trim($slug, '-');
        $slug = preg_replace('/-+/', '-', $slug);
        if ($slug === '') $slug = 'video';
        $slug .= '-' . bin2hex(random_bytes(3));
        $dir = MEDIA_ROOT . '/' . $slug;
        mkdir($dir, 0750, true);
        move_uploaded_file($f['tmp_name'], $dir . '/source.mp4');
        $size = filesize($dir . '/source.mp4') ?: 0;

        $duration = 0;
        $probe = shell_exec('ffprobe -v error -show_entries format=duration -of default=nokey=1:noprint_wrappers=1 ' . escapeshellarg($dir . '/source.mp4') . ' 2>/dev/null');
        if ($probe) $duration = (int)round((float)trim($probe));

        $poster = 'media/' . $slug . '/poster.jpg';
        $src = 'media/' . $slug . '/source.mp4';
        $status = 'processing';
        $s = $db->prepare('INSERT INTO videos(title,slug,category_id,poster,source,duration_sec,size_bytes,status) VALUES(?,?,?,?,?,?,?,?)');
        $s->bind_param('ssissiis', $fileTitle, $slug, $cat, $poster, $src, $duration, $size, $status);
        $s->execute();
        log_activity($db, (int)$_SESSION['admin_id'], 'video_upload', $slug);

        // Preview + HLS are generated by the background worker (setsid for cgroup v2 safety)
        $worker = '/usr/local/sbin/arsip-hls-worker';
        if (is_executable($worker)) {
            shell_exec('setsid nohup ' . escapeshellarg($worker) . ' ' . escapeshellarg($slug) . ' > /dev/null 2>&1 < /dev/null &');
        }
        $uploaded++;
    }

    if ($uploaded > 0) {
        go('?page=admin&uploaded=1');
    } else {
        go('?page=admin&err=upload');
    }
}

// -------- Token access: verify --------
if ($page === 'verify-token' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $tok      = strtoupper(trim((string)($_POST['token'] ?? '')));
    $redirect = (string)($_POST['redirect'] ?? '.');
    // Sanitize redirect — only allow internal relative URLs starting with ?
    if (!preg_match('/^\?/', $redirect)) $redirect = '.';

    $s = $db->prepare("SELECT id, expires_at FROM access_tokens WHERE token=? AND status='active'");
    $s->bind_param('s', $tok); $s->execute();
    $row = $s->get_result()->fetch_assoc();

    if ($row) {
        // Check expiry
        if ($row['expires_at'] && strtotime($row['expires_at']) < time()) {
            $db->query('UPDATE access_tokens SET status="expired" WHERE id=' . (int)$row['id']);
            $_SESSION['token_error'] = 'Token ini sudah kedaluwarsa.';
            go($redirect . (str_contains($redirect, '?') ? '&' : '?') . 'token_err=1');
        }
        grant_access_with_token($db, (int)$row['id']);
        $u = $db->prepare('UPDATE access_tokens SET use_count=use_count+1, last_used_at=NOW() WHERE id=?');
        $u->bind_param('i', $row['id']); $u->execute();
        go($redirect);
    } else {
        $_SESSION['token_error'] = 'Token tidak valid atau sudah dinonaktifkan.';
        go($redirect . (str_contains($redirect, '?') ? '&' : '?') . 'token_err=1');
    }
}

// -------- Token revoke (user cabut akses sendiri) --------
if ($page === 'revoke-access') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') check_csrf();
    revoke_access();
    go('.?logged_out=1');
}

// -------- Midtrans webhook: signature checked and idempotent before issuing a token --------
if ($page === 'midtrans-notify' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) { http_response_code(400); exit('Payload tidak valid'); }
    $orderId = (string)($payload['order_id'] ?? '');
    $statusCode = (string)($payload['status_code'] ?? '');
    $grossAmount = (string)($payload['gross_amount'] ?? '');
    $signature = (string)($payload['signature_key'] ?? '');
    $serverKey = setting($db, 'midtrans_server_key', '');
    $expected = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);
    if (!$serverKey || !$signature || !hash_equals($expected, $signature)) { http_response_code(403); exit('Signature tidak valid'); }
    $db->begin_transaction();
    $q = $db->prepare('SELECT * FROM payment_orders WHERE order_id=? FOR UPDATE'); $q->bind_param('s', $orderId); $q->execute();
    $order = $q->get_result()->fetch_assoc();
    if (!$order || number_format((float)$order['amount'], 2, '.', '') !== $grossAmount) { $db->rollback(); http_response_code(404); exit('Order tidak valid'); }
    $transactionStatus = (string)($payload['transaction_status'] ?? '');
    $fraudStatus = (string)($payload['fraud_status'] ?? 'accept');
    $newStatus = in_array($transactionStatus, ['settlement'], true) || ($transactionStatus === 'capture' && $fraudStatus === 'accept') ? 'settlement' : $transactionStatus;
    $paymentType = substr((string)($payload['payment_type'] ?? ''), 0, 60);
    $transactionId = substr((string)($payload['transaction_id'] ?? ''), 0, 100);
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $tokenId = (int)($order['token_id'] ?? 0);
    if ($newStatus === 'settlement' && !$tokenId) {
        $raw = generate_token(12); $token = substr($raw, 0, 4) . '-' . substr($raw, 4, 4) . '-' . substr($raw, 8, 4);
        $label = 'Midtrans — ' . $order['buyer_name']; $contactType = 'telegram'; $contact = $order['buyer_contact']; $active = 'active';
        $expiresAt = date('Y-m-d H:i:s', time() + 30 * 86400);
        $create = $db->prepare('INSERT INTO access_tokens(token,label,contact_type,contact_value,status,expires_at) VALUES(?,?,?,?,?,?)');
        $create->bind_param('ssssss', $token, $label, $contactType, $contact, $active, $expiresAt); $create->execute(); $tokenId = $db->insert_id;
        log_activity($db, null, 'midtrans_token_issue', "order=$orderId token_id=$tokenId");
    }
    $update = $db->prepare('UPDATE payment_orders SET status=?, token_id=?, midtrans_transaction_id=?, payment_type=?, notification_json=?, paid_at=IF(?="settlement",NOW(),paid_at) WHERE id=?');
    $update->bind_param('sissssi', $newStatus, $tokenId, $transactionId, $paymentType, $json, $newStatus, $order['id']); $update->execute();
    $db->commit();
    http_response_code(200); exit('OK');
}

// -------- Contact page settings (admin) --------
if ($page === 'save-contact' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    need_admin(); check_csrf();
    foreach (['contact_title', 'contact_subtitle', 'contact_telegram', 'contact_whatsapp', 'contact_email'] as $k) {
        $v = trim((string)($_POST[$k] ?? ''));
        set_setting($db, $k, $v);
    }
    log_activity($db, (int)$_SESSION['admin_id'], 'contact_settings_save', '');
    go('?page=admin&tab=system&contact_saved=1');
}

// -------- Midtrans settings (admin) --------
if ($page === 'save-midtrans' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    need_admin(); check_csrf();
    $mode = ($_POST['mode'] ?? 'sandbox') === 'production' ? 'production' : 'sandbox';
    $enabled = ($_POST['enabled'] ?? '') === '1' ? '1' : '0';
    if ($enabled === '1' && $mode === 'production' && !$isHttps) {
        go('?page=admin&tab=payments&midtrans_err=https');
    }
    $price = max(1000, min(100000000, (int)($_POST['price'] ?? 50000)));
    set_setting($db, 'midtrans_mode', $mode); set_setting($db, 'midtrans_enabled', $enabled); set_setting($db, 'midtrans_token_price', (string)$price);
    foreach (['client_key' => 'midtrans_client_key', 'server_key' => 'midtrans_server_key'] as $field => $settingKey) {
        $value = trim((string)($_POST[$field] ?? '')); if ($value !== '') set_setting($db, $settingKey, $value);
    }
    log_activity($db, (int)$_SESSION['admin_id'], 'midtrans_settings_save', "mode=$mode enabled=$enabled price=$price");
    go('?page=admin&tab=payments&saved=1');
}

// -------- Protected media delivery --------
// Nginx maps /protected-media/<path> here. HLS relative playlist URLs stay intact.
if ($page === 'media' || $page === 'poster' || $page === 'preview') {
    $relative = rawurldecode((string)($_GET['path'] ?? ''));
    $relative = preg_replace('#^/?(?:protected-media/|media/)#', '', $relative);
    $allowed = '#^[a-z0-9-]+/(?:poster\.jpg|preview\.mp4|source\.mp4|master\.m3u8|(?:360p|720p)\.m3u8|(?:360p|720p)_\d{3}\.ts)$#i';
    if (!preg_match($allowed, $relative)) { http_response_code(404); exit('Media tidak ditemukan.'); }
    $file = MEDIA_ROOT . '/' . $relative;
    $root = realpath(MEDIA_ROOT);
    $real = realpath($file);
    if (!$real || !$root || !str_starts_with($real, $root . DIRECTORY_SEPARATOR) || !is_file($real)) { http_response_code(404); exit('Media tidak ditemukan.'); }

    $isPoster = str_ends_with(strtolower($relative), '/poster.jpg');
    $isPreview = str_ends_with(strtolower($relative), '/preview.mp4');
    if ($page === 'media' && !$isPoster && !$isPreview && !has_access() && !admin()) { http_response_code(403); exit('Akses token diperlukan.'); }
    if ($page === 'poster' && !$isPoster) { http_response_code(404); exit('Media tidak ditemukan.'); }
    if ($page === 'preview' && !$isPreview) { http_response_code(404); exit('Media tidak ditemukan.'); }

    $extension = strtolower(pathinfo($real, PATHINFO_EXTENSION));
    $types = ['jpg' => 'image/jpeg', 'm3u8' => 'application/vnd.apple.mpegurl', 'ts' => 'video/mp2t', 'mp4' => 'video/mp4'];
    header('Content-Type: ' . ($types[$extension] ?? 'application/octet-stream'));
    header('Content-Length: ' . (string)filesize($real));
    header('Cache-Control: ' . ($isPreview ? 'public,max-age=86400' : 'private,no-store'));
    header('X-Content-Type-Options: nosniff');
    if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') readfile($real);
    exit;
}

// -------- Download original MP4 (valid token/admin only) --------
if ($page === 'download') {
    if (!has_access() && !admin()) { http_response_code(403); exit('Akses token diperlukan.'); }
    $id = (int)($_GET['id'] ?? 0);
    $q = $db->prepare('SELECT title,source FROM videos WHERE id=?'); $q->bind_param('i', $id); $q->execute();
    $video = $q->get_result()->fetch_assoc();
    $file = $video ? APP_ROOT . '/' . ltrim($video['source'], '/') : '';
    if (!$video || !is_file($file)) { http_response_code(404); exit('File video tidak ditemukan.'); }
    $name = preg_replace('/[^a-z0-9._-]+/i', '-', $video['title']) . '.mp4';
    header('Content-Type: video/mp4');
    header('Content-Length: ' . (string)filesize($file));
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Cache-Control: private, no-store');
    readfile($file);
    exit;
}

// -------- Token management fallback (works even when the Vue CDN is unavailable) --------
if ($page === 'token-create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    need_admin(); check_csrf();
    $label = trim((string)($_POST['label'] ?? ''));
    $contactType = (string)($_POST['contact_type'] ?? 'telegram');
    $contactValue = trim((string)($_POST['contact_value'] ?? ''));
    if (strlen($label) < 2 || strlen($contactValue) < 2 || !in_array($contactType, ['telegram', 'whatsapp', 'facebook'], true)) {
        go('?page=admin&tab=tokens&token_err=input');
    }

    $token = ''; $exists = true;
    for ($attempt = 0; $attempt < 10 && $exists; $attempt++) {
        $raw = generate_token(12);
        $token = substr($raw, 0, 4) . '-' . substr($raw, 4, 4) . '-' . substr($raw, 8, 4);
        $q = $db->prepare('SELECT id FROM access_tokens WHERE token=?');
        $q->bind_param('s', $token); $q->execute();
        $exists = (bool)$q->get_result()->fetch_assoc();
    }
    if ($exists) go('?page=admin&tab=tokens&token_err=generate');

    $adminId = (int)$_SESSION['admin_id'];
    $status = 'active';
    $expiresAt = date('Y-m-d H:i:s', time() + 30 * 86400); // 30 days default
    $q = $db->prepare('INSERT INTO access_tokens(token,label,contact_type,contact_value,status,created_by,expires_at) VALUES(?,?,?,?,?,?,?)');
    $q->bind_param('sssssis', $token, $label, $contactType, $contactValue, $status, $adminId, $expiresAt); $q->execute();
    log_activity($db, $adminId, 'token_create', "label=$label token=$token");
    go('?page=admin&tab=tokens&token_created=1');
}
if ($page === 'token-toggle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    need_admin(); check_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $q = $db->prepare('SELECT status FROM access_tokens WHERE id=?'); $q->bind_param('i', $id); $q->execute();
    $token = $q->get_result()->fetch_assoc();
    if ($token) {
        $status = $token['status'] === 'active' ? 'suspended' : 'active';
        $q = $db->prepare('UPDATE access_tokens SET status=? WHERE id=?'); $q->bind_param('si', $status, $id); $q->execute();
        log_activity($db, (int)$_SESSION['admin_id'], 'token_toggle', "id=$id status=$status");
    }
    go('?page=admin&tab=tokens');
}
if ($page === 'token-delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    need_admin(); check_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $q = $db->prepare('DELETE FROM access_tokens WHERE id=?'); $q->bind_param('i', $id); $q->execute();
    log_activity($db, (int)$_SESSION['admin_id'], 'token_delete', "id=$id");
    go('?page=admin&tab=tokens');
}

$site = setting($db, 'site_name', 'Arsip Layar');
$desc = setting($db, 'site_description', 'Platform berbagi karya video untuk kreator.');
$watermark_text = setting($db, 'watermark_text', 'Codename F');
$watermark_position = setting($db, 'watermark_position', 'br');
$watermark_opacity = (int)setting($db, 'watermark_opacity', '60');
$cache_ver = setting($db, 'cache_ver', '1');
$midtransEnabled = setting($db, 'midtrans_enabled', '0') === '1';
$midtransClientKey = setting($db, 'midtrans_client_key', '');
$midtransMode = setting($db, 'midtrans_mode', 'sandbox') === 'production' ? 'production' : 'sandbox';
$midtransPrice = (int)setting($db, 'midtrans_token_price', '50000');
?><!doctype html>
<html lang="id" class="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#09090b">
<title><?= e($site) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,300..700,0..1,-25..0">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/plyr@3.7.8/dist/plyr.css">
<style>.material-symbols-rounded{font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24;vertical-align:middle}</style>
<script src="https://cdn.tailwindcss.com?plugins=forms,typography" onerror="console.warn('Tailwind CDN unavailable')"></script>
<link rel="stylesheet" href="style.css?v=<?= e($cache_ver) ?>-midtrans-ui4">
<script>tailwind.config={darkMode:'class',theme:{extend:{fontFamily:{display:['Fraunces','serif'],sans:['Geist','system-ui','sans-serif'],mono:['JetBrains Mono','ui-monospace']}}}}</script>
<script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.13/dist/hls.min.js" defer onerror="console.warn('HLS.js CDN unavailable')"></script>
<script src="https://cdn.jsdelivr.net/npm/plyr@3.7.8/dist/plyr.min.js" defer onerror="console.warn('Plyr CDN unavailable')"></script>
<script src="https://unpkg.com/vue@3.4.38/dist/vue.global.prod.js" defer onerror="console.warn('Vue CDN unavailable')"></script>
<?php if ($midtransEnabled && $midtransClientKey): ?><script src="<?= e(midtrans_snap_url($midtransMode)) ?>" data-client-key="<?= e($midtransClientKey) ?>" defer></script><?php endif; ?>
<script src="vue_enhance.js?v=<?= e($cache_ver) ?>-midtrans-ui4" defer></script>
</head>
<body>
<header class="top">
  <a class="brand" href="."><?= e($site) ?><span>&nbsp;·&nbsp;arsip</span></a>
  <button class="burger" aria-label="Menu" aria-expanded="false" data-testid="nav-burger" type="button">
    <span class="material-symbols-rounded" aria-hidden="true">menu</span>
  </button>
  <nav class="nav" id="nav">
    <a href="."><span class="material-symbols-rounded">home</span>Beranda</a>
    <a href="?page=contact"><span class="material-symbols-rounded">support_agent</span>Kontak</a>
    <?php if (admin()): ?>
      <a href="?page=admin" data-testid="nav-admin"><span class="material-symbols-rounded">tune</span>Panel</a>
      <a href="?page=logout" data-testid="nav-logout"><span class="material-symbols-rounded">logout</span>Keluar</a>
    <?php elseif (has_access()): ?>
      <div class="nav-token-info">
        <div class="nav-token-label">
          <span class="material-symbols-rounded">vpn_key</span>
          <span><?= e($_SESSION['access_token_label'] ?? 'Token Aktif') ?></span>
        </div>
        <div class="nav-token-date">Dibuat: <?= e($_SESSION['access_token_created_at'] ?? '') ?></div>
        <div class="nav-token-actions">
          <?php if (($_SESSION['access_token_expires_at'] ?? '') && strtotime($_SESSION['access_token_expires_at']) < time()): ?>
            <span class="nav-token-expired-badge">
              <span class="material-symbols-rounded">error</span> Kedaluwarsa
            </span>
            <a href="?page=contact" class="button ghost small"><span class="material-symbols-rounded">support_agent</span> Hubungi Admin</a>
          <?php endif; ?>
          <form method="post" action="?page=revoke-access" style="margin:0;display:inline">
            <input type="hidden" name="csrf" value="<?= csrf() ?>">
            <button type="submit" class="button ghost small"><span class="material-symbols-rounded">logout</span> Keluar</button>
          </form>
        </div>
      </div>
    <?php else: ?>
      <a href="?page=login" data-testid="nav-login"><span class="material-symbols-rounded">login</span>Masuk</a>
    <?php endif; ?>
  </nav>
</header>

<?php if ($page === 'login'): ?>
<main class="wrap"><div class="auth">
  <div class="eyebrow">Ruang Kerja / 2026</div>
  <h1>Masuk ke panel.</h1>
  <?php if (!empty($error)): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
  <form method="post" data-testid="login-form">
    <input type="hidden" name="csrf" value="<?= csrf() ?>">
    <label>Email<input name="email" type="email" autocomplete="username" required data-testid="login-email"></label>
    <label>Kata sandi<input name="password" type="password" autocomplete="current-password" required data-testid="login-password"></label>
    <label>Kode 2FA (opsional)<input name="totp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="6 digit" data-testid="login-totp"></label>
    <button data-testid="login-submit">Masuk</button>
  </form>
</div></main>

<?php elseif ($page === 'admin'):
    $cats = $db->query('SELECT * FROM categories ORDER BY name');
    $vids = $db->query('SELECT v.*, c.name category FROM videos v LEFT JOIN categories c ON c.id=v.category_id ORDER BY v.created_at DESC');
    $meQ = $db->prepare('SELECT name,email,totp_enabled,last_login_at,last_login_ip FROM admins WHERE id=?'); $meQ->bind_param('i', $_SESSION['admin_id']); $meQ->execute(); $me = $meQ->get_result()->fetch_assoc();
    $upload_mb = (int)setting($db, 'upload_max_mb', '2048');
    $maintenance = setting($db, 'maintenance_mode', '0');
    $tab_qs = $_GET['tab'] ?? 'content';
?>
<main class="admin"><div class="wrap admin-grid">
  <aside class="side">
    <div class="eyebrow">Control Panel</div>
    <h2><?= e($site) ?></h2>
    <p class="who">Halo, <b><?= e($me['name']) ?></b></p>
    <div class="kv">
      <div>Login terakhir</div>
      <span><?= e($me['last_login_at'] ?? '—') ?></span>
      <span><?= e($me['last_login_ip'] ?? '') ?></span>
    </div>
    <a class="button ghost" href="?page=logout" data-testid="admin-logout">Keluar</a>
  </aside>

  <section>
    <div class="eyebrow">Penerbitan · <?= date('d M Y') ?></div>
    <h1 class="pagetitle">Ruang kendali.</h1>

    <div class="tabs" role="tablist" data-initial="<?= e($tab_qs) ?>" data-testid="admin-tabs">
      <button class="tab" data-tab="content">Konten</button>
      <button class="tab" data-tab="analytics">Analytics</button>
      <button class="tab" data-tab="security">Keamanan</button>
      <button class="tab" data-tab="account">Akun</button>
      <button class="tab" data-tab="system">Sistem</button>
      <button class="tab" data-tab="tokens">Akses Token</button>
      <button class="tab" data-tab="payments">Pembayaran</button>
    </div>

    <!-- ============ CONTENT ============ -->
    <div class="tabpane hidden" data-pane="content">
      <?php if (isset($_GET['uploaded'])): ?><p class="notice">Upload diterima. Transcode HLS jalan di background — refresh sebentar lagi.</p><?php endif; ?>
      <div class="grid2">
        <div class="panel">
          <h3><span class="material-symbols-rounded">cloud_upload</span> Unggah video MP4</h3>
          <form id="upload-form" method="post" enctype="multipart/form-data" action="?page=upload" data-testid="upload-form">
            <input type="hidden" name="csrf" value="<?= csrf() ?>">
            <label>Judul<input name="title" placeholder="Kosongkan untuk auto-generate dari nama file" data-testid="upload-title"></label>
            <label>Kategori<select name="category_id" data-testid="upload-category">
              <option value="0">Tanpa kategori</option>
              <?php $cats->data_seek(0); while ($c = $cats->fetch_assoc()): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endwhile; ?>
            </select></label>
            <label>File MP4 (bisa pilih beberapa sekaligus, maks <?= $upload_mb ?> MB per file)<input type="file" name="video[]" accept="video/mp4" multiple required data-testid="upload-file"></label>
            <div id="upload-progress" class="upload-progress hidden" data-testid="upload-progress">
              <div class="up-track"><div class="up-fill" style="width:0%"></div></div>
              <div class="up-info"><span class="up-pct">0%</span><span class="up-bytes muted">0 / 0 MB</span></div>
            </div>
            <button type="submit" data-testid="upload-submit"><span class="material-symbols-rounded">upload</span> Mulai unggah &amp; transcode</button>
          </form>
        </div>
        <div class="panel">
          <h3><span class="material-symbols-rounded">brush</span> Identitas website</h3>
          <form method="post" action="?page=save-settings" data-testid="identity-form">
            <input type="hidden" name="csrf" value="<?= csrf() ?>">
            <label>Nama website<input name="site_name" value="<?= e($site) ?>" data-testid="setting-site-name"></label>
            <label>Deskripsi<textarea name="site_description" data-testid="setting-desc"><?= e($desc) ?></textarea></label>
            <button data-testid="identity-submit"><span class="material-symbols-rounded">save</span> Simpan pengaturan</button>
          </form>
        </div>
      </div>

      <div class="panel">
        <h3><span class="material-symbols-rounded">branding_watermark</span> Watermark video</h3>
        <div id="watermark-mount" data-testid="watermark-panel"
             data-text="<?= e($watermark_text) ?>"
             data-position="<?= e($watermark_position) ?>"
             data-opacity="<?= (int)$watermark_opacity ?>"></div>
      </div>

      <div class="panel">
        <h3>Kategori</h3>
        <?php $cats2 = $db->query('SELECT * FROM categories ORDER BY name'); ?>
        <form class="inline" method="post" action="?page=add-category">
          <input type="hidden" name="csrf" value="<?= csrf() ?>">
          <input name="name" placeholder="Nama kategori" required data-testid="category-name">
          <button data-testid="category-add">Tambah</button>
        </form>
        <div class="chips">
          <?php while ($c = $cats2->fetch_assoc()): ?>
            <form method="post" action="?page=delete-category" class="chip" onsubmit="return confirm('Hapus kategori ini?')">
              <input type="hidden" name="csrf" value="<?= csrf() ?>">
              <input type="hidden" name="id" value="<?= $c['id'] ?>">
              <span><?= e($c['name']) ?></span>
              <button class="x" aria-label="Hapus">×</button>
            </form>
          <?php endwhile; ?>
        </div>
      </div>

      <div class="panel">
        <h3>Perpustakaan video</h3>
        <div class="table">
          <div class="tr head"><span>Judul</span><span>Kategori</span><span>Durasi</span><span>Views</span><span>Status</span><span></span></div>
          <?php while ($v = $vids->fetch_assoc()): ?>
            <div class="tr">
              <span><a href="?page=watch&id=<?= $v['id'] ?>"><?= e($v['title']) ?></a></span>
              <span><?= e($v['category'] ?? '—') ?></span>
              <span><?= gmdate('i:s', (int)$v['duration_sec']) ?></span>
              <span><?= (int)$v['views'] ?></span>
              <span><span class="badge <?= $v['status'] === 'ready' ? 'badge-success badge-dot' : 'badge-warning badge-dot' ?>"><?= e($v['status']) ?></span></span>
              <span>
                <form method="post" action="?page=delete-video" onsubmit="return confirm('Hapus video ini beserta file?')" style="margin:0">
                  <input type="hidden" name="csrf" value="<?= csrf() ?>">
                  <input type="hidden" name="id" value="<?= $v['id'] ?>">
                  <button class="ghost small">Hapus</button>
                </form>
              </span>
            </div>
          <?php endwhile; ?>
          <?php if ($vids->num_rows === 0): ?><p class="muted" style="padding:16px 0">Belum ada video. Unggah satu di atas.</p><?php endif; ?>
        </div>
      </div>
    </div>

    <!-- ============ ANALYTICS ============ -->
    <div class="tabpane hidden" data-pane="analytics"><div id="analytics-mount" data-testid="analytics-panel"></div></div>

    <!-- ============ SECURITY ============ -->
    <div class="tabpane hidden" data-pane="security"><div id="security-mount" data-testid="security-panel" data-totp-enabled="<?= (int)$me['totp_enabled'] ?>"></div></div>

    <!-- ============ ACCOUNT ============ -->
    <div class="tabpane hidden" data-pane="account">
      <?php
        $err = $_GET['err'] ?? '';
        $errmap = ['pw' => 'Password saat ini salah.', 'input' => 'Nama minimal 2 karakter dan email harus valid.', 'dupe' => 'Email tersebut sudah dipakai.', 'short' => 'Password baru minimal 10 karakter.', 'mismatch' => 'Konfirmasi password tidak sama.'];
      ?>
      <?php if (isset($_GET['saved'])): ?><p class="notice">Profil disimpan.</p><?php endif; ?>
      <?php if (isset($_GET['pwsaved'])): ?><p class="notice">Password diperbarui. Gunakan password baru saat login berikutnya.</p><?php endif; ?>
      <?php if ($err && isset($errmap[$err])): ?><p class="notice err"><?= e($errmap[$err]) ?></p><?php endif; ?>

      <div class="grid2">
        <div class="panel">
          <h3>Profil admin</h3>
          <form method="post" action="?page=account-update" data-testid="account-form">
            <input type="hidden" name="csrf" value="<?= csrf() ?>">
            <label>Nama tampilan<input name="name" value="<?= e($me['name']) ?>" required data-testid="account-name"></label>
            <label>Email (username login)<input name="email" type="email" value="<?= e($me['email']) ?>" required data-testid="account-email"></label>
            <label>Konfirmasi dengan password saat ini<input name="current_password" type="password" autocomplete="current-password" required data-testid="account-current"></label>
            <button data-testid="account-submit">Simpan profil</button>
          </form>
        </div>
        <div class="panel">
          <h3>Ubah password</h3>
          <form method="post" action="?page=password-change" data-testid="password-form">
            <input type="hidden" name="csrf" value="<?= csrf() ?>">
            <label>Password saat ini<input name="current_password" type="password" autocomplete="current-password" required data-testid="pw-current"></label>
            <label>Password baru (min 10 karakter)<input name="new_password" type="password" autocomplete="new-password" required minlength="10" data-testid="pw-new"></label>
            <label>Ulangi password baru<input name="confirm_password" type="password" autocomplete="new-password" required minlength="10" data-testid="pw-confirm"></label>
            <button data-testid="pw-submit">Ubah password</button>
          </form>
          <p class="muted" style="margin-top:14px;font-size:12px">Gunakan password acak &amp; unik. Simpan di manager password (Bitwarden, 1Password).</p>
        </div>
      </div>
    </div>

    <!-- ============ SYSTEM ============ -->
    <div class="tabpane hidden" data-pane="system">
      <div id="system-mount" data-testid="system-panel" data-upload-mb="<?= $upload_mb ?>" data-maintenance="<?= (int)$maintenance ?>"></div>
      <div id="telegram-mount" data-testid="telegram-panel"></div>

      <div class="panel" style="margin-top:22px">
        <h3><span class="material-symbols-rounded">contact_mail</span> Halaman Kontak</h3>
        <p class="muted" style="margin-bottom:14px;font-size:13px">Atur link kontak yang tampil di halaman <a href="?page=contact" style="color:var(--accent)">/contact</a> dan modal token.</p>
        <form method="post" action="?page=save-contact" class="grid2">
          <input type="hidden" name="csrf" value="<?= csrf() ?>">
          <label>Judul Halaman
            <input name="contact_title" value="<?= e(setting($db, 'contact_title', 'Hubungi Admin')) ?>" placeholder="Hubungi Admin">
          </label>
          <label>Subtitle
            <input name="contact_subtitle" value="<?= e(setting($db, 'contact_subtitle', 'Pilih platform yang paling nyaman untuk Anda.')) ?>" placeholder="Pilih platform yang paling nyaman...">
          </label>
          <label>Link Telegram
            <input name="contact_telegram" type="url" value="<?= e(setting($db, 'contact_telegram', '')) ?>" placeholder="https://t.me/username">
          </label>
          <label>Link WhatsApp
            <input name="contact_whatsapp" type="url" value="<?= e(setting($db, 'contact_whatsapp', '')) ?>" placeholder="https://wa.me/62812...">
          </label>
          <label>Email
            <input name="contact_email" type="email" value="<?= e(setting($db, 'contact_email', '')) ?>" placeholder="email@domain.com">
          </label>
          <div><button type="submit"><span class="material-symbols-rounded">save</span> Simpan Kontak</button></div>
        </form>
        <?php if (isset($_GET['contact_saved'])): ?><p class="notice">Pengaturan kontak disimpan.</p><?php endif; ?>
      </div>
    </div>

    <!-- ============ TOKENS ============ -->
    <div class="tabpane hidden" data-pane="tokens">
      <div id="tokens-mount" data-testid="tokens-panel">
        <?php
          $fallbackTokens = $db->query('SELECT id,token,label,contact_type,contact_value,status,use_count,last_used_at,created_at FROM access_tokens ORDER BY created_at DESC');
          $tokenErr = $_GET['token_err'] ?? '';
        ?>
        <div class="panel token-fallback">
          <h3><span class="material-symbols-rounded">vpn_key</span> Manajemen Token Akses</h3>
          <p class="muted">Buat dan kelola kode akses untuk pelanggan. Satu token dapat digunakan di beberapa perangkat.</p>
          <?php if (isset($_GET['token_created'])): ?><p class="notice">Token baru berhasil dibuat.</p><?php endif; ?>
          <?php if ($tokenErr === 'input'): ?><p class="notice err">Nama penerima dan kontak wajib diisi dengan benar.</p><?php endif; ?>
          <?php if ($tokenErr === 'generate'): ?><p class="notice err">Token unik gagal dibuat. Silakan ulangi.</p><?php endif; ?>
          <form method="post" action="?page=token-create" class="token-fallback-form">
            <input type="hidden" name="csrf" value="<?= csrf() ?>">
            <label>Label / nama penerima<input name="label" minlength="2" maxlength="120" required placeholder="Contoh: Member VIP — Budi"></label>
            <label>Platform kontak<select name="contact_type"><option value="telegram">Telegram</option><option value="whatsapp">WhatsApp</option><option value="facebook">Facebook</option></select></label>
            <label>Kontak<input name="contact_value" minlength="2" maxlength="200" required placeholder="@username atau +62812…"></label>
            <button type="submit"><span class="material-symbols-rounded">add</span> Buat token baru</button>
          </form>

          <div class="token-fallback-list">
            <?php while ($token = $fallbackTokens->fetch_assoc()): ?>
              <article class="token-fallback-row">
                <div><strong><?= e($token['label']) ?></strong><code><?= e($token['token']) ?></code><small><?= e($token['contact_type']) ?> · <?= e($token['contact_value']) ?> · dipakai <?= (int)$token['use_count'] ?>×</small></div>
                <div class="token-fallback-actions">
                  <span class="badge <?= $token['status'] === 'active' ? 'badge-success badge-dot' : 'badge-warning badge-dot' ?>"><?= e($token['status']) ?></span>
                  <form method="post" action="?page=token-toggle"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="id" value="<?= (int)$token['id'] ?>"><button class="ghost small"><?= $token['status'] === 'active' ? 'Suspend' : 'Aktifkan' ?></button></form>
                  <form method="post" action="?page=token-delete" onsubmit="return confirm('Hapus token ini?')"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="id" value="<?= (int)$token['id'] ?>"><button class="ghost small danger">Hapus</button></form>
                </div>
              </article>
            <?php endwhile; ?>
            <?php if ($fallbackTokens->num_rows === 0): ?><p class="muted">Belum ada token. Buat token pertama di formulir di atas.</p><?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="tabpane hidden" data-pane="payments">
      <?php $serverConfigured = setting($db, 'midtrans_server_key', '') !== ''; $clientConfigured = setting($db, 'midtrans_client_key', '') !== ''; ?>
      <div class="panel">
        <h3><span class="material-symbols-rounded">payments</span> Midtrans Snap</h3>
        <p class="muted">Gunakan Sandbox untuk pengujian. Sebelum mode Production, set Notification URL Midtrans ke <code>https://DOMAIN-ANDA/?page=midtrans-notify</code>.</p>
        <?php if (isset($_GET['saved'])): ?><p class="notice">Pengaturan Midtrans disimpan.</p><?php endif; ?>
        <?php if (($_GET['midtrans_err'] ?? '') === 'https'): ?><p class="notice err">Mode Production membutuhkan HTTPS aktif. Gunakan Sandbox sampai sertifikat TLS terpasang.</p><?php endif; ?>
        <form method="post" action="?page=save-midtrans" class="grid2">
          <input type="hidden" name="csrf" value="<?= csrf() ?>">
          <label>Mode<select name="mode"><option value="sandbox" <?= $midtransMode === 'sandbox' ? 'selected' : '' ?>>Sandbox (uji coba)</option><option value="production" <?= $midtransMode === 'production' ? 'selected' : '' ?>>Production (pembayaran nyata)</option></select></label>
          <label>Harga satu token (IDR)<input type="number" name="price" min="1000" step="1000" value="<?= $midtransPrice ?>" required></label>
          <label>Client Key <small class="muted"><?= $clientConfigured ? '(tersimpan; kosongkan untuk mempertahankan)' : '' ?></small><input type="text" name="client_key" autocomplete="off" placeholder="<?= $clientConfigured ? 'Client Key tersimpan' : 'SB-Mid-client-…' ?>"></label>
          <label>Server Key <small class="muted"><?= $serverConfigured ? '(tersimpan; kosongkan untuk mempertahankan)' : '' ?></small><input type="password" name="server_key" autocomplete="new-password" placeholder="<?= $serverConfigured ? 'Server Key tersimpan' : 'SB-Mid-server-…' ?>"></label>
          <label class="switch"><input type="checkbox" name="enabled" value="1" <?= $midtransEnabled ? 'checked' : '' ?>><span class="track"><span class="knob"></span></span><span>Aktifkan checkout Midtrans</span></label>
          <div><button type="submit"><span class="material-symbols-rounded">save</span> Simpan pembayaran</button></div>
        </form>
      </div>
      <div class="panel"><h3>Order terbaru</h3><div id="payments-orders" class="muted">Memuat order…</div></div>
    </div>
  </section>
</div></main>

<?php elseif ($page === 'contact'):
    $contactTitle = setting($db, 'contact_title', 'Hubungi Admin');
    $contactSubtitle = setting($db, 'contact_subtitle', 'Pilih platform yang paling nyaman untuk Anda.');
    $contactTelegram = setting($db, 'contact_telegram', '');
    $contactWhatsapp = setting($db, 'contact_whatsapp', '');
    $contactEmail = setting($db, 'contact_email', '');
?>
<main class="home"><div class="wrap">
  <section class="hero" style="border-bottom:none;margin-bottom:0">
    <div>
      <span class="eyebrow">Kontak</span>
      <h1><?= e($contactTitle) ?></h1>
      <p style="margin-top:18px"><?= e($contactSubtitle) ?></p>
    </div>
  </section>

  <div class="contact-grid">
    <?php if ($contactTelegram): ?>
    <a class="contact-card contact-telegram" href="<?= e($contactTelegram) ?>" target="_blank" rel="noopener noreferrer">
      <div class="contact-card-icon">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221l-1.97 9.286c-.145.658-.537.818-1.084.508l-3-2.21-1.446 1.394c-.14.18-.357.295-.6.295l.213-3.054 5.56-5.022c.242-.213-.054-.334-.373-.121L8.32 13.617l-2.96-.924c-.64-.203-.658-.64.135-.954l11.566-4.458c.538-.196 1.006.128.832.941z"/></svg>
      </div>
      <h3>Telegram</h3>
      <p>Chat langsung via Telegram</p>
      <span class="contact-card-cta">Buka Telegram →</span>
    </a>
    <?php endif; ?>

    <?php if ($contactWhatsapp): ?>
    <a class="contact-card contact-whatsapp" href="<?= e($contactWhatsapp) ?>" target="_blank" rel="noopener noreferrer">
      <div class="contact-card-icon">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
      </div>
      <h3>WhatsApp</h3>
      <p>Kirim pesan via WhatsApp</p>
      <span class="contact-card-cta">Buka WhatsApp →</span>
    </a>
    <?php endif; ?>

    <?php if ($contactEmail): ?>
    <a class="contact-card contact-email" href="mailto:<?= e($contactEmail) ?>">
      <div class="contact-card-icon">
        <span class="material-symbols-rounded" style="font-size:32px">mail</span>
      </div>
      <h3>Email</h3>
      <p>Kirim email langsung</p>
      <span class="contact-card-cta">Kirim Email →</span>
    </a>
    <?php endif; ?>

    <?php if (!$contactTelegram && !$contactWhatsapp && !$contactEmail): ?>
    <div class="contact-empty">
      <span class="material-symbols-rounded" style="font-size:48px;opacity:0.3">contact_support</span>
      <p class="muted">Belum ada info kontak yang dikonfigurasi. Hubungi admin melalui halaman panel.</p>
    </div>
    <?php endif; ?>
  </div>
</div></main>

<?php elseif ($page === 'watch'):
    $id = (int)($_GET['id'] ?? 0);
    $s = $db->prepare('SELECT v.*, c.name category FROM videos v LEFT JOIN categories c ON c.id=v.category_id WHERE v.id=?');
    $s->bind_param('i', $id); $s->execute();
    $v = $s->get_result()->fetch_assoc();
    if (!$v) go('.');
    $base = dirname($v['source']);
    $hasHls = is_file(APP_ROOT . '/' . $base . '/master.m3u8');
    $has720 = is_file(APP_ROOT . '/' . $base . '/720p.m3u8');
    $has360 = is_file(APP_ROOT . '/' . $base . '/360p.m3u8');
    $userHasAccess = has_access() || admin(); // admin always bypasses token gate
    $tokenError    = $_SESSION['token_error'] ?? '';
    unset($_SESSION['token_error']);
    $currentUrl = '?page=watch&id=' . $id;
?>
<main class="watch"><div class="wrap">
  <div class="eyebrow"><span class="material-symbols-rounded" style="font-size:14px">play_circle</span> Now Playing · <?= e($v['category'] ?? 'Video') ?></div>
  <h1><?= e($v['title']) ?></h1>

  <?php if ($userHasAccess): ?>
  <!-- ── UNLOCKED: player normal ── -->
  <div class="player-wrap" data-video-id="<?= (int)$v['id'] ?>"
       data-hls="<?= $hasHls ? e(protected_media_url($base . '/master.m3u8')) : '' ?>"
       data-hls-720="<?= $has720 ? e(protected_media_url($base . '/720p.m3u8')) : '' ?>"
       data-hls-360="<?= $has360 ? e(protected_media_url($base . '/360p.m3u8')) : '' ?>"
       data-src="<?= e(protected_media_url($v['source'])) ?>"
       data-poster="<?= e(poster_url($v['poster'])) ?>"
       data-wm-text="<?= e($watermark_text) ?>"
       data-wm-pos="<?= e($watermark_position) ?>"
       data-wm-opacity="<?= (int)$watermark_opacity ?>">
    <video id="plyr-player" data-testid="video-player" playsinline crossorigin poster="<?= e(poster_url($v['poster'])) ?>">
      <?php if ($has720): ?><source src="<?= e(protected_media_url($base . '/720p.m3u8')) ?>" type="application/x-mpegURL" size="720"><?php endif; ?>
      <?php if ($has360): ?><source src="<?= e(protected_media_url($base . '/360p.m3u8')) ?>" type="application/x-mpegURL" size="360"><?php endif; ?>
      <source src="<?= e(protected_media_url($v['source'])) ?>" type="video/mp4">
    </video>
    <div class="watermark wm-<?= e($watermark_position) ?>" data-testid="watermark" style="opacity:<?= number_format($watermark_opacity / 100, 2) ?>"><?= e($watermark_text) ?></div>
  </div>
  <div class="watch-actions" aria-label="Pilihan video">
    <?php if ($hasHls): ?><div class="quality-picker"><span>Kualitas</span><button class="ghost small active" type="button" data-quality="0">Auto</button><?php if ($has720): ?><button class="ghost small" type="button" data-quality="720">720p</button><?php endif; ?><?php if ($has360): ?><button class="ghost small" type="button" data-quality="360">360p</button><?php endif; ?></div><?php endif; ?>
    <a class="button ghost small" href="?page=download&id=<?= (int)$v['id'] ?>"><span class="material-symbols-rounded">download</span> Unduh MP4</a>
  </div>
  <div class="video-meta">
    <div><span class="material-symbols-rounded">schedule</span><?= gmdate('i:s', (int)$v['duration_sec']) ?></div>
    <div><span class="material-symbols-rounded">visibility</span><?= (int)$v['views'] ?> tayang</div>
    <div><span class="material-symbols-rounded">save</span><?= number_format(((int)$v['size_bytes']) / 1048576, 1) ?> MB</div>
    <?php if ($hasHls): ?><div><span class="material-symbols-rounded">high_quality</span>HLS adaptive</div><?php endif; ?>
  </div>

  <?php else: ?>
  <!-- ── PREVIEW: 15s preview + token gate ── -->
  <?php $hasPreview = is_file(APP_ROOT . '/' . ltrim(dirname($v['source']), '/') . '/preview.mp4'); ?>
  <div class="player-wrap preview-player" id="preview-player"
       data-video-id="<?= (int)$v['id'] ?>"
       data-preview-url="<?= $hasPreview ? e(preview_url($v['source'])) : '' ?>"
       data-src="<?= e(protected_media_url($v['source'])) ?>"
       data-poster="<?= e(poster_url($v['poster'])) ?>"
       data-preview-sec="15"
       data-wm-text="<?= e($watermark_text) ?>"
       data-wm-pos="<?= e($watermark_position) ?>"
       data-wm-opacity="<?= (int)$watermark_opacity ?>">
    <video id="preview-video" data-testid="preview-player" playsinline crossorigin
           poster="<?= e(poster_url($v['poster'])) ?>">
      <?php if ($hasPreview): ?>
        <source src="<?= e(preview_url($v['source'])) ?>" type="video/mp4">
      <?php endif; ?>
    </video>
    <div class="watermark wm-<?= e($watermark_position) ?>" style="opacity:<?= number_format($watermark_opacity / 100, 2) ?>"><?= e($watermark_text) ?></div>
    <div class="preview-overlay" id="preview-overlay">
      <div class="preview-overlay-content">
        <span class="material-symbols-rounded" style="font-size:42px;color:var(--accent);opacity:0.85">lock</span>
        <p class="preview-msg">Preview berakhir. Masukkan token untuk menonton penuh.</p>
        <div class="preview-actions">
          <button class="button" id="open-token-modal" type="button">
            <span class="material-symbols-rounded">vpn_key</span> Masukkan Token
          </button>
          <a class="button ghost" href="?page=contact">
            <span class="material-symbols-rounded">support_agent</span> Beli Akses Token
          </a>
        </div>
      </div>
    </div>
  </div>
  <div class="video-meta">
    <div><span class="material-symbols-rounded">schedule</span><?= gmdate('i:s', (int)$v['duration_sec']) ?></div>
    <div><span class="material-symbols-rounded">play_circle</span>Preview 15 detik</div>
  </div>

  <!-- ── TOKEN MODAL ── -->
  <div class="token-modal-overlay" id="token-modal" role="dialog" aria-modal="true" aria-labelledby="token-modal-title">
    <div class="token-modal-card">
      <button class="token-modal-close" id="close-token-modal" type="button" aria-label="Tutup">&times;</button>
      <div class="token-modal-icon"><span class="material-symbols-rounded">vpn_key</span></div>
      <h2 id="token-modal-title">Token Akses Diperlukan</h2>
      <p class="token-modal-desc">Masukkan token yang diberikan untuk mengakses seluruh koleksi video.</p>
      <?php if ($tokenError): ?>
        <p class="token-modal-error"><?= e($tokenError) ?></p>
      <?php endif; ?>
      <form method="post" action="?page=verify-token" class="token-modal-form">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <input type="hidden" name="redirect" value="<?= e($currentUrl) ?>">
        <input
          type="text"
          name="token"
          id="token-input"
          placeholder="XXXX-XXXX-XXXX"
          autocomplete="off"
          autocapitalize="characters"
          spellcheck="false"
          maxlength="14"
          required
          class="token-input"
        >
        <button type="submit" class="button token-submit">
          <span class="material-symbols-rounded">login</span> Verifikasi Token
        </button>
      </form>
      <p class="token-modal-hint">Belum punya token? Beli atau hubungi admin untuk mendapatkan akses.</p>
      <a class="token-contact-btn" href="?page=contact">
        <span class="material-symbols-rounded">support_agent</span> Beli Akses Token
      </a>
      <?php if ($midtransEnabled && $midtransClientKey): ?>
      <div class="token-purchase" id="token-purchase" data-price="<?= $midtransPrice ?>">
        <strong>Beli token — Rp<?= number_format($midtransPrice, 0, ',', '.') ?></strong>
        <p>Bayar aman melalui Midtrans. Token ditampilkan otomatis setelah pembayaran terkonfirmasi.</p>
        <input id="buyer-name" maxlength="120" placeholder="Nama Anda" autocomplete="name">
        <input id="buyer-contact" maxlength="200" placeholder="Username / nomor Telegram atau WhatsApp">
        <button class="button" id="buy-token" type="button"><span class="material-symbols-rounded">payments</span> Beli via Midtrans</button>
        <p class="token-purchase-status" id="purchase-status" aria-live="polite"></p>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

</div></main>

<?php else:
    $cat = (int)($_GET['category'] ?? 0);
    $where = $cat ? ' WHERE v.category_id=' . $cat : '';
    $videos = $db->query('SELECT v.*, c.name category FROM videos v LEFT JOIN categories c ON c.id=v.category_id' . $where . ' ORDER BY v.created_at DESC');
    $categories = $db->query('SELECT * FROM categories ORDER BY name');
?>
<main class="home"><div class="wrap">
  <section class="hero">
    <div>
      <span class="eyebrow">Koleksi Video · 2026</span>
      <h1>Layar untuk cerita yang ingin tinggal lebih lama.</h1>
      <p style="margin-top:18px"><?= e($desc) ?></p>
    </div>
  </section>
  <div class="filters" data-testid="filters">
    <a class="<?= $cat === 0 ? 'active' : '' ?>" href=".">Semua</a>
    <?php while ($c = $categories->fetch_assoc()): ?>
      <a class="<?= $cat === (int)$c['id'] ? 'active' : '' ?>" href="?category=<?= $c['id'] ?>"><?= e($c['name']) ?></a>
    <?php endwhile; ?>
  </div>
  <?php if ($videos->num_rows === 0): ?>
    <div class="empty-state">
      <svg viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <circle cx="100" cy="100" r="80" stroke="var(--border)" stroke-width="2" stroke-dasharray="6 4"/>
        <polygon points="85,65 85,135 140,100" fill="var(--accent)" opacity="0.3"/>
        <polygon points="85,65 85,135 140,100" stroke="var(--accent)" stroke-width="2" stroke-linejoin="round" fill="none"/>
      </svg>
      <span class="eyebrow">Koleksi kosong</span>
      <h2>Belum ada video.</h2>
      <p>Masuk ke panel admin dan unggah karya pertama Anda untuk memulai arsip.</p>
      <?php if (admin()): ?>
        <a href="?page=admin" class="button"><span class="material-symbols-rounded">cloud_upload</span> Unggah Video</a>
      <?php else: ?>
        <a href="?page=login" class="button"><span class="material-symbols-rounded">login</span> Masuk ke Panel</a>
      <?php endif; ?>
    </div>
  <?php else: ?>
  <section class="gallery" data-testid="gallery">
    <?php while ($v = $videos->fetch_assoc()):
        $posterFs = APP_ROOT . '/' . ltrim($v['poster'], '/');
        $hasPoster = is_file($posterFs);
    ?>
      <article class="card">
        <a href="?page=watch&id=<?= $v['id'] ?>">
          <div class="poster">
            <?php if ($hasPoster): ?><img src="<?= e(poster_url($v['poster'])) ?>" alt="<?= e($v['title']) ?>" loading="lazy">
            <?php else: ?><span><?= str_pad((string)$v['id'], 2, '0', STR_PAD_LEFT) ?></span><?php endif; ?>
          </div>
          <div class="cardmeta">
            <span class="kick"><?= e($v['category'] ?? 'Video') ?></span>
            <h3><?= e($v['title']) ?></h3>
            <small><?= gmdate('i:s', (int)$v['duration_sec']) ?> · <?= (int)$v['views'] ?> tayang</small>
          </div>
        </a>
      </article>
    <?php endwhile; ?>
  </section>
  <?php endif; ?>
</div></main>
<?php endif; ?>
<script>
// Register Service Worker
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js').catch(() => {});
  });
}
</script>
</body>
</html>
