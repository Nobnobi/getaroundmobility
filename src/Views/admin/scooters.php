<!-- filepath: c:\xampp\htdocs\GetAroundMobility\src\Views\admin\scooters.php -->
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$role = strtolower($_SESSION['admin_role'] ?? '');
$isStaff = ($role === 'staff');
// echo('ROLE: ' . $role . ' | isStaff: ' . ($isStaff ? 'yes' : 'no'));
?>

    <div class="flex flex-1 items-center justify-center w-full">
        <div class="bg-white rounded-2xl shadow-xl p-10 w-full max-w-6xl mx-auto border border-blue-200">
            <h2 class="text-3xl font-bold mb-8 text-[#062B41] tracking-tight">Scooter Inventory</h2>

            <!-- Success or status change messages -->
            <?php if (!empty($_SESSION['status_changes'])): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 px-6 py-4 rounded-lg mb-6 font-semibold">
                    <?php 
                    foreach ($_SESSION['status_changes'] as $msg) {
                        echo htmlspecialchars($msg) . "<br>";
                    }
                    unset($_SESSION['status_changes']); // clear after showing
                    ?>
                </div>
            <?php endif; ?>

            <div class="overflow-x-auto">
                <table class="min-w-full bg-white rounded-xl shadow text-base">
                    <colgroup>
                        <col style="width: 80%">
                        <col style="width: 20%">
                    </colgroup>
                    <thead class="bg-blue-100 text-blue-900">
                        <tr>
                            <th class="py-3 px-4 rounded-tl-xl">Product Name</th>
                            <th class="py-3 px-4 rounded-tr-xl">Quantity</th>
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
                            <td class="py-2 px-4 border-b border-gray-200 font-bold text-blue-900 text-center"><?= htmlspecialchars($prod['scooter_count']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal -->
        <div id="modalBg" class="fixed inset-0 flex items-center justify-center z-50 hidden p-4" style="background: rgba(30,41,59,0.75);">
            <div class="bg-white rounded-2xl shadow-2xl p-0 w-full max-w-5xl border border-gray-200 max-h-[92vh] flex flex-col overflow-hidden">
                <div class="flex justify-between items-center px-6 pt-6 pb-3 border-b border-gray-100 bg-gradient-to-r from-blue-50 to-blue-100 rounded-t-2xl">
                    <h3 class="text-2xl font-bold text-blue-900 flex items-center gap-2" id="modalTitle">
                        <svg class="w-7 h-7 text-blue-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Scooters
                    </h3>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-red-500 text-3xl font-bold transition-colors duration-150">&times;</button>
                </div>
                <div id="modalContent" class="p-6 bg-white rounded-b-2xl overflow-y-auto"></div>
            </div>
        </div>
    </div>

    <script>
        const isStaff = <?= $isStaff ? 'true' : 'false' ?>;
        // Open modal and fetch scooters for product
        document.querySelectorAll('.open-modal').forEach(link => {
            link.onclick = function(e) {
                e.preventDefault();
                let deletedIds = [];
                const productId = this.getAttribute('data-product-id');
                const productName = this.textContent.trim();
                document.getElementById('modalBg').classList.remove('hidden');
                document.getElementById('modalTitle').textContent = productName;

                let currentPage = 1;
                const pageSize = 5;
                let totalPages = 1;

                let allVariations = [];
                let selectedVariation = '';
                function renderScooterTable(data, page, filterVariation) {
                    let html = "";
                    // Filter data by variation and barcode search
                    var filteredData = data;
                    if (filterVariation && filterVariation !== 'all') {
                        filteredData = filteredData.filter(function(scooter) { return String(scooter.variation_id) === String(filterVariation); });
                    }
                    var barcodeSearchInput = document.getElementById('barcodeSearch');
                    var barcodeSearchValue = barcodeSearchInput ? barcodeSearchInput.value.trim().toLowerCase() : '';
                    if (barcodeSearchValue) {
                        filteredData = filteredData.filter(function(scooter) { return (scooter.barcode || '').toLowerCase().includes(barcodeSearchValue); });
                    }
                    // Search bar and variation filter
                    html += `<div class="mb-4 flex flex-wrap items-center gap-4 bg-blue-50 rounded-lg px-4 py-3 border border-blue-100 shadow-sm">`;
                    html += `<div class="flex items-center gap-2">`;
                    html += `<label for="barcodeSearch" class="text-sm font-semibold text-blue-900">Search Barcode:</label>`;
                    html += `<input type="text" id="barcodeSearch" class="border border-blue-200 rounded px-2 py-1 text-sm focus:ring-2 focus:ring-blue-300 focus:border-blue-400 transition w-44" placeholder="Enter barcode...">`;
                    html += `</div>`;
                    if (allVariations.length > 0) {
                        html += `<div class="flex items-center gap-2">`;
                        html += `<label for="variationFilter" class="text-sm font-semibold text-blue-900">Filter by Variation:</label>`;
                        html += `<select id="variationFilter" class="border border-blue-200 rounded px-2 py-1 text-sm focus:ring-2 focus:ring-blue-300 focus:border-blue-400 transition">`;
                        html += `<option value="all">All</option>`;
                        html += `${allVariations.map(v => `<option value="${v.variation_id}" ${filterVariation == v.variation_id ? 'selected' : ''}>${v.variation_name}</option>`).join('')}`;
                        html += `</select>`;
                        html += `</div>`;
                    }
                    html += `</div>`;
                        // Remove duplicate declarations: already declared above
                    const start = (page - 1) * pageSize;
                    const end = start + pageSize;
                    const pageData = filteredData.slice(start, end);
                        // Remove duplicate declaration: 'html' is already declared at the top of the function
                    // Remove duplicate variation filter dropdown
                    html += `<form method="post" action="/admin/scooters/save" id="modalForm" class="flex flex-col gap-4">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="product_id" value="${productId}">
                            <input type="hidden" name="deleted_ids" id="deleted_ids" value="">
                            <div class="max-h-[52vh] overflow-auto rounded-xl border border-gray-200">
                            <table class="min-w-full bg-white shadow text-sm overflow-hidden">
                                <thead class="bg-blue-100 text-blue-900">
                                    <tr>
                                        <th class="py-2 px-2 border-b w-16 font-semibold">ID</th>
                                        <th class="py-2 px-2 border-b w-24 font-semibold">Status</th>
                                        <th class="py-2 px-2 border-b w-40 font-semibold">Variation</th>
                                        <th class="py-2 px-2 border-b w-40 font-semibold">Barcode</th>
                                        <th class="py-2 px-2 border-b w-32 font-semibold">Barcode Image</th>
                                        <th class="py-2 px-2 border-b w-20 font-semibold">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>`;


                    // getVariations is now only used once, so we fetch allVariations outside

                    pageData.forEach(scooter => {
                        const scooterVariationId = typeof scooter.variation_id !== 'undefined' ? scooter.variation_id : '';
                        html += `
                            <tr>
                                <td class="py-1 px-2 border-b">${scooter.scooter_id}</td>
                                <td class="py-1 px-2 border-b">
                                    <select name="status[${scooter.scooter_id}]" class="border rounded px-1 py-1 w-full text-xs" ${isStaff ? 'disabled' : ''}>
                                        <option value="available" ${scooter.status === 'available' ? 'selected' : ''}>Available</option>
                                        <option value="maintenance" ${scooter.status === 'maintenance' ? 'selected' : ''}>Maintenance</option>
                                        ${scooter.sale_type === 'sale' ? `<option value="Sold" ${scooter.status === 'Sold' ? 'selected' : ''}>Sold</option>` : ''}
                                    </select>
                                </td>
                                <td class="py-1 px-2 border-b">
                                    <select name="variation_id[${scooter.scooter_id}]" class="border rounded px-1 py-1 w-full text-xs" ${isStaff ? 'disabled' : ''}>
                                        ${allVariations.length > 0 ? allVariations.map(v => `<option value="${v.variation_id}" ${scooterVariationId == v.variation_id ? 'selected' : ''}>${v.variation_name}</option>`).join('') : '<option value="">None</option>'}
                                    </select>
                                </td>
                                <td class="py-1 px-2 border-b">
                                    <input type="text" name="barcode[${scooter.scooter_id}]" value="${scooter.barcode}" class="border rounded px-1 py-1 w-full text-xs bg-gray-100 cursor-not-allowed" readonly tabindex="-1">
                                </td>
                                <td class="py-1 px-2 border-b">
                                    ${scooter.barcode ? `
                                        <img src="https://barcode.tec-it.com/barcode.ashx?data=${encodeURIComponent(scooter.barcode)}&code=Code128&dpi=96"
                                            alt="Barcode for ${scooter.barcode}" style="height:40px;">
                                        <br>
                                        <a href="https://barcode.tec-it.com/barcode.ashx?data=${encodeURIComponent(scooter.barcode)}&code=Code128&dpi=96"
                                        download="barcode-${scooter.barcode}.png"
                                        target="_blank"
                                        class="text-blue-600 underline text-xs">Download</a>
                                    ` : `<span class="text-gray-400 text-xs">No barcode</span>`}
                                </td>
                                <td class="py-1 px-2 border-b">
                                    ${!isStaff ? `<button type="button" class="deleteBtn bg-red-500 text-white px-2 py-1 rounded hover:bg-red-600 text-xs" data-id="${scooter.scooter_id}">Delete</button>` : ''}
                                </td>
                            </tr>`;
                    });

                        html += `</tbody></table>
                            </div>
                        <div class="flex flex-wrap gap-2 items-center justify-end">`;

                    // Pagination controls (show only if more than one page)
                    if (filteredData.length > pageSize) {
                        const filteredTotalPages = Math.ceil(filteredData.length / pageSize) || 1;
                        if (page > 1) {
                            html += `<button type="button" id="prevPageBtn" class="bg-gray-300 text-gray-700 px-3 py-1 rounded">Prev</button>`;
                        }
                        html += `<span class="mx-2">Page ${page} of ${filteredTotalPages}</span>`;
                        if (page < filteredTotalPages) {
                            html += `<button type="button" id="nextPageBtn" class="bg-gray-300 text-gray-700 px-3 py-1 rounded cursor-pointer">Next</button>`;
                        }
                    }

                    if (!isStaff) {
                        html += `
                            <button type="button" id="addBtn" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 ml-4 cursor-pointer">Add Scooter</button>
                            <button type="submit" class="bg-[#0086C9] text-white px-4 py-2 rounded hover:bg-[#006a9c] ml-2 cursor-pointer">Save</button>
                        `;
                    }
                    html += `<button type="button" onclick="closeModal()" class="bg-gray-400 text-white px-4 py-2 rounded hover:bg-gray-500 ml-2 cursor-pointer">Close</button>
                        </div>
                    </form>`;

                    document.getElementById('modalContent').innerHTML = html;

                    // Variation filter handler
                    if (allVariations.length > 0) {
                        document.getElementById('variationFilter').onchange = function() {
                            selectedVariation = this.value;
                            currentPage = 1;
                            renderScooterTable(data, currentPage, selectedVariation);
                        };
                    }
                        // Variation filter handler
                        if (allVariations.length > 0) {
                            document.getElementById('variationFilter').onchange = function() {
                                selectedVariation = this.value;
                                currentPage = 1;
                                renderScooterTable(data, currentPage, selectedVariation);
                            };
                        }
                        // Barcode search handler
                        setTimeout(function() {
                            var barcodeSearchInputLive = document.getElementById('barcodeSearch');
                            if (barcodeSearchInputLive) {
                                barcodeSearchInputLive.value = barcodeSearchValue;
                                barcodeSearchInputLive.oninput = function() {
                                    currentPage = 1;
                                    renderScooterTable(data, currentPage, selectedVariation);
                                };
                                // Move cursor to end
                                barcodeSearchInputLive.focus();
                                barcodeSearchInputLive.setSelectionRange(barcodeSearchInputLive.value.length, barcodeSearchInputLive.value.length);
                            }
                        }, 0);

                    // Pagination button handlers
                    const prevBtn = document.getElementById('prevPageBtn');
                    if (prevBtn) {
                        prevBtn.onclick = function() {
                            if (currentPage > 1) {
                                currentPage--;
                                renderScooterTable(data, currentPage, selectedVariation);
                            }
                        };
                    }
                    const nextBtn = document.getElementById('nextPageBtn');
                    if (nextBtn) {
                        const filteredTotalPages = Math.ceil(filteredData.length / pageSize) || 1;
                        nextBtn.onclick = function() {
                            if (currentPage < filteredTotalPages) {
                                currentPage++;
                                renderScooterTable(data, currentPage, selectedVariation);
                            }
                        };
                    }

                    // Only attach handlers if not staff
                    if (!isStaff) {
                        // Delete row
                        document.querySelectorAll('.deleteBtn').forEach(btn => {
                            btn.onclick = function() {
                                const row = btn.closest('tr');
                                const scooterId = btn.getAttribute('data-id');
                                if (scooterId && scooterId !== "New") {
                                    deletedIds.push(scooterId);
                                    document.getElementById('deleted_ids').value = deletedIds.join(',');
                                }
                                row.remove();
                            };
                        });

                        // Add new row(s)
                        document.getElementById('addBtn').onclick = function() {
                            const qtyInput = prompt('How many would you like to add?', '1');
                            if (qtyInput === null) {
                                return;
                            }

                            const quantity = parseInt(qtyInput, 10);
                            if (Number.isNaN(quantity) || quantity < 1 || quantity > 100) {
                                alert('Please enter a whole number between 1 and 100.');
                                return;
                            }

                            const table = document.querySelector('#modalForm tbody');

                            for (let i = 0; i < quantity; i++) {
                                const row = table.insertRow(-1);
                                let variationSelect = `<select name="variation_id[new][]" class="border rounded px-1 py-1 w-full text-xs">`;
                                if (allVariations.length > 0) {
                                    allVariations.forEach(v => {
                                        variationSelect += `<option value="${v.variation_id}">${v.variation_name}</option>`;
                                    });
                                } else {
                                    variationSelect += `<option value="">None</option>`;
                                }
                                variationSelect += `</select>`;

                                row.innerHTML = `
                                    <td class="py-1 px-2 border-b">New</td>
                                    <td class="py-1 px-2 border-b">
                                        <select name="status[new][]" class="border rounded px-1 py-1 w-full text-xs">
                                            <option value="available">Available</option>
                                            <option value="maintenance">Maintenance</option>
                                        </select>
                                        <input type="hidden" name="product_id[new][]" value="${productId}">
                                    </td>
                                    <td class="py-1 px-2 border-b">${variationSelect}</td>
                                    <td class="py-1 px-2 border-b">
                                        <input type="text" name="barcode[new][]" class="border rounded px-1 py-1 w-full text-xs bg-gray-100 cursor-not-allowed" readonly tabindex="-1">
                                    </td>
                                    <td class="py-1 px-2 border-b">
                                        <span class="text-gray-400 text-xs">No barcode</span>
                                    </td>
                                    <td class="py-1 px-2 border-b">
                                        <button type="button" class="deleteBtn bg-red-500 text-white px-2 py-1 rounded hover:bg-red-600 text-xs">Delete</button>
                                    </td>
                                `;

                                row.querySelector('.deleteBtn').onclick = function() {
                                    row.remove();
                                };
                            }
                        };

                        // Always enable all selects before submitting the form
                        document.getElementById('modalForm').onsubmit = function() {
                            document.querySelectorAll('#modalForm select').forEach(e => e.disabled = false);
                        };
                    }
                }

                // Initial fetch and render
                // Fetch variations first
                fetch('/admin/api/product-variations?product_id=' + encodeURIComponent(productId))
                .then(res => res.json())
                .then(vars => {
                    allVariations = vars;
                    fetch('/admin/scooters/list?product_id=' + encodeURIComponent(productId))
                        .then(res => res.json())
                        .then(data => {
                            totalPages = Math.ceil(data.length / pageSize) || 1;
                            renderScooterTable(data, currentPage, selectedVariation);
                        });
                });
            };
        });

        function closeModal() {
            document.getElementById('modalBg').classList.add('hidden');
            document.getElementById('modalContent').innerHTML = '';
        }
    </script>

