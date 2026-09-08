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
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
                http_response_code(403);
                die('Invalid CSRF token');
            }

            $rentalPriceModel = new RentalPriceModel();
            $productModel = new ProductModel();
            $days = $_POST['days'] ?? [];
            $prices = $_POST['price'] ?? [];

            $applyToAllVariations = isset($_POST['apply_all_variations'])
                && in_array(strtolower(trim((string)$_POST['apply_all_variations'])), ['1', 'true', 'on', 'yes'], true);
            $postedProductId = isset($_POST['product_id']) && is_numeric($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
            $sourceVariationId = isset($_POST['source_variation_id'])
                ? trim((string)$_POST['source_variation_id'])
                : null;

            if ($applyToAllVariations && $postedProductId > 0 && isset($days[$postedProductId]) && isset($prices[$postedProductId])) {
                $sourceKey = ($sourceVariationId === null || $sourceVariationId === '' || strtolower($sourceVariationId) === 'null')
                    ? 'null'
                    : (string)$sourceVariationId;

                $sourceDays = $days[$postedProductId][$sourceKey] ?? null;
                $sourcePrices = $prices[$postedProductId][$sourceKey] ?? null;
                if (is_array($sourceDays) && is_array($sourcePrices)) {
                    $allVariations = $productModel->getAllVariationsGrouped();
                    $variationRows = $allVariations[$postedProductId] ?? [];

                    if (!empty($variationRows)) {
                        foreach ($variationRows as $variationRow) {
                            $targetKey = (string)($variationRow['variation_id'] ?? '');
                            if ($targetKey === '') {
                                continue;
                            }
                            $days[$postedProductId][$targetKey] = $sourceDays;
                            $prices[$postedProductId][$targetKey] = $sourcePrices;
                        }
                    } else {
                        // Product without variations: treat base product as target.
                        $days[$postedProductId]['null'] = $sourceDays;
                        $prices[$postedProductId]['null'] = $sourcePrices;
                    }
                }
            }

            $rentalPriceModel->saveRentalPrices($days, $prices);
            $groupCount = 0;
            $tierCount = 0;
            foreach ($days as $variationRows) {
                if (!is_array($variationRows)) {
                    continue;
                }
                foreach ($variationRows as $dayArr) {
                    if (!is_array($dayArr)) {
                        continue;
                    }
                    $groupCount++;
                    foreach ($dayArr as $day) {
                        if ($day !== null && trim((string)$day) !== '') {
                            $tierCount++;
                        }
                    }
                }
            }
            $this->logAdminAction('rental_prices_saved', 'rental_price', null, [
                'product_variation_groups' => $groupCount,
                'tier_count' => $tierCount,
            ]);
            header('Location: /admin/rental-prices');
            exit;
        }
    }
}
