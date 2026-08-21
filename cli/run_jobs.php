<?php
declare(strict_types=1);

/**
 * Job Runner — processes pending jobs from the queue.
 * 
 * Usage:
 *   php cli/run_jobs.php              — Run once (process available jobs)
 *   php cli/run_jobs.php --daemon     — Run continuously (poll every 10s)
 *   php cli/run_jobs.php --stats      — Show queue statistics
 * 
 * This replaces fire-and-forget shell_exec calls with a managed queue.
 */

require __DIR__ . '/../app/bootstrap.php';

$conn = Connection::getInstance();
$queue = new JobQueue($conn);
$db = $conn->db();

// Handle --stats flag
if (in_array('--stats', $argv ?? [])) {
    $stats = $queue->stats();
    echo "Job Queue Stats:\n";
    echo "  Pending: {$stats['pending']}\n";
    echo "  Running: {$stats['running']}\n";
    echo "  Done:    {$stats['done']}\n";
    echo "  Failed:  {$stats['failed']}\n";
    exit(0);
}

$isDaemon = in_array('--daemon', $argv ?? []);

function processJob(JobQueue $queue, mysqli $db): bool
{
    $job = $queue->next();
    if (!$job) return false;

    $queue->markRunning($job['id']);
    $payload = json_decode($job['payload'], true) ?: [];
    $error = '';

    try {
        switch ($job['job_type']) {
            case 'preview_generate':
                $slug = $payload['slug'] ?? '';
                if (!$slug) throw new \RuntimeException('Missing slug');
                // Skip if already ready (worker was called directly during upload)
                $check = $db->prepare('SELECT status FROM videos WHERE slug=?');
                $check->bind_param('s', $slug); $check->execute();
                $row = $check->get_result()->fetch_assoc();
                if ($row && $row['status'] === 'ready') {
                    log_activity($db, null, 'job_preview_skip', $slug . ' (already ready)');
                    break;
                }
                // Run synchronously — the worker handles poster + preview + HLS + status update
                $worker = '/usr/local/sbin/arsip-hls-worker';
                if (is_executable($worker)) {
                    $output = [];
                    $exitCode = 0;
                    exec(escapeshellarg($worker) . ' ' . escapeshellarg($slug) . ' 2>&1', $output, $exitCode);
                    if ($exitCode !== 0) {
                        throw new \RuntimeException('Worker failed (exit ' . $exitCode . '): ' . implode('\n', array_slice($output, -5)));
                    }
                    log_activity($db, null, 'job_preview_hls', $slug);
                } else {
                    throw new \RuntimeException('HLS worker not found: ' . $worker);
                }
                break;

            case 'hls_transcode':
                $slug = $payload['slug'] ?? '';
                if (!$slug) throw new \RuntimeException('Missing slug');
                // Skip if preview_generate already ran the full worker and set status=ready
                $check = $db->prepare('SELECT status FROM videos WHERE slug=?');
                $check->bind_param('s', $slug); $check->execute();
                $row = $check->get_result()->fetch_assoc();
                if ($row && $row['status'] === 'ready') {
                    log_activity($db, null, 'job_hls_skip', $slug . ' (already ready)');
                    break;
                }
                $worker = '/usr/local/sbin/arsip-hls-worker';
                if (is_executable($worker)) {
                    $output = [];
                    $exitCode = 0;
                    exec(escapeshellarg($worker) . ' ' . escapeshellarg($slug) . ' 2>&1', $output, $exitCode);
                    if ($exitCode !== 0) {
                        throw new \RuntimeException('Worker failed (exit ' . $exitCode . '): ' . implode('\n', array_slice($output, -5)));
                    }
                    log_activity($db, null, 'job_hls', $slug);
                } else {
                    throw new \RuntimeException('HLS worker not found: ' . $worker);
                }
                break;

            case 'telegram_notify':
                $slug = $payload['slug'] ?? '';
                if (!$slug) throw new \RuntimeException('Missing slug');
                // Run the CLI notifier
                $cmd = sprintf(
                    'php %s %s 2>&1',
                    escapeshellarg(__DIR__ . '/notify_video_ready.php'),
                    escapeshellarg($slug)
                );
                shell_exec($cmd);
                log_activity($db, null, 'job_telegram', $slug);
                break;

            case 'backup_prune':
                $service = new BackupService();
                $service->prune((int)($payload['keep'] ?? 14));
                log_activity($db, null, 'job_backup_prune', '');
                break;

            default:
                throw new \RuntimeException("Unknown job type: {$job['job_type']}");
        }
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }

    if ($error) {
        $queue->markFailed($job['id'], $error, (int)$job['attempts'] + 1, (int)$job['max_attempts']);
        fwrite(STDERR, "Job #{$job['id']} failed: {$error}\n");
    } else {
        $queue->markDone($job['id']);
    }

    return true;
}

// Main loop
if ($isDaemon) {
    echo "Job runner started (daemon mode). Polling every 10s...\n";
    while (true) {
        $processed = false;
        while (processJob($queue, $db)) {
            $processed = true;
        }
        if (!$processed) {
            sleep(10);
        }
    }
} else {
    $count = 0;
    while (processJob($queue, $db)) {
        $count++;
    }
    echo "Processed {$count} job(s).\n";
}
