<?php
declare(strict_types=1);

class LoginAttempt
{
    private Connection $conn;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    /** Record a login attempt. */
    public function record(string $email, bool $success, string $reason = ''): void
    {
        $ip = client_ip();
        $s = $success ? 1 : 0;
        $this->conn->execute(
            'INSERT INTO login_attempts(ip,email,success,reason) VALUES(?,?,?,?)',
            [$ip, $email, $s, $reason], 'ssis'
        );
    }

    /** Get count of failed logins for an IP within a time window. */
    public function recentFailedCount(string $ip, int $minutes = 15): int
    {
        $row = $this->conn->selectOne(
            'SELECT COUNT(*) c FROM login_attempts WHERE ip=? AND success=0 AND created_at > (NOW() - INTERVAL ? MINUTE)',
            [$ip, $minutes], 'si'
        );
        return (int)($row['c'] ?? 0);
    }

    /** Get recent failed login attempts (admin view). */
    public function getRecentFailed(int $limit = 50): array
    {
        return $this->conn->selectAll(
            'SELECT ip,email,reason,created_at FROM login_attempts WHERE success=0 ORDER BY created_at DESC LIMIT ?',
            [$limit], 'i'
        );
    }
}
