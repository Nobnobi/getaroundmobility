<?php
$error = $error ?? '';
$success = $success ?? '';
?>

    <div class="flex-1 flex items-center justify-center">
        <div class="w-full max-w-md p-8 bg-white rounded-lg shadow-lg">
            <h1 class="text-2xl font-bold text-center mb-4 font-[Barlow]">Forgot Password</h1>
            <p class="text-center text-gray-500 mb-6">Enter your email to reset your password.</p>
            <?php if (!empty($error)): ?>
                <div class="bg-red-100 text-red-700 p-2 rounded mb-4 text-center"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="bg-green-100 text-green-700 p-2 rounded mb-4 text-center"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <?php if (empty($success)): ?>
                <form method="post" action="/forgot-password" class="space-y-6" id="forgotPasswordForm">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" name="email" required placeholder="Enter your email"
                            class="h-12 px-3 mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <button id="forgotPasswordSubmitBtn" type="submit" class="w-full bg-[#0086C9] text-white py-2 px-4 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Send Reset Link</button>
                </form>
            <?php endif; ?>

        </div>
    </div>

    <div id="forgotPasswordSendingModal" class="fixed inset-0 z-[120] hidden items-center justify-center bg-black/50 px-4">
        <div class="w-full max-w-sm rounded-xl bg-white p-6 text-center shadow-xl">
            <p class="text-base font-semibold text-slate-800">Sending reset link...</p>
            <p class="mt-2 text-sm text-slate-600">Please wait while we process your request.</p>
        </div>
    </div>

    <script>
    (function () {
        const form = document.getElementById('forgotPasswordForm');
        const submitBtn = document.getElementById('forgotPasswordSubmitBtn');
        const modal = document.getElementById('forgotPasswordSendingModal');

        if (!form || !submitBtn || !modal) {
            return;
        }

        form.addEventListener('submit', function () {
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-60', 'cursor-not-allowed');
            submitBtn.textContent = 'Sending...';
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        });
    })();
    </script>
