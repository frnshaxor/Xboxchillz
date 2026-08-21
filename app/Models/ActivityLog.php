<?php

declare(strict_types=1);

class ActivityLog
{
    private Connection $conn;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    /** Record an activity log entry. */
    public function record(?int $adminId, string $action, string $detail = ''): void
    {
        $ip = client_ip();
        $this->conn->execute(
            'INSERT INTO activity_log(admin_id,action,detail,ip) VALUES(?,?,?,?)',
            [$adminId, $action, $detail, $ip],
            'isss'
        );
    }

    /** Record an activity with before/after diff (audit trail). */
    public function recordDiff(?int $adminId, string $action, string $detail, array $oldValues = [], array $newValues = []): void
    {
        $ip = client_ip();
        $old = !empty($oldValues) ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null;
        $new = !empty($newValues) ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null;
        $this->conn->execute(
            'INSERT INTO activity_log(admin_id,action,detail,ip,old_values,new_values) VALUES(?,?,?,?,?,?)',
            [$adminId, $action, $detail, $ip, $old, $new],
            'isssss'
        );
    }

    /** Get recent activity log entries. */
    public function getRecent(int $limit = 50): array
    {
        return $this->conn->selectAll(
            'SELECT al.*, a.name FROM activity_log al LEFT JOIN admins a ON a.id=al.admin_id ORDER BY al.created_at DESC LIMIT ?',
            [$limit],
            'i'
        );
    }
}
