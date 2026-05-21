<?php
namespace App\Controllers;
use App\Controller;
use App\Models\ProductModel;

class ProductController extends Controller {
    private function normalizeShortDescription(?string $value): string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        $parts = preg_split('/\r\n|\r|\n|\|\|/', $text) ?: [];
        $clean = [];
        foreach ($parts as $part) {
            $line = trim($part);
            if ($line === '') {
                continue;
            }
            $clean[] = $line;
            if (count($clean) >= 2) {
                break;
            }
        }

        return implode("\n", $clean);
    }

    private function ensureAdminSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['admin_id'])) {
            header('Location: /admin/login');
            exit;
        }
    }

    private function ensureManagePermission(string $redirect = '/admin/orders'): void {
        $role = strtolower($_SESSION['admin_role'] ?? '');
        if (!in_array($role, ['admin', 'superadmin'], true)) {
            header('Location: ' . $redirect);
            exit;
        }
    }
    
    
    public function index() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['admin_id'])) {
            header('Location: /admin/login');
            exit;
        }

        // Only generate a new CSRF token if one does not exist
        if (!isset($_SESSION['csrf_token']) || empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $productModel = new ProductModel();
        // Only fetch products with sale_type = 'rental'
        $products = $productModel->getAllProducts();
        $categories = $productModel->getCategories();

        $this->renderAdmin('admin/products', [
            'products' => $products,
            'categories' => $categories,
            'csrf_token' => $_SESSION['csrf_token']
        ]);
    }

    public function save() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // VALIDATE CSRF TOKEN
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            error_log('POST CSRF: ' . ($_POST['csrf_token'] ?? 'NULL'));
            error_log('SESSION CSRF: ' . ($_SESSION['csrf_token'] ?? 'NULL'));
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
                http_response_code(403);
                die('Invalid CSRF token');
            }
        }

        $productModel = new ProductModel();

        // Handle deletions
        if (!empty($_POST['deleted_ids'])) {
            $ids = explode(',', $_POST['deleted_ids']);
            foreach ($ids as $id) {
                $id = intval($id);
                if ($id) {
                    $productModel->deleteProduct($id);
                }
            }
        }

        // Update existing products
        if (!empty($_POST['product_name'])) {
            // Get sale_type for each product
            $allProducts = $productModel->getAllProducts();
            $saleTypeMap = [];
            foreach ($allProducts as $prod) {
                $saleTypeMap[$prod['product_id']] = $prod['sale_type'] ?? '';
            }
            foreach ($_POST['product_name'] as $id => $name) {
                if ($id !== 'new') {
                    $data = [
                        'product_name' => htmlspecialchars(trim($name)),
                        'product_category_id' => intval($_POST['product_category_id'][$id] ?? 0),
                        'price' => filter_var($_POST['price'][$id] ?? 0, FILTER_VALIDATE_FLOAT),
                        'description' => htmlspecialchars(trim($_POST['description'][$id] ?? '')),
                        'short_description' => htmlspecialchars($this->normalizeShortDescription($_POST['short_description'][$id] ?? '')),
                        'image_url' => htmlspecialchars(trim($_POST['image_url'][$id] ?? '')),
                    ];
                    $saleType = $saleTypeMap[$id] ?? '';
                    if ($saleType === 'sale') {
                        $productModel->updateProductForSale($id, $data);
                    } else {
                        $productModel->updateProduct($id, $data);
                    }
                }
            }
        }

        // Add new products
        if (!empty($_POST['product_name']['new'])) {
            foreach ($_POST['product_name']['new'] as $i => $newName) {
                $newName = htmlspecialchars(trim($newName));
                if (!empty($newName)) {
                    $data = [
                        'product_name' => $newName,
                        'product_category_id' => intval($_POST['product_category_id']['new'][$i] ?? 0),
                        'price' => filter_var($_POST['price']['new'][$i] ?? 0, FILTER_VALIDATE_FLOAT),
                        'description' => !empty($_POST['description']['new'][$i]) ? htmlspecialchars(trim($_POST['description']['new'][$i])) : 'No description',
                        'short_description' => !empty($_POST['short_description']['new'][$i]) ? htmlspecialchars($this->normalizeShortDescription($_POST['short_description']['new'][$i])) : '',
                        'image_url' => !empty($_POST['image_url']['new'][$i]) ? htmlspecialchars(trim($_POST['image_url']['new'][$i])) : 'No image',
                    ];
                    $productModel->addProduct($data);
                }
            }
        }

        header('Location: /admin/products');
        exit;
    }

    public function forSale(){
        $productModel = new ProductModel();
        $products = $productModel->getProductsForSale();
        $categories = $productModel->getCategories();

        $this->render('for-sale', [
            'products' => $products,
            'categories' => $categories
        ]);
    }


    // ADMIN SIDE
    public function scootersForSale() {
        $this->ensureAdminSession();

        // Only check CSRF for POST requests
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
                http_response_code(403);
                die('Invalid CSRF token');
            }
        }

        // echo '<pre>';
        // print_r($_POST);
        // print_r($_SESSION['csrf_token']);
        // echo '</pre>';
        

        // Always set CSRF token if missing
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        $csrf_token = $_SESSION['csrf_token'];

        $productModel = new ProductModel();
        $scooters = $productModel->getProductsForSale();
        $categories = $productModel->getCategories();

        $this->renderAdmin('admin/scooters-for-sale', [
            'scooters' => $scooters,
            'categories' => $categories,
            'csrf_token' => $csrf_token
        ]);
    }

    public function addScooterForSale(){
        $this->ensureAdminSession();
        $this->ensureManagePermission('/admin/scooters-for-sale');

        
        $productModel = new ProductModel();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
                http_response_code(403);
                die('Invalid CSRF token');
            }
            $data = [
                'product_name' => htmlspecialchars(trim($_POST['product_name'] ?? '')),
                'product_category_id' => intval($_POST['product_category_id'] ?? 0),
                'price' => filter_var($_POST['price'] ?? 0, FILTER_VALIDATE_FLOAT),
                'stock_quantity' => intval($_POST['stock_quantity'] ?? 0),
                'description' => htmlspecialchars(trim($_POST['description'] ?? '')),
                'short_description' => htmlspecialchars($this->normalizeShortDescription($_POST['short_description'] ?? '')),
                'image_url' => htmlspecialchars(trim($_POST['image_url'] ?? '')),
                'is_available' => isset($_POST['is_available']) ? 1 : 0
            ];
            $productModel->addProductForSale($data);
            header('Location: /admin/scooters-for-sale');
            exit;
        } else {
            // GET request: show the add form
            $categories = $productModel->getCategories();
            $csrf_token = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
            $_SESSION['csrf_token'] = $csrf_token;
            $this->renderAdmin('admin/add-scooter-for-sale', [
                'categories' => $categories,
                'csrf_token' => $csrf_token
            ]);
        }
    }

    public function saveScootersForSale(){
        $this->ensureAdminSession();
        $this->ensureManagePermission('/admin/scooters-for-sale');

        // echo '<pre>';
        // print_r($_POST);
        // print_r($_SESSION['csrf_token']);
        // echo '</pre>';

        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            die('Invalid CSRF token');
        }

        $productModel = new ProductModel();

        // Handle deleted IDs
        if (!empty($_POST['deleted_ids'])) {
            $deletedIds = explode(',', $_POST['deleted_ids']);
            foreach ($deletedIds as $id) {
                $productModel->deleteProduct((int)$id);
            }
        }

        // Handle updates
        if (!empty($_POST['product_name'])) {
            foreach ($_POST['product_name'] as $id => $name) {
                if ($id === 'new') continue; // Skip if new items
                $data = [
                    'product_name' => htmlspecialchars(trim($name)),
                    'product_category_id' => intval($_POST['product_category_id'][$id] ?? 0),
                    'price' => filter_var($_POST['price'][$id] ?? 0, FILTER_VALIDATE_FLOAT),
                    'stock_quantity' => intval($_POST['stock_quantity'][$id] ?? 0),
                    'description' => htmlspecialchars(trim($_POST['description'][$id] ?? '')),
                    'short_description' => htmlspecialchars($this->normalizeShortDescription($_POST['short_description'][$id] ?? '')),
                    'image_url' => htmlspecialchars(trim($_POST['image_url'][$id] ?? '')),
                    'is_available' => isset($_POST['is_available'][$id]) ? 1 : 0
                ];
                $productModel->updateProductForSale($id, $data);
            }
        }

        // Handle new products
        if (!empty($_POST['product_name']['new'])) {
            foreach ($_POST['product_name']['new'] as $i => $newName) {
                $newName = htmlspecialchars(trim($newName));
                if (!empty($newName)) {
                    $data = [
                        'product_name' => $newName,
                        'product_category_id' => intval($_POST['product_category_id']['new'][$i] ?? 0),
                        'price' => filter_var($_POST['price']['new'][$i] ?? 0, FILTER_VALIDATE_FLOAT),
                        'stock_quantity' => intval($_POST['stock_quantity']['new'][$i] ?? 0),
                        'description' => htmlspecialchars(trim($_POST['description']['new'][$i] ?? '')),
                        'short_description' => htmlspecialchars($this->normalizeShortDescription($_POST['short_description']['new'][$i] ?? '')),
                        'image_url' => htmlspecialchars(trim($_POST['image_url']['new'][$i] ?? '')),
                        'is_available' => isset($_POST['is_available']['new'][$i]) ? 1 : 0
                    ];
                    $productModel->addProductForSale($data);
                }
            }
        }

        header('Location: /admin/scooters-for-sale');
        exit;
    }

    public function updateScooterForSale(){
        $this->ensureAdminSession();
        $this->ensureManagePermission('/admin/scooters-for-sale');

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
                http_response_code(403);
                die('Invalid CSRF token');
            }
            $data = [
                'product_name' => htmlspecialchars(trim($_POST['product_name'] ?? '')),
                'product_category_id' => intval($_POST['product_category_id'] ?? 0),
                'price' => filter_var($_POST['price'] ?? 0, FILTER_VALIDATE_FLOAT),
                'stock_quantity' => intval($_POST['stock_quantity'] ?? 0),
                'description' => htmlspecialchars(trim($_POST['description'] ?? '')),
                'short_description' => htmlspecialchars($this->normalizeShortDescription($_POST['short_description'] ?? '')),
                'image_url' => htmlspecialchars(trim($_POST['image_url'] ?? '')),
                'is_available' => isset($_POST['is_available']) ? 1 : 0
            ];
            $productModel = new ProductModel();
            $productModel->updateProductForSale(intval($_POST['product_id']), $data);
            header('Location: /admin/scooters-for-sale');
            exit;
        }
    }

    public function deleteScooterForSale(){
        $this->ensureAdminSession();
        $this->ensureManagePermission('/admin/scooters-for-sale');

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
            $productModel = new ProductModel();
            $productModel->deleteProduct($_POST['product_id']);
            header('Location: /admin/scooters-for-sale');
            exit;
        }
    }

    // ADMIN: Add Product Variation (form display & submission)
    public function addProductVariation() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $pdo = \App\Utils\Database::getInstance();
        // Always set CSRF token if missing
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        $csrf_token = $_SESSION['csrf_token'];
        $products = $pdo->query("SELECT product_id, product_name FROM products")->fetchAll(\PDO::FETCH_ASSOC);
        $error = $success = null;
        // Handle POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
                http_response_code(403);
                die('Invalid CSRF token');
            }
            $product_id = intval($_POST['product_id'] ?? 0);
            $variation_name = htmlspecialchars(trim($_POST['variation_name'] ?? ''));
            $price = filter_var($_POST['price'] ?? 0, FILTER_VALIDATE_FLOAT);
            $stock = intval($_POST['stock'] ?? 0);
            if (!$product_id || !$variation_name || $price === false || $stock < 0) {
                $error = 'Please fill in all fields correctly.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO product_variations (product_id, variation_name, price, stock) VALUES (?, ?, ?, ?)");
                if ($stmt->execute([$product_id, $variation_name, $price, $stock])) {
                    $success = 'Product variation added successfully!';
                } else {
                    $error = 'Failed to add product variation.';
                }
            }
        }
        $this->renderAdmin('admin/add_product_variation', [
            'products' => $products,
            'error' => $error,
            'success' => $success,
            'csrf_token' => $csrf_token
        ]);
    }

    // ADMIN: List all product variations
    public function listProductVariations() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $pdo = \App\Utils\Database::getInstance();
        $stmt = $pdo->query("SELECT v.*, p.product_name FROM product_variations v JOIN products p ON v.product_id = p.product_id WHERE v.is_active = 1 ORDER BY v.variation_id DESC");
        $variations = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        // Fetch all products for the dropdown
        $products = $pdo->query("SELECT product_id, product_name FROM products")->fetchAll(\PDO::FETCH_ASSOC);
        $this->renderAdmin('admin/list_product_variations', [
            'variations' => $variations,
            'products' => $products
        ]);
    }
    
     // API endpoint: Return product variations as JSON for AJAX
    public function apiProductVariations() {
        header('Content-Type: application/json');
        $product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
        if (!$product_id) {
            echo json_encode([]);
            exit;
        }
        $pdo = \App\Utils\Database::getInstance();
        $stmt = $pdo->prepare("SELECT variation_id, variation_name FROM product_variations WHERE product_id = ? AND is_active = 1");
        $stmt->execute([$product_id]);
        $variations = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        echo json_encode($variations);
        exit;
    }

    // Batch save/edit/delete for product variations
    public function saveProductVariations() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // CSRF check
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            die('Invalid CSRF token');
        }
        $pdo = \App\Utils\Database::getInstance();

        // Handle deletions (hard delete in batch save)
        if (!empty($_POST['deleted_ids'])) {
            $ids = explode(',', $_POST['deleted_ids']);
            foreach ($ids as $id) {
                $id = intval($id);
                if ($id) {
                    $stmt = $pdo->prepare("DELETE FROM product_variations WHERE variation_id = ?");
                    $stmt->execute([$id]);
                }
            }
        }

        // Update existing variations
        if (!empty($_POST['variation_name'])) {
            foreach ($_POST['variation_name'] as $id => $name) {
                if ($id !== 'new') {
                    $product_id = intval($_POST['product_id'][$id] ?? 0);
                    $price = floatval($_POST['price'][$id] ?? 0);
                    $name = htmlspecialchars(trim($name));
                    if ($product_id && $name !== '') {
                        $stmt = $pdo->prepare("UPDATE product_variations SET product_id=?, variation_name=?, price=? WHERE variation_id=?");
                        $stmt->execute([$product_id, $name, $price, $id]);
                    }
                }
            }
        }

        // Add new variations
        if (!empty($_POST['variation_name']['new'])) {
            $names = $_POST['variation_name']['new'];
            $productIds = $_POST['product_id']['new'] ?? [];
            $prices = $_POST['price']['new'] ?? [];
            for ($i = 0; $i < count($names); $i++) {
                $name = htmlspecialchars(trim($names[$i]));
                $product_id = intval($productIds[$i] ?? 0);
                $price = floatval($prices[$i] ?? 0);
                if ($name !== '' && $product_id) {
                    $stmt = $pdo->prepare("INSERT INTO product_variations (product_id, variation_name, price, is_active) VALUES (?, ?, ?, 1)");
                    $stmt->execute([$product_id, $name, $price]);
                }
            }
        }
        header('Location: /admin/product-variations');
        exit;
    }

    
}