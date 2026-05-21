<?php
namespace App\Models;

use App\Utils\Database;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;
use Dompdf\Dompdf;
use Dompdf\Options;

class OrderModel {
            /**
             * Minimal availability check for all cart items before order/charge
             * Returns true if all items are available, false if any are not
             */
            /**
             * Improved availability check: Only allow booking if enough scooters are truly available for the requested dates
             */
            public function isCartAvailable($cart, $pickup_datetime, $return_datetime) {
                foreach ($cart as $item) {
                    $productId = $item['id'];
                    $qty = max(1, (int)($item['qty'] ?? $item['quantity'] ?? 1));
                    $variationId = isset($item['variation_id']) && $item['variation_id'] !== '' ? $item['variation_id'] : null;
                    // Find all scooters for this product that are available
                    if ($variationId !== null) {
                        $sql = "SELECT scooter_id FROM scooters WHERE product_id = ? AND variation_id = ? AND status = 'available'";
                        $stmt = $this->db->prepare($sql);
                        $stmt->execute([$productId, $variationId]);
                    } else {
                        $sql = "SELECT scooter_id FROM scooters WHERE product_id = ? AND status = 'available' AND (variation_id IS NULL OR variation_id = 0)";
                        $stmt = $this->db->prepare($sql);
                        $stmt->execute([$productId]);
                    }
                    $allScooters = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                    $availableScooters = [];
                    foreach ($allScooters as $scooterId) {
                        // Check for overlapping reservations for this scooter
                        $resSql = "SELECT COUNT(*) FROM reservations WHERE scooter_id = ? AND status IN ('pending','approved','paid') AND (
                            (pickup_datetime < ? AND return_datetime > ?)
                            OR (pickup_datetime < ? AND return_datetime > ?)
                            OR (pickup_datetime >= ? AND pickup_datetime < ?)
                        )";
                        $resStmt = $this->db->prepare($resSql);
                        $resStmt->execute([
                            $scooterId,
                            $return_datetime, $pickup_datetime,
                            $return_datetime, $pickup_datetime,
                            $pickup_datetime, $return_datetime
                        ]);
                        $overlapCount = (int)$resStmt->fetchColumn();
                        if ($overlapCount === 0) {
                            $availableScooters[] = $scooterId;
                        }
                    }
                    if (count($availableScooters) < $qty) {
                        return false;
                    }
                }
                return true;
            }
        
    // Helper to mark scooters as sold after a for-sale order
    public function markScootersSoldIfForSale($cart, $assignedScooters, $orderSaleType = null) {
        // Mark as sold if sale_type is 'sale' or type is 'for-sale'
        $scooterIdsToMark = [];
        $debugFile = @fopen(__DIR__ . '/../../public/order-debug-log.txt', 'a');
        if (is_resource($debugFile)) {
            fwrite($debugFile, date('Y-m-d H:i:s') . "\n[DEBUG] ENTERED markScootersSoldIfForSale\nCart: " . print_r($cart, true) . "\nAssigned: " . print_r($assignedScooters, true));
        }
        foreach ($cart as $idx => $item) {
            $isForSale = strtolower((string)($orderSaleType ?? '')) === 'sale';
            if (!$isForSale && isset($item['type']) && $item['type'] === 'for-sale') {
                $isForSale = true;
            } elseif (!$isForSale && isset($item['sale_type']) && $item['sale_type'] === 'sale') {
                $isForSale = true;
            }
            if ($isForSale && !empty($assignedScooters[$idx]['scooter_ids'])) {
                foreach ($assignedScooters[$idx]['scooter_ids'] as $sid) {
                    $scooterIdsToMark[] = $sid;
                }
            }
        }
        if (is_resource($debugFile)) {
            fwrite($debugFile, date('Y-m-d H:i:s') . "\n[DEBUG] Scooters to mark as sold: " . print_r($scooterIdsToMark, true));
        }
        if (!empty($scooterIdsToMark)) {
            $scooterModel = new \App\Models\ScooterModel();
            $scooterModel->markScootersAsSold($scooterIdsToMark);
            if (is_resource($debugFile)) {
                fwrite($debugFile, date('Y-m-d H:i:s') . "\n[DEBUG] markScootersAsSold called for: " . print_r($scooterIdsToMark, true));
            }
        }
        if (is_resource($debugFile)) {
            fclose($debugFile);
        }
    }
    
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->ensureOrderColumns();
    }

    private function ensureOrderColumns(): void
    {
        $weightOptionCol = $this->db->query("SHOW COLUMNS FROM orders LIKE 'client_weight_option'");
        if (!$weightOptionCol || !$weightOptionCol->fetch(\PDO::FETCH_ASSOC)) {
            $this->db->exec("ALTER TABLE orders ADD COLUMN client_weight_option VARCHAR(32) NULL AFTER guest_phone");
        }

        $weightLbsCol = $this->db->query("SHOW COLUMNS FROM orders LIKE 'client_weight_lbs'");
        if (!$weightLbsCol || !$weightLbsCol->fetch(\PDO::FETCH_ASSOC)) {
            $this->db->exec("ALTER TABLE orders ADD COLUMN client_weight_lbs INT NULL AFTER client_weight_option");
        }
    }

    private function ensureOrderAssignments($orderId, $cart, $pickupDatetime, $returnDatetime, &$assignedScooters, $debugFile = null)
    {
        $assignedScooters = [];
        $existingItemsStmt = $this->db->prepare("SELECT COUNT(*) FROM order_items WHERE order_id = ?");
        $existingItemsStmt->execute([$orderId]);
        $existingItemCount = (int)$existingItemsStmt->fetchColumn();

        $existingReservationsStmt = $this->db->prepare("SELECT COUNT(*) FROM reservations WHERE order_id = ?");
        $existingReservationsStmt->execute([$orderId]);
        $existingReservationCount = (int)$existingReservationsStmt->fetchColumn();

        if ($existingItemCount > 0 || $existingReservationCount > 0) {
            if (is_resource($debugFile)) {
                fwrite($debugFile, date('Y-m-d H:i:s') . "\n[DEBUG] Assignment rows already exist for order_id {$orderId}. items={$existingItemCount}, reservations={$existingReservationCount}\n");
            }
            return true;
        }

        $pickup = date('Y-m-d H:i:00', strtotime($pickupDatetime));
        $return = date('Y-m-d H:i:00', strtotime($returnDatetime));
        $reservedScootersGlobal = [];
        $reservationStmt = $this->db->prepare("INSERT INTO reservations (scooter_id, pickup_datetime, return_datetime, order_id, status) VALUES (?, ?, ?, ?, 'pending')");
        $itemStmt = $this->db->prepare("INSERT INTO order_items (order_id, product_id, product_name, price, quantity, image_url, variation_id, variation_name, scooter_id) VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?)");

        foreach ($cart as $item) {
            $qty = max(1, (int)($item['qty'] ?? $item['quantity'] ?? 1));
            $variationId = isset($item['variation_id']) && $item['variation_id'] !== '' ? $item['variation_id'] : null;
            $productId = $item['id'] ?? null;
            $productName = $item['name'] ?? '';
            $price = $item['price'] ?? 0;
            $imageUrl = $item['image_url'] ?? null;
            $variationName = $item['variation_name'] ?? null;
            $scooterIdsForItem = [];

            for ($i = 0; $i < $qty; $i++) {
                $params = [$productId];
                if ($variationId !== null) {
                    $variationClause = " AND s.variation_id = ?";
                    $params[] = $variationId;
                } else {
                    $variationClause = " AND (s.variation_id IS NULL OR s.variation_id = 0)";
                }
                $params[] = $pickup;
                $params[] = $return;

                $excludeClause = '';
                if (!empty($reservedScootersGlobal)) {
                    $placeholders = implode(',', array_fill(0, count($reservedScootersGlobal), '?'));
                    $excludeClause = " AND s.scooter_id NOT IN ($placeholders)";
                    $params = array_merge($params, $reservedScootersGlobal);
                }

                $sql = "SELECT s.scooter_id FROM scooters s WHERE s.product_id = ?{$variationClause} AND s.status = 'available' AND NOT EXISTS (SELECT 1 FROM reservations r WHERE r.scooter_id = s.scooter_id AND r.status IN ('pending','confirmed','paid') AND NOT (r.return_datetime <= ? OR r.pickup_datetime >= ?)){$excludeClause} ORDER BY s.scooter_id ASC LIMIT 1";
                if (is_resource($debugFile)) {
                    fwrite($debugFile, date('Y-m-d H:i:s') . "\n[DEBUG] Scooter assignment SQL: {$sql}\nParams: " . print_r($params, true));
                }
                $stmtScooter = $this->db->prepare($sql);
                $stmtScooter->execute($params);
                $scooterId = $stmtScooter->fetchColumn();

                if (!$scooterId) {
                    if (is_resource($debugFile)) {
                        fwrite($debugFile, date('Y-m-d H:i:s') . "\n[ERROR] No available scooter found for product_id: {$productId}, variation_id: {$variationId}, qty: {$qty}\n");
                    }
                    $this->db->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$orderId]);
                    $this->db->prepare("DELETE FROM reservations WHERE order_id = ?")->execute([$orderId]);
                    return false;
                }

                $reservedScootersGlobal[] = $scooterId;
                $scooterIdsForItem[] = $scooterId;
                $reservationStmt->execute([$scooterId, $pickup, $return, $orderId]);
                if (is_resource($debugFile)) {
                    fwrite($debugFile, date('Y-m-d H:i:s') . "\n[DEBUG] Assigned scooter_id: {$scooterId} for product_id: {$productId}, variation_id: {$variationId}\n");
                }
            }

            foreach ($scooterIdsForItem as $scooterId) {
                $itemParams = [
                    $orderId,
                    $productId,
                    $productName,
                    $price,
                    $imageUrl,
                    $variationId,
                    $variationName,
                    $scooterId
                ];
                $itemStmt->execute($itemParams);
                if (is_resource($debugFile)) {
                    fwrite($debugFile, date('Y-m-d H:i:s') . "\n[DEBUG] order_item insert SUCCESS: " . print_r($itemParams, true));
                }
            }

            $assignedScooters[] = [
                'order_id' => $orderId,
                'product_id' => $productId,
                'product_name' => $productName,
                'price' => $price,
                'quantity' => $qty,
                'image_url' => $imageUrl,
                'variation_id' => $variationId,
                'variation_name' => $variationName,
                'scooter_ids' => $scooterIdsForItem,
            ];
        }

        return true;
    }

    /**
     * Validate stock availability for all items in the cart
     * Returns array with 'valid' => bool and 'errors' => array of error messages
     */
    public function validateStockAvailability($cart, $pickup_datetime = null, $return_datetime = null) {
        $errors = [];
        
        if (empty($cart)) {
            $errors[] = 'Cart is empty';
            return ['valid' => false, 'errors' => $errors];
        }

        foreach ($cart as $item) {
            $productId = $item['id'] ?? null;
            $qty = max(1, (int)($item['qty'] ?? $item['quantity'] ?? 1));
            $productName = $item['name'] ?? 'Unknown Product';
            $variationId = isset($item['variation_id']) && $item['variation_id'] !== '' ? $item['variation_id'] : null;

            if (!$productId) {
                $errors[] = 'Invalid product in cart';
                continue;
            }

            // Check scooter stock (for rentals)
            $scooterSql = "SELECT COUNT(*) FROM scooters WHERE product_id = ? AND status = 'available'";
            $scooterParams = [$productId];
            
            if ($variationId !== null) {
                $scooterSql .= " AND variation_id = ?";
                $scooterParams[] = $variationId;
            } else {
                $scooterSql .= " AND (variation_id IS NULL OR variation_id = 0)";
            }

            $scooterStmt = $this->db->prepare($scooterSql);
            $scooterStmt->execute($scooterParams);
            $scooterCount = (int)$scooterStmt->fetchColumn();

            // If dates are provided, validate rental availability
            if ($pickup_datetime && $return_datetime) {
                $pickup = date('Y-m-d H:i:00', strtotime($pickup_datetime));
                $return = date('Y-m-d H:i:00', strtotime($return_datetime));

                // Count available scooters for the rental period
                $availableSql = "
                    SELECT COUNT(*) FROM scooters s
                    WHERE s.product_id = ? AND s.status = 'available'
                ";
                $availableParams = [$productId];
                
                if ($variationId !== null) {
                    $availableSql .= " AND s.variation_id = ?";
                    $availableParams[] = $variationId;
                } else {
                    $availableSql .= " AND (s.variation_id IS NULL OR s.variation_id = 0)";
                }

                $availableSql .= " AND NOT EXISTS (
                    SELECT 1 FROM reservations r
                    WHERE r.scooter_id = s.scooter_id
                    AND r.status IN ('pending', 'confirmed', 'paid')
                    AND r.pickup_datetime < ?
                    AND r.return_datetime > ?
                )";
                $availableParams[] = $return;
                $availableParams[] = $pickup;

                $availableStmt = $this->db->prepare($availableSql);
                $availableStmt->execute($availableParams);
                $availableCount = (int)$availableStmt->fetchColumn();

                if ($availableCount < $qty) {
                    $errors[] = "Insufficient stock for '$productName'. Available: {$availableCount}, Requested: {$qty}";
                }
            } else {
                if ($scooterCount < $qty) {
                    $errors[] = "Insufficient stock for '$productName'. Available: {$scooterCount}, Requested: {$qty}";
                }
            }
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    public function placeOrder($orderData, $cart)
    {
        $sql = "INSERT INTO orders (
            user_id, guest_first_name, guest_last_name, guest_email, guest_phone,
            client_weight_option, client_weight_lbs,
            address1, address2, state, zip,
            pickup_datetime, return_datetime, delivery_type, hotel_id, pickup_location,
            notes, payment_method, total_amount, status, customer_type, sale_type
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?
        )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $orderData['user_id'] ?? null,
            $orderData['guest_first_name'] ?? null,
            $orderData['guest_last_name'] ?? null,
            $orderData['guest_email'] ?? null,
            $orderData['guest_phone'] ?? null,
            $orderData['client_weight_option'] ?? null,
            $orderData['client_weight_lbs'] ?? null,
            $orderData['address1'] ?? null,
            $orderData['address2'] ?? null,
            $orderData['state'] ?? null,
            $orderData['zip'] ?? null,
            $orderData['pickup_datetime'] ?? null,
            $orderData['return_datetime'] ?? null,
            $orderData['delivery_type'] ?? 'preferred',
            $orderData['hotel_id'] ?? null,
            $orderData['pickup_location'] ?? null,
            $orderData['notes'] ?? null,
            $orderData['payment_method'],
            $orderData['total_amount'],
            $orderData['customer_type'],
            $orderData['sale_type']
        ]);

        $orderId = $this->db->lastInsertId();

        // Insert order items (with variation support)
        $itemStmt = $this->db->prepare("
            INSERT INTO order_items 
            (order_id, product_id, product_name, price, quantity, image_url, variation_id, variation_name, scooter_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        // Track assigned scooter_ids for each item
        $assignedScooters = [];
        $pickup = date('Y-m-d H:i:00', strtotime($orderData['pickup_datetime']));
        $return = date('Y-m-d H:i:00', strtotime($orderData['return_datetime']));
        $reservedScootersGlobal = [];
        $reservationStmt = $this->db->prepare("
            INSERT INTO reservations 
            (scooter_id, pickup_datetime, return_datetime, order_id, status)
            VALUES (?, ?, ?, ?, 'pending')
        ");
        foreach ($cart as $item) {
            $qty = max(1, (int)($item['qty'] ?? $item['quantity'] ?? 1));
            $variationId = isset($item['variation_id']) && $item['variation_id'] !== '' ? $item['variation_id'] : null;
            $productId = $item['id'];
            $scooterIdsForItem = [];
            for ($i = 0; $i < $qty; $i++) {
                // Find available scooter for this product/variation and dates
                $params = [$productId];
                if ($variationId !== null) {
                    $variationClause = " AND s.variation_id = ?";
                    $params[] = $variationId;
                } else {
                    $variationClause = " AND (s.variation_id IS NULL OR s.variation_id = 0)";
                }
                $params[] = $pickup;
                $params[] = $return;
                $params[] = $orderId;
                $excludeClause = '';
                if (!empty($reservedScootersGlobal)) {
                    $placeholders = implode(',', array_fill(0, count($reservedScootersGlobal), '?'));
                    $excludeClause = " AND s.scooter_id NOT IN ($placeholders)";
                    $params = array_merge($params, $reservedScootersGlobal);
                }
                $sql = "
                    SELECT s.scooter_id
                    FROM scooters s
                    WHERE s.product_id = ?
                    {$variationClause}
                    AND s.status = 'available'
                    AND NOT EXISTS (
                        SELECT 1 
                        FROM reservations r
                        WHERE r.scooter_id = s.scooter_id
                        AND r.pickup_datetime < ?
                        AND r.return_datetime > ?
                        AND r.order_id != ?
                    )
                    $excludeClause
                    ORDER BY s.scooter_id ASC
                    LIMIT 1
                ";
                $stmtScooter = $this->db->prepare($sql);
                $stmtScooter->execute($params);
                $scooterId = $stmtScooter->fetchColumn();
                if ($scooterId) {
                    $reservedScootersGlobal[] = $scooterId;
                    $scooterIdsForItem[] = $scooterId;
                    $reservationStmt->execute([
                        $scooterId,
                        $pickup,
                        $return,
                        $orderId
                    ]);
                }
            }
            // Store assigned scooter_ids for this item
            $assignedScooters[] = [
                'order_id' => $orderId,
                'product_id' => $item['id'],
                'product_name' => $item['name'],
                'price' => $item['price'],
                'quantity' => $qty,
                'image_url' => $item['image_url'] ?? null,
                'variation_id' => $item['variation_id'] ?? null,
                'variation_name' => $item['variation_name'] ?? null,
                'scooter_ids' => $scooterIdsForItem
            ];
        }
        // Insert order_items with scooter_id for each assigned scooter
        foreach ($assignedScooters as $item) {
            foreach ($item['scooter_ids'] as $scooterId) {
                $itemStmt->execute([
                    $item['order_id'],
                    $item['product_id'],
                    $item['product_name'],
                    $item['price'],
                    1, // Each row is for one scooter
                    $item['image_url'],
                    $item['variation_id'],
                    $item['variation_name'],
                    $scooterId
                ]);
            }
        }
        // Mark scooters as sold if for-sale
        $this->markScootersSoldIfForSale($cart, $assignedScooters, $orderData['sale_type'] ?? null);


        // Reservation logic removed for walk-in booking (no scooter assignment)

        return $this->getOrderById($orderId);
    }

    public function getOrderById($orderId)
    {
        $stmt = $this->db->prepare("
            SELECT o.*,
                   h.name AS hotel_name,
                   pl.name AS pickup_location_name
            FROM orders o
            LEFT JOIN partner_hotels h ON o.hotel_id = h.id
            LEFT JOIN pickup_locations pl ON o.pickup_location = pl.id
            WHERE o.order_id = ?
        ");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$order) return null;

        $itemStmt = $this->db->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $itemStmt->execute([$orderId]);
        $order['items'] = $itemStmt->fetchAll(\PDO::FETCH_ASSOC);

        return $order;
    }


    // Get orders by user ID with item count and total amount in profile.php
    public function getOrdersByUserId($userId) {
        $stmt = $this->db->prepare("SELECT o.*, 
            (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.order_id) AS items_count,
            (SELECT SUM(oi.price * oi.quantity) FROM order_items oi WHERE oi.order_id = o.order_id) AS total
            FROM orders o
            WHERE o.user_id = ?
            ORDER BY o.order_date DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // Get paginated orders for a specific user (profile page)
    public function getOrdersByUserIdPaginated($userId, $page = 1, $perPage = 5) {
        $offset = ($page - 1) * $perPage;
        $stmt = $this->db->prepare("SELECT o.*, 
            (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.order_id) AS items_count,
            (SELECT SUM(oi.price * oi.quantity) FROM order_items oi WHERE oi.order_id = o.order_id) AS total
            FROM orders o
            WHERE o.user_id = :userId
            ORDER BY o.order_date DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':userId', (int)$userId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int)$perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // Count total orders for a specific user
    public function countOrdersByUserId($userId) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    // Get order items by order ID
    public function getOrderItems($orderId) {
        $stmt = $this->db->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $stmt->execute([$orderId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function searchOrdersByOrderId($searchTerm) {
        $sql = "SELECT * FROM orders WHERE order_id LIKE ? ORDER BY order_id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['%' . $searchTerm . '%']);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // Get paginated orders for admin orders page
    public function getOrdersPaginated($page = 1, $perPage = 10) {
        $offset = ($page - 1) * $perPage;
        $stmt = $this->db->prepare("SELECT * FROM orders ORDER BY order_id DESC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit', (int)$perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // Get total number of orders (for pagination)
    public function getTotalOrdersCount() {
        $stmt = $this->db->query("SELECT COUNT(*) FROM orders");
        return (int)$stmt->fetchColumn();
    }

    public function searchOrdersByOrderIdPaginated($searchTerm, $page = 1, $perPage = 10, &$total = null) {
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT * FROM orders WHERE order_id LIKE ? ORDER BY order_id DESC LIMIT ? OFFSET ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, '%' . $searchTerm . '%', \PDO::PARAM_STR);
        $stmt->bindValue(2, (int)$perPage, \PDO::PARAM_INT);
        $stmt->bindValue(3, (int)$offset, \PDO::PARAM_INT);
        $stmt->execute();
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        // Get total count for pagination
        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM orders WHERE order_id LIKE ?");
        $countStmt->execute(['%' . $searchTerm . '%']);
        $total = (int)$countStmt->fetchColumn();
        return $results;
    }

    public function getOrdersFilteredPaginated(array $filters, $page = 1, $perPage = 10, &$total = null) {
        $page = max(1, (int)$page);
        $perPage = max(1, (int)$perPage);
        $offset = ($page - 1) * $perPage;

        $where = [];
        $params = [];

        $searchTerm = trim((string)($filters['order_id_search'] ?? ''));
        if ($searchTerm !== '') {
            $where[] = 'CAST(order_id AS CHAR) LIKE ?';
            $params[] = '%' . $searchTerm . '%';
        }

        $status = strtolower(trim((string)($filters['status'] ?? '')));
        if ($status !== '') {
            $where[] = 'LOWER(status) = ?';
            $params[] = $status;
        }

        $customerType = strtolower(trim((string)($filters['customer_type'] ?? '')));
        if ($customerType !== '') {
            $where[] = 'LOWER(customer_type) = ?';
            $params[] = $customerType;
        }

        $saleType = strtolower(trim((string)($filters['sale_type'] ?? '')));
        if ($saleType !== '') {
            $where[] = 'LOWER(sale_type) = ?';
            $params[] = $saleType;
        }

        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $where[] = 'DATE(order_date) >= ?';
            $params[] = $dateFrom;
        }

        $dateTo = trim((string)($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $where[] = 'DATE(order_date) <= ?';
            $params[] = $dateTo;
        }

        $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

        $allowedSortColumns = ['order_id', 'sale_type', 'total_amount', 'status', 'order_date', 'pickup_datetime', 'return_datetime'];
        $sortBy = $filters['sort_by'] ?? 'order_id';
        if (!in_array($sortBy, $allowedSortColumns, true)) {
            $sortBy = 'order_id';
        }

        $sortDir = strtolower((string)($filters['sort_dir'] ?? 'desc'));
        $sortDir = $sortDir === 'asc' ? 'ASC' : 'DESC';

        $sql = "SELECT * FROM orders" . $whereSql . " ORDER BY {$sortBy} {$sortDir} LIMIT ? OFFSET ?";
        $stmt = $this->db->prepare($sql);

        $idx = 1;
        foreach ($params as $param) {
            $stmt->bindValue($idx++, $param, \PDO::PARAM_STR);
        }
        $stmt->bindValue($idx++, $perPage, \PDO::PARAM_INT);
        $stmt->bindValue($idx, $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $countSql = "SELECT COUNT(*) FROM orders" . $whereSql;
        $countStmt = $this->db->prepare($countSql);
        $idx = 1;
        foreach ($params as $param) {
            $countStmt->bindValue($idx++, $param, \PDO::PARAM_STR);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        return $results;
    }

    // Update order status to 'approved'
    public function approveOrder($orderId) {
        $stmt = $this->db->prepare("UPDATE orders SET status = 'approved' WHERE order_id = ?");
        return $stmt->execute([$orderId]);
    }

    // Update order status to 'completed'
    public function completeOrder($orderId) {
        $stmt = $this->db->prepare("UPDATE orders SET status = 'completed' WHERE order_id = ?");
        return $stmt->execute([$orderId]);
    }

    // Update order status to 'cancelled'
    public function rejectOrder($orderId) {
        $stmt = $this->db->prepare("UPDATE orders SET status = 'cancelled' WHERE order_id = ?");
        return $stmt->execute([$orderId]);
    }

    // Update order status to 'paid'
    public function markAsPaid($orderId) {
        $stmt = $this->db->prepare("UPDATE orders SET status = 'paid' WHERE order_id = ?");
        return $stmt->execute([$orderId]);
    }
    
    // Analytics methods
    public function getCompletedOrdersCount() {
        $stmt = $this->db->query("SELECT COUNT(*) FROM orders WHERE status = 'completed'");
        return (int)$stmt->fetchColumn();
    }

    public function getTotalSales() {
        $stmt = $this->db->query("SELECT SUM(total_amount) FROM orders WHERE status = 'completed'");
        $result = $stmt->fetchColumn();
        return (float)($result ?? 0);
    }

    public function getPendingOrdersCount() {
        $stmt = $this->db->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'");
        return (int)$stmt->fetchColumn();
    }

    public function getOrdersByStatus() {
        $stmt = $this->db->query("SELECT status, COUNT(*) as count FROM orders GROUP BY status");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getSalesByDate($days = 30) {
        $stmt = $this->db->prepare("SELECT DATE(order_date) as date, SUM(total_amount) as total FROM orders WHERE status = 'completed' AND order_date >= DATE_SUB(NOW(), INTERVAL ? DAY) GROUP BY DATE(order_date) ORDER BY date ASC");
        $stmt->execute([$days]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getOrderCountByDate($days = 30) {
        $stmt = $this->db->prepare("SELECT DATE(order_date) as date, COUNT(*) as count FROM orders WHERE order_date >= DATE_SUB(NOW(), INTERVAL ? DAY) GROUP BY DATE(order_date) ORDER BY date ASC");
        $stmt->execute([$days]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Full order process: creates order, items, reservations, generates PDFs, sends email, returns orderId
     */
    public function fullOrderProcess($form, $cart, $session) {
                $myfile = @fopen("order-debug-log.txt", "a");
                if (is_resource($myfile)) {
                    fwrite($myfile, date('Y-m-d H:i:s') . "\n[DEBUG] Entered fullOrderProcess in OrderModel\n");
                    fclose($myfile);
                }
            // DEBUG: Confirm function is called and file can be created
            $myfile = @fopen("order-debug-log.txt", "a");
            if (is_resource($myfile)) {
                fwrite($myfile, date('Y-m-d H:i:s') . "\n[DEBUG] Entered fullOrderProcess\n");
                fclose($myfile);
            }
        // Address logic
        $deliveryType = $form['delivery_type'] ?? 'preferred';
        if ($deliveryType === 'hotel') {
            $hotelId = $form['hotel_id'] ?? null;
            if ($hotelId) {
                $stmt = $this->db->prepare("SELECT address1, address2, state, zip FROM partner_hotels WHERE id = ?");
                $stmt->execute([$hotelId]);
                $hotel = $stmt->fetch(\PDO::FETCH_ASSOC);
                $address1 = $hotel['address1'] ?? '';
                $address2 = $hotel['address2'] ?? '';
                $state = $hotel['state'] ?? '';
                $zip = $hotel['zip'] ?? '';
            } else {
                $address1 = $address2 = $state = $zip = '';
            }
        } elseif ($deliveryType === 'pickup') {
            $address1 = $address2 = $state = $zip = '';
        } else {
            $address1 = htmlspecialchars(trim($form['address1'] ?? ''));
            $address2 = htmlspecialchars(trim($form['address2'] ?? ''));
            $state = htmlspecialchars(trim($form['state'] ?? ''));
            $zip = htmlspecialchars(trim($form['zip'] ?? ''));
        }
        $pickup_location_id = $form['pickup_location'] ?? '';
        $pickup_location = '';
        $pickup_location_address = '';
        if ($deliveryType === 'pickup' && $pickup_location_id) {
            $stmt = $this->db->prepare("SELECT name, address FROM pickup_locations WHERE id = ?");
            $stmt->execute([$pickup_location_id]);
            $pickup = $stmt->fetch(\PDO::FETCH_ASSOC);
            $pickup_location = $pickup['name'] ?? '';
            $pickup_location_address = $pickup['address'] ?? '';
        } else {
            $pickup_location = htmlspecialchars(trim($form['pickup_location'] ?? ''));
            $pickup_location_address = '';
        }
        if ($deliveryType === 'pickup') {
            $pickup_location = trim($pickup_location . ($pickup_location_address ? ' - ' . $pickup_location_address : ''));
        }
        $notes = htmlspecialchars(trim($form['notes'] ?? ''));
        $payment = htmlspecialchars(trim($form['payment'] ?? ''));
        $customerType = isset($session['user_id']) ? 'user' : 'guest';
        $first_name = htmlspecialchars(trim($form['first_name'] ?? ''));
        $last_name = htmlspecialchars(trim($form['last_name'] ?? ''));
        $phone = preg_replace('/\D/', '', $form['phone'] ?? '');
        $email = filter_var(trim($form['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $userId = null;
        $guestId = null;
        // Always use guest_* fields from $form for both user and guest
        $guest_first_name = htmlspecialchars(trim($form['guest_first_name'] ?? $first_name));
        $guest_last_name  = htmlspecialchars(trim($form['guest_last_name'] ?? $last_name));
        $guest_email      = filter_var(trim($form['guest_email'] ?? $email), FILTER_VALIDATE_EMAIL);
        $guest_phone      = preg_replace('/\D/', '', $form['guest_phone'] ?? $phone);
        $customerName = trim($guest_first_name . ' ' . $guest_last_name);
        $customerEmail = $guest_email;
        $customerPhone = $guest_phone;
        $clientWeightOption = htmlspecialchars(trim($form['client_weight_option'] ?? ''));
        $clientWeightLbsRaw = $form['client_weight_lbs'] ?? null;
        $clientWeightLbs = (is_numeric($clientWeightLbsRaw) && (int)$clientWeightLbsRaw > 0) ? (int)$clientWeightLbsRaw : null;
        if (isset($session['user_id'])) {
            $userId = $session['user_id'];
            // Optionally, you can still fetch userRow if needed for other logic
        } else {
            $stmt = $this->db->prepare("SELECT guest_id FROM guests WHERE email = ?");
            $stmt->execute([$email]);
            $guestId = $stmt->fetchColumn();
            if (!$guestId) {
                $fullAddress = $address1 . ($address2 ? " " . $address2 : "");
                $stmt = $this->db->prepare("INSERT INTO guests (first_name, last_name, email, phone, address) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$first_name, $last_name, $email, $phone, $fullAddress]);
                $guestId = $this->db->lastInsertId();
            }
        }
        // Calculate total
        $totalAmount = 0;
        foreach ($cart as $item) {
            $totalAmount += $item['qty'] * $item['price'];
        }
        $totalAmountWithTax = $totalAmount;
        $pickup_datetime = $form['pickup_datetime'] ?? null;
        $return_datetime = $form['return_datetime'] ?? null;
        $deliveryTypeForOrder = in_array(($form['delivery_type'] ?? ''), ['hotel', 'pickup'], true)
            ? $form['delivery_type']
            : 'hotel';
        $hotelIdForOrder = !empty($form['hotel_id']) ? $form['hotel_id'] : null;
            // Insert order with error logging
            try {
                $insertValues = [
                    $userId,
                    $guestId,
                    $guest_first_name,
                    $guest_last_name,
                    $guest_email,
                    $guest_phone,
                    $clientWeightOption !== '' ? $clientWeightOption : null,
                    $clientWeightLbs,
                    $address1,
                    $address2,
                    $state,
                    $zip,
                    $pickup_location,
                    $notes,
                    $payment,
                    $totalAmountWithTax,
                    $customerType,
                    $pickup_datetime,
                    $return_datetime,
                    $deliveryTypeForOrder,
                    $hotelIdForOrder
                ];
                $myfile = @fopen("order-debug-log.txt", "a");
                if (is_resource($myfile)) {
                    fwrite($myfile, date('Y-m-d H:i:s') . "\nOrderModel fullOrderProcess INSERT VALUES:\n" . print_r($insertValues, true) . "\n");
                }
                $stmt = $this->db->prepare("INSERT INTO orders (
                    user_id, guest_id, guest_first_name, guest_last_name, guest_email, guest_phone, client_weight_option, client_weight_lbs, address1, address2, state, zip, pickup_location, notes, payment_method, total_amount, customer_type, pickup_datetime, return_datetime, delivery_type, hotel_id, status, order_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
                $stmt->execute($insertValues);
                $orderId = $this->db->lastInsertId();
                if (is_resource($myfile)) {
                    fwrite($myfile, "OrderModel fullOrderProcess LAST INSERT ID: " . print_r($orderId, true) . "\n\n");
                    fclose($myfile);
                }
                    $myfile = @fopen("order-debug-log.txt", "a");
                    if (is_resource($myfile)) {
                        fwrite($myfile, date('Y-m-d H:i:s') . "\n[DEBUG] Order insert SUCCESS. orderId: $orderId\nParams: " . var_export($insertValues, true) . "\n");
                        fclose($myfile);
                    }
            } catch (\PDOException $e) {
                $myfile = @fopen("order-debug-log.txt", "a");
                if (is_resource($myfile)) {
                    fwrite($myfile, date('Y-m-d H:i:s') . "\nOrderModel fullOrderProcess SQL Error: " . $e->getMessage() . "\n\n");
                    fclose($myfile);
                }
                    $myfile = @fopen("order-debug-log.txt", "a");
                    if (is_resource($myfile)) {
                        fwrite($myfile, date('Y-m-d H:i:s') . "\n[ERROR] Order insert FAILED: " . $e->getMessage() . "\nParams: " . var_export($insertValues ?? [], true) . "\n");
                        fclose($myfile);
                    }
                return false;
            }
        // Debug: Fetch the order after insert to verify and log (fix WHERE clause)
        $debugFile = @fopen("order-debug-log.txt", "a");
        try {
            $stmt = $this->db->prepare("SELECT * FROM orders WHERE order_id = ?");
            $stmt->execute([$orderId]);
            $orderDebug = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (is_resource($debugFile)) {
                fwrite($debugFile, date('Y-m-d H:i:s') . "\n[DEBUG] Order fetch debug for orderId: $orderId: " . var_export($orderDebug, true) . "\n");
            }
        } catch (\PDOException $e) {
            if (is_resource($debugFile)) {
                fwrite($debugFile, date('Y-m-d H:i:s') . "\n[ERROR] Exception during order fetch debug for orderId: $orderId: " . $e->getMessage() . "\n");
            }
        }
        // Continue with order items and reservations
        if (is_resource($debugFile)) {
            fwrite($debugFile, date('Y-m-d H:i:s') . "\n[DEBUG] Starting scooter assignment with overlap check in fullOrderProcess\n");
        }
        $assignedScooters = [];
        $assignmentOk = $this->ensureOrderAssignments($orderId, $cart, $form['pickup_datetime'], $form['return_datetime'], $assignedScooters, $debugFile);
        if (!$assignmentOk) {
            if (is_resource($debugFile)) {
                fwrite($debugFile, date('Y-m-d H:i:s') . "\n[ERROR] Scooter assignment failed for order {$orderId}. Preserving paid order for manual fulfillment.\n");
            }

            // Ensure order_items exist even when scooter assignment fails.
            $itemCountStmt = $this->db->prepare("SELECT COUNT(*) FROM order_items WHERE order_id = ?");
            $itemCountStmt->execute([$orderId]);
            $existingOrderItems = (int)$itemCountStmt->fetchColumn();

            if ($existingOrderItems === 0) {
                $fallbackItemStmt = $this->db->prepare("INSERT INTO order_items (order_id, product_id, product_name, price, quantity, image_url, variation_id, variation_name, scooter_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL)");
                foreach ($cart as $item) {
                    $qty = max(1, (int)($item['qty'] ?? $item['quantity'] ?? 1));
                    $fallbackItemStmt->execute([
                        $orderId,
                        $item['id'] ?? null,
                        $item['name'] ?? 'Item',
                        (float)($item['price'] ?? 0),
                        $qty,
                        $item['image_url'] ?? null,
                        $item['variation_id'] ?? null,
                        $item['variation_name'] ?? null,
                    ]);
                }
            }

            $notesSuffix = trim(($notes ?? '') . "\n[System] Scooter assignment pending manual review.");
            $updateNotesStmt = $this->db->prepare("UPDATE orders SET notes = ? WHERE order_id = ?");
            $updateNotesStmt->execute([$notesSuffix, $orderId]);

            // Continue the flow so PDF generation and email still run.
            $assignedScooters = [];
        }

        // Debug: Log reservation and order_items count for this order after scooter assignment
                $resCount = $this->db->prepare("SELECT COUNT(*) FROM reservations WHERE order_id = ?");
                $resCount->execute([$orderId]);
                $reservationCount = $resCount->fetchColumn();
                $itemCount = $this->db->prepare("SELECT COUNT(*) FROM order_items WHERE order_id = ?");
                $itemCount->execute([$orderId]);
                $orderItemCount = $itemCount->fetchColumn();
                if (is_resource($debugFile)) {
                    fwrite($debugFile, date('Y-m-d H:i:s') . "\n[DEBUG] (Post-Assignment) Reservation count for order_id $orderId: $reservationCount\n");
                    fwrite($debugFile, date('Y-m-d H:i:s') . "\n[DEBUG] (Post-Assignment) Order item count for order_id $orderId: $orderItemCount\n");
                    fwrite($debugFile, date('Y-m-d H:i:s') . "\n[DEBUG] After order item insertions, before PDF/email code in fullOrderProcess\n");
                    fclose($debugFile);
                }
               // Mark scooters as sold if for-sale (for-sale flow)
               $debugFile = @fopen(__DIR__ . '/../../public/order-debug-log.txt', 'a');
               if (is_resource($debugFile)) {
                   fwrite($debugFile, date('Y-m-d H:i:s') . "\n[DEBUG] About to call markScootersSoldIfForSale in fullOrderProcess\nCart: " . print_r($cart, true) . "\nAssigned: " . print_r($assignedScooters, true));
                   fclose($debugFile);
               }
               $this->markScootersSoldIfForSale($cart, $assignedScooters, $form['sale_type'] ?? null);
        // --- CONTRACT PDF GENERATION ---
        $customerAddress = $address1 . ($address2 ? " " . $address2 : "");
        $pickupDate = $pickup_datetime ?? '';
        $returnDate = $return_datetime ?? '';
        $itemsTable = '<table class="w-full border border-collapse text-sm"><thead><tr><th class="border px-2 py-1 text-left">Qty</th><th class="border px-2 py-1 text-left">Item</th><th class="border px-2 py-1 text-left">Unit Price</th><th class="border px-2 py-1 text-left">Total</th></tr></thead><tbody>';
        foreach ($cart as $item) {
            $qty = htmlspecialchars($item['qty']);
            $name = htmlspecialchars($item['name']);
            $unitPrice = '$' . number_format($item['price'], 2);
            $lineTotal = '$' . number_format($item['qty'] * $item['price'], 2);
            $itemsTable .= "<tr><td class='border px-2 py-1'>{$qty}</td><td class='border px-2 py-1'>{$name}</td><td class='border px-2 py-1'>{$unitPrice}</td><td class='border px-2 py-1'>{$lineTotal}</td></tr>";
        }
        $itemsTable .= '</tbody></table>';
        
        // WRAP PDF & EMAIL GENERATION IN TRY-CATCH TO PREVENT BREAKING PAYMENT RESPONSE
        $pdfPath = null;
        $invoicePath = null;
        try {
            ob_start();
            $debugFile = @fopen("order-debug-log.txt", "a");
            if (is_resource($debugFile)) {
                fwrite($debugFile, date('Y-m-d H:i:s') . "\n[DEBUG] Entering PDF generation block in fullOrderProcess\n");
                fclose($debugFile);
            }
            include __DIR__ . '/../../Contracts/contract-template.php';
            $html = ob_get_clean();
            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $options->set('isHtml5ParserEnabled', true);
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $pdfDir = __DIR__ . '/../../Contracts/';
            if ((!is_dir($pdfDir) && !@mkdir($pdfDir, 0777, true)) || !is_writable($pdfDir)) {
                $pdfDir = __DIR__ . '/../../public/Contracts/';
                if (!is_dir($pdfDir) && !@mkdir($pdfDir, 0777, true)) {
                    throw new \RuntimeException('Unable to create Contracts directory.');
                }
            }
            $contractTarget = $pdfDir . "contract-{$orderId}.pdf";
            $written = @file_put_contents($contractTarget, $dompdf->output());
            if ($written === false || !is_file($contractTarget) || filesize($contractTarget) === 0) {
                throw new \RuntimeException('Failed to write contract PDF.');
            }
            $pdfPath = $pdfDir . "contract-{$orderId}.pdf";
            
            // --- INVOICE PDF GENERATION ---
            $invoiceItemsTable = '';
            foreach ($cart as $item) {
                $qty = htmlspecialchars($item['qty']);
                $name = htmlspecialchars($item['name']);
                $unitPrice = number_format($item['price'], 2);
                $lineTotal = number_format($item['qty'] * $item['price'], 2);
                $invoiceItemsTable .= "<tr><td class='border p-2'>{$qty}</td><td class='border p-2'>{$name}</td><td class='border p-2'>\${$unitPrice}</td><td class='border p-2'>\${$lineTotal}</td></tr>";
            }
            $logoSrc = '';
            if (extension_loaded('gd')) {
                $logoPath = __DIR__ . '/../../public/img/Original logo.png';
                if (!file_exists($logoPath)) {
                    $logoPath = __DIR__ . '/../../public/img/Original logo.svg';
                }
                if (file_exists($logoPath)) {
                    $mime = mime_content_type($logoPath);
                    $data = file_get_contents($logoPath);
                    $logoSrc = 'data:' . $mime . ';base64,' . base64_encode($data);
                }
            }
            $itemsTable = $invoiceItemsTable;
            $totalAmount = $totalAmount ?? 0;
            ob_start();
            include __DIR__ . '/../../Invoices/invoice-template.php';
            $invoiceHtml = ob_get_clean();
            $invoiceOptions = new Options();
            $invoiceOptions->set('isRemoteEnabled', true);
            $invoiceOptions->set('isHtml5ParserEnabled', true);
            $invoiceDompdf = new Dompdf($invoiceOptions);
            $invoiceDompdf->loadHtml($invoiceHtml);
            $invoiceDompdf->setPaper('A4', 'portrait');
            $invoiceDompdf->render();
            $invoiceDir = __DIR__ . '/../../Invoices/';
            if ((!is_dir($invoiceDir) && !@mkdir($invoiceDir, 0777, true)) || !is_writable($invoiceDir)) {
                $invoiceDir = __DIR__ . '/../../public/Invoices/';
                if (!is_dir($invoiceDir) && !@mkdir($invoiceDir, 0777, true)) {
                    throw new \RuntimeException('Unable to create Invoices directory.');
                }
            }
            $invoiceTarget = $invoiceDir . "invoice-{$orderId}.pdf";
            $written = @file_put_contents($invoiceTarget, $invoiceDompdf->output());
            if ($written === false || !is_file($invoiceTarget) || filesize($invoiceTarget) === 0) {
                throw new \RuntimeException('Failed to write invoice PDF.');
            }
            $invoicePath = $invoiceDir . "invoice-{$orderId}.pdf";
        } catch (\Throwable $e) {
            // PDF generation failed, but order is already created - log it and continue
            @ob_end_clean();
            error_log("PDF/Invoice generation failed for order {$orderId}: " . $e->getMessage());
            $debugFile = @fopen("order-debug-log.txt", "a");
            if (is_resource($debugFile)) {
                fwrite($debugFile, date('Y-m-d H:i:s') . "\n[ERROR] PDF generation error: " . $e->getMessage() . "\n");
                fclose($debugFile);
            }
            // Continue without PDFs - don't break the order
        }
        
        // --- EMAIL SENDING ---
        // Ensure finalEmail and finalName are set correctly
        if ($customerType === 'guest') {
            $finalEmail = $guest_email;
            $finalName = trim($guest_first_name . ' ' . $guest_last_name);
        } else {
            $finalEmail = $customerEmail;
            $finalName = $customerName;
        }
        
        if (filter_var($finalEmail, FILTER_VALIDATE_EMAIL)) {
            try {
                $mail = new PHPMailer(true);
                $debugFile = @fopen(__DIR__ . '/../../public/order-debug-log.txt', 'a');
                if (is_resource($debugFile)) {
                    fwrite($debugFile, date('Y-m-d H:i:s') . "\n[DEBUG] Preparing to send contract/invoice email to: $finalEmail\n");
                }
                $mail->isSMTP();
                $mail->Host = getenv('SMTP_HOST') ?: ($_ENV['SMTP_HOST'] ?? 'smtp.gmail.com');
                $mail->SMTPAuth = true;
                $mail->Username = getenv('SMTP_USERNAME') ?: ($_ENV['SMTP_USERNAME'] ?? null);
                $mail->Password = getenv('SMTP_PASSWORD') ?: ($_ENV['SMTP_PASSWORD'] ?? null);
                $mail->SMTPSecure = 'tls';
                $mail->Port = getenv('SMTP_PORT') ?: ($_ENV['SMTP_PORT'] ?? 587);
                $mail->setFrom(getenv('SMTP_FROM_EMAIL') ?: ($_ENV['SMTP_FROM_EMAIL'] ?? null), 'Get Around Mobility');
                $mail->addAddress($finalEmail, $finalName);
                $mail->Subject = 'Your Rental Booking Confirmation';
                if ($pdfPath && is_file($pdfPath) && $invoicePath && is_file($invoicePath)) {
                    $mail->Body = "Thank you for your booking! Please find your rental contract and invoice attached.";
                    $mail->addAttachment($pdfPath, "Rental-Contract-{$orderId}.pdf");
                    $mail->addAttachment($invoicePath, "Invoice-{$orderId}.pdf");
                } else {
                    $mail->Body = "Thank you for your booking! Your contract/invoice files are being prepared and will be sent shortly.";
                }
                $mail->send();
                if (is_resource($debugFile)) {
                    fwrite($debugFile, date('Y-m-d H:i:s') . "\n[DEBUG] Contract/invoice email sent successfully to: $finalEmail\n");
                }
            } catch (MailException $e) {
                $debugFile = @fopen(__DIR__ . '/../../public/order-debug-log.txt', 'a');
                if (is_resource($debugFile)) {
                    fwrite($debugFile, date('Y-m-d H:i:s') . "\n[ERROR] Contract/invoice email failed: " . $mail->ErrorInfo . "\nException: " . $e->getMessage() . "\n");
                    fclose($debugFile);
                }
                error_log("Mailer Error: {$mail->ErrorInfo}");
            }
        } else {
            $debugFile = @fopen(__DIR__ . '/../../public/order-debug-log.txt', 'a');
            if (is_resource($debugFile)) {
                fwrite($debugFile, date('Y-m-d H:i:s') . "\n[ERROR] Skipped email: invalid recipient for order {$orderId}. Value: {$finalEmail}\n");
                fclose($debugFile);
            }
        }
        if (is_resource($debugFile)) {
            fclose($debugFile);
        }
        // Generate one-time secure token
        $token = bin2hex(random_bytes(32));
        $_SESSION["order_token_{$orderId}"] = $token;
        return $orderId;
    }

    public function ensureOrderDocumentsAndEmail($orderId, $cart = null) {
        $debugFile = @fopen(__DIR__ . '/../../public/order-debug-log.txt', 'a');
        if (is_resource($debugFile)) {
            fwrite($debugFile, date('Y-m-d H:i:s') . "\n[DEBUG] ensureOrderDocumentsAndEmail started for orderId: {$orderId}\n");
        }

        $stmt = $this->db->prepare("SELECT * FROM orders WHERE order_id = ? LIMIT 1");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$order) {
            if (is_resource($debugFile)) {
                fwrite($debugFile, date('Y-m-d H:i:s') . "\n[ERROR] Order not found for document recovery. orderId: {$orderId}\n");
                fclose($debugFile);
            }
            return ['success' => false, 'error' => 'Order not found'];
        }

        if (!empty($cart) && is_array($cart)) {
            $recoveredAssignments = [];
            $this->ensureOrderAssignments($orderId, $cart, $order['pickup_datetime'] ?? '', $order['return_datetime'] ?? '', $recoveredAssignments, $debugFile);
        }

        $itemsStmt = $this->db->prepare("SELECT product_name, variation_name, quantity, price FROM order_items WHERE order_id = ? ORDER BY order_item_id ASC");
        $itemsStmt->execute([$orderId]);
        $items = $itemsStmt->fetchAll(\PDO::FETCH_ASSOC);

        $customerName = trim(($order['guest_first_name'] ?? '') . ' ' . ($order['guest_last_name'] ?? ''));
        $customerEmail = filter_var(trim($order['guest_email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $customerPhone = $order['guest_phone'] ?? '';
        $customerAddress = trim(($order['address1'] ?? '') . ' ' . ($order['address2'] ?? ''));
        $pickupDate = $order['pickup_datetime'] ?? '';
        $returnDate = $order['return_datetime'] ?? '';
        $totalAmountWithTax = (float)($order['total_amount'] ?? 0);

        $subtotal = 0;
        $itemsTable = '<table class="w-full border border-collapse text-sm"><thead><tr><th class="border px-2 py-1 text-left">Qty</th><th class="border px-2 py-1 text-left">Item</th><th class="border px-2 py-1 text-left">Unit Price</th><th class="border px-2 py-1 text-left">Total</th></tr></thead><tbody>';
        $invoiceItemsTable = '';
        foreach ($items as $item) {
            $qty = max(1, (int)($item['quantity'] ?? 1));
            $name = trim(($item['product_name'] ?? 'Item') . (!empty($item['variation_name']) ? ' - ' . $item['variation_name'] : ''));
            $price = (float)($item['price'] ?? 0);
            $lineTotalValue = $qty * $price;
            $subtotal += $lineTotalValue;

            $safeQty = htmlspecialchars((string)$qty);
            $safeName = htmlspecialchars($name);
            $unitPrice = '$' . number_format($price, 2);
            $lineTotal = '$' . number_format($lineTotalValue, 2);

            $itemsTable .= "<tr><td class='border px-2 py-1'>{$safeQty}</td><td class='border px-2 py-1'>{$safeName}</td><td class='border px-2 py-1'>{$unitPrice}</td><td class='border px-2 py-1'>{$lineTotal}</td></tr>";
            $invoiceItemsTable .= "<tr><td class='border p-2'>{$safeQty}</td><td class='border p-2'>{$safeName}</td><td class='border p-2'>{$unitPrice}</td><td class='border p-2'>{$lineTotal}</td></tr>";
        }
        $itemsTable .= '</tbody></table>';

        if ($subtotal <= 0 && $totalAmountWithTax > 0) {
            $subtotal = round($totalAmountWithTax / 1.08375, 2);
        }
        $totalAmount = $subtotal;
        $pickup_datetime = $pickupDate;
        $return_datetime = $returnDate;

        $contractDir = __DIR__ . '/../../Contracts/';
        $invoiceDir = __DIR__ . '/../../Invoices/';
        if ((!is_dir($contractDir) && !@mkdir($contractDir, 0777, true)) || !is_writable($contractDir)) {
            $contractDir = __DIR__ . '/../../public/Contracts/';
            if (!is_dir($contractDir) && !@mkdir($contractDir, 0777, true)) {
                $contractDir = null;
            }
        }
        if ((!is_dir($invoiceDir) && !@mkdir($invoiceDir, 0777, true)) || !is_writable($invoiceDir)) {
            $invoiceDir = __DIR__ . '/../../public/Invoices/';
            if (!is_dir($invoiceDir) && !@mkdir($invoiceDir, 0777, true)) {
                $invoiceDir = null;
            }
        }

        $pdfPath = $contractDir ? $contractDir . "contract-{$orderId}.pdf" : null;
        $invoicePath = $invoiceDir ? $invoiceDir . "invoice-{$orderId}.pdf" : null;

        // WRAP PDF GENERATION IN TRY-CATCH
        try {
            if ($pdfPath && (!file_exists($pdfPath) || filesize($pdfPath) === 0)) {
                ob_start();
                include __DIR__ . '/../../Contracts/contract-template.php';
                $html = ob_get_clean();
                $options = new Options();
                $options->set('isRemoteEnabled', true);
                $options->set('isHtml5ParserEnabled', true);
                $dompdf = new Dompdf($options);
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();
                $written = @file_put_contents($pdfPath, $dompdf->output());
                if ($written === false || !is_file($pdfPath) || filesize($pdfPath) === 0) {
                    throw new \RuntimeException('Failed to write recovery contract PDF.');
                }
                if (is_resource($debugFile)) {
                    fwrite($debugFile, date('Y-m-d H:i:s') . "\n[DEBUG] Contract PDF generated for orderId: {$orderId}\n");
                }
            }
        } catch (\Throwable $e) {
            @ob_end_clean();
            error_log("Contract PDF generation failed for order {$orderId}: " . $e->getMessage());
            if (is_resource($debugFile)) {
                fwrite($debugFile, date('Y-m-d H:i:s') . "\n[ERROR] Contract PDF error: " . $e->getMessage() . "\n");
            }
            $pdfPath = null;
        }

        $logoSrc = '';
        if (extension_loaded('gd')) {
            $logoPath = __DIR__ . '/../../public/img/Original logo.png';
            if (!file_exists($logoPath)) {
                $logoPath = __DIR__ . '/../../public/img/Original logo.svg';
            }
            if (file_exists($logoPath)) {
                $mime = mime_content_type($logoPath);
                $data = file_get_contents($logoPath);
                $logoSrc = 'data:' . $mime . ';base64,' . base64_encode($data);
            }
        }

        $itemsTable = $invoiceItemsTable;
        
        // WRAP INVOICE PDF GENERATION IN TRY-CATCH
        try {
            if ($invoicePath && (!file_exists($invoicePath) || filesize($invoicePath) === 0)) {
                ob_start();
                include __DIR__ . '/../../Invoices/invoice-template.php';
                $invoiceHtml = ob_get_clean();
                $invoiceOptions = new Options();
                $invoiceOptions->set('isRemoteEnabled', true);
                $invoiceOptions->set('isHtml5ParserEnabled', true);
                $invoiceDompdf = new Dompdf($invoiceOptions);
                $invoiceDompdf->loadHtml($invoiceHtml);
                $invoiceDompdf->setPaper('A4', 'portrait');
                $invoiceDompdf->render();
                $written = @file_put_contents($invoicePath, $invoiceDompdf->output());
                if ($written === false || !is_file($invoicePath) || filesize($invoicePath) === 0) {
                    throw new \RuntimeException('Failed to write recovery invoice PDF.');
                }
                if (is_resource($debugFile)) {
                    fwrite($debugFile, date('Y-m-d H:i:s') . "\n[DEBUG] Invoice PDF generated for orderId: {$orderId}\n");
                }
            }
        } catch (\Throwable $e) {
            @ob_end_clean();
            error_log("Invoice PDF generation failed for order {$orderId}: " . $e->getMessage());
            if (is_resource($debugFile)) {
                fwrite($debugFile, date('Y-m-d H:i:s') . "\n[ERROR] Invoice PDF error: " . $e->getMessage() . "\n");
            }
            $invoicePath = null;
        }

        $emailSent = false;
        if ($customerEmail) {
            require_once __DIR__ . '/../Utils/Mailer.php';
            $attachments = [];
            if ($pdfPath && file_exists($pdfPath)) {
                $attachments[] = ['path' => $pdfPath, 'name' => "Rental-Contract-{$orderId}.pdf"];
            }
            if ($invoicePath && file_exists($invoicePath)) {
                $attachments[] = ['path' => $invoicePath, 'name' => "Invoice-{$orderId}.pdf"];
            }
            $bodyHtml = !empty($attachments)
                ? 'Thank you for your booking! Please find your rental contract and invoice attached.'
                : 'Thank you for your booking! Your contract/invoice files are being prepared and will be sent shortly.';
            $emailSent = \sendBookingConfirmation($customerEmail, $customerName, 'Your Rental Booking Confirmation', $bodyHtml, $attachments);
            if (is_resource($debugFile)) {
                fwrite($debugFile, date('Y-m-d H:i:s') . "\n[DEBUG] Recovery email send result for orderId {$orderId}: " . ($emailSent ? 'sent' : 'failed') . "\n");
            }
        } else {
            if (is_resource($debugFile)) {
                fwrite($debugFile, date('Y-m-d H:i:s') . "\n[ERROR] Recovery skipped because customer email is invalid for orderId: {$orderId}\n");
            }
        }

        if (is_resource($debugFile)) {
            fclose($debugFile);
        }
        return [
            'success' => true,
            'contractPath' => $pdfPath,
            'invoicePath' => $invoicePath,
            'emailSent' => $emailSent,
        ];
    }

    /**
     * Complete an order: update scooter and reservation status, send email, return messages
     */
    public function completeOrderProcess($orderId){
        $pdo = $this->db;
        $messages = [];
        $messages[] = "<strong>Order Completed!</strong> (Order ID: " . htmlspecialchars($orderId) . ")";

        $stmtOrderMeta = $pdo->prepare("SELECT sale_type FROM orders WHERE order_id = ?");
        $stmtOrderMeta->execute([$orderId]);
        $orderMeta = $stmtOrderMeta->fetch(\PDO::FETCH_ASSOC);
        $isSaleOrder = isset($orderMeta['sale_type']) && strtolower((string)$orderMeta['sale_type']) === 'sale';

        // Get all order items for this order
        $stmt = $pdo->prepare("SELECT order_item_id, product_id, scooter_id, quantity FROM order_items WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $orderItems = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($orderItems as $item) {
            $productId = $item['product_id'];
            $scooterId = intval($item['scooter_id']);
            $orderItemId = $item['order_item_id'];
            // Only update scooter status to 'available' if scooterId exists AND is not already Sold
            if ($scooterId) {
                if ($isSaleOrder) {
                    $stmtScooterSold = $pdo->prepare("UPDATE scooters SET status = 'Sold' WHERE scooter_id = ?");
                    $stmtScooterSold->execute([$scooterId]);
                }
                $messages[] = "Order Item ID: " . htmlspecialchars($orderItemId) . " | Product ID: " . htmlspecialchars($productId) . " | Scooter ID: " . htmlspecialchars($scooterId);
                $messages[] = "<hr>";
            }
        }

        // Update order status to 'completed'
        $stmtOrder = $pdo->prepare("UPDATE orders SET status = 'completed' WHERE order_id = ?");
        $stmtOrder->execute([$orderId]);
        $messages[] = "Order $orderId marked as completed.";

        // Update all reservations for this order to 'completed'
        $stmtReservations = $pdo->prepare("UPDATE reservations SET status = 'completed' WHERE order_id = ?");
        $stmtReservations->execute([$orderId]);

        // Get customer info for this order
        // Try to get user info if user_id exists, else use guest info
        $stmt = $pdo->prepare("SELECT o.guest_first_name, o.guest_last_name, o.guest_email, u.first_name, u.last_name, u.email FROM orders o LEFT JOIN users u ON o.user_id = u.user_id WHERE o.order_id = ?");
        $stmt->execute([$orderId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $email = $row['email'] ?? $row['guest_email'] ?? null;
        $name = null;
        if (!empty($row['first_name']) || !empty($row['last_name'])) {
            $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        } elseif (!empty($row['guest_first_name']) || !empty($row['guest_last_name'])) {
            $name = trim(($row['guest_first_name'] ?? '') . ' ' . ($row['guest_last_name'] ?? ''));
        }
        if ($email) {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = getenv('SMTP_HOST') ?: ($_ENV['SMTP_HOST'] ?? 'smtp.gmail.com');
                $mail->SMTPAuth   = true;
                $mail->Username   = getenv('SMTP_USERNAME') ?: ($_ENV['SMTP_USERNAME'] ?? null);
                $mail->Password   = getenv('SMTP_PASSWORD') ?: ($_ENV['SMTP_PASSWORD'] ?? null);
                $mail->SMTPSecure = 'tls';
                $mail->Port = getenv('SMTP_PORT') ?: ($_ENV['SMTP_PORT'] ?? 587);
                $mail->setFrom(getenv('SMTP_FROM_EMAIL') ?: ($_ENV['SMTP_FROM_EMAIL'] ?? null), 'Get Around Mobility');
                $mail->addAddress($email, $name);
                $mail->Subject = 'Your Order Has Been Completed';
                $mail->Body    = "Hi $name,\n\nYour order #$orderId has been marked as completed. Thank you for using Get Around Mobility!";
                $mail->send();
            } catch (\PHPMailer\PHPMailer\Exception $e) {
                error_log("Mailer Error: {$mail->ErrorInfo}");
            }
        }

        return $messages;
    }

    /**
     * Cancel an order: set scooter status to available, set order status to cancelled, return message
     */
    public function cancelOrderProcess($orderId) {
        $pdo = $this->db;
        // Get all order items for this order
        $stmt = $pdo->prepare("SELECT order_item_id, product_id, scooter_id, quantity FROM order_items WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $orderItems = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($orderItems as $item) {
            $scooterId = intval($item['scooter_id']);
            // Set scooter status to 'available' only if not already Sold
            if ($scooterId) {
                $stmtPrevStatus = $pdo->prepare("SELECT status FROM scooters WHERE scooter_id = ?");
                $stmtPrevStatus->execute([$scooterId]);
                $prevStatus = $stmtPrevStatus->fetchColumn();
                if ($prevStatus !== 'Sold') {
                    $stmtScooter = $pdo->prepare("UPDATE scooters SET status = 'available' WHERE scooter_id = ?");
                    $stmtScooter->execute([$scooterId]);
                }
            }
        }

        // Update order status to 'cancelled'
        $stmtOrder = $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE order_id = ?");
        $stmtOrder->execute([$orderId]);

        return "Order $orderId has been cancelled and inventory restored.";
    }

    /**
     * Get order details, items, and PDF paths for ajaxOrderDetails
     */
    public function getOrderDetails($orderId) {
        $pdo = $this->db;
        // Fetch order
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(\PDO::FETCH_ASSOC);
        // Fetch order items
        $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        // Contract PDF (support both root and public fallback directories)
        $contractPdf = null;
        $contractCandidates = [
            [
                'url' => "/GetAroundMobility/Contracts/contract-{$orderId}.pdf",
                'path' => __DIR__ . '/../../Contracts/contract-' . $orderId . '.pdf',
            ],
            [
                'url' => "/GetAroundMobility/public/Contracts/contract-{$orderId}.pdf",
                'path' => __DIR__ . '/../../public/Contracts/contract-' . $orderId . '.pdf',
            ],
        ];
        foreach ($contractCandidates as $candidate) {
            if (file_exists($candidate['path']) && is_readable($candidate['path'])) {
                $contractPdf = $candidate['url'];
                break;
            }
        }

        // Invoice PDF (support both root and public fallback directories)
        $invoicePdf = null;
        $invoiceCandidates = [
            [
                'url' => "/GetAroundMobility/Invoices/invoice-{$orderId}.pdf",
                'path' => __DIR__ . '/../../Invoices/invoice-' . $orderId . '.pdf',
            ],
            [
                'url' => "/GetAroundMobility/public/Invoices/invoice-{$orderId}.pdf",
                'path' => __DIR__ . '/../../public/Invoices/invoice-' . $orderId . '.pdf',
            ],
        ];
        foreach ($invoiceCandidates as $candidate) {
            if (file_exists($candidate['path']) && is_readable($candidate['path'])) {
                $invoicePdf = $candidate['url'];
                break;
            }
        }
        return [
            'order' => $order,
            'items' => $items,
            'contract_pdf' => $contractPdf,
            'invoice_pdf' => $invoicePdf
        ];
    }

    /**
     * Create Stripe Checkout Session (business logic moved from controller)
     * @param array $post POST data
     * @param array $session SESSION data
     * @return array [ 'id' => sessionId ] or [ 'error' => message ]
     */
    public function createStripeCheckoutSession($post, $session) {
        // Validate cart
        $cart = json_decode($post['cart'] ?? '[]', true);
        if (!is_array($cart) || empty($cart)) {
            return ['error' => 'Empty cart'];
        }

        // Normalize cart shape so metadata cart_json always includes qty.
        $normalizedCart = [];
        foreach ($cart as $item) {
            $qty = max(1, intval($item['qty'] ?? $item['quantity'] ?? 1));
            $normalizedCart[] = array_merge($item, [
                'qty' => $qty,
                'quantity' => $qty,
            ]);
        }
        $cart = $normalizedCart;

        // Availability check
        $pickup_datetime = $post['pickup_datetime'] ?? '';
        $return_datetime = $post['return_datetime'] ?? '';
        if (!$this->isCartAvailable($cart, $pickup_datetime, $return_datetime)) {
            return ['error' => 'Some items are no longer available for the selected dates. Please update your cart.'];
        }

        $stripeSecret = $_ENV['STRIPE_SECRET_KEY'] ?? null;
        if (!$stripeSecret) {
            return ['error' => 'Stripe secret not configured'];
        }

        $guestEmail = filter_var(trim($post['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        if (!$guestEmail) {
            return ['error' => 'A valid customer email is required for Stripe checkout.'];
        }

        \Stripe\Stripe::setApiKey($stripeSecret);

        $lineItems = [];
        $totalAmount = 0;
        foreach ($cart as $item) {
            $price = (float)($item['price'] ?? 0);
            $qty = max(1, intval($item['qty'] ?? $item['quantity'] ?? 1));
            if ($price <= 0) continue;
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => ['name' => ($item['name'] ?? 'Item')],
                    'unit_amount' => (int) round($price * 100),
                ],
                'quantity' => $qty,
            ];
            $totalAmount += $price * $qty;
        }
        if (empty($lineItems)) {
            return ['error' => 'No valid items'];
        }

        $pickup_location_id = $post['pickup_location'] ?? '';
        $pickup_location = '';
        $pickup_location_address = '';
        $deliveryType = $post['delivery_type'] ?? 'preferred';

        if ($deliveryType === 'hotel' && empty($post['hotel_id'])) {
            return ['error' => 'Please select a partner hotel for delivery.'];
        }
        if ($deliveryType === 'pickup' && empty($pickup_location_id)) {
            return ['error' => 'Please select a pickup store.'];
        }

        if ($deliveryType === 'pickup' && $pickup_location_id) {
            $stmt = $this->db->prepare("SELECT name, address FROM pickup_locations WHERE id = ?");
            $stmt->execute([$pickup_location_id]);
            $pickup = $stmt->fetch(\PDO::FETCH_ASSOC);
            $pickup_location = $pickup['name'] ?? '';
            $pickup_location_address = $pickup['address'] ?? '';
            $pickup_location = trim($pickup_location . ($pickup_location_address ? ' - ' . $pickup_location_address : ''));
        } else {
            $pickup_location = htmlspecialchars(trim($post['pickup_location'] ?? ''));
        }

        // attach form fields in metadata so webhook can create order
        $metadata = [
            'first_name' => htmlspecialchars(trim($post['first_name'] ?? '')),
            'last_name' => htmlspecialchars(trim($post['last_name'] ?? '')),
            'guest_email' => $guestEmail,
            'guest_phone' => preg_replace('/\D/', '', $post['phone'] ?? ''),
            'client_weight_option' => htmlspecialchars(trim($post['client_weight_option'] ?? '')),
            'client_weight_lbs' => is_numeric($post['client_weight_lbs'] ?? null) ? (string) ((int) $post['client_weight_lbs']) : '',
            'address1' => htmlspecialchars(trim($post['address1'] ?? '')),
            'address2' => htmlspecialchars(trim($post['address2'] ?? '')),
            'state' => htmlspecialchars(trim($post['state'] ?? '')),
            'zip' => htmlspecialchars(trim($post['zip'] ?? '')),
            'delivery_type' => htmlspecialchars(trim($post['delivery_type'] ?? 'preferred')),
            'hotel_id' => (string)($post['hotel_id'] ?? ''),
            'pickup_datetime' => htmlspecialchars(trim($post['pickup_datetime'] ?? '')),
            'return_datetime' => htmlspecialchars(trim($post['return_datetime'] ?? '')),
            'pickup_location' => $pickup_location,
            'notes' => htmlspecialchars(trim($post['notes'] ?? '')),
            'sale_type' => htmlspecialchars(trim($post['sale_type'] ?? 'rental')),
            'cart_json' => json_encode($cart),
            'total_amount' => (string)$totalAmount,
            'logged_in_user_id' => (string)($session['user_id'] ?? ''),
        ];

        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $successUrl = "{$scheme}://{$host}/stripe-return";
        $cancelUrl  = "{$scheme}://{$host}/checkout?cancel=1";

        try {
            $sessionParams = [
                'automatic_payment_methods' => ['enabled' => true],
                'line_items' => $lineItems,
                'mode' => 'payment',
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'metadata' => $metadata,
            ];

            try {
                $session = \Stripe\Checkout\Session::create($sessionParams, [
                    'stripe_version' => '2023-10-16',
                ]);
            } catch (\Exception $inner) {
                // Fallback for accounts pinned to old API versions where automatic_payment_methods is rejected.
                if (stripos($inner->getMessage(), 'unknown parameter: automatic_payment_methods') !== false) {
                    unset($sessionParams['automatic_payment_methods']);
                    $sessionParams['payment_method_types'] = ['card'];
                    $session = \Stripe\Checkout\Session::create($sessionParams);
                } else {
                    throw $inner;
                }
            }

            return ['id' => $session->id];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function createStripePaymentIntent($post, $session) {
        $cart = json_decode($post['cart'] ?? '[]', true);
        if (!is_array($cart) || empty($cart)) {
            return ['error' => 'Empty cart'];
        }

        // Normalize cart shape for both Stripe metadata and order persistence.
        $normalizedCart = [];
        foreach ($cart as $item) {
            $qty = max(1, intval($item['qty'] ?? $item['quantity'] ?? 1));
            $normalizedCart[] = array_merge($item, [
                'qty' => $qty,
                'quantity' => $qty,
            ]);
        }
        $cart = $normalizedCart;

        $pickup_datetime = $post['pickup_datetime'] ?? '';
        $return_datetime = $post['return_datetime'] ?? '';
        if (!$this->isCartAvailable($cart, $pickup_datetime, $return_datetime)) {
            return ['error' => 'Some items are no longer available for the selected dates. Please update your cart.'];
        }

        $stripeSecret = $_ENV['STRIPE_SECRET_KEY'] ?? null;
        if (!$stripeSecret) {
            return ['error' => 'Stripe secret not configured'];
        }

        $guestEmail = filter_var(trim($post['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        if (!$guestEmail) {
            return ['error' => 'A valid customer email is required for Stripe checkout.'];
        }

        \Stripe\Stripe::setApiKey($stripeSecret);

        $totalAmount = 0;
        foreach ($cart as $item) {
            $price = (float)($item['price'] ?? 0);
            $qty = max(1, intval($item['qty'] ?? $item['quantity'] ?? 1));
            if ($price <= 0) continue;
            $totalAmount += $price * $qty;
        }

        if ($totalAmount <= 0) {
            return ['error' => 'No valid items'];
        }

        $deliveryType = $post['delivery_type'] ?? 'preferred';
        $pickup_location = '';
        if ($deliveryType === 'pickup' && !empty($post['pickup_location'])) {
            $pickup_location = htmlspecialchars(trim($post['pickup_location']));
        }

        $metadata = [
            'first_name' => htmlspecialchars(trim($post['first_name'] ?? '')),
            'last_name' => htmlspecialchars(trim($post['last_name'] ?? '')),
            'guest_email' => $guestEmail,
            'guest_phone' => preg_replace('/\D/', '', $post['phone'] ?? ''),
            'client_weight_option' => htmlspecialchars(trim($post['client_weight_option'] ?? '')),
            'client_weight_lbs' => is_numeric($post['client_weight_lbs'] ?? null) ? (string) ((int) $post['client_weight_lbs']) : '',
            'address1' => htmlspecialchars(trim($post['address1'] ?? '')),
            'address2' => htmlspecialchars(trim($post['address2'] ?? '')),
            'state' => htmlspecialchars(trim($post['state'] ?? '')),
            'zip' => htmlspecialchars(trim($post['zip'] ?? '')),
            'delivery_type' => htmlspecialchars(trim($post['delivery_type'] ?? 'preferred')),
            'hotel_id' => (string)($post['hotel_id'] ?? ''),
            'pickup_datetime' => htmlspecialchars(trim($post['pickup_datetime'] ?? '')),
            'return_datetime' => htmlspecialchars(trim($post['return_datetime'] ?? '')),
            'pickup_location' => $pickup_location,
            'notes' => htmlspecialchars(trim($post['notes'] ?? '')),
            'sale_type' => htmlspecialchars(trim($post['sale_type'] ?? 'rental')),
            'cart_json' => json_encode($cart),
            'total_amount' => (string)$totalAmount,
            'logged_in_user_id' => (string)($session['user_id'] ?? ''),
        ];

        $intentParams = [
            'amount' => (int)round($totalAmount * 100),
            'currency' => 'usd',
            'automatic_payment_methods' => ['enabled' => true],
            'metadata' => $metadata,
        ];

        try {
            try {
                $intent = \Stripe\PaymentIntent::create($intentParams, ['stripe_version' => '2023-10-16']);
            } catch (\Exception $e) {
                if (stripos($e->getMessage(), 'unknown parameter: automatic_payment_methods') !== false) {
                    unset($intentParams['automatic_payment_methods']);
                    $intentParams['payment_method_types'] = ['card'];
                    $intent = \Stripe\PaymentIntent::create($intentParams);
                } else {
                    throw $e;
                }
            }
            return ['clientSecret' => $intent->client_secret];
        } catch (\Exception $e) {
            error_log('Stripe PaymentIntent error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }


}