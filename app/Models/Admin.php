<?php

declare(strict_types=1);

class Admin
{
    private Connection $conn;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    /** Find admin by email (for login). */
    public function findByEmail(string $email): ?array
    {
        return $this->conn->selectOne(
            'SELECT id,password,name,totp_secret,totp_enabled FROM admins WHERE email=? AND active=1',
            [$email],
            's'
        );
    }

    /** Find admin by ID. */
    public function findById(int $id): ?array
    {
        return $this->conn->selectOne(
            'SELECT name,email,totp_enabled,last_login_at,last_login_ip FROM admins WHERE id=?',
            [$id],
            'i'
        );
    }

    /** Update last login info. */
    public function updateLastLogin(int $id, string $ip): void
    {
        $this->conn->execute(
            'UPDATE admins SET last_login_at=NOW(), last_login_ip=? WHERE id=?',
            [$ip, $id],
            'si'
        );
    }

    /** Update password. */
    public function updatePassword(int $id, string $hash): void
    {
        $this->conn->execute('UPDATE admins SET password=? WHERE id=?', [$hash, $id], 'si');
    }

    /** Update profile (name + email). */
    public function updateProfile(int $id, string $name, string $email): void
    {
        $this->conn->execute(
            'UPDATE admins SET name=?, email=? WHERE id=?',
            [$name, $email, $id],
            'ssi'
        );
    }

    /** Check if email is taken by another admin. */
    public function isEmailTaken(string $email, int $excludeId = 0): bool
    {
        $row = $this->conn->selectOne(
            'SELECT id FROM admins WHERE email=? AND id<>?',
            [$email, $excludeId],
            'si'
        );

        return $row !== null;
    }

    /** Enable 2FA TOTP. */
    public function enableTotp(int $id, string $secret): void
    {
        $this->conn->execute(
            'UPDATE admins SET totp_secret=?, totp_enabled=1 WHERE id=?',
            [$secret, $id],
            'si'
        );
    }

    /** Disable 2FA TOTP. */
    public function disableTotp(int $id): void
    {
        $this->conn->execute(
            'UPDATE admins SET totp_secret=NULL, totp_enabled=0 WHERE id=?',
            [$id],
            'i'
        );
    }

    /** Get all admins (admin list). */
    public function all(): array
    {
        return $this->conn->selectAll(
            'SELECT id,name,email,totp_enabled,last_login_at,last_login_ip,active,created_at FROM admins ORDER BY created_at DESC'
        );
    }
}
