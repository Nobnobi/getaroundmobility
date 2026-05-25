<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Walk-in Booking Kiosk</title>
    <link href="/css/output.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css" integrity="sha384-RkASv+6KfBMW9eknReJIJ6b3UnjKOKC5bOUaNgIY778NFbQ8MtWq9Lr/khUgqtTt" crossorigin="anonymous">
    <link rel="shortcut icon" href="/favicon.png" />
    <style>
        body {
            background:
                radial-gradient(1200px 500px at 10% -10%, rgba(0, 134, 201, 0.18), transparent 60%),
                radial-gradient(900px 420px at 100% 0%, rgba(6, 43, 65, 0.18), transparent 60%),
                linear-gradient(180deg, #e8f3fb 0%, #f7fbff 55%, #eaf2f8 100%);
        }
    </style>
</head>
<body class="min-h-screen text-slate-800">
    <header class="sticky top-0 z-40 border-b border-[#d7e6f2] bg-white/90 backdrop-blur">
        <div class="mx-auto flex w-full max-w-7xl items-center justify-between px-4 py-3 md:px-8">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#0086C9]">Get Around Mobility</p>
                <h1 class="text-lg font-bold text-[#062B41] md:text-2xl">Walk-in Booking Kiosk</h1>
            </div>
            <div class="flex items-center gap-3">
                <span class="hidden rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 md:inline-block">
                    Signed in: <?= htmlspecialchars($_SESSION['admin_username'] ?? 'Admin') ?>
                </span>
                <a href="/walkin-booking/logout" class="rounded-xl bg-[#062B41] px-4 py-2 text-sm font-semibold text-white hover:bg-[#0b3d5b]">Sign Out</a>
            </div>
        </div>
    </header>

    <main class="mx-auto flex w-full max-w-7xl flex-1">
        <?= $content ?>
    </main>

    <script>
    (function () {
        function formatAdminDateTime(value) {
            if (!value) return '';
            const date = new Date(String(value).replace(' ', 'T'));
            if (Number.isNaN(date.getTime())) return String(value);

            const month = date.toLocaleString(undefined, { month: 'long' });
            const day = date.getDate();
            const year = date.getFullYear();
            let hours = date.getHours();
            const minutes = String(date.getMinutes()).padStart(2, '0');
            const meridiem = hours >= 12 ? 'pm' : 'am';
            hours = hours % 12;
            if (hours === 0) hours = 12;

            return `${month} ${day}, ${year} ${hours}:${minutes}${meridiem}`;
        }

        function applyAdminDateFormatting(root) {
            const scope = root || document;
            scope.querySelectorAll('[data-admin-datetime]').forEach(function (node) {
                const raw = node.getAttribute('data-admin-datetime') || node.textContent || '';
                node.textContent = formatAdminDateTime(raw);
            });
        }

        window.formatAdminDateTime = formatAdminDateTime;
        window.applyAdminDateFormatting = applyAdminDateFormatting;

        document.addEventListener('DOMContentLoaded', function () {
            applyAdminDateFormatting(document);
        });
    })();
    </script>
</body>
</html>
