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
        <?php for ($i = 0; $i < ($isAdmin ? 4 : 3); $i++): ?>
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

<section class="notifications-shell is-loading" id="notificationsShell" aria-labelledby="notificationsTitle" aria-busy="true" data-csrf-token="<?= e($notificationCsrfToken) ?>" data-endpoints="<?= e(json_encode($notificationEndpoints, JSON_UNESCAPED_SLASHES)) ?>" data-is-admin="<?= $isAdmin ? 'true' : 'false' ?>" data-trash-retention-days="<?= (int)($notificationTrashRetentionDays ?? 60) ?>">
    <header class="notifications-heading">
        <div>
            <span class="notifications-eyebrow"><i class="fa-regular fa-comments"></i> Comunicación</span>
            <h1 id="notificationsTitle">Notificaciones</h1>
            <p>Consulta avisos, actualizaciones y actividades relacionadas con tus proyectos.</p>
        </div>
        <div class="notifications-heading-actions" aria-label="Acciones generales">
            <?php if ($isAdmin): ?>
                <button class="notification-action primary" id="btnNewNotification" type="button">
                    <i class="fa-solid fa-plus"></i><span>Nueva notificación</span>
                </button>
            <?php endif; ?>
            <button class="notification-action secondary" id="refreshNotifications" type="button">
                <i class="fa-solid fa-rotate"></i><span>Actualizar</span>
            </button>
        </div>
    </header>

    <section class="notification-stats" aria-label="Resumen de notificaciones">
        <?php foreach ($summary as $item): ?>
            <article class="notification-stat tone-<?= e($item['tone']) ?>" data-counter-card="<?= e($item['key']) ?>">
                <span class="notification-stat-icon"><i class="fa-solid <?= e($item['icon']) ?>" aria-hidden="true"></i></span>
                <div><strong class="notification-stat-value"><?= e($item['value']) ?></strong><span class="notification-stat-label"><?= e($item['label']) ?></span></div>
            </article>
        <?php endforeach; ?>
    </section>

    <!-- Pestañas Principales con estilo del sistema -->
    <section class="notification-toolbar notification-projects-pattern" aria-label="Búsqueda y filtros">
        <label class="notification-search notification-filter-search" for="notificationSearch">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input id="notificationSearch" type="search" placeholder="Buscar por título, mensaje o proyecto" autocomplete="off">
        </label>

        <label class="notification-filter-control" data-filter-control="status"><span>Mostrar</span><select id="notificationStatusFilter" aria-label="Mostrar notificaciones"><option value="all">Todas</option><option value="unread">No leídas</option><option value="hidden">Archivadas</option><?php if ($isAdmin): ?><option value="sent">Enviadas</option><?php endif; ?><option value="trash">Papelera</option></select></label>
        <label class="notification-filter-control" data-filter-control="type"><span>Tipo</span><select id="notificationTypeFilter" data-searchable="false" aria-label="Filtrar por tipo"><?php foreach ($typeFilters as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>

        <div class="notification-date-filter" data-filter-control="date"><button class="notification-date-trigger" id="notificationDateTrigger" type="button" aria-haspopup="dialog" aria-expanded="false" aria-controls="notificationDatePopover"><i class="fa-regular fa-calendar"></i><span>Fecha</span><small>Filtrar por fecha</small></button><div class="notification-date-popover" id="notificationDatePopover" role="dialog" aria-label="Filtrar por rango de fechas" hidden><label>Desde<input type="date" id="notificationDateFrom"></label><label>Hasta<input type="date" id="notificationDateTo"></label><div><button class="notification-action secondary" id="clearNotificationDate" type="button">Limpiar</button><button class="notification-action primary" id="applyNotificationDate" type="button">Aplicar</button></div></div></div>

    </section>

    <div class="notification-active-filter" id="notificationActiveFilter" aria-live="polite" hidden></div>

    <section class="notification-trash-toolbar" id="notificationTrashToolbar" aria-label="Acciones de papelera" hidden>
        <div class="notification-trash-notice"><span><i class="fa-regular fa-clock"></i></span><div><strong>La papelera se vacía automáticamente después de <?= (int)($notificationTrashRetentionDays ?? 60) ?> días</strong><p>Puedes restaurar o eliminar definitivamente las notificaciones antes de ese plazo.</p></div></div>
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
            <header class="notification-listing-heading"><div><span>Actividad</span><h2>Bandeja de notificaciones</h2></div><button class="notification-action secondary" id="markAllNotificationsRead" type="button" <?= $sidebarSummary['unread'] === 0 ? 'hidden' : '' ?>><i class="fa-solid fa-check-double"></i><span>Marcar todas como leídas</span></button></header>
            <?php foreach ($groups as $group => $notificationsList): ?>
                <section class="notification-group" aria-labelledby="group-<?= e(strtolower(str_replace(' ', '-', $group))) ?>">
                    <div class="notification-group-heading">
                        <h2 id="group-<?= e(strtolower(str_replace(' ', '-', $group))) ?>"><?= e($group) ?></h2>
                        <span><?= count($notificationsList) ?> <?= count($notificationsList) === 1 ? 'novedad' : 'novedades' ?></span>
                    </div>

                    <div class="notification-list">
                        <?php foreach ($notificationsList as $notification): ?>
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
                                        <button class="more-notification" data-notification-action="menu" type="button" aria-label="Mas opciones" aria-haspopup="menu" aria-expanded="false" title="Mas opciones"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                        <div class="notification-context-menu" role="menu" hidden>
                                            <button type="button" role="menuitem" data-menu-action="toggle-read"><i class="fa-regular <?= $notification['is_read'] ? 'fa-eye-slash' : 'fa-envelope-open' ?>"></i><span><strong><?= $notification['is_read'] ? 'Marcar como no leída' : 'Marcar como leída' ?></strong><small>Cambiar estado de lectura</small></span></button>
                                            <button type="button" role="menuitem" data-menu-action="delete"><i class="fa-solid fa-box-archive"></i><span><strong>Archivar</strong><small>Ocultar de la bandeja sin eliminar</small></span></button>
                                            <button type="button" role="menuitem" class="danger" data-menu-action="destroy"><i class="fa-regular fa-trash-can"></i><span><strong>Mover a la papelera</strong><small>Se eliminará automáticamente en <?= (int)($notificationTrashRetentionDays ?? 60) ?> días</small></span></button>
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
                <h2>No hay notificaciones</h2>
                <p>Las nuevas actualizaciones y actividades aparecerán aquí.</p>
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
                <div class="side-metric" data-side-counter="unread"><span><i class="fa-solid fa-circle unread"></i>No leídas</span><strong><?= e((string) $sidebarSummary['unread']) ?></strong></div>
                <div class="side-metric" data-side-counter="read"><span><i class="fa-solid fa-circle read"></i>Leídas</span><strong><?= e((string) $sidebarSummary['read']) ?></strong></div>
                <div class="side-update"><i class="fa-solid fa-clock-rotate-left"></i><div><span>Última actualización</span><strong id="notificationLastUpdate"><?= e($sidebarSummary['updated']) ?></strong></div></div>
            </section>
            <section>
                <div class="side-panel-heading compact"><span><i class="fa-solid fa-chart-column"></i></span><div><small>Distribución</small><h2>Actividad reciente</h2></div></div>
                <div class="notification-activity-summary">
                    <?php foreach ($sidebarActivity as $activity): ?>
                        <div class="activity-summary-item tone-<?= e($activity['tone']) ?>"><span><i class="fa-solid <?= e($activity['icon']) ?>"></i><?= e($activity['label']) ?></span><strong><?= e((string) $activity['value']) ?></strong></div>
                    <?php endforeach; ?>
                </div>
            </section>
        </aside>
    </div>
</section>

<!-- Modal de detalle rediseñado -->
<div class="notification-modal-overlay" id="notificationDetailModal" hidden>
    <section class="notification-detail-modal" role="dialog" aria-modal="true" aria-labelledby="notificationModalTitle">
        <button class="notification-modal-close" type="button" data-modal-close aria-label="Cerrar detalle"><i class="fa-solid fa-xmark"></i></button>
        <header class="notification-message-header">
            <span class="notifications-eyebrow" id="notificationModalType">Notificación</span>
            <h2 id="notificationModalTitle"></h2>
            <div class="notification-message-context">
                <span id="notificationModalDate"></span>
                <span id="notificationModalStatus"></span>
            </div>
        </header>

        <div class="notification-message-body">
            <div class="notification-detail-sender" id="notificationModalSenderBlock" hidden>
                <small class="notification-detail-label">Enviado por</small>
                <strong id="notificationModalSender"></strong>
            </div>

            <p id="notificationModalMessage"></p>

            <div class="notification-detail-context-box" id="notificationModalContextBlock" hidden>
                <small class="notification-detail-label">Relacionado con</small>
                <strong id="notificationModalContextTitle"></strong>
                <span id="notificationModalContextSub" class="notification-detail-sub"></span>
            </div>
        </div>

        <footer class="notification-modal-actions">
            <div class="notification-modal-menu-container">
                <button class="notification-action secondary notification-three-dots-btn" type="button" id="notificationModalMenuBtn" aria-label="Más opciones" aria-expanded="false">
                    <i class="fa-solid fa-ellipsis-vertical"></i>
                </button>
                <div class="notification-context-menu" id="notificationModalContextMenu" hidden>
                    <button type="button" id="notificationModalToggleReadBtn">
                        <i class="fa-regular fa-envelope"></i>
                        <span>Marcar como no leída</span>
                    </button>
                    <button type="button" id="notificationModalArchiveBtn">
                        <i class="fa-solid fa-box-archive"></i>
                        <span>Archivar</span>
                    </button>
                    <button type="button" class="danger" id="notificationModalTrashBtn">
                        <i class="fa-regular fa-trash-can"></i>
                        <span>Mover a papelera</span>
                    </button>
                </div>
            </div>

            <button class="notification-action secondary" type="button" data-modal-close>Cerrar</button>
            <a class="notification-action primary" id="notificationModalDestination" href="#" hidden>
                <span id="notificationModalDestinationLabel"></span>
                <i class="fa-solid fa-arrow-up-right-from-square"></i>
            </a>
        </footer>
    </section>
</div>

<!-- Modal para archivar/papelera -->
<div class="notification-modal-overlay" id="notificationDeleteModal" hidden>
    <section class="notification-detail-modal compact" role="alertdialog" aria-modal="true" aria-labelledby="notificationDeleteTitle">
        <h2 id="notificationDeleteTitle">¿Deseas archivar esta notificación?</h2>
        <p id="notificationDeleteText">La notificación saldrá del listado principal, pero podrás recuperarla desde el filtro Archivadas.</p>
        <div class="notification-modal-actions"><button class="notification-action secondary" type="button" data-modal-close>Cancelar</button><button class="notification-action danger" id="confirmDeleteNotification" type="button">Archivar</button></div>
    </section>
</div>

<!-- Modal de creación exclusivo del administrador -->
<?php if ($isAdmin): ?>
<div class="notification-modal-overlay" id="notificationCreateModal" hidden>
    <section class="notification-detail-modal" role="dialog" aria-modal="true" aria-labelledby="notificationCreateTitle">
        <button class="notification-modal-close" type="button" data-modal-close aria-label="Cerrar modal de creación"><i class="fa-solid fa-xmark"></i></button>
        <header class="notification-message-header">
            <span class="notifications-eyebrow"><i class="fa-solid fa-gear"></i> Gestión Administrativa</span>
            <h2 id="notificationCreateTitle">Nueva notificación</h2>
            <p style="color: var(--muted); font-size: 13px; margin: 5px 0 0;">Envía un aviso dirigido o institucional a los usuarios del sistema.</p>
        </header>

        <form id="notificationCreateForm">
            <input type="hidden" name="_csrf" value="<?= e($adminNotificationCsrf) ?>">
            
            <div class="form-group"><label for="newNotificationScope">Destinatarios</label><select id="newNotificationScope" name="scope" required><option value="student_one">Un estudiante</option><option value="student_many">Varios estudiantes</option><option value="teacher_one">Un docente</option><option value="teacher_many">Varios docentes</option><option value="semester_students">Estudiantes por semestre</option><option value="all_students">Todos los estudiantes</option><option value="all_teachers">Todos los docentes</option><option value="all">Todos los usuarios</option></select></div>
            <div class="form-group" id="groupScopeRecipients"><label for="newNotificationRecipientSearch">Buscar por nombre, correo o cédula</label><input id="newNotificationRecipientSearch" type="search" autocomplete="off" autocorrect="off" autocapitalize="none" spellcheck="false" placeholder="Escribe al menos 2 caracteres"><div id="newNotificationRecipientResults" class="notification-recipient-results" role="listbox" hidden></div><div id="newNotificationRecipientChips" class="notification-recipient-chips"></div></div>
            <div class="form-group" id="groupScopeSemester" hidden><label for="newNotificationSemester">Semestre</label><select id="newNotificationSemester" name="semester"><option value="">Selecciona un semestre</option></select></div>
            <div class="form-group" id="groupScopeMass" hidden><p id="newNotificationMassSummary" class="notification-send-summary"></p><label class="checkbox-label notification-confirmation-check"><input type="checkbox" name="confirm_mass" id="confirmMassCheckbox" value="1"><span>Confirmo este envío masivo.</span></label></div>
            <div class="form-group" id="groupScopeAll" hidden>
                <div class="all-users-warning" style="background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.3); border-radius: 12px; padding: 12px; display: flex; align-items: center; gap: 10px; color: #d97706; font-size: 13px;">
                    <i class="fa-solid fa-triangle-exclamation" style="font-size: 16px;"></i>
                    <span>Este comunicado será enviado a todos los usuarios activos del sistema.</span>
                </div>
                <label class="checkbox-label" style="display: flex; align-items: flex-start; gap: 9px; cursor: pointer; font-size: 13px; color: var(--text); font-weight: 600;">
                    <input type="checkbox" name="confirm_all" id="confirmAllCheckbox" value="1">
                    <span>Confirmo que este comunicado institucional debe llegar a todos los usuarios activos.</span>
                </label>
            </div>

            <p class="notification-send-summary" id="newNotificationSendSummary" aria-live="polite"></p>
            <div class="form-group"><label for="newNotificationTemplate">Plantilla rápida</label><select id="newNotificationTemplate"><option value="">Ninguna</option><option value="institutional">Comunicado institucional</option><option value="maintenance">Mantenimiento programado</option><option value="period">Cambio de periodo académico</option><option value="deadline">Recordatorio de fecha límite</option><option value="call">Convocatoria</option><option value="platform">Actualización de plataforma</option><option value="academic">Asignación académica</option><option value="important">Aviso importante</option></select></div>

            <div class="form-group" style="display: grid; gap: 6px;">
                <label for="newNotificationType" style="font-weight: 750; font-size: 13px; color: var(--text);">Tipo de notificación</label>
                <select id="newNotificationType" name="type" required style="min-height: 40px; padding: 0 12px; border: 1px solid var(--line); border-radius: 9px; background: var(--surface-soft); color: var(--text); font-family: inherit;">
                    <option value="system">Comunicado institucional</option>
                    <option value="repository">Información académica</option>
                    <option value="reminder">Recordatorio</option>
                    <option value="status_change">Aviso importante</option>
                    <option value="other" data-custom-type="1">Otro</option>
                </select>
            </div>
            <div class="form-group" id="groupScopeCustomType" hidden><label for="newNotificationCustomType">Nombre del tipo</label><input id="newNotificationCustomType" name="custom_type_label" maxlength="80" placeholder="Ej. Aviso de secretaría"></div>

            <div class="form-group" style="display: grid; gap: 6px;">
                <label for="newNotificationTitle" style="font-weight: 750; font-size: 13px; color: var(--text);">Título</label>
                <input type="text" id="newNotificationTitle" name="title" maxlength="180" required placeholder="Ingresa el título de la notificación" style="min-height: 40px; padding: 0 12px; border: 1px solid var(--line); border-radius: 9px; background: var(--surface-soft); color: var(--text); font-family: inherit;">
            </div>

            <div class="form-group" style="display: grid; gap: 6px;">
                <label for="newNotificationMessage" style="font-weight: 750; font-size: 13px; color: var(--text);">Mensaje</label>
                <textarea id="newNotificationMessage" name="message" maxlength="2000" required placeholder="Escribe el cuerpo del mensaje..." style="min-height: 120px; padding: 12px; border: 1px solid var(--line); border-radius: 9px; background: var(--surface-soft); color: var(--text); font-family: inherit; resize: vertical;"></textarea>
            </div>

            <p class="error-message" id="newNotificationError" style="color: var(--danger); font-size: 12px; font-weight: 700; margin: 0;" hidden></p>

            <div class="notification-modal-actions" style="margin-top: 15px; border-top: 1px solid var(--line); padding-top: 15px;">
                <button class="notification-action secondary" type="button" data-modal-close>Cancelar</button>
                <button class="notification-action primary" type="submit" id="btnSubmitNewNotification">Enviar notificación</button>
            </div>
        </form>
    </section>
</div>
<?php endif; ?>

<?php if ($isAdmin): ?>
<div class="notification-modal-overlay" id="notificationSendConfirmModal" hidden>
    <section class="notification-detail-modal compact" role="dialog" aria-modal="true" aria-labelledby="notificationSendConfirmTitle">
        <button class="notification-modal-close" type="button" id="closeNotificationSendConfirm" aria-label="Cerrar confirmación"><i class="fa-solid fa-xmark"></i></button>
        <h2 id="notificationSendConfirmTitle">Confirmar envío</h2>
        <p id="notificationSendConfirmText"></p>
        <p class="notification-send-summary" id="notificationSendConfirmMeta"></p>
        <div class="notification-modal-actions"><button class="notification-action secondary" id="cancelNotificationSendConfirm" type="button">Cancelar</button><button class="notification-action primary" id="confirmNotificationSend" type="button">Enviar notificación</button></div>
    </section>
</div>
<?php endif; ?>

<div class="notification-toast" id="notificationToast" role="status" aria-live="polite" hidden></div>
