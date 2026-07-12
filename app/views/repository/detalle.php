<?php if ($project === null): ?>
    <section class="repository-detail-not-found">
        <i class="fa-solid fa-folder-open" aria-hidden="true"></i>
        <h1>Proyecto no encontrado</h1>
        <p>El proyecto solicitado no existe o ya no se encuentra publicado.</p>
        <a class="open-btn" href="<?= e($repositoryUrl) ?>"><i class="fa-solid fa-arrow-left"></i> Volver al repositorio</a>
    </section>
<?php else: ?>
    <section class="skeleton-loader repository-detail-skeleton" id="repositoryDetailSkeleton" aria-label="Cargando detalle del proyecto">
        <div class="skeleton-card large"><span class="skeleton-line title"></span><span class="skeleton-line"></span><span class="skeleton-line medium"></span></div>
        <div class="skeleton-card large"><span class="skeleton-line title"></span><span class="skeleton-line"></span><span class="skeleton-line"></span></div>
    </section>

    <div
        class="repository-detail repository-project-card--<?= e($project['type_slug']) ?>"
        id="repositoryDetailContent"
        data-project-id="<?= e((string) $project['id']) ?>"
        data-favorite-url="<?= e($favoriteActionUrl) ?>"
        data-favorite-csrf="<?= e($favoriteCsrfToken) ?>"
        data-files-url="<?= e($filesActionUrl) ?>"
        data-file-download-url="<?= e($fileDownloadActionUrl) ?>"
        data-preview-url="<?= e($previewActionUrl) ?>"
        data-preview-content-url="<?= e($previewContentActionUrl) ?>"
        style="display: none;"
    >
        <nav class="repository-detail-breadcrumb" aria-label="Ruta de navegación">
            <a href="<?= e($repositoryUrl) ?>">Repositorio</a>
            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
            <span title="<?= e($project['title']) ?>"><?= e($project['title']) ?></span>
        </nav>

        <a class="repository-detail-back" href="<?= e($repositoryUrl) ?>"><i class="fa-solid fa-arrow-left"></i> Volver al repositorio</a>

        <header class="repository-detail-header">
            <div>
                <span class="repository-type"><?= e($project['type']) ?> · <?= e($project['pao_label']) ?></span>
                <h1><?= e($project['title']) ?></h1>
                <p>Publicado el <?= e($project['publication_date']) ?></p>
            </div>
            <button class="repository-detail-favorite<?= $project['is_favorite'] ? ' is-favorite' : '' ?>" id="repositoryDetailFavorite" type="button" aria-pressed="<?= $project['is_favorite'] ? 'true' : 'false' ?>">
                <i class="<?= $project['is_favorite'] ? 'fa-solid' : 'fa-regular' ?> fa-heart"></i>
                <span><?= $project['is_favorite'] ? 'Guardado en favoritos' : 'Guardar en favoritos' ?></span>
            </button>
        </header>

        <div class="repository-detail-layout">
            <aside class="repository-detail-info">
                <section class="repository-detail-panel">
                    <div class="repository-detail-panel-heading"><i class="fa-solid fa-graduation-cap"></i><h2>Información académica</h2></div>
                    <dl class="repository-detail-data">
                        <div><dt>Tipo de proyecto</dt><dd><?= e($project['type']) ?></dd></div>
                        <div><dt>Carrera</dt><dd><?= e($project['career']) ?></dd></div>
                        <div><dt>Semestre</dt><dd><?= e($project['semester']) ?>.° semestre</dd></div>
                        <div><dt>PAO</dt><dd><?= e($project['pao_label']) ?></dd></div>
                        <div><dt>Publicación</dt><dd><?= e($project['publication_date']) ?></dd></div>
                        <div><dt>Descargas</dt><dd><?= number_format((int) $project['downloads'], 0, ',', '.') ?></dd></div>
                        <div><dt>Archivos</dt><dd><?= e((string) $project['archive']['files_count']) ?></dd></div>
                        <div><dt>Tamaño ZIP</dt><dd><?= e($project['archive']['size']) ?></dd></div>
                    </dl>
                </section>

                <section class="repository-detail-panel">
                    <h2>Autores</h2>
                    <ul class="repository-detail-authors">
                        <?php foreach ($project['authors_list'] as $author): ?><li><i class="fa-solid fa-user-graduate"></i><?= e($author) ?></li><?php endforeach; ?>
                    </ul>
                    <div class="repository-detail-tutor"><span>Tutor</span><strong><?= e($project['tutor']) ?></strong></div>
                </section>

                <section class="repository-detail-panel">
                    <h2>Resumen</h2>
                    <p class="repository-detail-summary"><?= e($project['summary']) ?></p>
                </section>

                <section class="repository-detail-panel">
                    <h2>Tecnologías utilizadas</h2>
                    <div class="repository-technologies repository-detail-tags"><?php foreach ($project['technologies'] as $technology): ?><span><?= e($technology) ?></span><?php endforeach; ?></div>
                    <h2 class="repository-detail-keywords-title">Palabras clave</h2>
                    <div class="repository-detail-keywords"><?php foreach ($project['keywords'] as $keyword): ?><span><?= e($keyword) ?></span><?php endforeach; ?></div>
                </section>

                <a class="open-btn repository-detail-download" href="<?= e($projectDownloadUrl) ?>"><i class="fa-solid fa-download"></i> Descargar proyecto completo</a>
            </aside>

            <main class="repository-explorer" aria-labelledby="repositoryExplorerTitle" aria-busy="false">
                <header class="repository-explorer-header">
                    <div>
                        <span class="section-eyebrow">Archivo principal</span>
                        <h2 id="repositoryExplorerTitle"><?= e($project['archive']['name']) ?></h2>
                        <p><?= e((string) $project['archive']['files_count']) ?> archivos · <?= e((string) $project['archive']['folders_count']) ?> carpetas · <?= e($project['archive']['size']) ?></p>
                    </div>
                    <a class="open-btn" href="<?= e($projectDownloadUrl) ?>"><i class="fa-solid fa-download"></i> Descargar ZIP</a>
                </header>

                <nav class="repository-explorer-breadcrumb" id="repositoryExplorerBreadcrumb" aria-label="Ruta dentro del proyecto">
                    <?php foreach ($archiveState['breadcrumbs'] as $breadcrumbIndex => $breadcrumb): ?>
                        <?php if ($breadcrumbIndex > 0): ?><i class="fa-solid fa-chevron-right" aria-hidden="true"></i><?php endif; ?>
                        <button type="button" data-archive-path="<?= e($breadcrumb['path']) ?>"<?= $breadcrumb['path'] === $archiveState['path'] ? ' aria-current="page"' : '' ?>>
                            <?php if ($breadcrumbIndex === 0): ?><i class="fa-solid fa-box-archive" aria-hidden="true"></i><?php endif; ?>
                            <?= e($breadcrumb['label']) ?>
                        </button>
                    <?php endforeach; ?>
                </nav>

                <div class="repository-explorer-notice"><i class="fa-solid fa-shield-halved"></i><span>Exploración de solo lectura. Ningún archivo del proyecto se ejecuta o modifica.</span></div>

                <div class="repository-explorer-state repository-explorer-state--<?= e($archiveState['status']) ?>" id="repositoryExplorerState" role="status" aria-live="polite"<?= $archiveState['status'] === 'ready' ? ' hidden' : '' ?>>
                    <i class="fa-solid <?= $archiveState['status'] === 'empty' ? 'fa-folder-open' : 'fa-triangle-exclamation' ?>" aria-hidden="true"></i>
                    <p><?= e($archiveState['message']) ?></p>
                </div>

                <div class="repository-file-list" id="repositoryFileList" role="table" aria-label="Contenido del proyecto"<?= $archiveState['status'] === 'ready' ? '' : ' hidden' ?>>
                    <div class="repository-file-row repository-file-head" role="row"><span role="columnheader">Nombre</span><span role="columnheader">Tipo</span><span role="columnheader">Tamaño</span><span role="columnheader">Acción</span></div>
                    <div id="repositoryFileRows" role="rowgroup">
                    <?php foreach ($archiveState['items'] as $item): ?>
                        <div class="repository-file-row" role="row">
                            <?php if ($item['kind'] === 'folder'): ?>
                                <button class="repository-file-name repository-file-entry" type="button" role="cell" data-folder-path="<?= e($item['path']) ?>"><i class="fa-solid <?= e($item['icon']) ?> repository-file-icon--folder"></i><strong><?= e($item['name']) ?></strong></button>
                            <?php else: ?>
                                <button class="repository-file-name repository-file-entry" type="button" role="cell" data-file-path="<?= e($item['path']) ?>"><i class="fa-solid <?= e($item['icon']) ?> repository-file-icon--file"></i><strong><?= e($item['name']) ?></strong></button>
                            <?php endif; ?>
                            <span role="cell"><?= e($item['type']) ?></span>
                            <span role="cell"><?= e($item['size']) ?></span>
                            <span class="repository-file-action" role="cell">
                                <?php if ($item['kind'] === 'file'): ?>
                                    <a href="<?= e($fileDownloadActionUrl . '&id=' . rawurlencode((string) $project['id']) . '&path=' . rawurlencode($item['path'])) ?>" aria-label="Descargar <?= e($item['name']) ?>"><i class="fa-solid fa-download"></i></a>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                    </div>
                </div>

                <section class="repository-preview" id="repositoryPreview" aria-labelledby="repositoryPreviewTitle" aria-busy="false" hidden>
                    <button class="repository-preview-back" id="repositoryPreviewBack" type="button"><i class="fa-solid fa-arrow-left"></i> Volver a archivos</button>
                    <header class="repository-preview-header">
                        <div>
                            <span class="section-eyebrow" id="repositoryPreviewType">Archivo</span>
                            <h3 id="repositoryPreviewTitle">Vista previa</h3>
                            <p id="repositoryPreviewMeta"></p>
                        </div>
                        <div class="repository-preview-actions">
                            <button class="repository-preview-expand" id="repositoryPreviewExpand" type="button" disabled>
                                <i class="fa-solid fa-expand" aria-hidden="true"></i>
                                <span>Ampliar vista</span>
                            </button>
                            <a class="open-btn" id="repositoryPreviewDownload" href="#"><i class="fa-solid fa-download"></i> Descargar archivo</a>
                        </div>
                    </header>

                    <div class="repository-preview-message" id="repositoryPreviewMessage" hidden></div>

                    <div class="repository-preview-state" id="repositoryPreviewState" role="status" aria-live="polite">
                        <i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
                        <p>Preparando vista previa...</p>
                    </div>

                    <iframe class="repository-preview-pdf" id="repositoryPreviewPdf" title="Vista previa del documento PDF" hidden></iframe>

                    <div class="repository-preview-image-shell" id="repositoryPreviewImageShell" hidden>
                        <div class="repository-preview-image-tools">
                            <button type="button" id="repositoryImageZoomOut" aria-label="Reducir imagen"><i class="fa-solid fa-magnifying-glass-minus"></i></button>
                            <button type="button" id="repositoryImageZoomReset">100%</button>
                            <button type="button" id="repositoryImageZoomIn" aria-label="Ampliar imagen"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
                        </div>
                        <div class="repository-preview-image-canvas"><img id="repositoryPreviewImage" alt="" /></div>
                    </div>

                    <pre class="repository-preview-text" id="repositoryPreviewText" hidden></pre>
                    <div class="repository-preview-code" id="repositoryPreviewCode" hidden></div>
                    <article class="repository-preview-docx" id="repositoryPreviewDocx" aria-label="Contenido del documento de Word" hidden></article>
                </section>

                <div class="repository-preview-modal" id="repositoryPreviewModal" hidden>
                    <section class="repository-preview-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="repositoryPreviewModalTitle">
                        <header class="repository-preview-modal-header">
                            <div class="repository-preview-modal-heading">
                                <span>Vista previa del documento</span>
                                <h3 id="repositoryPreviewModalTitle">Documento PDF</h3>
                            </div>
                            <button class="repository-preview-modal-close" id="repositoryPreviewModalClose" type="button" aria-label="Cerrar vista ampliada">
                                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                            </button>
                        </header>
                        <div class="repository-preview-modal-body" id="repositoryPreviewModalBody"></div>
                    </section>
                </div>
            </main>
        </div>

        <div class="repository-toast" id="repositoryDetailToast" role="status" aria-live="polite" aria-atomic="true" hidden></div>
    </div>
<?php endif; ?>
