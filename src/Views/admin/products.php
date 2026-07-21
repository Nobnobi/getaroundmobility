<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$role = strtolower($_SESSION['admin_role'] ?? '');
$isStaff = ($role === 'staff');
// echo('ROLE: ' . $role . ' | isStaff: ' . ($isStaff ? 'yes' : 'no'));
?>

    <div class="flex flex-1 w-full px-3 lg:px-5">
        <div class="bg-white rounded-2xl shadow-xl p-6 lg:p-8 w-full max-w-[98vw] mx-auto border border-gray-200">
            <header class="mb-8 flex flex-col md:flex-row md:justify-between md:items-center gap-4">
                <h1 class="text-3xl font-bold text-[#062B41] tracking-tight">Products</h1>
                <input type="text" id="searchInput" placeholder="Search product..." class="border border-gray-300 rounded-lg px-4 py-2 w-full md:w-72 focus:ring-2 focus:ring-[#062B41] focus:outline-none">
            </header>
            <?php if (!empty($_SESSION['product_delete_warnings']) && is_array($_SESSION['product_delete_warnings'])): ?>
                <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800">
                    <div class="font-semibold mb-1">Some products were not deleted:</div>
                    <?php foreach ($_SESSION['product_delete_warnings'] as $warning): ?>
                        <div><?= htmlspecialchars($warning) ?></div>
                    <?php endforeach; ?>
                </div>
                <?php unset($_SESSION['product_delete_warnings']); ?>
            <?php endif; ?>
            <?php if (!empty($_SESSION['product_upload_error'])): ?>
                <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800">
                    <div class="font-semibold mb-1">Image upload was not accepted</div>
                    <div><?= htmlspecialchars($_SESSION['product_upload_error']) ?></div>
                </div>
                <?php unset($_SESSION['product_upload_error']); ?>
            <?php endif; ?>
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
                <form method="post" action="/admin/products/save" enctype="multipart/form-data" id="productForm"<?= $isStaff ? ' onsubmit="return false;"' : '' ?>>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                    <input type="hidden" name="deleted_ids" id="deletedIds">
                    <div class="overflow-x-auto lg:overflow-x-visible">
                        <table class="min-w-full bg-white rounded-xl shadow text-center" id="productTable">
                            <thead class="bg-[#062B41] text-white">
                                <tr>
                                    <th class="py-3 px-4 rounded-tl-xl">Product ID</th>
                                    <th class="py-3 px-4">Product Name</th>
                                    <th class="py-3 px-4">Category</th>
                                    <th class="py-3 px-4">Price/day ($)</th>
                                    <th class="py-3 px-4">Quantity</th>
                                    <th class="py-3 px-4">Short Description</th>
                                    <th class="py-3 px-4">Description</th>
                                    <th class="py-3 px-4">Image URL</th>
                                    <th class="py-3 px-3 min-w-[130px]">Visible on Website</th>
                                    <?php if (!$isStaff): ?>
                                        <th class="py-3 px-3 rounded-tr-xl min-w-[150px]">Actions</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                <tr class="hover:bg-gray-100 transition-colors">
                                    <td class="py-2 px-4 border-b border-gray-200 font-semibold text-gray-700"><?= $product['product_id'] ?></td>
                                    <td class="py-2 px-3 border-b border-gray-200 whitespace-normal break-words min-w-[160px] max-w-[260px]">
                                        <input type="text" name="product_name[<?= $product['product_id'] ?>]" value="<?= htmlspecialchars($product['product_name']) ?>" class="border border-gray-300 rounded-lg px-3 py-2 w-full text-sm focus:ring-2 focus:ring-[#062B41] focus:outline-none" style="min-width:140px;max-width:240px;" disabled>
                                    </td>
                                    <td class="py-2 px-3 border-b border-gray-200 whitespace-normal break-words min-w-[120px] max-w-[180px]">
                                        <select name="product_category_id[<?= $product['product_id'] ?>]" class="border border-gray-300 rounded-lg px-3 py-2 w-full text-sm focus:ring-2 focus:ring-[#062B41] focus:outline-none" style="min-width:110px;max-width:170px;" disabled>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?= $cat['category_id'] ?>" <?= $cat['category_id'] == $product['product_category_id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($cat['category_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td class="py-2 px-3 border-b border-gray-200">
                                        <input type="number" step="0.01" name="price[<?= $product['product_id'] ?>]" value="<?= $product['price'] ?>" class="border border-gray-300 rounded-lg px-2 py-1 w-full text-xs focus:ring-2 focus:ring-[#062B41] focus:outline-none" disabled>
                                    </td>
                                    <td class="py-2 px-3 border-b border-gray-200">
                                        <input type="number" 
                                               name="stock_quantity[<?= $product['product_id'] ?>]" 
                                               value="<?= $product['scooter_count'] ?>" 
                                               class="border border-gray-300 rounded-lg px-2 py-1 w-full text-xs bg-gray-100 cursor-not-allowed" 
                                               readonly>
                                    </td>
                                    <td class="py-2 px-3 border-b border-gray-200">
                                        <?php
                                        $shortRaw = (string) ($product['short_description'] ?? '');
                                        $shortParts = preg_split('/\r\n|\r|\n|\|\|/', $shortRaw) ?: [];
                                        $shortNotes = [];
                                        foreach ($shortParts as $part) {
                                            $line = trim($part);
                                            if ($line === '') {
                                                continue;
                                            }
                                            $shortNotes[] = $line;
                                            if (count($shortNotes) >= 2) {
                                                break;
                                            }
                                        }
                                        ?>
                                        <input type="hidden" name="short_description[<?= $product['product_id'] ?>]" value="<?= htmlspecialchars(implode("\n", $shortNotes)) ?>" class="short-desc-input">
                                        <div class="short-desc-preview mb-2 text-left text-[11px] text-gray-700">
                                            <?php if (!empty($shortNotes)): ?>
                                                <?php foreach ($shortNotes as $note): ?>
                                                    <div class="truncate">• <?= htmlspecialchars($note) ?></div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="text-gray-400">No notes yet.</div>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!$isStaff): ?>
                                            <button type="button" class="edit-notes-btn inline-flex items-center gap-1 rounded bg-[#0086C9] px-2 py-1 text-[11px] font-semibold text-white hover:bg-[#006a9c] cursor-pointer" style="display:none;">Edit Notes</button>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-2 px-3 border-b border-gray-200">
                                        <input type="text" name="description[<?= $product['product_id'] ?>]" value="<?= htmlspecialchars($product['description'] ?? '') ?>" class="border border-gray-300 rounded-lg px-2 py-1 w-full text-xs focus:ring-2 focus:ring-[#062B41] focus:outline-none" disabled>
                                    </td>
                                    <td class="py-2 px-3 border-b border-gray-200">
                                        <input type="hidden" name="image_url[<?= $product['product_id'] ?>]" value="<?= htmlspecialchars($product['image_url'] ?? '') ?>" class="product-image-url-input">
                                        <div class="flex items-center gap-2">
                                            <button type="button" class="hover:cursor-pointer product-image-browse-btn px-2 py-1 rounded bg-gray-100 border border-gray-300 hover:bg-gray-200 text-xs transition-colors" data-product-id="<?= $product['product_id'] ?>">Browse</button>
                                            <span class="product-image-filename text-xs text-gray-600"><?= !empty($product['image_url']) ? htmlspecialchars(basename($product['image_url'])) : 'No file chosen.' ?></span>
                                        </div>
                                        <input type="file" name="product_image[<?= $product['product_id'] ?>]" class="sr-only product-image-file-input" accept=".jpg,.jpeg,.png,.webp" data-product-id="<?= $product['product_id'] ?>">
                                    </td>
                                    <td class="py-2 px-3 border-b border-gray-200">
                                        <input type="hidden" name="is_hidden[<?= $product['product_id'] ?>]" value="0" disabled>
                                        <label class="inline-flex items-center gap-2 text-xs text-gray-700">
                                            <input type="checkbox" name="is_hidden[<?= $product['product_id'] ?>]" value="1" <?= !empty($product['is_hidden']) ? 'checked' : '' ?> disabled class="website-visible-toggle h-4 w-4 rounded border-gray-300 text-[#062B41] focus:ring-[#062B41]">
                                            <span class="visibility-state-label font-semibold"><?= !empty($product['is_hidden']) ? 'Hidden' : 'Visible' ?></span>
                                        </label>
                                    </td>
                                    <?php if (!$isStaff): ?>
                                    <td class="py-2 px-3 border-b border-gray-200 min-w-[150px]">
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
        document.addEventListener('DOMContentLoaded', function() {
            let isEditMode = false;

            function keepOnlyTwoNotes(value) {
                const lines = (value || '')
                    .split(/\r\n|\r|\n|\|\|/)
                    .map(line => line.trim())
                    .filter(Boolean)
                    .slice(0, 2);
                return lines.join('\n');
            }

            // Enable edit mode
            const editBtn = document.getElementById('editBtn');
            if (editBtn) {
                editBtn.onclick = function() {
                    isEditMode = true;
                    document.querySelectorAll('input, select, textarea').forEach(e => {
                        if (!e.hasAttribute('readonly')) {
                            e.disabled = false;
                        }
                    });
                    document.querySelectorAll('.deleteBtn').forEach(e => e.style.display = 'inline-block');
                    document.querySelectorAll('.edit-notes-btn').forEach(e => e.style.display = 'inline-flex');
                    const actionButtons = document.getElementById('actionButtons');
                    const addBtn = document.getElementById('addBtn');
                    if (actionButtons) actionButtons.style.display = 'flex';
                    editBtn.style.display = 'none';
                    if (addBtn) addBtn.style.display = 'inline-block';
                };
            }

            // Delete row
            document.querySelectorAll('.deleteBtn').forEach(btn => {
                btn.onclick = function() {
                    const confirmed = window.confirm('Delete this product? This will also delete linked inventory stock records for this product.');
                    if (!confirmed) {
                        return;
                    }
                    const row = btn.closest('tr');
                    const id = btn.getAttribute('data-id');
                    if (id && id !== 'New') {
                        const deletedInput = document.getElementById('deletedIds');
                        if (deletedInput) {
                            deletedInput.value += (deletedInput.value ? ',' : '') + id;
                        }
                    }
                    row.remove();
                };
            });

            // Add new row at the end of the table
            const addBtn = document.getElementById('addBtn');
            if (addBtn) {
                addBtn.onclick = function() {
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
                <td class="py-1 px-2 border-b">
                    <input type="hidden" name="short_description[new][]" value="" class="short-desc-input">
                    <div class="short-desc-preview mb-2 text-left text-[11px] text-gray-500">No notes yet.</div>
                    <button type="button" class="edit-notes-btn inline-flex items-center gap-1 rounded bg-[#0086C9] px-2 py-1 text-[11px] font-semibold text-white hover:bg-[#006a9c] cursor-pointer" style="display:none;">Edit Notes</button>
                </td>
                <td class="py-1 px-2 border-b"><input type="text" name="description[new][]" class="border rounded px-1 py-1 w-full text-xs"></td>
                <td class="py-1 px-2 border-b">
                    <input type="hidden" name="image_url[new][]" value="" class="product-image-url-input">
                    <div class="flex items-center gap-2">
                        <button type="button" class="product-image-browse-btn px-2 py-1 rounded bg-gray-100 border border-gray-300 hover:bg-gray-200 text-xs transition-colors" data-row-type="new">Browse</button>
                        <span class="product-image-filename text-xs text-gray-600">No file chosen.</span>
                    </div>
                    <input type="file" name="product_image[new][]" class="sr-only product-image-file-input" accept=".jpg,.jpeg,.png,.webp" data-row-type="new">
                </td>
                <td class="py-1 px-2 border-b">
                    <input type="hidden" name="is_hidden[new][]" value="0">
                    <label class="inline-flex items-center gap-2 text-xs text-gray-700">
                        <input type="checkbox" name="is_hidden[new][]" value="1" class="website-visible-toggle h-4 w-4 rounded border-gray-300 text-[#062B41] focus:ring-[#062B41]">
                        <span class="visibility-state-label font-semibold">Visible</span>
                    </label>
                </td>
                <td class="py-1 px-2 border-b"><button type="button" class="deleteBtn bg-red-500 text-white px-2 py-1 rounded hover:bg-red-600 text-xs" style="display:inline-block;">Delete</button></td>
                    `;
                    row.querySelector('.deleteBtn').onclick = function() {
                        const confirmed = window.confirm('Remove this new unsaved product row?');
                        if (!confirmed) {
                            return;
                        }
                        row.remove();
                    };
                    bindNotesButtons(row);
                    initVisibilityToggles(row);
                    if (isEditMode) {
                        row.querySelectorAll('.edit-notes-btn').forEach(btn => btn.style.display = 'inline-flex');
                    }
                };
            }

            // Ensure notes buttons stay hidden until edit mode is explicitly enabled.
            document.querySelectorAll('.edit-notes-btn').forEach(btn => {
                btn.style.display = 'none';
            });

            // Cancel button functionality
            const cancelBtn = document.getElementById('cancelBtn');
            if (cancelBtn) {
                cancelBtn.onclick = function() {
                    window.location.reload();
                };
            }

            // Product image file picker functionality
            function updateVisibilityLabel(checkbox) {
                const row = checkbox.closest('tr');
                const label = row ? row.querySelector('.visibility-state-label') : null;
                if (label) {
                    label.textContent = checkbox.checked ? 'Hidden' : 'Visible';
                }
            }

            function handleVisibilityToggleChange(e) {
                updateVisibilityLabel(e.currentTarget);
            }

            function initVisibilityToggles(container) {
                if (!container) return;
                container.querySelectorAll('.website-visible-toggle').forEach(checkbox => {
                    checkbox.removeEventListener('change', handleVisibilityToggleChange);
                    checkbox.addEventListener('change', handleVisibilityToggleChange);
                    updateVisibilityLabel(checkbox);
                });
            }

            function initProductImagePickers(container) {
                if (!container) return;
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
                const fileInput = row ? row.querySelector('.product-image-file-input') : null;
                if (fileInput) {
                    fileInput.click();
                }
            }

            function productImageFileChange(e) {
                const fileInput = this;
                const row = fileInput.closest('tr');
                if (!row) return;
                const urlInput = row.querySelector('.product-image-url-input');
                const fileNameSpan = row.querySelector('.product-image-filename');

                if (fileInput.files && fileInput.files.length > 0) {
                    const fileName = fileInput.files[0].name;
                    if (fileNameSpan) fileNameSpan.textContent = fileName;
                    return;
                }

                if (fileNameSpan) fileNameSpan.textContent = 'No file chosen.';
                if (urlInput) urlInput.value = '';
            }

            // Initialize on page load
            initProductImagePickers(document.getElementById('productTable'));
            initVisibilityToggles(document.getElementById('productTable'));

            let activeNotesRow = null;

            function parseNotes(value) {
                return (value || '')
                    .split(/\r\n|\r|\n|\|\|/)
                    .map(line => line.trim())
                    .filter(Boolean)
                    .slice(0, 2);
            }

            function updateRowNotesPreview(row, notes) {
                const preview = row.querySelector('.short-desc-preview');
                if (!preview) return;
                if (!notes.length) {
                    preview.innerHTML = '<div class="text-gray-400">No notes yet.</div>';
                    return;
                }
                preview.innerHTML = notes.map(n => `<div class="truncate">• ${n.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</div>`).join('');
            }

            function openNotesModalForRow(row) {
                activeNotesRow = row;
                const hidden = row.querySelector('.short-desc-input');
                const notes = parseNotes(hidden ? hidden.value : '');
                const note1 = document.getElementById('notesModalRow1');
                const note2 = document.getElementById('notesModalRow2');
                const notesModal = document.getElementById('notesModal');
                const notesModalOverlay = document.getElementById('notesModalOverlay');
                if (!note1 || !note2 || !notesModal || !notesModalOverlay) return;
                note1.value = notes[0] || '';
                note2.value = notes[1] || '';
                notesModal.classList.remove('hidden');
                notesModalOverlay.classList.remove('hidden');
            }

            function closeNotesModal() {
                const notesModal = document.getElementById('notesModal');
                const notesModalOverlay = document.getElementById('notesModalOverlay');
                if (notesModal) notesModal.classList.add('hidden');
                if (notesModalOverlay) notesModalOverlay.classList.add('hidden');
                activeNotesRow = null;
            }

            function bindNotesButtons(container) {
                if (!container) return;
                container.querySelectorAll('.edit-notes-btn').forEach(btn => {
                    btn.onclick = function() {
                        const row = this.closest('tr');
                        if (!row) return;
                        openNotesModalForRow(row);
                    };
                });
            }

            bindNotesButtons(document.getElementById('productTable'));

            const notesModalCloseBtn = document.getElementById('notesModalCloseBtn');
            const notesModalCancelBtn = document.getElementById('notesModalCancelBtn');
            const notesModalOverlay = document.getElementById('notesModalOverlay');
            const notesModalSaveBtn = document.getElementById('notesModalSaveBtn');
            if (notesModalCloseBtn) notesModalCloseBtn.onclick = closeNotesModal;
            if (notesModalCancelBtn) notesModalCancelBtn.onclick = closeNotesModal;
            if (notesModalOverlay) notesModalOverlay.onclick = closeNotesModal;

            if (notesModalSaveBtn) {
                notesModalSaveBtn.onclick = function() {
                    if (!activeNotesRow) return;
                    const note1Input = document.getElementById('notesModalRow1');
                    const note2Input = document.getElementById('notesModalRow2');
                    if (!note1Input || !note2Input) return;
                    const note1 = note1Input.value.trim();
                    const note2 = note2Input.value.trim();
                    const notes = [note1, note2].filter(Boolean).slice(0, 2);
                    const hidden = activeNotesRow.querySelector('.short-desc-input');
                    if (hidden) {
                        hidden.value = notes.join('\n');
                    }
                    updateRowNotesPreview(activeNotesRow, notes);
                    closeNotesModal();
                };
            }

            const productForm = document.getElementById('productForm');
            if (productForm) {
                productForm.addEventListener('submit', function() {
                    this.querySelectorAll('.short-desc-input').forEach(function(input) {
                        input.value = keepOnlyTwoNotes(input.value);
                    });
                });
            }
        });
    </script>

    <div id="notesModalOverlay" class="fixed inset-0 z-40 hidden bg-black/40"></div>
    <div id="notesModal" class="fixed left-1/2 top-1/2 z-50 hidden w-[94vw] max-w-2xl -translate-x-1/2 -translate-y-1/2 rounded-2xl bg-white p-6 md:p-8 shadow-2xl border border-gray-200 max-h-[88vh] overflow-y-auto">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-xl font-bold text-[#062B41]">Edit Product Notes</h3>
            <button id="notesModalCloseBtn" type="button" class="text-gray-500 hover:text-black text-2xl leading-none cursor-pointer">&times;</button>
        </div>
        <p class="mb-4 text-sm text-gray-500">Add up to 2 notes. One note per row.</p>
        <div class="space-y-4">
            <div>
                <label class="mb-2 block text-sm font-semibold text-gray-600">Note 1</label>
                <input id="notesModalRow1" type="text" class="w-full rounded-lg border border-gray-300 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#0086C9]" placeholder="Example: 35 mile range">
            </div>
            <div>
                <label class="mb-2 block text-sm font-semibold text-gray-600">Note 2</label>
                <input id="notesModalRow2" type="text" class="w-full rounded-lg border border-gray-300 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#0086C9]" placeholder="Example: 450 lbs capacity">
            </div>
        </div>
        <div class="mt-6 flex justify-end gap-2">
            <button id="notesModalCancelBtn" type="button" class="rounded-lg border border-gray-300 px-5 py-2.5 text-sm text-gray-700 hover:bg-gray-100 cursor-pointer">Cancel</button>
            <button id="notesModalSaveBtn" type="button" class="rounded-lg bg-[#0086C9] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#006a9c] cursor-pointer">Save Notes</button>
        </div>
    </div>
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
