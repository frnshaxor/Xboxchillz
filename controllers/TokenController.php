<?php

declare(strict_types=1);

/**
 * Token Controller — verify, create, toggle, delete tokens.
 */
class TokenController
{
    private TokenManager $tokenService;

    public function __construct(Connection $conn)
    {
        $this->tokenService = new TokenManager($conn);
    }

    /** Verify a token (POST form submission from watch page). */
    public function verify(): void
    {
        CsrfMiddleware::validate();

        $tok = strtoupper(trim((string) ($_POST['token'] ?? '')));
        $redirect = (string) ($_POST['redirect'] ?? '.');
        if (!preg_match('/^\?/', $redirect)) {
            $redirect = '.';
        }

        $result = $this->tokenService->verify($tok);

        if (isset($result['ok'])) {
            go($redirect);
        }

        $_SESSION['token_error'] = $result['error'];
        go($redirect . (str_contains($redirect, '?') ? '&' : '?') . 'token_err=1');
    }

    /** Revoke access (user voluntarily). */
    public function revoke(): void
    {
        CsrfMiddleware::validate();
        revoke_access();
        go('.?logged_out=1');
    }

    /** Create token (admin form fallback). */
    public function create(): void
    {
        AuthMiddleware::requireAdmin();
        CsrfMiddleware::validate();

        $label = trim((string) ($_POST['label'] ?? ''));
        $contactType = (string) ($_POST['contact_type'] ?? 'telegram');
        $contactValue = trim((string) ($_POST['contact_value'] ?? ''));

        $result = $this->tokenService->create($label, $contactType, $contactValue);

        if (isset($result['ok'])) {
            go('?page=admin&tab=tokens&token_created=1');
        }

        $errCode = (str_contains($result['error'], 'wajib diisi') ? 'input'
                 : (str_contains($result['error'], 'gagal dibuat') ? 'generate' : 'input'));
        go('?page=admin&tab=tokens&token_err=' . $errCode);
    }

    /** Toggle token status (admin). */
    public function toggle(): void
    {
        AuthMiddleware::requireAdmin();
        CsrfMiddleware::validate();

        $id = (int) ($_POST['id'] ?? 0);
        $this->tokenService->toggle($id);
        go('?page=admin&tab=tokens');
    }

    /** Delete token (admin). */
    public function delete(): void
    {
        AuthMiddleware::requireAdmin();
        CsrfMiddleware::validate();

        $id = (int) ($_POST['id'] ?? 0);
        $this->tokenService->delete($id);
        go('?page=admin&tab=tokens');
    }
}
