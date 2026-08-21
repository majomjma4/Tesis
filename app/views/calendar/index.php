<?php $eventPayload = json_encode($calendarEvents, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
<!-- Inicio de presentación del calendario -->
<!-- Introduce el propósito de la pantalla y reúne sus acciones principales. -->
<section class="calendar-hero" aria-labelledby="calendarPageTitle">
    <div>
        <span class="calendar-kicker"><i class="fa-regular fa-calendar-check"></i> Agenda académica</span>
        <h1 id="calendarPageTitle">Organiza y consulta tu agenda académica</h1>
        <p>Consulta y administra entregas, tutorías, revisiones y fechas importantes desde un solo lugar.</p>
    </div>
    <div class="calendar-hero-actions">
        <button class="calendar-secondary-btn" id="calendarTodayBtn" type="button"><i class="fa-solid fa-location-crosshairs"></i> Hoy</button>
        <button class="calendar-primary-btn" id="calendarNewEventBtn" type="button"><i class="fa-solid fa-plus"></i> Nuevo evento</button>
    </div>
</section>
<!-- Final de presentación del calendario -->

<!-- Inicio de indicadores de planificación -->
<!-- Resume los eventos mensuales, próximos compromisos y progreso completado. -->
<section class="calendar-stats" aria-label="Resumen del calendario">
    <article><button class="calendar-stat-action" type="button" data-scope="month" aria-pressed="false" aria-label="Ver eventos del mes"><span class="calendar-stat-icon blue"><i class="fa-solid fa-calendar-day"></i></span><div><strong id="calendarMonthCount">0</strong><span>Eventos del mes</span></div><i class="fa-solid fa-chevron-right calendar-stat-arrow"></i></button></article>
    <article><button class="calendar-stat-action" type="button" data-scope="week" aria-pressed="false" aria-label="Ver próximos eventos"><span class="calendar-stat-icon orange"><i class="fa-solid fa-bolt"></i></span><div><strong id="calendarUpcomingCount">0</strong><span>Próximos 7 días</span></div><i class="fa-solid fa-chevron-right calendar-stat-arrow"></i></button></article>
    <article><button class="calendar-stat-action" type="button" data-scope="completed" aria-pressed="false" aria-label="Ver eventos completados del mes"><span class="calendar-stat-icon green"><i class="fa-solid fa-circle-check"></i></span><div><strong id="calendarCompletedCount">0%</strong><span>Eventos completados</span><small class="calendar-stat-detail" id="calendarCompletedDetail">0 de 0 completados</small><span class="calendar-progress-track" aria-hidden="true"><i id="calendarProgressBar"></i></span></div><i class="fa-solid fa-chevron-right calendar-stat-arrow"></i></button></article>
</section>
<!-- Final de indicadores de planificación -->

<!-- Inicio del espacio de trabajo del calendario -->
<!-- Contiene navegación, filtros, vistas intercambiables y agenda contextual. -->
<div class="calendar-workspace" data-calendar-events='<?= e($eventPayload ?: '[]') ?>' data-events-url="<?= e(route('calendar-events')) ?>" data-csrf="<?= e($calendarCsrf ?? '') ?>" data-project-url="<?= e($projectsUrl) ?>" data-project-filter="<?= (int) ($projectFilterId ?? 0) ?>">
    <section class="calendar-board" aria-label="Calendario mensual">
        <header class="calendar-toolbar">
            <div class="calendar-navigation">
                <button class="calendar-icon-btn" id="calendarPrevBtn" type="button" aria-label="Mes anterior"><i class="fa-solid fa-chevron-left"></i></button>
                <button class="calendar-icon-btn" id="calendarNextBtn" type="button" aria-label="Mes siguiente"><i class="fa-solid fa-chevron-right"></i></button>
                <h2 id="calendarMonthTitle" aria-live="polite"></h2>
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
        </div>
        <div class="calendar-filters" aria-label="Filtrar eventos">
            <button class="calendar-filter active" type="button" data-filter="all">Todos</button>
            <button class="calendar-filter" type="button" data-filter="delivery"><span class="filter-dot delivery"></span>Entregas</button>
            <button class="calendar-filter" type="button" data-filter="meeting"><span class="filter-dot meeting"></span>Reuniones</button>
            <button class="calendar-filter" type="button" data-filter="review"><span class="filter-dot review"></span>Revisiones</button>
            <button class="calendar-filter" type="button" data-filter="deadline"><span class="filter-dot deadline"></span>Fechas límite</button>
            <button class="calendar-filter" type="button" data-filter="personal"><span class="filter-dot personal"></span>Personal</button>
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

    <aside class="calendar-agenda" id="calendarAgenda" aria-labelledby="calendarAgendaTitle">
        <header><div><span class="calendar-kicker">Agenda del día</span><h2 id="calendarAgendaTitle"></h2><span class="calendar-agenda-date" id="calendarAgendaDate"></span></div><button class="calendar-agenda-close" id="calendarAgendaClose" type="button" aria-label="Cerrar agenda"><i class="fa-solid fa-xmark"></i></button></header>
        <button class="calendar-agenda-add" id="calendarAgendaAdd" type="button"><i class="fa-solid fa-plus"></i> Agregar evento</button>
        <div class="calendar-agenda-list" id="calendarAgendaList"></div>
        <section class="calendar-upcoming"><header><div><span class="calendar-kicker">Planificación</span><h3>Próximos eventos</h3></div><span id="calendarUpcomingBadge">0</span></header><div id="calendarUpcomingList"></div></section>
        <div class="calendar-legend"><strong>Categorías</strong><span><i class="delivery"></i> Entrega</span><span><i class="meeting"></i> Reunión</span><span><i class="review"></i> Revisión</span><span><i class="deadline"></i> Fecha límite</span><span><i class="personal"></i> Personal</span></div>
    </aside>
    <div class="calendar-modal-overlay calendar-agenda-modal-overlay" id="calendarAgendaModal" hidden></div>
</div>
<!-- Final del espacio de trabajo del calendario -->

<!-- Inicio del formulario de eventos -->
<!-- Permite crear y editar recordatorios académicos mediante un diálogo reutilizable. -->
<div class="calendar-modal-overlay" id="calendarEventModal" hidden>
    <section class="calendar-modal" role="dialog" aria-modal="true" aria-labelledby="calendarModalTitle">
        <header><div><span class="calendar-kicker">Planificación académica</span><h2 id="calendarModalTitle">Nuevo evento</h2></div><button class="calendar-modal-close" id="calendarModalClose" type="button" aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button></header>
        <form id="calendarEventForm">
            <input id="calendarEventId" type="hidden">
            <label class="calendar-form-field calendar-form-wide"><span>Título *</span><input id="calendarEventTitle" type="text" maxlength="100" placeholder="Ej. Entregar capítulo metodológico" required></label>
            <div class="calendar-form-field"><span>Fecha *</span><div class="calendar-date-time-control" data-calendar-date-control><input id="calendarEventDate" type="hidden" required><button id="calendarEventDateTrigger" class="calendar-date-time-trigger" type="button" aria-haspopup="dialog" aria-expanded="false"><span></span><i class="fa-regular fa-calendar"></i></button><div id="calendarDatePicker" class="calendar-date-picker" hidden role="dialog" aria-label="Seleccionar fecha"><header><button id="calendarDatePrev" type="button" aria-label="Mes anterior"><i class="fa-solid fa-chevron-left"></i></button><strong id="calendarDateHeading"></strong><button id="calendarDateNext" type="button" aria-label="Mes siguiente"><i class="fa-solid fa-chevron-right"></i></button></header><div class="calendar-date-weekdays" aria-hidden="true"><span>Lu</span><span>Ma</span><span>Mi</span><span>Ju</span><span>Vi</span><span>Sá</span><span>Do</span></div><div id="calendarDateDays" class="calendar-date-days"></div><footer><button id="calendarDateClear" type="button">Limpiar</button><button id="calendarDateToday" type="button">Hoy</button></footer></div></div></div>
            <div class="calendar-form-field"><span>Hora (opcional)</span><div class="calendar-date-time-control" data-calendar-time-control><input id="calendarEventTime" type="hidden"><button id="calendarEventTimeTrigger" class="calendar-date-time-trigger" type="button" aria-haspopup="dialog" aria-expanded="false"><span></span><i class="fa-regular fa-clock"></i></button><div id="calendarTimePicker" class="calendar-time-picker" hidden role="dialog" aria-label="Seleccionar hora"><header><strong>Selecciona una hora</strong><button id="calendarTimeClose" type="button" aria-label="Cerrar selector de hora"><i class="fa-solid fa-xmark"></i></button></header><div class="calendar-time-inputs"><label>Hora<input id="calendarTimeHour" type="number" inputmode="numeric" min="0" max="23" placeholder="00" aria-label="Hora, de 0 a 23"></label><b>:</b><label>Minutos<input id="calendarTimeMinute" type="number" inputmode="numeric" min="0" max="59" placeholder="00" aria-label="Minutos, de 0 a 59"></label></div><small>Usa formato de 24 horas.</small><footer><button id="calendarTimeClear" type="button">Sin hora</button><button id="calendarTimeApply" type="button">Aplicar</button></footer></div></div></div>
            <div class="calendar-form-field"><span>Categoría</span><div class="calendar-select-wrap" data-select="type" data-value="delivery"><i class="fa-solid fa-layer-group calendar-select-leading"></i><select id="calendarEventType" aria-label="Categoría"><option value="delivery">Entrega</option><option value="meeting">Reunión</option><option value="review">Revisión</option><option value="deadline">Fecha límite</option><option value="personal">Personal</option></select><i class="fa-solid fa-chevron-down calendar-select-chevron"></i></div></div>
            <div class="calendar-form-field"><span>Prioridad</span><div class="calendar-select-wrap" data-select="priority" data-value="medium"><i class="fa-solid fa-signal calendar-select-leading"></i><select id="calendarEventPriority" aria-label="Prioridad"><option value="low">Baja</option><option value="medium" selected>Media</option><option value="high">Alta</option></select><i class="fa-solid fa-chevron-down calendar-select-chevron"></i></div></div>
            <label class="calendar-form-field calendar-form-wide"><span>Descripción</span><textarea id="calendarEventDescription" rows="3" maxlength="300" placeholder="Agrega una indicación breve para recordar lo importante"></textarea><small><span id="calendarDescriptionCount">0</span>/300 caracteres</small></label>
            <div class="calendar-modal-actions calendar-form-wide"><button class="calendar-cancel-btn" id="calendarModalCancel" type="button">Cancelar</button><button class="calendar-save-btn" id="calendarSaveBtn" type="submit"><i class="fa-solid fa-check"></i> Guardar evento</button></div>
        </form>
    </section>
</div>
<!-- Final del formulario de eventos -->

<!-- Inicio del detalle del evento -->
<!-- Presenta toda la información del recordatorio y sus acciones relacionadas. -->
<div class="calendar-modal-overlay" id="calendarDetailModal" hidden>
    <section class="calendar-detail-modal" role="dialog" aria-modal="true" aria-labelledby="calendarDetailTitle">
        <header><div><span class="calendar-detail-type" id="calendarDetailType"></span><h2 id="calendarDetailTitle"></h2></div><button class="calendar-modal-close" id="calendarDetailClose" type="button" aria-label="Cerrar detalles"><i class="fa-solid fa-xmark"></i></button></header>
        <div class="calendar-detail-summary"><span><i class="fa-regular fa-calendar"></i><strong id="calendarDetailDate"></strong></span><span><i class="fa-solid fa-flag"></i><strong id="calendarDetailPriority"></strong></span><span><i class="fa-regular fa-circle-check"></i><strong id="calendarDetailStatus"></strong></span></div>
        <div class="calendar-detail-description"><span>Descripción</span><p id="calendarDetailDescription"></p></div>
        <div class="calendar-detail-actions"><button class="calendar-cancel-btn" id="calendarDetailComplete" type="button"><i class="fa-solid fa-check"></i> Completar</button><button class="calendar-cancel-btn" id="calendarDetailEdit" type="button"><i class="fa-solid fa-pen"></i> Editar</button><a class="calendar-project-btn" id="calendarDetailProjectLink" href="<?= e($projectsUrl) ?>"><i class="fa-solid fa-arrow-right"></i> <span>Ir al proyecto</span></a></div>
    </section>
</div>
<!-- Final del detalle del evento -->

<!-- Inicio de confirmación de eliminación -->
<!-- Solicita confirmación antes de retirar un evento y conserva una salida segura. -->
<div class="calendar-modal-overlay" id="calendarDeleteModal" hidden>
    <section class="calendar-confirm-modal" role="alertdialog" aria-modal="true" aria-labelledby="calendarDeleteTitle">
        <span class="calendar-confirm-icon"><i class="fa-regular fa-trash-can"></i></span><h2 id="calendarDeleteTitle">¿Eliminar este evento?</h2><p id="calendarDeleteMessage"></p>
        <div class="calendar-confirm-actions"><button class="calendar-cancel-btn" id="calendarDeleteCancel" type="button">Conservar evento</button><button class="calendar-delete-btn" id="calendarDeleteConfirm" type="button"><i class="fa-regular fa-trash-can"></i> Sí, eliminar</button></div>
    </section>
</div>
<!-- Final de confirmación de eliminación -->

<!-- Inicio de mensajes temporales -->
<!-- Comunica resultados y aloja la acción para deshacer cuando corresponde. -->
<div class="calendar-toast" id="calendarToast" role="status" aria-live="polite" hidden></div>
<!-- Final de mensajes temporales -->
