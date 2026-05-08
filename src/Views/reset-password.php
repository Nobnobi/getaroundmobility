<?php
date_default_timezone_set('Asia/Manila'); // DELETE THIS LINE AFTER AFTER DEPLOYING SINCE THIS IS MANILA TIMEZONE.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <link href="/css/output.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">

    <?php $token = $_GET['token'] ?? $_POST['token'] ?? ''; ?>
    <div class="flex-1 flex items-center justify-center">
        <div class="w-full max-w-md p-8 bg-white rounded-lg shadow-lg">
            <h1 class="text-2xl font-bold text-center mb-4 font-[Barlow]">Reset Password</h1>
            <?php if (!empty($error)): ?>
                <div class="bg-red-100 text-red-700 p-2 rounded mb-4 text-center"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?= date('Y-m-d H:i:s', strtotime('+1 hour')) ?>
            <?php if (!empty($success)): ?>
                <div class="bg-green-100 text-green-700 p-2 rounded mb-4 text-center"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <?php if (empty($success)): ?>
                <form method="post" action="/reset-password" class="space-y-6">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">New Password</label>
                        <input type="password" name="password" required placeholder="Enter new password"
                            class="h-12 px-3 mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <button type="submit" class="w-full bg-[#0086C9] text-white py-2 px-4 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Reset Password</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>