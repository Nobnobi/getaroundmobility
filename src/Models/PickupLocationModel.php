<?php
// src/Models/PickupLocationModel.php
namespace App\Models;

use App\Utils\Database;

class PickupLocationModel {
    private $db;
    public function __construct() {
        $this->db = Database::getInstance();
    }
    public function getAll() {
        $stmt = $this->db->prepare('SELECT * FROM pickup_locations ORDER BY name');
        $stmt->execute();
        return $stmt->fetchAll();
    }
    public function add($data) {
        $stmt = $this->db->prepare('INSERT INTO pickup_locations (name, address) VALUES (?, ?)');
        return $stmt->execute([
            $data['name'], $data['address']
        ]);
    }
    public function update($id, $data) {
        $stmt = $this->db->prepare('UPDATE pickup_locations SET name=?, address=? WHERE id=?');
        return $stmt->execute([
            $data['name'], $data['address'], $id
        ]);
    }
    public function delete($id) {
        $stmt = $this->db->prepare('DELETE FROM pickup_locations WHERE id=?');
        return $stmt->execute([$id]);
    }
}
