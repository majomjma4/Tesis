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
    <?php
        $teamCount = count($teamMembers);
        $teamSummary = $teamCount === 1 ? $teamMembers[0]['name'] : $teamCount . ' Integrantes';
        $teamLabel = $teamCount === 1 ? 'Realizado por' : 'Integrantes';
    ?>
    <!-- Inicio de fila superior -->
    <div class="dashboard-top-grid">
        <!-- Inicio de informe actual -->
        <section class="current-report" aria-label="Informe academico actual">
            <div class="report-main">
                <div class="report-heading">
                    <span class="section-eyebrow">Seguimiento academico</span>
                    <span class="project-status <?= e($currentReport['statusClass']) ?>"><?= e($currentReport['status']) ?></span>
                </div>

                <h2><?= e($currentReport['title']) ?></h2>
                <p><?= e($currentReport['description']) ?></p>

                <div class="report-actions">
                    <button class="upload-btn" type="button">
                        <i class="fa-solid fa-upload"></i>
                        Subir nueva version
                    </button>
                    <button class="open-btn" type="button">
                        <i class="fa-solid fa-folder-open"></i>
                        Ver informe completo
                    </button>
                </div>
            </div>

            <div class="report-side">
                <div class="report-version">
                    <span>Documento actual</span>
                    <strong><?= e($currentReport['document']) ?></strong>
                    <small><?= e($currentReport['version']) ?> - Entregado el <?= e($currentReport['lastDelivery']) ?></small>
                </div>

                <div class="report-meta-grid">
                    <div>
                        <span>Semestre</span>
                        <strong><?= e($currentReport['semester']) ?></strong>
                    </div>
                    <div>
                        <span>Tutor</span>
                        <strong><?= e($currentReport['tutor']) ?></strong>
                    </div>
                    <div>
                        <span>Ultima revision</span>
                        <strong><?= e($currentReport['lastReview']) ?></strong>
                    </div>
                    <div>
                        <span>Observaciones</span>
                        <strong><?= e($currentReport['pendingObservations']) ?></strong>
                    </div>
                </div>
            </div>

            <div class="report-extra-row">
                <div class="report-quick-actions" aria-label="Accesos rapidos del informe">
                    <button type="button">
                        <i class="fa-solid fa-download"></i>
                        Descargar
                    </button>
                    <button type="button">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        Historial
                    </button>
                    <button type="button">
                        <i class="fa-solid fa-pen-to-square"></i>
                        Observaciones
                    </button>
                </div>

                <div class="report-team" aria-label="Integrantes del proyecto">
                    <button class="team-toggle <?= $teamCount === 1 ? 'single-member' : 'multiple-members' ?>" id="teamToggle" type="button" aria-label="Ver integrantes del proyecto" aria-expanded="false">
                        <?php if ($teamCount === 1): ?>
                            <span><?= e($teamLabel) ?></span>
                            <strong><?= e($teamSummary) ?></strong>
                        <?php else: ?>
                            <strong><?= e($teamSummary) ?></strong>
                            <span class="team-stack">
                                <?php foreach ($teamMembers as $member): ?>
                                    <span class="team-avatar" title="<?= e($member['name'] . ' - ' . $member['role']) ?>">
                                        <?= e($member['initial']) ?>
                                    </span>
                                <?php endforeach; ?>
                            </span>
                        <?php endif; ?>
                        <i class="fa-solid fa-chevron-down"></i>
                    </button>

                    <div class="team-dropdown" id="teamDropdown">
                        <?php foreach ($teamMembers as $member): ?>
                            <div class="team-member">
                                <span class="team-avatar"><?= e($member['initial']) ?></span>
                                <div>
                                    <strong><?= e($member['name']) ?></strong>
                                    <small><?= e($member['role']) ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </section>
        <!-- Final de informe actual -->

        <!-- Inicio de notificaciones -->
        <aside class="right-column" aria-label="Informacion complementaria">
            <section class="notifications-panel">
                <div class="panel-heading">
                    <h2><i class="fa-solid fa-bell"></i> Alertas clave</h2>
                    <span><?= count($notifications) ?> importantes</span>
                </div>

                <?php foreach ($notifications as $notification): ?>
                    <article class="notification-card">
                        <strong><?= e($notification['title']) ?></strong>
                        <p><?= e($notification['text']) ?></p>
                        <span><?= e($notification['time']) ?></span>
                    </article>
                <?php endforeach; ?>

                <button class="open-btn ghost-btn" type="button">
                    Ver alertas
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </section>
        </aside>
        <!-- Final de notificaciones -->
    </div>
    <!-- Final de fila superior -->

    <!-- Inicio de seguimiento rapido -->
    <div class="dashboard-follow-grid">
        <section class="observations-preview" aria-label="Observaciones recientes">
            <div class="section-heading compact-heading">
                <div>
                    <span class="section-eyebrow">Pendientes por corregir</span>
                    <h2 class="section-title">Observaciones accionables</h2>
                </div>
                <button class="open-btn ghost-btn compact-action" type="button">
                    Revisar todas
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>

            <?php foreach ($observations as $observation): ?>
                <article class="observation-card">
                    <div class="observation-top">
                        <strong><?= e($observation['title']) ?></strong>
                        <span class="observation-status <?= e($observation['statusClass']) ?>"><?= e($observation['status']) ?></span>
                    </div>
                    <p><?= e($observation['text']) ?></p>
                    <small><?= e($observation['date']) ?></small>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="reminders-panel">
            <div class="panel-heading">
                <h2><i class="fa-solid fa-thumbtack"></i> Proximas acciones</h2>
                <span>Plan</span>
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
                Ver plan
                <i class="fa-solid fa-arrow-right"></i>
            </button>
        </section>
    </div>
    <!-- Final de seguimiento rapido -->

    <!-- Inicio de historial resumido completo -->
    <section class="activity-summary" aria-label="Actividad reciente del informe">
        <div class="section-heading compact-heading">
            <div>
                <span class="section-eyebrow">Trazabilidad</span>
                <h2 class="section-title">Ultimos cambios del informe</h2>
            </div>
        </div>

        <div class="activity-list">
            <?php foreach ($recentActivity as $activity): ?>
                <article class="activity-item">
                    <span class="activity-icon"><i class="fa-solid <?= e($activity['icon']) ?>"></i></span>
                    <div>
                        <strong><?= e($activity['title']) ?></strong>
                        <p><?= e($activity['text']) ?></p>
                        <small><?= e($activity['time']) ?></small>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
    <!-- Final de historial resumido completo -->

    <!-- Inicio de fechas del proceso -->
    <section class="process-dates" aria-label="Fechas importantes del proceso">
        <?php foreach ($processDates as $date): ?>
            <article>
                <span><?= e($date['label']) ?></span>
                <strong><?= e($date['value']) ?></strong>
            </article>
        <?php endforeach; ?>
    </section>
    <!-- Final de fechas del proceso -->
</div>
<!-- Final de contenido del dashboard -->
