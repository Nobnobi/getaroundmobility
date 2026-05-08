<?php
// src/Views/admin/add_product_variation.php
// Fetch products for dropdown (assumes $products is passed from controller)
?>
<div class="max-w-2xl mx-auto mt-12 bg-white p-10 rounded-2xl shadow-xl border border-blue-200">
    <h2 class="text-3xl font-bold mb-8 text-[#062B41] tracking-tight">Add Product Variation</h2>
    <?php if (!empty($error)): ?>
        <div class="mb-6 p-4 bg-red-100 text-red-700 rounded-lg border-l-4 border-red-500 font-semibold"> <?= htmlspecialchars($error) ?> </div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <div class="mb-6 p-4 bg-green-100 text-green-700 rounded-lg border-l-4 border-green-500 font-semibold"> <?= htmlspecialchars($success) ?> </div>
    <?php endif; ?>
    <form method="post" action="" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? ($_SESSION['csrf_token'] ?? '')) ?>">
        <div>
            <label class="block mb-2 font-semibold text-gray-700">Product</label>
            <select name="product_id" class="w-full border border-gray-300 rounded-lg px-4 py-2 text-base focus:ring-2 focus:ring-[#062B41] focus:outline-none" required>
                <option value="">Select Product</option>
                <?php foreach ($products as $product): ?>
                    <option value="<?= $product['product_id'] ?>"> <?= htmlspecialchars($product['product_name']) ?> </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block mb-2 font-semibold text-gray-700">Variation Name</label>
            <input type="text" name="variation_name" class="w-full border border-gray-300 rounded-lg px-4 py-2 text-base focus:ring-2 focus:ring-[#062B41] focus:outline-none" required>
        </div>
        <div>
            <label class="block mb-2 font-semibold text-gray-700">Price</label>
            <input type="number" name="price" step="0.01" class="w-full border border-gray-300 rounded-lg px-4 py-2 text-base focus:ring-2 focus:ring-[#062B41] focus:outline-none" required>
        </div>
        <div class="flex gap-4 justify-end mt-8">
            <button type="submit" class="bg-[#0086C9] text-white px-8 py-2 rounded-lg font-semibold shadow hover:bg-[#006a9c] transition-colors cursor-pointer">Add Variation</button>
            <a href="/admin/product-variations" class="bg-gray-300 text-gray-800 px-8 py-2 rounded-lg font-semibold shadow hover:bg-gray-400 transition-colors inline-block text-center">Cancel</a>
        </div>
    </form>
</div>
