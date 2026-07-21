<?php
namespace App\Models;

use App\Utils\Database;

class AdminAuditLogModel
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function log(array $entry): bool
    {
        $details = $entry['details'] ?? [];
        $detailsJson = null;

        if (!empty($details)) {
            $detailsJson = json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($detailsJson === false) {
                $detailsJson = json_encode(['json_error' => json_last_error_msg()]);
            }
        }

        $stmt = $this->db->prepare("
            INSERT INTO admin_audit_logs (
                admin_id,
                admin_username,
                admin_role,
                action,
                target_type,
                target_id,
                details_json,
                ip_address,
                user_agent
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        return $stmt->execute([
            $entry['admin_id'] ?? null,
            $entry['admin_username'] ?? null,
            $entry['admin_role'] ?? null,
            $entry['action'] ?? '',
            $entry['target_type'] ?? null,
            $entry['target_id'] ?? null,
            $detailsJson,
            $entry['ip_address'] ?? null,
            $entry['user_agent'] ?? null,
        ]);
    }

    public function getLogs(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        [$whereSql, $params] = $this->buildFilterSql($filters);

        $stmt = $this->db->prepare("
            SELECT *
            FROM admin_audit_logs
            {$whereSql}
            ORDER BY created_at DESC, id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function countLogs(array $filters = []): int
    {
        [$whereSql, $params] = $this->buildFilterSql($filters);

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM admin_audit_logs
            {$whereSql}
        ");
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    public function getActions(): array
    {
        $stmt = $this->db->query("
            SELECT DISTINCT action
            FROM admin_audit_logs
            WHERE action IS NOT NULL AND action <> ''
            ORDER BY action ASC
        ");

        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function getAdminOptions(): array
    {
        $stmt = $this->db->query("
            SELECT DISTINCT admin_id, admin_username
            FROM admin_audit_logs
            WHERE admin_id IS NOT NULL
            ORDER BY admin_username ASC, admin_id ASC
        ");

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function buildFilterSql(array $filters): array
    {
        $where = [];
        $params = [];

        $action = trim((string)($filters['action'] ?? ''));
        if ($action !== '') {
            $where[] = 'action = ?';
            $params[] = $action;
        }

        $adminId = isset($filters['admin_id']) ? (int)$filters['admin_id'] : 0;
        if ($adminId > 0) {
            $where[] = 'admin_id = ?';
            $params[] = $adminId;
        }

        $targetType = trim((string)($filters['target_type'] ?? ''));
        if ($targetType !== '') {
            $where[] = 'target_type = ?';
            $params[] = $targetType;
        }

        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $where[] = 'created_at >= ?';
            $params[] = $dateFrom . ' 00:00:00';
        }

        $dateTo = trim((string)($filters['date_to'] ?? ''));
        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $where[] = 'created_at <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(admin_username LIKE ? OR action LIKE ? OR target_type LIKE ? OR details_json LIKE ? OR ip_address LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }

        return [
            $where ? 'WHERE ' . implode(' AND ', $where) : '',
            $params,
        ];
    }
}
