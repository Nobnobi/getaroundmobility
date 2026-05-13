<?php
namespace App\Controllers;
use App\Controller;
use App\Models\ProductModel;
use App\Models\RentalPriceModel;

class RentalPriceController extends Controller {
    private function ensureAdminSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['admin_id'])) {
            header('Location: /admin/login');
            exit;
        }
    }

    private function ensureManagePermission(): void {
        $role = strtolower($_SESSION['admin_role'] ?? '');
        if (!in_array($role, ['admin', 'superadmin'], true)) {
            header('Location: /admin/rental-prices');
            exit;
        }
    }

    public function index() {
        $this->ensureAdminSession();

        $productModel = new ProductModel();
        $rentalPriceModel = new RentalPriceModel();
        $products = $productModel->getAllProducts();
        $variations = $productModel->getAllVariationsGrouped();
        $rentalPrices = $rentalPriceModel->getAllRentalPricesGrouped();
        $this->renderAdmin('admin/rental-prices', [
            'products' => $products,
            'variations' => $variations,
            'rentalPrices' => $rentalPrices
        ]);
    }

    public function save() {
        $this->ensureAdminSession();
        $this->ensureManagePermission();

        // Handle POST data to update rental prices
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $rentalPriceModel = new RentalPriceModel();
            $days = $_POST['days'] ?? [];
            $prices = $_POST['price'] ?? [];
            $rentalPriceModel->saveRentalPrices($days, $prices);
            header('Location: /admin/rental-prices');
            exit;
        }
    }
}
