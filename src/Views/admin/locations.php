<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$role = strtolower($_SESSION['admin_role'] ?? '');
$isStaff = ($role === 'staff');
// Assumes $partnerHotels and $pickupLocations are provided by the controller
?>
<?php
$activeTab = $_GET['tab'] ?? '';
?>
<div class="flex flex-1 items-center justify-center w-full">
    <div class="bg-white rounded-2xl shadow-xl p-10 w-full max-w-6xl mx-auto border border-blue-200">
        <h2 class="text-3xl font-bold mb-8 text-[#062B41] tracking-tight">Locations Management</h2>
        <div class="overflow-x-auto">
            <div class="flex border-b mb-6">
                <button id="tab-hotels" class="tab-btn flex-1 py-3 px-4 text-center font-semibold text-lg border-r border-blue-200 focus:outline-none transition-all duration-200 text-blue-900 hover:bg-blue-50 active cursor-pointer" onclick="showTab('hotels')">
                    <span class="inline-flex items-center gap-2">Hotels</span>
                </button>
                <button id="tab-pickups" class="tab-btn flex-1 py-3 px-4 text-center font-semibold text-lg focus:outline-none transition-all duration-200 text-blue-900 hover:bg-blue-50 cursor-pointer" onclick="showTab('pickups')">
                    <span class="inline-flex items-center gap-2">Pickups</span>
                </button>
            </div>
            <!-- Hotels Tab -->
            <div id="tab-content-hotels" class="tab-content">
                <form method="post" action="/admin/locations?tab=hotels" id="hotelsForm">
                    <input type="hidden" name="tab" value="hotels">
                    <div class="flex items-center mb-6">
                        <h3 class="text-2xl font-bold text-blue-800 flex items-center gap-2 mr-4">Partner Hotels</h3>
                        <?php if (!$isStaff): ?>
                        <button type="button" id="editHotelsBtn" class="ml-auto bg-blue-500 text-white px-4 py-2 rounded shadow font-semibold flex items-center gap-2 cursor-pointer">Edit</button>
                        <button type="button" id="addHotelBtn" class="ml-2 bg-green-500 text-white px-4 py-2 rounded shadow font-semibold flex items-center gap-2 cursor-pointer" style="display:none;">Add Hotel</button>
                        <button type="submit" id="saveHotelsBtn" class="ml-2 bg-blue-600 text-white px-4 py-2 rounded shadow font-semibold flex items-center gap-2 cursor-pointer" style="display:none;">Save</button>
                        <button type="button" id="cancelHotelsBtn" class="ml-2 bg-gray-400 text-white px-4 py-2 rounded shadow font-semibold flex items-center gap-2 cursor-pointer" style="display:none;">Cancel</button>
                        <input type="hidden" name="deleted_ids" id="deletedHotelIds">
                        <?php endif; ?>
                    </div>
                    <div class="overflow-x-auto rounded-lg shadow">
                        <table class="w-full text-sm bg-white" id="hotelsTable">
                            <thead>
                                <tr class="bg-blue-100 text-blue-900">
                                    <th class="p-3">Name</th>
                                    <th class="p-3">Address 1</th>
                                    <th class="p-3">Address 2</th>
                                    <th class="p-3">State</th>
                                    <th class="p-3">ZIP</th>
                                    <?php if (!$isStaff): ?><th class="p-3">Actions</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($partnerHotels as $hotel): ?>
                                <tr class="hover:bg-blue-50 transition">
                                    <td class="p-2"><input type="text" name="hotels[<?= $hotel['id'] ?>][name]" value="<?= htmlspecialchars($hotel['name']) ?>" class="border rounded px-2 py-1 w-full" disabled></td>
                                    <td class="p-2"><input type="text" name="hotels[<?= $hotel['id'] ?>][address1]" value="<?= htmlspecialchars($hotel['address1']) ?>" class="border rounded px-2 py-1 w-full" disabled></td>
                                    <td class="p-2"><input type="text" name="hotels[<?= $hotel['id'] ?>][address2]" value="<?= htmlspecialchars($hotel['address2']) ?>" class="border rounded px-2 py-1 w-full" disabled></td>
                                    <td class="p-2"><input type="text" name="hotels[<?= $hotel['id'] ?>][state]" value="<?= htmlspecialchars($hotel['state']) ?>" class="border rounded px-2 py-1 w-full" disabled></td>
                                    <td class="p-2"><input type="text" name="hotels[<?= $hotel['id'] ?>][zip]" value="<?= htmlspecialchars($hotel['zip']) ?>" class="border rounded px-2 py-1 w-full" disabled></td>
                                    <?php if (!$isStaff): ?>
                                    <td class="p-2">
                                        <button type="button" class="deleteHotelBtn bg-red-500 text-white px-3 py-1 rounded shadow hover:bg-red-600 cursor-pointer" data-id="<?= $hotel['id'] ?>" style="display:none;">Delete</button>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
            <!-- Pickups Tab -->
            <div id="tab-content-pickups" class="tab-content hidden">
                <form method="post" action="/admin/locations?tab=pickups" id="pickupsForm">
                    <input type="hidden" name="tab" value="pickups">
                    <div class="flex items-center mb-6">
                        <h3 class="text-2xl font-bold text-blue-800 flex items-center gap-2 mr-4">Pickup Locations</h3>
                        <?php if (!$isStaff): ?>
                        <button type="button" id="editPickupsBtn" class="ml-auto bg-blue-500 text-white px-4 py-2 rounded shadow font-semibold flex items-center gap-2 cursor-pointer">Edit</button>
                        <button type="button" id="addPickupBtn" class="ml-2 bg-green-500 text-white px-4 py-2 rounded shadow font-semibold flex items-center gap-2 cursor-pointer" style="display:none;">Add Pickup</button>
                        <button type="submit" id="savePickupsBtn" class="ml-2 bg-blue-600 text-white px-4 py-2 rounded shadow font-semibold flex items-center gap-2 cursor-pointer" style="display:none;">Save</button>
                        <button type="button" id="cancelPickupsBtn" class="ml-2 bg-gray-400 text-white px-4 py-2 rounded shadow font-semibold flex items-center gap-2 cursor-pointer" style="display:none;">Cancel</button>
                        <input type="hidden" name="deleted_ids" id="deletedPickupIds">
                        <?php endif; ?>
                    </div>
                    <div class="overflow-x-auto rounded-lg shadow">
                        <table class="w-full text-sm bg-white" id="pickupsTable">
                            <thead>
                                <tr class="bg-blue-100 text-blue-900">
                                    <th class="p-3">Name</th>
                                    <th class="p-3">Address</th>
                                    <?php if (!$isStaff): ?><th class="p-3">Actions</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pickupLocations as $pickup): ?>
                                <tr class="hover:bg-blue-50 transition">
                                    <td class="p-2"><input type="text" name="pickups[<?= $pickup['id'] ?>][name]" value="<?= htmlspecialchars($pickup['name']) ?>" class="border rounded px-2 py-1 w-full" disabled></td>
                                    <td class="p-2"><input type="text" name="pickups[<?= $pickup['id'] ?>][address]" value="<?= htmlspecialchars($pickup['address']) ?>" class="border rounded px-2 py-1 w-full" disabled></td>
                                    <?php if (!$isStaff): ?>
                                    <td class="p-2">
                                        <button type="button" class="deletePickupBtn bg-red-500 text-white px-3 py-1 rounded shadow hover:bg-red-600 cursor-pointer" data-id="<?= $pickup['id'] ?>" style="display:none;">Delete</button>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php if (!$isStaff): ?>
