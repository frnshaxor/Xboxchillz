<?php

declare(strict_types=1);

/**
 * Media Service — handles protected media delivery, poster/preview URLs.
 */
class MediaService
{
    private Connection $conn;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    /** Allowed file patterns for media delivery. */
    private const ALLOWED_PATTERN = '#^[a-z0-9-]+/(?:poster\.jpg|preview\.mp4|source\.mp4|master\.m3u8|(?:360p|720p)\.m3u8|(?:360p|720p)_\d{3}\.ts)$#i';

    /**
     * Serve a media file (poster, preview, or protected video/HLS).
     * Handles access control and content-type headers.
     */
    public function serve(string $relative, string $page): void
    {
        $relative = preg_replace('#^/?(?:protected-media/|media/)#', '', $relative);

        if (!preg_match(self::ALLOWED_PATTERN, $relative)) {
            Response::error(404, 'Media tidak ditemukan.');
        }

        $file = MEDIA_ROOT . '/' . $relative;
        $root = realpath(MEDIA_ROOT);
        $real = realpath($file);

        if (!$real || !$root || !str_starts_with($real, $root . DIRECTORY_SEPARATOR) || !is_file($real)) {
            Response::error(404, 'Media tidak ditemukan.');
        }

        $isPoster = str_ends_with(strtolower($relative), '/poster.jpg');
        $isPreview = str_ends_with(strtolower($relative), '/preview.mp4');

        // Access control
        if ($page === 'media' && !$isPoster && !$isPreview && !has_access() && !admin()) {
            Response::error(403, 'Akses token diperlukan.');
        }
        if ($page === 'poster' && !$isPoster) {
            Response::error(404, 'Media tidak ditemukan.');
        }
        if ($page === 'preview' && !$isPreview) {
            Response::error(404, 'Media tidak ditemukan.');
        }

        $extension = strtolower(pathinfo($real, PATHINFO_EXTENSION));
        Response::serveMedia($real, $extension, $isPreview);
    }

    /**
     * Serve a video download (admin/token access only).
     */
    public function download(int $videoId): void
    {
        if (!has_access() && !admin()) {
            Response::error(403, 'Akses token diperlukan.');
        }

        $row = $this->conn->selectOne('SELECT title,source FROM videos WHERE id=?', [$videoId], 'i');

        if (!$row) {
            Response::error(404, 'File video tidak ditemukan.');
        }

        $file = APP_ROOT . '/' . ltrim($row['source'], '/');
        if (!is_file($file)) {
            Response::error(404, 'File video tidak ditemukan.');
        }

        $name = preg_replace('/[^a-z0-9._-]+/i', '-', $row['title']) . '.mp4';
        Response::download($file, $name);
    }

    /**
     * Check if a video has HLS renditions available.
     */
    public function getHlsInfo(string $sourcePath): array
    {
        $base = dirname($sourcePath);

        return [
            'master' => is_file(APP_ROOT . '/' . $base . '/master.m3u8'),
            '720p' => is_file(APP_ROOT . '/' . $base . '/720p.m3u8'),
            '360p' => is_file(APP_ROOT . '/' . $base . '/360p.m3u8'),
            'preview' => is_file(APP_ROOT . '/' . $base . '/preview.mp4'),
        ];
    }
}
