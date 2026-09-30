<?php
namespace App\Controllers;
use App\Controller;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dompdf\Dompdf;
use App\Models\AdminModel;
use App\Models\OrderModel;
use App\Models\ProductModel;
use App\Models\PromoCodeModel;
use App\Models\AdminAuditLogModel;
use App\Models\AdminLoginAttemptModel;
use App\Models\HeardAboutOptionModel;
use App\Models\ReservationModel;
use App\Models\TipsTroubleshootingModel;
use App\Services\AdminImageUploadService;

class AdminController extends Controller
{
    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
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
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            die('Invalid CSRF token');
        }

        $username = trim((string)($_POST['username'] ?? ''));
        $password = $_POST['password'] ?? '';
        $ipAddress = $this->getLoginIpAddress();
        $throttle = new AdminLoginAttemptModel();
        $lockout = $throttle->getLockout('admin', $username, $ipAddress);
        if ($lockout['locked']) {
            $error = $this->formatLoginLockoutMessage((int)$lockout['remaining_seconds']);
            require __DIR__ . '/../Views/admin/login.php';
            return;
        }

        $pdo = \App\Utils\Database::getInstance();
        $adminModel = new AdminModel($pdo);
        $admin = $adminModel->findByUsername($username);

        if ($admin && password_verify($password, $admin['password'])) {
            $throttle->recordSuccess('admin', $username, $ipAddress);
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_role'] = $admin['role'];
            header('Location: /admin/orders');
            exit;
        } else {
            $throttle->recordFailure('admin', $username, $ipAddress);
            $error = "Invalid username or password.";
            require __DIR__ . '/../Views/admin/login.php';
        }
    }

    public function walkinBookingLogin() {
        if (!empty($_SESSION['admin_id'])) {
            header('Location: /walkin-booking');
            exit;
        }
        require __DIR__ . '/../Views/admin/walkin-login.php';
    }

    public function processWalkinBookingLogin() {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            die('Invalid CSRF token');
        }

        $username = trim((string)($_POST['username'] ?? ''));
        $password = $_POST['password'] ?? '';
        $ipAddress = $this->getLoginIpAddress();
        $throttle = new AdminLoginAttemptModel();
        $lockout = $throttle->getLockout('walkin', $username, $ipAddress);
        if ($lockout['locked']) {
            $error = $this->formatLoginLockoutMessage((int)$lockout['remaining_seconds']);
            require __DIR__ . '/../Views/admin/walkin-login.php';
            return;
        }

        $pdo = \App\Utils\Database::getInstance();
        $adminModel = new AdminModel($pdo);
        $admin = $adminModel->findByUsername($username);

        if ($admin && password_verify($password, $admin['password'])) {
            $throttle->recordSuccess('walkin', $username, $ipAddress);
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_role'] = $admin['role'];
            header('Location: /walkin-booking');
            exit;
        }

        $throttle->recordFailure('walkin', $username, $ipAddress);
        $error = 'Invalid username or password.';
        require __DIR__ . '/../Views/admin/walkin-login.php';
    }

    public function walkinBookingLogout() {
        session_unset();
        session_destroy();
        header('Location: /walkin-booking/login');
        exit;
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
        $bookingSourceFilter = isset($_GET['booking_source']) ? trim($_GET['booking_source']) : '';
        $heardAboutFilter = isset($_GET['heard_about']) ? trim($_GET['heard_about']) : '';
        $promoUsageFilter = isset($_GET['promo_usage']) ? trim($_GET['promo_usage']) : '';
        $creatorRoleFilter = isset($_GET['creator_role']) ? trim($_GET['creator_role']) : '';
        $quickPeriodFilter = isset($_GET['quick_period']) ? strtolower(trim((string)$_GET['quick_period'])) : '';
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

        $allowedBookingSources = ['walk-in', 'online'];
        if (!in_array(strtolower($bookingSourceFilter), $allowedBookingSources, true)) {
            $bookingSourceFilter = '';
        }

        $allowedPromoUsage = ['with', 'without'];
        if (!in_array(strtolower($promoUsageFilter), $allowedPromoUsage, true)) {
            $promoUsageFilter = '';
        }

        $allowedCreatorRoles = ['superadmin', 'admin', 'staff', 'partner'];
        if (!in_array(strtolower($creatorRoleFilter), $allowedCreatorRoles, true)) {
            $creatorRoleFilter = '';
        }

        $allowedQuickPeriods = ['late', 'today', 'upcoming'];
        if (!in_array($quickPeriodFilter, $allowedQuickPeriods, true)) {
            $quickPeriodFilter = '';
        }

        if ($dateFromFilter !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFromFilter)) {
            $dateFromFilter = '';
        }

        if ($dateToFilter !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateToFilter)) {
            $dateToFilter = '';
        }

        $allowedSortBy = ['order_id', 'sale_type', 'total_amount', 'sales_amount', 'refund_amount', 'status', 'order_date', 'pickup_datetime', 'return_datetime'];
        if (!in_array($sortBy, $allowedSortBy, true)) {
            $sortBy = 'order_id';
        }

        $sortDir = strtolower($sortDir) === 'asc' ? 'asc' : 'desc';

        $filters = [
            'order_id_search' => $searchTerm,
            'status' => strtolower($statusFilter),
            'customer_type' => strtolower($customerTypeFilter),
            'sale_type' => strtolower($saleTypeFilter),
            'booking_source' => strtolower($bookingSourceFilter),
            'heard_about' => $heardAboutFilter,
            'promo_usage' => strtolower($promoUsageFilter),
            'creator_role' => strtolower($creatorRoleFilter),
            'quick_period' => $quickPeriodFilter,
            'date_from' => $dateFromFilter,
            'date_to' => $dateToFilter,
            'sort_by' => $sortBy,
            'sort_dir' => $sortDir,
        ];

        $heardAboutFilterOptions = method_exists($orderModel, 'getHeardAboutFilterOptions')
            ? $orderModel->getHeardAboutFilterOptions()
            : [];

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

        $pdo = \App\Utils\Database::getInstance();
        $hotelStmt = $pdo->query("SELECT id, name FROM partner_hotels ORDER BY name ASC");
        $partnerHotels = $hotelStmt ? $hotelStmt->fetchAll(\PDO::FETCH_ASSOC) : [];

        // Use renderAdmin to render the orders page with admin layout
        $this->renderAdmin('admin/orders', [
            'orders' => $orders,
            'partnerHotels' => $partnerHotels,
            'searchTerm' => $searchTerm,
            'statusFilter' => strtolower($statusFilter),
            'customerTypeFilter' => strtolower($customerTypeFilter),
            'saleTypeFilter' => strtolower($saleTypeFilter),
            'bookingSourceFilter' => strtolower($bookingSourceFilter),
            'heardAboutFilter' => $heardAboutFilter,
            'heardAboutFilterOptions' => $heardAboutFilterOptions,
            'promoUsageFilter' => strtolower($promoUsageFilter),
            'creatorRoleFilter' => strtolower($creatorRoleFilter),
            'quickPeriodFilter' => $quickPeriodFilter,
            'dateFromFilter' => $dateFromFilter,
            'dateToFilter' => $dateToFilter,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
            'page' => $page,
            'perPage' => $perPage,
            'totalOrders' => $totalOrders,
            'totalPages' => $totalPages,
        ]);
    }

    public function blockedDates() {
        $this->requireAdmin();
        if (!isset($_SESSION['admin_id'])) {
            header('Location: /admin/login');
            exit;
        }

        $role = strtolower((string)($_SESSION['admin_role'] ?? ''));
        $canEdit = in_array($role, ['superadmin', 'admin'], true);

        $orderModel = new OrderModel();
        $blockedDates = method_exists($orderModel, 'getBlockedDatesWithReason')
            ? $orderModel->getBlockedDatesWithReason()
            : [];

        $this->renderAdmin('admin/blocked-dates', [
            'blockedDates' => $blockedDates,
            'canEditBlockedDates' => $canEdit,
        ]);
    }

    public function getBlockedDatesApi() {
        $orderModel = new OrderModel();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'blocked_dates' => $orderModel->getBlockedDates(),
        ]);
        exit;
    }

    public function addBlockedDate() {
        $this->requireAdmin(['admin', 'superadmin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /admin/blocked-dates');
            exit;
        }
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            die('Invalid CSRF token');
        }

        $blockedDate = trim((string)($_POST['blocked_date'] ?? ''));
        $reason = trim((string)($_POST['reason'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $blockedDate)) {
            $_SESSION['order_cancel_message'] = 'Invalid blocked date format.';
            header('Location: /admin/blocked-dates');
            exit;
        }

        $orderModel = new OrderModel();
        try {
            $orderModel->addBlockedDate($blockedDate, $reason !== '' ? $reason : null, isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null);
            $this->logAdminAction('blocked_date_added', 'blocked_date', null, [
                'blocked_date' => $blockedDate,
                'reason' => $reason !== '' ? $reason : null,
            ]);
            $_SESSION['order_complete_message'] = 'Blocked date saved successfully.';
        } catch (\Throwable $e) {
            $_SESSION['order_cancel_message'] = 'Unable to save blocked date. It may already exist.';
        }

        header('Location: /admin/blocked-dates');
        exit;
    }

    public function removeBlockedDate() {
        $this->requireAdmin(['admin', 'superadmin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /admin/blocked-dates');
            exit;
        }
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            die('Invalid CSRF token');
        }

        $blockedDateId = isset($_POST['blocked_date_id']) ? (int)$_POST['blocked_date_id'] : 0;
        if ($blockedDateId <= 0) {
            $_SESSION['order_cancel_message'] = 'Invalid blocked date record.';
            header('Location: /admin/blocked-dates');
            exit;
        }

        $orderModel = new OrderModel();
        if ($orderModel->removeBlockedDateById($blockedDateId)) {
            $this->logAdminAction('blocked_date_removed', 'blocked_date', $blockedDateId);
            $_SESSION['order_complete_message'] = 'Blocked date removed successfully.';
        } else {
            $_SESSION['order_cancel_message'] = 'Blocked date could not be removed.';
        }

        header('Location: /admin/blocked-dates');
        exit;
    }

    public function orderAnalytics() {
        $this->requireAdmin();
        if (!isset($_SESSION['admin_id'])) {
            header('Location: /admin/login');
            exit;
        }

        $period = strtolower(trim((string)($_GET['period'] ?? 'month')));
        $periodOptions = [
            'week' => ['days' => 7, 'label' => 'Past Week'],
            'month' => ['days' => 30, 'label' => 'Past Month'],
            '3_months' => ['days' => 90, 'label' => 'Past 3 Months'],
            '6_months' => ['days' => 180, 'label' => 'Past 6 Months'],
            'year' => ['days' => 365, 'label' => 'Past Year'],
            '2_years' => ['days' => 730, 'label' => 'Past 2 Years'],
            'all' => ['days' => null, 'label' => 'All Time'],
        ];
        if (!isset($periodOptions[$period])) {
            $period = 'month';
        }

        $periodDays = $periodOptions[$period]['days'];
        $periodLabel = $periodOptions[$period]['label'];

        $orderModel = new OrderModel();
        $analyticsSummary = $orderModel->getAnalyticsSummary($periodDays);
        $ordersByStatus = $orderModel->getOrdersByStatus($periodDays);
        $salesByDate = $orderModel->getSalesByDate($periodDays);
        $orderCountByDate = $orderModel->getOrderCountByDate($periodDays);
        $refundsByDate = $orderModel->getRefundsByDate($periodDays);
        $paymentProviderBreakdown = $orderModel->getPaymentProviderBreakdown($periodDays);
        $heardAboutBreakdown = method_exists($orderModel, 'getHeardAboutBreakdown')
            ? $orderModel->getHeardAboutBreakdown($periodDays)
            : [];

        $this->renderAdmin('admin/orders-analytics', [
            'period' => $period,
            'periodLabel' => $periodLabel,
            'periodOptions' => $periodOptions,
            'analyticsSummary' => $analyticsSummary,
            'ordersByStatus' => $ordersByStatus,
            'salesByDate' => $salesByDate,
            'orderCountByDate' => $orderCountByDate,
            'refundsByDate' => $refundsByDate,
            'paymentProviderBreakdown' => $paymentProviderBreakdown,
            'heardAboutBreakdown' => $heardAboutBreakdown,
        ]);
    }

    public function approveOrder() {
        $this->requireAdmin(['admin', 'superadmin']);
        $this->requirePostWithCsrf('/admin/orders');

        $orderId = isset($_POST['order_id']) ? intval($_POST['order_id']) : null;
        if ($orderId) {
            require_once __DIR__ . '/../Models/OrderModel.php';
            $orderModel = new \App\Models\OrderModel();
            $transition = $orderModel->transitionOrderStatus($orderId, 'approved');
            if (!($transition['success'] ?? false)) {
                $_SESSION['order_cancel_message'] = (string)($transition['error'] ?? 'Order could not be approved.');
            } else {
                $this->logAdminAction('order_approved', 'order', $orderId, [
                    'previous_status' => $transition['current_status'] ?? null,
                    'new_status' => 'approved',
                ]);
                $_SESSION['order_complete_message'] = "Order {$orderId} approved.";
            }
        }
        header('Location: /admin/orders');
        exit;
    }

    public function completeOrder() {
        $this->requireAdmin(['admin', 'superadmin']);
        $this->requirePostWithCsrf('/admin/orders');

        $orderId = isset($_POST['order_id']) ? intval($_POST['order_id']) : null;
        if ($orderId) {
            require_once __DIR__ . '/../Models/OrderModel.php';
            $orderModel = new \App\Models\OrderModel();
            $transition = $orderModel->transitionOrderStatus($orderId, 'completed');
            if (!($transition['success'] ?? false)) {
                $_SESSION['order_cancel_message'] = (string)($transition['error'] ?? 'Order could not be completed.');
            } else {
                $this->logAdminAction('order_completed', 'order', $orderId, [
                    'previous_status' => $transition['current_status'] ?? null,
                    'new_status' => 'completed',
                    'handler' => 'admin_controller',
                ]);
                $_SESSION['order_complete_message'] = "Order {$orderId} completed.";
            }
        }
        header('Location: /admin/orders');
        exit;
    }

    // NEWBOOKING (WALK-IN BOOKING)
    public function newOrder() {
        header('Location: /walkin-booking');
        exit;
    }

    public function walkinBooking() {
        $this->requireWalkinBookingAuth();

        $viewData = $this->buildWalkinBookingViewData();
        $this->renderAdminWithLayout('admin/new-order', 'admin/walkin-layout.php', $viewData + [
            'formAction' => '/walkin-booking',
            'availabilityEndpoint' => '/walkin-booking/availability',
            'cancelUrl' => '/walkin-booking/logout',
            'cancelLabel' => 'Sign Out',
            'kioskMode' => true,
        ]);
    }

    private function buildWalkinBookingViewData(): array {
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
            $stmt = $db->prepare("SELECT variation_id, variation_name FROM product_variations WHERE product_id = ? AND is_active = 1 ORDER BY variation_name ASC");
            $stmt->execute([$product['product_id']]);
            $product['variations'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        unset($product);

        $promoModel = new PromoCodeModel();
        $activePromos = array_filter($promoModel->getAll(), function($p) {
            return (int)$p['active'] === 1
                && (int)$p['uses_count'] < (int)$p['max_uses']
                && (empty($p['expires_at']) || strtotime($p['expires_at']) >= strtotime('today'));
        });

        $db = \App\Utils\Database::getInstance();
        $hotelStmt = $db->query("SELECT * FROM partner_hotels ORDER BY name ASC");
        $partnerHotels = $hotelStmt ? $hotelStmt->fetchAll(\PDO::FETCH_ASSOC) : [];

        $pickupStmt = $db->query("SELECT * FROM pickup_locations ORDER BY name ASC");
        $pickupLocations = $pickupStmt ? $pickupStmt->fetchAll(\PDO::FETCH_ASSOC) : [];

        return [
            'products' => $products,
            'rentalPrices' => $rentalPrices,
            'activePromos' => array_values($activePromos),
            'partnerHotels' => $partnerHotels,
            'pickupLocations' => $pickupLocations,
            'canEditFinalPrice' => $this->isCurrentAdminSuperadmin(),
            'formAction' => '/admin/orders/new',
            'availabilityEndpoint' => '/admin/orders/availability',
            'cancelUrl' => '/admin/orders',
            'cancelLabel' => 'Cancel',
            'kioskMode' => false,
        ];
    }

    public function walkinCheckout(): void {
        $this->requireWalkinBookingAuth();

        $draft = $_SESSION['walkin_checkout_draft'] ?? null;
        if (!is_array($draft) || empty($draft['cart']) || empty($draft['prefill'])) {
            $_SESSION['form_errors'] = ['Please complete the walk-in booking details first.'];
            header('Location: /walkin-booking');
            exit;
        }

        $pdo = \App\Utils\Database::getInstance();
        $hotelStmt = $pdo->query("SELECT * FROM partner_hotels ORDER BY name ASC");
        $partnerHotels = $hotelStmt ? $hotelStmt->fetchAll(\PDO::FETCH_ASSOC) : [];
        $pickupStmt = $pdo->query("SELECT * FROM pickup_locations ORDER BY name ASC");
        $pickupLocations = $pickupStmt ? $pickupStmt->fetchAll(\PDO::FETCH_ASSOC) : [];
        $heardAboutOptions = (new HeardAboutOptionModel())->getActive();

        $this->render('checkout', [
            'user' => null,
            'csrf_token' => $_SESSION['csrf_token'],
            'partnerHotels' => $partnerHotels,
            'pickupLocations' => $pickupLocations,
            'heardAboutOptions' => $heardAboutOptions,
            'showConfirmation' => false,
            'order' => null,
            'walkinCheckout' => true,
            'walkinPrefill' => (array)($draft['prefill'] ?? []),
            'walkinCart' => (array)($draft['cart'] ?? []),
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

    public function walkinBookingAvailability() {
        $this->requireWalkinBookingAuth();
        $this->newOrderAvailability();
    }

    // ALSO FOR NEWBOOKING (WALK-IN BOOKING)
    public function processNewOrder() {
        $this->requireAdmin();

        $isKioskContext = strtolower((string)($_POST['booking_context'] ?? '')) === 'kiosk';
        $newOrderRoute = $isKioskContext ? '/walkin-booking' : '/admin/orders/new';
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
        $deliveryType = strtolower(trim((string)($_POST['delivery_type'] ?? 'hotel')));
        if (!in_array($deliveryType, ['hotel', 'pickup'], true)) {
            $deliveryType = 'hotel';
        }
        $hotelId = isset($_POST['hotel_id']) && is_numeric($_POST['hotel_id']) ? (int)$_POST['hotel_id'] : null;
        $returnHotelId = isset($_POST['return_hotel_id']) && is_numeric($_POST['return_hotel_id']) ? (int)$_POST['return_hotel_id'] : null;
        $pickupLocationSelection = trim((string)($_POST['pickup_location'] ?? ''));

        $address1 = htmlspecialchars(trim($_POST['address1'] ?? ''));
        $address2 = htmlspecialchars(trim($_POST['address2'] ?? ''));
        $state = htmlspecialchars(trim($_POST['state'] ?? ''));
        $zip = htmlspecialchars(trim($_POST['zip'] ?? ''));

        if ($deliveryType === 'hotel') {
            if (!$hotelId || $hotelId <= 0) {
                $_SESSION['form_errors'] = ['Please select a partner hotel for delivery.'];
                header('Location: ' . $newOrderRoute);
                exit;
            }

            $pdo = \App\Utils\Database::getInstance();
            $hotelStmt = $pdo->prepare("SELECT id, name, address1, address2, state, zip FROM partner_hotels WHERE id = ? LIMIT 1");
            $hotelStmt->execute([$hotelId]);
            $hotel = $hotelStmt->fetch(\PDO::FETCH_ASSOC) ?: null;
            if (!$hotel) {
                $_SESSION['form_errors'] = ['Selected delivery hotel is no longer available.'];
                header('Location: ' . $newOrderRoute);
                exit;
            }

            $address1 = htmlspecialchars(trim((string)($hotel['address1'] ?? '')));
            $address2 = htmlspecialchars(trim((string)($hotel['address2'] ?? '')));
            $state = htmlspecialchars(trim((string)($hotel['state'] ?? '')));
            $zip = htmlspecialchars(trim((string)($hotel['zip'] ?? '')));
            $pickupLocationSelection = 'walk-in booking';

            if (!$returnHotelId || $returnHotelId <= 0) {
                $returnHotelId = $hotelId;
            }
        } else {
            if ($pickupLocationSelection === '') {
                $_SESSION['form_errors'] = ['Please select a pickup location.'];
                header('Location: ' . $newOrderRoute);
                exit;
            }
            $hotelId = null;
            $returnHotelId = null;
        }

        $orderData = [
            'user_id' => null,
            'guest_name' => htmlspecialchars($fullName),
            'guest_first_name' => $guestFirstName,
            'guest_last_name' => $guestLastName,
            'guest_email' => filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL),
            'guest_phone' => preg_replace('/\D/', '', $_POST['phone'] ?? ''),
            'client_weight_option' => htmlspecialchars(substr(trim((string)($_POST['client_weight_option'] ?? '')), 0, 32)),
            'client_weight_lbs' => null,
            'address1' => $address1,
            'address2' => $address2,
            'state' => $state,
            'zip' => $zip,
            'pickup_location' => htmlspecialchars($pickupLocationSelection),
            'notes' => htmlspecialchars(trim($_POST['notes'] ?? '')),
            'payment_method' => htmlspecialchars(trim($_POST['payment_method'] ?? '')),
            'delivery_type' => $deliveryType,
            'hotel_id' => $hotelId,
            'return_hotel_id' => $returnHotelId,
            'total_amount' => 0,
            'customer_type' => 'guest',
            'booking_source' => 'walk-in',
            'promo_code' => null,
            'promo_discount' => null,
            'promo_applied_by_admin_id' => null,
            'promo_applied_by_admin_role' => null,
            'promo_applied_by_admin_name' => null,
            'computed_total_amount' => null,
            'final_price_edited' => 0,
            'final_price_override_amount' => null,
            'final_price_edited_by_admin_id' => null,
            'final_price_edited_by_admin_role' => null,
            'final_price_edited_by_admin_name' => null,
            'final_price_edited_at' => null,
            'created_by_admin_id' => (int)($_SESSION['admin_id'] ?? 0) ?: null,
            'created_by_admin_role' => strtolower(trim((string)($_SESSION['admin_role'] ?? ''))),
            'created_by_admin_name' => trim((string)($_SESSION['admin_username'] ?? '')),
            'sale_type' => htmlspecialchars(trim($_POST['sale_type'] ?? '')),
            'pickup_datetime' => htmlspecialchars(trim($_POST['pickup_datetime'] ?? '')),
            'return_datetime' => htmlspecialchars(trim($_POST['return_datetime'] ?? '')),
        ];
        $cart = isset($_POST['cart']) ? json_decode($_POST['cart'], true) : [];

        if (!isset($_POST['agree_policy']) || $_POST['agree_policy'] !== '1') {
            $_SESSION['form_errors'] = ['You must agree to the rental policy and terms before creating a booking.'];
            header('Location: ' . $newOrderRoute);
            exit;
        }

        $weightValue = trim((string)($orderData['client_weight_option'] ?? ''));
        if ($weightValue === '' || !ctype_digit($weightValue) || (int)$weightValue <= 0) {
            $_SESSION['form_errors'] = ['Client weight in lbs is required and must be numeric.'];
            header('Location: ' . $newOrderRoute);
            exit;
        }

        $promoCodeInput = strtoupper(trim($_POST['promo_code'] ?? ''));
        $finalPriceOverrideInput = trim((string)($_POST['final_price_override'] ?? ''));
        $finalPriceOverrideAmount = null;
        $finalPriceEditedBySuperadmin = false;

        if ($finalPriceOverrideInput !== '') {
            if (!$this->isCurrentAdminSuperadmin()) {
                $_SESSION['form_errors'] = ['Only a Super Admin can edit the final walk-in price.'];
                header('Location: ' . $newOrderRoute);
                exit;
            }

            $normalizedOverrideInput = str_replace([',', '$', ' '], '', $finalPriceOverrideInput);
            if (!is_numeric($normalizedOverrideInput)) {
                $_SESSION['form_errors'] = ['Final price override must be a valid number.'];
                header('Location: ' . $newOrderRoute);
                exit;
            }

            $candidateOverride = round((float)$normalizedOverrideInput, 2);
            if ($candidateOverride <= 0) {
                $_SESSION['form_errors'] = ['Final price override must be greater than $0.00.'];
                header('Location: ' . $newOrderRoute);
                exit;
            }

            $finalPriceOverrideAmount = $candidateOverride;
            $finalPriceEditedBySuperadmin = true;
            // Manual final price editing acts as an explicit override and bypasses promo rules.
            $promoCodeInput = '';
        }

        if (!is_array($cart) || empty($cart)) {
            $_SESSION['form_errors'] = ['Please add at least one product to the booking.'];
            header('Location: ' . $newOrderRoute);
            exit;
        }

        $paymentMethodKey = strtolower(trim((string)($orderData['payment_method'] ?? '')));
        if (!in_array($paymentMethodKey, ['cash', 'card', 'paypal'], true)) {
            $_SESSION['form_errors'] = ['Please select a valid payment method.'];
            header('Location: ' . $newOrderRoute);
            exit;
        }

        require_once __DIR__ . '/../Models/OrderModel.php';
        $orderModel = new \App\Models\OrderModel();
        $cart = $orderModel->normalizeCartForTrustedPricing(
            $cart,
            $orderData['pickup_datetime'] ?? null,
            $orderData['return_datetime'] ?? null,
            $orderData['sale_type'] ?? 'rental'
        );

        if (in_array($paymentMethodKey, ['card', 'paypal'], true)) {
            $_SESSION['walkin_checkout_draft'] = [
                'prefill' => [
                    'first_name' => $orderData['guest_first_name'] ?? '',
                    'last_name' => $orderData['guest_last_name'] ?? '',
                    'email' => $orderData['guest_email'] ?? '',
                    'phone' => $orderData['guest_phone'] ?? '',
                    'client_weight_option' => $orderData['client_weight_option'] ?? '',
                    'pickup_datetime' => $orderData['pickup_datetime'] ?? '',
                    'return_datetime' => $orderData['return_datetime'] ?? '',
                    'delivery_type' => $orderData['delivery_type'] ?? 'hotel',
                    'hotel_id' => $orderData['hotel_id'] ?? '',
                    'return_hotel_id' => $orderData['return_hotel_id'] ?? '',
                    'pickup_location' => $orderData['pickup_location'] ?? '',
                    'address1' => $orderData['address1'] ?? '',
                    'address2' => $orderData['address2'] ?? '',
                    'state' => $orderData['state'] ?? '',
                    'zip' => $orderData['zip'] ?? '',
                    'notes' => $orderData['notes'] ?? '',
                    'sale_type' => $orderData['sale_type'] ?? 'rental',
                    'payment' => $paymentMethodKey,
                    'booking_source' => 'walk-in',
                    'created_by_admin_id' => (int)($_SESSION['admin_id'] ?? 0) ?: null,
                    'created_by_admin_role' => strtolower(trim((string)($_SESSION['admin_role'] ?? ''))),
                    'created_by_admin_name' => trim((string)($_SESSION['admin_username'] ?? '')),
                ],
                'cart' => $cart,
            ];

            header('Location: /walkin-booking/checkout');
            exit;
        }

        $cartSubtotal = 0.0;
        foreach ($cart as $item) {
            $qty = max(0, (int)($item['qty'] ?? $item['quantity'] ?? 0));
            $price = max(0, (float)($item['price'] ?? 0));
            $cartSubtotal += ($qty * $price);
        }

        $promoCodeModel = new PromoCodeModel();
        $discountAmount = 0.0;
        $appliedPromoCode = null;
        $appliedPromoId = null;
        if ($promoCodeInput !== '') {
            $validPromo = $promoCodeModel->getValidByCode($promoCodeInput);
            if ($validPromo === null) {
                $_SESSION['form_errors'] = ['Invalid, expired, or fully-used promo code.'];
                header('Location: ' . $newOrderRoute);
                exit;
            }
            if ($validPromo['type'] === 'percent') {
                $discountAmount = round($cartSubtotal * ((float)$validPromo['value'] / 100), 2);
            } else {
                $discountAmount = round((float)$validPromo['value'], 2);
            }
            $discountAmount = max(0, min($discountAmount, $cartSubtotal));
            $appliedPromoCode = $validPromo['code'];
            $appliedPromoId = (int)$validPromo['id'];
        }

        $deliveryFee = 0.0;
        if (($orderData['delivery_type'] ?? '') === 'hotel' && !empty($orderData['hotel_id'])) {
            $pdo = \App\Utils\Database::getInstance();
            $deliveryFeeStmt = $pdo->prepare("SELECT delivery_fee FROM partner_hotels WHERE id = ? LIMIT 1");
            $deliveryFeeStmt->execute([(int)$orderData['hotel_id']]);
            $deliveryHotelFee = max(0, round((float)$deliveryFeeStmt->fetchColumn(), 2));

            $returnHotelFee = 0.0;
            if (!empty($orderData['return_hotel_id'])) {
                $returnFeeStmt = $pdo->prepare("SELECT delivery_fee FROM partner_hotels WHERE id = ? LIMIT 1");
                $returnFeeStmt->execute([(int)$orderData['return_hotel_id']]);
                $returnHotelFee = max(0, round((float)$returnFeeStmt->fetchColumn(), 2));
            }

            $deliveryFee = max($deliveryHotelFee, $returnHotelFee);
        }

        $securityDeposit = 100.00;
        $productTotalWithTax = round(max(0, $cartSubtotal - $discountAmount), 2);
        $calculatedFinalTotalAmount = round($productTotalWithTax + $securityDeposit + $deliveryFee, 2);
        $finalTotalAmount = $calculatedFinalTotalAmount;
        if ($finalPriceEditedBySuperadmin && $finalPriceOverrideAmount !== null && $finalPriceOverrideAmount > $calculatedFinalTotalAmount) {
            $_SESSION['form_errors'] = [
                sprintf(
                    'Final price override cannot exceed the computed total amount of $%0.2f.',
                    $calculatedFinalTotalAmount
                ),
            ];
            header('Location: ' . $newOrderRoute);
            exit;
        }
        $pretaxSubtotal = round($productTotalWithTax / 1.08375, 2);
        $includedTaxAmount = round($productTotalWithTax - $pretaxSubtotal, 2);
        $orderData['computed_total_amount'] = $calculatedFinalTotalAmount;
        if ($finalPriceEditedBySuperadmin && $finalPriceOverrideAmount !== null) {
            $finalTotalAmount = $finalPriceOverrideAmount;
            $orderData['final_price_edited'] = 1;
            $orderData['final_price_override_amount'] = $finalPriceOverrideAmount;
            $orderData['final_price_edited_by_admin_id'] = (int)($_SESSION['admin_id'] ?? 0) ?: null;
            $orderData['final_price_edited_by_admin_role'] = $this->normalizeAdminRoleKey((string)($_SESSION['admin_role'] ?? ''));
            $orderData['final_price_edited_by_admin_name'] = trim((string)($_SESSION['admin_username'] ?? ''));
            $orderData['final_price_edited_at'] = date('Y-m-d H:i:s');
        }
        $orderData['total_amount'] = $finalTotalAmount;
        if ($appliedPromoCode !== null) {
            $promoNote = sprintf('Promo applied: %s (-$%0.2f)', $appliedPromoCode, $discountAmount);
            $orderData['notes'] = trim(($orderData['notes'] !== '' ? ($orderData['notes'] . "\n") : '') . $promoNote);
            $orderData['promo_code'] = $appliedPromoCode;
            $orderData['promo_discount'] = $discountAmount;
            $orderData['promo_applied_by_admin_id'] = (int)($_SESSION['admin_id'] ?? 0) ?: null;
            $orderData['promo_applied_by_admin_role'] = strtolower(trim((string)($_SESSION['admin_role'] ?? '')));
            $orderData['promo_applied_by_admin_name'] = trim((string)($_SESSION['admin_username'] ?? ''));
        }
        if ($finalPriceEditedBySuperadmin && $finalPriceOverrideAmount !== null) {
            $overrideNote = sprintf(
                'Final price manually edited by %s (Super Admin): from $%0.2f to $%0.2f',
                trim((string)($_SESSION['admin_username'] ?? 'Super Admin')),
                $calculatedFinalTotalAmount,
                $finalPriceOverrideAmount
            );
            $orderData['notes'] = trim(($orderData['notes'] !== '' ? ($orderData['notes'] . "\n") : '') . $overrideNote);
        }

        // Validate stock availability before placing order
        $stockValidation = $orderModel->validateStockAvailability(
            $cart,
            $orderData['pickup_datetime'] ?? null,
            $orderData['return_datetime'] ?? null
        );
        
        if (!$stockValidation['valid']) {
            $_SESSION['form_errors'] = $stockValidation['errors'];
            header('Location: ' . $newOrderRoute);
            exit;
        }
                $result = $orderModel->placeOrder($orderData, $cart);

        if (isset($result['errors']) && count($result['errors']) > 0) {
            $_SESSION['form_errors'] = $result['errors'];
            header('Location: ' . $newOrderRoute);
            exit;
        }

        $order_id = $result['order_id'] ?? null;
        if (!$order_id) {
            $_SESSION['form_errors'] = ['Booking was not completed. Please try again.'];
            header('Location: ' . $newOrderRoute);
            exit;
        }

        if ($appliedPromoId !== null && $order_id) {
            $promoCodeModel->incrementUse($appliedPromoId);
        }

        if ($finalPriceEditedBySuperadmin && $finalPriceOverrideAmount !== null && $order_id) {
            $this->logAdminAction('walkin_final_price_edited', 'order', $order_id, [
                'booking_source' => 'walk-in',
                'computed_total_amount' => round((float)$calculatedFinalTotalAmount, 2),
                'final_total_amount' => round((float)$finalPriceOverrideAmount, 2),
                'difference_amount' => round((float)$finalPriceOverrideAmount - (float)$calculatedFinalTotalAmount, 2),
                'payment_method' => (string)($orderData['payment_method'] ?? ''),
                'editor_admin_id' => isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null,
                'editor_admin_username' => (string)($_SESSION['admin_username'] ?? ''),
                'editor_admin_role' => $this->normalizeAdminRoleKey((string)($_SESSION['admin_role'] ?? '')),
            ]);
        }

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
        $orderSummary .= "Subtotal: $" . number_format($cartSubtotal, 2) . "\n";
        if ($appliedPromoCode !== null) {
            $orderSummary .= "Promo ({$appliedPromoCode}): -$" . number_format($discountAmount, 2) . "\n";
        }
        $orderSummary .= "Security Deposit: $" . number_format($securityDeposit, 2) . "\n";
        $orderSummary .= "Subtotal (Pre-Tax): $" . number_format($pretaxSubtotal, 2) . "\n";
        $orderSummary .= "Included NV Sales Tax: $" . number_format($includedTaxAmount, 2) . "\n";
        $orderSummary .= "Total: $" . number_format($total_amount, 2) . "\n";
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
        // Product prices are tax-inclusive, while security deposit is non-taxable.
        $totalAmountWithTax = (float)$finalTotalAmount;
        $totalAmount = round($pretaxSubtotal + $securityDeposit, 2);
        $tax = round($includedTaxAmount, 2);

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

        $pdfDir = dirname(__DIR__, 2) . '/storage/documents/contracts/';
        if (!is_dir($pdfDir)) mkdir($pdfDir, 0777, true);
        file_put_contents($pdfDir . "contract-{$order_id}.pdf", $dompdf->output());
        $pdfPath = $pdfDir . "contract-{$order_id}.pdf";

        // --- PRO-FORMA PDF GENERATION ---
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

        $orderDate = date('Y-m-d H:i:s');
        $subtotal = (float)$cartSubtotal;
        $discountAmount = (float)$discountAmount;
        $promoCode = (string)($orderData['promo_code'] ?? '');
        $paymentMethod = (string)($payment_method ?? '');
        $paymentCardLast4 = $orderModel->resolveOrderPaymentCardLast4([
            'payment_method' => $paymentMethod,
            'payment_provider' => strtolower($paymentMethod) === 'card' ? 'stripe' : (strtolower($paymentMethod) === 'paypal' ? 'paypal' : ''),
            'provider_payment_intent_id' => (string)($orderData['provider_payment_intent_id'] ?? ($_POST['provider_payment_intent_id'] ?? '')),
            'provider_charge_id' => (string)($orderData['provider_charge_id'] ?? ($_POST['provider_charge_id'] ?? '')),
        ]);
        $pickupLocation = (string)($pickup_location ?? '');
        $deliveryType = (string)($orderData['delivery_type'] ?? '');

        ob_start();
        include __DIR__ . '/../../Proformas/proforma-template.php';
        $invoiceHtml = ob_get_clean();

        $invoiceOptions = new \Dompdf\Options();
        $invoiceOptions->set('isRemoteEnabled', true);
        $invoiceOptions->set('isHtml5ParserEnabled', true);
        $invoiceDompdf = new Dompdf($invoiceOptions);
        $invoiceDompdf->loadHtml($invoiceHtml);
        $invoiceDompdf->setPaper('A4', 'portrait');
        $invoiceDompdf->render();

        $invoiceDir = dirname(__DIR__, 2) . '/storage/documents/proformas/';
        if (!is_dir($invoiceDir)) mkdir($invoiceDir, 0777, true);
        file_put_contents($invoiceDir . "proforma-{$order_id}.pdf", $invoiceDompdf->output());
        $invoicePath = $invoiceDir . "proforma-{$order_id}.pdf";

        // --- EMAIL SENDING ---
        require_once __DIR__ . '/../Utils/Mailer.php';
        $attachments = [
            [
                'path' => $pdfPath,
                'name' => "Rental-Contract-{$order_id}.pdf"
            ],
            [
                'path' => $invoicePath,
                'name' => "Proforma-Invoice-{$order_id}.pdf"
            ]
        ];
        $subject = 'Your Rental Booking Confirmation';
        $body = buildBookingEmailTemplate([
            'customer_name' => $customerName,
            'order_id' => $order_id,
            'amount_due' => $total_amount,
            'issued_at' => date('Y-m-d H:i:s'),
            'pickup_datetime' => $pickup_datetime,
            'return_datetime' => $return_datetime,
            'payment_method' => $payment_method,
            'note' => 'Your booking is confirmed. A pro-forma invoice is attached. Final invoice is issued after completion.',
        ]);
        $result = sendBookingConfirmation($customerEmail, $customerName, $subject, $body, $attachments);
        if (!$result) {
            error_log("Mailer Error: Booking confirmation email failed to send.");
        }

        $_SESSION['booking_success'] = "Booking successful! Order ID: $order_id";
        header('Location: ' . $newOrderRoute);
        exit;
    }

    public function processWalkinBooking() {
        $this->requireWalkinBookingAuth();
        $_POST['booking_context'] = 'kiosk';
        $this->processNewOrder();
    }

    public function rejectOrder() {
        $this->requireAdmin(['admin', 'superadmin']);
        $this->requirePostWithCsrf('/admin/orders');

        $orderId = isset($_POST['order_id']) ? intval($_POST['order_id']) : null;
        if ($orderId) {
            require_once __DIR__ . '/../Models/OrderModel.php';
            $orderModel = new \App\Models\OrderModel();
            $transitionCheck = $orderModel->getOrderStatusTransitionCheck($orderId, 'cancelled');
            if (!($transitionCheck['allowed'] ?? false)) {
                $_SESSION['order_cancel_message'] = (string)($transitionCheck['error'] ?? 'Order could not be rejected.');
            } else {
                $adminId = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null;
                if ($adminId !== null && $adminId <= 0) {
                    $adminId = null;
                }
                $message = $orderModel->cancelOrderProcess($orderId, $adminId);
                $this->logAdminAction('order_rejected', 'order', $orderId, [
                    'previous_status' => $transitionCheck['current_status'] ?? null,
                    'new_status' => 'cancelled',
                    'message' => $message,
                ]);
                $_SESSION['order_complete_message'] = $message;
            }
        }
        header('Location: /admin/orders');
        exit;
    }

    public function markAsPaid() {
        $this->requireAdmin(['admin', 'superadmin']);
        $this->requirePostWithCsrf('/admin/orders');

        $orderId = isset($_POST['order_id']) ? intval($_POST['order_id']) : null;
        if ($orderId) {
            require_once __DIR__ . '/../Models/OrderModel.php';
            $orderModel = new \App\Models\OrderModel();
            $transitionCheck = $orderModel->getOrderStatusTransitionCheck($orderId, 'paid');
            if (($transitionCheck['current_status'] ?? null) !== 'approved') {
                $transition = [
                    'success' => false,
                    'error' => 'Only approved orders can be manually marked as paid.',
                ];
            } else {
                $transition = $orderModel->transitionOrderStatus($orderId, 'paid');
            }
            if (!($transition['success'] ?? false)) {
                $_SESSION['order_cancel_message'] = (string)($transition['error'] ?? 'Order could not be marked as paid.');
            } else {
                $this->logAdminAction('order_marked_paid', 'order', $orderId, [
                    'previous_status' => $transition['current_status'] ?? null,
                    'new_status' => 'paid',
                ]);
                $_SESSION['order_complete_message'] = "Order {$orderId} marked as paid.";
            }
        }
        header('Location: /admin/orders');
        exit;
    }

    public function updateOrderDetails() {
        header('Content-Type: application/json; charset=utf-8');

        if (empty($_SESSION['admin_id'])) {
            http_response_code(401);
            echo json_encode([
                'error' => 'Session expired. Please log in again.',
                'auth_required' => true,
            ]);
            exit;
        }

        $this->requireAdminRoleJson(
            ['admin', 'superadmin'],
            'Only admins can edit order details.'
        );

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }

        if (!isset($_POST['csrf_token']) || !hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)$_POST['csrf_token'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token']);
            exit;
        }

        $orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
        if ($orderId <= 0) {
            http_response_code(422);
            echo json_encode(['error' => 'Invalid order ID']);
            exit;
        }

        $allowedFields = [
            'status' => 'enum',
            'payment_method' => 'enum',
            'booking_source' => 'enum',
            'guest_first_name' => 'string',
            'guest_last_name' => 'string',
            'guest_email' => 'email',
            'guest_phone' => 'phone',
            'pickup_datetime' => 'datetime',
            'return_datetime' => 'datetime',
            'hotel_id' => 'int',
            'return_hotel_id' => 'int',
            'pickup_location' => 'string',
            'notes' => 'string',
            'total_amount' => 'float',
            'promo_discount' => 'float',
            'security_deposit' => 'float',
        ];

        $updates = [];
        $params = [];

        foreach ($allowedFields as $field => $validator) {
            if (!array_key_exists($field, $_POST)) {
                continue;
            }

            $rawValue = trim((string)($_POST[$field] ?? ''));
            $normalized = null;

            switch ($validator) {
                case 'enum':
                    $allowedValues = [
                        'status' => ['pending', 'approved', 'paid', 'completed', 'cancelled'],
                        'payment_method' => ['card', 'paypal', 'cash', 'manual', 'bank_transfer'],
                        'booking_source' => ['online', 'walk-in', 'website', 'admin'],
                    ];
                    if ($rawValue === '') {
                        continue 2;
                    }
                    $value = strtolower(trim($rawValue));
                    if (!in_array($value, $allowedValues[$field], true)) {
                        http_response_code(422);
                        echo json_encode(['error' => 'Invalid value for ' . $field]);
                        exit;
                    }
                    $normalized = $value;
                    break;

                case 'string':
                    $normalized = $rawValue;
                    if (in_array($field, ['guest_first_name', 'guest_last_name', 'guest_email', 'guest_phone', 'pickup_location', 'notes'], true) && strlen($normalized) > 5000) {
                        http_response_code(422);
                        echo json_encode(['error' => ucfirst(str_replace('_', ' ', $field)) . ' is too long.']);
                        exit;
                    }
                    if ($field === 'notes' && strlen($normalized) > 2000) {
                        http_response_code(422);
                        echo json_encode(['error' => 'Order notes are too long.']);
                        exit;
                    }
                    break;

                case 'email':
                    if ($rawValue === '') {
                        $normalized = '';
                        break;
                    }
                    $normalized = filter_var($rawValue, FILTER_VALIDATE_EMAIL) ?: '';
                    if ($normalized === '') {
                        http_response_code(422);
                        echo json_encode(['error' => 'Guest email is invalid.']);
                        exit;
                    }
                    break;

                case 'phone':
                    $normalized = preg_replace('/\D+/', '', $rawValue);
                    break;

                case 'datetime':
                    if ($rawValue === '') {
                        $normalized = null;
                        break;
                    }
                    $normalized = str_replace('T', ' ', $rawValue);
                    if (strtotime($normalized) === false) {
                        http_response_code(422);
                        echo json_encode(['error' => 'Invalid date/time for ' . $field]);
                        exit;
                    }
                    $normalized = date('Y-m-d H:i:s', strtotime($normalized));
                    break;

                case 'int':
                    if ($rawValue === '') {
                        $normalized = null;
                        break;
                    }
                    $numeric = filter_var($rawValue, FILTER_VALIDATE_INT);
                    if ($numeric === false) {
                        http_response_code(422);
                        echo json_encode(['error' => 'Invalid integer value for ' . $field]);
                        exit;
                    }
                    $normalized = (int)$numeric;
                    break;

                case 'float':
                    if ($rawValue === '') {
                        $normalized = 0.0;
                        break;
                    }
                    if (!is_numeric($rawValue)) {
                        http_response_code(422);
                        echo json_encode(['error' => 'Invalid numeric value for ' . $field]);
                        exit;
                    }
                    $normalized = round((float)$rawValue, 2);
                    break;

                default:
                    $normalized = $rawValue;
                    break;
            }

            if ($normalized === null && in_array($field, ['hotel_id', 'return_hotel_id'], true)) {
                $normalized = null;
            }

            $updates[] = $field . ' = ?';
            $params[] = $normalized;
        }

        if (empty($updates)) {
            http_response_code(422);
            echo json_encode(['error' => 'No valid order fields were supplied.']);
            exit;
        }

        $pdo = \App\Utils\Database::getInstance();
        $sql = 'UPDATE orders SET ' . implode(', ', $updates) . ' WHERE order_id = ?';
        $params[] = $orderId;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $this->logAdminAction('order_details_updated', 'order', $orderId, [
            'updated_fields' => array_keys(array_flip(array_map(fn ($entry) => explode(' =', $entry, 2)[0], $updates))),
            'admin_username' => $_SESSION['admin_username'] ?? null,
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Order details updated successfully.',
        ]);
        exit;
    }

    public function updateSecurityDeposit() {
        header('Content-Type: application/json; charset=utf-8');

        if (empty($_SESSION['admin_id'])) {
            http_response_code(401);
            echo json_encode([
                'error' => 'Session expired. Please log in again.',
                'auth_required' => true,
            ]);
            exit;
        }

        $this->requireAdminRoleJson(
            ['superadmin'],
            'Only Super Admin can update security deposit amounts.'
        );

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }

        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token']);
            exit;
        }

        $orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
        $depositRaw = trim((string)($_POST['security_deposit'] ?? ''));
        $reason = trim((string)($_POST['security_deposit_reason'] ?? ''));

        if ($orderId <= 0) {
            http_response_code(422);
            echo json_encode(['error' => 'Invalid order ID']);
            exit;
        }

        if ($depositRaw === '' || !is_numeric($depositRaw)) {
            http_response_code(422);
            echo json_encode(['error' => 'Security deposit must be a valid number']);
            exit;
        }

        $securityDeposit = round((float)$depositRaw, 2);
        if ($securityDeposit < 0) {
            http_response_code(422);
            echo json_encode(['error' => 'Security deposit cannot be negative']);
            exit;
        }

        if ($securityDeposit > 10000) {
            http_response_code(422);
            echo json_encode(['error' => 'Security deposit is too high']);
            exit;
        }

        if ($reason === '' || strlen($reason) < 5) {
            http_response_code(422);
            echo json_encode(['error' => 'Please provide a brief reason (at least 5 characters).']);
            exit;
        }

        if (strlen($reason) > 1000) {
            http_response_code(422);
            echo json_encode(['error' => 'Reason is too long.']);
            exit;
        }

        $orderModel = new \App\Models\OrderModel();
        $result = $orderModel->updateOrderSecurityDeposit(
            $orderId,
            $securityDeposit,
            $reason,
            isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null
        );
        if (!$result) {
            http_response_code(404);
            echo json_encode(['error' => 'Order not found']);
            exit;
        }

        $this->logAdminAction('security_deposit_updated', 'order', $orderId, [
            'security_deposit' => $securityDeposit,
            'reason' => $reason,
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Security deposit updated.',
            'order' => $result,
        ]);
        exit;
    }

    public function refundSecurityDeposit() {
        header('Content-Type: application/json; charset=utf-8');

        if (empty($_SESSION['admin_id'])) {
            http_response_code(401);
            echo json_encode([
                'error' => 'Session expired. Please log in again.',
                'auth_required' => true,
            ]);
            exit;
        }

        $this->requireAdminRoleJson(
            ['superadmin'],
            'Only Super Admin can refund security deposits.'
        );

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }

        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token']);
            exit;
        }

        $orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
        $amountRaw = trim((string)($_POST['refund_amount'] ?? ''));
        $reason = trim((string)($_POST['refund_reason'] ?? ''));
        $refundMethod = strtolower(trim((string)($_POST['refund_method'] ?? '')));

        if ($orderId <= 0) {
            http_response_code(422);
            echo json_encode(['error' => 'Invalid order ID']);
            exit;
        }

        if ($amountRaw === '' || !is_numeric($amountRaw)) {
            http_response_code(422);
            echo json_encode(['error' => 'Refund amount must be a valid number']);
            exit;
        }

        $refundAmount = round((float)$amountRaw, 2);
        if ($refundAmount <= 0) {
            http_response_code(422);
            echo json_encode(['error' => 'Refund amount must be greater than zero']);
            exit;
        }

        if ($refundAmount > 10000) {
            http_response_code(422);
            echo json_encode(['error' => 'Refund amount is too high']);
            exit;
        }

        if (strlen($reason) > 1000) {
            http_response_code(422);
            echo json_encode(['error' => 'Reason is too long.']);
            exit;
        }

        if ($refundMethod !== '' && !in_array($refundMethod, ['cash', 'card-terminal', 'manual', 'provider'], true)) {
            http_response_code(422);
            echo json_encode(['error' => 'Invalid refund method.']);
            exit;
        }

        $orderModel = new \App\Models\OrderModel();
        $result = $orderModel->refundSecurityDeposit(
            $orderId,
            $refundAmount,
            $reason,
            isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null,
            $refundMethod !== '' ? $refundMethod : null
        );

        if (!($result['success'] ?? false)) {
            http_response_code(422);
            echo json_encode([
                'error' => (string)($result['error'] ?? 'Refund failed'),
                'success' => false,
            ]);
            exit;
        }

        $this->logAdminAction('security_deposit_refunded', 'order', $orderId, [
            'refund_amount' => $refundAmount,
            'refund_method' => $refundMethod !== '' ? $refundMethod : null,
            'reason' => $reason,
            'payment_provider' => $result['refund']['payment_provider'] ?? null,
            'provider_refund_id' => $result['refund']['provider_refund_id'] ?? null,
            'status' => $result['refund']['status'] ?? null,
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Security deposit refund processed.',
            'refund' => $result,
        ]);
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
            $allowed = is_array($roles) ? $roles : [$roles];
            $this->requireAdminRoleRedirect($allowed, '/admin/orders');
        }
    }

    private function normalizeAdminRoleKey(string $role): string
    {
        $role = strtolower(trim($role));
        return str_replace(['_', '-', ' '], '', $role);
    }

    private function isCurrentAdminSuperadmin(): bool
    {
        return $this->normalizeAdminRoleKey((string)($_SESSION['admin_role'] ?? '')) === 'superadmin';
    }

    private function getLoginIpAddress(): string
    {
        $candidates = [
            $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
            $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (!$candidate) {
                continue;
            }
            $ip = trim(explode(',', (string)$candidate)[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return '0.0.0.0';
    }

    private function formatLoginLockoutMessage(int $remainingSeconds): string
    {
        $minutes = max(1, (int)ceil($remainingSeconds / 60));
        return 'Too many failed attempts. Please try again in ' . $minutes . ' minute' . ($minutes === 1 ? '' : 's') . '.';
    }

    private function streamCsv(string $filename, array $headers, array $rows): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        $output = fopen('php://output', 'w');
        fputcsv($output, $headers);
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }

    private function buildOrdersFilterFromRequest(): array
    {
        $searchTerm = isset($_GET['order_id_search']) ? trim((string)$_GET['order_id_search']) : '';
        $statusFilter = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
        $customerTypeFilter = isset($_GET['customer_type']) ? trim((string)$_GET['customer_type']) : '';
        $saleTypeFilter = isset($_GET['sale_type']) ? trim((string)$_GET['sale_type']) : '';
        $bookingSourceFilter = isset($_GET['booking_source']) ? trim((string)$_GET['booking_source']) : '';
        $heardAboutFilter = isset($_GET['heard_about']) ? trim((string)$_GET['heard_about']) : '';
        $promoUsageFilter = isset($_GET['promo_usage']) ? trim((string)$_GET['promo_usage']) : '';
        $creatorRoleFilter = isset($_GET['creator_role']) ? trim((string)$_GET['creator_role']) : '';
        $quickPeriodFilter = isset($_GET['quick_period']) ? strtolower(trim((string)$_GET['quick_period'])) : '';
        $dateFromFilter = isset($_GET['date_from']) ? trim((string)$_GET['date_from']) : '';
        $dateToFilter = isset($_GET['date_to']) ? trim((string)$_GET['date_to']) : '';
        $sortBy = isset($_GET['sort_by']) ? trim((string)$_GET['sort_by']) : 'order_id';
        $sortDir = isset($_GET['sort_dir']) ? trim((string)$_GET['sort_dir']) : 'desc';

        if (!in_array(strtolower($statusFilter), ['pending', 'approved', 'paid', 'completed', 'cancelled'], true)) {
            $statusFilter = '';
        }
        if (!in_array(strtolower($customerTypeFilter), ['user', 'guest'], true)) {
            $customerTypeFilter = '';
        }
        if (!in_array(strtolower($saleTypeFilter), ['rental', 'sale'], true)) {
            $saleTypeFilter = '';
        }
        if (!in_array(strtolower($bookingSourceFilter), ['walk-in', 'online'], true)) {
            $bookingSourceFilter = '';
        }
        if (!in_array(strtolower($promoUsageFilter), ['with', 'without'], true)) {
            $promoUsageFilter = '';
        }
        if (!in_array(strtolower($creatorRoleFilter), ['superadmin', 'admin', 'staff', 'partner'], true)) {
            $creatorRoleFilter = '';
        }
        if (!in_array($quickPeriodFilter, ['late', 'today', 'upcoming'], true)) {
            $quickPeriodFilter = '';
        }
        if ($dateFromFilter !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFromFilter)) {
            $dateFromFilter = '';
        }
        if ($dateToFilter !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateToFilter)) {
            $dateToFilter = '';
        }
        if (!in_array($sortBy, ['order_id', 'sale_type', 'total_amount', 'sales_amount', 'refund_amount', 'status', 'order_date', 'pickup_datetime', 'return_datetime'], true)) {
            $sortBy = 'order_id';
        }
        $sortDir = strtolower($sortDir) === 'asc' ? 'asc' : 'desc';

        return [
            'order_id_search' => $searchTerm,
            'status' => strtolower($statusFilter),
            'customer_type' => strtolower($customerTypeFilter),
            'sale_type' => strtolower($saleTypeFilter),
            'booking_source' => strtolower($bookingSourceFilter),
            'heard_about' => $heardAboutFilter,
            'promo_usage' => strtolower($promoUsageFilter),
            'creator_role' => strtolower($creatorRoleFilter),
            'quick_period' => $quickPeriodFilter,
            'date_from' => $dateFromFilter,
            'date_to' => $dateToFilter,
            'sort_by' => $sortBy,
            'sort_dir' => $sortDir,
        ];
    }

    private function requirePostWithCsrf(string $redirectPath): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . $redirectPath);
            exit;
        }

        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            die('Invalid CSRF token');
        }
    }

    private function requireWalkinBookingAuth(): void {
        if (empty($_SESSION['admin_id'])) {
            header('Location: /walkin-booking/login');
            exit;
        }
    }

    public function featuredProducts() {
        $this->requireAdmin(['admin', 'superadmin']);

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
            $this->requirePostWithCsrf('/admin/featured-products');
            $productIds = $_POST['product_id'] ?? [];
            $variationIds = $_POST['variation_id'] ?? [];
            $selectedSlots = [];
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
                    $selectedSlots[] = [
                        'slot' => $slot,
                        'product_id' => $productId,
                        'variation_id' => $variationId,
                    ];
                }
            }
            $this->logAdminAction('featured_products_updated', 'featured_product', null, [
                'slots' => $selectedSlots,
            ]);
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

    // ─── PROMO CODES ─────────────────────────────────────────────────────────

    public function promoCodes() {
        $this->requireAdmin(['admin', 'superadmin']);
        $promoModel = new PromoCodeModel();
        $codes = $promoModel->getAll();
        $this->renderAdmin('admin/promo-codes', ['promoCodes' => $codes]);
    }

    public function auditLogs() {
        $this->requireAdmin(['superadmin']);

        $activeTab = ($_GET['tab'] ?? 'audit') === 'login-attempts' ? 'login-attempts' : 'audit';
        $filters = [
            'action' => trim((string)($_GET['action'] ?? '')),
            'admin_id' => isset($_GET['admin_id']) ? (int)$_GET['admin_id'] : 0,
            'target_type' => trim((string)($_GET['target_type'] ?? '')),
            'date_from' => trim((string)($_GET['date_from'] ?? '')),
            'date_to' => trim((string)($_GET['date_to'] ?? '')),
            'search' => trim((string)($_GET['search'] ?? '')),
        ];
        $loginAttemptFilters = [
            'login_area' => trim((string)($_GET['login_area'] ?? '')),
            'username' => trim((string)($_GET['login_username'] ?? '')),
            'result' => trim((string)($_GET['login_result'] ?? '')),
            'date_from' => trim((string)($_GET['login_date_from'] ?? '')),
            'date_to' => trim((string)($_GET['login_date_to'] ?? '')),
            'search' => trim((string)($_GET['login_search'] ?? '')),
        ];

        $page = max(1, (int)($_GET['audit_page'] ?? 1));
        $loginAttemptPage = max(1, (int)($_GET['login_page'] ?? 1));
        $perPage = 15;
        $auditModel = new AdminAuditLogModel();
        $loginAttemptModel = new AdminLoginAttemptModel();
        $totalLogs = $auditModel->countLogs($filters);
        $totalPages = max(1, (int)ceil($totalLogs / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $totalLoginAttempts = $loginAttemptModel->countAttempts($loginAttemptFilters);
        $totalLoginAttemptPages = max(1, (int)ceil($totalLoginAttempts / $perPage));
        if ($loginAttemptPage > $totalLoginAttemptPages) {
            $loginAttemptPage = $totalLoginAttemptPages;
        }

        $this->renderAdmin('admin/audit-logs', [
            'activeTab' => $activeTab,
            'logs' => $auditModel->getLogs($filters, $page, $perPage),
            'actions' => $auditModel->getActions(),
            'adminOptions' => $auditModel->getAdminOptions(),
            'filters' => $filters,
            'page' => $page,
            'perPage' => $perPage,
            'totalLogs' => $totalLogs,
            'totalPages' => $totalPages,
            'loginAttempts' => $loginAttemptModel->getAttempts($loginAttemptFilters, $loginAttemptPage, $perPage),
            'loginAttemptFilters' => $loginAttemptFilters,
            'loginAreas' => $loginAttemptModel->getLoginAreas(),
            'loginAttemptUsernames' => $loginAttemptModel->getAttemptUsernames(),
            'loginAttemptPage' => $loginAttemptPage,
            'totalLoginAttempts' => $totalLoginAttempts,
            'totalLoginAttemptPages' => $totalLoginAttemptPages,
        ]);
    }

    public function exportSecurityLogsCsv() {
        $this->requireAdmin(['superadmin']);

        $tab = ($_GET['tab'] ?? 'audit') === 'login-attempts' ? 'login-attempts' : 'audit';
        if ($tab === 'login-attempts') {
            $filters = [
                'login_area' => trim((string)($_GET['login_area'] ?? '')),
                'username' => trim((string)($_GET['login_username'] ?? '')),
                'result' => trim((string)($_GET['login_result'] ?? '')),
                'date_from' => trim((string)($_GET['login_date_from'] ?? '')),
                'date_to' => trim((string)($_GET['login_date_to'] ?? '')),
                'search' => trim((string)($_GET['login_search'] ?? '')),
            ];
            $rows = (new AdminLoginAttemptModel())->getAttempts($filters, 1, 5000);
            $this->logAdminAction('security_logs_exported', 'admin_login_attempt', null, [
                'tab' => 'login-attempts',
                'filters' => $filters,
                'row_count' => count($rows),
            ]);
            $this->streamCsv('login-attempts-' . date('Y-m-d') . '.csv', [
                'ID', 'Attempted At', 'Login Area', 'Username', 'IP Address', 'Result'
            ], array_map(static function ($row) {
                return [
                    $row['id'] ?? '',
                    $row['attempted_at'] ?? '',
                    $row['login_area'] ?? '',
                    $row['username'] ?? '',
                    $row['ip_address'] ?? '',
                    !empty($row['successful']) ? 'Success' : 'Failed',
                ];
            }, $rows));
        }

        $filters = [
            'action' => trim((string)($_GET['action'] ?? '')),
            'admin_id' => isset($_GET['admin_id']) ? (int)$_GET['admin_id'] : 0,
            'target_type' => trim((string)($_GET['target_type'] ?? '')),
            'date_from' => trim((string)($_GET['date_from'] ?? '')),
            'date_to' => trim((string)($_GET['date_to'] ?? '')),
            'search' => trim((string)($_GET['search'] ?? '')),
        ];
        $rows = (new AdminAuditLogModel())->getLogs($filters, 1, 5000);
        $this->logAdminAction('security_logs_exported', 'admin_audit_log', null, [
            'tab' => 'audit',
            'filters' => $filters,
            'row_count' => count($rows),
        ]);
        $this->streamCsv('audit-logs-' . date('Y-m-d') . '.csv', [
            'ID', 'Created At', 'Admin ID', 'Admin Username', 'Admin Role', 'Action', 'Target Type', 'Target ID', 'Details JSON', 'IP Address', 'User Agent'
        ], array_map(static function ($row) {
            return [
                $row['id'] ?? '',
                $row['created_at'] ?? '',
                $row['admin_id'] ?? '',
                $row['admin_username'] ?? '',
                $row['admin_role'] ?? '',
                $row['action'] ?? '',
                $row['target_type'] ?? '',
                $row['target_id'] ?? '',
                $row['details_json'] ?? '',
                $row['ip_address'] ?? '',
                $row['user_agent'] ?? '',
            ];
        }, $rows));
    }

    public function exportOrdersCsv() {
        $this->requireAdmin(['admin', 'superadmin']);

        $filters = $this->buildOrdersFilterFromRequest();
        $rows = (new OrderModel())->getOrdersExportRows($filters, 50000);
        $this->logAdminAction('orders_exported', 'order', null, [
            'filters' => $filters,
            'row_count' => count($rows),
        ]);

        $this->streamCsv('orders-export-' . date('Y-m-d') . '.csv', [
            'Order ID',
            'Order Date',
            'Status',
            'Customer Type',
            'Sale Type',
            'Booking Source',
            'Customer Name',
            'Customer Email',
            'Customer Phone',
            'Pickup Datetime',
            'Return Datetime',
            'Delivery Type',
            'Delivery Hotel',
            'Delivery Hotel Address',
            'Pickup Hotel',
            'Pickup Hotel Address',
            'Pickup / Return Location',
            'Pickup Location',
            'Pickup Location Address',
            'Total Amount',
            'Computed Total Amount',
            'Final Price Edited',
            'Final Price Edited By',
            'Final Price Edited By Role',
            'Final Price Edited At',
            'Security Deposit',
            'Delivery Fee',
            'Promo Code',
            'Promo Discount',
            'Heard About',
            'Booked By',
            'Booked By Role',
            'Order Item ID',
            'Product ID',
            'Product Name',
            'Variation',
            'Quantity',
            'Item Price',
            'Scooter ID',
        ], array_map(static function ($row) {
            $pickupLocation = $row['pickup_location_name'] ?? '';
            if ($pickupLocation === '') {
                $pickupLocation = $row['pickup_location'] ?? '';
            }
            $pickupReturnLocation = $row['pickup_hotel_name'] ?? '';
            if ($pickupReturnLocation === '') {
                $pickupReturnLocation = $pickupLocation;
            }

            $roleLabels = [
                'superadmin' => 'Super Admin',
                'admin' => 'Admin',
                'staff' => 'Staff',
                'partner' => 'Partner',
            ];
            $bookedByRoleKey = strtolower(str_replace(['_', '-', ' '], '', (string)($row['created_by_admin_role'] ?? '')));
            $bookedByRoleLabel = $bookedByRoleKey !== ''
                ? ($roleLabels[$bookedByRoleKey] ?? ucfirst($bookedByRoleKey))
                : '';
            $finalPriceEditedRoleKey = strtolower(str_replace(['_', '-', ' '], '', (string)($row['final_price_edited_by_admin_role'] ?? '')));
            $finalPriceEditedRoleLabel = $finalPriceEditedRoleKey !== ''
                ? ($roleLabels[$finalPriceEditedRoleKey] ?? ucfirst($finalPriceEditedRoleKey))
                : '';

            return [
                $row['order_id'] ?? '',
                $row['order_date'] ?? '',
                $row['status'] ?? '',
                $row['customer_type'] ?? '',
                $row['sale_type'] ?? '',
                $row['booking_source_display'] ?? $row['booking_source'] ?? '',
                $row['customer_name'] ?? '',
                $row['guest_email'] ?? '',
                $row['guest_phone'] ?? '',
                $row['pickup_datetime'] ?? '',
                $row['return_datetime'] ?? '',
                $row['delivery_type'] ?? '',
                $row['delivery_hotel_name'] ?? '',
                $row['delivery_hotel_address'] ?? '',
                $row['pickup_hotel_name'] ?? '',
                $row['pickup_hotel_address'] ?? '',
                $pickupReturnLocation,
                $pickupLocation,
                $row['pickup_location_address'] ?? '',
                $row['total_amount'] ?? '',
                $row['computed_total_amount'] ?? '',
                ((int)($row['final_price_edited'] ?? 0) === 1) ? 'Yes' : 'No',
                $row['final_price_edited_by_admin_name'] ?? '',
                $finalPriceEditedRoleLabel,
                $row['final_price_edited_at'] ?? '',
                $row['security_deposit'] ?? '',
                $row['delivery_fee'] ?? '',
                $row['promo_code'] ?? '',
                $row['promo_discount'] ?? '',
                $row['heard_about_display'] ?? '',
                $row['created_by_admin_name'] ?? '',
                $bookedByRoleLabel,
                $row['order_item_id'] ?? '',
                $row['product_id'] ?? '',
                $row['product_name'] ?? '',
                $row['variation_name'] ?? '',
                $row['quantity'] ?? '',
                $row['item_price'] ?? '',
                $row['scooter_id'] ?? '',
            ];
        }, $rows));
    }

    public function savePromoCode() {
        $this->requireAdmin(['admin', 'superadmin']);
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            die('Invalid CSRF token');
        }
        $promoModel = new PromoCodeModel();
        $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
        $existingPromo = $id ? $promoModel->getById($id) : null;
        $ok = $promoModel->save($_POST, $id);
        if (!$ok) {
            $_SESSION['promo_error'] = 'Failed to save promo code. The code may already exist.';
        } else {
            $savedPromo = $id ? $promoModel->getById($id) : $promoModel->getByCode((string)($_POST['code'] ?? ''));
            $this->logAdminAction($id ? 'promo_code_updated' : 'promo_code_created', 'promo_code', $savedPromo['id'] ?? $id, [
                'before' => $existingPromo,
                'after' => $savedPromo,
            ]);
            $_SESSION['promo_success'] = $id ? 'Promo code updated.' : 'Promo code created.';
        }
        header('Location: /admin/promo-codes');
        exit;
    }

    public function deletePromoCode() {
        $this->requireAdmin(['admin', 'superadmin']);
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            die('Invalid CSRF token');
        }
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $promoModel = new PromoCodeModel();
            $existingPromo = $promoModel->getById($id);
            $promoModel->delete($id);
            $this->logAdminAction('promo_code_deleted', 'promo_code', $id, [
                'deleted' => $existingPromo,
            ]);
            $_SESSION['promo_success'] = 'Promo code deleted.';
        }
        header('Location: /admin/promo-codes');
        exit;
    }

    public function heardAboutOptions() {
        $this->requireAdmin(['admin', 'superadmin']);
        $options = (new HeardAboutOptionModel())->getAll();
        $this->renderAdmin('admin/heard-about-options', ['options' => $options]);
    }

    public function saveHeardAboutOption() {
        $this->requireAdmin(['admin', 'superadmin']);
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            die('Invalid CSRF token');
        }

        $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
        $ok = (new HeardAboutOptionModel())->save($_POST, $id);
        if (!$ok) {
            $_SESSION['heard_about_error'] = 'Failed to save option. Label may already exist.';
        } else {
            $_SESSION['heard_about_success'] = $id ? 'Option updated.' : 'Option created.';
        }

        header('Location: /admin/heard-about-options');
        exit;
    }

    public function deleteHeardAboutOption() {
        $this->requireAdmin(['admin', 'superadmin']);
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            die('Invalid CSRF token');
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            (new HeardAboutOptionModel())->delete($id);
            $_SESSION['heard_about_success'] = 'Option deleted.';
        }

        header('Location: /admin/heard-about-options');
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────

    public function admins() {
        $this->requireAdmin('superadmin');

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
            $role = strtolower(trim((string)($_POST['role'] ?? '')));
            $allowedRoles = ['superadmin', 'admin', 'staff', 'partner'];
            if (!in_array($role, $allowedRoles, true)) {
                $_SESSION['flash_message'] = 'Invalid admin role selected.';
                $_SESSION['flash_type'] = 'error';
                header('Location: /admin/admins/add');
                exit;
            }
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $adminModel->addAdmin($username, $password, $role);
            $newAdminId = (int)$pdo->lastInsertId();
            $this->logAdminAction('admin_user_created', 'admin_user', $newAdminId ?: null, [
                'username' => $username,
                'role' => $role,
            ]);
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
            $role = strtolower(trim((string)($_POST['role'] ?? '')));
            $allowedRoles = ['superadmin', 'admin', 'staff', 'partner'];
            if (!in_array($role, $allowedRoles, true)) {
                $_SESSION['flash_message'] = 'Invalid admin role selected.';
                $_SESSION['flash_type'] = 'error';
                header('Location: /admin/admins/edit?id=' . urlencode((string)$id));
                exit;
            }
            $existingAdmin = $adminModel->getAdminById($id);
            $adminModel->updateAdmin($id, $username, $role);
            $updatedAdmin = $adminModel->getAdminById($id);
            $this->logAdminAction('admin_user_updated', 'admin_user', $id, [
                'before' => $existingAdmin,
                'after' => $updatedAdmin,
            ]);
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
            $existingAdmin = $adminModel->getAdminById($id);
            $adminModel->deleteAdmin($id);
            $this->logAdminAction('admin_user_deleted', 'admin_user', $id, [
                'deleted' => $existingAdmin,
            ]);
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
        $orderId = isset($_GET['order_id']) && is_numeric($_GET['order_id']) && (int)$_GET['order_id'] > 0
            ? (int)$_GET['order_id']
            : null;
        $reservationModel = new ReservationModel();
        $result = $reservationModel->getReservations($status, $page, $perPage, $search, $orderId);
        $orderIds = $reservationModel->getReservationOrderIds($status);

        $assignableScooters = [];
        foreach (($result['reservations'] ?? []) as $reservationRow) {
            $rid = (int)($reservationRow['reservation_id'] ?? 0);
            if ($rid > 0) {
                $assignableScooters[$rid] = $reservationModel->getAssignableScootersForReservation($rid);
            }
        }

        $this->renderAdmin('admin/reservations', [
            'reservations' => $result['reservations'],
            'totalReservations' => $result['totalReservations'],
            'totalPages' => $result['totalPages'],
            'page' => $page,
            'perPage' => $perPage,
            'status' => $status,
            'search' => $search,
            'orderId' => $orderId,
            'orderIds' => $orderIds,
            'assignableScooters' => $assignableScooters,
        ]);
    }

    public function updateReservation()
    {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /admin/reservations');
            exit;
        }

        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            die('Invalid CSRF token');
        }

        $reservationId = isset($_POST['reservation_id']) ? (int)$_POST['reservation_id'] : 0;
        $scooterId = isset($_POST['scooter_id']) ? (int)$_POST['scooter_id'] : 0;
        $notes = trim((string)($_POST['notes'] ?? ''));
        if (strlen($notes) > 1000) {
            $notes = substr($notes, 0, 1000);
        }

        $reservationModel = new ReservationModel();
        $updateResult = $reservationModel->updateReservationAssignment($reservationId, $scooterId, $notes);
        if (!empty($updateResult['success'])) {
            $_SESSION['reservation_success'] = $updateResult['message'] ?? 'Reservation updated.';
        } else {
            $_SESSION['reservation_error'] = $updateResult['message'] ?? 'Unable to update reservation.';
        }

        $redirectQuery = trim((string)($_POST['redirect_query'] ?? ''));
        if ($redirectQuery !== '') {
            header('Location: /admin/reservations?' . ltrim($redirectQuery, '?'));
            exit;
        }

        header('Location: /admin/reservations');
        exit;
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
            $this->requirePostWithCsrf('/admin/testimonials/add');
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
            $this->requirePostWithCsrf('/admin/testimonials/edit?id=' . urlencode((string)$id));
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
        $this->requirePostWithCsrf('/admin/testimonials');
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
        $this->requirePostWithCsrf('/admin/tips-troubleshooting');
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
        $this->requirePostWithCsrf('/admin/tips-troubleshooting');
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
        $this->requirePostWithCsrf('/admin/tips-troubleshooting');
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
        $this->requirePostWithCsrf('/admin/tips-troubleshooting');
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
        $this->requirePostWithCsrf('/admin/tips-troubleshooting');
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
        return (new AdminImageUploadService())->store($imageFile, 'tips-article', 'Article image upload failed.');
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