<script>
// --- Hotels CRUD ---
const hotelsForm = document.getElementById('hotelsForm');
const editHotelsBtn = document.getElementById('editHotelsBtn');
const addHotelBtn = document.getElementById('addHotelBtn');
const saveHotelsBtn = document.getElementById('saveHotelsBtn');
const cancelHotelsBtn = document.getElementById('cancelHotelsBtn');
const deletedHotelIds = document.getElementById('deletedHotelIds');
const hotelsTable = document.getElementById('hotelsTable').getElementsByTagName('tbody')[0];

editHotelsBtn.onclick = function() {
    hotelsForm.querySelectorAll('input[type="text"]').forEach(e => e.disabled = false);
    hotelsForm.querySelectorAll('.deleteHotelBtn').forEach(e => e.style.display = 'inline-block');
    addHotelBtn.style.display = 'inline-block';
    saveHotelsBtn.style.display = 'inline-block';
    cancelHotelsBtn.style.display = 'inline-block';
    editHotelsBtn.style.display = 'none';
};
addHotelBtn.onclick = function() {
    const row = hotelsTable.insertRow(-1);
    row.innerHTML = `
        <td class="p-2"><input type="text" name="hotels[new][name][]" class="border rounded px-2 py-1 w-full"></td>
        <td class="p-2"><input type="text" name="hotels[new][address1][]" class="border rounded px-2 py-1 w-full"></td>
        <td class="p-2"><input type="text" name="hotels[new][address2][]" class="border rounded px-2 py-1 w-full"></td>
        <td class="p-2"><input type="text" name="hotels[new][state][]" class="border rounded px-2 py-1 w-full"></td>
        <td class="p-2"><input type="text" name="hotels[new][zip][]" class="border rounded px-2 py-1 w-full"></td>
        <td class="p-2"><button type="button" class="deleteHotelBtn bg-red-500 text-white px-3 py-1 rounded shadow hover:bg-red-600 cursor-pointer" style="display:inline-block;">Delete</button></td>
    `;
    row.querySelector('.deleteHotelBtn').onclick = function() { row.remove(); };
};
hotelsForm.querySelectorAll('.deleteHotelBtn').forEach(btn => {
    btn.onclick = function() {
        const row = btn.closest('tr');
        const id = btn.getAttribute('data-id');
        if (id && id !== 'New') {
            deletedHotelIds.value += (deletedHotelIds.value ? ',' : '') + id;
        }
        row.remove();
    };
});
cancelHotelsBtn.onclick = function() { window.location.reload(); };
// --- Pickups CRUD ---
const pickupsForm = document.getElementById('pickupsForm');
const editPickupsBtn = document.getElementById('editPickupsBtn');
const addPickupBtn = document.getElementById('addPickupBtn');
const savePickupsBtn = document.getElementById('savePickupsBtn');
const cancelPickupsBtn = document.getElementById('cancelPickupsBtn');
const deletedPickupIds = document.getElementById('deletedPickupIds');
const pickupsTable = document.getElementById('pickupsTable').getElementsByTagName('tbody')[0];

