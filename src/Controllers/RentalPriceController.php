<?php
namespace App\Controllers;
use App\Controller;
use App\Models\ProductModel;
use App\Models\RentalPriceModel;

class RentalPriceController extends Controller {
    public function index() {
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
