<?php
/** @var array<string,mixed> $project */
/** @var array<string,bool> $projectCapabilities */
/** @var array<string,mixed> $studentProjectEditorCatalogs */
/** @var string $studentProjectSaveEndpoint */
/** @var string $studentProjectEditCsrf */
/** @var int $currentUserId */

$projectId = (int) $project['id'];
$participants = (array) ($project['participants'] ?? []);

$tutors = array_values(array_filter($participants, static fn(array $p): bool =>
    in_array(strtolower((string)($p['role_code'] ?? '')), ['tutor', 'cotutor', 'co_tutor', 'co-tutor'], true)
    && (string)($p['status'] ?? 'active') === 'active' && empty($p['removed_at'])
));
$authors = array_values(array_filter($participants, static fn(array $p): bool =>
    strtolower((string)($p['role_code'] ?? '')) === 'student'
    && (string)($p['status'] ?? 'active') === 'active' && empty($p['removed_at'])
));

$primaryTutorId = (int) ($project['tutor_id'] ?? 0);
if ($primaryTutorId === 0 && !empty($tutors)) {
    $primaryTutorId = (int) $tutors[0]['user_id'];
}

$leaderAuthorId = 0;
foreach ($authors as $author) {
    if (!empty($author['is_leader'])) {
        $leaderAuthorId = (int) $author['user_id'];
        break;
    }
}
if ($leaderAuthorId === 0 && !empty($authors)) {
    $leaderAuthorId = (int) ($authors[0]['user_id'] ?? 0);
}

$initialDataPayload = [
    'id' => $projectId,
    'title' => (string) ($project['title'] ?? ''),
    'summary' => (string) ($project['summary'] ?? ''),
    'tutor_id' => $primaryTutorId,
    'tutoring_primary_id' => $primaryTutorId,
    'tutoring_user_ids' => array_values(array_map(static fn($t) => (int)$t['user_id'], $tutors)),
    'author_leader_id' => $leaderAuthorId,
    'author_user_ids' => array_values(array_map(static fn($a) => (int)$a['user_id'], $authors)),
    'tutors' => array_map(static fn($t) => [
        'user_id' => (int)$t['user_id'],
        'full_name' => (string)($t['full_name'] ?? $t['name'] ?? ''),
        'username' => (string)($t['username'] ?? ''),
        'email' => (string)($t['email'] ?? ''),
    ], $tutors),
    'authors' => array_map(static fn($a) => [
        'user_id' => (int)$a['user_id'],
        'full_name' => (string)($a['full_name'] ?? $a['name'] ?? ''),
        'username' => (string)($a['username'] ?? ''),
        'institutional_code' => (string)($a['institutional_code'] ?? ''),
        'is_leader' => (int)$a['user_id'] === $leaderAuthorId,
    ], $authors),
];
?>

