<?php
declare(strict_types=1);

/**
 * Analytics Service — track events, compute insights, heatmap data.
 */
class AnalyticsService
{
    private AnalyticsEvent $eventModel;
    private VideoHeatmap $heatmapModel;

    public function __construct(Connection $conn)
    {
        $this->eventModel  = new AnalyticsEvent($conn);
        $this->heatmapModel = new VideoHeatmap($conn);
    }

    /** Record a page view or video event. */
    public function recordEvent(array $data): void
    {
        $this->eventModel->record([
            'event'        => $data['event'] ?? 'page_view',
            'path'         => $data['path'] ?? '/',
            'visitor_hash' => $data['visitor_hash'] ?? hash('sha256', client_ip() . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? '')),
            'video_id'     => $data['video_id'] ?? null,
            'progress_sec' => $data['progress_sec'] ?? null,
            'device'       => $data['device'] ?? null,
            'browser'      => $data['browser'] ?? null,
            'referrer'     => $data['referrer'] ?? null,
        ]);
    }

    /** Record heatmap data (which seconds were watched). */
    public function recordHeatmap(int $videoId, string $secondsStr): void
    {
        $hash = VideoHeatmap::visitorHash();
        $seconds = array_map('intval', explode(',', $secondsStr));
        $this->heatmapModel->record($videoId, $hash, $seconds);
    }

    /** Get full insights for a date range. */
    public function getInsights(int $days): array
    {
        return [
            'metrics'   => $this->eventModel->getMetrics($days),
            'popular'   => $this->eventModel->getPopularPages($days),
            'sources'   => $this->eventModel->getSources($days),
            'heatmap'   => $this->eventModel->getHeatmap($days),
            'retention' => $this->eventModel->getRetention($days),
            'devices'   => $this->eventModel->getDevices($days),
        ];
    }

    /** Get video engagement heatmap data. */
    public function getVideoHeatmap(int $videoId): array
    {
        $video = (new Video(Connection::getInstance()))->findRawById($videoId);
        return [
            'heatmap'  => $this->heatmapModel->getForVideo($videoId),
            'duration' => (int)($video['duration_sec'] ?? 0),
        ];
    }
}
