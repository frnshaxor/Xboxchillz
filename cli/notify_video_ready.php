<?php
// CLI notifier — called by the FFmpeg worker after HLS is ready.
// Usage: php cli/notify_video_ready.php <video_slug>
declare(strict_types=1);
require __DIR__ . '/../lib/Telegram.php';

$slug = $argv[1] ?? '';
if (!$slug) exit(1);

$db = new mysqli(
    getenv('DB_HOST') ?: '127.0.0.1',
    getenv('DB_USER') ?: 'arsip',
    getenv('DB_PASS') ?: '',
    getenv('DB_NAME') ?: 'arsip_layar'
);
if ($db->connect_error) exit(2);

function s(mysqli $db, string $k, string $d = ''): string {
    $st = $db->prepare('SELECT value FROM settings WHERE name=?');
    $st->bind_param('s', $k); $st->execute();
    return $st->get_result()->fetch_assoc()['value'] ?? $d;
}

if (s($db, 'telegram_enabled', '0') !== '1') exit(0);
$token = s($db, 'telegram_bot_token', '');
$chat  = s($db, 'telegram_chat_id', '');
if (!$token || !$chat) exit(0);

$q = $db->prepare('SELECT v.id,v.title,v.slug,v.duration_sec,v.poster,c.name category FROM videos v LEFT JOIN categories c ON c.id=v.category_id WHERE v.slug=?');
$q->bind_param('s', $slug); $q->execute();
$v = $q->get_result()->fetch_assoc();
if (!$v) exit(3);

$site = s($db, 'site_name', 'Arsip Layar');
$base_url = getenv('SITE_URL') ?: 'http://' . (gethostname() ?: 'localhost');
$watch_url = rtrim($base_url, '/') . '/?page=watch&id=' . (int)$v['id'];
$duration = gmdate('i:s', (int)$v['duration_sec']);

$caption = "🎬 <b>" . htmlspecialchars($v['title'], ENT_QUOTES) . "</b>\n"
         . "📁 " . htmlspecialchars($v['category'] ?? 'Umum', ENT_QUOTES) . " · ⏱ $duration\n"
         . "✅ HLS siap ditonton\n\n"
         . "🔗 $watch_url\n\n"
         . "<i>— $site</i>";

$tg = new Telegram($token);
$poster_fs = __DIR__ . '/../' . ltrim($v['poster'], '/');
if (is_file($poster_fs)) {
    $r = $tg->sendPhoto($chat, $poster_fs, $caption);
} else {
    $r = $tg->sendMessage($chat, $caption);
}
if (!empty($r['ok'])) {
    // Log to activity
    $ip = 'cli';
    $s = $db->prepare('INSERT INTO activity_log(admin_id,action,detail,ip) VALUES(NULL,?,?,?)');
    $action = 'telegram_notify'; $detail = $v['slug'];
    $s->bind_param('sss', $action, $detail, $ip); $s->execute();
    exit(0);
}
fwrite(STDERR, "TG error: " . json_encode($r) . "\n");
exit(4);
