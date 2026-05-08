
    <!-- Main Content -->
    <div class="flex flex-row flex-1 min-h-[500px]">
        <div class="flex-1 flex items-center justify-center bg-white">
            <div class="w-full max-w-md p-8 bg-white rounded-lg shadow-lg">
                <div class="text-center mb-6">
                    <img src="/img/Original logo.svg" alt="Your Logo" class="mx-auto max-h-16 h-12">
                </div>
                <h1 class="text-2xl font-bold text-center mb-4 font-[Barlow]">Login</h1>
                <p class="text-center text-gray-500 mb-6">Please enter your details.</p>

                <!-- Display success message if exists -->
                <?php if (!empty($success)): ?>
                    <div class="bg-green-100 text-green-700 p-2 rounded mb-4 text-center"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                
                <!-- Display error message if exists -->
                <?php if (!empty($error)): ?>
                    <div class="bg-red-100 text-red-700 p-2 rounded mb-4 text-center"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="post" action="/login" class="space-y-6">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" name="email" required placeholder="Enter your email"
                            class="h-12 px-3 mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Password</label>
                        <input type="password" name="password" required
                            class="h-12 px-3 mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <input type="hidden" name="return" value="<?= htmlspecialchars($returnUrl) ?>">
                    <button type="submit" class="w-full bg-[#0086C9] text-white py-2 px-4 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Sign in</button>
                    <p class="text-center text-sm text-gray-600 mt-2">
                        <a href="/forgot-password" class="text-indigo-600 hover:underline">Forgot Password?</a>
                    </p>
                    <p class="text-center text-sm text-gray-600 mt-4">
                        Don't have an account? <a href="javascript:void(0);" onclick="openRegisterModal()" class="text-blue-600 font-semibold">Register Now</a>
                    </p>
                </form>
            </div>
        </div>

        <!-- LOGIN PICTURE -->
        <div class="flex-1 flex items-center bg-gray-100">
            <img src="/img/login.svg" alt="Login Illustration" class="max-w-full max-h-[1000px] object-contain">
        </div>
    </div>