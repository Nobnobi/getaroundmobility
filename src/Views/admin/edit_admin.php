<!-- filepath: c:\xampp\htdocs\GetAroundMobility\src\Views\admin\edit_admin.php -->

    <div class="flex-1 flex flex-col">
        <main class="flex-1 p-6">
            <div class="bg-white rounded shadow p-4 max-w-md mx-auto">
                <h2 class="text-xl font-bold mb-4">Edit Admin</h2>
                <form method="post" action="/admin/admins/edit?id=<?= urlencode($admin['id']) ?>">
                    <label class="block mb-2">Username</label>
                    <input type="text" name="username" value="<?= htmlspecialchars($admin['username']) ?>" required class="w-full border rounded px-3 py-2 mb-4">
                    <label class="block mb-2">Role</label>
                    <select name="role" class="w-full border rounded px-3 py-2 mb-4">
                        <option value="admin" <?= $admin['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="staff" <?= $admin['role'] === 'staff' ? 'selected' : '' ?>>Staff</option>
                        <option value="superadmin" <?= $admin['role'] === 'superadmin' ? 'selected' : '' ?>>Super Admin</option>
                    </select>
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Update Admin</button>
                </form>
            </div>
        </main>
    </div>
