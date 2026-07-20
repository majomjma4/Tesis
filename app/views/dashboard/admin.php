<section class="admin-dashboard-heading">
    <div><span>Administración</span><h1>Panorama institucional</h1><p>Información actualizada directamente desde MariaDB.</p></div>
</section>

<?php if ($dashboardError): ?><div class="admin-dashboard-error" role="alert"><i class="fa-solid fa-triangle-exclamation"></i><span><?= e($dashboardError) ?></span></div><?php endif; ?>

<section class="admin-metrics" aria-label="Indicadores principales">
    <article><span class="metric-icon users"><i class="fa-solid fa-users"></i></span><div><small>Usuarios activos</small><strong><?= (int)$dashboard['users']['active'] ?></strong><p><?= (int)$dashboard['users']['total'] ?> registrados en total</p></div></article>
    <article><span class="metric-icon blocked"><i class="fa-solid fa-user-lock"></i></span><div><small>Usuarios bloqueados</small><strong><?= (int)$dashboard['users']['blocked'] ?></strong><p>Requieren revisión administrativa</p></div></article>
    <article><span class="metric-icon recent"><i class="fa-solid fa-user-plus"></i></span><div><small>Nuevos usuarios</small><strong><?= (int)$dashboard['users']['recent'] ?></strong><p>Registrados en los últimos 30 días</p></div></article>
    <article><span class="metric-icon projects"><i class="fa-solid fa-folder-tree"></i></span><div><small>Proyectos activos</small><strong><?= (int)$dashboard['projects']['total'] ?></strong><p>Sin incluir elementos en papelera</p></div></article>
</section>

<div class="admin-dashboard-primary">
    <section class="admin-dashboard-panel project-overview">
        <header><div><span>Seguimiento</span><h2>Proyectos por estado</h2></div><a href="<?= e(route('projects')) ?>">Gestionar proyectos <i class="fa-solid fa-arrow-right"></i></a></header>
        <?php if (empty($dashboard['projects']['items'])): ?>
            <div class="admin-empty"><i class="fa-regular fa-folder-open"></i><strong>Aún no hay proyectos</strong><p>Los estados aparecerán cuando se registren proyectos.</p></div>
        <?php else: ?>
            <div class="project-status-list">
                <?php foreach ($dashboard['projects']['items'] as $item): $percentage=$dashboard['projects']['total'] ? round($item['count']*100/$dashboard['projects']['total']) : 0; ?>
                    <div class="project-status-row"><div><strong><?= e($item['label']) ?></strong><span><?= (int)$item['count'] ?></span></div><div class="status-track"><span style="width:<?= (int)$percentage ?>%"></span></div><small><?= (int)$percentage ?>% del total</small></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="admin-dashboard-panel important-alerts">
        <header><div><span>Atención</span><h2>Alertas importantes</h2></div></header>
        <?php if (empty($dashboard['alerts'])): ?>
            <div class="admin-empty compact"><i class="fa-solid fa-circle-check"></i><strong>Todo está en orden</strong><p>No hay alertas administrativas pendientes.</p></div>
        <?php else: ?>
            <div class="admin-alert-list"><?php foreach ($dashboard['alerts'] as $alert): ?><a class="admin-alert <?= e($alert['tone']) ?>" href="<?= e($alert['url']) ?>"><i class="fa-solid <?= e($alert['icon']) ?>"></i><span><strong><?= e($alert['title']) ?></strong><small><?= e($alert['text']) ?></small></span><i class="fa-solid fa-chevron-right"></i></a><?php endforeach; ?></div>
        <?php endif; ?>
    </section>
</div>

<div class="admin-dashboard-secondary">
    <section class="admin-dashboard-panel recent-admin-activity">
        <header><div><span>Trazabilidad</span><h2>Actividad administrativa</h2></div></header>
        <?php if (empty($dashboard['activity'])): ?>
            <div class="admin-empty compact"><i class="fa-solid fa-clock-rotate-left"></i><strong>Sin actividad registrada</strong><p>Las acciones auditadas aparecerán aquí.</p></div>
        <?php else: ?>
            <div class="admin-activity-list"><?php foreach ($dashboard['activity'] as $activity): ?><a href="<?= e($activity['url']) ?>"><span class="activity-dot"></span><div><strong><?= e($activity['action']) ?></strong><p><?= e($activity['detail']) ?></p><small><?= e($activity['user']) ?> · <?= e($activity['date']) ?></small></div></a><?php endforeach; ?></div>
        <?php endif; ?>
    </section>

    <section class="admin-dashboard-panel upcoming-dates">
        <header><div><span>Agenda</span><h2>Próximas fechas</h2></div><a href="<?= e(route('calendar')) ?>">Calendario</a></header>
        <?php if (empty($dashboard['dates'])): ?>
            <div class="admin-empty compact"><i class="fa-regular fa-calendar"></i><strong>Sin fechas próximas</strong><p>No hay periodos o eventos futuros registrados.</p></div>
        <?php else: ?>
            <div class="admin-date-list"><?php foreach ($dashboard['dates'] as $date): ?><article><time><?= e($date['date']) ?></time><div><strong><?= e($date['label']) ?></strong><small><?= $date['days'] === 0 ? 'Hoy' : 'En '.(int)$date['days'].' días' ?></small></div></article><?php endforeach; ?></div>
        <?php endif; ?>
    </section>
</div>

<section class="admin-quick-actions" aria-label="Acciones rápidas">
    <div><span>Accesos directos</span><h2>¿Qué necesitas gestionar?</h2></div>
    <nav><a href="<?= e(route('admin-users')) ?>"><i class="fa-solid fa-user-plus"></i><span><strong>Usuarios</strong><small>Consultar y administrar cuentas</small></span></a><a href="<?= e(route('projects')) ?>"><i class="fa-solid fa-folder-plus"></i><span><strong>Proyectos</strong><small>Revisar el catálogo institucional</small></span></a><a href="<?= e(route('admin-academic')) ?>"><i class="fa-solid fa-graduation-cap"></i><span><strong>Académico</strong><small>Periodos y configuración académica</small></span></a></nav>
</section>
