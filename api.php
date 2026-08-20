<?php
require __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
maintenance_guard($db);

$op = $_GET['op'] ?? '';
function j(array $data, int $code = 200): never { http_response_code($code); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function post_csrf_ok(): bool { return hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? ''); }

// -------- Public state --------
if ($op === 'state') {
    j([
        'csrf'  => csrf(),
        'theme' => setting($db, 'theme_key', 'obsidian'),
        'site'  => setting($db, 'site_name', 'Arsip Layar'),
        'admin' => admin(),
    ]);
}

// -------- Analytics event (public) --------
if ($op === 'event' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!post_csrf_ok()) j(['error' => 'Token tidak valid'], 419);
    $event = preg_replace('/[^a-z0-9_]/', '', strtolower($_POST['event'] ?? ''));
    if (!$event) j(['ok' => true]);
    $path = substr((string)($_POST['path'] ?? '/'), 0, 255);
    $visitor = hash('sha256', client_ip() . '|' . date('Y-m-d') . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $device = substr((string)($_POST['device'] ?? 'unknown'), 0, 40);
    $browser = substr((string)($_POST['browser'] ?? 'unknown'), 0, 80);
    $ref = substr((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, 255);
    $video_id = isset($_POST['video_id']) ? (int)$_POST['video_id'] : null;
    $progress = isset($_POST['progress']) ? max(0, min(86400, (int)$_POST['progress'])) : null;
    $s = $db->prepare('INSERT INTO analytics_events(event,path,visitor_hash,video_id,progress_sec,device,browser,referrer) VALUES(?,?,?,?,?,?,?,?)');
    $s->bind_param('sssiisss', $event, $path, $visitor, $video_id, $progress, $device, $browser, $ref);
    $s->execute();
    if ($event === 'video_start' && $video_id) {
        $vu = $db->prepare('UPDATE videos SET views = views + 1 WHERE id = ?'); $vu->bind_param('i', $video_id); $vu->execute();
    }
    j(['ok' => true]);
}

// -------- Insights (admin) --------
if ($op === 'insights') {
    need_admin();
    $days = max(1, min(90, (int)($_GET['days'] ?? 30)));
    $since = date('Y-m-d H:i:s', time() - $days * 86400);

    $q = $db->prepare('SELECT COUNT(*) total, COUNT(DISTINCT visitor_hash) visitors, SUM(event="video_start") video_views, SUM(event="page_view") page_views FROM analytics_events WHERE created_at>=?');
    $q->bind_param('s', $since); $q->execute();
    $metrics = $q->get_result()->fetch_assoc();

    $q = $db->prepare('SELECT path, COUNT(*) views FROM analytics_events WHERE event="page_view" AND created_at>=? GROUP BY path ORDER BY views DESC LIMIT 10');
    $q->bind_param('s', $since); $q->execute();
    $popular = $q->get_result()->fetch_all(MYSQLI_ASSOC);

    $q = $db->prepare("SELECT COALESCE(NULLIF(referrer,''),'(langsung)') src, COUNT(*) hits FROM analytics_events WHERE event='page_view' AND created_at>=? GROUP BY src ORDER BY hits DESC LIMIT 10");
    $q->bind_param('s', $since); $q->execute();
    $sources = $q->get_result()->fetch_all(MYSQLI_ASSOC);

    // hour/day heatmap  -> [dow(0=Sun..6=Sat) x hour(0..23)]
    $q = $db->prepare('SELECT DAYOFWEEK(created_at)-1 dow, HOUR(created_at) hr, COUNT(*) c FROM analytics_events WHERE event="page_view" AND created_at>=? GROUP BY dow, hr');
    $q->bind_param('s', $since); $q->execute();
    $heatmap = array_fill(0, 7, array_fill(0, 24, 0));
    foreach ($q->get_result() as $row) { $heatmap[(int)$row['dow']][(int)$row['hr']] = (int)$row['c']; }

    // retention per video: avg watched, max watched (progress_sec) as % of duration
    $q = $db->prepare('SELECT v.id, v.title, v.duration_sec, AVG(a.progress_sec) avg_sec, MAX(a.progress_sec) max_sec, COUNT(a.id) samples FROM videos v LEFT JOIN analytics_events a ON a.video_id=v.id AND a.event="video_progress" AND a.created_at>=? GROUP BY v.id ORDER BY v.created_at DESC LIMIT 20');
    $q->bind_param('s', $since); $q->execute();
    $retention = $q->get_result()->fetch_all(MYSQLI_ASSOC);

    // device breakdown
    $q = $db->prepare('SELECT device, COUNT(*) c FROM analytics_events WHERE event="page_view" AND created_at>=? GROUP BY device');
    $q->bind_param('s', $since); $q->execute();
    $devices = $q->get_result()->fetch_all(MYSQLI_ASSOC);

    j(compact('metrics', 'popular', 'sources', 'heatmap', 'retention', 'devices') + ['days' => $days]);
}

// -------- Change theme --------
if ($op === 'theme' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    need_admin(); check_csrf();
    $allowed = ['ivory', 'obsidian', 'emerald'];
    $theme = $_POST['theme'] ?? '';
    if (!in_array($theme, $allowed, true)) j(['error' => 'Tema tidak valid'], 422);
    set_setting($db, 'theme_key', $theme);
    log_activity($db, (int)$_SESSION['admin_id'], 'theme_change', $theme);
    j(['ok' => true, 'theme' => $theme]);
}

// -------- Cache bust --------
if ($op === 'cache_bust' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    need_admin(); check_csrf();
    $ver = (int)setting($db, 'cache_ver', '1') + 1;
    set_setting($db, 'cache_ver', (string)$ver);
    // clear on-disk cache dir
    foreach (glob(CACHE_DIR . '/*') ?: [] as $f) @unlink($f);
    log_activity($db, (int)$_SESSION['admin_id'], 'cache_bust', 'ver=' . $ver);
    j(['ok' => true, 'cache_ver' => $ver]);
}

// -------- Maintenance toggle --------
if ($op === 'maintenance' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    need_admin(); check_csrf();
    $on = ($_POST['on'] ?? '') === '1' ? '1' : '0';
    set_setting($db, 'maintenance_mode', $on);
    log_activity($db, (int)$_SESSION['admin_id'], 'maintenance', 'on=' . $on);
    j(['ok' => true, 'maintenance' => $on]);
}

// -------- Upload limit setting --------
if ($op === 'upload_limit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    need_admin(); check_csrf();
    $mb = max(10, min(20480, (int)($_POST['mb'] ?? 2048)));
    set_setting($db, 'upload_max_mb', (string)$mb);
    log_activity($db, (int)$_SESSION['admin_id'], 'upload_limit', $mb . 'MB');
    j(['ok' => true, 'mb' => $mb]);
}

// -------- Watermark settings --------
if ($op === 'watermark' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    need_admin(); check_csrf();
    $text = trim((string)($_POST['text'] ?? ''));
    if ($text === '') $text = 'Codename F';
    if (mb_strlen($text) > 40) $text = mb_substr($text, 0, 40);
    $pos = in_array($_POST['position'] ?? 'br', ['tl', 'tr', 'bl', 'br', 'center'], true) ? $_POST['position'] : 'br';
    $op_val = max(10, min(100, (int)($_POST['opacity'] ?? 60)));
    set_setting($db, 'watermark_text', $text);
    set_setting($db, 'watermark_position', $pos);
    set_setting($db, 'watermark_opacity', (string)$op_val);
    log_activity($db, (int)$_SESSION['admin_id'], 'watermark', "$text|$pos|$op_val");
    j(['ok' => true, 'text' => $text, 'position' => $pos, 'opacity' => $op_val]);
}
if ($op === 'watermark_get') {
    j([
        'text' => setting($db, 'watermark_text', 'Codename F'),
        'position' => setting($db, 'watermark_position', 'br'),
        'opacity' => (int)setting($db, 'watermark_opacity', '60'),
    ]);
}

// -------- Telegram integration --------
if ($op === 'telegram_get') {
    need_admin();
    $tok = setting($db, 'telegram_bot_token', '');
    $mask = $tok ? (substr($tok, 0, 8) . '…' . substr($tok, -6)) : '';
    j([
        'has_token' => (bool)$tok,
        'token_mask' => $mask,
        'chat_id' => setting($db, 'telegram_chat_id', ''),
        'enabled' => setting($db, 'telegram_enabled', '0') === '1',
    ]);
}
if ($op === 'telegram_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    need_admin(); check_csrf();
    if (isset($_POST['token'])) {
        $tok = trim((string)$_POST['token']);
        // Basic sanity check: bot tokens are like "123456:ABC-..."
        if ($tok !== '' && !preg_match('/^\d+:[A-Za-z0-9_-]{20,}$/', $tok)) j(['error' => 'Format token tidak valid'], 422);
        if ($tok !== '') set_setting($db, 'telegram_bot_token', $tok);
    }
    if (isset($_POST['chat_id'])) {
        $cid = trim((string)$_POST['chat_id']);
        if ($cid !== '' && !preg_match('/^-?\d{1,20}$/', $cid)) j(['error' => 'Chat ID harus berupa angka'], 422);
        set_setting($db, 'telegram_chat_id', $cid);
    }
    if (isset($_POST['enabled'])) {
        set_setting($db, 'telegram_enabled', $_POST['enabled'] === '1' ? '1' : '0');
    }
    log_activity($db, (int)$_SESSION['admin_id'], 'telegram_save', '');
    j(['ok' => true]);
}
if ($op === 'telegram_test' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    need_admin(); check_csrf();
    require_once __DIR__ . '/lib/Telegram.php';
    $tok = setting($db, 'telegram_bot_token', '');
    $cid = setting($db, 'telegram_chat_id', '');
    if (!$tok) j(['error' => 'Token belum diisi'], 422);
    $tg = new Telegram($tok);
    $me = $tg->getMe();
    if (empty($me['ok'])) j(['error' => 'Token ditolak Telegram', 'raw' => $me], 422);
    if (!$cid) j(['ok' => true, 'bot' => $me['result'], 'note' => 'Token valid tapi chat_id belum diisi.']);
    $site = setting($db, 'site_name', 'Arsip Layar');
    $msg = "✅ <b>Telegram tersambung.</b>\n<i>$site</i> · " . date('d M Y H:i');
    $send = $tg->sendMessage($cid, $msg);
    if (empty($send['ok'])) j(['error' => 'Bot tidak bisa kirim ke chat_id ini. Pastikan Anda sudah /start bot Anda.', 'raw' => $send], 422);
    log_activity($db, (int)$_SESSION['admin_id'], 'telegram_test', 'ok');
    j(['ok' => true, 'bot' => $me['result'], 'chat_id' => $cid]);
}
if ($op === 'telegram_updates') {
    need_admin();
    require_once __DIR__ . '/lib/Telegram.php';
    $tok = setting($db, 'telegram_bot_token', '');
    if (!$tok) j(['error' => 'Token belum diisi'], 422);
    $tg = new Telegram($tok);
    $u = $tg->getUpdates();
    if (empty($u['ok'])) j(['error' => 'Gagal ambil updates', 'raw' => $u], 422);
    $chats = [];
    foreach (($u['result'] ?? []) as $up) {
        $m = $up['message'] ?? $up['edited_message'] ?? null;
        if (!$m) continue;
        $c = $m['chat'];
        $chats[$c['id']] = [
            'id' => $c['id'],
            'title' => $c['title'] ?? trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')),
            'type' => $c['type'],
            'username' => $c['username'] ?? null,
            'last_text' => mb_substr((string)($m['text'] ?? '[non-text]'), 0, 80),
            'when' => date('d M H:i', (int)($m['date'] ?? 0)),
        ];
    }
    j(['ok' => true, 'chats' => array_values($chats)]);
}

// -------- Backup DB --------
if ($op === 'backup' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    need_admin(); check_csrf();
    $file = 'arsip_layar_' . date('Ymd_His') . '.sql.gz';
    $path = BACKUP_DIR . '/' . $file;
    $cmd = sprintf('mysqldump --single-transaction --routines --triggers -h%s -u%s -p%s %s | gzip -9 > %s 2>&1',
        escapeshellarg(getenv('DB_HOST') ?: '127.0.0.1'),
        escapeshellarg(getenv('DB_USER') ?: 'arsip'),
        escapeshellarg(getenv('DB_PASS') ?: ''),
        escapeshellarg(getenv('DB_NAME') ?: 'arsip_layar'),
        escapeshellarg($path));
    exec($cmd, $out, $rc);
    if ($rc !== 0 || !is_file($path)) j(['error' => 'Backup gagal', 'rc' => $rc, 'log' => implode("\n", $out)], 500);
    $size = filesize($path) ?: 0;
    $s = $db->prepare('INSERT INTO backups(file,size_bytes) VALUES(?,?)');
    $s->bind_param('si', $file, $size); $s->execute();
    log_activity($db, (int)$_SESSION['admin_id'], 'backup', $file);
    j(['ok' => true, 'file' => $file, 'size' => $size]);
}
if ($op === 'backup_list') {
    need_admin();
    $rows = $db->query('SELECT id,file,size_bytes,created_at FROM backups ORDER BY id DESC LIMIT 20')->fetch_all(MYSQLI_ASSOC);
    j(['items' => $rows]);
}
if ($op === 'backup_download') {
    need_admin();
    $file = basename($_GET['file'] ?? '');
    $path = BACKUP_DIR . '/' . $file;
    if (!preg_match('/^arsip_layar_[0-9_]+\.sql\.gz$/', $file) || !is_file($path)) { http_response_code(404); exit('Tidak ditemukan.'); }
    header('Content-Type: application/gzip');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    header('Content-Length: ' . filesize($path));
    log_activity($db, (int)$_SESSION['admin_id'], 'backup_download', $file);
    readfile($path); exit;
}

// -------- Activity log viewer --------
if ($op === 'activity') {
    need_admin();
    $rows = $db->query('SELECT a.id,a.action,a.detail,a.ip,a.created_at,ad.name FROM activity_log a LEFT JOIN admins ad ON ad.id=a.admin_id ORDER BY a.id DESC LIMIT 100')->fetch_all(MYSQLI_ASSOC);
    $fails = $db->query('SELECT ip,email,reason,created_at FROM login_attempts WHERE success=0 ORDER BY id DESC LIMIT 50')->fetch_all(MYSQLI_ASSOC);
    j(['activity' => $rows, 'failed_logins' => $fails]);
}

// -------- 2FA setup: get provisioning URI + secret --------
if ($op === '2fa_setup') {
    need_admin();
    $secret = base32_encode(random_bytes(20));
    $_SESSION['pending_totp_secret'] = $secret;
    $site = rawurlencode(setting($db, 'site_name', 'Arsip Layar'));
    $label = rawurlencode(($_SESSION['admin_name'] ?? 'admin') . '@arsiplayar');
    $uri = "otpauth://totp/{$site}:{$label}?secret={$secret}&issuer={$site}&period=30&digits=6";
    j(['secret' => $secret, 'otpauth' => $uri]);
}
if ($op === '2fa_enable' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    need_admin(); check_csrf();
    $code = preg_replace('/\D/', '', (string)($_POST['code'] ?? ''));
    $secret = $_SESSION['pending_totp_secret'] ?? '';
    if (!$secret || !totp_verify($secret, $code)) j(['error' => 'Kode salah / kedaluwarsa'], 422);
    $s = $db->prepare('UPDATE admins SET totp_secret=?, totp_enabled=1 WHERE id=?');
    $aid = (int)$_SESSION['admin_id'];
    $s->bind_param('si', $secret, $aid); $s->execute();
    unset($_SESSION['pending_totp_secret']);
    log_activity($db, $aid, '2fa_enable', 'ok');
    j(['ok' => true]);
}
if ($op === '2fa_disable' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    need_admin(); check_csrf();
    $aid = (int)$_SESSION['admin_id'];
    $td = $db->prepare('UPDATE admins SET totp_secret=NULL, totp_enabled=0 WHERE id=?'); $td->bind_param('i', $aid); $td->execute();
    log_activity($db, $aid, '2fa_disable', '');
    j(['ok' => true]);
}

// -------- Token management (admin) --------
if ($op === 'token_list') {
    need_admin();
    $rows = $db->query('SELECT id, token, label, contact_type, contact_value, status, use_count, last_used_at, created_at FROM access_tokens ORDER BY created_at DESC')->fetch_all(MYSQLI_ASSOC);
    j(['tokens' => $rows]);
}

if ($op === 'token_create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    need_admin(); check_csrf();
    $label        = trim((string)($_POST['label'] ?? ''));
    $contact_type = (string)($_POST['contact_type'] ?? 'telegram');
    $contact_value = trim((string)($_POST['contact_value'] ?? ''));

    if (strlen($label) < 2)  j(['error' => 'Label minimal 2 karakter'], 422);
    if (!in_array($contact_type, ['telegram', 'whatsapp', 'facebook'], true)) j(['error' => 'Tipe kontak tidak valid'], 422);
    if (strlen($contact_value) < 2) j(['error' => 'Kontak wajib diisi'], 422);

    // Generate unique token — format XXXX-XXXX-XXXX
    $tok = ''; $exists = null; $attempts = 0;
    do {
        $raw = generate_token(12);
        $tok = substr($raw, 0, 4) . '-' . substr($raw, 4, 4) . '-' . substr($raw, 8, 4);
        $check = $db->prepare('SELECT id FROM access_tokens WHERE token=?');
        $check->bind_param('s', $tok); $check->execute();
        $exists = $check->get_result()->fetch_assoc();
        $attempts++;
    } while ($exists && $attempts < 10);

    if ($exists) j(['error' => 'Gagal generate token unik, coba lagi'], 500);

    $aid = (int)$_SESSION['admin_id'];
    $status = 'active';
    $s = $db->prepare('INSERT INTO access_tokens(token, label, contact_type, contact_value, status, created_by) VALUES(?,?,?,?,?,?)');
    $s->bind_param('sssssi', $tok, $label, $contact_type, $contact_value, $status, $aid);
    $s->execute();
    $new_id = $db->insert_id;
    log_activity($db, $aid, 'token_create', "label=$label token=$tok");
    j(['ok' => true, 'token' => [
        'id' => $new_id, 'token' => $tok, 'label' => $label,
        'contact_type' => $contact_type, 'contact_value' => $contact_value,
        'status' => $status, 'use_count' => 0, 'last_used_at' => null,
        'created_at' => date('Y-m-d H:i:s'),
    ]]);
}

if ($op === 'token_edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    need_admin(); check_csrf();
    $id           = (int)($_POST['id'] ?? 0);
    $label        = trim((string)($_POST['label'] ?? ''));
    $contact_type = (string)($_POST['contact_type'] ?? 'telegram');
    $contact_value = trim((string)($_POST['contact_value'] ?? ''));

    if (!$id) j(['error' => 'ID tidak valid'], 422);
    if (strlen($label) < 2)  j(['error' => 'Label minimal 2 karakter'], 422);
    if (!in_array($contact_type, ['telegram', 'whatsapp', 'facebook'], true)) j(['error' => 'Tipe kontak tidak valid'], 422);
    if (strlen($contact_value) < 2) j(['error' => 'Kontak wajib diisi'], 422);

    $s = $db->prepare('UPDATE access_tokens SET label=?, contact_type=?, contact_value=? WHERE id=?');
    $s->bind_param('sssi', $label, $contact_type, $contact_value, $id);
    $s->execute();
    if ($s->affected_rows === 0) j(['error' => 'Token tidak ditemukan atau tidak ada perubahan'], 404);
    log_activity($db, (int)$_SESSION['admin_id'], 'token_edit', "id=$id label=$label");
    j(['ok' => true]);
}

if ($op === 'token_toggle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    need_admin(); check_csrf();
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) j(['error' => 'ID tidak valid'], 422);

    $s = $db->prepare('SELECT status FROM access_tokens WHERE id=?');
    $s->bind_param('i', $id); $s->execute();
    $row = $s->get_result()->fetch_assoc();
    if (!$row) j(['error' => 'Token tidak ditemukan'], 404);

    $new_status = $row['status'] === 'active' ? 'suspended' : 'active';
    $u = $db->prepare('UPDATE access_tokens SET status=? WHERE id=?');
    $u->bind_param('si', $new_status, $id); $u->execute();
    log_activity($db, (int)$_SESSION['admin_id'], 'token_toggle', "id=$id status=$new_status");
    j(['ok' => true, 'status' => $new_status]);
}

