<?php
declare(strict_types=1);

class Video
{
    private Connection $conn;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    /** Insert a new video record. */
    public function create(array $data): int
    {
        return $this->conn->insert(
            'INSERT INTO videos(title,slug,category_id,poster,source,duration_sec,size_bytes,status) VALUES(?,?,?,?,?,?,?,?)',
            [$data['title'], $data['slug'], $data['category_id'], $data['poster'], $data['source'], $data['duration'], $data['size'], $data['status']],
            'ssissiis'
        );
    }

    /** Find video by ID with category name. */
    public function findById(int $id): ?array
    {
        return $this->conn->selectOne(
            'SELECT v.*, c.name category FROM videos v LEFT JOIN categories c ON c.id=v.category_id WHERE v.id=?',
            [$id], 'i'
        );
    }

    /** Find video by ID (raw, no join). */
    public function findRawById(int $id): ?array
    {
        return $this->conn->selectOne('SELECT * FROM videos WHERE id=?', [$id], 'i');
    }

    /** Find video by slug. */
    public function findBySlug(string $slug): ?array
    {
        return $this->conn->selectOne(
            'SELECT v.*, c.name category FROM videos v LEFT JOIN categories c ON c.id=v.category_id WHERE v.slug=?',
            [$slug], 's'
        );
    }

    /** Get all videos with category name, ordered by newest. */
    public function all(?int $categoryId = null): array
    {
        if ($categoryId) {
            return $this->conn->selectAll(
                'SELECT v.*, c.name category FROM videos v LEFT JOIN categories c ON c.id=v.category_id WHERE v.category_id=? ORDER BY v.created_at DESC',
                [$categoryId], 'i'
            );
        }
        return $this->conn->selectAll(
            'SELECT v.*, c.name category FROM videos v LEFT JOIN categories c ON c.id=v.category_id ORDER BY v.created_at DESC'
        );
    }

    /** Increment view count. */
    public function incrementViews(int $id): void
    {
        $this->conn->execute('UPDATE videos SET views=views+1 WHERE id=?', [$id], 'i');
    }

    /** Delete video and its files. */
    public function delete(int $id): bool
    {
        $video = $this->conn->selectOne('SELECT slug FROM videos WHERE id=?', [$id], 'i');
        if (!$video) return false;

        $dir = MEDIA_ROOT . '/' . $video['slug'];
        if (is_dir($dir) && strpos(realpath($dir) ?: '', realpath(MEDIA_ROOT)) === 0) {
            foreach (glob($dir . '/*') ?: [] as $f) @unlink($f);
            @rmdir($dir);
        }
        $this->conn->execute('DELETE FROM videos WHERE id=?', [$id], 'i');
        return true;
    }

    /** Get source path by ID. */
    public function getSourcePath(int $id): ?string
    {
        $row = $this->conn->selectOne('SELECT title,source FROM videos WHERE id=?', [$id], 'i');
        return $row ? APP_ROOT . '/' . ltrim($row['source'], '/') : null;
    }

    /** Get poster path by ID. */
    public function getPosterPath(int $id): ?string
    {
        $row = $this->conn->selectOne('SELECT poster FROM videos WHERE id=?', [$id], 'i');
        return $row ? APP_ROOT . '/' . ltrim($row['poster'], '/') : null;
    }

    /** Update status. */
    public function updateStatus(int $id, string $status): void
    {
        $this->conn->execute('UPDATE videos SET status=? WHERE id=?', [$status, $id], 'si');
    }

    /**
     * Search and paginate videos for the library view.
     * Returns ['total' => int, 'videos' => array].
     */
    public function searchPaginated(string $search = '', int $page = 1, int $perPage = 64): array
    {
        $offset = max(0, ($page - 1) * $perPage);

        if ($search !== '') {
            $like = '%' . $search . '%';
            $countRow = $this->conn->selectOne(
                'SELECT COUNT(*) c FROM videos v LEFT JOIN categories c ON c.id=v.category_id WHERE v.title LIKE ? OR c.name LIKE ?',
                [$like, $like], 'ss'
            );
            $total = (int)($countRow['c'] ?? 0);

            $videos = $this->conn->selectAll(
                'SELECT v.id,v.title,v.slug,v.category_id,v.poster,v.source,v.duration_sec,v.size_bytes,v.views,v.status,v.created_at,c.name AS category'
                . ' FROM videos v LEFT JOIN categories c ON c.id=v.category_id'
                . ' WHERE v.title LIKE ? OR c.name LIKE ?'
                . ' ORDER BY v.created_at DESC LIMIT ? OFFSET ?',
                [$like, $like, $perPage, $offset], 'ssii'
            );
        } else {
            $countRow = $this->conn->selectOne(
                'SELECT COUNT(*) c FROM videos v LEFT JOIN categories c ON c.id=v.category_id'
            );
            $total = (int)($countRow['c'] ?? 0);

            $videos = $this->conn->selectAll(
                'SELECT v.id,v.title,v.slug,v.category_id,v.poster,v.source,v.duration_sec,v.size_bytes,v.views,v.status,v.created_at,c.name AS category'
                . ' FROM videos v LEFT JOIN categories c ON c.id=v.category_id'
                . ' ORDER BY v.created_at DESC LIMIT ? OFFSET ?',
                [$perPage, $offset], 'ii'
            );
        }

        return ['total' => $total, 'videos' => $videos];
    }

    /** Update video metadata (title + category). */
    public function updateMetadata(int $id, string $title, int $categoryId): bool
    {
        $affected = $this->conn->execute(
            'UPDATE videos SET title=?, category_id=? WHERE id=?',
            [$title, $categoryId, $id], 'sii'
        );
        return $affected > 0;
    }
}
