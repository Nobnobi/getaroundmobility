<?php
function paginationUrl($page) {
    $params = $_GET;
    $params['page'] = $page;
    return '/search?' . http_build_query($params);
}
?>

    <div class="mt-20 max-w-7xl mx-auto px-4">
        <!-- Date form and Apply Changes button are now inside the filterForm below -->
    </div>

    

    <div class="container mx-auto px-4 py-8 flex-1">
        
        <form id="filterForm" action="/search" method="get">
            <div class="w-full flex flex-col items-center hidden md:block">
                <?php include __DIR__ . '/partials/date-form.php'; ?>
            </div>

            <div class="flex flex-col md:flex-row gap-6">
                <!-- LEFT SIDE: Sidebar + Search -->
                <aside class="bg-white shadow rounded-lg p-4 space-y-6 w-full md:w-72 h-fit sticky top-28 flex-shrink-0 hidden md:block">

                    <!-- Search Bar (now INSIDE sidebar) -->
                    <div class="w-full border-[#D9D9D9] border rounded-lg">
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <img src="/img/search_grey.png" alt="Search" class="w-5 h-5 text-gray-400" />
                            </span>
                            <input
                                type="text"
                                name="q"
                                placeholder="Search products..."
                                class="rounded px-10 py-2 w-full font-[Barlow]"
                                value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
                                style="padding-left: 2.5rem;" 
                            >
                        </div>
                    </div>

                    <!-- FILTER TITLE -->
                    <h2 class="text-2xl font-semibold font-[Barlow]">Filter</h2>

                    <!-- Category Dropdown -->
                    <div>
                        <label class="block text-sm font-medium">Equipment Type</label>
                        <select name="category" class="w-full border border-[#D9D9D9] rounded p-2 font-[Barlow]">
                            <option value="">Select a type</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= (isset($_GET['category']) && $_GET['category'] == $cat['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Price field -->
                    <div>
                        <label class="block text-sm font-medium font-[Barlow]">Price Range</label>
                        <input type="number" name="weight" placeholder="Enter Price"
                            class="border border-[#D9D9D9] rounded p-2 w-full font-[Barlow]"
                            value="<?= htmlspecialchars($_GET['weight'] ?? '') ?>">
                    </div>

                    <!-- PriceOrder -->
                    <div>
                        <label class="block text-sm font-medium font-[Barlow]">Filter Price</label>
                        <select name="price_order" class="w-full border border-[#D9D9D9] rounded p-2 font-[Barlow]">
                            <option value="">None</option>
                            <option value="1" <?= ($_GET['price_order'] ?? '') == '1' ? 'selected' : '' ?>>Highest to Lowest</option>
                            <option value="2" <?= ($_GET['price_order'] ?? '') == '2' ? 'selected' : '' ?>>Lowest to Highest</option>
                        </select>
                    </div>

                    <!-- Availability -->
                    <div>
                        <label class="inline-flex items-center"> <input type="checkbox" name="available_only" value="1" <?= (!isset($_GET['available_only']) || $_GET['available_only'] == '1') ? 'checked' : '' ?>> <span class="ml-2 text-sm font-[Barlow]">Show only available scooters</span> </label>
                    </div>

                    <!-- Search button -->
                    <button type="submit" class="bg-[#0086C9] text-white w-full py-2 rounded-lg shadow mt-4 font-[Barlow] cursor-pointer">
                        Search
                    </button>

                </aside>

                <!-- RIGHT SIDE: Heading + Products + Pagination -->
                <main class="flex-1">

                    <!-- HEADING MOVED HERE -->
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-4 gap-4">
                        <h1 class="font-[Barlow] text-2xl font-bold">Our Equipments</h1>
                    </div>

                    <!-- Mobile Filter Toggle Button with Icon -->
                    <div class="md:hidden mb-4 flex justify-between items-center">
                        <button type="button" id="toggleFiltersBtn" class="bg-[#0086C9] text-white flex items-center gap-2 px-4 py-2 rounded-lg font-[Barlow]">
                            <img src="/img/filter-white.svg" alt="Filter" class="w-6 h-6" />
                            <span>Filters</span>
                        </button>
                    </div>

                    <!-- HEADING MOVED HERE -->
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-4 gap-4">
                        <!-- Show search summary or selected dates, but never both -->
                        <?php if (!empty($_GET['q'])): ?>
                            <p id="searchSummary" class="text-gray-600 text-base mt-1 font-[Barlow]">
                                Search results for "<span class="font-semibold"><?= htmlspecialchars($_GET['q']) ?></span>"
                            </p>
                        <?php else: ?>
                            <p id="dateSummary" class="text-gray-600 text-base mt-1 font-[Barlow]" style="display:none;"></p>
                        <?php endif; ?>
                    </div>
    <!-- Live date summary update script -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Helper to format date string (YYYY-MM-DDTHH:MM or YYYY-MM-DD HH:MM)
        function formatDateString(dateStr) {
            if (!dateStr) return '';
            let d = new Date(dateStr.replace(' ', 'T'));
            if (isNaN(d)) return dateStr;
            return d.toLocaleString(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
        }

        // Only update date summary if search is not active
        <?php if (empty($_GET['q'])): ?>
        function updateDateSummary() {
            const pickupDesktop = document.getElementById('pickupDatetime');
            const returnDesktop = document.getElementById('returnDatetime');
            const pickupMobile = document.querySelector('#mobileFilterPanel #pickupDatetime');
            const returnMobile = document.querySelector('#mobileFilterPanel #returnDatetime');
            let pickup = pickupDesktop?.value || pickupMobile?.value || '';
            let ret = returnDesktop?.value || returnMobile?.value || '';
            const summary = document.getElementById('dateSummary');
            if (pickup && ret) {
                summary.innerHTML = 'Showing available rentals from <span class="font-semibold">' + formatDateString(pickup) + '</span> to <span class="font-semibold">' + formatDateString(ret) + '</span>';
                summary.style.display = '';
            } else {
                summary.innerHTML = '';
                summary.style.display = 'none';
            }
        }
        updateDateSummary();
        const pickupDesktop = document.getElementById('pickupDatetime');
        const returnDesktop = document.getElementById('returnDatetime');
        const pickupMobile = document.querySelector('#mobileFilterPanel #pickupDatetime');
        const returnMobile = document.querySelector('#mobileFilterPanel #returnDatetime');

        // TEMPORARY COMMENT OUT
        // [pickupDesktop, returnDesktop, pickupMobile, returnMobile].forEach(function(input) {
        //     if (input) input.addEventListener('change', updateDateSummary);
        // });
        <?php endif; ?>
    });
    </script>

                    <!-- Pagination Top (inside main, above grid) -->
                    <?php if (!empty($total_products) && $total_products >= 10 && $total_pages > 1): ?>
                        <div class="flex justify-center mb-6">
                            <nav class="inline-flex items-center space-x-1 font-[Barlow]" aria-label="Pagination">
                                <!-- Previous Arrow -->
                                <?php if ($current_page > 1): ?>
                                    <a href="<?= paginationUrl($current_page - 1) ?>" class="px-3 py-1 rounded-full bg-gray-200 text-black hover:bg-gray-300" aria-label="Previous page">
                                        &laquo;
                                    </a>
                                <?php else: ?>
                                    <span class="px-3 py-1 rounded-full bg-gray-100 text-gray-400 cursor-not-allowed">&laquo;</span>
                                <?php endif; ?>

                                <!-- Page Numbers -->
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <?php if ($i == $current_page): ?>
                                        <span class="px-3 py-1 bg-[#0086C9] text-white rounded-full"><?= $i ?></span>
                                    <?php else: ?>
                                        <a href="<?= paginationUrl($i) ?>" class="px-3 py-1 bg-gray-200 text-black rounded-full hover:bg-gray-300"><?= $i ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>

                                <!-- Next Arrow -->
                                <?php if ($current_page < $total_pages): ?>
                                    <a href="<?= paginationUrl($current_page + 1) ?>" class="px-3 py-1 rounded-full bg-gray-200 text-black hover:bg-gray-300" aria-label="Next page">
                                        &raquo;
                                    </a>
                                <?php else: ?>
                                    <span class="px-3 py-1 rounded-full bg-gray-100 text-gray-400 cursor-not-allowed">&raquo;</span>
                                <?php endif; ?>
                            </nav>
                        </div>
                    <?php endif; ?>

                    <!-- Products Grid -->
                    <div class="flex flex-wrap gap-6 justify-center" id="productsGrid">
                        <?php if (!empty($products)): ?>
                            <?php foreach ($products as $item): ?>
                                <?php
                                $modalData = [
                                    'id' => (int)$item['product_id'],
                                    'name' => $item['product_name'],
                                    'price' => $item['price'],
                                    'image_url' => $item['image_url'],
                                    'category' => $item['category_name'] ?? '',
                                    'description' => $item['description'] ?? '',
                                    'total_stock' => isset($item['total_stock']) ? (int)$item['total_stock'] : 0
                                ];
                                $modalDataJson = htmlspecialchars(json_encode($modalData), ENT_QUOTES, 'UTF-8');
                                $count = isset($item['total_stock']) ? (int)$item['total_stock'] : 0;
                                ?>
                                <div
                                    class="bg-white border-2 border-blue-100 rounded-xl shadow hover:shadow-lg transition flex flex-col flex-wrap cursor-pointer relative group w-full sm:w-[48%] md:w-[31%] max-w-xs p-0"
                                    onclick="window.isProductListModal = true; openProductListModal(<?= $modalDataJson ?>)"
                                    data-product-id="<?= $item['product_id'] ?>"
                                >
                                    
                                    <?php if (!empty($item['image_url'])): ?>
                                        <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['product_name']) ?>" class="mb-4 w-full h-60 object-contain rounded-t-xl">
                                    <?php else: ?>
                                        <div class="w-full h-60 bg-gray-200 rounded-t-xl"></div>
                                    <?php endif; ?>
                                    <div class="p-4 flex-1 flex flex-col justify-between text-left w-full">
                                        <!-- Days badge inside card -->
                                        <div class="mb-2 flex items-center justify-end">
                                            <span class="inline-block bg-blue-100 text-blue-700 rounded-full px-3 py-1 text-xs font-[Barlow] animate-pulse-fast product-days-badge"></span>
                                        </div>
                                        <div class="mb-2 flex flex-wrap gap-2 items-center">
                                            <?php if (!empty($item['category_name'])): ?>
                                                <span class="text-xs px-2 py-1 bg-gray-200 rounded-full font-[Barlow]">
                                                    <?= htmlspecialchars($item['category_name']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <h3 class="font-semibold text-lg mb-1 font-[Barlow]"><?= htmlspecialchars($item['product_name']) ?></h3>

                                        <!-- Variations Table -->
                                        <?php if (!empty($item['variations'])): ?>
                                            <div class="mb-2">
                                                <table class="w-full text-xs border border-blue-100 rounded overflow-hidden">
                                                    <thead>
                                                        <tr class="bg-blue-50">
                                                            <th class="py-1 px-2 text-left">Variation</th>
                                                            <th class="py-1 px-2 text-right">Price</th>
                                                            <th class="py-1 px-2 text-center">Stock</th>
                                                            <th class="py-1 px-2"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($item['variations'] as $var): ?>
                                                            <tr data-variation-id="<?= $var['variation_id'] ?>">
                                                                <td class="py-1 px-2 font-medium text-gray-700"><?= htmlspecialchars($var['variation_name']); ?></td>
                                                                <td class="py-1 px-2 text-right text-blue-700 variation-price" data-product-id="<?= $item['product_id'] ?>" data-variation-id="<?= $var['variation_id'] ?>">
                                                                    $<?= number_format($var['price'], 2); ?>
                                                                </td>
                                                                <td class="py-1 px-2 text-center">
                                                                    <?= $var['stock'] > 0 ? $var['stock'] : '<span class="text-red-500">0</span>' ?>
                                                                </td>
                                                                <td class="py-1 px-2">
                                                                    <?php if ($var['stock'] > 0): ?>
                                                                        <button type="button" onclick="event.stopPropagation();addToCartDynamicPrice('<?= htmlspecialchars($item['product_name'] . ' - ' . $var['variation_name']); ?>', <?= $item['product_id']; ?>, <?= $var['variation_id']; ?>, '<?= htmlspecialchars($item['image_url']); ?>', <?= $var['stock']; ?>, '<?= htmlspecialchars($var['variation_name']); ?>')" class="add-to-cart-btn bg-[#0086C9] text-white px-3 py-1 rounded hover:bg-blue-700 cursor-pointer text-xs">Add</button>
                                                                    <?php else: ?>
                                                                        <button class="bg-gray-400 text-white px-3 py-1 rounded opacity-60 cursor-not-allowed text-xs" disabled>Unavailable</button>
                                                                    <?php endif; ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Dynamic Price for products with no variations -->
                                        <?php if (empty($item['variations'])): ?>
                                            <span class="block text-blue-700 font-semibold text-lg mb-1 base-product-price" data-product-id="<?= $item['product_id'] ?>">$<?= number_format($item['price'], 2) ?></span>
                                        <?php endif; ?>
                                        <span class="text-gray-500 text-xs ml-2 font-[Barlow]">
                                            <?= ($count > 0) ? "In stock: {$count}" : "Out of stock" ?>
                                        </span>
                                        <?php if ($count > 0 && empty($item['variations'])): ?>
                                            <button type="button" onclick="event.stopPropagation();addToCartDynamicPrice('<?= htmlspecialchars($item['product_name']); ?>', <?= $item['product_id']; ?>, null, '<?= htmlspecialchars($item['image_url']); ?>', <?= $count; ?>)" class="add-to-cart-btn mt-3 w-full bg-[#0086C9] text-white py-2 rounded hover:bg-blue-700 cursor-pointer">
                                                Add to Cart
                                            </button>
                                        <?php elseif ($count == 0 && empty($item['variations'])): ?>
                                            <button class="mt-3 w-full bg-gray-400 text-white py-2 rounded cursor-not-allowed opacity-60" disabled>
                                                Unavailable
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-center">No equipment available for the date you have specified</p>
                        <?php endif; ?>
                    </div>

                    <!-- Pagination Bottom (inside main, below grid) -->
                    <?php if (!empty($total_products) && $total_products >= 10 && $total_pages > 1): ?>
                        <div class="flex justify-center my-6">
                            <nav class="inline-flex items-center space-x-1 font-[Barlow]" aria-label="Pagination">
                                <!-- Previous Arrow -->
                                <?php if ($current_page > 1): ?>
                                    <a href="<?= paginationUrl($current_page - 1) ?>" class="px-3 py-1 rounded-full bg-gray-200 text-black hover:bg-gray-300" aria-label="Previous page">
                                        &laquo;
                                    </a>
                                <?php else: ?>
                                    <span class="px-3 py-1 rounded-full bg-gray-100 text-gray-400 cursor-not-allowed">&laquo;</span>
                                <?php endif; ?>

                                <!-- Page Numbers -->
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <?php if ($i == $current_page): ?>
                                        <span class="px-3 py-1 bg-[#0086C9] text-white rounded-full"><?= $i ?></span>
                                    <?php else: ?>
                                        <a href="<?= paginationUrl($i) ?>" class="px-3 py-1 bg-gray-200 text-black rounded-full hover:bg-gray-300"><?= $i ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>

                                <!-- Next Arrow -->
                                <?php if ($current_page < $total_pages): ?>
                                    <a href="<?= paginationUrl($current_page + 1) ?>" class="px-3 py-1 rounded-full bg-gray-200 text-black hover:bg-gray-300" aria-label="Next page">
                                        &raquo;
                                    </a>
                                <?php else: ?>
                                    <span class="px-3 py-1 rounded-full bg-gray-100 text-gray-400 cursor-not-allowed">&raquo;</span>
                                <?php endif; ?>
                            </nav>
                        </div>
                    <?php endif; ?>

                </main>
            </div>
        </form>
    </div>

    <!-- MOBILE FILTER OVERLAY AND PANEL -->
    <div id="mobileFilterOverlay" class="fixed inset-0 bg-black/30 z-[60] hidden md:hidden"></div>
    <aside id="mobileFilterPanel" class="fixed top-0 left-0 h-full w-80 max-w-full bg-white shadow-xl z-[70] flex flex-col p-4 space-y-6 -translate-x-full transition-transform duration-300 md:hidden">
        <button id="closeMobileFilter" class="self-end mb-2 text-gray-500 hover:text-black text-2xl">&times;</button>
        <form action="/search" method="get" class="flex flex-col gap-4">
            <h2 class="text-2xl font-semibold font-[Barlow] mb-2">Filter</h2>
            <!-- Date Form -->
            <div class="w-full flex flex-col items-center md:block">
                <?php include __DIR__ . '/partials/date-form.php'; ?>
            </div>
            <!-- Category Dropdown -->
            <div>
                <label class="block text-sm font-medium">Equipment Type</label>
                <select name="category" class="w-full border border-[#D9D9D9] rounded p-2 font-[Barlow]">
                    <option value="">Select a type</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= (isset($_GET['category']) && $_GET['category'] == $cat['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['category_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- Price field -->
            <div>
                <label class="block text-sm font-medium font-[Barlow]">Price Range</label>
                <input type="number" name="weight" placeholder="Enter Price"
                    class="border border-[#D9D9D9] rounded p-2 w-full font-[Barlow]"
                    value="<?= htmlspecialchars($_GET['weight'] ?? '') ?>">
            </div>
            <!-- PriceOrder -->
            <div>
                <label class="block text-sm font-medium font-[Barlow]">Filter Price</label>
                <select name="price_order" class="w-full border border-[#D9D9D9] rounded p-2 font-[Barlow]">
                    <option value="">None</option>
                    <option value="1" <?= ($_GET['price_order'] ?? '') == '1' ? 'selected' : '' ?>>Highest to Lowest</option>
                    <option value="2" <?= ($_GET['price_order'] ?? '') == '2' ? 'selected' : '' ?>>Lowest to Highest</option>
                </select>
            </div>
            <!-- Availability -->
            <div>
                <label class="inline-flex items-center"> <input type="checkbox" name="available_only" value="1" <?= (!isset($_GET['available_only']) || $_GET['available_only'] == '1') ? 'checked' : '' ?>> <span class="ml-2 text-sm font-[Barlow]">Show only available scooters</span> </label>
            </div>
            <button type="submit" class="bg-[#0086C9] text-white w-full py-2 rounded-lg shadow font-[Barlow] cursor-pointer">
                Search
            </button>
        </form>
    </aside>

    <?php if (isset($rentalPrices)): ?>
    <script>
    window.rentalPrices = <?= json_encode($rentalPrices) ?>;
    </script>
    <?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
    // --- Days Badge and Dynamic Price Logic ---
    function getDaysDiff(pickup, ret) {
        if (!pickup || !ret) return 1;
        const start = new Date(pickup);
        const end = new Date(ret);
        if (isNaN(start) || isNaN(end)) return 1;
        let diff = (end - start) / (1000 * 60 * 60 * 24);
        return diff > 0 ? Math.ceil(diff) : 1;
    }

    function parseDateValue(raw) {
        if (!raw) return null;
        const normalized = String(raw).replace(' ', 'T');
        const date = new Date(normalized);
        return isNaN(date) ? null : date;
    }

    function showDateValidationAlertOnce(message) {
        const now = Date.now();
        if (!window.__dateValidationAlertState) {
            window.__dateValidationAlertState = { message: '', at: 0 };
        }

        const last = window.__dateValidationAlertState;
        if (last.message === message && (now - last.at) < 1200) {
            return;
        }

        window.__dateValidationAlertState = { message: message, at: now };
        alert(message);
    }

    function getNearest15MinNow() {
        const now = new Date();
        now.setSeconds(0, 0);
        const minutes = now.getMinutes();
        const remainder = minutes % 15;
        if (remainder !== 0) {
            now.setMinutes(minutes + (15 - remainder));
        }
        return now;
    }

    function clearRentalDatesForInvalidAttempt() {
        localStorage.removeItem('pickupDatetime');
        localStorage.removeItem('returnDatetime');

        const desktopPickup = document.getElementById('pickupDatetime');
        const desktopReturn = document.getElementById('returnDatetime');
        const mobilePickup = document.querySelector('#mobileFilterPanel #pickupDatetime');
        const mobileReturn = document.querySelector('#mobileFilterPanel #returnDatetime');

        [desktopPickup, desktopReturn, mobilePickup, mobileReturn].forEach(function(input) {
            if (!input) return;
            input.value = '';
            if (input._flatpickr) input._flatpickr.clear();
        });

        if (typeof window.updateDaysAndPrices === 'function') window.updateDaysAndPrices();
        if (typeof window.updateDateSummary === 'function') window.updateDateSummary();
    }

    function validateRentalWindow(pickup, ret) {
        const pickupDate = parseDateValue(pickup);
        const returnDate = parseDateValue(ret);
        const now = getNearest15MinNow();
        const minReturnDate = pickupDate ? new Date(pickupDate.getTime() + (30 * 60 * 1000)) : null;

        if (!pickupDate || !returnDate) {
            return { valid: false, message: 'Please select both Pickup and Return date/time.' };
        }

        if (pickupDate < now || returnDate < now) {
            return { valid: false, message: 'Past dates are not allowed. Please choose valid pickup and return dates.' };
        }

        if (returnDate <= pickupDate) {
            return { valid: false, message: 'Return date/time must be after pickup date/time.' };
        }

        if (minReturnDate && returnDate < minReturnDate) {
            return { valid: false, message: 'Return date/time must be at least 30 minutes after pickup date/time.' };
        }

        const days = Math.ceil((returnDate - pickupDate) / (1000 * 60 * 60 * 24));
        if (days > 31) {
            return { valid: false, message: 'Online booking is limited to 31 days. For rentals longer than 31 days, please call us.' };
        }

        return { valid: true, days: days };
    }
    function getTieredPrice(productId, variationId, days) {
        if (!window.rentalPrices || !window.rentalPrices[productId] || !window.rentalPrices[productId][variationId]) return null;
        const tiers = window.rentalPrices[productId][variationId];
        days = Math.min(Math.max(parseInt(days, 10) || 1, 1), 31);
        let key = String(days);
        if (!Object.prototype.hasOwnProperty.call(tiers, key)) return null;
        return Number(tiers[key]);
    }
    function updateDaysAndPrices() {
        const pickup = document.getElementById('pickupDatetime')?.value || localStorage.getItem('pickupDatetime');
        const ret = document.getElementById('returnDatetime')?.value || localStorage.getItem('returnDatetime');
        const days = getDaysDiff(pickup, ret);
        // Update all product days badges
        document.querySelectorAll('.product-days-badge').forEach(function(badge) {
            badge.textContent = days + ' day' + (days > 1 ? 's' : '');
        });
        // Update all variation prices
        document.querySelectorAll('.variation-price').forEach(function(td) {
            const productId = td.getAttribute('data-product-id');
            const variationId = td.getAttribute('data-variation-id');
            const price = getTieredPrice(productId, variationId, days);
            if (price !== null) {
                td.textContent = '$' + Number(price).toFixed(2);
            }
        });
        // Update base product prices (no variations)
        document.querySelectorAll('.base-product-price').forEach(function(span) {
            const productId = span.getAttribute('data-product-id');
            const price = getTieredPrice(productId, 'null', days);
            if (price !== null) {
                span.textContent = '$' + Number(price).toFixed(2);
            }
        });
    }
    // Add pulsing animation style if not present
    if (!document.getElementById('searchPulseStyle')) {
        const style = document.createElement('style');
        style.id = 'searchPulseStyle';
        style.textContent = `
        .animate-pulse-fast { animation: pulse-fast 1.2s cubic-bezier(0.4,0,0.6,1) infinite; }
        @keyframes pulse-fast { 0%,100% { opacity:1; } 50% { opacity:0.5; } }
        `;
        document.head.appendChild(style);
    }
    
    // TEMPORARY COMMENT OUT

    document.addEventListener('DOMContentLoaded', function() {
        updateDaysAndPrices();
        // Desktop date inputs
        const pickupDesktop = document.getElementById('pickupDatetime');
        const returnDesktop = document.getElementById('returnDatetime');
        // Mobile date inputs
        const pickupMobile = document.querySelector('#mobileFilterPanel #pickupDatetime');
        const returnMobile = document.querySelector('#mobileFilterPanel #returnDatetime');

        // Attach updateDaysAndPrices to all date inputs
        [pickupDesktop, returnDesktop, pickupMobile, returnMobile].forEach(function(input) {
            if (input) input.addEventListener('change', updateDaysAndPrices);
        });

        // Attach cart date change modal logic to mobile date inputs
        // function handleDateInputChangeWithCartCheck(input, key) {
        //     let lastValue = input.value;
        //     input.addEventListener('focus', function() {
        //         lastValue = input.value;
        //     });
        //     input.addEventListener('change', function() {
        //         const cart = JSON.parse(localStorage.getItem('cart') || '[]');
        //         const newValue = input.value || '';
        //         if (cart.length > 0 && newValue !== lastValue) {
        //             // Show modal
        //             if (typeof window.showCartDateChangeModal === 'function') {
        //                 window.showCartDateChangeModal(
        //                     function onConfirm() {
        //                         localStorage.setItem(key, newValue);
        //                         if (typeof window.updateDaysAndPrices === 'function') window.updateDaysAndPrices();
        //                         if (input.form) {
        //                             input.form.submit();
        //                         } else {
        //                             location.reload();
        //                         }
        //                     },
        //                     function onCancel() {
        //                         input.value = lastValue;
        //                         if (typeof window.updateDaysAndPrices === 'function') window.updateDaysAndPrices();
        //                     }
        //                 );
        //             }
        //         } else {
        //             localStorage.setItem(key, newValue);
        //             if (typeof window.updateDaysAndPrices === 'function') window.updateDaysAndPrices();
        //         }
        //     });
        // }
        // if (pickupMobile) handleDateInputChangeWithCartCheck(pickupMobile, 'pickupDatetime');
        // if (returnMobile) handleDateInputChangeWithCartCheck(returnMobile, 'returnDatetime');
        // Form submission and modal logic for desktop are handled in date-form.php
    });

    function getActiveDateFormState() {
        const isMobileView = window.matchMedia('(max-width: 767px)').matches;
        const mobilePanel = document.getElementById('mobileFilterPanel');

        if (isMobileView && mobilePanel) {
            return {
                pickup: mobilePanel.querySelector('#pickupDatetime'),
                ret: mobilePanel.querySelector('#returnDatetime'),
                message: mobilePanel.querySelector('#formMessage'),
                panel: mobilePanel,
                overlay: document.getElementById('mobileFilterOverlay')
            };
        }

        return {
            pickup: document.getElementById('pickupDatetime'),
            ret: document.getElementById('returnDatetime'),
            message: document.getElementById('formMessage'),
            panel: null,
            overlay: null
        };
    }

    function showMissingDateWarning() {
        const { message, panel, overlay } = getActiveDateFormState();

        if (panel && overlay) {
            panel.classList.remove('-translate-x-full');
            panel.classList.add('translate-x-0');
            overlay.classList.remove('hidden');
        }

        if (message) {
            message.classList.remove('hidden');
            setTimeout(() => message.classList.add('hidden'), 3000);
        } else {
            showDateValidationAlertOnce('Please select a date and time for Pickup and Return.');
        }

        if (typeof emphasizeRentalForm === 'function') emphasizeRentalForm();
    }

    function addToCartDynamicPrice(name, id, variation_id, image_url, scooter_count, variation_name = null) {
        // Date validation
        const { pickup: pickupInput, ret: returnInput } = getActiveDateFormState();
        const pickup = pickupInput?.value || '';
        const ret = returnInput?.value || '';
        if (!pickup || !ret) {
            showMissingDateWarning();
            return;
        }

        const rentalValidation = validateRentalWindow(pickup, ret);
        if (!rentalValidation.valid) {
            showDateValidationAlertOnce(rentalValidation.message);
            clearRentalDatesForInvalidAttempt();
            if (typeof emphasizeRentalForm === 'function') emphasizeRentalForm();
            return;
        }

        // Calculate correct price for selected days
        const days = rentalValidation.days;
        let price = null;
        if (window.rentalPrices && window.rentalPrices[id]) {
            const vId = variation_id === null ? 'null' : String(variation_id);
            if (window.rentalPrices[id][vId]) {
                price = getTieredPrice(id, vId, days);
            }
        }
        if (price === null) {
            // fallback: use base price (not recommended)
            price = 0;
        }
        let cart = loadCart();
        let added = false;
        let existing = cart.find(item => {
            if (variation_id !== undefined && variation_id !== null) {
                return String(item.id) === String(id) && String(item.variation_id) === String(variation_id);
            } else {
                return String(item.id) === String(id) && (!item.variation_id || item.variation_id === null);
            }
        });
        if (existing) {
            if (existing.qty < scooter_count) {
                existing.qty += 1;
                added = true;
            } else {
                alert('You cannot add more than the available stock.');
            }
        } else {
            added = true;
            cart.push({
                id,
                name,
                price: Number(price),
                qty: 1,
                image_url,
                scooter_count,
                variation_id: variation_id !== undefined ? variation_id : null,
                variation_name: variation_name !== undefined ? variation_name : null
            });
        }
        saveCart(cart);
        renderCart();
        updateCartCountBadge();
        if (added && typeof showCartToast === 'function') {
            showCartToast(name);
        }
    }
    window.addToCartDynamicPrice = addToCartDynamicPrice;
    </script>

    <!-- FILTER SIDEBAR MOBILE -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const toggleFiltersBtn = document.getElementById('toggleFiltersBtn');
        const mobileFilterPanel = document.getElementById('mobileFilterPanel');
        const mobileFilterOverlay = document.getElementById('mobileFilterOverlay');
        const closeMobileFilter = document.getElementById('closeMobileFilter');
        // Open filter
        if (toggleFiltersBtn && mobileFilterPanel && mobileFilterOverlay) {
            toggleFiltersBtn.addEventListener('click', function() {
                mobileFilterPanel.classList.remove('-translate-x-full');
                mobileFilterPanel.classList.add('translate-x-0');
                mobileFilterOverlay.classList.remove('hidden');
                // Initialize mobile date pickers and logic on open
                setTimeout(initMobileDateForm, 100);
            });
        }
        // Close filter
        function closeFilterPanel() {
            mobileFilterPanel.classList.add('-translate-x-full');
            mobileFilterPanel.classList.remove('translate-x-0');
            mobileFilterOverlay.classList.add('hidden');
        }
        if (closeMobileFilter) closeMobileFilter.addEventListener('click', closeFilterPanel);
        if (mobileFilterOverlay) mobileFilterOverlay.addEventListener('click', closeFilterPanel);

        // --- Mobile date-form logic (same as desktop) ---
        function initMobileDateForm() {
            const pickupInput = document.querySelector('#mobileFilterPanel #pickupDatetime');
            const returnInput = document.querySelector('#mobileFilterPanel #returnDatetime');
            if (!pickupInput || !returnInput) return;

            // Prevent double-init
            if (pickupInput._flatpickr && returnInput._flatpickr) return;

            function getNearest15Min() {
                const now = new Date();
                now.setSeconds(0, 0);
                const minutes = now.getMinutes();
                const remainder = minutes % 15;
                if (remainder !== 0) {
                    now.setMinutes(minutes + (15 - remainder));
                }
                return now;
            }

            function formatTime24(date) {
                const h = String(date.getHours()).padStart(2, '0');
                const m = String(date.getMinutes()).padStart(2, '0');
                return h + ':' + m;
            }

            function isSameCalendarDay(a, b) {
                return a && b
                    && a.getFullYear() === b.getFullYear()
                    && a.getMonth() === b.getMonth()
                    && a.getDate() === b.getDate();
            }

            function syncMobileReturnTimeBounds() {
                const pickupDate = parseDateValue(pickupInput.value);
                const returnDate = parseDateValue(returnInput.value);

                if (!pickupDate || !returnPicker) return;

                const minReturnDate = new Date(pickupDate.getTime() + (30 * 60 * 1000));

                if (returnDate && isSameCalendarDay(pickupDate, returnDate)) {
                    returnPicker.set('minTime', formatTime24(minReturnDate));
                } else {
                    returnPicker.set('minTime', '00:00');
                }
                returnPicker.set('maxTime', '23:59');
            }

            // Remove defaultDate — we control it manually
            const pickupPicker = flatpickr(pickupInput, {
                enableTime: true,
                dateFormat: "Y-m-d H:i",
                minDate: getNearest15Min(),
                time_24hr: true,
                minuteIncrement: 15,
                disableMobile: true,
                onChange: function(selectedDates) {
                    if (selectedDates[0]) {
                        const minReturnDate = new Date(selectedDates[0].getTime() + (30 * 60 * 1000));
                        const maxReturnDate = new Date(selectedDates[0]);
                        maxReturnDate.setDate(maxReturnDate.getDate() + 31);
                        returnPicker.set('minDate', minReturnDate);
                        returnPicker.set('maxDate', maxReturnDate);
                        syncMobileReturnTimeBounds();
                        const currentReturn = parseDateValue(returnInput.value);
                        if (currentReturn && currentReturn < minReturnDate) {
                            const adjustedReturn = flatpickr.formatDate(minReturnDate, 'Y-m-d H:i');
                            returnInput.value = adjustedReturn;
                            returnPicker.setDate(adjustedReturn, false);
                            localStorage.setItem('returnDatetime', adjustedReturn);
                            if (typeof window.updateDaysAndPrices === 'function') window.updateDaysAndPrices();
                            if (typeof window.updateDateSummary === 'function') window.updateDateSummary();
                            window.dispatchEvent(new CustomEvent('rental-dates-updated', {
                                detail: { pickup: pickupInput.value || '', return: adjustedReturn }
                            }));
                        } else if (currentReturn && currentReturn > maxReturnDate) {
                            returnInput.value = '';
                            returnPicker.clear();
                            localStorage.removeItem('returnDatetime');
                            if (typeof window.updateDaysAndPrices === 'function') window.updateDaysAndPrices();
                            if (typeof window.updateDateSummary === 'function') window.updateDateSummary();
                            window.dispatchEvent(new CustomEvent('rental-dates-updated', {
                                detail: { pickup: pickupInput.value || '', return: '' }
                            }));
                        }
                    }
                }
            });

            const returnPicker = flatpickr(returnInput, {
                enableTime: true,
                dateFormat: "Y-m-d H:i",
                minDate: getNearest15Min(),
                time_24hr: true,
                minuteIncrement: 15,
                disableMobile: true,
                onOpen: function() {
                    syncMobileReturnTimeBounds();
                },
                onChange: function(selectedDates) {
                    if (selectedDates[0]) {
                        syncMobileReturnTimeBounds();
                        const pickupDate = parseDateValue(pickupInput.value);
                        if (pickupDate && pickupDate >= selectedDates[0]) {
                            const minReturnDate = new Date(pickupDate.getTime() + (30 * 60 * 1000));
                            const adjustedReturn = flatpickr.formatDate(minReturnDate, 'Y-m-d H:i');
                            returnInput.value = adjustedReturn;
                            returnPicker.setDate(adjustedReturn, false);
                            localStorage.setItem('returnDatetime', adjustedReturn);
                            if (typeof window.updateDaysAndPrices === 'function') window.updateDaysAndPrices();
                            if (typeof window.updateDateSummary === 'function') window.updateDateSummary();
                            window.dispatchEvent(new CustomEvent('rental-dates-updated', {
                                detail: { pickup: pickupInput.value || '', return: adjustedReturn }
                            }));
                            return;
                        }

                        if (pickupDate) {
                            const days = Math.ceil((selectedDates[0] - pickupDate) / (1000 * 60 * 60 * 24));
                            if (days > 31) {
                                returnInput.value = '';
                                returnPicker.clear();
                                localStorage.removeItem('returnDatetime');
                                if (typeof window.updateDaysAndPrices === 'function') window.updateDaysAndPrices();
                                if (typeof window.updateDateSummary === 'function') window.updateDateSummary();
                                window.dispatchEvent(new CustomEvent('rental-dates-updated', {
                                    detail: { pickup: pickupInput.value || '', return: '' }
                                }));
                                return;
                            }
                        }
                    }
                }
            });

            // Load from localStorage or set defaults
            const savedPickup = localStorage.getItem('pickupDatetime');
            const savedReturn = localStorage.getItem('returnDatetime');
            if (!savedPickup || savedPickup === 'null' || savedPickup === '') {
                const nearest = getNearest15Min();
                const formatted = flatpickr.formatDate(nearest, "Y-m-d H:i");
                pickupInput.value = formatted;
                pickupPicker.setDate(nearest, false);
                localStorage.setItem('pickupDatetime', formatted);
            } else {
                pickupInput.value = savedPickup;
                pickupPicker.setDate(savedPickup, false);
            }
            if (!savedReturn || savedReturn === 'null' || savedReturn === '') {
                const baseDate = new Date(savedPickup || pickupInput.value);
                const defaultReturn = new Date(baseDate);
                defaultReturn.setMinutes(defaultReturn.getMinutes() + 30);
                const formatted = flatpickr.formatDate(defaultReturn, "Y-m-d H:i");
                returnInput.value = formatted;
                returnPicker.setDate(defaultReturn, false);
                localStorage.setItem('returnDatetime', formatted);
            } else {
                returnInput.value = savedReturn;
                returnPicker.setDate(savedReturn, false);
            }

            syncMobileReturnTimeBounds();

            // Validation on load
            const pickupVal = pickupInput.value;
            const returnVal = returnInput.value;
            if (pickupVal && returnVal) {
                const pickupDate = parseDateValue(pickupVal);
                const returnDate = parseDateValue(returnVal);
                if (pickupDate > returnDate) {
                    returnInput.value = '';
                    returnPicker.clear();
                    localStorage.removeItem('returnDatetime');
                } else if (returnDate < pickupDate) {
                    returnInput.value = '';
                    returnPicker.clear();
                    localStorage.removeItem('returnDatetime');
                } else {
                    const diffDays = Math.ceil((returnDate - pickupDate) / (1000 * 60 * 60 * 24));
                    if (diffDays > 31) {
                        returnInput.value = '';
                        returnPicker.clear();
                        localStorage.removeItem('returnDatetime');
                    }
                }
            }

            function syncMobileDateInputsFromStorage() {
                const pickupStored = localStorage.getItem('pickupDatetime') || '';
                const returnStored = localStorage.getItem('returnDatetime') || '';

                pickupInput.value = pickupStored;
                returnInput.value = returnStored;
                if (pickupInput._flatpickr) pickupInput._flatpickr.setDate(pickupStored, false);
                if (returnInput._flatpickr) returnInput._flatpickr.setDate(returnStored, false);
            }

            function notifyRentalDatesChanged() {
                if (typeof window.updateDaysAndPrices === 'function') window.updateDaysAndPrices();
                if (typeof window.updateDateSummary === 'function') window.updateDateSummary();
                window.dispatchEvent(new CustomEvent('rental-dates-updated', {
                    detail: {
                        pickup: pickupInput.value || '',
                        return: returnInput.value || ''
                    }
                }));
            }

            function submitMobileDateFormIfReady(form) {
                if (!form) return;
                const rentalCheck = validateRentalWindow(pickupInput.value, returnInput.value);
                if (!rentalCheck.valid) return;
                form.submit();
            }

            syncMobileDateInputsFromStorage();
            window.addEventListener('rental-dates-updated', syncMobileDateInputsFromStorage);
            window.addEventListener('storage', function(event) {
                if (event.key === 'pickupDatetime' || event.key === 'returnDatetime') {
                    syncMobileDateInputsFromStorage();
                }
            });

            const mobileFilterForm = pickupInput.form;
            if (mobileFilterForm && !mobileFilterForm.dataset.dateValidationBound) {
                mobileFilterForm.dataset.dateValidationBound = '1';
                mobileFilterForm.addEventListener('submit', function(e) {
                    localStorage.setItem('pickupDatetime', pickupInput.value || '');
                    localStorage.setItem('returnDatetime', returnInput.value || '');

                    const rentalCheck = validateRentalWindow(pickupInput.value, returnInput.value);
                    if (!rentalCheck.valid) {
                        e.preventDefault();
                        showDateValidationAlertOnce(rentalCheck.message);
                        return;
                    }

                    notifyRentalDatesChanged();
                });
            }

            // Attach cart date change modal logic to mobile date inputs
            function saveCart(cart) {
                localStorage.setItem('cart', JSON.stringify(cart));
            }
            function handleDateInputChangeWithCartCheck(input, key, picker) {
                let lastValue = input.value;
                input.addEventListener('focus', function() {
                    lastValue = input.value;
                });
                input.addEventListener('change', function() {
                    const cart = JSON.parse(localStorage.getItem('cart') || '[]');
                    const newValue = input.value || '';
                    if (cart.length > 0 && newValue !== lastValue) {
                        if (typeof window.showCartDateChangeModal === 'function') {
                            window.showCartDateChangeModal(
                                function onConfirm() {
                                    saveCart([]);
                                    localStorage.setItem(key, newValue);
                                    if (picker) picker.setDate(newValue, false);
                                    notifyRentalDatesChanged();
                                },
                                function onCancel() {
                                    input.value = lastValue;
                                    if (picker) picker.setDate(lastValue, false);
                                    notifyRentalDatesChanged();
                                }
                            );
                        }
                    } else {
                        localStorage.setItem(key, newValue);
                        if (picker) picker.setDate(newValue, false);
                        notifyRentalDatesChanged();
                    }

                    lastValue = newValue;
                });
            }
            handleDateInputChangeWithCartCheck(pickupInput, 'pickupDatetime', pickupPicker);
            handleDateInputChangeWithCartCheck(returnInput, 'returnDatetime', returnPicker);
        }
    });
    </script>

    <!-- <?php if (!empty($products)): ?>
    <div style="background:#f9fafb;border:1px solid #cbd5e1;padding:12px;margin-bottom:16px;font-size:13px;overflow-x:auto;">
        <strong>Debug: rentalPrices for first product (JS):</strong><br>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var firstId = <?= json_encode($products[0]['product_id']) ?>;
            var debugDiv = document.createElement('pre');
            debugDiv.style.maxWidth = '100%';
            debugDiv.style.whiteSpace = 'pre-wrap';
            debugDiv.textContent = JSON.stringify(window.rentalPrices && window.rentalPrices[firstId], null, 2);
            document.currentScript.parentNode.appendChild(debugDiv);
        });
        </script>
    </div>
    <div style="background:#f9fafb;border:1px solid #cbd5e1;padding:12px;margin-bottom:16px;font-size:13px;overflow-x:auto;">
        <strong>Debug: rentalPrices (JS, all products)</strong><br>
        <button onclick="console.log('window.rentalPrices:', window.rentalPrices);alert('Check the browser console for full output.');" style="margin-bottom:8px;padding:2px 8px;font-size:12px;">Print to Console</button>
        <pre id="rentalPricesDebug" style="max-width:100%;white-space:pre-wrap;"></pre>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var debugDiv = document.getElementById('rentalPricesDebug');
            debugDiv.textContent = JSON.stringify(window.rentalPrices, null, 2);
        });
        </script>
    </div>
    <?php endif; ?> -->

    <!-- Cross-form synchronization for date inputs -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Desktop date inputs
        const pickupDesktop = document.getElementById('pickupDatetime');
        const returnDesktop = document.getElementById('returnDatetime');
        // Mobile date inputs
        const pickupMobile = document.querySelector('#mobileFilterPanel #pickupDatetime');
        const returnMobile = document.querySelector('#mobileFilterPanel #returnDatetime');

        // Helper to sync values
        function syncDateInputs(source, target) {
            if (source && target && source.value !== target.value) {
                target.value = source.value;
                if (target._flatpickr) target._flatpickr.setDate(source.value, false);
            }
        }

        // Desktop → Mobile
        if (pickupDesktop && pickupMobile) {
            pickupDesktop.addEventListener('change', function() {
                syncDateInputs(pickupDesktop, pickupMobile);
            });
        }
        if (returnDesktop && returnMobile) {
            returnDesktop.addEventListener('change', function() {
                syncDateInputs(returnDesktop, returnMobile);
            });
        }

        // Mobile → Desktop
        if (pickupMobile && pickupDesktop) {
            pickupMobile.addEventListener('change', function() {
                syncDateInputs(pickupMobile, pickupDesktop);
            });
        }
        if (returnMobile && returnDesktop) {
            returnMobile.addEventListener('change', function() {
                syncDateInputs(returnMobile, returnDesktop);
            });
        }
    });
    </script>
    </div>
</div>