<!-- filepath: c:\xampp\htdocs\GetAroundMobility\src\Views\admin\customers.php -->

<?php
// Provide safe defaults to avoid warnings if not set
if (!isset($search)) $search = '';
if (!isset($totalPages)) $totalPages = 1;
?>
    <div class="flex flex-1 items-center justify-center w-full">
        <div class="bg-white rounded-2xl shadow-xl p-10 w-full max-w-6xl mx-auto border border-blue-200">
            <h2 class="text-3xl font-bold mb-8 text-[#062B41] tracking-tight">Customers</h2>
            <!-- Search Bar -->
            <form method="get" class="mb-6 flex flex-wrap items-center gap-4">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name..." class="border border-blue-200 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-300 focus:border-blue-400 transition text-base w-64">
                <button type="submit" class="bg-[#0086C9] text-white px-6 py-2 rounded-lg shadow hover:bg-[#006a9c] font-semibold transition cursor-pointer">Search</button>
            </form>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white rounded-xl shadow text-base border border-gray-200">
                    <colgroup>
                        <col style="width: 7%">
                        <col style="width: 18%">
                        <col style="width: 20%">
                        <col style="width: 13%">
                        <col style="width: 20%">
                        <col style="width: 12%">
                        <col style="width: 10%">
                    </colgroup>
                    <thead class="bg-blue-100 text-blue-900">
                        <tr>
                            <th class="py-3 px-4 rounded-tl-xl">ID</th>
                            <th class="py-3 px-4">Name</th>
                            <th class="py-3 px-4">Email</th>
                            <th class="py-3 px-4">Phone</th>
                            <th class="py-3 px-4">Address</th>
                            <th class="py-3 px-4">Created At</th>
                            <th class="py-3 px-4 rounded-tr-xl">Account Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $customer): ?>
                        <tr class="hover:bg-blue-50 transition-colors">
                            <td class="py-2 px-4 border-b border-gray-200 align-middle font-semibold text-blue-900"><?= htmlspecialchars($customer['user_id']) ?></td>
                            <td class="py-2 px-4 border-b border-gray-200 align-middle font-semibold"><?= htmlspecialchars($customer['name']) ?></td>
                            <td class="py-2 px-4 border-b border-gray-200 align-middle"><?= htmlspecialchars($customer['email']) ?></td>
                            <td class="py-2 px-4 border-b border-gray-200 align-middle"><?= htmlspecialchars($customer['phone']) ?></td>
                            <td class="py-2 px-4 border-b border-gray-200 align-middle"><?= htmlspecialchars($customer['address']) ?></td>
                            <td class="py-2 px-4 border-b border-gray-200 align-middle text-sm"><?= htmlspecialchars($customer['created_at']) ?></td>
                            <td class="py-2 px-4 border-b border-gray-200 align-middle">
                                <?php if (empty($customer['password_hash'])): ?>
                                    <span class="inline-block bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-xs font-semibold">Guest</span>
                                <?php else: ?>
                                    <span class="inline-block bg-green-100 text-green-800 px-3 py-1 rounded-full text-xs font-semibold">Registered</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($customers)): ?>
                        <tr>
                            <td colspan="7" class="py-6 px-4 text-center text-gray-500 text-lg">No customers found.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="flex justify-center mt-8 gap-2">
                <?php
                $queryBase = '?';
                if ($search !== '') {
                    $queryBase .= 'search=' . urlencode($search) . '&';
                }
                for ($i = 1; $i <= $totalPages; $i++):
                ?>
                    <a href="<?= $queryBase . 'page=' . $i ?>"
                        class="px-4 py-2 rounded-lg border transition font-semibold <?= $i == $page ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-blue-700 border-blue-200 hover:bg-blue-50' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>