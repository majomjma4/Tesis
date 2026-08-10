<!-- Inicio de precarga del repositorio -->
<section class="skeleton-loader repository-skeleton" id="repositorySkeleton" aria-label="Cargando repositorio">
    <div class="skeleton-card repository-skeleton-hero">
        <span class="skeleton-line medium"></span>
        <span class="skeleton-line title"></span>
        <span class="skeleton-line"></span>
    </div>
    <div class="repository-skeleton-toolbar">
        <span class="skeleton-line"></span>
        <span class="skeleton-line"></span>
    </div>
    <div class="repository-skeleton-grid">
        <?php for ($skeletonIndex = 0; $skeletonIndex < 4; $skeletonIndex++): ?>
            <div class="skeleton-card repository-skeleton-card">
                <span class="skeleton-pill"></span>
                <span class="skeleton-line title"></span>
                <span class="skeleton-line"></span>
                <span class="skeleton-line medium"></span>
                <span class="skeleton-line short"></span>
            </div>
        <?php endfor; ?>
    </div>
</section>
<!-- Final de precarga del repositorio -->

<div id="repositoryContent" style="display: none;">
    <div class="ar-page" id="arPage">
        <!-- Encabezado exacto Admin -->
        <header class="ar-head">
            <div>
                <span class="ar-eyebrow">Biblioteca digital institucional</span>
                <h1>Repositorio institucional</h1>
                <p>Consulta proyectos publicados y materiales de apoyo disponibles para la comunidad académica.</p>
            </div>
        </header>

        <!-- Catálogo unificado y contenedor principal -->
        <main class="ar-catalog" id="repositoryShell">
            <!-- Pestañas exactas Admin -->
            <nav class="ar-tabs" role="tablist" aria-label="Secciones del repositorio">
                <button type="button" class="active" id="tabProjects" role="tab" aria-selected="true" aria-controls="panelProjects">
                    <i class="fa-solid fa-diagram-project"></i>
                    <span class="ar-tab-label">Proyectos publicados</span>
                    <span class="ar-tab-count" id="badgeProjectsCount"><?= count($projects) ?></span>
                </button>
                <button type="button" id="tabSupport" role="tab" aria-selected="false" aria-controls="panelSupport">
                    <i class="fa-solid fa-folder-open"></i>
                    <span class="ar-tab-label">Material de apoyo</span>
                    <span class="ar-tab-count" id="badgeSupportCount"><?= count($supportDocuments) ?></span>
                </button>
            </nav>

            <!-- Toolbar de proyectos (hermano directo de ar-panel, como en Admin) -->
            <div class="ar-tools" id="toolsProjects">
                <label class="ar-search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input id="repositorySearch" type="text" role="searchbox" placeholder="Buscar en el repositorio..." autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">
                    <button id="repositoryClearSearch" type="button" aria-label="Limpiar búsqueda" hidden>
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </label>

                <label class="ar-filter-control">
                    <span>Tipo</span>
                    <select id="repositoryType">
                        <option value="all">Todos</option>
                        <?php foreach ($projectTypes as $type): ?>
                            <option value="<?= e($type['value']) ?>"><?= e($type['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <?php if (count($academicPeriods) === 1): ?>
                    <div class="ar-filter-control ar-fixed-filter">
                        <span>Período académico</span>
                        <div>
                            <i class="fa-regular fa-calendar"></i>
                            <strong><?= e($academicPeriods[0]['label']) ?></strong>
                        </div>
                        <input id="repositoryPao" type="hidden" value="<?= e($academicPeriods[0]['value']) ?>">
                    </div>
                <?php elseif (count($academicPeriods) > 1): ?>
                    <label class="ar-filter-control">
                        <span>Período académico</span>
                        <select id="repositoryPao">
                            <option value="all">Todos</option>
                            <?php foreach ($academicPeriods as $period): ?>
                                <option value="<?= e($period['value']) ?>"><?= e($period['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php else: ?>
                    <div class="ar-filter-control ar-fixed-filter">
                        <span>Período académico</span>
                        <div>
                            <i class="fa-regular fa-calendar"></i>
                            <strong>Sin períodos disponibles</strong>
                        </div>
                        <input id="repositoryPao" type="hidden" value="all">
                    </div>
                <?php endif; ?>
            </div>

            <!-- Toolbar de materiales (se muestra solo en pestaña de materiales) -->
            <div class="ar-tools" id="toolsSupport" hidden>
                <label class="ar-search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input id="repositorySupportSearch" type="text" role="searchbox" placeholder="Buscar por título, tipo, PAO o palabra clave..." autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">
                </label>
                <label class="ar-filter-control">
                    <span>Categoría</span>
                    <select id="repositorySupportCategory">
                        <option value="all">Todas</option>
                        <option value="vinculacion">Vinculación</option>
                        <option value="practicas">Prácticas</option>
                        <option value="tesis">Tesis</option>
                        <option value="proyecto-pis">Proyectos PIS</option>
                    </select>
                </label>
            </div>

            <!-- Panel 1: Proyectos publicados -->
            <section
                class="ar-panel"
                id="panelProjects"
                role="tabpanel"
                aria-labelledby="tabProjects"
                data-favorite-url="<?= e($favoriteActionUrl) ?>"
                data-favorite-csrf="<?= e($favoriteCsrfToken) ?>"
            >
                <!-- Encabezado de sección exacto Admin -->
                <header class="ar-section-head">
                    <div><span>Catálogo institucional</span><h2>Proyectos publicados</h2></div>
                    <p><strong id="repositoryCount"><?= count($projects) ?></strong> resultados visibles</p>
                </header>

                <!-- Grilla de proyectos con tarjetas exactas Admin -->
                <div class="ar-grid" id="repositoryGrid">
                    <?php foreach ($projects as $project): ?>
                        <?php
                        $projectSearchText = implode(' ', [
                            $project['code'] ?? sprintf('PRJ-%03d', $project['id']),
                            $project['title'],
                            $project['description'],
                            $project['authors'],
                            $project['tutor'],
                            $project['type'],
                            $project['pao_label'],
                            $project['year'],
                            implode(' ', $project['technologies'] ?? []),
                            implode(' ', $project['keywords'] ?? [])
                        ]);
                        $typeSlug = $project['type_slug'] ?? 'tesis';
                        $typeCode = match ($typeSlug) {
                            'tesis' => 'thesis',
                            'perfil-tesis' => 'thesis_profile',
                            'practicas' => 'practice',
                            'proyecto-pis' => 'pis',
                            'vinculacion' => 'community',
                            default => $typeSlug,
                        };
                        ?>
                        <article
                            class="ar-project-card repository-card repository-project-card"
                            tabindex="0"
                            role="link"
                            aria-label="Explorar proyecto <?= e($project['title']) ?>"
                            data-project-id="<?= e((string) $project['id']) ?>"
                            data-project-url="<?= e($project['detail_url']) ?>"
                            data-project-search="<?= e(mb_strtolower($projectSearchText, 'UTF-8')) ?>"
                            data-favorite="<?= !empty($project['is_favorite']) ? 'true' : 'false' ?>"
                            data-type="<?= e($typeSlug) ?>"
                            data-type-code="<?= e($typeCode) ?>"
                            data-pao="<?= e($project['pao'] ?? '') ?>"
                        >
                            <header>
                                <span class="ar-code"><?= e($project['code'] ?? sprintf('PRJ-%03d', $project['id'])) ?></span>
                                <span class="ar-project-type"><?= e($project['type']) ?></span>
                            </header>
                            <div class="ar-card-copy">
                                <h3 title="<?= e($project['title']) ?>"><?= e($project['title']) ?></h3>
                                <dl>
                                    <div><dt><i class="fa-solid fa-users"></i> Autores</dt><dd><?= e($project['authors'] ?: 'Sin autores registrados') ?></dd></div>
                                    <div><dt><i class="fa-solid fa-user-tie"></i> Tutor</dt><dd><?= e($project['tutor'] ?: 'Sin asignar') ?></dd></div>
                                    <div><dt><i class="fa-regular fa-calendar"></i> Período</dt><dd><?= e($project['pao_label']) ?></dd></div>
                                </dl>
                            </div>
                            <div class="ar-card-meta">
                                <span><i class="fa-regular fa-file-lines"></i> <?= (int) ($project['file_count'] ?? 1) ?> <?= ((int) ($project['file_count'] ?? 1)) === 1 ? 'documento' : 'documentos' ?></span>
                                <span><i class="fa-solid fa-globe"></i> <?= e(!empty($project['published_at']) ? $project['published_at'] : $project['year']) ?></span>
                            </div>
                            <footer>
                                <a class="ar-primary-action" href="<?= e($project['detail_url']) ?>">
                                    <i class="fa-solid fa-diagram-project"></i> Abrir expediente
                                </a>
                                <button
                                    class="repository-favorite-btn ar-icon-action<?= !empty($project['is_favorite']) ? ' is-favorite' : '' ?>"
                                    type="button"
                                    aria-label="<?= !empty($project['is_favorite']) ? 'Eliminar de favoritos' : 'Guardar en favoritos' ?>: <?= e($project['title']) ?>"
                                    aria-pressed="<?= !empty($project['is_favorite']) ? 'true' : 'false' ?>"
                                    title="<?= !empty($project['is_favorite']) ? 'Eliminar de favoritos' : 'Guardar en favoritos' ?>"
                                    style="margin-left: auto;"
                                >
                                    <i class="<?= !empty($project['is_favorite']) ? 'fa-solid' : 'fa-regular' ?> fa-heart" aria-hidden="true"></i>
                                </button>
                            </footer>
                        </article>
                    <?php endforeach; ?>
                </div>

                <!-- Estado vacío exacto Admin -->
                <div class="ar-empty" id="repositoryEmpty" hidden>
                    <span><i class="fa-solid fa-book-open"></i></span>
                    <h2 id="repositoryEmptyTitle">Aún no existen proyectos publicados.</h2>
                    <p id="repositoryEmptyText">Los proyectos aprobados aparecerán aquí después de completar su publicación.</p>
                </div>

                <!-- Paginación exacta Admin (Se oculta automáticamente si totalPages <= 1) -->
                <footer class="ar-pagination" id="repositoryPagination" hidden>
                    <span id="repositoryPaginationSummary">Mostrando 0 de 0</span>
                    <nav id="repositoryPaginationPages" aria-label="Paginación de proyectos publicados">
                        <button type="button" id="repositoryPagePrevious" disabled><i class="fa-solid fa-chevron-left"></i> Anterior</button>
                        <span id="repositoryPageInfo" aria-live="polite">Página 1 de 1</span>
                        <button type="button" id="repositoryPageNext">Siguiente <i class="fa-solid fa-chevron-right"></i></button>
                    </nav>
                </footer>
            </section>

            <!-- Panel 2: Material de apoyo -->
            <section class="ar-panel" id="panelSupport" role="tabpanel" aria-labelledby="tabSupport" hidden>
                <header class="ar-section-head">
                    <div><span>Recursos académicos</span><h2>Material de apoyo</h2></div>
                    <p id="repositorySupportCount" aria-live="polite"><?= count($supportDocuments) ?> <?= count($supportDocuments) === 1 ? 'resultado visible' : 'resultados visibles' ?></p>
                </header>

                <div class="repository-carousel-stage">
                    <button class="repository-carousel-btn repository-carousel-prev" id="repositorySupportPrev" type="button" aria-label="Ver documentos anteriores">
                        <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                    </button>

                    <div class="ar-grid" id="repositorySupportGrid">
                        <?php foreach ($supportDocuments as $document): ?>
                            <article class="ar-material-card"
                                data-category-slug="<?= e($document['category_slug']) ?>"
                                data-support-text="<?= e($document['title'] . ' ' . $document['description'] . ' ' . $document['type'] . ' ' . $document['year'] . ' ' . $document['pao_label'] . ' ' . $document['category_label'] . ' ' . implode(' ', $document['keywords'])) ?>"
                                data-support-category="<?= e($document['category_slug']) ?>"
                            >
                                <header>
                                    <span class="ar-material-icon"><i class="fa-regular fa-file-lines"></i></span>
                                    <div><span><?= e($document['type']) ?></span><strong><?= e($document['category_label']) ?></strong></div>
                                    <span class="ar-available">Disponible</span>
                                </header>
                                <div class="ar-card-copy">
                                    <h3 title="<?= e($document['title']) ?>"><?= e($document['title']) ?></h3>
                                    <p><?= e($document['description']) ?></p>
                                </div>
                                <div class="ar-card-meta">
                                    <span><i class="fa-regular fa-calendar"></i> <?= e(!empty($document['publication_date']) ? $document['publication_date'] : $document['year']) ?></span>
                                    <span><i class="fa-solid fa-download"></i> <?= number_format((int) $document['downloads'], 0, ',', '.') ?> descargas</span>
                                </div>
                                <footer>
                                    <a class="ar-primary-action" href="<?= e($document['detail_url']) ?>"><i class="fa-regular fa-eye"></i> Ver detalle</a>
                                </footer>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <button class="repository-carousel-btn repository-carousel-next" id="repositorySupportNext" type="button" aria-label="Ver documentos siguientes">
                        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="ar-empty" id="repositorySupportEmpty" <?= $supportDocuments ? 'hidden' : '' ?>>
                    <span><i class="fa-solid fa-folder-open"></i></span>
                    <h2>Aún no existen materiales de apoyo.</h2>
                    <p>Los recursos institucionales publicados aparecerán en esta sección.</p>
                </div>
            </section>

            <!-- Toast de notificaciones de favorito -->
            <div class="repository-toast" id="repositoryToast" role="status" aria-live="polite" aria-atomic="true" hidden></div>
        </main>
    </div>
</div>
