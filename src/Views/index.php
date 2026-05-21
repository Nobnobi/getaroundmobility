<?php $isHomePage = true; ?>
<!-- Cart Date Change Modal -->

<!-- HERO SECTION -->
<section class="relative h-screen bg-cover bg-center bg-[url(/img/new-las-vegas-hero-section.png)] mt-16">
    <div class="relative z-10 h-full flex px-6 items-center justify-center">
        <div class="max-w-screen-xl mx-auto text-white text-center">
            <h1 class="text-5xl md:text-6xl font-bold leading-tight mb-4 font-[Barlow]">Your partner in Mobility</h1>
            <p class="text-lg md:text-xl mb-6 max-w-2xl mx-auto font-[Barlow]">The best customer mobility rental for customer-first teams. Industry-leading support.</p>
            <a id="heroRentNowBtn" href="#rentalForm" class="group inline-flex items-center gap-3
                    bg-[#0086C9] text-white
                    px-10 py-4 rounded-full
                    font-[Barlow] font-semibold text-lg
                    shadow-[0_10px_25px_rgba(0,134,201,0.35)]
                    hover:shadow-[0_14px_35px_rgba(0,134,201,0.45)]
                    hover:scale-[1.03]
                    transition-all duration-200
                    focus:outline-none focus:ring-4 focus:ring-[#0086C9]/40">
                
                <span>Rent a Scooter</span>
                
                <!-- Arrow Icon -->
                <svg class="w-5 h-5 transform group-hover:translate-x-1 transition"
                    fill="none" stroke="currentColor" stroke-width="2"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9 5l7 7-7 7" />
                </svg>
            </a>
        </div>
    </div>
</section>

<!-- RENTAL FORM -->
<section id="rentalForm" class="relative transition-shadow duration-500 -mt-28">
    <div id="rentalFormCard" class="relative max-w-6xl mx-auto bg-white shadow-lg rounded-xl p-8 md:p-12">
        <h2 class="text-2xl md:text-3xl font-semibold mb-2 font-[Barlow]">Looking to rent mobility equipment in Las Vegas?</h2>
        <p class="mb-6 text-gray-600 font-sans text-base md:text-lg">Book your mobility rental quickly and easily. We provide top-quality wheelchairs, scooters, and mobility aids with convenient delivery throughout Las Vegas.</p>
        <form action="/search" method="GET" class="flex flex-wrap gap-4 justify-center items-center">
            <div class="w-full flex justify-center items-center">
                <?php include __DIR__ . '/partials/date-form.php'; ?>
                                <script>
                                    
                                // On homepage: prevent form auto-submit on date change, but update localStorage and prices/days
                                document.addEventListener('DOMContentLoaded', function() {
                                    var pickup = document.getElementById('pickupDatetime');
                                    var ret = document.getElementById('returnDatetime');
                                    var form = pickup && pickup.form;
                                    function handleDateChangeNoSubmit(input, key) {
                                        input.addEventListener('change', function(e) {
                                            localStorage.setItem(key, input.value);
                                            if (typeof window.updateEquipmentPrices === 'function') window.updateEquipmentPrices();
                                            // Prevent form auto-submit from date-form.php
                                            if (form) form.onsubmit = function(ev) { ev.preventDefault(); return false; };
                                        });
                                        input.addEventListener('input', function(e) {
                                            localStorage.setItem(key, input.value);
                                            if (typeof window.updateEquipmentPrices === 'function') window.updateEquipmentPrices();
                                        });
                                    }
                                    if (pickup) handleDateChangeNoSubmit(pickup, 'pickupDatetime');
                                    if (ret) handleDateChangeNoSubmit(ret, 'returnDatetime');
                                     // Modal logic for cart clearing on date change
                                     function showCartDateChangeModal() {
                                         var modal = document.getElementById('cartDateChangeModal');
                                         if (modal) {
                                             modal.classList.remove('hidden');
                                             modal.classList.add('flex');
                                         }
                                     }
                                     function hideCartDateChangeModal() {
                                         var modal = document.getElementById('cartDateChangeModal');
                                         if (modal) {
                                             modal.classList.remove('flex');
                                             modal.classList.add('hidden');
                                         }
                                     }
                                     function clearCart() {
                                         // Clear cart logic: remove cart from localStorage and sessionStorage
                                         localStorage.removeItem('cart');
                                         sessionStorage.removeItem('cart');
                                         // Optionally, clear cart UI if present
                                         if (typeof window.updateCartUI === 'function') window.updateCartUI();
                                     }
                                     function handleDateInputChangeWithCartCheck(e) {
                                         // Only show modal if cart is not empty
                                         var cart = localStorage.getItem('cart');
                                         if (cart && cart.length > 2) {
                                             clearCart();
                                             showCartDateChangeModal();
                                         }
                                     }
                                     // Attach modal logic to both date inputs
                                     if (pickup) {
                                         pickup.addEventListener('change', handleDateInputChangeWithCartCheck);
                                         pickup.addEventListener('input', handleDateInputChangeWithCartCheck);
                                     }
                                     if (ret) {
                                         ret.addEventListener('change', handleDateInputChangeWithCartCheck);
                                         ret.addEventListener('input', handleDateInputChangeWithCartCheck);
                                     }
                                     // Modal close button
                                     var modalCloseBtn = document.getElementById('cartDateChangeModalCloseBtn');
                                     if (modalCloseBtn) {
                                         modalCloseBtn.addEventListener('click', function() {
                                             hideCartDateChangeModal();
                                         });
                                     }
                                });
                                </script>
                <script>
                // Prevent instant form submission on date change for homepage only, but allow localStorage update
                document.addEventListener('DOMContentLoaded', function() {
                    var form = document.querySelector('form[action="/search"]');
                    if (!form) return;
                    var pickup = document.getElementById('pickupDatetime');
                    var ret = document.getElementById('returnDatetime');
                    // Remove only the auto-submit behavior from date-form.js logic
                    if (pickup) {
                        pickup.addEventListener('change', function(e) {
                            // Save to localStorage as normal
                            localStorage.setItem('pickupDatetime', pickup.value);
                            // Prevent form auto-submit if any
                                            // Simple modal logic for cart clearing on date change
                                            function showCartDateChangeModal() {
                                                var modal = document.getElementById('cartDateChangeModal');
                                                if (modal) {
                                                    modal.classList.remove('hidden');
                                                    modal.classList.add('flex');
                                                }
                                            }
                                            function hideCartDateChangeModal() {
                                                var modal = document.getElementById('cartDateChangeModal');
                                                if (modal) {
                                                    modal.classList.remove('flex');
                                                    modal.classList.add('hidden');
                                                }
                                            }
                                            function clearCart() {
                                                localStorage.removeItem('cart');
                                                sessionStorage.removeItem('cart');
                                            }
                                            function handleCartDateChange(e) {
                                                var cart = localStorage.getItem('cart');
                                                if (cart && cart.length > 2) {
                                                    clearCart();
                                                    showCartDateChangeModal();
                                                }
                                            }
                                            if (pickup) {
                                                pickup.addEventListener('change', handleCartDateChange);
                                                pickup.addEventListener('input', handleCartDateChange);
                                            }
                                            if (ret) {
                                                ret.addEventListener('change', handleCartDateChange);
                                                ret.addEventListener('input', handleCartDateChange);
                                            }
                                            var modalCloseBtn = document.getElementById('cartDateChangeModalCloseBtn');
                                            if (modalCloseBtn) {
                                                modalCloseBtn.addEventListener('click', function() {
                                                    hideCartDateChangeModal();
                                                });
                                            }
                            if (e && e.stopImmediatePropagation) e.stopImmediatePropagation();
                        }, true);
                        pickup.addEventListener('input', function(e) {
                            localStorage.setItem('pickupDatetime', pickup.value);
                            if (e && e.stopImmediatePropagation) e.stopImmediatePropagation();
                        }, true);
                    }
                    if (ret) {
                        ret.addEventListener('change', function(e) {
                            localStorage.setItem('returnDatetime', ret.value);
                            if (e && e.stopImmediatePropagation) e.stopImmediatePropagation();
                        }, true);
                        ret.addEventListener('input', function(e) {
                            localStorage.setItem('returnDatetime', ret.value);
                            if (e && e.stopImmediatePropagation) e.stopImmediatePropagation();
                        }, true);
                    }
                });
                </script>
            </div>

            <div id="formMessage" class="hidden mb-4">
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded relative animate-bounce font-semibold text-center">
                    Please select both Pickup/Delivery and Return date & time before adding to cart.
                </div>
            </div>
            <div class="w-full mt-6">
                <button type="submit" class="bg-[#0086C9] text-white w-full py-3 rounded-lg cursor-pointer hover:bg-blue-700 font-semibold">
                    Search Available Rentals
                </button>
            </div>
        </form>
    </div>
</section>

<!-- EQUIPMENTS -->

<section class="mt-20 px-6 mb-20">
    <div class="max-w-7xl mx-auto">
        <div class="text-center mb-10">
            <h2 class="text-3xl md:text-4xl font-bold mb-3 font-[Barlow]">Instant Mobility</h2>
            <p class="text-gray-600 text-lg">Choose a scooter today and rent it immediately — no cart, no waiting.</p>
            <div class="mt-4">
                <a href="/product-list" class="inline-flex items-center gap-2 rounded-full border border-[#0086C9] px-4 py-2 text-sm font-semibold text-[#0086C9] transition hover:bg-[#0086C9] hover:text-white">
                    See all available products
                    <span aria-hidden="true">&rarr;</span>
                </a>
            </div>
            <div id="instantMobilityTimeInfo" class="mt-4 text-sm md:text-base font-medium text-gray-700 inline-block">
                Rent a scooter for <span class="text-[#0086C9]" id="instantMobilityDate"></span> at <span class="text-[#0086C9]" id="instantMobilityTime"></span>
            </div>
        </div>

            <!-- Responsive Grid: 2 on mobile, 3 on desktop, max 6 items -->
    <div class="grid grid-cols-2 md:grid-cols-3 gap-7 md:gap-10 max-w-5xl mx-auto">
    <?php $count = 0; foreach ($featuredProducts as $item): ?>
        <?php if ($count++ >= 6) break; ?>

                     <div class="group cursor-pointer"
                             data-instant-product
                             data-product-id="<?= (int)$item['product_id'] ?>"
                             data-variation-id="<?= isset($item['featured_variation_id']) ? (int)$item['featured_variation_id'] : 'null' ?>"
                             data-base-price="<?= isset($item['featured_variation_price']) ? $item['featured_variation_price'] : $item['price'] ?>"
                             style="min-height: 370px;"
                             onclick="openProductModal({
                             id: <?= (int)$item['product_id'] ?>,
                             name: '<?= htmlspecialchars($item['product_name'], ENT_QUOTES) ?>',
                             price: <?= isset($item['featured_variation_price']) ? $item['featured_variation_price'] : $item['price'] ?>,
                             image_url: '<?= htmlspecialchars($item['image_url'], ENT_QUOTES) ?>',
                             category: '<?= htmlspecialchars($item['category_name'] ?? '', ENT_QUOTES) ?>',
                             description: '<?= htmlspecialchars($item['description'] ?? '', ENT_QUOTES) ?>',
                             scooter_count: <?= (int)$item['scooter_count'] ?>,
                             variation_id: <?= isset($item['featured_variation_id']) ? (int)$item['featured_variation_id'] : 'null' ?>,
                             variation_name: '<?= isset($item['featured_variation_name']) ? htmlspecialchars($item['featured_variation_name'], ENT_QUOTES) : '' ?>'
                         })">

                    <div class="bg-white rounded-2xl shadow-lg hover:shadow-2xl transition-all duration-300 overflow-hidden hover:-translate-y-2 flex flex-col h-full">
                        <!-- Image Container - preserves ratio, no stretch -->
                        <div class="relative aspect-square bg-gray-50 flex items-center justify-center">
                            <img src="<?= htmlspecialchars($item['image_url']); ?>"
                                     alt="<?= htmlspecialchars($item['product_name']); ?>"
                                     class="w-4/5 h-4/5 object-contain p-4 md:p-8 group-hover:scale-105 transition-transform duration-500">
                        </div>

                        <!-- Card Content -->
                        <div class="p-5 md:p-7 flex flex-col flex-1 justify-between">
                <?php if (!empty($item['category_name'])): ?>
                    <span class="inline-block px-3 py-1 text-[10px] sm:text-xs font-medium text-gray-700 bg-gray-100 rounded-full mb-3">
                    <?= htmlspecialchars($item['category_name']); ?>
                    </span>
                <?php endif; ?>

                <h3 class="font-semibold text-xs sm:text-sm md:text-lg text-gray-900 line-clamp-2 font-[Barlow] mb-1">
                    <?= htmlspecialchars($item['product_name']); ?>
                </h3>
                <?php
                $noteParts = preg_split('/\r\n|\r|\n|\|\|/', (string) ($item['short_description'] ?? '')) ?: [];
                $notes = [];
                foreach ($noteParts as $part) {
                    $line = trim($part);
                    if ($line === '') {
                        continue;
                    }
                    $notes[] = $line;
                    if (count($notes) >= 2) {
                        break;
                    }
                }
                ?>
                <div class="mb-2 min-h-[4.25rem] space-y-1.5 text-center flex flex-col items-center">
                    <?php if (!empty($notes)): ?>
                        <?php foreach ($notes as $note): ?>
                            <div class="grid grid-cols-[20px_minmax(0,1fr)] sm:grid-cols-[24px_minmax(0,1fr)] items-start gap-2 w-full max-w-[18rem] text-left text-sm font-semibold text-[#0086C9] sm:text-base">
                                <img src="/img/check-circle-blue.svg" alt="check" class="w-5 h-5 sm:w-6 sm:h-6 mt-0.5 justify-self-center">
                                <span class="leading-snug"><?= htmlspecialchars($note); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-base font-semibold text-[#0086C9] sm:text-lg text-center"></div>
                    <?php endif; ?>
                </div>

                <div style="min-height:2.25rem;display:flex;align-items:center;justify-content:center;">
                <?php if (!empty($item['featured_variation_name'])): ?>
                    <span class="inline-flex items-center gap-1 mb-2 px-3 py-1 rounded-lg font-semibold text-xs sm:text-sm bg-gradient-to-r from-blue-100 to-blue-200 text-[#0086C9] border border-blue-300 shadow-sm">
                        <svg class="w-4 h-4 text-[#0086C9] opacity-70" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg>
                        <?= htmlspecialchars($item['featured_variation_name']); ?>
                    </span>
                <?php else: ?>
                    <!-- Reserve space for badge for uniform height -->
                    <span class="mb-2" style="opacity:0;">&nbsp;</span>
                <?php endif; ?>
                </div>

                <div class="flex flex-col gap-2 mt-2">
                                    <div class="flex flex-row items-end justify-between w-full">
                                        <div class="flex flex-col items-start justify-center">
                                                <div class="flex flex-col w-full mb-2">
                                                    <span class="text-xs sm:text-sm md:text-base font-semibold tracking-wide text-gray-600 mb-1 uppercase letter-spacing-1">Select Rental Duration</span>
                                                    <div class="relative">
                                                        <select class="instant-days-dropdown w-full px-3 py-2 sm:px-4 sm:py-2 rounded-xl border border-gray-300 text-sm sm:text-base font-semibold bg-white focus:outline-none focus:ring-2 focus:ring-[#0086C9] shadow transition-all duration-150 cursor-pointer appearance-none pr-10" id="instant-days-<?= (int)$item['product_id'] ?>-<?= isset($item['featured_variation_id']) ? (int)$item['featured_variation_id'] : 'null' ?>">
                                                            <?php for ($d = 1; $d <= 31; $d++): ?>
                                                                <option value="<?= $d ?>"><?= $d ?> Day<?= $d > 1 ? 's' : '' ?></option>
                                                            <?php endfor; ?>
                                                        </select>
                                                        <span class="pointer-events-none absolute right-2 sm:right-3 top-1/2 transform -translate-y-1/2">
                                                            <img src="/img/arrow-down.svg" alt="▼" class="w-4 h-4 sm:w-5 sm:h-5" />
                                                        </span>
                                                    </div>
                                                </div>
                                                <span class="instant-mobility-price text-base sm:text-lg md:text-xl font-bold text-[#0086C9] equipment-price"
                                                            data-product-id="<?= (int)$item['product_id'] ?>"
                                                            data-variation-id="<?= isset($item['featured_variation_id']) ? (int)$item['featured_variation_id'] : 'null' ?>"
                                                            data-base-price="<?= isset($item['featured_variation_price']) ? $item['featured_variation_price'] : $item['price'] ?>">
                                                        $<?= number_format(isset($item['featured_variation_price']) ? $item['featured_variation_price'] : $item['price'], 2); ?>
                                                </span>
                                        </div>
                                    </div>
                  <div class="flex w-full mt-2">
                    <?php if (!empty($item['scooter_count'])): ?>
                    <button type="button"
                        class="text-white w-full px-3 py-2 rounded-lg text-xs sm:text-sm md:text-base font-medium bg-[#0086C9] transition shadow-md cursor-pointer hover:bg-blue-700"
                        onclick="fastCheckoutProduct(<?= htmlspecialchars(json_encode([
                            'id' => $item['product_id'],
                            'name' => $item['product_name'],
                            'price' => isset($item['featured_variation_price']) ? $item['featured_variation_price'] : $item['price'],
                            'image_url' => $item['image_url'],
                            'scooter_count' => (int)$item['scooter_count'],
                            'variation_name' => $item['featured_variation_name'] ?? null,
                            'variation_id' => $item['featured_variation_id'] ?? null
                        ]), ENT_QUOTES, 'UTF-8') ?>, event)">
                        Rent Now
                    </button>
                    <?php else: ?>
                    <span class="text-[10px] sm:text-xs md:text-sm font-medium text-red-600 w-full text-center">Out of stock</span>
                    <?php endif; ?>
                  </div>
                </div>
            <script>
                // --- Dynamic Equipment Price Update Based on Date Selection ---
                function updateDaysAndPrices() {
                    const pickup = document.getElementById('pickupDatetime')?.value || localStorage.getItem('pickupDatetime');
                    const ret = document.getElementById('returnDatetime')?.value || localStorage.getItem('returnDatetime');

                    if (!pickup || !ret) return;

                    const start = new Date(pickup);
                    const end = new Date(ret);
                    let days = Math.ceil((end - start) / (1000 * 60 * 60 * 24));
                    days = days > 0 ? days : 1;

                    document.querySelectorAll('.equipment-price').forEach(span => {
                        const basePrice = parseFloat(span.dataset.basePrice);
                        const productId = span.dataset.productId;
                        const variationId = span.dataset.variationId;

                        let price = basePrice;
                        if (window.rentalPrices?.[productId]?.[variationId]) {
                            price = getTieredPrice(productId, variationId, days);
                            if (price === null) price = basePrice;
                        }

                        span.textContent = `$${Number(price).toFixed(2)}`;

                        const daysBadge = span.closest('div').querySelector('.equipment-days');
                        if (daysBadge) {
                            daysBadge.textContent = `${days} day${days > 1 ? 's' : ''}`;
                        }
                    });
                }

                document.addEventListener('DOMContentLoaded', updateDaysAndPrices);
                window.updateDaysAndPrices = updateDaysAndPrices;
                // Attach event listeners for instant update
                document.addEventListener('DOMContentLoaded', function() {
                    var pickup = document.getElementById('pickupDatetime');
                    var ret = document.getElementById('returnDatetime');
                    if (pickup) {
                        pickup.addEventListener('change', updateDaysAndPrices);
                        pickup.addEventListener('input', updateDaysAndPrices);
                    }
                    if (ret) {
                        ret.addEventListener('change', updateDaysAndPrices);
                        ret.addEventListener('input', updateDaysAndPrices);
                    }
                });

            </script>
            </div>
          </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <script>
        // Display current date and nearest 15-min interval for Instant Mobility
        document.addEventListener('DOMContentLoaded', function() {
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
            const nearest = getNearest15Min();
            const dateStr = nearest.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
            const timeStr = nearest.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit', hour12: false });
            document.getElementById('instantMobilityDate').textContent = dateStr;
            document.getElementById('instantMobilityTime').textContent = timeStr;
        });
    </script>
