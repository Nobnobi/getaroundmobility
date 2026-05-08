<?php
namespace App\Models;

use App\Utils\Database;
use PDO;

class TestimonialsModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // Add a new testimonial
    public function addTestimonial($reviewer_name, $review_text, $star_rating) {
        $stmt = $this->db->prepare("INSERT INTO testimonials (reviewer_name, review_text, star_rating) VALUES (?, ?, ?)");
        $stmt->execute([$reviewer_name, $review_text, $star_rating]);
        return $this->db->lastInsertId();
    }

    // Get all testimonials
    public function getAllTestimonials() {
        $stmt = $this->db->query("SELECT * FROM testimonials ORDER BY created_at DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get a single testimonial by ID
    public function getTestimonialById($id) {
        $stmt = $this->db->prepare("SELECT * FROM testimonials WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Update a testimonial
    public function updateTestimonial($id, $reviewer_name, $review_text, $star_rating) {
        $stmt = $this->db->prepare("UPDATE testimonials SET reviewer_name = ?, review_text = ?, star_rating = ? WHERE id = ?");
        return $stmt->execute([$reviewer_name, $review_text, $star_rating, $id]);
    }

    // Delete a testimonial
    public function deleteTestimonial($id) {
        $stmt = $this->db->prepare("DELETE FROM testimonials WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
