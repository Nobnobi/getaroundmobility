
<?php
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$rentalPrices  = $rentalPrices ?? [];
$activePromos  = $activePromos ?? [];
$formAction = $formAction ?? '/admin/orders/new';
$availabilityEndpoint = $availabilityEndpoint ?? '/admin/orders/availability';
$cancelUrl = $cancelUrl ?? '/admin/orders';
$cancelLabel = $cancelLabel ?? 'Cancel';
$kioskMode = !empty($kioskMode);
$adminRoleRaw = $_SESSION['admin_role'] ?? '';
$adminRoleKey = strtolower(str_replace(['_', '-', ' '], '', (string)$adminRoleRaw));
$canEditFinalPrice = isset($canEditFinalPrice)
    ? (bool)$canEditFinalPrice
    : ($adminRoleKey === 'superadmin');

$outerWrapClass = $kioskMode
    ? 'flex flex-1 items-start justify-center w-full px-4 py-6 md:px-8 md:py-10'
    : 'flex flex-1 items-center justify-center w-full';
$panelClass = $kioskMode
    ? 'w-full max-w-6xl mx-auto'
    : 'rounded-2xl p-10 w-full max-w-2xl mx-auto';
?>

    <div id="booking-loading-overlay" class="fixed inset-0 z-50 hidden items-center justify-center bg-[#062B41]/70 px-6">
        <div class="w-full max-w-sm rounded-2xl bg-white px-6 py-7 text-center shadow-2xl">
            <div class="mx-auto h-12 w-12 animate-spin rounded-full border-4 border-gray-200 border-t-[#0086C9]"></div>
            <h2 class="mt-4 text-xl font-bold text-[#062B41]">Creating booking</h2>
            <p class="mt-2 text-sm text-gray-500">Please wait while the order, stock assignment, and pricing are being saved.</p>
        </div>
    </div>

    <div class="<?= $outerWrapClass ?>">
        <div class="<?= $panelClass ?>">
            <div class="mb-5">
                <h1 class="text-4xl font-bold text-[#0086C9] font-[Barlow]">Walk-in Booking</h1>
                <div class="mt-2 h-px w-full bg-[#b8c4d1]"></div>
            </div>
            <?php if (!empty($_SESSION['form_errors'])): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-3 rounded mb-6">
                    <?php foreach ($_SESSION['form_errors'] as $err): ?>
                        <div><?= htmlspecialchars($err) ?></div>
                    <?php endforeach; unset($_SESSION['form_errors']); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($_SESSION['booking_success'])): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-3 rounded mb-6">
                    <?= htmlspecialchars($_SESSION['booking_success']) ?>
                </div>
                <?php unset($_SESSION['booking_success']); ?>
            <?php endif; ?>
            <form id="walkin-booking-form" method="post" action="<?= htmlspecialchars($formAction) ?>" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="cart" id="cart-json">
                <input type="hidden" name="promo_code" id="promo-code-input" value="">
                <?php if ($kioskMode): ?>
                    <input type="hidden" name="booking_context" value="kiosk">
                <?php endif; ?>

                <div class="space-y-6">
                    <section class="p-0">
                        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">First Name <span class="text-red-500">*</span></label>
                                <input type="text" name="guest_first_name" required class="w-full rounded-lg border border-[#c9d1dc] bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-[#0086C9] focus:outline-none focus:ring-2 focus:ring-[#0086C9]/20" placeholder="Enter First Name">
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Last Name <span class="text-red-500">*</span></label>
                                <input type="text" name="guest_last_name" required class="w-full rounded-lg border border-[#c9d1dc] bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-[#0086C9] focus:outline-none focus:ring-2 focus:ring-[#0086C9]/20" placeholder="Enter Last Name">
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Email <span class="text-red-500">*</span></label>
                                <input type="email" name="email" required class="w-full rounded-lg border border-[#c9d1dc] bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-[#0086C9] focus:outline-none focus:ring-2 focus:ring-[#0086C9]/20" placeholder="youremail@email.com">
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Phone <span class="text-red-500">*</span></label>
                                <input type="text" name="phone" required class="w-full rounded-lg border border-[#c9d1dc] bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-[#0086C9] focus:outline-none focus:ring-2 focus:ring-[#0086C9]/20" placeholder="Enter Phone Number">
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Sale Type <span class="text-red-500">*</span></label>
                                <select name="sale_type" class="w-full rounded-lg border border-[#c9d1dc] bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-[#0086C9] focus:outline-none focus:ring-2 focus:ring-[#0086C9]/20">
                                    <option value="rental">Rental</option>
                                    <option value="sale">Sale</option>
                                </select>
                                <p id="sale-type-helper" class="mt-2 text-xs text-slate-500">Showing products marked for rental bookings.</p>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Client Weight (lbs) <span class="text-red-500">*</span></label>
                                <input type="number" name="client_weight_option" id="clientWeightRange" min="1" step="1" inputmode="numeric" required class="w-full rounded-lg border border-[#c9d1dc] bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-[#0086C9] focus:outline-none focus:ring-2 focus:ring-[#0086C9]/20" placeholder="e.g. 160">
                            </div>
                            <div class="md:col-span-2">
                                <label class="mb-1 block text-sm font-medium text-slate-700">Notes</label>
                                <textarea name="notes" rows="4" class="w-full rounded-lg border border-[#c9d1dc] bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-[#0086C9] focus:outline-none focus:ring-2 focus:ring-[#0086C9]/20" placeholder="Enter any additional notes or special instructions"></textarea>
                            </div>
                            <div class="md:col-span-1 md:max-w-sm">
                                <label class="mb-1 block text-sm font-medium text-slate-700">Payment Method <span class="text-red-500">*</span></label>
                                <select name="payment_method" class="w-full rounded-lg border border-[#c9d1dc] bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-[#0086C9] focus:outline-none focus:ring-2 focus:ring-[#0086C9]/20">
                                    <option value="cash">Cash</option>
                                    <option value="card">Card</option>
                                </select>
                            </div>
                        </div>
                    </section>

                    <section class="p-0">
                        <div id="rental-window-card" class="rounded-xl border border-[#d8e0ea] bg-white p-4 shadow-none transition-colors duration-200">
                            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                                <div>
                                    <p id="rental-window-eyebrow" class="text-sm font-semibold uppercase tracking-[0.18em] text-[#0086C9]">Rental Window</p>
                                    <h2 id="rental-window-heading" class="mt-1 text-2xl font-bold text-[#1f2937]">Pickup and Return</h2>
                                    <p id="rental-window-copy" class="mt-1 text-sm text-slate-500">Pricing updates automatically from the selected rental duration.</p>
                                </div>
                                <div id="rental-duration-badge" class="inline-flex items-center rounded-full border border-[#9bc9e5] bg-[#e7f4fc] px-3 py-1 text-xs font-semibold text-[#0086C9]">
                                    1 day rental
                                </div>
                            </div>
                            <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <label for="pickupDatetime" class="mb-1 block text-sm font-medium text-slate-700">Pickup date & time <span class="text-red-500">*</span></label>
                                    <input
                                        id="pickupDatetime"
                                        name="pickup_datetime"
                                        type="text"
                                        readonly
                                        required
                                        class="w-full rounded-lg border border-[#c9d1dc] bg-white px-3 py-2.5 text-sm text-slate-700 shadow-sm transition focus:border-[#0086C9] focus:outline-none focus:ring-2 focus:ring-[#0086C9]/20"
                                        placeholder="Select pickup date and time"
                                        autocomplete="off"
                                    >
                                </div>
                                <div>
                                    <label for="returnDatetime" class="mb-1 block text-sm font-medium text-slate-700">Return date & time <span class="text-red-500">*</span></label>
                                    <input
                                        id="returnDatetime"
                                        name="return_datetime"
                                        type="text"
                                        readonly
                                        required
                                        class="w-full rounded-lg border border-[#c9d1dc] bg-white px-3 py-2.5 text-sm text-slate-700 shadow-sm transition focus:border-[#0086C9] focus:outline-none focus:ring-2 focus:ring-[#0086C9]/20"
                                        placeholder="Select return date and time"
                                        autocomplete="off"
                                    >
                                </div>
                            </div>
                            <div class="mt-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                <div id="rental-window-note" class="rounded-xl border border-[#b7d8ee] bg-[#e8f4fd] px-4 py-3 text-sm text-[#0079b6]">
                                    Bookings support up to 31 rental days in this form.
                                </div>
                                <div id="rental-window-summary" class="text-sm font-medium text-slate-500">
                                    Select both dates to calculate tiered rental pricing.
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <section class="mt-2 rounded-2xl border border-[#d8e0ea] bg-white p-5 md:p-6">
                    <div class="mb-4 flex items-center justify-between">
                        <label class="block text-2xl font-bold text-[#1f2937]">Order Items</label>
                        <button type="button" onclick="addProductRow()" class="flex cursor-pointer items-center gap-2 rounded-lg bg-[#0086C9] px-4 py-2 text-sm font-semibold text-white shadow hover:bg-[#0873ab] transition-colors">
                            <span>+</span> Add Product
                        </button>
                    </div>
                    <div class="mb-3 rounded-lg border border-[#c8e2f3] bg-[#edf7ff] px-4 py-2">
                        <p id="order-items-tier-text" class="text-sm font-semibold text-[#0086C9]">Select dates first</p>
                        <p id="order-items-availability-note" class="mt-1 text-xs text-[#0b5f8a]">Only products available for the selected pickup and return time are shown.</p>
                    </div>
                    <div id="products-list" class="space-y-2">
                        <div class="product-row rounded-xl border border-[#d5dde8] bg-transparent p-4 shadow-none transition-shadow hover:shadow-none">
                            <div class="grid grid-cols-1 gap-4 items-end md:grid-cols-2 xl:grid-cols-12">
                                <div class="md:col-span-2 xl:col-span-4">
                                    <label class="mb-2 block text-sm font-medium text-slate-700">Product</label>
                                    <select required class="product-select w-full cursor-pointer rounded-lg border border-[#c9d1dc] bg-white px-3 py-2 text-sm text-slate-700 focus:border-[#0086C9] focus:outline-none focus:ring-2 focus:ring-[#0086C9]/20" onchange="updateProductRow(this)">
                                        <option value="">Select a product</option>
                                        <?php foreach ($products as $product): ?>
                                            <?php 
                                                    $totalStock = $product['scooter_count'];
                                                    $stockStatus = $totalStock > 0 ? 'In Stock' : 'Out of Stock';
                                                    $stockClass = $totalStock > 5 ? 'text-green-600' : ($totalStock > 0 ? 'text-orange-600' : 'text-red-600');
                                            ?>
                                            <option value="<?= $product['product_id'] ?>"
                                                data-price="<?= $product['price'] ?>"
                                                data-img="<?= htmlspecialchars($product['image_url']) ?>"
                                                data-stock="<?= $totalStock ?>"
                                                data-scooter-count="<?= $product['scooter_count'] ?>"
                                                data-product-label="<?= htmlspecialchars($product['product_name']) ?>"
                                                data-sale-type="<?= htmlspecialchars($product['sale_type'] ?? 'rental') ?>"
                                                data-variations='<?= isset($product['variations']) ? json_encode($product['variations']) : "[]" ?>'>
                                                <?= htmlspecialchars($product['product_name']) ?> (<?= $totalStock ?> available)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="md:col-span-1 xl:col-span-2">
                                    <select class="variation-select w-full cursor-pointer rounded-lg border border-[#c9d1dc] bg-white px-3 py-2 text-sm text-slate-700 focus:border-[#0086C9] focus:outline-none focus:ring-2 focus:ring-[#0086C9]/20" style="display:none;">
                                        <option value="">Select variation</option>
                                    </select>
                                </div>
                                <div class="md:col-span-1 xl:col-span-2">
                                    <label class="mb-2 block text-sm font-medium text-slate-700">Qty</label>
                                    <input type="number" min="1" value="1" class="quantity-input w-full cursor-pointer rounded-lg border border-[#c9d1dc] bg-white px-3 py-2 text-center text-sm text-slate-700 focus:border-[#0086C9] focus:outline-none focus:ring-2 focus:ring-[#0086C9]/20" onchange="updateTotal()" max="1">
                                </div>
                                <div class="md:col-span-1 xl:col-span-2">
                                    <label class="mb-2 block text-sm font-medium text-slate-700">Price</label>
                                    <span class="product-price text-lg font-bold text-[#1f2937]">--</span>
                                </div>
                                <div class="md:col-span-1 xl:col-span-2 flex gap-2 xl:justify-end">
                                    <span class="product-stock w-full rounded-lg bg-gray-100 px-3 py-2 text-center text-xs font-semibold text-gray-600" title="Available stock">0 left</span>
                                    <button type="button" onclick="removeProductRow(this)" class="cursor-pointer rounded-lg bg-red-500 px-3 py-2 font-bold text-white transition-colors hover:bg-red-600">×</button>
                                </div>
                            </div>
                            <div class="product-image-section mt-2 hidden rounded-lg bg-[#f8fbff] px-3 py-2">
                                <div class="flex items-center gap-3">
                                    <img src="" alt="Product" class="product-image h-12 w-12 object-contain rounded">
                                    <div>
                                        <p class="product-name text-sm font-semibold text-slate-700"></p>
                                        <p class="product-details text-xs text-slate-500"></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="sale-type-empty-state" class="mt-4 hidden rounded-2xl border border-dashed border-amber-300 bg-amber-50 px-4 py-5 text-sm text-amber-800">
                        No products are currently marked for this booking type.
                    </div>
                </section>

                <section class="p-0">
                    <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                        <div class="w-full md:max-w-xs">
                            <label for="promo-code" class="mb-1 block text-sm font-medium text-slate-700">Promo Code</label>
                            <div class="flex gap-2">
                                <input type="text" id="promo-code" maxlength="24" placeholder="e.g. WELCOME10" class="w-full rounded-lg border border-[#c9d1dc] bg-white px-3 py-2 text-sm uppercase text-slate-700 focus:border-[#0086C9] focus:outline-none focus:ring-2 focus:ring-[#0086C9]/20">
                                <button type="button" id="apply-promo-btn" class="cursor-pointer rounded-lg bg-[#0086C9] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#0873ab]">Apply</button>
                            </div>
                            <p id="promo-feedback" class="mt-2 text-xs text-slate-500" aria-live="polite"></p>
                            
                        </div>
                        <?php if ($canEditFinalPrice): ?>
                            <div class="w-full md:max-w-xs">
                                <label for="final-price-override" class="mb-1 block text-sm font-medium text-slate-700">Final Price Override (Super Admin)</label>
                                <input type="number" id="final-price-override" name="final_price_override" min="0.01" step="0.01" inputmode="decimal" placeholder="Leave blank to use computed total" class="w-full rounded-lg border border-[#c9d1dc] bg-white px-3 py-2 text-sm text-slate-700 focus:border-[#0086C9] focus:outline-none focus:ring-2 focus:ring-[#0086C9]/20">
                                <p id="final-price-override-feedback" class="mt-2 text-xs text-slate-500">If set, this overrides the computed total and is tracked with your superadmin account.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between text-sm text-slate-600">
                            <span>Subtotal</span>
                            <span id="subtotal-amount">$0.00</span>
                        </div>
                        <div class="flex items-center justify-between text-sm text-slate-600">
                            <span>Discount</span>
                            <span id="discount-amount">-$0.00</span>
                        </div>
                        <div class="flex items-center justify-between text-sm text-slate-600">
                            <span>Refundable Security Deposit</span>
                            <span id="security-deposit-amount">$100.00</span>
                        </div>
                        <div class="flex items-center justify-between text-sm text-slate-600">
                            <span>Subtotal (Pre-Tax)</span>
                            <span id="pretax-amount">$0.00</span>
                        </div>
                        <div class="flex items-center justify-between text-sm text-slate-600">
                            <span>Included NV Sales Tax</span>
                            <span id="tax-amount">$0.00</span>
                        </div>
                        <div class="mt-3 flex items-center justify-between border-t border-[#d8e0ea] pt-3">
                            <label class="block font-semibold text-slate-700">Total Amount</label>
                            <span id="total-amount" class="text-2xl font-bold text-[#1f2937]">$0.00</span>
                        </div>
                    </div>
                    <input type="hidden" name="total_amount" id="total-amount-input" value="0">

                    <div class="mt-5 rounded-lg border border-[#d8e0ea] bg-white px-4 py-3">
                        <label class="flex items-start gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="agree_policy" value="1" id="walkinPolicyCheckbox" required class="mt-1 h-4 w-4 rounded border-gray-300 text-[#0086C9] focus:ring-[#0086C9]">
                            <span>
                                I agree to the rental policy and terms.
                                <button type="button" onclick="openPolicyModal()" class="ml-1 text-[#0086C9] underline underline-offset-2 cursor-pointer">View policy</button>
                            </span>
                        </label>
                    </div>
                </section>
                <div class="mt-8">
                    <?php if (!$kioskMode): ?>
                        <div class="mb-3 text-right">
                            <a href="<?= htmlspecialchars($cancelUrl) ?>" class="inline-flex cursor-pointer rounded-lg border border-gray-300 bg-white px-5 py-2 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-100"><?= htmlspecialchars($cancelLabel) ?></a>
                        </div>
                    <?php endif; ?>
                    <button type="submit" class="w-full cursor-pointer rounded-lg border border-[#0086C9] bg-white px-6 py-3 text-sm font-semibold text-[#0086C9] transition-colors hover:bg-[#e8f4fd]">Create Booking</button>
                </div>
            </form>
        </div>
    </div>

    <div id="walkinDateTimeModal" class="fixed inset-0 z-[70] hidden items-center justify-center bg-black/55 px-3 py-4 sm:px-4">
        <div class="w-full max-w-lg max-h-[92vh] overflow-y-auto rounded-[28px] bg-white p-5 shadow-2xl sm:p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#0086C9]">Schedule Rental</p>
                    <h2 id="walkinDateTimeModalTitle" class="mt-1 text-3xl font-bold text-[#062B41]">Select pickup date &amp; time</h2>
                    <p class="mt-2 text-sm text-gray-500">Available hours are 8:30am to 5:30pm only.</p>
                </div>
                <button type="button" id="walkinDateTimeModalClose" class="rounded-full p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700" aria-label="Close date picker modal">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <div class="mt-6 space-y-4">
                <div>
                    <label class="mb-2 block text-sm font-medium text-[#062B41]">Date</label>
                    <input type="text" id="walkinDateTimeModalDate" class="hidden" readonly>
                    <div id="walkinDateTimeModalCalendar" class="rounded-2xl border border-[#c8d9e6] bg-[#f9fcff] p-2 shadow-sm"></div>
                </div>
                <div>
                    <label for="walkinDateTimeModalTime" class="mb-2 block text-sm font-medium text-[#062B41]">Time slot</label>
                    <div class="relative">
                        <select id="walkinDateTimeModalTime" class="w-full appearance-none rounded-2xl border border-[#c8d9e6] bg-[linear-gradient(180deg,#ffffff_0%,#f3f9fe_100%)] px-4 py-3 pr-11 text-base font-semibold text-[#062B41] shadow-sm focus:outline-none focus:ring-2 focus:ring-[#0086C9]"></select>
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-[#0b5f8a]">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </span>
                    </div>
                </div>
                <div id="walkinDateTimeModalInfo" class="rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800"></div>
            </div>

            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button type="button" id="walkinDateTimeModalCancel" class="rounded-xl border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-100">Cancel</button>
                <button type="button" id="walkinDateTimeModalSave" class="rounded-xl bg-[#0086C9] px-5 py-2 text-sm font-semibold text-white transition hover:bg-[#066fa6]">Set date &amp; time</button>
            </div>
        </div>
    </div>

    <div id="policyModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4">
        <div class="w-full max-w-3xl rounded-md border border-gray-400 bg-[#fefbea] shadow-2xl">
            <div class="relative border-b border-gray-400 px-6 py-4 text-center">
                <button type="button" onclick="closePolicyModal()" class="absolute right-4 top-2 text-3xl leading-none text-gray-600 hover:text-black cursor-pointer">&times;</button>
                <h2 class="text-xl font-bold tracking-wide">RENTAL AGREEMENT</h2>
            </div>
            <div class="border-b border-gray-400 bg-gray-300 py-2 text-center font-semibold tracking-wider">TERMS & CONDITIONS</div>
            <div class="max-h-[60vh] overflow-y-auto px-6 py-5 text-[15px] leading-relaxed text-slate-800">
                <p class="mb-3">By renting from Get Around Mobility, you agree to return the scooter in good condition and on time.</p>
                <p class="mb-3">Renter is responsible for loss, theft, and damage while the scooter is in their possession.</p>
                <p class="mb-3">Late returns may incur additional daily rental charges.</p>
                <p class="mb-3">Do not operate while under the influence of alcohol or drugs. Ride safely and follow local laws.</p>
                <p class="mb-3">All disputes are governed by the terms in the signed rental agreement.</p>
                <p class="mb-0">If you do not agree with these terms, do not complete this booking.</p>
            </div>
            <div class="flex justify-center px-6 pb-5">
                <button type="button" onclick="agreeAndClosePolicyModal()" class="cursor-pointer rounded bg-[#0086C9] px-6 py-2 font-bold text-white">I Agree</button>
            </div>
        </div>
    </div>
    
    <script>
window.rentalPrices = <?= json_encode($rentalPrices, JSON_UNESCAPED_SLASHES) ?>;
// DB-loaded active promo rules (for client-side discount preview only; server re-validates)
window.WALKIN_PROMO_RULES = {};
<?php foreach ($activePromos as $p): ?>
window.WALKIN_PROMO_RULES[<?= json_encode($p['code']) ?>] = {type: <?= json_encode($p['type']) ?>, value: <?= (float)$p['value'] ?>};
<?php endforeach; ?>
</script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js" integrity="sha384-5JqMv4L/Xa0hfvtF06qboNdhvuYXUku9ZrhZh3bSk8VXF0A/RuSLHpLsSV9Zqhl6" crossorigin="anonymous"></script>
<script>
const bookingForm = document.getElementById('walkin-booking-form');
const bookingLoadingOverlay = document.getElementById('booking-loading-overlay');
const availabilityEndpoint = <?= json_encode($availabilityEndpoint) ?>;
const saleTypeSelect = bookingForm.querySelector('select[name="sale_type"]');
const clientWeightRangeInput = document.getElementById('clientWeightRange');
const pickupInput = document.getElementById('pickupDatetime');
const returnInput = document.getElementById('returnDatetime');
const rentalDurationBadge = document.getElementById('rental-duration-badge');
const rentalWindowSummary = document.getElementById('rental-window-summary');
const saleTypeHelper = document.getElementById('sale-type-helper');
const rentalWindowCard = document.getElementById('rental-window-card');
const rentalWindowEyebrow = document.getElementById('rental-window-eyebrow');
const rentalWindowHeading = document.getElementById('rental-window-heading');
const rentalWindowCopy = document.getElementById('rental-window-copy');
const saleTypeEmptyState = document.getElementById('sale-type-empty-state');
const promoCodeInput = document.getElementById('promo-code');
const promoCodeHiddenInput = document.getElementById('promo-code-input');
const applyPromoBtn = document.getElementById('apply-promo-btn');
const promoFeedback = document.getElementById('promo-feedback');
const subtotalAmountEl = document.getElementById('subtotal-amount');
const discountAmountEl = document.getElementById('discount-amount');
const securityDepositAmountEl = document.getElementById('security-deposit-amount');
const pretaxAmountEl = document.getElementById('pretax-amount');
const taxAmountEl = document.getElementById('tax-amount');
const orderItemsTierText = document.getElementById('order-items-tier-text');
const walkinPolicyCheckbox = document.getElementById('walkinPolicyCheckbox');
const finalPriceOverrideInput = document.getElementById('final-price-override');
const finalPriceOverrideFeedback = document.getElementById('final-price-override-feedback');

const NV_TAX_INCLUSIVE_FACTOR = 1.08375;
const SECURITY_DEPOSIT = 100;

const WALKIN_PROMO_RULES = window.WALKIN_PROMO_RULES || {};
let activePromoCode = '';

function showBookingLoadingState() {
    if (bookingLoadingOverlay) {
        bookingLoadingOverlay.classList.remove('hidden');
        bookingLoadingOverlay.classList.add('flex');
    }

    bookingForm.setAttribute('aria-busy', 'true');

    const submitButton = bookingForm.querySelector('button[type="submit"]');
    if (submitButton) {
        submitButton.disabled = true;
    }

    bookingForm.querySelectorAll('button[type="button"]').forEach(button => {
        button.disabled = true;
    });
}

function openPolicyModal() {
    const modal = document.getElementById('policyModal');
    if (!modal) return;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closePolicyModal() {
    const modal = document.getElementById('policyModal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function agreeAndClosePolicyModal() {
    if (walkinPolicyCheckbox) {
        walkinPolicyCheckbox.checked = true;
    }
    closePolicyModal();
}

function formatMoney(value) {
    return `$${Number(value || 0).toFixed(2)}`;
}

function getManualOverrideAmount() {
    if (!finalPriceOverrideInput) {
        return null;
    }

    const raw = String(finalPriceOverrideInput.value || '').trim();
    if (raw === '') {
        return null;
    }

    const normalized = raw.replace(/[$,\s]/g, '');
    const numericValue = Number(normalized);
    if (!Number.isFinite(numericValue) || numericValue <= 0) {
        return null;
    }

    return Number(numericValue.toFixed(2));
}

function getComputedWalkinTotal() {
    let subtotal = 0;
    document.querySelectorAll('.product-row').forEach(row => {
        const select = row.querySelector('.product-select');
        const qtyInput = row.querySelector('.quantity-input');
        const selectedOption = select.options[select.selectedIndex];
        if (!selectedOption.value) return;

        const quantity = parseInt(qtyInput.value) || 0;
        const price = Number(row.dataset.effectivePrice || getEffectiveRowPrice(row));
        subtotal += price * quantity;
    });

    const discount = calculatePromoDiscount(subtotal);
    const productTotalWithTax = Math.max(0, subtotal - discount);
    return Number((productTotalWithTax + SECURITY_DEPOSIT).toFixed(2));
}

function updateOrderItemsTierText() {
    if (!orderItemsTierText) return;
    if (getSelectedMode() === 'sale') {
        orderItemsTierText.textContent = 'Sale booking pricing';
        return;
    }
    const days = getRentalDays();
    orderItemsTierText.textContent = `Rental tier for ${days} day${days > 1 ? 's' : ''}`;
}

function parseAdminDate(value) {
    if (!value) {
        return null;
    }
    const normalized = String(value).replace(' ', 'T');
    const date = new Date(normalized);
    return Number.isNaN(date.getTime()) ? null : date;
}

function getNearest15Min() {
    const now = new Date();
    now.setSeconds(0, 0);
    const remainder = now.getMinutes() % 15;
    if (remainder !== 0) {
        now.setMinutes(now.getMinutes() + (15 - remainder));
    }
    return now;
}

function toStartOfDay(date) {
    const normalized = new Date(date);
    normalized.setHours(0, 0, 0, 0);
    return normalized;
}

function getMaxReturnDate(pickupDate) {
    const maxReturnDate = new Date(pickupDate);
    maxReturnDate.setDate(maxReturnDate.getDate() + 31);
    return maxReturnDate;
}

function getRentalDays() {
    const pickupDate = parseAdminDate(pickupInput.value);
    const returnDate = parseAdminDate(returnInput.value);
    if (!pickupDate || !returnDate) {
        return 1;
    }

    const diffMs = returnDate.getTime() - pickupDate.getTime();
    if (diffMs <= 0) {
        return 1;
    }

    return Math.max(1, Math.ceil(diffMs / (1000 * 60 * 60 * 24)));
}

function getTieredPrice(productId, variationId, days) {
    if (!window.rentalPrices || !window.rentalPrices[productId] || !window.rentalPrices[productId][variationId]) {
        return null;
    }

    const tiers = window.rentalPrices[productId][variationId];
    const normalizedDays = Math.min(Math.max(parseInt(days, 10) || 1, 1), 31);
    const key = String(normalizedDays);
    if (!Object.prototype.hasOwnProperty.call(tiers, key)) {
        return null;
    }

    return Number(tiers[key]);
}

function getSelectedMode() {
    return saleTypeSelect.value === 'sale' ? 'sale' : 'rental';
}

function resetProductRow(row) {
    const select = row.querySelector('.product-select');
    const variationSelect = row.querySelector('.variation-select');
    const qtyInput = row.querySelector('.quantity-input');
    const stockSpan = row.querySelector('.product-stock');

    select.value = '';
    variationSelect.innerHTML = '<option value="">Select variation</option>';
    variationSelect.style.display = 'none';
    qtyInput.value = 1;
    qtyInput.disabled = false;
    qtyInput.max = 1;
    stockSpan.textContent = '0 left';
    stockSpan.className = 'product-stock text-xs font-semibold px-3 py-2 rounded-lg bg-gray-100 text-gray-600 w-full text-center';
    row.querySelector('.product-image-section').classList.add('hidden');
    updateRowPrice(row);
}

function syncProductOptionsToMode() {
    const mode = getSelectedMode();
    let visibleOptions = 0;

    document.querySelectorAll('.product-row').forEach(row => {
        const select = row.querySelector('.product-select');
        const currentValue = select.value;
        let selectedStillVisible = !currentValue;

        Array.from(select.options).forEach((option, index) => {
            if (index === 0) {
                option.hidden = false;
                option.disabled = false;
                option.textContent = mode === 'sale' ? 'Select a product for sale' : 'Select a product for rental';
                return;
            }

            const optionMode = option.getAttribute('data-sale-type') === 'sale' ? 'sale' : 'rental';
            const stock = Number(option.getAttribute('data-stock') ?? 0);
            const showOption = optionMode === mode && stock > 0;
            option.hidden = !showOption;
            option.disabled = !showOption;

            if (showOption) {
                visibleOptions += 1;
            }

            if (option.value === currentValue) {
                selectedStillVisible = showOption;
            }
        });

        if (!selectedStillVisible) {
            resetProductRow(row);
        }
    });

    if (saleTypeEmptyState) {
        saleTypeEmptyState.classList.toggle('hidden', visibleOptions > 0);
    }
}

function updateSaleTypeUI() {
    const mode = getSelectedMode();
    if (mode === 'sale') {
        saleTypeHelper.textContent = 'Showing products marked for sale bookings.';
        rentalWindowCard.className = 'rounded-xl border border-amber-200 bg-white p-4 shadow-none transition-colors duration-200';
        rentalWindowEyebrow.textContent = 'Sale Booking';
        rentalWindowEyebrow.className = 'text-sm font-semibold uppercase tracking-[0.18em] text-amber-600';
        rentalWindowHeading.textContent = 'Booking Details';
        rentalWindowCopy.textContent = 'Sale bookings use the listed sale price per unit. Pickup and return are still recorded here.';
    } else {
        saleTypeHelper.textContent = 'Showing products marked for rental bookings.';
        rentalWindowCard.className = 'rounded-xl border border-[#d8e0ea] bg-white p-4 shadow-none transition-colors duration-200';
        rentalWindowEyebrow.textContent = 'Rental Window';
        rentalWindowEyebrow.className = 'text-sm font-semibold uppercase tracking-[0.18em] text-[#0086C9]';
        rentalWindowHeading.textContent = 'Pickup and Return';
        rentalWindowCopy.textContent = 'Pricing updates automatically from the selected rental duration.';
    }

    syncProductOptionsToMode();
    updateOrderItemsTierText();
    updateRentalWindowSummary();
    refreshAllRowPrices();
    refreshAvailabilityForWindow();
}

async function refreshAvailabilityForWindow() {
    const params = new URLSearchParams();
    if (pickupInput.value) {
        params.set('pickup_datetime', pickupInput.value);
    }
    if (returnInput.value) {
        params.set('return_datetime', returnInput.value);
    }

    try {
        const response = await fetch(`${availabilityEndpoint}?${params.toString()}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (!response.ok) {
            throw new Error(`Availability request failed with status ${response.status}`);
        }

        const payload = await response.json();
        const availability = payload.availability || {};

        document.querySelectorAll('.product-select').forEach(select => {
            Array.from(select.options).forEach((option, index) => {
                if (index === 0 || !option.value) {
                    return;
                }

                const productId = option.value;
                const stock = Number(availability[productId] ?? option.getAttribute('data-stock') ?? 0);
                const label = option.getAttribute('data-product-label') || option.textContent.replace(/\s*\([^)]*\)\s*$/, '');

                option.setAttribute('data-stock', String(stock));
                option.setAttribute('data-scooter-count', String(stock));
                option.textContent = `${label} (${stock} available)`;
            });
        });

        document.querySelectorAll('.product-row').forEach(row => {
            const select = row.querySelector('.product-select');
            if (select && select.value) {
                updateProductRow(select);
            }
        });

        syncProductOptionsToMode();
        updateTotal();
    } catch (error) {
        console.error('Failed to refresh booking availability:', error);
    }
}

function updateRentalWindowSummary() {
    const days = getRentalDays();
    const pickupDate = parseAdminDate(pickupInput.value);
    const returnDate = parseAdminDate(returnInput.value);
    rentalDurationBadge.textContent = getSelectedMode() === 'sale'
        ? 'Sale booking'
        : `${days} day${days > 1 ? 's' : ''} rental`;

    if (!pickupDate || !returnDate) {
        rentalWindowSummary.textContent = getSelectedMode() === 'sale'
            ? 'Select dates to record this walk-in sale booking.'
            : 'Select both dates to calculate tiered rental pricing.';
        return;
    }

    const fmt = (value) => {
        if (typeof window.formatAdminDateTime === 'function') {
            return window.formatAdminDateTime(value);
        }
        return String(value || '');
    };
    const pickupLabel = fmt(pickupInput.value);
    const returnLabel = fmt(returnInput.value);

    rentalWindowSummary.textContent = getSelectedMode() === 'sale'
        ? `Sale recorded on ${pickupLabel} with return noted for ${returnLabel}`
        : `${pickupLabel} to ${returnLabel}`;

    updateOrderItemsTierText();
}

function validateRentalWindow() {
    const pickupDate = parseAdminDate(pickupInput.value);
    const returnDate = parseAdminDate(returnInput.value);
    if (!pickupDate || !returnDate) {
        return { valid: false, message: 'Please select both pickup and return date/time.' };
    }
    if (returnDate <= pickupDate) {
        return { valid: false, message: 'Return date/time must be after pickup date/time.' };
    }
    const days = getRentalDays();
    if (days > 31) {
        return { valid: false, message: 'Booking in this form are limited to 31 days.' };
    }
    return { valid: true, days: days };
}

function getVariationKey(row) {
    const variationSelect = row.querySelector('.variation-select');
    if (!variationSelect || variationSelect.style.display === 'none' || !variationSelect.value) {
        return 'null';
    }
    return variationSelect.value;
}

function getEffectiveRowPrice(row) {
    const productSelect = row.querySelector('.product-select');
    const selected = productSelect.options[productSelect.selectedIndex];
    if (!selected || !selected.value) {
        return 0;
    }

    const basePrice = Number(selected.getAttribute('data-price')) || 0;
    if (saleTypeSelect.value === 'sale') {
        return basePrice;
    }

    const tieredPrice = getTieredPrice(selected.value, getVariationKey(row), getRentalDays());
    return tieredPrice !== null ? tieredPrice : basePrice;
}

function calculatePromoDiscount(subtotal) {
    if (!activePromoCode || !Object.prototype.hasOwnProperty.call(WALKIN_PROMO_RULES, activePromoCode)) {
        return 0;
    }
    const rule = WALKIN_PROMO_RULES[activePromoCode];
    let discount = 0;
    if (rule.type === 'percent') {
        discount = subtotal * (Number(rule.value) / 100);
    } else {
        discount = Number(rule.value);
    }
    return Math.max(0, Math.min(discount, subtotal));
}

function setPromoFeedback(message, type = 'neutral') {
    if (!promoFeedback) return;
    promoFeedback.textContent = message;
    promoFeedback.className = 'mt-2 text-xs';
    if (type === 'success') {
        promoFeedback.classList.add('text-green-700');
    } else if (type === 'error') {
        promoFeedback.classList.add('text-red-700');
    } else {
        promoFeedback.classList.add('text-gray-500');
    }
}

function setApplyPromoLoadingState(isLoading) {
    if (!applyPromoBtn) return;
    applyPromoBtn.disabled = isLoading;
    applyPromoBtn.classList.toggle('opacity-60', isLoading);
    applyPromoBtn.classList.toggle('cursor-not-allowed', isLoading);
    applyPromoBtn.textContent = isLoading ? 'Applying...' : 'Apply';
    if (promoCodeInput) {
        promoCodeInput.disabled = isLoading;
    }
}

function wait(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function applyPromoCode() {
    if (!promoCodeInput) return;
    const code = String(promoCodeInput.value || '').trim().toUpperCase();

    if (code === '') {
        activePromoCode = '';
        if (promoCodeHiddenInput) promoCodeHiddenInput.value = '';
        setPromoFeedback('Promo removed.');
        updateTotal();
        return;
    }

    setApplyPromoLoadingState(true);
    setPromoFeedback('Checking promo code...');
    await wait(450);

    try {
        if (!Object.prototype.hasOwnProperty.call(WALKIN_PROMO_RULES, code)) {
            activePromoCode = '';
            if (promoCodeHiddenInput) promoCodeHiddenInput.value = '';
            setPromoFeedback('Invalid promo code.', 'error');
            updateTotal();
            return;
        }

        const confirmed = window.confirm(`Apply promo code ${code}?`);
        if (!confirmed) {
            setPromoFeedback('Promo application cancelled.');
            return;
        }

        activePromoCode = code;
        if (promoCodeHiddenInput) promoCodeHiddenInput.value = code;
        const rule = WALKIN_PROMO_RULES[code];
        const details = rule.type === 'percent' ? `${rule.value}% off` : `$${Number(rule.value).toFixed(2)} off`;
        setPromoFeedback(`Promo ${code} confirmed (${details}).`, 'success');
        updateTotal();
    } finally {
        setApplyPromoLoadingState(false);
    }
}

function updateRowPrice(row) {
    const productSelect = row.querySelector('.product-select');
    const selected = productSelect.options[productSelect.selectedIndex];
    const priceLabel = row.querySelector('.product-price');
    if (!selected || !selected.value) {
        priceLabel.textContent = '--';
        row.dataset.effectivePrice = '0';
        return;
    }

    const price = getEffectiveRowPrice(row);
    row.dataset.effectivePrice = String(price);
    priceLabel.textContent = formatMoney(price);
}

function refreshAllRowPrices() {
    document.querySelectorAll('.product-row').forEach(row => updateRowPrice(row));
    updateTotal();
}

function addProductRow() {
    const productsList = document.getElementById('products-list');
    const firstRow = productsList.querySelector('.product-row');
    const newRow = firstRow.cloneNode(true);

    // Reset all values
    const select = newRow.querySelector('.product-select');
    select.selectedIndex = 0;
    select.addEventListener('change', function() { updateProductRow(this); });
    
    const variationSelect = newRow.querySelector('.variation-select');
    variationSelect.addEventListener('change', function() {
        updateRowPrice(newRow);
        updateTotal();
    });
    
    newRow.querySelector('.quantity-input').value = 1;
    newRow.querySelector('.product-price').textContent = '--';
    newRow.querySelector('.product-stock').textContent = '0 left';
    newRow.querySelector('.product-stock').className = 'product-stock text-xs font-semibold px-3 py-2 rounded-lg bg-gray-100 text-gray-600 w-full text-center';
    newRow.querySelector('.product-image-section').classList.add('hidden');
    
    // Reset variation dropdown
    variationSelect.innerHTML = '';
    variationSelect.style.display = 'none';
    
    // Re-attach delete button handler
    newRow.querySelector('button[onclick="removeProductRow(this)"]').onclick = function() { removeProductRow(this); };

    productsList.appendChild(newRow);
}

function removeProductRow(btn) {
    const row = btn.closest('.product-row');
    const productsList = document.getElementById('products-list');
    if (productsList.querySelectorAll('.product-row').length > 1) {
        row.remove();
        updateTotal();
    }
}

function setVariationOptions(selectEl, variations) {
    if (!selectEl) return;

    selectEl.replaceChildren();
    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = 'Select variation';
    selectEl.appendChild(placeholder);

    (variations || []).forEach(function (variation) {
        const option = document.createElement('option');
        option.value = String(variation?.variation_id ?? '');
        option.textContent = String(variation?.variation_name ?? '');
        selectEl.appendChild(option);
    });
}

function updateProductRow(select) {
    const selected = select.options[select.selectedIndex];
    const imgUrl = selected.getAttribute('data-img');
    const productName = selected.text;
    const stock = parseInt(selected.getAttribute('data-stock')) || 0;
    const scooterCount = parseInt(selected.getAttribute('data-scooter-count')) || 0;
    const row = select.closest('.product-row');
    
    // Update price display
    updateRowPrice(row);
    
    // Update stock display with color coding
    const stockSpan = row.querySelector('.product-stock');
    if (stock === 0) {
        stockSpan.textContent = '0 left';
        stockSpan.className = 'product-stock text-xs font-semibold px-3 py-2 rounded-lg bg-red-100 text-red-700 w-full text-center';
    } else if (stock <= 3) {
        stockSpan.textContent = stock + ' left';
        stockSpan.className = 'product-stock text-xs font-semibold px-3 py-2 rounded-lg bg-orange-100 text-orange-700 w-full text-center';
    } else {
        stockSpan.textContent = stock + ' left';
        stockSpan.className = 'product-stock text-xs font-semibold px-3 py-2 rounded-lg bg-green-100 text-green-700 w-full text-center';
    }
    
    // Update image preview
    if (imgUrl) {
        const imageSection = row.querySelector('.product-image-section');
        imageSection.classList.remove('hidden');
        row.querySelector('.product-image').src = imgUrl;
        row.querySelector('.product-name').textContent = productName.split('(')[0].trim();
        row.querySelector('.product-details').textContent = `Available units: ${scooterCount}`;
    } else {
        row.querySelector('.product-image-section').classList.add('hidden');
    }
    
    // Set max quantity to available stock and reset if needed
    const qtyInput = row.querySelector('.quantity-input');
    qtyInput.max = Math.max(1, stock);
    qtyInput.disabled = stock === 0;
    if (stock === 0) {
        qtyInput.value = 0;
    } else if (parseInt(qtyInput.value) > stock) {
        qtyInput.value = stock;
    }

    // Handle variations
    const variationSelect = row.querySelector('.variation-select');
    let variations = [];
    try {
        variations = JSON.parse(selected.getAttribute('data-variations'));
    } catch (e) { variations = []; }
    if (variations && variations.length > 0) {
        setVariationOptions(variationSelect, variations);
        variationSelect.style.display = '';
        variationSelect.required = true;
        variationSelect.onchange = function() {
            updateRowPrice(row);
            updateTotal();
        };
    } else {
        variationSelect.innerHTML = '';
        variationSelect.style.display = 'none';
        variationSelect.required = false;
        variationSelect.onchange = null;
    }
    updateTotal();
}

function updateTotal() {
    let subtotal = 0;
    let hasErrors = false;
    document.querySelectorAll('.product-row').forEach(row => {
        const select = row.querySelector('.product-select');
        const qtyInput = row.querySelector('.quantity-input');
        const selectedOption = select.options[select.selectedIndex];
        
        if (!selectedOption.value) return; // Skip if no product selected
        
        const quantity = parseInt(qtyInput.value) || 0;
        const price = Number(row.dataset.effectivePrice || getEffectiveRowPrice(row));
        const stock = parseInt(selectedOption.getAttribute('data-stock')) || 0;
        
        if (quantity > stock && stock > 0) {
            qtyInput.classList.add('border-red-500', 'bg-red-50');
            hasErrors = true;
        } else {
            qtyInput.classList.remove('border-red-500', 'bg-red-50');
        }
        
        subtotal += price * quantity;
    });

    const discount = calculatePromoDiscount(subtotal);
    const productTotalWithTax = Math.max(0, subtotal - discount);
    const total = productTotalWithTax + SECURITY_DEPOSIT;
    const pretaxSubtotal = productTotalWithTax > 0 ? (productTotalWithTax / NV_TAX_INCLUSIVE_FACTOR) : 0;
    const taxIncluded = Math.max(0, productTotalWithTax - pretaxSubtotal);
    const manualOverrideAmount = getManualOverrideAmount();
    const overrideExceedsComputed = manualOverrideAmount !== null && manualOverrideAmount > total;
    const displayTotal = manualOverrideAmount !== null && !overrideExceedsComputed ? manualOverrideAmount : total;

    if (subtotalAmountEl) subtotalAmountEl.textContent = formatMoney(subtotal);
    if (discountAmountEl) discountAmountEl.textContent = `-$${Number(discount).toFixed(2)}`;
    if (securityDepositAmountEl) securityDepositAmountEl.textContent = formatMoney(SECURITY_DEPOSIT);
    if (pretaxAmountEl) pretaxAmountEl.textContent = formatMoney(pretaxSubtotal);
    if (taxAmountEl) taxAmountEl.textContent = formatMoney(taxIncluded);
    document.getElementById('total-amount').textContent = `$${displayTotal.toFixed(2)}`;
    document.getElementById('total-amount-input').value = displayTotal.toFixed(2);

    if (finalPriceOverrideFeedback) {
        if (overrideExceedsComputed) {
            finalPriceOverrideFeedback.textContent = `Override cannot exceed computed total ${formatMoney(total)}.`;
            finalPriceOverrideFeedback.className = 'mt-2 text-xs text-red-700';
        } else if (manualOverrideAmount !== null) {
            finalPriceOverrideFeedback.textContent = `Override active. Computed total is ${formatMoney(total)}; final total will be ${formatMoney(displayTotal)}.`;
            finalPriceOverrideFeedback.className = 'mt-2 text-xs text-amber-700';
        } else {
            finalPriceOverrideFeedback.textContent = 'If set, this overrides the computed total and is tracked with your superadmin account.';
            finalPriceOverrideFeedback.className = 'mt-2 text-xs text-slate-500';
        }
    }

    if (finalPriceOverrideInput) {
        finalPriceOverrideInput.max = total.toFixed(2);
        if (overrideExceedsComputed) {
            finalPriceOverrideInput.setCustomValidity(`Override cannot exceed computed total ${formatMoney(total)}.`);
            finalPriceOverrideInput.classList.add('border-red-500', 'bg-red-50');
        } else {
            finalPriceOverrideInput.setCustomValidity('');
            finalPriceOverrideInput.classList.remove('border-red-500', 'bg-red-50');
        }
    }
}

// Initial setup
document.querySelectorAll('.product-select').forEach(select => {
    select.addEventListener('change', function() { updateProductRow(this); });
});
document.querySelectorAll('.variation-select').forEach(select => {
    select.addEventListener('change', function() {
        const row = this.closest('.product-row');
        updateRowPrice(row);
        updateTotal();
    });
});
document.querySelectorAll('.quantity-input').forEach(input => {
    input.addEventListener('input', updateTotal);
});
document.querySelectorAll('.product-row').forEach(row => {
    const deleteBtn = row.querySelector('button[onclick="removeProductRow(this)"]');
    if (deleteBtn) {
        deleteBtn.onclick = function() { removeProductRow(this); };
    }
});

if (applyPromoBtn) {
    applyPromoBtn.addEventListener('click', applyPromoCode);
}
if (clientWeightRangeInput) {
    clientWeightRangeInput.addEventListener('input', function() {
        const normalized = String(this.value || '').replace(/\D/g, '');
        if (normalized !== this.value) {
            this.value = normalized;
        }
    });
}
if (promoCodeInput) {
    promoCodeInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            applyPromoCode();
        }
    });
}
if (finalPriceOverrideInput) {
    finalPriceOverrideInput.addEventListener('input', function() {
        const normalized = String(this.value || '').replace(/[^0-9.]/g, '');
        if (normalized !== this.value) {
            this.value = normalized;
        }
        updateTotal();
    });
}

const BUSINESS_OPEN_MINUTES = (8 * 60) + 30;
const BUSINESS_CLOSE_MINUTES = (17 * 60) + 30;
const MIN_RETURN_GAP_MINUTES = 30;
const MAX_RENTAL_DAYS = 31;

const walkinDateTimeModal = document.getElementById('walkinDateTimeModal');
const walkinDateTimeModalTitle = document.getElementById('walkinDateTimeModalTitle');
const walkinDateTimeModalDateInput = document.getElementById('walkinDateTimeModalDate');
const walkinDateTimeModalCalendar = document.getElementById('walkinDateTimeModalCalendar');
const walkinDateTimeModalTime = document.getElementById('walkinDateTimeModalTime');
const walkinDateTimeModalInfo = document.getElementById('walkinDateTimeModalInfo');
const walkinDateTimeModalClose = document.getElementById('walkinDateTimeModalClose');
const walkinDateTimeModalCancel = document.getElementById('walkinDateTimeModalCancel');
const walkinDateTimeModalSave = document.getElementById('walkinDateTimeModalSave');

let walkinModalCalendarPicker = null;
let walkinActiveField = 'pickup';
let walkinPreviousSelection = { pickup: '', ret: '' };

function padDatePart(value) {
    return String(value).padStart(2, '0');
}

function formatStorageDateTime(date) {
    return `${date.getFullYear()}-${padDatePart(date.getMonth() + 1)}-${padDatePart(date.getDate())} ${padDatePart(date.getHours())}:${padDatePart(date.getMinutes())}`;
}

function formatDisplayDateTime(date) {
    const month = date.toLocaleString(undefined, { month: 'long' });
    const day = date.getDate();
    const year = date.getFullYear();
    let hours = date.getHours();
    const minutes = padDatePart(date.getMinutes());
    const meridiem = hours >= 12 ? 'pm' : 'am';
    hours = hours % 12;
    if (hours === 0) hours = 12;
    return `${month} ${day}, ${year} ${hours}:${minutes}${meridiem}`;
}

function formatDisplayTimeOnly(date) {
    let hours = date.getHours();
    const minutes = padDatePart(date.getMinutes());
    const meridiem = hours >= 12 ? 'pm' : 'am';
    hours = hours % 12;
    if (hours === 0) hours = 12;
    return `${hours}:${minutes}${meridiem}`;
}

function normalizeToBusinessHours(date) {
    const normalized = new Date(date);
    normalized.setSeconds(0, 0);

    const remainder = normalized.getMinutes() % 15;
    if (remainder !== 0) {
        normalized.setMinutes(normalized.getMinutes() + (15 - remainder));
    }

    while (true) {
        const minutes = (normalized.getHours() * 60) + normalized.getMinutes();
        if (minutes < BUSINESS_OPEN_MINUTES) {
            normalized.setHours(8, 30, 0, 0);
            break;
        }
        if (minutes > BUSINESS_CLOSE_MINUTES) {
            normalized.setDate(normalized.getDate() + 1);
            normalized.setHours(8, 30, 0, 0);
            continue;
        }
        break;
    }

    return normalized;
}

function getMinimumPickupDateTime() {
    return normalizeToBusinessHours(getNearest15Min());
}

function getMinimumReturnDateTime(pickupDate) {
    return normalizeToBusinessHours(new Date(pickupDate.getTime() + (MIN_RETURN_GAP_MINUTES * 60000)));
}

function getMaximumReturnDateTime(pickupDate) {
    const max = new Date(pickupDate);
    max.setDate(max.getDate() + MAX_RENTAL_DAYS);
    max.setHours(17, 30, 0, 0);
    return max;
}

function setDateInputValue(input, date) {
    input.value = date ? formatStorageDateTime(date) : '';
}

function syncRentalUiAfterDateChange() {
    updateRentalWindowSummary();
    refreshAllRowPrices();
    refreshAvailabilityForWindow();
}

function getFieldDate(fieldName) {
    return parseAdminDate(fieldName === 'pickup' ? pickupInput.value : returnInput.value);
}

function getModalMinimumDateTime() {
    if (walkinActiveField === 'pickup') {
        return getMinimumPickupDateTime();
    }
    const pickupDate = getFieldDate('pickup') || getMinimumPickupDateTime();
    return getMinimumReturnDateTime(pickupDate);
}

function getModalMaximumDateTime() {
    if (walkinActiveField !== 'return') return null;
    const pickupDate = getFieldDate('pickup') || getMinimumPickupDateTime();
    return getMaximumReturnDateTime(pickupDate);
}

function ensureModalCalendarPicker() {
    if (walkinModalCalendarPicker || !walkinDateTimeModalCalendar) return;

    walkinModalCalendarPicker = flatpickr(walkinDateTimeModalDateInput, {
        inline: true,
        dateFormat: 'Y-m-d',
        clickOpens: false,
        disableMobile: true,
        appendTo: walkinDateTimeModalCalendar,
        onChange: function() {
            populateWalkinTimeSlots();
        }
    });
}

function refreshWalkinCalendar(referenceDate) {
    ensureModalCalendarPicker();
    if (!walkinModalCalendarPicker) return;

    const minDateTime = getModalMinimumDateTime();
    const maxDateTime = getModalMaximumDateTime();

    walkinModalCalendarPicker.set('minDate', minDateTime);
    walkinModalCalendarPicker.set('maxDate', maxDateTime || null);

    const selected = referenceDate instanceof Date ? referenceDate : minDateTime;
    walkinModalCalendarPicker.setDate(selected, false);
}

function populateWalkinTimeSlots(preferredValue) {
    if (!walkinModalCalendarPicker || !walkinDateTimeModalTime) return;

    const selectedDate = walkinModalCalendarPicker.selectedDates[0] || getModalMinimumDateTime();
    const minDateTime = getModalMinimumDateTime();
    const maxDateTime = getModalMaximumDateTime();

    walkinDateTimeModalTime.innerHTML = '';
    let firstValue = '';

    for (let mins = BUSINESS_OPEN_MINUTES; mins <= BUSINESS_CLOSE_MINUTES; mins += 15) {
        const hour = Math.floor(mins / 60);
        const minute = mins % 60;
        const candidate = new Date(selectedDate);
        candidate.setHours(hour, minute, 0, 0);

        if (candidate < minDateTime) continue;
        if (maxDateTime && candidate > maxDateTime) continue;

        const value = `${padDatePart(hour)}:${padDatePart(minute)}`;
        const option = document.createElement('option');
        option.value = value;
        option.textContent = formatDisplayTimeOnly(candidate);
        walkinDateTimeModalTime.appendChild(option);
        if (!firstValue) firstValue = value;
    }

    if (preferredValue && walkinDateTimeModalTime.querySelector(`option[value="${preferredValue}"]`)) {
        walkinDateTimeModalTime.value = preferredValue;
    } else {
        walkinDateTimeModalTime.value = firstValue;
    }

    const infoDate = minDateTime;
    walkinDateTimeModalInfo.textContent = walkinActiveField === 'pickup'
        ? `Earliest pickup is ${formatDisplayDateTime(infoDate)}.`
        : `Return must be after ${formatDisplayDateTime(infoDate)}.`;
}

function openWalkinDateTimeModal(fieldName) {
    if (!walkinDateTimeModal) return;

    walkinActiveField = fieldName;
    walkinPreviousSelection = {
        pickup: pickupInput.value || '',
        ret: returnInput.value || ''
    };

    walkinDateTimeModalTitle.textContent = fieldName === 'pickup'
        ? 'Select pickup date & time'
        : 'Select return date & time';

    const currentDate = getFieldDate(fieldName) || getModalMinimumDateTime();
    refreshWalkinCalendar(currentDate);
    populateWalkinTimeSlots(currentDate ? `${padDatePart(currentDate.getHours())}:${padDatePart(currentDate.getMinutes())}` : '');

    walkinDateTimeModal.classList.remove('hidden');
    walkinDateTimeModal.classList.add('flex');
}

function closeWalkinDateTimeModal() {
    if (!walkinDateTimeModal) return;
    walkinDateTimeModal.classList.add('hidden');
    walkinDateTimeModal.classList.remove('flex');
}

function buildWalkinModalSelection() {
    if (!walkinModalCalendarPicker || !walkinDateTimeModalTime || !walkinDateTimeModalTime.value) {
        return null;
    }

    const selectedDate = walkinModalCalendarPicker.selectedDates[0];
    if (!selectedDate) return null;

    const [hours, minutes] = walkinDateTimeModalTime.value.split(':').map(Number);
    const date = new Date(selectedDate);
    date.setHours(hours, minutes, 0, 0);
    return date;
}

function applyWalkinModalSelection(selectedDate) {
    if (!selectedDate) return;

    if (walkinActiveField === 'pickup') {
        const pickupDate = normalizeToBusinessHours(selectedDate);
        setDateInputValue(pickupInput, pickupDate);

        const currentReturn = parseAdminDate(returnInput.value);
        const minReturn = getMinimumReturnDateTime(pickupDate);
        const maxReturn = getMaximumReturnDateTime(pickupDate);

        if (!currentReturn || currentReturn < minReturn || currentReturn > maxReturn) {
            setDateInputValue(returnInput, minReturn);
        }
    } else {
        const pickupDate = parseAdminDate(pickupInput.value) || getMinimumPickupDateTime();
        const minReturn = getMinimumReturnDateTime(pickupDate);
        const maxReturn = getMaximumReturnDateTime(pickupDate);
        const returnDate = selectedDate < minReturn ? minReturn : (selectedDate > maxReturn ? maxReturn : selectedDate);
        setDateInputValue(returnInput, returnDate);
    }

    syncRentalUiAfterDateChange();
}

function restoreWalkinDateSelection() {
    pickupInput.value = walkinPreviousSelection.pickup || '';
    returnInput.value = walkinPreviousSelection.ret || '';
    syncRentalUiAfterDateChange();
}

function initializeWalkinDates() {
    const existingPickup = parseAdminDate(pickupInput.value);
    const minimumPickup = getMinimumPickupDateTime();
    const pickupDate = existingPickup && existingPickup >= minimumPickup
        ? normalizeToBusinessHours(existingPickup)
        : minimumPickup;
    setDateInputValue(pickupInput, pickupDate);

    const existingReturn = parseAdminDate(returnInput.value);
    const minReturn = getMinimumReturnDateTime(pickupDate);
    const maxReturn = getMaximumReturnDateTime(pickupDate);

    if (!existingReturn || existingReturn < minReturn || existingReturn > maxReturn) {
        setDateInputValue(returnInput, minReturn);
    } else {
        setDateInputValue(returnInput, normalizeToBusinessHours(existingReturn));
    }

    syncRentalUiAfterDateChange();
}

if (walkinDateTimeModalClose) {
    walkinDateTimeModalClose.addEventListener('click', function() {
        restoreWalkinDateSelection();
        closeWalkinDateTimeModal();
    });
}
if (walkinDateTimeModalCancel) {
    walkinDateTimeModalCancel.addEventListener('click', function() {
        restoreWalkinDateSelection();
        closeWalkinDateTimeModal();
    });
}
if (walkinDateTimeModal) {
    walkinDateTimeModal.addEventListener('click', function(event) {
        if (event.target === walkinDateTimeModal) {
            restoreWalkinDateSelection();
            closeWalkinDateTimeModal();
        }
    });
}
if (walkinDateTimeModalSave) {
    walkinDateTimeModalSave.addEventListener('click', function() {
        const selectedDate = buildWalkinModalSelection();
        if (!selectedDate) {
            alert('Please select a valid date and time.');
            return;
        }
        applyWalkinModalSelection(selectedDate);
        closeWalkinDateTimeModal();
    });
}

pickupInput.addEventListener('click', function() {
    openWalkinDateTimeModal('pickup');
});
returnInput.addEventListener('click', function() {
    openWalkinDateTimeModal('return');
});

initializeWalkinDates();

saleTypeSelect.addEventListener('change', function() {
    updateSaleTypeUI();
});

updateOrderItemsTierText();
updateSaleTypeUI();
refreshAvailabilityForWindow();

// Build cart JSON before submit
bookingForm.addEventListener('submit', function(e) {
    if (bookingForm.dataset.submitting === 'true') {
        e.preventDefault();
        return;
    }

    const rentalCheck = validateRentalWindow();
    if (!rentalCheck.valid) {
        e.preventDefault();
        alert(rentalCheck.message);
        return;
    }

    if (!walkinPolicyCheckbox || !walkinPolicyCheckbox.checked) {
        e.preventDefault();
        alert('You must agree to the rental policy and terms before proceeding.');
        return;
    }

    if (finalPriceOverrideInput) {
        const overrideRaw = String(finalPriceOverrideInput.value || '').trim();
        if (overrideRaw !== '') {
            const overrideAmount = getManualOverrideAmount();
            if (overrideAmount === null) {
                e.preventDefault();
                alert('Final price override must be a valid amount greater than $0.00.');
                return;
            }
            const computedTotal = getComputedWalkinTotal();
            if (overrideAmount > computedTotal) {
                e.preventDefault();
                alert(`Final price override cannot exceed the computed total amount of ${formatMoney(computedTotal)}.`);
                finalPriceOverrideInput.classList.add('border-red-500', 'bg-red-50');
                finalPriceOverrideInput.focus();
                return;
            }
            finalPriceOverrideInput.value = overrideAmount.toFixed(2);
        }
    }

    const cart = [];
    let missingVariationSelection = false;
    document.querySelectorAll('.product-row').forEach(row => {
        const select = row.querySelector('select.product-select');
        const productId = select.value;
        const quantity = row.querySelector('.quantity-input').value;
        const price = row.dataset.effectivePrice || getEffectiveRowPrice(row);
        const selectedOption = select.options[select.selectedIndex];
        const optionText = selectedOption?.textContent || '';
        const optionLabel = selectedOption?.getAttribute('data-product-label') || '';
        const name = (optionLabel || optionText.replace(/\s*\(\d+\s+available\)\s*$/i, '')).trim();
        const image_url = selectedOption?.getAttribute('data-img');
        // Variation
        const variationSelect = row.querySelector('.variation-select');
        let variation_id = null;
        let variation_name = null;
        const variationRequired = variationSelect && variationSelect.style.display !== 'none';
        if (variationRequired && !variationSelect.value && productId) {
            missingVariationSelection = true;
            variationSelect.classList.add('border-red-500', 'bg-red-50');
        } else if (variationSelect) {
            variationSelect.classList.remove('border-red-500', 'bg-red-50');
        }
        if (variationRequired && variationSelect.value) {
            variation_id = variationSelect.value;
            variation_name = variationSelect.options[variationSelect.selectedIndex]?.textContent;
        }
        if (productId) {
            cart.push({
                id: productId,
                qty: quantity,
                price: price,
                name: name,
                image_url: image_url,
                variation_id: variation_id,
                variation_name: variation_name
            });
        }
    });

    if (missingVariationSelection) {
        e.preventDefault();
        alert('Please select a variation for all products that require one.');
        return;
    }

    document.getElementById('cart-json').value = JSON.stringify(cart);

    bookingForm.dataset.submitting = 'true';
    showBookingLoadingState();
});

document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('policyModal');
    if (!modal) return;
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closePolicyModal();
        }
    });
});
    </script>
