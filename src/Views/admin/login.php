<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
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
<body class="flex items-center justify-center min-h-screen">
    <div class="bg-white/80 backdrop-blur-md border border-white/20 p-10 rounded-2xl shadow-2xl w-full max-w-md">
        <div class="flex justify-center mb-6">
            <img src="/img/Original logo.svg" alt="Logo" class="w-40 h-40 object-contain drop-shadow" />
        </div>
        <h2 class="text-3xl font-bold mb-8 text-center text-gray-800">Admin Log In</h2>
        
        <form method="post" action="/admin/login">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <div class="mb-6">
                <input type="text" name="username" id="username" required 
                       placeholder="Enter your username" 
                       class="w-full text-gray-700 px-5 py-4 border border-gray-300 rounded-lg focus:outline-none focus:border-gray-500 focus:ring-4 focus:ring-gray-200 transition"
                       autofocus>
            </div>
            
            <div class="mb-8">
                <input type="password" name="password" id="password" required 
                       placeholder="Password" 
                       class="w-full text-gray-700 px-5 py-4 border border-gray-300 rounded-lg focus:outline-none focus:border-gray-500 focus:ring-4 focus:ring-gray-200 transition">
            </div>
            
            <button type="submit" 
                    class="w-full bg-gray-900 hover:bg-gray-800 text-white font-semibold py-4 rounded-lg transition duration-200 shadow-md cursor-pointer">
                Log In
            </button>
            
            <?php if (!empty($error)): ?>
                <div class="mt-6 text-red-600 text-center font-medium">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
        </form>
    </div>
</body>
</html>