</section>

<!-- Booking Made Simple -->
<section class="py-20 px-6 bg-gradient-to-b from-white to-gray-50">
    <div class="max-w-7xl mx-auto text-center">
        <h2 class="text-3xl md:text-4xl font-bold mb-4 font-[Barlow] text-gray-900">
            Booking Made Simple
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-5xl mx-auto">
            <!-- Step 1 -->
            <div class="step-card bg-white rounded-2xl shadow-lg p-8 text-center opacity-0 translate-y-10 transition-all duration-700 ease-out">
                <div class="text-6xl md:text-7xl font-black bg-gradient-to-br from-[#0086C9] to-blue-600 bg-clip-text text-transparent mb-6">
                    1
                </div>
                <svg class="w-20 h-20 mx-auto mb-6 text-[#0086C9]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                </svg>
                <h3 class="text-xl md:text-2xl font-semibold mb-3 text-gray-800 font-[Barlow]">
                    Tell us when you need it
                </h3>
                <p class="text-gray-600 leading-relaxed">
                    Choose when you need the scooter.<br>Plan your rental schedule.
                </p>
            </div>

            <!-- Step 2 -->
            <div class="step-card bg-white rounded-2xl shadow-lg p-8 text-center opacity-0 translate-y-10 transition-all duration-700 ease-out" style="transition-delay: 200ms;">
                <div class="text-6xl md:text-7xl font-black bg-gradient-to-br from-[#0086C9] to-blue-600 bg-clip-text text-transparent mb-6">
                    2
                </div>
                <svg class="w-20 h-20 mx-auto mb-6 text-[#0086C9]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"></circle>
                    <circle cx="12" cy="12" r="4"></circle>
                    <path d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32l1.41 1.41M2 12h2m16 0h2"></path>
                </svg>
                <h3 class="text-xl md:text-2xl font-semibold mb-3 text-gray-800 font-[Barlow]">
                    Select your scooter
                </h3>
                <p class="text-gray-600 leading-relaxed">
                    Pick the perfect ride for your trip.<br>Find the equipment that fits your needs.
                </p>
            </div>

            <!-- Step 3 -->
            <div class="step-card bg-white rounded-2xl shadow-lg p-8 text-center opacity-0 translate-y-10 transition-all duration-700 ease-out" style="transition-delay: 400ms;">
                <div class="text-6xl md:text-7xl font-black bg-gradient-to-br from-[#0086C9] to-blue-600 bg-clip-text text-transparent mb-6">
                    3
                </div>
                <svg class="w-20 h-20 mx-auto mb-6 text-[#0086C9]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <h3 class="text-xl md:text-2xl font-semibold mb-3 text-gray-800 font-[Barlow]">
                    Complete your booking
                </h3>
                <p class="text-gray-600 leading-relaxed">
                    Review your details and confirm your booking.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- TIPS AND TROUBLE -->
