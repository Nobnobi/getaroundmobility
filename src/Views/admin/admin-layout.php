

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin-Get Around Mobility</title>
    <link href="/css/output.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css" integrity="sha384-RkASv+6KfBMW9eknReJIJ6b3UnjKOKC5bOUaNgIY778NFbQ8MtWq9Lr/khUgqtTt" crossorigin="anonymous">
    <link rel="shortcut icon" href="/favicon.png" />
    <link rel="apple-touch-icon" sizes="76x76" href="/apple-icon.png" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700&display=swap"/>
</head>
<body class="bg-gray-100 min-h-screen flex">
    <?php include __DIR__ . '/sidebar.php'; ?>

    <?= $content ?>   

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