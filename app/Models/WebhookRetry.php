<?php
declare(strict_types=1);

class WebhookRetry
{
    private Connection $conn;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    /** Create a new webhook retry entry. */
    public function create(string $source, string $payload): int
    {
        return $this->conn->insert(
            'INSERT INTO webhook_retry(source,payload) VALUES(?,?)',
            [$source, $payload], 'ss'
        );
    }

    /** Get pending retries that are due. */
    public function getPending(): array
    {
        return $this->conn->selectAll(
            "SELECT * FROM webhook_retry WHERE status='pending' AND (next_retry_at IS NULL OR next_retry_at <= NOW()) ORDER BY created_at ASC LIMIT 20"
        );
    }

    /** Update retry status after processing. */
    public function updateResult(int $id, string $status, int $attempts, ?string $nextRetry, ?string $lastError): void
    {
        $this->conn->execute(
            'UPDATE webhook_retry SET status=?, attempts=?, next_retry_at=?, last_error=? WHERE id=?',
            [$status, $attempts, $nextRetry, $lastError, $id], 'sisss'
        );
    }
}
