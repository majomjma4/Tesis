<?php
$teacherOwnedContent = is_array($teacherOwnedContent ?? null) ? $teacherOwnedContent : [];
$teacherOwnedCounts = (array) ($teacherOwnedContent['counts'] ?? []);
$teacherManagementSections = [
    ['key' => 'unavailable', 'title' => 'No disponibles', 'icon' => 'fa-eye-slash', 'empty' => 'No tienes contenidos marcados como no disponibles.'],
    ['key' => 'withdrawn', 'title' => 'Retirados', 'icon' => 'fa-box-archive', 'empty' => 'No tienes contenidos retirados del repositorio.'],
    ['key' => 'trash', 'title' => 'Papelera', 'icon' => 'fa-trash-can', 'empty' => 'No tienes contenidos en la Papelera.'],
];
$visibleManagementSections = array_values(array_filter(
    $teacherManagementSections,
    static fn (array $section): bool => (int) ($teacherOwnedCounts[$section['key']] ?? count((array) ($teacherOwnedContent[$section['key']] ?? []))) > 0
));
$firstManagementKey = (string) ($visibleManagementSections[0]['key'] ?? '');
$teacherTrashRetentionValues = [];
foreach ((array) ($teacherOwnedContent['trash'] ?? []) as $trashItem) {
    $retentionDays = (int) ($trashItem['trash_retention_days'] ?? 0);
    if ($retentionDays > 0) $teacherTrashRetentionValues[$retentionDays] = $retentionDays;
}
$teacherTrashRetentionText = count($teacherTrashRetentionValues) === 1
    ? 'despu&eacute;s de ' . (int) reset($teacherTrashRetentionValues) . ' d&iacute;as'
    : 'seg&uacute;n el periodo de retenci&oacute;n configurado';
