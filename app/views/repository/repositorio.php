<div id="repositoryContent">
    <div class="ar-page" id="arPage">
        <!-- Encabezado exacto Admin -->
        <header class="ar-head">
            <div class="ar-head-top">
                <span class="ar-eyebrow">Biblioteca digital institucional</span>
                <h1>Repositorio institucional</h1>
            </div>
            <div class="ar-head-bottom">
                <p>Consulta proyectos publicados y materiales de apoyo disponibles para la comunidad académica.</p>
                <?php if (!empty($canAddRepositoryContent)): ?>
                    <div class="ar-head-actions">
                        <button class="ar-primary-action" type="button" data-teacher-content-trigger data-teacher-material-create>
                            <i class="fa-solid fa-plus"></i> Agregar contenido
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </header>

        <!-- Catálogo unificado y contenedor principal -->
        <main class="ar-catalog" id="repositoryShell">
            <?php
            $teacherOwnedContent = is_array($teacherOwnedContent ?? null) ? $teacherOwnedContent : [];
            $teacherOwnedCounts = (array) ($teacherOwnedContent['counts'] ?? []);
            $teacherManagementCounts = [
                'unavailable' => (int) ($teacherOwnedCounts['unavailable'] ?? count((array) ($teacherOwnedContent['unavailable'] ?? []))),
                'withdrawn' => (int) ($teacherOwnedCounts['withdrawn'] ?? count((array) ($teacherOwnedContent['withdrawn'] ?? []))),
                'trash' => (int) ($teacherOwnedCounts['trash'] ?? count((array) ($teacherOwnedContent['trash'] ?? []))),
            ];
            $teacherManagementTotal = array_sum($teacherManagementCounts);
            $showTeacherManagement = !empty($teacherOwnedContentUi) && $teacherManagementTotal > 0;
            ?>
            <!-- Pestañas exactas Admin -->
            <nav class="ar-tabs" role="tablist" aria-label="Secciones del repositorio">
                <button type="button" class="active" id="tabProjects" role="tab" aria-selected="true" aria-controls="panelProjects">
                    <i class="fa-solid fa-diagram-project"></i>
                    <span class="ar-tab-label">Proyectos publicados</span>
                    <span class="ar-tab-count" id="badgeProjectsCount"><?= (int) (($repositoryPagination ?? [])['total'] ?? count($projects)) ?></span>
                </button>
                <button type="button" id="tabSupport" role="tab" aria-selected="false" aria-controls="panelSupport">
                    <i class="fa-solid fa-folder-open"></i>
                    <span class="ar-tab-label">Material de apoyo</span>
                    <span class="ar-tab-count" id="badgeSupportCount"<?= ($supportStatus ?? 'loaded') === 'error' ? ' aria-label="Estado no disponible"' : '' ?>><?= ($supportStatus ?? 'loaded') === 'error' ? '—' : count($supportDocuments) ?></span>
                </button>
            <?php if ($showTeacherManagement): ?>
                <button type="button" id="tabManagement" role="tab" aria-selected="false" aria-controls="panelManagement">
                    <i class="fa-solid fa-sliders"></i>
                    <span class="ar-tab-label">Mi gestión</span>
                    <span class="ar-tab-count" id="badgeManagementCount"><?= $teacherManagementTotal ?></span>
                </button>
            <?php endif; ?>
            </nav>

            <!-- Toolbar de proyectos (hermano directo de ar-panel, como en Admin) -->
            <?php
            $repositoryFilters = (array) ($repositoryFilters ?? []);
            $repositoryPagination = (array) ($repositoryPagination ?? []);
            $repositoryPage = max(1, (int) ($repositoryPagination['page'] ?? 1));
            $repositoryPageSize = (int) ($repositoryPagination['per_page'] ?? $repositoryPagination['page_size'] ?? 10);
            $repositoryTotal = (int) ($repositoryPagination['total'] ?? count($projects));
            $repositoryPages = max(1, (int) ($repositoryPagination['pages'] ?? 1));
            $repositoryStatus = (string) ($repositoryStatus ?? 'loaded');
            $supportStatus = (string) ($supportStatus ?? 'loaded');
            $supportError = (string) ($supportError ?? 'No fue posible cargar los materiales de apoyo en este momento.');
            $repositorySearchValue = (string) ($repositoryFilters['search'] ?? '');
            $repositoryTypeValue = (string) ($repositoryFilters['type'] ?? 'all');
            $repositoryPeriodValue = (string) ($repositoryFilters['period'] ?? 'all');
            $repositoryPageItemCount = $repositoryTotal > 0
                ? min($repositoryPageSize, max(0, $repositoryTotal - (($repositoryPage - 1) * $repositoryPageSize)))
                : 0;
            $repositoryPageSizes = array_values(array_filter([10, 25, 50, 75, 100], static fn (int $size): bool => $size <= max($repositoryTotal, 10)));
            if (!$repositoryPageSizes) $repositoryPageSizes = [10];
            $repositoryUrl = route('repository');
            $repositoryQuery = static function (int $page) use ($repositorySearchValue, $repositoryTypeValue, $repositoryPeriodValue, $repositoryPageSize, $repositoryUrl): string {
                return $repositoryUrl . '&' . http_build_query(['search'=>$repositorySearchValue,'type'=>$repositoryTypeValue,'period'=>$repositoryPeriodValue,'page_size'=>$repositoryPageSize,'repository_page'=>$page], '', '&', PHP_QUERY_RFC3986);
            };
            ?>
            <!-- Toolbar de materiales (se muestra solo en pestaña de materiales) -->
            <div class="ar-tools" id="toolsSupport" hidden>
                <label class="ar-search<?= $supportStatus !== 'loaded' ? ' is-disabled' : '' ?>">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input id="repositorySupportSearch" type="text" role="searchbox" placeholder="Buscar por título, descripción o palabra clave" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"<?= !$supportDocuments ? ' disabled' : '' ?>>
                </label>
                <label class="ar-filter-control">
                    <span>Categoría</span>
                    <select id="repositorySupportCategory"<?= !$supportDocuments ? ' disabled' : '' ?>>
                        <option value="all">Todas</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= e($category['value']) ?>"><?= e($category['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <form method="get" action="<?= e(base_url('index.php')) ?>" class="ar-tools" id="repositoryProjectFilters">
                <input type="hidden" name="page" value="repository">
                <input type="hidden" name="repository_page" value="1">
                <input type="hidden" name="page_size" value="<?= $repositoryPageSize ?>">
                <label class="ar-search<?= $repositoryStatus === 'error' ? ' is-disabled' : '' ?>">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input name="search" value="<?= e($repositorySearchValue) ?>" type="text" role="searchbox" placeholder="Buscar por título, código, autor o tutor" aria-label="Buscar proyectos publicados" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"<?= $repositoryStatus === 'error' ? ' disabled' : '' ?>>
                </label>
                <label class="ar-filter-control"><span>Tipo</span><select name="type"<?= $repositoryStatus === 'error' ? ' disabled' : '' ?>><option value="all">Todos</option><?php foreach ($projectTypes as $type): ?><option value="<?= e($type['value']) ?>"<?= $repositoryTypeValue === (string)$type['value'] ? ' selected' : '' ?>><?= e($type['label']) ?></option><?php endforeach; ?></select></label>
                <?php if (count($academicPeriods) === 1): ?>
                    <div class="ar-filter-control ar-fixed-filter">
                        <span>Período académico</span>
                        <div><i class="fa-regular fa-calendar" aria-hidden="true"></i><strong><?= e($academicPeriods[0]['label']) ?></strong></div>
                    </div>
                <?php elseif (count($academicPeriods) > 1): ?>
                    <label class="ar-filter-control"><span>Período académico</span><select name="period"<?= $repositoryStatus === 'error' ? ' disabled' : '' ?>><option value="all">Todos</option><?php foreach ($academicPeriods as $period): ?><option value="<?= e($period['value']) ?>"<?= $repositoryPeriodValue === (string)$period['value'] ? ' selected' : '' ?>><?= e($period['label']) ?></option><?php endforeach; ?></select></label>
                <?php else: ?>
                    <div class="ar-filter-control ar-fixed-filter">
                        <span>Período académico</span>
                        <div><i class="fa-regular fa-calendar" aria-hidden="true"></i><strong>Sin períodos disponibles</strong></div>
                    </div>
                <?php endif; ?>
            </form>

            <!-- Panel 1: Proyectos publicados -->
            <section
                class="ar-panel"
                id="panelProjects"
                role="tabpanel"
                aria-labelledby="tabProjects"
                data-favorite-url="<?= e($favoriteActionUrl) ?>"
                data-favorite-csrf="<?= e($favoriteCsrfToken) ?>"
                data-base-project-count="<?= count($projects) ?>"
            >
                <!-- Encabezado de sección exacto Admin -->
                <header class="ar-section-head">
                    <div><span>Catálogo institucional</span><h2>Proyectos publicados</h2></div>
                    <p><strong id="repositoryCount"><?= $repositoryTotal ?></strong> <?= $repositoryTotal === 1 ? 'resultado visible' : 'resultados visibles' ?></p>
                </header>

                <!-- Grilla de proyectos con tarjetas exactas Admin -->
                <div class="ar-grid" id="readerProjectGrid">
                    <?php foreach ($projects as $project): ?>
                        <?php
                        $projectCardId = (int) ($project['id'] ?? 0);
                        $projectCardCapabilities = is_array(($repositoryProjectCapabilities ?? [])[$projectCardId] ?? null)
                            ? ($repositoryProjectCapabilities[$projectCardId] ?? [])
                            : [];
                        $projectCardMenuActions = [];
                        $projectCardOwnStatus = !empty($projectCardCapabilities['manage_own_repository_status']);
                        if ($projectCardOwnStatus) {
                            $projectCardMenuActions[] = [
                                'label' => 'Marcar como no disponible',
                                'icon' => 'fa-ban',
                                'enabled' => true,
                                'action' => 'availability',
                            ];
                            $projectCardMenuActions[] = [
                                'label' => 'Retirar publicación',
                                'icon' => 'fa-box-archive',
                                'enabled' => true,
                                'action' => 'publication',
                            ];
                        }
                        if (!empty($projectCardCapabilities['edit_information'])) {
                            $projectCardMenuActions[] = [
                                'label' => 'Enviar a Papelera',
                                'icon' => 'fa-trash-can',
                                'enabled' => true,
                                'action' => 'trash',
                                'danger' => true,
                                'separator' => $projectCardMenuActions !== [],
                            ];
                        }
                        $projectCardActionEndpoint = $projectCardOwnStatus ? route('repository-direct-project-status') : '';
                        $projectCardTrashEndpoint = $projectCardOwnStatus ? route('repository-direct-project-trash') : '';
                        $projectCardActionCsrf = $projectCardOwnStatus ? (string) ($repositoryProjectActionCsrf ?? '') : '';
                        $projectCardTrashCsrf = $projectCardOwnStatus ? (string) ($repositoryProjectActionCsrf ?? '') : '';
                        $projectSearchText = implode(' ', [
                            $project['code'] ?? '',
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
                        $typeCode = !empty($project['type_code']) ? $project['type_code'] : match ($typeSlug) {
                            'tesis', 'titulacion' => 'thesis',
                            'perfil-tesis' => 'thesis_profile',
                            'practicas', 'practicas-preprofesionales' => 'practice',
                            'proyecto-pis', 'proyecto-integrador-de-saberes', 'pis' => 'pis',
                            'vinculacion', 'proyecto-de-vinculacion' => 'community',
                            default => $typeSlug,
                        };
                        $typeLabels = [
                            'thesis'         => 'Titulación',
                            'thesis_profile' => 'Perfil de Titulación',
                            'pis'            => 'Proyecto PIS',
                            'practice'       => 'Proyecto de Prácticas',
                            'community'      => 'Proyecto de Vinculación',
                        ];
                        $typeBadge = $typeLabels[$typeCode] ?? $project['type'];
                        ?>
                        <article
                            class="ar-project-card"
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
                            <?php if ($projectCardMenuActions): ?>
                            data-record-status="published"
                            data-record-available="1"
                            data-project-action-endpoint="<?= e($projectCardActionEndpoint) ?>"
                            data-project-action-csrf="<?= e($projectCardActionCsrf) ?>"
                            data-project-trash-endpoint="<?= e($projectCardTrashEndpoint) ?>"
                            data-project-trash-csrf="<?= e($projectCardTrashCsrf) ?>"
                            data-teacher-owner-status-management="<?= $projectCardOwnStatus ? 'true' : 'false' ?>"
                            <?php endif; ?>
                        >
                            <header>
                                <span class="ar-code"><?= e($project['code'] ?? '') ?></span>
                                <span class="ar-project-type"><?= e($typeBadge) ?></span>
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
                                <span><i class="fa-regular fa-file-lines"></i> <?= (int) ($project['file_count'] ?? 0) ?> <?= ((int) ($project['file_count'] ?? 0)) === 1 ? 'documento' : 'documentos' ?></span>
                                <span><i class="fa-solid fa-globe"></i> <?= e(!empty($project['published_at_label']) ? $project['published_at_label'] : $project['year']) ?></span>
                            </div>
                            <footer>
                                <a class="ar-primary-action" href="<?= e($project['detail_url']) ?>">
                                    <i class="fa-solid fa-diagram-project"></i> Abrir expediente
                                </a>
                                <?php if (!empty($project['package_available']) && !empty($project['package_download_url'])): ?>
                                    <a class="ar-icon-action" href="<?= e($project['package_download_url']) ?>" data-tooltip="Descargar ZIP" aria-label="Descargar ZIP completo de <?= e($project['title']) ?>">
                                        <i class="fa-solid fa-download" aria-hidden="true"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if ($projectCardMenuActions): ?>
                                    <?php
                                    $repositoryCardMenuId = 'repositoryProjectActionsMenu-' . $projectCardId;
                                    $repositoryCardMenuActions = $projectCardMenuActions;
                                    require __DIR__ . '/_repository-card-action-menu.php';
                                    ?>
                                <?php endif; ?>
                            </footer>
                        </article>
                    <?php endforeach; ?>
                </div>

                <!-- Estado vacío exacto Admin -->
                <?php if ($repositoryStatus === 'error'): ?>
                    <div class="ar-empty" id="repositoryErrorState">
                        <span><i class="fa-solid fa-triangle-exclamation"></i></span>
                        <h2>No fue posible cargar el repositorio.</h2>
                        <p><?= e((string) ($repositoryError ?: 'Inténtalo nuevamente más tarde.')) ?></p>
                    </div>
                <?php endif; ?>
                <div class="ar-empty" id="repositoryEmpty" <?= $repositoryStatus !== 'empty' ? 'hidden' : '' ?>>
                    <span><i class="fa-solid fa-book-open"></i></span>
                    <h2 id="repositoryEmptyTitle">Aún no existen proyectos publicados.</h2>
                    <p id="repositoryEmptyText">Los proyectos aprobados aparecerán aquí después de completar su publicación.</p>
                </div>

                <!-- Paginación exacta Admin (Se oculta automáticamente si totalPages <= 1) -->
                <?php if ($repositoryStatus === 'loaded' && $repositoryPages > 1): ?>
                    <footer class="ar-pagination" id="repositoryPagination">
                        <p>Mostrando <strong><?= $repositoryPageItemCount ?></strong> de <strong><?= $repositoryTotal ?></strong></p>
                        <form method="get" action="<?= e(base_url('index.php')) ?>" class="ar-pagination-size data-pagination-size">
                            <input type="hidden" name="page" value="repository">
                            <input type="hidden" name="search" value="<?= e($repositorySearchValue) ?>">
                            <input type="hidden" name="type" value="<?= e($repositoryTypeValue) ?>">
                            <input type="hidden" name="period" value="<?= e($repositoryPeriodValue) ?>">
                            <input type="hidden" name="repository_page" value="1">
                            <label for="repositoryPageSize"><span>Mostrar</span></label>
                            <select id="repositoryPageSize" name="page_size" aria-label="Cantidad de proyectos por página" data-dropdown-placement="top">
                                <?php foreach ($repositoryPageSizes as $size): ?><option value="<?= $size ?>"<?= $repositoryPageSize === $size ? ' selected' : '' ?>><?= $size ?></option><?php endforeach; ?>
                            </select>
                        </form>
                        <nav aria-label="Paginación de proyectos publicados">
                            <?php $previousDisabled = $repositoryPage <= 1; ?>
                            <a href="<?= e($repositoryQuery(max(1, $repositoryPage - 1))) ?>" class="<?= $previousDisabled ? 'is-disabled' : '' ?>" aria-label="Página anterior"<?= $previousDisabled ? ' aria-disabled="true" tabindex="-1"' : '' ?>><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></a>
                            <?php
                            if ($repositoryPages <= 5) $repositoryPageTokens = range(1, $repositoryPages);
                            elseif ($repositoryPage <= 3) $repositoryPageTokens = [1, 2, 3, 'ellipsis', $repositoryPages];
                            elseif ($repositoryPage >= $repositoryPages - 2) $repositoryPageTokens = [1, 'ellipsis', $repositoryPages - 2, $repositoryPages - 1, $repositoryPages];
                            else $repositoryPageTokens = [1, 'ellipsis', $repositoryPage - 1, $repositoryPage, $repositoryPage + 1, 'ellipsis', $repositoryPages];
                            foreach ($repositoryPageTokens as $pageToken):
                                if ($pageToken === 'ellipsis'): ?><span class="pagination-ellipsis" aria-hidden="true">…</span><?php
                                else: ?><a href="<?= e($repositoryQuery((int) $pageToken)) ?>" class="<?= (int) $pageToken === $repositoryPage ? 'is-active' : '' ?>"<?= (int) $pageToken === $repositoryPage ? ' aria-current="page"' : '' ?>><?= (int) $pageToken ?></a><?php
                                endif;
                            endforeach;
                            $nextDisabled = $repositoryPage >= $repositoryPages;
                            ?><a href="<?= e($repositoryQuery(min($repositoryPages, $repositoryPage + 1))) ?>" class="<?= $nextDisabled ? 'is-disabled' : '' ?>" aria-label="Página siguiente"<?= $nextDisabled ? ' aria-disabled="true" tabindex="-1"' : '' ?>><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
                        </nav>
                    </footer>
                <?php endif; ?>
            </section>

            <!-- Panel 2: Material de apoyo -->
            <section class="ar-panel" id="panelSupport" role="tabpanel" aria-labelledby="tabSupport" data-support-status="<?= e($supportStatus) ?>" data-base-support-count="<?= count($supportDocuments) ?>" hidden>
                <header class="ar-section-head">
                    <div><span>Recursos académicos</span><h2>Material de apoyo</h2></div>
                    <div style="display:flex;align-items:center;gap:10px"><p id="repositorySupportCount" aria-live="polite"><?= $supportStatus === 'error' ? 'Estado no disponible' : count($supportDocuments) . ' ' . (count($supportDocuments) === 1 ? 'resultado visible' : 'resultados visibles') ?></p></div>
                </header>

                <div class="ar-grid" id="repositorySupportGrid">
                        <?php foreach ($supportDocuments as $document): ?>
                            <?php
                            $supportMaterialCardId = (int) ($document['id'] ?? 0);
                            $supportMaterialCardCapabilities = is_array(($repositorySupportMaterialCapabilities ?? [])[$supportMaterialCardId] ?? null)
                                ? ($repositorySupportMaterialCapabilities[$supportMaterialCardId] ?? [])
                                : [];
                            $supportMaterialStatus = (string) ($document['status_key'] ?? 'published');
                            $supportMaterialIsPublished = $supportMaterialStatus === 'published';
                            $supportMaterialIsAvailable = !empty($document['is_available']);
                            $supportMaterialMenuActions = [];
                            $supportMaterialCanManageFiles = !empty($supportMaterialCardCapabilities['manage_files']);
                            $supportMaterialCanChangeStatus = !empty($supportMaterialCardCapabilities['change_status']);
                            $supportMaterialCanDelete = !empty($supportMaterialCardCapabilities['delete']);
                            $supportMaterialDetailUrl = (string) ($document['detail_url'] ?? (route('support-material-detail') . '&id=' . $supportMaterialCardId));
                            if ($supportMaterialCanChangeStatus) {
                                if ($supportMaterialIsPublished) {
                                    $supportMaterialMenuActions[] = [
                                        'label' => $supportMaterialIsAvailable ? 'Marcar como no disponible' : 'Marcar como disponible',
                                        'icon' => $supportMaterialIsAvailable ? 'fa-ban' : 'fa-circle-check',
                                        'enabled' => true,
                                        'action' => 'availability',
                                    ];
                                }
                                $supportMaterialMenuActions[] = [
                                    'label' => $supportMaterialIsPublished ? 'Retirar publicación' : 'Publicar material',
                                    'icon' => $supportMaterialIsPublished ? 'fa-box-archive' : 'fa-box-open',
                                    'enabled' => true,
                                    'action' => 'publication',
                                ];
                            }
                            if ($supportMaterialCanManageFiles) {
                                $supportMaterialMenuActions[] = [
                                    'label' => 'Gestionar archivos',
                                    'icon' => 'fa-folder-open',
                                    'enabled' => true,
                                    'url' => $supportMaterialDetailUrl . '&tab=files',
                                ];
                            }
                            if ($supportMaterialCanDelete) {
                                $supportMaterialMenuActions[] = [
                                    'label' => 'Enviar a Papelera',
                                    'icon' => 'fa-trash-can',
                                    'enabled' => true,
                                    'action' => 'trash',
                                    'danger' => true,
                                    'separator' => $supportMaterialMenuActions !== [],
                                ];
                            }
                            $supportMaterialActionEndpoint = ($supportMaterialCanChangeStatus || $supportMaterialCanDelete)
                                ? route('support-material-manage-status')
                                : '';
                            $supportMaterialActionCsrf = ($supportMaterialCanChangeStatus || $supportMaterialCanDelete)
                                ? (string) ($repositorySupportMaterialActionCsrf ?? '')
                                : '';
                            ?>
                            <article class="ar-material-card"
                                data-material-id="<?= $supportMaterialCardId ?>"
                                data-category-slug="<?= e($document['category_slug']) ?>"
                                data-support-text="<?= e($document['title'] . ' ' . $document['description'] . ' ' . $document['type'] . ' ' . $document['year'] . ' ' . $document['pao_label'] . ' ' . $document['category_label'] . ' ' . implode(' ', $document['keywords'])) ?>"
                                data-support-category="<?= e($document['category_slug']) ?>"
                                <?php if ($supportMaterialMenuActions): ?>
                                data-record-status="<?= e($supportMaterialStatus) ?>"
                                data-record-available="<?= $supportMaterialIsAvailable ? '1' : '0' ?>"
                                data-material-action-endpoint="<?= e($supportMaterialActionEndpoint) ?>"
                                data-material-action-csrf="<?= e($supportMaterialActionCsrf) ?>"
                                <?php endif; ?>
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
                                    <a class="ar-icon-action" href="<?= e(route('support-material-package-download') . '&material_id=' . (int) $document['id']) ?>" data-tooltip="Descargar ZIP" aria-label="Descargar ZIP completo de <?= e($document['title']) ?>">
                                        <i class="fa-solid fa-download" aria-hidden="true"></i>
                                    </a>
                                    <?php if ($supportMaterialMenuActions): ?>
                                        <?php
                                        $repositoryCardMenuId = 'repositorySupportMaterialActionsMenu-' . $supportMaterialCardId;
                                        $repositoryCardMenuActions = $supportMaterialMenuActions;
                                        require __DIR__ . '/_repository-card-action-menu.php';
                                        ?>
                                    <?php endif; ?>
                                </footer>
                            </article>
                        <?php endforeach; ?>
                </div>

                <div class="ar-empty" id="repositorySupportError" role="status" aria-live="polite" <?= $supportStatus !== 'error' ? 'hidden' : '' ?>>
                    <span><i class="fa-solid fa-triangle-exclamation"></i></span>
                    <h2>No fue posible cargar los materiales de apoyo.</h2>
                    <p><?= e($supportError) ?></p>
                </div>
                <div class="ar-empty" id="repositorySupportEmpty" <?= $supportStatus !== 'empty' ? 'hidden' : '' ?>>
                    <span><i class="fa-solid fa-folder-open"></i></span>
                    <h2 id="repositorySupportEmptyTitle">Aún no existen materiales de apoyo.</h2>
                    <p id="repositorySupportEmptyText">Los recursos institucionales publicados aparecerán en esta sección.</p>
                </div>
                <footer class="ar-pagination" id="repositorySupportPagination" hidden><span id="repositorySupportPaginationSummary">Mostrando 0 de 0</span><label>Mostrar <select id="repositorySupportPageSize"><option value="10">10</option><option value="25">25</option><option value="50">50</option><option value="75">75</option><option value="100">100</option></select></label><nav id="repositorySupportPaginationPages" aria-label="Paginación de materiales de apoyo"></nav></footer>
            </section>

            <?php if (!empty($teacherOwnedContentUi)): ?>
                <?php require __DIR__ . '/_teacher-owned-content.php'; ?>
            <?php endif; ?>

        </main>
    </div>
</div>
<?php require __DIR__ . '/_teacher-material-modal.php'; ?>
<?php require __DIR__ . '/_teacher-repository-content-selector.php'; ?>
<?php require __DIR__ . '/_teacher-direct-project-modal.php'; ?>
<?php if (!empty($repositoryProjectActionUi) || !empty($repositorySupportMaterialActionUi)): ?>
    <?php require __DIR__ . '/_material-admin-action-dialog.php'; ?>
<?php endif; ?>
