<?php

namespace App\Controllers;
use App\Controller;
// MAILER
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
// MODEL
use App\Models\OrderModel;
// PDF
use Dompdf\Dompdf;
use Dompdf\Options;
// STRIPE
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;
// PAYPAL
use PaypalServerSdkLib\PaypalServerSdkClientBuilder;
use PaypalServerSdkLib\Authentication\ClientCredentialsAuthCredentialsBuilder;
use PaypalServerSdkLib\Logging\LoggingConfigurationBuilder;
use PaypalServerSdkLib\Logging\RequestLoggingConfigurationBuilder;
use PaypalServerSdkLib\Logging\ResponseLoggingConfigurationBuilder;
use Psr\Log\LogLevel;
use PaypalServerSdkLib\Models\Builders\OrderRequestBuilder;
use PaypalServerSdkLib\Models\CheckoutPaymentIntent;
use PaypalServerSdkLib\Models\Builders\PurchaseUnitRequestBuilder;
use PaypalServerSdkLib\Models\Builders\AmountWithBreakdownBuilder;
use PaypalServerSdkLib\Models\AmountBreakdown;
use PaypalServerSdkLib\Models\Builders\AmountBreakdownBuilder;
use PaypalServerSdkLib\Models\Builders\MoneyBuilder;
use PaypalServerSdkLib\Models\Builders\ItemBuilder;
use PaypalServerSdkLib\Models\ItemCategory;
use PaypalServerSdkLib\Models\Builders\ShippingDetailsBuilder;
use PaypalServerSdkLib\Models\Builders\ShippingNameBuilder;
use PaypalServerSdkLib\Models\Builders\ShippingOptionBuilder;
use PaypalServerSdkLib\Models\ShippingType;
use PaypalServerSdkLib\Environment;
use PaypalServerSdkLib\Models\Builders\PaypalWalletBuilder;
use PaypalServerSdkLib\Models\Builders\PaypalWalletExperienceContextBuilder;
use PaypalServerSdkLib\Models\ShippingPreference;
use PaypalServerSdkLib\Models\PaypalExperienceLandingPage;
use PaypalServerSdkLib\Models\PaypalExperienceUserAction;
use PaypalServerSdkLib\Models\Builders\CallbackConfigurationBuilder;
use PaypalServerSdkLib\Models\Builders\PhoneNumberWithCountryCodeBuilder;
use PaypalServerSdkLib\Models\Builders\PaymentSourceBuilder;
use PaypalServerSdkLib\Models\CallbackEvents;




class OrderController extends Controller
{   
    private const NV_TAX_INCLUSIVE_FACTOR = 1.08375;
    private const SECURITY_DEPOSIT = 100.00;

    private $paypalClient;

