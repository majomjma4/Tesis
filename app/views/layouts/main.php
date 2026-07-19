<!DOCTYPE html>
<html lang="es" class="dashboard-root">
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
    <?php foreach (($pageStyles ?? []) as $pageStyle): ?>
        <link rel="stylesheet" href="<?= e($pageStyle) ?>">
    <?php endforeach; ?>
    <link rel="stylesheet" href="<?= e(asset('css/admin-controls.css')) ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="<?= e(trim(($bodyClass ?? 'dashboard-page') . ' app-shell app-page-loading')) ?>">
    <noscript><style>.app-global-skeleton{display:none!important}.app-page-content{position:static!important;width:100%!important;height:auto!important;overflow:visible!important;opacity:1!important}</style></noscript>
    <!-- Inicio de capa para cerrar el menu movil -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <!-- Final de capa para cerrar el menu movil -->

    <!-- Inicio de menu lateral principal -->
    <aside class="sidebar" id="sidebar" aria-label="Menu principal">
        <div>
            <div class="logo-area">
                <img src="<?= e(asset('img/Logo2.webp')) ?>" alt="Logo Instituto Superior Tecnologico El Libertador">
                <h1>
                    Instituto Superior Tecnologico<br>
                    "El Libertador"
                </h1>
                <p>Plataforma academica de gestion de proyectos estudiantiles.</p>
            </div>

            <nav class="menu" aria-label="Navegacion principal">
                <a href="<?= e(route('dashboard')) ?>" class="menu-item <?= ($currentPage ?? '') === 'dashboard' ? 'active' : '' ?>">
                    <span class="menu-icon"><i class="fa-solid fa-house"></i></span>
                    <span>Inicio</span>
                </a>
                <a href="<?= e(route('projects')) ?>" class="menu-item <?= ($currentPage ?? '') === 'projects' ? 'active' : '' ?>">
                    <span class="menu-icon"><i class="fa-solid fa-folder-open"></i></span>
                    <span>Proyectos</span>
                </a>
                <?php if (in_array('administrator', $layoutUserRoles ?? [], true)): ?>
                <a href="<?= e(route('admin-users')) ?>" class="menu-item <?= ($currentPage ?? '') === 'admin-users' ? 'active' : '' ?>"><span class="menu-icon"><i class="fa-solid fa-users"></i></span><span>Usuarios</span></a>
                <a href="<?= e(route('admin-academic')) ?>" class="menu-item <?= ($currentPage ?? '') === 'admin-academic' ? 'active' : '' ?>"><span class="menu-icon"><i class="fa-solid fa-graduation-cap"></i></span><span>Académico</span></a>
                <?php endif; ?>
                <a href="<?= e(route('repository')) ?>" class="menu-item <?= ($currentPage ?? '') === 'repository' ? 'active' : '' ?>">
                    <span class="menu-icon"><i class="fa-solid fa-book-open"></i></span>
                    <span>Repositorio</span>
                </a>
                <a href="<?= e(route('calendar')) ?>" class="menu-item <?= ($currentPage ?? '') === 'calendar' ? 'active' : '' ?>">
                    <span class="menu-icon"><i class="fa-solid fa-calendar-days"></i></span>
                    <span>Calendario</span>
                </a>
                <a href="<?= e(route('notifications')) ?>" class="menu-item <?= ($currentPage ?? '') === 'notifications' ? 'active' : '' ?>">
                    <span class="menu-icon"><i class="fa-solid fa-bell"></i></span>
                    <span>Notificaciones</span>
                </a>
                <?php if (in_array('administrator', $layoutUserRoles ?? [], true)): ?>
                <a href="<?= e(route('admin-reports')) ?>" class="menu-item <?= ($currentPage ?? '') === 'admin-reports' ? 'active' : '' ?>"><span class="menu-icon"><i class="fa-solid fa-chart-column"></i></span><span>Reportes</span></a>
                <a href="<?= e(route('admin-settings')) ?>" class="menu-item <?= ($currentPage ?? '') === 'admin-settings' ? 'active' : '' ?>"><span class="menu-icon"><i class="fa-solid fa-gear"></i></span><span>Configuración</span></a>
                <a href="<?= e(route('admin-trash')) ?>" class="menu-item <?= ($currentPage ?? '') === 'admin-trash' ? 'active' : '' ?>"><span class="menu-icon"><i class="fa-regular fa-trash-can"></i></span><span>Papelera</span></a>
                <?php endif; ?>
            </nav>
        </div>

        <div class="sidebar-footer">
            <button class="close-btn js-logout-trigger" type="button">
                <i class="fa-solid fa-right-from-bracket"></i>
                Cerrar sesión
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
                <h2>Hola, <?= e(explode(' ', trim($layoutUserName ?? 'Usuario'))[0] ?: 'Usuario') ?></h2>
                <p><?= in_array('administrator', $layoutUserRoles ?? [], true) ? 'Panel de administración del sistema.' : 'Continúa gestionando tus proyectos académicos.' ?></p>
            </div>

            <div class="topbar-right">
                <a class="topbar-action-btn" href="<?= e(in_array('administrator', $layoutUserRoles ?? [], true) ? route('projects') : route('new-project')) ?>">
                    <i class="fa-solid fa-plus"></i>
                    <span class="topbar-action-label">Nuevo proyecto</span>
                    <span class="topbar-action-label-short">Nuevo</span>
                </a>
                <button class="icon-btn theme-toggle" id="themeToggle" type="button" aria-label="Cambiar modo claro u oscuro">
                    <i class="fa-solid fa-moon"></i>
                </button>
                <div class="topbar-notifications">
                    <button class="notification-icon" id="topbarNotificationsButton" type="button" aria-label="Mostrar notificaciones recientes" aria-haspopup="dialog" aria-expanded="false" aria-controls="topbarNotificationsPanel" data-list-endpoint="<?= e(route('notifications/list')) ?>" data-open-endpoint="<?= e($notificationOpenEndpoint ?? route('notifications/open')) ?>" data-csrf-token="<?= e($notificationCsrfToken ?? '') ?>">
                        <i class="fa-solid fa-bell"></i>
                        <span class="notification-count" <?= empty($notificationUnreadCount) ? 'hidden' : '' ?>><?= e((string) ($notificationUnreadCount ?? 0)) ?></span>
                    </button>
                    <section class="topbar-notifications-panel" id="topbarNotificationsPanel" aria-label="Notificaciones recientes" hidden>
                        <header><div><span>Actividad reciente</span><h2>Notificaciones</h2></div><i class="fa-regular fa-bell"></i></header>
                        <div class="topbar-notifications-list" id="topbarNotificationsList">
                            <div class="topbar-notifications-loading"><i class="fa-solid fa-circle-notch fa-spin"></i><span>Cargando...</span></div>
                        </div>
                        <footer><a href="<?= e(route('notifications')) ?>">Ver más notificaciones <i class="fa-solid fa-arrow-right"></i></a></footer>
                    </section>
                </div>

                <div class="avatar-menu">
                    <button class="user-avatar" id="avatarButton" type="button" aria-label="Abrir menu de usuario" aria-expanded="false">
                        <?= e(mb_strtoupper(mb_substr($layoutUserName ?? 'U', 0, 1, 'UTF-8'), 'UTF-8')) ?>
                    </button>
                    <div class="avatar-dropdown" id="avatarDropdown">
                        <div class="avatar-dropdown-identity"><strong><?= e($layoutUserName ?? 'Usuario') ?></strong><small><?= e($layoutUserEmail ?? '') ?></small></div>
                        <a href="<?= e(route('profile')) ?>">Mi perfil</a>
                        <a href="<?= e(route('change-password')) ?>">Cambiar contraseña</a>
                        <button type="button" class="danger-option js-logout-trigger">Cerrar sesión</button>
                    </div>
                </div>
            </div>
        </header>
        <?php if (!empty($layoutMustChangePassword) && !in_array('administrator', $layoutUserRoles ?? [], true) && (int)($layoutPasswordWarningCount ?? 0) < 3): ?>
        <aside class="password-warning-banner" role="status" data-password-warning data-warning-key="<?= e(hash('sha256', ($layoutUserEmail ?? '') . ':' . (int)$layoutPasswordWarningCount)) ?>">
            <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
            <div><strong>Tu contraseña sigue siendo temporal</strong><span>Aviso <?= (int)$layoutPasswordWarningCount ?> de 3</span></div>
            <div class="password-warning-actions">
                <a href="<?= e(route('change-password')) ?>">Cambiar ahora</a>
                <button type="button" data-password-warning-dismiss>Recordármelo más tarde</button>
            </div>
            <button type="button" class="password-warning-close" data-password-warning-dismiss aria-label="Cerrar aviso"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </aside>
        <?php endif; ?>
        <!-- Final de barra superior -->

        <section class="app-global-skeleton" id="appGlobalSkeleton" aria-label="Cargando contenido" aria-live="polite">
            <div class="app-skeleton-heading"><span class="app-skeleton-block kicker"></span><span class="app-skeleton-block title"></span><span class="app-skeleton-block subtitle"></span></div>
            <div class="app-skeleton-toolbar"><span class="app-skeleton-block"></span><span class="app-skeleton-block"></span><span class="app-skeleton-block compact"></span></div>
            <div class="app-skeleton-grid"><?php for ($i = 0; $i < 4; $i++): ?><article><span class="app-skeleton-block icon"></span><span class="app-skeleton-block line strong"></span><span class="app-skeleton-block line"></span><span class="app-skeleton-block line short"></span></article><?php endfor; ?></div>
        </section>
        <div class="app-page-content" id="appPageContent"><?= $content ?></div>
    </main>
    <!-- Final de contenido principal -->

    <!-- Inicio de confirmacion de cierre de sesion -->
    <div class="logout-modal-overlay" id="logoutModal" hidden>
        <div class="logout-modal" role="dialog" aria-modal="true" aria-labelledby="logoutModalTitle" aria-describedby="logoutModalText">
            <span class="logout-modal-icon" aria-hidden="true">
                <i class="fa-solid fa-right-from-bracket"></i>
            </span>
            <h2 id="logoutModalTitle">¿Cerrar sesión?</h2>
            <p id="logoutModalText">¿Estás seguro de que deseas cerrar la sesión actual?</p>
            <div class="logout-modal-actions">
                <button class="modal-cancel-btn" id="logoutCancelBtn" type="button">Cancelar</button>
                <button class="modal-accept-btn" id="logoutAcceptBtn" type="button" data-logout-url="<?= e(route('logout')) ?>">Aceptar</button>
            </div>
        </div>
    </div>
    <!-- Final de confirmacion de cierre de sesion -->

    <!-- Inicio de scripts de pagina -->
    <script src="<?= e(asset('js/main.js')) ?>"></script>
    <?php if (!empty($pageScript)): ?>
        <script src="<?= e($pageScript) ?>"></script>
    <?php endif; ?>
    <?php foreach (($pageScripts ?? []) as $additionalPageScript): ?>
        <script src="<?= e($additionalPageScript) ?>"></script>
    <?php endforeach; ?>
    <script src="<?= e(asset('js/admin-action-menus.js')) ?>"></script>
    <?php if (!empty($GLOBALS['config']['dev_autoreload'])): ?>
        <script src="<?= e(asset('js/dev-reload.js')) ?>" data-endpoint="<?= e(route('dev-reload')) ?>" defer></script>
    <?php endif; ?>
    <!-- Final de scripts de pagina -->
</body>
</html>
