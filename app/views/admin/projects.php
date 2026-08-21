<?php
$qaEmptyTeacherProjects = app_is_development()
    && !is_array($_GET['qa_empty_projects'] ?? null)
    && (string) ($_GET['qa_empty_projects'] ?? '') === '1'
    && (string) ($currentPage ?? '') === 'projects'
    && empty($layoutIsAdmin)
    && in_array('teacher', (array) ($layoutUserRoles ?? []), true);

if ($qaEmptyTeacherProjects) {
    $projects = [];
    $pagePagination = array_replace((array) ($pagePagination ?? []), ['total' => 0]);
    $projectSummary = ['total' => 0, 'development' => 0, 'review' => 0, 'approved' => 0, 'defense' => 0];
}

$statusLabels = [];
foreach (['development', 'under_review', 'approved', 'defense', 'tribunal_approved'] as $projectStatusCode)
    $statusLabels[$projectStatusCode] = project_academic_labels($projectStatusCode)['status'];
$projectStageLabel = !empty($projectEditorOnly) && !empty($projectEditorPayload) ? project_academic_labels((string) ($projectEditorPayload['status'] ?? ''))['stage'] : 'Etapa no disponible'; ?>
<?php if (empty($projectEditorOnly)): ?>
    <section class="ap-head">
        <div><span>Administración</span>
            <h1>Proyectos activos</h1>
            <p>Consulta y actualiza los expedientes académicos que aún no han sido publicados.</p>
        </div>
    </section>
    <?php if (($filters['group'] ?? '') === 'finished'): ?>
        <div class="ap-active-filter"><i class="fa-solid fa-filter"></i><span>Proyectos activos en etapas finales</span><a
                href="<?= e(route('projects')) ?>">Quitar filtro</a></div><?php endif; ?>
    <?php if (!empty($filters['review_situation'])):
        $sitLabel = match ($filters['review_situation']) {
            'pending' => 'Con observaciones pendientes',
            'addressed' => 'Observaciones atendidas',
            'none' => 'Sin observaciones registradas',
            default => 'Situación de revisión'
        };
        ?>
        <div class="ap-active-filter"><i class="fa-solid fa-filter"></i><span>Filtrado por situación de revisión:
                <strong><?= e($sitLabel) ?></strong></span><a href="<?= e(route('projects')) ?>">Quitar filtro</a></div>
    <?php endif; ?>
    <section class="ap-stats">
        <article><strong><?= $projectSummary['total'] ?></strong><span>Total activos</span></article>
        <article><strong><?= $projectSummary['development'] ?></strong><span>En desarrollo</span></article>
        <article><strong><?= $projectSummary['review'] ?></strong><span>En revisión</span></article>
        <article><strong><?= $projectSummary['approved'] ?></strong><span>Aprobados</span></article>
        <article><strong><?= $projectSummary['defense'] ?></strong><span>En tribunal</span></article>
    </section>
    <?php
        $filteredProjectTotal = (int) ($pagePagination['total'] ?? 0);
        $hasActiveFilters = ($filters['search'] ?? '') !== ''
            || ($filters['status'] ?? '') !== ''
            || ($filters['type_id'] ?? 0) > 0
            || ($filters['review_situation'] ?? '') !== '';
        $teacherProjectsContext = empty($layoutIsAdmin)
            && in_array('teacher', (array) ($layoutUserRoles ?? []), true);
        $emptyProjectsDataset = $teacherProjectsContext
            && $filteredProjectTotal === 0
            && !$hasActiveFilters;
    ?>
    <form class="ap-filters admin-filter-bar" role="search">
        <input type="hidden" name="page" value="projects">
        <?php foreach (['group', 'sort', 'per_page'] as $preservedFilter): ?>
            <?php if (isset($_GET[$preservedFilter]) && !is_array($_GET[$preservedFilter]) && (string) $_GET[$preservedFilter] !== ''): ?>
                <input type="hidden" name="<?= e($preservedFilter) ?>" value="<?= e((string) $_GET[$preservedFilter]) ?>">
            <?php endif; ?>
        <?php endforeach; ?>
        <label class="ap-filter-control ap-filter-search admin-filter-item-search">
            <span class="sr-only">Buscar proyectos activos</span>
            <span class="ap-search-field admin-filter-search"><i class="fa-solid fa-magnifying-glass"
                    aria-hidden="true"></i><input type="search" name="search" value="<?= e($filters['search']) ?>"
                    <?= $emptyProjectsDataset ? 'disabled' : '' ?>
                    placeholder="Buscar por título, código o tutor" autocomplete="off" data-no-search-history><button
                    type="button" class="ap-search-clear" aria-label="Limpiar búsqueda" title="Limpiar búsqueda" hidden><i
                        class="fa-solid fa-xmark" aria-hidden="true"></i></button></span>
        </label>
        <label class="ap-filter-control admin-filter-control"><span>Estado</span><select name="status"
                aria-label="Filtrar proyectos activos por estado" <?= $emptyProjectsDataset ? 'disabled' : '' ?>>
                <option value="">Todos</option><?php foreach ($statusLabels as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select></label>
        <label class="ap-filter-control admin-filter-control"><span>Tipo</span><select name="type_id"
                aria-label="Filtrar proyectos por tipo" <?= $emptyProjectsDataset ? 'disabled' : '' ?>>
                <option value="">Todos</option><?php foreach ($catalogs['types'] as $item): ?>
                    <option value="<?= $item['id'] ?>" <?= $filters['type_id'] === $item['id'] ? 'selected' : '' ?>><?= e($item['name']) ?>
                    </option><?php endforeach; ?>
            </select></label>
        <?php if (count($catalogs['periods']) >= 2): ?>
            <label class="ap-filter-control admin-filter-control"><span>Período académico</span><select name="period_id"
                    aria-label="Filtrar proyectos por período académico">
                    <option value="">Todos</option><?php foreach ($catalogs['periods'] as $item): ?>
                        <option value="<?= $item['id'] ?>" <?= $filters['period_id'] === $item['id'] ? 'selected' : '' ?>>
                            <?= e($item['name']) ?></option><?php endforeach; ?>
                </select></label>
        <?php elseif (count($catalogs['periods']) === 1): ?>
            <div class="ap-filter-control ap-fixed-filter admin-filter-control admin-filter-fixed"><span>Período
                    académico</span>
                <div><i class="fa-regular fa-calendar"
                        aria-hidden="true"></i><strong><?= e($catalogs['periods'][0]['name']) ?></strong></div><input
                    type="hidden" name="period_id" value="<?= (int) $catalogs['periods'][0]['id'] ?>">
            </div>
        <?php else: ?>
            <div class="ap-filter-control ap-fixed-filter admin-filter-control admin-filter-fixed"><span>Período
                    académico</span>
                <div><i class="fa-regular fa-calendar-xmark" aria-hidden="true"></i><strong>Sin períodos disponibles</strong>
                </div>
            </div>
        <?php endif; ?>
    </form>
    <p class="ap-results-count" role="status">
        <?php if ($filteredProjectTotal === 0 && $hasActiveFilters): ?>
            0 proyectos encontrados con los filtros seleccionados
        <?php elseif ($filteredProjectTotal === 0): ?>
            0 proyectos activos encontrados
        <?php else: ?>
            Mostrando <?= $filteredProjectTotal ?> proyecto<?= $filteredProjectTotal === 1 ? ' activo' : 's activos' ?>
        <?php endif; ?>
    </p>
    <?php $projectListReturnQuery = ['page' => 'projects'];
    foreach (['p', 'search', 'type_id', 'status', 'period_id', 'sort', 'per_page', 'group'] as $returnKey) {
        if (isset($_GET[$returnKey]) && is_scalar($_GET[$returnKey]) && (string) $_GET[$returnKey] !== '')
            $projectListReturnQuery[$returnKey] = (string) $_GET[$returnKey];
    }
    $projectListReturnUrl = base_url('index.php?' . http_build_query($projectListReturnQuery)); ?>
    <section class="ap-list"><?php if ($filteredProjectTotal === 0): ?>
        <?php if ($emptyProjectsDataset): ?>
            <div class="ap-empty">
                <i class="fa-regular fa-folder-open"></i>
                <h2>No hay proyectos activos en este momento.</h2>
                <p>Los proyectos publicados se encuentran disponibles en el Repositorio.</p>
                <a href="<?= e(!empty($isAdministrator) ? route('admin-repository') : route('repository')) ?>">Ir al Repositorio</a>
            </div>
        <?php else: ?>
            <div class="ap-empty ap-empty-filtered">
                <i class="fa-solid fa-filter-circle-xmark"></i>
                <h2>No se encontraron proyectos</h2>
                <p>Prueba cambiando o limpiando los filtros de búsqueda.</p>
                <a href="<?= e(route('projects')) ?>">Limpiar filtros</a>
            </div>
        <?php endif; ?>
    <?php else: foreach ($projects as $p): $pending = !empty($p['review_situation']['has_pending_observations']); ?>
                <article data-project-status="<?= e($p['status']) ?>" data-project-type-id="<?= (int) $p['project_type_id'] ?>"
                    data-project-period-id="<?= (int) $p['academic_period_id'] ?>">
                    <div class="ap-main"><span><?= e($p['code']) ?> · <?= e($p['type_name']) ?></span>
                        <h2><?= e($p['title']) ?></h2>
                        <p><?= e($p['career_name']) ?> · <?= e($p['period_name']) ?></p>
                    </div>
                    <div><small>Tutor</small><strong><?= e($p['tutor_name'] ?: 'Por asignar') ?></strong></div>
                    <div class="ap-status-column"><small>Estado</small>
                        <div class="ap-status-stack"><span
                                class="ap-status"><?= e($statusLabels[$p['status']] ?? $p['status']) ?></span><?php if ($pending): ?><span
                                    class="ap-review-situation" aria-label="Observaciones pendientes"
                                    data-tooltip="Observaciones pendientes" tabindex="0"><i class="fa-solid fa-triangle-exclamation"
                                        aria-hidden="true"></i></span><?php endif; ?></div>
                    </div>
                    <div class="ap-actions"><a
                            href="<?= e(route('project-detail') . '&id=' . (int) $p['id'] . '&return=' . rawurlencode($projectListReturnUrl)) ?>">Abrir</a>
                    </div>
                </article>
    <?php endforeach; endif; ?>
    </section>
    <?php if ($projectError): ?>
        <aside class="ap-connection-notice" role="status"><i class="fa-solid fa-cloud-arrow-down" aria-hidden="true"></i>
            <div><strong>Datos temporalmente no disponibles</strong><span>Conservamos la pantalla lista para que puedas
                    intentarlo nuevamente.</span></div><a href="<?= e(route('projects')) ?>">Reintentar</a>
        </aside><?php endif; ?>
<?php endif; ?>
<?php if (!empty($projectEditorOnly) && !empty($projectEditorPayload)): ?>
    <script type="application/json"
        data-project-editor-payload><?= json_encode($projectEditorPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
<?php endif; ?>
<?php if (empty($projectEditorOnly))
    require __DIR__ . '/_public-description-modal.php'; ?>
<script type="application/json"
    id="apAuthorsCatalog"><?= json_encode(array_values((array) ($catalogs['students'] ?? [])), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
<div class="ap-modal" id="apModal" hidden>
    <div class="ap-editor" role="dialog" aria-modal="true" aria-labelledby="apTitle">
        <header class="ap-editor-header">
            <h2 id="apTitle">Editar</h2><button data-close type="button" aria-label="Cerrar"><i
                    class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </header>
        <form id="apForm"><input type="hidden" name="_csrf" value="<?= e($projectCsrf) ?>"><input type="hidden" name="id">
            <div class="ap-editor-body">
                <section class="ap-section">
                    <h3><i class="fa-regular fa-id-card" aria-hidden="true"></i>Identificación</h3>
                    <div class="ap-grid"><label>Título<input name="title" required maxlength="240"></label><input
                            type="hidden" name="project_type_id">
                        <div class="ap-readonly" aria-label="Tipo de proyecto"><i class="fa-solid fa-folder-tree"
                                aria-hidden="true"></i><span><small>Tipo de proyecto</small><strong
                                    data-project-type>Sin información</strong></span></div>
                    </div>
                </section>
                <section class="ap-section">
                    <h3><i class="fa-solid fa-align-left" aria-hidden="true"></i>Descripción</h3><input type="hidden"
                        name="subtitle"><label>Descripción<textarea name="summary"
                            data-project-summary></textarea></label>
                </section>
                <section class="ap-section">
                    <h3><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i>Información académica</h3>
                    <div class="ap-grid" data-project-academic-grid><label class="ap-protected"><select name="career_id"
                                required>
                                <option value="">Selecciona</option><?php foreach ($catalogs['careers'] as $i): ?>
                                    <option value="<?= $i['id'] ?>"><?= e($i['name']) ?></option><?php endforeach; ?>
                            </select><span class="ap-fixed-field" data-fixed-field="career_id" aria-label="Carrera"><i
                                    class="fa-solid fa-code"
                                    aria-hidden="true"></i><span><small>Carrera</small><strong>Desarrollo de
                                        Software</strong></span></span></label><label class="ap-protected"><select
                                name="academic_period_id" required>
                                <option value="">Selecciona</option><?php foreach ($catalogs['periods'] as $i): ?>
                                    <option value="<?= $i['id'] ?>"><?= e($i['name']) ?></option><?php endforeach; ?>
                            </select><span class="ap-fixed-field" data-fixed-field="academic_period_id"
                                aria-label="Período académico"><i class="fa-solid fa-calendar-days"
                                    aria-hidden="true"></i><span><small>Período
                                        académico</small><strong><?= e($catalogs['periods'][0]['name'] ?? 'Sin período activo') ?></strong></span></span></label><input
                            type="hidden" name="tutor_id">
                        <div class="ap-tutoring" data-project-tutoring>
                            <div class="ap-tutoring-heading"><i class="fa-solid fa-user-tie" aria-hidden="true"></i>
                                <div><small>Tutoría</small><strong>Docentes responsables del proyecto</strong></div>
                            </div>
                            <aside class="ap-tutoring-warning"><i class="fa-solid fa-triangle-exclamation"
                                    aria-hidden="true"></i>
                                <p>Los cambios en la Tutoría afectan a los docentes responsables del proyecto y quedarán
                                    registrados en el historial administrativo.<small>El proyecto debe conservar al
                                        menos un tutor.</small></p>
                            </aside>
                            <div class="ap-tutoring-list" data-tutoring-list></div>
                            <div class="ap-tutoring-editor" data-tutoring-editor hidden></div><button
                                class="ap-tutoring-add" type="button" data-tutoring-add><i class="fa-solid fa-plus"
                                    aria-hidden="true"></i>Añadir tutor</button>
                            <p class="ap-tutoring-note" role="status" aria-live="polite" data-tutoring-note hidden></p>
                            <script type="application/json"
                                data-tutoring-catalog><?= json_encode(array_values((array) ($catalogs['teachers'] ?? [])), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
                        </div>
                        <div class="ap-readonly ap-project-status-card"><i class="fa-solid fa-circle-check"
                                aria-hidden="true"></i><span><small>Estado</small><strong data-project-status>Sin
                                    información</strong><input type="hidden" name="status" value="development">
                                <div class="ap-project-status-actions" data-project-status-actions hidden></div>
                                <p class="ap-project-status-complete" data-project-status-complete hidden>El proyecto ha
                                    finalizado su flujo académico.</p>
                            </span></div>
                        <div class="ap-readonly"><i class="fa-solid fa-list-check"
                                aria-hidden="true"></i><span><small>Etapa académica</small><strong
                                    data-project-stage><?= e($projectStageLabel) ?></strong></span></div>
                    </div>
                </section>
                <section class="ap-section">
                    <h3><i class="fa-solid fa-tags" aria-hidden="true"></i>Clasificación</h3>
                    <div class="ap-keyword-selector" data-project-keyword-selector><button class="ap-keyword-trigger"
                            type="button" role="combobox" aria-haspopup="listbox" aria-expanded="false"
                            aria-controls="apKeywordPanel" data-project-keyword-trigger><span
                                data-project-keyword-summary>Selecciona etiquetas de clasificación</span><i
                                class="fa-solid fa-chevron-down" aria-hidden="true"></i></button>
                        <div class="ap-keyword-panel" id="apKeywordPanel" hidden data-project-keyword-panel><label
                                class="ap-keyword-search"><i class="fa-solid fa-magnifying-glass"
                                    aria-hidden="true"></i><span class="sr-only">Buscar etiquetas de
                                    clasificación</span><input type="search"
                                    placeholder="Buscar etiquetas de clasificación…" autocomplete="off"
                                    data-project-keyword-search></label>
                            <div class="ap-keyword-options" role="listbox" aria-multiselectable="true"
                                data-project-keyword-options></div>
                            <p class="ap-keyword-limit" role="status" aria-live="polite" hidden
                                data-project-keyword-limit>Máximo 4 etiquetas de clasificación.</p>
                        </div>
                    </div>
                    <div class="ap-keyword-chips" aria-live="polite" data-project-keyword-chips></div><small
                        class="ap-section-note">Máximo cuatro etiquetas.</small>
                    <script type="application/json"
                        data-project-keyword-catalog><?= json_encode(array_values((array) ($catalogs['keywords'] ?? [])), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
                </section>
                <section class="ap-section">
                    <h3><i class="fa-solid fa-building-columns" aria-hidden="true"></i>Información institucional</h3>
                    <div class="ap-institutional">
                        <div class="ap-readonly"><small>Código</small><strong data-project-code>Sin información</strong>
                        </div>
                        <div class="ap-readonly"><i class="fa-solid fa-calendar-days"
                                aria-hidden="true"></i><span><small>Fecha de publicación</small><strong
                                    data-project-published>Sin publicar</strong></span></div>
                        <div class="ap-readonly is-discreet"><small>Última actualización</small><strong
                                data-project-updated>Sin información</strong></div>
                    </div>
                </section>
                <details class="ap-advanced" data-advanced-options hidden>
                    <summary><span><i class="fa-solid fa-sliders"></i> Opciones avanzadas</span><i
                            class="fa-solid fa-chevron-down"></i></summary>
                    <div class="ap-advanced-content">
                        <div class="ap-advanced-grid"><a data-manage-participants href="#"><i
                                    class="fa-solid fa-users"></i><span><strong>Participantes</strong><small>Administrar
                                        autor o autores, cambiar el líder y revisar la composición del
                                        proyecto.</small></span><i class="fa-solid fa-arrow-right"></i></a><a
                                data-manage-files href="#"><i
                                    class="fa-regular fa-folder-open"></i><span><strong>Documentación</strong><small>Los
                                        archivos, versiones y documentos de presentación se administran desde la pestaña
                                        Documentos.</small><em>Ir a Documentos</em></span><i
                                    class="fa-solid fa-arrow-right"></i></a></div>
                    </div>
                </details>
                <aside class="ap-edit-warning" data-edit-warning hidden><i class="fa-solid fa-triangle-exclamation"></i>
                    <p>Las modificaciones quedarán registradas en el historial administrativo y podrán reflejarse en los
                        reportes institucionales.</p>
                </aside>
                <p id="apMessage" class="ap-message" hidden></p>
            </div>
            <footer class="ap-editor-footer"><span class="ap-change-state" data-change-state aria-live="polite"
                    hidden><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><span>Hay cambios
                        pendientes.</span></span>
                <div><button data-close type="button">Cancelar</button><button type="submit">Guardar cambios</button>
                </div>
            </footer>
        </form>
    </div>
</div>
<div class="ap-confirm" id="apSaveConfirm" hidden>
    <div role="alertdialog" aria-modal="true" aria-labelledby="apSaveConfirmTitle"><span><i
                class="fa-solid fa-triangle-exclamation"></i></span>
        <h2 id="apSaveConfirmTitle">¿Guardar estos cambios?</h2>
        <p>Esta acción modificará el expediente. Los cambios se registrarán con tu usuario, fecha y hora, y podrán
            aparecer en reportes institucionales.</p>
        <div><button type="button" data-cancel-save>Revisar cambios</button><button type="button"
                data-confirm-save>Guardar cambios</button></div>
    </div>
</div>
<?php $projectRetentionDays = (new SystemSettingModel())->retentionDays('retention_projects_days'); ?>
<div class="ap-modal" id="apTrash" hidden>
    <div class="small">
        <header>
            <h2>Enviar a la Papelera</h2><button data-close-trash type="button" aria-label="Cerrar ventana">×</button>
        </header>
        <p>El proyecto conservará sus documentos y podrá restaurarse durante <?= $projectRetentionDays ?> días.</p>
        <form id="apTrashForm"><input type="hidden" name="_csrf" value="<?= e($projectCsrf) ?>"><input type="hidden"
                name="id"><label>Motivo<textarea name="reason" required minlength="5"></textarea></label>
            <footer><button data-close-trash type="button">Cancelar</button><button type="submit"
                    class="danger">Enviar</button></footer>
        </form>
    </div>
</div>
<div id="apConfig" data-save="<?= e($projectEndpoints['save']) ?>" data-trash="<?= e($projectEndpoints['trash']) ?>"></div>
<?php if (!empty($projectStatusDialog['enabled']))
    require __DIR__ . '/../repository/_project-status-transition-dialog.php'; ?>
<div class="ap-modal" id="apPresentation" hidden>
    <div class="small" role="dialog" aria-modal="true" aria-labelledby="apPresentationTitle">
        <header>
            <h2 id="apPresentationTitle">Elegir archivo de presentación</h2><button type="button"
                data-cancel-presentation aria-label="Cerrar ventana">×</button>
        </header>
        <p class="ap-presentation-copy">Selecciona el archivo que se mostrará automáticamente cuando una persona ingrese
            a este expediente. Esta elección no indica que el archivo sea más importante que los demás.</p>
        <div class="ap-presentation-options" data-presentation-options></div>
        <p class="ap-presentation-error" data-presentation-error hidden></p>
        <footer><button type="button" data-cancel-presentation>Cancelar</button><button type="button"
                data-confirm-presentation>Continuar con la publicación</button></footer>
    </div>
</div>
