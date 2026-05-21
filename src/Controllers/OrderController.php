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

    private $paypalClient;

    private function prepareJsonResponse()
    {
        ob_start();
        @header('Content-Type: application/json; charset=utf-8');
    }

    private function openDebugLog($filename)
    {
        $path = __DIR__ . '/../../public/' . $filename;
        return @fopen($path, 'a');
    }

    public function __construct()
    {
        $PAYPAL_CLIENT_ID = getenv("PAYPAL_CLIENT_ID") ?: ($_ENV["PAYPAL_CLIENT_ID"] ?? '');
        $PAYPAL_CLIENT_SECRET = getenv("PAYPAL_CLIENT_SECRET") ?: ($_ENV["PAYPAL_CLIENT_SECRET"] ?? '');

        $this->paypalClient = PaypalServerSdkClientBuilder::init()
            ->clientCredentialsAuthCredentials(
                ClientCredentialsAuthCredentialsBuilder::init(
                    $PAYPAL_CLIENT_ID,
                    $PAYPAL_CLIENT_SECRET
                )
            )
            ->environment(Environment::SANDBOX)
            ->build();
    }

    public function processCheckout() {
            // DEBUG: Top of processCheckout
            $debugFile = fopen("order-debug-log.txt", "a");
            fwrite($debugFile, date('Y-m-d H:i:s') . "\n[DEBUG] Top of processCheckout\n");
            fclose($debugFile);
        // DEBUG: Confirm controller is being executed and log file can be created
        $myfile = fopen("order-debug-log.txt", "a") or die("Unable to open file!");
        fwrite($myfile, date('Y-m-d H:i:s') . "\n[DEBUG] Entered processCheckout in OrderController\n");
        fwrite($myfile, date('Y-m-d H:i:s') . "\n[DEBUG] REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD'] . "\n");
        fwrite($myfile, date('Y-m-d H:i:s') . "\n[DEBUG] POST DATA: " . var_export($_POST, true) . "\n");
        fclose($myfile);
        if (session_status() === PHP_SESSION_NONE) session_start();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
                http_response_code(403);
                die('Invalid CSRF token');
            }

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
            if (empty($_POST['first_name']) || empty($_POST['last_name']) || empty($_POST['phone']) || empty($_POST['email']) || empty($_POST['payment']) || empty($_POST['client_weight_option']) || empty($cart)) {
                echo "Missing required checkout fields.";
                exit;
            }

            if (($_POST['client_weight_option'] ?? '') === 'other' && empty($_POST['client_weight_lbs'])) {
                echo "Please provide the customer's exact weight in lbs.";
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
        // DEBUG: Bottom of processCheckout (should never reach here for POST)
        $debugFile = fopen("order-debug-log.txt", "a");
        fwrite($debugFile, date('Y-m-d H:i:s') . "\n[DEBUG] Bottom of processCheckout\n");
        fclose($debugFile);
    }

    public function completeOrder() {
        if (session_status() === PHP_SESSION_NONE) session_start();
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
        $messages = $orderModel->completeOrderProcess($orderId);
        $_SESSION['order_complete_message'] = implode("<br>", $messages);
        header("Location: /admin/orders");
        exit;
    }

    public function cancelOrder() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
                http_response_code(403);
                die('Invalid CSRF token');
            }
        }
        if (session_status() === PHP_SESSION_NONE) session_start();

        $orderId = isset($_POST['order_id']) ? intval($_POST['order_id']) : null;
        if (!$orderId) {
            $_SESSION['order_cancel_message'] = "Order ID missing.";
            header("Location: /admin/orders");
            exit;
        }

        $orderModel = new OrderModel();
        $message = $orderModel->cancelOrderProcess($orderId);
        $_SESSION['order_cancel_message'] = $message;
        header("Location: /admin/orders");
        exit;
    }

    public function ajaxOrderDetails() {
        if (empty($_GET['order_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Order ID required']);
            exit;
        }
        $orderId = intval($_GET['order_id']);
        $orderModel = new OrderModel();
        $details = $orderModel->getOrderDetails($orderId);
        header('Content-Type: application/json');
        echo json_encode($details);
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
            
            $orderModel = new OrderModel();
            $result = $orderModel->createStripePaymentIntent($_POST, $_SESSION);
            
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
            
            // Get payment intent ID from JSON body or POST
            $input = @json_decode(file_get_contents('php://input'), true) ?: [];
            $paymentIntentId = $input['payment_intent_id'] ?? $_POST['payment_intent_id'] ?? null;
            
            if (!$paymentIntentId) {
                http_response_code(400);
                echo json_encode(['error' => 'Payment intent ID missing']);
                ob_end_flush();
                exit;
            }
            
            // Call the finalization logic
            $result = $this->finalizeStripePaymentIntentById($paymentIntentId);
            
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
            error_log('Finalize payment exception: ' . $e->getMessage());
            echo json_encode(['error' => 'Payment processing failed']);
            ob_end_flush();
        }
        exit;
    }

    private function finalizeStripePaymentIntentById($paymentIntentId)
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
            return ['error' => 'Unable to verify Stripe payment: ' . $e->getMessage()];
        }

        if (($intent->status ?? '') !== 'succeeded') {
            return ['error' => 'Payment is not completed yet.'];
        }

        $meta = [];
        if (isset($intent->metadata)) {
            if (is_object($intent->metadata) && method_exists($intent->metadata, 'toArray')) {
                $meta = $intent->metadata->toArray();
            } else {
                $meta = (array)$intent->metadata;
            }
        }

        $cart = json_decode($meta['cart_json'] ?? '[]', true);
        $guestEmail = filter_var(trim($meta['guest_email'] ?? ''), FILTER_VALIDATE_EMAIL);
        if (!is_array($cart) || empty($cart)) {
            return ['error' => 'Missing Stripe cart details.'];
        }
        if (!$guestEmail) {
            return ['error' => 'Missing or invalid customer email for Stripe confirmation.'];
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

        $orderModel = new OrderModel();
        if (!$orderId) {
            $postData = [
                'first_name' => $meta['first_name'] ?? '',
                'last_name' => $meta['last_name'] ?? '',
                'email' => $guestEmail,
                'phone' => $meta['guest_phone'] ?? '',
                'address1' => $meta['address1'] ?? '',
                'address2' => $meta['address2'] ?? '',
                'state' => $meta['state'] ?? '',
                'zip' => $meta['zip'] ?? '',
                'delivery_type' => $meta['delivery_type'] ?? 'preferred',
                'hotel_id' => $meta['hotel_id'] ?? '',
                'pickup_datetime' => $meta['pickup_datetime'] ?? '',
                'return_datetime' => $meta['return_datetime'] ?? '',
                'pickup_location' => $meta['pickup_location'] ?? '',
                'notes' => $meta['notes'] ?? '',
                'sale_type' => $meta['sale_type'] ?? 'rental',
                'guest_first_name' => $meta['first_name'] ?? '',
                'guest_last_name' => $meta['last_name'] ?? '',
                'guest_email' => $guestEmail,
                'guest_phone' => $meta['guest_phone'] ?? '',
                'payment' => 'card',
            ];

            $orderId = $orderModel->fullOrderProcess($postData, $cart, $_SESSION);
            if (!$orderId) {
                return ['error' => 'Could not store the Stripe order after payment.'];
            }
            $_SESSION[$intentOrderSessionKey] = $orderId;
            $orderModel->markAsPaid($orderId);
        }

        // Always ensure docs/email after finalize (safe to re-run).
        $orderModel->ensureOrderDocumentsAndEmail($orderId, $cart);

        $token = $_SESSION["order_token_$orderId"] ?? null;
        if (!$token) {
            $token = bin2hex(random_bytes(16));
            $_SESSION["order_token_$orderId"] = $token;
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

        // Fallback to latest paid Stripe order if payment intent query params are missing.
        $pdo = \App\Utils\Database::getInstance();
        $stmt = $pdo->prepare("SELECT order_id FROM orders WHERE payment_method = 'card' ORDER BY order_id DESC LIMIT 1");
        $stmt->execute();
        $orderId = $stmt->fetchColumn();

        if (!$orderId) {
            header('Location: /checkout');
            exit;
        }

        $token = $_SESSION["order_token_$orderId"] ?? null;
        if (!$token) {
            $token = bin2hex(random_bytes(16));
            $_SESSION["order_token_$orderId"] = $token;
        }

        header("Location: /checkout?order=$orderId&token=$token");
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
        $myfile = fopen("stripe-webhook-logs.txt", "a");
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
            $pickupLocation = htmlspecialchars(trim($meta->pickup_location ?? ''));
            $notes = htmlspecialchars(trim($meta->notes ?? ''));
            $saleType = htmlspecialchars(trim($meta->sale_type ?? 'rental'));
            $totalAmount = (float)($meta->total_amount ?? 0);
            $clientWeightOption = htmlspecialchars(trim($meta->client_weight_option ?? ''));
            $clientWeightLbsRaw = $meta->client_weight_lbs ?? null;
            $clientWeightLbs = is_numeric($clientWeightLbsRaw) ? (int) $clientWeightLbsRaw : null;
            $loggedInUserId = $meta->logged_in_user_id ?? null;

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
            $totalAmountWithTax = $totalAmount;

            
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
                $pdo->beginTransaction();
                $stmt = $pdo->prepare(
                    "INSERT INTO orders (
                        user_id, guest_first_name, guest_last_name, guest_email, guest_phone, client_weight_option, client_weight_lbs, address1, address2, state, zip, pickup_datetime, return_datetime, pickup_location, notes, payment_method, total_amount, status, order_date, customer_type, sale_type
                    ) VALUES (
                        :user_id, :guest_first_name, :guest_last_name, :guest_email, :guest_phone, :client_weight_option, :client_weight_lbs, :address1, :address2, :state, :zip, :pickup_datetime, :return_datetime, :pickup_location, :notes, 'card', :total_amount, 'paid', NOW(), :customer_type, :sale_type
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
                    ':address1' => $address1,
                    ':address2' => $address2,
                    ':state' => $state,
                    ':zip' => $zip,
                    ':pickup_datetime' => $pickup_datetime,
                    ':return_datetime' => $return_datetime,
                    ':pickup_location' => $pickupLocation,
                    ':notes' => $notes,
                    ':total_amount' => $totalAmount,
                    ':customer_type' => $loggedInUserId ? 'user' : 'guest',
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
                            $sql = "SELECT s.scooter_id FROM scooters s WHERE s.product_id = ?{$variationClause} AND s.status = 'available' AND NOT EXISTS (SELECT 1 FROM reservations r WHERE r.scooter_id = s.scooter_id AND r.status IN ('pending','confirmed','paid') AND NOT (r.return_datetime <= ? OR r.pickup_datetime >= ?)) $excludeClause ORDER BY s.scooter_id ASC LIMIT 1";
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
                                "INSERT INTO order_items (order_id, product_id, variation_id, variation_name, scooter_id, quantity, price, product_name, image_url) VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?)"
                            );
                            $params = [
                                $orderId,
                                $pid,
                                $variation_id,
                                $variation_name,
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
                $totalAmountWithTax = $subtotal;
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
                $pdfDir = __DIR__ . '/../../Contracts/';
                if (!is_dir($pdfDir)) mkdir($pdfDir, 0777, true);
                file_put_contents($pdfDir . "contract-{$orderId}.pdf", $dompdf->output());
                $pdfPath = $pdfDir . "contract-{$orderId}.pdf";

                // Invoice PDF
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
                $totalAmount = $subtotal;
                ob_start();
                include __DIR__ . '/../../Invoices/invoice-template.php';
                $invoiceHtml = ob_get_clean();
                $invoiceOptions = new \Dompdf\Options();
                $invoiceOptions->set('isRemoteEnabled', true);
                $invoiceOptions->set('isHtml5ParserEnabled', true);
                $invoiceDompdf = new \Dompdf\Dompdf($invoiceOptions);
                $invoiceDompdf->loadHtml($invoiceHtml);
                $invoiceDompdf->setPaper('A4', 'portrait');
                $invoiceDompdf->render();
                $invoiceDir = __DIR__ . '/../../Invoices/';
                if (!is_dir($invoiceDir)) mkdir($invoiceDir, 0777, true);
                file_put_contents($invoiceDir . "invoice-{$orderId}.pdf", $invoiceDompdf->output());
                $invoicePath = $invoiceDir . "invoice-{$orderId}.pdf";

                // --- EMAIL SENDING ---
                require_once __DIR__ . '/../Utils/Mailer.php';
                $attachments = [
                    [
                        'path' => $pdfPath,
                        'name' => "Rental-Contract-{$orderId}.pdf"
                    ],
                    [
                        'path' => $invoicePath,
                        'name' => "Invoice-{$orderId}.pdf"
                    ]
                ];
                $subject = 'Your Rental Booking Confirmation';
                $body = "Thank you for your booking! Please find your rental contract and invoice attached.";
                $result = sendBookingConfirmation($customerEmail, $customerName, $subject, $body, $attachments);
                $debugMailFile = fopen("order-debug-log.txt", "a");
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

        // Availability check
        $pickup_datetime = $formData['pickup_datetime'] ?? '';
        $return_datetime = $formData['return_datetime'] ?? '';
        $orderModel = new \App\Models\OrderModel();
        if (!$orderModel->isCartAvailable($cart, $pickup_datetime, $return_datetime)) {
            echo json_encode(['error' => 'Some items are no longer available for the selected dates. Please update your cart.']);
            exit;
        }

        if (is_resource($myfile)) {
            fwrite($myfile, "DEBUG CART: " . print_r($cart, true) . "\n");
            fwrite($myfile, "DEBUG FORM DATA: " . print_r($formData, true) . "\n");
        }
        // --- END DEBUGGING ---



        // Calculate total and build items array
        $totalAmount = 0;
        $items = [];
        foreach ($cart as $item) {
            $qty = max(1, intval($item['quantity'] ?? 1));
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
            echo json_encode(['error' => $e->getMessage()]);
        }

        exit;
    }

    // PayPal: Capture payment (called by app.js onApprove())
    public function capturePaypalOrder($orderId){
        $this->prepareJsonResponse();
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

            if (!empty($cart)) {
                $log("CART IS NOT EMPTY. Proceeding to create DB order.\n");
                $this->createDbOrderFromPaypal($userId, $formData, $cart);
            } else {
                $log("CART IS EMPTY. No DB order will be created.\n");
            }

            $log("CAPTURE SUCCESSFUL\n");

            echo json_encode(method_exists($order, 'jsonSerialize') ? $order->jsonSerialize() : $order);
        } catch (\Exception $e) {
            http_response_code(500);
            $log("EXCEPTION: " . $e->getMessage() . "\n");
            error_log("PayPal capture error: " . $e->getMessage());
            echo json_encode(['error' => $e->getMessage()]);
        }


        if (is_resource($myfile)) {
            fclose($myfile);
        }

        exit;
    }

    // Helper to create DB order from PayPal capture
    private function createDbOrderFromPaypal($userId, $formData, $cart)
    {   
        if (empty($formData)) {
            error_log('PayPal order: formData is empty!');
            // Optionally, return an error or fallback
            return;
        }
        $pdo = \App\Utils\Database::getInstance();

        $guestName = htmlspecialchars(trim($formData['name'] ?? ''));
        $guestEmail = filter_var(trim($formData['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $guestPhone = preg_replace('/\D/', '', $formData['phone'] ?? '');
        $clientWeightOption = htmlspecialchars(trim($formData['client_weight_option'] ?? ''));
        $clientWeightLbsRaw = $formData['client_weight_lbs'] ?? null;
        $clientWeightLbs = (is_numeric($clientWeightLbsRaw) && (int)$clientWeightLbsRaw > 0) ? (int)$clientWeightLbsRaw : null;
        $notes = htmlspecialchars(trim($formData['notes'] ?? ''));

        $deliveryType = $formData['delivery_type'] ?? 'preferred';

        if ($deliveryType === 'hotel' && empty($formData['hotel_id'])) {
            throw new \Exception('Please select a partner hotel for delivery before checkout.');
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
                $stmt = $pdo->prepare("SELECT address1, address2, state, zip FROM partner_hotels WHERE id = ?");
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

        // Calculate total
        $totalAmount = 0;
        foreach ($cart as $item) {
            $totalAmount += ($item['quantity'] ?? 1) * ($item['price'] ?? 0);
        }
        $totalAmountWithTax = $totalAmount;

        // Insert order
        // Extract first and last name from formData (PayPal checkout)
        $first_name = $formData['first_name'] ?? '';
        $last_name = $formData['last_name'] ?? '';
        $stmt = $pdo->prepare(
            "INSERT INTO orders (
                user_id, guest_id, guest_first_name, guest_last_name, guest_email, guest_phone, client_weight_option, client_weight_lbs, total_amount, order_date, status, address1, address2, state, zip, pickup_location, notes, payment_method, customer_type, sale_type, pickup_datetime, return_datetime, delivery_type, hotel_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $userId, $guestId, $first_name, $last_name, $guestEmail, $guestPhone, $clientWeightOption !== '' ? $clientWeightOption : null, $clientWeightLbs,
            $totalAmountWithTax, date('Y-m-d H:i:s'), 'paid',
            $address1, $address2, $state, $zip, $pickupLocation, $notes,
            'paypal', $customerType, $formData['sale_type'] ?? 'rental',
            $pickup_datetime, $return_datetime, $formData['delivery_type'] ?? 'preferred', $formData['hotel_id'] ?? null
        ]);
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
                $scooterQuery .= " ORDER BY s.scooter_id ASC LIMIT 1";
                $stmtScooter = $pdo->prepare($scooterQuery);
                $stmtScooter->execute($params);
                $scooterId = $stmtScooter->fetchColumn();

                // Debug log for each attempt
                $debugFile = fopen("paypal-order-logs.txt", "a");
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
                        "INSERT INTO order_items (order_id, product_id, variation_id, variation_name, scooter_id, quantity, price, product_name, image_url)
                        VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?)"
                    );
                    $stmt->execute([
                        $orderId,
                        $pid,
                        $variation_id,
                        $variation_name,
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
        $orderModel = new \App\Models\OrderModel();
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
        $totalAmountWithTax = $subtotal;

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

        $pdfDir = __DIR__ . '/../../Contracts/';
        if (!is_dir($pdfDir)) mkdir($pdfDir, 0777, true);
        file_put_contents($pdfDir . "contract-{$orderId}.pdf", $dompdf->output());
        $pdfPath = $pdfDir . "contract-{$orderId}.pdf";

        // --- INVOICE PDF GENERATION ---
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
        $totalAmount = $subtotal;

        ob_start();
        include __DIR__ . '/../../Invoices/invoice-template.php';
        $invoiceHtml = ob_get_clean();

        $invoiceOptions = new \Dompdf\Options();
        $invoiceOptions->set('isRemoteEnabled', true);
        $invoiceOptions->set('isHtml5ParserEnabled', true);

        $invoiceDompdf = new \Dompdf\Dompdf($invoiceOptions);
        $invoiceDompdf->loadHtml($invoiceHtml);
        $invoiceDompdf->setPaper('A4', 'portrait');
        $invoiceDompdf->render();

        $invoiceDir = __DIR__ . '/../../Invoices/';
        if (!is_dir($invoiceDir)) mkdir($invoiceDir, 0777, true);
        file_put_contents($invoiceDir . "invoice-{$orderId}.pdf", $invoiceDompdf->output());
        $invoicePath = $invoiceDir . "invoice-{$orderId}.pdf";

        // --- EMAIL SENDING ---
        require_once __DIR__ . '/../Utils/Mailer.php';
        $attachments = [
            [
                'path' => $pdfPath,
                'name' => "Rental-Contract-{$orderId}.pdf"
            ],
            [
                'path' => $invoicePath,
                'name' => "Invoice-{$orderId}.pdf"
            ]
        ];
        $subject = 'Your Rental Booking Confirmation';
        $body = "Thank you for your booking! Please find your rental contract and invoice attached.";
        $result = sendBookingConfirmation($customerEmail, $customerName, $subject, $body, $attachments);
        $debugMailFile = fopen("order-debug-log.txt", "a");
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
        error_log('saveCheckoutForm called');
        error_log('Session CSRF: ' . ($_SESSION['csrf_token'] ?? ''));
        error_log('Posted CSRF: ' . ($_POST['csrf_token'] ?? ''));
        if (session_status() === PHP_SESSION_NONE) session_start();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
                http_response_code(403);
                die('Invalid CSRF token');
            }
        }
        $_SESSION['checkout_form_data'] = $_POST;
        echo json_encode(['success' => true]);
        exit;
    }

    public function paypalReturn(){
        if (session_status() === PHP_SESSION_NONE) session_start();
        
        // Find the last PayPal order (you can improve this later with token from description)
        $pdo = \App\Utils\Database::getInstance();
        $stmt = $pdo->prepare("SELECT order_id FROM orders WHERE payment_method = 'paypal' ORDER BY order_id DESC LIMIT 1");
        $stmt->execute();
        $orderId = $stmt->fetchColumn();

        if (!$orderId) {
            header('Location: /checkout');
            exit;
        }

        $token = $_SESSION["order_token_$orderId"] ?? null;
        if (!$token) {
            $token = bin2hex(random_bytes(16));
            $_SESSION["order_token_$orderId"] = $token;
        }

        header("Location: /checkout?order=$orderId&token=$token");
        exit;
    }
            
}