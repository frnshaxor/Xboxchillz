<?php
declare(strict_types=1);

/**
 * VideoUpload Service — handles upload validation, transcoding, and preview generation.
 */
class VideoUpload
{
    const CHUNK_SIZE = 5 * 1024 * 1024; // 5MB per chunk

    private Video $videoModel;
    private ActivityLog $activityModel;

    public function __construct(Connection $conn)
    {
        $this->videoModel    = new Video($conn);
        $this->activityModel = new ActivityLog($conn);
    }

    /**
     * Process uploaded file(s). Returns count of successfully uploaded files.
     */
    public function process(string $title, int $categoryId, array $files): int
    {
        $limitMb = (int)setting(Connection::getInstance()->db(), 'upload_max_mb', '2048');
        $uploaded = 0;
        $maxFiles = 20; // Safety limit: max 20 files per batch upload

        foreach ($files as $f) {
            if ($uploaded >= $maxFiles) break;
            $result = $this->processOne($title, $categoryId, $f, $limitMb);
            if ($result) $uploaded++;
        }

        return $uploaded;
    }

    /**
     * Process a single file upload.
     */
    private function processOne(string $title, int $categoryId, array $f, int $limitMb): bool
    {
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if ($ext !== 'mp4') return false;
        if ($f['size'] > $limitMb * 1024 * 1024) return false;

        // MIME validation
        $fi = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $fi->file($f['tmp_name']) ?: '';
        $isMp4 = in_array($mime, ['video/mp4', 'application/mp4'], true);
        if (!$isMp4 && $mime === 'application/octet-stream') {
            $handle = fopen($f['tmp_name'], 'rb');
            if ($handle) { $header = fread($handle, 12); fclose($handle); $isMp4 = str_contains($header, 'ftyp'); }
        }
        if (!$isMp4) return false;

        $fileTitle = $title ?: pathinfo($f['name'], PATHINFO_FILENAME);
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($fileTitle));
        $slug = trim($slug, '-');          // strip leading/trailing hyphens from special chars
        $slug = preg_replace('/-+/', '-', $slug); // collapse consecutive hyphens
        if ($slug === '') $slug = 'video';  // fallback for titles with no alphanumeric chars
        $slug .= '-' . bin2hex(random_bytes(3));
        $dir = MEDIA_ROOT . '/' . $slug;
        mkdir($dir, 0750, true);
        move_uploaded_file($f['tmp_name'], $dir . '/source.mp4');
        $size = filesize($dir . '/source.mp4') ?: 0;

        // Probe duration
        $duration = 0;
        $probe = shell_exec('ffprobe -v error -show_entries format=duration -of default=nokey=1:noprint_wrappers=1 ' . escapeshellarg($dir . '/source.mp4') . ' 2>/dev/null');
        if ($probe) $duration = (int)round((float)trim($probe));

        $poster = 'media/' . $slug . '/poster.jpg';
        $src = 'media/' . $slug . '/source.mp4';
        $status = 'processing';

        $this->videoModel->create([
            'title'       => $fileTitle,
            'slug'        => $slug,
            'category_id' => $categoryId,
            'poster'      => $poster,
            'source'      => $src,
            'duration'    => $duration,
            'size'        => $size,
            'status'      => $status,
        ]);

        $this->activityModel->record((int)$_SESSION['admin_id'], 'video_upload', $slug);

        // Always fire arsip-hls-worker directly — it handles poster, preview,
        // HLS transcode, status update (processing→ready), and Telegram notify.
        $worker = '/usr/local/sbin/arsip-hls-worker';
        if (is_executable($worker)) {
            shell_exec('setsid nohup ' . escapeshellarg($worker) . ' ' . escapeshellarg($slug) . ' > /dev/null 2>&1 < /dev/null &');
        }

