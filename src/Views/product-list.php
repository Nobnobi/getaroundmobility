
<div class="mt-20 max-w-7xl mx-auto px-4">   
    <div class="flex flex-col md:flex-row gap-6 pt-5 justify-center">
        <!-- Filters Sidebar -->
        <form id="filterForm" action="" method="get">
            <aside class="bg-white shadow rounded-lg p-4 space-y-6 w-full md:w-72 h-fit max-h-[600px] sticky top-28 flex-shrink-0">
                <h2 class="text-2xl font-semibold font-[Barlow]">Filter</h2>
                <!-- Equipment Type -->
                <div>
                    <label for="category" class="block text-sm font-medium">Equipment Type</label>
                    <select name="category" id="category" class="w-full border border-[#D9D9D9] rounded p-2 font-[Barlow]">
                        <option value="">Select a type</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>" <?= (isset($_GET['category']) && $_GET['category'] == $cat) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Filter Price and Availability Checkbox removed -->
                <!-- Search Button -->
                <button type="submit" class="bg-[#0086C9] text-white w-full py-2 rounded-lg shadow mt-4 font-[Barlow] cursor-pointer">Search</button>
            </aside>
        </form>

        <section class="max-w-7xl px-4 flex-1">
            <h1 class="text-3xl font-bold mb-6 font-[Barlow]">Our Equipments</h1>
            <!-- CATEGORY TAB as a horizontal row with select -->
            <div class="flex flex-wrap items-center gap-2 mb-4 font-[Barlow] font-semibold">
                <a href="?category=all<?= isset($_GET['price_order']) ? '&price_order=' . urlencode($_GET['price_order']) : '' ?>"
                    class="pb-2 px-3 <?= !isset($_GET['category']) || $_GET['category'] === 'all' ? 'text-blue-600 border-b-2 border-blue-600 font-medium' : 'text-gray-600 border-b-2 border-transparent' ?> bg-transparent">View all</a>
                <?php foreach ($categories as $cat): ?>
                    <a href="?category=<?= urlencode($cat) ?><?= isset($_GET['price_order']) ? '&price_order=' . urlencode($_GET['price_order']) : '' ?>"
                    class="pb-2 px-3 <?= (isset($_GET['category']) && $_GET['category'] === $cat) ? 'text-blue-600 border-b-2 border-blue-600 font-medium' : 'text-gray-600 border-b-2 border-transparent hover:text-blue-600 hover:border-blue-600' ?> bg-transparent">
                        <?= htmlspecialchars($cat) ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-8">
                <?php foreach ($products as $item): ?>
                    <div
                        class="bg-white rounded-lg shadow p-4 flex flex-col cursor-pointer transition font-[Barlow] hover:bg-blue-50 hover:shadow-lg"
                        onclick="window.isProductListModal = true; openProductListModal({
                            id: <?= (int)$item['product_id'] ?>,
                            name: '<?= htmlspecialchars($item['product_name'], ENT_QUOTES) ?>',
                            price: <?= $item['price'] ?>,
                            image_url: '<?= htmlspecialchars($item['image_url'], ENT_QUOTES) ?>',
                            category: '<?= htmlspecialchars($item['category_name'] ?? '', ENT_QUOTES) ?>',
                            short_description: '<?= htmlspecialchars($item['short_description'] ?? '', ENT_QUOTES) ?>',
                            description: '<?= htmlspecialchars($item['description'] ?? '', ENT_QUOTES) ?>',
                            total_stock: <?= isset($item['total_stock']) ? (int)$item['total_stock'] : 0 ?>
                        })"
                    >
                        <img src="<?= htmlspecialchars($item['image_url']); ?>" alt="<?= htmlspecialchars($item['product_name']); ?>" class="mb-4 w-full h-60 object-contain rounded">
                        <div class="mb-2">
                            <?php if (!empty($item['category_name'])): ?>
                                <span class="text-xs px-2 py-1 bg-gray-200 rounded-full font-[Barlow]"><?= htmlspecialchars($item['category_name']); ?></span>
                            <?php endif; ?>
                        </div>
                        <h3 class="font-semibold text-lg"><?= htmlspecialchars($item['product_name']); ?></h3>
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

                        <!-- Variations Table -->
                        <?php if (!empty($item['variations'])): ?>
                            <div class="mb-2">
                                <table class="w-full text-xs border border-gray-200 rounded overflow-hidden">
                                    <thead>
                                        <tr class="bg-gray-50">
                                            <th class="py-1 px-2 text-left">Variation</th>
                                            <th class="py-1 px-2"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($item['variations'] as $var): ?>
                                            <tr>
                                                <td class="py-1 px-2"><?= htmlspecialchars($var['variation_name']); ?></td>
                                                <td class="py-1 px-2"></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <!-- Total Stock and Add to Cart (fallback for no variations) -->
                        <?php /* Unavailable button removed for products with zero stock and no variations */ ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php
            // Build base query string with all current filters except 'page'
            $queryParams = $_GET;
            unset($queryParams['page']);
            $baseUrl = '?' . http_build_query($queryParams);
            ?>
            <?php 
            $totalPages = $total_pages ?? 1;
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
</div>

    

    <!-- Cart Toast (keep this for notifications) -->
    <!-- <div id="cartToast" class="fixed bottom-6 right-6 z-50 bg-green-600 text-white px-4 py-2 rounded shadow-lg hidden transition-opacity duration-300"></div> -->
    <div id="formOverlay" class="fixed inset-0 z-40 hidden transition-opacity duration-500" style="background: rgba(0,0,0,0.7);"></div>


    <!-- Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
            // Ensure isProductListModal is reset when modal closes
            document.addEventListener('DOMContentLoaded', function() {
                const modal = document.getElementById('productModal');
                if (modal) {
                    const observer = new MutationObserver(function(mutations) {
                        mutations.forEach(function(m) {
                            if (m.attributeName === 'class') {
                                if (modal.classList.contains('hidden')) {
                                    window.isProductListModal = false;
                                }
                            }
                        });
                    });
                    observer.observe(modal, { attributes: true });
                }
            });
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
    