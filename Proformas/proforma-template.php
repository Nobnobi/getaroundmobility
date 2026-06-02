<?php
$documentTitle = $documentTitle ?? 'PRO-FORMA INVOICE';
$documentDisclaimer = $documentDisclaimer ?? 'This is not a final tax invoice. Final settlement invoice is issued after order completion.';
$footerLine1 = $footerLine1 ?? 'This pro-forma shows estimated/initial charges including refundable security deposit handling.';
$footerLine2 = $footerLine2 ?? 'Final invoice is issued after completion and any deposit settlement adjustments.';
$footerLine3 = $footerLine3 ?? 'Thank you for booking with Get Around Mobility!';

include __DIR__ . '/../Invoices/invoice-template.php';
