<?php

declare(strict_types=1);

/**
 * Database Connection — singleton wrapper for mysqli.
 * Provides prepared-statement helpers to eliminate repetitive boilerplate.
 */
class Connection
{
    private static ?self $instance = null;
    private mysqli $db;

    private function __construct()
    {
        $this->db = new mysqli(
            getenv('DB_HOST') ?: '127.0.0.1',
            getenv('DB_USER') ?: 'arsip',
            getenv('DB_PASS') ?: '',
            getenv('DB_NAME') ?: 'arsip_layar'
        );
        if ($this->db->connect_error) {
            http_response_code(500);
            exit('Database belum siap.');
        }
        $this->db->set_charset('utf8mb4');
        mysqli_report(MYSQLI_REPORT_OFF);
    }

    /** Get the singleton instance. */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /** Raw mysqli handle (for complex queries). */
    public function db(): mysqli
    {
        return $this->db;
    }

    /**
     * Execute a prepared SELECT and return the first row as assoc array, or null.
     *
     *   $row = $conn->selectOne('SELECT * FROM admins WHERE id=?', [$id]);
     */
    public function selectOne(string $sql, array $params = [], ?string $types = null): ?array
    {
        $stmt = $this->prepare($sql, $params, $types);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $result ?: null;
    }

    /**
     * Execute a prepared SELECT and return all rows as array of assoc arrays.
     *
     *   $rows = $conn->selectAll('SELECT * FROM categories ORDER BY name');
     */
    public function selectAll(string $sql, array $params = [], ?string $types = null): array
    {
        $stmt = $this->prepare($sql, $params, $types);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $result;
    }

    /**
     * Execute a prepared INSERT and return the insert ID.
     *
     *   $id = $conn->insert('INSERT INTO categories(name) VALUES(?)', [$name], 's');
     */
    public function insert(string $sql, array $params = [], ?string $types = null): int
    {
        $stmt = $this->prepare($sql, $params, $types);
        $stmt->execute();
        $id = $this->db->insert_id;
        $stmt->close();

        return $id;
    }

    /**
     * Execute a prepared UPDATE/DELETE and return affected rows.
     *
     *   $affected = $conn->execute('DELETE FROM categories WHERE id=?', [$id], 'i');
     */
    public function execute(string $sql, array $params = [], ?string $types = null): int
    {
        $stmt = $this->prepare($sql, $params, $types);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        return $affected;
    }

    /**
     * Simple query (no params) — for adminer/debug only.
     */
    public function query(string $sql): \mysqli_result
    {
        return $this->db->query($sql);
    }

    /** Begin transaction. */
    public function beginTransaction(): bool
    {
        return $this->db->begin_transaction();
    }

    /** Commit transaction. */
    public function commit(): bool
    {
        return $this->db->commit();
    }

    /** Rollback transaction. */
    public function rollback(): bool
    {
        return $this->db->rollback();
    }

    /** Get the last insert ID. */
    public function insertId(): int
    {
        return $this->db->insert_id;
    }

    /**
     * Auto-detect types from PHP values and prepare statement.
     */
    private function prepare(string $sql, array $params, ?string $types): \mysqli_stmt
    {
        // Reconnect if server went away
        if ($this->db->errno === 2006 || $this->db->errno === 2013 || !$this->db->ping()) {
            $this->db->close();
            $this->db = new mysqli(
                getenv('DB_HOST') ?: '127.0.0.1',
                getenv('DB_USER') ?: 'arsip',
                getenv('DB_PASS') ?: '',
                getenv('DB_NAME') ?: 'arsip_layar'
            );
            $this->db->set_charset('utf8mb4');
        }
        $stmt = $this->db->prepare($sql);
        if ($stmt === false) {
            http_response_code(500);
            exit('Query error: ' . $this->db->error);
        }
        if (!empty($params)) {
            $types = $types ?? $this->inferTypes($params);
            $stmt->bind_param($types, ...$params);
        }

        return $stmt;
    }

    /**
     * Infer MySQL type string from PHP values.
     *   int   → 'i'
     *   float → 'd'
     *   string → 's'
     *   NULL  → 's'
     */
    private function inferTypes(array $params): string
    {
        $types = '';
        foreach ($params as $p) {
            if (is_int($p)) {
                $types .= 'i';
                continue;
            }
            if (is_float($p)) {
                $types .= 'd';
                continue;
            }
            $types .= 's';
        }

        return $types;
    }
}
