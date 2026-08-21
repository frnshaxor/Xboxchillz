<?php

declare(strict_types=1);

/**
 * JobQueue Model — DB-backed job queue for background tasks.
 * Replaces fire-and-forget shell_exec calls with managed queue.
 */
class JobQueue
{
    private Connection $conn;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    /** Push a job to the queue. */
    public function push(string $jobType, array $payload, int $delaySeconds = 0): int
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $nextRun = $delaySeconds > 0
            ? date('Y-m-d H:i:s', time() + $delaySeconds)
            : date('Y-m-d H:i:s');

        return $this->conn->insert(
            'INSERT INTO job_queue(job_type, payload, status, next_run_at) VALUES(?,?,?,?)',
            [$jobType, $json, 'pending', $nextRun],
            'ssss'
        );
    }

    /** Get next pending job (with row lock). */
    public function next(): ?array
    {
        $this->conn->beginTransaction();
        $row = $this->conn->selectOne(
            "SELECT * FROM job_queue WHERE status='pending' AND (next_run_at IS NULL OR next_run_at <= NOW()) ORDER BY created_at ASC LIMIT 1 FOR UPDATE"
        );
        if (!$row) {
            $this->conn->rollback();

            return null;
        }

        return $row;
    }

    /** Mark job as running. */
    public function markRunning(int $id): void
    {
        $this->conn->execute(
            'UPDATE job_queue SET status=?, attempts=attempts+1 WHERE id=?',
            ['running', $id],
            'si'
        );
        $this->conn->commit();
    }

    /** Mark job as completed. */
    public function markDone(int $id): void
    {
        $this->conn->execute(
            'UPDATE job_queue SET status=? WHERE id=?',
            ['done', $id],
            'si'
        );
    }

    /** Mark job as failed, schedule retry with exponential backoff. */
    public function markFailed(int $id, string $error, int $attempts, int $maxAttempts): void
    {
        $backoffMinutes = [5, 15, 45, 120];
        if ($attempts >= $maxAttempts) {
            $this->conn->execute(
                'UPDATE job_queue SET status=?, last_error=? WHERE id=?',
                ['failed', $error, $id],
                'ssi'
            );
        } else {
            $idx = min($attempts, count($backoffMinutes) - 1);
            $nextRun = date('Y-m-d H:i:s', time() + $backoffMinutes[$idx] * 60);
            $this->conn->execute(
                'UPDATE job_queue SET status=?, last_error=?, next_run_at=? WHERE id=?',
                ['pending', $error, $nextRun, $id],
                'sssi'
            );
        }
    }

    /** Get queue stats. */
    public function stats(): array
    {
        $rows = $this->conn->selectAll(
            'SELECT status, COUNT(*) c FROM job_queue GROUP BY status'
        );
        $stats = ['pending' => 0, 'running' => 0, 'done' => 0, 'failed' => 0];
        foreach ($rows as $r) {
            $stats[$r['status']] = (int) $r['c'];
        }

        return $stats;
    }

    /** Cleanup old completed/failed jobs (older than N days). */
    public function prune(int $days = 7): int
    {
        return $this->conn->execute(
            "DELETE FROM job_queue WHERE status IN ('done','failed') AND created_at < (NOW() - INTERVAL ? DAY)",
            [$days],
            'i'
        );
    }
}
