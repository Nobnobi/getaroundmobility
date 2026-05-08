<?php
namespace App\Models;
use App\Utils\Database;
use PDO;

class UserModel
{
    private $db;

    public function __construct(){
        // Inject PDO instance into model
        $this->db = Database::getInstance();
    }

    public function getUserByEmail($email){
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function getUserById($userId){
        $stmt = $this->db->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function emailExists($email){
        $stmt = $this->db->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch() ? true : false;
    }

    public function createUser($first_name, $last_name, $email, $phone, $address, $hashedPassword){
        $stmt = $this->db->prepare("
            INSERT INTO users (first_name, last_name, email, phone, address, password_hash, user_type, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'customer', NOW())
        ");
        $stmt->execute([$first_name, $last_name, $email, $phone, $address, $hashedPassword]);
        return $this->db->lastInsertId();
    }

    public function createPasswordReset($email, $token, $expiry){
        $stmt = $this->db->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$email, $token, $expiry]);
    }

    public function cleanExpiredPasswordResets(){
        $this->db->prepare("DELETE FROM password_resets WHERE expires_at < NOW()")->execute();
    }

    public function getPasswordResetByToken($token){
        $stmt = $this->db->prepare("SELECT email, expires_at FROM password_resets WHERE token = ?");
        $stmt->execute([$token]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function updateUserPassword($email, $password){
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
        $stmt->execute([$hash, $email]);
    }

    public function deletePasswordResetToken($token){
        $stmt = $this->db->prepare("DELETE FROM password_resets WHERE token = ?");
        $stmt->execute([$token]);
    }

    public function updateUser($userId, $data) {
        $stmt = $this->db->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ? WHERE user_id = ?");
        $stmt->execute([
            $data['first_name'],
            $data['last_name'],
            $data['email'],
            $data['phone'],
            $data['address'],
            $userId
        ]);
    }

}
