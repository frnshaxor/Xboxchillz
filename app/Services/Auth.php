<?php
declare(strict_types=1);

/**
 * Auth Service — login, logout, session management, 2FA.
 */
class Auth
{
    private Admin $adminModel;
    private LoginAttempt $loginModel;
    private ActivityLog $activityModel;

    public function __construct(Connection $conn)
    {
        $this->adminModel  = new Admin($conn);
        $this->loginModel  = new LoginAttempt($conn);
        $this->activityModel = new ActivityLog($conn);
    }

    /**
     * Attempt login with email + password + optional TOTP.
     * Returns ['ok' => true] on success, or ['error' => '...', 'need_totp' => bool].
     */
    public function login(string $email, string $pass, string $totpCode = ''): array
    {
        $ip = client_ip();

        // Rate limit check
        if ($this->loginModel->recentFailedCount($ip, 15) >= 8) {
            $this->loginModel->record($email, false, 'rate_limited');
            return ['error' => 'Terlalu banyak percobaan gagal. Coba lagi dalam 15 menit.'];
        }

        $admin = $this->adminModel->findByEmail($email);
        if (!$admin || !password_verify($pass, $admin['password'])) {
            $this->loginModel->record($email, false, 'bad_credentials');
            return ['error' => 'Email atau kata sandi salah.'];
        }

        // 2FA check
        if ((int)$admin['totp_enabled'] === 1) {
            if (!$totpCode || !totp_verify((string)$admin['totp_secret'], $totpCode)) {
                $this->loginModel->record($email, false, 'totp_failed');
                return ['error' => 'Kode 2FA salah atau kedaluwarsa.', 'need_totp' => true];
            }
        }

        // Upgrade password hash if needed
        if (password_needs_upgrade($admin['password'])) {
            $this->adminModel->updatePassword($admin['id'], password_new($pass));
        }

        // Start session
        session_regenerate_id(true);
        $_SESSION['admin_id']   = (int)$admin['id'];
        $_SESSION['admin_name'] = $admin['name'];

        // Update last login
        $this->adminModel->updateLastLogin($admin['id'], $ip);

        // Log success
        $this->loginModel->record($email, true, 'ok');
        $this->activityModel->record((int)$admin['id'], 'login', 'ok');

        return ['ok' => true, 'admin_id' => $admin['id']];
    }

    /** Logout the current admin. */
    public function logout(): void
    {
        $this->activityModel->record($_SESSION['admin_id'] ?? null, 'logout', '');
        $_SESSION = [];
        session_destroy();
    }

    /** Get current admin profile. */
    public function currentAdmin(): ?array
    {
        if (!admin()) return null;
        return $this->adminModel->findById((int)$_SESSION['admin_id']);
    }

    /** Update admin profile (name + email). */
    public function updateProfile(string $name, string $email, string $currentPassword): array
    {
        $aid = (int)$_SESSION['admin_id'];

        // Verify current password
        $admin = $this->adminModel->findById($aid);
        $full = $this->getFullAdmin($aid);
        if (!$full || !password_verify($currentPassword, $full['password'])) {
            return ['error' => 'Password saat ini salah.'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($name) < 2) {
            return ['error' => 'Nama minimal 2 karakter dan email harus valid.'];
        }

        if ($this->adminModel->isEmailTaken($email, $aid)) {
            return ['error' => 'Email tersebut sudah dipakai.'];
        }

        $this->adminModel->updateProfile($aid, $name, $email);
        $_SESSION['admin_name'] = $name;
        $this->activityModel->record($aid, 'account_update', 'name/email');
        return ['ok' => true];
    }

    /** Change admin password. */
    public function changePassword(string $current, string $new, string $confirm): array
    {
        $aid = (int)$_SESSION['admin_id'];
        $full = $this->getFullAdmin($aid);

        if (!$full || !password_verify($current, $full['password'])) {
            return ['error' => 'Password saat ini salah.'];
        }
        if (strlen($new) < 10) {
            return ['error' => 'Password baru minimal 10 karakter.'];
        }
        if ($new !== $confirm) {
            return ['error' => 'Konfirmasi password tidak sama.'];
        }

        $this->adminModel->updatePassword($aid, password_new($new));
        session_regenerate_id(true);
        $this->activityModel->record($aid, 'password_change', 'ok');
        return ['ok' => true];
    }

    /** Get full admin record (including password hash — internal use only). */
    private function getFullAdmin(int $id): ?array
    {
        $db = Connection::getInstance()->db();
        $s = $db->prepare('SELECT id,password,name FROM admins WHERE id=?');
        $s->bind_param('i', $id); $s->execute();
        return $s->get_result()->fetch_assoc() ?: null;
    }

    /** Setup 2FA — generate secret. */
    public function setup2fa(): array
    {
        $secret = base32_encode(random_bytes(20));
        $label = 'ArsipLayar:' . ($_SESSION['admin_name'] ?? 'admin');
        $otpauth = 'otpauth://totp/' . rawurlencode($label) . '?secret=' . $secret . '&issuer=ArsipLayar&algorithm=SHA1&digits=6&period=30';
        return ['secret' => $secret, 'otpauth' => $otpauth];
    }

    /** Enable 2FA with verification code. */
    public function enable2fa(string $secret, string $code): bool
    {
        if (!totp_verify($secret, $code)) return false;
        $aid = (int)$_SESSION['admin_id'];
        $this->adminModel->enableTotp($aid, $secret);
        $this->activityModel->record($aid, '2fa_enable', '');
        return true;
    }

    /** Disable 2FA. */
    public function disable2fa(): void
    {
        $aid = (int)$_SESSION['admin_id'];
        $this->adminModel->disableTotp($aid);
        $this->activityModel->record($aid, '2fa_disable', '');
    }
}
