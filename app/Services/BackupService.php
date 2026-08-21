<?php
declare(strict_types=1);

/**
 * Backup Service — database backup creation and listing.
 */
class BackupService
{
    /** Create a gzipped SQL dump. Returns ['ok' => true, 'file' => string, 'size' => int] or ['error' => '...']. */
    public function create(): array
    {
        $dbUser = getenv('DB_USER') ?: 'arsip';
        $dbPass = getenv('DB_PASS') ?: '';
        $dbName = getenv('DB_NAME') ?: 'arsip_layar';

        $file = 'arsip_layar_' . date('Y-m-d_H-i-s') . '.sql.gz';
        $path = BACKUP_DIR . '/' . $file;

        $cmd = sprintf(
            'mysqldump -u%s -p%s %s 2>/dev/null | gzip -9 > %s',
            escapeshellarg($dbUser),
            escapeshellarg($dbPass),
            escapeshellarg($dbName),
            escapeshellarg($path)
        );
        shell_exec($cmd);

        if (!is_file($path) || filesize($path) === 0) {
            return ['error' => 'Gagal membuat backup.'];
        }

        $size = filesize($path);
        @chmod($path, 0640);

        // Log to backups table
        $db = Connection::getInstance()->db();
        $s = $db->prepare('INSERT INTO backups(file,size_bytes) VALUES(?,?)');
        $s->bind_param('si', $file, $size);
        $s->execute();

        return ['ok' => true, 'file' => $file, 'size' => $size];
    }

    /** List all backups. */
    public function list(): array
    {
        $db = Connection::getInstance()->db();
        $rows = $db->query('SELECT id,file,size_bytes,created_at FROM backups ORDER BY created_at DESC')->fetch_all(MYSQLI_ASSOC);
        return $rows;
    }

    /** Get full path for a backup file. */
    public function getPath(string $file): ?string
    {
        // Only allow .sql.gz files with expected naming pattern
        $base = basename($file);
        if (!preg_match('/^arsip_layar_[\d_-]+\.sql\.gz$/', $base)) {
            return null;
        }
        $path = BACKUP_DIR . '/' . $base;
        // Verify resolved path is inside BACKUP_DIR
        $realPath = realpath($path);
        $realDir = realpath(BACKUP_DIR);
        if (!$realPath || !$realDir || !str_starts_with($realPath, $realDir . DIRECTORY_SEPARATOR)) {
            return null;
        }
        return is_file($realPath) ? $realPath : null;
    }

    /** Delete old backups, keeping last N. */
    public function prune(int $keep = 14): void
    {
        $files = glob(BACKUP_DIR . '/*.sql.gz');
        if (!$files || count($files) <= $keep) return;
        usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
        foreach (array_slice($files, $keep) as $f) {
            @unlink($f);
        }
    }
}
