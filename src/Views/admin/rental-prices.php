<?php
// filepath: src/Views/admin/rental-prices.php
// Admin interface for managing rental prices per product/variation and days

// Assume $products, $variations, $rentalPrices are provided by the controller
// $products: array of all products
// $variations: array of all variations, grouped by product_id
// $rentalPrices: array of all rental prices, grouped by product_id, variation_id, days

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$role = strtolower($_SESSION['admin_role'] ?? '');
$isStaff = ($role === 'staff');

?>

<div class="flex flex-1 items-center justify-center w-full">
    <div class="bg-white rounded-2xl shadow-xl p-10 w-full max-w-6xl mx-auto border border-blue-200">
        <h2 class="text-3xl font-bold mb-8 text-[#062B41] tracking-tight">Rental Prices</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white rounded-xl shadow text-base">
                <colgroup>
                    <col style="width: 80%">
                    <col style="width: 20%">
                </colgroup>
                <thead class="bg-blue-100 text-blue-900">
                    <tr>
                        <th class="py-3 px-4 rounded-tl-xl rounded-tr-xl">Product Name</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $prod): ?>
                    <tr class="hover:bg-blue-50 transition-colors">
                        <td class="py-2 px-4 border-b border-gray-200">
                            <a href="#" class="text-blue-700 hover:underline open-modal font-semibold" data-product-id="<?= htmlspecialchars($prod['product_id']) ?>">
                                <?= htmlspecialchars($prod['product_name']) ?>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <!-- Modal -->
        <div id="modalBg" class="fixed inset-0 flex items-center justify-center z-50 hidden overflow-y-auto p-4 sm:p-6" style="background: rgba(30,41,59,0.75);">
            <div class="bg-white rounded-2xl shadow-2xl p-0 w-full max-w-3xl max-h-[90vh] border border-gray-200 overflow-hidden flex flex-col">
                <div class="flex justify-between items-center px-6 pt-6 pb-3 border-b border-gray-100 bg-gradient-to-r from-blue-50 to-blue-100 rounded-t-2xl">
                    <h3 class="text-2xl font-bold text-blue-900 flex items-center gap-2" id="modalTitle">
                        <svg class="w-7 h-7 text-blue-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Edit Rental Prices
                    </h3>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-red-500 text-3xl font-bold transition-colors duration-150 cursor-pointer">&times;</button>
                </div>
                <div id="modalContent" class="p-6 bg-white rounded-b-2xl overflow-y-auto flex-1 min-h-0"></div>
            </div>
        </div>
    </div>
</div>

