<?php
$eventPayload = json_encode($calendarEvents, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
<section class="calendar-hero" aria-labelledby="calendarPageTitle">
    <div>
        <span class="calendar-kicker"><i class="fa-regular fa-calendar-check"></i> Planificacion academica</span>
        <h1 id="calendarPageTitle">Organiza tus fechas importantes</h1>
        <p>Consulta entregas, reuniones y revisiones de tu proyecto en un solo lugar.</p>
    </div>
    <div class="calendar-hero-actions">
        <button class="calendar-secondary-btn" id="calendarTodayBtn" type="button">
            <i class="fa-solid fa-location-crosshairs"></i> Ir a hoy
        </button>
        <button class="calendar-primary-btn" id="calendarNewEventBtn" type="button">
            <i class="fa-solid fa-plus"></i> Nueva tarea
        </button>
    </div>
</section>

<section class="calendar-stats" aria-label="Resumen del calendario">
    <article><span class="calendar-stat-icon blue"><i class="fa-solid fa-calendar-day"></i></span><div><strong id="calendarMonthCount">0</strong><span>Eventos este mes</span></div></article>
    <article><span class="calendar-stat-icon orange"><i class="fa-solid fa-hourglass-half"></i></span><div><strong id="calendarUpcomingCount">0</strong><span>Proximos eventos</span></div></article>
    <article><span class="calendar-stat-icon green"><i class="fa-solid fa-circle-check"></i></span><div><strong id="calendarDeliveryCount">0</strong><span>Entregas programadas</span></div></article>
</section>

<div class="calendar-workspace" data-calendar-events='<?= e($eventPayload ?: '[]') ?>'>
    <section class="calendar-board" aria-label="Calendario mensual">
        <header class="calendar-toolbar">
            <div class="calendar-navigation">
                <button class="calendar-icon-btn" id="calendarPrevBtn" type="button" aria-label="Mes anterior"><i class="fa-solid fa-chevron-left"></i></button>
                <button class="calendar-icon-btn" id="calendarNextBtn" type="button" aria-label="Mes siguiente"><i class="fa-solid fa-chevron-right"></i></button>
                <h2 id="calendarMonthTitle" aria-live="polite"></h2>
                <button class="calendar-mobile-agenda-btn" id="calendarOpenAgendaBtn" type="button" aria-controls="calendarAgenda" aria-expanded="false">
                    <i class="fa-solid fa-list-check"></i> Ver agenda
                </button>
            </div>
            <div class="calendar-filters" aria-label="Filtrar eventos">
                <button class="calendar-filter active" type="button" data-filter="all">Todos</button>
                <button class="calendar-filter" type="button" data-filter="delivery"><span class="filter-dot delivery"></span>Entregas</button>
                <button class="calendar-filter" type="button" data-filter="meeting"><span class="filter-dot meeting"></span>Reuniones</button>
                <button class="calendar-filter" type="button" data-filter="review"><span class="filter-dot review"></span>Revisiones</button>
                <button class="calendar-filter" type="button" data-filter="deadline"><span class="filter-dot deadline"></span>Fechas limite</button>
            </div>
        </header>
        <div class="calendar-weekdays" aria-hidden="true">
            <span>Lun</span><span>Mar</span><span>Mie</span><span>Jue</span><span>Vie</span><span>Sab</span><span>Dom</span>
        </div>
        <div class="calendar-days-grid" id="calendarDaysGrid"></div>
    </section>

    <div class="calendar-agenda-backdrop" id="calendarAgendaBackdrop" hidden></div>
    <aside class="calendar-agenda" id="calendarAgenda" aria-labelledby="calendarAgendaTitle">
        <header>
            <div><span class="calendar-kicker">Agenda</span><h2 id="calendarAgendaTitle">Eventos del dia</h2></div>
            <div class="calendar-agenda-controls">
                <span class="calendar-agenda-date" id="calendarAgendaDate"></span>
                <button class="calendar-agenda-close" id="calendarAgendaClose" type="button" aria-label="Cerrar agenda"><i class="fa-solid fa-xmark"></i></button>
            </div>
        </header>
        <div class="calendar-agenda-list" id="calendarAgendaList"></div>
        <div class="calendar-legend">
            <strong>Tipos de evento</strong>
            <span><i class="delivery"></i> Entrega</span>
            <span><i class="meeting"></i> Reunion</span>
            <span><i class="review"></i> Revision</span>
            <span><i class="deadline"></i> Fecha limite</span>
        </div>
    </aside>
</div>

<div class="calendar-modal-overlay" id="calendarEventModal" hidden>
    <section class="calendar-modal" role="dialog" aria-modal="true" aria-labelledby="calendarModalTitle">
        <header>
            <div><span class="calendar-kicker">Planificacion</span><h2 id="calendarModalTitle">Nueva tarea</h2></div>
            <button class="calendar-modal-close" id="calendarModalClose" type="button" aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
        </header>
        <form id="calendarEventForm">
            <input id="calendarEventId" type="hidden">
            <label class="calendar-form-field calendar-form-wide">
                <span>Titulo</span>
                <input id="calendarEventTitle" type="text" maxlength="80" placeholder="Ej. Entregar avance del proyecto" required>
            </label>
            <label class="calendar-form-field">
                <span>Fecha</span>
                <input id="calendarEventDate" type="date" required>
            </label>
            <label class="calendar-form-field">
                <span>Hora</span>
                <input id="calendarEventTime" type="time" required>
            </label>
            <label class="calendar-form-field calendar-form-wide">
                <span>Tipo</span>
                <select id="calendarEventType" required>
                    <option value="delivery">Entrega</option>
                    <option value="meeting">Reunion</option>
                    <option value="review">Revision</option>
                    <option value="deadline">Fecha limite</option>
                </select>
            </label>
            <label class="calendar-form-field calendar-form-wide">
                <span>Descripcion</span>
                <textarea id="calendarEventDescription" rows="4" maxlength="240" placeholder="Agrega los detalles necesarios..."></textarea>
            </label>
            <div class="calendar-modal-actions calendar-form-wide">
                <button class="calendar-cancel-btn" id="calendarModalCancel" type="button">Cancelar</button>
                <button class="calendar-save-btn" type="submit"><i class="fa-solid fa-check"></i> Guardar tarea</button>
            </div>
        </form>
    </section>
</div>
