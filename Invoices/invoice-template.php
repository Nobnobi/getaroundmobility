<?php
// Keep caller-provided values; only apply safe defaults when missing.
$logoSrc = $logoSrc ?? '';
$inlineLogoSvg = '';

if ($logoSrc === '') {
    $hasGd = extension_loaded('gd');
    $logoCandidates = $hasGd
        ? [
            __DIR__ . '/../public/img/logo-fallback.jpg' => 'image/jpeg',
            __DIR__ . '/../public/img/Original logo.png' => 'image/png',
            __DIR__ . '/../public/img/Original logo.jpg' => 'image/jpeg',
            __DIR__ . '/../public/img/Original logo.jpeg' => 'image/jpeg',
            __DIR__ . '/../public/img/Original logo.svg' => 'image/svg+xml',
        ]
        : [
            // JPEG is supported without GD in Dompdf's CPDF backend.
            __DIR__ . '/../public/img/logo-fallback.jpg' => 'image/jpeg',
            __DIR__ . '/../public/img/Original logo.jpg' => 'image/jpeg',
            __DIR__ . '/../public/img/Original logo.jpeg' => 'image/jpeg',
            // Fallback to inline SVG if JPEG is unavailable.
            __DIR__ . '/../public/img/Original logo.svg' => 'image/svg+xml',
        ];

    foreach ($logoCandidates as $logoPath => $fallbackMime) {
        if (!is_file($logoPath) || !is_readable($logoPath)) {
            continue;
        }

        $data = @file_get_contents($logoPath);
        if ($data === false || $data === '') {
            continue;
        }

        $mime = function_exists('mime_content_type') ? (mime_content_type($logoPath) ?: $fallbackMime) : $fallbackMime;
        $isSvg = stripos((string)$mime, 'svg') !== false || strtolower((string)$fallbackMime) === 'image/svg+xml';
        if (!$hasGd && $isSvg) {
            // Normalize common legacy SVG attribute for better renderer compatibility.
            $inlineLogoSvg = str_replace('xlink:href', 'href', $data);
            break;
        }

        $logoSrc = 'data:' . $mime . ';base64,' . base64_encode($data);
        break;
    }
}

$orderId = $orderId ?? ($order_id ?? '');
$orderDate = $orderDate ?? date('Y-m-d H:i:s');
$pickup_datetime = $pickup_datetime ?? ($pickupDate ?? '');
$return_datetime = $return_datetime ?? ($returnDate ?? '');
$customerName = $customerName ?? ($finalName ?? '');
$customerAddress = $customerAddress ?? '';
$customerEmail = $customerEmail ?? ($finalEmail ?? '');
$customerPhone = $customerPhone ?? '';
$pickupLocation = $pickupLocation ?? ($pickup_location ?? '');
$paymentMethod = $paymentMethod ?? ($payment_method ?? '');
$deliveryType = $deliveryType ?? ($delivery_type ?? '');
$promoCode = $promoCode ?? ($promo_code ?? '');
$itemsTable = $itemsTable ?? '';

if (stripos((string)$itemsTable, '<table') !== false) {
    $extractedRows = '';
    if (preg_match('/<tbody[^>]*>(.*?)<\/tbody>/is', (string)$itemsTable, $matches)) {
        $extractedRows = (string)$matches[1];
    } else {
        $extractedRows = preg_replace('/<\/?table[^>]*>/i', '', (string)$itemsTable);
    }
    $itemsTable = trim($extractedRows);
}

$lineSubtotal = isset($subtotal) ? (float)$subtotal : (isset($cartSubtotal) ? (float)$cartSubtotal : (float)($totalAmount ?? 0));
$discountAmount = isset($discountAmount)
    ? (float)$discountAmount
    : (isset($promo_discount) ? (float)$promo_discount : 0.0);

$productTotalWithTax = round(max(0, $lineSubtotal - $discountAmount), 2);