<script>
    // Variation and price data from PHP
    const rentalPrices = <?php echo json_encode($rentalPrices); ?>;
    const variations = <?php echo json_encode($variations); ?>;

    function renderPriceForm(productId, productName, productVariations) {
        let html = `<form method="post" action="/admin/rental-prices/save" id="rentalPriceForm" class="flex flex-col min-h-0">
            <input type="hidden" name="product_id" value="${productId}">`;
        // Tabs for variations (styled like locations.php)
        let tabs = '';
        let tabContents = '';
        let variationsList = productVariations.length === 0 ? [{variation_id: 'null', variation_name: 'Base Product'}] : productVariations;
        tabs += '<div class="flex border-b mb-6">';
        variationsList.forEach((variation, idx) => {
            const activeClass = idx === 0 ? 'tab-btn flex-1 py-3 px-4 text-center font-semibold text-lg border-r border-blue-200 focus:outline-none transition-all duration-200 text-blue-900 bg-white border-b-4 border-b-[#2563eb] z-10' : 'tab-btn flex-1 py-3 px-4 text-center font-semibold text-lg border-r border-blue-200 focus:outline-none transition-all duration-200 text-blue-900 hover:bg-blue-50 bg-blue-50';
            tabs += `<button type="button" class="cursor-pointer variation-tab ${activeClass}" data-tab="tab-${variation.variation_id}">${variation.variation_name}</button>`;
            tabContents += `<div class="variation-tab-content" id="tab-${variation.variation_id}" style="display:${idx === 0 ? 'block' : 'none'};">` +
                renderVariationPriceTable(productId, variation.variation_id, variation.variation_name, rentalPrices) +
                `</div>`;
        });
        tabs += '</div>';
        html += tabs;
        html += `<div class="min-h-0">${tabContents}</div>`;
        if (!isStaff) {
            html += `<div class="mt-6 flex flex-wrap gap-2 items-center justify-end" id="rentalActionButtons" style="display:none;">
                    <button type="button" id="addBtn" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 ml-4 cursor-pointer">Add Tier</button>
                    <button type="submit" class="bg-[#0086C9] text-white px-4 py-2 rounded hover:bg-[#006a9c] ml-2 cursor-pointer">Save</button>
                    <button type="button" id="cancelBtn" class="bg-gray-400 text-white px-4 py-2 rounded hover:bg-gray-500 ml-2 cursor-pointer">Cancel</button>
                </div>
                <div class="mt-6 flex flex-wrap gap-2 items-center justify-end" id="editRentalBtnWrap">
                    <button type="button" id="editRentalBtn" class="bg-[#0086C9] text-white px-4 py-2 rounded hover:bg-[#006a9c] ml-2 cursor-pointer">Edit</button>
                    <button type="button" onclick="closeModal()" class="bg-gray-400 text-white px-4 py-2 rounded hover:bg-gray-500 ml-2 cursor-pointer">Close</button>
                </div>`;
        } else {
            html += `<div class="mt-6 flex flex-wrap gap-2 items-center justify-end" id="editRentalBtnWrap">
                    <span class="text-sm text-gray-500 mr-2">Staff account: view only</span>
                    <button type="button" onclick="closeModal()" class="bg-gray-400 text-white px-4 py-2 rounded hover:bg-gray-500 ml-2 cursor-pointer">Close</button>
                </div>`;
        }
        html += `</form>`;
        document.getElementById('modalContent').innerHTML = html;

        // Tab switching logic (styled like locations.php)
        document.querySelectorAll('.variation-tab').forEach(tab => {
            tab.onclick = function() {
                document.querySelectorAll('.variation-tab').forEach(t => {
                    t.classList.remove('bg-white', 'border-b-4', 'border-b-[#2563eb]', 'z-10');
                    t.classList.add('bg-blue-50');
                });
                this.classList.add('bg-white', 'border-b-4', 'border-b-[#2563eb]', 'z-10');
                this.classList.remove('bg-blue-50');
                document.querySelectorAll('.variation-tab-content').forEach(tc => tc.style.display = 'none');
                document.getElementById(this.getAttribute('data-tab')).style.display = 'block';
            };
        });

        // Edit button functionality
        const editRentalBtn = document.getElementById('editRentalBtn');
        if (editRentalBtn) {
            editRentalBtn.onclick = function() {
                document.querySelectorAll('#rentalPriceForm input').forEach(e => e.disabled = false);
                document.querySelectorAll('#rentalPriceForm .remove-row').forEach(e => e.disabled = false);
                document.getElementById('rentalActionButtons').style.display = 'flex';
                document.getElementById('editRentalBtnWrap').style.display = 'none';
            };
        }
        // Remove row handler
        document.querySelectorAll('.remove-row').forEach(btn => {
            btn.onclick = function() {
                this.closest('tr').remove();
            };
        });
        // Add new row handler for each variation
        document.querySelectorAll('.add-tier-btn').forEach(function(addBtn) {
            addBtn.onclick = function() {
                const variationId = this.getAttribute('data-variation-id');
                const table = document.querySelector(`#variation-table-${variationId} tbody`);
                const rowIndex = table.rows.length;
                const row = table.insertRow(-1);
                row.innerHTML = `
                    <td class="py-1 px-2 border-b text-center align-middle">
                        <input type="number" name="days[${productId}][${variationId}][${rowIndex}]" value="" min="1" class="w-16 border rounded px-1 py-1 text-center mx-auto block">
                    </td>
                    <td class="py-1 px-2 border-b text-center align-middle">
                        <input type="number" step="0.01" name="price[${productId}][${variationId}][${rowIndex}]" value="" class="w-24 border rounded px-1 py-1 text-center mx-auto block">
                    </td>
                    <td class="py-1 px-2 border-b text-center align-middle">
                        <div class="flex justify-center">
                            <button type="button" class="remove-row bg-red-500 text-white px-2 py-1 rounded">Remove</button>
                        </div>
                    </td>
                `;
                row.querySelector('.remove-row').onclick = function() {
                    row.remove();
                };
            };
        });
        // Cancel button functionality
        const cancelBtn = document.getElementById('cancelBtn');
        if (cancelBtn) {
            cancelBtn.onclick = function() {
                closeModal();
            };
        }
    }

    const isStaff = <?= $isStaff ? 'true' : 'false' ?>;

    function renderVariationPriceTable(productId, variationId, variationName, rentalPrices) {
        // Get price rows for this variation
        let rows = (rentalPrices[productId] && rentalPrices[productId][variationId]) ? rentalPrices[productId][variationId] : [];
        // Always show individual day rows from 1 to 31
        const defaultDays = Array.from({ length: 31 }, (_, index) => String(index + 1));
        let newRows = [];
        defaultDays.forEach((d) => {
            let found = rows.find(r => String(r.days) === d);
            if (!found) {
                found = {days: d, price: ''};
            }
            newRows.push(found);
        });
        rows = newRows;
        let html = `<div class="mb-6 border border-blue-200 rounded-lg p-4">
            <div class="mb-2 font-semibold text-blue-900">${variationName}</div>
            <div class="max-h-[55vh] overflow-auto rounded-xl border border-gray-200">
            <table class="min-w-full bg-white shadow text-sm overflow-hidden" id="variation-table-${variationId}">
                <thead class="bg-blue-100 text-blue-900 sticky top-0 z-10">
                    <tr>
                        <th class="py-2 px-2 border-b w-32 font-semibold text-center">Day/Days</th>
                        <th class="py-2 px-2 border-b w-32 font-semibold text-center">Per-Day Price</th>
                        <th class="py-2 px-2 border-b w-20 font-semibold text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>`;
        rows.forEach((row, i) => {
            let dayVal = row.days;
            let min = (typeof dayVal === 'number' || !isNaN(dayVal)) ? 1 : undefined;
            html += `<tr>
                <td class="py-1 px-2 border-b text-center align-middle">
                    <input type="text" name="days[${productId}][${variationId}][${i}]" value="${dayVal}" ${min ? `min=\"${min}\"` : ''} class="w-16 border rounded px-1 py-1 text-center mx-auto block" disabled>
                </td>
                <td class="py-1 px-2 border-b text-center align-middle">
                    <input type="number" step="0.01" name="price[${productId}][${variationId}][${i}]" value="${row.price}" class="w-24 border rounded px-1 py-1 text-center mx-auto block" disabled>
                </td>
                <td class="py-1 px-2 border-b text-center align-middle">
                    <div class="flex justify-center">
                        <button type="button" class="remove-row bg-red-500 text-white px-2 py-1 rounded" disabled>Remove</button>
                    </div>
                </td>
            </tr>`;
        });
        html += `</tbody></table></div>
            <div class="flex justify-end mt-2">
                <button type="button" class="add-tier-btn bg-green-500 text-white px-2 py-1 rounded" data-variation-id="${variationId}" style="display:none;">Add Tier</button>
            </div>
        </div>`;
        return html;
    }

    // Open modal and render price form for product/variation
    document.querySelectorAll('.open-modal').forEach(link => {
        link.onclick = function(e) {
            e.preventDefault();
            const productId = this.getAttribute('data-product-id');
            const productName = this.textContent.trim();
            document.getElementById('modalBg').classList.remove('hidden');
            // Set modal title
            document.getElementById('modalTitle').textContent = productName + ' - Edit Rental Prices';
            // Get variations for this product
            const productVariations = variations[productId] || [];
            renderPriceForm(productId, productName, productVariations);
            setTimeout(function() {
                document.addEventListener('mousedown', outsideModalClick);
            }, 10);
        };
    });

    function closeModal() {
        document.getElementById('modalBg').classList.add('hidden');
        document.getElementById('modalContent').innerHTML = '';
        document.removeEventListener('mousedown', outsideModalClick);
    }

    function outsideModalClick(e) {
        const modal = document.querySelector('#modalBg > div');
        if (modal && !modal.contains(e.target)) {
            closeModal();
        }
    }
</script>
