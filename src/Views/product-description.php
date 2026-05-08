<?php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="/css/output.css" rel="stylesheet">
    <!-- Flatpickr CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <title>Product Description</title>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<div class="max-w-3xl mx-auto mt-10 bg-white rounded shadow p-6">
    <?php if (!$product): ?>
        <p class="text-center text-red-600 mt-10">Product not found.</p>
    <?php else: ?>
        <a href="/search" class="inline-block mb-4 text-blue-600 hover:underline">&larr; Back to Products</a>
        <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['product_name']) ?>" class="w-full h-64 object-cover rounded mb-4">
        <h1 class="text-3xl font-bold mb-2"><?= htmlspecialchars($product['product_name']) ?></h1>
        <span class="inline-block text-xs px-2 py-1 bg-gray-200 rounded-full text-gray-700 mb-2"><?= htmlspecialchars($product['category_name']) ?></span>
        <p class="text-blue-600 font-bold text-xl mb-4">$<?= number_format($product['price'], 2) ?></p>
        <p class="text-gray-700 mb-4"><?= htmlspecialchars($product['description']) ?: 'No description available.' ?></p>
        <div class="flex items-center mb-4">
            <label class="mr-2 text-gray-700">Quantity:</label>
            <button type="button" id="descQtyDecrease" class="px-2 py-1 bg-gray-200 rounded-l hover:bg-gray-300 text-lg font-bold">-</button>
            <input type="number" id="descProductQuantity" min="1" value="1" class="w-16 text-center border-t border-b border-gray-300 py-1">
            <button type="button" id="descQtyIncrease" class="px-2 py-1 bg-gray-200 rounded-r hover:bg-gray-300 text-lg font-bold">+</button>
        </div>
        <button 
            onclick="
                let qty = parseInt(document.getElementById('descProductQuantity').value) || 1;
                for(let i=0;i<qty;i++){
                    addToCart('<?= htmlspecialchars($product['product_name'], ENT_QUOTES) ?>', <?= (int)$product['product_id'] ?>, <?= $product['price'] ?>, '<?= htmlspecialchars($product['image_url'], ENT_QUOTES) ?>');
                }
            " 
            class="bg-[#0086C9] text-white px-6 py-2 rounded hover:bg-blue-700 w-full mb-2">
            Add to Cart
        </button>
    <?php endif; ?>
</div>
<script>
document.getElementById('descQtyDecrease')?.addEventListener('click', function() {
    let qtyInput = document.getElementById('descProductQuantity');
    let qty = parseInt(qtyInput.value) || 1;
    if (qty > 1) qtyInput.value = qty - 1;
});
document.getElementById('descQtyIncrease')?.addEventListener('click', function() {
    let qtyInput = document.getElementById('descProductQuantity');
    let qty = parseInt(qtyInput.value) || 1;
    qtyInput.value = qty + 1;
});
</script>
<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>