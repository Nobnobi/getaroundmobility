<?php
$contractTemplateDebugFile = fopen(__DIR__ . '/../public/order-debug-log.txt', 'a');
fwrite($contractTemplateDebugFile, date('Y-m-d H:i:s') . "\n[DEBUG] contract-template.php included and running\n");
fclose($contractTemplateDebugFile);

$customerName = $customerName ?? '';
$pickupDate = $pickupDate ?? '';
$returnDate = $returnDate ?? '';
$totalAmountWithTax = $totalAmountWithTax ?? 0;
$itemsTable = $itemsTable ?? '';
?>
<!-- filepath: c:\xampp\htdocs\GetAroundMobility\src\Contracts\contract-template.php -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Rental Contract</title>
    <link href="/css/output.css" rel="stylesheet">
</head>
<body class="font-sans bg-white text-gray-900 p-8">
    <h1 class="text-3xl font-bold text-center uppercase underline mb-8">Rental Contract</h1>

    <p class="mb-6">
        This Rental Contract (the <strong>“Agreement”</strong>) is made and entered into on 
        <span class="border-b border-black px-2"><?= date('F j, Y') ?></span> 
        (the <strong>“Effective Date”</strong>), by and between 
        <span class="border-b border-black px-2">Get Around Mobility</span> 
        (hereinafter referred to as the <strong>“Provider”</strong>), 
        and <span class="border-b border-black px-2"><?= htmlspecialchars($customerName) ?></span>, 
        (hereinafter referred to as the <strong>“Renter”</strong>), 
        collectively referred to as the <strong>“Parties”</strong>.
    </p>

    <div class="mb-6">
        <h3 class="font-semibold text-lg mb-2">1. Vehicle Description</h3>
        The Provider hereby agrees to rent to the Renter the following equipment:
        <div class="mt-2">
            <?= $itemsTable ?>
        </div>
    </div>

    <div class="mb-6">
        <h3 class="font-semibold text-lg mb-2">2. Rental Term</h3>
        This rental shall begin on 
        <span class="border-b border-black px-2"><?= htmlspecialchars($pickupDate) ?></span> 
        and shall continue until 
        <span class="border-b border-black px-2"><?= htmlspecialchars($returnDate) ?></span>, 
        unless extended by mutual agreement in writing.
    </div>

    <div class="mb-6">
        <h3 class="font-semibold text-lg mb-2">3. Rental Payments</h3>
        The Renter agrees to pay a total rental fee of 
        <span class="border-b border-black px-2">$<?= htmlspecialchars(number_format($totalAmountWithTax, 2)) ?></span> 
        (including tax).  
        Payment shall be made on or before the pickup date.  
        Late returns may incur a fee of <span class="border-b border-black px-2">$20</span> per day.
    </div>

    <div class="mb-6">
        <h3 class="font-semibold text-lg mb-2">4. Security Deposit</h3>
        A refundable security deposit of 
        <span class="border-b border-black px-2">$100</span> shall be paid by the Renter upon pickup.  
        The deposit may be used to cover damages, loss, or unpaid fees.  
        The deposit will be returned within 7 days after the scooter is returned, 
        less any deductions for damages or late fees.
    </div>

    <div class="mb-6">
        <h3 class="font-semibold text-lg mb-2">5. Responsibilities</h3>
        The Renter agrees to operate the scooter safely and in compliance with traffic laws.  
        The Renter is responsible for any damages or violations during the rental period.  
        The Provider is not liable for accidents, injuries, or third-party damages.
    </div>

    <div class="mb-6">
        <h3 class="font-semibold text-lg mb-2">6. Termination</h3>
        This Agreement may be terminated early if the Renter fails to comply with the terms.  
        In such cases, the Provider reserves the right to reclaim the scooter immediately.
    </div>

    <div class="mb-10">
        <p>
            By signing below, both Parties agree to the terms and conditions outlined in this Agreement.
        </p>
        <div class="flex flex-col md:flex-row justify-between mt-10 gap-10">
            <div>
                <div class="h-10 border-b-2 border-gray-700 w-64"></div>
                <div class="font-semibold mt-2">Provider: Get Around Mobility</div>
            </div>
            <div>
                <div class="h-10 border-b-2 border-gray-700 w-64"></div>
                <div class="font-semibold mt-2">Renter: <?= htmlspecialchars($customerName) ?></div>
            </div>
        </div>
    </div>
</body>
</html>
