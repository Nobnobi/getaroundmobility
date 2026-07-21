<?php

namespace App\Services;

class OrderTotalsService
{
    public function calculateFromSubtotal(float $subtotal, float $discountAmount = 0.0, float $securityDeposit = 100.0, float $deliveryFee = 0.0): array
    {
        $subtotal = round(max(0, $subtotal), 2);
        $discountAmount = round(max(0, $discountAmount), 2);
        $securityDeposit = round(max(0, $securityDeposit), 2);
        $deliveryFee = round(max(0, $deliveryFee), 2);

        $productTotalWithTax = round(max(0, $subtotal - $discountAmount), 2);
        $productPreTax = round($productTotalWithTax / 1.08375, 2);
        $tax = round(max(0, $productTotalWithTax - $productPreTax), 2);
        $totalAmountWithTax = round($productTotalWithTax + $securityDeposit + $deliveryFee, 2);
        $totalAmount = round($productPreTax + $securityDeposit + $deliveryFee, 2);

        return [
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'security_deposit' => $securityDeposit,
            'delivery_fee' => $deliveryFee,
            'product_total_with_tax' => $productTotalWithTax,
            'product_pre_tax' => $productPreTax,
            'tax' => $tax,
            'total_amount_with_tax' => $totalAmountWithTax,
            'total_amount' => $totalAmount,
        ];
    }
}