<section class="px-4 md:px-6 py-8 md:py-10 flex flex-col items-center font-[Barlow]">
    <?php
    if (!function_exists('formatTipsHomePreview')) {
        function formatTipsHomePreview(?string $raw): string
        {
            if ($raw === null || trim($raw) === '') {
                return '';
            }

            $text = str_replace(["\r\n", "\r"], "\n", $raw);
            $text = preg_replace('/\*header size\*/i', '', $text);
            $text = preg_replace('/\*bold\*(.*?)\*end of bold\*/i', '$1', $text);
            $text = preg_replace('/\*bullet\*/i', '', $text);
            $text = preg_replace('/\*link\*(.*?)\|(https?:\/\/[^\s]+)\*end of link\*/i', '$1', $text);
            $text = preg_replace('/\*link\*(.*?)\*end of link\*/i', '$1', $text);
            $text = preg_replace('/\[(.*?)\]\((https?:\/\/[^\s)]+)\)/i', '$1', $text);
            $text = preg_replace('/\*\*(.*?)\*\*/', '$1', $text);
            $text = preg_replace('/\s+/', ' ', $text);

            return trim($text);
        }
    }

    ?>
    <div class="w-full max-w-6xl">
        <div class="text-center mb-8">
            <h3 class="text-2xl md:text-3xl font-semibold mb-2"><?= htmlspecialchars($tipsSection['heading'] ?? 'Tips & Troubleshooting') ?></h3>
            <p class="text-sm md:text-base text-gray-600 max-w-2xl mx-auto">
                <?= htmlspecialchars($tipsSection['description'] ?? 'Treat candidates with a rich careers site and a wonderful application process.') ?>
            </p>
        </div>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach (array_slice($tipsArticles ?? [], 0, 3) as $article): ?>
                <?php
                $thumb = trim((string) ($article['image_path'] ?? ''));
                $preview = formatTipsHomePreview($article['description'] ?? '');
                if (mb_strlen($preview) > 120) {
                    $preview = rtrim(mb_substr($preview, 0, 120)) . '...';
                }
                ?>
                <a href="/tips-troubleshooting?article=<?= (int) $article['id'] ?>" class="group relative block h-72 overflow-hidden rounded-2xl shadow-md transition-all duration-300 hover:-translate-y-1 hover:shadow-xl">
                    <?php if ($thumb !== ''): ?>
                        <img src="<?= htmlspecialchars($thumb) ?>" alt="<?= htmlspecialchars($article['title']) ?>" class="absolute inset-0 h-full w-full object-cover transition-transform duration-500 group-hover:scale-105">
                    <?php else: ?>
                        <div class="absolute inset-0 bg-gradient-to-br from-[#0d3954] to-[#062B41]"></div>
                    <?php endif; ?>
                    <div class="absolute inset-0 bg-gradient-to-t from-black/75 via-black/35 to-transparent"></div>
                    <div class="relative flex h-full flex-col justify-end p-5 text-white">
                        <h4 class="line-clamp-2 min-h-[3.5rem] text-lg font-bold leading-tight drop-shadow-sm"><?= htmlspecialchars($article['title']) ?></h4>
                        <p class="mt-2 line-clamp-2 min-h-[3.25rem] text-base text-white/90 drop-shadow-sm"><?= htmlspecialchars($preview) ?></p>
                        <span class="mt-3 inline-flex items-center gap-2 text-lg font-semibold text-white">
                            Learn more
                            <span aria-hidden="true">&rarr;</span>
                        </span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- TESTIMONIAL -->
