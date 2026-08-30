<?php
$publishedProjects = array_values(array_filter(
    $repositoryProjects,
    static fn (array $project): bool => $project['status'] === 'published'
));
$activeSupportMaterials = array_values(array_filter(
    $supportMaterials,
    static fn (array $material): bool => ($material['status_key'] ?? '') === 'published'
));
$catalogSupportMaterials = $activeSupportMaterials;
$withdrawnProjects = array_values($withdrawnPublications);
$withdrawnSupportMaterials = array_values($withdrawnMaterials);
$withdrawnTotal = count($withdrawnProjects) + count($withdrawnSupportMaterials);
$repositorySectionErrors = (array) ($repositorySectionErrors ?? []);
$projectLoadFailed = isset($repositorySectionErrors['projects']);
$materialLoadFailed = isset($repositorySectionErrors['materials']);
$withdrawnLoadFailed = isset($repositorySectionErrors['withdrawn_projects']) || isset($repositorySectionErrors['withdrawn_materials']);
$withdrawnError = $repositorySectionErrors['withdrawn_projects'] ?? $repositorySectionErrors['withdrawn_materials'] ?? null;
$summaryLoadFailed = isset($repositorySectionErrors['summary']);
$countOrDash = static fn (mixed $value, bool $failed = false): string => $failed || $value === null ? '—' : (string) (int) $value;
$categoryBySlug = [];
foreach ($materialCategories as $category) $categoryBySlug[(string)$category['slug']] = $category;
$functionalMaterialCategories = [
    ['slug'=>(string)($categoryBySlug['tesis']['slug']??'tesis'),'name'=>'Tesis'],
    ['slug'=>(string)($categoryBySlug['perfil-tesis']['slug']??'perfil-tesis'),'name'=>'Perfil de tesis'],
    ['slug'=>(string)($categoryBySlug['proyecto-pis']['slug']??'proyecto-pis'),'name'=>'Proyectos PIS'],
    ['slug'=>(string)($categoryBySlug['practicas']['slug']??'practicas'),'name'=>'Prácticas preprofesionales'],
    ['slug'=>(string)($categoryBySlug['vinculacion']['slug']??'vinculacion'),'name'=>'Vinculación'],
];
$formatDate = static function (?string $date): string {
    if (!$date) return 'Sin fecha registrada';
    return (new DateTimeImmutable($date))->format('d/m/Y');
};
$formatProjectType = static function (array $project): string {
    $labels = [
        'pis' => 'Proyecto PIS',
        'thesis' => 'Titulación',
        'thesis_profile' => 'Perfil de tesis',
        'community' => 'Proyecto de vinculación',
        'practice' => 'Prácticas',
    ];
    $code = strtolower(trim((string) ($project['type_code'] ?? '')));
    return $labels[$code] ?? (string) ($project['type_name'] ?? '');
};
?>

