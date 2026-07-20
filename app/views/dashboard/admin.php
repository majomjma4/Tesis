<?php
$alerts=$dashboard['alerts'];
$attentionTotal=array_sum(array_map(static fn(array $alert):int=>(int)($alert['count']??0),$alerts));
$updatedAt='hoy, '.date('H:i',strtotime((string)$dashboard['updated_at']));
?>
<header class="admin-dashboard-heading">
    <div><span>Administración</span><h1>Panorama institucional</h1><p>Estado general y pendientes que requieren tu atención.</p></div>
    <p class="dashboard-updated"><i class="fa-solid fa-rotate" aria-hidden="true"></i> Actualizado <?=$updatedAt?></p>
</header>

<?php if($dashboardError):?><div class="admin-dashboard-error" role="alert"><i class="fa-solid fa-triangle-exclamation"></i><span><?=e($dashboardError)?></span></div><?php endif;?>

<section class="admin-metrics" aria-label="Resumen institucional">
    <a href="<?=e(route('projects'))?>"><span class="metric-icon projects"><i class="fa-solid fa-folder-open"></i></span><div><small>Proyectos en curso</small><strong><?= (int)$dashboard['projects']['active']?></strong><p>Desarrollo, revisión o cambios</p></div><i class="fa-solid fa-arrow-right metric-arrow"></i></a>
    <a href="<?=e(route('admin-users').'&status=active')?>"><span class="metric-icon users"><i class="fa-solid fa-users"></i></span><div><small>Usuarios activos</small><strong><?= (int)$dashboard['users']['active']?></strong><p><?= (int)$dashboard['users']['total']?> cuentas registradas</p></div><i class="fa-solid fa-arrow-right metric-arrow"></i></a>
    <a href="#dashboardAttention" class="<?=$attentionTotal?'needs-attention':'is-clear'?>"><span class="metric-icon attention"><i class="fa-solid <?=$attentionTotal?'fa-triangle-exclamation':'fa-circle-check'?>"></i></span><div><small>Situaciones pendientes</small><strong><?=$attentionTotal?></strong><p><?=$attentionTotal?'Requieren atención administrativa':'Todo está en orden'?></p></div><i class="fa-solid fa-arrow-down metric-arrow"></i></a>
</section>

<div class="admin-dashboard-focus">
    <section class="admin-dashboard-panel important-alerts" id="dashboardAttention">
        <header><div><span>Prioridad</span><h2>Requiere tu atención</h2><p>Situaciones que pueden bloquear o retrasar procesos.</p></div></header>
        <?php if(!$alerts):?>
            <div class="admin-empty compact is-success"><i class="fa-solid fa-circle-check"></i><strong>No hay pendientes críticos</strong><p>La operación institucional se encuentra al día.</p></div>
        <?php else:?>
            <div class="admin-alert-list"><?php foreach($alerts as $alert):?><a class="admin-alert <?=e($alert['tone'])?>" href="<?=e($alert['url'])?>"><i class="fa-solid <?=e($alert['icon'])?>"></i><span><strong><?=e($alert['title'])?></strong><small><?=e($alert['text'])?></small></span><b><?= (int)$alert['count']?></b><i class="fa-solid fa-chevron-right"></i></a><?php endforeach;?></div>
        <?php endif;?>
    </section>

    <section class="admin-dashboard-panel project-overview">
        <header><div><span>Seguimiento</span><h2>Estado de los proyectos</h2><p><?= (int)$dashboard['projects']['total']?> expedientes fuera de la papelera.</p></div><a href="<?=e(route('projects'))?>">Abrir proyectos <i class="fa-solid fa-arrow-right"></i></a></header>
        <?php if(!$dashboard['projects']['total']):?>
            <div class="admin-empty compact"><i class="fa-regular fa-folder-open"></i><strong>Aún no hay proyectos</strong><p>La distribución aparecerá con el primer registro.</p></div>
        <?php else:?>
            <div class="project-status-list"><?php foreach($dashboard['projects']['items'] as $item):$percentage=round($item['count']*100/$dashboard['projects']['total']);?><a class="project-status-row" href="<?=e($item['url'])?>"><div><strong><?=e($item['label'])?></strong><span><?= (int)$item['count']?></span></div><div class="status-track" role="progressbar" aria-label="<?=e($item['label'])?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?=$percentage?>"><span style="width:<?=$percentage?>%"></span></div><small><?=$percentage?>% del total</small></a><?php endforeach;?></div>
        <?php endif;?>
    </section>
</div>

<div class="admin-dashboard-context">
    <section class="admin-dashboard-panel upcoming-dates">
        <header><div><span>Agenda</span><h2>Próximas fechas</h2></div><a href="<?=e(route('calendar'))?>">Abrir calendario</a></header>
        <?php if(!$dashboard['dates']):?><div class="admin-empty compact"><i class="fa-regular fa-calendar"></i><strong>Sin fechas próximas</strong><p>No existen eventos futuros registrados.</p></div><?php else:?><div class="admin-date-list"><?php foreach($dashboard['dates'] as $date):?><article><time><?=e($date['date'])?></time><div><strong><?=e($date['label'])?></strong><small><?=$date['days']===0?'Hoy':'En '.(int)$date['days'].' días'?></small></div></article><?php endforeach;?></div><?php endif;?>
    </section>
    <section class="admin-dashboard-panel recent-admin-activity">
        <header><div><span>Trazabilidad</span><h2>Actividad reciente</h2></div><a href="<?=e(route('admin-reports'))?>">Abrir auditoría</a></header>
        <?php if(!$dashboard['activity']):?><div class="admin-empty compact"><i class="fa-solid fa-clock-rotate-left"></i><strong>Sin actividad registrada</strong><p>Las acciones auditadas aparecerán aquí.</p></div><?php else:?><div class="admin-activity-list"><?php foreach(array_slice($dashboard['activity'],0,4) as $activity):?><a href="<?=e($activity['url'])?>"><span class="activity-dot"></span><div><strong><?=e($activity['action'])?></strong><p><?=e($activity['detail'])?></p><small><?=e($activity['user'])?> · <?=e($activity['date'])?></small></div></a><?php endforeach;?></div><?php endif;?>
    </section>
</div>
