<?php if ($material === null): ?>
    <section class="repository-detail-not-found"><i class="fa-solid fa-book-open"></i><h1>Material no encontrado</h1><p>El material solicitado no existe o ya no se encuentra disponible.</p><a class="open-btn" href="<?= e($materialsUrl) ?>"><i class="fa-solid fa-arrow-left"></i> Volver a Material de apoyo</a></section>
<?php else: ?>
    <?php
    $downloadUrl = static fn (array $file): string => $downloadActionUrl . '&material_id=' . rawurlencode((string) $material['id']) . '&file_id=' . rawurlencode((string) $file['id']);
    ?>
    <section class="skeleton-loader repository-detail-skeleton" id="repositoryDetailSkeleton" aria-label="Cargando material"><div class="skeleton-card large"><span class="skeleton-line title"></span><span class="skeleton-line"></span></div><div class="skeleton-card large"><span class="skeleton-line title"></span><span class="skeleton-line"></span></div></section>

    <div class="repository-detail support-material-detail" id="repositoryDetailContent" data-project-id="<?= e((string) $material['id']) ?>" data-preview-url="<?= e($previewActionUrl) ?>" data-preview-content-url="<?= e($previewContentActionUrl) ?>" data-file-download-url="<?= e($downloadActionUrl) ?>" style="display:none;">
        <nav class="repository-detail-breadcrumb" aria-label="Ruta de navegación"><a href="<?= e($repositoryUrl) ?>">Repositorio</a><i class="fa-solid fa-chevron-right"></i><a href="<?= e($materialsUrl) ?>">Material de apoyo</a><i class="fa-solid fa-chevron-right"></i><span title="<?= e($material['title']) ?>"><?= e($material['title']) ?></span></nav>
        <a class="repository-detail-back" href="<?= e($materialsUrl) ?>"><i class="fa-solid fa-arrow-left"></i> Volver a Material de apoyo</a>
        <?php if (!empty($isAdministrator)): ?><a class="repository-detail-back" href="<?= e($materialEditUrl) ?>"><i class="fa-regular fa-pen-to-square"></i> Editar material</a><?php endif; ?>

        <header class="repository-detail-header support-material-header">
            <div><span class="repository-type"><?= e($material['category_label']) ?> · <?= e($material['type']) ?></span><h1><?= e($material['title']) ?></h1><p>Publicado el <?= e($material['publication_date']) ?></p></div>
            <span class="project-status approved"><i class="fa-solid fa-circle-check"></i> <?= e($material['status']) ?></span>
        </header>

        <div class="repository-detail-layout">
            <aside class="repository-detail-info">
                <section class="repository-detail-panel"><div class="repository-detail-panel-heading"><i class="fa-solid fa-circle-info"></i><h2>Información del material</h2></div><dl class="repository-detail-data">
                    <div><dt><i class="fa-solid fa-folder-open"></i> Categoría</dt><dd><?= e($material['category_label']) ?></dd></div><div><dt><i class="fa-solid fa-file-lines"></i> Tipo</dt><dd><?= e($material['type']) ?></dd></div>
                    <div><dt><i class="fa-solid fa-calendar-check"></i> PAO</dt><dd><?= e($material['pao_label']) ?></dd></div><div><dt><i class="fa-solid fa-calendar-days"></i> Año</dt><dd><?= e($material['year']) ?></dd></div>
                    <div><dt>Publicación</dt><dd><?= e($material['publication_date']) ?></dd></div><div><dt><i class="fa-solid fa-file-circle-check"></i> Formato principal</dt><dd><?= e($material['primary_file']['format']) ?></dd></div>
                    <div><dt>Tamaño total</dt><dd><?= e($material['size']) ?></dd></div><div><dt><i class="fa-solid fa-copy"></i> Archivos</dt><dd><?= e((string) $material['files_count']) ?></dd></div>
                    <div><dt><i class="fa-solid fa-download"></i> Descargas</dt><dd><?= number_format($material['downloads'], 0, ',', '.') ?></dd></div>
                </dl></section>
                <section class="repository-detail-panel support-material-publisher"><span class="support-material-publisher-icon"><i class="fa-solid fa-building-columns"></i></span><div><span>Publicado por</span><strong><?= e($material['publisher']) ?></strong></div></section>
                <section class="repository-detail-panel support-material-description-panel"><div class="repository-detail-panel-heading"><i class="fa-solid fa-align-left"></i><h2>Descripción</h2></div><p class="repository-detail-summary support-material-description"><?= nl2br(e($material['full_description'])) ?></p></section>
                <section class="repository-detail-panel support-material-keywords-panel"><div class="repository-detail-panel-heading"><i class="fa-solid fa-tags"></i><h2>Palabras clave</h2></div><div class="repository-detail-keywords"><?php foreach ($material['keywords'] as $keyword): ?><span><?= e($keyword) ?></span><?php endforeach; ?></div></section>
                <a class="open-btn repository-detail-download" href="<?= e($downloadUrl($material['primary_file'])) ?>"><i class="fa-solid fa-download"></i> <?= $material['files_count'] === 1 ? 'Descargar documento' : 'Descargar archivo principal' ?></a>
                <?php if (isset($material['package'])): ?><a class="repository-complete-download" href="<?= e($downloadUrl($material['package'])) ?>"><i class="fa-solid fa-file-zipper"></i> Descargar material completo <small><?= e($material['package']['size']) ?></small></a><?php endif; ?>
            </aside>

            <main class="repository-explorer support-material-files" aria-labelledby="supportMaterialFilesTitle" aria-busy="false">
                <header class="repository-explorer-header support-material-main-file"><span class="support-material-main-icon"><i class="fa-regular fa-file-lines"></i><small><?= e($material['primary_file']['format']) ?></small></span><div><span class="section-eyebrow">Archivo principal</span><h2 id="supportMaterialFilesTitle" title="<?= e($material['primary_file']['name']) ?>"><?= e($material['primary_file']['name']) ?></h2><p><?= e($material['primary_file']['format']) ?> · <?= e($material['primary_file']['size']) ?></p></div></header>
                <div class="repository-explorer-notice"><i class="fa-solid fa-shield-halved"></i><span>Consulta segura y de solo lectura. Ningún archivo se ejecuta dentro de la plataforma.</span></div>
                <div class="repository-explorer-state" id="repositoryExplorerState" role="status" aria-live="polite" hidden><i class="fa-solid fa-spinner"></i><p></p></div>
                <div class="repository-file-list support-material-file-list" id="repositoryFileList">
                    <div id="repositoryFileRows">
                        <div class="repository-file-row support-material-primary-file">
                            <button class="repository-file-name repository-file-entry" type="button" data-file-path="<?= e((string) $material['primary_file']['id']) ?>"><span class="support-material-file-icon"><i class="fa-regular fa-file-lines"></i><small><?= e($material['primary_file']['format']) ?></small></span><strong><?= e($material['primary_file']['name']) ?></strong></button>
                            <span><?= e($material['primary_file']['format']) ?></span><span><?= e($material['primary_file']['size']) ?></span><span class="repository-file-action"><button type="button" data-file-path="<?= e((string) $material['primary_file']['id']) ?>" aria-label="Vista previa de <?= e($material['primary_file']['name']) ?>"><i class="fa-solid fa-eye"></i></button><a href="<?= e($downloadUrl($material['primary_file'])) ?>" aria-label="Descargar <?= e($material['primary_file']['name']) ?>"><i class="fa-solid fa-download"></i></a></span>
                        </div>
                        <?php if ($material['additional_files'] !== []): ?>
                            <h3 class="support-material-additional-title">Archivos adicionales</h3>
                            <?php foreach ($material['additional_files'] as $file): ?><div class="repository-file-row support-material-additional-file">
                                <button class="repository-file-name repository-file-entry" type="button" data-file-path="<?= e((string) $file['id']) ?>" title="<?= e($file['name']) ?>"><span class="support-material-file-icon"><i class="fa-regular fa-file-lines"></i><small><?= e($file['format']) ?></small></span><span class="support-material-file-copy"><strong><?= e($file['name']) ?></strong><small>Archivo <?= e($file['format']) ?></small></span></button>
                                <span class="support-material-extension-chip">.<?= e(strtoupper($file['extension'])) ?></span><span class="support-material-file-size"><?= e($file['size']) ?></span><span class="repository-file-action"><button type="button" data-file-path="<?= e((string) $file['id']) ?>" aria-label="Vista previa de <?= e($file['name']) ?>"><i class="fa-solid fa-eye"></i></button><a href="<?= e($downloadUrl($file)) ?>" aria-label="Descargar <?= e($file['name']) ?>"><i class="fa-solid fa-download"></i></a></span>
                            </div><?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <section class="repository-preview" id="repositoryPreview" aria-labelledby="repositoryPreviewTitle" aria-busy="false" hidden>
                    <button class="repository-preview-back" id="repositoryPreviewBack" type="button"><i class="fa-solid fa-arrow-left"></i> Volver a archivos</button>
                    <header class="repository-preview-header"><div><span class="section-eyebrow" id="repositoryPreviewType">Archivo</span><h3 id="repositoryPreviewTitle">Vista previa</h3><p id="repositoryPreviewMeta"></p></div><div class="repository-preview-actions"><button class="repository-preview-expand" id="repositoryPreviewExpand" type="button" disabled><i class="fa-solid fa-expand"></i><span>Ampliar vista</span></button><a class="open-btn" id="repositoryPreviewDownload" href="<?= e($downloadUrl($material['primary_file'])) ?>"><i class="fa-solid fa-download"></i> Descargar archivo</a></div></header>
                    <div class="repository-preview-message" id="repositoryPreviewMessage" hidden></div><div class="repository-preview-state" id="repositoryPreviewState" role="status" aria-live="polite"><i class="fa-solid fa-spinner fa-spin"></i><p>Preparando vista previa...</p></div>
                    <iframe class="repository-preview-pdf" id="repositoryPreviewPdf" title="Vista previa del documento PDF" hidden></iframe>
                    <div class="repository-preview-image-shell" id="repositoryPreviewImageShell" hidden><div class="repository-preview-image-tools"><button type="button" id="repositoryImageZoomOut"><i class="fa-solid fa-magnifying-glass-minus"></i></button><button type="button" id="repositoryImageZoomReset">100%</button><button type="button" id="repositoryImageZoomIn"><i class="fa-solid fa-magnifying-glass-plus"></i></button></div><div class="repository-preview-image-canvas"><img id="repositoryPreviewImage" alt=""></div></div>
                    <pre class="repository-preview-text" id="repositoryPreviewText" hidden></pre><div class="repository-preview-code" id="repositoryPreviewCode" hidden></div><article class="repository-preview-docx" id="repositoryPreviewDocx" hidden></article>
                </section>
                <div class="repository-preview-modal" id="repositoryPreviewModal" hidden><section class="repository-preview-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="repositoryPreviewModalTitle"><header class="repository-preview-modal-header"><div class="repository-preview-modal-heading"><span>Vista previa del documento</span><h3 id="repositoryPreviewModalTitle">Documento</h3></div><button class="repository-preview-modal-close" id="repositoryPreviewModalClose" type="button" aria-label="Cerrar vista ampliada"><i class="fa-solid fa-xmark"></i></button></header><div class="repository-preview-modal-body" id="repositoryPreviewModalBody"></div></section></div>
            </main>
        </div>
        <div class="repository-toast" id="repositoryDetailToast" role="status" aria-live="polite" hidden></div>
    </div>
<?php endif; ?>
