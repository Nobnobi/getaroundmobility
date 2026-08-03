<?php
namespace App\Models;

use App\Utils\Database;

class AdminLoginAttemptModel
{
    private const MAX_FAILURES = 5;
    private const WINDOW_MINUTES = 15;

    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getLockout(string $loginArea, string $username, string $ipAddress): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) AS failure_count,
                MIN(attempted_at) AS first_failed_at,
                GREATEST(
                    0,
                    TIMESTAMPDIFF(
                        SECOND,
                        NOW(),
                        DATE_ADD(MIN(attempted_at), INTERVAL " . self::WINDOW_MINUTES . " MINUTE)
                    )
                ) AS remaining_seconds
            FROM admin_login_attempts
            WHERE login_area = ?
              AND username = ?
              AND ip_address = ?
              AND successful = 0
              AND attempted_at >= (NOW() - INTERVAL " . self::WINDOW_MINUTES . " MINUTE)
        ");
        $stmt->execute([
            $this->normalizeLoginArea($loginArea),
            $this->normalizeUsername($username),
            $ipAddress,
        ]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
        $failureCount = (int)($row['failure_count'] ?? 0);
        $remaining = (int)($row['remaining_seconds'] ?? 0);

        if ($failureCount < self::MAX_FAILURES || empty($row['first_failed_at']) || $remaining <= 0) {
            return [
                'locked' => false,
                'remaining_seconds' => 0,
                'failure_count' => $failureCount,
            ];
        }

        return [
            'locked' => true,
            'remaining_seconds' => $remaining,
            'failure_count' => $failureCount,
        ];
    }

    public function recordFailure(string $loginArea, string $username, string $ipAddress): void
    {
        $this->recordAttempt($loginArea, $username, $ipAddress, false);
    }

    public function recordSuccess(string $loginArea, string $username, string $ipAddress): void
    {
        $this->recordAttempt($loginArea, $username, $ipAddress, true);
        $this->clearFailures($loginArea, $username, $ipAddress);
    }

    public function clearFailures(string $loginArea, string $username, string $ipAddress): void
    {
        $stmt = $this->db->prepare("
            DELETE FROM admin_login_attempts
            WHERE login_area = ?
              AND username = ?
              AND ip_address = ?
              AND successful = 0
        ");
        $stmt->execute([
            $this->normalizeLoginArea($loginArea),
            $this->normalizeUsername($username),
            $ipAddress,
        ]);
    }

    public function getAttempts(array $filters = [], int $page = 1, int $perPage = 15): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        [$whereSql, $params] = $this->buildAttemptFilterSql($filters);

        $stmt = $this->db->prepare("
            SELECT *
            FROM admin_login_attempts
            {$whereSql}
            ORDER BY attempted_at DESC, id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function countAttempts(array $filters = []): int
    {
        [$whereSql, $params] = $this->buildAttemptFilterSql($filters);

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM admin_login_attempts
            {$whereSql}
        ");
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    public function getLoginAreas(): array
    {
        $stmt = $this->db->query("
            SELECT DISTINCT login_area
            FROM admin_login_attempts
            WHERE login_area IS NOT NULL AND login_area <> ''
            ORDER BY login_area ASC
        ");

        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function getAttemptUsernames(): array
    {
        $stmt = $this->db->query("
            SELECT DISTINCT username
            FROM admin_login_attempts
            WHERE username IS NOT NULL AND username <> ''
            ORDER BY username ASC
        ");

        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function pruneOlderThanDays(int $days): int
    {
        $days = max(1, min(3650, $days));
        $stmt = $this->db->prepare(
            "DELETE FROM admin_login_attempts
             WHERE attempted_at < (NOW() - INTERVAL ? DAY)"
        );
        $stmt->execute([$days]);
        return (int)$stmt->rowCount();
    }

    private function recordAttempt(string $loginArea, string $username, string $ipAddress, bool $successful): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO admin_login_attempts (login_area, username, ip_address, successful)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $this->normalizeLoginArea($loginArea),
            $this->normalizeUsername($username),
            $ipAddress,
            $successful ? 1 : 0,
        ]);
    }

    private function normalizeLoginArea(string $loginArea): string
    {
        $loginArea = strtolower(trim($loginArea));
        return $loginArea !== '' ? substr($loginArea, 0, 30) : 'admin';
    }

    private function normalizeUsername(string $username): string
    {
        return substr(strtolower(trim($username)), 0, 120);
    }

    private function buildAttemptFilterSql(array $filters): array
    {
        $where = [];
        $params = [];

        $loginArea = trim((string)($filters['login_area'] ?? ''));
        if ($loginArea !== '') {
            $where[] = 'login_area = ?';
            $params[] = $this->normalizeLoginArea($loginArea);
        }

        $username = trim((string)($filters['username'] ?? ''));
        if ($username !== '') {
            $where[] = 'username = ?';
            $params[] = $this->normalizeUsername($username);
        }

        $result = trim((string)($filters['result'] ?? ''));
        if ($result === 'success') {
            $where[] = 'successful = 1';
        } elseif ($result === 'failed') {
            $where[] = 'successful = 0';
        }

        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $where[] = 'attempted_at >= ?';
            $params[] = $dateFrom . ' 00:00:00';
        }

        $dateTo = trim((string)($filters['date_to'] ?? ''));
        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $where[] = 'attempted_at <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(login_area LIKE ? OR username LIKE ? OR ip_address LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like);
        }

        return [
            $where ? 'WHERE ' . implode(' AND ', $where) : '',
            $params,
        ];
    }
}
