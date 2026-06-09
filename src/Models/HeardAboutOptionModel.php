<?php
namespace App\Models;

use App\Utils\Database;

class HeardAboutOptionModel
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->ensureTable();
    }

    private function ensureTable(): void
    {
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS heard_about_options (
                id INT AUTO_INCREMENT PRIMARY KEY,
                label VARCHAR(120) NOT NULL UNIQUE,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    public function getAll(): array
    {
        $stmt = $this->db->query("SELECT * FROM heard_about_options ORDER BY sort_order ASC, label ASC");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getActive(): array
    {
        $stmt = $this->db->query("SELECT * FROM heard_about_options WHERE is_active = 1 ORDER BY sort_order ASC, label ASC");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM heard_about_options WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $data, ?int $id = null): bool
    {
        $label = trim((string)($data['label'] ?? ''));
        $isActive = isset($data['is_active']) ? 1 : 0;
        $sortOrder = is_numeric($data['sort_order'] ?? null) ? (int)$data['sort_order'] : 0;

        if ($label === '') {
            return false;
        }

        if ($id === null) {
            $stmt = $this->db->prepare(
                "INSERT INTO heard_about_options (label, is_active, sort_order)
                 VALUES (?, ?, ?)"
            );
            return $stmt->execute([$label, $isActive, $sortOrder]);
        }

        $stmt = $this->db->prepare(
            "UPDATE heard_about_options
             SET label = ?, is_active = ?, sort_order = ?
             WHERE id = ?"
        );
        return $stmt->execute([$label, $isActive, $sortOrder, $id]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare("DELETE FROM heard_about_options WHERE id = ?");
        $stmt->execute([$id]);
    }
}
