<?php
declare(strict_types=1);

/**
 * Settings API Controller — theme, watermark, system, backup, maintenance, cache.
 */
class SettingsApiController
{
    public function __construct(Connection $conn)
    {
    }

    /** Get public state (site name, admin status, CSRF, token info). */
    public function state(): void
    {
        $db = Connection::getInstance()->db();
        $data = [
            'csrf' => csrf(),
            'site' => setting($db, 'site_name', 'Arsip Layar'),
            'admin' => admin(),
            'has_access' => has_access(),
        ];
        if (has_access()) {
            $data['token_label']       = $_SESSION['access_token_label'] ?? null;
            $data['token_created_at']  = $_SESSION['access_token_created_at'] ?? null;
            $data['token_expires_at']  = $_SESSION['access_token_expires_at'] ?? null;
        }
        Response::json($data);
    }

    /** Get watermark settings. */
    public function getWatermark(): void
    {
        $db = Connection::getInstance()->db();
        Response::json([
            'text'     => setting($db, 'watermark_text', 'Codename F'),
            'position' => setting($db, 'watermark_position', 'br'),
            'opacity'  => (int)setting($db, 'watermark_opacity', '60'),
        ]);
    }

    /** Save watermark settings. */
    public function watermark(): void
    {
        AuthMiddleware::requireAdmin();
        $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        CsrfMiddleware::validateApi($body);

        $db = Connection::getInstance()->db();
        set_setting($db, 'watermark_text', (string)($body['text'] ?? ''));
        set_setting($db, 'watermark_position', (string)($body['position'] ?? 'br'));
        set_setting($db, 'watermark_opacity', (string)(int)($body['opacity'] ?? 60));
        Response::json(['ok' => true]);
    }

    /** Save upload limit. */
    public function uploadLimit(): void
    {
        AuthMiddleware::requireAdmin();
        $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $mb = max(10, min(20480, (int)($body['mb'] ?? 2048)));
        set_setting(Connection::getInstance()->db(), 'upload_max_mb', (string)$mb);
        Response::json(['ok' => true, 'mb' => $mb]);
    }

    /** Toggle maintenance mode. */
    public function maintenance(): void
    {
        AuthMiddleware::requireAdmin();
        $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $on = ($body['on'] ?? '0') === '1' ? '1' : '0';
        set_setting(Connection::getInstance()->db(), 'maintenance_mode', $on);
        Response::json(['ok' => true]);
    }

    /** Bust cache version. */
    public function cacheBust(): void
    {
        AuthMiddleware::requireAdmin();
        $newVer = (string)time();
        set_setting(Connection::getInstance()->db(), 'cache_ver', $newVer);
        Response::json(['ok' => true, 'cache_ver' => $newVer]);
    }

    /** Create database backup. */
    public function backup(): void
    {
        AuthMiddleware::requireAdmin();
        $service = new BackupService();
        Response::json($service->create());
    }

    /** List backups. */
    public function backupList(): void
    {
        AuthMiddleware::requireAdmin();
        $service = new BackupService();
        Response::json(['items' => $service->list()]);
    }

    /** Download a backup file. */
    public function backupDownload(): void
    {
        AuthMiddleware::requireAdmin();
        $file = $_GET['file'] ?? '';
        $service = new BackupService();
        $path = $service->getPath($file);
        if (!$path) Response::error(404, 'File tidak ditemukan.');
        header('Content-Type: application/gzip');
        header('Content-Length: ' . (string)filesize($path));
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        readfile($path);
        exit;
    }
}
