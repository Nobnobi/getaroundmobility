<!-- filepath: c:\xampp\htdocs\GetAroundMobility\src\Views\admin\new-order.php -->
<?php
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$rentalPrices = $rentalPrices ?? [];
?>

    <div id="booking-loading-overlay" class="fixed inset-0 z-50 hidden items-center justify-center bg-[#062B41]/70 px-6">
        <div class="w-full max-w-sm rounded-2xl bg-white px-6 py-7 text-center shadow-2xl">
            <div class="mx-auto h-12 w-12 animate-spin rounded-full border-4 border-gray-200 border-t-[#0086C9]"></div>
            <h2 class="mt-4 text-xl font-bold text-[#062B41]">Creating booking</h2>
            <p class="mt-2 text-sm text-gray-500">Please wait while the order, stock assignment, and pricing are being saved.</p>
        </div>
    </div>

    <div class="flex flex-1 items-center justify-center w-full">
        <div class="bg-white rounded-2xl shadow-xl p-10 w-full max-w-2xl mx-auto border border-gray-200">
            <h1 class="text-3xl font-bold mb-8 text-center text-[#062B41] tracking-tight">Walk-in Booking</h1>
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
            <form method="post" action="/admin/orders/new" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="agree_policy" value="1">
                <input type="hidden" name="cart" id="cart-json">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block mb-1 font-semibold text-gray-700">First Name</label>
                        <input type="text" name="guest_first_name" required class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-[#062B41] focus:outline-none">
                    </div>
                    <div>
                        <label class="block mb-1 font-semibold text-gray-700">Last Name</label>
                        <input type="text" name="guest_last_name" required class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-[#062B41] focus:outline-none">
                    </div>
                    <div>
                        <label class="block mb-1 font-semibold text-gray-700">Email</label>
                        <input type="email" name="email" required class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-[#062B41] focus:outline-none">
                    </div>
                    <div>
                        <label class="block mb-1 font-semibold text-gray-700">Phone</label>
                        <input type="text" name="phone" required class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-[#062B41] focus:outline-none">
                    </div>
                    <div>
                        <label class="block mb-1 font-semibold text-gray-700">Address</label>
                        <input type="text" name="address1" required class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-[#062B41] focus:outline-none">
                    </div>
                    <!-- Pickup Location removed for walk-in booking, default will be set in backend -->
                    <div>
                        <label class="block mb-1 font-semibold text-gray-700">Notes</label>
                        <textarea name="notes" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-[#062B41] focus:outline-none"></textarea>
                    </div>
                    <div>
                        <label class="block mb-1 font-semibold text-gray-700">Sale Type</label>
                        <select name="sale_type" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-[#062B41] focus:outline-none">
                            <option value="rental">Rental</option>
                            <option value="sale">Sale</option>
                        </select>
                        <p id="sale-type-helper" class="mt-2 text-sm text-gray-500">Showing products marked for rental bookings.</p>
                    </div>
                    <div>
                        <label class="block mb-1 font-semibold text-gray-700">Payment Method</label>
                        <select name="payment_method" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-[#062B41] focus:outline-none">
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <div id="rental-window-card" class="rounded-2xl border border-gray-200 bg-gradient-to-br from-slate-50 to-white p-5 shadow-sm transition-colors duration-200">
                            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                                <div>
                                    <p id="rental-window-eyebrow" class="text-sm font-semibold uppercase tracking-[0.18em] text-[#0086C9]">Rental Window</p>
                                    <h2 id="rental-window-heading" class="mt-1 text-xl font-bold text-[#062B41]">Pickup and Return</h2>
                                    <p id="rental-window-copy" class="mt-1 text-sm text-gray-500">Pricing updates automatically from the selected rental duration.</p>
                                </div>
                                <div id="rental-duration-badge" class="inline-flex items-center rounded-full bg-[#062B41] px-4 py-2 text-sm font-semibold text-white">
                                    1 day rental
                                </div>
                            </div>
                            <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <label for="pickupDatetime" class="mb-1 block text-sm font-semibold text-gray-700">Pickup date & time</label>
                                    <input
                                        id="pickupDatetime"
                                        name="pickup_datetime"
                                        type="text"
                                        readonly
                                        required
                                        class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm shadow-sm transition focus:outline-none focus:ring-2 focus:ring-[#062B41]"
                                        placeholder="Select pickup date and time"
                                        autocomplete="off"
                                    >
                                </div>
                                <div>
                                    <label for="returnDatetime" class="mb-1 block text-sm font-semibold text-gray-700">Return date & time</label>
                                    <input
                                        id="returnDatetime"
                                        name="return_datetime"
                                        type="text"
                                        readonly
                                        required
                                        class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm shadow-sm transition focus:outline-none focus:ring-2 focus:ring-[#062B41]"
                                        placeholder="Select return date and time"
                                        autocomplete="off"
                                    >
                                </div>
                            </div>
                            <div class="mt-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                <div id="rental-window-note" class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                                    Admin bookings support up to 31 rental days in this form.
                                </div>
                                <div id="rental-window-summary" class="text-sm font-medium text-gray-500">
                                    Select both dates to calculate tiered rental pricing.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-8 border-t pt-6">
                    <div class="flex justify-between items-center mb-4">
                        <label class="block font-bold text-lg text-gray-800">Order Items</label>
                        <button type="button" onclick="addProductRow()" class="bg-green-500 text-white px-4 py-2 rounded-lg shadow hover:bg-green-600 transition-colors cursor-pointer flex items-center gap-2">
                            <span>+</span> Add Product
                        </button>
                    </div>
                    <div id="products-list" class="space-y-4">
                        <div class="product-row bg-white rounded-lg border border-gray-300 p-5 shadow-sm hover:shadow-md transition-shadow">
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                                <div class="md:col-span-4">
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Product</label>
                                    <select required class="w-full border border-gray-300 rounded-lg px-3 py-2 product-select focus:ring-2 focus:ring-[#062B41] focus:outline-none text-sm" onchange="updateProductRow(this)">
                                        <option value="">-- Select a product --</option>
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
                                <div class="md:col-span-2">
                                    <select class="w-full border border-gray-300 rounded-lg px-3 py-2 variation-select focus:ring-2 focus:ring-[#062B41] focus:outline-none text-sm" style="display:none;">
                                        <option value="">-- Select variation --</option>
                                    </select>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Qty</label>
                                    <input type="number" min="1" value="1" class="w-full border border-gray-300 rounded-lg px-3 py-2 quantity-input focus:ring-2 focus:ring-[#062B41] focus:outline-none text-sm text-center" onchange="updateTotal()" max="1">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Price</label>
                                    <span class="product-price text-gray-700 font-bold text-lg">--</span>
                                    <p class="product-price-meta mt-1 text-xs text-gray-500">Choose dates to see rental pricing.</p>
                                </div>
                                <div class="md:col-span-2 flex gap-2">
                                    <span class="product-stock text-xs font-semibold px-3 py-2 rounded-lg bg-gray-100 text-gray-600 w-full text-center" title="Available stock">0 left</span>
                                    <button type="button" onclick="removeProductRow(this)" class="text-white bg-red-500 hover:bg-red-600 rounded-lg px-3 py-2 font-bold transition-colors">×</button>
                                </div>
                            </div>
                            <div class="product-image-section mt-4 hidden">
                                <div class="flex items-center gap-4 p-3 bg-gray-50 rounded-lg border border-gray-200">
                                    <img src="" alt="Product" class="product-image h-16 w-16 object-contain rounded">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-700 product-name"></p>
                                        <p class="text-xs text-gray-500 product-details"></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="sale-type-empty-state" class="mt-4 hidden rounded-2xl border border-dashed border-amber-300 bg-amber-50 px-4 py-5 text-sm text-amber-800">
                        No products are currently marked for this booking type.
                    </div>
                </div>

                <div class="mb-4 mt-6 flex items-center justify-between">
                    <label class="block font-semibold text-gray-700">Total Amount</label>
                    <span id="total-amount" class="font-bold text-2xl text-[#062B41]">$0.00</span>
                    <input type="hidden" name="total_amount" id="total-amount-input" value="0">
                </div>
                <div class="flex justify-end mt-8 gap-3">
                    <a href="/admin/orders" class="bg-gray-200 text-gray-800 px-6 py-2 rounded-lg hover:bg-gray-300 transition-colors cursor-pointer">Cancel</a>
                    <button type="submit" class="bg-[#0086C9] text-white px-6 py-2 rounded-lg font-semibold shadow hover:bg-[#08456b] transition-colors cursor-pointer">Create Booking</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
window.rentalPrices = <?= json_encode($rentalPrices, JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
const bookingForm = document.querySelector('form[action="/admin/orders/new"]');
const bookingLoadingOverlay = document.getElementById('booking-loading-overlay');
const saleTypeSelect = bookingForm.querySelector('select[name="sale_type"]');
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

function formatMoney(value) {
    return `$${Number(value || 0).toFixed(2)}`;
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
    variationSelect.innerHTML = '<option value="">-- Select variation --</option>';
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
                option.textContent = mode === 'sale' ? '-- Select a product for sale --' : '-- Select a product for rental --';
                return;
            }

            const optionMode = option.getAttribute('data-sale-type') === 'sale' ? 'sale' : 'rental';
            const showOption = optionMode === mode;
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
        rentalWindowCard.className = 'rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 to-white p-5 shadow-sm transition-colors duration-200';
        rentalWindowEyebrow.textContent = 'Sale Booking';
        rentalWindowEyebrow.className = 'text-sm font-semibold uppercase tracking-[0.18em] text-amber-600';
        rentalWindowHeading.textContent = 'Booking Details';
        rentalWindowCopy.textContent = 'Sale bookings use the listed sale price per unit. Pickup and return are still recorded here.';
    } else {
        saleTypeHelper.textContent = 'Showing products marked for rental bookings.';
        rentalWindowCard.className = 'rounded-2xl border border-gray-200 bg-gradient-to-br from-slate-50 to-white p-5 shadow-sm transition-colors duration-200';
        rentalWindowEyebrow.textContent = 'Rental Window';
        rentalWindowEyebrow.className = 'text-sm font-semibold uppercase tracking-[0.18em] text-[#0086C9]';
        rentalWindowHeading.textContent = 'Pickup and Return';
        rentalWindowCopy.textContent = 'Pricing updates automatically from the selected rental duration.';
    }

    syncProductOptionsToMode();
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
        const response = await fetch(`/admin/orders/availability?${params.toString()}`, {
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

    rentalWindowSummary.textContent = getSelectedMode() === 'sale'
        ? `Sale recorded on ${pickupDate.toLocaleString()} with return noted for ${returnDate.toLocaleString()}`
        : `${pickupDate.toLocaleString()} to ${returnDate.toLocaleString()}`;
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
        return { valid: false, message: 'Admin bookings in this form are limited to 31 days.' };
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

function updateRowPrice(row) {
    const productSelect = row.querySelector('.product-select');
    const selected = productSelect.options[productSelect.selectedIndex];
    const priceLabel = row.querySelector('.product-price');
    const priceMeta = row.querySelector('.product-price-meta');
    if (!selected || !selected.value) {
        priceLabel.textContent = '--';
        priceMeta.textContent = 'Choose dates to see rental pricing.';
        row.dataset.effectivePrice = '0';
        return;
    }

    const price = getEffectiveRowPrice(row);
    row.dataset.effectivePrice = String(price);
    priceLabel.textContent = formatMoney(price);
    if (saleTypeSelect.value === 'sale') {
        priceMeta.textContent = 'One-time sale price per unit.';
    } else {
        const days = getRentalDays();
        priceMeta.textContent = `Rental tier for ${days} day${days > 1 ? 's' : ''}.`;
    }
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
    newRow.querySelector('.product-price-meta').textContent = 'Choose dates to see rental pricing.';
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
        variationSelect.innerHTML = '<option value="">-- Select variation --</option>' +
            variations.map(v => `<option value="${v.variation_id}">${v.variation_name}</option>`).join('');
        variationSelect.style.display = '';
        variationSelect.onchange = function() {
            updateRowPrice(row);
            updateTotal();
        };
    } else {
        variationSelect.innerHTML = '';
        variationSelect.style.display = 'none';
        variationSelect.onchange = null;
    }
    updateTotal();
}

function updateTotal() {
    let total = 0;
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
        
        total += price * quantity;
    });
    document.getElementById('total-amount').textContent = `$${total.toFixed(2)}`;
    document.getElementById('total-amount-input').value = total.toFixed(2);
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

let returnPicker;

const pickupPicker = flatpickr(pickupInput, {
    enableTime: true,
    dateFormat: 'Y-m-d H:i',
    altInput: true,
    altFormat: 'F j, Y h:i K',
    minDate: getNearest15Min(),
    time_24hr: true,
    minuteIncrement: 15,
    disableMobile: true,
    defaultDate: getNearest15Min(),
    onChange: function(selectedDates) {
        if (selectedDates[0]) {
            const minReturn = new Date(selectedDates[0]);
            const maxReturn = getMaxReturnDate(minReturn);
            returnPicker.set('minDate', minReturn);
            returnPicker.set('maxDate', maxReturn);

            const currentReturn = parseAdminDate(returnInput.value);
            if (!currentReturn || currentReturn <= minReturn || currentReturn > maxReturn) {
                const defaultReturn = new Date(minReturn);
                defaultReturn.setDate(defaultReturn.getDate() + 1);
                returnPicker.setDate(defaultReturn, true);
            }
        }
        updateRentalWindowSummary();
        refreshAllRowPrices();
        refreshAvailabilityForWindow();
    }
});

const defaultReturnDate = new Date(getNearest15Min());
defaultReturnDate.setDate(defaultReturnDate.getDate() + 1);

returnPicker = flatpickr(returnInput, {
    enableTime: true,
    dateFormat: 'Y-m-d H:i',
    altInput: true,
    altFormat: 'F j, Y h:i K',
    minDate: new Date(getNearest15Min().getTime() + (60 * 60 * 1000)),
    maxDate: getMaxReturnDate(getNearest15Min()),
    time_24hr: true,
    minuteIncrement: 15,
    disableMobile: true,
    defaultDate: defaultReturnDate,
    onChange: function() {
        const rentalCheck = validateRentalWindow();
        if (!rentalCheck.valid) {
            alert(rentalCheck.message);
        }
        updateRentalWindowSummary();
        refreshAllRowPrices();
        refreshAvailabilityForWindow();
    }
});

saleTypeSelect.addEventListener('change', function() {
    updateSaleTypeUI();
});

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

    const cart = [];
    document.querySelectorAll('.product-row').forEach(row => {
        const select = row.querySelector('select.product-select');
        const productId = select.value;
        const quantity = row.querySelector('.quantity-input').value;
        const price = row.dataset.effectivePrice || getEffectiveRowPrice(row);
        const name = select.options[select.selectedIndex]?.textContent;
        const image_url = select.options[select.selectedIndex]?.getAttribute('data-img');
        // Variation
        const variationSelect = row.querySelector('.variation-select');
        let variation_id = null;
        let variation_name = null;
        if (variationSelect && variationSelect.style.display !== 'none' && variationSelect.value) {
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
    document.getElementById('cart-json').value = JSON.stringify(cart);

    bookingForm.dataset.submitting = 'true';
    showBookingLoadingState();
});
    </script>