if ($op === 'token_delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    need_admin(); check_csrf();
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) j(['error' => 'ID tidak valid'], 422);
    $s = $db->prepare('DELETE FROM access_tokens WHERE id=?');
    $s->bind_param('i', $id); $s->execute();
    log_activity($db, (int)$_SESSION['admin_id'], 'token_delete', "id=$id");
    j(['ok' => true]);
}

// -------- Midtrans Snap checkout (public, server key never leaves PHP) --------
if ($op === 'midtrans_checkout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!post_csrf_ok()) j(['error' => 'Token tidak valid'], 419);
    if (setting($db, 'midtrans_enabled', '0') !== '1') j(['error' => 'Pembayaran online belum tersedia'], 503);
    $serverKey = setting($db, 'midtrans_server_key', '');
    $clientKey = setting($db, 'midtrans_client_key', '');
    if (!$serverKey || !$clientKey) j(['error' => 'Pembayaran sedang dikonfigurasi'], 503);
    $name = trim((string)($_POST['name'] ?? ''));
    $contact = trim((string)($_POST['contact'] ?? ''));
    if (mb_strlen($name) < 2 || mb_strlen($name) > 120 || mb_strlen($contact) < 2 || mb_strlen($contact) > 200) j(['error' => 'Nama dan kontak wajib diisi'], 422);
    $ip = client_ip();
    $rate = $db->prepare('SELECT COUNT(*) c FROM payment_orders WHERE client_ip=? AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)');
    $rate->bind_param('s', $ip); $rate->execute();
    if ((int)($rate->get_result()->fetch_assoc()['c'] ?? 0) >= 5) j(['error' => 'Terlalu banyak checkout. Coba lagi dalam 15 menit.'], 429);
    $amount = max(1000, (int)setting($db, 'midtrans_token_price', '50000'));
    $orderId = 'AL-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(4)));
    $secret = bin2hex(random_bytes(24));
    $secretHash = hash('sha256', $secret);
    $status = 'pending';
    $insert = $db->prepare('INSERT INTO payment_orders(order_id,buyer_name,buyer_contact,amount,status,access_secret_hash,client_ip) VALUES(?,?,?,?,?,?,?)');
    $insert->bind_param('sssisss', $orderId, $name, $contact, $amount, $status, $secretHash, $ip); $insert->execute();
    $mode = setting($db, 'midtrans_mode', 'sandbox') === 'production' ? 'production' : 'sandbox';
    $isHttps = !empty($_SERVER['HTTPS']) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    if ($mode === 'production' && !$isHttps) j(['error' => 'Checkout Production membutuhkan HTTPS aktif'], 503);
    $payload = [
        'transaction_details' => ['order_id' => $orderId, 'gross_amount' => $amount],
        'item_details' => [['id' => 'access-token', 'price' => $amount, 'quantity' => 1, 'name' => 'Token akses ' . setting($db, 'site_name', 'Arsip Layar')]],
        'customer_details' => ['first_name' => $name],
        'callbacks' => ['finish' => (($_SERVER['HTTPS'] ?? '') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/?page=payment-finish'],
    ];
    $ch = curl_init(midtrans_endpoint($mode));
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($payload), CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json', 'Authorization: Basic ' . base64_encode($serverKey . ':')]]);
    $response = curl_exec($ch); $curlError = curl_error($ch); $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    $decoded = is_string($response) ? json_decode($response, true) : null;
    if ($http < 200 || $http >= 300 || !is_array($decoded) || empty($decoded['token'])) {
        $fail = $db->prepare("UPDATE payment_orders SET status='gateway_error' WHERE order_id=?"); $fail->bind_param('s', $orderId); $fail->execute();
        j(['error' => 'Gagal membuat transaksi pembayaran', 'detail' => $curlError ?: ($decoded['status_message'] ?? 'gateway_error')], 502);
    }
    $snapToken = (string)$decoded['token'];
    $update = $db->prepare('UPDATE payment_orders SET snap_token=? WHERE order_id=?'); $update->bind_param('ss', $snapToken, $orderId); $update->execute();
    j(['ok' => true, 'order_id' => $orderId, 'access_secret' => $secret, 'snap_token' => $snapToken, 'client_key' => $clientKey, 'mode' => $mode]);
}

if ($op === 'payment_status') {
    $orderId = (string)($_GET['order_id'] ?? ''); $secret = (string)($_GET['access_secret'] ?? '');
    if (!preg_match('/^AL-[A-Z0-9-]{10,50}$/', $orderId) || !preg_match('/^[a-f0-9]{48}$/', $secret)) j(['error' => 'Order tidak valid'], 422);
    $q = $db->prepare('SELECT p.order_id,p.status,p.token_id,t.token FROM payment_orders p LEFT JOIN access_tokens t ON t.id=p.token_id WHERE p.order_id=? AND p.access_secret_hash=?');
    $hash = hash('sha256', $secret); $q->bind_param('ss', $orderId, $hash); $q->execute(); $row = $q->get_result()->fetch_assoc();
    if (!$row) j(['error' => 'Order tidak ditemukan'], 404);
    j(['ok' => true, 'status' => $row['status'], 'token' => $row['status'] === 'settlement' ? $row['token'] : null]);
}

// -------- Midtrans controls and order ledger (admin) --------
if ($op === 'midtrans_orders') {
    need_admin();
    $rows = $db->query('SELECT p.order_id,p.buyer_name,p.buyer_contact,p.amount,p.status,p.payment_type,p.token_id,p.paid_at,p.created_at,t.token FROM payment_orders p LEFT JOIN access_tokens t ON t.id=p.token_id ORDER BY p.id DESC LIMIT 100')->fetch_all(MYSQLI_ASSOC);
    j(['orders' => $rows]);
}

j(['error' => 'Not found'], 404);
