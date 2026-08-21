<?php
declare(strict_types=1);

class Category
{
    private Connection $conn;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    /** Get all categories ordered by name. */
    public function all(): array
    {
        return $this->conn->selectAll('SELECT * FROM categories ORDER BY name');
    }

    /** Create a new category (ignore duplicates). */
    public function create(string $name): void
    {
        $this->conn->execute('INSERT IGNORE INTO categories(name) VALUES(?)', [$name], 's');
    }

    /** Delete category and reassign videos to uncategorized. */
    public function delete(int $id): void
    {
        $this->conn->execute('UPDATE videos SET category_id=0 WHERE category_id=?', [$id], 'i');
        $this->conn->execute('DELETE FROM categories WHERE id=?', [$id], 'i');
    }
}
