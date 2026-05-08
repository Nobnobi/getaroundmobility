<?php
namespace App\Models;

use App\Utils\Database;

class CustomerModel {
    protected $db;
    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Get customers with optional search and pagination
     * @param string $search
     * @param int $page
     * @param int $perPage
     * @return array [customers, totalCustomers, totalPages]
     */
    public function getCustomers($search = '', $page = 1, $perPage = 15) {
        $where = "WHERE user_type = 'customer'";
        $params = [];
        if ($search !== '') {
            $where .= " AND (CONCAT(first_name, ' ', last_name) LIKE :search)";
            $params[':search'] = "%$search%";
        }
        // Get total count
        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM users $where");
        $countStmt->execute($params);
        $totalCustomers = $countStmt->fetchColumn();
        $totalPages = max(1, ceil($totalCustomers / $perPage));

        // Get paginated customers
        $sql = "SELECT user_id, CONCAT(first_name, ' ', last_name) AS name, email, phone, address, created_at, password_hash FROM users $where ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, \PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', (int)$perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)(($page - 1) * $perPage), \PDO::PARAM_INT);
        $stmt->execute();
        $customers = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return [
            'customers' => $customers,
            'totalCustomers' => $totalCustomers,
            'totalPages' => $totalPages
        ];
    }
}
