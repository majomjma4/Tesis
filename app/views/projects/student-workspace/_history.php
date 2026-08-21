<?php
/** @var array<string,mixed> $project */
/** @var array<int,array<string,mixed>> $studentVersions */
/** @var \Closure(?string, bool=): string $formatDate */
$events = (array) ($project['academic_history'] ?? []);
$versions = (array) ($studentVersions ?? []);
$groupedVersions = [];
foreach ($versions as $ver) {
    $fileId = (int) ($ver['file_id'] ?? 0);
    if (!isset($groupedVersions[$fileId])) {
        $groupedVersions[$fileId] = [
            'file_id' => $fileId,
            'name' => (string) ($ver['original_name'] ?? 'Archivo'),
            'items' => [],
        ];
    }
    $groupedVersions[$fileId]['items'][] = $ver;
}
?>
<div class="sw-history-container" style="display:flex; flex-direction:column; gap:1.5rem;">
    <?php if ($groupedVersions !== []): ?>
        <section class="sw-card">
            <header class="sw-section-heading">
                <div>
                    <h2><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Versiones históricas de documentos</h2>
                    <p>Histórico de versiones anteriores reemplazadas para este proyecto, agrupadas por documento.</p>
                </div>
            </header>
            <div class="sw-history-accordion-list">
                <?php foreach ($groupedVersions as $group): ?>
                    <?php
                    $vCount = count($group['items']);
                    $vLabel = $vCount === 1 ? '1 versión anterior' : $vCount . ' versiones anteriores';
                    $fileName = (string) ($group['items'][0]['original_name'] ?? $group['name']);
                    ?>
                    <details class="sw-history-accordion">
                        <summary class="sw-history-accordion-header">
                            <div class="sw-history-accordion-title">
                                <i class="fa-regular fa-file-lines" aria-hidden="true"></i>
                                <strong class="sw-history-file-name"><?= e($fileName) ?></strong>
                            </div>
                            <div class="sw-history-accordion-meta">
                                <span class="sw-badge-type sw-history-count-badge"><?= e($vLabel) ?></span>
                                <i class="fa-solid fa-chevron-down sw-history-chevron" aria-hidden="true"></i>
                            </div>
                        </summary>
                        <div class="sw-history-accordion-body">
                            <?php foreach ($group['items'] as $ver): ?>
                                <article class="sw-record sw-history-subrecord">
                                    <div class="sw-history-subrecord-info">
                                        <div class="sw-history-subrecord-header">
                                            <span class="sw-badge-type sw-history-version-badge">
                                                Versión <?= (int) ($ver['version_number'] ?? 1) ?>
                                            </span>
                                            <small class="sw-history-subrecord-date">
                                                <?= e($formatDate((string) ($ver['replaced_at'] ?? ''), true)) ?>
                                                <?= !empty($ver['responsible']) ? ' · Autor: ' . e((string) $ver['responsible']) : '' ?>
                                            </small>
                                        </div>
                                        <?php if (!empty($ver['replacement_reason'])): ?>
                                            <p class="sw-history-subrecord-reason">
                                                <strong>Motivo:</strong> <?= e((string) $ver['replacement_reason']) ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="sw-history-subrecord-actions">
                                        <a class="sw-secondary-link"
                                            href="<?= e($detailUrl . '&tab=documents&version_id=' . (int) $ver['id']) ?>"
                                            title="Ver esta versión en el visor de documentos">
                                            <i class="fa-solid fa-eye" aria-hidden="true"></i> Ver versión
                                        </a>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="sw-card">
        <header class="sw-section-heading">
            <div>
                <h2><i class="fa-solid fa-list-check" aria-hidden="true"></i> Historial académico</h2>
                <p>Trazabilidad y eventos registrados durante el ciclo de vida del proyecto.</p>
            </div>
        </header>

        <?php if (!$events): ?>
            <p class="sw-empty-state">No hay historial adicional registrado.</p>
        <?php else: ?>
            <div class="sw-history-list">
                <?php foreach ($events as $event): ?>
                    <?php
                    $eventType = (string) ($event['event_type'] ?? $event['type'] ?? '');
                    $eventCategory = match (true) {
                        str_contains($eventType, 'registration') => 'is-registration',
                        str_contains($eventType, 'delivery') => 'is-delivery',
                        str_contains($eventType, 'observation') || str_contains($eventType, 'adjustment') => 'is-observation',
                        str_contains($eventType, 'version') || str_contains($eventType, 'file') => 'is-version',
                        str_contains($eventType, 'published') || str_contains($eventType, 'approved') => 'is-approval',
                        str_contains($eventType, 'tribunal') => 'is-tribunal',
                        default => 'is-info',
                    };
                    $iconClass = match (true) {
                        str_contains($eventType, 'registration') => 'fa-circle-plus',
                        str_contains($eventType, 'delivery') => 'fa-paper-plane',
                        str_contains($eventType, 'observation') => 'fa-comment-dots',
                        str_contains($eventType, 'adjustment') => 'fa-sliders',
                        str_contains($eventType, 'version') || str_contains($eventType, 'file') => 'fa-file-signature',
                        str_contains($eventType, 'published') => 'fa-globe',
                        str_contains($eventType, 'approved') => 'fa-award',
                        str_contains($eventType, 'tribunal') => 'fa-gavel',
                        default => 'fa-circle-info',
                    };
                    $historicalVerId = (int) ($event['version']['id'] ?? $event['metadata']['payload']['version_id'] ?? 0);
                    $actor = !empty($event['actor']) ? (string) $event['actor'] : '';
                    $rawDate = (string) ($event['occurred_at_local'] ?? $event['date'] ?? '');
                    $formattedDate = $rawDate !== '' ? $formatDate($rawDate, true) : '';
                    $fileName = (string) ($event['file']['name'] ?? $event['metadata']['payload']['file_name'] ?? '');
                    ?>
                    <article class="sw-history-event <?= e($eventCategory) ?>">
                        <div class="sw-history-main">
                            <strong class="sw-history-title">
                                <i class="fa-solid <?= e($iconClass) ?>" aria-hidden="true"></i>
                                <?= e((string) ($event['title'] ?? 'Evento académico')) ?>
                            </strong>
                            <?php if (!empty($event['description'])): ?>
                                <p class="sw-history-desc"><?= e((string) $event['description']) ?></p>
                            <?php endif; ?>
                            <?php if ($fileName !== '' && in_array($eventType, ['document_version_uploaded', 'file_version_registered'], true)): ?>
                                <div class="sw-history-file-ref">
                                    <i class="fa-regular fa-file-lines" aria-hidden="true"></i>
                                    <strong><?= e($fileName) ?></strong>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($event['badges']) && is_array($event['badges'])): ?>
                                <div class="sw-history-badges">
                                    <?php foreach ($event['badges'] as $badge): ?>
                                        <span class="sw-protected-label"
                                            style="background:#e2e8f0; color:#334155;"><?= e((string) $badge) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($historicalVerId > 0): ?>
                                <div class="sw-history-action">
                                    <a class="sw-secondary-link"
                                        href="<?= e($detailUrl . '&tab=documents&version_id=' . $historicalVerId) ?>">
                                        <i class="fa-solid fa-eye" aria-hidden="true"></i> Ver versión
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($actor !== '' || $formattedDate !== ''): ?>
                            <div class="sw-history-meta">
                                <?php if ($actor !== ''): ?>
                                    <strong class="sw-history-actor"><?= e($actor) ?></strong>
                                <?php endif; ?>
                                <?php if ($formattedDate !== ''): ?>
                                    <span class="sw-history-date"><?= e($formattedDate) ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php
            $pagination = (array) ($project['academic_history_pagination'] ?? []);
            if (!empty($pagination) && ($pagination['total'] ?? 0) > 0):
                require APP_PATH . '/views/components/pagination.php';
            endif;
            ?>
        <?php endif; ?>
    </section>
</div>