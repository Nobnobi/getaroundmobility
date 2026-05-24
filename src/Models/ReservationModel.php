<?php
namespace App\Models;

use App\Utils\Database;

class ReservationModel {
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->ensureColumns();
    }

    private function ensureColumns(): void
    {
        $notesCol = $this->db->query("SHOW COLUMNS FROM reservations LIKE 'notes'");
        if (!$notesCol || !$notesCol->fetch(\PDO::FETCH_ASSOC)) {
            $this->db->exec("ALTER TABLE reservations ADD COLUMN notes TEXT NULL AFTER status");
        }
    }

    /**
     * Get reservations with optional status filter and pagination
     * @param string|null $status 'pending', 'completed', or null for all
     * @param int $page
     * @param int $perPage
     * @return array [reservations, totalReservations, totalPages]
     */
    public function getReservations($status = null, $page = 1, $perPage = 30, $search = '', $orderId = null)
    {
        $whereClauses = [];
        $params = [];
        if ($status === 'pending') {
            $whereClauses[] = "r.status IN ('pending', 'paid')";
        } elseif ($status === 'completed') {
            $whereClauses[] = 'r.status = :status';
            $params[':status'] = $status;
        }
        if ($search !== '') {
            $whereClauses[] = "(r.order_id LIKE :search OR r.reservation_id LIKE :search OR r.scooter_id LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }
        if ($orderId !== null) {
            $whereClauses[] = 'r.order_id = :order_id';
            $params[':order_id'] = (int)$orderId;
        }
        $where = count($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

        $countSql = "SELECT COUNT(*) FROM reservations r
                     LEFT JOIN scooters s ON s.scooter_id = r.scooter_id
                     LEFT JOIN products p ON p.product_id = s.product_id
                     $where";
        $countStmt = $this->db->prepare($countSql);
        foreach ($params as $k => $v) {
            $countStmt->bindValue($k, $v);
        }
        $countStmt->execute();
        $totalReservations = $countStmt->fetchColumn();
        $totalPages = max(1, ceil($totalReservations / $perPage));

        $sql = "SELECT r.*,
                       p.product_name
                FROM reservations r
                LEFT JOIN scooters s ON s.scooter_id = r.scooter_id
                LEFT JOIN products p ON p.product_id = s.product_id
                $where
                ORDER BY r.reservation_id DESC
                LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, \PDO::PARAM_INT);
        $stmt->execute();
        $reservations = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return [
            'reservations' => $reservations,
            'totalReservations' => $totalReservations,
            'totalPages' => $totalPages
        ];
    }

    public function getReservationOrderIds($status = null)
    {
        $whereClauses = [];
        $params = [];

        if ($status === 'pending') {
            $whereClauses[] = "status IN ('pending', 'paid')";
        } elseif ($status === 'completed') {
            $whereClauses[] = 'status = :status';
            $params[':status'] = $status;
        }

        $where = count($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';
        $sql = "SELECT DISTINCT order_id FROM reservations {$where} ORDER BY order_id DESC";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Get reservations overlapping a date range
     */
    public function getReservationsBetween($pickup, $return) {
        $sql = "SELECT product_id, variation_id, qty, pickup_datetime, return_datetime FROM reservations WHERE status IN ('pending', 'paid') AND NOT (return_datetime <= ? OR pickup_datetime >= ? )";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$pickup, $return]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getAssignableScootersForReservation(int $reservationId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM reservations WHERE reservation_id = ?");
        $stmt->execute([$reservationId]);
        $reservation = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$reservation) {
            return [];
        }

        $currentScooterId = (int)($reservation['scooter_id'] ?? 0);
        if ($currentScooterId <= 0) {
            return [];
        }

        $scooterStmt = $this->db->prepare("SELECT product_id, variation_id FROM scooters WHERE scooter_id = ? LIMIT 1");
        $scooterStmt->execute([$currentScooterId]);
        $currentScooter = $scooterStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$currentScooter) {
            return [$currentScooterId];
        }

        $productId = (int)$currentScooter['product_id'];
        $variationId = $currentScooter['variation_id'];
        $variationIsNull = ($variationId === null || (int)$variationId === 0);

        $sql = "
            SELECT s.scooter_id
            FROM scooters s
            WHERE s.product_id = :product_id
              AND (
                    (:variation_is_null = 1 AND (s.variation_id IS NULL OR s.variation_id = 0))
                    OR
                    (:variation_is_null = 0 AND s.variation_id = :variation_id)
              )
              AND (s.status = 'available' OR s.scooter_id = :current_scooter_id)
              AND NOT EXISTS (
                    SELECT 1
                    FROM reservations r
                    WHERE r.scooter_id = s.scooter_id
                      AND r.reservation_id <> :reservation_id
                      AND r.status IN ('pending','approved','paid')
                      AND NOT (r.return_datetime <= :pickup_datetime OR r.pickup_datetime >= :return_datetime)
              )
            ORDER BY s.scooter_id ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':product_id', $productId, \PDO::PARAM_INT);
        $stmt->bindValue(':variation_is_null', $variationIsNull ? 1 : 0, \PDO::PARAM_INT);
        if ($variationIsNull) {
            $stmt->bindValue(':variation_id', 0, \PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':variation_id', (int)$variationId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':current_scooter_id', $currentScooterId, \PDO::PARAM_INT);
        $stmt->bindValue(':reservation_id', $reservationId, \PDO::PARAM_INT);
        $stmt->bindValue(':pickup_datetime', (string)$reservation['pickup_datetime'], \PDO::PARAM_STR);
        $stmt->bindValue(':return_datetime', (string)$reservation['return_datetime'], \PDO::PARAM_STR);
        $stmt->execute();

        $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        $rows = array_map('intval', $rows);
        if (!in_array($currentScooterId, $rows, true)) {
            array_unshift($rows, $currentScooterId);
        }
        return array_values(array_unique($rows));
    }

    public function updateReservationAssignment(int $reservationId, int $newScooterId, string $notes = ''): array
    {
        $notes = trim($notes);
        if ($reservationId <= 0 || $newScooterId <= 0) {
            return ['success' => false, 'message' => 'Invalid reservation or scooter selection.'];
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT * FROM reservations WHERE reservation_id = ? LIMIT 1");
            $stmt->execute([$reservationId]);
            $reservation = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$reservation) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Reservation record not found.'];
            }

            $oldScooterId = (int)$reservation['scooter_id'];

            $oldScooterStmt = $this->db->prepare("SELECT product_id, variation_id FROM scooters WHERE scooter_id = ? LIMIT 1");
            $oldScooterStmt->execute([$oldScooterId]);
            $oldScooter = $oldScooterStmt->fetch(\PDO::FETCH_ASSOC);

            $newScooterStmt = $this->db->prepare("SELECT scooter_id, product_id, variation_id, status FROM scooters WHERE scooter_id = ? LIMIT 1");
            $newScooterStmt->execute([$newScooterId]);
            $newScooter = $newScooterStmt->fetch(\PDO::FETCH_ASSOC);
            if (!$newScooter) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Selected scooter does not exist.'];
            }

            if ($oldScooter && ((int)$oldScooter['product_id'] !== (int)$newScooter['product_id'])) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Selected scooter does not match the reserved product.'];
            }

            $oldVariation = $oldScooter['variation_id'] ?? null;
            $newVariation = $newScooter['variation_id'] ?? null;
            $oldVariationNorm = ($oldVariation === null) ? 0 : (int)$oldVariation;
            $newVariationNorm = ($newVariation === null) ? 0 : (int)$newVariation;
            if ($oldScooter && $oldVariationNorm !== $newVariationNorm) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Selected scooter does not match the reserved variation.'];
            }

            if ((int)$newScooterId !== $oldScooterId) {
                if (strtolower((string)$newScooter['status']) !== 'available') {
                    $this->db->rollBack();
                    return ['success' => false, 'message' => 'Selected scooter is not available.'];
                }

                $overlapSql = "
                    SELECT COUNT(*)
                    FROM reservations
                    WHERE scooter_id = ?
                      AND reservation_id <> ?
                      AND status IN ('pending','approved','paid')
                      AND NOT (return_datetime <= ? OR pickup_datetime >= ?)
                ";
                $overlapStmt = $this->db->prepare($overlapSql);
                $overlapStmt->execute([
                    $newScooterId,
                    $reservationId,
                    $reservation['pickup_datetime'],
                    $reservation['return_datetime'],
                ]);
                $overlapCount = (int)$overlapStmt->fetchColumn();
                if ($overlapCount > 0) {
                    $this->db->rollBack();
                    return ['success' => false, 'message' => 'Selected scooter is already reserved for the same time window.'];
                }
            }

            $updateResStmt = $this->db->prepare("UPDATE reservations SET scooter_id = ?, notes = ? WHERE reservation_id = ?");
            $updateResStmt->execute([$newScooterId, $notes !== '' ? $notes : null, $reservationId]);

            if ((int)$newScooterId !== $oldScooterId) {
                $orderItemIdStmt = $this->db->prepare("SELECT order_item_id FROM order_items WHERE order_id = ? AND scooter_id = ? ORDER BY order_item_id ASC LIMIT 1");
                $orderItemIdStmt->execute([(int)$reservation['order_id'], $oldScooterId]);
                $orderItemId = $orderItemIdStmt->fetchColumn();
                if ($orderItemId) {
                    $updateItemStmt = $this->db->prepare("UPDATE order_items SET scooter_id = ? WHERE order_item_id = ?");
                    $updateItemStmt->execute([$newScooterId, (int)$orderItemId]);
                }
            }

            $this->db->commit();
            return ['success' => true, 'message' => 'Reservation updated successfully.'];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['success' => false, 'message' => 'Failed to update reservation: ' . $e->getMessage()];
        }
    }
}
