<?php

namespace App\Controllers;

use App\Controller;
use App\Models\Journal;
use App\Models\ProductModel;
use App\Models\CartModel;
use App\Models\OrderModel;
use App\Models\TestimonialsModel;
use App\Models\TipsTroubleshootingModel;

class HomeController extends Controller
{

    private $productModel;

    //Loads the initial variables of the Controller
    public function __construct()
    {
        // Load the model
        $this->productModel = new ProductModel();
    }


    public function index()
    {
        // Instant Mobility: get reference pickup/return datetime
        $pickup = '';
        $return = '';
        // Use current date/time rounded to nearest 15 min for pickup
        $now = new \DateTime();
        $minutes = (int)$now->format('i');
        $nearest = $minutes - ($minutes % 15);
        $now->setTime((int)$now->format('H'), $nearest, 0);
        $pickup = $now->format('Y-m-d H:i:s');
        // Return = pickup + 1 day (default Instant Mobility duration)
        $returnDate = clone $now;
        $returnDate->modify('+1 day');
        $return = $returnDate->format('Y-m-d H:i:s');

        // Get only available featured products for reference period
        $featuredProducts = $this->productModel->getFeaturedProducts($pickup, $return);
        $testimonialsModel = new \App\Models\TestimonialsModel();
        $testimonials = $testimonialsModel->getAllTestimonials();
        $tipsModel = new TipsTroubleshootingModel();
        $tipsSection = $tipsModel->getSection();
        $tipsArticles = $tipsModel->getArticles();
        // Collect all product and variation IDs for rental price lookup
        $productIds = [];
        foreach ($featuredProducts as $item) {
            if (!empty($item['product_id'])) $productIds[] = $item['product_id'];
        }
        $rentalPrices = $this->productModel->getRentalPricesForProducts($productIds);
        $this->render('index', [
            'featuredProducts' => $featuredProducts,
            'rentalPrices' => $rentalPrices,
            'testimonials' => $testimonials,
            'tipsSection' => $tipsSection,
            'tipsArticles' => $tipsArticles
        ]);
    }

    public function productList()
    {

        $filters = [
            'category' => $_GET['category'] ?? null,
            'page' => $_GET['page'] ?? 1
        ];

        $productData = $this->productModel->getProductList($filters);

        $this->render('product-list', [
            'categories' => $productData['categories'],
            'products' => $productData['products'],
            'total_pages' => $productData['total_pages'],
            'current_page' => $productData['current_page'],
            'selected_category' => $productData['selected_category']
        ]);
    }

    public function search()
    {   
        // GET PICKUP AND RETURN DATES FOR FILTERING
        $pickup = $_GET['pickup_datetime'] ?? '';
        $return = $_GET['return_datetime'] ?? '';

        // Normalize to 'Y-m-d H:i:s'
        if ($pickup) $pickup = date('Y-m-d H:i:s', strtotime($pickup));
        if ($return) $return = date('Y-m-d H:i:s', strtotime($return));

        $filters = [
            'query' => $_GET['q'] ?? '',
            'category' => $_GET['category'] ?? '',
            'price_order' => $_GET['price_order'] ?? '',
            'weight' => $_GET['weight'] ?? '',
            'available_only' => !isset($_GET['available_only']) || $_GET['available_only'] == '1',
            'page' => $_GET['page'] ?? 1,
            'pickup' => $pickup,
            'return' => $return
        ];
        $productData = $this->productModel->getSearch($filters);

        // Collect all product IDs for rental price lookup
        $productIds = [];
        foreach ($productData['products'] as $item) {
            if (!empty($item['product_id'])) $productIds[] = $item['product_id'];
        }
        $rentalPrices = $this->productModel->getRentalPricesForProducts($productIds);

        $this->render('search-results', [
            'categories' => $productData['categories'],
            'products' => $productData['products'],
            'total_pages' => $productData['total_pages'],
            'current_page' => $productData['current_page'],
            'selected_category' => $filters['category'],
            'price_order' => $filters['price_order'],
            'total_products' => $productData['total_products'],
            'rentalPrices' => $rentalPrices
            // ...other filter values as needed
        ]);
    }

    // public function searchResults()
    // {
    //     $pickup = $_GET['pickupDatetime'] ?? '';
    //     $return = $_GET['returnDatetime'] ?? '';

    //     $db = new \PDO('mysql:host=localhost;dbname=getaround_db', 'getaroundmobility', 'itup420');

    //     // Pagination logic
    //     $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    //     $perPage = 9;
    //     $offset = ($page - 1) * $perPage;

    //     // Count total products (only those with available scooters)
    //     $countStmt = $db->query(
    //         "SELECT COUNT(*) FROM products p
    //          JOIN categories c ON p.product_category_id = c.category_id
    //          WHERE p.is_available = 1
    //          AND EXISTS (SELECT 1 FROM scooters s WHERE s.product_id = p.product_id AND s.status = 'available')"
    //     );
    //     $total_products = $countStmt->fetchColumn();
    //     $total_pages = ceil($total_products / $perPage);
    //     $current_page = $page;

    //     // Fetch products for current page (only those with available scooters)
    //     $stmt = $db->prepare(
    //         "SELECT 
    //             p.product_id, p.product_name, p.price, p.description, p.image_url, 
    //             c.category_name,
    //             (SELECT COUNT(*) FROM scooters s WHERE s.product_id = p.product_id AND s.status = 'available') AS scooter_count
    //          FROM products p
    //          JOIN categories c ON p.product_category_id = c.category_id
    //          WHERE p.is_available = 1
    //          AND EXISTS (SELECT 1 FROM scooters s WHERE s.product_id = p.product_id AND s.status = 'available')
    //          LIMIT :perPage OFFSET :offset"
    //     );
    //     $stmt->bindValue(':perPage', $perPage, \PDO::PARAM_INT);
    //     $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
    //     $stmt->execute();
    //     $products = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    //     require __DIR__ . '/../Views/search-results.php';
    // }

