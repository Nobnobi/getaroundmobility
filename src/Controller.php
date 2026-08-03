<?php

namespace App;

use App\Models\AdminAuditLogModel;
use App\Models\PublicRateLimitModel;

class Controller
{
    protected function normalizeAdminRole(?string $role = null): string
    {
        $role = $role ?? (string)($_SESSION['admin_role'] ?? '');
        return strtolower(str_replace(['_', '-', ' '], '', trim($role)));
    }

    protected function adminRoleAllowed(array $allowedRoles): bool
    {
        $currentRole = $this->normalizeAdminRole();
        $allowed = array_map(function ($role) {
            return $this->normalizeAdminRole((string)$role);
        }, $allowedRoles);

        return in_array($currentRole, $allowed, true);
    }

    protected function requireAdminRoleRedirect(array $allowedRoles, string $redirectPath = '/admin/orders'): void
    {
        if (!$this->adminRoleAllowed($allowedRoles)) {
            header('Location: ' . $redirectPath);
            exit;
        }
    }

    protected function requireAdminRoleJson(array $allowedRoles, string $message = 'You do not have permission to perform this action.'): void
    {
        if (!$this->adminRoleAllowed($allowedRoles)) {
            http_response_code(403);
            echo json_encode([
                'error' => $message,
                'forbidden' => true,
            ]);
            exit;
        }
    }

    protected function logAdminAction(string $action, ?string $targetType = null, $targetId = null, array $details = []): void
    {
        try {
            $logger = new AdminAuditLogModel();
            $logger->log([
                'admin_id' => isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null,
                'admin_username' => $_SESSION['admin_username'] ?? null,
                'admin_role' => $_SESSION['admin_role'] ?? null,
                'action' => $action,
                'target_type' => $targetType,
                'target_id' => $targetId !== null ? (int)$targetId : null,
                'details' => $details,
                'ip_address' => $this->getClientIpAddress(),
                'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]);
        } catch (\Throwable $e) {
            error_log('Admin audit log failed: ' . $e->getMessage());
        }
    }

    protected function checkPublicRateLimit(string $action, int $maxAttempts, int $windowMinutes): array
    {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            $ipAddress = $this->getClientIpAddress() ?? '0.0.0.0';
            $sessionId = session_id() ?: null;
            $limiter = new PublicRateLimitModel();
            $status = $limiter->getLimitStatus($action, $ipAddress, $sessionId, $maxAttempts, $windowMinutes);
            if (!empty($status['limited'])) {
                $limiter->recordAttempt($action, $ipAddress, $sessionId, false);
                return $status + [
                    'allowed' => false,
                    'message' => $this->formatPublicRateLimitMessage((int)($status['remaining_seconds'] ?? 60)),
                ];
            }

            $limiter->recordAttempt($action, $ipAddress, $sessionId, true);
            return $status + [
                'allowed' => true,
                'message' => '',
            ];
        } catch (\Throwable $e) {
            error_log('Public rate limit failed open: ' . $e->getMessage());
            return [
                'allowed' => true,
                'limited' => false,
                'message' => '',
            ];
        }
    }

    protected function enforcePublicJsonRateLimit(string $action, int $maxAttempts, int $windowMinutes): void
    {
        $status = $this->checkPublicRateLimit($action, $maxAttempts, $windowMinutes);
        if (!($status['allowed'] ?? true)) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(429);
            echo json_encode([
                'error' => $status['message'] ?? 'Too many attempts. Please try again later.',
                'rate_limited' => true,
                'remaining_seconds' => (int)($status['remaining_seconds'] ?? 0),
            ]);
            exit;
        }
    }

    private function formatPublicRateLimitMessage(int $remainingSeconds): string
    {
        $minutes = max(1, (int)ceil($remainingSeconds / 60));
        return 'Too many attempts. Please wait about ' . $minutes . ' minute' . ($minutes === 1 ? '' : 's') . ' and try again.';
    }

    protected function getClientIpAddress(): ?string
    {
        $candidates = [
            $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
            $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (!$candidate) {
                continue;
            }
            $ip = trim(explode(',', (string)$candidate)[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return null;
    }

    protected function render($view, $data = [])
    {
        extract($data);

        // Capture page content (output buffering)
        ob_start();
        include __DIR__ . "/Views/$view.php";
        $content = ob_get_clean();

        include __DIR__ . "/Views/layout.php";
        //include "Views/$view.php";

        
    }

    protected function renderAdmin($view, $data = [])
    {
        extract($data);

        // Capture page content (output buffering)
        ob_start();
        include __DIR__ . "/Views/$view.php";
        $content = ob_get_clean();

        include __DIR__ . "/Views/admin/admin-layout.php";

        
    }

    protected function renderAdminWithLayout($view, $layoutFile, $data = [])
    {
        extract($data);

        ob_start();
        include __DIR__ . "/Views/$view.php";
        $content = ob_get_clean();

        include __DIR__ . "/Views/$layoutFile";
    }

}
