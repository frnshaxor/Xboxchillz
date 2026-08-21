<?php
declare(strict_types=1);

/**
 * Health Check — runs periodically via systemd timer to detect and fix video issues.
 *
 * Checks:
 *   H1: Stuck processing (processing > 30 min) → auto-retry worker
 *   H2: Invalid slugs (starts with -/_ or contains invalid chars) → auto-rename
 *   H3: Missing HLS files (status=ready but master.m3u8 missing) → auto-retry worker
 *   H4: Missing poster (status=ready but poster.jpg missing) → auto-retry worker
 *   H5: Unplayable video (slug fails ALLOWED_PATTERN) → auto-rename slug
 *   H6: Low disk space (< 1GB) → log warning only
 *   H7: Stale upload sessions (>24h) → auto-cleanup
 *
 * Usage:
 *   php cli/health_check.php              — Run once
 *   php cli/health_check.php --dry-run    — Run checks without auto-fixing
 *   php cli/health_check.php --verbose    — Extra output for debugging
 */

require __DIR__ . '/../app/bootstrap.php';

$conn = Connection::getInstance();
$db = $conn->db();

$isDryRun  = in_array('--dry-run', $argv ?? []);
$isVerbose = in_array('--verbose', $argv ?? []);

// ─── Load Telegram for alerts ───
$telegramToken  = setting($db, 'telegram_bot_token', '');
$telegramChatId = setting($db, 'telegram_chat_id', '');
$telegramEnabled = setting($db, 'telegram_enabled', '0') === '1';
$telegram = null;
if ($telegramEnabled && $telegramToken && $telegramChatId) {
    require_once APP_ROOT . '/lib/Telegram.php';
    $telegram = new Telegram($telegramToken);
}

// ─── Helpers ───
$logLines = [];
$issues = 0;
$fixes = 0;
$retriedSlugs = []; // Track slugs we already retried in this run (prevent duplicates)

function log_msg(string $msg, bool $verbose = false): void {
    global $logLines, $isVerbose;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    $logLines[] = $line;
    if ($isVerbose || !$verbose) {
        fwrite(STDERR, $line . PHP_EOL);
    }
}

function send_telegram(string $text): void {
    global $telegram, $telegramChatId;
    if ($telegram && $telegramChatId) {
        $telegram->sendMessage($telegramChatId, $text);
    }
}

// ─── H1: Stuck Processing (> 30 min) ───
log_msg('H1: Checking for stuck processing videos...');
$stuck = $db->query(
    "SELECT id, title, slug, created_at FROM videos WHERE status='processing' AND created_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)"
);
if ($stuck && $stuck->num_rows > 0) {
    while ($row = $stuck->fetch_assoc()) {
        $issues++;
        $slug = $row['slug'];
        $age = round((time() - strtotime($row['created_at'])) / 60);
        log_msg("H1: Stuck processing: [{$row['id']}] {$row['title']} (slug: {$slug}, age: {$age} min)");
        if (!$isDryRun && !isset($retriedSlugs[$slug])) {
            $worker = '/usr/local/sbin/arsip-hls-worker';
            if (is_executable($worker)) {
                shell_exec('setsid nohup ' . escapeshellarg($worker) . ' ' . escapeshellarg($slug) . ' > /dev/null 2>&1 < /dev/null &');
                log_msg("H1: Retried worker for {$slug}");
                $fixes++;
                $retriedSlugs[$slug] = true;
            } else {
                log_msg("H1: Worker not found, cannot retry {$slug}");
            }
        }
    }
} else {
    log_msg('H1: No stuck processing videos.', true);
}

