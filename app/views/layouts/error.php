<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'Página no encontrada') ?></title>
    <script>if(localStorage.getItem('theme')==='dark')document.documentElement.classList.add('theme-dark');</script>
    <link rel="stylesheet" href="<?= e(asset('css/styles.css')) ?>">
    <?php foreach (($pageStyles ?? []) as $pageStyle): ?><link rel="stylesheet" href="<?= e($pageStyle) ?>"><?php endforeach; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="standalone-error-page">
    <?= $content ?>
</body>
</html>
