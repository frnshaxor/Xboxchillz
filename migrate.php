<?php
declare(strict_types=1);

/**
 * Database Migration Runner
 * 
 * Usage:
 *   php migrate.php              — Run all pending migrations
 *   php migrate.php --status     — Show migration status
 *   php migrate.php --down <id>  — Rollback a specific migration
 * 
 * Migration files go in migrations/ directory with format:
 *   YYYYMMDD_HHMMSS_description.sql
 * 
 * Each file can contain multiple SQL statements separated by semicolons.
 * Lines starting with -- are comments and are ignored.
 */

$migrationsDir = __DIR__ . '/migrations';
$logFile = __DIR__ . '/storage/cache/migrations.json';

// Ensure directories exist
if (!is_dir($migrationsDir)) {
    @mkdir($migrationsDir, 0750, true);
}
@is_dir(__DIR__ . '/storage/cache') or @mkdir(__DIR__ . '/storage/cache', 0750, true);

// Load DB credentials
$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbUser = getenv('DB_USER') ?: 'arsip';
$dbPass = getenv('DB_PASS') ?: '';
$dbName = getenv('DB_NAME') ?: 'arsip_layar';

$db = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($db->connect_error) {
    fwrite(STDERR, "DB connection failed: " . $db->connect_error . "\n");
    exit(1);
}
$db->set_charset('utf8mb4');
mysqli_report(MYSQLI_REPORT_OFF);

// Create migrations tracking table
$db->query("CREATE TABLE IF NOT EXISTS _migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) UNIQUE NOT NULL,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Load applied migrations
$applied = [];
$result = $db->query("SELECT filename FROM _migrations ORDER BY id");
while ($row = $result->fetch_assoc()) {
    $applied[$row['filename']] = true;
}

// Get migration files
$migrationFiles = [];
if (is_dir($migrationsDir)) {
    $files = glob($migrationsDir . '/*.sql');
    if ($files) {
        foreach ($files as $file) {
            $basename = basename($file);
            $migrationFiles[$basename] = $file;
        }
    }
}
ksort($migrationFiles);

// Handle --status flag
if (in_array('--status', $argv ?? [])) {
    echo "Migration Status:\n";
    echo str_repeat('-', 60) . "\n";
    foreach ($migrationFiles as $name => $path) {
        $status = isset($applied[$name]) ? '✓ APPLIED' : '○ PENDING';
        echo "  {$status}  {$name}\n";
    }
    $pending = count(array_diff_key($migrationFiles, $applied));
    echo "\nTotal: " . count($migrationFiles) . " migrations, {$pending} pending\n";
    exit(0);
}

// Handle --down flag
if (isset($argv[1]) && $argv[1] === '--down' && isset($argv[2])) {
    $targetFile = $argv[2];
    // Look for corresponding down migration
    $downFile = str_replace('.sql', '_down.sql', $targetFile);
    $downPath = $migrationsDir . '/' . $downFile;
    if (!is_file($downPath)) {
        // Try removing the .sql and adding _down.sql
        $base = preg_replace('/\.sql$/', '', $targetFile);
        $downPath = $migrationsDir . '/' . $base . '_down.sql';
    }
    if (!is_file($downPath)) {
        fwrite(STDERR, "No down migration found for {$targetFile}\n");
        exit(1);
    }
    echo "Rolling back: {$targetFile}\n";
    $sql = file_get_contents($downPath);
    if ($db->multi_query($sql)) {
        while ($db->next_result()) { $db->store_result(); }
    }
    $db->query("DELETE FROM _migrations WHERE filename='" . $db->real_escape_string($targetFile) . "'");
    echo "Rolled back successfully.\n";
    exit(0);
}

// Run pending migrations
$pending = array_diff_key($migrationFiles, $applied);
if (empty($pending)) {
    echo "No pending migrations.\n";
    exit(0);
}

$appliedCount = 0;
foreach ($pending as $name => $path) {
    echo "Applying: {$name}... ";
    $sql = file_get_contents($path);
    
    // Remove comment-only lines for cleaner execution
    $sql = preg_replace('/^--.*$/m', '', $sql);
    $sql = trim($sql);
    
    if (empty($sql)) {
        echo "SKIP (empty)\n";
        $db->query("INSERT INTO _migrations(filename) VALUES('" . $db->real_escape_string($name) . "')");
        continue;
    }
    
    if ($db->multi_query($sql)) {
        // Consume all results
        while ($db->next_result()) { 
            if ($res = $db->store_result()) { $res->free(); }
        }
        if ($db->errno) {
            echo "ERROR: " . $db->error . "\n";
            fwrite(STDERR, "Migration {$name} failed: " . $db->error . "\n");
            exit(1);
        }
        $db->query("INSERT INTO _migrations(filename) VALUES('" . $db->real_escape_string($name) . "')");
        echo "OK\n";
        $appliedCount++;
    } else {
        echo "ERROR: " . $db->error . "\n";
        fwrite(STDERR, "Migration {$name} failed: " . $db->error . "\n");
        exit(1);
    }
}

echo "\nApplied {$appliedCount} migration(s). Done.\n";
exit(0);
