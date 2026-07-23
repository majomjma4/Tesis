<?php
$alerts=$dashboard['alerts'];
$updatedTimestamp=!$dashboardError&&!empty($dashboard['updated_at'])?strtotime((string)$dashboard['updated_at']):false;
$updatedAt=$updatedTimestamp?'hoy, '.date('h:i',$updatedTimestamp).' '.(date('a',$updatedTimestamp)==='am'?'a. m.':'p. m.'):null;
$activeUsers=(int)$dashboard['users']['active'];$totalUsers=(int)$dashboard['users']['total'];$weeklyActivity=(int)$dashboard['weekly_activity'];
?>
<header class="admin-dashboard-heading">
    <div><span>Administración</span><h1>Panorama institucional</h1><p>Estado general y pendientes que requieren tu atención.</p></div>
    <div class="dashboard-update-meta" data-update-ok="<?=$updatedAt?'true':'false'?>">
        <button class="dashboard-refresh" id="dashboardRefresh" type="button" aria-label="Actualizar los datos del Dashboard" title="Actualizar datos"><i class="fa-solid fa-rotate" aria-hidden="true"></i></button>
        <p class="dashboard-updated<?=$updatedAt?'':' is-error'?>" id="dashboardUpdateStatus" role="status" aria-live="polite"><?=$updatedAt?'Datos actualizados: '.e($updatedAt):'No fue posible obtener la última actualización.'?></p>
    </div>
</header>

<div class="dashboard-fresh-toast" id="dashboardFreshToast" role="status" aria-live="polite" hidden><i class="fa-solid fa-circle-check" aria-hidden="true"></i><span>Ya estás al día</span></div>

<?php if($dashboardError):?><div class="admin-dashboard-error" role="alert"><i class="fa-solid fa-triangle-exclamation"></i><span><?=e($dashboardError)?></span></div><?php endif;?>

<section class="admin-metrics" aria-label="Resumen institucional">
    <a href="<?=e(route('projects'))?>" aria-label="Abrir los proyectos en curso"><span class="metric-icon projects"><i class="fa-solid fa-folder-open" aria-hidden="true"></i></span><div><small>Proyectos en curso</small><strong><?= (int)$dashboard['projects']['active']?></strong><p>Desarrollo, revisión y cambios</p></div><i class="fa-solid fa-arrow-right metric-arrow" aria-hidden="true"></i></a>
    <a href="<?=e(route('admin-users').'&status=active')?>" aria-label="Abrir los usuarios activos"><span class="metric-icon users"><i class="fa-solid fa-users" aria-hidden="true"></i></span><div><small>Usuarios activos</small><strong><?=$activeUsers?></strong><p><?=$activeUsers?> de <?=$totalUsers?> <?=$totalUsers===1?'cuenta':'cuentas'?></p></div><i class="fa-solid fa-arrow-right metric-arrow" aria-hidden="true"></i></a>
    <a href="<?=e(route('admin-reports'))?>" aria-label="Abrir la actividad administrativa de esta semana"><span class="metric-icon activity"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i></span><div><small>Actividad esta semana</small><strong><?=$weeklyActivity?></strong><p><?=$weeklyActivity?> <?=$weeklyActivity===1?'acción registrada':'acciones registradas'?></p></div><i class="fa-solid fa-arrow-right metric-arrow" aria-hidden="true"></i></a>
</section>

