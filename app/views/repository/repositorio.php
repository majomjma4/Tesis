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
<!-- Inicio de cabecera del repositorio -->
<section class="repository-hero">
    <div>
        <span class="section-eyebrow">CARRERA DE DESARROLLO DE SOFTWARE</span>
        <h2>Repositorio de proyectos finalizados</h2>
        <p>Consulta trabajos desarrollados por estudiantes y accede a sus documentos académicos.</p>
    </div>
    <div class="repository-hero-icon" aria-hidden="true">
        <i class="fa-solid fa-book-open-reader"></i>
    </div>
</section>
<!-- Final de cabecera del repositorio -->

<div class="dashboard-container repository-container">
    <!-- Inicio de documentos de apoyo -->
    <section class="repository-support" aria-labelledby="repositorySupportTitle">
        <div class="section-heading repository-results-heading">
            <div>
                <span class="section-eyebrow">Material complementario</span>
                <h2 class="section-title" id="repositorySupportTitle">Guías y documentos de apoyo</h2>
            </div>
            <span class="repository-count" id="repositorySupportCount" aria-live="polite"><?= count($supportDocuments) ?> documentos</span>
        </div>

        <div class="repository-section-divider" aria-hidden="true"></div>

        <div class="repository-support-tools repository-support-row">
            <div class="repository-search repository-search-support">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input id="repositorySupportSearch" type="search" placeholder="Buscar guía o documento de apoyo" aria-label="Buscar documentos de apoyo">
            </div>

            <div class="repository-filters repository-support-category">
                <div class="repository-filter">
                    <span>Categoría</span>
                    <div class="repository-dropdown" data-dropdown>
                        <button class="repository-dropdown-trigger" type="button" data-dropdown-trigger aria-haspopup="listbox" aria-expanded="false">
                            <span data-dropdown-label>Todos</span>
                            <i class="fa-solid fa-chevron-down"></i>
                        </button>
                        <input id="repositorySupportCategory" type="hidden" value="all" data-dropdown-input>
                        <div class="repository-dropdown-menu" role="listbox" tabindex="-1" hidden data-dropdown-menu>
                            <button type="button" class="repository-dropdown-option is-selected" data-dropdown-option data-value="all">Todos</button>
                            <button type="button" class="repository-dropdown-option" data-dropdown-option data-value="vinculacion">Vinculación</button>
                            <button type="button" class="repository-dropdown-option" data-dropdown-option data-value="practicas">Prácticas</button>
                            <button type="button" class="repository-dropdown-option" data-dropdown-option data-value="tesis">Tesis</button>
                            <button type="button" class="repository-dropdown-option" data-dropdown-option data-value="proyecto-pis">Proyectos PIS</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="repository-section-divider" aria-hidden="true"></div>

        <div class="repository-carousel-topbar" aria-label="Opciones de documentos de apoyo">
            <a class="repository-carousel-more" id="repositorySupportMore" href="<?= e($supportMaterialsUrl) ?>">
                Ver más
                <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>

        <div class="repository-section-divider" aria-hidden="true"></div>

        <div class="repository-carousel-stage">
            <button class="repository-carousel-btn repository-carousel-prev" id="repositorySupportPrev" type="button" aria-label="Ver documentos anteriores">
                <i class="fa-solid fa-chevron-left"></i>
            </button>

            <div class="repository-grid repository-support-grid" id="repositorySupportGrid">
            <?php foreach ($supportDocuments as $document): ?>
                <article class="repository-card repository-support-card" data-support-text="<?= e($document['title'] . ' ' . $document['description'] . ' ' . $document['type'] . ' ' . $document['year'] . ' ' . $document['pao_label'] . ' ' . $document['category_label'] . ' ' . implode(' ', $document['keywords'])) ?>" data-support-category="<?= e($document['category_slug']) ?>">
                    <div class="repository-card-top">
                        <span class="repository-document-icon"><i class="fa-solid fa-file-circle-check"></i></span>
                        <span class="project-status approved">Disponible</span>
                    </div>
                    <span class="repository-type"><?= e($document['type']) ?> · <?= e($document['pao_label']) ?></span>
                    <h3><?= e($document['title']) ?></h3>
                    <p><?= e($document['description']) ?></p>

                    <div class="repository-meta">
                        <span><i class="fa-solid fa-calendar-days"></i> <?= e($document['year']) ?></span>
                        <span><i class="fa-solid fa-folder-open"></i> Documento de apoyo</span>
                    </div>

                    <span hidden><?= e(implode(' ', $document['keywords'])) ?></span>

                    <div class="repository-card-actions">
                        <a class="open-btn repository-open-btn" href="<?= e($document['detail_url']) ?>">
                            Ver documento
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>

                <article class="repository-card repository-support-card repository-more-card" id="repositorySupportMoreCard" hidden>
                    <div class="repository-more-card-icon" aria-hidden="true">
                        <i class="fa-solid fa-folder-plus"></i>
                    </div>
                    <span class="repository-type">Más documentos disponibles</span>
                    <h3>¿Quieres ver más?</h3>
                    <p>Este carrusel muestra un máximo de documentos. Accede a la vista completa para consultar todo el material de apoyo disponible.</p>
                    <a class="open-btn repository-open-btn" href="<?= e($supportMaterialsUrl) ?>">Consultar materiales <i class="fa-solid fa-arrow-right"></i></a>
                </article>
            </div>

            <button class="repository-carousel-btn repository-carousel-next" id="repositorySupportNext" type="button" aria-label="Ver documentos siguientes">
                <i class="fa-solid fa-chevron-right"></i>
            </button>
        </div>

    </section>
    <!-- Final de documentos de apoyo -->

    <!-- Inicio de catálogo institucional -->
    <section
        class="repository-results"
        aria-labelledby="repositoryResultsTitle"
        data-favorite-url="<?= e($favoriteActionUrl) ?>"
        data-favorite-csrf="<?= e($favoriteCsrfToken) ?>"
    >
        <div class="section-heading repository-results-heading">
            <div>
                <span class="section-eyebrow">Catálogo institucional</span>
                <h2 class="section-title" id="repositoryResultsTitle">Proyectos disponibles</h2>
            </div>
            <div class="repository-catalog-summary">
                <span class="repository-count" id="repositoryCount" aria-live="polite">Mostrando <?= count($projects) ?> de <?= count($projects) ?> proyectos</span>
                <button class="repository-favorites-filter" id="repositoryFavoritesFilter" type="button" aria-pressed="false">
                    <i class="fa-regular fa-heart"></i>
                    <span>Favoritos</span>
                    (<span id="repositoryFavoritesCount"><?= count(array_filter($projects, static fn (array $project): bool => $project['is_favorite'])) ?></span>)
                </button>
            </div>
        </div>

        <div class="repository-section-divider" aria-hidden="true"></div>

        <div class="repository-support-tools repository-filter-row">
            <div class="repository-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input id="repositorySearch" type="search" placeholder="Buscar por proyecto, autor, tutor o tecnología" aria-label="Buscar proyectos">
            </div>

            <div class="repository-filters repository-filters-inline">
                <div class="repository-filter">
                    <span>Semestre</span>
                    <div class="repository-dropdown" data-dropdown>
                        <button class="repository-dropdown-trigger" type="button" data-dropdown-trigger aria-haspopup="listbox" aria-expanded="false">
                            <span data-dropdown-label>Todos</span>
                            <i class="fa-solid fa-chevron-down"></i>
                        </button>
                        <input id="repositorySemester" type="hidden" value="all" data-dropdown-input>
                        <div class="repository-dropdown-menu" role="listbox" tabindex="-1" hidden data-dropdown-menu>
                            <button type="button" class="repository-dropdown-option is-selected" data-dropdown-option data-value="all">Todos</button>
                            <?php foreach ($semesters as $semester): ?>
                                <button type="button" class="repository-dropdown-option" data-dropdown-option data-value="<?= e($semester['value']) ?>"><?= e($semester['label']) ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="repository-filter">
                    <span>Docente</span>
                    <div class="repository-dropdown" data-dropdown>
                        <button class="repository-dropdown-trigger" type="button" data-dropdown-trigger aria-haspopup="listbox" aria-expanded="false">
                            <span data-dropdown-label>Todos</span>
                            <i class="fa-solid fa-chevron-down"></i>
                        </button>
                        <input id="repositoryTeacher" type="hidden" value="all" data-dropdown-input>
                        <div class="repository-dropdown-menu" role="listbox" tabindex="-1" hidden data-dropdown-menu>
                            <button type="button" class="repository-dropdown-option is-selected" data-dropdown-option data-value="all">Todos</button>
                            <?php foreach ($teachers as $teacher): ?>
                                <button type="button" class="repository-dropdown-option" data-dropdown-option data-value="<?= e($teacher['value']) ?>"><?= e($teacher['label']) ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="repository-filter">
                    <span>PAO</span>
                    <div class="repository-dropdown" data-dropdown>
                        <button class="repository-dropdown-trigger" type="button" data-dropdown-trigger aria-haspopup="listbox" aria-expanded="false">
                            <span data-dropdown-label>Todos</span>
                            <i class="fa-solid fa-chevron-down"></i>
                        </button>
                        <input id="repositoryPao" type="hidden" value="all" data-dropdown-input>
                        <div class="repository-dropdown-menu" role="listbox" tabindex="-1" hidden data-dropdown-menu>
                            <button type="button" class="repository-dropdown-option is-selected" data-dropdown-option data-value="all">Todos</button>
                            <?php foreach ($academicPeriods as $period): ?>
                                <button type="button" class="repository-dropdown-option" data-dropdown-option data-value="<?= e($period['value']) ?>"><?= e($period['label']) ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="repository-filter">
                    <span>Tipo</span>
                    <div class="repository-dropdown" data-dropdown>
                        <button class="repository-dropdown-trigger" type="button" data-dropdown-trigger aria-haspopup="listbox" aria-expanded="false">
                            <span data-dropdown-label>Todos</span>
                            <i class="fa-solid fa-chevron-down"></i>
                        </button>
                        <input id="repositoryType" type="hidden" value="all" data-dropdown-input>
                        <div class="repository-dropdown-menu" role="listbox" tabindex="-1" hidden data-dropdown-menu>
                            <button type="button" class="repository-dropdown-option is-selected" data-dropdown-option data-value="all">Todos</button>
                            <?php foreach ($projectTypes as $type): ?>
                                <button type="button" class="repository-dropdown-option" data-dropdown-option data-value="<?= e($type['value']) ?>"><?= e($type['label']) ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="repository-filter-actions">
            <button class="repository-clear-filters" id="repositoryClearFilters" type="button">
                <i class="fa-solid fa-rotate-left"></i>
                Limpiar filtros
            </button>
        </div>

        <div class="repository-section-divider" aria-hidden="true"></div>

        <div class="repository-grid" id="repositoryGrid">
            <?php foreach ($projects as $project): ?>
                <?php
                $visibleTechnologies = array_slice($project['technologies'], 0, 4);
                $remainingTechnologies = max(0, count($project['technologies']) - count($visibleTechnologies));
                $projectSearchText = implode(' ', [
                    $project['title'],
                    $project['description'],
                    $project['authors'],
                    $project['tutor'],
                    $project['type'],
                    $project['pao_label'],
                    $project['year'],
                    implode(' ', $project['technologies']),
                    implode(' ', $project['keywords'])
                ]);
                ?>
                <article
                    class="repository-card repository-project-card repository-project-card--<?= e($project['type_slug']) ?>"
                    tabindex="0"
                    role="link"
                    aria-label="Explorar proyecto <?= e($project['title']) ?>"
                    data-project-id="<?= e((string) $project['id']) ?>"
                    data-project-url="<?= e($project['detail_url']) ?>"
                    data-project-search="<?= e($projectSearchText) ?>"
                    data-favorite="<?= $project['is_favorite'] ? 'true' : 'false' ?>"
                    data-semester="<?= e($project['semester']) ?>"
                    data-teacher="<?= e($project['teacher_slug']) ?>"
                    data-category="<?= e($project['category_slug']) ?>"
                    data-type="<?= e($project['type_slug']) ?>"
                    data-pao="<?= e($project['pao']) ?>"
                >
                    <div class="repository-card-top">
                        <span class="repository-document-icon"><i class="fa-solid fa-file-lines"></i></span>
                        <div class="repository-card-top-actions">
                            <span class="project-status approved">Publicado</span>
                            <button
                                class="repository-favorite-btn<?= $project['is_favorite'] ? ' is-favorite' : '' ?>"
                                type="button"
                                aria-label="<?= $project['is_favorite'] ? 'Eliminar de favoritos' : 'Guardar en favoritos' ?>: <?= e($project['title']) ?>"
                                aria-pressed="<?= $project['is_favorite'] ? 'true' : 'false' ?>"
                                title="<?= $project['is_favorite'] ? 'Eliminar de favoritos' : 'Guardar en favoritos' ?>"
                            >
                                <i class="<?= $project['is_favorite'] ? 'fa-solid' : 'fa-regular' ?> fa-heart"></i>
                            </button>
                        </div>
                    </div>
                    <span class="repository-type"><?= e($project['type']) ?> · <?= e($project['pao_label']) ?></span>
                    <h3><?= e($project['title']) ?></h3>
                    <p><?= e($project['description']) ?></p>

                    <div class="repository-meta">
                        <span><i class="fa-solid fa-graduation-cap"></i> <?= e($project['career']) ?></span>
                        <span><i class="fa-solid fa-layer-group"></i> <?= e($project['semester']) ?>.° semestre</span>
                        <span><i class="fa-solid fa-user-group"></i> <?= e($project['authors']) ?></span>
                        <span><i class="fa-solid fa-chalkboard-user"></i> <?= e($project['tutor']) ?></span>
                    </div>

                    <div class="repository-technologies" aria-label="Tecnologías utilizadas">
                        <?php foreach ($visibleTechnologies as $technology): ?>
                            <span><?= e($technology) ?></span>
                        <?php endforeach; ?>
                        <?php if ($remainingTechnologies > 0): ?>
                            <span class="repository-technologies-more">+<?= $remainingTechnologies ?></span>
                        <?php endif; ?>
                    </div>

                    <span class="repository-downloads">
                        <i class="fa-solid fa-arrow-down"></i>
                        <?= number_format((int) $project['downloads'], 0, ',', '.') ?> descargas
                    </span>

                    <div class="repository-card-actions">
                        <span class="open-btn repository-open-btn" aria-hidden="true">
                            Explorar proyecto
                            <i class="fa-solid fa-arrow-right"></i>
                        </span>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="repository-empty" id="repositoryEmpty" hidden>
            <i class="fa-solid fa-folder-open"></i>
            <h3 id="repositoryEmptyTitle">No se encontraron proyectos</h3>
            <p id="repositoryEmptyText">Prueba con otros términos o modifica los filtros seleccionados.</p>
            <button class="repository-empty-action" id="repositoryShowAllProjects" type="button" hidden>Ver todos los proyectos</button>
        </div>

        <nav class="repository-pagination" id="repositoryPagination" aria-label="Paginación de proyectos" hidden>
            <button type="button" id="repositoryPagePrevious"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i> Anterior</button>
            <span id="repositoryPageInfo" aria-live="polite">Página 1 de 1</span>
            <button type="button" id="repositoryPageNext">Siguiente <i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
        </nav>

        <div class="repository-toast" id="repositoryToast" role="status" aria-live="polite" aria-atomic="true" hidden></div>
    </section>
    <!-- Final de catálogo institucional -->
</div>
</div>
