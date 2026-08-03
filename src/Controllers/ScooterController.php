<?php
namespace App\Controllers;
use App\Controller;

class ScooterController extends Controller {
    private $allowedStatuses = ['available', 'maintenance', 'Sold', 'archived'];

    private function requireAdminSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['admin_id'])) {
            header('Location: /admin/login');
            exit;
        }
    }

    private function requireInventoryManager(): void {
        $this->requireAdminSession();
        $this->requireAdminRoleRedirect(['admin', 'superadmin'], '/admin/orders');
    }

    private function requireAdminSessionJson(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        header('Content-Type: application/json; charset=utf-8');
        if (empty($_SESSION['admin_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
    }

    public function index() {
        $this->requireAdminSession();

        // Get all products and their scooter counts
            $scooterModel = new \App\Models\ScooterModel();
            $products = $scooterModel->getProductScooterCounts();
            $this->renderAdmin('admin/scooters', [
                'products' => $products
            ]);
    }

    public function create() {
        $this->requireInventoryManager();

        $scooterModel = new \App\Models\ScooterModel();
        $products = $scooterModel->getAllProductsBasic();
        $scooters = $scooterModel->getAllScootersBasic();
        $success = null;
        $successType = null;
        $this->renderAdmin('admin/scooters', [
            'products' => $products,
            'scooters' => $scooters,
            'success' => $success,
            'successType' => $successType
        ]);
    }

    public function store() {
        $this->requireInventoryManager();

        // VALIDATE CSRF TOKEN
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
                http_response_code(403);
                die('Invalid CSRF token');
            }
        }
        $scooterModel = new \App\Models\ScooterModel();
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : null;
        $status = in_array($_POST['status'] ?? 'available', $this->allowedStatuses) ? $_POST['status'] : 'available';
        $barcode = htmlspecialchars(trim($_POST['barcode'] ?? ''));
        $scooterModel->addScooterWithStock($product_id, $status, $barcode);

        $success = 'added';
        $successType = 'add';
        $products = $scooterModel->getAllProductsBasic();
        $scooters = $scooterModel->getAllScootersBasic();
        $this->renderAdmin('admin/scooters', [
            'products' => $products,
            'scooters' => $scooters,
            'success' => $success,
            'successType' => $successType
        ]);
    }

    public function delete() {
        $this->requireInventoryManager();

        // VALIDATE CSRF TOKEN
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
                http_response_code(403);
                die('Invalid CSRF token');
            }
        }
        $scooterModel = new \App\Models\ScooterModel();
        $scooterId = isset($_POST['scooter_id']) ? intval($_POST['scooter_id']) : null;
        if ($scooterId) {
            $deleteResult = $scooterModel->batchDeleteScooters([(string) $scooterId]);
            $_SESSION['status_changes'] = array_merge(
                array_map(function($d) {
                    return "Scooter #{$d['id']} (barcode: {$d['barcode']}, status: {$d['status']}) was deleted.";
                }, $deleteResult['deleted'] ?? []),
                array_map(function($d) {
                    return "Scooter #{$d['id']} (barcode: {$d['barcode']}) has completed history and was archived instead of deleted.";
                }, $deleteResult['archived'] ?? []),
                $deleteResult['errors'] ?? []
            );
        }
        header('Location: /admin/scooters');
        exit;
    }

    public function edit() {
        $this->requireInventoryManager();

        $scooterModel = new \App\Models\ScooterModel();
        $scooterId = isset($_GET['scooter_id']) ? intval($_GET['scooter_id']) : null;
        $scooter = $scooterModel->getScooterById($scooterId);
        $products = $scooterModel->getAllProductsBasic();
        $this->renderAdmin('admin/scooters', [
            'scooter' => $scooter,
            'products' => $products
        ]);
    }

    public function update() {
        $this->requireInventoryManager();

        // VALIDATE CSRF TOKEN
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
                http_response_code(403);
                die('Invalid CSRF token');
            }
        }
        $scooterModel = new \App\Models\ScooterModel();
        $scooterId = isset($_POST['scooter_id']) ? intval($_POST['scooter_id']) : null;
        $data = [
            'product_id' => isset($_POST['product_id']) ? intval($_POST['product_id']) : null,
            'status' => in_array($_POST['status'] ?? 'available', $this->allowedStatuses) ? $_POST['status'] : 'available',
            'barcode' => htmlspecialchars(trim($_POST['barcode'] ?? '')),
        ];
        $scooterModel->updateScooterById($scooterId, $data);
        header('Location: /admin/scooters');
        exit;
    }

    public function save() {
        $this->requireInventoryManager();

        // VALIDATE CSRF TOKEN
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
                http_response_code(403);
                die('Invalid CSRF token');
            }
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $scooterModel = new \App\Models\ScooterModel();
            // Validate product IDs
            $products = $scooterModel->getAllProductsBasic();
            $validProductIds = array_map(function($row) { return (string)$row['product_id']; }, $products);

            // 1. Handle deletions
            $deleteResult = ['deleted' => [], 'errors' => []];
            if (!empty($_POST['deleted_ids'])) {
                $ids = explode(',', $_POST['deleted_ids']);
                $deleteResult = $scooterModel->batchDeleteScooters($ids);
            }

            // 2. Update existing scooters
            $statuses = $_POST['status'] ?? [];
            $barcodes = $_POST['barcode'] ?? [];
            $productIds = $_POST['product_id'] ?? [];
            $variationIds = $_POST['variation_id'] ?? [];
            $updates = [];
            foreach ($statuses as $scooterId => $newStatus) {
                $updates[$scooterId] = [
                    'status' => $newStatus,
                    'barcode' => $barcodes[$scooterId] ?? null,
                    'product_id' => $productIds[$scooterId] ?? null,
                    'variation_id' => $variationIds[$scooterId] ?? null
                ];
            }
            $updateResult = $scooterModel->batchUpdateScooters($updates, $validProductIds);

            // 3. Add new scooters
            $addResult = ['addChanges' => [], 'errors' => []];
            if (!empty($_POST['product_id']['new'])) {
                $newProductIds = is_array($_POST['product_id']['new']) ? $_POST['product_id']['new'] : [$_POST['product_id']['new']];
                $newStatuses = is_array($_POST['status']['new']) ? $_POST['status']['new'] : [$_POST['status']['new']];
                $newVariationIds = is_array($_POST['variation_id']['new']) ? $_POST['variation_id']['new'] : [$_POST['variation_id']['new']];
                $newScooters = [];
                foreach ($newProductIds as $i => $prodId) {
                    $newScooters[] = [
                        'product_id' => $prodId,
                        'status' => $newStatuses[$i] ?? 'available',
                        'variation_id' => $newVariationIds[$i] ?? null
                    ];
                }
                $addResult = $scooterModel->batchInsertScooters($newScooters, $validProductIds);
            }

            // 4. Store all messages in session for feedback
            $_SESSION['status_changes'] = array_merge(
                $updateResult['statusChanges'] ?? [],
                $addResult['addChanges'] ?? [],
                array_map(function($d) {
                    return "Scooter #{$d['id']} (barcode: {$d['barcode']}, status: {$d['status']}) was deleted.";
                }, $deleteResult['deleted'] ?? []),
                array_map(function($d) {
                    return "Scooter #{$d['id']} (barcode: {$d['barcode']}) has completed history and was archived instead of deleted.";
                }, $deleteResult['archived'] ?? []),
                $updateResult['errors'] ?? [],
                $addResult['errors'] ?? [],
                $deleteResult['errors'] ?? []
            );

            $this->logAdminAction('scooter_inventory_saved', 'scooter', null, [
                'updated_count' => count($updateResult['statusChanges'] ?? []),
                'added_count' => count($addResult['addChanges'] ?? []),
                'deleted_count' => count($deleteResult['deleted'] ?? []),
                'archived_count' => count($deleteResult['archived'] ?? []),
                'error_count' => count(array_merge(
                    $updateResult['errors'] ?? [],
                    $addResult['errors'] ?? [],
                    $deleteResult['errors'] ?? []
                )),
                'messages' => $_SESSION['status_changes'],
            ]);

            // 5. Redirect back to scooters page
            header('Location: /admin/scooters');
            exit;
        }
    }


    public function listByProduct() {
        $this->requireAdminSessionJson();

        $scooterModel = new \App\Models\ScooterModel();
        $product_id = $_GET['product_id'] ?? null;
        $scooters = [];
        if ($product_id !== null && $product_id !== '' && !ctype_digit((string)$product_id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid product_id']);
            exit;
        }

        if ($product_id && ctype_digit((string)$product_id)) {
            $scooters = $scooterModel->getScootersByProductId($product_id);
        }
        echo json_encode($scooters); // For AJAX
    }

    // private function getPDO() {
    //     return new \PDO('mysql:host=localhost;dbname=getaround_db', 'getaroundmobility', 'itup420');
    // }

    

    // private function generateReadableBarcode($productName) {
    //     $pdo = $this->getPDO();
    //     $words = preg_split('/\s+/', trim($productName));
    //     if (count($words) >= 2) {
    //         $prefix = strtoupper(substr($words[0], 0, 3) . substr($words[1], 0, 3));
    //     } elseif (count($words) === 1) {
    //         $prefix = strtoupper(substr($words[0], 0, 6));
    //     } else {
    //         $prefix = 'SCOOTER';
    //     }

    //     // Try to find a unique code
    //     do {
    //         $random = strtoupper(dechex(mt_rand(0, 0xFFFFFFFF))); // 8 HEX DIGITS (4,294,967,296 POSSIBLE COMBINATION)
    //         $barcode = $prefix . '-' . $random;
    //         $stmt = $pdo->prepare("SELECT COUNT(*) FROM scooters WHERE barcode = ?");
    //         $stmt->execute([$barcode]);
    //         $exists = $stmt->fetchColumn() > 0;
    //     } while ($exists);

    //     return $barcode;
    // }
}

