<?php
namespace App\Models;

use App\Utils\Database;

class ReservationModel {
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Get reservations with optional status filter and pagination
     * @param string|null $status 'pending', 'completed', or null for all
     * @param int $page
     * @param int $perPage
     * @return array [reservations, totalReservations, totalPages]
     */
    public function getReservations($status = null, $page = 1, $perPage = 30, $search = '')
    {
        $whereClauses = [];
        $params = [];
        if ($status === 'pending') {
            $whereClauses[] = "status IN ('pending', 'paid')";
        } elseif ($status === 'completed') {
            $whereClauses[] = 'status = :status';
            $params[':status'] = $status;
        }
        if ($search !== '') {
            $whereClauses[] = "(order_id LIKE :search OR reservation_id LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }
        $where = count($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

        $countSql = "SELECT COUNT(*) FROM reservations $where";
        $countStmt = $this->db->prepare($countSql);
        foreach ($params as $k => $v) {
            $countStmt->bindValue($k, $v);
        }
        $countStmt->execute();
        $totalReservations = $countStmt->fetchColumn();
        $totalPages = max(1, ceil($totalReservations / $perPage));

        $sql = "SELECT * FROM reservations $where ORDER BY reservation_id DESC LIMIT :limit OFFSET :offset";
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

    /**
     * Get reservations overlapping a date range
     */
    public function getReservationsBetween($pickup, $return) {
        $sql = "SELECT product_id, variation_id, qty, pickup_datetime, return_datetime FROM reservations WHERE status IN ('pending', 'paid') AND NOT (return_datetime <= ? OR pickup_datetime >= ? )";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$pickup, $return]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
