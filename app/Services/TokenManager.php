<?php

declare(strict_types=1);

/**
 * TokenManager Service — create, verify, revoke, manage access tokens.
 */
class TokenManager
{
    private AccessToken $tokenModel;
    private ActivityLog $activityModel;

    public function __construct(Connection $conn)
    {
        $this->tokenModel = new AccessToken($conn);
        $this->activityModel = new ActivityLog($conn);
    }

    /**
     * Verify a token string. Returns ['ok' => true, 'id' => int] or ['error' => '...'].
     */
    public function verify(string $token): array
    {
        // Rate limit: max 10 attempts per minute per IP
        $ip = client_ip();
        if (!RateLimitMiddleware::check('token_verify_' . $ip, 10, 60)) {
            return ['error' => 'Terlalu banyak percobaan. Coba lagi dalam beberapa saat.'];
        }

        $row = $this->tokenModel->verify($token);
        if (!$row) {
            return ['error' => 'Token tidak valid atau sudah dinonaktifkan.'];
        }

        // Check expiry
        if ($row['expires_at'] && strtotime($row['expires_at']) < time()) {
            $this->tokenModel->markExpired($row['id']);

            return ['error' => 'Token ini sudah kedaluwarsa.'];
        }

        grant_access_with_token(Connection::getInstance()->db(), (int) $row['id']);
        session_regenerate_id(true);
        $this->tokenModel->recordUsage($row['id']);

        return ['ok' => true, 'id' => $row['id']];
    }

    /**
     * Create a new token (admin action).
     * Returns ['ok' => true, 'token' => string] or ['error' => '...'].
     */
    public function create(string $label, string $contactType, string $contactValue): array
    {
        if (strlen($label) < 2 || strlen($contactValue) < 2) {
            return ['error' => 'Nama penerima dan kontak wajib diisi dengan benar.'];
        }
        if (!in_array($contactType, ['telegram', 'whatsapp', 'facebook'], true)) {
            return ['error' => 'Platform kontak tidak valid.'];
        }

        try {
            $token = $this->tokenModel->generateUnique();
        } catch (\RuntimeException $e) {
            return ['error' => 'Token unik gagal dibuat. Silakan ulangi.'];
        }

        $adminId = (int) $_SESSION['admin_id'];
        $id = $this->tokenModel->create([
            'token' => $token,
            'label' => $label,
            'contact_type' => $contactType,
            'contact_value' => $contactValue,
            'status' => 'active',
            'created_by' => $adminId,
            'expires_at' => AccessToken::defaultExpiry(),
        ]);

        $this->activityModel->record($adminId, 'token_create', "label=$label token=$token");

        return ['ok' => true, 'id' => $id, 'token' => $token];
    }

    /**
     * Create token from Midtrans payment (auto-issue on settlement).
     */
    public function createFromPayment(string $buyerName, string $buyerContact): array
    {
        try {
            $token = $this->tokenModel->generateUnique();
        } catch (\RuntimeException $e) {
            return ['error' => 'Token generation failed'];
        }

        $id = $this->tokenModel->createFromPayment([
            'token' => $token,
            'label' => 'Midtrans — ' . $buyerName,
            'contact_type' => 'midtrans',
            'contact_value' => $buyerContact,
            'status' => 'active',
            'expires_at' => AccessToken::defaultExpiry(),
        ]);

        return ['ok' => true, 'id' => $id, 'token' => $token];
    }

    /** Toggle token status. */
    public function toggle(int $id): array
    {
        $newStatus = $this->tokenModel->toggle($id);
        if (!$newStatus) {
            return ['error' => 'Token tidak ditemukan.'];
        }
        $this->activityModel->record((int) $_SESSION['admin_id'], 'token_toggle', "id=$id status=$newStatus");

        return ['ok' => true, 'status' => $newStatus];
    }

    /** Update token info. */
    public function update(int $id, string $label, string $contactType, string $contactValue): array
    {
        $this->tokenModel->update($id, $label, $contactType, $contactValue);

        return ['ok' => true];
    }

    /** Delete a token. */
    public function delete(int $id): void
    {
        $this->tokenModel->delete($id);
        $this->activityModel->record((int) $_SESSION['admin_id'], 'token_delete', "id=$id");
    }

    /** List all tokens. */
    public function list(): array
    {
        return $this->tokenModel->all();
    }
}
