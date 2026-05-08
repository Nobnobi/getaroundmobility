<!-- filepath: c:\xampp\htdocs\GetAroundMobility\src\Views\admin\featured-products.php -->

<?php
$role = strtolower($_SESSION['admin_role'] ?? '');
$isStaff = ($role === 'staff');
// echo('ROLE: ' . $role . ' | isStaff: ' . ($isStaff ? 'yes' : 'no'));
?>


    <!-- <?php echo($_SESSION['admin_role']); ?> -->
    <main class="flex-1 p-8">
        <h1 class="text-3xl font-bold mb-8 text-gray-800 flex items-center gap-2">
            Featured Products
        </h1>
        <form class="bg-white p-6 rounded-2xl shadow-xl max-w-3xl mx-auto" method="post" autocomplete="off">
            <div class="mb-4 text-gray-700 text-sm flex items-center gap-2">
                <svg class="w-5 h-5 text-blue-400 inline-block" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M12 20a8 8 0 100-16 8 8 0 000 16z"/></svg>
                Select up to <span class="font-semibold">6 products</span> and their variations to feature on the homepage.
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php for ($i = 0; $i < 6; $i++): ?>
                    <div class="bg-gray-50 rounded-xl p-4 shadow flex flex-col gap-2 relative group border border-gray-200">
                        <div class="absolute -top-3 -left-3 bg-[#0086C9] text-white rounded-full w-8 h-8 flex items-center justify-center font-bold shadow group-hover:scale-110 transition-transform">#<?= $i+1 ?></div>
                        <label class="text-xs font-semibold text-gray-600 mb-1">Product Slot <?= $i+1 ?></label>
                        <select name="product_id[]" class="w-full border border-gray-300 rounded px-3 py-2 mb-1 product-select focus:ring-2 focus:ring-blue-400" data-slot="<?= $i ?>">
                            <option value="">-- Select Product --</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?= $product['product_id'] ?>"
                                    <?= (isset($featuredProductIds[$i]) && $featuredProductIds[$i] == $product['product_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($product['product_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php
                            $selectedProductId = $featuredProductIds[$i] ?? null;
                            $hasVariations = $selectedProductId && !empty($variationsByProduct[$selectedProductId]);
                            $productImage = '';
                            if ($selectedProductId) {
                                foreach ($products as $p) {
                                    if ($p['product_id'] == $selectedProductId) {
                                        $productImage = $p['image_url'] ?? '';
                                        break;
                                    }
                                }
                            }
                        ?>
                        <?php if ($productImage): ?>
                            <img src="<?= htmlspecialchars($productImage) ?>" alt="Product Image" class="w-20 h-20 object-contain rounded-lg border mb-2 mx-auto bg-white shadow-sm">
                        <?php endif; ?>
                        <select name="variation_id[]" class="w-full border border-gray-300 rounded px-3 py-2 mb-1 variation-select focus:ring-2 focus:ring-blue-400" data-slot="<?= $i ?>" style="<?= $hasVariations ? '' : 'display:none;' ?>">
                            <option value="">-- Select Variation --</option>
                            <?php
                                $selectedVariationId = $featuredVariationIds[$i] ?? null;
                                if ($hasVariations) {
                                    foreach ($variationsByProduct[$selectedProductId] as $variation) {
                                        $selected = ($selectedVariationId == $variation['variation_id']) ? 'selected' : '';
                                        echo '<option value="' . $variation['variation_id'] . '" ' . $selected . '>' . htmlspecialchars($variation['variation_name']) . '</option>';
                                    }
                                }
                            ?>
                        </select>
                        <div class="text-xs text-gray-400 mt-1 min-h-[1.5em] variation-hint">
                            <?php if ($hasVariations): ?>
                                <?php if (!$selectedVariationId): ?>
                                    <span class="text-yellow-600">Please select a variation.</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
            <?php if (!$isStaff): ?>
                <button type="submit" class="mt-8 px-8 py-3 bg-[#0086C9] text-white rounded-lg hover:bg-[#006a9c] font-semibold shadow cursor-pointer">Save Featured Products</button>
            <?php else: ?>
                <div class="mt-8 text-gray-500 italic">Staff can only view featured products.</div>
            <?php endif; ?>
        </form>
        <script>
        // Preload all variations by product for JS
        const variationsByProduct = <?php echo json_encode($variationsByProduct); ?>;
        const productsById = <?php echo json_encode(array_column($products, null, 'product_id')); ?>;
        document.querySelectorAll('.product-select').forEach(function(productSelect) {
            productSelect.addEventListener('change', function() {
                const slot = this.getAttribute('data-slot');
                const variationSelect = document.querySelector('.variation-select[data-slot="' + slot + '"]');
                const card = this.closest('.bg-gray-50');
                const productId = this.value;
                // Clear variations
                variationSelect.innerHTML = '<option value="">-- Select Variation --</option>';
                // Update product image
                let img = card.querySelector('img');
                if (img) img.remove();
                if (productId && productsById[productId] && productsById[productId]['image_url']) {
                    img = document.createElement('img');
                    img.src = productsById[productId]['image_url'];
                    img.alt = 'Product Image';
                    img.className = 'w-20 h-20 object-contain rounded-lg border mb-2 mx-auto bg-white shadow-sm';
                    card.insertBefore(img, variationSelect);
                }
                if (productId && variationsByProduct[productId] && variationsByProduct[productId].length > 0) {
                    variationsByProduct[productId].forEach(function(variation) {
                        const opt = document.createElement('option');
                        opt.value = variation.variation_id;
                        opt.textContent = variation.variation_name;
                        variationSelect.appendChild(opt);
                    });
                    variationSelect.style.display = '';
                    card.querySelector('.variation-hint').innerHTML = '<span class="text-yellow-600">Please select a variation.</span>';
                } else {
                    variationSelect.style.display = 'none';
                    card.querySelector('.variation-hint').innerHTML = '';
                }
            });
        });
        // On page load, hide variation selects with no options
        document.querySelectorAll('.variation-select').forEach(function(variationSelect) {
            if (variationSelect.options.length <= 1) {
                variationSelect.style.display = 'none';
            }
        });
        </script>
    </main>