// ─── H2: Invalid Slugs ───
log_msg('H2: Checking for invalid slugs...');
$allVideos = $db->query("SELECT id, title, slug FROM videos");
$slugPattern = '#^[a-z0-9-]+/(?:poster\.jpg|preview\.mp4|source\.mp4|master\.m3u8|(?:360p|720p)\.m3u8|(?:360p|720p)_\d{3}\.ts)$#i';
if ($allVideos) {
    while ($row = $allVideos->fetch_assoc()) {
        $slug = $row['slug'];
        $needsFix = false;

        // Check: starts with hyphen or underscore
        if (preg_match('/^[-_]/', $slug)) {
            $needsFix = true;
        }
        // Check: contains characters outside [a-z0-9-]
        if (preg_match('/[^a-z0-9-]/', $slug)) {
            $needsFix = true;
        }

        if ($needsFix) {
            $issues++;
            log_msg("H2: Invalid slug: [{$row['id']}] {$row['title']} (slug: {$slug})");
            if (!$isDryRun) {
                // Generate new valid slug
                $newSlug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($row['title']));
                $newSlug = trim($newSlug, '-');
                $newSlug = preg_replace('/-+/', '-', $newSlug);
                if ($newSlug === '') $newSlug = 'video';
                // Add new random suffix
                $newSlug .= '-' . bin2hex(random_bytes(3));

                $oldDir = MEDIA_ROOT . '/' . $slug;
                $newDir = MEDIA_ROOT . '/' . $newSlug;

                if (is_dir($oldDir)) {
                    rename($oldDir, $newDir);
                    log_msg("H2: Renamed directory: {$slug} → {$newSlug}");
                }

                // Update DB: slug, poster, source paths
                $conn->execute(
                    'UPDATE videos SET slug=?, poster=REPLACE(REPLACE(poster,CONCAT("media/",?),CONCAT("media/",?)),?,?), source=REPLACE(REPLACE(source,CONCAT("media/",?),CONCAT("media/",?)),?,?) WHERE id=?',
                    [$newSlug, $slug, $newSlug, $slug, $newSlug, $slug, $newSlug, $slug, $newSlug, (int)$row['id']],
                    'sssssssssi'
                );
                log_msg("H2: Updated DB: [{$row['id']}] slug {$slug} → {$newSlug}");
                $fixes++;
            }
        }
    }
}

// ─── H3: Missing HLS Files ───
log_msg('H3: Checking for missing HLS files...');
$readyVideos = $db->query("SELECT id, title, slug FROM videos WHERE status='ready'");
if ($readyVideos) {
    while ($row = $readyVideos->fetch_assoc()) {
        $slug = $row['slug'];
        $masterPath = MEDIA_ROOT . '/' . $slug . '/master.m3u8';
        if (!is_file($masterPath)) {
            $issues++;
            log_msg("H3: Missing master.m3u8: [{$row['id']}] {$row['title']} (slug: {$slug})");
            if (!$isDryRun && !isset($retriedSlugs[$slug])) {
                $worker = '/usr/local/sbin/arsip-hls-worker';
                if (is_executable($worker)) {
                    shell_exec('setsid nohup ' . escapeshellarg($worker) . ' ' . escapeshellarg($slug) . ' > /dev/null 2>&1 < /dev/null &');
                    log_msg("H3: Retried worker for {$slug}");
                    $fixes++;
                    $retriedSlugs[$slug] = true;
                }
            }
        } else {
            // Also check 720p and 360p
            $has720 = is_file(MEDIA_ROOT . '/' . $slug . '/720p.m3u8');
            $has360 = is_file(MEDIA_ROOT . '/' . $slug . '/360p.m3u8');
            if (!$has720 || !$has360) {
                $issues++;
                log_msg("H3: Missing renditions: [{$row['id']}] {$row['title']} (720p: " . ($has720 ? 'ok' : 'MISSING') . ", 360p: " . ($has360 ? 'ok' : 'MISSING') . ")");
                if (!$isDryRun && !isset($retriedSlugs[$slug])) {
                    $worker = '/usr/local/sbin/arsip-hls-worker';
                    if (is_executable($worker)) {
                        shell_exec('setsid nohup ' . escapeshellarg($worker) . ' ' . escapeshellarg($slug) . ' > /dev/null 2>&1 < /dev/null &');
                        log_msg("H3: Retried worker for {$slug}");
                        $fixes++;
                        $retriedSlugs[$slug] = true;
                    }
                }
            }
        }
    }
} else {
    log_msg('H3: No ready videos found.', true);
}

// ─── H4: Missing Poster ───
log_msg('H4: Checking for missing posters...');
$readyForPoster = $db->query("SELECT id, title, slug FROM videos WHERE status='ready'");
if ($readyForPoster) {
    while ($row = $readyForPoster->fetch_assoc()) {
        $slug = $row['slug'];
        $posterPath = MEDIA_ROOT . '/' . $slug . '/poster.jpg';
        if (!is_file($posterPath)) {
            $issues++;
            log_msg("H4: Missing poster.jpg: [{$row['id']}] {$row['title']} (slug: {$slug})");
            if (!$isDryRun && !isset($retriedSlugs[$slug])) {
                $worker = '/usr/local/sbin/arsip-hls-worker';
                if (is_executable($worker)) {
                    shell_exec('setsid nohup ' . escapeshellarg($worker) . ' ' . escapeshellarg($slug) . ' > /dev/null 2>&1 < /dev/null &');
                    log_msg("H4: Retried worker for {$slug}");
                    $fixes++;
                    $retriedSlugs[$slug] = true;
                }
            }
        }
    }
}