$securityDeposit = isset($securityDeposit)
    ? (float)$securityDeposit
    : (isset($security_deposit) ? (float)$security_deposit : 0.0);

if ($securityDeposit <= 0 && isset($totalAmountWithTax)) {
    $securityDeposit = round(max(0, (float)$totalAmountWithTax - $productTotalWithTax), 2);
}

$productPreTaxSubtotal = round($productTotalWithTax / 1.08375, 2);
$tax = isset($tax) ? (float)$tax : round(max(0, $productTotalWithTax - $productPreTaxSubtotal), 2);
$totalAmount = isset($totalAmount) ? (float)$totalAmount : round($productPreTaxSubtotal + $securityDeposit, 2);
$totalAmountWithTax = isset($totalAmountWithTax) ? (float)$totalAmountWithTax : round($productTotalWithTax + $securityDeposit, 2);

if ($lineSubtotal <= 0) {
    // If line subtotal was not provided, infer it from amount fields.
    $lineSubtotal = round(max(0, $totalAmount + $discountAmount), 2);
}

$preTaxSubtotal = round(max(0, $productPreTaxSubtotal), 2);
$grandTotal = round(max(0, $totalAmountWithTax), 2);

$esc = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};

$fmtMoney = static function ($value): string {
    return '$' . number_format((float)$value, 2);
};

