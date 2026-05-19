<?php
namespace App\Controllers;
use App\Controller;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dompdf\Dompdf;
use App\Models\AdminModel;
use App\Models\OrderModel;
use App\Models\ProductModel;
use App\Models\ReservationModel;
use App\Models\TipsTroubleshootingModel;

class AdminController extends Controller
{
    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    public function login() {
        // Do NOT check for admin session here!
        require __DIR__ . '/../Views/admin/login.php';
    }

    public function processLogin() {
        // Do NOT check for admin session here!
        $username = htmlspecialchars(trim($_POST['username'] ?? ''));
        $password = $_POST['password'] ?? '';

        $pdo = \App\Utils\Database::getInstance();
        $adminModel = new AdminModel($pdo);
        $admin = $adminModel->findByUsername($username);

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_role'] = $admin['role'];
            header('Location: /admin/orders');
            exit;
        } else {
            $error = "Invalid username or password.";
            require __DIR__ . '/../Views/admin/login.php';
        }
    }

    public function orders() {
        $this->requireAdmin();
        if (!isset($_SESSION['admin_id'])) {
            header('Location: /admin/login');
            exit;
        }

        $orderModel = new OrderModel();

        $searchTerm = isset($_GET['order_id_search']) ? trim($_GET['order_id_search']) : '';
        $statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
        $customerTypeFilter = isset($_GET['customer_type']) ? trim($_GET['customer_type']) : '';
        $saleTypeFilter = isset($_GET['sale_type']) ? trim($_GET['sale_type']) : '';
        $dateFromFilter = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
        $dateToFilter = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
        $sortBy = isset($_GET['sort_by']) ? trim($_GET['sort_by']) : 'order_id';
        $sortDir = isset($_GET['sort_dir']) ? trim($_GET['sort_dir']) : 'desc';
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $perPage = 25;

        $allowedStatuses = ['pending', 'approved', 'paid', 'completed', 'cancelled'];
        if (!in_array(strtolower($statusFilter), $allowedStatuses, true)) {
            $statusFilter = '';
        }

        $allowedCustomerTypes = ['user', 'guest'];
        if (!in_array(strtolower($customerTypeFilter), $allowedCustomerTypes, true)) {
            $customerTypeFilter = '';
        }

        $allowedSaleTypes = ['rental', 'sale'];
        if (!in_array(strtolower($saleTypeFilter), $allowedSaleTypes, true)) {
            $saleTypeFilter = '';
        }

        if ($dateFromFilter !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFromFilter)) {
            $dateFromFilter = '';
        }

        if ($dateToFilter !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateToFilter)) {
            $dateToFilter = '';
        }

        $allowedSortBy = ['order_id', 'sale_type', 'total_amount', 'status', 'order_date', 'pickup_datetime', 'return_datetime'];
        if (!in_array($sortBy, $allowedSortBy, true)) {
            $sortBy = 'order_id';
        }

        $sortDir = strtolower($sortDir) === 'asc' ? 'asc' : 'desc';

        $filters = [
            'order_id_search' => $searchTerm,
            'status' => strtolower($statusFilter),
            'customer_type' => strtolower($customerTypeFilter),
            'sale_type' => strtolower($saleTypeFilter),
            'date_from' => $dateFromFilter,
            'date_to' => $dateToFilter,
            'sort_by' => $sortBy,
            'sort_dir' => $sortDir,
        ];

        $totalOrders = 0;
        if (method_exists($orderModel, 'getOrdersFilteredPaginated')) {
            $orders = $orderModel->getOrdersFilteredPaginated($filters, $page, $perPage, $totalOrders);
        } else {
            $orders = $orderModel->getOrdersPaginated($page, $perPage);
            $totalOrders = $orderModel->getTotalOrdersCount();
        }

        // Ensure each order has a display_name field for the view (first + last name logic)
        foreach ($orders as &$order) {
            $order['display_name'] = '';
            // If guest order, use guest_first_name + guest_last_name
            if (!empty($order['guest_first_name']) || !empty($order['guest_last_name'])) {
                $order['display_name'] = trim(($order['guest_first_name'] ?? '') . ' ' . ($order['guest_last_name'] ?? ''));
            }
            // If user order, try to get user first/last name (if not already set)
            if (empty($order['display_name']) && !empty($order['user_id'])) {
                // Query user table for first/last name
                $pdo = \App\Utils\Database::getInstance();
                $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
                $stmt->execute([$order['user_id']]);
                $user = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($user) {
                    $order['display_name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                }
            }
        }
        unset($order);

        $totalPages = max(1, ceil($totalOrders / $perPage));

        // Get analytics data
        $completedOrders = $orderModel->getCompletedOrdersCount();
        $totalSales = $orderModel->getTotalSales();
        $pendingOrders = $orderModel->getPendingOrdersCount();
        $ordersByStatus = $orderModel->getOrdersByStatus();
        $salesByDate = $orderModel->getSalesByDate(30);
        $orderCountByDate = $orderModel->getOrderCountByDate(30);

        // Use renderAdmin to render the orders page with admin layout
        $this->renderAdmin('admin/orders', [
            'orders' => $orders,
            'searchTerm' => $searchTerm,
            'statusFilter' => strtolower($statusFilter),
            'customerTypeFilter' => strtolower($customerTypeFilter),
            'saleTypeFilter' => strtolower($saleTypeFilter),
            'dateFromFilter' => $dateFromFilter,
            'dateToFilter' => $dateToFilter,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
            'page' => $page,
            'perPage' => $perPage,
            'totalOrders' => $totalOrders,
            'totalPages' => $totalPages,
            'completedOrders' => $completedOrders,
            'totalSales' => $totalSales,
            'pendingOrders' => $pendingOrders,
            'ordersByStatus' => $ordersByStatus,
            'salesByDate' => $salesByDate,
            'orderCountByDate' => $orderCountByDate
        ]);
    }

    public function approveOrder() {
        // Allow both 'admin' and 'superadmin' (case-insensitive)
        session_start();
        $role = strtolower($_SESSION['admin_role'] ?? '');
        if (empty($_SESSION['admin_id']) || !in_array($role, ['admin', 'superadmin'])) {
            header('Location: /admin/login');
            exit;
        }
        $orderId = isset($_POST['order_id']) ? intval($_POST['order_id']) : null;
        if ($orderId) {
            require_once __DIR__ . '/../Models/OrderModel.php';
            $orderModel = new \App\Models\OrderModel();
            $orderModel->approveOrder($orderId);
        }
        header('Location: /admin/orders');
        exit;
    }

    public function completeOrder() {
        session_start();
        if (empty($_SESSION['admin_id'])) {
            header('Location: /admin/login');
            exit;
        }
        $orderId = isset($_POST['order_id']) ? intval($_POST['order_id']) : null;
        if ($orderId) {
            require_once __DIR__ . '/../Models/OrderModel.php';
            $orderModel = new \App\Models\OrderModel();
            $orderModel->completeOrder($orderId);
        }
        header('Location: /admin/orders');
        exit;
    }

    // NEWBOOKING (WALK-IN BOOKING)
    public function newOrder() {
        $this->requireAdmin();

        if (!isset($_SESSION['admin_id'])) {
            header('Location: /admin/login');
            exit;
        }

        $productModel = new ProductModel();
        $products = $productModel->getAllProductsBasic();
        $productIds = array_values(array_filter(array_map(static function ($product) {
            return isset($product['product_id']) ? (int)$product['product_id'] : null;
        }, $products)));
        $rentalPrices = $productModel->getRentalPricesForProducts($productIds);

        // Fetch variations for each product using public getter
        $db = $productModel->getDb();
        foreach ($products as &$product) {
            if (!isset($product['product_id'])) continue;
            $product['variations'] = [];
            $stmt = $db->prepare("SELECT variation_id, variation_name FROM product_variations WHERE product_id = ? ORDER BY variation_name ASC");
            $stmt->execute([$product['product_id']]);
            $product['variations'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        unset($product);

        $this->renderAdmin('admin/new-order', [
            'products' => $products,
            'rentalPrices' => $rentalPrices
        ]);
    }

    public function newOrderAvailability() {
        $this->requireAdmin();

        header('Content-Type: application/json');

        $pickupDatetime = trim($_GET['pickup_datetime'] ?? '');
        $returnDatetime = trim($_GET['return_datetime'] ?? '');

        $productModel = new ProductModel();
        $availability = $productModel->getAvailabilityCountsForWindow(
            $pickupDatetime !== '' ? $pickupDatetime : null,
            $returnDatetime !== '' ? $returnDatetime : null
        );

        echo json_encode([
            'availability' => $availability,
        ]);
        exit;
    }

    // ALSO FOR NEWBOOKING (WALK-IN BOOKING)
    public function processNewOrder() {
        $this->requireAdmin();
        // VALIDATE CSRF TOKEN
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
                http_response_code(403);
                die('Invalid CSRF token');
            }
        }


        // Gather and sanitize order data
        $guestFirstName = htmlspecialchars(trim($_POST['guest_first_name'] ?? ''));
        $guestLastName = htmlspecialchars(trim($_POST['guest_last_name'] ?? ''));
        $fullName = trim($guestFirstName . ' ' . $guestLastName);

        $orderData = [
            'user_id' => null,
            'guest_name' => htmlspecialchars($fullName),
            'guest_first_name' => $guestFirstName,
            'guest_last_name' => $guestLastName,
            'guest_email' => filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL),
            'guest_phone' => preg_replace('/\D/', '', $_POST['phone'] ?? ''),
            'address1' => htmlspecialchars(trim($_POST['address1'] ?? '')),
            // Pickup location is set to default for walk-in booking
            'pickup_location' => 'walk-in booking',
            'notes' => htmlspecialchars(trim($_POST['notes'] ?? '')),
            'payment_method' => htmlspecialchars(trim($_POST['payment_method'] ?? '')),
            'total_amount' => filter_var($_POST['total_amount'] ?? '', FILTER_VALIDATE_FLOAT),
            'customer_type' => 'guest',
            'sale_type' => htmlspecialchars(trim($_POST['sale_type'] ?? '')),
            'pickup_datetime' => htmlspecialchars(trim($_POST['pickup_datetime'] ?? '')),
            'return_datetime' => htmlspecialchars(trim($_POST['return_datetime'] ?? '')),
        ];
        $cart = isset($_POST['cart']) ? json_decode($_POST['cart'], true) : [];

        require_once __DIR__ . '/../Models/OrderModel.php';
        $orderModel = new \App\Models\OrderModel();        
        // Validate stock availability before placing order
        $stockValidation = $orderModel->validateStockAvailability(
            $cart,
            $orderData['pickup_datetime'] ?? null,
            $orderData['return_datetime'] ?? null
        );
        
        if (!$stockValidation['valid']) {
            $_SESSION['form_errors'] = $stockValidation['errors'];
            header('Location: /admin/orders/new');
            exit;
        }
                $result = $orderModel->placeOrder($orderData, $cart);

        if (isset($result['errors']) && count($result['errors']) > 0) {
            $_SESSION['form_errors'] = $result['errors'];
            header('Location: /admin/orders/new');
            exit;
        }

        $order_id = $result['order_id'] ?? null;
        $name = trim(($orderData['guest_first_name'] ?? '') . ' ' . ($orderData['guest_last_name'] ?? ''));
        if ($name === '') {
            $name = $orderData['guest_name'] ?? '';
        }
        $email = $orderData['guest_email'];
        $phone = $orderData['guest_phone'] ?? '';
        $address1 = $orderData['address1'] ?? '';
        $pickup_datetime = $orderData['pickup_datetime'];
        $return_datetime = $orderData['return_datetime'];
        $pickup_location = $orderData['pickup_location'];
        $notes = $orderData['notes'];
        $payment_method = $orderData['payment_method'];
        $total_amount = $orderData['total_amount'];
        // $cart is already set

        // Generate invoice/contract (redirect to invoice page)
        // You should have a route and view for this
        // Example: /admin/orders/invoice/{order_id}
        // You can also generate PDF here if needed

        // Send email notification (simple mail example)
        $subject = "Your Walk-in Booking Confirmation";
        $message = "Dear $name,\n\nYour booking has been received. Order ID: $order_id\nPickup: $pickup_datetime\nReturn: $return_datetime\n\nThank you!";
        @mail($email, $subject, $message);

        // Prepare order summary for email
        $orderSummary = "Thank you for your order!\n\nOrder Details:\n";
        $orderSummary .= "----------------------------------------\n";
        foreach ($cart as $item) {
            $lineTotal = $item['qty'] * $item['price'];
            $orderSummary .= "{$item['qty']} x {$item['name']} @ $" . number_format($item['price'], 2) . " each = $" . number_format($lineTotal, 2) . "\n";
        }
        $orderSummary .= "----------------------------------------\n";
        $orderSummary .= "Subtotal: $" . number_format($total_amount, 2) . "\n";
        $orderSummary .= "Pickup Location: {$pickup_location}\n";
        $orderSummary .= "Notes: {$notes}\n";
        $orderSummary .= "Payment Method: {$payment_method}\n";

        // --- CONTRACT PDF GENERATION ---
        // Populate template variables expected by contract/invoice templates
        $orderId = $order_id;
        $finalName = $name;
        $finalEmail = $email;
        $address2 = $address2 ?? '';
        $state = $state ?? '';
        $zip = $zip ?? '';

        // compute totals (subtotal, tax, total with tax) used by templates
        $totalAmount = 0.0;
        foreach ($cart as $cItem) {
            $totalAmount += (float)$cItem['qty'] * (float)$cItem['price'];
        }
        $tax = round($totalAmount * 0.12, 2); // 12% tax as used elsewhere
        $totalAmountWithTax = round($totalAmount + $tax, 2);

        $customerName = $name;
        $customerEmail = $email;
        $customerPhone = $phone;
        $customerAddress = $address1;
        $pickupDate = $pickup_datetime;
        $returnDate = $return_datetime;
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
            $qty = htmlspecialchars($item['qty']);
            $nameItem = htmlspecialchars($item['name']);
            $unitPrice = '$' . number_format($item['price'], 2);
            $lineTotal = '$' . number_format($item['qty'] * $item['price'], 2);
            $itemsTable .= "<tr>
                <td class='border px-2 py-1'>{$qty}</td>
                <td class='border px-2 py-1'>{$nameItem}</td>
                <td class='border px-2 py-1'>{$unitPrice}</td>
                <td class='border px-2 py-1'>{$lineTotal}</td>
            </tr>";
        }
        $itemsTable .= '</tbody></table>';

        ob_start();
        include __DIR__ . '/../../Contracts/contract-template.php';
        $html = ob_get_clean();

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdfDir = __DIR__ . '/../../Contracts/';
        if (!is_dir($pdfDir)) mkdir($pdfDir, 0777, true);
        file_put_contents($pdfDir . "contract-{$order_id}.pdf", $dompdf->output());
        $pdfPath = $pdfDir . "contract-{$order_id}.pdf";

        // --- INVOICE PDF GENERATION ---
        $invoiceItemsTable = '';
        foreach ($cart as $item) {
            $qty = htmlspecialchars($item['qty']);
            $nameItem = htmlspecialchars($item['name']);
            $unitPrice = number_format($item['price'], 2);
            $lineTotal = number_format($item['qty'] * $item['price'], 2);
            $invoiceItemsTable .= "<tr>
                <td class='border p-2'>{$qty}</td>
                <td class='border p-2'>{$nameItem}</td>
                <td class='border p-2'>\${$unitPrice}</td>
                <td class='border p-2'>\${$lineTotal}</td>
            </tr>";
        }

        ob_start();
        include __DIR__ . '/../../Invoices/invoice-template.php';
        $invoiceHtml = ob_get_clean();

        $invoiceDompdf = new Dompdf();
        $invoiceDompdf->loadHtml($invoiceHtml);
        $invoiceDompdf->setPaper('A4', 'portrait');
        $invoiceDompdf->render();

        $invoiceDir = __DIR__ . '/../../Invoices/';
        if (!is_dir($invoiceDir)) mkdir($invoiceDir, 0777, true);
        file_put_contents($invoiceDir . "invoice-{$order_id}.pdf", $invoiceDompdf->output());
        $invoicePath = $invoiceDir . "invoice-{$order_id}.pdf";

        // --- EMAIL SENDING ---
        require_once __DIR__ . '/../Utils/Mailer.php';
        $attachments = [
            [
                'path' => $pdfPath,
                'name' => "Rental-Contract-{$order_id}.pdf"
            ],
            [
                'path' => $invoicePath,
                'name' => "Invoice-{$order_id}.pdf"
            ]
        ];
        $subject = 'Your Rental Booking Confirmation';
        $body = "Thank you for your booking! Please find your rental contract and invoice attached.";
        $result = sendBookingConfirmation($customerEmail, $customerName, $subject, $body, $attachments);
        if (!$result) {
            error_log("Mailer Error: Booking confirmation email failed to send.");
        }

        $_SESSION['booking_success'] = "Booking successful! Order ID: $order_id";
        header('Location: /admin/orders/new');
        exit;
    }

    public function rejectOrder() {
        $this->requireAdmin();
        if (!isset($_SESSION['admin_id'])) {
            header('Location: /admin/login');
            exit;
        }
        $orderId = isset($_POST['order_id']) ? intval($_POST['order_id']) : null;
        if ($orderId) {
            require_once __DIR__ . '/../Models/OrderModel.php';
            $orderModel = new \App\Models\OrderModel();
            $orderModel->rejectOrder($orderId);
        }
        header('Location: /admin/orders');
        exit;
    }

    public function markAsPaid() {
        $this->requireAdmin();
        if (!isset($_SESSION['admin_id'])) {
            header('Location: /admin/login');
            exit;
        }
        $orderId = isset($_POST['order_id']) ? intval($_POST['order_id']) : null;
        if ($orderId) {
            require_once __DIR__ . '/../Models/OrderModel.php';
            $orderModel = new \App\Models\OrderModel();
            $orderModel->markAsPaid($orderId);
        }
        header('Location: /admin/orders');
        exit;
    }

    public function logout() {
        session_start();
        session_unset();
        session_destroy();
        header('Location: /admin/login');
        exit;
    }


    /**
     * Checks if the current user is an authenticated admin and (optionally) has the required role(s).
     * @param string|array|null $roles Allowed role(s), e.g. 'admin', 'superadmin', or ['admin','superadmin']
     */
    private function requireAdmin($roles = null) {
        if (empty($_SESSION['admin_id'])) {
            header('Location: /admin/login');
            exit;
        }
        if ($roles) {
            $userRole = strtolower($_SESSION['admin_role'] ?? '');
            if (is_array($roles)) {
                $allowed = array_map('strtolower', $roles);
                if (!in_array($userRole, $allowed)) {
                    header('Location: /admin/orders');
                    exit;
                }
            } else {
                if ($userRole !== strtolower($roles)) {
                    header('Location: /admin/orders');
                    exit;
                }
            }
        }
    }

    public function featuredProducts() {
        $this->requireAdmin();

        require_once __DIR__ . '/../Models/ProductModel.php';
        $productModel = new \App\Models\ProductModel();

        // Fetch all products for dropdowns
        $products = $productModel->getAllProductsBasic();

        // Fetch all variations for all products
        $variationsByProduct = [];
        foreach ($products as $product) {
            $variationsByProduct[$product['product_id']] = $productModel->getVariationsByProductId($product['product_id']);
        }

        // Handle form submission
        $success = false;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $productIds = $_POST['product_id'] ?? [];
            $variationIds = $_POST['variation_id'] ?? [];
            // Clear all featured slots and featured_variation_id
            $db = $productModel->getDb();
            $db->query("UPDATE products SET featured_slot = NULL, featured_variation_id = NULL");
            // Assign selected products and variations to slots
            for ($i = 0; $i < 6; $i++) {
                if (!empty($productIds[$i])) {
                    $productId = intval($productIds[$i]);
                    $variationId = !empty($variationIds[$i]) ? intval($variationIds[$i]) : null;
                    $slot = $i + 1;
                    $stmt = $db->prepare("UPDATE products SET featured_slot = ?, featured_variation_id = ? WHERE product_id = ?");
                    $stmt->execute([$slot, $variationId, $productId]);
                }
            }
            $success = true;
        }

        // Fetch current featured products and variations for each slot
        $db = $productModel->getDb();
        $stmt = $db->query("SELECT featured_slot, product_id, featured_variation_id FROM products WHERE featured_slot IS NOT NULL ORDER BY featured_slot ASC");
        $featuredProductIds = [];
        $featuredVariationIds = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $featuredProductIds[$row['featured_slot'] - 1] = $row['product_id'];
            $featuredVariationIds[$row['featured_slot'] - 1] = $row['featured_variation_id'];
        }

        $this->renderAdmin('admin/featured-products', [
            'products' => $products,
            'variationsByProduct' => $variationsByProduct,
            'featuredProductIds' => $featuredProductIds,
            'featuredVariationIds' => $featuredVariationIds,
            'success' => $success
        ]);
    }

    public function admins() {
        $this->requireAdmin();

        require_once __DIR__ . '/../Models/AdminModel.php';
        $pdo = \App\Utils\Database::getInstance();
        $adminModel = new \App\Models\AdminModel($pdo);
        $admins = $adminModel->getAllAdmins();

        $this->renderAdmin('admin/admins', [
            'admins' => $admins
        ]);
    }

    public function addAdmin() {
        $this->requireAdmin('superadmin');

        require_once __DIR__ . '/../Models/AdminModel.php';
        $pdo = \App\Utils\Database::getInstance();
        $adminModel = new \App\Models\AdminModel($pdo);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // VALIDATE CSRF TOKEN
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
                http_response_code(403);
                die('Invalid CSRF token');
            }
            $username = htmlspecialchars(trim($_POST['username']));
            $role = htmlspecialchars(trim($_POST['role']));
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $adminModel->addAdmin($username, $password, $role);
            header('Location: /admin/admins');
            exit;
        }

        $this->renderAdmin('admin/add_admin');
    }

    public function editAdmin() {
        $this->requireAdmin('superadmin');

        require_once __DIR__ . '/../Models/AdminModel.php';
        $pdo = \App\Utils\Database::getInstance();
        $adminModel = new \App\Models\AdminModel($pdo);
        $id = isset($_GET['id']) ? intval($_GET['id']) : null;
        if (!$id) {
            header('Location: /admin/admins');
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // VALIDATE CSRF TOKEN
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
                http_response_code(403);
                die('Invalid CSRF token');
            }
            $username = htmlspecialchars(trim($_POST['username']));
            $role = htmlspecialchars(trim($_POST['role']));
            $adminModel->updateAdmin($id, $username, $role);
            header('Location: /admin/admins');
            exit;
        }
        $admin = $adminModel->getAdminById($id);
        $this->renderAdmin('admin/edit_admin', [
            'admin' => $admin
        ]);
    }

    public function deleteAdmin() {
        $this->requireAdmin('superadmin');

        require_once __DIR__ . '/../Models/AdminModel.php';
        $pdo = \App\Utils\Database::getInstance();
        $adminModel = new \App\Models\AdminModel($pdo);

        // VALIDATE CSRF TOKEN
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
                http_response_code(403);
                die('Invalid CSRF token');
            }
        }

        $id = isset($_POST['id']) ? intval($_POST['id']) : null;
        if ($id) {
            $adminModel->deleteAdmin($id);
        }
        header('Location: /admin/admins');
        exit;
    }

    // Show reservations page
    public function reservations() {
        $this->requireAdmin();
        $perPage = 30;
        $page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
        $status = isset($_GET['status']) ? strtolower(trim($_GET['status'])) : 'pending';
        if ($status === 'paid') $status = 'pending'; // treat 'paid' as 'pending' for filter logic
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $reservationModel = new ReservationModel();
        $result = $reservationModel->getReservations($status, $page, $perPage, $search);
        $this->renderAdmin('admin/reservations', [
            'reservations' => $result['reservations'],
            'totalReservations' => $result['totalReservations'],
            'totalPages' => $result['totalPages'],
            'page' => $page,
            'perPage' => $perPage,
            'status' => $status,
            'search' => $search
        ]);
    }

    // ===================== TESTIMONIALS ADMIN =====================
    public function testimonials() {
        $this->requireAdmin();
        require_once __DIR__ . '/../Models/TestimonialsModel.php';
        $testimonialsModel = new \App\Models\TestimonialsModel();
        $testimonials = $testimonialsModel->getAllTestimonials();
        $this->renderAdmin('admin/testimonials', [
            'testimonials' => $testimonials
        ]);
    }

    public function addTestimonial() {
        $this->requireAdmin(['admin', 'superadmin']);
        require_once __DIR__ . '/../Models/TestimonialsModel.php';
        $testimonialsModel = new \App\Models\TestimonialsModel();
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $reviewer_name = htmlspecialchars(trim($_POST['reviewer_name'] ?? ''));
            $review_text = htmlspecialchars(trim($_POST['review_text'] ?? ''));
            $star_rating = intval($_POST['star_rating'] ?? 5);
            if (!$reviewer_name || !$review_text || $star_rating < 1 || $star_rating > 5) {
                $error = 'All fields are required and star rating must be 1-5.';
            } else {
                $testimonialsModel->addTestimonial($reviewer_name, $review_text, $star_rating);
                header('Location: /admin/testimonials');
                exit;
            }
        }
        $this->renderAdmin('admin/add_testimonial', [
            'error' => $error
        ]);
    }

    public function editTestimonial() {
        $this->requireAdmin(['admin', 'superadmin']);
        require_once __DIR__ . '/../Models/TestimonialsModel.php';
        $testimonialsModel = new \App\Models\TestimonialsModel();
        $id = isset($_GET['id']) ? intval($_GET['id']) : null;
        if (!$id) {
            header('Location: /admin/testimonials');
            exit;
        }
        $testimonial = $testimonialsModel->getTestimonialById($id);
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $reviewer_name = htmlspecialchars(trim($_POST['reviewer_name'] ?? ''));
            $review_text = htmlspecialchars(trim($_POST['review_text'] ?? ''));
            $star_rating = intval($_POST['star_rating'] ?? 5);
            if (!$reviewer_name || !$review_text || $star_rating < 1 || $star_rating > 5) {
                $error = 'All fields are required and star rating must be 1-5.';
            } else {
                $testimonialsModel->updateTestimonial($id, $reviewer_name, $review_text, $star_rating);
                header('Location: /admin/testimonials');
                exit;
            }
        }
        $this->renderAdmin('admin/edit_testimonial', [
            'testimonial' => $testimonial,
            'error' => $error
        ]);
    }

    public function deleteTestimonial() {
        $this->requireAdmin(['admin', 'superadmin']);
        require_once __DIR__ . '/../Models/TestimonialsModel.php';
        $testimonialsModel = new \App\Models\TestimonialsModel();
        $id = isset($_POST['id']) ? intval($_POST['id']) : null;
        if ($id) {
            $testimonialsModel->deleteTestimonial($id);
        }
        header('Location: /admin/testimonials');
        exit;
    }

    public function tipsTroubleshooting() {
        $this->requireAdmin();
        require_once __DIR__ . '/../Models/TipsTroubleshootingModel.php';

        $tipsModel = new TipsTroubleshootingModel();
        $section = $tipsModel->getSection();

        $perPage = 3;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $totalArticles = $tipsModel->countArticles();
        $totalPages = (int) ceil($totalArticles / $perPage);
        if ($page > $totalPages && $totalPages > 0) {
            $page = $totalPages;
        }
        $articles = $tipsModel->getArticlesPaginated($page, $perPage);
        $allArticles = $tipsModel->getArticles();

        $success = $_SESSION['tips_troubleshooting_success'] ?? '';
        $error = $_SESSION['tips_troubleshooting_error'] ?? '';
        unset($_SESSION['tips_troubleshooting_success'], $_SESSION['tips_troubleshooting_error']);

        $this->renderAdmin('admin/tips_troubleshooting', [
            'section'      => $section,
            'articles'     => $articles,
            'allArticles'  => $allArticles,
            'page'         => $page,
            'totalPages'   => $totalPages,
            'success'      => $success,
            'error'        => $error
        ]);
    }

    public function toggleFeaturedTipsArticle() {
        $this->requireAdmin(['admin', 'superadmin']);
        require_once __DIR__ . '/../Models/TipsTroubleshootingModel.php';

        $tipsModel = new TipsTroubleshootingModel();

        // Handle display-order save (array of article ids in desired order)
        if (isset($_POST['featured_order']) && is_array($_POST['featured_order'])) {
            $orderedIds = array_map('intval', $_POST['featured_order']);
            $orderedIds = array_filter($orderedIds, static fn($id) => $id > 0);
            $tipsModel->updateFeaturedOrder(array_values($orderedIds));
            $_SESSION['tips_troubleshooting_success'] = 'Featured article order saved.';
            $page = max(1, (int) ($_POST['page'] ?? 1));
            header('Location: /admin/tips-troubleshooting?page=' . $page);
            exit;
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Location: /admin/tips-troubleshooting');
            exit;
        }

        $featured = (int) ($_POST['is_featured'] ?? 0);
        $article = $tipsModel->getArticleById($id);
        if (!$article) {
            $_SESSION['tips_troubleshooting_error'] = 'Article not found.';
            $page = max(1, (int) ($_POST['page'] ?? 1));
            header('Location: /admin/tips-troubleshooting?page=' . $page);
            exit;
        }

        if ($featured === 1 && empty($article['is_featured']) && $tipsModel->countFeaturedArticles() >= 6) {
            $_SESSION['tips_troubleshooting_error'] = 'You can only feature up to 6 articles.';
            $page = max(1, (int) ($_POST['page'] ?? 1));
            header('Location: /admin/tips-troubleshooting?page=' . $page);
            exit;
        }

        $tipsModel->toggleFeatured($id, $featured === 1);
        $_SESSION['tips_troubleshooting_success'] = 'Article updated.';
        $page = max(1, (int) ($_POST['page'] ?? 1));
        header('Location: /admin/tips-troubleshooting?page=' . $page);
        exit;
    }

    public function saveTipsTroubleshootingSection() {
        $this->requireAdmin(['admin', 'superadmin']);
        require_once __DIR__ . '/../Models/TipsTroubleshootingModel.php';

        $tipsModel = new TipsTroubleshootingModel();
        $heading = trim($_POST['heading'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($heading === '' || $description === '') {
            $_SESSION['tips_troubleshooting_error'] = 'Heading and description are required.';
            header('Location: /admin/tips-troubleshooting');
            exit;
        }

        $tipsModel->updateSection($heading, $description);
        $_SESSION['tips_troubleshooting_success'] = 'Tips section updated.';
        header('Location: /admin/tips-troubleshooting');
        exit;
    }

    public function addTipsTroubleshootingArticle() {
        $this->requireAdmin(['admin', 'superadmin']);
        require_once __DIR__ . '/../Models/TipsTroubleshootingModel.php';

        $tipsModel = new TipsTroubleshootingModel();
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($title === '' || $description === '') {
            $_SESSION['tips_troubleshooting_error'] = 'Article title and description are required.';
            header('Location: /admin/tips-troubleshooting');
            exit;
        }

        $imagePath = null;
        if (isset($_FILES['article_image']) && (int)($_FILES['article_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $imagePath = $this->storeTipsTroubleshootingArticleImage($_FILES['article_image']);
            } catch (\RuntimeException $exception) {
                $_SESSION['tips_troubleshooting_error'] = $exception->getMessage();
                header('Location: /admin/tips-troubleshooting');
                exit;
            }
        }

        $tipsModel->addArticle($title, $description, $imagePath);
        $_SESSION['tips_troubleshooting_success'] = 'Article added.';
        header('Location: /admin/tips-troubleshooting');
        exit;
    }

    public function updateTipsTroubleshootingArticle() {
        $this->requireAdmin(['admin', 'superadmin']);
        require_once __DIR__ . '/../Models/TipsTroubleshootingModel.php';

        $tipsModel = new TipsTroubleshootingModel();
        $id = (int) ($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $currentImagePath = trim($_POST['current_image_path'] ?? '');
        $page = max(1, (int) ($_POST['page'] ?? 1));

        if ($id <= 0 || $title === '' || $description === '') {
            $_SESSION['tips_troubleshooting_error'] = 'Invalid article update request.';
            header('Location: /admin/tips-troubleshooting?page=' . $page);
            exit;
        }

        $imagePath = $currentImagePath !== '' ? $currentImagePath : null;
        if (isset($_FILES['article_image']) && (int)($_FILES['article_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $imagePath = $this->storeTipsTroubleshootingArticleImage($_FILES['article_image']);
            } catch (\RuntimeException $exception) {
                $_SESSION['tips_troubleshooting_error'] = $exception->getMessage();
                header('Location: /admin/tips-troubleshooting?page=' . $page);
                exit;
            }
        }

        $tipsModel->updateArticle($id, $title, $description, $imagePath);
        $_SESSION['tips_troubleshooting_success'] = 'Article updated.';
        header('Location: /admin/tips-troubleshooting?page=' . $page);
        exit;
    }

    public function deleteTipsTroubleshootingArticle() {
        $this->requireAdmin(['admin', 'superadmin']);
        require_once __DIR__ . '/../Models/TipsTroubleshootingModel.php';

        $id = (int) ($_POST['id'] ?? 0);
        $page = max(1, (int) ($_POST['page'] ?? 1));
        if ($id > 0) {
            $tipsModel = new TipsTroubleshootingModel();
            $tipsModel->deleteArticle($id);
            $_SESSION['tips_troubleshooting_success'] = 'Article deleted.';
        }

        header('Location: /admin/tips-troubleshooting?page=' . $page);
        exit;
    }

    private function storeTipsTroubleshootingArticleImage(array $imageFile) {
        $errorCode = (int) ($imageFile['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($errorCode !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Article image upload failed.');
        }

        $uploadDir = dirname(__DIR__, 2) . '/public/img/uploads';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            throw new \RuntimeException('Unable to create the image upload folder.');
        }

        $originalName = (string) ($imageFile['name'] ?? '');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
        if (!in_array($extension, $allowedExtensions, true)) {
            throw new \RuntimeException('Please upload JPG, PNG, WEBP, or SVG images only.');
        }

        $tmpFile = (string) ($imageFile['tmp_name'] ?? '');
        if ($tmpFile === '') {
            throw new \RuntimeException('Invalid uploaded article image.');
        }

        $fileName = 'tips-article-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
        $destination = $uploadDir . '/' . $fileName;
        if (!move_uploaded_file($tmpFile, $destination)) {
            throw new \RuntimeException('Unable to save the article image.');
        }

        return '/img/uploads/' . $fileName;
    }

    private function isPostBodyTooLarge() {
        $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($contentLength <= 0) {
            return false;
        }

        $maxPostSize = $this->parseIniSize(ini_get('post_max_size'));
        if ($maxPostSize <= 0) {
            return false;
        }

        return $contentLength > $maxPostSize && empty($_POST) && empty($_FILES);
    }

    private function parseIniSize($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $number = (float) $value;

        switch ($unit) {
            case 'g':
                $number *= 1024;
            case 'm':
                $number *= 1024;
            case 'k':
                $number *= 1024;
                break;
        }

        return (int) round($number);
    }

    
    
}