        return true;
    }


    /** Delete a video and its files. */
    public function delete(int $id): bool
    {
        $deleted = $this->videoModel->delete($id);
        if ($deleted) {
            $this->activityModel->record((int)$_SESSION['admin_id'], 'video_delete', (string)$id);
        }
        return $deleted;
    }

    // ─── Chunked Upload Methods (Bulk Upload) ───

    /** Create a chunked upload session. */
    public function createUploadSession(string $filename, int $totalSize): array
    {
        $uploadId = bin2hex(random_bytes(16));
        $dir = UPLOADS_DIR . '/' . $uploadId;
        if (!mkdir($dir, 0750, true)) { // FIX #6: Removed @ — let errors surface
            return ['error' => 'Gagal membuat sesi upload. Periksa permission storage/uploads/'];
        }
        file_put_contents($dir . '/meta.json', json_encode([
            'filename'   => $filename,
            'total_size' => $totalSize,
            'created_at' => time(),
        ]));
        return [
            'ok'         => true,
            'upload_id'  => $uploadId,
            'chunk_size' => self::CHUNK_SIZE,
        ];
    }

    /** Save a single chunk to disk. */
    public function saveChunk(string $uploadId, int $chunkNumber, string $tmpPath): array
    {
        $dir = UPLOADS_DIR . '/' . $uploadId;
        if (!is_dir($dir)) {
            return ['error' => 'Sesi upload tidak ditemukan'];
        }
        // FIX #8: Reject chunks if session was aborted
        if (is_file($dir . '/.aborted')) {
            return ['error' => 'Sesi upload ini sudah dibatalkan'];
        }
        $chunkFile = $dir . '/chunk_' . str_pad((string)$chunkNumber, 6, '0', STR_PAD_LEFT);
        if (!move_uploaded_file($tmpPath, $chunkFile)) {
            return ['error' => 'Gagal menyimpan chunk'];
        }
        $uploadedChunks = $this->listChunks($uploadId);
        $meta = json_decode(file_get_contents($dir . '/meta.json'), true) ?: [];
        $totalChunks = (int)ceil(($meta['total_size'] ?? 0) / self::CHUNK_SIZE);
        return [
            'ok'              => true,
            'uploaded_chunks' => $uploadedChunks,
            'total_chunks'    => $totalChunks,
        ];
    }

    /** Get list of already-uploaded chunk numbers. */
    public function listChunks(string $uploadId): array
    {
        $dir = UPLOADS_DIR . '/' . $uploadId;
        if (!is_dir($dir)) return [];
        $chunks = [];
        foreach (glob($dir . '/chunk_*') as $file) {
            $name = basename($file);
            if (preg_match('/^chunk_(\d+)$/', $name, $m)) {
                $chunks[] = (int)$m[1];
            }
        }
        sort($chunks);
        return $chunks;
    }

    /** Assemble chunks into final file and process. */
    public function assembleAndProcess(string $uploadId, int $categoryId, string $title): array
    {
        $dir = UPLOADS_DIR . '/' . $uploadId;
        if (!is_dir($dir)) {
            return ['error' => 'Sesi upload tidak ditemukan'];
        }
        $meta = json_decode(file_get_contents($dir . '/meta.json'), true) ?: [];
        $filename = $meta['filename'] ?? 'video.mp4';
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($ext !== 'mp4') {
            $this->cleanup($uploadId);
            return ['error' => 'Hanya file MP4 yang diperbolehkan'];
        }

        $chunks = $this->listChunks($uploadId);
        $totalChunks = (int)ceil(($meta['total_size'] ?? 0) / self::CHUNK_SIZE);
        if (count($chunks) < $totalChunks) {
            return ['error' => 'Belum semua chunk terkirim (' . count($chunks) . '/' . $totalChunks . ')'];
        }

        // Validate total assembled size against server limit
        $limitMb = (int)setting(Connection::getInstance()->db(), 'upload_max_mb', '2048');
        if (($meta['total_size'] ?? 0) > $limitMb * 1024 * 1024) {
            $this->cleanup($uploadId);
            return ['error' => 'Ukuran file melebihi batas ' . $limitMb . ' MB'];
        }

        $fileTitle = $title ?: pathinfo($filename, PATHINFO_FILENAME);
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($fileTitle));
        $slug = trim($slug, '-');          // strip leading/trailing hyphens from special chars
        $slug = preg_replace('/-+/', '-', $slug); // collapse consecutive hyphens
        if ($slug === '') $slug = 'video';  // fallback for titles with no alphanumeric chars
        $slug .= '-' . bin2hex(random_bytes(3));
        $destDir = MEDIA_ROOT . '/' . $slug;
        mkdir($destDir, 0750, true);
        $destFile = $destDir . '/source.mp4';

        // Concatenate chunks
        $out = fopen($destFile, 'wb');
        if (!$out) { $this->cleanup($uploadId); return ['error' => 'Gagal menulis file output']; }
        foreach ($chunks as $c) {
            $chunkPath = $dir . '/chunk_' . str_pad((string)$c, 6, '0', STR_PAD_LEFT);
            $in = @fopen($chunkPath, 'rb');
            if ($in) {
                while (!feof($in)) {
                    $buf = fread($in, 8192);
                    if ($buf !== false && $buf !== '') fwrite($out, $buf);
                }
                fclose($in);
            }
        }
        fclose($out);

        $size = filesize($destFile) ?: 0;

        // FIX #5: Validate assembled file size before DB insert
        $expectedSize = $meta['total_size'] ?? 0;
        if ($size < 1024) {
            @unlink($destFile);
            $this->cleanup($uploadId);
            return ['error' => 'File hasil assembly terlalu kecil (' . $size . ' byte) — kemungkinan corrupt'];
        }
        if ($expectedSize > 0 && abs($size - $expectedSize) > 1024) {
            // Allow 1KB tolerance for chunk boundary rounding
            @unlink($destFile);
            $this->cleanup($uploadId);
            return ['error' => 'Ukuran file tidak cocok (expected ' . $expectedSize . ', got ' . $size . ')'];
        }

        // MIME validation (same as processOne)
        $fi = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $fi->file($destFile) ?: '';
        $isMp4 = in_array($mime, ['video/mp4', 'application/mp4'], true);
        if (!$isMp4 && $mime === 'application/octet-stream') {
            $handle = fopen($destFile, 'rb');
            if ($handle) { $header = fread($handle, 12); fclose($handle); $isMp4 = str_contains($header, 'ftyp'); }
        }
        if (!$isMp4) {
            @unlink($destFile);
            $this->cleanup($uploadId);
            return ['error' => 'File bukan MP4 valid (MIME: ' . ($mime ?: 'unknown') . ')'];
        }

        // Probe duration
        $duration = 0;
        $probe = shell_exec('ffprobe -v error -show_entries format=duration -of default=nokey=1:noprint_wrappers=1 ' . escapeshellarg($destFile) . ' 2>/dev/null');
        if ($probe) $duration = (int)round((float)trim($probe));

        $poster = 'media/' . $slug . '/poster.jpg';
        $src = 'media/' . $slug . '/source.mp4';
        $status = 'processing';

        $this->videoModel->create([
            'title'       => $fileTitle,
            'slug'        => $slug,
            'category_id' => $categoryId,
            'poster'      => $poster,
            'source'      => $src,
            'duration'    => $duration,
            'size'        => $size,
            'status'      => $status,
        ]);

        $this->activityModel->record((int)$_SESSION['admin_id'], 'video_upload', $slug);

        // Fire arsip-hls-worker
        $worker = '/usr/local/sbin/arsip-hls-worker';
        if (is_executable($worker)) {
            shell_exec('setsid nohup ' . escapeshellarg($worker) . ' ' . escapeshellarg($slug) . ' > /dev/null 2>&1 < /dev/null &');
        }

        // Cleanup temp chunks
        $this->cleanup($uploadId);

        return ['ok' => true, 'slug' => $slug, 'title' => $fileTitle, 'status' => $status];
    }

    /** Remove temp upload directory. */
    public function cleanup(string $uploadId): void
    {
        $dir = UPLOADS_DIR . '/' . preg_replace('#[^a-f0-9]#', '', $uploadId);
        if (is_dir($dir)) {
            // FIX #8: Write abort flag before cleanup to prevent concurrent chunk writes
            @file_put_contents($dir . '/.aborted', '1');
            $files = glob($dir . '/*');
            foreach ($files as $file) { @unlink($file); }
            @rmdir($dir);
        }
    }

    // ─── Stale Upload Cleanup ───

    /** Remove upload sessions older than $maxAge seconds. Returns count removed. */
    public function pruneStaleUploads(int $maxAge = 86400): int
    {
        $uploadsDir = UPLOADS_DIR;
        if (!is_dir($uploadsDir)) return 0;
        $removed = 0;
        $now = time();
        foreach (glob($uploadsDir . '/*') as $subdir) {
            if (!is_dir($subdir)) continue;
            $metaFile = $subdir . '/meta.json';
            if (is_file($metaFile)) {
                $meta = json_decode(file_get_contents($metaFile), true) ?: [];
                $createdAt = $meta['created_at'] ?? 0;
                if (($now - $createdAt) > $maxAge) {
                    $this->cleanup(basename($subdir));
                    $removed++;
                }
            } else {
                // No meta.json — definitely stale
                $this->cleanup(basename($subdir));
                $removed++;
            }
        }
        return $removed;
    }
}
