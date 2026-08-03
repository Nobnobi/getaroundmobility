<?php
namespace App\Models;
use App\Utils\Database;
use PDO;

class RentalPriceModel {
    private $db;
    public function __construct() {
        $this->db = Database::getInstance();
        $this->ensureRentalPriceUniqueness();
    }

    private function ensureRentalPriceUniqueness(): void
    {
        try {
            $indexStmt = $this->db->query("SHOW INDEX FROM rental_prices WHERE Key_name = 'uq_rental_price_tier'");
            if (!$indexStmt || !$indexStmt->fetch(PDO::FETCH_ASSOC)) {
                $this->db->exec("ALTER TABLE rental_prices ADD UNIQUE KEY uq_rental_price_tier (product_id, variation_id, days)");
            }
        } catch (\Throwable $e) {
            error_log('Rental price unique-index warning: ' . $e->getMessage());
        }
    }

    private function normalizeDayTokens($rawDay): array
    {
        $value = trim((string)$rawDay);
        if ($value === '') {
            return [];
        }

        if (preg_match('/^(\d{1,2})\s*-\s*(\d{1,2})$/', $value, $m)) {
            $start = (int)$m[1];
            $end = (int)$m[2];
            if ($start <= 0 || $end <= 0) {
                return [];
            }
            if ($start > $end) {
                [$start, $end] = [$end, $start];
            }
            $start = max(1, min(31, $start));
            $end = max(1, min(31, $end));
            return array_map('strval', range($start, $end));
        }

        if (!preg_match('/^\d{1,2}$/', $value)) {
            return [];
        }

        $day = max(1, min(31, (int)$value));
        return [(string)$day];
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
                $insertedDays = [];
                foreach ($dayArr as $i => $day) {
                    $price = $prices[$pid][$vid][$i] ?? null;
                    if ($price === null || $price === '' || !is_numeric($price)) {
                        continue;
                    }

                    $normalizedDays = $this->normalizeDayTokens($day);
                    foreach ($normalizedDays as $dayStr) {
                        if (isset($insertedDays[$dayStr])) {
                            continue;
                        }
                        $stmt = $this->db->prepare('INSERT INTO rental_prices (product_id, variation_id, days, price) VALUES (?, ?, ?, ?)');
                        $stmt->execute([$pid, $variationId, $dayStr, round((float)$price, 2)]);
                        $insertedDays[$dayStr] = true;
                    }
                }
            }
        }
    }
}
