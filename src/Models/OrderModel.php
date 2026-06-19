<?php
namespace App\Models;

use App\Utils\Database;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;
use Dompdf\Dompdf;
use Dompdf\Options;

class OrderModel {
            private const NV_TAX_INCLUSIVE_FACTOR = 1.08375;
            private const SECURITY_DEPOSIT = 100.00;

            private function sanitizeProductNameForStorage($name): string {
                $value = trim((string)$name);
                // Remove UI stock suffixes such as "(12 available)".
                $value = preg_replace('/\s*\(\d+\s+available\)\s*$/i', '', $value);
                return trim((string)$value);
            }

            private function normalizeVariationId($variationId): ?int {
                if ($variationId === null) return null;
                $value = trim((string)$variationId);
                if ($value === '' || strtolower($value) === 'null' || $value === '0') return null;
                return is_numeric($value) ? (int)$value : null;
            }

            private function getRentalDays($pickupDatetime, $returnDatetime): int {
                $pickupTs = strtotime((string)$pickupDatetime);
                $returnTs = strtotime((string)$returnDatetime);
                if (!$pickupTs || !$returnTs || $returnTs <= $pickupTs) {
                    return 1;
                }
                $days = (int)ceil(($returnTs - $pickupTs) / 86400);
                return max(1, min(31, $days));
            }

            private function getCatalogBasePrice(int $productId, ?int $variationId): float {
                if ($variationId !== null) {
                    $stmt = $this->db->prepare("SELECT price FROM product_variations WHERE variation_id = ? AND product_id = ? LIMIT 1");
                    $stmt->execute([$variationId, $productId]);
                    $price = $stmt->fetchColumn();
                    if ($price !== false) {
                        return (float)$price;
                    }
                }

                $stmt = $this->db->prepare("SELECT price FROM products WHERE product_id = ? LIMIT 1");
                $stmt->execute([$productId]);
                $price = $stmt->fetchColumn();
                return $price !== false ? (float)$price : 0.0;
            }

            private function getTrustedUnitPrice(int $productId, ?int $variationId, string $saleType, int $rentalDays): float {
                if ($saleType === 'rental') {
                    if ($variationId !== null) {
                        $stmt = $this->db->prepare("SELECT price FROM rental_prices WHERE product_id = ? AND variation_id = ? AND days = ? LIMIT 1");
                        $stmt->execute([$productId, $variationId, $rentalDays]);
                        $tierPrice = $stmt->fetchColumn();
                        if ($tierPrice !== false) {
                            return (float)$tierPrice;
                        }
                    }

                    $stmt = $this->db->prepare("SELECT price FROM rental_prices WHERE product_id = ? AND variation_id IS NULL AND days = ? LIMIT 1");
                    $stmt->execute([$productId, $rentalDays]);
                    $baseTierPrice = $stmt->fetchColumn();
                    if ($baseTierPrice !== false) {
                        return (float)$baseTierPrice;
                    }
                }

                return $this->getCatalogBasePrice($productId, $variationId);
            }

            private ?bool $orderRefundsHasRefundMethodColumn = null;

            private function hasOrderRefundMethodColumn(): bool {
                if ($this->orderRefundsHasRefundMethodColumn !== null) {
                    return $this->orderRefundsHasRefundMethodColumn;
                }

                try {
                    $col = $this->db->query("SHOW COLUMNS FROM order_refunds LIKE 'refund_method'");
                    $this->orderRefundsHasRefundMethodColumn = (bool)($col && $col->fetch(\PDO::FETCH_ASSOC));
                } catch (\Throwable $e) {
                    $this->orderRefundsHasRefundMethodColumn = false;
                }

                return $this->orderRefundsHasRefundMethodColumn;
            }

            private function executeOrThrow(\PDOStatement $stmt, array $params): void {
                if (!$stmt->execute($params)) {
                    $errorInfo = $stmt->errorInfo();
                    throw new \RuntimeException('SQL execute failed: ' . implode(' | ', array_filter($errorInfo ?: [])));
                }
            }

