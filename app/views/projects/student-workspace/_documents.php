<?php
$files = (array) ($project['files'] ?? []);
$observations = (array) ($project['observations'] ?? []);
$historical = is_array($historicalVersion ?? null) ? $historicalVersion : null;
$canManageFiles = !$historical && !empty($projectCapabilities['manage_workspace_files']);
$canSendForReview = !$historical && !empty($projectCapabilities['send_for_review']);
$canReviewDocuments = !$historical && !empty($projectCapabilities['review_documents']);
$reviewCategories = ['General', 'Contenido', 'Formato', 'Redacción', 'Referencias'];
$reviewFiles = array_map(static fn(array $file): array => [
    'file_id' => (int) $file['id'],
    'expected_checksum' => strtolower((string) ($file['checksum_sha256'] ?? '')),
    'status' => (string) ($file['document_status'] ?? 'development'),
    'name' => (string) ($file['original_name'] ?? 'Documento'),
], $files);
$documentEndpoint = (string) ($studentDocumentEndpoint ?? '');
$documentCsrf = (string) ($studentDocumentCsrf ?? '');
$historicalPreviewUrl = $historical ? route('project-file-version-preview').'&project_id='.$projectId.'&version_id='.(int)$historical['id'] : '';
$docLimits = (new ProjectDocumentFileService())->limits();
?>
<script type="application/json" data-sw-observations-json><?= json_encode($observations, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?></script>
<?php if ($canReviewDocuments): ?>
<script type="application/json" data-sw-review-config-json><?= json_encode([
    'project_id' => $projectId,
    'teacher_id' => (int) (new AuthSessionService())->userId(),
    'expected_project_status' => $status,
    'context' => 'academic',
    'endpoint' => route('project-document-review-save'),
    'csrf' => (new AuthSessionService())->csrfToken('project_document_review'),
    'files' => $reviewFiles,
    'categories' => $reviewCategories,
    'limits' => ['body_min' => 5, 'body_max' => 2000, 'category_max' => 60, 'location_max' => 180],
], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?></script>
<?php endif; ?>
<section class="sw-doc-workspace<?= $historical ? ' is-historical' : '' ?><?= $canReviewDocuments ? ' is-teacher-review' : '' ?>" data-sw-document-manager data-sw-active-tab="explorer"<?= $canReviewDocuments ? ' data-sw-review-mode="draft"' : '' ?> data-endpoint="<?= e($documentEndpoint) ?>" data-csrf="<?= e($documentCsrf) ?>" data-submit-endpoint="<?= e((string) ($studentProjectSubmitEndpoint ?? '')) ?>" data-submit-csrf="<?= e((string) ($studentProjectSubmitCsrf ?? '')) ?>" data-max-file-bytes="<?= (int) $docLimits['max_file_bytes'] ?>" data-max-file-mb="<?= (int) $docLimits['max_file_mb'] ?>" data-review-representation-endpoint="<?= e(route('student-project-review-representation')) ?>" data-review-representation-csrf="<?= e((new AuthSessionService())->csrfToken('student_project_review_representation')) ?>" data-project-id="<?= (int) $projectId ?>" data-historical-preview="<?= e($historicalPreviewUrl) ?>" data-pdfjs-url="<?= e(asset('vendor/pdfjs/4.10.38/build/pdf.mjs')) ?>" data-pdfjs-worker="<?= e(asset('vendor/pdfjs/4.10.38/build/pdf.worker.mjs')) ?>" data-pdfjs-fonts="<?= e(asset('vendor/pdfjs/4.10.38/web/standard_fonts/')) ?>">
    <?php if ($historical): ?><div class="sw-historical-banner" role="status"><strong>Versión <?= (int)$historical['version_number'] ?> · Historial</strong><span>Estás consultando una versión anterior de este documento.</span><a href="<?= e($detailUrl.'&tab=documents') ?>">Volver a versión actual</a></div><?php endif; ?>
    <nav class="sw-mobile-switcher" data-sw-mobile-switcher aria-label="Navegación móvil del espacio de trabajo">
        <button type="button" class="sw-mobile-tab is-active" data-sw-mobile-tab="explorer">
            <i class="fa-solid fa-folder-open" aria-hidden="true"></i> <span>Archivos</span>
        </button>
        <button type="button" class="sw-mobile-tab" data-sw-mobile-tab="viewer">
            <i class="fa-solid fa-file-lines" aria-hidden="true"></i> <span>Documento</span>
        </button>
        <button type="button" class="sw-mobile-tab" data-sw-mobile-tab="observations">
            <i class="fa-solid fa-comments" aria-hidden="true"></i> <span>Observaciones</span>
            <span class="sw-mobile-badge" data-sw-mobile-obs-badge hidden>0</span>
        </button>
    </nav>
    <aside class="sw-explorer-panel" data-sw-explorer id="swExplorerPanel">
        <button type="button" class="sw-panel-reopen-btn" data-sw-open-explorer hidden aria-label="Abrir panel de archivos" title="Abrir panel de archivos"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
        <header class="sw-explorer-header"><span class="sw-explorer-title"><i class="fa-solid fa-folder-open" aria-hidden="true"></i> Archivos</span><button type="button" class="sw-panel-toggle" data-sw-toggle-explorer aria-controls="swExplorerPanel" aria-label="Contraer panel de archivos" aria-expanded="true"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button></header>
        <?php if ($canManageFiles): ?><button type="button" class="sw-add-files" data-sw-add-files><i class="fa-solid fa-plus" aria-hidden="true"></i> Agregar archivos</button><input type="file" data-sw-file-input hidden multiple><?php endif; ?>
        <?php if (!$files): ?><p class="sw-empty-state">Todavía no hay archivos en este proyecto.</p><?php else: ?><ul class="sw-tree-list"><?php foreach ($files as $file):
            $fileId=(int)$file['id']; $observationCount=count(array_filter($observations, static fn(array $obs): bool => (int)($obs['file_id'] ?? 0) === $fileId));
            $extension=strtolower((string)$file['extension']); $documentStatus=(string)($file['document_status'] ?? 'development');
            $statusLabel=(string)($file['document_status_label'] ?? 'En desarrollo'); $protected=$documentStatus !== 'development';
            $previewVersion=!empty($file['checksum_sha256']) ? '&v='.rawurlencode(substr((string)$file['checksum_sha256'],0,16)) : '';
            $previewUrl=route('project-file-preview').'&project_id='.$projectId.'&file_id='.$fileId.$previewVersion;
            $downloadUrl=route('project-file-download').'&project_id='.$projectId.'&file_id='.$fileId;
            $zipUrl=$extension==='zip' ? route('project-zip-list').'&context=academic&project_id='.$projectId.'&file_id='.$fileId.$previewVersion : '';
            $zipPreviewUrl=$extension==='zip' ? route('project-zip-entry-preview').'&context=academic&project_id='.$projectId.'&file_id='.$fileId : '';
            $zipDownloadUrl=$extension==='zip' ? route('project-zip-entry-download').'&context=academic&project_id='.$projectId.'&file_id='.$fileId : '';
        ?><li class="sw-archive-node"><div class="sw-file-row"><button type="button" class="sw-tree-item" aria-label="<?= e((string)$file['original_name']) ?>. Estado: <?= e($statusLabel) ?>" data-sw-file data-file-id="<?= $fileId ?>" data-file-name="<?= e((string)$file['original_name']) ?>" data-file-extension="<?= e(strtoupper($extension)) ?>" data-file-size="<?= e(ArchiveService::formatBytes((int)($file['size_bytes'] ?? 0))) ?>" data-file-preview="<?= e($previewUrl) ?>" data-file-download="<?= e($downloadUrl) ?>" data-file-zip-url="<?= e($zipUrl) ?>" data-file-zip-preview-url="<?= e($zipPreviewUrl) ?>" data-file-zip-download-url="<?= e($zipDownloadUrl) ?>" data-file-observations="<?= $observationCount ?>"><span class="sw-tree-item-info"><span class="sw-status-dot is-<?= e($documentStatus) ?>" aria-label="Estado: <?= e($statusLabel) ?>" role="img"></span><i class="fa-regular fa-file" aria-hidden="true"></i><span><?= e((string)$file['original_name']) ?></span></span><span class="sw-file-tooltip" role="tooltip" aria-hidden="true" hidden><span class="sw-file-tooltip-name"><?= e((string)$file['original_name']) ?></span><span class="sw-file-tooltip-status"><span class="sw-status-dot is-<?= e($documentStatus) ?>" aria-hidden="true"></span><span class="sw-file-tooltip-label"><?= e($statusLabel) ?></span></span></span></button><div class="sw-file-row-actions"><?php if ($canManageFiles && !$protected): ?><button type="button" class="sw-file-menu-trigger" data-sw-menu-trigger aria-label="Acciones de <?= e((string)$file['original_name']) ?>" aria-expanded="false"><i class="fa-solid fa-ellipsis" aria-hidden="true"></i></button><div class="sw-file-menu" data-sw-file-menu hidden><button type="button" data-sw-replace data-file-id="<?= $fileId ?>" data-file-name="<?= e((string)$file['original_name']) ?>" data-file-checksum="<?= e((string)$file['checksum_sha256']) ?>">Reemplazar archivo</button><a href="<?= e($downloadUrl) ?>">Descargar</a><button type="button" data-sw-remove data-file-id="<?= $fileId ?>" data-file-name="<?= e((string)$file['original_name']) ?>">Quitar archivo</button></div><?php elseif ($protected && !$canReviewDocuments): ?><span class="sw-file-lock-badge" tabindex="0" role="img" aria-label="Bloqueado durante la revisión. No puedes modificar este archivo mientras se encuentra en revisión."><i class="fa-solid fa-lock" aria-hidden="true"></i><span class="sw-file-lock-tooltip" role="tooltip" aria-hidden="true"><strong>Bloqueado durante la revisión</strong><span>No puedes modificar este archivo mientras se encuentra en revisión.</span></span></span><?php endif; ?></div></div><?php if ($extension === 'zip'): ?><ul class="sw-zip-tree" data-sw-zip-tree hidden></ul><?php endif; ?></li><?php endforeach; ?></ul><?php endif; ?>
    </aside>
    <div class="sw-resizer sw-resizer-explorer" data-sw-resizer="explorer" role="separator" aria-orientation="vertical" aria-label="Redimensionar panel de archivos" tabindex="0" title="Arrastra para redimensionar (Doble clic para restablecer)"></div>
    <section class="sw-viewer-panel">
        <?php $packageUrl = route('project-package-download') . '&id=' . (int)$projectId; ?>
        <div class="sw-project-actions">
            <div class="sw-project-actions-group">
                <a class="sw-viewer-action" href="<?= e($packageUrl) ?>"><i class="fa-solid fa-file-zipper" aria-hidden="true"></i> Descargar todo (.zip)</a>
            </div>
            <div class="sw-project-actions-file">
                <button type="button" class="sw-viewer-action is-file-download" data-sw-viewer-download disabled><i class="fa-solid fa-download" aria-hidden="true"></i> Descargar</button>
                <button type="button" class="sw-viewer-action" data-sw-print disabled><i class="fa-solid fa-print" aria-hidden="true"></i> Imprimir</button>
            </div>
        </div>
        <header class="sw-viewer-toolbar">
            <div class="sw-viewer-doc-info">
                <i class="fa-solid fa-folder-open" data-sw-viewer-icon aria-hidden="true"></i>
                <div>
                    <strong data-sw-viewer-name>Visor de documentos</strong>
                    <span data-sw-viewer-meta>Exploración y consulta documental</span>
                </div>
            </div>
        </header>
        <?php if ($canReviewDocuments): ?>
        <div class="sw-viewer-help-banner">
            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
            <div class="sw-viewer-help-content">
                <strong>¿Cómo agregar observaciones?</strong>
                <span>Selecciona un fragmento del documento para agregar una observación. Para comentar todo el archivo, usa <strong>“Observación general”</strong>.</span>
            </div>
        </div>
        <?php endif; ?>
        <div class="sw-viewer-zoom-wrapper">
            <div class="sw-viewer-zoom" data-sw-viewer-zoom hidden>
                <button type="button" data-sw-zoom-minus aria-label="Alejar" title="Alejar (−)">−</button>
                <button type="button" data-sw-zoom-fit aria-label="Ajustar al ancho" title="Ajustar al ancho">Ajustar</button>
                <button type="button" data-sw-zoom-plus aria-label="Acercar" title="Acercar (+)">+</button>
                <span data-sw-zoom-percentage>100%</span>
            </div>
        </div>
        <div class="sw-viewer-canvas"><div class="sw-preview-stage" data-sw-preview-stage></div></div>
    </section>
    <div class="sw-resizer sw-resizer-observations" data-sw-resizer="observations" role="separator" aria-orientation="vertical" aria-label="Redimensionar panel de observaciones" tabindex="0" title="Arrastra para redimensionar (Doble clic para restablecer)"></div>
    <aside class="sw-observations-panel" data-sw-observations id="swObservationsPanel">
        <button type="button" class="sw-panel-reopen-btn" data-sw-open-observations hidden aria-label="Abrir panel de observaciones" title="Abrir panel de observaciones"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
        <header class="sw-obs-header"><span class="sw-obs-title"><i class="fa-solid fa-comments" aria-hidden="true"></i> Observaciones</span><button type="button" class="sw-panel-toggle" data-sw-toggle-observations aria-controls="swObservationsPanel" aria-label="Contraer panel de observaciones" aria-expanded="true"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button></header>
        <div data-sw-file-observations><p class="sw-empty-state">Selecciona un archivo para consultar sus observaciones.</p></div>
        <footer class="sw-obs-footer">
            <?php if ($canSendForReview): ?><button type="button" class="sw-obs-action-btn" data-sw-submit-review><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Enviar a revisión</button><?php endif; ?>
        </footer>
    </aside>
    <div class="sw-operation-modal" data-sw-operation-modal hidden><section role="dialog" aria-modal="true" aria-labelledby="swOperationTitle"><header><h2 data-sw-modal-title id="swOperationTitle">Confirmar acción</h2><button type="button" data-sw-modal-cancel aria-label="Cerrar"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></header><div><p data-sw-modal-message></p><p class="sw-modal-file-summary" data-sw-modal-summary hidden></p></div><footer><button type="button" data-sw-modal-cancel>Cancelar</button><button type="button" class="is-danger" data-sw-modal-confirm>Confirmar</button></footer></section></div>
    <?php
    $hasPreviousReview = !empty(array_filter($files, static fn(array $f): bool =>
        in_array(strtolower((string)($f['document_status'] ?? 'development')), ['approved', 'corrections_requested'], true)
    )) || !empty($observations);
    ?>
    <div class="sw-operation-modal sw-submit-modal-overlay" data-sw-submit-modal hidden>
        <section role="dialog" aria-modal="true" aria-labelledby="swSubmitReviewTitle" class="sw-submit-modal-card">
            <header class="sw-submit-modal-header">
                <h2 id="swSubmitReviewTitle">Enviar proyecto a revisión</h2>
                <button type="button" class="sw-submit-modal-close-btn" data-sw-submit-cancel aria-label="Cerrar ventana">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </header>

            <div class="sw-submit-modal-body">
                <div class="sw-submit-hero">
                    <div class="sw-submit-icon-badge">
                        <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                    </div>
                    <h3 class="sw-submit-question">
                        <?= $hasPreviousReview ? '¿Listo para reenviar tus correcciones?' : '¿Listo para enviar tu proyecto?' ?>
                    </h3>
                    <p class="sw-submit-subtitle">
                        <?= $hasPreviousReview
                            ? 'Se enviarán únicamente los documentos corregidos o pendientes. Los documentos ya aprobados se conservarán.'
                            : 'Tus documentos serán enviados al tutor para su primera revisión.' ?>
                    </p>
                </div>

                <div class="sw-submit-restriction-box">
                    <i class="fa-solid fa-lock" aria-hidden="true"></i>
                    <span>Mientras esté en revisión, no podrás editar la información ni modificar los archivos.</span>
                </div>

                <div data-sw-submit-error hidden role="alert" class="sw-submit-error-alert">
                    <strong data-sw-submit-error-title></strong>
                    <ul data-sw-submit-error-list hidden></ul>
                </div>
            </div>

            <footer class="sw-submit-modal-footer">
                <button type="button" class="sw-submit-cancel-btn" data-sw-submit-cancel>Cancelar</button>
                <button type="button" class="sw-submit-confirm-btn" data-sw-submit-confirm>
                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i> <span>Enviar a revisión</span>
                </button>
            </footer>
        </section>
    </div>
    <?php if ($canReviewDocuments): ?>
    <div class="sw-operation-modal sw-review-confirm-modal" data-sw-review-confirm-modal hidden>
        <section role="dialog" aria-modal="true" aria-labelledby="swReviewConfirmTitle" class="sw-review-confirm-card">
            <header class="sw-review-confirm-header">
                <h2 id="swReviewConfirmTitle">Confirmar revisión</h2>
                <button type="button" data-sw-review-confirm-close aria-label="Cerrar ventana">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </header>
            <div class="sw-review-confirm-body">
                <div class="sw-review-confirm-copy">
                    <strong data-sw-review-confirm-heading>Has terminado la revisión de los documentos.</strong>
                    <p data-sw-review-confirm-message>Al confirmar, el sistema registrará el resultado de esta revisión.</p>
                </div>
                <div class="sw-review-confirm-stats" data-sw-review-confirm-stats>
                    <span data-sw-review-approved-count>0 aprobados</span>
                    <span data-sw-review-corrections-count>0 con correcciones</span>
                </div>
                <p class="sw-review-confirm-status" data-sw-review-confirm-status hidden></p>
                <p class="sw-review-confirm-error" data-sw-review-confirm-error hidden role="alert"></p>
            </div>
            <footer class="sw-review-confirm-footer">
                <button type="button" class="sw-review-confirm-cancel" data-sw-review-confirm-cancel>Cancelar</button>
                <button type="button" class="sw-review-confirm-submit" data-sw-review-confirm-submit>
                    <i class="fa-solid fa-check" aria-hidden="true"></i> <span>Confirmar revisión</span>
                </button>
            </footer>
        </section>
    </div>
    <div class="sw-operation-modal sw-review-confirm-modal" data-sw-review-decision-modal hidden>
        <section role="dialog" aria-modal="true" aria-labelledby="swReviewDecisionTitle" class="sw-review-confirm-card">
            <header class="sw-review-confirm-header">
                <h2 id="swReviewDecisionTitle" data-sw-review-decision-title>¿Aprobar este documento?</h2>
                <button type="button" data-sw-review-decision-cancel aria-label="Cerrar ventana"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            </header>
            <div class="sw-review-confirm-body">
                <div class="sw-review-confirm-copy"><p data-sw-review-decision-message></p></div>
            </div>
            <footer class="sw-review-confirm-footer">
                <button type="button" class="sw-review-confirm-cancel" data-sw-review-decision-cancel>Cancelar</button>
                <button type="button" class="sw-review-confirm-submit" data-sw-review-decision-confirm><span>Aprobar y continuar</span></button>
            </footer>
        </section>
    </div>
    <?php endif; ?>
</section>