$fmtDate = static function ($value): string {
    if (!$value) {
        return '';
    }
    $ts = strtotime((string)$value);
    return $ts ? date('M d, Y h:i A', $ts) : (string)$value;
};

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice</title>
    <link href="/css/output.css" rel="stylesheet">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 12px; }
        .container { width: 100%; }
        .header { width: 100%; border-bottom: 2px solid #0f172a; padding-bottom: 12px; margin-bottom: 16px; }
        .left { width: 60%; float: left; }
        .right { width: 38%; float: right; text-align: right; }
        .muted { color: #6b7280; }
        .label { font-weight: 700; color: #374151; }
        .invoice-title { font-size: 26px; font-weight: 700; letter-spacing: 1px; margin: 0 0 8px 0; }
        .section { margin-bottom: 16px; }
        .meta-box { width: 100%; border: 1px solid #d1d5db; border-collapse: collapse; }
        .meta-box td { border: 1px solid #e5e7eb; padding: 8px; vertical-align: top; }
        .items { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .items th { background: #f3f4f6; color: #111827; font-weight: 700; border: 1px solid #d1d5db; padding: 8px; text-align: left; }
        .items td { border: 1px solid #e5e7eb; padding: 8px; }
        .summary-wrap { width: 100%; margin-top: 14px; }
        .summary { width: 46%; margin-left: auto; border-collapse: collapse; }
        .summary td { padding: 6px 8px; }
        .summary .line td { border-bottom: 1px solid #e5e7eb; }
        .summary .grand td { font-weight: 700; font-size: 13px; border-top: 2px solid #111827; padding-top: 8px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .footer { margin-top: 26px; font-size: 10px; color: #6b7280; line-height: 1.45; }
        .clearfix { clear: both; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="left">
            <?php if ($logoSrc): ?>
                <img src="<?= $logoSrc ?>" style="height:48px;object-fit:contain;margin-bottom:.5rem;">
            <?php elseif ($inlineLogoSvg): ?>
                <div style="height:48px; margin-bottom:.5rem; display:inline-block;"><?= $inlineLogoSvg ?></div>
            <?php else: ?>
                <div style="font-weight:700; font-size:18px; margin-bottom:.5rem;">GetAroundMobility</div>
            <?php endif; ?>
                <div style="font-weight:700; font-size:16px; margin-bottom:4px;">Get Around Mobility</div>
                <div>3170 Polaris Ave, Suite #25</div>
                <div>Las Vegas, Nevada 89102, United States</div>
                <div>(702) 637-008 | gio@getaroundmobility.com</div>
            </div>
            <div class="right">
                <div class="invoice-title">INVOICE</div>
                <div><span class="label">Order #:</span> <?= $esc($orderId) ?></div>
                <div><span class="label">Issued:</span> <?= $esc($fmtDate($orderDate)) ?></div>
                <div><span class="label">Pickup:</span> <?= $esc($fmtDate($pickup_datetime)) ?></div>
                <div><span class="label">Return:</span> <?= $esc($fmtDate($return_datetime)) ?></div>
            </div>
            <div class="clearfix"></div>
        </div>

        <div class="section">
            <table class="meta-box">
                <tr>
                    <td style="width: 55%;">
                        <div class="label" style="margin-bottom: 4px;">Bill To</div>
                        <div><?= $esc($customerName) ?></div>
                        <?php if (!empty($customerAddress)): ?><div><?= $esc($customerAddress) ?></div><?php endif; ?>
                        <?php if (!empty($customerEmail)): ?><div><?= $esc($customerEmail) ?></div><?php endif; ?>
                        <?php if (!empty($customerPhone)): ?><div><?= $esc($customerPhone) ?></div><?php endif; ?>
                    </td>
                    <td style="width: 45%;">
                        <?php if (!empty($pickupLocation)): ?><div><span class="label">Pickup Location:</span> <?= $esc($pickupLocation) ?></div><?php endif; ?>
                        <?php if (!empty($deliveryType)): ?><div><span class="label">Delivery Type:</span> <?= $esc($deliveryType) ?></div><?php endif; ?>
                        <?php if (!empty($paymentMethod)): ?><div><span class="label">Payment Method:</span> <?= $esc(ucfirst((string)$paymentMethod)) ?></div><?php endif; ?>
                        <?php if (!empty($promoCode)): ?><div><span class="label">Promo Code:</span> <?= $esc($promoCode) ?></div><?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>

        <div class="section">
            <table class="items">
                <thead>
                    <tr>
                        <th style="width: 12%;">Qty</th>
                        <th style="width: 48%;">Description</th>
                        <th style="width: 20%;" class="text-right">Unit Price</th>
                        <th style="width: 20%;" class="text-right">Line Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?= $itemsTable ?>
                </tbody>
            </table>
        </div>

        <div class="summary-wrap">
            <table class="summary">
                <tr class="line">
                    <td>Items Subtotal</td>
                    <td class="text-right"><?= $fmtMoney($lineSubtotal) ?></td>
                </tr>
                <tr class="line">
                    <td>Discount<?= !empty($promoCode) ? ' (' . $esc($promoCode) . ')' : '' ?></td>
                    <td class="text-right">-<?= $fmtMoney($discountAmount) ?></td>
                </tr>
                <tr class="line">
                    <td>Subtotal (Pre-Tax)</td>
                    <td class="text-right"><?= $fmtMoney($preTaxSubtotal) ?></td>
                </tr>
                <?php if ($securityDeposit > 0): ?>
                <tr class="line">
                    <td>Refundable Security Deposit</td>
                    <td class="text-right"><?= $fmtMoney($securityDeposit) ?></td>
                </tr>
                <?php endif; ?>
                <tr class="line">
                    <td>Included NV Sales Tax</td>
                    <td class="text-right"><?= $fmtMoney($tax) ?></td>
                </tr>
                <tr class="grand">
                    <td>Total</td>
                    <td class="text-right"><?= $fmtMoney($grandTotal) ?></td>
                </tr>
            </table>
        </div>

        <div class="footer text-center">
            USED ITEMS SOLD AS IS/ALL SALES ARE FINAL FOR ITEMS PURCHASED (RENTALS NOT INCLUDED)<br><br>
            Get Around Mobility Terms and Conditions - Get Around Mobility Online Retail Agreement<br>
            Thank you for your business!<br>
            getaroundmobility.com
        </div>
        <div class="text-center footer">
            ©GetAroundMobility <?= date('Y') ?>
        </div>
    </div>
</body>
</html>