    public function cart()
    {
        $cartModel = new CartModel();
        $cart = $cartModel->getCart();
        $total = $cartModel->getTotal();

        $this->render('cart', [
            'cart' => $cart,
            'total' => $total
        ]);
    }

    public function checkout(){

        session_start();

        // Get user info if logged in
        $user = null;
        if (!empty($_SESSION['user_id'])) {
            $pdo = \App\Utils\Database::getInstance();
            $stmt = $pdo->prepare("SELECT first_name, last_name, email, phone, address FROM users WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        }

        // Generate CSRF token if not present
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $pdo = \App\Utils\Database::getInstance();

                // Get user info if logged in
                $user = null;
                if (!empty($_SESSION['user_id'])) {
                    $pdo = \App\Utils\Database::getInstance();
                    $stmt = $pdo->prepare("SELECT first_name, last_name, email, phone, address FROM users WHERE user_id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $user = $stmt->fetch(\PDO::FETCH_ASSOC);
                }
        // Partner hotels
        $stmt = $pdo->query("SELECT * FROM partner_hotels ORDER BY name ASC");
        $partnerHotels = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Pickup locations
        $pickupStmt = $pdo->query("SELECT * FROM pickup_locations ORDER BY name ASC");
        $pickupLocations = $pickupStmt->fetchAll(\PDO::FETCH_ASSOC);

        $order = null;
        $showConfirmation = false;

        if (isset($_GET['order']) && ctype_digit($_GET['order']) && isset($_GET['token'])) {
            $orderId = (int)$_GET['order'];
            $token   = $_GET['token'];

            if (isset($_SESSION["order_token_$orderId"]) && hash_equals($_SESSION["order_token_$orderId"], $token)) {
                $orderModel = new \App\Models\OrderModel();
                $order = $orderModel->getOrderById($orderId);

                if ($order) {
                    $showConfirmation = true;
                    // Token used → delete it so link can't be reused
                    // unset($_SESSION["order_token_$orderId"]);
                }
            }
        }
        

        $this->render('checkout', [
            'user'            => $user,
            'csrf_token'      => $_SESSION['csrf_token'],
            'partnerHotels'   => $partnerHotels,
            'pickupLocations' => $pickupLocations,
            'showConfirmation'=> $showConfirmation,   
            'order'           => $order,              
        ]);

        
    }

    // private function getPDO()
    // {
    //     return new \PDO('mysql:host=localhost;dbname=getaround_db', 'getaroundmobility', 'itup420');
    // }

    public function processCheckout(){
        session_start();

        // CSRF check
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            echo "Order failed: Invalid CSRF token";
            exit;
        }

        // Sanitize and validate input
        $first_name = htmlspecialchars(trim($_POST['first_name'] ?? ''));
        $last_name = htmlspecialchars(trim($_POST['last_name'] ?? ''));
        $phone = preg_replace('/\D/', '', $_POST['phone'] ?? '');
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $address1 = htmlspecialchars(trim($_POST['address1'] ?? ''));
        $address2 = htmlspecialchars(trim($_POST['address2'] ?? ''));
        $state = htmlspecialchars(trim($_POST['state'] ?? ''));
        $zip = htmlspecialchars(trim($_POST['zip'] ?? ''));
        $pickup_location = htmlspecialchars(trim($_POST['pickup_location'] ?? ''));
        $notes = htmlspecialchars(trim($_POST['notes'] ?? ''));
        $payment = htmlspecialchars(trim($_POST['payment'] ?? ''));
        $cart = json_decode($_POST['cart'] ?? '[]', true);

        // Validate required fields
        if (!$first_name || !$last_name || !$phone || !$email || !$address1 || !$state || !$zip || !$payment || empty($cart)) {
            http_response_code(400);
            echo "Missing required fields.";
            exit;
        }

        // Calculate total
        $total_amount = 0;
        foreach ($cart as $item) {
            $total_amount += $item['price'] * $item['qty'];
        }
        $status = 'pending';

        // Determine user type
        $user_id = $_SESSION['user_id'] ?? null;
        $guest_first_name = $user_id ? null : $first_name;
        $guest_last_name = $user_id ? null : $last_name;
        $guest_email = $user_id ? null : $email;
        $customer_type = $user_id ? 'user' : 'guest';
        $sale_type = $_POST['sale_type'] ?? 'rental';

        // Prepare order data for model
        $orderData = [
            'user_id' => $user_id,
            'guest_first_name' => $guest_first_name,
            'guest_last_name' => $guest_last_name,
            'guest_email' => $guest_email,
            'address1' => $address1,
            'address2' => $address2,
            'state' => $state,
            'zip' => $zip,
            'pickup_location' => $pickup_location,
            'notes' => $notes,
            'payment' => $payment,
            'total_amount' => $total_amount,
            'status' => $status,
            'customer_type' => $customer_type,
            'sale_type' => $sale_type
        ];

        // Place order using the unified fullOrderProcess (handles DB, PDFs, email)
        $orderModel = new OrderModel();
        $orderId = $orderModel->fullOrderProcess($_POST, $cart, $_SESSION);

        if ($orderId) {
            echo "success";
        } else {
            http_response_code(500);
            echo "Order failed to process.";
        }
    }

    public function contact(){
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        $this->render('contact', [
            'csrf_token' => $_SESSION['csrf_token'],
            'error' => '',
            'success' => ''
        ]);
    }

}



