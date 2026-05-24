<?php
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$promoCodes = $promoCodes ?? [];
?>

<div class="flex-1 p-8">
    <div class="max-w-4xl mx-auto">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-[#062B41]">Promo Codes</h1>
                <p class="text-gray-500 mt-1">Create and manage discount codes with usage limits.</p>
            </div>
            <button onclick="openModal()" class="bg-[#0086C9] text-white px-5 py-2 rounded-xl font-semibold hover:bg-[#08456b] transition-colors flex items-center gap-2 cursor-pointer">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                New Promo Code
            </button>
        </div>

        <?php if (!empty($_SESSION['promo_success'])): ?>
            <div class="mb-5 rounded-xl border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800">
                <?= htmlspecialchars($_SESSION['promo_success']) ?>
            </div>
            <?php unset($_SESSION['promo_success']); ?>
        <?php endif; ?>
        <?php if (!empty($_SESSION['promo_error'])): ?>
            <div class="mb-5 rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
                <?= htmlspecialchars($_SESSION['promo_error']) ?>
            </div>
            <?php unset($_SESSION['promo_error']); ?>
        <?php endif; ?>

        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
            <?php if (empty($promoCodes)): ?>
                <div class="py-16 text-center text-gray-400">
                    <svg class="mx-auto mb-4 h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a2 2 0 012-2z"/></svg>
                    <p class="text-lg font-semibold">No promo codes yet</p>
                    <p class="text-sm mt-1">Click "New Promo Code" to add your first discount code.</p>
                </div>
            <?php else: ?>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50">
                        <th class="px-5 py-3 text-left font-semibold text-gray-600">Code</th>
                        <th class="px-5 py-3 text-left font-semibold text-gray-600">Discount</th>
                        <th class="px-5 py-3 text-left font-semibold text-gray-600">Uses</th>
                        <th class="px-5 py-3 text-left font-semibold text-gray-600">Expires</th>
                        <th class="px-5 py-3 text-left font-semibold text-gray-600">Status</th>
                        <th class="px-5 py-3 text-right font-semibold text-gray-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($promoCodes as $promo): ?>
                    <?php
                        $isActive   = (int)$promo['active'] === 1;
                        $maxUses    = (int)$promo['max_uses'];
                        $usesCount  = (int)$promo['uses_count'];
                        $exhausted  = $usesCount >= $maxUses;
                        $expired    = !empty($promo['expires_at']) && strtotime($promo['expires_at']) < strtotime('today');
                        $statusLabel = !$isActive ? 'Inactive' : ($exhausted ? 'Exhausted' : ($expired ? 'Expired' : 'Active'));
                        $statusClass = $statusLabel === 'Active'
                            ? 'bg-green-100 text-green-800'
                            : ($statusLabel === 'Inactive' ? 'bg-gray-100 text-gray-600' : 'bg-red-100 text-red-700');
                        $discountLabel = $promo['type'] === 'percent'
                            ? number_format($promo['value'], 0) . '% off'
                            : '$' . number_format($promo['value'], 2) . ' off';
                    ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-4 font-mono font-bold text-[#062B41] tracking-wide"><?= htmlspecialchars($promo['code']) ?></td>
                        <td class="px-5 py-4 text-gray-700"><?= $discountLabel ?></td>
                        <td class="px-5 py-4 text-gray-700">
                            <span class="<?= $exhausted ? 'text-red-600 font-semibold' : '' ?>"><?= $usesCount ?></span>
                            <span class="text-gray-400">/ <?= $maxUses ?></span>
                        </td>
                        <td class="px-5 py-4 text-gray-500">
                            <?= !empty($promo['expires_at']) ? date('M j, Y', strtotime($promo['expires_at'])) : '<span class="text-gray-400">Never</span>' ?>
                        </td>
                        <td class="px-5 py-4">
                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $statusClass ?>"><?= $statusLabel ?></span>
                        </td>
                        <td class="px-5 py-4 text-right">
                            <div class="inline-flex gap-2">
                                <button
                                    onclick='openModal(<?= htmlspecialchars(json_encode($promo), ENT_QUOTES) ?>)'
                                    class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-100 transition-colors">
                                    Edit
                                </button>
                                <form method="post" action="/admin/promo-codes/delete" onsubmit="return confirm('Delete promo code <?= htmlspecialchars($promo['code'], ENT_QUOTES) ?>?')">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="id" value="<?= (int)$promo['id'] ?>">
                                    <button type="submit" class="rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100 transition-colors">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add / Edit Modal -->
<div id="promo-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-[#062B41]/60 px-4">
    <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
            <h2 id="modal-title" class="text-lg font-bold text-[#062B41]">New Promo Code</h2>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="post" action="/admin/promo-codes/save" class="p-6 space-y-5">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="id" id="modal-id" value="">

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-semibold text-gray-700">Code <span class="text-red-500">*</span></label>
                    <input type="text" name="code" id="modal-code" required maxlength="32"
                        placeholder="e.g. WELCOME10"
                        class="w-full rounded-xl border border-gray-300 px-4 py-2 font-mono text-sm uppercase focus:outline-none focus:ring-2 focus:ring-[#062B41]"
                        oninput="this.value = this.value.toUpperCase()">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold text-gray-700">Discount Type <span class="text-red-500">*</span></label>
                    <select name="type" id="modal-type" required class="w-full rounded-xl border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#062B41]">
                        <option value="percent">Percentage (%)</option>
                        <option value="fixed">Fixed Amount ($)</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold text-gray-700">Value <span class="text-red-500">*</span></label>
                    <input type="number" name="value" id="modal-value" required min="0.01" step="0.01"
                        placeholder="e.g. 10"
                        class="w-full rounded-xl border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#062B41]">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold text-gray-700">Max Uses <span class="text-red-500">*</span></label>
                    <input type="number" name="max_uses" id="modal-max-uses" required min="1" value="1"
                        class="w-full rounded-xl border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#062B41]">
                    <p class="mt-1 text-xs text-gray-500">How many times this code can be used total.</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold text-gray-700">Expires On</label>
                    <input type="date" name="expires_at" id="modal-expires-at"
                        class="w-full rounded-xl border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#062B41]">
                    <p class="mt-1 text-xs text-gray-500">Leave blank for no expiry.</p>
                </div>
                <div class="flex items-center gap-3 sm:col-span-2">
                    <input type="checkbox" name="active" id="modal-active" value="1" class="h-4 w-4 rounded border-gray-300 text-[#0086C9]" checked>
                    <label for="modal-active" class="text-sm font-semibold text-gray-700">Active (can be applied at checkout)</label>
                </div>
            </div>

            <div class="flex justify-end gap-3 border-t border-gray-100 pt-4">
                <button type="button" onclick="closeModal()" class="rounded-xl border border-gray-300 px-5 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors">Cancel</button>
                <button type="submit" class="rounded-xl bg-[#0086C9] px-5 py-2 text-sm font-semibold text-white hover:bg-[#08456b] transition-colors">Save Code</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(promo) {
    const modal = document.getElementById('promo-modal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    if (promo) {
        document.getElementById('modal-title').textContent = 'Edit Promo Code';
        document.getElementById('modal-id').value     = promo.id;
        document.getElementById('modal-code').value   = promo.code;
        document.getElementById('modal-type').value   = promo.type;
        document.getElementById('modal-value').value  = promo.value;
        document.getElementById('modal-max-uses').value = promo.max_uses;
        document.getElementById('modal-expires-at').value = promo.expires_at ?? '';
        document.getElementById('modal-active').checked = parseInt(promo.active, 10) === 1;
    } else {
        document.getElementById('modal-title').textContent = 'New Promo Code';
        document.getElementById('modal-id').value     = '';
        document.getElementById('modal-code').value   = '';
        document.getElementById('modal-type').value   = 'percent';
        document.getElementById('modal-value').value  = '';
        document.getElementById('modal-max-uses').value = 1;
        document.getElementById('modal-expires-at').value = '';
        document.getElementById('modal-active').checked = true;
    }
}

function closeModal() {
    const modal = document.getElementById('promo-modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

document.getElementById('promo-modal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>
