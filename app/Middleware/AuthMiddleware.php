<?php

declare(strict_types=1);

/**
 * AuthMiddleware — checks admin authentication.
 */
class AuthMiddleware
{
    /** Ensure the current user is an admin. Redirect to login if not. */
    public static function requireAdmin(): void
    {
        if (!admin()) {
            Response::redirect('?page=login');
        }
    }

    /** Check if the current user is an admin (non-blocking). */
    public static function isAdmin(): bool
    {
        return admin();
    }
}
