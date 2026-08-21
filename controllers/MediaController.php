<?php

declare(strict_types=1);

/**
 * Media Controller — serve protected media, posters, previews, downloads.
 */
class MediaController
{
    private MediaService $mediaService;

    public function __construct(Connection $conn)
    {
        $this->mediaService = new MediaService($conn);
    }

    /** Serve media files (poster, preview, protected HLS/MP4). */
    public function serve(): void
    {
        $relative = rawurldecode((string) ($_GET['path'] ?? ''));
        $page = $_GET['page'] ?? 'media';
        $this->mediaService->serve($relative, $page);
    }

    /** Serve video download. */
    public function download(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $this->mediaService->download($id);
    }
}