    private function isDebugEnabled(): bool
    {
        $value = getenv('APP_DEBUG');
        if ($value === false) {
            $value = $_ENV['APP_DEBUG'] ?? '';
        }
        $value = strtolower(trim((string)$value));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    private function canPersistDebugLogs(): bool
    {
        if (!$this->isDebugEnabled()) {
            return false;
        }

        $env = getenv('APP_ENV');
        if ($env === false) {
            $env = $_ENV['APP_ENV'] ?? '';
        }

        $env = strtolower(trim((string)$env));
        return in_array($env, ['local', 'development', 'dev'], true);
    }

    private function ensureAdminAuthenticatedJson(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['admin_id'])) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
    }

    private function ensureAdminAuthenticatedRedirect(string $redirectPath = '/admin/login'): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['admin_id'])) {
            header('Location: ' . $redirectPath);
            exit;
        }
    }

    private function prepareJsonResponse()
    {
        ob_start();
        @header('Content-Type: application/json; charset=utf-8');
    }

    private function openDebugLog($filename)
    {
        if (!$this->canPersistDebugLogs()) {
            return @fopen('php://temp', 'w+');
        }

        $logDir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        $path = rtrim($logDir, '/\\') . '/' . basename((string)$filename);
        $resource = @fopen($path, 'a');
        if (is_resource($resource)) {
            return $resource;
        }

        return @fopen('php://temp', 'w+');
    }

    private function readJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function getStripeFallbackPayloadFromPost(array $post): array
    {
        $bookingSource = strtolower(trim((string)($post['booking_source'] ?? 'online')));
        $isAdminOrigin = in_array($bookingSource, ['walk-in', 'walkin', 'admin', 'kiosk'], true);

        return [
            'checkout_ref' => trim((string)($post['checkout_ref'] ?? '')),
            'first_name' => trim((string)($post['first_name'] ?? '')),
            'last_name' => trim((string)($post['last_name'] ?? '')),
            'guest_email' => trim((string)($post['email'] ?? '')),
            'guest_phone' => trim((string)($post['phone'] ?? '')),
            'client_weight_option' => trim((string)($post['client_weight_option'] ?? '')),
            'client_weight_lbs' => trim((string)($post['client_weight_lbs'] ?? '')),
            'client_height' => trim((string)($post['client_height'] ?? '')),
            'power_chair_handedness' => trim((string)($post['power_chair_handedness'] ?? '')),
            'address1' => trim((string)($post['address1'] ?? '')),
            'address2' => trim((string)($post['address2'] ?? '')),
            'state' => trim((string)($post['state'] ?? '')),
            'zip' => trim((string)($post['zip'] ?? '')),
            'delivery_type' => trim((string)($post['delivery_type'] ?? 'preferred')),
            'hotel_id' => trim((string)($post['hotel_id'] ?? '')),
            'return_hotel_id' => trim((string)($post['return_hotel_id'] ?? '')),
            'delivery_fee' => trim((string)($post['delivery_fee'] ?? '')),
            'pickup_datetime' => trim((string)($post['pickup_datetime'] ?? '')),
            'return_datetime' => trim((string)($post['return_datetime'] ?? '')),
            'pickup_location' => trim((string)($post['pickup_location'] ?? '')),
            'notes' => trim((string)($post['notes'] ?? '')),
            'heard_about_option_id' => trim((string)($post['heard_about_option_id'] ?? '')),
            'heard_about_other_text' => trim((string)($post['heard_about_other_text'] ?? '')),
            'acknowledge_id_presence' => trim((string)($post['acknowledge_id_presence'] ?? '')),
            'sale_type' => trim((string)($post['sale_type'] ?? 'rental')),
            'cart_json' => (string)($post['cart'] ?? '[]'),
            'created_by_admin_id' => $isAdminOrigin ? trim((string)($_SESSION['admin_id'] ?? '')) : '',
            'created_by_admin_role' => $isAdminOrigin ? trim((string)($_SESSION['admin_role'] ?? '')) : '',
            'created_by_admin_name' => $isAdminOrigin ? trim((string)($_SESSION['admin_username'] ?? '')) : '',
        ];
    }

    private function persistCheckoutSessionSnapshot(
        string $provider,
        string $checkoutRef,
        ?string $providerPaymentIntentId,
        array $payload,
        array $cart,
        string $status = 'pending',
        ?int $finalizedOrderId = null,
        ?string $lastError = null
    ): void {
        try {
            $orderModel = new OrderModel();
            $orderModel->upsertCheckoutSession([
                'checkout_ref' => $checkoutRef,
                'provider' => $provider,
                'provider_payment_intent_id' => $providerPaymentIntentId,
                'status' => $status,
                'customer_email' => trim((string)($payload['guest_email'] ?? $payload['email'] ?? '')),
                'payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES),
                'cart_json' => json_encode($cart, JSON_UNESCAPED_SLASHES),
                'finalized_order_id' => $finalizedOrderId,
                'last_error' => $lastError,
            ]);
        } catch (\Throwable $e) {
            error_log('Checkout session persistence warning: ' . $e->getMessage());
        }
    }

    private function validateIdPresenceAcknowledgement(array $source): ?string
    {
        $ack = strtolower(trim((string)($source['acknowledge_id_presence'] ?? '')));
        if (!in_array($ack, ['1', 'true', 'on', 'yes'], true)) {
            return 'Please complete the required acknowledgements before proceeding.';
        }
        return null;
    }

    private function validatePowerChairHandednessRequirement(array $source, array $cart): ?string
    {
        // Handedness is now captured as a product variation, not a checkout field.
        return null;
    }

    private function validateBookingWindowConstraints(array $source): ?string
    {
        $pickupRaw = trim((string)($source['pickup_datetime'] ?? ''));
        $returnRaw = trim((string)($source['return_datetime'] ?? ''));
        if ($pickupRaw === '' || $returnRaw === '') {
            return 'Please select both pickup and return date/time.';
        }

        $pickupTs = strtotime($pickupRaw);
        $returnTs = strtotime($returnRaw);
        if ($pickupTs === false || $returnTs === false) {
            return 'Invalid pickup/return datetime provided.';
        }

        $minPickupTs = time() + (24 * 60 * 60);
        if ($pickupTs < $minPickupTs) {
            return 'Bookings must be made at least 24 hours in advance.';
        }

        if ($returnTs <= $pickupTs) {
            return 'Return date/time must be after pickup date/time.';
        }

        $pickupMins = ((int)date('G', $pickupTs) * 60) + (int)date('i', $pickupTs);
        $returnMins = ((int)date('G', $returnTs) * 60) + (int)date('i', $returnTs);
        if ($pickupMins < 510 || $pickupMins > 1050 || $returnMins < 510 || $returnMins > 1050) {
            return 'Pickups and returns are available from 8:30 am to 5:30 pm only.';
        }

        $orderModel = new OrderModel();
        if ($orderModel->isRangeBlocked($pickupRaw, $returnRaw)) {
            return 'Selected date is blocked for online bookings. Please choose another date.';
        }

        return null;
    }

    private function resolveHeardAboutSelection(array $source): array
    {
        $optionIdRaw = trim((string)($source['heard_about_option_id'] ?? ''));
        $otherText = trim((string)($source['heard_about_other_text'] ?? ''));
        $optionId = (is_numeric($optionIdRaw) && (int)$optionIdRaw > 0) ? (int)$optionIdRaw : null;
        $label = null;

        if ($optionId !== null) {
            try {
                $pdo = \App\Utils\Database::getInstance();
                $stmt = $pdo->prepare("SELECT label FROM heard_about_options WHERE id = ? LIMIT 1");
                $stmt->execute([$optionId]);
                $found = $stmt->fetchColumn();
                if (is_string($found) && trim($found) !== '') {
                    $label = trim($found);
                }
            } catch (\Throwable $e) {
                $label = null;
            }
        }

        if ($label === null && $optionIdRaw === '-1') {
            $label = $otherText !== '' ? $otherText : 'Other';
        }

        return [
            'option_id' => $optionId,
            'label' => $label,
            'raw' => $optionIdRaw,
        ];
    }

    private function getHotelDeliveryFeeById($hotelId): float
    {
        if (!is_numeric($hotelId) || (int)$hotelId <= 0) {
            return 0.0;
        }

        try {
            $pdo = \App\Utils\Database::getInstance();
            $stmt = $pdo->prepare("SELECT delivery_fee FROM partner_hotels WHERE id = ? LIMIT 1");
            $stmt->execute([(int)$hotelId]);
            $fee = $stmt->fetchColumn();
            if ($fee === false || !is_numeric($fee)) {
                return 0.0;
            }
            return round(max(0, (float)$fee), 2);
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    private function resolveDeliveryFeeForInput(array $source): float
    {
        $deliveryType = strtolower(trim((string)($source['delivery_type'] ?? 'hotel')));
        if ($deliveryType !== 'hotel') {
            return 0.0;
        }

        return $this->getHotelDeliveryFeeById($source['hotel_id'] ?? null);
    }

    private function validateHeardAboutSelection(array $source): ?string
    {
        $selection = trim((string)($source['heard_about_option_id'] ?? ''));
        if ($selection === '') {
            return 'Please select where you heard about us.';
        }

        if ($selection === '-1') {
            $otherText = trim((string)($source['heard_about_other_text'] ?? ''));
            if (strlen($otherText) < 2) {
                return 'Please provide details for Other (at least 2 characters).';
            }
            return null;
        }

        if (!is_numeric($selection) || (int)$selection <= 0) {
            return 'Invalid referral source selection.';
        }

        return null;
    }

    private function findRecentlyCreatedStripeOrderId(string $guestEmail, array $cart, array $meta): ?int
    {
        try {
            $productAmount = 0.0;
            foreach ($cart as $item) {
                $qty = max(1, (int)($item['qty'] ?? $item['quantity'] ?? 1));
                $price = (float)($item['price'] ?? 0);
                if ($price <= 0) {
                    continue;
                }
                $productAmount += ($qty * $price);
            }

            if ($productAmount <= 0) {
                return null;
            }

            $deliveryFee = $this->resolveDeliveryFeeForInput($meta);

            // Prefer metadata total when available, otherwise derive by adding mandatory deposit.
            $metaTotal = (float)($meta['total_amount'] ?? 0);
            $totalAmount = $metaTotal > 0
                ? round($metaTotal, 2)
                : (new \App\Services\OrderTotalsService())->calculateFromSubtotal($productAmount, 0.0, self::SECURITY_DEPOSIT, $deliveryFee)['total_amount_with_tax'];

            $pickup = trim((string)($meta['pickup_datetime'] ?? ''));
            $return = trim((string)($meta['return_datetime'] ?? ''));

            $pdo = \App\Utils\Database::getInstance();
            if ($pickup !== '' && $return !== '') {
                $stmt = $pdo->prepare("SELECT order_id FROM orders WHERE payment_method = 'card' AND guest_email = ? AND pickup_datetime = ? AND return_datetime = ? AND ABS(total_amount - ?) < 0.01 AND order_date >= DATE_SUB(NOW(), INTERVAL 60 MINUTE) ORDER BY order_id DESC LIMIT 1");
                $stmt->execute([$guestEmail, $pickup, $return, $totalAmount]);
                $orderId = $stmt->fetchColumn();
                if ($orderId) {
                    return (int)$orderId;
                }
            }

            $stmt = $pdo->prepare("SELECT order_id FROM orders WHERE payment_method = 'card' AND guest_email = ? AND ABS(total_amount - ?) < 0.01 AND order_date >= DATE_SUB(NOW(), INTERVAL 60 MINUTE) ORDER BY order_id DESC LIMIT 1");
            $stmt->execute([$guestEmail, $totalAmount]);
            $orderId = $stmt->fetchColumn();
            if ($orderId) {
                return (int)$orderId;
            }

            // Backward-compatible fallback for pre-deposit rows.
            $stmt = $pdo->prepare("SELECT order_id FROM orders WHERE payment_method = 'card' AND guest_email = ? AND ABS(total_amount - ?) < 0.01 AND order_date >= DATE_SUB(NOW(), INTERVAL 60 MINUTE) ORDER BY order_id DESC LIMIT 1");
            $stmt->execute([$guestEmail, round($productAmount, 2)]);
            $orderId = $stmt->fetchColumn();
            return $orderId ? (int)$orderId : null;
        } catch (\Throwable $e) {
            error_log('Finalize payment recovery lookup error: ' . $e->getMessage());
            return null;
        }
    }

    public function __construct()
    {
        $PAYPAL_CLIENT_ID = getenv("PAYPAL_CLIENT_ID") ?: ($_ENV["PAYPAL_CLIENT_ID"] ?? '');
        $PAYPAL_CLIENT_SECRET = getenv("PAYPAL_CLIENT_SECRET") ?: ($_ENV["PAYPAL_CLIENT_SECRET"] ?? '');
        $paypalMode = strtolower(trim((string)(getenv('PAYPAL_MODE') ?: ($_ENV['PAYPAL_MODE'] ?? 'sandbox'))));
        $paypalEnvironment = $paypalMode === 'live' ? Environment::PRODUCTION : Environment::SANDBOX;

        $this->paypalClient = PaypalServerSdkClientBuilder::init()
            ->clientCredentialsAuthCredentials(
                ClientCredentialsAuthCredentialsBuilder::init(
                    $PAYPAL_CLIENT_ID,
                    $PAYPAL_CLIENT_SECRET
                )
            )
            ->environment($paypalEnvironment)
            ->build();
    }

    public function processCheckout() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
                http_response_code(403);
                die('Invalid CSRF token');
            }
            $this->enforcePublicJsonRateLimit('checkout_cod_order', 5, 15);

            $cart = json_decode($_POST['cart'] ?? '[]', true);
            $pickup_datetime = $_POST['pickup_datetime'] ?? null;
            $return_datetime = $_POST['return_datetime'] ?? null;
            $orderModel = new OrderModel();
            if (!$orderModel->isCartAvailable($cart, $pickup_datetime, $return_datetime)) {
                http_response_code(409);
                echo json_encode(['error' => 'One or more items in your cart are no longer available for the selected dates. Please update your cart.']);
                exit;
            }

            // Validate required fields (including explicit delivery destination selection)
            $deliveryType = $_POST['delivery_type'] ?? 'preferred';
            if (empty($_POST['first_name']) || empty($_POST['last_name']) || empty($_POST['phone']) || empty($_POST['email']) || empty($_POST['payment']) || empty($_POST['client_weight_option']) || empty($_POST['client_height']) || empty($cart)) {
                echo "Missing required checkout fields.";
                exit;
            }

            $clientWeightLbs = trim((string)($_POST['client_weight_option'] ?? ''));
            if ($clientWeightLbs === '' || !ctype_digit($clientWeightLbs) || (int)$clientWeightLbs <= 0) {
                echo "Please provide a valid customer weight in lbs.";
                exit;
            }

            $bookingWindowError = $this->validateBookingWindowConstraints($_POST);
            if ($bookingWindowError !== null) {
                http_response_code(422);
                echo $bookingWindowError;
                exit;
            }

            $handednessError = $this->validatePowerChairHandednessRequirement($_POST, is_array($cart) ? $cart : []);
            if ($handednessError !== null) {
                http_response_code(422);
                echo $handednessError;
                exit;
            }

            $heardAboutError = $this->validateHeardAboutSelection($_POST);
            if ($heardAboutError !== null) {
                http_response_code(422);
                echo $heardAboutError;
                exit;
            }

            $idPresenceError = $this->validateIdPresenceAcknowledgement($_POST);
            if ($idPresenceError !== null) {
                http_response_code(422);
                echo $idPresenceError;
                exit;
            }

            if ($deliveryType === 'pickup') {
                if (empty($_POST['pickup_location'])) {
                    echo "Please select a pickup store.";
                    exit;
                }
            } elseif ($deliveryType === 'hotel') {
                if (empty($_POST['hotel_id'])) {
                    echo "Please select a partner hotel.";
                    exit;
                }
                if (empty($_POST['return_hotel_id'])) {
                    echo "Please select a return hotel/address.";
                    exit;
                }
            } else {
                if (empty($_POST['address1']) || empty($_POST['state']) || empty($_POST['zip'])) {
                    echo "Missing required delivery address fields.";
                    exit;
                }
            }
            // Always set guest fields for both guest and logged-in users
            $postData = $_POST;
            $postData['guest_first_name'] = $_POST['first_name'] ?? '';
            $postData['guest_last_name']  = $_POST['last_name'] ?? '';
            $postData['guest_email']      = $_POST['email'] ?? '';
            $postData['guest_phone']      = $_POST['phone'] ?? '';
            $orderId = $orderModel->fullOrderProcess($postData, $cart, $_SESSION);
            // For COD, show confirmation in the same view
            if (isset($postData['payment']) && $postData['payment'] === 'cod') {
                $order = $orderModel->getOrderById($orderId);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'cod' => true,
                    'order' => $order
                ]);
                exit;
            } else {
                $token = bin2hex(random_bytes(32));
                $_SESSION["order_token_{$orderId}"] = $token;
                header("Location: /checkout?order={$orderId}&token={$token}");
                exit;
            }
        }
    }

    public function completeOrder() {
        $this->ensureAdminAuthenticatedRedirect('/admin/login');
        if (session_status() === PHP_SESSION_NONE) session_start();
        $this->requireAdminRoleRedirect(['superadmin', 'admin', 'staff'], '/admin/orders');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
                http_response_code(403);
                die('Invalid CSRF token');
            }
        }

        $orderId = isset($_POST['order_id']) ? intval($_POST['order_id']) : null;
        if (!$orderId) {
            $_SESSION['order_complete_message'] = "Order ID missing.";
            header("Location: /admin/orders");
            exit;
        }

        $orderModel = new OrderModel();
        $transitionCheck = $orderModel->getOrderStatusTransitionCheck($orderId, 'completed');
        if (!($transitionCheck['allowed'] ?? false)) {
            $_SESSION['order_cancel_message'] = (string)($transitionCheck['error'] ?? 'This order cannot be completed right now.');
            header("Location: /admin/orders");
            exit;
        }

        $messages = $orderModel->completeOrderProcess($orderId);
        $this->logAdminAction('order_completed', 'order', $orderId, [
            'previous_status' => $transitionCheck['current_status'] ?? null,
            'new_status' => 'completed',
            'messages' => $messages,
            'handler' => 'order_controller',
        ]);
        $_SESSION['order_complete_message'] = implode("<br>", $messages);
        header("Location: /admin/orders");
        exit;
    }

    public function cancelOrder() {
        $this->ensureAdminAuthenticatedRedirect('/admin/login');
        if (session_status() === PHP_SESSION_NONE) session_start();
        $this->requireAdminRoleRedirect(['superadmin', 'admin'], '/admin/orders');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
                http_response_code(403);
                die('Invalid CSRF token');
            }
        }

        $orderId = isset($_POST['order_id']) ? intval($_POST['order_id']) : null;
        if (!$orderId) {
            $_SESSION['order_cancel_message'] = "Order ID missing.";
            header("Location: /admin/orders");
            exit;
        }

        $orderModel = new OrderModel();
        $transitionCheck = $orderModel->getOrderStatusTransitionCheck($orderId, 'cancelled');
        if (!($transitionCheck['allowed'] ?? false)) {
            $_SESSION['order_cancel_message'] = (string)($transitionCheck['error'] ?? 'This order cannot be cancelled right now.');
            header("Location: /admin/orders");
            exit;
        }

        $message = $orderModel->cancelOrderProcess($orderId);
        $this->logAdminAction('order_cancelled', 'order', $orderId, [
            'previous_status' => $transitionCheck['current_status'] ?? null,
            'new_status' => 'cancelled',
            'message' => $message,
        ]);
        $_SESSION['order_cancel_message'] = $message;
        header("Location: /admin/orders");
        exit;
    }

    public function ajaxOrderDetails() {
        $this->ensureAdminAuthenticatedJson();
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        if (empty($_GET['order_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Order ID required']);
            exit;
        }

        $orderId = intval($_GET['order_id']);
        if ($orderId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid order ID']);
            exit;
        }

        $orderModel = new OrderModel();
        $details = $orderModel->getOrderDetails($orderId);
        if (!is_array($details) || (isset($details['order']) && empty($details['order']))) {
            http_response_code(404);
            echo json_encode(['error' => 'Order not found']);
            exit;
        }

        echo json_encode($details);
        exit;
    }

    public function downloadOrderDocument() {
        $this->ensureAdminAuthenticatedRedirect('/admin/login');

        $orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
        $type = strtolower(trim((string)($_GET['type'] ?? '')));
        $allowedTypes = ['contract', 'invoice', 'proforma'];
        if ($orderId <= 0 || !in_array($type, $allowedTypes, true)) {
            http_response_code(400);
            echo 'Invalid document request.';
            exit;
        }

        $orderModel = new OrderModel();
        $path = $orderModel->resolveOrderDocumentPath($orderId, $type);
        if (!$path || !is_file($path) || !is_readable($path)) {
            http_response_code(404);
            echo 'Document not found.';
            exit;
        }

        $fileName = $type . '-' . $orderId . '.pdf';
        $this->logAdminAction('order_document_downloaded', 'order', $orderId, [
            'document_type' => $type,
            'filename' => $fileName,
        ]);
        header('Content-Type: application/pdf');
        header('Content-Length: ' . (string)filesize($path));
        header('Content-Disposition: inline; filename="' . $fileName . '"');
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
        readfile($path);
        exit;
    }


    // STRIPE CHECKOUT SESSION CREATION
    public function createCheckoutSession()
    {
        // CRITICAL: Set headers BEFORE anything else can execute
        ob_start();
        @header('Content-Type: application/json; charset=utf-8');
        
        try {
            if (session_status() === PHP_SESSION_NONE) session_start();
            $this->enforcePublicJsonRateLimit('create_checkout_session', 10, 15);
            
            $orderModel = new OrderModel();
            $result = $orderModel->createStripeCheckoutSession($_POST, $_SESSION);
            
            // Clear any stray output and return JSON
            ob_end_clean();
            ob_start();
            http_response_code(isset($result['error']) ? 400 : 200);
            echo json_encode($result);
            ob_end_flush();
        } catch (\Throwable $e) {
            // Capture and suppress any output
            ob_end_clean();
            ob_start();
            http_response_code(500);
            error_log('Checkout session exception: ' . $e->getMessage());
            echo json_encode(['error' => 'Stripe checkout failed']);
            ob_end_flush();
        }
        exit;
    }

    public function createPaymentIntent()
    {
        // CRITICAL: Set headers BEFORE anything else can execute
        ob_start();
        @header('Content-Type: application/json; charset=utf-8');
        
        try {
            if (session_status() === PHP_SESSION_NONE) session_start();
            $this->enforcePublicJsonRateLimit('create_payment_intent', 10, 15);

            $cart = json_decode($_POST['cart'] ?? '[]', true);
            if (trim((string)($_POST['client_height'] ?? '')) === '') {
                ob_end_clean();
                ob_start();
                http_response_code(422);
                echo json_encode(['error' => 'Please select the client height.']);
                ob_end_flush();
                exit;
            }
            $bookingWindowError = $this->validateBookingWindowConstraints($_POST);
            if ($bookingWindowError !== null) {
                ob_end_clean();
                ob_start();
                http_response_code(422);
                echo json_encode(['error' => $bookingWindowError]);
                ob_end_flush();
                exit;
            }

            // Do not block Stripe Payment Element initialization based on acknowledgement checkboxes.
            // Acknowledgements are enforced before final order creation in finalize and checkout flows.

            $handednessError = $this->validatePowerChairHandednessRequirement($_POST, is_array($cart) ? $cart : []);
            if ($handednessError !== null) {
                ob_end_clean();
                ob_start();
                http_response_code(422);
                echo json_encode(['error' => $handednessError]);
                ob_end_flush();
                exit;
            }

            $checkoutRef = bin2hex(random_bytes(8));
            $postData = $_POST;
            $postData['checkout_ref'] = $checkoutRef;

            // Keep full checkout payload server-side; Stripe metadata stays compact.
            $_SESSION["stripe_checkout_payload_{$checkoutRef}"] = $this->getStripeFallbackPayloadFromPost($postData);
            
            $orderModel = new OrderModel();
            $result = $orderModel->createStripePaymentIntent($postData, $_SESSION);

            // Store a fallback payload so finalize can continue even if Stripe metadata is missing/truncated.
            if (!isset($result['error']) && !empty($result['paymentIntentId'])) {
                $intentId = (string)$result['paymentIntentId'];
                $_SESSION["stripe_intent_fallback_{$intentId}"] = $this->getStripeFallbackPayloadFromPost($postData);
                $_SESSION["stripe_checkout_ref_by_intent_{$intentId}"] = $checkoutRef;

                $payloadSnapshot = $this->getStripeFallbackPayloadFromPost($postData);
                $cartSnapshot = json_decode((string)($payloadSnapshot['cart_json'] ?? '[]'), true);
                $this->persistCheckoutSessionSnapshot(
                    'stripe',
                    $checkoutRef,
                    $intentId,
                    $payloadSnapshot,
                    is_array($cartSnapshot) ? $cartSnapshot : [],
                    'intent_created'
                );
            }
            
            // Clear any stray output and return JSON
            ob_end_clean();
            ob_start();
            http_response_code(isset($result['error']) ? 400 : 200);
            echo json_encode($result);
            ob_end_flush();
        } catch (\Throwable $e) {
            // Capture and suppress any output
            ob_end_clean();
            ob_start();
            http_response_code(500);
            error_log('Payment intent exception: ' . $e->getMessage());
            echo json_encode(['error' => 'Payment initialization failed']);
            ob_end_flush();
        }
        exit;
    }

    public function finalizeStripePayment()
    {
        // CRITICAL: Set headers BEFORE anything else can execute
        ob_start();
        @header('Content-Type: application/json; charset=utf-8');
        
        try {
            if (session_status() === PHP_SESSION_NONE) session_start();
            $this->enforcePublicJsonRateLimit('stripe_finalize_payment', 20, 15);
            
            // Get payment intent ID from JSON body or POST
            $input = $this->readJsonBody();
            $paymentIntentId = $input['payment_intent_id'] ?? $_POST['payment_intent_id'] ?? null;
            $paymentIntentId = is_string($paymentIntentId) ? trim($paymentIntentId) : '';
            
            if (!$paymentIntentId) {
                http_response_code(400);
                echo json_encode(['error' => 'Payment intent ID missing']);
                ob_end_flush();
                exit;
            }

            if (strpos($paymentIntentId, 'pi_') !== 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid payment intent ID']);
                ob_end_flush();
                exit;
            }
            
            // Call the finalization logic
            $result = $this->finalizeStripePaymentIntentById($paymentIntentId, is_array($input) ? $input : []);
            
            // Clear any stray output and return JSON
            ob_end_clean();
            ob_start();
            $statusCode = isset($result['error']) ? (int)($result['http_status'] ?? 400) : 200;
            http_response_code($statusCode);
            echo json_encode($result);
            ob_end_flush();
        } catch (\Throwable $e) {
            // Capture and suppress any output
            ob_end_clean();
            ob_start();
            http_response_code(500);
            error_log('Finalize payment exception: ' . $e->getMessage());
            echo json_encode(['error' => 'Payment processing failed. Please contact support if this persists.']);
            ob_end_flush();
        }
        exit;
    }

    private function finalizeStripePaymentIntentById($paymentIntentId, array $requestInput = [])
    {
        if (!$paymentIntentId) {
            return ['error' => 'Payment intent ID missing'];
        }

        $stripeSecret = $_ENV['STRIPE_SECRET_KEY'] ?? null;
        if (!$stripeSecret) {
            return ['error' => 'Stripe secret not configured'];
        }

        \Stripe\Stripe::setApiKey($stripeSecret);

        try {
            $intent = \Stripe\PaymentIntent::retrieve($paymentIntentId);
        } catch (\Exception $e) {
            error_log('Stripe retrieve intent error: ' . $e->getMessage());
            return ['error' => 'Unable to verify payment right now. Please try again.', 'http_status' => 400];
        }

        if (($intent->status ?? '') !== 'succeeded') {
            return ['error' => 'Payment is not completed yet.', 'http_status' => 409];
        }

        $meta = [];
        if (isset($intent->metadata)) {
            if (is_object($intent->metadata) && method_exists($intent->metadata, 'toArray')) {
                $meta = $intent->metadata->toArray();
            } else {
                $meta = (array)$intent->metadata;
            }
        }

        // Fallback for metadata limits/truncation: use server-side session snapshot from intent creation.
        $fallbackKey = "stripe_intent_fallback_{$paymentIntentId}";
        $fallbackMeta = $_SESSION[$fallbackKey] ?? [];
        if (!is_array($fallbackMeta)) {
            $fallbackMeta = [];
        }
        if (!empty($fallbackMeta)) {
            $meta = array_merge($fallbackMeta, $meta);
        }

        // Resolve full checkout payload via compact metadata reference token.
        $checkoutRef = trim((string)($meta['checkout_ref'] ?? ''));
        if ($checkoutRef === '') {
            $mappedRef = $_SESSION["stripe_checkout_ref_by_intent_{$paymentIntentId}"] ?? '';
            $checkoutRef = is_string($mappedRef) ? trim($mappedRef) : '';
        }

        $orderModel = new OrderModel();

        $dbCheckoutSession = $orderModel->getCheckoutSessionByPaymentIntentId((string)$paymentIntentId);
        if (is_array($dbCheckoutSession)) {
            if ($checkoutRef === '') {
                $checkoutRef = trim((string)($dbCheckoutSession['checkout_ref'] ?? ''));
            }

            $payloadJson = (string)($dbCheckoutSession['payload_json'] ?? '');
            if ($payloadJson !== '') {
                $dbPayload = json_decode($payloadJson, true);
                if (is_array($dbPayload) && !empty($dbPayload)) {
                    $meta = array_merge($dbPayload, $meta);
                }
            }

            $cartJson = (string)($dbCheckoutSession['cart_json'] ?? '');
            if ($cartJson !== '' && empty($meta['cart_json'])) {
                $meta['cart_json'] = $cartJson;
            }
        }

        if ($checkoutRef !== '') {
            $sessionPayload = $_SESSION["stripe_checkout_payload_{$checkoutRef}"] ?? [];
            if (is_array($sessionPayload) && !empty($sessionPayload)) {
                $meta = array_merge($sessionPayload, $meta);
            }
        }

        $snapshot = $requestInput['checkout_snapshot'] ?? null;
        if (is_array($snapshot)) {
            $allowedSnapshotKeys = [
                'first_name', 'last_name', 'email', 'phone',
                'client_weight_option', 'client_height',
                'address1', 'address2', 'state', 'zip',
                'delivery_type', 'hotel_id', 'return_hotel_id',
                'pickup_datetime', 'return_datetime', 'pickup_location',
                'notes', 'heard_about_option_id', 'heard_about_other_text',
                'sale_type'
            ];

            foreach ($allowedSnapshotKeys as $key) {
                if (array_key_exists($key, $snapshot)) {
                    $meta[$key] = trim((string)$snapshot[$key]);
                }
            }
        }

        // Prefer the most recent acknowledgement values from finalize request.
        if (array_key_exists('acknowledge_id_presence', $requestInput)) {
            $meta['acknowledge_id_presence'] = (string)$requestInput['acknowledge_id_presence'];
        }
        if (array_key_exists('agree_policy', $requestInput)) {
            $meta['agree_policy'] = (string)$requestInput['agree_policy'];
        }
        if (array_key_exists('cart_json', $requestInput)) {
            $meta['cart_json'] = (string)$requestInput['cart_json'];
        }

        $cart = json_decode($meta['cart_json'] ?? '[]', true);
        $guestEmail = filter_var(trim($meta['guest_email'] ?? ''), FILTER_VALIDATE_EMAIL);
        if (!is_array($cart) || empty($cart)) {
            return ['error' => 'Missing Stripe cart details.', 'http_status' => 422];
        }
        if (!$guestEmail) {
            return ['error' => 'Missing or invalid customer email for Stripe confirmation.', 'http_status' => 422];
        }

        // Normalize cart shape so downstream order logic always has qty.
        $normalizedCart = [];
        foreach ($cart as $item) {
            $qty = max(1, (int)($item['qty'] ?? $item['quantity'] ?? 1));
            $normalizedCart[] = array_merge($item, [
                'qty' => $qty,
                'quantity' => $qty,
            ]);
        }
        $cart = $normalizedCart;

        // Finalization is idempotent by payment intent id.
        $intentOrderSessionKey = "stripe_order_by_intent_{$paymentIntentId}";
        $orderId = $_SESSION[$intentOrderSessionKey] ?? null;

        $chargeId = null;
        if (isset($intent->latest_charge)) {
            if (is_string($intent->latest_charge)) {
                $chargeId = $intent->latest_charge;
            } elseif (is_object($intent->latest_charge) && isset($intent->latest_charge->id)) {
                $chargeId = (string)$intent->latest_charge->id;
            }
        }

        if (!$orderId) {
            $orderId = $orderModel->findOrderIdByProviderReference([
                'payment_provider' => 'stripe',
                'provider_payment_intent_id' => (string)$paymentIntentId,
                'provider_charge_id' => $chargeId,
            ]);
            if ($orderId) {
                $_SESSION[$intentOrderSessionKey] = $orderId;
            }
        }

        if (!$orderId) {
            $postData = [
                'first_name' => $meta['first_name'] ?? '',
                'last_name' => $meta['last_name'] ?? '',
                'email' => $guestEmail,
                'phone' => $meta['guest_phone'] ?? '',
                'client_weight_option' => $meta['client_weight_option'] ?? '',
                'client_weight_lbs' => $meta['client_weight_lbs'] ?? '',
                'client_height' => $meta['client_height'] ?? '',
                'power_chair_handedness' => $meta['power_chair_handedness'] ?? '',
                'address1' => $meta['address1'] ?? '',
                'address2' => $meta['address2'] ?? '',
                'state' => $meta['state'] ?? '',
                'zip' => $meta['zip'] ?? '',
                'delivery_type' => $meta['delivery_type'] ?? 'preferred',
                'hotel_id' => $meta['hotel_id'] ?? '',
                'return_hotel_id' => $meta['return_hotel_id'] ?? '',
                'pickup_datetime' => $meta['pickup_datetime'] ?? '',
                'return_datetime' => $meta['return_datetime'] ?? '',
                'pickup_location' => $meta['pickup_location'] ?? '',
                'notes' => $meta['notes'] ?? '',
                'heard_about_option_id' => $meta['heard_about_option_id'] ?? '',
                'heard_about_other_text' => $meta['heard_about_other_text'] ?? '',
                'acknowledge_id_presence' => $meta['acknowledge_id_presence'] ?? '',
                'sale_type' => $meta['sale_type'] ?? 'rental',
                'guest_first_name' => $meta['first_name'] ?? '',
                'guest_last_name' => $meta['last_name'] ?? '',
                'guest_email' => $guestEmail,
                'guest_phone' => $meta['guest_phone'] ?? '',
                'created_by_admin_id' => $meta['created_by_admin_id'] ?? '',
                'created_by_admin_role' => $meta['created_by_admin_role'] ?? '',
                'created_by_admin_name' => $meta['created_by_admin_name'] ?? '',
                'payment' => 'card',
                'provider_payment_intent_id' => (string)$paymentIntentId,
                'provider_charge_id' => $chargeId,
            ];

            $bookingWindowError = $this->validateBookingWindowConstraints($postData);
            if ($bookingWindowError !== null) {
                return ['error' => $bookingWindowError, 'http_status' => 422];
            }

            if (trim((string)($postData['client_height'] ?? '')) === '') {
                return ['error' => 'Please select the client height.', 'http_status' => 422];
            }

            $idPresenceError = $this->validateIdPresenceAcknowledgement($postData);
            if ($idPresenceError !== null) {
                return ['error' => $idPresenceError, 'http_status' => 422];
            }

            $handednessError = $this->validatePowerChairHandednessRequirement($postData, $cart);
            if ($handednessError !== null) {
                return ['error' => $handednessError, 'http_status' => 422];
            }

            try {
                $orderId = $orderModel->fullOrderProcess($postData, $cart, $_SESSION);
            } catch (\Throwable $e) {
                error_log('Finalize payment fullOrderProcess error: ' . $e->getMessage());
                $orderId = $orderModel->findOrderIdByProviderReference([
                    'payment_provider' => 'stripe',
                    'provider_payment_intent_id' => (string)$paymentIntentId,
                    'provider_charge_id' => $chargeId,
                ]);
                if (!$orderId) {
                    $orderId = $this->findRecentlyCreatedStripeOrderId($guestEmail, $cart, $meta);
                }
                if (!$orderId) {
                    $this->persistCheckoutSessionSnapshot(
                        'stripe',
                        $checkoutRef,
                        (string)$paymentIntentId,
                        $meta,
                        is_array($cart) ? $cart : [],
                        'finalize_failed',
                        null,
                        'Could not store the Stripe order after payment.'
                    );
                    return ['error' => 'Could not store the Stripe order after payment.', 'http_status' => 500];
                }
            }

            if (!$orderId) {
                $orderId = $orderModel->findOrderIdByProviderReference([
                    'payment_provider' => 'stripe',
                    'provider_payment_intent_id' => (string)$paymentIntentId,
                    'provider_charge_id' => $chargeId,
                ]);
                if (!$orderId) {
                    $orderId = $this->findRecentlyCreatedStripeOrderId($guestEmail, $cart, $meta);
                }
                if (!$orderId) {
                    $this->persistCheckoutSessionSnapshot(
                        'stripe',
                        $checkoutRef,
                        (string)$paymentIntentId,
                        $meta,
                        is_array($cart) ? $cart : [],
                        'finalize_failed',
                        null,
                        'Could not store the Stripe order after payment.'
                    );
                    return ['error' => 'Could not store the Stripe order after payment.', 'http_status' => 500];
                }
            }

            $_SESSION[$intentOrderSessionKey] = $orderId;

            try {
                $orderModel->markAsPaid($orderId);
            } catch (\Throwable $e) {
                error_log('Finalize payment markAsPaid error: ' . $e->getMessage());
                return ['error' => 'Order created, but payment status update failed.', 'http_status' => 500];
            }

        }

        try {
            $orderModel->saveOrderPaymentProviderReferences((int)$orderId, [
                'payment_provider' => 'stripe',
                'provider_payment_intent_id' => (string)$paymentIntentId,
                'provider_charge_id' => $chargeId,
            ]);
            $orderModel->recordPaymentEvent([
                'order_id' => (int)$orderId,
                'checkout_ref' => $checkoutRef,
                'payment_provider' => 'stripe',
                'event_type' => 'payment_finalized',
                'provider_reference' => $chargeId ?: (string)$paymentIntentId,
                'amount' => isset($intent->amount_received) ? ((float)$intent->amount_received / 100) : null,
                'payload_json' => json_encode($this->deepJsonSerialize($intent), JSON_UNESCAPED_SLASHES),
            ]);
            $this->persistCheckoutSessionSnapshot(
                'stripe',
                $checkoutRef,
                (string)$paymentIntentId,
                $meta,
                is_array($cart) ? $cart : [],
                'finalized',
                (int)$orderId,
                null
            );
        } catch (\Throwable $e) {
            error_log('Finalize payment provider reference update warning: ' . $e->getMessage());
            $existingOrderId = $orderModel->findOrderIdByProviderReference([
                'payment_provider' => 'stripe',
                'provider_payment_intent_id' => (string)$paymentIntentId,
                'provider_charge_id' => $chargeId,
            ]);
            if ($existingOrderId) {
                $orderId = $existingOrderId;
                $_SESSION[$intentOrderSessionKey] = $orderId;
            }
        }

        // Always ensure docs/email after finalize (safe to re-run).
        try {
            $orderModel->ensureOrderDocumentsAndEmail($orderId, $cart);
        } catch (\Throwable $e) {
            // Non-fatal: the order is already paid/created. Keep checkout success path alive.
            error_log('Finalize payment post-processing warning: ' . $e->getMessage());
        }

        $token = $_SESSION["order_token_$orderId"] ?? null;
        if (!$token) {
            $token = bin2hex(random_bytes(16));
            $_SESSION["order_token_$orderId"] = $token;
        }

        // Cleanup fallback payload once finalization succeeds.
        unset($_SESSION[$fallbackKey]);
        unset($_SESSION["stripe_checkout_ref_by_intent_{$paymentIntentId}"]);
        if ($checkoutRef !== '') {
            unset($_SESSION["stripe_checkout_payload_{$checkoutRef}"]);
        }

        return [
            'success' => true,
            'orderId' => $orderId,
            'redirectUrl' => "/checkout?order=$orderId&token=$token",
        ];
    }

    public function stripeReturn() {
        if (session_status() === PHP_SESSION_NONE) session_start();

        $paymentIntentId = $_GET['payment_intent'] ?? null;
        if ($paymentIntentId) {
            $result = $this->finalizeStripePaymentIntentById($paymentIntentId);
            if (!isset($result['error']) && !empty($result['redirectUrl'])) {
                header('Location: ' . $result['redirectUrl']);
                exit;
            }
        }

        header('Location: /checkout');
        exit;
    }

    public function checkoutSuccess()
    {
       require __DIR__ . '/../Views/checkout-success.php';
    }

    public function checkoutCancel()
    {
        require __DIR__ . '/../Views/checkout-cancel.php';
    }


    // Webhook endpoint: verify signature and create the order in DB
    public function stripeWebhook()
    {
        // TOP-LEVEL DEBUG: Confirm webhook handler is being executed and log file can be created
        $myfile = $this->openDebugLog('stripe-webhook-logs.txt');
        if ($myfile) {
            fwrite($myfile, date('Y-m-d H:i:s') . " [DEBUG] stripeWebhook handler ENTERED\n");
        } else {
            error_log("[ERROR] Unable to open stripe-webhook-logs.txt for writing");
        }
   

        
        // require_once __DIR__ . '/../../vendor/autoload.php';
        $payload = @file_get_contents('php://input');
        fwrite($myfile, "PAYLOAD: \n"  . $payload . "\n");
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        fwrite($myfile, "SIGNATURE HEADER: \n"  . $sig_header . "\n");
        $endpoint_secret = $_ENV['STRIPE_WEBHOOK_SECRET'];

        if (!$endpoint_secret) {
            http_response_code(500);
            fwrite($myfile, "ENDPOINT SECRET NOT FOUND" . "\n");
            fclose($myfile);
            exit;
        }

        try {
            $event = Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
            fwrite($myfile, "EVENT ENTERED: " . $event->type . "\n");
        } catch (SignatureVerificationException $e) {
            http_response_code(400);
            fwrite($myfile, "SIGNATURE VERIFICATION FAILED" . "\n");
            fclose($myfile);
            exit;
        } catch (\Exception $e) {
            http_response_code(400);
            fwrite($myfile, "EXCEPTION OCCURRED" . "\n");
            fclose($myfile);
            exit;
        }

        if (in_array($event->type, ['refund.created', 'refund.updated'], true)) {
            $stripeObject = $event->data->object;
            $refundPayload = json_decode(json_encode($stripeObject), true);
            $orderModel = new \App\Models\OrderModel();
            $syncResult = $orderModel->syncStripeRefundFromWebhook(is_array($refundPayload) ? $refundPayload : []);
            if (isset($myfile) && is_resource($myfile)) {
                fwrite($myfile, "[DEBUG] Stripe refund webhook sync result: " . print_r($syncResult, true) . "\n");
                fclose($myfile);
            }
            http_response_code(200);
            exit;
        }

        if ($event->type === 'charge.refunded') {
            $stripeObject = $event->data->object;
            $chargePayload = json_decode(json_encode($stripeObject), true);
            $refundItems = $chargePayload['refunds']['data'] ?? [];
            $orderModel = new \App\Models\OrderModel();
            $syncResults = [];

            if (is_array($refundItems)) {
                foreach ($refundItems as $refundItem) {
                    if (!is_array($refundItem)) {
                        continue;
                    }
                    if (empty($refundItem['charge']) && !empty($chargePayload['id'])) {
                        $refundItem['charge'] = (string)$chargePayload['id'];
                    }
                    $syncResults[] = $orderModel->syncStripeRefundFromWebhook($refundItem);
                }
            }

            if (isset($myfile) && is_resource($myfile)) {
                fwrite($myfile, "[DEBUG] Stripe charge.refunded sync results: " . print_r($syncResults, true) . "\n");
                fclose($myfile);
            }
            http_response_code(200);
            exit;
        }

        if ($event->type === 'checkout.session.completed' || $event->type === 'payment_intent.succeeded') {

            
            fwrite($myfile, "EVENT TRIGGERED: " . $event->type . "\n");
            // fwrite($myfile, "EVENT->DATA: " . $event->data . "\n");
            // fwrite($myfile, "EVENT->DATA->OBJECT: " . $event->data->object . "\n");
            $stripeObject = $event->data->object;
            $meta = $stripeObject->metadata ?? [];

            $cart = json_decode($meta->cart_json ?? '[]', true);
            $first_name = htmlspecialchars(trim($meta->first_name ?? ''));
            $last_name = htmlspecialchars(trim($meta->last_name ?? ''));
            $guestEmail = filter_var(trim($meta->guest_email ?? ''), FILTER_VALIDATE_EMAIL);
            $guestPhone = preg_replace('/\D/', '', $meta->guest_phone ?? '');
            $address1 = htmlspecialchars(trim($meta->address1 ?? ''));
            $address2 = htmlspecialchars(trim($meta->address2 ?? ''));
            $state = htmlspecialchars(trim($meta->state ?? ''));
            $zip = htmlspecialchars(trim($meta->zip ?? ''));
            $pickup_datetime = htmlspecialchars(trim($meta->pickup_datetime ?? ''));
            $return_datetime = htmlspecialchars(trim($meta->return_datetime ?? ''));
            $delivery_type = htmlspecialchars(trim($meta->delivery_type ?? 'preferred'));
            $hotel_id = isset($meta->hotel_id) && $meta->hotel_id !== '' ? (int)$meta->hotel_id : null;
            $return_hotel_id = isset($meta->return_hotel_id) && $meta->return_hotel_id !== '' ? (int)$meta->return_hotel_id : null;
            $pickupLocation = htmlspecialchars(trim($meta->pickup_location ?? ''));
            $notes = htmlspecialchars(trim($meta->notes ?? ''));
            $heardAboutResolved = $this->resolveHeardAboutSelection([
                'heard_about_option_id' => $meta->heard_about_option_id ?? '',
                'heard_about_other_text' => $meta->heard_about_other_text ?? '',
            ]);
            $heardAboutOptionId = $heardAboutResolved['option_id'];
            $heardAboutLabel = $heardAboutResolved['label'];
            $saleType = htmlspecialchars(trim($meta->sale_type ?? 'rental'));
            $totalAmount = (float)($meta->total_amount ?? 0);
            $metadataSecurityDeposit = isset($meta->security_deposit) ? (float)$meta->security_deposit : null;
            $clientWeightOption = htmlspecialchars(trim($meta->client_weight_option ?? ''));
            $clientWeightLbsRaw = $meta->client_weight_lbs ?? null;
            $clientWeightLbs = is_numeric($clientWeightLbsRaw) ? (int) $clientWeightLbsRaw : null;
            $loggedInUserId = $meta->logged_in_user_id ?? null;
            $createdByAdminId = isset($meta->created_by_admin_id) && $meta->created_by_admin_id !== '' ? (int)$meta->created_by_admin_id : null;
            $createdByAdminRole = strtolower(trim((string)($meta->created_by_admin_role ?? '')));
            $createdByAdminName = trim((string)($meta->created_by_admin_name ?? ''));
            $providerPaymentIntentId = null;
            if (isset($stripeObject->id) && is_string($stripeObject->id) && strpos($stripeObject->id, 'pi_') === 0) {
                $providerPaymentIntentId = $stripeObject->id;
            } elseif (isset($stripeObject->payment_intent) && is_string($stripeObject->payment_intent)) {
                $providerPaymentIntentId = $stripeObject->payment_intent;
            }

            // --- CUSTOMER LOGIC START ---
            // $pdo = \App\Utils\Database::getInstance();
            // if (isset($_SESSION['user_id'])) {
            //     $userId = $_SESSION['user_id'];
            //     $stmt = $pdo->prepare("SELECT name, email FROM users WHERE user_id = ?");
            //     $stmt->execute([$userId]);
            //     $userRow = $stmt->fetch(\PDO::FETCH_ASSOC);
            //     $finalName = $userRow['name'];
            //     $finalEmail = $userRow['email'];
            // } else {
            //     // Guest booking: check if customer exists by email
            //     $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_type = 'customer'");
            //     $stmt->execute([$email]);
            //     $userId = $stmt->fetchColumn();

            //     if (!$userId) {
            //         $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, address, user_type, created_at) VALUES (?, ?, ?, ?, 'customer', NOW())");
            //         $fullAddress = $address1 . ($address2 ? " " . $address2 : "");
            //         $stmt->execute([$name, $email, $phone, $fullAddress]);
            //         $userId = $pdo->lastInsertId();
            //     }
            //     $finalName = $name;
            //     $finalEmail = $email;
            // }
            // --- CUSTOMER LOGIC END ---

            // --- TOTAL AMOUNT + TAX COMPUTATION ---
            $totalAmount = 0;
            foreach ($cart as $item) {
                $totalAmount += $item['qty'] * $item['price'];
            }
            $productTotalWithTax = round($totalAmount, 2);
            $securityDeposit = $metadataSecurityDeposit !== null
                ? round(max(0, $metadataSecurityDeposit), 2)
                : self::SECURITY_DEPOSIT;
            $deliveryFee = $this->resolveDeliveryFeeForInput([
                'delivery_type' => $delivery_type,
                'hotel_id' => $hotel_id,
            ]);
            $totals = (new \App\Services\OrderTotalsService())->calculateFromSubtotal($productTotalWithTax, 0.0, $securityDeposit, $deliveryFee);
            $totalAmountWithTax = $totals['total_amount_with_tax'];
            $totalAmount = $totals['total_amount'];

            
            if (isset($myfile) && is_resource($myfile)) {
                fwrite($myfile, "GUEST_FIRST NAME: {$first_name}\n");
                fwrite($myfile, "GUEST_LAST NAME: {$last_name}\n");
                fwrite($myfile, "GUEST_EMAIL: {$guestEmail}\n");
                fwrite($myfile, "GUEST_PHONE: {$guestPhone}\n");
                fwrite($myfile, "ADDRESS1: {$address1}\n");
                fwrite($myfile, "PICKUP_DATETIME: {$pickup_datetime}\n");
                fwrite($myfile, "RETURN_DATETIME: {$return_datetime}\n");
                fwrite($myfile, "PICKUP_LOCATION: {$pickupLocation}\n");
                fwrite($myfile, "NOTES: {$notes}\n");
                fwrite($myfile, "SALE_TYPE: {$saleType}\n");
                fwrite($myfile, "TOTAL_AMOUNT: {$totalAmount}\n");
                fwrite($myfile, "CART: " . print_r($cart, true) . "\n");
            }

            // --- CUSTOMER LOGIC: Only for guest/user lookup if needed, but not used for new schema ---

            $pdo = \App\Utils\Database::getInstance();
            $existingOrderCheck = $pdo->prepare("SELECT order_id FROM orders WHERE payment_method = 'card' AND guest_email = ? AND pickup_datetime <=> ? AND return_datetime <=> ? AND ABS(total_amount - ?) < 0.01 ORDER BY order_id DESC LIMIT 1");
            $existingOrderCheck->execute([
                $guestEmail,
                $pickup_datetime ?: null,
                $return_datetime ?: null,
                $totalAmount,
            ]);
            $existingOrderId = $existingOrderCheck->fetchColumn();
            if ($existingOrderId) {
                fwrite($myfile, "[DEBUG] Existing Stripe order already stored for this payment context. orderId: $existingOrderId\n");
                http_response_code(200);
                fclose($myfile);
                exit;
            }

            // --- CREATE ORDER USING NEW SCHEMA ---
            try {
                fwrite($myfile, "[DEBUG] About to begin transaction and insert order\n");
                new \App\Models\OrderModel();
                $pdo->beginTransaction();
                $stmt = $pdo->prepare(
                    "INSERT INTO orders (
                        user_id, guest_first_name, guest_last_name, guest_email, guest_phone, client_weight_option, client_weight_lbs, client_height, address1, address2, state, zip, pickup_datetime, return_datetime, delivery_type, hotel_id, return_hotel_id, pickup_location, notes, heard_about_option_id, heard_about_label, payment_method, payment_provider, provider_payment_intent_id, total_amount, security_deposit, delivery_fee, status, order_date, customer_type, booking_source, created_by_admin_id, created_by_admin_role, created_by_admin_name, sale_type
                    ) VALUES (
                        :user_id, :guest_first_name, :guest_last_name, :guest_email, :guest_phone, :client_weight_option, :client_weight_lbs, :client_height, :address1, :address2, :state, :zip, :pickup_datetime, :return_datetime, :delivery_type, :hotel_id, :return_hotel_id, :pickup_location, :notes, :heard_about_option_id, :heard_about_label, 'card', 'stripe', :provider_payment_intent_id, :total_amount, :security_deposit, :delivery_fee, 'paid', NOW(), :customer_type, :booking_source, :created_by_admin_id, :created_by_admin_role, :created_by_admin_name, :sale_type
                    )"
                );
                $params = [
                    ':user_id' => $loggedInUserId,
                    ':guest_first_name' => $first_name,
                    ':guest_last_name' => $last_name,
                    ':guest_email' => $guestEmail,
                    ':guest_phone' => $guestPhone,
                    ':client_weight_option' => $clientWeightOption !== '' ? $clientWeightOption : null,
                    ':client_weight_lbs' => $clientWeightLbs,
                    ':client_height' => trim((string)($postData['client_height'] ?? '')) !== '' ? trim((string)($postData['client_height'] ?? '')) : null,
                    ':address1' => $address1,
                    ':address2' => $address2,
                    ':state' => $state,
                    ':zip' => $zip,
                    ':pickup_datetime' => $pickup_datetime,
                    ':return_datetime' => $return_datetime,
                    ':delivery_type' => $delivery_type,
                    ':hotel_id' => $hotel_id,
                    ':return_hotel_id' => $return_hotel_id,
                    ':pickup_location' => $pickupLocation,
                    ':notes' => $notes,
                    ':heard_about_option_id' => $heardAboutOptionId,
                    ':heard_about_label' => $heardAboutLabel,
                    ':provider_payment_intent_id' => $providerPaymentIntentId,
                    ':total_amount' => $totalAmount,
                    ':security_deposit' => $securityDeposit,
                    ':delivery_fee' => $deliveryFee,
                    ':customer_type' => $loggedInUserId ? 'user' : 'guest',
                    ':booking_source' => 'online',
                    ':created_by_admin_id' => $createdByAdminId,
                    ':created_by_admin_role' => $createdByAdminRole !== '' ? $createdByAdminRole : null,
                    ':created_by_admin_name' => $createdByAdminName !== '' ? $createdByAdminName : null,
                    ':sale_type' => $saleType,
                ];
                fwrite($myfile, "[DEBUG] Order insert params: " . print_r($params, true) . "\n");
                $stmt->execute($params);
                $orderId = $pdo->lastInsertId();
                fwrite($myfile, "[DEBUG] Order inserted, orderId: $orderId\n");
                // --- INSERT ORDER ITEMS FOR STRIPE CHECKOUT ---
                if (!empty($cart) && is_array($cart)) {
                    fwrite($myfile, "[DEBUG] Inserting order_items and reservations for orderId: $orderId\n");
                    $pickup = date('Y-m-d H:i:00', strtotime($pickup_datetime));
                    $return = date('Y-m-d H:i:00', strtotime($return_datetime));
                    $reservedScootersGlobal = [];
                    $reservationStmt = $pdo->prepare(
                        "INSERT INTO reservations (scooter_id, pickup_datetime, return_datetime, order_id, status) VALUES (?, ?, ?, ?, 'pending')"
                    );
                    $insufficientStock = false;
                    foreach ($cart as $item) {
                        $pid = $item['id'] ?? null;
                        $qty = max(1, intval($item['qty'] ?? $item['quantity'] ?? 1));
                        $variation_id = isset($item['variation_id']) && $item['variation_id'] !== null && $item['variation_id'] !== '' ? $item['variation_id'] : null;
                        $variation_name = isset($item['variation_name']) && $item['variation_name'] !== null && $item['variation_name'] !== '' ? $item['variation_name'] : null;
                        $price = isset($item['price']) && $item['price'] !== null && $item['price'] !== '' ? $item['price'] : 0;
                        $name = isset($item['name']) && $item['name'] !== null && $item['name'] !== '' ? $item['name'] : '';
                        $image_url = isset($item['image_url']) && $item['image_url'] !== null && $item['image_url'] !== '' ? $item['image_url'] : '';
                        $powerChairHandedness = strtolower(trim((string)($item['power_chair_handedness'] ?? '')));
                        if (!in_array($powerChairHandedness, ['left', 'right'], true)) {
                            $powerChairHandedness = null;
                        }

                        $scooterIdsForItem = [];
                        for ($i = 0; $i < $qty; $i++) {
                            // Find available scooter for this product/variation and dates
                            $params = [$pid];
                            if ($variation_id !== null && $variation_id !== '') {
                                $variationClause = " AND s.variation_id = ?";
                                $params[] = $variation_id;
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
                            // Exclude scooters with any overlapping reservations (pending, confirmed, paid)
                            $sql = "SELECT s.scooter_id FROM scooters s WHERE s.product_id = ?{$variationClause} AND s.status = 'available' AND NOT EXISTS (SELECT 1 FROM reservations r WHERE r.scooter_id = s.scooter_id AND r.status IN ('pending','confirmed','paid') AND NOT (r.return_datetime <= ? OR r.pickup_datetime >= ?)) $excludeClause ORDER BY s.scooter_id ASC LIMIT 1 FOR UPDATE";
                            fwrite($myfile, "[DEBUG] Scooter assignment attempt $i/$qty for product_id=$pid, variation_id=$variation_id, order_id=$orderId\n");
                            fwrite($myfile, "[DEBUG] SQL: $sql\n");
                            fwrite($myfile, "[DEBUG] Params: " . json_encode($params) . "\n");
                            $stmtScooter = $pdo->prepare($sql);
                            $stmtScooter->execute($params);
                            $scooterId = $stmtScooter->fetchColumn();
                            fwrite($myfile, "[DEBUG] scooterId result: " . var_export($scooterId, true) . "\n");
                            if ($scooterId) {
                                $reservedScootersGlobal[] = $scooterId;
                                $scooterIdsForItem[] = $scooterId;
                                $reservationStmt->execute([
                                    $scooterId,
                                    $pickup,
                                    $return,
                                    $orderId
                                ]);
                                fwrite($myfile, "[DEBUG] Inserted reservation: scooter_id=$scooterId, order_id=$orderId, pickup=$pickup, return=$return\n");
                            } else {
                                fwrite($myfile, "[ERROR] No available scooter found for product_id=$pid, variation_id=$variation_id, order_id=$orderId, attempt=$i\n");
                                $insufficientStock = true;
                                break;
                            }
                        }
                        // If not enough scooters, block order and rollback
                        if ($insufficientStock) {
                            $pdo->rollBack();
                            fwrite($myfile, "[ERROR] Order $orderId rolled back due to insufficient stock.\n");
                            fclose($myfile);
                            http_response_code(400);
                            exit('Not enough scooters available for your order.');
                        }
                        // Insert order_items with assigned scooter_ids
                        foreach ($scooterIdsForItem as $scooterId) {
                            fwrite($myfile, "[DEBUG] About to insert order_item: order_id=$orderId, product_id=$pid, variation_id=$variation_id, scooter_id=$scooterId, price=$price, name=$name, image_url=$image_url\n");
                            $stmtItem = $pdo->prepare(
                                "INSERT INTO order_items (order_id, product_id, variation_id, variation_name, power_chair_handedness, scooter_id, quantity, price, product_name, image_url) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?)"
                            );
                            $params = [
                                $orderId,
                                $pid,
                                $variation_id,
                                $variation_name,
                                $powerChairHandedness,
                                $scooterId,
                                $price,
                                $name,
                                $image_url
                            ];
                            fwrite($myfile, "[DEBUG] order_items params: " . print_r($params, true) . "\n");
                            $success = $stmtItem->execute($params);
                            fwrite($myfile, "[DEBUG] order_items execute result: " . var_export($success, true) . "\n");
                            if (!$success) {
                                $errorInfo = $stmtItem->errorInfo();
                                fwrite($myfile, "[ERROR] order_items insert failed: " . print_r($errorInfo, true) . "\n");
                            } else {
                                fwrite($myfile, "[DEBUG] Inserted order_item: order_id=$orderId, product_id=$pid, variation_id=$variation_id, scooter_id=$scooterId, price=$price, name=$name\n");
                            }
                        }
                    }
                    fwrite($myfile, "[DEBUG] Finished inserting order_items and reservations for orderId: $orderId\n");
                } else {
                    fwrite($myfile, "[DEBUG] No cart items found to insert into order_items for orderId: $orderId\n");
                }
                $pdo->commit();
                // Fetch the inserted order for debug, with error checks
                if ($orderId) {
                    try {
                        $pdoDebug = \App\Utils\Database::getInstance();
                        $stmtDebug = $pdoDebug->prepare("SELECT * FROM orders WHERE id = ?");
                        if ($stmtDebug && $stmtDebug->execute([$orderId])) {
                            $orderRow = $stmtDebug->fetch(\PDO::FETCH_ASSOC);
                            if ($orderRow) {
                                fwrite($myfile, "[DEBUG] Order stored successfully in orders table. Details:\n");
                                foreach ($orderRow as $key => $value) {
                                    fwrite($myfile, "    $key: $value\n");
                                }
                            } else {
                                fwrite($myfile, "[DEBUG] Order fetch after insert FAILED for orderId: $orderId\n");
                            }
                        } else {
                            fwrite($myfile, "[DEBUG] Failed to prepare/execute SELECT for orderId: $orderId\n");
                        }
                    } catch (\Exception $ex) {
                        fwrite($myfile, "[ERROR] Exception during order fetch debug for orderId: $orderId: " . $ex->getMessage() . "\n");
                    }
                } else {
                    fwrite($myfile, "[DEBUG] orderId is undefined or false after insert.\n");
                }
                $token = bin2hex(random_bytes(32));
                $_SESSION["order_token_{$orderId}"] = $token;

                // --- PDF & INVOICE GENERATION FOR STRIPE SUCCESSFUL CHECKOUT ---
                // Contract PDF
                $customerName = trim($first_name . ' ' . $last_name);
                $customerEmail = $guestEmail;
                $customerPhone = $guestPhone;
                $customerAddress = $address1 . ($address2 ? " " . $address2 : "");
                $subtotal = 0;
                $itemsTable = '<table class="w-full border border-collapse text-sm"><thead><tr><th class="border px-2 py-1 text-left">Qty</th><th class="border px-2 py-1 text-left">Item</th><th class="border px-2 py-1 text-left">Unit Price</th><th class="border px-2 py-1 text-left">Total</th></tr></thead><tbody>';
                foreach ($cart as $item) {
                    $qty = htmlspecialchars($item['quantity'] ?? 1);
                    $name = htmlspecialchars($item['name']);
                    $unitPrice = '$' . number_format($item['price'], 2);
                    $lineTotal = '$' . number_format(($item['quantity'] ?? 1) * $item['price'], 2);
                    $subtotal += ($item['quantity'] ?? 1) * $item['price'];
                    $itemsTable .= "<tr><td class='border px-2 py-1'>{$qty}</td><td class='border px-2 py-1'>{$name}</td><td class='border px-2 py-1'>{$unitPrice}</td><td class='border px-2 py-1'>{$lineTotal}</td></tr>";
                }
                $itemsTable .= '</tbody></table>';
                $pickupDate = $pickup_datetime ?? '';
                $returnDate = $return_datetime ?? '';
                $productTotalWithTax = round((float)$subtotal, 2);
                $totalAmountWithTax = round($productTotalWithTax + self::SECURITY_DEPOSIT, 2);
                ob_start();
                include __DIR__ . '/../../Contracts/contract-template.php';
                $html = ob_get_clean();
                $options = new \Dompdf\Options();
                $options->set('isRemoteEnabled', true);
                $options->set('isHtml5ParserEnabled', true);
                $dompdf = new \Dompdf\Dompdf($options);
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();
                $pdfDir = dirname(__DIR__, 2) . '/storage/documents/contracts/';
                if (!is_dir($pdfDir)) mkdir($pdfDir, 0777, true);
                file_put_contents($pdfDir . "contract-{$orderId}.pdf", $dompdf->output());
                $pdfPath = $pdfDir . "contract-{$orderId}.pdf";

                // Pro-forma PDF
                $invoiceItemsTable = '';
                foreach ($cart as $item) {
                    $qty = htmlspecialchars($item['quantity'] ?? 1);
                    $name = htmlspecialchars($item['name']);
                    $unitPrice = number_format($item['price'], 2);
                    $lineTotal = number_format(($item['quantity'] ?? 1) * $item['price'], 2);
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
                $orderDate = date('Y-m-d H:i:s');
                $productTotalWithTax = round((float)$subtotal, 2);
                $securityDeposit = self::SECURITY_DEPOSIT;
                $totals = (new \App\Services\OrderTotalsService())->calculateFromSubtotal($productTotalWithTax, 0.0, $securityDeposit, 0.0);
                $totalAmountWithTax = $totals['total_amount_with_tax'];
                $productPreTax = $totals['product_pre_tax'];
                $totalAmount = $totals['total_amount'];
                $tax = $totals['tax'];
                $discountAmount = 0.0;
                $promoCode = (isset($meta) && is_object($meta)) ? (string)($meta->promo_code ?? '') : '';
                $paymentMethod = 'card';
                $pickupLocation = (string)($pickup_location ?? '');
                $deliveryType = (string)($delivery_type ?? '');
                ob_start();
                include __DIR__ . '/../../Proformas/proforma-template.php';
                $invoiceHtml = ob_get_clean();
                $invoiceOptions = new \Dompdf\Options();
                $invoiceOptions->set('isRemoteEnabled', true);
                $invoiceOptions->set('isHtml5ParserEnabled', true);
                $invoiceDompdf = new \Dompdf\Dompdf($invoiceOptions);
                $invoiceDompdf->loadHtml($invoiceHtml);
                $invoiceDompdf->setPaper('A4', 'portrait');
                $invoiceDompdf->render();
                $invoiceDir = dirname(__DIR__, 2) . '/storage/documents/proformas/';
                if (!is_dir($invoiceDir)) mkdir($invoiceDir, 0777, true);
                file_put_contents($invoiceDir . "proforma-{$orderId}.pdf", $invoiceDompdf->output());
                $invoicePath = $invoiceDir . "proforma-{$orderId}.pdf";

                // --- EMAIL SENDING ---
                require_once __DIR__ . '/../Utils/Mailer.php';
                $attachments = [
                    [
                        'path' => $pdfPath,
                        'name' => "Rental-Contract-{$orderId}.pdf"
                    ],
                    [
                        'path' => $invoicePath,
                        'name' => "Proforma-Invoice-{$orderId}.pdf"
                    ]
                ];
                $subject = 'Your Rental Booking Confirmation';
                $body = buildBookingEmailTemplate([
                    'customer_name' => $customerName,
                    'order_id' => $orderId,
                    'amount_due' => $totalAmountWithTax,
                    'issued_at' => date('Y-m-d H:i:s'),
                    'pickup_datetime' => $pickup_datetime ?? '',
                    'return_datetime' => $return_datetime ?? '',
                    'payment_method' => 'card',
                    'note' => 'Your booking is confirmed. A pro-forma invoice is attached. Final invoice is issued after completion.',
                ]);
                $result = sendBookingConfirmation($customerEmail, $customerName, $subject, $body, $attachments);
                $debugMailFile = $this->openDebugLog('order-debug-log.txt');
                if ($result) {
                    fwrite($debugMailFile, date('Y-m-d H:i:s') . " [DEBUG] (STRIPE) Booking confirmation email sent successfully for orderId: $orderId to $customerEmail\n");
                } else {
                    fwrite($debugMailFile, date('Y-m-d H:i:s') . " [ERROR] (STRIPE) Booking confirmation email failed to send for orderId: $orderId to $customerEmail\n");
                }
                fclose($debugMailFile);
            } catch (\Exception $e) {
                if (isset($myfile) && is_resource($myfile)) {
                    fwrite($myfile, "[DEBUG] Entered catch block after order insert for orderId: $orderId\n");
                }
                if (isset($pdo)) {
                    $pdo->rollback();
                    fwrite($myfile, "ROLLBACK STARTED " . date('Y-m-d H:i:s') . "\n");
                }
                // Log error, but still return 200 to Stripe to avoid re-delivery storms.
                error_log("stripe webhook order creation error: " . $e->getMessage());
                fwrite($myfile, "[ERROR] Exception during order insert: " . $e->getMessage() . "\nStack trace:\n" . $e->getTraceAsString() . "\nParams: " . print_r($params ?? [], true) . "\n");
            }
            }

            http_response_code(200);
            exit;
    }

    // public function insertOrder(){
    //     // Get form data
    //     $name = htmlspecialchars(trim($_POST['name'] ?? ''));
    //     $phone = preg_replace('/\D/', '', $_POST['phone'] ?? '');
    //     $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    //     $deliveryType = $_POST['delivery_type'] ?? 'preferred';

    //     // Validate required fields (moved from controller for now)
    //     $deliveryType = $_POST['delivery_type'] ?? 'preferred';
    //     $cart = json_decode($_POST['cart'] ?? '[]', true);
    //     if ($deliveryType === 'pickup') {
    //         if (empty($_POST['name']) || empty($_POST['phone']) || empty($_POST['email']) || empty($_POST['pickup_location']) || empty($_POST['payment']) || empty($cart)) {
    //             echo "Missing required fields for pickup.";
    //             exit;
    //         }
    //     } else {
    //         if (empty($_POST['name']) || empty($_POST['phone']) || empty($_POST['email']) || empty($_POST['address1']) || empty($_POST['state']) || empty($_POST['zip']) || empty($_POST['payment']) || empty($cart)) {
    //             echo "Missing required fields for delivery.";
    //             exit;
    //         }
    //     }
    //     // Call model
    //     $orderModel = new \App\Models\OrderModel();
    //     $orderId = $orderModel->fullOrderProcess($_POST, $cart, $_SESSION);
    //     // Generate one-time secure token
    //     $token = bin2hex(random_bytes(32));
    //     $_SESSION["order_token_{$orderId}"] = $token;
    //     header("Location: /checkout?order={$orderId}&token={$token}");
    //     exit;

        
    // }

    private function deepJsonSerialize($value) {
        if (is_object($value) && method_exists($value, 'jsonSerialize')) {
            $value = $value->jsonSerialize();
        }
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = $this->deepJsonSerialize($v);
            }
        }
        return $value;
    }

    // PayPal: Create order (called by app.js createOrder())
    public function createPaypalOrder() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $this->prepareJsonResponse();
        $this->enforcePublicJsonRateLimit('paypal_create_order', 10, 15);

        // Get cart from POST body
        $payload = file_get_contents('php://input');
        $myfile = $this->openDebugLog('paypal-create-order-logs.txt');
        if (is_resource($myfile)) {
            fwrite($myfile, "ENTERING CREATE PAYPAL ORDER FUNCTION\n");
            fwrite($myfile, "POST PAYLOAD: \n" . $payload . "\n");
        }

        $input = json_decode($payload, true);
        $cart = $input['cart'] ?? [];
        if (is_resource($myfile)) {
            fwrite($myfile, " CART DATA: \n" . print_r($cart, true) . "\n");
        }

        if (empty($cart)) {
            echo json_encode(['error' => 'Cart is empty']);
            exit;
        }

        $formData = $_SESSION['checkout_form_data'] ?? [];
        if (trim((string)($formData['client_height'] ?? '')) === '') {
            echo json_encode(['error' => 'Please select the client height.']);
            exit;
        }
        $heardAboutError = $this->validateHeardAboutSelection($formData);
        if ($heardAboutError !== null) {
            echo json_encode(['error' => $heardAboutError]);
            exit;
        }

        $idPresenceError = $this->validateIdPresenceAcknowledgement($formData);
        if ($idPresenceError !== null) {
            echo json_encode(['error' => $idPresenceError]);
            exit;
        }

        $bookingWindowError = $this->validateBookingWindowConstraints($formData);
        if ($bookingWindowError !== null) {
            echo json_encode(['error' => $bookingWindowError]);
            exit;
        }

        $handednessError = $this->validatePowerChairHandednessRequirement($formData, is_array($cart) ? $cart : []);
        if ($handednessError !== null) {
            echo json_encode(['error' => $handednessError]);
            exit;
        }

        // Availability check
        $pickup_datetime = $formData['pickup_datetime'] ?? '';
        $return_datetime = $formData['return_datetime'] ?? '';
        $orderModel = new \App\Models\OrderModel();
        if (!$orderModel->isCartAvailable($cart, $pickup_datetime, $return_datetime)) {
            echo json_encode(['error' => 'Some items are no longer available for the selected dates. Please update your cart.']);
            exit;
        }

        $cart = $orderModel->normalizeCartForTrustedPricing(
            is_array($cart) ? $cart : [],
            $pickup_datetime,
            $return_datetime,
            $formData['sale_type'] ?? 'rental'
        );
        if (empty($cart)) {
            echo json_encode(['error' => 'No valid cart items were found. Please update your cart.']);
            exit;
        }

        if (is_resource($myfile)) {
            fwrite($myfile, "DEBUG TRUSTED CART: " . print_r($cart, true) . "\n");
            fwrite($myfile, "DEBUG FORM DATA: " . print_r($formData, true) . "\n");
        }
        // --- END DEBUGGING ---



        // Calculate total and build items array
        $totalAmount = 0;
        $items = [];
        foreach ($cart as $item) {
            $qty = max(1, intval($item['qty'] ?? $item['quantity'] ?? 1));
            $price = (float)($item['price'] ?? 0);
            $lineTotal = $qty * $price;
            $totalAmount += $lineTotal;

            $items[] = [
                'name' => substr($item['name'] ?? 'Product', 0, 127),
                'unit_amount' => [
                    'currency_code' => 'USD',
                    'value' => number_format($price, 2, '.', '')
                ],
                'quantity' => (string)$qty,
                'category' => 'PHYSICAL_GOODS'
            ];
        }

        $items[] = [
            'name' => 'Refundable Security Deposit',
            'unit_amount' => [
                'currency_code' => 'USD',
                'value' => number_format(self::SECURITY_DEPOSIT, 2, '.', '')
            ],
            'quantity' => '1',
            'category' => 'PHYSICAL_GOODS'
        ];

        $deliveryFee = $this->resolveDeliveryFeeForInput($formData);
        if ($deliveryFee > 0) {
            $items[] = [
                'name' => 'Hotel Delivery Fee',
                'unit_amount' => [
                    'currency_code' => 'USD',
                    'value' => number_format($deliveryFee, 2, '.', '')
                ],
                'quantity' => '1',
                'category' => 'PHYSICAL_GOODS'
            ];
        }

        $totalAmount = (new \App\Services\OrderTotalsService())->calculateFromSubtotal($totalAmount, 0.0, self::SECURITY_DEPOSIT, $deliveryFee)['total_amount_with_tax'];

        if (is_resource($myfile)) {
            fwrite($myfile,"Items array: \n" . print_r($items, true) . "\n");
        }

        // Use correct snake_case keys for PayPal API
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $returnUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . '/paypal-return';
        $cancelUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . '/checkout';

        // Generate a unique token for this PayPal checkout
        $orderToken = bin2hex(random_bytes(16));

        // Save cart and form data in session using this token
        $_SESSION['paypal_checkout'][$orderToken] = [
            'user_id' => $_SESSION['user_id'] ?? null,
            'form_data' => $formData,
            'cart' => $cart
        ];

        $paypalSnapshotPayload = [
            'checkout_ref' => $orderToken,
            'guest_email' => trim((string)($formData['email'] ?? '')),
            'first_name' => trim((string)($formData['first_name'] ?? '')),
            'last_name' => trim((string)($formData['last_name'] ?? '')),
            'pickup_datetime' => trim((string)($formData['pickup_datetime'] ?? '')),
            'return_datetime' => trim((string)($formData['return_datetime'] ?? '')),
            'delivery_type' => trim((string)($formData['delivery_type'] ?? '')),
            'sale_type' => trim((string)($formData['sale_type'] ?? 'rental')),
        ];
        $this->persistCheckoutSessionSnapshot('paypal', $orderToken, null, $paypalSnapshotPayload, $cart, 'paypal_created');
        

        $requestArray = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => 'default',
                    'amount' => [
                        'currency_code' => 'USD',
                        'value' => number_format($totalAmount, 2, '.', ''),
                        'breakdown' => [
                            'item_total' => [
                                'currency_code' => 'USD',
                                'value' => number_format($totalAmount, 2, '.', '')
                            ]
                        ]
                    ],
                    'description' => $orderToken,
                    'items' => $items
                ]
            ],
            'payment_source' => [
                'paypal' => [
                    'experience_context' => [
                        'return_url' => $returnUrl,
                        'cancel_url' => $cancelUrl,
                        'user_action' => 'CONTINUE'
                    ]
                ]
            ]
        ];

        // Log for debugging
        if (is_resource($myfile)) {
            fwrite($myfile,"REQUEST ARRAY: \n" . print_r($requestArray, true) . "\n");
        }


        // wrap body & headers as PayPal expects
        $collect = [
            'body' => $requestArray,
            'prefer' => 'return=minimal'
        ];

        try {
            // Use the correct controller method from PayPal SDK
            $ordersController = $this->paypalClient->getOrdersController();
            $response = $ordersController->createOrder($collect);

            $order = $response->getResult();
            if (is_resource($myfile)) {
                fwrite($myfile,"RESPONSE IS: \n" . print_r($response, true) . "\n");
                fwrite($myfile,"ORDER IS: \n" . print_r($order, true) . "\n");
            }

            // Safely extract ID
            $orderId = null;
            if (is_array($order)) {
                $orderId = $order['id'] ?? null;
            } elseif (is_object($order) && method_exists($order, 'getId')) {
                $orderId = $order->getId();
            } elseif (is_object($order) && property_exists($order, 'id')) {
                $orderId = $order->id;
            }

            if (is_resource($myfile)) {
                fwrite($myfile,"ORDER ID is: " . print_r($orderId, true) . "\n");
                fclose($myfile);
            }
            echo json_encode(['id' => $orderId]);
        } catch (\Exception $e) {
            http_response_code(500);
            if (is_resource($myfile)) {
                fwrite($myfile, "EXCEPTION: " . $e->getMessage() . "\n");
                fclose($myfile);
            }
            error_log('PayPal create order error: ' . $e->getMessage());
            echo json_encode(['error' => 'Unable to create PayPal order right now. Please try again.']);
        }

        exit;
    }

    // PayPal: Capture payment (called by app.js onApprove())
    public function capturePaypalOrder($orderId){
        $this->prepareJsonResponse();
        $this->enforcePublicJsonRateLimit('paypal_capture_order', 20, 15);
        $myfile = $this->openDebugLog('paypal-order-logs.txt');
        $log = function ($message) use ($myfile) {
            if (is_resource($myfile)) {
                fwrite($myfile, $message);
            }
        };

        $log("ENTERING CAPTURE PAYPAL ORDER FUNCTION\n");

        if (session_status() === PHP_SESSION_NONE) session_start();
        $log("ORDER ID: " . print_r($orderId, true) . "\n");

        $payload = file_get_contents('php://input');
        $log("POST PAYLOAD: \n" . $payload . "\n");

        if (!$orderId) {
            http_response_code(400);
            $log("ERROR: Order ID missing\n");
            echo json_encode(['error' => 'Order ID missing']);
            if (is_resource($myfile)) {
                fclose($myfile);
            }
            exit;
        }

        try {
            $ordersController = $this->paypalClient->getOrdersController();
            $orderDetailsResponse = $ordersController->getOrder(['id' => $orderId]);
            $orderDetails = $orderDetailsResponse->getResult();


            if (method_exists($orderDetailsResponse, 'getStatusCode')) {
                $log("RESPONSE STATUS CODE: " . $orderDetailsResponse->getStatusCode() . "\n");
            }
            if (method_exists($orderDetailsResponse, 'getBody')) {
                $log("RESPONSE BODY: " . $orderDetailsResponse->getBody() . "\n");
            }

            $log("ORDER DETAILS RAW: " . print_r($orderDetails, true) . "\n");
            $log("ORDER DETAILS RESPONSE: " . print_r($orderDetailsResponse, true) . "\n");

            // Check status
            $status = null;
            if (is_object($orderDetails) && method_exists($orderDetails, 'getStatus')) {
                $status = $orderDetails->getStatus();
            } elseif (is_object($orderDetails) && property_exists($orderDetails, 'status')) {
                $status = $orderDetails->status;
            }
            $log("ORDER STATUS: " . $status . "\n");

            if ($status !== 'APPROVED') {
                throw new \Exception("Order status is not APPROVED. Current status: $status");
            }

            $response = $ordersController->captureOrder(['id' => $orderId]);
            $log("CAPTURE RESPONSE: " . print_r($response, true) . "\n");
            if (method_exists($response, 'getStatusCode')) {
                $log("CAPTURE RESPONSE STATUS CODE: " . $response->getStatusCode() . "\n");
            }
            if (method_exists($response, 'getBody')) {
                $log("CAPTURE RESPONSE BODY: " . $response->getBody() . "\n");
            }
            $order = $response->getResult();

            // Defensive: decode if string
            if (is_string($order)) {
                $order = json_decode($order);
            }

            $log("CAPTURED ORDER RAW: " . print_r($order, true) . "\n");

            if (!$order) {
                throw new \Exception('PayPal capture did not return a valid order object.');
            }

            // Defensive: check method existence
            if (method_exists($order, 'getPurchaseUnits')) {
                $purchaseUnits = $order->getPurchaseUnits();
            } elseif (isset($order->purchase_units)) {
                $purchaseUnits = $order->purchase_units;
            } else {
                throw new \Exception('No purchase units found in PayPal order.');
            }

            // Defensive: check purchaseUnits array
            if (empty($purchaseUnits) || !isset($purchaseUnits[0])) {
                throw new \Exception('No purchase units found in PayPal order.');
            }

            // Defensive: get description
            $orderToken = '';
            if (method_exists($orderDetailsResponse, 'getBody')) {
                $body = $orderDetailsResponse->getBody();
                $bodyArr = json_decode($body, true);
                if (isset($bodyArr['purchase_units'][0]['description'])) {
                    $orderToken = $bodyArr['purchase_units'][0]['description'];
                    $log("ORDER TOKEN EXTRACTED FROM ORDER DETAILS RAW BODY: " . $orderToken . "\n");
                }
            }

            // If still empty, try SDK object as fallback (rarely works after capture)
            if (empty($orderToken) && isset($purchaseUnits[0])) {
                if (method_exists($purchaseUnits[0], 'getDescription')) {
                    $orderToken = $purchaseUnits[0]->getDescription();
                } elseif (isset($purchaseUnits[0]->description)) {
                    $orderToken = $purchaseUnits[0]->description;
                } elseif (is_array($purchaseUnits[0]) && isset($purchaseUnits[0]['description'])) {
                    $orderToken = $purchaseUnits[0]['description'];
                }
            }

            $log("ORDER TOKEN: " . $orderToken . "\n");


            $metadata = $_SESSION['paypal_checkout'][$orderToken] ?? [];
            $log("ORDER METADATA FROM SESSION: " . print_r($metadata, true) . "\n");
            $cart = $metadata['cart'] ?? [];
            $formData = $metadata['form_data'] ?? [];
            $userId = $metadata['user_id'] ?? null;

            $paypalCaptureId = null;
            if (is_object($order) && method_exists($order, 'getPurchaseUnits')) {
                $orderPurchaseUnits = $order->getPurchaseUnits();
                if (is_array($orderPurchaseUnits) && isset($orderPurchaseUnits[0])) {
                    $pu = $orderPurchaseUnits[0];
                    if (is_object($pu) && method_exists($pu, 'getPayments')) {
                        $payments = $pu->getPayments();
                        if (is_object($payments) && method_exists($payments, 'getCaptures')) {
                            $captures = $payments->getCaptures();
                            if (is_array($captures) && isset($captures[0])) {
                                $c0 = $captures[0];
                                if (is_object($c0) && method_exists($c0, 'getId')) {
                                    $paypalCaptureId = (string)$c0->getId();
                                } elseif (is_object($c0) && isset($c0->id)) {
                                    $paypalCaptureId = (string)$c0->id;
                                }
                            }
                        }
                    }
                }
            }

            if (!empty($cart)) {
                $log("CART IS NOT EMPTY. Proceeding to create DB order.\n");
                $localOrderId = $this->createDbOrderFromPaypal($userId, $formData, $cart, (string)$orderId, $paypalCaptureId);
            } else {
                $localOrderId = null;
                $log("CART IS EMPTY. No DB order will be created.\n");
            }

            try {
                $orderModel = new \App\Models\OrderModel();
                $orderModel->recordPaymentEvent([
                    'order_id' => $localOrderId ? (int)$localOrderId : null,
                    'checkout_ref' => $orderToken,
                    'payment_provider' => 'paypal',
                    'event_type' => 'payment_captured',
                    'provider_reference' => $paypalCaptureId ?: (string)$orderId,
                    'payload_json' => json_encode($this->deepJsonSerialize($order), JSON_UNESCAPED_SLASHES),
                ]);
                $this->persistCheckoutSessionSnapshot(
                    'paypal',
                    (string)$orderToken,
                    null,
                    is_array($formData) ? $formData : [],
                    is_array($cart) ? $cart : [],
                    $localOrderId ? 'finalized' : 'captured_no_local_order',
                    $localOrderId ? (int)$localOrderId : null
                );
            } catch (\Throwable $evtErr) {
                $log('PAYPAL RECONCILIATION WARNING: ' . $evtErr->getMessage() . "\n");
            }

            $log("CAPTURE SUCCESSFUL\n");

            $responsePayload = method_exists($order, 'jsonSerialize') ? $order->jsonSerialize() : $order;
            if (!is_array($responsePayload)) {
                $responsePayload = json_decode(json_encode($responsePayload), true) ?: [];
            }

            if ($localOrderId) {
                $token = $_SESSION["order_token_$localOrderId"] ?? '';
                if ($token) {
                    $responsePayload['local_order_id'] = (int)$localOrderId;
                    $responsePayload['local_order_token'] = $token;
                    $responsePayload['redirect_url'] = '/checkout?order=' . urlencode((string)$localOrderId) . '&token=' . urlencode($token);
                }
            }

            echo json_encode($responsePayload);
        } catch (\Exception $e) {
            http_response_code(500);
            $log("EXCEPTION: " . $e->getMessage() . "\n");
            error_log("PayPal capture error: " . $e->getMessage());
            echo json_encode(['error' => 'Payment capture failed. Please contact support if this persists.']);
        }


        if (is_resource($myfile)) {
            fclose($myfile);
        }

        exit;
    }

    // Helper to create DB order from PayPal capture
    private function createDbOrderFromPaypal($userId, $formData, $cart, $paypalOrderId = null, $paypalCaptureId = null)
    {   
        if (empty($formData)) {
            error_log('PayPal order: formData is empty!');
            // Optionally, return an error or fallback
            return;
        }
        $pdo = \App\Utils\Database::getInstance();
        $orderModel = new \App\Models\OrderModel();

        $existingOrderId = $orderModel->findOrderIdByProviderReference([
            'payment_provider' => 'paypal',
            'provider_paypal_order_id' => $paypalOrderId,
            'provider_paypal_capture_id' => $paypalCaptureId,
        ]);
        if ($existingOrderId) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            if (empty($_SESSION["order_token_$existingOrderId"])) {
                $_SESSION["order_token_$existingOrderId"] = bin2hex(random_bytes(16));
            }
            return (int)$existingOrderId;
        }

        $guestName = htmlspecialchars(trim($formData['name'] ?? ''));
        $guestEmail = filter_var(trim($formData['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $guestPhone = preg_replace('/\D/', '', $formData['phone'] ?? '');
        $clientWeightOption = htmlspecialchars(trim($formData['client_weight_option'] ?? ''));
        $clientWeightLbsRaw = $formData['client_weight_lbs'] ?? null;
        $clientWeightLbs = (is_numeric($clientWeightLbsRaw) && (int)$clientWeightLbsRaw > 0) ? (int)$clientWeightLbsRaw : null;
        $clientHeight = htmlspecialchars(trim((string)($formData['client_height'] ?? '')));
        $notes = htmlspecialchars(trim($formData['notes'] ?? ''));

        $deliveryType = $formData['delivery_type'] ?? 'preferred';

        if ($deliveryType === 'hotel' && empty($formData['hotel_id'])) {
            throw new \Exception('Please select a partner hotel for delivery before checkout.');
        }
        if ($deliveryType === 'hotel' && empty($formData['return_hotel_id'])) {
            throw new \Exception('Please select the return hotel/address before checkout.');
        }
        if ($deliveryType === 'pickup' && empty($formData['pickup_location'])) {
            throw new \Exception('Please select a pickup store before checkout.');
        }

        $pickup_location_id = $formData['pickup_location'] ?? '';
        $pickupLocation = '';
        $pickupLocationAddress = '';

        if ($deliveryType === 'pickup' && $pickup_location_id) {
            $stmt = $pdo->prepare("SELECT name, address FROM pickup_locations WHERE id = ?");
            $stmt->execute([$pickup_location_id]);
            $pickup = $stmt->fetch(\PDO::FETCH_ASSOC);
            $pickupLocation = $pickup['name'] ?? '';
            $pickupLocationAddress = $pickup['address'] ?? '';
            $pickupLocation = trim($pickupLocation . ($pickupLocationAddress ? ' - ' . $pickupLocationAddress : ''));
        } else {
            $pickupLocation = htmlspecialchars(trim($formData['pickup_location'] ?? ''));
        }

        if ($deliveryType === 'hotel') {
            $hotelId = $formData['hotel_id'] ?? null;
            if ($hotelId) {
                $stmt = $pdo->prepare("SELECT name, address1, address2, state, zip FROM partner_hotels WHERE id = ?");
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
            $address1 = htmlspecialchars(trim($formData['address1'] ?? ''));
            $address2 = htmlspecialchars(trim($formData['address2'] ?? ''));
            $state = htmlspecialchars(trim($formData['state'] ?? ''));
            $zip = htmlspecialchars(trim($formData['zip'] ?? ''));
        }

        $pickup_datetime = !empty($formData['pickup_datetime']) ? $formData['pickup_datetime'] : null;
        $return_datetime = !empty($formData['return_datetime']) ? $formData['return_datetime'] : null;
        $customerAddress = trim($address1 . ($address2 ? " " . $address2 : ""));

        $customerType = $userId ? 'user' : 'guest';

        // If guest, find or create customer
        $guestId = null;
        if (!$userId) {
            $stmt = $pdo->prepare("SELECT guest_id FROM guests WHERE email = ?");
            $stmt->execute([$guestEmail]);
            $guestId = $stmt->fetchColumn();

            if (!$guestId) {
                $fullAddress = trim($address1 . ($address2 ? " {$address2}" : ""));
                $stmt = $pdo->prepare("INSERT INTO guests (name, email, phone, address) VALUES (?, ?, ?, ?)");
                $stmt->execute([$guestName, $guestEmail, $guestPhone, $fullAddress]);
                $guestId = $pdo->lastInsertId();
            }
        }

        $cart = $orderModel->normalizeCartForTrustedPricing(
            is_array($cart) ? $cart : [],
            $pickup_datetime,
            $return_datetime,
            $formData['sale_type'] ?? 'rental'
        );
        if (empty($cart)) {
            throw new \Exception('No valid cart items were found for PayPal order creation.');
        }

        // Calculate total
        $totalAmount = 0;
        foreach ($cart as $item) {
            $totalAmount += ($item['qty'] ?? $item['quantity'] ?? 1) * ($item['price'] ?? 0);
        }
        $productTotalWithTax = round($totalAmount, 2);
        $securityDeposit = self::SECURITY_DEPOSIT;
        $deliveryFee = $this->resolveDeliveryFeeForInput($formData);
        $totalAmountWithTax = (new \App\Services\OrderTotalsService())->calculateFromSubtotal($productTotalWithTax, 0.0, $securityDeposit, $deliveryFee)['total_amount_with_tax'];

        // Insert order
        // Extract first and last name from formData (PayPal checkout)
        $first_name = $formData['first_name'] ?? '';
        $last_name = $formData['last_name'] ?? '';
        $heardAboutResolved = $this->resolveHeardAboutSelection($formData);
        $heardAboutOptionId = $heardAboutResolved['option_id'];
        $heardAboutLabel = $heardAboutResolved['label'];
        $stmt = $pdo->prepare(
            "INSERT INTO orders (
                user_id, guest_id, guest_first_name, guest_last_name, guest_email, guest_phone, client_weight_option, client_weight_lbs, client_height, total_amount, security_deposit, delivery_fee, order_date, status, address1, address2, state, zip, pickup_location, notes, heard_about_option_id, heard_about_label, payment_method, payment_provider, provider_paypal_order_id, provider_paypal_capture_id, customer_type, sale_type, pickup_datetime, return_datetime, delivery_type, hotel_id, return_hotel_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        try {
            $stmt->execute([
                $userId, $guestId, $first_name, $last_name, $guestEmail, $guestPhone, $clientWeightOption !== '' ? $clientWeightOption : null, $clientWeightLbs, $clientHeight !== '' ? $clientHeight : null,
                $totalAmountWithTax, $securityDeposit, $deliveryFee, date('Y-m-d H:i:s'), 'paid',
                $address1, $address2, $state, $zip, $pickupLocation, $notes,
                $heardAboutOptionId,
                $heardAboutLabel,
                'paypal', 'paypal', $paypalOrderId, $paypalCaptureId, $customerType, $formData['sale_type'] ?? 'rental',
                $pickup_datetime, $return_datetime, $formData['delivery_type'] ?? 'preferred', $formData['hotel_id'] ?? null, $formData['return_hotel_id'] ?? null
            ]);
        } catch (\PDOException $e) {
            if (($e->errorInfo[0] ?? '') !== '23000') {
                throw $e;
            }

            $existingOrderId = $orderModel->findOrderIdByProviderReference([
                'payment_provider' => 'paypal',
                'provider_paypal_order_id' => $paypalOrderId,
                'provider_paypal_capture_id' => $paypalCaptureId,
            ]);
            if (!$existingOrderId) {
                throw $e;
            }

            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            if (empty($_SESSION["order_token_$existingOrderId"])) {
                $_SESSION["order_token_$existingOrderId"] = bin2hex(random_bytes(16));
            }
            return (int)$existingOrderId;
        }
        $orderId = $pdo->lastInsertId();

        // Generate token for PayPal success ===
        $token = bin2hex(random_bytes(16));
        $_SESSION["order_token_$orderId"] = $token;

        // Insert order items + reserve scooters (copy from insertOrder)
        $assignedScooters = [];
        foreach ($cart as $idx => $item) {
            $pid = $item['id'] ?? null;
            $qty = max(1, intval($item['qty'] ?? $item['quantity'] ?? 1));

            // Fallback to DB product fields if cart fields are missing/empty
            $stmtP = $pdo->prepare("SELECT price, product_name, image_url FROM products WHERE product_id = ?");
            $stmtP->execute([$pid]);
            $product = $stmtP->fetch(\PDO::FETCH_ASSOC);

            $variation_id = isset($item['variation_id']) && $item['variation_id'] !== null && $item['variation_id'] !== '' ? $item['variation_id'] : null;
            $variation_name = isset($item['variation_name']) && $item['variation_name'] !== null && $item['variation_name'] !== '' ? $item['variation_name'] : null;
            $price = isset($item['price']) && $item['price'] !== null && $item['price'] !== '' ? $item['price'] : ($product['price'] ?? 0);
            $name = isset($item['name']) && $item['name'] !== null && $item['name'] !== '' ? $item['name'] : ($product['product_name'] ?? '');
            $image_url = isset($item['image_url']) && $item['image_url'] !== null && $item['image_url'] !== '' ? $item['image_url'] : ($product['image_url'] ?? '');
            $powerChairHandedness = strtolower(trim((string)($item['power_chair_handedness'] ?? '')));
            if (!in_array($powerChairHandedness, ['left', 'right'], true)) {
                $powerChairHandedness = null;
            }

            $reservedScooterIds = [];
            for ($i = 0; $i < $qty; $i++) {
                // Exclude scooters already reserved for overlapping dates
                $params = [$pid];
                $scooterQuery = "SELECT s.scooter_id FROM scooters s WHERE s.product_id = ? AND s.status = 'available'";
                if ($variation_id !== null) {
                    $scooterQuery .= " AND s.variation_id = ?";
                    $params[] = $variation_id;
                }
                // Exclude scooters already reserved for overlapping dates
                $scooterQuery .= " AND NOT EXISTS (SELECT 1 FROM reservations r WHERE r.scooter_id = s.scooter_id AND r.status IN ('pending','confirmed','paid') AND NOT (r.return_datetime <= ? OR r.pickup_datetime >= ?))";
                $params[] = $pickup_datetime;
                $params[] = $return_datetime;
                // Exclude scooters already picked in this order
                if (!empty($reservedScooterIds)) {
                    $placeholders = implode(',', array_fill(0, count($reservedScooterIds), '?'));
                    $scooterQuery .= " AND s.scooter_id NOT IN ($placeholders)";
                    $params = array_merge($params, $reservedScooterIds);
                }
                $scooterQuery .= " ORDER BY s.scooter_id ASC LIMIT 1 FOR UPDATE";
                $stmtScooter = $pdo->prepare($scooterQuery);
                $stmtScooter->execute($params);
                $scooterId = $stmtScooter->fetchColumn();

                // Debug log for each attempt
                $debugFile = $this->openDebugLog('paypal-order-logs.txt');
                fwrite($debugFile, date('Y-m-d H:i:s') . " [DEBUG] Scooter Query: $scooterQuery\n");
                fwrite($debugFile, date('Y-m-d H:i:s') . " [DEBUG] Params: " . var_export($params, true) . "\n");
                fwrite($debugFile, date('Y-m-d H:i:s') . " [DEBUG] ScooterId found: " . var_export($scooterId, true) . "\n");
                fclose($debugFile);

                if ($scooterId) {
                    $reservedScooterIds[] = $scooterId;
                    // Insert reservation
                    $stmtReservation = $pdo->prepare("INSERT INTO reservations (scooter_id, pickup_datetime, return_datetime, order_id, status) VALUES (?, ?, ?, ?, 'pending')");
                    $stmtReservation->execute([$scooterId, $pickup_datetime, $return_datetime, $orderId]);

                    // Insert order item for this scooter (use cart's data, fallback to DB)
                    $stmt = $pdo->prepare(
                        "INSERT INTO order_items (order_id, product_id, variation_id, variation_name, power_chair_handedness, scooter_id, quantity, price, product_name, image_url)
                        VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?)"
                    );
                    $stmt->execute([
                        $orderId,
                        $pid,
                        $variation_id,
                        $variation_name,
                        $powerChairHandedness,
                        $scooterId,
                        $price,
                        $name,
                        $image_url
                    ]);
                }
            }
            // Store assigned scooter_ids for this item (for markScootersSoldIfForSale)
            $assignedScooters[] = [
                'order_id' => $orderId,
                'product_id' => $pid,
                'product_name' => $name,
                'price' => $price,
                'quantity' => $qty,
                'image_url' => $image_url,
                'variation_id' => $variation_id,
                'variation_name' => $variation_name,
                'scooter_ids' => $reservedScooterIds
            ];
        }
        // Mark scooters as sold if for-sale (for-sale flow)
        $orderModel->markScootersSoldIfForSale($cart, $assignedScooters);

        // --- CONTRACT PDF GENERATION ---
        $customerName = $guestName;
        $customerEmail = $guestEmail;
        $customerPhone = $guestPhone;
        $customerAddress = $address1 . ($address2 ? " " . $address2 : "");
        $subtotal = 0;
        $itemsTable = '<table class="w-full border border-collapse text-sm">
            <thead>
                <tr>
                    <th class="border px-2 py-1 text-left">Qty</th>
                    <th class="border px-2 py-1 text-left">Item</th>
                    <th class="border px-2 py-1 text-left">Unit Price</th>
                    <th class="border px-2 py-1 text-left">Total</th>
                </tr>
            </thead>
            <tbody>';
        foreach ($cart as $item) {
            $qty = htmlspecialchars($item['quantity'] ?? 1);
            $name = htmlspecialchars($item['name']);
            $unitPrice = '$' . number_format($item['price'], 2);
            $lineTotal = '$' . number_format(($item['quantity'] ?? 1) * $item['price'], 2);
            $subtotal += ($item['quantity'] ?? 1) * $item['price'];
            $itemsTable .= "<tr>
                <td class='border px-2 py-1'>{$qty}</td>
                <td class='border px-2 py-1'>{$name}</td>
                <td class='border px-2 py-1'>{$unitPrice}</td>
                <td class='border px-2 py-1'>{$lineTotal}</td>
            </tr>";
        }
        $itemsTable .= '</tbody></table>';
        $pickupDate = $pickup_datetime ?? '';
        $returnDate = $return_datetime ?? '';
        $productTotalWithTax = round((float)$subtotal, 2);
        $totalAmountWithTax = round($productTotalWithTax + self::SECURITY_DEPOSIT, 2);

        ob_start();
        include __DIR__ . '/../../Contracts/contract-template.php';
        $html = ob_get_clean();

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdfDir = dirname(__DIR__, 2) . '/storage/documents/contracts/';
        if (!is_dir($pdfDir)) mkdir($pdfDir, 0777, true);
        file_put_contents($pdfDir . "contract-{$orderId}.pdf", $dompdf->output());
        $pdfPath = $pdfDir . "contract-{$orderId}.pdf";

        try {
            $orderModel->upsertOrderDocumentMetadata((int)$orderId, 'contract', $pdfPath, 'generated');
        } catch (\Throwable $e) {
            error_log('PayPal document metadata warning (contract): ' . $e->getMessage());
        }

        // --- PRO-FORMA PDF GENERATION ---
        $invoiceItemsTable = '';
        foreach ($cart as $item) {
            $qty = htmlspecialchars($item['quantity'] ?? 1);
            $name = htmlspecialchars($item['name']);
            $unitPrice = number_format($item['price'], 2);
            $lineTotal = number_format(($item['quantity'] ?? 1) * $item['price'], 2);
            $invoiceItemsTable .= "<tr>
                <td class='border p-2'>{$qty}</td>
                <td class='border p-2'>{$name}</td>
                <td class='border p-2'>\${$unitPrice}</td>
                <td class='border p-2'>\${$lineTotal}</td>
            </tr>";
        }

        // Prepare logo for invoice
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
        $orderDate = date('Y-m-d H:i:s');
        $productTotalWithTax = round((float)$subtotal, 2);
        $securityDeposit = self::SECURITY_DEPOSIT;
        $totals = (new \App\Services\OrderTotalsService())->calculateFromSubtotal($productTotalWithTax, 0.0, $securityDeposit, 0.0);
        $totalAmountWithTax = $totals['total_amount_with_tax'];
        $productPreTax = $totals['product_pre_tax'];
        $totalAmount = $totals['total_amount'];
        $tax = $totals['tax'];
        $discountAmount = 0.0;
        $promoCode = '';
        $paymentMethod = 'paypal';
        $pickupLocation = (string)($pickupLocation ?? '');
        $deliveryType = (string)($deliveryType ?? ($formData['delivery_type'] ?? ''));

        ob_start();
        include __DIR__ . '/../../Proformas/proforma-template.php';
        $invoiceHtml = ob_get_clean();

        $invoiceOptions = new \Dompdf\Options();
        $invoiceOptions->set('isRemoteEnabled', true);
        $invoiceOptions->set('isHtml5ParserEnabled', true);

        $invoiceDompdf = new \Dompdf\Dompdf($invoiceOptions);
        $invoiceDompdf->loadHtml($invoiceHtml);
        $invoiceDompdf->setPaper('A4', 'portrait');
        $invoiceDompdf->render();

        $invoiceDir = dirname(__DIR__, 2) . '/storage/documents/proformas/';
        if (!is_dir($invoiceDir)) mkdir($invoiceDir, 0777, true);
        file_put_contents($invoiceDir . "proforma-{$orderId}.pdf", $invoiceDompdf->output());
        $invoicePath = $invoiceDir . "proforma-{$orderId}.pdf";

        try {
            $orderModel->upsertOrderDocumentMetadata((int)$orderId, 'proforma', $invoicePath, 'generated');
        } catch (\Throwable $e) {
            error_log('PayPal document metadata warning (proforma): ' . $e->getMessage());
        }

        // --- EMAIL SENDING ---
        require_once __DIR__ . '/../Utils/Mailer.php';
        $attachments = [
            [
                'path' => $pdfPath,
                'name' => "Rental-Contract-{$orderId}.pdf"
            ],
            [
                'path' => $invoicePath,
                'name' => "Proforma-Invoice-{$orderId}.pdf"
            ]
        ];
        $subject = 'Your Rental Booking Confirmation';
        $body = buildBookingEmailTemplate([
            'customer_name' => $customerName,
            'order_id' => $orderId,
            'amount_due' => $totalAmountWithTax,
            'issued_at' => date('Y-m-d H:i:s'),
            'pickup_datetime' => $pickup_datetime ?? '',
            'return_datetime' => $return_datetime ?? '',
            'payment_method' => 'paypal',
            'note' => 'Your booking is confirmed. A pro-forma invoice is attached. Final invoice is issued after completion.',
        ]);
        $result = sendBookingConfirmation($customerEmail, $customerName, $subject, $body, $attachments);
        $debugMailFile = $this->openDebugLog('order-debug-log.txt');
        if ($result) {
            fwrite($debugMailFile, date('Y-m-d H:i:s') . " [DEBUG] Booking confirmation email sent successfully for orderId: $orderId to $customerEmail\n");
        } else {
            fwrite($debugMailFile, date('Y-m-d H:i:s') . " [ERROR] Booking confirmation email failed to send for orderId: $orderId to $customerEmail\n");
        }
        fclose($debugMailFile);

        // TO FOLLOWUP: GENERATE INVCOICE/CONTRACT AND SEND EMAIL

        return $orderId;
    }

    
    public function saveCheckoutForm(){
        if (session_status() === PHP_SESSION_NONE) session_start();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
                http_response_code(403);
                die('Invalid CSRF token');
            }
            $this->enforcePublicJsonRateLimit('save_checkout_form', 30, 15);
        }

        $idPresenceError = $this->validateIdPresenceAcknowledgement($_POST);
        if ($idPresenceError !== null) {
            http_response_code(422);
            echo json_encode(['error' => $idPresenceError]);
            exit;
        }

        $_SESSION['checkout_form_data'] = $_POST;

        $checkoutRef = trim((string)($_POST['checkout_ref'] ?? ''));
        if ($checkoutRef === '') {
            $checkoutRef = 'form_' . bin2hex(random_bytes(8));
        }
        $_SESSION['checkout_form_ref'] = $checkoutRef;

        $payload = $this->getStripeFallbackPayloadFromPost($_POST);
        $cart = json_decode((string)($payload['cart_json'] ?? '[]'), true);
        $this->persistCheckoutSessionSnapshot(
            'checkout',
            $checkoutRef,
            null,
            $payload,
            is_array($cart) ? $cart : [],
            'form_saved'
        );

        echo json_encode(['success' => true]);
        exit;
    }

    public function paypalReturn(){
        if (session_status() === PHP_SESSION_NONE) session_start();

        $orderId = (int)($_GET['order'] ?? 0);
        $token = (string)($_GET['token'] ?? '');

        if (!$orderId || $token === '') {
            header('Location: /checkout');
            exit;
        }

        $sessionToken = $_SESSION["order_token_$orderId"] ?? '';
        if ($sessionToken === '' || !hash_equals($sessionToken, $token)) {
            header('Location: /checkout');
            exit;
        }

        header('Location: /checkout?order=' . urlencode((string)$orderId) . '&token=' . urlencode($token));
        exit;
    }
            
}