            private function insertOrderRefundRecord(array $payload): void {
                $supportsRefundMethod = $this->hasOrderRefundMethodColumn();

                if ($supportsRefundMethod) {
                    $stmt = $this->db->prepare(
                        "INSERT INTO order_refunds (
                            order_id, payment_provider, refund_method, requested_amount, approved_amount, reason, admin_id,
                            provider_refund_id, provider_transaction_reference, status, provider_response_snapshot, idempotency_key
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    try {
                        $this->executeOrThrow($stmt, [
                            $payload['order_id'],
                            $payload['payment_provider'],
                            $payload['refund_method'],
                            $payload['requested_amount'],
                            $payload['approved_amount'],
                            $payload['reason'],
                            $payload['admin_id'],
                            $payload['provider_refund_id'],
                            $payload['provider_transaction_reference'],
                            $payload['status'],
                            $payload['provider_response_snapshot'],
                            $payload['idempotency_key'],
                        ]);
                        return;
                    } catch (\Throwable $e) {
                        if (stripos($e->getMessage(), 'refund_method') === false) {
                            throw $e;
                        }
                        $this->orderRefundsHasRefundMethodColumn = false;
                    }
                }

                $legacyStmt = $this->db->prepare(
                    "INSERT INTO order_refunds (
                        order_id, payment_provider, requested_amount, approved_amount, reason, admin_id,
                        provider_refund_id, provider_transaction_reference, status, provider_response_snapshot, idempotency_key
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $this->executeOrThrow($legacyStmt, [
                    $payload['order_id'],
                    $payload['payment_provider'],
                    $payload['requested_amount'],
                    $payload['approved_amount'],
                    $payload['reason'],
                    $payload['admin_id'],
                    $payload['provider_refund_id'],
                    $payload['provider_transaction_reference'],
                    $payload['status'],
                    $payload['provider_response_snapshot'],
                    $payload['idempotency_key'],
                ]);
            }

            public function normalizeCartForTrustedPricing(array $cart, $pickupDatetime = null, $returnDatetime = null, $saleType = 'rental'): array {
                $normalized = [];
                $days = $this->getRentalDays($pickupDatetime, $returnDatetime);
                $orderSaleType = strtolower(trim((string)$saleType));
                if (!in_array($orderSaleType, ['rental', 'sale'], true)) {
                    $orderSaleType = 'rental';
                }

                foreach ($cart as $item) {
                    $productId = isset($item['id']) && is_numeric($item['id']) ? (int)$item['id'] : 0;
                    if ($productId <= 0) continue;

                    $variationId = $this->normalizeVariationId($item['variation_id'] ?? null);
                    $qty = max(1, (int)($item['qty'] ?? $item['quantity'] ?? 1));
                    $trustedPrice = $this->getTrustedUnitPrice($productId, $variationId, $orderSaleType, $days);

                    $normalized[] = array_merge($item, [
                        'id' => $productId,
                        'variation_id' => $variationId,
                        'qty' => $qty,
                        'quantity' => $qty,
                        'price' => round(max(0, (float)$trustedPrice), 2),
                    ]);
                }

                return $normalized;
            }

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
        $this->ensureRefundTables();
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

        $bookingSourceCol = $this->db->query("SHOW COLUMNS FROM orders LIKE 'booking_source'");
        if (!$bookingSourceCol || !$bookingSourceCol->fetch(\PDO::FETCH_ASSOC)) {
            $this->db->exec("ALTER TABLE orders ADD COLUMN booking_source VARCHAR(20) NULL AFTER customer_type");
        }

        $heardAboutOptionCol = $this->db->query("SHOW COLUMNS FROM orders LIKE 'heard_about_option_id'");
        if (!$heardAboutOptionCol || !$heardAboutOptionCol->fetch(\PDO::FETCH_ASSOC)) {
            $this->db->exec("ALTER TABLE orders ADD COLUMN heard_about_option_id INT NULL AFTER booking_source");
        }

        $heardAboutLabelCol = $this->db->query("SHOW COLUMNS FROM orders LIKE 'heard_about_label'");
        if (!$heardAboutLabelCol || !$heardAboutLabelCol->fetch(\PDO::FETCH_ASSOC)) {
            $this->db->exec("ALTER TABLE orders ADD COLUMN heard_about_label VARCHAR(120) NULL AFTER heard_about_option_id");
        }

        $promoCodeCol = $this->db->query("SHOW COLUMNS FROM orders LIKE 'promo_code'");
        if (!$promoCodeCol || !$promoCodeCol->fetch(\PDO::FETCH_ASSOC)) {
            $this->db->exec("ALTER TABLE orders ADD COLUMN promo_code VARCHAR(32) NULL AFTER booking_source");
        }

        $promoDiscountCol = $this->db->query("SHOW COLUMNS FROM orders LIKE 'promo_discount'");
        if (!$promoDiscountCol || !$promoDiscountCol->fetch(\PDO::FETCH_ASSOC)) {
            $this->db->exec("ALTER TABLE orders ADD COLUMN promo_discount DECIMAL(10,2) NULL AFTER promo_code");
        }

        $promoAdminIdCol = $this->db->query("SHOW COLUMNS FROM orders LIKE 'promo_applied_by_admin_id'");
        if (!$promoAdminIdCol || !$promoAdminIdCol->fetch(\PDO::FETCH_ASSOC)) {
            $this->db->exec("ALTER TABLE orders ADD COLUMN promo_applied_by_admin_id INT NULL AFTER promo_discount");
        }

        $promoAdminRoleCol = $this->db->query("SHOW COLUMNS FROM orders LIKE 'promo_applied_by_admin_role'");
        if (!$promoAdminRoleCol || !$promoAdminRoleCol->fetch(\PDO::FETCH_ASSOC)) {
            $this->db->exec("ALTER TABLE orders ADD COLUMN promo_applied_by_admin_role VARCHAR(32) NULL AFTER promo_applied_by_admin_id");
        }

        $promoAdminNameCol = $this->db->query("SHOW COLUMNS FROM orders LIKE 'promo_applied_by_admin_name'");
        if (!$promoAdminNameCol || !$promoAdminNameCol->fetch(\PDO::FETCH_ASSOC)) {
            $this->db->exec("ALTER TABLE orders ADD COLUMN promo_applied_by_admin_name VARCHAR(120) NULL AFTER promo_applied_by_admin_role");
        }

        $createdByAdminIdCol = $this->db->query("SHOW COLUMNS FROM orders LIKE 'created_by_admin_id'");
        if (!$createdByAdminIdCol || !$createdByAdminIdCol->fetch(\PDO::FETCH_ASSOC)) {
            $this->db->exec("ALTER TABLE orders ADD COLUMN created_by_admin_id INT NULL AFTER promo_applied_by_admin_name");
        }

        $createdByAdminRoleCol = $this->db->query("SHOW COLUMNS FROM orders LIKE 'created_by_admin_role'");
        if (!$createdByAdminRoleCol || !$createdByAdminRoleCol->fetch(\PDO::FETCH_ASSOC)) {
            $this->db->exec("ALTER TABLE orders ADD COLUMN created_by_admin_role VARCHAR(32) NULL AFTER created_by_admin_id");
        }

        $createdByAdminNameCol = $this->db->query("SHOW COLUMNS FROM orders LIKE 'created_by_admin_name'");
        if (!$createdByAdminNameCol || !$createdByAdminNameCol->fetch(\PDO::FETCH_ASSOC)) {
            $this->db->exec("ALTER TABLE orders ADD COLUMN created_by_admin_name VARCHAR(120) NULL AFTER created_by_admin_role");
        }

        $securityDepositCol = $this->db->query("SHOW COLUMNS FROM orders LIKE 'security_deposit'");
        if (!$securityDepositCol || !$securityDepositCol->fetch(\PDO::FETCH_ASSOC)) {
            $this->db->exec("ALTER TABLE orders ADD COLUMN security_deposit DECIMAL(10,2) NULL AFTER total_amount");
        }

        $securityDepositReasonCol = $this->db->query("SHOW COLUMNS FROM orders LIKE 'security_deposit_reason'");
        if (!$securityDepositReasonCol || !$securityDepositReasonCol->fetch(\PDO::FETCH_ASSOC)) {
            $this->db->exec("ALTER TABLE orders ADD COLUMN security_deposit_reason TEXT NULL AFTER security_deposit");
        }

        $securityDepositUpdatedByCol = $this->db->query("SHOW COLUMNS FROM orders LIKE 'security_deposit_updated_by_admin_id'");
        if (!$securityDepositUpdatedByCol || !$securityDepositUpdatedByCol->fetch(\PDO::FETCH_ASSOC)) {
            $this->db->exec("ALTER TABLE orders ADD COLUMN security_deposit_updated_by_admin_id INT NULL AFTER security_deposit_reason");
        }

        $securityDepositUpdatedAtCol = $this->db->query("SHOW COLUMNS FROM orders LIKE 'security_deposit_updated_at'");
        if (!$securityDepositUpdatedAtCol || !$securityDepositUpdatedAtCol->fetch(\PDO::FETCH_ASSOC)) {
            $this->db->exec("ALTER TABLE orders ADD COLUMN security_deposit_updated_at DATETIME NULL AFTER security_deposit_updated_by_admin_id");
        }

        $paymentProviderCol = $this->db->query("SHOW COLUMNS FROM orders LIKE 'payment_provider'");
        if (!$paymentProviderCol || !$paymentProviderCol->fetch(\PDO::FETCH_ASSOC)) {
            $this->db->exec("ALTER TABLE orders ADD COLUMN payment_provider VARCHAR(20) NULL AFTER payment_method");
        }

        $stripeIntentCol = $this->db->query("SHOW COLUMNS FROM orders LIKE 'provider_payment_intent_id'");
        if (!$stripeIntentCol || !$stripeIntentCol->fetch(\PDO::FETCH_ASSOC)) {
            $this->db->exec("ALTER TABLE orders ADD COLUMN provider_payment_intent_id VARCHAR(80) NULL AFTER payment_provider");
        }

        $stripeChargeCol = $this->db->query("SHOW COLUMNS FROM orders LIKE 'provider_charge_id'");
        if (!$stripeChargeCol || !$stripeChargeCol->fetch(\PDO::FETCH_ASSOC)) {
            $this->db->exec("ALTER TABLE orders ADD COLUMN provider_charge_id VARCHAR(80) NULL AFTER provider_payment_intent_id");
        }

        $paypalOrderCol = $this->db->query("SHOW COLUMNS FROM orders LIKE 'provider_paypal_order_id'");
        if (!$paypalOrderCol || !$paypalOrderCol->fetch(\PDO::FETCH_ASSOC)) {
            $this->db->exec("ALTER TABLE orders ADD COLUMN provider_paypal_order_id VARCHAR(80) NULL AFTER provider_charge_id");
        }

        $paypalCaptureCol = $this->db->query("SHOW COLUMNS FROM orders LIKE 'provider_paypal_capture_id'");
        if (!$paypalCaptureCol || !$paypalCaptureCol->fetch(\PDO::FETCH_ASSOC)) {
            $this->db->exec("ALTER TABLE orders ADD COLUMN provider_paypal_capture_id VARCHAR(80) NULL AFTER provider_paypal_order_id");
        }

        $depositRefundedCol = $this->db->query("SHOW COLUMNS FROM orders LIKE 'security_deposit_refunded_amount'");
        if (!$depositRefundedCol || !$depositRefundedCol->fetch(\PDO::FETCH_ASSOC)) {
            $this->db->exec("ALTER TABLE orders ADD COLUMN security_deposit_refunded_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER security_deposit_updated_at");
        }

        $lastRefundAtCol = $this->db->query("SHOW COLUMNS FROM orders LIKE 'last_security_deposit_refund_at'");
        if (!$lastRefundAtCol || !$lastRefundAtCol->fetch(\PDO::FETCH_ASSOC)) {
            $this->db->exec("ALTER TABLE orders ADD COLUMN last_security_deposit_refund_at DATETIME NULL AFTER security_deposit_refunded_amount");
        }
    }

    private function resolveHeardAboutSelection(array $form): array
    {
        $optionIdRaw = trim((string)($form['heard_about_option_id'] ?? ''));
        $otherTextRaw = trim((string)($form['heard_about_other_text'] ?? ''));
        $optionId = (is_numeric($optionIdRaw) && (int)$optionIdRaw > 0) ? (int)$optionIdRaw : null;
        $label = null;

        if ($optionId !== null) {
            try {
                $stmt = $this->db->prepare("SELECT label FROM heard_about_options WHERE id = ? LIMIT 1");
                $stmt->execute([$optionId]);
                $foundLabel = $stmt->fetchColumn();
                if (is_string($foundLabel) && trim($foundLabel) !== '') {
                    $label = trim($foundLabel);
                }
            } catch (\Throwable $e) {
                $label = null;
            }
        }

        if ($label === null && $optionIdRaw === '-1') {
            $label = $otherTextRaw !== '' ? $otherTextRaw : 'Other';
        }

        return [
            'id' => $optionId,
            'label' => $label,
        ];
    }

    private function ensureRefundTables(): void
    {
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS order_refunds (
                refund_id INT AUTO_INCREMENT PRIMARY KEY,
                order_id INT NOT NULL,
                payment_provider VARCHAR(20) NOT NULL,
                refund_method VARCHAR(30) NULL,
                requested_amount DECIMAL(10,2) NOT NULL,
                approved_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                reason TEXT NOT NULL,
                admin_id INT NULL,
                provider_refund_id VARCHAR(100) NULL,
                provider_transaction_reference VARCHAR(100) NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'pending',
                provider_response_snapshot LONGTEXT NULL,
                idempotency_key VARCHAR(120) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_order_refunds_order_id (order_id),
                INDEX idx_order_refunds_provider_refund (provider_refund_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $requiredRefundColumns = [
            'refund_method' => "ALTER TABLE order_refunds ADD COLUMN refund_method VARCHAR(30) NULL AFTER payment_provider",
            'requested_amount' => "ALTER TABLE order_refunds ADD COLUMN requested_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER refund_method",
            'approved_amount' => "ALTER TABLE order_refunds ADD COLUMN approved_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER requested_amount",
            'provider_transaction_reference' => "ALTER TABLE order_refunds ADD COLUMN provider_transaction_reference VARCHAR(100) NULL AFTER provider_refund_id",
            'status' => "ALTER TABLE order_refunds ADD COLUMN status VARCHAR(30) NOT NULL DEFAULT 'pending' AFTER provider_transaction_reference",
            'provider_response_snapshot' => "ALTER TABLE order_refunds ADD COLUMN provider_response_snapshot LONGTEXT NULL AFTER status",
            'idempotency_key' => "ALTER TABLE order_refunds ADD COLUMN idempotency_key VARCHAR(120) NULL AFTER provider_response_snapshot",
            'updated_at' => "ALTER TABLE order_refunds ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
        ];

        foreach ($requiredRefundColumns as $columnName => $alterSql) {
            $colCheck = $this->db->query("SHOW COLUMNS FROM order_refunds LIKE '" . $columnName . "'");
            if (!$colCheck || !$colCheck->fetch(\PDO::FETCH_ASSOC)) {
                $this->db->exec($alterSql);
            }
        }
    }

    public function saveOrderPaymentProviderReferences(int $orderId, array $refs): bool
    {
        if ($orderId <= 0) {
            return false;
        }

        $provider = strtolower(trim((string)($refs['payment_provider'] ?? '')));
        if ($provider === '') {
            $provider = strtolower(trim((string)($refs['provider'] ?? '')));
        }
        if (!in_array($provider, ['stripe', 'paypal'], true)) {
            return false;
        }

        $stmt = $this->db->prepare(
            "UPDATE orders
             SET payment_provider = ?,
                 provider_payment_intent_id = ?,
                 provider_charge_id = ?,
                 provider_paypal_order_id = ?,
                 provider_paypal_capture_id = ?
             WHERE order_id = ?"
        );

        return $stmt->execute([
            $provider,
            $refs['provider_payment_intent_id'] ?? null,
            $refs['provider_charge_id'] ?? null,
            $refs['provider_paypal_order_id'] ?? null,
            $refs['provider_paypal_capture_id'] ?? null,
            $orderId,
        ]);
    }

    public function refundSecurityDeposit(int $orderId, float $amount, string $reason, ?int $adminId = null, ?string $refundMethod = null): array
    {
        if ($orderId <= 0) {
            return ['success' => false, 'error' => 'Invalid order ID'];
        }

        $requestedAmount = round($amount, 2);
        if ($requestedAmount <= 0) {
            return ['success' => false, 'error' => 'Refund amount must be greater than zero'];
        }

        $reasonText = trim($reason);
        if ($reasonText === '' || strlen($reasonText) < 5) {
            return ['success' => false, 'error' => 'Please provide a refund reason (at least 5 characters).'];
        }

        $orderStmt = $this->db->prepare("SELECT * FROM orders WHERE order_id = ? LIMIT 1");
        $orderStmt->execute([$orderId]);
        $order = $orderStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$order) {
            return ['success' => false, 'error' => 'Order not found'];
        }

        $provider = strtolower(trim((string)($order['payment_provider'] ?? '')));
        if ($provider === '') {
            $method = strtolower(trim((string)($order['payment_method'] ?? '')));
            if ($method === 'card') {
                $provider = 'stripe';
            } elseif ($method === 'paypal') {
                $provider = 'paypal';
            }
        }

        $bookingSource = strtolower(trim((string)($order['booking_source'] ?? '')));
        $resolvedRefundMethod = strtolower(trim((string)($refundMethod ?? '')));
        if ($resolvedRefundMethod === '' && $bookingSource === 'walk-in') {
            $walkInPaymentMethod = strtolower(trim((string)($order['payment_method'] ?? '')));
            if ($walkInPaymentMethod === 'card') {
                $resolvedRefundMethod = 'card-terminal';
            } elseif ($walkInPaymentMethod === 'cash') {
                $resolvedRefundMethod = 'cash';
            }
        }

        $isManualRefund =
            $bookingSource === 'walk-in' ||
            $resolvedRefundMethod === 'cash' ||
            !in_array($provider, ['stripe', 'paypal'], true);

        if ($isManualRefund) {
            if (!in_array($resolvedRefundMethod, ['cash', 'card-terminal', 'manual'], true)) {
                $resolvedRefundMethod = 'manual';
            }
            $provider = 'manual';
        } elseif (!in_array($provider, ['stripe', 'paypal'], true)) {
            return ['success' => false, 'error' => 'This order does not support automated provider refunds.'];
        } else {
            if ($resolvedRefundMethod === '' || $resolvedRefundMethod === 'provider') {
                $resolvedRefundMethod = 'provider';
            }
        }

        $depositCharged = max(0, round((float)($order['security_deposit'] ?? 0), 2));
        $alreadyRefunded = max(0, round((float)($order['security_deposit_refunded_amount'] ?? 0), 2));
        $remaining = round(max(0, $depositCharged - $alreadyRefunded), 2);
        if ($requestedAmount > $remaining) {
            return ['success' => false, 'error' => 'Refund amount exceeds refundable deposit balance.'];
        }

        $adminId = $adminId !== null ? (int)$adminId : null;
        if ($adminId !== null && $adminId <= 0) {
            $adminId = null;
        }

        $idempotencyKey = 'dep_refund_' . $orderId . '_' . str_replace('.', '', (string)$requestedAmount) . '_' . time();
        $providerResult = [];
        if ($provider === 'stripe') {
            $providerResult = $this->issueStripeSecurityDepositRefund($order, $requestedAmount, $reasonText, $idempotencyKey);
            if (!($providerResult['success'] ?? false)) {
                return [
                    'success' => false,
                    'error' => (string)($providerResult['error'] ?? 'Refund failed'),
                ];
            }
        } elseif ($provider === 'paypal') {
            $providerResult = $this->issuePaypalSecurityDepositRefund($order, $requestedAmount, $reasonText, $idempotencyKey);
            if (!($providerResult['success'] ?? false)) {
                return [
                    'success' => false,
                    'error' => (string)($providerResult['error'] ?? 'Refund failed'),
                ];
            }
        } else {
            $providerResult = [
                'success' => true,
                'approved_amount' => $requestedAmount,
                'status' => 'manual_recorded',
                'provider_refund_id' => null,
                'provider_transaction_reference' => null,
                'raw_response' => [
                    'mode' => 'manual',
                    'refund_method' => $resolvedRefundMethod,
                    'booking_source' => $bookingSource,
                ],
            ];
        }

        $approvedAmount = round((float)($providerResult['approved_amount'] ?? $requestedAmount), 2);
        $status = (string)($providerResult['status'] ?? 'succeeded');
        $providerRefundId = (string)($providerResult['provider_refund_id'] ?? '');
        $providerTxnRef = (string)($providerResult['provider_transaction_reference'] ?? '');
        $snapshot = json_encode($providerResult['raw_response'] ?? [], JSON_UNESCAPED_SLASHES);

        $this->db->beginTransaction();
        try {
            $this->insertOrderRefundRecord([
                'order_id' => $orderId,
                'payment_provider' => $provider,
                'refund_method' => $resolvedRefundMethod !== '' ? $resolvedRefundMethod : null,
                'requested_amount' => $requestedAmount,
                'approved_amount' => $approvedAmount,
                'reason' => $reasonText,
                'admin_id' => $adminId,
                'provider_refund_id' => $providerRefundId !== '' ? $providerRefundId : null,
                'provider_transaction_reference' => $providerTxnRef !== '' ? $providerTxnRef : null,
                'status' => $status,
                'provider_response_snapshot' => $snapshot !== false ? $snapshot : null,
                'idempotency_key' => $idempotencyKey,
            ]);

            $updatedRefunded = round($alreadyRefunded + $approvedAmount, 2);
            $updateOrderStmt = $this->db->prepare(
                "UPDATE orders
                 SET security_deposit_refunded_amount = ?, last_security_deposit_refund_at = NOW()
                 WHERE order_id = ?"
            );
            $updateOrderStmt->execute([$updatedRefunded, $orderId]);

            $this->db->commit();

            return [
                'success' => true,
                'order_id' => $orderId,
                'requested_amount' => $requestedAmount,
                'approved_amount' => $approvedAmount,
                'status' => $status,
                'provider_refund_id' => $providerRefundId,
                'security_deposit_refunded_amount' => $updatedRefunded,
                'security_deposit_refundable_remaining' => round(max(0, $depositCharged - $updatedRefunded), 2),
            ];
        } catch (\Throwable $e) {
            $this->db->rollBack();

            if ($provider === 'stripe' && !empty($providerResult['raw_response']) && is_array($providerResult['raw_response'])) {
                $stripeRefundPayload = $providerResult['raw_response'];
                $stripeRefundPayload['id'] = $providerRefundId !== '' ? $providerRefundId : ($stripeRefundPayload['id'] ?? '');
                $stripeRefundPayload['status'] = $status;
                $stripeRefundPayload['amount'] = (int)round($approvedAmount * 100);
                $stripeRefundPayload['payment_intent'] = trim((string)($stripeRefundPayload['payment_intent'] ?? ($order['provider_payment_intent_id'] ?? '')));
                if (empty($stripeRefundPayload['payment_intent']) && !empty($order['provider_payment_intent_id'])) {
                    $stripeRefundPayload['payment_intent'] = (string)$order['provider_payment_intent_id'];
                }
                $stripeRefundPayload['charge'] = trim((string)($stripeRefundPayload['charge'] ?? ($order['provider_charge_id'] ?? '')));
                if (!isset($stripeRefundPayload['metadata']) || !is_array($stripeRefundPayload['metadata'])) {
                    $stripeRefundPayload['metadata'] = [];
                }
                $stripeRefundPayload['metadata']['order_id'] = (string)$orderId;
                $stripeRefundPayload['reason'] = $reasonText;

                $syncResult = $this->syncStripeRefundFromWebhook($stripeRefundPayload);
                if (($syncResult['success'] ?? false) === true) {
                    return [
                        'success' => true,
                        'order_id' => (int)($syncResult['order_id'] ?? $orderId),
                        'requested_amount' => $requestedAmount,
                        'approved_amount' => $approvedAmount,
                        'status' => $status,
                        'provider_refund_id' => $providerRefundId,
                        'security_deposit_refunded_amount' => $approvedAmount,
                        'security_deposit_refundable_remaining' => round(max(0, $depositCharged - $approvedAmount), 2),
                        'reconciled' => true,
                    ];
                }

                return [
                    'success' => false,
                    'error' => 'Refund was processed but local tracking failed: ' . (string)($syncResult['error'] ?? $e->getMessage()),
                ];
            }

            return ['success' => false, 'error' => 'Refund was processed but local tracking failed: ' . $e->getMessage()];
        }
    }

    private function issueStripeSecurityDepositRefund(array $order, float $amount, string $reason, string $idempotencyKey): array
    {
        $intentId = trim((string)($order['provider_payment_intent_id'] ?? ''));
        if ($intentId === '') {
            return ['success' => false, 'error' => 'Missing Stripe payment intent reference for this order.'];
        }

        $stripeSecret = $_ENV['STRIPE_SECRET_KEY'] ?? null;
        if (!$stripeSecret) {
            return ['success' => false, 'error' => 'Stripe secret not configured.'];
        }

        \Stripe\Stripe::setApiKey($stripeSecret);

        try {
            $refund = \Stripe\Refund::create([
                'payment_intent' => $intentId,
                'amount' => (int)round($amount * 100),
                'reason' => 'requested_by_customer',
                'metadata' => [
                    'order_id' => (string)($order['order_id'] ?? ''),
                    'security_deposit_refund_reason' => $reason,
                ],
            ], [
                'idempotency_key' => $idempotencyKey,
            ]);

            $status = strtolower((string)($refund->status ?? 'succeeded'));
            $approvedAmount = isset($refund->amount) ? ((float)$refund->amount / 100) : $amount;
            return [
                'success' => true,
                'approved_amount' => round($approvedAmount, 2),
                'status' => $status,
                'provider_refund_id' => (string)($refund->id ?? ''),
                'provider_transaction_reference' => $intentId,
                'raw_response' => method_exists($refund, 'toArray') ? $refund->toArray() : (array)$refund,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Stripe refund error: ' . $e->getMessage()];
        }
    }

    private function issuePaypalSecurityDepositRefund(array $order, float $amount, string $reason, string $idempotencyKey): array
    {
        $captureId = trim((string)($order['provider_paypal_capture_id'] ?? ''));
        if ($captureId === '') {
            return ['success' => false, 'error' => 'Missing PayPal capture reference for this order.'];
        }

        $clientId = getenv('PAYPAL_CLIENT_ID') ?: ($_ENV['PAYPAL_CLIENT_ID'] ?? '');
        $clientSecret = getenv('PAYPAL_CLIENT_SECRET') ?: ($_ENV['PAYPAL_CLIENT_SECRET'] ?? '');
        if ($clientId === '' || $clientSecret === '') {
            return ['success' => false, 'error' => 'PayPal credentials are not configured.'];
        }

        $baseUrl = 'https://api-m.sandbox.paypal.com';

        $ch = curl_init($baseUrl . '/v1/oauth2/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $clientId . ':' . $clientSecret,
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 30,
        ]);
        $authBody = curl_exec($ch);
        $authHttp = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $authErr = curl_error($ch);
        curl_close($ch);

        if ($authErr !== '') {
            return ['success' => false, 'error' => 'PayPal auth request failed: ' . $authErr];
        }

        $authData = json_decode((string)$authBody, true);
        $accessToken = is_array($authData) ? (string)($authData['access_token'] ?? '') : '';
        if ($authHttp < 200 || $authHttp >= 300 || $accessToken === '') {
            return ['success' => false, 'error' => 'PayPal auth failed.'];
        }

        $currency = strtoupper((string)($order['currency_code'] ?? 'USD'));
        if ($currency === '') {
            $currency = 'USD';
        }

        $payload = [
            'amount' => [
                'value' => number_format($amount, 2, '.', ''),
                'currency_code' => $currency,
            ],
            'note_to_payer' => $reason,
        ];

        $ch = curl_init($baseUrl . '/v2/payments/captures/' . rawurlencode($captureId) . '/refund');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken,
                'PayPal-Request-Id: ' . $idempotencyKey,
            ],
            CURLOPT_TIMEOUT => 30,
        ]);
        $refundBody = curl_exec($ch);
        $refundHttp = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $refundErr = curl_error($ch);
        curl_close($ch);

