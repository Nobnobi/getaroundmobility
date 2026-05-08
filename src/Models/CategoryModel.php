<?php
namespace App\Models;

use App\Utils\Database;

class CategoryModel {
    protected $db;
    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getAllCategories() {
        $stmt = $this->db->query("SELECT category_id, category_name FROM categories");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function addCategory($name) {
        $stmt = $this->db->prepare("INSERT INTO categories (category_name) VALUES (?)");
        return $stmt->execute([$name]);
    }

    public function deleteCategory($id) {
        $stmt = $this->db->prepare("DELETE FROM categories WHERE category_id = ?");
        return $stmt->execute([$id]);
    }

    public function getCategoryName($id) {
        $stmt = $this->db->prepare("SELECT category_name FROM categories WHERE category_id = ?");
        $stmt->execute([$id]);
        return $stmt->fetchColumn();
    }

    public function updateCategory($id, $name) {
        $stmt = $this->db->prepare("UPDATE categories SET category_name = ? WHERE category_id = ?");
        return $stmt->execute([$name, $id]);
    }
}