?>
<section class="ar-panel teacher-owned-content" id="panelManagement" role="tabpanel" aria-labelledby="tabManagement" hidden
    data-teacher-owned-content
    data-csrf="<?= e((string) ($teacherOwnedContentCsrf ?? '')) ?>"
    data-project-save-endpoint="<?= e((string) (($teacherOwnedContentEndpoints ?? [])['project_save'] ?? '')) ?>"
    data-project-status-endpoint="<?= e((string) (($teacherOwnedContentEndpoints ?? [])['project_status'] ?? '')) ?>"
    data-project-trash-endpoint="<?= e((string) (($teacherOwnedContentEndpoints ?? [])['project_trash'] ?? '')) ?>"
    data-project-restore-endpoint="<?= e((string) (($teacherOwnedContentEndpoints ?? [])['project_restore'] ?? '')) ?>"
    data-teacher-trash-endpoint="<?= e((string) (($teacherOwnedContentEndpoints ?? [])['teacher_trash'] ?? '')) ?>"
    data-material-status-endpoint="<?= e((string) (($teacherOwnedContentEndpoints ?? [])['material_status'] ?? '')) ?>">
    <header class="ar-section-head teacher-owned-content__header">
        <div>
            <span>Gesti&oacute;n propia del repositorio</span>
            <h2>Mi gesti&oacute;n</h2>
        </div>
        <p>Contenido propio que requiere una acci&oacute;n de gesti&oacute;n.</p>
    </header>

    <nav class="teacher-owned-content__tabs" role="tablist" aria-label="Estados de mi gesti&oacute;n">
        <?php foreach ($visibleManagementSections as $section):
            $key = (string) $section['key'];
            $count = (int) ($teacherOwnedCounts[$key] ?? count((array) ($teacherOwnedContent[$key] ?? [])));
            $active = $key === $firstManagementKey;
        ?>
            <button type="button" class="teacher-owned-content__tab<?= $active ? ' is-active' : '' ?>" id="teacherOwnedTab-<?= e($key) ?>" role="tab" aria-selected="<?= $active ? 'true' : 'false' ?>" aria-controls="teacherOwnedPanel-<?= e($key) ?>">
                <i class="fa-solid <?= e((string) $section['icon']) ?>" aria-hidden="true"></i>
                <span><?= e((string) $section['title']) ?></span>
                <strong><?= $count ?></strong>
            </button>
        <?php endforeach; ?>
    </nav>

    <?php foreach ($visibleManagementSections as $section):
        $key = (string) $section['key'];
        $items = array_values(array_filter((array) ($teacherOwnedContent[$key] ?? []), 'is_array'));
        $active = $key === $firstManagementKey;
    ?>
        <section class="teacher-owned-content__section" id="teacherOwnedPanel-<?= e($key) ?>" role="tabpanel" aria-labelledby="teacherOwnedTab-<?= e($key) ?>"<?= $active ? '' : ' hidden' ?> data-owned-section="<?= e($key) ?>">
            <?php if ($key === 'trash'): ?>
                <div class="notification-trash-toolbar" data-teacher-trash-tools>
                    <div class="notification-trash-notice">
                        <span><i class="fa-regular fa-clock" aria-hidden="true"></i></span>
                        <div>
                            <strong>La papelera se vac&iacute;a autom&aacute;ticamente <?= $teacherTrashRetentionText ?></strong>
                            <p>Puedes restaurar o eliminar definitivamente el contenido antes de ese plazo.</p>
                        </div>
                    </div>
                    <div class="notification-trash-selection">
                        <label>
                            <input type="checkbox" data-teacher-trash-select-all>
                            <span>Seleccionar todo lo visible</span>
                        </label>
                        <span class="teacher-owned-content__selection-count" data-teacher-trash-selection-count aria-live="polite">0 seleccionados</span>
                    </div>
                    <div class="notification-trash-actions">
                        <button type="button" class="notification-action secondary" data-teacher-trash-bulk-action="restore" disabled>
                            <i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Restaurar seleccionados
                        </button>
                        <button type="button" class="notification-action danger" data-teacher-trash-bulk-action="purge" disabled>
                            <i class="fa-solid fa-trash-can" aria-hidden="true"></i> Eliminar seleccionados
                        </button>
                        <button type="button" class="notification-action danger ghost-danger" data-teacher-trash-empty>
                            <i class="fa-solid fa-broom" aria-hidden="true"></i> Vaciar papelera
                        </button>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($items === []): ?>
                <div class="teacher-owned-content__empty"><i class="fa-regular fa-folder-open" aria-hidden="true"></i><p><?= e((string) $section['empty']) ?></p></div>
            <?php else: ?>
                <div class="teacher-owned-content__grid">
                    <?php foreach ($items as $item):
                        $kind = (string) ($item['kind'] ?? '');
                        $originLabel = $kind === 'material' ? 'Material de apoyo' : 'Proyecto académico';
                        $itemId = (int) ($item['id'] ?? 0);
                        $fileCount = (int) ($item['file_count'] ?? 0);
                        $title = (string) ($item['title'] ?? 'Contenido sin t&iacute;tulo');
                        $type = (string) ($item['type'] ?? ($kind === 'project' ? 'Proyecto acad&eacute;mico' : 'Material de apoyo'));
                        $endpointKey = $key === 'trash'
                            ? 'teacher_trash'
                            : ($kind === 'project' ? 'project_status' : 'material_status');
                        $endpoint = (string) (($teacherOwnedContentEndpoints ?? [])[$endpointKey] ?? '');
                        $action = $key === 'unavailable' ? 'availability' : ($key === 'withdrawn' ? 'publication' : 'restore');
                        $trashAtLabel = (string) ($item['trash_at_label'] ?? '');
                        $trashDaysLeft = $item['trash_days_left'] ?? null;
                        $detailUrl = (string) ($item['detail_url'] ?? '');
                        if ($kind === 'project' && in_array($key, ['unavailable', 'withdrawn', 'trash'], true)) {
                            $detailUrl = route('repository-teacher-project-detail') . '&id=' . $itemId;
                        } elseif ($kind === 'material' && in_array($key, ['unavailable', 'withdrawn', 'trash'], true)) {
                            $detailUrl = route('support-material-teacher-detail') . '&id=' . $itemId;
                        }
                    ?>
                        <article class="teacher-owned-content__card" data-owned-kind="<?= e($kind) ?>" data-owned-id="<?= $itemId ?>">
                            <header class="teacher-owned-content__card-head">
                                <div class="teacher-owned-content__card-leading">
                                <?php if ($key === 'trash'): ?>
                                    <label class="teacher-owned-content__select" title="Seleccionar contenido">
                                        <input type="checkbox" data-teacher-trash-select data-kind="<?= e($kind) ?>" data-id="<?= $itemId ?>" aria-label="Seleccionar <?= e($title) ?>">
                                    </label>
                                <?php endif; ?>
                                <span class="teacher-owned-content__kind"><i class="fa-solid <?= $kind === 'project' ? 'fa-diagram-project' : 'fa-book-open' ?>" aria-hidden="true"></i> <?= e($type) ?></span>
                                <span class="teacher-owned-content__origin"><?= e($originLabel) ?></span>
                                </div>
                                <span class="teacher-owned-content__state teacher-owned-content__state--<?= e($key) ?>"><?= e((string) $section['title']) ?></span>
                            </header>
                            <div class="teacher-owned-content__copy">
                                <?php if ((string) ($item['code'] ?? '') !== ''): ?><span class="ar-code"><?= e((string) $item['code']) ?></span><?php endif; ?>
                                <h4 title="<?= e($title) ?>"><?= e($title) ?></h4>
                                <?php if ($key === 'trash'): ?>
                                    <dl class="teacher-owned-content__metadata">
                                        <?php if ((string) ($item['period'] ?? '') !== ''): ?><div><dt>Per&iacute;odo</dt><dd><?= e((string) $item['period']) ?></dd></div><?php endif; ?>
                                        <div><dt>Archivos</dt><dd><?= $fileCount ?> <?= $fileCount === 1 ? 'archivo' : 'archivos' ?></dd></div>
                                    </dl>
                                <?php endif; ?>
                                <?php if ($key === 'trash'): ?>
                                    <div class="teacher-owned-content__trash-meta">
                                        <?php if ($trashAtLabel !== ''): ?><span><small>Enviada a la papelera</small><strong><?= e($trashAtLabel) ?></strong></span><?php endif; ?>
                                        <span><small>Eliminaci&oacute;n autom&aacute;tica</small><strong><?= $trashDaysLeft === null ? 'Retenci&oacute;n activa' : ($trashDaysLeft === 0 ? 'Purga pendiente' : 'En ' . (int) $trashDaysLeft . ' d&iacute;as') ?></strong></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if ($key !== 'trash'): ?>
                                <div class="ar-card-meta teacher-owned-content__metadata-summary">
                                    <?php if ((string) ($item['career'] ?? '') !== ''): ?><span><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i> <?= e((string) $item['career']) ?></span><?php endif; ?>
                                    <?php if ((string) ($item['period'] ?? '') !== ''): ?><span><i class="fa-regular fa-calendar" aria-hidden="true"></i> <?= e((string) $item['period']) ?></span><?php endif; ?>
                                    <span><i class="fa-regular fa-file-lines" aria-hidden="true"></i> <?= $fileCount ?> <?= $fileCount === 1 ? 'archivo' : 'archivos' ?></span>
                                </div>
                            <?php endif; ?>
                            <footer class="teacher-owned-content__actions">
                                <?php if (trim($detailUrl) !== ''): ?>
                                    <a class="ar-primary-action" href="<?= e($detailUrl) ?>">
                                        <i class="fa-regular fa-eye" aria-hidden="true"></i> Ver detalle
                                    </a>
                                <?php endif; ?>
                                <?php if ($action === 'availability'): ?>
                                    <button type="button" class="ar-secondary-action" data-teacher-repository-action="availability" data-teacher-repository-kind="<?= e($kind) ?>" data-teacher-repository-id="<?= $itemId ?>" data-teacher-repository-endpoint="<?= e($endpoint) ?>" data-teacher-repository-available="1"><i class="fa-solid fa-eye" aria-hidden="true"></i> Volver disponible</button>
                                <?php elseif ($action === 'publication'): ?>
                                    <button type="button" class="ar-secondary-action" data-teacher-repository-action="publication" data-teacher-repository-kind="<?= e($kind) ?>" data-teacher-repository-id="<?= $itemId ?>" data-teacher-repository-endpoint="<?= e($endpoint) ?>" data-teacher-repository-status="published"><i class="fa-solid fa-box-open" aria-hidden="true"></i> Reincorporar al repositorio</button>
                                <?php else: ?>
                                    <button type="button" class="ar-primary-action ar-restore-catalog-action" data-teacher-repository-action="restore" data-teacher-repository-kind="<?= e($kind) ?>" data-teacher-repository-id="<?= $itemId ?>" data-teacher-repository-endpoint="<?= e($endpoint) ?>"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Restaurar</button>
                                    <button type="button" class="notification-action danger" data-teacher-repository-action="purge" data-teacher-repository-kind="<?= e($kind) ?>" data-teacher-repository-id="<?= $itemId ?>" data-teacher-repository-endpoint="<?= e($endpoint) ?>"><i class="fa-solid fa-trash-can" aria-hidden="true"></i> Eliminar definitivamente</button>
                                <?php endif; ?>
                            </footer>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>

    <div class="notification-modal-overlay" data-teacher-trash-confirm hidden>
        <section class="notification-detail-modal compact" role="alertdialog" aria-modal="true" aria-labelledby="teacherTrashConfirmTitle" aria-describedby="teacherTrashConfirmMessage">
            <button class="notification-modal-close" type="button" data-teacher-trash-confirm-close aria-label="Cerrar confirmaci&oacute;n"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            <h2 id="teacherTrashConfirmTitle" data-teacher-trash-confirm-title>Confirmar acci&oacute;n</h2>
            <p id="teacherTrashConfirmMessage" data-teacher-trash-confirm-message></p>
            <div class="notification-modal-actions">
                <button class="notification-action secondary" type="button" data-teacher-trash-confirm-cancel>Cancelar</button>
                <button class="notification-action primary" type="button" data-teacher-trash-confirm-submit>Confirmar</button>
            </div>
        </section>
    </div>
</section>
