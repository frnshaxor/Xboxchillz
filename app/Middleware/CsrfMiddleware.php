<?php

declare(strict_types=1);

/**
 * CsrfMiddleware — validates CSRF tokens on POST requests.
 * Supports both session-based and double-submit cookie patterns.
 */
class CsrfMiddleware
{
    /** Set CSRF cookie for double-submit pattern (call on GET requests). */
    public static function setCookie(): void
    {
        $token = csrf();
        setcookie('csrf_double', $token, [
            'path' => '/',
            'httponly' => false, // JS needs to read it for API calls
            'samesite' => 'Strict',
            'secure' => !empty($_SERVER['HTTPS']),
        ]);
    }

    /** Validate CSRF token from POST data. Exits with 419 if invalid. */
    public static function validate(): void
    {
        check_csrf();
        // Also verify double-submit cookie if present
        self::verifyDoubleSubmit();
    }

    /** Validate CSRF for API requests (JSON or form data). */
    public static function validateApi(?array $postData = null): void
    {
        $token = $postData['csrf'] ?? $_POST['csrf'] ?? '';
        if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
            Response::json(['error' => 'Token tidak valid'], 419);
        }
        // Also verify double-submit cookie if present
        self::verifyDoubleSubmit();
    }

    /** Verify double-submit cookie pattern. */
    private static function verifyDoubleSubmit(): void
    {
        $cookieToken = $_COOKIE['csrf_double'] ?? '';
        if ($cookieToken !== '' && !hash_equals($_SESSION['csrf'] ?? '', $cookieToken)) {
            http_response_code(419);
            exit('Token tidak valid (cookie mismatch).');
        }
    }
}
