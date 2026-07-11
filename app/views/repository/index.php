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
            <span class="repository-count" id="repositorySupportCount"><?= count($supportDocuments) ?> documentos</span>
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

        <div class="repository-section-divider" aria-hidden="true"></div>

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

                    <div class="repository-keywords">
                        <?php foreach ($document['keywords'] as $keyword): ?>
                            <span><?= e($keyword) ?></span>
                        <?php endforeach; ?>
                    </div>

                    <button class="open-btn repository-open-btn" type="button">
                        Ver documento
                        <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
    <!-- Final de documentos de apoyo -->

    <!-- Inicio de catálogo institucional -->
    <section class="repository-results" aria-labelledby="repositoryResultsTitle">
        <div class="section-heading repository-results-heading">
            <div>
                <span class="section-eyebrow">Catálogo institucional</span>
                <h2 class="section-title" id="repositoryResultsTitle">Proyectos disponibles</h2>
            </div>
            <span class="repository-count" id="repositoryCount"><?= count($projects) ?> resultados</span>
        </div>

        <div class="repository-section-divider" aria-hidden="true"></div>

        <div class="repository-support-tools repository-filter-row">
            <div class="repository-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input id="repositorySearch" type="search" placeholder="Buscar por título, autor o palabra clave" aria-label="Buscar proyectos">
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

        <div class="repository-section-divider" aria-hidden="true"></div>

        <div class="repository-grid" id="repositoryGrid">
            <?php foreach ($projects as $project): ?>
                <article class="repository-card" data-semester="<?= e($project['semester']) ?>" data-teacher="<?= e($project['teacher_slug']) ?>" data-category="<?= e($project['category_slug']) ?>" data-type="<?= e(strtolower(str_replace([' ', 'á', 'é', 'í', 'ó', 'ú', 'ñ'], ['-', 'a', 'e', 'i', 'o', 'u', 'n'], $project['type']))) ?>" data-pao="<?= e($project['pao']) ?>">
                    <div class="repository-card-top">
                        <span class="repository-document-icon"><i class="fa-solid fa-file-lines"></i></span>
                        <span class="project-status approved">Publicado</span>
                    </div>
                    <span class="repository-type"><?= e($project['category']) ?> · <?= e($project['pao_label']) ?></span>
                    <h3><?= e($project['title']) ?></h3>
                    <p><?= e($project['description']) ?></p>

                    <div class="repository-meta">
                        <span><i class="fa-solid fa-graduation-cap"></i> <?= e($project['career']) ?></span>
                        <span><i class="fa-solid fa-layer-group"></i> <?= e($project['semester']) ?>.° semestre</span>
                        <span><i class="fa-solid fa-user-group"></i> <?= e($project['authors']) ?></span>
                        <span><i class="fa-solid fa-chalkboard-user"></i> <?= e($project['tutor']) ?></span>
                    </div>

                    <div class="repository-keywords">
                        <?php foreach ($project['keywords'] as $keyword): ?>
                            <span><?= e($keyword) ?></span>
                        <?php endforeach; ?>
                    </div>

                    <button class="open-btn repository-open-btn" type="button">
                        Ver documento
                        <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="repository-empty" id="repositoryEmpty" hidden>
            <i class="fa-solid fa-folder-open"></i>
            <h3>No se encontraron proyectos</h3>
            <p>Prueba con otros términos o modifica los filtros seleccionados.</p>
        </div>
    </section>
    <!-- Final de catálogo institucional -->
</div>
