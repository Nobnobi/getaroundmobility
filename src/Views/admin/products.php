<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$role = strtolower($_SESSION['admin_role'] ?? '');
$isStaff = ($role === 'staff');
// echo('ROLE: ' . $role . ' | isStaff: ' . ($isStaff ? 'yes' : 'no'));
?>

    <div class="flex flex-1 items-center justify-center w-full">
        <div class="bg-white rounded-2xl shadow-xl p-10 w-full max-w-7xl mx-auto border border-gray-200">
            <header class="mb-8 flex flex-col md:flex-row md:justify-between md:items-center gap-4">
                <h1 class="text-3xl font-bold text-[#062B41] tracking-tight">Products</h1>
                <input type="text" id="searchInput" placeholder="Search product..." class="border border-gray-300 rounded-lg px-4 py-2 w-full md:w-72 focus:ring-2 focus:ring-[#062B41] focus:outline-none">
            </header>
            <div class="bg-gray-50 rounded-xl shadow-inner p-6">
                <div class="flex flex-col md:flex-row md:items-center gap-4 mb-6">
                    <h2 class="text-xl font-bold text-gray-800">Products Table</h2>
                    <div class="flex gap-2">
                        <?php if (!$isStaff): ?>
                            <button type="button" id="editBtn" class="bg-[#0086C9] text-white px-4 py-2 rounded-lg shadow hover:bg-[#006a9c] transition-colors cursor-pointer">Edit</button>
                            <button type="button" id="addBtn" class="bg-green-500 text-white px-4 py-2 rounded-lg shadow hover:bg-green-600 transition-colors cursor-pointer" style="display:none;">Add Product</button>
                        <?php endif; ?>
                    </div>
                </div>
                <form method="post" action="/admin/products/save" id="productForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                    <input type="hidden" name="deleted_ids" id="deletedIds">
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white rounded-xl shadow text-center" id="productTable">
                            <thead class="bg-[#062B41] text-white">
                                <tr>
                                    <th class="py-3 px-4 rounded-tl-xl">Product ID</th>
                                    <th class="py-3 px-4">Product Name</th>
                                    <th class="py-3 px-4">Category</th>
                                    <th class="py-3 px-4">Price/day ($)</th>
                                    <th class="py-3 px-4">Quantity</th>
                                    <th class="py-3 px-4">Description</th>
                                    <th class="py-3 px-4">Image URL</th>
                                    <?php if (!$isStaff): ?>
                                        <th class="py-3 px-4 rounded-tr-xl">Actions</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                <tr class="hover:bg-gray-100 transition-colors">
                                    <td class="py-2 px-4 border-b border-gray-200 font-semibold text-gray-700"><?= $product['product_id'] ?></td>
                                    <td class="py-2 px-4 border-b border-gray-200 whitespace-normal break-words min-w-[220px] max-w-[500px]">
                                        <input type="text" name="product_name[<?= $product['product_id'] ?>]" value="<?= htmlspecialchars($product['product_name']) ?>" class="border border-gray-300 rounded-lg px-4 py-2 w-full text-base focus:ring-2 focus:ring-[#062B41] focus:outline-none" style="min-width:180px;max-width:480px;" disabled>
                                    </td>
                                    <td class="py-2 px-4 border-b border-gray-200 whitespace-normal break-words min-w-[160px] max-w-[320px]">
                                        <select name="product_category_id[<?= $product['product_id'] ?>]" class="border border-gray-300 rounded-lg px-4 py-2 w-full text-base focus:ring-2 focus:ring-[#062B41] focus:outline-none" style="min-width:140px;max-width:300px;" disabled>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?= $cat['category_id'] ?>" <?= $cat['category_id'] == $product['product_category_id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($cat['category_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td class="py-2 px-4 border-b border-gray-200">
                                        <input type="number" step="0.01" name="price[<?= $product['product_id'] ?>]" value="<?= $product['price'] ?>" class="border border-gray-300 rounded-lg px-2 py-1 w-full text-xs focus:ring-2 focus:ring-[#062B41] focus:outline-none" disabled>
                                    </td>
                                    <td class="py-2 px-4 border-b border-gray-200">
                                        <input type="number" 
                                               name="stock_quantity[<?= $product['product_id'] ?>]" 
                                               value="<?= $product['scooter_count'] ?>" 
                                               class="border border-gray-300 rounded-lg px-2 py-1 w-full text-xs bg-gray-100 cursor-not-allowed" 
                                               readonly>
                                    </td>
                                    <td class="py-2 px-4 border-b border-gray-200">
                                        <input type="text" name="description[<?= $product['product_id'] ?>]" value="<?= htmlspecialchars($product['description'] ?? '') ?>" class="border border-gray-300 rounded-lg px-2 py-1 w-full text-xs focus:ring-2 focus:ring-[#062B41] focus:outline-none" disabled>
                                    </td>
                                    <td class="py-2 px-4 border-b border-gray-200">
                                        <input type="hidden" name="image_url[<?= $product['product_id'] ?>]" value="<?= htmlspecialchars($product['image_url'] ?? '') ?>" class="product-image-url-input">
                                        <div class="flex items-center gap-2">
                                            <button type="button" class="hover:cursor-pointer product-image-browse-btn px-2 py-1 rounded bg-gray-100 border border-gray-300 hover:bg-gray-200 text-xs transition-colors" data-product-id="<?= $product['product_id'] ?>">Browse</button>
                                            <span class="product-image-filename text-xs text-gray-600"><?= !empty($product['image_url']) ? htmlspecialchars(basename($product['image_url'])) : 'No file chosen.' ?></span>
                                        </div>
                                        <input type="file" class="sr-only product-image-file-input" accept=".jpg,.jpeg,.png,.webp,.svg" data-product-id="<?= $product['product_id'] ?>">
                                    </td>
                                    <?php if (!$isStaff): ?>
                                    <td class="py-2 px-4 border-b border-gray-200">
                                        <button type="button" class="deleteBtn bg-red-500 text-white px-3 py-1 rounded-lg shadow hover:bg-red-600 text-xs transition-colors cursor-pointer" data-id="<?= htmlspecialchars($product['product_id']) ?>" style="display:none;">Delete</button>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (!$isStaff): ?>
                    <div class="mt-6 flex gap-3 justify-end" id="actionButtons" style="display:none;">
                        <button type="submit" id="saveBtn" class="bg-[#0086C9] text-white px-6 py-2 rounded-lg font-semibold shadow hover:bg-[#006a9c] transition-colors cursor-pointer">Save</button>
                        <button type="button" id="cancelBtn" class="bg-gray-400 text-white px-6 py-2 rounded-lg font-semibold shadow hover:bg-gray-500 transition-colors cursor-pointer">Cancel</button>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <?php if (!$isStaff): ?>
    <script>
        // Enable edit mode
        document.getElementById('editBtn').onclick = function() {
            document.querySelectorAll('input[type="text"], input[type="number"], select').forEach(e => e.disabled = false);
            document.querySelectorAll('.deleteBtn').forEach(e => e.style.display = 'inline-block');
            document.getElementById('actionButtons').style.display = 'flex';
            document.getElementById('editBtn').style.display = 'none';
            document.getElementById('addBtn').style.display = 'inline-block';
        };
        // Delete row
        document.querySelectorAll('.deleteBtn').forEach(btn => {
            btn.onclick = function() {
                const row = btn.closest('tr');
                const id = btn.getAttribute('data-id');
                if (id && id !== 'New') {
                    // Add to deleted IDs
                    const deletedInput = document.getElementById('deletedIds');
                    deletedInput.value += (deletedInput.value ? ',' : '') + id;
                }
                row.remove();
            };
        });
        // Add new row at the end of the table
        document.getElementById('addBtn').onclick = function() {
            const table = document.getElementById('productTable').getElementsByTagName('tbody')[0];
            const row = table.insertRow(-1); // Insert at the end
            row.innerHTML = `
                <td class="py-1 px-2 border-b">New</td>
                <td class="py-1 px-2 border-b"><input type="text" name="product_name[new][]" class="border rounded px-1 py-1 w-full text-xs"></td>
                <td class="py-1 px-2 border-b">
                    <select name="product_category_id[new][]" class="border rounded px-1 py-1 w-full text-xs">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td class="py-1 px-2 border-b"><input type="number" step="0.01" name="price[new][]" class="border rounded px-1 py-1 w-full text-xs"></td>
                <td class="py-1 px-2 border-b"><input type="number" name="stock_quantity[new][]" class="border rounded px-1 py-1 w-full text-xs"></td>
                <td class="py-1 px-2 border-b"><input type="text" name="description[new][]" class="border rounded px-1 py-1 w-full text-xs"></td>
                <td class="py-1 px-2 border-b">
                    <input type="hidden" name="image_url[new][]" value="" class="product-image-url-input">
                    <div class="flex items-center gap-2">
                        <button type="button" class="product-image-browse-btn px-2 py-1 rounded bg-gray-100 border border-gray-300 hover:bg-gray-200 text-xs transition-colors" data-row-type="new">Browse</button>
                        <span class="product-image-filename text-xs text-gray-600">No file chosen.</span>
                    </div>
                    <input type="file" class="sr-only product-image-file-input" accept=".jpg,.jpeg,.png,.webp,.svg" data-row-type="new">
                </td>
                <td class="py-1 px-2 border-b"><button type="button" class="deleteBtn bg-red-500 text-white px-2 py-1 rounded hover:bg-red-600 text-xs" style="display:inline-block;">Delete</button></td>
            `;
            row.querySelector('.deleteBtn').onclick = function() {
                row.remove();
            };
        };
        // Cancel button functionality
        document.getElementById('cancelBtn').onclick = function() {
            window.location.reload();
        };

        // Product image file picker functionality
        function initProductImagePickers(container) {
            const browseBtns = container.querySelectorAll('.product-image-browse-btn');
            browseBtns.forEach(btn => {
                btn.removeEventListener('click', productImageBrowseClick);
                btn.addEventListener('click', productImageBrowseClick);
            });

            const fileInputs = container.querySelectorAll('.product-image-file-input');
            fileInputs.forEach(input => {
                input.removeEventListener('change', productImageFileChange);
                input.addEventListener('change', productImageFileChange);
            });
        }

        function productImageBrowseClick(e) {
            e.preventDefault();
            const btn = this;
            const row = btn.closest('tr');
            const fileInput = row.querySelector('.product-image-file-input');
            if (fileInput) {
                fileInput.click();
            }
        }

        function productImageFileChange(e) {
            const fileInput = this;
            const row = fileInput.closest('tr');
            const urlInput = row.querySelector('.product-image-url-input');
            const fileNameSpan = row.querySelector('.product-image-filename');

            if (fileInput.files && fileInput.files.length > 0) {
                const fileName = fileInput.files[0].name;
                fileNameSpan.textContent = fileName;
                urlInput.value = '/img/' + fileName;
                return;
            }

            fileNameSpan.textContent = 'No file chosen.';
            urlInput.value = '';
        }

        // Initialize on page load
        initProductImagePickers(document.getElementById('productTable'));
    </script>
    <?php endif; ?>

    <script>
        // Search functionality
        document.getElementById('searchInput').onkeyup = function() {
            const filter = this.value.toLowerCase();
            document.querySelectorAll('#productTable tbody tr').forEach(row => {
                const input = row.querySelector('input[type="text"]');
                if (input) {
                    const name = input.value.toLowerCase();
                    row.style.display = name.includes(filter) ? '' : 'none';
                }
            });
        };
    </script>
