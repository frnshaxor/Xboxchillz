<?php
declare(strict_types=1);

class Setting
{
    private Connection $conn;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    /**
     * Get a setting value (with in-memory cache).
     * 
     * Note: This uses the global setting() helper for cache consistency.
     */
    public function get(string $key, string $fallback = ''): string
    {
        return setting($this->conn->db(), $key, $fallback);
    }

    /** Set (upsert) a setting value. */
    public function set(string $key, string $value): void
    {
        set_setting($this->conn->db(), $key, $value);
    }

    /** Get multiple settings at once. */
    public function getMany(array $keys): array
    {
        $db = $this->conn->db();
        $result = [];
        foreach ($keys as $key => $fallback) {
            $result[$key] = setting($db, $key, $fallback);
        }
        return $result;
    }
}