editPickupsBtn.onclick = function() {
    pickupsForm.querySelectorAll('input[type="text"]').forEach(e => e.disabled = false);
    pickupsForm.querySelectorAll('.deletePickupBtn').forEach(e => e.style.display = 'inline-block');
    addPickupBtn.style.display = 'inline-block';
    savePickupsBtn.style.display = 'inline-block';
    cancelPickupsBtn.style.display = 'inline-block';
    editPickupsBtn.style.display = 'none';
};
addPickupBtn.onclick = function() {
    const row = pickupsTable.insertRow(-1);
    row.innerHTML = `
        <td class="p-2"><input type="text" name="pickups[new][name][]" class="border rounded px-2 py-1 w-full"></td>
        <td class="p-2"><input type="text" name="pickups[new][address][]" class="border rounded px-2 py-1 w-full"></td>
        <td class="p-2"><button type="button" class="deletePickupBtn bg-red-500 text-white px-3 py-1 rounded shadow hover:bg-red-600 cursor-pointer" style="display:inline-block;">Delete</button></td>
    `;
    row.querySelector('.deletePickupBtn').onclick = function() { row.remove(); };
};
pickupsForm.querySelectorAll('.deletePickupBtn').forEach(btn => {
    btn.onclick = function() {
        const row = btn.closest('tr');
        const id = btn.getAttribute('data-id');
        if (id && id !== 'New') {
            deletedPickupIds.value += (deletedPickupIds.value ? ',' : '') + id;
        }
        row.remove();
    };
});
cancelPickupsBtn.onclick = function() { window.location.reload(); };
</script>
<?php endif; ?>

<script>
function showTab(tab) {
    document.getElementById('tab-content-hotels').classList.add('hidden');
    document.getElementById('tab-content-pickups').classList.add('hidden');
    document.getElementById('tab-hotels').classList.remove('active');
    document.getElementById('tab-pickups').classList.remove('active');

    if (tab === 'pickups') {
        document.getElementById('tab-content-pickups').classList.remove('hidden');
        document.getElementById('tab-pickups').classList.add('active');
    } else {
        document.getElementById('tab-content-hotels').classList.remove('hidden');
        document.getElementById('tab-hotels').classList.add('active');
        tab = 'hotels';
    }

    localStorage.setItem('adminLocationsActiveTab', tab);
}

document.addEventListener('DOMContentLoaded', function() {
    const hotelsTabButton = document.getElementById('tab-hotels');
    const pickupsTabButton = document.getElementById('tab-pickups');
    const urlTab = <?= json_encode($activeTab === 'pickups' ? 'pickups' : '') ?>;
    const storedTab = localStorage.getItem('adminLocationsActiveTab');
    const initialTab = urlTab || storedTab || 'hotels';

    if (hotelsTabButton) {
        hotelsTabButton.addEventListener('click', function() {
            showTab('hotels');
        });
    }

    if (pickupsTabButton) {
        pickupsTabButton.addEventListener('click', function() {
            showTab('pickups');
        });
    }

    showTab(initialTab);
});
</script>
<style>
.tab-btn.active {
    background: #fff;
    color: #2563eb;
    border-bottom: 4px solid #2563eb;
    z-index: 1;
    box-shadow: 0 2px 8px 0 #2563eb22;
}
input:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 2px #2563eb33;
}
table th, table td {
    border: none !important;
}
button, .tab-btn {
    cursor: pointer !important;
}
</style>
