<?php
// Keep caller-provided values; only apply safe defaults when missing.
$logoSrc = $logoSrc ?? '';
$orderId = $orderId ?? '';
$pickup_datetime = $pickup_datetime ?? '';
$return_datetime = $return_datetime ?? '';
$customerName = $customerName ?? '';
$customerAddress = $customerAddress ?? '';
$customerEmail = $customerEmail ?? '';
$itemsTable = $itemsTable ?? '';
$totalAmount = $totalAmount ?? 0;

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice</title>
    <link href="/css/output.css" rel="stylesheet">
    <style>
        body { font-family: sans-serif; }
        .border { border: 1px solid #e5e7eb; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .mb-2 { margin-bottom: 0.5rem; }
        .mb-4 { margin-bottom: 1rem; }
        .p-2 { padding: 0.5rem; }
        .p-4 { padding: 1rem; }
    </style>
</head>
<body class="p-8">
    <div class="flex justify-between items-start mb-4">
        <div>
            <?php if ($logoSrc): ?>
                <img src="<?= $logoSrc ?>" alt="Get Around Mobility" style="height:48px;object-fit:contain;margin-bottom:.5rem;">
            <?php else: ?>
                <div style="font-weight:700; font-size:18px; margin-bottom:.5rem;">GetAroundMobility</div>
            <?php endif; ?>
            <!-- optional title retained for clients that can't load images -->
            <div style="font-weight:700; font-size:16px;">Get Around Mobility</div>
            <div>Get Around Mobility</div>
            <div>3170 Polaris Ave</div>
            <div>Suite #25</div>
            <div>Las Vegas Nevada 89102</div>
            <div>United States</div>
            <div>(702) 637-008</div>
            <div>gio@getaroundmobility.com</div>
        </div>
        <div class="text-right">
            <h2 class="text-xl font-bold mb-2">Rental Invoice</h2>
            <div><span class="font-bold">Order no.</span> #<?= $orderId ?></div>
            <div><span class="font-bold">Pickup Date</span> <?= htmlspecialchars($pickup_datetime) ?></div>
            <div><span class="font-bold">Return Date</span> <?= htmlspecialchars($return_datetime) ?></div>
        </div>
    </div>
    <div class="mb-4">
        <div>Mobility Scooter Rentals Sales Service in the Las Vegas area</div>
    </div>
    <div class="mb-4">
        <div><span class="font-bold">Customer:</span> <?= htmlspecialchars($customerName) ?></div>
        <div><span class="font-bold">Address:</span> <?= htmlspecialchars($customerAddress) ?></div>
        <div><span class="font-bold">Email:</span> <?= htmlspecialchars($customerEmail) ?></div>
    </div>
    <table class="w-full border mb-4 text-sm">
        <tbody>
            <?= $itemsTable ?>
        </tbody>
    </table>
    <div class="text-right mb-2">
        <div>Subtotal: $<?= number_format($totalAmount, 2) ?></div>
    </div>
    <div class="mt-8 text-center text-xs text-gray-500">
        USED ITEMS SOLD AS IS/ALL SALES ARE FINAL FOR ITEMS PURCHASED (RENTALS NOT INCLUDED)
        <br><br>
        Get Around Mobility Terms And Conditions - Get Around Mobility Online Retail Agreement
        <br>
        Thank you for your business!
        <br>
        getaroundmobility.com
    </div>
    <div class="text-center text-xs text-gray-500 mt-4">
        ©GetAroundMobility <?= date('Y') ?>
    </div>
</body>
</html>