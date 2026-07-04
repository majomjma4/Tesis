<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'Gestion Documental Academica') ?></title>
    <script>
        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.classList.add('theme-dark');
        }
    </script>
    <link rel="stylesheet" href="<?= e(asset('css/styles.css')) ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="<?= e(($bodyClass ?? '') . ' app-shell') ?>">
    <!-- Inicio de capa para cerrar el menu movil -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <!-- Final de capa para cerrar el menu movil -->

    <!-- Inicio de menu lateral principal -->
    <aside class="sidebar" id="sidebar" aria-label="Menu principal">
        <div>
            <div class="logo-area">
                <img src="<?= e(asset('img/logo2.webp')) ?>" alt="Logo Instituto Superior Tecnologico El Libertador">
                <h1>
                    Instituto Superior Tecnologico<br>
                    "El Libertador"
                </h1>
                <p>Plataforma academica de gestion de proyectos estudiantiles.</p>
            </div>

            <nav class="menu" aria-label="Navegacion principal">
                <a href="<?= e(route('dashboard')) ?>" class="menu-item active">
                    <span class="menu-icon"><i class="fa-solid fa-house"></i></span>
                    <span>Inicio</span>
                </a>
                <a href="#" class="menu-item">
                    <span class="menu-icon"><i class="fa-solid fa-folder-open"></i></span>
                    <span>Mis proyectos</span>
                </a>
                <a href="#" class="menu-item">
                    <span class="menu-icon"><i class="fa-solid fa-book-open"></i></span>
                    <span>Repositorio</span>
                </a>
                <a href="#" class="menu-item">
                    <span class="menu-icon"><i class="fa-solid fa-calendar-days"></i></span>
                    <span>Calendario</span>
                </a>
                <a href="#" class="menu-item">
                    <span class="menu-icon"><i class="fa-solid fa-bell"></i></span>
                    <span>Notificaciones</span>
                </a>
            </nav>
        </div>

        <div class="sidebar-footer">
            <button class="close-btn" type="button">
                <i class="fa-solid fa-right-from-bracket"></i>
                Cerrar sesion
            </button>
        </div>
    </aside>
    <!-- Final de menu lateral principal -->

    <!-- Inicio de contenido principal -->
    <main class="main">
        <!-- Inicio de barra superior -->
        <header class="topbar">
            <button class="hamburger-btn" id="hamburgerBtn" type="button" aria-label="Abrir menu" aria-expanded="false">
                <i class="fa-solid fa-bars"></i>
            </button>

            <div class="welcome">
                <h2>Bienvenido, Usuario</h2>
                <p>Continua gestionando tus proyectos academicos.</p>
            </div>

            <div class="topbar-right">
                <button class="topbar-action-btn" type="button">
                    <i class="fa-solid fa-plus"></i>
                    Nuevo proyecto
                </button>
                <button class="icon-btn theme-toggle" id="themeToggle" type="button" aria-label="Cambiar modo claro u oscuro">
                    <i class="fa-solid fa-moon"></i>
                </button>
                <button class="notification-icon" type="button" aria-label="Ver notificaciones">
                    <i class="fa-solid fa-bell"></i>
                    <span class="notification-count">3</span>
                </button>

                <div class="avatar-menu">
                    <button class="user-avatar" id="avatarButton" type="button" aria-label="Abrir menu de usuario" aria-expanded="false">
                        U
                    </button>
                    <div class="avatar-dropdown" id="avatarDropdown">
                        <button type="button">Cambiar correo electronico</button>
                        <button type="button">Cambiar contrasena</button>
                        <button type="button" class="danger-option">Cerrar sesion</button>
                    </div>
                </div>
            </div>
        </header>
        <!-- Final de barra superior -->

        <?= $content ?>
    </main>
    <!-- Final de contenido principal -->

    <!-- Inicio de scripts de pagina -->
    <?php if (!empty($pageScript)): ?>
        <script src="<?= e($pageScript) ?>"></script>
    <?php endif; ?>
    <?php if (!empty($GLOBALS['config']['dev_autoreload'])): ?>
        <script src="<?= e(asset('js/dev-reload.js')) ?>" data-endpoint="<?= e(route('dev-reload')) ?>" defer></script>
    <?php endif; ?>
    <!-- Final de scripts de pagina -->
</body>
</html>
