<!DOCTYPE html>
<html lang="es" class="<?= e(($bodyClass ?? '') === 'login-page' ? 'login-root' : '') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'Gestion Documental Academica') ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/styles.css')) ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="<?= e($bodyClass ?? '') ?>">
    <!-- Inicio de contenido especifico de autenticacion -->
    <?= $content ?>
    <!-- Final de contenido especifico de autenticacion -->

    <!-- Inicio de scripts de autenticacion -->
    <?php if (!empty($pageScript)): ?>
        <script src="<?= e($pageScript) ?>"></script>
    <?php endif; ?>
    <?php if (!empty($GLOBALS['config']['dev_autoreload'])): ?>
        <script src="<?= e(asset('js/dev-reload.js')) ?>" data-endpoint="<?= e(route('dev-reload')) ?>" defer></script>
    <?php endif; ?>
    <!-- Final de scripts de autenticacion -->
</body>
</html>
