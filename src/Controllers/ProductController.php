<?php
namespace App\Controllers;
use App\Controller;
use App\Models\ProductModel;
use App\Services\AdminImageUploadService;

class ProductController extends Controller {
    private function storeUploadedAdminImage(array $imageFile, string $prefix): ?string
    {
        return (new AdminImageUploadService())->store($imageFile, $prefix, 'Image upload failed.');
    }

    private function extractUploadedFile(array $files, string $field, array $path): ?array
    {
        if (!isset($files[$field]) || !is_array($files[$field])) {
            return null;
        }

        $source = $files[$field];
        $entry = [];
        foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $attribute) {
            $value = $source[$attribute] ?? null;
            foreach ($path as $segment) {
                if (!is_array($value) || !array_key_exists($segment, $value)) {
                    return null;
                }
                $value = $value[$segment];
            }
            $entry[$attribute] = $value;
        }

        return $entry;
    }

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

    private function appendAdminAuditLog(string $message): void {
        $logDir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($logDir) && !mkdir($logDir, 0775, true) && !is_dir($logDir)) {
            return;
        }

        $entry = sprintf(
            "%s [%s] %s\n",
            date('Y-m-d H:i:s'),
            trim((string)($_SESSION['admin_username'] ?? 'admin')),
            $message
        );

        @file_put_contents($logDir . '/admin-audit.log', $entry, FILE_APPEND | LOCK_EX);
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
        $this->ensureAdminSession();
        $this->ensureManagePermission('/admin/products');

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
        $auditDetails = [
            'deleted_ids' => [],
            'delete_failed' => [],
            'updated_ids' => [],
            'created_names' => [],
            'visibility_changes' => [],
            'sale_type' => 'rental',
        ];

        // Handle deletions
        if (!empty($_POST['deleted_ids'])) {
            $ids = explode(',', $_POST['deleted_ids']);
            $deleteWarnings = [];
            foreach ($ids as $id) {
                $id = intval($id);
                if ($id) {
                    $deleteResult = $productModel->deleteProduct($id);
                    if (!($deleteResult['success'] ?? false)) {
                        $deleteWarnings[] = "Product ID {$id}: " . ($deleteResult['message'] ?? 'Delete failed.');
                        $auditDetails['delete_failed'][] = [
                            'product_id' => $id,
                            'message' => $deleteResult['message'] ?? 'Delete failed.',
                        ];
                    } else {
                        $auditDetails['deleted_ids'][] = $id;
                    }
                }
            }
            if (!empty($deleteWarnings)) {
                $_SESSION['product_delete_warnings'] = $deleteWarnings;
            }
        }

        // Update existing products
        if (!empty($_POST['product_name'])) {
            // Get sale_type for each product
            $allProducts = $productModel->getAllProducts();
            $saleTypeMap = [];
            $hiddenMap = [];
            foreach ($allProducts as $prod) {
                $saleTypeMap[$prod['product_id']] = $prod['sale_type'] ?? '';
                $hiddenMap[$prod['product_id']] = !empty($prod['is_hidden']) ? 1 : 0;
            }
            foreach ($_POST['product_name'] as $id => $name) {
                if ($id !== 'new') {
                    $currentHidden = $hiddenMap[$id] ?? 0;
                    $imagePath = htmlspecialchars(trim($_POST['image_url'][$id] ?? ''));
                    $uploadedImage = $this->extractUploadedFile($_FILES, 'product_image', [$id]);
                    if ($uploadedImage) {
                        try {
                            $uploadedPath = $this->storeUploadedAdminImage($uploadedImage, 'product');
                        } catch (\RuntimeException $exception) {
                            $_SESSION['product_upload_error'] = $exception->getMessage();
                            header('Location: /admin/products');
                            exit;
                        }
                        if ($uploadedPath !== null) {
                            $imagePath = $uploadedPath;
                        }
                    }

                    $data = [
                        'product_name' => htmlspecialchars(trim($name)),
                        'product_category_id' => intval($_POST['product_category_id'][$id] ?? 0),
                        'price' => filter_var($_POST['price'][$id] ?? 0, FILTER_VALIDATE_FLOAT),
                        'description' => htmlspecialchars(trim($_POST['description'][$id] ?? '')),
                        'short_description' => htmlspecialchars($this->normalizeShortDescription($_POST['short_description'][$id] ?? '')),
                        'image_url' => htmlspecialchars(trim($imagePath)),
                        'is_hidden' => !empty($_POST['is_hidden'][$id]) ? 1 : 0,
                    ];
                    $saleType = $saleTypeMap[$id] ?? '';
                    $newHidden = !empty($data['is_hidden']) ? 1 : 0;
                    if ($saleType === 'sale') {
                        $productModel->updateProductForSale($id, $data);
                    } else {
                        $productModel->updateProduct($id, $data);
                    }
                    $auditDetails['updated_ids'][] = (int)$id;

                    if ((int)$currentHidden !== (int)$newHidden) {
                        $auditDetails['visibility_changes'][] = [
                            'product_id' => (int)$id,
                            'from' => $currentHidden ? 'hidden' : 'visible',
                            'to' => $newHidden ? 'hidden' : 'visible',
                        ];
                        $this->appendAdminAuditLog(sprintf(
                            'Product ID %d visibility changed from %s to %s.',
                            (int)$id,
                            $currentHidden ? 'hidden' : 'visible',
                            $newHidden ? 'hidden' : 'visible'
                        ));
                    }
                }
            }
        }

        // Add new products
        if (!empty($_POST['product_name']['new'])) {
            foreach ($_POST['product_name']['new'] as $i => $newName) {
                $newName = htmlspecialchars(trim($newName));
                if (!empty($newName)) {
                    $imagePath = !empty($_POST['image_url']['new'][$i]) ? htmlspecialchars(trim($_POST['image_url']['new'][$i])) : '';
                    $uploadedImage = $this->extractUploadedFile($_FILES, 'product_image', ['new', $i]);
                    if ($uploadedImage) {
                        try {
                            $uploadedPath = $this->storeUploadedAdminImage($uploadedImage, 'product');
                        } catch (\RuntimeException $exception) {
                            $_SESSION['product_upload_error'] = $exception->getMessage();
                            header('Location: /admin/products');
                            exit;
                        }
                        if ($uploadedPath !== null) {
                            $imagePath = $uploadedPath;
                        }
                    }

                    $data = [
                        'product_name' => $newName,
                        'product_category_id' => intval($_POST['product_category_id']['new'][$i] ?? 0),
                        'price' => filter_var($_POST['price']['new'][$i] ?? 0, FILTER_VALIDATE_FLOAT),
                        'description' => !empty($_POST['description']['new'][$i]) ? htmlspecialchars(trim($_POST['description']['new'][$i])) : 'No description',
                        'short_description' => !empty($_POST['short_description']['new'][$i]) ? htmlspecialchars($this->normalizeShortDescription($_POST['short_description']['new'][$i])) : '',
                        'image_url' => $imagePath !== '' ? htmlspecialchars(trim($imagePath)) : 'No image',
                        'is_hidden' => !empty($_POST['is_hidden']['new'][$i]) ? 1 : 0,
                    ];
                    $productModel->addProduct($data);
                    $auditDetails['created_names'][] = $newName;
                    if (!empty($data['is_hidden'])) {
                        $this->appendAdminAuditLog(sprintf('New product "%s" was created hidden.', $newName));
                    }
                }
            }
        }

        if (
            !empty($auditDetails['deleted_ids']) ||
            !empty($auditDetails['delete_failed']) ||
            !empty($auditDetails['updated_ids']) ||
            !empty($auditDetails['created_names'])
        ) {
            $this->logAdminAction('products_saved', 'product', null, $auditDetails + [
                'counts' => [
                    'deleted' => count($auditDetails['deleted_ids']),
                    'delete_failed' => count($auditDetails['delete_failed']),
                    'updated' => count(array_unique($auditDetails['updated_ids'])),
                    'created' => count($auditDetails['created_names']),
                    'visibility_changed' => count($auditDetails['visibility_changes']),
                ],
            ]);
        }

        header('Location: /admin/products');
        exit;
    }

    public function create() {
        $this->ensureAdminSession();
        $this->ensureManagePermission('/admin/products');

        header('Location: /admin/products');
        exit;
    }

    public function store() {
        $this->save();
    }

    public function delete() {
        $this->ensureAdminSession();
        $this->ensureManagePermission('/admin/products');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /admin/products');
            exit;
        }

        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            die('Invalid CSRF token');
        }

        $id = (int)($_POST['product_id'] ?? $_POST['id'] ?? 0);
        if ($id > 0) {
            $_POST['deleted_ids'] = (string)$id;
            $this->save();
        }

        header('Location: /admin/products');
        exit;
    }

    public function forSale(){
        $productModel = new ProductModel();
        $products = $productModel->getProductsForSale(false);
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
        $scooters = $productModel->getProductsForSale(false);
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
                'image_url' => htmlspecialchars(trim((string)($_POST['image_url'] ?? ''))),
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
        $auditDetails = [
            'deleted_ids' => [],
            'delete_failed' => [],
            'updated_ids' => [],
            'created_names' => [],
            'sale_type' => 'sale',
        ];

        // Handle deleted IDs
        if (!empty($_POST['deleted_ids'])) {
            $deletedIds = explode(',', $_POST['deleted_ids']);
            $deleteWarnings = [];
            foreach ($deletedIds as $id) {
                $id = (int)$id;
                if ($id <= 0) {
                    continue;
                }
                $deleteResult = $productModel->deleteProduct($id);
                if (!($deleteResult['success'] ?? false)) {
                    $deleteWarnings[] = "Product ID {$id}: " . ($deleteResult['message'] ?? 'Delete failed.');
                    $auditDetails['delete_failed'][] = [
                        'product_id' => $id,
                        'message' => $deleteResult['message'] ?? 'Delete failed.',
                    ];
                } else {
                    $auditDetails['deleted_ids'][] = $id;
                }
            }
            if (!empty($deleteWarnings)) {
                $_SESSION['product_delete_warnings'] = $deleteWarnings;
            }
        }

        // Handle updates
        if (!empty($_POST['product_name'])) {
            foreach ($_POST['product_name'] as $id => $name) {
                if ($id === 'new') continue; // Skip if new items
                $imagePath = htmlspecialchars(trim($_POST['image_url'][$id] ?? ''));
                $uploadedImage = $this->extractUploadedFile($_FILES, 'scooter_image', [$id]);
                if ($uploadedImage) {
                    try {
                        $uploadedPath = $this->storeUploadedAdminImage($uploadedImage, 'scooter');
                    } catch (\RuntimeException $exception) {
                        $_SESSION['product_upload_error'] = $exception->getMessage();
                        header('Location: /admin/scooters-for-sale');
                        exit;
                    }
                    if ($uploadedPath !== null) {
                        $imagePath = $uploadedPath;
                    }
                }

                $data = [
                    'product_name' => htmlspecialchars(trim($name)),
                    'product_category_id' => intval($_POST['product_category_id'][$id] ?? 0),
                    'price' => filter_var($_POST['price'][$id] ?? 0, FILTER_VALIDATE_FLOAT),
                    'stock_quantity' => intval($_POST['stock_quantity'][$id] ?? 0),
                    'description' => htmlspecialchars(trim($_POST['description'][$id] ?? '')),
                    'short_description' => htmlspecialchars($this->normalizeShortDescription($_POST['short_description'][$id] ?? '')),
                    'image_url' => htmlspecialchars(trim($imagePath)),
                    'is_available' => isset($_POST['is_available'][$id]) ? 1 : 0
                ];
                $productModel->updateProductForSale($id, $data);
                $auditDetails['updated_ids'][] = (int)$id;
            }
        }

        // Handle new products
        if (!empty($_POST['product_name']['new'])) {
            foreach ($_POST['product_name']['new'] as $i => $newName) {
                $newName = htmlspecialchars(trim($newName));
                if (!empty($newName)) {
                    $imagePath = !empty($_POST['image_url']['new'][$i]) ? htmlspecialchars(trim($_POST['image_url']['new'][$i])) : '';
                    $uploadedImage = $this->extractUploadedFile($_FILES, 'scooter_image', ['new', $i]);
                    if ($uploadedImage) {
                        try {
                            $uploadedPath = $this->storeUploadedAdminImage($uploadedImage, 'scooter');
                        } catch (\RuntimeException $exception) {
                            $_SESSION['product_upload_error'] = $exception->getMessage();
                            header('Location: /admin/scooters-for-sale');
                            exit;
                        }
                        if ($uploadedPath !== null) {
                            $imagePath = $uploadedPath;
                        }
                    }

                    $data = [
                        'product_name' => $newName,
                        'product_category_id' => intval($_POST['product_category_id']['new'][$i] ?? 0),
                        'price' => filter_var($_POST['price']['new'][$i] ?? 0, FILTER_VALIDATE_FLOAT),
                        'stock_quantity' => intval($_POST['stock_quantity']['new'][$i] ?? 0),
                        'description' => htmlspecialchars(trim($_POST['description']['new'][$i] ?? '')),
                        'short_description' => htmlspecialchars($this->normalizeShortDescription($_POST['short_description']['new'][$i] ?? '')),
                        'image_url' => $imagePath !== '' ? htmlspecialchars(trim($imagePath)) : 'No image',
                        'is_available' => isset($_POST['is_available']['new'][$i]) ? 1 : 0
                    ];
                    $productModel->addProductForSale($data);
                    $auditDetails['created_names'][] = $newName;
                }
            }
        }

        if (
            !empty($auditDetails['deleted_ids']) ||
            !empty($auditDetails['delete_failed']) ||
            !empty($auditDetails['updated_ids']) ||
            !empty($auditDetails['created_names'])
        ) {
            $this->logAdminAction('sale_products_saved', 'product', null, $auditDetails + [
                'counts' => [
                    'deleted' => count($auditDetails['deleted_ids']),
                    'delete_failed' => count($auditDetails['delete_failed']),
                    'updated' => count(array_unique($auditDetails['updated_ids'])),
                    'created' => count($auditDetails['created_names']),
                ],
            ]);
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
            $imagePath = htmlspecialchars(trim((string)($_POST['image_url'] ?? '')));
            $uploadedImage = $this->extractUploadedFile($_FILES, 'scooter_image', [$_POST['product_id']]);
            if ($uploadedImage) {
                try {
                    $uploadedPath = $this->storeUploadedAdminImage($uploadedImage, 'scooter');
                } catch (\RuntimeException $exception) {
                    $_SESSION['product_upload_error'] = $exception->getMessage();
                    header('Location: /admin/scooters-for-sale');
                    exit;
                }
                if ($uploadedPath !== null) {
                    $imagePath = $uploadedPath;
                }
            }

            $data = [
                'product_name' => htmlspecialchars(trim($_POST['product_name'] ?? '')),
                'product_category_id' => intval($_POST['product_category_id'] ?? 0),
                'price' => filter_var($_POST['price'] ?? 0, FILTER_VALIDATE_FLOAT),
                'stock_quantity' => intval($_POST['stock_quantity'] ?? 0),
                'description' => htmlspecialchars(trim($_POST['description'] ?? '')),
                'short_description' => htmlspecialchars($this->normalizeShortDescription($_POST['short_description'] ?? '')),
                'image_url' => htmlspecialchars(trim($imagePath)),
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
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
                http_response_code(403);
                die('Invalid CSRF token');
            }
            $productModel = new ProductModel();
            $deleteResult = $productModel->deleteProduct($_POST['product_id']);
            if (!($deleteResult['success'] ?? false)) {
                $_SESSION['product_delete_warnings'] = [
                    'Product ID ' . (int)$_POST['product_id'] . ': ' . ($deleteResult['message'] ?? 'Delete failed.'),
                ];
            }
            header('Location: /admin/scooters-for-sale');
            exit;
        }
    }

    // ADMIN: Add Product Variation (form display & submission)
    public function addProductVariation() {
        $this->ensureAdminSession();
        $this->ensureManagePermission('/admin/product-variations');

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
                    $this->logAdminAction('product_variation_created', 'product_variation', (int)$pdo->lastInsertId(), [
                        'product_id' => $product_id,
                        'variation_name' => $variation_name,
                        'price' => $price,
                        'stock' => $stock,
                    ]);
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
        $this->ensureAdminSession();

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
        $this->ensureAdminSession();
        header('Content-Type: application/json; charset=utf-8');
        $product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
        if (!$product_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid product_id']);
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
        $this->ensureAdminSession();
        $this->ensureManagePermission('/admin/product-variations');

        // CSRF check
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            die('Invalid CSRF token');
        }
        $pdo = \App\Utils\Database::getInstance();
        $deleteWarnings = [];
        $auditDetails = [
            'deleted_ids' => [],
            'delete_failed' => [],
            'updated_ids' => [],
            'created_names' => [],
        ];

        // Handle deletions (hard delete in batch save)
        if (!empty($_POST['deleted_ids'])) {
            $ids = explode(',', $_POST['deleted_ids']);
            foreach ($ids as $id) {
                $id = intval($id);
                if ($id) {
                    try {
                        $pdo->beginTransaction();

                        $deleteRentalPricesStmt = $pdo->prepare("DELETE FROM rental_prices WHERE variation_id = ?");
                        $deleteRentalPricesStmt->execute([$id]);

                        // Keep historical integrity and remove stock from availability.
                        // We keep variation links intact for history, but mark units as Sold so stock decreases.
                        $archiveScootersStmt = $pdo->prepare("UPDATE scooters SET status = 'Sold' WHERE variation_id = ?");
                        $archiveScootersStmt->execute([$id]);

                        // Clear featured variation reference for products using this variation.
                        $clearFeaturedStmt = $pdo->prepare("UPDATE products SET featured_variation_id = NULL WHERE featured_variation_id = ?");
                        $clearFeaturedStmt->execute([$id]);

                        // Soft delete variation so legacy order/history references remain valid.
                        $stmt = $pdo->prepare("UPDATE product_variations SET is_active = 0 WHERE variation_id = ?");
                        $stmt->execute([$id]);

                        $pdo->commit();
                        $auditDetails['deleted_ids'][] = $id;
                    } catch (\PDOException $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        $deleteWarnings[] = "Variation ID {$id}: cannot be archived due to required linked records.";
                        $auditDetails['delete_failed'][] = $id;
                    }
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
                        $auditDetails['updated_ids'][] = (int)$id;
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
                    $auditDetails['created_names'][] = $name;
                }
            }
        }

        if (
            !empty($auditDetails['deleted_ids']) ||
            !empty($auditDetails['delete_failed']) ||
            !empty($auditDetails['updated_ids']) ||
            !empty($auditDetails['created_names'])
        ) {
            $this->logAdminAction('product_variations_saved', 'product_variation', null, $auditDetails + [
                'counts' => [
                    'deleted' => count($auditDetails['deleted_ids']),
                    'delete_failed' => count($auditDetails['delete_failed']),
                    'updated' => count(array_unique($auditDetails['updated_ids'])),
                    'created' => count($auditDetails['created_names']),
                ],
            ]);
        }

        if (!empty($deleteWarnings)) {
            $_SESSION['variation_delete_warnings'] = $deleteWarnings;
        }
        header('Location: /admin/product-variations');
        exit;
    }

    
}