<div class="admin-dashboard-focus">
    <section class="admin-dashboard-panel important-alerts" id="dashboardAttention" aria-labelledby="dashboardAttentionTitle">
        <header><div><span>Prioridad</span><h2 id="dashboardAttentionTitle">Requiere tu atención</h2><p>Situaciones que pueden bloquear o retrasar procesos.</p></div></header>
        <?php if(!$alerts):?>
            <div class="admin-empty compact is-success"><i class="fa-solid fa-circle-check"></i><strong>No hay pendientes críticos</strong><p>La operación institucional se encuentra al día.</p></div>
        <?php else:?>
            <div class="admin-alert-list"><?php foreach($alerts as $alert):$alertCount=(int)$alert['count'];?><a class="admin-alert <?=e($alert['tone'])?>" href="<?=e($alert['url'])?>" aria-label="<?=e($alert['title'].': '.$alert['text'])?>"><i class="fa-solid <?=e($alert['icon'])?>" aria-hidden="true"></i><span><strong><?=e($alert['title'])?></strong><small><?=e($alert['text'])?></small></span><b aria-label="<?=$alertCount?> <?=$alertCount===1?'incidencia':'incidencias'?>"><?=$alertCount?></b><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a><?php endforeach;?></div>
        <?php endif;?>
    </section>

    <section class="admin-dashboard-panel project-overview">
        <header><div><span>Seguimiento</span><h2>Estado de los proyectos</h2><p><?php $projectTotal=(int)$dashboard['projects']['total'];?><?=$projectTotal?> <?=$projectTotal===1?'expediente':'expedientes'?> fuera de la papelera.</p></div><a href="<?=e(route('projects'))?>">Abrir proyectos <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a></header>
        <?php if(!$dashboard['projects']['total']):?>
            <div class="admin-empty compact"><i class="fa-regular fa-folder-open"></i><strong>Aún no hay proyectos</strong><p>La distribución aparecerá con el primer registro.</p></div>
        <?php else:?>
            <div class="project-status-list"><?php foreach($dashboard['projects']['items'] as $item):$count=(int)$item['count'];$percentage=round($count*100/$dashboard['projects']['total']);?><a class="project-status-row" data-project-status="<?=e($item['status'])?>" href="<?=e($item['url'])?>" aria-label="Ver <?=e($item['label'])?>: <?=$count?> de <?=$projectTotal?> proyectos"><div><strong><?=e($item['label'])?></strong><span><?=$count?></span></div><div class="status-track" role="progressbar" aria-label="Proporción de <?=e($item['label'])?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?=$percentage?>" aria-valuetext="<?=$percentage?>%, <?=$count?> de <?=$projectTotal?> proyectos"><span class="<?=$count?'has-value':''?>" style="width:<?=$percentage?>%"></span></div><small><?=$percentage?>% del total</small></a><?php endforeach;?></div>
        <?php endif;?>
    </section>
</div>

<div class="admin-dashboard-context">
    <section class="admin-dashboard-panel upcoming-dates">
        <header><div><span>Agenda</span><h2>Próximas fechas</h2></div><a href="<?=e(route('calendar'))?>">Abrir calendario</a></header>
        <?php if(!$dashboard['dates']):?><div class="admin-empty compact"><i class="fa-regular fa-calendar"></i><strong>Sin fechas próximas</strong><p>No existen eventos futuros registrados.</p></div><?php else:?><div class="admin-date-list"><?php foreach($dashboard['dates'] as $date):$days=(int)$date['days'];?><article><time><?=e($date['date'])?></time><div><strong><?=e($date['label'])?></strong><small><?=$days===0?'Hoy':'En '.$days.' '.($days===1?'día':'días')?></small></div></article><?php endforeach;?></div><?php endif;?>
    </section>
    <section class="admin-dashboard-panel recent-admin-activity">
        <header><div><span>Trazabilidad</span><h2>Actividad reciente</h2></div><a href="<?=e(route('admin-reports'))?>">Abrir auditoría</a></header>
        <?php if(!$dashboard['activity']):?><div class="admin-empty compact"><i class="fa-solid fa-clock-rotate-left"></i><strong>Sin actividad registrada</strong><p>Las acciones auditadas aparecerán aquí.</p></div><?php else:?><div class="admin-activity-list"><?php foreach(array_slice($dashboard['activity'],0,4) as $activity):?><a href="<?=e($activity['url'])?>"><span class="activity-dot"></span><div><strong><?=e($activity['action'])?></strong><p><?=e($activity['detail'])?></p><small><?=e($activity['user'])?> · <?=e($activity['date'])?></small></div></a><?php endforeach;?></div><?php endif;?>
    </section>
</div>
