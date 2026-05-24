<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Walk-in Booking Login</title>
    <link href="/css/output.css" rel="stylesheet">
    <style>
        body {
            background-image: url('/img/Blocks.svg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center px-4 py-8">
    <div class="bg-white/80 backdrop-blur-md border border-white/20 p-8 md:p-10 rounded-2xl shadow-2xl w-full max-w-md">
        <div class="flex justify-center mb-4">
            <img src="/img/Original logo.svg" alt="Logo" class="w-32 h-32 md:w-36 md:h-36 object-contain drop-shadow" />
        </div>

        <div class="mb-6 text-center">
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Walk-in Booking Login</h1>
            <p class="mt-2 text-sm text-gray-600">Use authorized admin credentials to open kiosk mode.</p>
        </div>

        <form method="post" action="/walkin-booking/login" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div>
                <label for="username" class="mb-2 block text-sm font-semibold text-slate-700">Username</label>
                <input id="username" type="text" name="username" required autofocus
                    class="w-full text-gray-700 px-5 py-4 border border-gray-300 rounded-lg focus:outline-none focus:border-gray-500 focus:ring-4 focus:ring-gray-200 transition">
            </div>

            <div>
                <label for="password" class="mb-2 block text-sm font-semibold text-slate-700">Password</label>
                <input id="password" type="password" name="password" required
                    class="w-full text-gray-700 px-5 py-4 border border-gray-300 rounded-lg focus:outline-none focus:border-gray-500 focus:ring-4 focus:ring-gray-200 transition">
            </div>

            <?php if (!empty($error)): ?>
                <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <button type="submit" class="w-full bg-gray-900 hover:bg-gray-800 text-white font-semibold py-4 rounded-lg transition duration-200 shadow-md cursor-pointer">
                Enter Kiosk
            </button>

            <a href="/admin/login" class="block text-center text-sm font-semibold text-gray-700 hover:underline">
                Go to Full Admin Login
            </a>
        </form>
    </div>
</body>
</html>
