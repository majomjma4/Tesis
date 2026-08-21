<?php
/** @var array<string,mixed> $project */
$projectId = (int) $project['id'];
$status = (string) ($project['status'] ?? 'development');
$statusLabels = ['development'=>'En desarrollo','under_review'=>'En revisión','approved'=>'Aprobado','defense'=>'En tribunal','tribunal_approved'=>'Aprobado por el tribunal','published'=>'Publicado'];
$statusLabel = $statusLabels[$status] ?? 'No disponible';
$typeCode = (string) ($project['type_code'] ?? '');
$isDegreeProject = $typeCode === 'thesis';
$participants = (array) ($project['participants'] ?? []);

// Extract tutors
$tutorParticipants = array_values(array_filter($participants, static fn(array $p): bool => in_array((string) ($p['role_code'] ?? ''), ['tutor', 'cotutor', 'director', 'codirector'], true) && (string) ($p['status'] ?? 'active') === 'active' && empty($p['removed_at'])));
$tutorNames = array_map(static fn(array $p): string => (string) ($p['full_name'] ?? $p['name'] ?? 'Tutor'), $tutorParticipants);
if ($tutorNames === [] && !empty($project['tutor_name'])) {
    $tutorNames = [(string) $project['tutor_name']];
}
if ($tutorNames === []) {
    $tutorNames = ['No asignado'];
}

// Extract students (integrantes)
$studentParticipants = array_values(array_filter($participants, static fn(array $p): bool => (string) ($p['role_code'] ?? '') === 'student' && (string) ($p['status'] ?? 'active') === 'active' && empty($p['removed_at'])));
$studentNames = array_map(static fn(array $p): string => (string) ($p['full_name'] ?? $p['name'] ?? 'Estudiante'), $studentParticipants);
if ($studentNames === []) {
    $studentNames = ['No asignado'];
}

$tribunalMembers = array_values(array_filter($participants, static fn(array $member): bool => in_array((string) ($member['role_code'] ?? ''), ['tribunal','jury'], true)));
$showTribunalTab = $isDegreeProject && ($tribunalMembers !== [] || $studentDefense !== null || in_array($status, ['defense','tribunal_approved','published'], true));
$activeTab = (string) ($studentActiveTab ?? 'documents');
if ($activeTab === 'tribunal' && !$showTribunalTab) $activeTab = 'documents';

$workflowSequence = ['development', 'under_review', 'approved'];
if ($isDegreeProject || in_array($status, ['defense', 'tribunal_approved'], true)) {
    $workflowSequence[] = 'defense';
    $workflowSequence[] = 'tribunal_approved';
}
$workflowSequence[] = 'published';
$currentIndex = array_search($status, $workflowSequence, true);
if ($currentIndex === false) $currentIndex = 0;

$compactTimeline = [];
$seqCount = count($workflowSequence);
foreach ($workflowSequence as $idx => $code) {
    $compactTimeline[] = [
        'code' => $code,
        'label' => $statusLabels[$code] ?? $code,
        'is_completed' => $idx < $currentIndex,
        'is_current' => $idx === $currentIndex,
        'is_pending' => $idx > $currentIndex,
        'is_last' => $idx === $seqCount - 1,
    ];
}

$detailUrl = route('project-detail') . '&id=' . $projectId;
$tabs = [
    'documents' => ['Documentos', 'fa-folder-open'],
    'history' => ['Historial', 'fa-list-check'],
];
if ($showTribunalTab) $tabs['tribunal'] = ['Tribunal y defensa', 'fa-gavel'];
$formatDate = static function (?string $date, bool $time = false): string { if (!$date) return 'No disponible'; $stamp = strtotime($date); return $stamp === false ? 'No disponible' : date($time ? 'd/m/Y H:i' : 'd/m/Y', $stamp); };
    $backUrl = !empty($isTeacherContext) ? route('assigned-projects') : route('projects');
    $backLabel = !empty($isTeacherContext) ? 'Volver a Proyectos asignados' : 'Volver a Mis proyectos';
    ?>
