<?php
    $activeType = $trashData['active_type'] ?? 'users';
    $key = $activeType;
    $entity = $key === 'materials' ? 'support_material' : substr($key, 0, -1);
    $summary = $trashData['summary'] ?? ['users' => 0, 'projects' => 0, 'materials' => 0, 'total' => 0];
    $usersCount = (int)($summary['users'] ?? 0);
    $projectsCount = (int)($summary['projects'] ?? 0);
    $materialsCount = (int)($summary['materials'] ?? 0);
    $totalCount = (int)($summary['total'] ?? ($usersCount + $projectsCount + $materialsCount));
    $itemsCount = count($trashData[$key] ?? []);

    $emptyStates = [
        'users' => [
            'icon' => 'fa-user-slash',
            'title' => 'No hay usuarios en la papelera',
            'description' => 'Los usuarios eliminados aparecerán aquí durante el periodo de recuperación.'
        ],
        'projects' => [
            'icon' => 'fa-folder-minus',
            'title' => 'No hay proyectos en la papelera',
            'description' => 'Los proyectos eliminados podrán restaurarse mientras permanezcan dentro del periodo de recuperación.'
        ],
        'materials' => [
            'icon' => 'fa-file-circle-xmark',
            'title' => 'No hay materiales en la papelera',
            'description' => 'Los materiales eliminados aparecerán aquí mientras puedan ser recuperados.'
        ]
    ];
    $currentEmpty = $emptyStates[$key] ?? $emptyStates['users'];
    $trashError = $trashError ?? null;
    $trashCsrf = $trashCsrf ?? '';
    $trashEndpoints = $trashEndpoints ?? ['user' => '', 'restore' => '', 'restoreBatch' => '', 'restoreAll' => '', 'delete' => '', 'deleteBatch' => '', 'emptyCategory' => '', 'purge' => ''];
?>
<header class="at-head">
    <div class="at-head-top">
        <div>
            <span>Administración</span>
            <h1>Papelera</h1>
        </div>
        <div class="at-global-badge">
            <i class="fa-solid fa-box-archive" aria-hidden="true"></i>
            <span>Total en papelera: <strong id="atGlobalTotal"><?= $totalCount ?></strong></span>
        </div>
    </div>
    <p>Los elementos enviados a la papelera podrán restaurarse durante 60 días. Después de ese plazo, se eliminarán automáticamente de forma definitiva.</p>
</header>

<?php if($trashError):?><p class="at-error"><?=e($trashError)?></p><?php endif;?>

<section class="at-stats">
    <article class="<?= $activeType==='users'?'is-active-card':'' ?>">
        <strong><?= $usersCount ?></strong>
        <span>Usuarios</span>
    </article>
    <article class="<?= $activeType==='projects'?'is-active-card':'' ?>">
        <strong><?= $projectsCount ?></strong>
        <span>Proyectos</span>
    </article>
    <article class="<?= $activeType==='materials'?'is-active-card':'' ?>">
        <strong><?= $materialsCount ?></strong>
        <span>Materiales</span>
    </article>
</section>

<div class="at-toolbar">
    <nav class="at-tabs" role="tablist">
        <a class="at-tab-link <?= $activeType==='users'?'active':'' ?>" data-tab-type="users" href="<?= e(route('admin-trash').'&trash_type=users') ?>">
            Usuarios (<span data-count="users"><?= $usersCount ?></span>)
        </a>
        <a class="at-tab-link <?= $activeType==='projects'?'active':'' ?>" data-tab-type="projects" href="<?= e(route('admin-trash').'&trash_type=projects') ?>">
            Proyectos (<span data-count="projects"><?= $projectsCount ?></span>)
        </a>
        <a class="at-tab-link <?= $activeType==='materials'?'active':'' ?>" data-tab-type="materials" href="<?= e(route('admin-trash').'&trash_type=materials') ?>">
            Materiales de apoyo (<span data-count="materials"><?= $materialsCount ?></span>)
        </a>
    </nav>
    <div class="at-category-actions">
        <button type="button" class="at-btn-action" data-category-action="restore-all" <?= empty($trashData[$key]) ? 'disabled' : '' ?>>
            <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
            <span>Restaurar todos</span>
        </button>
        <button type="button" class="at-btn-action is-danger" data-category-action="empty-category" <?= empty($trashData[$key]) ? 'disabled' : '' ?>>
            <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
            <span>Vaciar categoría</span>
        </button>
    </div>