// ─── H5: Unplayable Videos (slug fails ALLOWED_PATTERN) ───
log_msg('H5: Checking for unplayable videos...');
$allReady = $db->query("SELECT id, title, slug FROM videos WHERE status='ready'");
if ($allReady) {
    while ($row = $allReady->fetch_assoc()) {
        $slug = $row['slug'];
        // Test if the slug would pass the media delivery regex
        $testPath = $slug . '/master.m3u8';
        if (!preg_match($slugPattern, $testPath)) {
            $issues++;
            log_msg("H5: Unplayable video: [{$row['id']}] {$row['title']} (slug: {$slug})");
            if (!$isDryRun) {
                // Same fix as H2: rename slug
                $newSlug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($row['title']));
                $newSlug = trim($newSlug, '-');
                $newSlug = preg_replace('/-+/', '-', $newSlug);
                if ($newSlug === '') $newSlug = 'video';
                $newSlug .= '-' . bin2hex(random_bytes(3));

                $oldDir = MEDIA_ROOT . '/' . $slug;
                $newDir = MEDIA_ROOT . '/' . $newSlug;
                if (is_dir($oldDir)) {
                    rename($oldDir, $newDir);
                    log_msg("H5: Renamed directory: {$slug} → {$newSlug}");
                }
                $conn->execute(
                    'UPDATE videos SET slug=?, poster=REPLACE(REPLACE(poster,CONCAT("media/",?),CONCAT("media/",?)),?,?), source=REPLACE(REPLACE(source,CONCAT("media/",?),CONCAT("media/",?)),?,?) WHERE id=?',
                    [$newSlug, $slug, $newSlug, $slug, $newSlug, $slug, $newSlug, $slug, $newSlug, (int)$row['id']],
                    'sssssssssi'
                );
                log_msg("H5: Updated DB: [{$row['id']}] slug {$slug} → {$newSlug}");
                $fixes++;
            }
        }
    }
}

// ─── H7: Stale Upload Cleanup (>24h) ───
log_msg('H7: Checking for stale upload sessions...');
$uploadService = new VideoUpload($conn);
$pruned = $uploadService->pruneStaleUploads(86400); // 24 hours
if ($pruned > 0) {
    $issues += $pruned;
    $fixes += $pruned;
    log_msg("H7: Cleaned {$pruned} stale upload session(s)");
} else {
    log_msg('H7: No stale upload sessions.', true);
}

// ─── H6: Low Disk Space ───
log_msg('H6: Checking disk space...');
$freeSpace = @disk_free_space(MEDIA_ROOT);
if ($freeSpace !== false && $freeSpace < 1024 * 1024 * 1024) { // < 1GB
    $issues++;
    $freeMB = round($freeSpace / 1024 / 1024);
    log_msg("H6: LOW DISK SPACE: {$freeMB} MB free (threshold: 1 GB)");
} else {
    $freeMB = $freeSpace !== false ? round($freeSpace / 1024 / 1024) : 'unknown';
    log_msg("H6: Disk space OK: {$freeMB} MB free", true);
}

// ─── Summary ───
$summary = '[' . date('Y-m-d H:i:s') . '] Health check complete: ' . $issues . ' issue(s) found, ' . $fixes . ' auto-fixed';
log_msg($summary);

// ─── Write to log file ───
$logFile = '/var/log/arsip-health-check.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
@file_put_contents($logFile, implode(PHP_EOL, $logLines) . PHP_EOL, FILE_APPEND | LOCK_EX);

// ─── Log to DB activity_log ───
$conn->execute(
    'INSERT INTO activity_log(admin_id, action, detail, ip) VALUES(NULL, ?, ?, ?)',
    ['health_check', "issues={$issues} fixes={$fixes}", 'cron'],
    'sss'
);

// ─── Telegram Alert ───
if ($issues > 0) {
    $alert = "🔍 *Health Check Report*\n\n";
    $alert .= "Found {$issues} issue(s):\n";

    // Re-run counts for the alert message (lightweight queries)
    $stuckCount = $db->query("SELECT COUNT(*) c FROM videos WHERE status='processing' AND created_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)")->fetch_assoc()['c'] ?? 0;
    $readyCount = $db->query("SELECT COUNT(*) c FROM videos WHERE status='ready'")->fetch_assoc()['c'] ?? 0;
    $totalVideos = $db->query("SELECT COUNT(*) c FROM videos")->fetch_assoc()['c'] ?? 0;

    if ($stuckCount > 0) $alert .= "• {$stuckCount} video(s) stuck processing\n";
    $alert .= "• {$totalVideos} total videos ({$readyCount} ready)\n";
    if ($freeSpace !== false && $freeSpace < 1024 * 1024 * 1024) {
        $alert .= "• ⚠️ Low disk space: {$freeMB} MB\n";
    }
    $alert .= "\nAuto-fixed: {$fixes} issue(s)";

    send_telegram($alert);
}

exit($issues > 0 ? 1 : 0);
