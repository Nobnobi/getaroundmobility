<?php
namespace App\Models;
use App\Utils\Database;
use PDO;

class RentalPriceModel {
    private $db;
    public function __construct() {
        $this->db = Database::getInstance();
    }

    // Get all rental prices grouped by product_id, variation_id, days
    public function getAllRentalPricesGrouped() {
        $stmt = $this->db->query('SELECT * FROM rental_prices');
        $prices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $grouped = [];
        foreach ($prices as $row) {
            $pid = $row['product_id'];
            $vid = $row['variation_id'] ?? 'null';
            if (!isset($grouped[$pid])) $grouped[$pid] = [];
            if (!isset($grouped[$pid][$vid])) $grouped[$pid][$vid] = [];
            $grouped[$pid][$vid][] = [
                'days' => $row['days'],
                'price' => $row['price']
            ];
        }
        return $grouped;
    }

    // Save rental prices from admin form
    public function saveRentalPrices($days, $prices) {
        // Only delete and replace price tiers for the submitted product/variation(s)
        foreach ($days as $pid => $variationRows) {
            foreach ($variationRows as $vid => $dayArr) {
                // Delete existing price tiers for this product/variation only
                $variationId = ($vid === 'null' || $vid === '') ? null : $vid;
                if ($variationId === null) {
                    $delStmt = $this->db->prepare('DELETE FROM rental_prices WHERE product_id = ? AND (variation_id IS NULL OR variation_id = "null")');
                    $delStmt->execute([$pid]);
                } else {
                    $delStmt = $this->db->prepare('DELETE FROM rental_prices WHERE product_id = ? AND variation_id = ?');
                    $delStmt->execute([$pid, $variationId]);
                }
                // Insert new price tiers
                foreach ($dayArr as $i => $day) {
                    $price = $prices[$pid][$vid][$i] ?? null;
                    // Accept day ranges as string (e.g. '8-14'), not just int
                    if ($day !== null && $day !== '' && $price !== null && $price !== '') {
                        $dayStr = (string)$day;
                        $stmt = $this->db->prepare('INSERT INTO rental_prices (product_id, variation_id, days, price) VALUES (?, ?, ?, ?)');
                        $stmt->execute([$pid, $variationId, $dayStr, $price]);
                    }
                }
            }
        }
    }
}
