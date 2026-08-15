<?php
/** @var array<string,mixed> $project */
$projectId = (int) $project['id'];
$status = (string) ($project['status'] ?? 'development');
$statusLabels = ['development'=>'En desarrollo','under_review'=>'En revisión','approved'=>'Aprobado','defense'=>'En tribunal','tribunal_approved'=>'Aprobado por el tribunal','published'=>'Publicado'];
$statusLabel = $statusLabels[$status] ?? 'No disponible';
$typeCode = (string) ($project['type_code'] ?? '');
$isDegreeProject = $typeCode === 'thesis';
$participants = (array) ($project['participants'] ?? []);
$tribunalMembers = array_values(array_filter($participants, static fn(array $member): bool => in_array((string) ($member['role_code'] ?? ''), ['tribunal','jury'], true)));
$showTribunalTab = $isDegreeProject && ($tribunalMembers !== [] || $studentDefense !== null || in_array($status, ['defense','tribunal_approved','published'], true));
$activeTab = (string) ($studentActiveTab ?? 'summary');
if ($activeTab === 'tribunal' && !$showTribunalTab) $activeTab = 'summary';
$reviewSituation = (array) ($project['review_situation'] ?? []);
$situation = null;
if ($status === 'development' && !empty($reviewSituation['has_pending_observations'])) $situation = 'Requiere correcciones';
elseif ($status === 'development' && empty($project['files'])) $situation = 'Preparando primera entrega';
elseif ($status === 'under_review') $situation = 'Esperando revisión del tutor';
elseif ($status === 'approved') $situation = 'Publicación pendiente';
elseif ($status === 'defense') $situation = $tribunalMembers ? 'Tribunal asignado' : 'Tribunal pendiente';
elseif ($status === 'tribunal_approved' && $studentDefense !== null) $situation = !empty($studentDefense['defense_date']) ? 'Defensa programada' : 'Defensa pendiente';
$statusTrail = [];
foreach ((array) ($project['academic_history'] ?? []) as $event) {
    foreach (['previous_state','new_state'] as $stateKey) {
        $code = (string) (($event[$stateKey]['status'] ?? $event[$stateKey]['project_status'] ?? ''));
        if (isset($statusLabels[$code]) && ($statusTrail === [] || end($statusTrail) !== $code)) $statusTrail[] = $code;
    }
}
foreach ((array) ($project['activity'] ?? []) as $event) {
    foreach (['previous_state','new_state'] as $stateKey) {
        $raw = $event[$stateKey] ?? [];
        if (is_string($raw)) $raw = json_decode($raw, true) ?: [];
        $code = (string) ($raw['status'] ?? $raw['project_status'] ?? '');
        if (isset($statusLabels[$code]) && ($statusTrail === [] || end($statusTrail) !== $code)) $statusTrail[] = $code;
    }
}
if ($statusTrail === []) $statusTrail[] = $status;
elseif (end($statusTrail) !== $status) $statusTrail[] = $status;
$horizontalTrail = array_slice($statusTrail, -4);
$detailUrl = route('project-detail') . '&id=' . $projectId;
$tabs = [
    'summary'=>['Resumen','fa-circle-info'], 'documents'=>['Documentos','fa-folder-open'],
    'observations'=>['Observaciones','fa-comments'], 'versions'=>['Versiones','fa-clock-rotate-left'],
    'history'=>['Historial','fa-list-check'],
];
if ($showTribunalTab) $tabs['tribunal'] = ['Tribunal y defensa','fa-gavel'];
$formatDate = static function (?string $date, bool $time = false): string { if (!$date) return 'No disponible'; $stamp = strtotime($date); return $stamp === false ? 'No disponible' : date($time ? 'd/m/Y H:i' : 'd/m/Y', $stamp); };
?>
<div class="student-workspace" data-student-workspace data-project-url="<?= e($detailUrl) ?>">
    <header class="sw-header-card">
        <div class="sw-header-top">
            <div style="min-width:0;">
                <div class="sw-header-meta"><span class="sw-badge-type"><?= e((string) ($project['type_name'] ?? 'No disponible')) ?></span><span class="sw-code"><?= e((string) ($project['code'] ?? 'No disponible')) ?></span></div>
                <h1 class="sw-title" title="<?= e((string) ($project['title'] ?? 'Sin título')) ?>"><?= e((string) ($project['title'] ?? 'Sin título')) ?></h1>
            </div>
            <span class="sw-badge-status is-<?= e($status) ?>"><i class="fa-solid fa-circle-dot" aria-hidden="true"></i><?= e($statusLabel) ?></span>
        </div>
        <div class="sw-header-info-grid">
            <?php if ($situation !== null): ?><div class="sw-info-item"><span class="sw-info-label">Situación</span><span class="sw-info-value"><?= e($situation) ?></span></div><?php endif; ?>
            <div class="sw-info-item"><span class="sw-info-label">Tutor</span><span class="sw-info-value"><?= e((string) ($project['tutor_name'] ?? 'No asignado')) ?></span></div>
            <div class="sw-info-item"><span class="sw-info-label">Periodo académico</span><span class="sw-info-value"><?= e((string) ($project['period_name'] ?? 'No disponible')) ?></span></div>
            <div class="sw-info-item"><span class="sw-info-label">Última actualización</span><span class="sw-info-value"><?= e($formatDate((string) ($project['updated_at'] ?? ''), true)) ?></span></div>
        </div>
        <div class="sw-header-actions"><a class="sw-secondary-link" href="<?= e(route('projects')) ?>"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Volver a Mis proyectos</a><a class="sw-primary-link" href="<?= e($detailUrl . '&tab=documents') ?>"><i class="fa-solid fa-folder-open" aria-hidden="true"></i> Preparar documentos</a></div>
    </header>

    <section class="sw-timeline-card" aria-label="Recorrido de estados del proyecto">
        <div class="sw-timeline-header"><span class="sw-timeline-title"><i class="fa-solid fa-route" aria-hidden="true"></i> Recorrido de estados</span><?php if (count($statusTrail) > count($horizontalTrail)): ?><button type="button" class="sw-toggle-btn" data-sw-toggle-timeline aria-expanded="false">Ver recorrido completo</button><?php endif; ?></div>
        <div class="sw-timeline-horizontal"><?php foreach ($horizontalTrail as $index => $code): ?><div class="sw-timeline-step"><span class="sw-timeline-node<?= $index === count($horizontalTrail)-1 ? ' is-active' : '' ?>"><?= e($statusLabels[$code]) ?><?= $index === count($horizontalTrail)-1 ? ' · Estado actual' : '' ?></span><?php if ($index < count($horizontalTrail)-1): ?><span class="sw-timeline-line" aria-hidden="true"></span><?php endif; ?></div><?php endforeach; ?></div>
        <?php if (count($statusTrail) > count($horizontalTrail)): ?><div class="sw-timeline-vertical" data-sw-full-timeline hidden><?php foreach ($statusTrail as $index => $code): ?><div class="sw-timeline-vnode"><i class="fa-solid <?= $index === count($statusTrail)-1 ? 'fa-circle-dot' : 'fa-circle' ?>" aria-hidden="true"></i><?= e($statusLabels[$code]) ?><?= $index === count($statusTrail)-1 ? ' · Estado actual' : '' ?></div><?php endforeach; ?></div><?php endif; ?>
    </section>

    <nav class="sw-tabs-nav" aria-label="Secciones de Mi proyecto" role="tablist">
        <?php $primaryTabKeys = ['summary','documents','observations']; foreach ($tabs as $key => [$label,$icon]): ?><a class="sw-tab-btn<?= $activeTab === $key ? ' is-active' : '' ?><?= in_array($key, $primaryTabKeys, true) ? '' : ' sw-tab-secondary' ?>" href="<?= e($detailUrl . '&tab=' . $key) ?>" role="tab" aria-selected="<?= $activeTab === $key ? 'true' : 'false' ?>" data-sw-tab="<?= e($key) ?>"><i class="fa-solid <?= e($icon) ?>" aria-hidden="true"></i><?= e($label) ?></a><?php endforeach; ?>
        <details class="sw-tabs-more"><summary><i class="fa-solid fa-ellipsis" aria-hidden="true"></i> Más</summary><div><?php foreach ($tabs as $key => [$label,$icon]): if (in_array($key, $primaryTabKeys, true)) continue; ?><a href="<?= e($detailUrl . '&tab=' . $key) ?>" data-sw-tab="<?= e($key) ?>"><i class="fa-solid <?= e($icon) ?>" aria-hidden="true"></i><?= e($label) ?></a><?php endforeach; ?></div></details>
    </nav>
    <main>
        <?php foreach (array_keys($tabs) as $key): ?><section class="sw-tab-pane<?= $activeTab === $key ? ' is-active' : '' ?>" id="swTab-<?= e($key) ?>" role="tabpanel"><?php require __DIR__ . '/student-workspace/_' . $key . '.php'; ?></section><?php endforeach; ?>
    </main>
</div>
