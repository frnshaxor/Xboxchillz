<?php
declare(strict_types=1);

/**
 * RateLimitMiddleware — file-based rate limiter.
 */
class RateLimitMiddleware
{
    /** Check rate limit. Returns true if allowed, false if blocked. */
    public static function check(string $key, int $max = 30, int $window = 60): bool
    {
        $dir = CACHE_DIR . '/ratelimit';
        @is_dir($dir) or @mkdir($dir, 0750, true);
        $file = $dir . '/' . preg_replace('/[^a-z0-9_-]/', '', $key) . '.json';
        $now = time();
        $data = ['hits' => [], 'blocked_until' => 0];

        if (is_file($file)) {
            $raw = @file_get_contents($file);
            if ($raw) $data = json_decode($raw, true) ?: $data;
        }

        $data['hits'] = array_values(array_filter($data['hits'], fn($t) => $t > $now - $window));

        if (count($data['hits']) >= $max) {
            file_put_contents($file, json_encode($data), LOCK_EX);
            return false; // rate limited
        }

        $data['hits'][] = $now;
        file_put_contents($file, json_encode($data), LOCK_EX);
        return true; // allowed
    }

    /** Enforce rate limit — return 429 if blocked. */
    public static function enforce(string $key, int $max = 30, int $window = 60): void
    {
        if (!self::check($key, $max, $window)) {
            Response::json(['error' => 'Terlalu banyak request'], 429);
        }
    }

    /** Check login rate limit (8 failures per 15 minutes per IP). */
    public static function checkLogin(string $ip): bool
    {
        return self::check('login_' . $ip, 8, 900);
    }

    /** Enforce global API rate limit — 100 requests per minute per IP. */
    public static function enforceGlobalApi(): void
    {
        $ip = client_ip();
        if (!self::check('api_global_' . $ip, 100, 60)) {
            Response::json(['error' => 'Terlalu banyak request API. Batas: 100/menit.'], 429);
        }
    }
}