<!-- Modal de Edición de Información del Estudiante -->
<div class="sw-edit-project-overlay" id="swEditProjectModal" hidden>
    <div class="sw-edit-project-dialog" role="dialog" aria-modal="true" aria-labelledby="swEditProjectTitle">
        <header class="sw-edit-project-header">
            <div class="sw-edit-project-header-title">
                <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                <h2 id="swEditProjectTitle">Editar información del proyecto</h2>
            </div>
            <button type="button" class="sw-edit-project-close-btn" data-sw-edit-close aria-label="Cerrar ventana">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </header>

        <form id="swEditProjectForm" autocomplete="off" novalidate>
            <input type="hidden" name="_csrf" value="<?= e($studentProjectEditCsrf) ?>">
            <input type="hidden" name="project_id" value="<?= $projectId ?>">

            <div class="sw-edit-project-body">
                <!-- Alerta de estado -->
                <div class="sw-edit-project-alert" id="swEditProjectAlert" role="alert" hidden></div>

                <!-- 1. Título del Proyecto -->
                <section class="sw-edit-project-section">
                    <label for="swEditProjectTitleInput" class="sw-edit-project-label">
                        <i class="fa-regular fa-id-card" aria-hidden="true"></i>
                        <span>Título del proyecto <strong class="sw-required">*</strong></span>
                    </label>
                    <input type="text" id="swEditProjectTitleInput" name="title" class="sw-edit-project-input" required minlength="5" maxlength="240" placeholder="Ingresa el título del proyecto" value="<?= e((string)($project['title'] ?? '')) ?>">
                    <small class="sw-edit-project-hint">Mínimo 5 caracteres, máximo 240.</small>
                </section>

                <!-- 2. Descripción / Resumen -->
                <section class="sw-edit-project-section">
                    <label for="swEditProjectSummaryInput" class="sw-edit-project-label">
                        <i class="fa-solid fa-align-left" aria-hidden="true"></i>
                        <span>Descripción o resumen <strong class="sw-required">*</strong></span>
                    </label>
                    <textarea id="swEditProjectSummaryInput" name="summary" class="sw-edit-project-textarea" rows="4" minlength="30" required placeholder="Describe brevemente el alcance y propósito de tu proyecto de titulación..."><?= e((string)($project['summary'] ?? '')) ?></textarea>
                    <small class="sw-edit-project-hint">Mínimo 30 caracteres.</small>
                </section>

                <!-- 3. Tutoría / Docentes Responsables -->
                <section class="sw-edit-project-section">
                    <div class="sw-edit-project-section-head">
                        <label class="sw-edit-project-label">
                            <i class="fa-solid fa-user-tie" aria-hidden="true"></i>
                            <span>Tutoría del proyecto <strong class="sw-required">*</strong></span>
                        </label>
                        <button type="button" class="sw-edit-project-add-btn" data-sw-tutor-add-trigger>
                            <i class="fa-solid fa-plus" aria-hidden="true"></i> Añadir tutor
                        </button>
                    </div>

                    <div class="sw-edit-project-picker-panel" data-sw-tutor-picker hidden>
                        <div class="sw-edit-project-picker-bar">
                            <div class="sw-edit-project-search-box">
                                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                                <input type="search" data-sw-tutor-search placeholder="Buscar docente por nombre o correo..." autocomplete="off">
                            </div>
                            <button type="button" class="sw-edit-project-picker-hide-btn" data-sw-tutor-picker-hide aria-label="Ocultar opciones" title="Ocultar opciones">
                                <i class="fa-solid fa-chevron-up" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="sw-edit-project-picker-list" data-sw-tutor-picker-options role="listbox"></div>
                    </div>

                    <div class="sw-edit-project-selected-list" data-sw-tutors-list></div>
                    <small class="sw-edit-project-hint">Debes conservar al menos un tutor de referencia.</small>
                </section>

                <!-- 4. Integrantes / Autores -->
                <section class="sw-edit-project-section">
                    <div class="sw-edit-project-section-head">
                        <label class="sw-edit-project-label">
                            <i class="fa-solid fa-users" aria-hidden="true"></i>
                            <span>Integrantes del proyecto <strong class="sw-required">*</strong></span>
                        </label>
                        <button type="button" class="sw-edit-project-add-btn" data-sw-author-add-trigger>
                            <i class="fa-solid fa-plus" aria-hidden="true"></i> Añadir integrante
                        </button>
                    </div>

                    <div class="sw-edit-project-picker-panel" data-sw-author-picker hidden>
                        <div class="sw-edit-project-picker-bar">
                            <div class="sw-edit-project-search-box">
                                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                                <input type="search" data-sw-author-search placeholder="Buscar estudiante por nombre o cédula/código..." autocomplete="off">
                            </div>
                            <button type="button" class="sw-edit-project-picker-hide-btn" data-sw-author-picker-hide aria-label="Ocultar opciones" title="Ocultar opciones">
                                <i class="fa-solid fa-chevron-up" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="sw-edit-project-picker-list" data-sw-author-picker-options role="listbox"></div>
                    </div>

                    <div class="sw-edit-project-selected-list" data-sw-authors-list></div>
                    <small class="sw-edit-project-hint">Selecciona exactamente un integrante líder del proyecto. Tú no puedes retirarte del proyecto.</small>
                </section>
            </div>

            <footer class="sw-edit-project-footer">
                <div class="sw-edit-project-footer-left">
                    <span class="sw-edit-project-dirty-indicator" data-sw-dirty-indicator hidden>
                        <i class="fa-solid fa-pen" aria-hidden="true"></i> Hay cambios pendientes
                    </span>
                </div>
                <div class="sw-edit-project-footer-actions">
                    <button type="button" class="sw-edit-project-cancel-btn" data-sw-edit-close>Cancelar</button>
                    <button type="submit" class="sw-edit-project-submit-btn" data-sw-edit-submit disabled>
                        <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                        <span>Guardar cambios</span>
                    </button>
                </div>
            </footer>
        </form>
    </div>
</div>

<!-- Modal de Confirmación de Cambios Sin Guardar -->
<div class="sw-edit-project-overlay sw-confirm-overlay" id="swUnsavedChangesConfirm" hidden>
    <div class="sw-confirm-dialog" role="alertdialog" aria-modal="true" aria-labelledby="swConfirmTitle" aria-describedby="swConfirmMessage">
        <div class="sw-confirm-icon warning">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
        </div>
        <h3 id="swConfirmTitle">¿Descartar cambios sin guardar?</h3>
        <p id="swConfirmMessage">Has realizado modificaciones en la información del proyecto. Si continúas, los cambios no guardados se perderán.</p>
        <div class="sw-confirm-actions">
            <button type="button" class="sw-confirm-btn secondary" data-sw-confirm-keep>Seguir editando</button>
            <button type="button" class="sw-confirm-btn danger" data-sw-confirm-discard>Descartar cambios</button>
        </div>
    </div>
</div>

<script type="application/json" id="swStudentTeachersCatalog"><?= json_encode(array_values((array) ($studentProjectEditorCatalogs['teachers'] ?? [])), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
<script type="application/json" id="swStudentAuthorsCatalog"><?= json_encode(array_values((array) ($studentProjectEditorCatalogs['students'] ?? [])), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
<script type="application/json" id="swStudentProjectInitialPayload"><?= json_encode($initialDataPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
<div id="swStudentProjectEditorConfig"
    data-save="<?= e($studentProjectSaveEndpoint) ?>"
    data-csrf="<?= e($studentProjectEditCsrf) ?>"
    data-current-user-id="<?= (int) ($currentUserId ?? 0) ?>"
    data-project-id="<?= $projectId ?>">
</div>
