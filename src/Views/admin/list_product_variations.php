<?php
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}
$role = strtolower($_SESSION['admin_role'] ?? '');
$isStaff = ($role === 'staff');
// $products: array of [product_id, product_name] (fetch in controller)
// $variations: array of all variations (fetch in controller)
// $csrf_token: string (fetch in controller)
// For reference, see categories.php for UI/UX and JS pattern
?>

<div class="flex flex-1 items-center justify-center w-full">
	<div class="bg-white rounded-2xl shadow-xl p-10 w-full max-w-5xl mx-auto border border-gray-200">
		<header class="mb-8 flex flex-col md:flex-row md:justify-between md:items-center gap-4">
			<h1 class="text-3xl font-bold text-[#062B41] tracking-tight">Product Variations</h1>
			<input type="text" id="searchInput" placeholder="Search variation..." class="border border-gray-300 rounded-lg px-4 py-2 w-full md:w-72 focus:ring-2 focus:ring-[#062B41] focus:outline-none">
		</header>
		<div class="bg-gray-50 rounded-xl shadow-inner p-6">
			<div class="flex flex-col md:flex-row md:justify-between md:items-center mb-6 gap-4">
				<div class="flex items-center gap-3">
					<h2 class="text-xl font-bold text-gray-800">Variations Table</h2>
					<?php if (!$isStaff): ?>
						<button type="button" id="editBtn" class="bg-[#0086C9] text-white px-4 py-2 rounded-lg shadow hover:bg-[#006a9c] transition-colors cursor-pointer">Edit</button>
						<button type="button" id="addBtn" class="bg-green-500 text-white px-4 py-2 rounded-lg shadow hover:bg-green-600 transition-colors cursor-pointer" style="display:none;">Add Variation</button>
					<?php endif; ?>
				</div>
			</div>
			<form method="post" action="/admin/product-variations/save" id="variationForm">
				<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
				<input type="hidden" name="deleted_ids" id="deletedIds">
				<div class="overflow-x-auto">
					<table class="min-w-full bg-white rounded-xl shadow text-center" id="variationTable">
						<thead class="bg-[#062B41] text-white">
							<tr>
								<th class="py-3 px-4 rounded-tl-xl">ID</th>
								<th class="py-3 px-4">Product Name</th>
								<th class="py-3 px-4">Variation Name</th>
								<th class="py-3 px-4">Price</th>
                                
								<?php if (!$isStaff): ?>
									<th class="py-3 px-4 rounded-tr-xl">Actions</th>
								<?php endif; ?>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($variations as $variation): ?>
							<tr class="hover:bg-gray-100 transition-colors">
								<td class="py-2 px-4 border-b border-gray-200 font-semibold text-gray-700"><?= $variation['variation_id'] ?></td>
								<td class="py-2 px-4 border-b border-gray-200">
									<select name="product_id[<?= $variation['variation_id'] ?>]" class="border border-gray-300 rounded-lg px-2 py-1 w-full focus:ring-2 focus:ring-[#062B41] focus:outline-none" disabled>
										<?php foreach ($products as $product): ?>
											<option value="<?= $product['product_id'] ?>" <?= $product['product_id'] == $variation['product_id'] ? 'selected' : '' ?>><?= htmlspecialchars($product['product_name']) ?></option>
										<?php endforeach; ?>
									</select>
								</td>
								<td class="py-2 px-4 border-b border-gray-200">
									<input type="text" name="variation_name[<?= $variation['variation_id'] ?>]" value="<?= htmlspecialchars($variation['variation_name']) ?>" class="border border-gray-300 rounded-lg px-2 py-1 w-full focus:ring-2 focus:ring-[#062B41] focus:outline-none" disabled>
								</td>
								<td class="py-2 px-4 border-b border-gray-200">
									<input type="number" step="0.01" name="price[<?= $variation['variation_id'] ?>]" value="<?= htmlspecialchars($variation['price']) ?>" class="border border-gray-300 rounded-lg px-2 py-1 w-full focus:ring-2 focus:ring-[#062B41] focus:outline-none" disabled>
								</td>
                                
								<?php if (!$isStaff): ?>
								<td class="py-2 px-4 border-b border-gray-200">
									<button type="button" class="deleteBtn bg-red-500 text-white px-3 py-1 rounded-lg shadow hover:bg-red-600 transition-colors cursor-pointer" data-id="<?= $variation['variation_id'] ?>" style="display:none;">Delete</button>
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
	document.querySelectorAll('input, select').forEach(e => e.disabled = false);
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
			const deletedInput = document.getElementById('deletedIds');
			let ids = deletedInput.value ? deletedInput.value.split(',') : [];
			if (!ids.includes(id)) {
				ids.push(id);
				deletedInput.value = ids.join(',');
			}
		}
		row.remove();
	};
});
// Add new row at the end of the table
document.getElementById('addBtn').onclick = function() {
	const table = document.getElementById('variationTable').getElementsByTagName('tbody')[0];
	const row = table.insertRow(-1); // Insert at the end
	row.innerHTML = `
		<td class="py-2 px-4 border-b">New</td>
		<td class="py-2 px-4 border-b">
			<select name="product_id[new][]" class="border rounded px-2 py-1 w-full">
				<?php foreach ($products as $product): ?>
					<option value="<?= $product['product_id'] ?>"><?= htmlspecialchars($product['product_name']) ?></option>
				<?php endforeach; ?>
			</select>
		</td>
		<td class="py-2 px-4 border-b"><input type="text" name="variation_name[new][]" class="border rounded px-2 py-1 w-full"></td>
		<td class="py-2 px-4 border-b"><input type="number" step="0.01" name="price[new][]" class="border rounded px-2 py-1 w-full"></td>
        
		<td class="py-2 px-4 border-b"><button type="button" class="deleteBtn bg-red-500 text-white px-2 py-1 rounded hover:bg-red-600" style="display:inline-block;">Delete</button></td>
	`;
	row.querySelector('.deleteBtn').onclick = function() {
		row.remove();
	};
};
// Cancel button functionality
document.getElementById('cancelBtn').onclick = function() {
	window.location.reload();
};
// Enable all fields before submit
document.getElementById('variationForm').onsubmit = function() {
	document.querySelectorAll('input, select').forEach(e => e.disabled = false);
};
</script>
<?php endif; ?>

<script>
// Search functionality
document.getElementById('searchInput').onkeyup = function() {
	const filter = this.value.toLowerCase();
	document.querySelectorAll('#variationTable tbody tr').forEach(row => {
		const input = row.querySelector('input[name^="variation_name"], input[name^="variation_name[new]"]');
		if (input) {
			const name = input.value.toLowerCase();
			row.style.display = name.includes(filter) ? '' : 'none';
		}
	});
};
</script>