</div>

<section class="at-list active" data-panel="<?= $key ?>">
    <?php if(empty($trashData[$key])): ?>
        <div class="at-empty">
            <i class="fa-solid <?= e($currentEmpty['icon']) ?>" aria-hidden="true"></i>
            <h3><?= e($currentEmpty['title']) ?></h3>
            <p><?= e($currentEmpty['description']) ?></p>
        </div>
    <?php else: ?>
        <div class="at-list-controls" id="atListControls" <?= $itemsCount < 2 ? 'hidden' : '' ?>>
            <label class="at-select-all-label">
                <input type="checkbox" id="atSelectAll" class="at-checkbox">
                <span id="atSelectAllText">Seleccionar todos los visibles</span>
            </label>
            <div class="at-bulk-actions" id="atBulkActions" hidden>
                <button type="button" class="at-btn-bulk" id="atBulkRestore">
                    <i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Restaurar seleccionados
                </button>
                <button type="button" class="at-btn-bulk is-danger" id="atBulkDelete">
                    <i class="fa-solid fa-trash-can" aria-hidden="true"></i> Eliminar definitivamente
                </button>
            </div>
        </div>
        <div class="at-items-container">
            <?php foreach($trashData[$key] as $item): ?>
                <article class="at-item-card" data-item-id="<?= (int)$item['id'] ?>">
                    <div class="at-item-check">
                        <input type="checkbox" class="at-item-checkbox at-checkbox" value="<?= (int)$item['id'] ?>" data-entity="<?= e($entity) ?>" aria-label="Seleccionar elemento">
                    </div>
                    <div class="at-item-info">
                        <strong><?= e($item['full_name'] ?? $item['title']) ?></strong>
                        <small><?= e($item['email'] ?? $item['code']) ?></small>
                    </div>
                    <div class="at-item-meta">
                        <span>Eliminado por <?= e($item['deleted_by_name'] ?: 'Sistema') ?></span>
                        <small><?= e($item['deletion_reason'] ?: 'Sin motivo registrado') ?></small>
                    </div>
                    <div class="at-item-actions">
                        <button type="button" class="at-btn-restore" data-restore data-entity="<?= e($entity) ?>" data-id="<?= (int)$item['id'] ?>">
                            <i class="fa-solid fa-rotate-left" aria-hidden="true"></i> <span>Restaurar</span>
                        </button>
                        <button type="button" class="at-btn-delete-single" data-delete-single data-entity="<?= e($entity) ?>" data-id="<?= (int)$item['id'] ?>" data-title="<?= e($item['full_name'] ?? $item['title']) ?>" aria-label="Eliminar definitivamente">
                            <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                        </button>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- Modal de Confirmación Reutilizable -->
<div id="atModalOverlay" class="at-modal-overlay" hidden>
    <div class="at-modal-card" role="dialog" aria-modal="true" aria-labelledby="atModalTitle">
        <div class="at-modal-icon" id="atModalIcon"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></div>
        <h3 id="atModalTitle">Confirmar acción</h3>
        <p id="atModalMessage">¿Está seguro de realizar esta acción?</p>
        <p id="atModalWarning" class="at-modal-warning"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> <span>Esta acción no se puede deshacer.</span></p>
        <div class="at-modal-actions">
            <button type="button" class="at-btn-modal-cancel" id="atModalCancel">Cancelar</button>
            <button type="button" class="at-btn-modal-confirm" id="atModalConfirm">Confirmar</button>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="atToastContainer" class="at-toast-container"></div>

<div id="atConfig" data-user="<?=e($trashEndpoints['user'])?>" data-restore="<?=e($trashEndpoints['restore'])?>" data-restore-batch="<?=e($trashEndpoints['restoreBatch'])?>" data-restore-all="<?=e($trashEndpoints['restoreAll'])?>" data-delete="<?=e($trashEndpoints['delete'])?>" data-delete-batch="<?=e($trashEndpoints['deleteBatch'])?>" data-empty-category="<?=e($trashEndpoints['emptyCategory'])?>" data-purge="<?=e($trashEndpoints['purge'])?>" data-csrf="<?=e($trashCsrf)?>" data-users-retention="<?= (int) (new SystemSettingModel())->retentionDays('retention_users_days') ?>" data-projects-retention="<?= (int) (new SystemSettingModel())->retentionDays('retention_projects_days') ?>"></div>
