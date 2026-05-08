<!-- filepath: c:\xampp\htdocs\GetAroundMobility\src\Views\admin\add_admin.php -->

    <div class="flex flex-1 items-center justify-center w-full">
        <div class="bg-white rounded-2xl shadow-xl p-10 w-full max-w-lg mx-auto border border-blue-200">
            <h2 class="text-3xl font-bold mb-8 text-[#062B41] tracking-tight text-center">Add Admin</h2>
            <form method="post" action="/admin/admins/add" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div>
                    <label class="block mb-2 font-semibold text-blue-900">Username</label>
                    <input type="text" name="username" required class="w-full border border-blue-200 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-300 focus:border-blue-400 transition text-base">
                </div>
                <div>
                    <label class="block mb-2 font-semibold text-blue-900">Password</label>
                    <input type="password" name="password" required class="w-full border border-blue-200 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-300 focus:border-blue-400 transition text-base">
                </div>
                <div>
                    <label class="block mb-2 font-semibold text-blue-900">Role</label>
                    <select name="role" class="w-full border border-blue-200 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-300 focus:border-blue-400 transition text-base">
                        <option value="admin">Admin</option>
                        <option value="staff">Staff</option>
                        <option value="superadmin">Super Admin</option>
                    </select>
                </div>
                <div class="flex justify-center gap-4">
                    <button type="submit" class="bg-[#0086C9] text-white px-8 py-3 rounded-lg shadow hover:bg-[#006a9c] font-semibold text-lg transition cursor-pointer">Add Admin</button>
                    <a href="/admin/admins" class="bg-gray-300 text-gray-800 px-8 py-3 rounded-lg shadow hover:bg-gray-400 font-semibold text-lg transition cursor-pointer flex items-center justify-center">Cancel</a>
                </div>
            </form>
        </div>
