<!-- filepath: c:\xampp\htdocs\GetAroundMobility\src\Views\admin\admins.php -->
<?php

$pdo = new PDO('mysql:host=localhost;dbname=getaround_db', 'getaroundmobility', 'itup420');
$admins = $pdo->query("SELECT id, username, role, created_at FROM admins ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$isSuperAdmin = (isset($_SESSION['admin_role']) && strtolower($_SESSION['admin_role']) === 'superadmin');
$roleLabels = [
    'superadmin' => 'Super Admin',
    'admin' => 'Admin',
    'staff' => 'Staff'
];

?>

    
    <div class="flex flex-1 items-center justify-center w-full">
        <div class="bg-white rounded-2xl shadow-xl p-10 w-full max-w-4xl mx-auto border border-blue-200">
            <h2 class="text-3xl font-bold mb-8 text-[#062B41] tracking-tight">Admins</h2>
            <div class="flex flex-wrap items-center justify-between mb-6">
                <div class="flex items-center gap-4">
                    <span class="font-bold text-base">Roles:</span>
                    <span class="inline-block bg-purple-100 text-purple-800 px-3 py-1 rounded-full text-xs font-semibold mr-2">Super Admin</span>
                    <span class="inline-block bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-xs font-semibold mr-2">Admin</span>
                    <span class="inline-block bg-gray-100 text-gray-800 px-3 py-1 rounded-full text-xs font-semibold">Staff</span>
                </div>
                <?php if ($isSuperAdmin): ?>
                    <a href="/admin/admins/add" class="bg-[#0086C9] text-white px-6 py-2 rounded-lg shadow hover:bg-[#006a9c] font-semibold transition cursor-pointer">Add Admin</a>
                <?php endif; ?>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white rounded-xl shadow text-base border border-gray-200">
                    <colgroup>
                        <col style="width: 10%">
                        <col style="width: 30%">
                        <col style="width: 20%">
                        <col style="width: 25%">
                        <col style="width: 15%">
                    </colgroup>
                    <thead class="bg-blue-100 text-blue-900">
                        <tr>
                            <th class="py-3 px-4 rounded-tl-xl text-left">ID</th>
                            <th class="py-3 px-4 text-left">Username</th>
                            <th class="py-3 px-4 text-left">Role</th>
                            <th class="py-3 px-4 text-left">Created At</th>
                            <?php if ($isSuperAdmin): ?>
                                <th class="py-3 px-4 rounded-tr-xl text-left">Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admins as $admin): ?>
                        <tr class="hover:bg-blue-50 transition-colors">
                            <td class="py-2 px-4 border-b border-gray-200 align-middle font-semibold text-blue-900"><?= htmlspecialchars($admin['id']) ?></td>
                            <td class="py-2 px-4 border-b border-gray-200 align-middle font-semibold"><?= htmlspecialchars($admin['username']) ?></td>
                            <td class="py-2 px-4 border-b border-gray-200 align-middle">
                                <?php
                                    $role = strtolower($admin['role']);
                                    $badgeClass = '';
                                    if ($role === 'superadmin') {
                                        $badgeClass = 'bg-purple-100 text-purple-800';
                                    } elseif ($role === 'admin') {
                                        $badgeClass = 'bg-blue-100 text-blue-800';
                                    } else {
                                        $badgeClass = 'bg-gray-100 text-gray-800';
                                    }
                                ?>
                                <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold <?= $badgeClass ?>">
                                    <?= htmlspecialchars($roleLabels[$role] ?? $admin['role']) ?>
                                </span>
                            </td>
                            <td class="py-2 px-4 border-b border-gray-200 align-middle text-sm"><?= htmlspecialchars($admin['created_at']) ?></td>
                            <?php if ($isSuperAdmin): ?>
                            <td class="py-2 px-4 border-b border-gray-200 align-middle">
                                <a href="/admin/admins/edit?id=<?= urlencode($admin['id']) ?>" class="text-blue-600 hover:underline mr-4 font-semibold">Edit</a>
                                <?php if ($admin['role'] !== 'superadmin'): ?>
                                <form method="post" action="/admin/admins/delete" style="display:inline;" onsubmit="return confirm('Delete this admin?');">
                                    <input type="hidden" name="id" value="<?= htmlspecialchars($admin['id']) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <button type="submit" class="text-red-600 hover:underline bg-transparent border-none cursor-pointer font-semibold">Delete</button>
                                </form>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($admins)): ?>
                        <tr>
                            <td colspan="<?= $isSuperAdmin ? 5 : 4 ?>" class="py-6 px-4 text-center text-gray-500 text-lg">No Admins found.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
