<?php

declare(strict_types=1);

/**
 * Analytics API Controller — page views, video events, heatmap, insights.
 */
class AnalyticsApiController
{
    private AnalyticsService $analyticsService;

    public function __construct(Connection $conn)
    {
        $this->analyticsService = new AnalyticsService($conn);
    }

    /** Record an analytics event. */
    public function recordEvent(): void
    {
        $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        if (empty($body['csrf'])) {
            Response::json(['error' => 'Token tidak valid'], 419);
        }

        RateLimitMiddleware::enforce('event_' . client_ip(), 60, 60);

        // Validate and sanitize inputs
        $allowedEvents = ['page_view', 'video_start', 'video_progress', 'video_complete'];
        $event = $body['event'] ?? 'page_view';
        if (!in_array($event, $allowedEvents, true)) {
            $event = 'page_view';
        }

        $this->analyticsService->recordEvent([
            'event' => $event,
            'path' => substr($body['path'] ?? '/', 0, 255),
            'visitor_hash' => hash('sha256', client_ip() . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? '')),
            'video_id' => !empty($body['video_id']) ? (int) $body['video_id'] : null,
            'progress_sec' => isset($body['progress']) ? (int) $body['progress'] : null,
            'device' => substr($body['device'] ?? '', 0, 40),
            'browser' => substr($body['browser'] ?? '', 0, 80),
        ]);

        Response::json(['ok' => true]);
    }

    /** Record heatmap data. */
    public function recordHeatmap(): void
    {
        $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $videoId = (int) ($body['video_id'] ?? 0);
        $seconds = (string) ($body['seconds'] ?? '');
        if ($videoId && $seconds) {
            $this->analyticsService->recordHeatmap($videoId, $seconds);
        }
        Response::json(['ok' => true]);
    }

    /** Get insights data. */
    public function getInsights(): void
    {
        AuthMiddleware::requireAdmin();
        $days = max(1, min(365, (int) ($_GET['days'] ?? 30)));
        Response::json($this->analyticsService->getInsights($days));
    }

    /** Get video heatmap data. */
    public function getVideoHeatmap(): void
    {
        AuthMiddleware::requireAdmin();
        $videoId = (int) ($_GET['video_id'] ?? 0);
        Response::json($this->analyticsService->getVideoHeatmap($videoId));
    }
}
