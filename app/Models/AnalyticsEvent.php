<?php
declare(strict_types=1);

class AnalyticsEvent
{
    private Connection $conn;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    /** Record an analytics event. */
    public function record(array $data): void
    {
        $this->conn->execute(
            'INSERT INTO analytics_events(event,path,visitor_hash,video_id,progress_sec,device,browser,referrer) VALUES(?,?,?,?,?,?,?,?)',
            [
                $data['event'],
                $data['path'],
                $data['visitor_hash'],
                $data['video_id'] ?? null,
                $data['progress_sec'] ?? null,
                $data['device'] ?? null,
                $data['browser'] ?? null,
                $data['referrer'] ?? null,
            ],
            'sssiisss'
        );
    }

    /** Get metrics for a date range. */
    public function getMetrics(int $days): array
    {
        $rows = $this->conn->selectAll(
            "SELECT event, COUNT(*) c FROM analytics_events WHERE created_at > (NOW() - INTERVAL ? DAY) GROUP BY event",
            [$days], 'i'
        );
        $metrics = ['visitors' => 0, 'page_views' => 0, 'video_views' => 0, 'total' => 0];
        foreach ($rows as $r) {
            $metrics['total'] += (int)$r['c'];
            if ($r['event'] === 'page_view') $metrics['page_views'] = (int)$r['c'];
            if ($r['event'] === 'video_start') $metrics['video_views'] = (int)$r['c'];
        }
        // Unique visitors
        $v = $this->conn->selectOne(
            "SELECT COUNT(DISTINCT visitor_hash) c FROM analytics_events WHERE created_at > (NOW() - INTERVAL ? DAY)",
            [$days], 'i'
        );
        $metrics['visitors'] = (int)($v['c'] ?? 0);
        return $metrics;
    }

    /** Get popular pages. */
    public function getPopularPages(int $days, int $limit = 10): array
    {
        return $this->conn->selectAll(
            "SELECT path, COUNT(*) views FROM analytics_events WHERE event='page_view' AND created_at > (NOW() - INTERVAL ? DAY) GROUP BY path ORDER BY views DESC LIMIT ?",
            [$days, $limit], 'ii'
        );
    }

    /** Get traffic sources (referrers). */
    public function getSources(int $days, int $limit = 10): array
    {
        return $this->conn->selectAll(
            "SELECT referrer src, COUNT(*) hits FROM analytics_events WHERE event='page_view' AND referrer IS NOT NULL AND referrer != '' AND created_at > (NOW() - INTERVAL ? DAY) GROUP BY referrer ORDER BY hits DESC LIMIT ?",
            [$days, $limit], 'ii'
        );
    }

    /** Get heatmap data (hour of day vs day of week). */
    public function getHeatmap(int $days): array
    {
        $rows = $this->conn->selectAll(
            "SELECT DAYOFWEEK(created_at) dow, HOUR(created_at) h, COUNT(*) c FROM analytics_events WHERE created_at > (NOW() - INTERVAL ? DAY) GROUP BY dow, h",
            [$days], 'i'
        );
        $map = array_fill(0, 7, array_fill(0, 24, 0));
        foreach ($rows as $r) {
            $map[(int)$r['dow'] - 1][(int)$r['h']] = (int)$r['c'];
        }
        return $map;
    }

    /** Get device breakdown. */
    public function getDevices(int $days): array
    {
        return $this->conn->selectAll(
            "SELECT device, COUNT(*) c FROM analytics_events WHERE created_at > (NOW() - INTERVAL ? DAY) GROUP BY device ORDER BY c DESC",
            [$days], 'i'
        );
    }

    /** Get video retention data. */
    public function getRetention(int $days): array
    {
        return $this->conn->selectAll(
            "SELECT v.id, v.title, v.duration_sec, AVG(ae.progress_sec) avg_sec, COUNT(DISTINCT ae.visitor_hash) samples
             FROM analytics_events ae
             JOIN videos v ON v.id = ae.video_id
             WHERE ae.event='video_progress' AND ae.created_at > (NOW() - INTERVAL ? DAY)
             GROUP BY v.id, v.title, v.duration_sec
             ORDER BY samples DESC",
            [$days], 'i'
        );
    }
}
