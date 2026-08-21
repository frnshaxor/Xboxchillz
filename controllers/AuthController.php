<?php
declare(strict_types=1);

/**
 * Auth Controller — login, logout, 2FA management.
 */
class AuthController
{
    private Auth $authService;

    public function __construct(Connection $conn)
    {
        $this->authService = new Auth($conn);
    }

    /** Show login page. */
    public function loginForm(): void
    {
        if (admin()) go('?page=admin');
        $error = $_SESSION['login_error'] ?? '';
        unset($_SESSION['login_error']);
        Response::view('auth/login', ['error' => $error]);
    }

    /** Process login POST. */
    public function login(): void
    {
        CsrfMiddleware::validate();

        $email = trim((string)($_POST['email'] ?? ''));
        $pass  = (string)($_POST['password'] ?? '');
        $code  = preg_replace('/\D/', '', (string)($_POST['totp'] ?? ''));

        $result = $this->authService->login($email, $pass, $code);

        if (isset($result['ok'])) {
            go('?page=admin');
        }

        $_SESSION['login_error'] = $result['error'];
        $_SESSION['need_totp'] = $result['need_totp'] ?? false;
        go('?page=login');
    }

    /** Logout. */
    public function logout(): void
    {
        $this->authService->logout();
        go('?page=login');
    }

    /** Setup 2FA (API). */
    public function setup2fa(): void
    {
        AuthMiddleware::requireAdmin();
        Response::json($this->authService->setup2fa());
    }

    /** Enable 2FA (API). */
    public function enable2fa(): void
    {
        AuthMiddleware::requireAdmin();
        $code = $_POST['code'] ?? '';
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $code = $code ?: ($body['code'] ?? '');

        // Get secret from session
        $secret = $_SESSION['2fa_secret'] ?? '';
        if (!$secret) {
            Response::json(['error' => 'Setup 2FA terlebih dahulu'], 400);
        }

        if ($this->authService->enable2fa($secret, $code)) {
            unset($_SESSION['2fa_secret']);
            Response::json(['ok' => true]);
        } else {
            Response::json(['error' => 'Kode salah atau kedaluwarsa.'], 422);
        }
    }

    /** Disable 2FA (API). */
    public function disable2fa(): void
    {
        AuthMiddleware::requireAdmin();
        $this->authService->disable2fa();
        Response::json(['ok' => true]);
    }

    /** Update admin profile. */
    public function updateProfile(): void
    {
        AuthMiddleware::requireAdmin();
        CsrfMiddleware::validate();

        $name    = trim((string)($_POST['name'] ?? ''));
        $email   = trim((string)($_POST['email'] ?? ''));
        $current = (string)($_POST['current_password'] ?? '');

        $result = $this->authService->updateProfile($name, $email, $current);
        if (isset($result['ok'])) {
            go('?page=admin&tab=account&saved=1');
        }
        $errMap = [
            'Password saat ini salah.' => 'pw',
            'Nama minimal 2 karakter dan email harus valid.' => 'input',
            'Email tersebut sudah dipakai.' => 'dupe',
        ];
        $errCode = $errMap[$result['error']] ?? 'input';
        go('?page=admin&tab=account&err=' . $errCode);
    }

    /** Change password. */
    public function changePassword(): void
    {
        AuthMiddleware::requireAdmin();
        CsrfMiddleware::validate();

        $current = (string)($_POST['current_password'] ?? '');
        $new     = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        $result = $this->authService->changePassword($current, $new, $confirm);
        if (isset($result['ok'])) {
            go('?page=admin&tab=account&pwsaved=1');
        }
        $errMap = [
            'Password saat ini salah.' => 'pw',
            'Password baru minimal 10 karakter.' => 'short',
            'Konfirmasi password tidak sama.' => 'mismatch',
        ];
        $errCode = $errMap[$result['error']] ?? 'input';
        go('?page=admin&tab=account&err=' . $errCode);
    }
}
