<?php

declare(strict_types=1);

class AccessToken
{
    private Connection $conn;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    /** Verify a token exists and is active. Returns row if valid, null otherwise. */
    public function verify(string $token): ?array
    {
        return $this->conn->selectOne(
            "SELECT id, expires_at FROM access_tokens WHERE token=? AND status='active'",
            [$token],
            's'
        );
    }

    /** Create a new access token. */
    public function create(array $data): int
    {
        return $this->conn->insert(
            'INSERT INTO access_tokens(token,label,contact_type,contact_value,status,created_by,expires_at) VALUES(?,?,?,?,?,?,?)',
            [$data['token'], $data['label'], $data['contact_type'], $data['contact_value'], $data['status'], $data['created_by'], $data['expires_at']],
            'sssssis'
        );
    }

    /** Create token from Midtrans payment (no created_by). */
    public function createFromPayment(array $data): int
    {
        return $this->conn->insert(
            'INSERT INTO access_tokens(token,label,contact_type,contact_value,status,expires_at) VALUES(?,?,?,?,?,?)',
            [$data['token'], $data['label'], $data['contact_type'], $data['contact_value'], $data['status'], $data['expires_at']],
            'ssssss'
        );
    }

    /** Mark a token as expired. */
    public function markExpired(int $id): void
    {
        $this->conn->execute('UPDATE access_tokens SET status="expired" WHERE id=?', [$id], 'i');
    }

    /** Increment use count and update last_used_at. */
    public function recordUsage(int $id): void
    {
        $this->conn->execute(
            'UPDATE access_tokens SET use_count=use_count+1, last_used_at=NOW() WHERE id=?',
            [$id],
            'i'
        );
    }

    /** List all tokens (admin view). */
    public function all(): array
    {
        return $this->conn->selectAll(
            'SELECT id,token,label,contact_type,contact_value,status,use_count,last_used_at,expires_at,created_at FROM access_tokens ORDER BY created_at DESC'
        );
    }

    /** Toggle token status (active ↔ suspended). */
    public function toggle(int $id): string
    {
        $row = $this->conn->selectOne('SELECT status FROM access_tokens WHERE id=?', [$id], 'i');
        if (!$row) {
            return '';
        }
        $newStatus = $row['status'] === 'active' ? 'suspended' : 'active';
        $this->conn->execute('UPDATE access_tokens SET status=? WHERE id=?', [$newStatus, $id], 'si');

        return $newStatus;
    }

    /** Update token label/contact info. */
    public function update(int $id, string $label, string $contactType, string $contactValue): void
    {
        $this->conn->execute(
            'UPDATE access_tokens SET label=?, contact_type=?, contact_value=? WHERE id=?',
            [$label, $contactType, $contactValue, $id],
            'sssi'
        );
    }

    /** Delete a token. */
    public function delete(int $id): void
    {
        $this->conn->execute('DELETE FROM access_tokens WHERE id=?', [$id], 'i');
    }

    /** Find token by ID. */
    public function findById(int $id): ?array
    {
        return $this->conn->selectOne('SELECT * FROM access_tokens WHERE id=?', [$id], 'i');
    }

    /** Generate a unique token string in XXXX-XXXX-XXXX format. */
    public function generateUnique(): string
    {
        $exists = true;
        for ($attempt = 0; $attempt < 10 && $exists; $attempt++) {
            $raw = generate_token(12);
            $token = substr($raw, 0, 4) . '-' . substr($raw, 4, 4) . '-' . substr($raw, 8, 4);
            $exists = $this->conn->selectOne('SELECT id FROM access_tokens WHERE token=?', [$token], 's') !== null;
        }
        if ($exists) {
            throw new \RuntimeException('Token unik gagal dibuat.');
        }

        return $token;
    }

    /** Get 30-day expiry datetime string. */
    public static function defaultExpiry(): string
    {
        return date('Y-m-d H:i:s', time() + 30 * 86400);
    }
}