<section class="px-4 py-16 bg-gray-50">
    <div class="max-w-6xl mx-auto flex flex-col items-center gap-10">

        <!-- Section Header -->
        <div class="text-center">
            <h2 class="text-3xl md:text-4xl font-bold font-[Barlow] text-[#062C41]">
                What Our Customers Say
            </h2>
            <p class="mt-2 text-gray-600 font-[Barlow]">
                Trusted by thousands of satisfied customers across Las Vegas
            </p>
        </div>

        <!-- Carousel Wrapper -->
        <div class="relative w-full flex items-center" style="min-height:320px;">

            <!-- Prev Button -->
            <button id="testimonial-prev"
                class="hidden md:flex absolute -left-3 md:-left-16 top-1/2 -translate-y-1/2 z-10
                       bg-white rounded-full p-1 md:p-3
                       shadow-lg border
                       text-[#0086C9]
                       opacity-60 hover:opacity-100 hover:scale-110
                       transition-all duration-200"
                style="display:none;">
                <svg class="w-4 h-4 md:w-6 md:h-6" fill="none" stroke="currentColor" stroke-width="2"
                     viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M15 19l-7-7 7-7"/>
                </svg>
            </button>

            <!-- Testimonials -->
            <div id="testimonial-carousel"
                class="
                    flex flex-col gap-4 w-full font-[Barlow]
                    md:flex-row md:gap-8
                    md:overflow-visible md:justify-center
                ">

                <?php 
                if (!empty($testimonials) && is_array($testimonials)) {
                    shuffle($testimonials);
                }
                ?>

                <?php if (!empty($testimonials)): ?>
                    <?php foreach ($testimonials as $idx => $t): ?>
                        <div class="testimonial-item
                                bg-white
                                p-4 md:p-8
                                rounded-2xl
                                shadow-md
                                w-full
                                flex flex-col
                                transition-all duration-300
                                hover:-translate-y-2 hover:shadow-xl
                                border border-gray-100
                                md:max-w-md md:min-w-[340px]"
                        data-index="<?= $idx ?>"
                        style="display: <?= $idx < 2 ? 'flex' : 'none' ?>;">

                            <!-- Stars -->
                            <div class="flex items-center gap-1 mb-4">
                                <?php for ($i = 0; $i < $t['star_rating']; $i++): ?>
                                    <span class="text-yellow-500 text-sm md:text-lg">&#9733;</span>
                                <?php endfor; ?>
                                <?php for ($i = $t['star_rating']; $i < 5; $i++): ?>
                                    <span class="text-gray-300 text-lg">&#9733;</span>
                                <?php endfor; ?>
                            </div>

                            <!-- Quote -->
                            <p class="text-gray-700 text-sm md:text-base leading-relaxed flex-1">                            
                                “<?= nl2br(htmlspecialchars($t['review_text'])) ?>”
                            </p>

                            <!-- Divider -->
                            <div class="mt-6 h-px bg-gray-200"></div>

                            <!-- Name -->
                            <p class="mt-3 md:mt-4 font-semibold text-sm md:text-base text-[#0086C9]">
                                — <?= htmlspecialchars($t['reviewer_name']) ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-gray-500">No testimonials yet.</p>
                <?php endif; ?>
            </div>

            <!-- Next Button -->
            <button id="testimonial-next"
                class="absolute -right-3 md:-right-16 top-1/2 -translate-y-1/2 z-10
                       bg-white rounded-full p-1 md:p-3
                       shadow-lg border
                       text-[#0086C9]
                       opacity-60 hover:opacity-100 hover:scale-110
                       transition-all duration-200 cursor-pointer"
                style="display:none;">
                <svg class="w-4 h-4 md:w-6 md:h-6" fill="none" stroke="currentColor" stroke-width="2"
                     viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M9 5l7 7-7 7"/>
                </svg>
            </button>

        </div>
    </div>
</section>

<script>
// --- Testimonial Carousel with Sliding Animation ---
document.addEventListener('DOMContentLoaded', function() {
    const items = document.querySelectorAll('#testimonial-carousel .testimonial-item');
    const prevBtn = document.getElementById('testimonial-prev');
    const nextBtn = document.getElementById('testimonial-next');
    let startIdx = 0;
    let animating = false;
    function showTestimonials(direction = null) {
        if (animating) return;
        animating = true;
        const visible = [];
        items.forEach((item, idx) => {
            if (idx >= startIdx && idx < startIdx + 3) visible.push(item);
        });
        // Animate out current
        if (direction) {
            visible.forEach(item => {
                item.classList.remove('slide-in-left', 'slide-in-right');
                item.classList.add(direction === 'left' ? 'slide-out-left' : 'slide-out-right');
            });
            setTimeout(() => {
                visible.forEach(item => {
                    item.style.display = 'none';
                    item.classList.remove('slide-out-left', 'slide-out-right');
                });
                // Animate in new
                items.forEach((item, idx) => {
                    if (idx >= startIdx && idx < startIdx + 3) {
                        item.style.display = 'flex';
                        item.classList.add(direction === 'left' ? 'slide-in-right' : 'slide-in-left');
                        setTimeout(() => {
                            item.classList.remove('slide-in-left', 'slide-in-right');
                        }, 400);
                    } else {
                        item.style.display = 'none';
                    }
                });
                animating = false;
            }, 400);
        } else {
            items.forEach((item, idx) => {
                item.style.display = (idx >= startIdx && idx < startIdx + 3) ? 'flex' : 'none';
                item.classList.remove('slide-in-left', 'slide-in-right', 'slide-out-left', 'slide-out-right');
            });
            animating = false;
        }
        prevBtn.style.display = (items.length > 3) ? 'block' : 'none';
        nextBtn.style.display = (items.length > 3) ? 'block' : 'none';
    }
    function nextTestimonials() {
        if (animating) return;
        const oldIdx = startIdx;
        startIdx += 3;
        if (startIdx >= items.length) startIdx = 0;
        showTestimonials('left');
    }
    function prevTestimonials() {
        if (animating) return;
        const oldIdx = startIdx;
        startIdx -= 3;
        if (startIdx < 0) startIdx = Math.max(0, items.length - (items.length % 3 || 3));
        showTestimonials('right');
    }
    nextBtn && nextBtn.addEventListener('click', nextTestimonials);
    prevBtn && prevBtn.addEventListener('click', prevTestimonials);
    showTestimonials();
});
</script>

<style>
.slide-in-left {
    animation: slideInLeftTestimonial 0.4s cubic-bezier(0.4,0,0.2,1);
}
.slide-in-right {
    animation: slideInRightTestimonial 0.4s cubic-bezier(0.4,0,0.2,1);
}
.slide-out-left {
    animation: slideOutLeftTestimonial 0.4s cubic-bezier(0.4,0,0.2,1);
}
.slide-out-right {
    animation: slideOutRightTestimonial 0.4s cubic-bezier(0.4,0,0.2,1);
}
@keyframes slideInLeftTestimonial {
    from { opacity: 0; transform: translateX(60px); }
    to { opacity: 1; transform: translateX(0); }
}
@keyframes slideInRightTestimonial {
    from { opacity: 0; transform: translateX(-60px); }
    to { opacity: 1; transform: translateX(0); }
}
@keyframes slideOutLeftTestimonial {
    from { opacity: 1; transform: translateX(0); }
    to { opacity: 0; transform: translateX(-60px); }
}
@keyframes slideOutRightTestimonial {
    from { opacity: 1; transform: translateX(0); }
    to { opacity: 0; transform: translateX(60px); }
}
</style>

<!-- FOOTER -->

<!-- Cart Toast (keep this for notifications) -->
<!-- <div id="cartToast" class="fixed bottom-6 right-6 z-50 bg-green-600 text-white px-4 py-2 rounded shadow-lg hidden transition-opacity duration-300"></div> -->

<!-- Messenger Plugin -->
<div id="fb-root"></div>
<div id="fb-customer-chat" class="fb-customerchat"
    attribution="biz_inbox"
    page_id="547099968497805">
</div>
<script>
window.fbAsyncInit = function() {
  FB.init({
    xfbml            : true,
    version          : 'v19.0'
  });
};
(function(d, s, id) {
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) return;
  js = d.createElement(s); js.id = id;
  js.src = "https://connect.facebook.net/en_US/sdk/xfbml.customerchat.js";
  fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'facebook-jssdk'));
</script>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    const pickupInput = document.getElementById('pickupDatetime');
    const returnInput = document.getElementById('returnDatetime');

    // Helper: Get nearest 15-minute interval from now
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

    // Helper: Format time as "H:i"
    function formatTime(date) {
        let h = date.getHours();
        let m = date.getMinutes();
        return (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m;
    }

    // Helper: Check if a date is today
    function isToday(date) {
        const today = new Date();
        return (
            date.getFullYear() === today.getFullYear() &&
            date.getMonth() === today.getMonth() &&
            date.getDate() === today.getDate()
        );
    }

    const pickupPicker = flatpickr(pickupInput, {
        enableTime: true,
        dateFormat: "Y-m-d H:i",
        minDate: getNearest15Min(),
        defaultDate: getNearest15Min(),
        time_24hr: true,
        minuteIncrement: 15,
        onOpen: function(selectedDates, dateStr, instance) {
            const selected = selectedDates[0] || (instance.input.value ? new Date(instance.input.value) : new Date());
            instance.set('minTime', isToday(selected) ? formatTime(getNearest15Min()) : null);
        },
        onChange: function(selectedDates, dateStr, instance) {
            if (selectedDates.length > 0) {
                // Update minTime based on selected date
                instance.set('minTime', isToday(selectedDates[0]) ? formatTime(getNearest15Min()) : null);
                // Set minDate of return picker to selected pickup date/time
                returnPicker.set('minDate', selectedDates[0]);
                // If return date/time is before pickup, clear it
                if (returnInput.value && new Date(returnInput.value) < selectedDates[0]) {
                    returnInput.value = '';
                    returnPicker.clear();
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
        onOpen: function(selectedDates, dateStr, instance) {
            const selected = selectedDates[0] || (instance.input.value ? new Date(instance.input.value) : new Date());
            instance.set('minTime', isToday(selected) ? formatTime(getNearest15Min()) : null);
        },
        onChange: function(selectedDates, dateStr, instance) {
            if (selectedDates.length > 0) {
                instance.set('minTime', isToday(selectedDates[0]) ? formatTime(getNearest15Min()) : null);
            }
        }
    });
</script>

<!-- <script>
const carousel = document.getElementById('carousel');
const leftBtn = document.getElementById('slideLeft');
const rightBtn = document.getElementById('slideRight');
leftBtn.addEventListener('click', () => {
  carousel.scrollBy({ left: -300, behavior: 'smooth' });
});
rightBtn.addEventListener('click', () => {
  carousel.scrollBy({ left: 300, behavior: 'smooth' });
});
</script> -->

<script>
document.addEventListener('DOMContentLoaded', function() {
    const rentNowBtn = document.getElementById('rentNowBtn');
    const heroRentNowBtn = document.getElementById('heroRentNowBtn');
    const rentalForm = document.getElementById('rentalForm');
    const rentalFormCard = document.getElementById('rentalFormCard');
    const overlay = document.getElementById('formOverlay');

    function emphasizeForm(e) {
        e.preventDefault();
        rentalForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
        overlay.classList.remove('hidden');
        rentalFormCard.classList.add(
            'ring-4', 'ring-blue-400', 'shadow-2xl', 'z-50', 'relative'
        );
        setTimeout(() => {
            overlay.classList.add('hidden');
            rentalFormCard.classList.remove(
                'ring-4', 'ring-blue-400', 'shadow-2xl', 'z-50', 'relative'
            );
        }, 5000);
    }

    if (rentNowBtn) {
        rentNowBtn.addEventListener('click', emphasizeForm);
    }
    if (heroRentNowBtn) {
        heroRentNowBtn.addEventListener('click', emphasizeForm);
    }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const pickupInput = document.getElementById('pickupDatetime');
    const returnInput = document.getElementById('returnDatetime');
    // Load saved values on page load
    if (pickupInput && localStorage.getItem('pickupDatetime')) {
        pickupInput.value = localStorage.getItem('pickupDatetime');
    }
    if (returnInput && localStorage.getItem('returnDatetime')) {
        returnInput.value = localStorage.getItem('returnDatetime');
    }
    // Save values to localStorage on change
    if (pickupInput) {
        pickupInput.addEventListener('change', function() {
            localStorage.setItem('pickupDatetime', pickupInput.value);
        });
    }
    if (returnInput) {
        returnInput.addEventListener('change', function() {
            localStorage.setItem('returnDatetime', returnInput.value);
        });
    }
});
</script>

<script>
function emphasizeRentalForm() {
    const rentalForm = document.getElementById('rentalForm');
    const rentalFormCard = document.getElementById('rentalFormCard');
    if (rentalForm && rentalFormCard) {
        rentalForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
        rentalFormCard.classList.add('ring-4', 'ring-blue-400', 'shadow-2xl', 'z-50', 'relative');
        setTimeout(() => {
            rentalFormCard.classList.remove('ring-4', 'ring-blue-400', 'shadow-2xl', 'z-50', 'relative');
        }, 3000);
    }
}
window.emphasizeRentalForm = emphasizeRentalForm; // Make it global
</script>

<script>
document.querySelector('form[action="/search"]').addEventListener('submit', function(e) {
    const pickup = document.getElementById('pickupDatetime').value;
    const ret = document.getElementById('returnDatetime').value;
    if (!pickup || !ret) {
        e.preventDefault();
        const msg = document.getElementById('formMessage');
        if (msg) {
            msg.classList.remove('hidden');
            setTimeout(() => msg.classList.add('hidden'), 3000);
        }
        if (typeof emphasizeRentalForm === 'function') emphasizeRentalForm();
    }
});

// Animate step cards on scroll into view
document.addEventListener('DOMContentLoaded', () => {
    const cards = document.querySelectorAll('.step-card');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('opacity-100', 'translate-y-0');
            }
        });
    }, { threshold: 0.2 });

    cards.forEach(card => observer.observe(card));
});

