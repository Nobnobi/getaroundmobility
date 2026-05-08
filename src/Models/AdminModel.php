<?php
namespace App\Models;

class AdminModel {
    public function deleteAdmin($id) {
        $stmt = $this->pdo->prepare("DELETE FROM admins WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function updateAdmin($id, $username, $role) {
        $stmt = $this->pdo->prepare("UPDATE admins SET username = ?, role = ? WHERE id = ?");
        return $stmt->execute([$username, $role, $id]);
    }

    public function getAdminById($id) {
        $stmt = $this->pdo->prepare("SELECT id, username, role FROM admins WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function addAdmin($username, $password, $role) {
        $stmt = $this->pdo->prepare("INSERT INTO admins (username, password, role) VALUES (?, ?, ?)");
        return $stmt->execute([$username, $password, $role]);
    }

    public function getAllAdmins() {
        $stmt = $this->pdo->query("SELECT id, username, role, created_at FROM admins ORDER BY created_at DESC");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    private $pdo;
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function findByUsername($username) {
        $stmt = $this->pdo->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
}
