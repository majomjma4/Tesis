<!-- Inicio de skeleton loader -->
<section class="skeleton-loader" id="dashboardSkeleton" aria-label="Cargando informacion del dashboard" hidden>
    <div class="skeleton-status-grid">
        <?php for ($i = 0; $i < 4; $i++): ?>
            <article class="skeleton-card">
                <span class="skeleton-line short"></span>
                <span class="skeleton-line title"></span>
                <span class="skeleton-line"></span>
                <span class="skeleton-pill"></span>
            </article>
        <?php endfor; ?>
    </div>
    <div class="skeleton-dashboard-grid">
        <div class="skeleton-card large">
            <span class="skeleton-line title"></span>
            <span class="skeleton-line"></span>
            <span class="skeleton-line"></span>
            <span class="skeleton-line medium"></span>
        </div>
        <div class="skeleton-card side">
            <span class="skeleton-line title"></span>
            <span class="skeleton-line"></span>
            <span class="skeleton-line"></span>
            <span class="skeleton-line short"></span>
        </div>
    </div>
</section>
<!-- Final de skeleton loader -->

<!-- Inicio de resumen academico -->
<section class="status-section" aria-label="Resumen academico">
    <?php foreach ($summaryCards as $card): ?>
        <article class="status-card <?= e($card['cardClass']) ?>">
            <div class="status-card-header">
                <span class="icon"><i class="fa-solid <?= e($card['icon']) ?>"></i></span>
                <span class="status-label"><?= e($card['label']) ?></span>
            </div>
            <h3><?= e($card['title']) ?></h3>
            <p><?= e($card['description']) ?></p>
            <span class="status-meta"><?= e($card['meta']) ?></span>
        </article>
    <?php endforeach; ?>
</section>
<!-- Final de resumen academico -->

<!-- Inicio de contenido del dashboard -->
<div class="dashboard-container">
    <!-- Inicio de columna principal -->
    <div class="left-column">
        <!-- Inicio de encabezado de proyectos -->
        <div class="section-heading">
            <div>
                <span class="section-eyebrow">Gestion academica</span>
                <h2 class="section-title">Mis proyectos</h2>
            </div>
            <button class="upload-btn" type="button">
                <i class="fa-solid fa-plus"></i>
                Nuevo Proyecto
            </button>
        </div>
        <!-- Final de encabezado de proyectos -->

        <!-- Inicio de listado de proyectos -->
        <section class="projects-grid" aria-label="Listado de proyectos">
            <?php foreach ($projects as $project): ?>
                <article class="project-card">
                    <div class="project-card-top">
                        <span class="project-status <?= e($project['statusClass']) ?>"><?= e($project['status']) ?></span>
                    </div>
                    <h3><?= e($project['title']) ?></h3>
                    <p><?= e($project['description']) ?></p>
                    <div class="project-details">
                        <span>Semestre: <?= e($project['semester']) ?></span>
                        <span>Tutor: <?= e($project['tutor']) ?></span>
                        <span>Ultima actualizacion: <?= e($project['updatedAt']) ?></span>
                    </div>
                    <div class="project-footer">
                        <span class="date"><?= e($project['footer']) ?></span>
                        <button class="open-btn" type="button">Abrir Proyecto</button>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
        <!-- Final de listado de proyectos -->

        <button class="open-btn ghost-btn more-projects-btn" type="button">
            Ver mas
            <i class="fa-solid fa-arrow-right"></i>
        </button>

        <!-- Inicio de calendario academico -->
        <section class="calendar-summary" aria-label="Calendario academico">
            <div class="section-heading calendar-heading">
                <div>
                    <span class="section-eyebrow">Agenda rapida</span>
                    <h2 class="section-title">Calendario academico</h2>
                </div>
                <button class="open-btn ghost-btn calendar-action" type="button">
                    Ver calendario completo
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>

            <div class="calendar-content">
                <div class="calendar-month">
                    <div class="calendar-title">
                        <strong><?= e($calendar['month']) ?></strong>
                        <span><?= e($calendar['subtitle']) ?></span>
                    </div>
                    <div class="calendar-grid">
                        <?php foreach ($calendar['weekDays'] as $dayName): ?>
                            <span class="calendar-day-name"><?= e($dayName) ?></span>
                        <?php endforeach; ?>

                        <?php foreach ($calendar['days'] as $day): ?>
                            <span class="calendar-day <?= e($day['class']) ?>"><?= e($day['number']) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>
        <!-- Final de calendario academico -->
    </div>
    <!-- Final de columna principal -->

    <!-- Inicio de columna complementaria -->
    <aside class="right-column" aria-label="Informacion complementaria">
        <!-- Inicio de notificaciones -->
        <section class="notifications-panel">
            <div class="panel-heading">
                <h2><i class="fa-solid fa-bell"></i> Notificaciones</h2>
                <span><?= count($notifications) ?> nuevas</span>
            </div>

            <?php foreach ($notifications as $notification): ?>
                <article class="notification-card">
                    <strong><?= e($notification['title']) ?></strong>
                    <p><?= e($notification['text']) ?></p>
                    <span><?= e($notification['time']) ?></span>
                </article>
            <?php endforeach; ?>

            <button class="open-btn ghost-btn" type="button">
                Ver mas
                <i class="fa-solid fa-arrow-right"></i>
            </button>
        </section>
        <!-- Final de notificaciones -->

        <!-- Inicio de recordatorios -->
        <section class="reminders-panel">
            <div class="panel-heading">
                <h2><i class="fa-solid fa-thumbtack"></i> Recordatorios</h2>
                <span>Personal</span>
            </div>

            <?php foreach ($reminders as $reminder): ?>
                <article class="reminder-card">
                    <div class="reminder-date"><?= e($reminder['date']) ?></div>
                    <div>
                        <strong><?= e($reminder['title']) ?></strong>
                        <p><?= e($reminder['text']) ?></p>
                    </div>
                </article>
            <?php endforeach; ?>

            <button class="open-btn ghost-btn" type="button">
                Ver mas
                <i class="fa-solid fa-arrow-right"></i>
            </button>
        </section>
        <!-- Final de recordatorios -->
    </aside>
    <!-- Final de columna complementaria -->
</div>
<!-- Final de contenido del dashboard -->
