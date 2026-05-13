
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?php
    $cssFilePath = dirname(__DIR__, 2) . '/public/css/output.css';
    $cssVersion = is_file($cssFilePath) ? filemtime($cssFilePath) : time();
    ?>
    <link href="/css/output.css?v=<?= $cssVersion ?>" rel="stylesheet">
    <title>Get Around Mobility</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link href="/img/Original logo.svg" rel="icon" type="image/x-icon">
</head>

<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>


<body class="bg-gray-100 min-h-screen flex flex-col">

    <?php include __DIR__ . '/partials/navbar.php'; ?>

     <?= $content ?>   <!-- Dynamic page content -->


    <?php include __DIR__ . '/partials/footer.php'; ?>

</body>
</html>