<div class="ar-page" id="arPage">
    <header class="ar-head">
        <div>
            <span class="ar-eyebrow">Biblioteca digital institucional</span>
            <h1>Repositorio institucional</h1>
            <p>Administra el contenido publicado que estará disponible para estudiantes y docentes.</p>
        </div>
    </header>

    <?php if ($repositoryError): ?>
        <p class="ar-error" role="alert"><?= e($repositoryError) ?></p>
    <?php endif; ?>

    <section class="ar-stats" aria-label="Resumen del repositorio">
        <article class="ar-stat ar-stat-projects">
            <span class="ar-stat-icon"><i class="fa-solid fa-book-open"></i></span>
            <div><strong><?= e($countOrDash($repositorySummary['published'] ?? null, $summaryLoadFailed)) ?></strong><span>Proyectos publicados</span></div>
        </article>
        <article class="ar-stat ar-stat-materials">
            <span class="ar-stat-icon"><i class="fa-solid fa-file-lines"></i></span>
            <div><strong><?= e($countOrDash($materialLoadFailed ? null : count($catalogSupportMaterials), $materialLoadFailed)) ?></strong><span>Materiales de apoyo</span></div>
        </article>
        <a class="ar-stat ar-stat-pending" data-pending-publication-card data-base-url="<?= e(route('projects')) ?>" aria-disabled="true" tabindex="-1" aria-label="No hay proyectos pendientes de publicación">
            <span class="ar-stat-icon"><i class="fa-regular fa-clock"></i></span>
            <div><strong data-pending-publication-count><?= e($countOrDash($repositorySummary['pending'] ?? null, $summaryLoadFailed)) ?></strong><span>Pendientes de publicación</span></div>
        </a>
        <script id="arPendingByPeriod" type="application/json"><?= json_encode($repositorySummary['pending_by_period'] ?? [], JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
    </section>

    <?php foreach (['summary', 'catalogs', 'material_categories'] as $sectionKey): ?>
        <?php if (isset($repositorySectionErrors[$sectionKey])): ?><p class="ar-error" role="alert"><?= e($repositorySectionErrors[$sectionKey]) ?></p><?php endif; ?>
    <?php endforeach; ?>

    <section class="ar-catalog">
        <nav class="ar-tabs" aria-label="Contenido del repositorio">
            <button class="active" type="button" data-repository-tab="projects" aria-selected="true">
                <i class="fa-solid fa-diagram-project"></i>
                <span class="ar-tab-label">Proyectos publicados</span>
                <span class="ar-tab-count"><?= e($countOrDash($repositorySummary['published'] ?? null, $summaryLoadFailed)) ?></span>
            </button>
            <button type="button" data-repository-tab="materials" aria-selected="false">
                <i class="fa-solid fa-folder-open"></i>
                <span class="ar-tab-label">Material de apoyo</span>
                <span class="ar-tab-count"><?= count($catalogSupportMaterials) ?></span>
            </button>
            <?php if ($withdrawnTotal > 0): ?>
                <button type="button" data-repository-tab="withdrawn" aria-selected="false">
                    <i class="fa-solid fa-box-archive"></i>
                    <span class="ar-tab-label">Retirados</span>
                    <span class="ar-tab-count"><?= $withdrawnTotal ?></span>
                </button>
            <?php endif; ?>
        </nav>

        <div class="ar-tools admin-filter-bar">
            <label class="ar-search admin-filter-search admin-filter-item-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input id="arSearch" type="search" placeholder="Buscar por título, código, autor o tutor" autocomplete="off">
                <button id="arClearSearch" type="button" aria-label="Limpiar búsqueda" hidden>
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </label>
            <label class="ar-filter-control admin-filter-control" data-filter-for="projects">
                <span>Tipo</span>
                <select id="arTypeFilter">
                    <option value="">Todos</option>
                    <?php foreach ($repositoryCatalogs['types'] as $type): ?>
                        <option value="<?= e(mb_strtolower($type['name'], 'UTF-8')) ?>"><?= e($type['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php if (count($repositoryCatalogs['periods']) === 0): ?>
                <div class="ar-filter-control ar-fixed-filter admin-filter-control admin-filter-fixed" data-filter-for="projects">
                    <span>Período académico</span>
                    <div><i class="fa-regular fa-calendar"></i><strong>Sin períodos disponibles</strong></div>
                    <input id="arPeriodFilter" type="hidden" value="" data-fixed-filter data-period-id="">
                </div>
            <?php elseif (count($repositoryCatalogs['periods']) === 1): ?>
                <div class="ar-filter-control ar-fixed-filter admin-filter-control admin-filter-fixed" data-filter-for="projects">
                    <span>Período académico</span>
                    <div><i class="fa-regular fa-calendar"></i><strong><?= e($repositoryCatalogs['periods'][0]['name']) ?></strong></div>
                    <input id="arPeriodFilter" type="hidden" value="<?= e(mb_strtolower($repositoryCatalogs['periods'][0]['name'], 'UTF-8')) ?>" data-fixed-filter data-period-id="<?= (int) $repositoryCatalogs['periods'][0]['id'] ?>">
                </div>
            <?php else: ?>
                <label class="ar-filter-control admin-filter-control" data-filter-for="projects">
                    <span>Período académico</span>
                    <select id="arPeriodFilter">
                        <option value="">Todos</option>
                        <?php foreach ($repositoryCatalogs['periods'] as $period): ?>
                            <option value="<?= e(mb_strtolower($period['name'], 'UTF-8')) ?>" data-period-id="<?= (int) $period['id'] ?>"><?= e($period['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
            <label class="ar-filter-control admin-filter-control" data-filter-for="materials" hidden>
                <span>Categoría</span>
                <select id="arCategoryFilter">
                    <option value="">Todos</option>
                    <?php foreach ($functionalMaterialCategories as $category): ?>
                        <option value="<?= e($category['slug']) ?>"><?= e($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="ar-filter-control admin-filter-control" data-filter-for="materials" hidden>
                <span>Estado</span>
                <select id="arMaterialStatusFilter">
                    <option value="all">Todos</option>
                    <option value="available">Disponibles</option>
                    <option value="unavailable">No disponibles</option>
                </select>
            </label>
            <button
                class="ar-clear-material-filters"
                id="arClearMaterialFilters"
                type="button"
                aria-label="Limpiar filtros de material de apoyo"
                hidden
            >
                <i class="fa-solid fa-filter-circle-xmark" aria-hidden="true"></i>
                <span>Limpiar</span>
            </button>
        </div>

        <section class="ar-panel" data-repository-panel="projects">
            <header class="ar-section-head">
                <div><span>Catálogo institucional</span><h2>Proyectos publicados</h2></div>
                <p><strong id="arProjectCount"><?= e($countOrDash($projectLoadFailed ? null : count($publishedProjects), $projectLoadFailed)) ?></strong> resultados visibles</p>
            </header>

            <div class="ar-grid" id="arProjectGrid">
                <?php foreach ($publishedProjects as $project): ?>
                    <?php
                    try {
                        $package = (new ProjectRepositoryPackageService())->describe(
                            (int) $project['id'],
                            route('repository-download') . '&id=' . (int) $project['id']
                        );
                        $project['package_available'] = !empty($package['available']);
                        $project['package_download_url'] = (string) ($package['download_url'] ?? '');
                    } catch (Throwable $exception) {
                        error_log('Admin repository package descriptor: ' . $exception->getMessage());
                        $project['package_available'] = false;
                        $project['package_download_url'] = '';
                    }
                    ?>
                    <?php $projectSearch = implode(' ', [
                        $project['code'], $project['type_name'], $project['title'],
                        $project['authors'] ?: '', $project['tutor_name'] ?: '',
                        $project['period_name'],
                    ]); ?>
                    <article class="ar-project-card"
                        data-repository-item="projects"
                        data-project-available="<?= !empty($project['is_available']) ? '1' : '0' ?>"
                        data-type-code="<?= e($project['type_code']) ?>"
                        data-search="<?= e(mb_strtolower($projectSearch, 'UTF-8')) ?>"
                        data-type="<?= e(mb_strtolower($project['type_name'], 'UTF-8')) ?>"
                        data-period="<?= e(mb_strtolower($project['period_name'], 'UTF-8')) ?>">
                        <header>
                            <span class="ar-code"><?= e($project['code']) ?></span>
                            <span class="ar-project-type"><?= e($formatProjectType($project)) ?></span>
                        </header>
                        <div class="ar-card-copy">
                            <h3 title="<?= e($project['title']) ?>"><?= e($project['title']) ?></h3>
                            <dl>
                                <div><dt><i class="fa-solid fa-users"></i> Autores</dt><dd><?= e($project['authors'] ?: 'Sin autores registrados') ?></dd></div>
                                <div><dt><i class="fa-solid fa-user-tie"></i> Tutor</dt><dd><?= e($project['tutor_name'] ?: 'Sin asignar') ?></dd></div>
                                <div><dt><i class="fa-regular fa-calendar"></i> Período</dt><dd><?= e($project['period_name']) ?></dd></div>
                            </dl>
                        </div>
                        <?php if ((int) ($project['pending_adjustment_count'] ?? 0) > 0): ?>
                            <?php $pendingModificationCount = (int) $project['pending_adjustment_count']; ?>
                            <div class="ar-project-adjustment-notice" role="status">
                                <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                                <span><?= $pendingModificationCount ?> solicitud<?= $pendingModificationCount === 1 ? '' : 'es' ?> de modificación pendiente<?= $pendingModificationCount === 1 ? '' : 's' ?></span>
                                <a href="<?= e(route('repository-detail') . '&id=' . (int) $project['id'] . '&tab=information#projectAdjustmentListTitle') ?>">Gestionar</a>
                            </div>
                        <?php endif; ?>
                        <div class="ar-card-meta">
                            <span><i class="fa-regular fa-file-lines"></i> <?= (int) $project['file_count'] ?> documentos</span>
                            <span><i class="fa-solid fa-globe"></i> <?= e($formatDate($project['published_at'])) ?></span>
                        </div>
                        <footer>
                            <a class="ar-primary-action" href="<?= e(route('repository-detail') . '&id=' . (int) $project['id'] . '&tab=information&return=' . rawurlencode((string)($_SERVER['REQUEST_URI'] ?? route('admin-repository')))) ?>">
                                <i class="fa-solid fa-diagram-project"></i> Abrir expediente
                            </a>
                            <?php if (!empty($project['package_available']) && !empty($project['package_download_url'])): ?>
                                <a class="ar-icon-action" href="<?= e($project['package_download_url']) ?>" data-tooltip="Descargar ZIP" aria-label="Descargar ZIP completo de <?= e($project['title']) ?>">
                                    <i class="fa-solid fa-download" aria-hidden="true"></i>
                                </a>
                            <?php endif; ?>
                            <button class="ar-icon-action ar-availability-action" data-project-availability data-id="<?= (int) $project['id'] ?>" data-available="<?= !empty($project['is_available']) ? '1' : '0' ?>" data-tooltip="<?= !empty($project['is_available']) ? 'Marcar como no disponible' : 'Marcar como disponible' ?>" type="button" aria-label="<?= !empty($project['is_available']) ? 'Marcar proyecto como no disponible' : 'Marcar proyecto como disponible' ?>">
                                <i class="fa-solid <?= !empty($project['is_available']) ? 'fa-ban' : 'fa-circle-check' ?>"></i>
                            </button>
                            <button class="ar-icon-action ar-withdraw-action" data-publish="unpublish" data-id="<?= (int) $project['id'] ?>" data-tooltip="Retirar del repositorio" type="button" aria-label="Retirar del repositorio">
                                <i class="fa-solid fa-box-archive"></i>
                            </button>
                            <button class="ar-icon-action ar-trash-action" data-project-trash data-id="<?= (int) $project['id'] ?>" data-tooltip="Enviar a Papelera" type="button" aria-label="Enviar a Papelera">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </footer>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="ar-error" id="arProjectsError" role="alert" <?= $projectLoadFailed ? '' : 'hidden' ?>><?= e($repositorySectionErrors['projects'] ?? '') ?></div>
            <div class="ar-empty" id="arProjectsEmpty" <?= $publishedProjects || $projectLoadFailed ? 'hidden' : '' ?>>
                <span><i class="fa-solid fa-book-open"></i></span>
                <h2>Aún no existen proyectos publicados.</h2>
                <p>Los proyectos aprobados aparecerán aquí después de completar su publicación.</p>
            </div>
            <footer class="ar-pagination" data-pagination-for="projects" hidden>
                <span data-pagination-summary>Mostrando 0 de 0</span>
                <label>Mostrar
                    <select data-page-size>
                        <option value="10">10</option><option value="25">25</option><option value="50">50</option><option value="75">75</option><option value="100">100</option>
                    </select>
                </label>
                <nav data-pagination-pages aria-label="Paginación de proyectos publicados"></nav>
            </footer>
        </section>

        <section class="ar-panel" data-repository-panel="materials" hidden>
            <header class="ar-section-head">
                <div><span>Recursos académicos</span><h2>Material de apoyo</h2></div>
                <p id="arMaterialCountText"><?= e($materialLoadFailed ? '—' : count($catalogSupportMaterials) . ' ' . (count($catalogSupportMaterials) === 1 ? 'resultado visible' : 'resultados visibles')) ?></p>
            </header>

            <div class="ar-grid ar-material-grid" id="arMaterialGrid">
                <?php foreach ($catalogSupportMaterials as $material): ?>
                    <?php
                    $materialState = !empty($material['is_available']) ? 'available' : 'unavailable';
                    $materialStateLabel = match ($materialState) {
                        'unavailable' => 'No disponible',
                        default => 'Disponible',
                    };
                    $materialSearch = implode(' ', [
                        $material['title'], $material['category_label'], $material['description'],
                        $material['type'], $material['publisher'] ?? '', implode(' ', $material['keywords']),
                        $material['pao_label'] ?? '',
                    ]); ?>
                    <article class="ar-material-card"
                        data-repository-item="materials"
                        data-material-state="<?= e($materialState) ?>"
                        data-category-slug="<?= e($material['category_slug']) ?>"
                        data-search="<?= e(mb_strtolower($materialSearch, 'UTF-8')) ?>"
                        data-category="<?= e($material['category_slug']) ?>"
                        data-period="<?= e(mb_strtolower($material['pao_label'], 'UTF-8')) ?>">
                        <header>
                            <span class="ar-material-icon"><i class="fa-regular fa-file-lines"></i></span>
                            <div><span><?= e($material['type']) ?></span><strong><?= e($material['category_label']) ?></strong></div>
                            <span class="ar-available is-<?= e($materialState) ?>"><?= e($materialStateLabel) ?></span>
                        </header>
                        <div class="ar-card-copy">
                            <h3 title="<?= e($material['title']) ?>"><?= e($material['title']) ?></h3>
                            <p><?= e($material['description']) ?></p>
                        </div>
                        <div class="ar-card-meta">
                            <span><i class="fa-regular fa-calendar"></i> <?= e($material['publication_date']) ?></span>
                            <span><i class="fa-solid fa-download"></i> <?= number_format((int) $material['downloads'], 0, ',', '.') ?> descargas</span>
                        </div>
                        <footer>
                            <a class="ar-primary-action" href="<?= e(route('support-material-detail') . '&id=' . (int) $material['id']) ?>"><i class="fa-regular fa-eye"></i> Ver detalle</a>
                            <a class="ar-icon-action" href="<?= e(route('support-material-package-download') . '&material_id=' . (int) $material['id']) ?>" data-tooltip="Descargar ZIP" aria-label="Descargar ZIP completo de <?= e($material['title']) ?>">
                                <i class="fa-solid fa-download" aria-hidden="true"></i>
                            </a>
                            <button class="ar-icon-action ar-availability-action" type="button" data-material-availability data-available="<?= !empty($material['is_available']) ? '1' : '0' ?>" aria-label="<?= !empty($material['is_available']) ? 'Marcar material como no disponible' : 'Marcar material como disponible' ?>" data-tooltip="<?= !empty($material['is_available']) ? 'Marcar como no disponible' : 'Marcar como disponible' ?>"><i class="fa-solid <?= !empty($material['is_available']) ? 'fa-ban' : 'fa-circle-check' ?>"></i></button>
                            <button class="ar-icon-action ar-withdraw-action" type="button" data-withdraw-material aria-label="Retirar del repositorio" data-tooltip="Retirar del repositorio"><i class="fa-solid fa-box-archive"></i></button>
                            <button class="ar-icon-action ar-trash-action" type="button" data-trash-material aria-label="Enviar a Papelera" data-tooltip="Enviar a Papelera"><i class="fa-solid fa-trash-can"></i></button>
                        </footer>
                        <script type="application/json" data-material-json><?= json_encode([
                            'id'=>$material['id'],'title'=>$material['title'],'category_id'=>(int)$material['category_id'],
                            'material_type'=>$material['material_type'],'description'=>$material['description'],
                            'full_description'=>$material['full_description'],'publisher'=>$material['publisher'],
                            'publication_date'=>$material['publication_date_iso'],'keywords'=>implode(', ',$material['keywords']),
                            'status_key'=>$material['status_key'],'is_available'=>!empty($material['is_available']),'presentation_file_id'=>(int)($material['presentation_file']['id']??0),
                            'files'=>array_map(static fn(array $file):array=>[
                                'id'=>$file['id'],'name'=>$file['name'],'format'=>$file['format'],
                                'size'=>$file['size'],'presentation'=>$file['presentation'],
                                'extension'=>$file['extension'],
                            ],$material['files']),
                        ], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) ?></script>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="ar-error" id="arMaterialsError" role="alert" <?= $materialLoadFailed ? '' : 'hidden' ?>><?= e($repositorySectionErrors['materials'] ?? '') ?></div>
            <div class="ar-empty" id="arMaterialsEmpty" <?= $catalogSupportMaterials || $materialLoadFailed ? 'hidden' : '' ?>>
                <span><i class="fa-solid fa-folder-open"></i></span>
                <h2>Aún no existen materiales de apoyo.</h2>
                <p>Los recursos institucionales publicados aparecerán en esta sección.</p>
            </div>
            <footer class="ar-pagination" data-pagination-for="materials" hidden>
                <span data-pagination-summary>Mostrando 0 de 0</span>
                <label>Mostrar
                    <select data-page-size>
                        <option value="10">10</option><option value="25">25</option><option value="50">50</option><option value="75">75</option><option value="100">100</option>
                    </select>
                </label>
                <nav data-pagination-pages aria-label="Paginación de materiales de apoyo"></nav>
            </footer>
        </section>

        <?php if ($withdrawnTotal > 0 || $withdrawnLoadFailed): ?>
            <section class="ar-panel" data-repository-panel="withdrawn" hidden>
                <header class="ar-section-head">
                    <div><span>Contenido no visible en catálogo</span><h2>Retirados</h2></div>
                    <p id="arWithdrawnCountText"><?= $withdrawnLoadFailed ? '—' : $withdrawnTotal . ' ' . ($withdrawnTotal === 1 ? 'elemento retirado' : 'elementos retirados') ?></p>
                </header>
                <?php if ($withdrawnError !== null): ?><p class="ar-error" role="alert"><?= e($withdrawnError) ?></p><?php endif; ?>

                <?php if ($withdrawnProjects): ?>
                    <header class="ar-section-head"><div><span>Publicaciones académicas</span><h2>Proyectos retirados</h2></div></header>
                    <div class="ar-grid">
                        <?php foreach ($withdrawnProjects as $project): ?>
                            <?php $projectSearch = implode(' ', [$project['code'], $project['type_name'], $project['title'], $project['authors'] ?: '', $project['tutor_name'] ?: '', $project['period_name']]); ?>
                            <article class="ar-project-card"
                                data-repository-item="withdrawn"
                                data-type-code="<?= e($project['type_code'] ?? '') ?>"
                                data-search="<?= e(mb_strtolower($projectSearch, 'UTF-8')) ?>">
                                <header><span class="ar-code"><?= e($project['code']) ?></span><span class="ar-project-type"><?= e($formatProjectType($project)) ?></span></header>
                                <div class="ar-card-copy">
                                    <h3 title="<?= e($project['title']) ?>"><?= e($project['title']) ?></h3>
                                    <dl>
                                        <div><dt><i class="fa-solid fa-users"></i> Autores</dt><dd><?= e($project['authors'] ?: 'Sin autores registrados') ?></dd></div>
                                        <div><dt><i class="fa-solid fa-user-tie"></i> Tutor</dt><dd><?= e($project['tutor_name'] ?: 'Sin asignar') ?></dd></div>
                                        <div><dt><i class="fa-regular fa-calendar"></i> Período</dt><dd><?= e($project['period_name']) ?></dd></div>
                                    </dl>
                                </div>
                                <div class="ar-card-meta">
                                    <span><i class="fa-regular fa-file-lines"></i> <?= (int) $project['file_count'] ?> documentos</span>
                                    <span><i class="fa-solid fa-box-archive"></i> Retirado <?= e($formatDate($project['withdrawn_at'])) ?></span>
                                </div>
                                <footer>
                                    <button class="ar-primary-action ar-restore-catalog-action" type="button" data-restore-project data-id="<?= (int) $project['id'] ?>"><i class="fa-solid fa-rotate-left"></i> Reincorporar al repositorio</button>
                                    <button class="ar-icon-action ar-trash-action" data-project-trash data-id="<?= (int) $project['id'] ?>" data-tooltip="Enviar a Papelera" type="button" aria-label="Enviar a Papelera"><i class="fa-solid fa-trash-can"></i></button>
                                </footer>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <footer class="ar-pagination" data-pagination-for="withdrawn" hidden>
                    <span data-pagination-summary>Mostrando 0 de 0</span>
                    <label>Mostrar
                        <select data-page-size>
                            <option value="10">10</option><option value="25">25</option><option value="50">50</option><option value="75">75</option><option value="100">100</option>
                        </select>
                    </label>
                    <nav data-pagination-pages aria-label="Paginación de contenido retirado"></nav>
                </footer>

                <?php if ($withdrawnSupportMaterials): ?>
                    <header class="ar-section-head"><div><span>Recursos académicos</span><h2>Materiales retirados</h2></div></header>
                    <div class="ar-grid ar-material-grid">
                        <?php foreach ($withdrawnSupportMaterials as $material): ?>
                            <?php $materialSearch = implode(' ', [$material['title'], $material['category_label'], $material['description'], $material['type'], $material['publisher'] ?? '', implode(' ', $material['keywords'])]); ?>
                            <article class="ar-material-card" data-repository-item="withdrawn" data-search="<?= e(mb_strtolower($materialSearch, 'UTF-8')) ?>">
                                <header>
                                    <span class="ar-material-icon"><i class="fa-regular fa-file-lines"></i></span>
                                    <div><span><?= e($material['type']) ?></span><strong><?= e($material['category_label']) ?></strong></div>
                                    <span class="ar-available is-withdrawn">Retirado</span>
                                </header>
                                <div class="ar-card-copy"><h3 title="<?= e($material['title']) ?>"><?= e($material['title']) ?></h3><p><?= e($material['description']) ?></p></div>
                                <div class="ar-withdrawn-catalog-details">
                                    <div class="ar-withdrawn-catalog-meta">
                                        <span><i class="fa-regular fa-calendar"></i> Retirado el <?= e($formatDate($material['withdrawn_event_at'] ?? $material['withdrawn_at'])) ?></span>
                                        <span><i class="fa-regular fa-user"></i> Por <?= e($material['withdrawn_by_name'] ?: 'Sistema') ?></span>
                                    </div>
                                    <div><strong>Motivo</strong><span><?= e($material['withdrawal_reason'] ?: 'Sin motivo registrado') ?></span></div>
                                </div>
                                <footer><button class="ar-primary-action ar-restore-catalog-action" type="button" data-restore-material data-id="<?= (int) $material['id'] ?>"><i class="fa-solid fa-rotate-left"></i> Reincorporar al repositorio</button></footer>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </section>

    <div class="ar-material-modal" id="arMaterialEditModal" hidden>
        <section role="dialog" aria-modal="true" aria-labelledby="arMaterialEditTitle">
            <header><div><span>Material de apoyo</span><h2 id="arMaterialEditTitle">Editar material</h2><p>Actualiza la información visible en el repositorio.</p></div><button type="button" data-close-material-edit aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button></header>
            <form id="arMaterialEditForm">
                <input type="hidden" name="_csrf" value="<?= e($repositoryCsrf) ?>">
                <input type="hidden" name="id">
                <div class="ar-material-form-grid">
                    <label class="wide">Título<input name="title" maxlength="220" required></label>
                    <label>Categoría<select name="category_id" required><?php foreach($materialCategories as $category):?><option value="<?= (int)$category['id'] ?>"><?= e($category['name']) ?></option><?php endforeach;?></select></label>
                    <label>Tipo de material<input name="material_type" maxlength="100" required></label>
                    <label class="wide">Descripción corta<textarea name="description" maxlength="500" rows="2" required></textarea></label>
                    <label class="wide">Descripción completa<textarea name="full_description" rows="5" required></textarea></label>
                    <label>Responsable<input name="publisher" maxlength="180" required></label>
                    <label>Fecha de publicación<span class="ar-readonly-value" data-material-publication-date>Se asigna al publicar</span><small>Solo lectura</small></label>
                    <label class="wide">Palabras clave<input name="keywords" placeholder="Separadas por comas"></label>
                </div>
                <footer><button type="button" data-close-material-edit>Cancelar</button><button type="submit">Guardar cambios</button></footer>
            </form>
        </section>
    </div>

    <div class="ar-material-modal" id="arMaterialFilesModal" hidden>
        <section role="dialog" aria-modal="true" aria-labelledby="arMaterialFilesTitle">
            <header><div><span>Documentos</span><h2 id="arMaterialFilesTitle">Gestionar archivos</h2><p id="arMaterialFilesSubtitle"></p></div><button type="button" data-close-material-files aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button></header>
            <div class="ar-material-files-list" id="arMaterialFilesList"></div>
            <form id="arMaterialFileForm" enctype="multipart/form-data">
                <input type="hidden" name="_csrf" value="<?= e($repositoryCsrf) ?>">
                <input type="hidden" name="material_id">
                <input type="hidden" name="action" value="add">
                <label>Agregar archivo<input name="file" type="file" accept=".pdf,.docx,.xlsx,.pptx,.png,.jpg,.jpeg,.webp,.txt,.zip" required></label>
                <footer><button type="button" data-close-material-files>Cerrar</button><button type="submit">Agregar archivo</button></footer>
            </form>
        </section>
    </div>

    <div class="ar-material-modal" id="arPresentationModal" hidden>
        <section role="dialog" aria-modal="true" aria-labelledby="arPresentationTitle">
            <header><div><span>Vista inicial</span><h2 id="arPresentationTitle">Elegir archivo de presentación</h2><p>Selecciona el archivo que se mostrará automáticamente cuando una persona ingrese a este expediente. Esta elección no indica que el archivo sea más importante que los demás.</p></div><button type="button" data-close-presentation aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button></header>
            <form id="arPresentationForm">
                <div class="ar-presentation-options" data-presentation-options></div>
                <p class="ar-file-error" data-presentation-error hidden></p>
                <footer><button type="button" data-close-presentation>Cancelar</button><button type="submit" disabled>Continuar con la publicación</button></footer>
            </form>
        </section>
    </div>

    <div class="ar-confirm" id="arConfirm" hidden>
        <div role="alertdialog" aria-modal="true" aria-labelledby="arConfirmTitle" aria-describedby="arConfirmText">
            <span><i class="fa-solid fa-triangle-exclamation"></i></span>
            <h2 id="arConfirmTitle">Confirmar acción</h2>
            <p id="arConfirmText"></p>
            <div class="ar-confirm-reason" data-confirm-reason hidden>
                <label for="arConfirmReasonSelect">Motivo</label>
                <select id="arConfirmReasonSelect" data-confirm-reason-select disabled>
                    <option value="">Selecciona un motivo</option>
                    <option value="Publicación incorrecta">Publicación incorrecta</option>
                    <option value="Contenido duplicado">Contenido duplicado</option>
                    <option value="Información desactualizada">Información desactualizada</option>
                    <option value="Solicitud de retiro institucional">Solicitud de retiro institucional</option>
                    <option value="Incumplimiento de requisitos">Incumplimiento de requisitos</option>
                    <option value="other">Otro</option>
                </select>
                <label class="ar-confirm-reason-detail" data-confirm-reason-detail hidden for="arConfirmReasonInput">Especifica el motivo
                    <textarea id="arConfirmReasonInput" data-confirm-reason-input rows="3" minlength="5" maxlength="500" placeholder="Describe brevemente el motivo." disabled></textarea>
                </label>
            </div>
            <div>
                <button type="button" data-confirm-cancel>Cancelar</button>
                <button type="button" class="danger" data-confirm-accept>Retirar proyecto</button>
            </div>
        </div>
    </div>
    <div class="ar-tooltip" id="arTooltip" role="tooltip" hidden></div>
    <div id="arConfig" data-endpoint="<?= e($repositoryPublishEndpoint) ?>" data-trash-endpoint="<?= e(route('admin-repository-trash')) ?>" data-material-save="<?= e($materialSaveEndpoint) ?>" data-material-status="<?= e($materialStatusEndpoint) ?>" data-material-file="<?= e($materialFileEndpoint) ?>" data-csrf="<?= e($repositoryCsrf) ?>"></div>
</div>
<?php require APP_PATH . '/views/repository/_material-admin-action-dialog.php'; ?>
<script src="<?= e(asset('js/material-admin-actions.js')) ?>"></script>
