<!-- filepath: c:\xampp\htdocs\GetAroundMobility\src\Views\for-sale.php -->


<div class="flex flex-col md:flex-row gap-6 mt-32 md:mt-5 px-4 md:px-0 justify-center min-h-screen">
    <!-- Filters Sidebar -->
    <form id="filterForm" action="" method="get" class="w-full md:w-auto">
        <aside class="bg-white shadow rounded-lg p-4 space-y-6 w-full md:w-72 h-fit max-h-[600px] sticky top-28 flex-shrink-0">
            <h2 class="text-2xl font-semibold font-[Barlow]">Filter</h2>
            <!-- Equipment Type -->
            <div>
                <label for="category" class="block text-sm font-medium">Equipment Type</label>
                <select name="category" id="category" class="w-full border border-[#D9D9D9] rounded p-2 font-[Barlow]">
                    <option value="">Select a type</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['category_id']) ?>" <?= (isset($_GET['category']) && $_GET['category'] == $cat['category_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['category_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- Filter Price -->
            <div>
                <label for="price_order" class="block text-sm font-medium font-[Barlow]">Filter Price</label>
                <select name="price_order" id="price_order" class="w-full border border-[#D9D9D9] rounded p-2 font-[Barlow]">
                    <option value="">None</option>
                    <option value="1" <?= (isset($_GET['price_order']) && $_GET['price_order'] == '1') ? 'selected' : '' ?>>Highest to Lowest</option>
                    <option value="2" <?= (isset($_GET['price_order']) && $_GET['price_order'] == '2') ? 'selected' : '' ?>>Lowest to Highest</option>
                </select>
            </div>
            <!-- Availability Checkbox -->
            <div>
                <input type="hidden" name="available_only" value="0">
                <label class="inline-flex items-center">
                    <input type="checkbox" name="available_only" value="1"
                        <?= (!isset($_GET['available_only']) || $_GET['available_only'] == '1') ? 'checked' : '' ?>>
                    <span class="ml-2 text-sm font-[Barlow]">Show only available scooters</span>
                </label>
            </div>
            <!-- Search Button -->
            <button type="submit" class="bg-[#0086C9] text-white w-full py-2 rounded-lg shadow mt-4 font-[Barlow] cursor-pointer">Search</button>
        </aside>
    </form>

    <section class="max-w-7xl px-4 flex-1">
        <h1 class="text-3xl font-bold mb-6 font-[Barlow]">Scooters For Sale</h1>
        <!-- CATEGORY TAB as a horizontal row with select -->
        <div class="flex flex-wrap items-center gap-2 mb-4 font-[Barlow] font-semibold">
            <a href="?category=all<?= isset($_GET['price_order']) ? '&price_order=' . urlencode($_GET['price_order']) : '' ?>"
                class="pb-2 px-3 <?= !isset($_GET['category']) || $_GET['category'] === 'all' ? 'text-blue-600 border-b-2 border-blue-600 font-medium' : 'text-gray-600 border-b-2 border-transparent' ?> bg-transparent">View all</a>
            <?php foreach ($categories as $cat): ?>
                <a href="?category=<?= urlencode($cat['category_id']) ?><?= isset($_GET['price_order']) ? '&price_order=' . urlencode($_GET['price_order']) : '' ?>"
                class="pb-2 px-3 <?= (isset($_GET['category']) && $_GET['category'] == $cat['category_id']) ? 'text-blue-600 border-b-2 border-blue-600 font-medium' : 'text-gray-600 border-b-2 border-transparent hover:text-blue-600 hover:border-blue-600' ?> bg-transparent">
                    <?= htmlspecialchars($cat['category_name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-8">
            <?php if (empty($products)): ?>
                <div class="col-span-3 text-center text-gray-500 py-12">
                    <p class="text-lg">No scooters for sale at the moment.</p>
                </div>
            <?php else: ?>
                <?php foreach ($products as $item): ?>
                    <div
                        class="bg-white rounded-lg shadow p-4 flex flex-col cursor-pointer transition font-[Barlow] hover:bg-blue-50 hover:shadow-lg"
                        onclick="openForSaleProductModal({
                            product_id: '<?= (int)$item['product_id'] ?>',
                            product_name: '<?= htmlspecialchars($item['product_name'], ENT_QUOTES) ?>',
                            category_name: '<?= htmlspecialchars($item['category_name'] ?? '', ENT_QUOTES) ?>',
                            description: '<?= htmlspecialchars($item['description'] ?? '', ENT_QUOTES) ?>',
                            price: '<?= htmlspecialchars($item['price'], ENT_QUOTES) ?>',
                            image_url: '<?= htmlspecialchars($item['image_url'], ENT_QUOTES) ?>',
                            available_scooter_count: '<?= isset($item['available_scooter_count']) ? (int)$item['available_scooter_count'] : 0 ?>'
                        })"
                    >
                        <img src="<?= htmlspecialchars($item['image_url']); ?>" alt="<?= htmlspecialchars($item['product_name']); ?>" class="mb-4 w-full h-60 object-contain rounded">
                        <div class="mb-2">
                            <?php if (!empty($item['category_name'])): ?>
                                <span class="text-xs px-2 py-1 bg-gray-200 rounded-full font-[Barlow]"><?= htmlspecialchars($item['category_name']); ?></span>
                            <?php endif; ?>
                        </div>
                        <h3 class="font-semibold text-lg"><?= htmlspecialchars($item['product_name']); ?></h3>
                        <p class="text-sm text-gray-600 mb-3 font-[SFPro]"><?= htmlspecialchars($item['description']); ?></p>
                        <span class="text-blue-600 font-semibold text-sm">$<?= number_format($item['price'], 2); ?></span>
                        <span class="text-gray-500 text-xs ml-2">
                            <?php $count = isset($item['available_scooter_count']) ? (int)$item['available_scooter_count'] : 0; ?>
                            <?= ($count > 0) ? "In stock: {$count}" : "Out of stock" ?>
                        </span>
                        <?php if ($count > 0): ?>
                            <button class="add-to-cart-btn mt-3 w-full bg-[#0086C9] text-white py-2 rounded hover:bg-blue-700 cursor-pointer"
                                onclick="event.stopPropagation(); addForSaleToCart({
                                    product_id: '<?= (int)$item['product_id'] ?>',
                                    product_name: '<?= htmlspecialchars($item['product_name'], ENT_QUOTES) ?>',
                                    category_name: '<?= htmlspecialchars($item['category_name'] ?? '', ENT_QUOTES) ?>',
                                    description: '<?= htmlspecialchars($item['description'] ?? '', ENT_QUOTES) ?>',
                                    price: '<?= htmlspecialchars($item['price'], ENT_QUOTES) ?>',
                                    image_url: '<?= htmlspecialchars($item['image_url'], ENT_QUOTES) ?>',
                                    available_scooter_count: '<?= isset($item['available_scooter_count']) ? (int)$item['available_scooter_count'] : 0 ?>'
                                }, 1);">
                                Add to Cart
                            </button>
                        <?php else: ?>
                            <button class="mt-3 w-full bg-gray-400 text-white py-2 rounded cursor-not-allowed opacity-60" disabled>
                                Unavailable
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php
        $queryParams = $_GET;
        unset($queryParams['page']);
        $baseUrl = '?' . http_build_query($queryParams);
        $totalPages = $total_pages ?? 1;
        $page = $current_page ?? 1;
        if ($totalPages > 1): ?>
            <div class="flex justify-center mt-8 space-x-2 font-[Barlow]">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="<?= $baseUrl ?>&page=<?= $i ?>"
                    class="px-4 py-2 rounded <?= $i == $page ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-blue-100' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<!-- Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Pickup DateTime Picker
        flatpickr("#pickupDatetime", {
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            minDate: "today",
            time_24hr: true,
            minuteIncrement: 15
        });
        // Return DateTime Picker
        flatpickr("#returnDatetime", {
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            minDate: "today",
            time_24hr: true,
            minuteIncrement: 15
        });
    </script>
    
    
    <script>
    // Rent now scroll and highlight effect
    document.addEventListener('DOMContentLoaded', function() {
        const rentNowBtn = document.getElementById('rentNowBtn');
        const productListForm = document.getElementById('productListForm');
        const overlay = document.getElementById('formOverlay');

        if (rentNowBtn && productListForm && overlay) {
            rentNowBtn.addEventListener('click', function(e) {
                e.preventDefault();

                // Smooth scroll to form
                productListForm.scrollIntoView({ behavior: 'smooth', block: 'center' });

                // Emphasize form and dim rest
                overlay.classList.remove('hidden');
                productListForm.classList.add('ring-4', 'ring-blue-400', 'shadow-2xl', 'z-50', 'relative');

                // Remove emphasis after 2 seconds
                setTimeout(() => {
                    overlay.classList.add('hidden');
                    productListForm.classList.remove('ring-4', 'ring-blue-400', 'shadow-2xl', 'z-50', 'relative');
                }, 2000);
            });
        }
    });
    </script>

