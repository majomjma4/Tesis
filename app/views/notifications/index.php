<link rel="stylesheet" href="<?= e(asset('css/notifications.css')) ?>">

<section class="notifications-preloader" id="notificationsPreloader" aria-label="Cargando notificaciones" aria-live="polite">
    <span class="sr-only">Cargando notificaciones...</span>
    <div class="skeleton-heading">
        <div>
            <span class="notification-skeleton skeleton-kicker"></span>
            <span class="notification-skeleton skeleton-title"></span>
            <span class="notification-skeleton skeleton-subtitle"></span>
        </div>
        <div class="skeleton-heading-actions">
            <span class="notification-skeleton skeleton-button"></span>
            <span class="notification-skeleton skeleton-button wide"></span>
        </div>
    </div>
    <div class="skeleton-stats" aria-hidden="true">
        <?php for ($i = 0; $i < 4; $i++): ?>
            <div class="skeleton-stat">
                <span class="notification-skeleton skeleton-icon"></span>
                <div><span class="notification-skeleton skeleton-number"></span><span class="notification-skeleton skeleton-label"></span></div>
            </div>
        <?php endfor; ?>
    </div>
    <div class="skeleton-toolbar notification-skeleton" aria-hidden="true"></div>
    <div class="skeleton-content" aria-hidden="true">
        <div>
            <span class="notification-skeleton skeleton-group-title"></span>
            <?php for ($i = 0; $i < 5; $i++): ?>
                <div class="skeleton-notification">
                    <span class="notification-skeleton skeleton-dot"></span>
                    <span class="notification-skeleton skeleton-avatar"></span>
                    <div class="skeleton-lines">
                        <span class="notification-skeleton skeleton-line short"></span>
                        <span class="notification-skeleton skeleton-line"></span>
                        <span class="notification-skeleton skeleton-line medium"></span>
                    </div>
                    <span class="notification-skeleton skeleton-action"></span>
                </div>
            <?php endfor; ?>
        </div>
        <div class="skeleton-side-card">
            <span class="notification-skeleton skeleton-line medium"></span>
            <span class="notification-skeleton skeleton-chart"></span>
            <span class="notification-skeleton skeleton-line"></span>
            <span class="notification-skeleton skeleton-line"></span>
            <span class="notification-skeleton skeleton-side-block"></span>
        </div>
    </div>
</section>

