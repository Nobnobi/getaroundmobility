<?php
namespace App\Models;

use App\Utils\Database;

class PromoCodeModel
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->ensureTable();
    }

    private function ensureTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS promo_codes (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                code        VARCHAR(32) NOT NULL UNIQUE,
                type        ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
                value       DECIMAL(10,2) NOT NULL,
                max_uses    INT NOT NULL DEFAULT 1,
                uses_count  INT NOT NULL DEFAULT 0,
                active      TINYINT(1) NOT NULL DEFAULT 1,
                expires_at  DATE NULL,
                created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    public function getAll(): array
    {
        $stmt = $this->db->query("SELECT * FROM promo_codes ORDER BY created_at DESC");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getByCode(string $code): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM promo_codes WHERE code = ?");
        $stmt->execute([strtoupper(trim($code))]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Returns the promo row only if it is valid for use right now.
     * Checks: active, not expired, uses_count < max_uses.
     */
    public function getValidByCode(string $code): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM promo_codes
            WHERE code = ?
              AND active = 1
              AND uses_count < max_uses
              AND (expires_at IS NULL OR expires_at >= CURDATE())
        ");
        $stmt->execute([strtoupper(trim($code))]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function incrementUse(int $id): void
    {
        $this->db->prepare("UPDATE promo_codes SET uses_count = uses_count + 1 WHERE id = ?")
                 ->execute([$id]);
    }

    public function save(array $data, ?int $id = null): bool
    {
        $code       = strtoupper(trim($data['code'] ?? ''));
        $type       = in_array($data['type'] ?? '', ['percent', 'fixed'], true) ? $data['type'] : 'percent';
        $value      = max(0, (float)($data['value'] ?? 0));
        $maxUses    = max(1, (int)($data['max_uses'] ?? 1));
        $active     = isset($data['active']) ? 1 : 0;
        $expiresAt  = !empty($data['expires_at']) ? $data['expires_at'] : null;

        if ($code === '') {
            return false;
        }

        if ($id === null) {
            $stmt = $this->db->prepare("
                INSERT INTO promo_codes (code, type, value, max_uses, active, expires_at)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            return $stmt->execute([$code, $type, $value, $maxUses, $active, $expiresAt]);
        }

        $stmt = $this->db->prepare("
            UPDATE promo_codes
               SET code = ?, type = ?, value = ?, max_uses = ?, active = ?, expires_at = ?
             WHERE id = ?
        ");
        return $stmt->execute([$code, $type, $value, $maxUses, $active, $expiresAt, $id]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare("DELETE FROM promo_codes WHERE id = ?")->execute([$id]);
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM promo_codes WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