<div class="student-workspace" data-student-workspace data-project-url="<?= e($detailUrl) ?>" data-sw-project-status="<?= e($status) ?>">
    <div class="sw-top-bar">
        <a class="sw-back-link" href="<?= e($backUrl) ?>">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> <?= e($backLabel) ?>
        </a>
    </div>

    <header class="sw-header-card">
        <div class="sw-header-main">
            <div class="sw-header-left">
                <div class="sw-header-meta">
                    <span class="sw-badge-type"><?= e(mb_strtoupper((string) ($project['type_name'] ?? 'Titulación'), 'UTF-8')) ?></span>
                    <span class="sw-meta-sep">·</span>
                    <span class="sw-code"><?= e((string) ($project['code'] ?? 'No disponible')) ?></span>
                </div>
                <h1 class="sw-title" title="<?= e((string) ($project['title'] ?? 'Sin título')) ?>"><?= e((string) ($project['title'] ?? 'Sin título')) ?></h1>
            </div>
            <div class="sw-header-right">
                <?php if (!empty($projectCapabilities['edit_information'])): ?>
                    <button type="button" class="sw-edit-info-btn" data-sw-edit-info-open aria-label="Editar información del proyecto">
                        <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> <span>Editar información</span>
                    </button>
                <?php endif; ?>
                <span class="sw-badge-status is-<?= e($status) ?>" data-sw-project-status-badge><i class="fa-solid fa-circle-dot" aria-hidden="true"></i><?= e($statusLabel) ?></span>
            </div>
        </div>

        <div class="sw-header-people-grid">
            <div class="sw-person-group sw-tutor-group">
                <span class="sw-person-label"><i class="fa-solid fa-user-tie" aria-hidden="true"></i> <strong><?= count($tutorNames) > 1 ? 'TUTORES' : 'TUTOR' ?></strong></span>
                <div class="sw-person-list">
                    <?php foreach ($tutorNames as $name): ?>
                        <span class="sw-person-name"><?= e($name) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="sw-person-group sw-students-group">
                <span class="sw-person-label"><i class="fa-solid fa-users" aria-hidden="true"></i> <strong>INTEGRANTES</strong></span>
                <div class="sw-person-list">
                    <?php foreach ($studentNames as $name): ?>
                        <span class="sw-person-name"><?= e($name) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="sw-compact-timeline" data-sw-timeline aria-label="Recorrido de estados del proyecto">
            <div class="sw-ct-track" data-sw-timeline-track>
                <?php foreach ($compactTimeline as $step): ?>
                    <div class="sw-ct-step<?= $step['is_current'] ? ' is-current' : ($step['is_completed'] ? ' is-completed' : ' is-pending') ?>" data-sw-timeline-step data-sw-status-step="<?= e($step['code']) ?>">
                        <span class="sw-ct-node">
                            <i class="fa-solid <?= $step['is_current'] ? 'fa-circle-dot' : ($step['is_completed'] ? 'fa-circle-check' : 'fa-circle') ?>" aria-hidden="true"></i>
                            <?= e($step['label']) ?>
                        </span>
                        <?php if (!$step['is_last']): ?>
                            <span class="sw-ct-line" aria-hidden="true"></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="sw-ct-expand-btn" data-sw-timeline-toggle hidden aria-label="Ver Recorrido Completo" title="Ver recorrido completo de estados">
                <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
            </button>
        </div>
    </header>

    <nav class="sw-tabs-nav" aria-label="Secciones de Mi proyecto" role="tablist">
        <?php foreach ($tabs as $key => [$label,$icon]): ?><a class="sw-tab-btn<?= $activeTab === $key ? ' is-active' : '' ?>" href="<?= e($detailUrl . '&tab=' . $key) ?>" role="tab" aria-selected="<?= $activeTab === $key ? 'true' : 'false' ?>" data-sw-tab="<?= e($key) ?>"><i class="fa-solid <?= e($icon) ?>" aria-hidden="true"></i><?= e($label) ?></a><?php endforeach; ?>
    </nav>
    <main>
        <?php foreach (array_keys($tabs) as $key): ?><section class="sw-tab-pane<?= $activeTab === $key ? ' is-active' : '' ?>" id="swTab-<?= e($key) ?>" role="tabpanel"><?php require __DIR__ . '/student-workspace/_' . $key . '.php'; ?></section><?php endforeach; ?>
    </main>
    <?php if (!empty($projectCapabilities['edit_information'])): ?>
        <?php require __DIR__ . '/student-workspace/_edit-information-modal.php'; ?>
    <?php endif; ?>
</div>