<section class="notifications-shell is-loading" id="notificationsShell" aria-labelledby="notificationsTitle" aria-busy="true" data-csrf-token="<?= e($notificationCsrfToken) ?>" data-endpoints="<?= e(json_encode($notificationEndpoints, JSON_UNESCAPED_SLASHES)) ?>">
    <header class="notifications-heading">
        <div>
            <span class="notifications-eyebrow"><i class="fa-regular fa-bell"></i> Centro de novedades</span>
            <h1 id="notificationsTitle">Notificaciones</h1>
            <p>Consulta las novedades relacionadas con tus proyectos academicos.</p>
        </div>
        <div class="notifications-heading-actions" aria-label="Acciones generales">
            <button class="notification-action secondary" id="refreshNotifications" type="button">
                <i class="fa-solid fa-rotate"></i><span>Actualizar</span>
            </button>
            <button class="notification-action primary" id="markAllNotificationsRead" type="button" <?= $sidebarSummary['unread'] === 0 ? 'disabled' : '' ?>>
                <i class="fa-solid fa-check-double"></i><span>Marcar todas como leidas</span>
            </button>
        </div>
    </header>

    <section class="notification-stats" aria-label="Resumen de notificaciones">
        <?php foreach ($summary as $item): ?>
            <article class="notification-stat tone-<?= e($item['tone']) ?>" data-counter-card="<?= e($item['key']) ?>">
                <span class="notification-stat-icon"><i class="fa-solid <?= e($item['icon']) ?>"></i></span>
                <div><strong><?= e($item['value']) ?></strong><span><?= e($item['label']) ?></span></div>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="notification-toolbar" aria-label="Busqueda y filtros">
        <label class="notification-search" for="notificationSearch">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input id="notificationSearch" type="search" placeholder="Buscar notificaciones..." autocomplete="off">
            <kbd>Ctrl K</kbd>
        </label>
        <div class="notification-filter notification-filter-custom" data-filter-control="status">
            <i class="fa-solid fa-sliders"></i>
            <select id="notificationStatusFilter" class="notification-filter-native" tabindex="-1" aria-hidden="true">
                <?php foreach ($statusFilters as $value => $label): ?>
                    <option value="<?= e($value) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="notification-filter-trigger" type="button" aria-haspopup="listbox" aria-expanded="false">
                <span>Estado: Todas</span><i class="fa-solid fa-chevron-down"></i>
            </button>
            <div class="notification-filter-menu" role="listbox" aria-label="Filtrar por estado" hidden>
                <?php foreach ($statusFilters as $value => $label): ?>
                    <button type="button" role="option" data-filter-value="<?= e($value) ?>" aria-selected="<?= $value === 'all' ? 'true' : 'false' ?>">
                        <span class="filter-option-icon"><i class="fa-solid <?= $value === 'hidden' ? 'fa-box-archive' : ($value === 'trash' ? 'fa-trash-can' : ($value === 'unread' ? 'fa-eye-slash' : ($value === 'read' ? 'fa-eye' : ($value === 'all' ? 'fa-layer-group' : 'fa-bell')))) ?>"></i></span>
                        <span><?= e($label) ?></span><i class="fa-solid fa-check filter-option-check"></i>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="notification-filter notification-filter-custom" data-filter-control="type">
            <i class="fa-solid fa-tags"></i>
            <select id="notificationTypeFilter" class="notification-filter-native" tabindex="-1" aria-hidden="true">
                <?php foreach ($typeFilters as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?>
            </select>
            <button class="notification-filter-trigger" type="button" aria-haspopup="listbox" aria-expanded="false"><span>Tipo: Todos</span><i class="fa-solid fa-chevron-down"></i></button>
            <div class="notification-filter-menu" role="listbox" aria-label="Filtrar por tipo" hidden>
                <?php foreach ($typeFilters as $value => $label): ?>
                    <button type="button" role="option" data-filter-value="<?= e($value) ?>" aria-selected="<?= $value === 'all' ? 'true' : 'false' ?>"><span class="filter-option-icon"><i class="fa-solid fa-bell"></i></span><span><?= e($label) ?></span><i class="fa-solid fa-check filter-option-check"></i></button>
                <?php endforeach; ?>
            </div>
        </div>
        <button class="clear-filter" id="clearNotificationFilters" type="button" aria-label="Limpiar filtros" hidden>
            <i class="fa-solid fa-filter-circle-xmark"></i><span>Limpiar</span>
        </button>
    </section>
    <div class="notification-active-filter" id="notificationActiveFilter" hidden>
        <span><i class="fa-solid fa-filter"></i>Filtro activo: <strong id="notificationActiveFilterLabel"></strong></span>
        <button type="button" id="clearActiveNotificationFilter" aria-label="Quitar filtro activo"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <section class="notification-trash-toolbar" id="notificationTrashToolbar" aria-label="Acciones de papelera" hidden>
        <div class="notification-trash-notice"><span><i class="fa-regular fa-clock"></i></span><div><strong>La papelera se vacia automaticamente despues de 30 dias</strong><p>Puedes restaurar o eliminar definitivamente las notificaciones antes de ese plazo.</p></div></div>
        <div class="notification-trash-selection">
            <label><input type="checkbox" id="selectAllTrashNotifications"><span>Seleccionar todas</span></label>
            <span id="trashSelectionCount">0 seleccionadas</span>
        </div>
        <div class="notification-trash-actions">
            <button class="notification-action secondary" id="restoreSelectedNotifications" type="button" disabled><i class="fa-solid fa-rotate-left"></i>Restaurar seleccionadas</button>
            <button class="notification-action danger" id="deleteSelectedNotifications" type="button" disabled><i class="fa-regular fa-trash-can"></i>Eliminar seleccionadas</button>
            <button class="notification-action danger ghost-danger" id="emptyNotificationTrash" type="button"><i class="fa-solid fa-trash-can-arrow-up"></i>Vaciar papelera</button>
        </div>
    </section>

    <div class="notifications-layout">
        <main class="notification-groups" id="notificationGroups">
            <?php foreach ($groups as $group => $notifications): ?>
                <section class="notification-group" aria-labelledby="group-<?= e(strtolower(str_replace(' ', '-', $group))) ?>">
                    <div class="notification-group-heading">
                        <h2 id="group-<?= e(strtolower(str_replace(' ', '-', $group))) ?>"><?= e($group) ?></h2>
                        <span><?= count($notifications) ?> <?= count($notifications) === 1 ? 'novedad' : 'novedades' ?></span>
                    </div>

                    <div class="notification-list">
                        <?php foreach ($notifications as $notification): ?>
                            <article class="notification-row type-<?= e(str_replace(' ', '-', $notification['type_class'])) ?> <?= $notification['unread'] ? 'is-unread' : 'is-read' ?>" data-notification-id="<?= e((string) $notification['id']) ?>" data-type="<?= e($notification['type']) ?>" data-read="<?= $notification['is_read'] ? 'true' : 'false' ?>" data-filter="<?= e($notification['type']) ?>" data-search="<?= e(strtolower($notification['title'] . ' ' . $notification['description'] . ' ' . $notification['project'])) ?>">
                                <span class="unread-dot" aria-label="<?= $notification['unread'] ? 'No leida' : 'Leida' ?>"></span>
                                <span class="notification-type-icon"><i class="fa-solid <?= e($notification['icon']) ?>"></i></span>
                                <div class="notification-copy">
                                    <div class="notification-copy-heading">
                                        <span class="notification-category"><?= e($notification['filter']) ?></span>
                                        <span class="notification-date-mobile"><?= e($notification['time']) ?></span>
                                    </div>
                                    <h3><?= e($notification['title']) ?></h3>
                                    <p><?= e($notification['description']) ?></p>
                                    <span class="notification-project"><i class="fa-regular fa-folder-open"></i><span class="notification-project-name"><?= e($notification['project']) ?></span></span>
                                </div>
                                <div class="notification-meta">
                                    <time datetime="2026-07-15T<?= e($notification['time']) ?>:00"><?= e($notification['date']) ?><strong><?= e($notification['time']) ?></strong></time>
                                    <div class="notification-row-actions">
                                        <button class="view-notification" data-notification-action="open-detail" type="button"><i class="fa-regular fa-file-lines"></i> Detalle</button>
                                        <button class="mark-notification" data-notification-action="toggle-read" type="button" aria-pressed="<?= $notification['is_read'] ? 'true' : 'false' ?>" aria-label="<?= $notification['is_read'] ? 'Marcar como no leida' : 'Marcar como leida' ?>" title="<?= $notification['is_read'] ? 'Marcar como no leida' : 'Marcar como leida' ?>"><i class="fa-regular <?= $notification['is_read'] ? 'fa-eye' : 'fa-eye-slash' ?>"></i></button>
                                        <button class="more-notification" data-notification-action="menu" type="button" aria-label="Mas opciones" aria-haspopup="menu" aria-expanded="false" title="Mas opciones"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                        <div class="notification-context-menu" role="menu" hidden>
                                            <button type="button" role="menuitem" data-menu-action="delete"><i class="fa-solid fa-box-archive"></i><span><strong>Archivar</strong><small>Ocultar de la bandeja sin eliminar</small></span></button>
                                            <button type="button" role="menuitem" class="danger" data-menu-action="destroy"><i class="fa-regular fa-trash-can"></i><span><strong>Mover a la papelera</strong><small>Se eliminara automaticamente en 30 dias</small></span></button>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>

            <nav class="notification-pagination" id="notificationPagination" aria-label="Paginación de notificaciones" data-pagination="<?= e(json_encode($notificationPagination, JSON_UNESCAPED_UNICODE)) ?>"></nav>

            <div class="notifications-empty" id="notificationsEmpty" <?= $groups !== [] || $loadError !== null ? 'hidden' : '' ?>>
                <span><i class="fa-regular fa-bell-slash"></i></span>
                <h2>No tienes notificaciones por el momento.</h2>
                <p>No se encontraron notificaciones con los filtros seleccionados.</p>
            </div>
            <div class="notifications-empty notification-load-error" id="notificationsLoadError" <?= $loadError === null ? 'hidden' : '' ?>>
                <span><i class="fa-solid fa-triangle-exclamation"></i></span><h2><?= e($loadError ?? '') ?></h2>
                <button class="notification-action primary" id="retryNotifications" type="button">Reintentar</button>
            </div>
        </main>

        <aside class="notification-side-panel" aria-label="Resumen y accesos rapidos">
            <section>
                <div class="side-panel-heading"><span><i class="fa-solid fa-chart-pie"></i></span><div><small>Actividad</small><h2>Resumen</h2></div></div>
                <?php $readProgress = ($sidebarSummary['read'] + $sidebarSummary['unread']) > 0 ? (int) round(($sidebarSummary['read'] / ($sidebarSummary['read'] + $sidebarSummary['unread'])) * 100) : 0; ?>
                <div class="side-progress-label"><span>Progreso de lectura</span><strong id="notificationReadProgressLabel"><?= e((string) $readProgress) ?>%</strong></div>
                <div class="side-progress" id="notificationReadProgress" style="--progress: <?= e((string) $readProgress) ?>%" role="progressbar" aria-label="Porcentaje de notificaciones leidas" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= e((string) $readProgress) ?>"><span></span></div>
                <div class="side-metric" data-side-counter="unread"><span><i class="fa-solid fa-circle unread"></i>No leidas</span><strong><?= e((string) $sidebarSummary['unread']) ?></strong></div>
                <div class="side-metric" data-side-counter="read"><span><i class="fa-solid fa-circle read"></i>Leidas</span><strong><?= e((string) $sidebarSummary['read']) ?></strong></div>
                <div class="side-update"><i class="fa-solid fa-clock-rotate-left"></i><div><span>Ultima actualizacion</span><strong id="notificationLastUpdate"><?= e($sidebarSummary['updated']) ?></strong></div></div>
            </section>
            <section>
                <div class="side-panel-heading compact"><span><i class="fa-solid fa-chart-column"></i></span><div><small>Distribucion</small><h2>Actividad reciente</h2></div></div>
                <div class="notification-activity-summary">
                    <?php foreach ($sidebarActivity as $activity): ?>
                        <div class="activity-summary-item tone-<?= e($activity['tone']) ?>"><span><i class="fa-solid <?= e($activity['icon']) ?>"></i><?= e($activity['label']) ?></span><strong><?= e((string) $activity['value']) ?></strong></div>
                    <?php endforeach; ?>
                </div>
            </section>
        </aside>
    </div>
</section>

<div class="notification-modal-overlay" id="notificationDetailModal" hidden>
    <section class="notification-detail-modal" role="dialog" aria-modal="true" aria-labelledby="notificationModalTitle">
        <button class="notification-modal-close" type="button" data-modal-close aria-label="Cerrar detalle"><i class="fa-solid fa-xmark"></i></button>
        <header class="notification-message-header">
            <span class="notifications-eyebrow" id="notificationModalType">Notificacion</span>
            <h2 id="notificationModalTitle"></h2>
            <div class="notification-message-context"><span id="notificationModalProject"></span><span id="notificationModalDate"></span><span id="notificationModalStatus"></span></div>
        </header>
        <div class="notification-message-body"><p id="notificationModalMessage"></p></div>
        <div class="notification-modal-actions"><button class="notification-action secondary" type="button" data-modal-close>Cerrar</button><button class="notification-action secondary" id="notificationModalMarkUnread" type="button"><i class="fa-regular fa-eye-slash"></i>Marcar como no leida</button><a class="notification-action primary" id="notificationModalDestination" href="#" hidden><span>Ir a la seccion relacionada</span><i class="fa-solid fa-arrow-up-right-from-square"></i></a></div>
    </section>
</div>

<div class="notification-modal-overlay" id="notificationDeleteModal" hidden>
    <section class="notification-detail-modal compact" role="alertdialog" aria-modal="true" aria-labelledby="notificationDeleteTitle">
        <h2 id="notificationDeleteTitle">¿Deseas archivar esta notificacion?</h2>
        <p id="notificationDeleteText">La notificacion saldra del listado principal, pero podras recuperarla desde el filtro Archivadas.</p>
        <div class="notification-modal-actions"><button class="notification-action secondary" type="button" data-modal-close>Cancelar</button><button class="notification-action danger" id="confirmDeleteNotification" type="button">Archivar</button></div>
    </section>
</div>

<div class="notification-toast" id="notificationToast" role="status" aria-live="polite" hidden></div>
