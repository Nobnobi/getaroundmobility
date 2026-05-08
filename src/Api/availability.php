<?php
namespace App\Controllers;

use App\Models\ProductModel;
use App\Models\ReservationModel;
// src/Api/availability.php
header('Content-Type: application/json');
require_once __DIR__ . '/../Models/ProductModel.php';
require_once __DIR__ . '/../Models/ReservationModel.php';

$pickup = $_GET['pickup'] ?? null;
$return = $_GET['return'] ?? null;
if (!$pickup || !$return) {
    echo json_encode(['error' => 'Missing dates']);
    exit;
}

$productModel = new ProductModel();
$reservationModel = new ReservationModel();

// Get all products and variations
$products = $productModel->getAllProductsWithVariations(); // You may need to implement this if not present

// Get reservations overlapping the selected dates
$reservations = $reservationModel->getReservationsBetween($pickup, $return); // You may need to implement this

// Calculate available stock for each product/variation
$availability = [];
foreach ($products as $product) {
    $pid = $product['product_id'];
    if (!empty($product['variations'])) {
        foreach ($product['variations'] as $var) {
            $vid = $var['variation_id'];
            $totalStock = $var['stock'];
            $reserved = 0;
            foreach ($reservations as $res) {
                if ($res['product_id'] == $pid && $res['variation_id'] == $vid) {
                    $reserved += $res['qty'];
                }
            }
            $availability[$pid][$vid] = max(0, $totalStock - $reserved);
        }
    } else {
        $totalStock = $product['total_stock'];
        $reserved = 0;
        foreach ($reservations as $res) {
            if ($res['product_id'] == $pid && (empty($res['variation_id']) || $res['variation_id'] === null)) {
                $reserved += $res['qty'];
            }
        }
        $availability[$pid]['null'] = max(0, $totalStock - $reserved);
    }
}

echo json_encode(['availability' => $availability]);
