<?php
// src/Models/PartnerHotelModel.php
namespace App\Models;

use App\Utils\Database;

class PartnerHotelModel {
    private $db;
    public function __construct() {
        $this->db = Database::getInstance();
    }

    private function normalizeDeliveryFee($value): float {
        if (!is_numeric($value)) {
            return 0.0;
        }
        return round(max(0, (float)$value), 2);
    }

    public function getAll() {
        $stmt = $this->db->prepare('SELECT * FROM partner_hotels ORDER BY name');
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getPaginated(string $search = '', int $page = 1, int $perPage = 20): array {
        $search = trim($search);
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;

        if ($search !== '') {
            $like = '%' . $search . '%';
            $stmt = $this->db->prepare(
                'SELECT * FROM partner_hotels
                 WHERE name LIKE ? OR address1 LIKE ? OR address2 LIKE ? OR state LIKE ? OR zip LIKE ?
                 ORDER BY name
                 LIMIT ? OFFSET ?'
            );
            $stmt->bindValue(1, $like, \PDO::PARAM_STR);
            $stmt->bindValue(2, $like, \PDO::PARAM_STR);
            $stmt->bindValue(3, $like, \PDO::PARAM_STR);
            $stmt->bindValue(4, $like, \PDO::PARAM_STR);
            $stmt->bindValue(5, $like, \PDO::PARAM_STR);
            $stmt->bindValue(6, $perPage, \PDO::PARAM_INT);
            $stmt->bindValue(7, $offset, \PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        }

        $stmt = $this->db->prepare('SELECT * FROM partner_hotels ORDER BY name LIMIT ? OFFSET ?');
        $stmt->bindValue(1, $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countFiltered(string $search = ''): int {
        $search = trim($search);
        if ($search === '') {
            $stmt = $this->db->query('SELECT COUNT(*) FROM partner_hotels');
            return (int)$stmt->fetchColumn();
        }

        $like = '%' . $search . '%';
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM partner_hotels WHERE name LIKE ? OR address1 LIKE ? OR address2 LIKE ? OR state LIKE ? OR zip LIKE ?'
        );
        $stmt->execute([$like, $like, $like, $like, $like]);
        return (int)$stmt->fetchColumn();
    }

    public function add($data) {
        $stmt = $this->db->prepare('INSERT INTO partner_hotels (name, address1, address2, state, zip, delivery_fee) VALUES (?, ?, ?, ?, ?, ?)');
        return $stmt->execute([
            $data['name'],
            $data['address1'],
            $data['address2'],
            $data['state'],
            $data['zip'],
            $this->normalizeDeliveryFee($data['delivery_fee'] ?? 0)
        ]);
    }
    public function update($id, $data) {
        $stmt = $this->db->prepare('UPDATE partner_hotels SET name=?, address1=?, address2=?, state=?, zip=?, delivery_fee=? WHERE id=?');
        return $stmt->execute([
            $data['name'],
            $data['address1'],
            $data['address2'],
            $data['state'],
            $data['zip'],
            $this->normalizeDeliveryFee($data['delivery_fee'] ?? 0),
            $id
        ]);
    }
    public function delete($id) {
        $stmt = $this->db->prepare('DELETE FROM partner_hotels WHERE id=?');
        return $stmt->execute([$id]);
    }
}
