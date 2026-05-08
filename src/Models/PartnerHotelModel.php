<?php
// src/Models/PartnerHotelModel.php
namespace App\Models;

use App\Utils\Database;

class PartnerHotelModel {
    private $db;
    public function __construct() {
        $this->db = Database::getInstance();
    }
    public function getAll() {
        $stmt = $this->db->prepare('SELECT * FROM partner_hotels ORDER BY name');
        $stmt->execute();
        return $stmt->fetchAll();
    }
    public function add($data) {
        $stmt = $this->db->prepare('INSERT INTO partner_hotels (name, address1, address2, state, zip) VALUES (?, ?, ?, ?, ?)');
        return $stmt->execute([
            $data['name'], $data['address1'], $data['address2'], $data['state'], $data['zip']
        ]);
    }
    public function update($id, $data) {
        $stmt = $this->db->prepare('UPDATE partner_hotels SET name=?, address1=?, address2=?, state=?, zip=? WHERE id=?');
        return $stmt->execute([
            $data['name'], $data['address1'], $data['address2'], $data['state'], $data['zip'], $id
        ]);
    }
    public function delete($id) {
        $stmt = $this->db->prepare('DELETE FROM partner_hotels WHERE id=?');
        return $stmt->execute([$id]);
    }
}
