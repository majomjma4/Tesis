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
    <link rel="stylesheet" href="<?= e(asset('css/card-accents.css')) ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<?php $isAdministratorLayout = (bool) ($layoutIsAdmin ?? false); $isStudentWorkspaceFullscreen = !empty($studentWorkspaceFullscreen); ?>

<body
    class="<?= e(trim(($bodyClass ?? 'dashboard-page') . ' app-shell' . ($isAdministratorLayout ? ' app-admin-layout' : '') . ($isStudentWorkspaceFullscreen ? ' app-student-workspace-fullscreen' : ''))) ?>">
    <noscript>
        <style>
            .app-global-skeleton {
                display: none !important
            }

            .app-page-content {
                position: static !important;
                width: 100% !important;
                height: auto !important;
                overflow: visible !important;
                opacity: 1 !important
            }
        </style>
    </noscript>
    <!-- Inicio de capa para cerrar el menu movil -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <!-- Final de capa para cerrar el menu movil -->

    <!-- Inicio de menu lateral principal -->
    <aside class="sidebar" id="sidebar" aria-label="Menu principal">
        <div>
            <div class="logo-area">
                <img src="<?= e(asset('img/Logo2.webp')) ?>" alt="Logo <?= e($institutionName ?? '') ?>">
                <h1>
                    <?= e($institutionName ?? '') ?>
                </h1>
                <p>Plataforma academica de gestion de proyectos estudiantiles.</p>
            </div>

            <nav class="menu" aria-label="Navegacion principal">
                <a href="<?= e(route('dashboard')) ?>"
                    class="menu-item <?= ($currentPage ?? '') === 'dashboard' ? 'active' : '' ?>">
                    <span class="menu-icon"><i class="fa-solid fa-house"></i></span>
                    <span>Inicio</span>
                </a>
                <a href="<?= e(route('projects')) ?>"
                    class="menu-item <?= ($currentPage ?? '') === 'projects' ? 'active' : '' ?>">
                    <span class="menu-icon"><i class="fa-solid fa-folder-open"></i></span>
                    <span><?= ($isAdministratorLayout || in_array('teacher', (array) ($layoutUserRoles ?? []), true)) ? 'Proyectos activos' : 'Proyectos' ?></span>
                </a>
                <?php if (!$isAdministratorLayout && in_array('teacher', (array) ($layoutUserRoles ?? []), true)): ?>
                    <a href="<?= e(route('assigned-projects')) ?>"
                        class="menu-item <?= ($currentPage ?? '') === 'assigned-projects' ? 'active' : '' ?>">
                        <span class="menu-icon"><i class="fa-solid fa-clipboard-user"></i></span>
                        <span>Proyectos asignados</span>
                    </a>
                    <?php if (!empty((new TeacherThesisCapabilityService())->capabilitiesForCurrentUser()['manage_thesis_process'])): ?>
                        <a href="<?= e(route('thesis-management')) ?>"
                            class="menu-item <?= ($currentPage ?? '') === 'thesis-management' ? 'active' : '' ?>">
                            <span class="menu-icon"><i class="fa-solid fa-scale-balanced"></i></span>
                            <span>Gestión de Titulación</span>
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ($isAdministratorLayout): ?>
                    <a href="<?= e(route('admin-users')) ?>"
                        class="menu-item <?= ($currentPage ?? '') === 'admin-users' ? 'active' : '' ?>"><span
                            class="menu-icon"><i class="fa-solid fa-users"></i></span><span>Usuarios</span></a>
                    <a href="<?= e(route('admin-academic')) ?>"
                        class="menu-item <?= ($currentPage ?? '') === 'admin-academic' ? 'active' : '' ?>"><span
                            class="menu-icon"><i class="fa-solid fa-graduation-cap"></i></span><span>Gestión
                            académica</span></a>
                <?php endif; ?>
                <a href="<?= e(route($isAdministratorLayout ? 'admin-repository' : 'repository')) ?>"
                    class="menu-item <?= ($currentPage ?? '') === 'repository' ? 'active' : '' ?>">
                    <span class="menu-icon"><i class="fa-solid fa-book-open"></i></span>
                    <span>Repositorio</span>
                </a>
                <a href="<?= e(route('calendar')) ?>"
                    class="menu-item <?= ($currentPage ?? '') === 'calendar' ? 'active' : '' ?>">
                    <span class="menu-icon"><i class="fa-solid fa-calendar-days"></i></span>
                    <span>Calendario</span>
                </a>
                <a href="<?= e(route('notifications')) ?>"
                    class="menu-item <?= ($currentPage ?? '') === 'notifications' ? 'active' : '' ?>">
                    <span class="menu-icon"><i class="fa-solid fa-bell"></i></span>
                    <span>Notificaciones</span>
                </a>
                <?php if ($isAdministratorLayout): ?>
                    <a href="<?= e(route('admin-reports')) ?>"
                        class="menu-item <?= ($currentPage ?? '') === 'admin-reports' ? 'active' : '' ?>"><span
                            class="menu-icon"><i class="fa-solid fa-chart-column"></i></span><span>Reportes</span></a>
                    <a href="<?= e(route('admin-settings')) ?>"
                        class="menu-item <?= ($currentPage ?? '') === 'admin-settings' ? 'active' : '' ?>"><span
                            class="menu-icon"><i class="fa-solid fa-gear"></i></span><span>Configuración</span></a>
                    <a href="<?= e(route('admin-trash')) ?>"
                        class="menu-item <?= ($currentPage ?? '') === 'admin-trash' ? 'active' : '' ?>"><span
                            class="menu-icon"><i class="fa-regular fa-trash-can"></i></span><span>Papelera</span></a>
                <?php endif; ?>
            </nav>
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
                <?php
                $firstName = explode(' ', trim($layoutUserName ?? 'Usuario'))[0] ?: 'Usuario';
                $greetingName = ($isAdministratorLayout && strtolower($firstName) === 'docente') ? 'Administrador' : $firstName;
                ?>
                <h2>Hola, <?= e($greetingName) ?></h2>
                <p><?= $isAdministratorLayout ? 'Panel de administración del sistema.' : 'Continúa gestionando tus proyectos académicos.' ?>
                </p>
            </div>

            <div class="topbar-right">
                <?php if (!empty($layoutCanToggleAdminMode)): ?>
                    <form action="<?= e(route('toggle-admin-mode')) ?>" method="POST" class="topbar-mode-toggle-form">
                        <input type="hidden" name="_csrf" value="<?= e($layoutToggleAdminModeCsrf ?? '') ?>">
                        <button class="topbar-mode-pill <?= !empty($layoutIsAdminModeActive) ? 'is-admin' : 'is-teacher' ?>"
                                type="submit"
                                title="<?= !empty($layoutIsAdminModeActive) ? 'Volver a modo docente' : 'Cambiar a modo administrador' ?>"
                                aria-label="<?= !empty($layoutIsAdminModeActive) ? 'Volver a modo docente' : 'Cambiar a modo administrador' ?>">
                            <span class="mode-pill-badge">
                                <i class="fa-solid <?= !empty($layoutIsAdminModeActive) ? 'fa-shield-halved' : 'fa-graduation-cap' ?>"></i>
                                <span class="mode-pill-text"><?= !empty($layoutIsAdminModeActive) ? 'Administración' : 'Docente' ?></span>
                            </span>
                            <i class="fa-solid fa-arrow-right-arrow-left mode-pill-switch-icon" aria-hidden="true"></i>
                        </button>
                    </form>
                <?php endif; ?>
                <?php if (!$isAdministratorLayout): ?>
                    <a class="topbar-action-btn" href="<?= e(route('new-project')) ?>">
                        <i class="fa-solid fa-plus"></i>
                        <span class="topbar-action-label">Nuevo proyecto</span>
                        <span class="topbar-action-label-short">Nuevo</span>
                    </a>
                <?php endif; ?>
                <button class="icon-btn theme-toggle" id="themeToggle" type="button"
                    aria-label="Cambiar modo claro u oscuro">
                    <i class="fa-solid fa-moon"></i>
                </button>
                <div class="topbar-notifications">
                    <button class="notification-icon" id="topbarNotificationsButton" type="button"
                        aria-label="Mostrar notificaciones recientes" aria-haspopup="dialog" aria-expanded="false"
                        aria-controls="topbarNotificationsPanel"
                        data-list-endpoint="<?= e(route('notifications/list')) ?>"
                        data-counters-endpoint="<?= e(route('notifications/counters')) ?>"
                        data-open-endpoint="<?= e($notificationOpenEndpoint ?? route('notifications/open')) ?>"
                        data-csrf-token="<?= e($notificationCsrfToken ?? '') ?>">
                        <i class="fa-solid fa-bell"></i>
                        <?php $topbarUnreadCount = max(0, (int) ($notificationUnreadCount ?? 0)); ?>
                        <span class="notification-count" <?= $topbarUnreadCount === 0 ? 'hidden aria-hidden="true"' : 'aria-label="' . e($topbarUnreadCount . ($topbarUnreadCount === 1 ? ' notificación no leída' : ' notificaciones no leídas')) . '"' ?>><?= $topbarUnreadCount === 0 ? '' : e($topbarUnreadCount >= 10 ? '9+' : (string) $topbarUnreadCount) ?></span>
                    </button>
                    <section class="topbar-notifications-panel" id="topbarNotificationsPanel"
                        aria-label="Notificaciones recientes" hidden>
                        <header>
                            <div><span>Actividad reciente</span>
                                <h2>Notificaciones</h2>
                            </div><i class="fa-regular fa-bell"></i>
                        </header>
                        <div class="topbar-notifications-list" id="topbarNotificationsList">
                            <div class="topbar-notifications-loading"><i
                                    class="fa-solid fa-circle-notch fa-spin"></i><span>Cargando...</span></div>
                        </div>
                        <footer><a href="<?= e(route('notifications')) ?>">Ver más notificaciones <i
                                    class="fa-solid fa-arrow-right"></i></a></footer>
                    </section>
                </div>

                <div class="avatar-menu">
                    <button class="user-avatar" id="avatarButton" type="button" aria-label="Abrir menu de usuario"
                        aria-expanded="false">
                        <?php if (!empty($layoutAvatarUrl)): ?><img src="<?= e((string) $layoutAvatarUrl) ?>"
                                alt=""><?php else: ?><?= e(mb_strtoupper(mb_substr($layoutUserName ?? 'U', 0, 1, 'UTF-8'), 'UTF-8')) ?><?php endif; ?>
                    </button>
                    <div class="avatar-dropdown" id="avatarDropdown">
                        <div class="avatar-dropdown-identity">
                            <strong><?= e($layoutUserName ?? 'Usuario') ?></strong><small><?= e($layoutUserEmail ?? '') ?></small>
                        </div>
                        <?php
                        $currentUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
                        $isProfileRoute = str_contains($currentUri, 'page=profile');
                        $isChangePasswordRoute = str_contains($currentUri, 'page=change-password');
                        ?>
                        <?php if ($isProfileRoute): ?>
                            <span class="avatar-dropdown-active-item" aria-current="page">Mi perfil</span>
                        <?php else: ?>
                            <a href="<?= e(route('profile')) ?>">Mi perfil</a>
                        <?php endif; ?>
                        <?php if ($isChangePasswordRoute): ?>
                            <span class="avatar-dropdown-active-item" aria-current="page">Cambiar contraseña</span>
                        <?php else: ?>
                            <a href="<?= e(route('change-password')) ?>">Cambiar contraseña</a>
                        <?php endif; ?>
                        <button type="button" class="danger-option js-logout-trigger">Cerrar sesión</button>
                    </div>
                </div>
            </div>
        </header>
        <?php
        $daysLeft = $layoutTemporaryPasswordRemainingDays ?? null;
        $showWarning = !empty($layoutHasTemporaryPassword) && !$isAdministratorLayout && $daysLeft !== null && $daysLeft > 0 && empty($layoutIsTemporaryPasswordDismissedToday);
        ?>
        <?php if ($showWarning): ?>
            <?php
            $daysText = match (true) {
                $daysLeft > 1 => "Tu contraseña temporal vence en {$daysLeft} días.",
                $daysLeft === 1 => "Tu contraseña temporal vence mañana.",
                default => "Tu contraseña temporal vence hoy."
            };
            ?>
            <aside class="password-warning-banner" role="status" data-password-warning>
                <i class="fa-solid fa-clock" aria-hidden="true"></i>
                <div>
                    <strong>Contraseña temporal</strong>
                    <span><?= e($daysText) ?> Por seguridad, crea una contraseña personal antes de que finalice el
                        plazo.</span>
                </div>
                <div class="password-warning-actions">
                    <a href="<?= e(route('change-password')) ?>" class="as-btn-change">Cambiar ahora</a>
                    <button type="button" class="as-btn-dismiss" data-password-warning-dismiss>Omitir por hoy</button>
                </div>
            </aside>
            <script>
                document.addEventListener('click', function (e) {
                    var btn = e.target.closest('[data-password-warning-dismiss]');
                    if (!btn) return;
                    var banner = btn.closest('[data-password-warning]');
                    if (banner) banner.remove();
                    var data = new FormData();
                    data.set('_csrf', '<?= e($layoutTemporaryPasswordWarningCsrf ?? '') ?>');
                    fetch('index.php?page=dismiss-temp-password-warning', { method: 'POST', body: data, headers: { 'Accept': 'application/json' } }).catch(function () { });
                });
            </script>
        <?php endif; ?>
        <!-- Final de barra superior -->

        <?php if ($isAdministratorLayout): ?>
            <section class="app-global-skeleton" id="appGlobalSkeleton" aria-label="Cargando contenido" aria-live="polite">
                <div class="app-skeleton-heading"><span class="app-skeleton-block kicker"></span><span
                        class="app-skeleton-block title"></span><span class="app-skeleton-block subtitle"></span></div>
                <div class="app-skeleton-toolbar"><span class="app-skeleton-block"></span><span
                        class="app-skeleton-block"></span><span class="app-skeleton-block compact"></span></div>
                <div class="app-skeleton-grid"><?php for ($i = 0; $i < 4; $i++): ?>
                        <article><span class="app-skeleton-block icon"></span><span
                                class="app-skeleton-block line strong"></span><span class="app-skeleton-block line"></span><span
                                class="app-skeleton-block line short"></span></article><?php endfor; ?>
                </div>
            </section>
        <?php endif; ?>
        <div class="app-page-content" id="appPageContent">
            <?= $content ?><?php if (!empty($pagePagination)):
                  $pagination = $pagePagination;
                  require APP_PATH . '/views/components/pagination.php'; endif; ?>
        </div>
    </main>
    <!-- Final de contenido principal -->

    <!-- Inicio de confirmacion de cierre de sesion -->
    <div class="logout-modal-overlay" id="logoutModal" hidden>
        <div class="logout-modal" role="dialog" aria-modal="true" aria-labelledby="logoutModalTitle"
            aria-describedby="logoutModalText">
            <span class="logout-modal-icon" aria-hidden="true">
                <i class="fa-solid fa-right-from-bracket"></i>
            </span>
            <h2 id="logoutModalTitle">¿Cerrar sesión?</h2>
            <p id="logoutModalText">¿Estás seguro de que deseas cerrar la sesión actual?</p>
            <div class="logout-modal-actions">
                <button class="modal-cancel-btn" id="logoutCancelBtn" type="button">Cancelar</button>
                <button class="modal-accept-btn" id="logoutAcceptBtn" type="button"
                    data-logout-url="<?= e(route('logout')) ?>">Aceptar</button>
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
