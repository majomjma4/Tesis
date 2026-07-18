<?php $eventPayload = json_encode($calendarEvents, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
<section class="calendar-hero" aria-labelledby="calendarPageTitle">
    <div>
        <span class="calendar-kicker"><i class="fa-regular fa-calendar-check"></i> Agenda académica</span>
        <h1 id="calendarPageTitle">Planifica, prioriza y cumple</h1>
        <p>Gestiona entregas, tutorías y revisiones con una agenda clara y centralizada.</p>
    </div>
    <div class="calendar-hero-actions">
        <button class="calendar-secondary-btn" id="calendarTodayBtn" type="button"><i class="fa-solid fa-location-crosshairs"></i> Hoy</button>
        <button class="calendar-primary-btn" id="calendarNewEventBtn" type="button"><i class="fa-solid fa-plus"></i> Nuevo evento</button>
    </div>
</section>

<section class="calendar-stats" aria-label="Resumen del calendario">
    <article><button class="calendar-stat-action" type="button" data-scope="month" aria-pressed="false" aria-label="Ver eventos del mes"><span class="calendar-stat-icon blue"><i class="fa-solid fa-calendar-day"></i></span><div><strong id="calendarMonthCount">0</strong><span>Eventos del mes</span></div><i class="fa-solid fa-chevron-right calendar-stat-arrow"></i></button></article>
    <article><button class="calendar-stat-action" type="button" data-scope="week" aria-pressed="false" aria-label="Ver próximos eventos"><span class="calendar-stat-icon orange"><i class="fa-solid fa-bolt"></i></span><div><strong id="calendarUpcomingCount">0</strong><span>Próximos 7 días</span></div><i class="fa-solid fa-chevron-right calendar-stat-arrow"></i></button></article>
    <article><button class="calendar-stat-action" type="button" data-scope="completed" aria-pressed="false" aria-label="Ver eventos completados del mes"><span class="calendar-stat-icon green"><i class="fa-solid fa-circle-check"></i></span><div><strong id="calendarCompletedCount">0%</strong><span>Progreso del mes</span><small class="calendar-stat-detail" id="calendarCompletedDetail">0 de 0 completados</small><span class="calendar-progress-track" aria-hidden="true"><i id="calendarProgressBar"></i></span></div><i class="fa-solid fa-chevron-right calendar-stat-arrow"></i></button></article>
</section>

<div class="calendar-workspace" data-calendar-events='<?= e($eventPayload ?: '[]') ?>' data-events-url="<?= e(route('calendar-events')) ?>" data-project-url="<?= e($projectsUrl) ?>">
    <section class="calendar-board" aria-label="Calendario mensual">
        <header class="calendar-toolbar">
            <div class="calendar-navigation">
                <button class="calendar-icon-btn" id="calendarPrevBtn" type="button" aria-label="Mes anterior"><i class="fa-solid fa-chevron-left"></i></button>
                <button class="calendar-icon-btn" id="calendarNextBtn" type="button" aria-label="Mes siguiente"><i class="fa-solid fa-chevron-right"></i></button>
                <h2 id="calendarMonthTitle" aria-live="polite"></h2>
                <button class="calendar-mobile-agenda-btn" id="calendarOpenAgendaBtn" type="button" aria-controls="calendarAgenda" aria-expanded="false"><i class="fa-solid fa-list-check"></i> Agenda</button>
            </div>
            <label class="calendar-search"><i class="fa-solid fa-magnifying-glass"></i><span class="sr-only">Buscar eventos</span><input id="calendarSearch" type="search" placeholder="Buscar evento..."></label>
        </header>
        <div class="calendar-viewbar">
            <div class="calendar-view-switcher" role="group" aria-label="Cambiar vista">
                <button class="active" type="button" data-view="month" aria-selected="true" aria-controls="calendarMonthView"><i class="fa-regular fa-calendar"></i> Mes</button>
                <button type="button" data-view="week" aria-selected="false" aria-controls="calendarWeekView"><i class="fa-solid fa-table-columns"></i> Semana</button>
                <button type="button" data-view="list" aria-selected="false" aria-controls="calendarListView"><i class="fa-solid fa-list"></i> Lista</button>
            </div>
            <div class="calendar-list-tools" id="calendarListTools" hidden>
                <div class="calendar-select-wrap calendar-sort-wrap" data-select="sort" data-value="date"><i class="fa-solid fa-arrow-down-wide-short calendar-select-leading"></i><select id="calendarSort" aria-label="Ordenar eventos"><option value="date">Más próximos</option><option value="priority">Prioridad alta primero</option><option value="completed">Completados al final</option></select><i class="fa-solid fa-chevron-down calendar-select-chevron"></i></div>
            </div>
            <div class="calendar-filter-status" id="calendarFilterStatus" hidden><i class="fa-solid fa-filter"></i><span></span><button id="calendarClearFilters" type="button">Limpiar</button></div>
        </div>
        <div class="calendar-filters" aria-label="Filtrar eventos">
            <button class="calendar-filter active" type="button" data-filter="all">Todos</button>
            <button class="calendar-filter" type="button" data-filter="delivery"><span class="filter-dot delivery"></span>Entregas</button>
            <button class="calendar-filter" type="button" data-filter="meeting"><span class="filter-dot meeting"></span>Reuniones</button>
            <button class="calendar-filter" type="button" data-filter="review"><span class="filter-dot review"></span>Revisiones</button>
            <button class="calendar-filter" type="button" data-filter="deadline"><span class="filter-dot deadline"></span>Fechas límite</button>
            <button class="calendar-filter" type="button" data-filter="pending"><i class="fa-regular fa-clock"></i>Pendientes</button>
            <span class="calendar-filter-divider" aria-hidden="true"></span>
            <button class="calendar-filter calendar-priority-filter" type="button" data-priority="high"><i class="fa-solid fa-flag"></i>Alta</button>
            <button class="calendar-filter calendar-priority-filter" type="button" data-priority="medium"><i class="fa-solid fa-flag"></i>Media</button>
            <button class="calendar-filter calendar-priority-filter" type="button" data-priority="low"><i class="fa-solid fa-flag"></i>Baja</button>
        </div>
        <div class="calendar-view-stage">
            <div class="calendar-view-panel active" id="calendarMonthView" role="tabpanel">
                <div class="calendar-weekdays" aria-hidden="true"><span>Lun</span><span>Mar</span><span>Mié</span><span>Jue</span><span>Vie</span><span>Sáb</span><span>Dom</span></div>
                <div class="calendar-days-grid" id="calendarDaysGrid"></div>
            </div>
            <div class="calendar-view-panel calendar-week-view" id="calendarWeekView" role="tabpanel" hidden></div>
            <div class="calendar-view-panel calendar-list-view" id="calendarListView" role="tabpanel" hidden></div>
        </div>
    </section>

    <div class="calendar-agenda-backdrop" id="calendarAgendaBackdrop" hidden></div>
    <aside class="calendar-agenda" id="calendarAgenda" aria-labelledby="calendarAgendaTitle">
        <header><div><span class="calendar-kicker">Agenda del día</span><h2 id="calendarAgendaTitle"></h2><span class="calendar-agenda-date" id="calendarAgendaDate"></span></div><button class="calendar-agenda-close" id="calendarAgendaClose" type="button" aria-label="Cerrar agenda"><i class="fa-solid fa-xmark"></i></button></header>
        <button class="calendar-agenda-add" id="calendarAgendaAdd" type="button"><i class="fa-solid fa-plus"></i> Agregar a este día</button>
        <div class="calendar-agenda-list" id="calendarAgendaList"></div>
        <section class="calendar-upcoming"><header><div><span class="calendar-kicker">Planificación</span><h3>Próximos eventos</h3></div><span id="calendarUpcomingBadge">0</span></header><div id="calendarUpcomingList"></div></section>
        <div class="calendar-legend"><strong>Categorías</strong><span><i class="delivery"></i> Entrega</span><span><i class="meeting"></i> Reunión</span><span><i class="review"></i> Revisión</span><span><i class="deadline"></i> Fecha límite</span></div>
    </aside>
</div>

<div class="calendar-modal-overlay" id="calendarEventModal" hidden>
    <section class="calendar-modal" role="dialog" aria-modal="true" aria-labelledby="calendarModalTitle">
        <header><div><span class="calendar-kicker">Planificación académica</span><h2 id="calendarModalTitle">Nuevo evento</h2></div><button class="calendar-modal-close" id="calendarModalClose" type="button" aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button></header>
        <form id="calendarEventForm">
            <input id="calendarEventId" type="hidden">
            <label class="calendar-form-field calendar-form-wide"><span>Título *</span><input id="calendarEventTitle" type="text" maxlength="100" placeholder="Ej. Entregar capítulo metodológico" required></label>
            <label class="calendar-form-field calendar-form-wide"><span>Fecha *</span><input id="calendarEventDate" type="date" required></label>
            <div class="calendar-form-field"><span>Categoría</span><div class="calendar-select-wrap" data-select="type" data-value="delivery"><i class="fa-solid fa-layer-group calendar-select-leading"></i><select id="calendarEventType" aria-label="Categoría"><option value="delivery">Entrega</option><option value="meeting">Reunión</option><option value="review">Revisión</option><option value="deadline">Fecha límite</option></select><i class="fa-solid fa-chevron-down calendar-select-chevron"></i></div></div>
            <div class="calendar-form-field"><span>Prioridad</span><div class="calendar-select-wrap" data-select="priority" data-value="medium"><i class="fa-solid fa-signal calendar-select-leading"></i><select id="calendarEventPriority" aria-label="Prioridad"><option value="low">Baja</option><option value="medium" selected>Media</option><option value="high">Alta</option></select><i class="fa-solid fa-chevron-down calendar-select-chevron"></i></div></div>
            <label class="calendar-form-field calendar-form-wide"><span>Descripción</span><textarea id="calendarEventDescription" rows="3" maxlength="300" placeholder="Agrega una indicación breve para recordar lo importante"></textarea><small><span id="calendarDescriptionCount">0</span>/300 caracteres</small></label>
            <div class="calendar-modal-actions calendar-form-wide"><button class="calendar-cancel-btn" id="calendarModalCancel" type="button">Cancelar</button><button class="calendar-save-btn" id="calendarSaveBtn" type="submit"><i class="fa-solid fa-check"></i> Guardar evento</button></div>
        </form>
    </section>
</div>
<div class="calendar-modal-overlay" id="calendarDetailModal" hidden>
    <section class="calendar-detail-modal" role="dialog" aria-modal="true" aria-labelledby="calendarDetailTitle">
        <header><div><span class="calendar-detail-type" id="calendarDetailType"></span><h2 id="calendarDetailTitle"></h2></div><button class="calendar-modal-close" id="calendarDetailClose" type="button" aria-label="Cerrar detalles"><i class="fa-solid fa-xmark"></i></button></header>
        <div class="calendar-detail-summary"><span><i class="fa-regular fa-calendar"></i><strong id="calendarDetailDate"></strong></span><span><i class="fa-solid fa-flag"></i><strong id="calendarDetailPriority"></strong></span><span><i class="fa-regular fa-circle-check"></i><strong id="calendarDetailStatus"></strong></span></div>
        <div class="calendar-detail-description"><span>Descripción</span><p id="calendarDetailDescription"></p></div>
        <div class="calendar-detail-actions"><button class="calendar-cancel-btn" id="calendarDetailComplete" type="button"><i class="fa-solid fa-check"></i> Completar</button><button class="calendar-cancel-btn" id="calendarDetailEdit" type="button"><i class="fa-solid fa-pen"></i> Editar</button><a class="calendar-project-btn" id="calendarDetailProjectLink" href="<?= e($projectsUrl) ?>"><i class="fa-solid fa-arrow-right"></i> <span>Ir al proyecto</span></a></div>
    </section>
</div>
<div class="calendar-modal-overlay" id="calendarDeleteModal" hidden>
    <section class="calendar-confirm-modal" role="alertdialog" aria-modal="true" aria-labelledby="calendarDeleteTitle">
        <span class="calendar-confirm-icon"><i class="fa-regular fa-trash-can"></i></span><h2 id="calendarDeleteTitle">¿Eliminar este evento?</h2><p id="calendarDeleteMessage"></p>
        <div class="calendar-confirm-actions"><button class="calendar-cancel-btn" id="calendarDeleteCancel" type="button">Conservar evento</button><button class="calendar-delete-btn" id="calendarDeleteConfirm" type="button"><i class="fa-regular fa-trash-can"></i> Sí, eliminar</button></div>
    </section>
</div>
<div class="calendar-toast" id="calendarToast" role="status" aria-live="polite" hidden></div>
