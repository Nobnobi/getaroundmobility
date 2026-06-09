<?php
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$options = $options ?? [];
?>

<div class="flex-1 p-8">
    <div class="max-w-4xl mx-auto">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-[#062B41]">Checkout Referral Sources</h1>
                <p class="text-gray-500 mt-1">Manage the options shown in "Where did you hear about us?" on checkout.</p>
            </div>
            <button onclick="openSourceModal()" class="bg-[#0086C9] text-white px-5 py-2 rounded-xl font-semibold hover:bg-[#08456b] transition-colors flex items-center gap-2 cursor-pointer">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                New Option
            </button>
        </div>

        <?php if (!empty($_SESSION['heard_about_success'])): ?>
            <div class="mb-5 rounded-xl border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800">
                <?= htmlspecialchars($_SESSION['heard_about_success']) ?>
            </div>
            <?php unset($_SESSION['heard_about_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['heard_about_error'])): ?>
            <div class="mb-5 rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
                <?= htmlspecialchars($_SESSION['heard_about_error']) ?>
            </div>
            <?php unset($_SESSION['heard_about_error']); ?>
        <?php endif; ?>

        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
            <?php if (empty($options)): ?>
                <div class="py-16 text-center text-gray-400">
                    <p class="text-lg font-semibold">No options yet</p>
                    <p class="text-sm mt-1">Click "New Option" to add your first source.</p>
                </div>
            <?php else: ?>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50">
                            <th class="px-5 py-3 text-left font-semibold text-gray-600">Label</th>
                            <th class="px-5 py-3 text-left font-semibold text-gray-600">Sort Order</th>
                            <th class="px-5 py-3 text-left font-semibold text-gray-600">Status</th>
                            <th class="px-5 py-3 text-right font-semibold text-gray-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($options as $option): ?>
                            <?php $active = (int)($option['is_active'] ?? 0) === 1; ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-5 py-4 text-gray-800 font-semibold"><?= htmlspecialchars($option['label']) ?></td>
                                <td class="px-5 py-4 text-gray-700"><?= (int)$option['sort_order'] ?></td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' ?>">
                                        <?= $active ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <div class="inline-flex gap-2">
                                        <button
                                            onclick='openSourceModal(<?= htmlspecialchars(json_encode($option), ENT_QUOTES) ?>)'
                                            class="rounded-lg cursor-pointer border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-100 transition-colors">
                                            Edit
                                        </button>
                                        <form method="post" action="/admin/heard-about-options/delete" onsubmit="return confirm('Delete this option?')">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="id" value="<?= (int)$option['id'] ?>">
                                            <button type="submit" class="rounded-lg cursor-pointer border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100 transition-colors">
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

<div id="source-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-[#062B41]/60 px-4">
    <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
            <h2 id="source-modal-title" class="text-lg font-bold text-[#062B41]">New Option</h2>
            <button onclick="closeSourceModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <svg class="h-6 w-6 cursor-pointer" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form method="post" action="/admin/heard-about-options/save" class="p-6 space-y-5">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="id" id="source-id" value="">

            <div>
                <label class="mb-1 block text-sm font-semibold text-gray-700">Label <span class="text-red-500">*</span></label>
                <input type="text" name="label" id="source-label" required maxlength="120"
                    placeholder="e.g. Google Search"
                    class="w-full rounded-xl border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#062B41]">
            </div>

            <div>
                <label class="mb-1 block text-sm font-semibold text-gray-700">Sort Order</label>
                <input type="number" name="sort_order" id="source-sort-order" value="0"
                    class="w-full rounded-xl border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#062B41]">
            </div>

            <div class="flex items-center gap-3">
                <input type="checkbox" name="is_active" id="source-active" value="1" class="h-4 w-4 rounded border-gray-300 text-[#0086C9]" checked>
                <label for="source-active" class="text-sm font-semibold text-gray-700">Active</label>
            </div>

            <div class="flex justify-end gap-3 border-t border-gray-100 pt-4">
                <button type="button" onclick="closeSourceModal()" class="rounded-xl cursor-pointer border border-gray-300 px-5 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors">Cancel</button>
                <button type="submit" class="rounded-xl cursor-pointer bg-[#0086C9] px-5 py-2 text-sm font-semibold text-white hover:bg-[#08456b] transition-colors">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
function openSourceModal(option) {
    const modal = document.getElementById('source-modal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    if (option) {
        document.getElementById('source-modal-title').textContent = 'Edit Option';
        document.getElementById('source-id').value = option.id || '';
        document.getElementById('source-label').value = option.label || '';
        document.getElementById('source-sort-order').value = option.sort_order || 0;
        document.getElementById('source-active').checked = Number(option.is_active) === 1;
    } else {
        document.getElementById('source-modal-title').textContent = 'New Option';
        document.getElementById('source-id').value = '';
        document.getElementById('source-label').value = '';
        document.getElementById('source-sort-order').value = 0;
        document.getElementById('source-active').checked = true;
    }
}

function closeSourceModal() {
    const modal = document.getElementById('source-modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

document.getElementById('source-modal').addEventListener('click', function(e) {
    if (e.target === this) closeSourceModal();
});
</script>
