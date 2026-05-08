<?php
namespace App\Models;
use App\Utils\Database;
use PDO;
use PDOException;

class ScooterModel {
    private const ARCHIVED_STATUS = 'archived';
    private const ACTIVE_RESERVATION_STATUSES = ['pending', 'approved', 'paid', 'confirmed'];
                        
    private $db;
    public function __construct() {
        $this->db = Database::getInstance();
    }

    // Get all products and their scooter counts
    public function getProductScooterCounts() {
        $stmt = $this->db->query("SELECT p.product_id, p.product_name, COUNT(s.scooter_id) AS scooter_count FROM products p LEFT JOIN scooters s ON p.product_id = s.product_id AND s.status <> 'archived' GROUP BY p.product_id");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get all products (id, name) for dropdowns
    public function getAllProductsBasic() {
        $stmt = $this->db->query("SELECT product_id, product_name FROM products");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get all scooters (id, barcode) for list
    public function getAllScootersBasic() {
        $stmt = $this->db->query("SELECT scooter_id, barcode FROM scooters WHERE status <> 'archived'");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Add a scooter and increment product stock
    public function addScooterWithStock($product_id, $status, $available, $barcode) {
        $stmt = $this->db->prepare("INSERT INTO scooters (product_id, status, available, barcode) VALUES (?, ?, ?, ?)");
        $stmt->execute([$product_id, $status, $available, $barcode]);
    }

    // Delete a scooter and decrement product stock
    public function deleteScooterWithStock($scooterId) {
        // Delete scooter
        $stmt = $this->db->prepare("DELETE FROM scooters WHERE scooter_id = ?");
        $stmt->execute([$scooterId]);
    }

    private function getScooterReferenceCounts($scooterId) {
        $reservationStmt = $this->db->prepare("SELECT COUNT(*) FROM reservations WHERE scooter_id = ?");
        $reservationStmt->execute([$scooterId]);

        $orderItemStmt = $this->db->prepare("SELECT COUNT(*) FROM order_items WHERE scooter_id = ?");
        $orderItemStmt->execute([$scooterId]);

        return [
            'reservations' => (int) $reservationStmt->fetchColumn(),
            'order_items' => (int) $orderItemStmt->fetchColumn(),
        ];
    }

    private function getActiveReservationCount($scooterId) {
        $placeholders = implode(',', array_fill(0, count(self::ACTIVE_RESERVATION_STATUSES), '?'));
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM reservations WHERE scooter_id = ? AND status IN ($placeholders)");
        $stmt->execute(array_merge([$scooterId], self::ACTIVE_RESERVATION_STATUSES));

        return (int) $stmt->fetchColumn();
    }

    private function formatDeleteBlockerMessage($scooterId, array $referenceCounts) {
        $reasons = [];

        if ($referenceCounts['reservations'] > 0) {
            $reasons[] = $referenceCounts['reservations'] . ' reservation' . ($referenceCounts['reservations'] === 1 ? '' : 's');
        }

        if ($referenceCounts['order_items'] > 0) {
            $reasons[] = $referenceCounts['order_items'] . ' order item' . ($referenceCounts['order_items'] === 1 ? '' : 's');
        }

        if (empty($reasons)) {
            return "Scooter #$scooterId could not be deleted because it is still referenced by existing records.";
        }

        return "Scooter #$scooterId could not be deleted because it is linked to " . implode(' and ', $reasons) . ".";
    }

    private function formatActiveReservationBlockerMessage($scooterId, $activeReservationCount) {
        return "Scooter #$scooterId could not be deleted because it still has " . $activeReservationCount . " active reservation" . ($activeReservationCount === 1 ? '' : 's') . ".";
    }

    private function archiveScooter($scooterId) {
        $stmt = $this->db->prepare("UPDATE scooters SET status = ? WHERE scooter_id = ?");
        $stmt->execute([self::ARCHIVED_STATUS, $scooterId]);
    }

    // Get scooter details by ID
    public function getScooterById($scooterId) {
        $stmt = $this->db->prepare("SELECT * FROM scooters WHERE scooter_id = ?");
        $stmt->execute([$scooterId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Update scooter details by ID
    public function updateScooterById($scooterId, $data) {
        $stmt = $this->db->prepare("UPDATE scooters SET product_id = ?, model = ?, status = ?, available = ?, barcode = ? WHERE scooter_id = ?");
        $stmt->execute([
            $data['product_id'],
            $data['model'],
            $data['status'],
            $data['available'],
            $data['barcode'],
            $scooterId
        ]);
    }

    // Batch delete scooters by IDs
    public function batchDeleteScooters(array $ids) {
        if (empty($ids)) return [];
        $deleted = [];
        $archived = [];
        $errors = [];
        foreach ($ids as $id) {
            $id = trim((string) $id);
            if ($id === '' || !ctype_digit($id)) {
                continue;
            }

            $stmtOld = $this->db->prepare("SELECT status, barcode, product_id FROM scooters WHERE scooter_id = ?");
            $stmtOld->execute([$id]);
            $old = $stmtOld->fetch(PDO::FETCH_ASSOC);
            if ($old) {
                $activeReservationCount = $this->getActiveReservationCount($id);
                if ($activeReservationCount > 0) {
                    $errors[] = $this->formatActiveReservationBlockerMessage($id, $activeReservationCount);
                    continue;
                }

                $referenceCounts = $this->getScooterReferenceCounts($id);
                if ($referenceCounts['reservations'] > 0 || $referenceCounts['order_items'] > 0) {
                    $this->archiveScooter($id);
                    $archived[] = [
                        'id' => $id,
                        'barcode' => $old['barcode'],
                        'status' => $old['status'],
                        'reference_counts' => $referenceCounts,
                    ];
                    continue;
                }

                try {
                    $this->deleteScooterWithStock($id);
                    $deleted[] = [
                        'id' => $id,
                        'barcode' => $old['barcode'],
                        'status' => $old['status']
                    ];
                } catch (PDOException $e) {
                    if ((string) $e->getCode() === '23000') {
                        $errors[] = $this->formatDeleteBlockerMessage($id, $this->getScooterReferenceCounts($id));
                        continue;
                    }

                    throw $e;
                }
            } else {
                $errors[] = "Scooter #$id could not be found for deletion.";
            }
        }
        return ['deleted' => $deleted, 'archived' => $archived, 'errors' => $errors];
    }

    // Batch update scooters
    public function batchUpdateScooters(array $updates, array $validProductIds) {
        $statusChanges = [];
        $errors = [];
        foreach ($updates as $scooterId => $data) {
            if ($scooterId === 'new' || !ctype_digit((string)$scooterId)) continue;
            if (!in_array($data['status'], ['available', 'maintenance'])) {
                $errors[] = "Invalid status for scooter #$scooterId.";
                continue;
            }
            $stmtOld = $this->db->prepare("SELECT status, barcode, product_id, variation_id FROM scooters WHERE scooter_id = ?");
            $stmtOld->execute([$scooterId]);
            $old = $stmtOld->fetch(PDO::FETCH_ASSOC);
            if (!$old) {
                $errors[] = "Scooter #$scooterId could not be found for update.";
                continue;
            }
            $newBarcode = $data['barcode'] ?? $old['barcode'];
            $newProductId = $data['product_id'] ?? $old['product_id'];
            $newVariationId = $data['variation_id'] ?? $old['variation_id'];
            if (!in_array((string)$newProductId, $validProductIds)) {
                $errors[] = "Invalid product for scooter #$scooterId.";
                continue;
            }
            // Always allow variation_id to be NULL or empty
            if (empty($newVariationId) || !ctype_digit((string)$newVariationId)) {
                $newVariationId = null;
            }
            if ($old['status'] !== $data['status'] || $old['barcode'] !== $newBarcode || $old['product_id'] != $newProductId || $old['variation_id'] != $newVariationId) {
                try {
                    $stmt = $this->db->prepare("UPDATE scooters SET status = ?, barcode = ?, product_id = ?, variation_id = ? WHERE scooter_id = ?");
                    $stmt->execute([$data['status'], $newBarcode, $newProductId, $newVariationId, $scooterId]);
                    if ($old['status'] !== $data['status']) {
                        $statusChanges[] = "Scooter #$scooterId status updated from {$old['status']} to {$data['status']}.";
                    }
                } catch (\Exception $e) {
                    $errors[] = "Failed to update scooter #$scooterId: " . $e->getMessage();
                }
            }
        }
        return ['statusChanges' => $statusChanges, 'errors' => $errors];
    }

    // Batch insert new scooters
    public function batchInsertScooters(array $newScooters, array $validProductIds) {
        $addChanges = [];
        $errors = [];
        foreach ($newScooters as $i => $data) {
            $prodId = $data['product_id'] ?? null;
            $status = $data['status'] ?? 'available';
            $variationId = $data['variation_id'] ?? null;
            if (!in_array($status, ['available', 'maintenance'])) {
                $errors[] = "Invalid status for new scooter.";
                continue;
            }
            if (!in_array((string)$prodId, $validProductIds)) {
                $errors[] = "Invalid product for new scooter.";
                continue;
            }
            // Always allow variation_id to be NULL or empty
            if (empty($variationId) || !ctype_digit((string)$variationId)) {
                $variationId = null;
            }
                
            $productName = $this->getProductNameById($prodId);
            $barcode = $this->generateBarcodeForProductName($productName);
            try {
                $stmt = $this->db->prepare("INSERT INTO scooters (product_id, variation_id, status, barcode) VALUES (?, ?, ?, ?)");
                $stmt->execute([$prodId, $variationId, $status, $barcode]);
                $newId = $this->db->lastInsertId();
                $addChanges[] = "Scooter #$newId (barcode: $barcode, status: $status) was added.";
            } catch (\Exception $e) {
                $errors[] = "Failed to add new scooter: " . $e->getMessage();
            }
        }
        return ['addChanges' => $addChanges, 'errors' => $errors];
    }

    // Helper: get product name by ID
    public function getProductNameById($productId) {
        $stmt = $this->db->prepare("SELECT product_name FROM products WHERE product_id = ?");
        $stmt->execute([$productId]);
        return $stmt->fetchColumn();
    }

    // Helper: generate readable barcode
    public function generateBarcodeForProductName($productName) {
        $words = preg_split('/\s+/', trim($productName));
        if (count($words) >= 2) {
            $prefix = strtoupper(substr($words[0], 0, 3) . substr($words[1], 0, 3));
        } elseif (count($words) === 1) {
            $prefix = strtoupper(substr($words[0], 0, 6));
        } else {
            $prefix = 'SCOOTER';
        }
        do {
            $random = strtoupper(dechex(mt_rand(0, 0xFFFFFFFF)));
            $barcode = $prefix . '-' . $random;
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM scooters WHERE barcode = ?");
            $stmt->execute([$barcode]);
            $exists = $stmt->fetchColumn() > 0;
        } while ($exists);
        return $barcode;
    }

    // Get all scooters for a given product_id, including sale_type
    public function getScootersByProductId($product_id) {
        $stmt = $this->db->prepare("SELECT s.scooter_id, s.status, s.barcode, s.variation_id, p.sale_type FROM scooters s JOIN products p ON s.product_id = p.product_id WHERE s.product_id = ? AND s.status <> 'archived'");
        $stmt->execute([$product_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Mark scooters as sold by IDs
    public function markScootersAsSold(array $scooterIds) {
        if (empty($scooterIds)) return;
        $in  = str_repeat('?,', count($scooterIds) - 1) . '?';
        $sql = "UPDATE scooters SET status = 'Sold' WHERE scooter_id IN ($in)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($scooterIds);
    }

}
