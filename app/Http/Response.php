<?php

declare(strict_types=1);

/**
 * HTTP Response — helpers for JSON, redirect, HTML, file output.
 */
class Response
{
    /** Send a JSON response and exit. */
    public static function json(array $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Redirect and exit. */
    public static function redirect(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }

    /** Render a PHP view template with optional data. */
    public static function view(string $template, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $file = VIEWS_DIR . '/' . $template . '.php';
        if (!is_file($file)) {
            http_response_code(504);
            echo 'View not found: ' . htmlspecialchars($template);

            return;
        }
        require $file;
    }

    /** Serve a file for download. */
    public static function download(string $filePath, string $filename): void
    {
        if (!is_file($filePath)) {
            http_response_code(404);
            exit('File tidak ditemukan.');
        }
        // Sanitize filename: strip anything that isn't alphanumeric, dash, dot, underscore
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '-', $filename);
        $safeName = preg_replace('/-{2,}/', '-', $safeName);
        $safeName = trim($safeName, '-.');
        if ($safeName === '') {
            $safeName = 'download.mp4';
        }
        header('Content-Type: video/mp4');
        header('Content-Length: ' . (string) filesize($filePath));
        header('Content-Disposition: attachment; filename="' . $safeName . '"; filename*=UTF-8\'\'' . rawurlencode($safeName));
        header('Cache-Control: private, no-store');
        readfile($filePath);
        exit;
    }

    /** Serve a media file (poster, HLS, MP4, preview). */
    public static function serveMedia(string $realPath, string $extension, bool $isPreview = false): void
    {
        $types = [
            'jpg' => 'image/jpeg',
            'm3u8' => 'application/vnd.apple.mpegurl',
            'ts' => 'video/mp2t',
            'mp4' => 'video/mp4',
        ];
        header('Content-Type: ' . ($types[$extension] ?? 'application/octet-stream'));
        header('Content-Length: ' . (string) filesize($realPath));
        header('Cache-Control: ' . ($isPreview ? 'public,max-age=86400' : 'private,no-store'));
        header('X-Content-Type-Options: nosniff');
        if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') {
            readfile($realPath);
        }
        exit;
    }

    /** Send a plain text error and exit. */
    public static function error(int $code, string $message): never
    {
        http_response_code($code);
        header('Content-Type: text/plain; charset=utf-8');
        exit($message);
    }

    /** Send 503 maintenance page. */
    public static function maintenance(): never
    {
        http_response_code(503);
        header('Retry-After: 300');
        echo '<!doctype html><meta charset=utf-8><title>Sedang dirapikan</title>'
            . '<body style="background:#111;color:#e8dfcf;font:16px system-ui;padding:12vh 8vw">'
            . '<h1 style="font:600 56px \'Cormorant Garamond\',serif">Sedang dirapikan.</h1>'
            . '<p>Situs sementara dalam mode perawatan. Silakan kembali beberapa saat lagi.</p></body>';
        exit;
    }
}
