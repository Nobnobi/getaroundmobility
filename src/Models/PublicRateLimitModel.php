<?php
namespace App\Models;

use App\Utils\Database;

class PublicRateLimitModel
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getLimitStatus(string $action, string $ipAddress, ?string $sessionId, int $maxAttempts, int $windowMinutes): array
    {
        $action = $this->normalizeAction($action);
        $ipAddress = substr(trim($ipAddress), 0, 45);
        $sessionId = $sessionId !== null && trim($sessionId) !== '' ? substr(trim($sessionId), 0, 128) : null;
        $maxAttempts = max(1, $maxAttempts);
        $windowMinutes = max(1, $windowMinutes);

        $conditions = ['(action = ? AND ip_address = ?)'];
        $params = [$action, $ipAddress];

        if ($sessionId !== null) {
            $conditions[] = '(action = ? AND session_id = ?)';
            $params[] = $action;
            $params[] = $sessionId;
        }

        $sql = "
            SELECT
                COUNT(*) AS attempt_count,
                MIN(attempted_at) AS first_attempt_at,
                GREATEST(
                    0,
                    TIMESTAMPDIFF(
                        SECOND,
                        NOW(),
                        DATE_ADD(MIN(attempted_at), INTERVAL {$windowMinutes} MINUTE)
                    )
                ) AS remaining_seconds
            FROM public_rate_limits
            WHERE (" . implode(' OR ', $conditions) . ")
              AND successful = 1
              AND attempted_at >= (NOW() - INTERVAL {$windowMinutes} MINUTE)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
        $attemptCount = (int)($row['attempt_count'] ?? 0);
        $remaining = (int)($row['remaining_seconds'] ?? 0);

        return [
            'limited' => $attemptCount >= $maxAttempts && $remaining > 0,
            'attempt_count' => $attemptCount,
            'max_attempts' => $maxAttempts,
            'remaining_seconds' => $remaining,
            'window_minutes' => $windowMinutes,
        ];
    }

    public function recordAttempt(string $action, string $ipAddress, ?string $sessionId, bool $successful): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO public_rate_limits (action, ip_address, session_id, successful)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $this->normalizeAction($action),
            substr(trim($ipAddress), 0, 45),
            $sessionId !== null && trim($sessionId) !== '' ? substr(trim($sessionId), 0, 128) : null,
            $successful ? 1 : 0,
        ]);
    }

    private function normalizeAction(string $action): string
    {
        $action = strtolower(trim($action));
        $action = preg_replace('/[^a-z0-9_\-]/', '_', $action) ?: 'public';
        return substr($action, 0, 80);
    }
}
