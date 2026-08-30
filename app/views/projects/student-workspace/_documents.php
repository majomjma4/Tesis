<?php
$files = (array) ($project['files'] ?? []);
$observations = (array) ($project['observations'] ?? []);
$historical = null;
if (isset($historicalVersion) && is_array($historicalVersion)) {
    $historical = $historicalVersion;
}
$canManageFiles = !$historical && !empty($projectCapabilities['manage_workspace_files']);
$canSendForReview = !$historical && !empty($projectCapabilities['send_for_review']);
$canPublishProject = !$historical && !empty($projectCapabilities['publish_project']);
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
$hasPreviousReviewForReplacement = !empty((array) ($project['deliveries'] ?? [])) || (int) ($project['delivery_count'] ?? 0) > 0;
$historicalPreviewUrl = $historical ? route('project-file-version-preview').'&project_id='.$projectId.'&version_id='.(int)$historical['id'] : '';
$docLimits = (new ProjectDocumentFileService())->limits();
$packageUrl = route('project-package-download') . '&id=' . (int)$projectId;
$packageAvailable = !empty(($studentAcademicPackage ?? [])['available']);
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
<section class="sw-doc-workspace<?= $historical ? ' is-historical' : '' ?><?= $canReviewDocuments ? ' is-teacher-review' : '' ?>" data-sw-document-manager data-sw-active-tab="explorer"<?= $canReviewDocuments ? ' data-sw-review-mode="draft"' : '' ?> data-endpoint="<?= e($documentEndpoint) ?>" data-csrf="<?= e($documentCsrf) ?>" data-submit-endpoint="<?= e((string) ($studentProjectSubmitEndpoint ?? '')) ?>" data-submit-csrf="<?= e((string) ($studentProjectSubmitCsrf ?? '')) ?>" data-correction-readiness="<?= e(json_encode($correctionReadiness ?? ['has_deliveries'=>false,'source'=>'legacy','required'=>[],'total_needed'=>0,'completed'=>0,'pending'=>[],'eligible'=>true], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) ?>" data-max-file-bytes="<?= (int) $docLimits['max_file_bytes'] ?>" data-max-file-mb="<?= (int) $docLimits['max_file_mb'] ?>" data-review-representation-endpoint="<?= e(route('student-project-review-representation')) ?>" data-review-representation-csrf="<?= e((new AuthSessionService())->csrfToken('student_project_review_representation')) ?>" data-project-id="<?= (int) $projectId ?>" data-sw-has-previous-review="<?= $hasPreviousReviewForReplacement ? 'true' : 'false' ?>"<?= $packageAvailable ? ' data-package-url="'.e($packageUrl).'"' : '' ?> data-project-status="<?= e((string)($status ?? '')) ?>" data-historical-preview="<?= e($historicalPreviewUrl) ?>" data-pdfjs-url="<?= e(asset('vendor/pdfjs/4.10.38/build/pdf.mjs')) ?>" data-pdfjs-worker="<?= e(asset('vendor/pdfjs/4.10.38/build/pdf.worker.mjs')) ?>" data-pdfjs-fonts="<?= e(asset('vendor/pdfjs/4.10.38/web/standard_fonts/')) ?>">
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
    <?php if ($canSendForReview || $canPublishProject): ?>
    <div class="sw-mobile-primary-action" data-sw-mobile-primary-action>
        <?php if ($canSendForReview): ?>
            <button type="button" class="sw-obs-action-btn" data-sw-submit-review><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Enviar a revisión</button>
        <?php elseif ($canPublishProject): ?>
            <button type="button" class="sw-obs-action-btn" data-project-publish data-project-id="<?= (int) $projectId ?>"><i class="fa-solid fa-upload" aria-hidden="true"></i> Publicar</button>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <aside class="sw-explorer-panel" data-sw-explorer id="swExplorerPanel">
        <button type="button" class="sw-panel-reopen-btn" data-sw-open-explorer hidden aria-label="Abrir panel de archivos" title="Abrir panel de archivos"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
        <header class="sw-explorer-header"><span class="sw-explorer-title"><i class="fa-solid fa-folder-open" aria-hidden="true"></i> Archivos</span><button type="button" class="sw-panel-toggle" data-sw-toggle-explorer aria-controls="swExplorerPanel" aria-label="Contraer panel de archivos" aria-expanded="true"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button></header>
        <?php if ($canManageFiles): ?><button type="button" class="sw-add-files" data-sw-add-files><i class="fa-solid fa-plus" aria-hidden="true"></i> Agregar archivos</button><input type="file" data-sw-file-input hidden multiple><?php endif; ?>
