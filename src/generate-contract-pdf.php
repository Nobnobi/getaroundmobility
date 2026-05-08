<?php
// filepath: c:\xampp\htdocs\GetAroundMobility\src\generate-contract-pdf.php

require __DIR__ . '/../vendor/autoload.php';
use Dompdf\Dompdf;

// 1. Get order ID from URL
$orderId = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
if (!$orderId) {
    die("Order ID is required.");
}

// 2. Fetch booking and user details from DB
$pdo = new PDO('mysql:host=localhost;dbname=getaround_db', 'getaroundmobility', 'itup420');
$stmt = $pdo->prepare("
    SELECT o.*, u.name AS customerName, u.email AS customerEmail, u.phone AS customerPhone, u.address AS customerAddress
    FROM orders o
    JOIN users u ON o.user_id = u.user_id
    WHERE o.order_id = ?
");
$stmt->execute([$orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Order not found.");
}

// 3. Prepare variables for the template
$customerName = $order['customerName'];
$customerEmail = $order['customerEmail'];
$customerPhone = $order['customerPhone'];
$customerAddress = $order['customerAddress'];
$pickupDate = $order['pickup_date'];
$returnDate = $order['return_date'];
$items = $order['items'] ?? 'N/A'; // Adjust as needed
$total = $order['total'] ?? '0.00';

// 4. Capture the contract HTML as a string
ob_start();
include __DIR__ . '/Contracts/contract-template.php';
$html = ob_get_clean();

// 5. Create and render PDF
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// 6. Output the PDF to browser
$dompdf->stream("rental-contract-{$orderId}.pdf", ["Attachment" => false]);
exit;