// Make getDaysDiff globally available
function getDaysDiff(pickup, ret) {
    if (!pickup || !ret) return 1;
    const start = new Date(pickup);
    const end = new Date(ret);
    if (isNaN(start) || isNaN(end)) return 1;
    let diff = (end - start) / (1000 * 60 * 60 * 24);
    return diff > 0 ? Math.ceil(diff) : 1;
}

function fastCheckoutProduct(product, event) {
    if (event) event.stopPropagation(); // Prevent card click

    // Empty cart first
    localStorage.removeItem('cart');

    // Use the selected days from the dropdown (if present)
    let days = 1;
    const cardSelector = `.instant-days-dropdown#instant-days-${product.id}-${product.variation_id}`;
    const cardDropdown = document.querySelector(cardSelector);
    if (cardDropdown) {
        days = parseInt(cardDropdown.value) || 1;
    }
    let price = Number(product.price);
    if (window.rentalPrices && window.rentalPrices[product.id] && window.rentalPrices[product.id][product.variation_id]) {
        const tierPrice = getTieredPrice(product.id, product.variation_id, days);
        if (tierPrice !== null) price = Number(tierPrice);
    }

    // Set pickup and return date in localStorage based on current date/time and days
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
    const pickupDate = getNearest15Min();
    const returnDate = new Date(pickupDate);
    returnDate.setDate(returnDate.getDate() + days);
    // Format as "Y-m-d H:i"
    function pad(n) { return n < 10 ? '0' + n : n; }
    function formatDate(date) {
        return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate()) + ' ' + pad(date.getHours()) + ':' + pad(date.getMinutes());
    }
    localStorage.setItem('pickupDatetime', formatDate(pickupDate));
    localStorage.setItem('returnDatetime', formatDate(returnDate));

    // Prepare cart with only this product, including variation info if present
    const cartItem = {
        id: product.id,
        name: product.variation_name ? product.name + ' - ' + product.variation_name : product.name,
        price: price,
        qty: 1,
        image_url: product.image_url,
        scooter_count: product.scooter_count
    };
    if (product.variation_id) cartItem.variation_id = product.variation_id;
    if (product.variation_name) cartItem.variation_name = product.variation_name;
    const cart = [cartItem];
    localStorage.setItem('cart', JSON.stringify(cart));

    // Redirect to checkout
    window.location.href = '/checkout';
}