<?php
$getFileIconClass = static function (?string $extension, ?string $mimeType = null, ?string $filename = null): string {
    $ext = strtolower(trim((string)$extension));
    $mime = strtolower(trim((string)$mimeType));
    $name = strtolower(trim((string)$filename));

    if ($ext === '' && $name !== '') {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    }

    if (in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz'], true) || str_contains($mime, 'zip') || str_contains($mime, 'compressed') || str_contains($mime, 'tar') || str_contains($mime, 'archive')) {
        return 'fa-solid fa-file-zipper';
    }

    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'tiff', 'ico'], true) || str_starts_with($mime, 'image/')) {
        return 'fa-solid fa-file-image';
    }

    if ($ext === 'pdf' || $mime === 'application/pdf') {
        return 'fa-solid fa-file-lines';
    }

    if (in_array($ext, ['doc', 'docx', 'odt', 'rtf'], true) || str_contains($mime, 'word') || str_contains($mime, 'officedocument.wordprocessingml')) {
        return 'fa-solid fa-file-word';
    }

    if (in_array($ext, ['xls', 'xlsx', 'csv', 'ods'], true) || str_contains($mime, 'excel') || str_contains($mime, 'spreadsheet')) {
        return 'fa-solid fa-file-excel';
    }

    if (in_array($ext, ['ppt', 'pptx', 'odp'], true) || str_contains($mime, 'powerpoint') || str_contains($mime, 'presentation')) {
        return 'fa-solid fa-file-powerpoint';
    }

    if (in_array($ext, ['txt', 'md', 'json', 'xml', 'log'], true) || str_starts_with($mime, 'text/')) {
        return 'fa-solid fa-file-lines';
    }

    return 'fa-regular fa-file';
};
?>
        <?php if (!$files): ?><p class="sw-empty-state">Todavía no hay archivos en este proyecto.</p><?php else: ?><ul class="sw-tree-list"><?php foreach ($files as $file):
            $fileId=(int)$file['id']; $fileChecksum=strtolower((string)($file['checksum_sha256'] ?? '')); $observationCount=count(array_filter($observations, static fn(array $obs): bool => (int)($obs['file_id'] ?? 0) === $fileId && strtolower((string)($obs['file_checksum_sha256'] ?? '')) === $fileChecksum));
            $extension=strtolower((string)$file['extension']); $mimeType=strtolower((string)($file['mime_type'] ?? '')); $documentStatus=(string)($file['document_status'] ?? 'development');
            $hasDeliveries = !empty($project['deliveries']) || (int)($project['delivery_count'] ?? 0) > 0;
            $statusLabel = match ($documentStatus) {
                'approved' => 'Aprobado',
                'corrections_requested' => 'Requiere correcciones',
                'under_review' => 'En revisión',
                'development' => $hasDeliveries ? 'Corrección cargada' : 'En desarrollo',
                default => (string)($file['document_status_label'] ?? 'En desarrollo'),
            };
            $protected = in_array($documentStatus, ['approved', 'under_review'], true);
            $previewVersion=!empty($file['checksum_sha256']) ? '&v='.rawurlencode(substr((string)$file['checksum_sha256'],0,16)) : '';
            $previewUrl=route('project-file-preview').'&project_id='.$projectId.'&file_id='.$fileId.$previewVersion;
            $downloadUrl=route('project-file-download').'&project_id='.$projectId.'&file_id='.$fileId;
            $zipUrl=$extension==='zip' ? route('project-zip-list').'&context=academic&project_id='.$projectId.'&file_id='.$fileId.$previewVersion : '';
            $zipPreviewUrl=$extension==='zip' ? route('project-zip-entry-preview').'&context=academic&project_id='.$projectId.'&file_id='.$fileId : '';
            $zipDownloadUrl=$extension==='zip' ? route('project-zip-entry-download').'&context=academic&project_id='.$projectId.'&file_id='.$fileId : '';
            $iconClass = $getFileIconClass($extension, $mimeType, (string)($file['original_name'] ?? ''));
            $isDeliveredFile = !empty($file['delivery_id']) || $protected || $documentStatus === 'corrections_requested' || $hasDeliveries;
            $canRemoveFile = !$isDeliveredFile && $documentStatus === 'development';
        ?><li class="sw-archive-node"><div class="sw-file-row"><button type="button" class="sw-tree-item" aria-label="<?= e((string)$file['original_name']) ?>. Estado: <?= e($statusLabel) ?>" data-sw-file data-file-id="<?= $fileId ?>" data-file-checksum="<?= e($fileChecksum) ?>" data-file-name="<?= e((string)$file['original_name']) ?>" data-document-status="<?= e($documentStatus) ?>" data-file-extension="<?= e(strtoupper($extension)) ?>" data-file-size="<?= e(ArchiveService::formatBytes((int)($file['size_bytes'] ?? 0))) ?>" data-file-preview="<?= e($previewUrl) ?>" data-file-download="<?= e($downloadUrl) ?>" data-file-zip-url="<?= e($zipUrl) ?>" data-file-zip-preview-url="<?= e($zipPreviewUrl) ?>" data-file-zip-download-url="<?= e($zipDownloadUrl) ?>" data-file-observations="<?= $observationCount ?>"><span class="sw-tree-item-info"><span class="sw-status-dot is-<?= e($documentStatus) ?>" aria-label="Estado: <?= e($statusLabel) ?>" role="img"></span><i class="<?= e($iconClass) ?>" aria-hidden="true"></i><span><?= e((string)$file['original_name']) ?></span></span><span class="sw-file-tooltip" role="tooltip" aria-hidden="true" hidden><span class="sw-file-tooltip-name"><?= e((string)$file['original_name']) ?></span><span class="sw-file-tooltip-status"><span class="sw-status-dot is-<?= e($documentStatus) ?>" aria-hidden="true"></span><span class="sw-file-tooltip-label"><?= e($statusLabel) ?></span></span></span></button><div class="sw-file-row-actions"><?php if ($canManageFiles && $documentStatus !== 'under_review'): ?><button type="button" class="sw-file-menu-trigger" data-sw-menu-trigger aria-label="Acciones de <?= e((string)$file['original_name']) ?>" aria-expanded="false"><i class="fa-solid fa-ellipsis" aria-hidden="true"></i></button><div class="sw-file-menu" data-sw-file-menu hidden><button type="button" data-sw-replace data-file-id="<?= $fileId ?>" data-file-name="<?= e((string)$file['original_name']) ?>" data-file-checksum="<?= e((string)$file['checksum_sha256']) ?>" data-reason-required="<?= $hasPreviousReviewForReplacement && $documentStatus !== 'corrections_requested' ? 'true' : 'false' ?>">Reemplazar archivo</button><a data-sw-original-download href="<?= e($downloadUrl) ?>">Descargar</a><?php if ($canRemoveFile): ?><button type="button" data-sw-remove data-file-id="<?= $fileId ?>" data-file-name="<?= e((string)$file['original_name']) ?>">Quitar archivo</button><?php endif; ?></div><?php elseif ($protected && !$canReviewDocuments): ?><span class="sw-file-lock-badge" tabindex="0" role="img" aria-label="<?= $documentStatus === 'approved' ? 'Documento aprobado. No requiere modificaciones.' : 'Bloqueado durante la revisión.' ?>"><i class="fa-solid <?= $documentStatus === 'approved' ? 'fa-circle-check' : 'fa-lock' ?>" style="<?= $documentStatus === 'approved' ? 'color:#16a34a;' : '' ?>" aria-hidden="true"></i><span class="sw-file-lock-tooltip" role="tooltip" aria-hidden="true"><strong><?= $documentStatus === 'approved' ? 'Documento aprobado' : 'Bloqueado durante la revisión' ?></strong><span><?= $documentStatus === 'approved' ? 'Este documento fue aprobado en la última revisión y no requiere cambios.' : 'No puedes modificar este archivo mientras se encuentra en revisión.' ?></span></span></span><?php endif; ?></div></div><?php if ($extension === 'zip'): ?><ul class="sw-zip-tree" data-sw-zip-tree hidden></ul><?php endif; ?></li><?php endforeach; ?></ul><?php endif; ?>
    </aside>
    <div class="sw-resizer sw-resizer-explorer" data-sw-resizer="explorer" role="separator" aria-orientation="vertical" aria-label="Redimensionar panel de archivos" tabindex="0" title="Arrastra para redimensionar (Doble clic para restablecer)"></div>
    <section class="sw-viewer-panel">
        <div class="sw-project-actions">
            <div class="sw-project-actions-group">
                <?php if ($packageAvailable): ?><a class="sw-viewer-action" data-sw-original-download data-sw-download-kind="package" href="<?= e($packageUrl) ?>"><i class="fa-solid fa-file-zipper" aria-hidden="true"></i> Descargar todo (.zip)</a><?php else: ?><span class="sw-viewer-action is-disabled" aria-disabled="true" title="El paquete estará disponible después de preparar los documentos."><i class="fa-solid fa-file-zipper" aria-hidden="true"></i> Descargar todo (.zip)</span><?php endif; ?>
            </div>
            <div class="sw-project-actions-file">
                <button type="button" class="sw-viewer-action is-file-download" data-sw-viewer-download aria-label="Descargar documento" disabled><i class="fa-solid fa-download" aria-hidden="true"></i> Descargar</button>
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
        <?php if (!$canReviewDocuments): ?>
        <div class="sw-viewer-download-note" role="note">
            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
            <span>Las observaciones y correcciones de esta revisión se almacenan en el sistema y no modifican el archivo original. La descarga corresponde al documento originalmente enviado.</span>
        </div>
        <?php endif; ?>
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
            <?php if ($canPublishProject): ?><button type="button" class="sw-obs-action-btn" data-project-publish data-project-id="<?= (int) $projectId ?>"><i class="fa-solid fa-upload" aria-hidden="true"></i> Publicar</button><?php endif; ?>
        </footer>
    </aside>
    <?php if ($canReviewDocuments): ?>
    <footer class="sw-mobile-review-footer" data-sw-mobile-review-footer hidden></footer>
    <?php endif; ?>
    <div class="sw-operation-modal" data-sw-operation-modal hidden><section role="dialog" aria-modal="true" aria-labelledby="swOperationTitle"><header><h2 data-sw-modal-title id="swOperationTitle">Confirmar acción</h2><button type="button" data-sw-modal-cancel aria-label="Cerrar"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></header><div><p data-sw-modal-message></p><p class="sw-modal-file-summary" data-sw-modal-summary hidden></p></div><footer><button type="button" data-sw-modal-cancel>Cancelar</button><button type="button" class="is-danger" data-sw-modal-confirm>Confirmar</button></footer></section></div>
    <div class="sw-operation-modal" data-sw-download-confirm-modal hidden><section role="dialog" aria-modal="true" aria-labelledby="swDownloadConfirmTitle" aria-describedby="swDownloadConfirmMessage" tabindex="-1"><header><h2 id="swDownloadConfirmTitle" data-sw-download-title>Descargar documento</h2><button type="button" data-sw-download-cancel aria-label="Cerrar"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></header><div><p id="swDownloadConfirmMessage" data-sw-download-message>Las observaciones y correcciones de la revisión no se incorporan al archivo descargado. Se descargará el documento original que fue enviado.</p></div><footer><button type="button" data-sw-download-cancel>Cancelar</button><button type="button" class="is-primary" data-sw-download-confirm>Descargar</button></footer></section></div>
    <div class="sw-operation-modal sw-submit-modal-overlay" data-sw-replace-modal hidden>
        <section role="dialog" aria-modal="true" aria-labelledby="swReplaceModalTitle" class="sw-submit-modal-card" style="max-width: 480px; width: 92%;">
            <header class="sw-submit-modal-header">
                <h2 id="swReplaceModalTitle" data-sw-replace-title>Reemplazar archivo</h2>
                <button type="button" class="sw-submit-modal-close-btn" data-sw-replace-cancel aria-label="Cerrar ventana">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </header>
            <div class="sw-submit-modal-body" style="padding: 1.1rem 1.25rem;">
                <div class="sw-replace-file-info" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 12px; font-size: 0.83rem; margin-bottom: 12px;">
                    <div style="margin-bottom: 4px; color: #475569;">
                        <strong>Archivo actual:</strong> <span data-sw-replace-current-name style="color: #0f172a; font-weight: 600;"></span>
                    </div>
                    <div style="color: #475569;">
                        <strong data-sw-replace-new-label>Nueva versión:</strong> <span data-sw-replace-new-name style="color: #2563eb; font-weight: 600;"></span>
                    </div>
                </div>

                <div class="sw-replace-notice" data-sw-replace-notice style="font-size: 0.83rem; color: #334155; line-height: 1.45; margin-bottom: 12px;">
                    <?= $hasPreviousReviewForReplacement
                        ? 'Se cargará una nueva versión de este documento. La versión anterior se conservará en el historial. Indica el motivo del cambio para continuar.'
                        : 'El archivo será reemplazado durante la preparación del proyecto.' ?>
                </div>

                <div class="sw-replace-reason-group" data-sw-replace-reason-group hidden style="width: 100%; box-sizing: border-box; margin-bottom: 12px; position: relative;">
                    <label for="swReplaceReasonSelect" style="display: block; font-size: 0.82rem; font-weight: 600; color: #1e293b; margin-bottom: 4px;">
                        Motivo del cambio <span style="color: #dc2626;">*</span>
                    </label>
                    <select id="swReplaceReasonSelect" data-sw-replace-reason-select data-native-select="true" class="sw-replace-reason-native" style="display: none !important; width: 0 !important; height: 0 !important; opacity: 0 !important; pointer-events: none !important; position: absolute !important;">
                        <option value="">-- Selecciona un motivo --</option>
                        <option value="name_change">Cambio de nombre del archivo</option>
                        <option value="format_change">Cambio de formato</option>
                        <option value="restructuring">Reestructuración del documento</option>
                        <option value="substitution">Sustitución por versión actualizada</option>
                        <option value="wrong_file">Corrección del archivo equivocado</option>
                        <option value="other">Otro</option>
                    </select>
                    <button type="button" id="swReplaceReasonTrigger" data-sw-reason-trigger class="sw-custom-select-trigger" aria-haspopup="listbox" aria-expanded="false" style="width: 100%; box-sizing: border-box; display: flex; align-items: center; justify-content: space-between; padding: 8px 12px; font-size: 0.83rem; border: 1px solid #cbd5e1; border-radius: 6px; background: #ffffff; color: #0f172a; cursor: pointer; text-align: left;">
                        <span data-sw-reason-trigger-text style="color: #64748b;">-- Selecciona un motivo --</span>
                        <i class="fa-solid fa-chevron-down" style="font-size: 0.75rem; color: #64748b; margin-left: 8px; transition: transform 0.2s ease;"></i>
                    </button>
                </div>

                <div class="sw-replace-other-group" data-sw-replace-other-group hidden style="width: 100%; box-sizing: border-box; margin-bottom: 12px;">
                    <label for="swReplaceOtherDetail" style="display: block; font-size: 0.82rem; font-weight: 600; color: #1e293b; margin-bottom: 4px;">
                        Describe brevemente el motivo del cambio <span style="color: #dc2626;">*</span>
                    </label>
                    <textarea id="swReplaceOtherDetail" data-sw-replace-other-detail placeholder="Ingresa al menos 5 caracteres..." rows="3" style="width: 100%; padding: 8px 10px; font-size: 0.83rem; border: 1px solid #cbd5e1; border-radius: 6px; resize: vertical; color: #0f172a;"></textarea>
                    <span style="font-size: 0.75rem; color: #64748b; margin-top: 2px; display: block;">Mínimo 5 caracteres.</span>
                </div>

                <div class="sw-replace-error-alert" data-sw-replace-error hidden role="alert" style="font-size: 0.82rem; color: #dc2626; background: #fef2f2; border: 1px solid #fecaca; padding: 8px 12px; border-radius: 6px; margin-top: 8px;"></div>
            </div>
            <footer class="sw-submit-modal-footer">
                <button type="button" class="sw-submit-cancel-btn" data-sw-replace-cancel>Cancelar</button>
                <button type="button" class="sw-submit-confirm-btn" data-sw-replace-confirm>
                    <i class="fa-solid fa-arrows-rotate" aria-hidden="true" style="color: #ffffff !important;"></i> <span style="color: #ffffff !important;">Reemplazar archivo</span>
                </button>
            </footer>
        </section>
    </div>
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
        <section role="dialog" aria-modal="true" aria-labelledby="swReviewConfirmTitle" class="sw-review-confirm-card sw-submit-modal-card">
            <header class="sw-review-confirm-header sw-submit-modal-header">
                <h2 id="swReviewConfirmTitle">¿Terminar revisión?</h2>
                <button type="button" class="sw-submit-modal-close-btn" data-sw-review-confirm-close aria-label="Cerrar ventana">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </header>
            <div class="sw-review-confirm-body sw-submit-modal-body" style="text-align: left; align-items: stretch; padding: 1.1rem 1.25rem;">
                <div class="sw-submit-hero" style="margin-bottom: 0.6rem;">
                    <div class="sw-submit-icon-badge" style="width: 52px; height: 52px; font-size: 1.35rem; margin-bottom: 0.5rem;">
                        <i class="fa-solid fa-clipboard-check" aria-hidden="true"></i>
                    </div>
                    <h3 class="sw-submit-question" data-sw-review-confirm-heading style="font-size: 1rem; margin-bottom: 0.2rem;">Estás a punto de enviar esta revisión al estudiante.</h3>
                </div>
                <div class="sw-review-confirm-stats" data-sw-review-confirm-stats style="display: flex; flex-wrap: wrap; gap: 0.35rem; justify-content: center; width: 100%; margin-bottom: 0.65rem; font-size: 0.76rem; font-weight: 600;">
                    <span data-sw-review-approved-count style="color: #166534; background: #f0fdf4; padding: 0.25rem 0.55rem; border-radius: 6px; border: 1px solid #bbf7d0;">0 aprobados</span>
                    <span data-sw-review-corrections-count style="color: #991b1b; background: #fef2f2; padding: 0.25rem 0.55rem; border-radius: 6px; border: 1px solid #fecaca;">0 con correcciones</span>
                    <span data-sw-review-observations-count style="color: #1e40af; background: #eff6ff; padding: 0.25rem 0.55rem; border-radius: 6px; border: 1px solid #bfdbfe;">0 observaciones nuevas</span>
                </div>
                <div class="sw-submit-subtitle" data-sw-review-confirm-message style="font-size: 0.83rem; color: #334155; line-height: 1.45; margin-bottom: 0.65rem; max-width: 100%; text-align: left;"></div>
                <div class="sw-submit-restriction-box" style="padding: 0.55rem 0.8rem;">
                    <i class="fa-solid fa-lock" aria-hidden="true"></i>
                    <span data-sw-review-confirm-lock>Después de confirmar no podrás modificar esta revisión.</span>
                </div>
                <p class="sw-review-confirm-status" data-sw-review-confirm-status hidden style="margin-top: 0.5rem; font-size: 0.82rem; color: #2563eb; font-weight: 600; text-align: center;"></p>
                <p class="sw-review-confirm-error" data-sw-review-confirm-error hidden role="alert" style="margin-top: 0.5rem; font-size: 0.82rem; color: #dc2626; background: #fef2f2; border: 1px solid #fecaca; padding: 0.5rem 0.75rem; border-radius: 8px; text-align: left;"></p>
            </div>
            <footer class="sw-review-confirm-footer sw-submit-modal-footer" style="padding: 0.75rem 1.15rem;">
                <button type="button" class="sw-submit-cancel-btn" data-sw-review-confirm-cancel>Cancelar</button>
                <button type="button" class="sw-submit-confirm-btn sw-review-confirm-submit" data-sw-review-confirm-submit style="background: var(--sw-primary, #2563eb) !important; color: #ffffff !important; border-color: var(--sw-primary, #2563eb) !important;">
                    <i class="fa-solid fa-check" aria-hidden="true" style="color: #ffffff !important;"></i> <span style="color: #ffffff !important;">Terminar revisión</span>
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
