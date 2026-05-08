<div class="flex flex-1 items-center justify-center w-full">
    <div class="bg-white rounded-2xl shadow-xl p-10 w-full max-w-5xl mx-auto border border-gray-200">
        <header class="mb-8 flex flex-col md:flex-row md:justify-between md:items-center gap-4">
            <h1 class="text-3xl font-bold text-[#062B41] tracking-tight">Scooters For Sale</h1>
            <input type="text" id="searchInput" placeholder="Search scooter..." class="border border-gray-300 rounded-lg px-4 py-2 w-full md:w-72 focus:ring-2 focus:ring-[#062B41] focus:outline-none">
        </header>
        <div class="bg-gray-50 rounded-xl shadow-inner p-6">
            <div class="flex flex-col md:flex-row md:items-center gap-4 mb-6">
                <h2 class="text-xl font-bold text-gray-800">Scooters For Sale Table</h2>
                <div class="flex gap-2">
                    <button type="button" id="editBtn" class="bg-[#0086C9] text-white px-4 py-2 rounded-lg shadow hover:bg-[#006a9c] transition-colors cursor-pointer">Edit</button>
                    <button type="button" id="addBtn" class="bg-green-500 text-white px-4 py-2 rounded-lg shadow hover:bg-green-600 transition-colors cursor-pointer" style="display:none;">Add Scooter</button>
                </div>
            </div>
            <form method="post" action="/admin/scooters-for-sale/save" id="scooterSaleForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="deleted_ids" id="deletedIds">
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white rounded-xl shadow text-center" id="scooterSaleTable">
                        <thead class="bg-[#062B41] text-white">
                            <tr>
                                <th class="py-3 px-4 rounded-tl-xl">ID</th>
                                <th class="py-3 px-4">Name</th>
                                <th class="py-3 px-4">Category</th>
                                <th class="py-3 px-4">Price</th>
                                <th class="py-3 px-4">Stock</th>
                                <th class="py-3 px-4">Description</th>
                                <th class="py-3 px-4">Image</th>
                                <th class="py-3 px-4 rounded-tr-xl">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($scooters as $scooter): ?>
                            <tr class="hover:bg-gray-100 transition-colors">
                                <td class="py-2 px-4 border-b border-gray-200 font-semibold text-gray-700"><?= $scooter['product_id'] ?></td>
                                <td class="py-2 px-4 border-b border-gray-200">
                                    <input type="text" name="product_name[<?= $scooter['product_id'] ?>]" value="<?= htmlspecialchars($scooter['product_name']) ?>" class="border border-gray-300 rounded-lg px-2 py-1 w-full text-xs focus:ring-2 focus:ring-[#062B41] focus:outline-none" disabled>
                                </td>
                                <td class="py-2 px-4 border-b border-gray-200">
                                    <select name="product_category_id[<?= $scooter['product_id'] ?>]" class="border border-gray-300 rounded-lg px-2 py-1 w-full text-xs focus:ring-2 focus:ring-[#062B41] focus:outline-none" disabled>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?= $cat['category_id'] ?>" <?= $cat['category_id'] == $scooter['product_category_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cat['category_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="py-2 px-4 border-b border-gray-200">
                                    <input type="number" step="0.01" name="price[<?= $scooter['product_id'] ?>]" value="<?= $scooter['price'] ?>" class="border border-gray-300 rounded-lg px-2 py-1 w-full text-xs focus:ring-2 focus:ring-[#062B41] focus:outline-none" disabled>
                                </td>
                                <td class="py-2 px-4 border-b border-gray-200">
                                    <input type="number" value="<?= isset($scooter['available_scooter_count']) ? (int)$scooter['available_scooter_count'] : 0 ?>" class="border border-gray-300 rounded-lg px-2 py-1 w-full text-xs bg-gray-100" readonly tabindex="-1">
                                </td>
                                <td class="py-2 px-4 border-b border-gray-200">
                                    <input type="text" name="description[<?= $scooter['product_id'] ?>]" value="<?= htmlspecialchars($scooter['description'] ?? '') ?>" class="border border-gray-300 rounded-lg px-2 py-1 w-full text-xs focus:ring-2 focus:ring-[#062B41] focus:outline-none" disabled>
                                </td>
                                <td class="py-2 px-4 border-b border-gray-200">
                                    <input type="hidden" name="image_url[<?= $scooter['product_id'] ?>]" value="<?= htmlspecialchars($scooter['image_url'] ?? '') ?>" class="sale-image-url-input">
                                    <div class="flex items-center gap-2">
                                        <button type="button" class="sale-image-browse-btn px-2 py-1 rounded bg-gray-100 border border-gray-300 hover:bg-gray-200 text-xs transition-colors cursor-pointer" data-product-id="<?= $scooter['product_id'] ?>" style="display:none;">Browse</button>
                                        <span class="sale-image-filename text-xs text-gray-600"><?= !empty($scooter['image_url']) ? htmlspecialchars(basename($scooter['image_url'])) : 'No file chosen.' ?></span>
                                    </div>
                                    <input type="file" class="sr-only sale-image-file-input" accept=".jpg,.jpeg,.png,.webp,.svg" data-product-id="<?= $scooter['product_id'] ?>">
                                </td>
                                <td class="py-2 px-4 border-b border-gray-200">
                                    <button type="button" class="deleteBtn bg-red-500 text-white px-3 py-1 rounded-lg shadow hover:bg-red-600 text-xs transition-colors" data-id="<?= htmlspecialchars($scooter['product_id']) ?>" style="display:none;">Delete</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-6 flex gap-3 justify-end" id="actionButtons" style="display:none;">
                    <button type="submit" id="saveBtn" class="bg-blue-600 text-white px-6 py-2 rounded-lg font-semibold shadow hover:bg-blue-700 transition-colors">Save</button>
                    <button type="button" id="cancelBtn" class="bg-gray-400 text-white px-6 py-2 rounded-lg font-semibold shadow hover:bg-gray-500 transition-colors">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    // Enable edit mode
    document.getElementById('editBtn').onclick = function() {
        document.querySelectorAll('input[type="text"], input[type="number"], select, input[type="checkbox"]').forEach(e => e.disabled = false);
        document.querySelectorAll('.deleteBtn').forEach(e => e.style.display = 'inline-block');
        document.querySelectorAll('.sale-image-browse-btn').forEach(e => e.style.display = 'inline-block');
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
                const deletedInput = document.getElementById('deletedIds');
                deletedInput.value += (deletedInput.value ? ',' : '') + id;
            }
            row.remove();
        };
    });
    // Add new row at the end of the table
    document.getElementById('addBtn').onclick = function() {
        const table = document.getElementById('scooterSaleTable').getElementsByTagName('tbody')[0];
        const row = table.insertRow(-1);
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
            <td class="py-1 px-2 border-b">
                <input type="number" value="0" class="border rounded px-1 py-1 w-full text-xs bg-gray-100" readonly tabindex="-1">
            </td>
            <td class="py-1 px-2 border-b"><input type="text" name="description[new][]" class="border rounded px-1 py-1 w-full text-xs"></td>
            <td class="py-1 px-2 border-b">
                <input type="hidden" name="image_url[new][]" value="" class="sale-image-url-input">
                <div class="flex items-center gap-2">
                    <button type="button" class="sale-image-browse-btn px-2 py-1 rounded bg-gray-100 border border-gray-300 hover:bg-gray-200 text-xs transition-colors cursor-pointer" data-row-type="new">Browse</button>
                    <span class="sale-image-filename text-xs text-gray-600">No file chosen.</span>
                </div>
                <input type="file" class="sr-only sale-image-file-input" accept=".jpg,.jpeg,.png,.webp,.svg" data-row-type="new">
            </td>
            <!-- is_available column removed for new rows -->
            <td class="py-1 px-2 border-b"><button type="button" class="deleteBtn bg-red-500 text-white px-2 py-1 rounded hover:bg-red-600 text-xs" style="display:inline-block;">Delete</button></td>
        `;
        row.querySelector('.deleteBtn').onclick = function() {
            row.remove();
        };
        initSaleImagePickers(row);
    };
    // Cancel button functionality
    document.getElementById('cancelBtn').onclick = function() {
        window.location.reload();
    };

    function initSaleImagePickers(container) {
        const browseBtns = container.querySelectorAll('.sale-image-browse-btn');
        browseBtns.forEach(btn => {
            btn.removeEventListener('click', saleImageBrowseClick);
            btn.addEventListener('click', saleImageBrowseClick);
        });

        const fileInputs = container.querySelectorAll('.sale-image-file-input');
        fileInputs.forEach(input => {
            input.removeEventListener('change', saleImageFileChange);
            input.addEventListener('change', saleImageFileChange);
        });
    }

    function saleImageBrowseClick(e) {
        e.preventDefault();
        const row = this.closest('tr');
        const fileInput = row.querySelector('.sale-image-file-input');
        if (fileInput) {
            fileInput.click();
        }
    }

    function saleImageFileChange() {
        const row = this.closest('tr');
        const urlInput = row.querySelector('.sale-image-url-input');
        const fileNameSpan = row.querySelector('.sale-image-filename');

        if (this.files && this.files.length > 0) {
            const fileName = this.files[0].name;
            fileNameSpan.textContent = fileName;
            urlInput.value = '/img/' + fileName;
            return;
        }

        fileNameSpan.textContent = 'No file chosen.';
        urlInput.value = '';
    }

    initSaleImagePickers(document.getElementById('scooterSaleTable'));

    // Search functionality
    document.getElementById('searchInput').onkeyup = function() {
        const filter = this.value.toLowerCase();
        document.querySelectorAll('#scooterSaleTable tbody tr').forEach(row => {
            const input = row.querySelector('input[type="text"]');
            if (input) {
                const name = input.value.toLowerCase();
                row.style.display = name.includes(filter) ? '' : 'none';
            }
        });
    };
</script>