function getTieredPrice(productId, variationId, days) {
    if (!window.rentalPrices || !window.rentalPrices[productId] || !window.rentalPrices[productId][variationId]) return null;
    const tiers = window.rentalPrices[productId][variationId];
    days = Math.min(Math.max(parseInt(days, 10) || 1, 1), 31);
    const key = String(days);
    if (!Object.prototype.hasOwnProperty.call(tiers, key)) return null;
    return Number(tiers[key]);
}
</script>

<?php if (isset($rentalPrices)): ?>
<script>
window.rentalPrices = <?= json_encode($rentalPrices) ?>;
</script>

<?php endif; ?>

<script>
// --- Instant Mobility Price Update Logic (Dropdown Version) ---
document.addEventListener('DOMContentLoaded', function() {
    function updateInstantMobilityPrices() {
        const cards = document.querySelectorAll('[data-instant-product]');
        cards.forEach(card => {
            const productId = card.getAttribute('data-product-id');
            const variationId = card.getAttribute('data-variation-id');
            let price = Number(card.getAttribute('data-base-price'));
            const dropdown = card.querySelector('.instant-days-dropdown');
            let days = 1;
            if (dropdown) {
                days = parseInt(dropdown.value) || 1;
            }
            if (window.rentalPrices && window.rentalPrices[productId] && window.rentalPrices[productId][variationId]) {
                if (typeof getTieredPrice === 'function') {
                    const tierPrice = getTieredPrice(productId, variationId, days);
                    if (tierPrice !== null) price = Number(tierPrice);
                }
            }
            // Update price element in card
            const priceElem = card.querySelector('.instant-mobility-price');
            if (priceElem) {
                priceElem.textContent = '$' + price.toFixed(2);
            }
        });
    }

    // Attach listeners to dropdowns
    document.querySelectorAll('.instant-days-dropdown').forEach(dropdown => {
        dropdown.addEventListener('change', updateInstantMobilityPrices);
        // Prevent dropdown click from triggering modal
        dropdown.addEventListener('click', function(e) { e.stopPropagation(); });
        dropdown.addEventListener('mousedown', function(e) { e.stopPropagation(); });
        dropdown.addEventListener('touchstart', function(e) { e.stopPropagation(); });
    });

    // Initial update on page load
    updateInstantMobilityPrices();
});
</script>


