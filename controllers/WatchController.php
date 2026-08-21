<?php
declare(strict_types=1);

/**
 * Watch Controller — renders the video watch/playback page.
 */
class WatchController
{
    private MediaService $mediaService;

    public function __construct(Connection $conn)
    {
        $this->mediaService = new MediaService($conn);
    }

    /** Show the watch page for a video. */
    public function show(): void
    {
        $db = Connection::getInstance()->db();
        $id = (int)($_GET['id'] ?? 0);

        $s = $db->prepare('SELECT v.*, c.name category FROM videos v LEFT JOIN categories c ON c.id=v.category_id WHERE v.id=?');
        $s->bind_param('i', $id); $s->execute();
        $v = $s->get_result()->fetch_assoc();
        if (!$v) go('.');

        $base = dirname($v['source']);
        $hlsInfo = $this->mediaService->getHlsInfo($v['source']);

        $userHasAccess = has_access() || admin();
        $tokenError    = $_SESSION['token_error'] ?? '';
        unset($_SESSION['token_error']);
        $currentUrl = '?page=watch&id=' . $id;

        $site = setting($db, 'site_name', 'Arsip Layar');
        $watermark_text     = setting($db, 'watermark_text', 'Codename F');
        $watermark_position = setting($db, 'watermark_position', 'br');
        $watermark_opacity  = (int)setting($db, 'watermark_opacity', '60');
        $midtransEnabled   = setting($db, 'midtrans_enabled', '0') === '1';
        $midtransClientKey = setting($db, 'midtrans_client_key', '');
        $midtransPrice     = (int)setting($db, 'midtrans_token_price', '50000');

        require VIEWS_DIR . '/pages/watch.php';
    }
}
