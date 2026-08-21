<?php

declare(strict_types=1);

class VideoHeatmap
{
    private Connection $conn;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    /** Record which seconds a viewer watched (batch upsert). */
    public function record(int $videoId, string $viewerHash, array $seconds): void
    {
        if (empty($seconds)) {
            return;
        }
        $db = $this->conn->db();
        $stmt = $db->prepare(
            'INSERT INTO video_heatmap(video_id,viewer_hash,second_index) VALUES(?,?,?)
             ON DUPLICATE KEY UPDATE view_count=view_count+1'
        );
        foreach ($seconds as $sec) {
            $s = (int) $sec;
            $stmt->bind_param('isi', $videoId, $viewerHash, $s);
            $stmt->execute();
        }
        $stmt->close();
    }

    /** Get engagement data for a specific video. */
    public function getForVideo(int $videoId): array
    {
        return $this->conn->selectAll(
            'SELECT second_index, SUM(view_count) total FROM video_heatmap WHERE video_id=? GROUP BY second_index ORDER BY second_index',
            [$videoId],
            'i'
        );
    }

    /** Generate a visitor hash for heatmap tracking. */
    public static function visitorHash(): string
    {
        return hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0') . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    }
}