        if ($refundErr !== '') {
            return ['success' => false, 'error' => 'PayPal refund request failed: ' . $refundErr];
        }

        $refundData = json_decode((string)$refundBody, true);
        if ($refundHttp < 200 || $refundHttp >= 300 || !is_array($refundData)) {
            return ['success' => false, 'error' => 'PayPal refund rejected.'];
        }

        $status = strtolower((string)($refundData['status'] ?? 'completed'));
        $approvedAmount = isset($refundData['amount']['value']) ? (float)$refundData['amount']['value'] : $amount;
        return [
            'success' => in_array($status, ['completed', 'pending'], true),
            'approved_amount' => round($approvedAmount, 2),
            'status' => $status,
            'provider_refund_id' => (string)($refundData['id'] ?? ''),
            'provider_transaction_reference' => $captureId,
            'raw_response' => $refundData,
        ];
    }

    public function syncStripeRefundFromWebhook(array $refundPayload): array
    {
        $refundId = trim((string)($refundPayload['id'] ?? ''));
        if ($refundId === '') {
            return ['success' => false, 'error' => 'Missing Stripe refund id'];
        }

        $stripeStatus = strtolower(trim((string)($refundPayload['status'] ?? 'pending')));
        $amountCents = isset($refundPayload['amount']) ? (int)$refundPayload['amount'] : 0;
        $approvedAmount = round(max(0, $amountCents) / 100, 2);
        $paymentIntentId = trim((string)($refundPayload['payment_intent'] ?? ''));
        $chargeId = trim((string)($refundPayload['charge'] ?? ''));
        $metadataOrderId = isset($refundPayload['metadata']['order_id']) ? (int)$refundPayload['metadata']['order_id'] : 0;
        $reason = trim((string)($refundPayload['reason'] ?? ''));
        if ($reason === '') {
            $reason = 'Stripe dashboard/provider refund sync';
        }

        $resolvedPaymentIntentId = $paymentIntentId;
        if ($resolvedPaymentIntentId === '' && $chargeId !== '') {
            try {
                $stripeSecret = (string)(getenv('STRIPE_SECRET_KEY') ?: getenv('STRIPE_SECRET') ?: '');
                if ($stripeSecret !== '') {
                    \Stripe\Stripe::setApiKey($stripeSecret);
                    $charge = \Stripe\Charge::retrieve($chargeId);
                    if (is_object($charge) && isset($charge->payment_intent) && $charge->payment_intent) {
                        $resolvedPaymentIntentId = trim((string)$charge->payment_intent);
                    }
                }
            } catch (\Throwable $e) {
                error_log('Stripe refund webhook charge lookup warning: ' . $e->getMessage());
            }
        }

        $snapshot = json_encode($refundPayload, JSON_UNESCAPED_SLASHES);

        $this->db->beginTransaction();
        try {
            $existingStmt = $this->db->prepare(
                "SELECT refund_id, order_id, approved_amount
                 FROM order_refunds
                 WHERE provider_refund_id = ? AND payment_provider = 'stripe'
                 LIMIT 1"
            );
            $existingStmt->execute([$refundId]);
            $existing = $existingStmt->fetch(\PDO::FETCH_ASSOC) ?: null;

            if ($existing) {
                $existingApproved = round((float)($existing['approved_amount'] ?? 0), 2);
                $delta = round($approvedAmount - $existingApproved, 2);

                $updateRefundStmt = $this->db->prepare(
                    "UPDATE order_refunds
                     SET approved_amount = ?,
                         requested_amount = ?,
                         status = ?,
                         provider_transaction_reference = ?,
                         provider_response_snapshot = ?,
                         reason = ?
                     WHERE refund_id = ?"
                );
                $updateRefundStmt->execute([
                    $approvedAmount,
                    $approvedAmount,
                    $stripeStatus !== '' ? $stripeStatus : 'pending',
                    $chargeId !== '' ? $chargeId : null,
                    $snapshot !== false ? $snapshot : null,
                    $reason,
                    (int)$existing['refund_id'],
                ]);

                if ($delta !== 0.0) {
                    $orderUpdateStmt = $this->db->prepare(
                        "UPDATE orders
                         SET security_deposit_refunded_amount = GREATEST(0, COALESCE(security_deposit_refunded_amount, 0) + ?),
                             last_security_deposit_refund_at = NOW()
                         WHERE order_id = ?"
                    );
                    $orderUpdateStmt->execute([$delta, (int)$existing['order_id']]);
                }

                $this->db->commit();
                return [
                    'success' => true,
                    'order_id' => (int)$existing['order_id'],
                    'refund_id' => (int)$existing['refund_id'],
                    'updated' => true,
                ];
            }

            $orderStmt = $this->db->prepare(
                "SELECT order_id
                 FROM orders
                 WHERE payment_provider = 'stripe'
                   AND (
                        (provider_payment_intent_id IS NOT NULL AND provider_payment_intent_id = ?)
                        OR (provider_charge_id IS NOT NULL AND provider_charge_id = ?)
                   )
                 ORDER BY order_id DESC
                 LIMIT 1"
            );
            $orderStmt->execute([$resolvedPaymentIntentId, $chargeId]);
            $orderId = (int)$orderStmt->fetchColumn();

            if ($orderId <= 0 && $metadataOrderId > 0) {
                $metaOrderStmt = $this->db->prepare("SELECT order_id FROM orders WHERE order_id = ? LIMIT 1");
                $metaOrderStmt->execute([$metadataOrderId]);
                $orderId = (int)$metaOrderStmt->fetchColumn();
            }

            if ($orderId <= 0 && $chargeId !== '' && $resolvedPaymentIntentId === '') {
                $this->db->rollBack();
                return ['success' => false, 'error' => 'Unable to resolve Stripe refund to a local order'];
            }

            if ($orderId <= 0) {
                $this->db->rollBack();
                return ['success' => false, 'error' => 'No matching order for Stripe refund'];
            }

            $this->insertOrderRefundRecord([
                'order_id' => $orderId,
                'payment_provider' => 'stripe',
                'refund_method' => 'provider',
                'requested_amount' => $approvedAmount,
                'approved_amount' => $approvedAmount,
                'reason' => $reason,
                'admin_id' => null,
                'provider_refund_id' => $refundId,
                'provider_transaction_reference' => $chargeId !== '' ? $chargeId : null,
                'status' => $stripeStatus !== '' ? $stripeStatus : 'pending',
                'provider_response_snapshot' => $snapshot !== false ? $snapshot : null,
                'idempotency_key' => null,
            ]);

            if ($approvedAmount > 0) {
                $orderUpdateStmt = $this->db->prepare(
                    "UPDATE orders
                     SET security_deposit_refunded_amount = COALESCE(security_deposit_refunded_amount, 0) + ?,
                         last_security_deposit_refund_at = NOW()
                     WHERE order_id = ?"
                );
                $orderUpdateStmt->execute([$approvedAmount, $orderId]);
            }

            $this->db->commit();
            return [
                'success' => true,
                'order_id' => $orderId,
                'created' => true,
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getOrderRefundData(int $orderId): array
    {
        $orderStmt = $this->db->prepare(
            "SELECT order_id, security_deposit, security_deposit_refunded_amount, payment_provider, provider_payment_intent_id, provider_paypal_capture_id
             FROM orders WHERE order_id = ? LIMIT 1"
        );
        $orderStmt->execute([$orderId]);
        $order = $orderStmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        $depositCharged = max(0, round((float)($order['security_deposit'] ?? 0), 2));
        $refunded = max(0, round((float)($order['security_deposit_refunded_amount'] ?? 0), 2));
        $remaining = round(max(0, $depositCharged - $refunded), 2);

                $refundSelectMethod = $this->hasOrderRefundMethodColumn()
                    ? 'refund_method'
                    : 'NULL AS refund_method';
                $refundStmt = $this->db->prepare(
                    "SELECT refund_id, payment_provider, {$refundSelectMethod}, requested_amount, approved_amount, reason, admin_id, provider_refund_id, status, created_at
                     FROM order_refunds WHERE order_id = ? ORDER BY refund_id DESC"
                );
        $refundStmt->execute([$orderId]);

        return [
            'summary' => [
                'payment_provider' => (string)($order['payment_provider'] ?? ''),
                'deposit_charged' => $depositCharged,
                'refunded_total' => $refunded,
                'refundable_remaining' => $remaining,
                'can_refund' => $remaining > 0,
            ],
            'refunds' => $refundStmt->fetchAll(\PDO::FETCH_ASSOC),
        ];
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
            $productName = $this->sanitizeProductNameForStorage($item['name'] ?? '');
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
        $cart = $this->normalizeCartForTrustedPricing(
            is_array($cart) ? $cart : [],
            $orderData['pickup_datetime'] ?? null,
            $orderData['return_datetime'] ?? null,
            $orderData['sale_type'] ?? 'rental'
        );

        $trustedSubtotal = 0.0;
        foreach ($cart as $item) {
            $trustedSubtotal += ((float)($item['price'] ?? 0)) * max(1, (int)($item['qty'] ?? $item['quantity'] ?? 1));
        }
        $trustedSubtotal = round($trustedSubtotal, 2);

        $promoDiscount = max(0, (float)($orderData['promo_discount'] ?? 0));
        if (empty($orderData['promo_code'])) {
            $promoDiscount = 0.0;
        }
        $promoDiscount = round(min($promoDiscount, $trustedSubtotal), 2);

        $orderData['promo_discount'] = $promoDiscount > 0 ? $promoDiscount : null;
        $productTotalWithTax = round(max(0, $trustedSubtotal - $promoDiscount), 2);
        $orderData['total_amount'] = round($productTotalWithTax + self::SECURITY_DEPOSIT, 2);
        $orderData['security_deposit'] = isset($orderData['security_deposit'])
            ? round(max(0, (float)$orderData['security_deposit']), 2)
            : self::SECURITY_DEPOSIT;

        $sql = "INSERT INTO orders (
            user_id, guest_first_name, guest_last_name, guest_email, guest_phone,
            client_weight_option, client_weight_lbs,
            address1, address2, state, zip,
            pickup_datetime, return_datetime, delivery_type, hotel_id, pickup_location,
            notes, payment_method, payment_provider, total_amount, security_deposit, status, customer_type, booking_source,
            promo_code, promo_discount, promo_applied_by_admin_id, promo_applied_by_admin_role, promo_applied_by_admin_name,
            created_by_admin_id, created_by_admin_role, created_by_admin_name,
            sale_type
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
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
            strtolower((string)($orderData['payment_method'] ?? '')) === 'paypal' ? 'paypal' : (strtolower((string)($orderData['payment_method'] ?? '')) === 'card' ? 'stripe' : null),
            $orderData['total_amount'],
            $orderData['security_deposit'],
            $orderData['customer_type'],
            $orderData['booking_source'] ?? null,
            $orderData['promo_code'] ?? null,
            $orderData['promo_discount'] ?? null,
            $orderData['promo_applied_by_admin_id'] ?? null,
            $orderData['promo_applied_by_admin_role'] ?? null,
            $orderData['promo_applied_by_admin_name'] ?? null,
            $orderData['created_by_admin_id'] ?? null,
            $orderData['created_by_admin_role'] ?? null,
            $orderData['created_by_admin_name'] ?? null,
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
                'product_name' => $this->sanitizeProductNameForStorage($item['name'] ?? ''),
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
        $stmt = $this->db->prepare(
            "SELECT
                o.*,
                COALESCE(NULLIF(TRIM(o.heard_about_label), ''), NULLIF(TRIM(hao.label), ''), 'Not specified') AS heard_about_display,
                ROUND(GREATEST(0, COALESCE(o.total_amount, 0) - COALESCE(o.security_deposit, 0)), 2) AS sales_amount,
                COALESCE(refunds.refund_amount, 0) AS refund_amount
             FROM orders o
             LEFT JOIN heard_about_options hao ON hao.id = o.heard_about_option_id
             LEFT JOIN (
                SELECT order_id, ROUND(SUM(approved_amount), 2) AS refund_amount
                                        FROM order_refunds
                                        WHERE approved_amount > 0
                                            AND LOWER(COALESCE(status, '')) IN ('pending', 'succeeded', 'completed', 'manual_recorded')
                  AND LOWER(COALESCE(status, '')) IN ('pending', 'succeeded', 'completed')
                GROUP BY order_id
             ) refunds ON refunds.order_id = o.order_id
             ORDER BY o.order_id DESC
             LIMIT :limit OFFSET :offset"
        );
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
                $sql = "SELECT
                                        o.*,
                                        ROUND(GREATEST(0, COALESCE(o.total_amount, 0) - COALESCE(o.security_deposit, 0)), 2) AS sales_amount,
                                        COALESCE(refunds.refund_amount, 0) AS refund_amount
                                FROM orders o
                                LEFT JOIN (
                                        SELECT order_id, ROUND(SUM(approved_amount), 2) AS refund_amount
                                        FROM order_refunds
                                        WHERE approved_amount > 0
                                            AND LOWER(COALESCE(status, '')) IN ('pending', 'succeeded', 'completed', 'manual_recorded')
                                            AND LOWER(COALESCE(status, '')) IN ('pending', 'succeeded', 'completed')
                                        GROUP BY order_id
                                ) refunds ON refunds.order_id = o.order_id
                                WHERE CAST(o.order_id AS CHAR) LIKE ?
                                ORDER BY o.order_id DESC
                                LIMIT ? OFFSET ?";
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
            $where[] = 'CAST(o.order_id AS CHAR) LIKE ?';
            $params[] = '%' . $searchTerm . '%';
        }

        $status = strtolower(trim((string)($filters['status'] ?? '')));
        if ($status !== '') {
            $where[] = 'LOWER(o.status) = ?';
            $params[] = $status;
        }

        $customerType = strtolower(trim((string)($filters['customer_type'] ?? '')));
        if ($customerType !== '') {
            $where[] = 'LOWER(o.customer_type) = ?';
            $params[] = $customerType;
        }

        $saleType = strtolower(trim((string)($filters['sale_type'] ?? '')));
        if ($saleType !== '') {
            $where[] = 'LOWER(o.sale_type) = ?';
            $params[] = $saleType;
        }

        $bookingSource = strtolower(trim((string)($filters['booking_source'] ?? '')));
        if ($bookingSource === 'walk-in') {
            $where[] = "(LOWER(COALESCE(o.booking_source, '')) = 'walk-in' OR LOWER(COALESCE(o.pickup_location, '')) = 'walk-in booking')";
        } elseif ($bookingSource === 'online') {
            $where[] = "(LOWER(COALESCE(o.booking_source, 'online')) = 'online' AND LOWER(COALESCE(o.pickup_location, '')) <> 'walk-in booking')";
        }

        $heardAboutExpr = "COALESCE(NULLIF(TRIM(o.heard_about_label), ''), NULLIF(TRIM(hao.label), ''), 'Not specified')";
        $heardAbout = trim((string)($filters['heard_about'] ?? ''));
        if ($heardAbout !== '') {
            if ($heardAbout === 'others') {
                $where[] = "o.heard_about_option_id IS NULL AND TRIM(COALESCE(o.heard_about_label, '')) <> ''";
            } else {
                $where[] = $heardAboutExpr . ' = ?';
                $params[] = $heardAbout;
            }
        }

        $promoUsage = strtolower(trim((string)($filters['promo_usage'] ?? '')));
        if ($promoUsage === 'with') {
            $where[] = "(o.promo_code IS NOT NULL AND o.promo_code <> '')";
        } elseif ($promoUsage === 'without') {
            $where[] = "(o.promo_code IS NULL OR o.promo_code = '')";
        }

        $creatorRole = strtolower(trim((string)($filters['creator_role'] ?? '')));
        if ($creatorRole !== '') {
            $where[] = "LOWER(COALESCE(o.created_by_admin_role, '')) = ?";
            $params[] = $creatorRole;
        }

        $quickPeriod = strtolower(trim((string)($filters['quick_period'] ?? '')));
        if (in_array($quickPeriod, ['late', 'today', 'upcoming'], true)) {
            // Quick period filters always target ongoing incomplete orders.
            $where[] = "LOWER(COALESCE(o.status, '')) IN ('pending', 'approved', 'paid')";

            if ($quickPeriod === 'late') {
                $where[] = 'DATE(o.return_datetime) < CURDATE()';
            } elseif ($quickPeriod === 'today') {
                $where[] = 'DATE(o.pickup_datetime) <= CURDATE() AND DATE(o.return_datetime) >= CURDATE()';
            } elseif ($quickPeriod === 'upcoming') {
                $where[] = 'DATE(o.pickup_datetime) > CURDATE()';
            }
        }

        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        $dateTo = trim((string)($filters['date_to'] ?? ''));
        if ($dateFrom !== '' && $dateTo !== '') {
            // Match orders whose rental period overlaps the selected date window.
            $where[] = '(DATE(o.pickup_datetime) <= ? AND DATE(o.return_datetime) >= ?)';
            $params[] = $dateTo;
            $params[] = $dateFrom;
        } elseif ($dateFrom !== '') {
            $where[] = 'DATE(o.return_datetime) >= ?';
            $params[] = $dateFrom;
        } elseif ($dateTo !== '') {
            $where[] = 'DATE(o.pickup_datetime) <= ?';
            $params[] = $dateTo;
        }

        $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

        $sortExpressions = [
            'order_id' => 'o.order_id',
            'sale_type' => 'o.sale_type',
            'total_amount' => 'o.total_amount',
            'sales_amount' => '(COALESCE(o.total_amount, 0) - COALESCE(o.security_deposit, 0))',
            'refund_amount' => 'COALESCE(refunds.refund_amount, 0)',
            'status' => 'o.status',
            'order_date' => 'o.order_date',
            'pickup_datetime' => 'o.pickup_datetime',
            'return_datetime' => 'o.return_datetime',
        ];
        $sortBy = $filters['sort_by'] ?? 'order_id';
        if (!array_key_exists($sortBy, $sortExpressions)) {
            $sortBy = 'order_id';
        }
        $sortColumnSql = $sortExpressions[$sortBy];

        $sortDir = strtolower((string)($filters['sort_dir'] ?? 'desc'));
        $sortDir = $sortDir === 'asc' ? 'ASC' : 'DESC';

                $sql =
                        "SELECT
                                o.*,
                                    COALESCE(NULLIF(TRIM(o.heard_about_label), ''), NULLIF(TRIM(hao.label), ''), 'Not specified') AS heard_about_display,
                                ROUND(GREATEST(0, COALESCE(o.total_amount, 0) - COALESCE(o.security_deposit, 0)), 2) AS sales_amount,
                                COALESCE(refunds.refund_amount, 0) AS refund_amount
                         FROM orders o
                                LEFT JOIN heard_about_options hao ON hao.id = o.heard_about_option_id
                         LEFT JOIN (
                                SELECT order_id, ROUND(SUM(approved_amount), 2) AS refund_amount
                                FROM order_refunds
                                WHERE approved_amount > 0
                                    AND LOWER(COALESCE(status, '')) IN ('pending', 'succeeded', 'completed')
                                GROUP BY order_id
                         ) refunds ON refunds.order_id = o.order_id" .
                        $whereSql .
                        " ORDER BY {$sortColumnSql} {$sortDir} LIMIT ? OFFSET ?";
        $stmt = $this->db->prepare($sql);

        $idx = 1;
        foreach ($params as $param) {
            $stmt->bindValue($idx++, $param, \PDO::PARAM_STR);
        }
        $stmt->bindValue($idx++, $perPage, \PDO::PARAM_INT);
        $stmt->bindValue($idx, $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $countSql = "SELECT COUNT(*) FROM orders o LEFT JOIN heard_about_options hao ON hao.id = o.heard_about_option_id" . $whereSql;
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

    public function updateOrderSecurityDeposit($orderId, $securityDeposit, $reason, $updatedByAdminId = null)
    {
        $orderId = (int)$orderId;
        if ($orderId <= 0) {
            return null;
        }

        $newDeposit = round(max(0, (float)$securityDeposit), 2);
        $reasonText = trim((string)$reason);
        if ($reasonText === '') {
            return null;
        }
        $updatedByAdminId = $updatedByAdminId !== null ? (int)$updatedByAdminId : null;
        if ($updatedByAdminId !== null && $updatedByAdminId <= 0) {
            $updatedByAdminId = null;
        }

        $orderStmt = $this->db->prepare("SELECT order_id, total_amount, promo_discount, security_deposit FROM orders WHERE order_id = ? LIMIT 1");
        $orderStmt->execute([$orderId]);
        $order = $orderStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$order) {
            return null;
        }

        $itemsStmt = $this->db->prepare("SELECT COALESCE(SUM(price * quantity), 0) FROM order_items WHERE order_id = ?");
        $itemsStmt->execute([$orderId]);
        $itemsSubtotal = round((float)$itemsStmt->fetchColumn(), 2);

        $promoDiscount = max(0, round((float)($order['promo_discount'] ?? 0), 2));
        $productTotalWithTax = round(max(0, $itemsSubtotal - $promoDiscount), 2);

        if ($productTotalWithTax <= 0) {
            $existingTotal = round((float)($order['total_amount'] ?? 0), 2);
            $existingDeposit = max(0, round((float)($order['security_deposit'] ?? 0), 2));
            $productTotalWithTax = round(max(0, $existingTotal - $existingDeposit), 2);
        }

        $newTotalAmount = round($productTotalWithTax + $newDeposit, 2);

        $updateStmt = $this->db->prepare("UPDATE orders SET security_deposit = ?, total_amount = ?, security_deposit_reason = ?, security_deposit_updated_by_admin_id = ?, security_deposit_updated_at = NOW() WHERE order_id = ?");
        $updateStmt->execute([$newDeposit, $newTotalAmount, $reasonText, $updatedByAdminId, $orderId]);

        return [
            'order_id' => $orderId,
            'security_deposit' => $newDeposit,
            'total_amount' => $newTotalAmount,
            'product_total_with_tax' => $productTotalWithTax,
            'security_deposit_reason' => $reasonText,
            'security_deposit_updated_by_admin_id' => $updatedByAdminId,
        ];
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

    private function normalizeAnalyticsDays($days): ?int
    {
        if ($days === null || $days === '') {
            return null;
        }

        $value = (int)$days;
        return $value > 0 ? $value : null;
    }

    public function getAnalyticsSummary($days = 30): array
    {
        $days = $this->normalizeAnalyticsDays($days);

        $orderWhere = "WHERE status = 'completed'";
        $orderParams = [];
        if ($days !== null) {
            $orderWhere .= " AND order_date >= DATE_SUB(NOW(), INTERVAL ? DAY)";
            $orderParams[] = $days;
        }

        $summarySql = "
            SELECT
                COALESCE(SUM(total_amount), 0) AS total_amount,
                COALESCE(SUM(GREATEST(0, COALESCE(total_amount, 0) - COALESCE(security_deposit, 0))), 0) AS sales_after_tax,
                COALESCE(SUM(GREATEST(0, COALESCE(total_amount, 0) - COALESCE(security_deposit, 0)) / 1.08375), 0) AS sales_before_tax,
                COALESCE(SUM(COALESCE(security_deposit, 0)), 0) AS security_deposit_collected,
                COUNT(*) AS completed_orders
            FROM orders
            {$orderWhere}
        ";
        $summaryStmt = $this->db->prepare($summarySql);
        foreach ($orderParams as $idx => $param) {
            $summaryStmt->bindValue($idx + 1, $param, \PDO::PARAM_INT);
        }
        $summaryStmt->execute();
        $summary = $summaryStmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        $ordersWhere = '';
        $ordersParams = [];
        if ($days !== null) {
            $ordersWhere = "WHERE order_date >= DATE_SUB(NOW(), INTERVAL ? DAY)";
            $ordersParams[] = $days;
        }

        $totalOrdersSql = "SELECT COUNT(*) FROM orders {$ordersWhere}";
        $totalOrdersStmt = $this->db->prepare($totalOrdersSql);
        foreach ($ordersParams as $idx => $param) {
            $totalOrdersStmt->bindValue($idx + 1, $param, \PDO::PARAM_INT);
        }
        $totalOrdersStmt->execute();
        $totalOrders = (int)$totalOrdersStmt->fetchColumn();

        $pendingWhere = "WHERE status = 'pending'";
        $pendingParams = [];
        if ($days !== null) {
            $pendingWhere .= " AND order_date >= DATE_SUB(NOW(), INTERVAL ? DAY)";
            $pendingParams[] = $days;
        }
        $pendingSql = "SELECT COUNT(*) FROM orders {$pendingWhere}";
        $pendingStmt = $this->db->prepare($pendingSql);
        foreach ($pendingParams as $idx => $param) {
            $pendingStmt->bindValue($idx + 1, $param, \PDO::PARAM_INT);
        }
        $pendingStmt->execute();
        $pendingOrders = (int)$pendingStmt->fetchColumn();

        $refundWhere = "WHERE approved_amount > 0 AND LOWER(COALESCE(status, '')) IN ('pending', 'succeeded', 'completed', 'manual_recorded')";
        $refundParams = [];
        if ($days !== null) {
            $refundWhere .= " AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
            $refundParams[] = $days;
        }
        $refundSql = "SELECT COALESCE(SUM(approved_amount), 0) FROM order_refunds {$refundWhere}";
        $refundStmt = $this->db->prepare($refundSql);
        foreach ($refundParams as $idx => $param) {
            $refundStmt->bindValue($idx + 1, $param, \PDO::PARAM_INT);
        }
        $refundStmt->execute();
        $refundedAmount = (float)$refundStmt->fetchColumn();

        $salesAfterTax = (float)($summary['sales_after_tax'] ?? 0);
        $salesBeforeTax = (float)($summary['sales_before_tax'] ?? 0);

        return [
            'total_orders' => $totalOrders,
            'completed_orders' => (int)($summary['completed_orders'] ?? 0),
            'pending_orders' => $pendingOrders,
            'total_amount' => (float)($summary['total_amount'] ?? 0),
            'sales_after_tax' => $salesAfterTax,
            'sales_before_tax' => $salesBeforeTax,
            'tax_collected' => max(0, $salesAfterTax - $salesBeforeTax),
            'security_deposit_collected' => (float)($summary['security_deposit_collected'] ?? 0),
            'security_deposit_refunded' => $refundedAmount,
            'net_sales_after_refunds' => max(0, $salesAfterTax - $refundedAmount),
        ];
    }

    public function getOrdersByStatus($days = null) {
        $days = $this->normalizeAnalyticsDays($days);
        if ($days === null) {
            $stmt = $this->db->query("SELECT status, COUNT(*) as count FROM orders GROUP BY status");
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }

        $stmt = $this->db->prepare(
            "SELECT status, COUNT(*) as count
             FROM orders
             WHERE order_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY status"
        );
        $stmt->execute([$days]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getSalesByDate($days = 30) {
        $days = $this->normalizeAnalyticsDays($days);
        if ($days === null) {
            $stmt = $this->db->query(
                "SELECT DATE(order_date) as date,
                        SUM(GREATEST(0, COALESCE(total_amount, 0) - COALESCE(security_deposit, 0))) as total
                 FROM orders
                 WHERE status = 'completed'
                 GROUP BY DATE(order_date)
                 ORDER BY date ASC"
            );
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }

        $stmt = $this->db->prepare(
            "SELECT DATE(order_date) as date,
                    SUM(GREATEST(0, COALESCE(total_amount, 0) - COALESCE(security_deposit, 0))) as total
             FROM orders
             WHERE status = 'completed'
               AND order_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY DATE(order_date)
             ORDER BY date ASC"
        );
        $stmt->execute([$days]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getOrderCountByDate($days = 30) {
        $days = $this->normalizeAnalyticsDays($days);
        if ($days === null) {
            $stmt = $this->db->query(
                "SELECT DATE(order_date) as date, COUNT(*) as count
                 FROM orders
                 GROUP BY DATE(order_date)
                 ORDER BY date ASC"
            );
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }

        $stmt = $this->db->prepare(
            "SELECT DATE(order_date) as date, COUNT(*) as count
             FROM orders
             WHERE order_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY DATE(order_date)
             ORDER BY date ASC"
        );
        $stmt->execute([$days]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getRefundsByDate($days = 30): array
    {
        $days = $this->normalizeAnalyticsDays($days);
        if ($days === null) {
            $stmt = $this->db->query(
                "SELECT DATE(created_at) as date, SUM(approved_amount) as total
                 FROM order_refunds
                                 WHERE approved_amount > 0
                                     AND LOWER(COALESCE(status, '')) IN ('pending', 'succeeded', 'completed', 'manual_recorded')
                 GROUP BY DATE(created_at)
                 ORDER BY date ASC"
            );
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }

        $stmt = $this->db->prepare(
            "SELECT DATE(created_at) as date, SUM(approved_amount) as total
             FROM order_refunds
             WHERE approved_amount > 0
               AND LOWER(COALESCE(status, '')) IN ('pending', 'succeeded', 'completed')
               AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY DATE(created_at)
             ORDER BY date ASC"
        );
        $stmt->execute([$days]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getPaymentProviderBreakdown($days = 30): array
    {
        $days = $this->normalizeAnalyticsDays($days);
        if ($days === null) {
            $stmt = $this->db->query(
                "SELECT COALESCE(NULLIF(LOWER(TRIM(payment_provider)), ''), 'unknown') AS provider, COUNT(*) AS count
                 FROM orders
                 GROUP BY provider
                 ORDER BY count DESC"
            );
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }

        $stmt = $this->db->prepare(
            "SELECT COALESCE(NULLIF(LOWER(TRIM(payment_provider)), ''), 'unknown') AS provider, COUNT(*) AS count
             FROM orders
             WHERE order_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY provider
             ORDER BY count DESC"
        );
        $stmt->execute([$days]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getHeardAboutFilterOptions(): array
    {
        $stmt = $this->db->query(
            "SELECT
                COALESCE(NULLIF(TRIM(o.heard_about_label), ''), NULLIF(TRIM(hao.label), ''), 'Not specified') AS heard_about,
                COUNT(*) AS count
             FROM orders o
             LEFT JOIN heard_about_options hao ON hao.id = o.heard_about_option_id
             GROUP BY heard_about
             ORDER BY count DESC, heard_about ASC"
        );

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getHeardAboutBreakdown($days = 30): array
    {
        $days = $this->normalizeAnalyticsDays($days);
        if ($days === null) {
            $stmt = $this->db->query(
                "SELECT
                    COALESCE(NULLIF(TRIM(o.heard_about_label), ''), NULLIF(TRIM(hao.label), ''), 'Not specified') AS heard_about,
                    COUNT(*) AS count
                 FROM orders o
                 LEFT JOIN heard_about_options hao ON hao.id = o.heard_about_option_id
                 GROUP BY heard_about
                 ORDER BY count DESC, heard_about ASC"
            );

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }

        $stmt = $this->db->prepare(
            "SELECT
                COALESCE(NULLIF(TRIM(o.heard_about_label), ''), NULLIF(TRIM(hao.label), ''), 'Not specified') AS heard_about,
                COUNT(*) AS count
             FROM orders o
             LEFT JOIN heard_about_options hao ON hao.id = o.heard_about_option_id
             WHERE o.order_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY heard_about
             ORDER BY count DESC, heard_about ASC"
        );
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
                $stmt = $this->db->prepare("SELECT name, address1, address2, state, zip FROM partner_hotels WHERE id = ?");
                $stmt->execute([$hotelId]);
                $hotel = $stmt->fetch(\PDO::FETCH_ASSOC);
                $hotelName = trim((string)($hotel['name'] ?? ''));
                $hotelAddress1 = trim((string)($hotel['address1'] ?? ''));
                $address1 = $hotelAddress1;
                if ($hotelName !== '' && stripos($hotelAddress1, $hotelName) === false) {
                    $address1 = trim($hotelAddress1 . ' (' . $hotelName . ')');
                }
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

        $cart = $this->normalizeCartForTrustedPricing(
            is_array($cart) ? $cart : [],
            $form['pickup_datetime'] ?? null,
            $form['return_datetime'] ?? null,
            $form['sale_type'] ?? 'rental'
        );
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
        $productTotalWithTax = round($totalAmount, 2);
        $securityDeposit = self::SECURITY_DEPOSIT;
        $totalAmountWithTax = round($productTotalWithTax + $securityDeposit, 2);
        $pickup_datetime = $form['pickup_datetime'] ?? null;
        $return_datetime = $form['return_datetime'] ?? null;
        $bookingSource = htmlspecialchars(trim((string)($form['booking_source'] ?? 'online')));
        $heardAbout = $this->resolveHeardAboutSelection($form);
        $createdByAdminId = isset($form['created_by_admin_id']) && is_numeric($form['created_by_admin_id'])
            ? (int)$form['created_by_admin_id']
            : (isset($session['admin_id']) && is_numeric($session['admin_id']) ? (int)$session['admin_id'] : null);
        $createdByAdminRole = strtolower(trim((string)($form['created_by_admin_role'] ?? ($session['admin_role'] ?? ''))));
        $createdByAdminName = trim((string)($form['created_by_admin_name'] ?? ($session['admin_username'] ?? '')));
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
                    strtolower((string)$payment) === 'paypal' ? 'paypal' : (strtolower((string)$payment) === 'card' ? 'stripe' : null),
                    $totalAmountWithTax,
                    $securityDeposit,
                    $customerType,
                    $bookingSource,
                    $heardAbout['id'],
                    $heardAbout['label'],
                    $createdByAdminId,
                    $createdByAdminRole !== '' ? $createdByAdminRole : null,
                    $createdByAdminName !== '' ? $createdByAdminName : null,
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
                    user_id, guest_id, guest_first_name, guest_last_name, guest_email, guest_phone, client_weight_option, client_weight_lbs, address1, address2, state, zip, pickup_location, notes, payment_method, payment_provider, total_amount, security_deposit, customer_type, booking_source, heard_about_option_id, heard_about_label, created_by_admin_id, created_by_admin_role, created_by_admin_name, pickup_datetime, return_datetime, delivery_type, hotel_id, status, order_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
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
                        $this->sanitizeProductNameForStorage($item['name'] ?? 'Item'),
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
        $proformaPath = null;
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
            
            // --- PRO-FORMA PDF GENERATION ---
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

            $subtotal = 0.0;
            foreach ($cart as $cartItem) {
                $qtyValue = max(1, (int)($cartItem['qty'] ?? $cartItem['quantity'] ?? 1));
                $subtotal += $qtyValue * (float)($cartItem['price'] ?? 0);
            }
            $discountAmount = isset($orderData['promo_discount']) ? (float)$orderData['promo_discount'] : 0.0;
            $promoCode = (string)($orderData['promo_code'] ?? '');
            $orderDate = date('Y-m-d H:i:s');
            $paymentMethod = (string)($payment_method ?? '');
            $pickupLocation = (string)($pickup_location ?? '');
            $deliveryType = (string)($delivery_type ?? '');

            $itemsTable = $invoiceItemsTable;
            $productTotalWithTax = round(max(0, $subtotal - $discountAmount), 2);
            $securityDeposit = self::SECURITY_DEPOSIT;
            $productPreTax = round($productTotalWithTax / self::NV_TAX_INCLUSIVE_FACTOR, 2);
            $totalAmountWithTax = round($productTotalWithTax + $securityDeposit, 2);
            $totalAmount = round($productPreTax + $securityDeposit, 2);
            $tax = round(max(0, $productTotalWithTax - $productPreTax), 2);
            ob_start();
            include __DIR__ . '/../../Proformas/proforma-template.php';
            $invoiceHtml = ob_get_clean();
            $invoiceOptions = new Options();
            $invoiceOptions->set('isRemoteEnabled', true);
            $invoiceOptions->set('isHtml5ParserEnabled', true);
            $invoiceDompdf = new Dompdf($invoiceOptions);
            $invoiceDompdf->loadHtml($invoiceHtml);
            $invoiceDompdf->setPaper('A4', 'portrait');
            $invoiceDompdf->render();
            $proformaDir = __DIR__ . '/../../Proformas/';
            if ((!is_dir($proformaDir) && !@mkdir($proformaDir, 0777, true)) || !is_writable($proformaDir)) {
                $proformaDir = __DIR__ . '/../../public/Proformas/';
                if (!is_dir($proformaDir) && !@mkdir($proformaDir, 0777, true)) {
                    throw new \RuntimeException('Unable to create Proformas directory.');
                }
            }
            $invoiceTarget = $proformaDir . "proforma-{$orderId}.pdf";
            $written = @file_put_contents($invoiceTarget, $invoiceDompdf->output());
            if ($written === false || !is_file($invoiceTarget) || filesize($invoiceTarget) === 0) {
                throw new \RuntimeException('Failed to write pro-forma PDF.');
            }
            $proformaPath = $proformaDir . "proforma-{$orderId}.pdf";
        } catch (\Throwable $e) {
            // PDF generation failed, but order is already created - log it and continue
            @ob_end_clean();
            error_log("Contract/Pro-forma generation failed for order {$orderId}: " . $e->getMessage());
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
                    fwrite($debugFile, date('Y-m-d H:i:s') . "\n[DEBUG] Preparing to send contract/pro-forma email to: $finalEmail\n");
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
                $mail->isHTML(true);
                $mail->Subject = 'Your Rental Booking Confirmation';
                if ($pdfPath && is_file($pdfPath) && $proformaPath && is_file($proformaPath)) {
                    $mail->Body = buildBookingEmailTemplate([
                        'customer_name' => $finalName,
                        'order_id' => $orderId,
                        'amount_due' => $totalAmountWithTax,
                        'issued_at' => date('Y-m-d H:i:s'),
                        'pickup_datetime' => $pickupDate ?? '',
                        'return_datetime' => $returnDate ?? '',
                        'payment_method' => (string)($payment_method ?? ''),
                        'note' => 'Your booking is confirmed. A pro-forma invoice is attached. Final invoice will be issued after order completion.',
                    ]);
                    $mail->AltBody = "Thank you for your booking! Please find your rental contract and pro-forma invoice attached.";
                    $mail->addAttachment($pdfPath, "Rental-Contract-{$orderId}.pdf");
                    $mail->addAttachment($proformaPath, "Proforma-Invoice-{$orderId}.pdf");
                } else {
                    $mail->Body = buildBookingEmailTemplate([
                        'customer_name' => $finalName,
                        'order_id' => $orderId,
                        'amount_due' => $totalAmountWithTax,
                        'issued_at' => date('Y-m-d H:i:s'),
                        'pickup_datetime' => $pickupDate ?? '',
                        'return_datetime' => $returnDate ?? '',
                        'payment_method' => (string)($payment_method ?? ''),
                        'note' => 'Thank you for your booking. Your pro-forma files are being prepared and will be sent shortly.',
                    ]);
                    $mail->AltBody = "Thank you for your booking! Your contract/pro-forma files are being prepared and will be sent shortly.";
                }
                $mail->send();
                if (is_resource($debugFile)) {
                    fwrite($debugFile, date('Y-m-d H:i:s') . "\n[DEBUG] Contract/pro-forma email sent successfully to: $finalEmail\n");
                }
            } catch (MailException $e) {
                $debugFile = @fopen(__DIR__ . '/../../public/order-debug-log.txt', 'a');
                if (is_resource($debugFile)) {
                    fwrite($debugFile, date('Y-m-d H:i:s') . "\n[ERROR] Contract/pro-forma email failed: " . $mail->ErrorInfo . "\nException: " . $e->getMessage() . "\n");
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
            $fallbackDeposit = $totalAmountWithTax >= self::SECURITY_DEPOSIT ? self::SECURITY_DEPOSIT : 0.0;
            $subtotal = round(max(0, $totalAmountWithTax - $fallbackDeposit), 2);
        }
        $pickup_datetime = $pickupDate;
        $return_datetime = $returnDate;

        $contractDir = __DIR__ . '/../../Contracts/';
        $invoiceDir = __DIR__ . '/../../Proformas/';
        if ((!is_dir($contractDir) && !@mkdir($contractDir, 0777, true)) || !is_writable($contractDir)) {
            $contractDir = __DIR__ . '/../../public/Contracts/';
            if (!is_dir($contractDir) && !@mkdir($contractDir, 0777, true)) {
                $contractDir = null;
            }
        }
        if ((!is_dir($invoiceDir) && !@mkdir($invoiceDir, 0777, true)) || !is_writable($invoiceDir)) {
            $invoiceDir = __DIR__ . '/../../public/Proformas/';
            if (!is_dir($invoiceDir) && !@mkdir($invoiceDir, 0777, true)) {
                $invoiceDir = null;
            }
        }

        $pdfPath = $contractDir ? $contractDir . "contract-{$orderId}.pdf" : null;
        $invoicePath = $invoiceDir ? $invoiceDir . "proforma-{$orderId}.pdf" : null;

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
        $discountAmount = (float)($order['promo_discount'] ?? 0);
        $promoCode = (string)($order['promo_code'] ?? '');
        $securityDepositReason = (string)($order['security_deposit_reason'] ?? '');
        $securityDepositBaseline = self::SECURITY_DEPOSIT;
        $orderDate = (string)($order['order_date'] ?? date('Y-m-d H:i:s'));
        $paymentMethod = (string)($order['payment_method'] ?? '');
        $pickupLocation = (string)($order['pickup_location'] ?? '');
        $deliveryType = (string)($order['delivery_type'] ?? '');
        $subtotal = (float)$subtotal;
        $productTotalWithTax = round(max(0, $subtotal - $discountAmount), 2);
        $storedSecurityDeposit = isset($order['security_deposit']) ? (float)$order['security_deposit'] : null;
        $securityDeposit = $storedSecurityDeposit !== null && $storedSecurityDeposit >= 0
            ? round($storedSecurityDeposit, 2)
            : 0.0;
        if ($securityDeposit <= 0 && $totalAmountWithTax > 0) {
            $securityDeposit = round(max(0, $totalAmountWithTax - $productTotalWithTax), 2);
        }
        if ($totalAmountWithTax <= 0) {
            $securityDeposit = self::SECURITY_DEPOSIT;
            $totalAmountWithTax = round($productTotalWithTax + $securityDeposit, 2);
        }
        $productPreTax = round($productTotalWithTax / self::NV_TAX_INCLUSIVE_FACTOR, 2);
        $totalAmount = round($productPreTax + $securityDeposit, 2);
        $tax = round(max(0, $productTotalWithTax - $productPreTax), 2);
        
        // WRAP PRO-FORMA PDF GENERATION IN TRY-CATCH
        try {
            if ($invoicePath && (!file_exists($invoicePath) || filesize($invoicePath) === 0)) {
                ob_start();
            include __DIR__ . '/../../Proformas/proforma-template.php';
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
                    throw new \RuntimeException('Failed to write recovery pro-forma PDF.');
                }
                if (is_resource($debugFile)) {
                    fwrite($debugFile, date('Y-m-d H:i:s') . "\n[DEBUG] Pro-forma PDF generated for orderId: {$orderId}\n");
                }
            }
        } catch (\Throwable $e) {
            @ob_end_clean();
            error_log("Pro-forma PDF generation failed for order {$orderId}: " . $e->getMessage());
            if (is_resource($debugFile)) {
                fwrite($debugFile, date('Y-m-d H:i:s') . "\n[ERROR] Pro-forma PDF error: " . $e->getMessage() . "\n");
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
                $attachments[] = ['path' => $invoicePath, 'name' => "Proforma-Invoice-{$orderId}.pdf"];
            }
            $bodyHtml = buildBookingEmailTemplate([
                'customer_name' => $customerName,
                'order_id' => $orderId,
                'amount_due' => $totalAmountWithTax,
                'issued_at' => (string)($order['order_date'] ?? date('Y-m-d H:i:s')),
                'pickup_datetime' => $pickupDate,
                'return_datetime' => $returnDate,
                'payment_method' => (string)($order['payment_method'] ?? ''),
                'note' => !empty($attachments)
                    ? 'Your booking is confirmed. Your pro-forma invoice is attached. Final invoice is issued after completion.'
                    : 'Thank you for your booking. Your pro-forma files are being prepared and will be sent shortly.',
            ]);
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

    private function generateAndSendFinalInvoiceForCompletedOrder($orderId): bool {
        $stmt = $this->db->prepare("SELECT * FROM orders WHERE order_id = ? LIMIT 1");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$order) {
            return false;
        }

        $itemsStmt = $this->db->prepare("SELECT product_name, variation_name, quantity, price FROM order_items WHERE order_id = ? ORDER BY order_item_id ASC");
        $itemsStmt->execute([$orderId]);
        $items = $itemsStmt->fetchAll(\PDO::FETCH_ASSOC);

        $groupedItems = [];
        foreach ($items as $item) {
            $qty = max(1, (int)($item['quantity'] ?? 1));
            $name = trim((string)($item['product_name'] ?? 'Item') . (!empty($item['variation_name']) ? ' - ' . (string)$item['variation_name'] : ''));
            $price = (float)($item['price'] ?? 0);
            $groupKey = $name . '||' . number_format($price, 2, '.', '');
            if (!isset($groupedItems[$groupKey])) {
                $groupedItems[$groupKey] = [
                    'name' => $name,
                    'price' => $price,
                    'qty' => 0,
                ];
            }
            $groupedItems[$groupKey]['qty'] += $qty;
        }

        $invoiceItemsTable = '';
        $subtotal = 0.0;
        foreach ($groupedItems as $group) {
            $lineQty = (int)$group['qty'];
            $linePrice = (float)$group['price'];
            $lineTotalValue = $lineQty * $linePrice;
            $subtotal += $lineTotalValue;

            $safeQty = htmlspecialchars((string)$lineQty);
            $safeName = htmlspecialchars((string)$group['name']);
            $unitPrice = number_format($linePrice, 2);
            $lineTotal = number_format($lineTotalValue, 2);
            $invoiceItemsTable .= "<tr><td class='border p-2'>{$safeQty}</td><td class='border p-2'>{$safeName}</td><td class='border p-2'>\${$unitPrice}</td><td class='border p-2'>\${$lineTotal}</td></tr>";
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
        $orderDate = (string)($order['order_date'] ?? date('Y-m-d H:i:s'));
        $pickup_datetime = (string)($order['pickup_datetime'] ?? '');
        $return_datetime = (string)($order['return_datetime'] ?? '');
        $customerName = trim((string)($order['guest_first_name'] ?? '') . ' ' . (string)($order['guest_last_name'] ?? ''));
        $customerEmail = filter_var(trim((string)($order['guest_email'] ?? '')), FILTER_VALIDATE_EMAIL);
        if ((!$customerEmail || $customerName === '') && !empty($order['user_id'])) {
            $userStmt = $this->db->prepare("SELECT first_name, last_name, email FROM users WHERE user_id = ? LIMIT 1");
            $userStmt->execute([$order['user_id']]);
            $user = $userStmt->fetch(\PDO::FETCH_ASSOC) ?: [];
            $userEmail = filter_var(trim((string)($user['email'] ?? '')), FILTER_VALIDATE_EMAIL);
            if ($userEmail) {
                $customerEmail = $userEmail;
            }
            if ($customerName === '') {
                $customerName = trim((string)($user['first_name'] ?? '') . ' ' . (string)($user['last_name'] ?? ''));
            }
        }
        $customerPhone = (string)($order['guest_phone'] ?? '');
        $customerAddress = trim((string)($order['address1'] ?? '') . ' ' . (string)($order['address2'] ?? ''));
        $discountAmount = (float)($order['promo_discount'] ?? 0);
        $promoCode = (string)($order['promo_code'] ?? '');
        $securityDepositReason = (string)($order['security_deposit_reason'] ?? '');
        $securityDepositRefundReason = '';
        try {
            $refundReasonStmt = $this->db->prepare("SELECT reason FROM order_refunds WHERE order_id = ? AND approved_amount > 0 AND status IN ('succeeded','completed','pending') ORDER BY refund_id DESC LIMIT 1");
            $refundReasonStmt->execute([$orderId]);
            $latestRefundReason = $refundReasonStmt->fetchColumn();
            if ($latestRefundReason !== false && $latestRefundReason !== null) {
                $securityDepositRefundReason = trim((string)$latestRefundReason);
            }
        } catch (\Throwable $e) {
            $securityDepositRefundReason = '';
        }
        $securityDepositBaseline = self::SECURITY_DEPOSIT;
        $paymentMethod = (string)($order['payment_method'] ?? '');
        $pickupLocation = (string)($order['pickup_location'] ?? '');
        $deliveryType = (string)($order['delivery_type'] ?? '');

        $totalAmountWithTax = (float)($order['total_amount'] ?? 0);
        $productTotalWithTax = round(max(0, $subtotal - $discountAmount), 2);
        $storedSecurityDeposit = isset($order['security_deposit']) ? (float)$order['security_deposit'] : null;
        $securityDeposit = $storedSecurityDeposit !== null && $storedSecurityDeposit >= 0
            ? round($storedSecurityDeposit, 2)
            : 0.0;
        if ($securityDeposit <= 0 && $totalAmountWithTax > 0) {
            $securityDeposit = round(max(0, $totalAmountWithTax - $productTotalWithTax), 2);
        }
        $productPreTax = round($productTotalWithTax / self::NV_TAX_INCLUSIVE_FACTOR, 2);
        $totalAmount = round($productPreTax + $securityDeposit, 2);
        $tax = round(max(0, $productTotalWithTax - $productPreTax), 2);

        $invoiceDir = __DIR__ . '/../../Invoices/';
        if ((!is_dir($invoiceDir) && !@mkdir($invoiceDir, 0777, true)) || !is_writable($invoiceDir)) {
            $invoiceDir = __DIR__ . '/../../public/Invoices/';
            if (!is_dir($invoiceDir) && !@mkdir($invoiceDir, 0777, true)) {
                return false;
            }
        }
        $invoicePath = $invoiceDir . "invoice-{$orderId}.pdf";

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
            return false;
        }

        if ($customerEmail) {
            require_once __DIR__ . '/../Utils/Mailer.php';
            $subject = 'Your Final Invoice - Completed Order';
            $body = buildBookingEmailTemplate([
                'customer_name' => $customerName,
                'order_id' => $orderId,
                'amount_due' => $totalAmountWithTax,
                'issued_at' => date('Y-m-d H:i:s'),
                'pickup_datetime' => $pickup_datetime,
                'return_datetime' => $return_datetime,
                'payment_method' => $paymentMethod,
                'note' => 'Your order has been completed. Your final invoice is attached.',
            ]);
            @sendBookingConfirmation($customerEmail, $customerName, $subject, $body, [
                ['path' => $invoicePath, 'name' => "Final-Invoice-{$orderId}.pdf"],
            ]);
        }

        return true;
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

        if ($this->generateAndSendFinalInvoiceForCompletedOrder($orderId)) {
            $messages[] = "Final invoice generated and emailed for order {$orderId}.";
        } else {
            $messages[] = "Order completed, but final invoice generation/email needs manual follow-up.";
        }

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

        // Keep reservation lifecycle in sync with order lifecycle.
        $stmtReservations = $pdo->prepare("UPDATE reservations SET status = 'cancelled' WHERE order_id = ?");
        $stmtReservations->execute([$orderId]);

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
        $scriptDir = isset($_SERVER['SCRIPT_NAME']) ? rtrim(str_replace('\\', '/', dirname((string)$_SERVER['SCRIPT_NAME'])), '/') : '';
        if ($scriptDir === '/' || $scriptDir === '.') {
            $scriptDir = '';
        }

        // Build web URLs that are always public, and mirror legacy files if needed.
        $contractPdf = null;
        $contractPublicPath = __DIR__ . '/../../public/Contracts/contract-' . $orderId . '.pdf';
        $contractLegacyPath = __DIR__ . '/../../Contracts/contract-' . $orderId . '.pdf';
        if (!is_dir(dirname($contractPublicPath))) {
            @mkdir(dirname($contractPublicPath), 0777, true);
        }
        if (!file_exists($contractPublicPath) && file_exists($contractLegacyPath) && is_readable($contractLegacyPath)) {
            @copy($contractLegacyPath, $contractPublicPath);
        }
        if (file_exists($contractPublicPath) && is_readable($contractPublicPath)) {
            $contractPdf = ($scriptDir !== '' ? $scriptDir : '') . '/Contracts/contract-' . $orderId . '.pdf';
        }

        $invoicePdf = null;
        $invoicePublicPath = __DIR__ . '/../../public/Invoices/invoice-' . $orderId . '.pdf';
        $invoiceLegacyPath = __DIR__ . '/../../Invoices/invoice-' . $orderId . '.pdf';
        if (!is_dir(dirname($invoicePublicPath))) {
            @mkdir(dirname($invoicePublicPath), 0777, true);
        }
        if (!file_exists($invoicePublicPath) && file_exists($invoiceLegacyPath) && is_readable($invoiceLegacyPath)) {
            @copy($invoiceLegacyPath, $invoicePublicPath);
        }
        if (file_exists($invoicePublicPath) && is_readable($invoicePublicPath)) {
            $invoicePdf = ($scriptDir !== '' ? $scriptDir : '') . '/Invoices/invoice-' . $orderId . '.pdf';
        }

        $proformaPdf = null;
        $proformaPublicPath = __DIR__ . '/../../public/Proformas/proforma-' . $orderId . '.pdf';
        $proformaLegacyPath = __DIR__ . '/../../Proformas/proforma-' . $orderId . '.pdf';
        if (!is_dir(dirname($proformaPublicPath))) {
            @mkdir(dirname($proformaPublicPath), 0777, true);
        }
        if (!file_exists($proformaPublicPath) && file_exists($proformaLegacyPath) && is_readable($proformaLegacyPath)) {
            @copy($proformaLegacyPath, $proformaPublicPath);
        }
        if (file_exists($proformaPublicPath) && is_readable($proformaPublicPath)) {
            $proformaPdf = ($scriptDir !== '' ? $scriptDir : '') . '/Proformas/proforma-' . $orderId . '.pdf';
        }

        $refundData = $this->getOrderRefundData((int)$orderId);
        return [
            'order' => $order,
            'items' => $items,
            'contract_pdf' => $contractPdf,
            'invoice_pdf' => $invoicePdf,
            'proforma_pdf' => $proformaPdf,
            'refund_summary' => $refundData['summary'] ?? [],
            'refunds' => $refundData['refunds'] ?? []
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

        $cart = $this->normalizeCartForTrustedPricing(
            $cart,
            $pickup_datetime,
            $return_datetime,
            $post['sale_type'] ?? 'rental'
        );

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

        $lineItems[] = [
            'price_data' => [
                'currency' => 'usd',
                'product_data' => ['name' => 'Refundable Security Deposit'],
                'unit_amount' => (int) round(self::SECURITY_DEPOSIT * 100),
            ],
            'quantity' => 1,
        ];
        $totalAmount = round($totalAmount + self::SECURITY_DEPOSIT, 2);

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
            'heard_about_option_id' => trim((string)($post['heard_about_option_id'] ?? '')),
            'heard_about_other_text' => htmlspecialchars(trim((string)($post['heard_about_other_text'] ?? ''))),
            'sale_type' => htmlspecialchars(trim($post['sale_type'] ?? 'rental')),
            'cart_json' => json_encode($cart),
            'total_amount' => (string)$totalAmount,
            'security_deposit' => (string)self::SECURITY_DEPOSIT,
            'logged_in_user_id' => (string)($session['user_id'] ?? ''),
            'created_by_admin_id' => (string)($session['admin_id'] ?? ''),
            'created_by_admin_role' => strtolower(trim((string)($session['admin_role'] ?? ''))),
            'created_by_admin_name' => trim((string)($session['admin_username'] ?? '')),
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

        $cart = $this->normalizeCartForTrustedPricing(
            $cart,
            $pickup_datetime,
            $return_datetime,
            $post['sale_type'] ?? 'rental'
        );

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

        $totalAmount = round($totalAmount + self::SECURITY_DEPOSIT, 2);

        if ($totalAmount <= 0) {
            return ['error' => 'No valid items'];
        }

        $deliveryType = $post['delivery_type'] ?? 'preferred';
        $pickup_location = '';
        if ($deliveryType === 'pickup' && !empty($post['pickup_location'])) {
            $pickup_location = htmlspecialchars(trim($post['pickup_location']));
        }

        $metadata = [
            'checkout_ref' => trim((string)($post['checkout_ref'] ?? '')),
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
            'heard_about_option_id' => trim((string)($post['heard_about_option_id'] ?? '')),
            'heard_about_other_text' => htmlspecialchars(trim((string)($post['heard_about_other_text'] ?? ''))),
            'sale_type' => htmlspecialchars(trim($post['sale_type'] ?? 'rental')),
            'total_amount' => (string)$totalAmount,
            'security_deposit' => (string)self::SECURITY_DEPOSIT,
            'logged_in_user_id' => (string)($session['user_id'] ?? ''),
            'created_by_admin_id' => (string)($session['admin_id'] ?? ''),
            'created_by_admin_role' => strtolower(trim((string)($session['admin_role'] ?? ''))),
            'created_by_admin_name' => trim((string)($session['admin_username'] ?? '')),
        ];

        // Stripe metadata values are limited to 500 chars each.
        foreach ($metadata as $key => $value) {
            $valueStr = (string)$value;
            if (strlen($valueStr) > 500) {
                $metadata[$key] = substr($valueStr, 0, 500);
            }
        }

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
            return [
                'clientSecret' => $intent->client_secret,
                'paymentIntentId' => $intent->id,
            ];
        } catch (\Exception $e) {
            error_log('Stripe PaymentIntent error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }


}