<?php
$periods = $academic['periods'] ?? [];
$activePeriod = $academic['promotion']['source'] ?? null;
$plannedPeriod = $academic['promotion']['target'] ?? null;
$closedPeriods = array_values(array_filter($periods, static fn(array $period): bool => ($period['status'] ?? '') === 'closed'));
$suggestedPeriod = $academic['promotion']['suggested'] ?? null;
$reversal = $academic['reversal'] ?? null;
$academicReversalHours = max(1, (int) (new SystemSettingModel())->retentionDays('academic_period_reversal_hours'));
$typeCount = count($academic['types'] ?? []);
$materialTypeCount = count($academic['material_types'] ?? []);
$keywordCount = count($academic['keywords'] ?? []);
$catalogActions = static function (string $entity, array $item): void {
    $active = (int) ($item['is_active'] ?? 0) === 1;
    $associated = (int) ($item['references_count'] ?? $item['materials'] ?? $item['projects'] ?? 0);
    $name = (string) ($item['name'] ?? '');
    ?>
    <div class="aa-type-actions">
        <button type="button" class="aa-secondary" data-catalog-edit data-entity="<?= e($entity) ?>" data-id="<?= (int) $item['id'] ?>" data-name="<?= e($name) ?>">Editar</button>
        <?php if ($active): ?>
            <button type="button" class="aa-icon-action is-deactivate" data-catalog-state data-aa-tooltip="Desactivar" data-entity="<?= e($entity) ?>" data-action="deactivate" data-id="<?= (int) $item['id'] ?>" data-name="<?= e($name) ?>" aria-label="Desactivar <?= e($name) ?>"><i class="fa-solid fa-ban" aria-hidden="true"></i></button>
        <?php else: ?>
            <button type="button" class="aa-activate" data-catalog-state data-aa-tooltip="Activar" data-entity="<?= e($entity) ?>" data-action="activate" data-id="<?= (int) $item['id'] ?>" data-name="<?= e($name) ?>"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Activar</button>
        <?php endif; ?>
        <span class="aa-action-tooltip"<?= $associated > 0 ? ' tabindex="0" role="note" data-aa-tooltip="No puede eliminarse porque tiene elementos asociados." aria-label="No puede eliminarse porque tiene elementos asociados."' : '' ?>>
            <button type="button" class="aa-icon-action is-delete" data-catalog-delete<?= $associated === 0 ? ' data-aa-tooltip="Eliminar"' : '' ?> data-entity="<?= e($entity) ?>" data-id="<?= (int) $item['id'] ?>" data-name="<?= e($name) ?>" aria-label="Eliminar <?= e($name) ?>"<?= $associated > 0 ? ' disabled aria-disabled="true"' : '' ?>><i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>
        </span>
    </div>
    <?php
};
$catalogHeader = static function (
    string $panelId,
    string $category,
    string $title,
    string $description,
    int $count
): void {
    ?>
    <header class="aa-accordion-header">
        <button type="button" class="aa-accordion-toggle" data-aa-accordion-toggle
            aria-expanded="false" aria-controls="<?= e($panelId) ?>">
            <span class="aa-accordion-copy">
                <span class="aa-accordion-category"><?= e($category) ?></span>
                <strong><?= e($title) ?></strong>
                <span><?= e($description) ?></span>
            </span>
            <span class="aa-accordion-meta">
                <span class="aa-accordion-count" aria-label="<?= $count ?> registros"><?= $count ?></span>
                <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
            </span>
        </button>
    </header>
    <?php
};
$minimumPlanningStart = $activePeriod
    ? (new DateTimeImmutable($activePeriod['ends_on']))->modify('+1 day')->format('Y-m-d')
    : '';
$today = new DateTimeImmutable('today');
$activePeriodEnded = $activePeriod
    ? new DateTimeImmutable($activePeriod['ends_on']) <= $today
    : false;
$activePeriodClosesEarly = $activePeriod
    ? new DateTimeImmutable($activePeriod['ends_on']) > $today
    : false;
$periodProjectsUrl = static fn(array $period): string =>
    route('projects') . '&period_id=' . (int) $period['id'];
$repositoryPeriodUrl = static fn(array $period): string =>
    route('admin-repository') . '&period=' . rawurlencode((string) $period['name']);
$monthNames = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];
$friendlyRange = static function (?string $start, ?string $end) use ($monthNames): string {
    if (!$start || !$end) return 'Fechas pendientes';
    $startDate = new DateTimeImmutable($start);
    $endDate = new DateTimeImmutable($end);
    return $monthNames[(int) $startDate->format('n')] . ' de ' . $startDate->format('Y') . ' – ' . $monthNames[(int) $endDate->format('n')] . ' de ' . $endDate->format('Y');
};
?>

<header class="aa-head">
    <span>Administración</span>
    <h1>Gestión académica</h1>
    <p>Administra los períodos académicos y los catálogos institucionales utilizados en proyectos y materiales de apoyo.
</header>

<?php if ($academicError): ?>
    <p class="aa-error"><?= e($academicError) ?></p>
<?php endif; ?>

<div class="aa-workspace">
    <section class="aa-section" aria-labelledby="aaCurrentPeriodTitle">
        <header class="aa-section-heading">
            <div>
                <span>Configuración institucional</span>
                <h2 id="aaCurrentPeriodTitle">Período académico</h2>
                <p>Controla el período vigente y prepara únicamente su continuación inmediata.</p>
            </div>
        </header>

        <?php if (!$activePeriod): ?>
            <div class="aa-empty-state">
                <i class="fa-regular fa-calendar-xmark" aria-hidden="true"></i>
                <strong>No existe un período activo</strong>
                <p>Configura el primer período real desde el que comenzará a operar la plataforma.</p>
                <?php if (!$periods): ?><button type="button" data-form="period">Configurar primer período</button><?php endif; ?>
            </div>
        <?php else: ?>
            <article class="aa-current-period">
                <div class="aa-current-period-main">
                    <span class="aa-period-icon"><i class="fa-regular fa-calendar-check" aria-hidden="true"></i></span>
                    <div>
                        <small>Período académico actual</small>
                        <h3><?= e($activePeriod['name']) ?></h3>
                        <p><?= e($friendlyRange($activePeriod['starts_on'], $activePeriod['ends_on'])) ?></p>
                        <?php if ($activePeriodEnded): ?>
                            <p role="status">⚠ El período ya alcanzó su fecha de finalización y permanece activo hasta que un administrador realice el cierre manual.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="aa-period-summary">
                    <span class="aa-status active"><i class="fa-solid fa-circle" aria-hidden="true"></i> Activo</span>
                    <div>
                        <strong><?= (int) ($activePeriod['projects'] ?? 0) ?></strong>
                        <small>Proyectos del período</small>
                        <?php if ((int) ($activePeriod['projects'] ?? 0) > 0): ?>
                            <a class="aa-active-projects-link" href="<?= e($periodProjectsUrl($activePeriod)) ?>">
                                Ver proyectos <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <footer>
                    <?php if (!$plannedPeriod): ?>
                        <div class="aa-next-period">
                            <small>Siguiente período</small>
                            <strong>Todavía no se ha planificado el siguiente período</strong>
                            <span>Debes planificarlo antes de cerrar el período actual.</span>
                        </div>
                        <button type="button" class="aa-secondary" data-form="period">
                            <i class="fa-regular fa-calendar-plus" aria-hidden="true"></i>
                            Planificar <?= e($suggestedPeriod['name'] ?? 'siguiente período') ?>
                        </button>
                    <?php else: ?>
                        <div class="aa-next-period">
                            <small>Siguiente período planificado</small>
                            <strong><?= e($plannedPeriod['name']) ?></strong>
                            <span><?= e($friendlyRange($plannedPeriod['starts_on'], $plannedPeriod['ends_on'])) ?></span>
                        </div>
                        <button type="button" class="aa-secondary" data-edit-period
                            data-id="<?= (int) $plannedPeriod['id'] ?>"
                            data-start="<?= e($plannedPeriod['starts_on']) ?>"
                            data-end="<?= e($plannedPeriod['ends_on']) ?>">Editar planificación</button>
                        <button type="button" class="aa-danger-text" data-delete-period
                            data-id="<?= (int) $plannedPeriod['id'] ?>"
                            data-name="<?= e($plannedPeriod['name']) ?>">Eliminar planificación</button>
                    <?php endif; ?>
                    <button type="button" class="aa-warning" data-close-period
                        <?= !$plannedPeriod ? 'disabled title="Primero debes planificar el siguiente período."' : '' ?>>
                        <i class="fa-solid fa-lock" aria-hidden="true"></i> Cerrar período
                    </button>
                </footer>
            </article>
        <?php endif; ?>

        <?php if ($reversal): ?>
            <aside class="aa-reversal" aria-label="Reversión del cierre académico">
                <span class="aa-reversal-icon"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i></span>
                <div>
                    <small>Acción temporal</small>
                    <strong>Revertir cierre de <?= e($reversal['closed_period_name']) ?></strong>
                    <?php if ($reversal['available']): ?>
                        <p>Disponible hasta el <?= e($reversal['expires_label']) ?>. Solo procede si no existe actividad académica en <?= e($reversal['activated_period_name']) ?>.</p>
                    <?php else: ?>
                        <p><?= e($reversal['reason'] ?? 'La reversión ya no está disponible.') ?></p>
                    <?php endif; ?>
                </div>
                <button type="button" class="aa-secondary" data-revert-period
                    data-transition-id="<?= (int) $reversal['id'] ?>"
                    data-closed-name="<?= e($reversal['closed_period_name']) ?>"
                    <?= !$reversal['available'] ? 'disabled aria-disabled="true"' : '' ?>>
                    <i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Revertir cierre
                </button>
            </aside>
        <?php endif; ?>

        <?php if ($closedPeriods): ?>
            <div class="aa-history">
                <header>
                    <div><small>Registro institucional</small><h3>Historial de períodos académicos</h3></div>
                    <span><?= count($closedPeriods) ?></span>
                </header>
                <div class="aa-history-list">
                    <?php foreach ($closedPeriods as $period): ?>
                        <details class="aa-history-period">
                            <summary>
                                <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                                <strong><?= e($period['name']) ?></strong>
                                <span><small>Finalizado:</small> <?= e((new DateTimeImmutable($period['ends_on']))->format('d/m/Y')) ?></span>
                                <span><?= (int) $period['projects'] ?> proyecto<?= (int) $period['projects'] === 1 ? '' : 's' ?> publicado<?= (int) $period['projects'] === 1 ? '' : 's' ?></span>
                            </summary>
                            <div class="aa-history-period-content">
                                <?php if (!empty($period['project_preview'])): ?>
                                    <ul>
                                        <?php foreach ($period['project_preview'] as $project): ?>
                                            <li><a href="<?= e($repositoryPeriodUrl($period)) ?>"><?= e($project['title']) ?></a></li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <a class="aa-history-all" href="<?= e($repositoryPeriodUrl($period)) ?>">
                                        Ver todos los proyectos del <?= e($period['name']) ?> <span aria-hidden="true">→</span>
                                    </a>
                                <?php else: ?>
                                    <p>Este período no registra proyectos publicados.</p>
                                <?php endif; ?>
                            </div>
                        </details>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <section class="aa-section aa-catalog-accordion" data-aa-accordion data-catalog="type">
        <?php $catalogHeader('aaProjectTypesPanel', 'Catálogo de proyectos', 'Tipos de proyecto', 'Define las categorías disponibles para los proyectos.', $typeCount); ?>
        <div class="aa-accordion-panel" id="aaProjectTypesPanel" data-aa-accordion-panel aria-hidden="true" inert>
            <div class="aa-accordion-inner">
                <div class="aa-catalog-toolbar"><button type="button" data-form="type"><i class="fa-solid fa-plus" aria-hidden="true"></i> Agregar tipo</button></div>
                <div class="aa-type-list">
                    <?php foreach ($academic['types'] as $type): ?>
                        <article class="<?= (int) $type['is_active'] === 1 ? '' : 'is-inactive' ?>">
                            <span class="aa-type-icon"><i class="fa-regular fa-folder" aria-hidden="true"></i></span>
                            <div>
                                <strong><?= e($type['name']) ?></strong>
                                <small><?= (int) $type['projects'] ?> proyecto<?= (int) $type['projects'] === 1 ? '' : 's' ?> asociado<?= (int) $type['projects'] === 1 ? '' : 's' ?></small>
                            </div>
                            <span class="aa-status <?= (int) $type['is_active'] === 1 ? 'active' : 'closed' ?>"><?= (int) $type['is_active'] === 1 ? 'Activo' : 'Inactivo' ?></span>
                            <?php $catalogActions('type', $type); ?>
                            <?php $typeDescription = trim((string) ($type['description'] ?? '')); ?>
                            <div class="aa-type-description" data-project-type-description data-project-type-id="<?= (int) $type['id'] ?>">
                                <div class="aa-type-description-copy">
                                    <span>Descripción para el registro</span>
                                    <p class="<?= $typeDescription === '' ? 'is-empty' : '' ?>">
                                        <?= $typeDescription !== '' ? e($typeDescription) : 'No se ha definido una descripción.' ?>
                                    </p>
                                </div>
                                <div class="aa-type-description-actions" aria-label="Acciones de descripción para <?= e($type['name']) ?>">
                                    <?php if ($typeDescription !== ''): ?>
                                        <button type="button" class="aa-description-action" data-description-action="edit" data-project-type-id="<?= (int) $type['id'] ?>" data-name="<?= e($type['name']) ?>" data-description="<?= e($typeDescription) ?>">
                                            <i class="fa-solid fa-pencil" aria-hidden="true"></i> Editar
                                        </button>
                                        <button type="button" class="aa-description-action is-danger" data-description-action="delete" data-project-type-id="<?= (int) $type['id'] ?>" data-name="<?= e($type['name']) ?>">
                                            <i class="fa-regular fa-trash-can" aria-hidden="true"></i> Eliminar
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="aa-description-action" data-description-action="add" data-project-type-id="<?= (int) $type['id'] ?>" data-name="<?= e($type['name']) ?>">
                                            <i class="fa-solid fa-plus" aria-hidden="true"></i> Añadir descripción
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="aa-section aa-catalog-accordion" data-aa-accordion data-catalog="material_type">
        <?php $catalogHeader('aaMaterialTypesPanel', 'Materiales de apoyo', 'Tipos de material', 'Administra los tipos de documentos utilizados por Materiales de apoyo.', $materialTypeCount); ?>
        <div class="aa-accordion-panel" id="aaMaterialTypesPanel" data-aa-accordion-panel aria-hidden="true" inert>
            <div class="aa-accordion-inner">
                <div class="aa-catalog-toolbar"><button type="button" data-form="material_type"><i class="fa-solid fa-plus" aria-hidden="true"></i> Agregar tipo de material</button></div>
                <?php if (empty($academic['material_types'])): ?>
                    <div class="aa-empty-state"><i class="fa-regular fa-file-lines" aria-hidden="true"></i><strong>No existen tipos de material registrados.</strong></div>
                <?php else: ?>
                    <div class="aa-type-list">
                        <?php foreach ($academic['material_types'] as $materialType): ?>
                            <article class="<?= (int) $materialType['is_active'] === 1 ? '' : 'is-inactive' ?>">
                                <span class="aa-type-icon"><i class="fa-regular fa-file-lines" aria-hidden="true"></i></span>
                                <div>
                                    <strong><?= e($materialType['name']) ?></strong>
                                    <small><?= (int) $materialType['materials'] ?> material<?= (int) $materialType['materials'] === 1 ? '' : 'es' ?> asociado<?= (int) $materialType['materials'] === 1 ? '' : 's' ?></small>
                                </div>
                                <span class="aa-status <?= (int) $materialType['is_active'] === 1 ? 'active' : 'closed' ?>"><?= (int) $materialType['is_active'] === 1 ? 'Activo' : 'Inactivo' ?></span>
                                <?php $catalogActions('material_type', $materialType); ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="aa-section aa-catalog-accordion" data-aa-accordion data-catalog="keyword">
        <?php $catalogHeader('aaKeywordsPanel', 'Clasificación institucional', 'Palabras clave', 'Administra las palabras clave disponibles para clasificar Materiales de apoyo.', $keywordCount); ?>
        <div class="aa-accordion-panel" id="aaKeywordsPanel" data-aa-accordion-panel aria-hidden="true" inert>
            <div class="aa-accordion-inner">
                <div class="aa-catalog-toolbar"><button type="button" data-form="keyword"><i class="fa-solid fa-plus" aria-hidden="true"></i> Agregar palabra clave</button></div>
                <?php if (empty($academic['keywords'])): ?>
                    <div class="aa-empty-state"><i class="fa-solid fa-tags" aria-hidden="true"></i><strong>No existen palabras clave registradas.</strong></div>
                <?php else: ?>
                    <div class="aa-type-list">
                        <?php foreach ($academic['keywords'] as $keyword): ?>
                            <article class="<?= (int) $keyword['is_active'] === 1 ? '' : 'is-inactive' ?>">
                                <span class="aa-type-icon"><i class="fa-solid fa-tag" aria-hidden="true"></i></span>
                                <div><strong><?= e($keyword['name']) ?></strong><small><?= (int) $keyword['materials'] ?> material<?= (int) $keyword['materials'] === 1 ? '' : 'es' ?> asociado<?= (int) $keyword['materials'] === 1 ? '' : 's' ?></small></div>
                                <span class="aa-status <?= (int) $keyword['is_active'] === 1 ? 'active' : 'closed' ?>"><?= (int) $keyword['is_active'] === 1 ? 'Activo' : 'Inactivo' ?></span>
                                <?php $catalogActions('keyword', $keyword); ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>

<div class="aa-modal" id="aaModal" hidden>
    <form id="aaForm">
        <input type="hidden" name="_csrf" value="<?= e($academicCsrf) ?>">
        <input type="hidden" name="entity">
        <input type="hidden" name="id">
        <input type="hidden" name="action" value="save">
        <header>
            <div><small id="aaModalEyebrow">Configuración académica</small><h2 id="aaTitle"></h2></div>
            <button type="button" class="aa-modal-close" data-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
        </header>

        <div data-fields="period">
            <div class="aa-form-note">
                <i class="fa-regular fa-calendar-check" aria-hidden="true"></i>
                <div>
                    <small>Siguiente período</small>
                    <strong><?= e($suggestedPeriod['name'] ?? 'Período consecutivo') ?></strong>
                    <span>Se genera automáticamente como continuación del período actual.</span>
                </div>
            </div>
            <div class="aa-form-grid">
                <label>
                    Fecha de inicio
                    <span class="aa-date-control">
                        <input type="text" data-date-display="starts_on" placeholder="dd/mm/aaaa" readonly>
                        <button type="button" data-open-date="starts_on" aria-label="Seleccionar fecha de inicio"><i class="fa-regular fa-calendar"></i></button>
                    </span>
                    <input type="hidden" name="starts_on" data-date-value="starts_on" data-min-date="<?= e($minimumPlanningStart) ?>">
                </label>
                <label>
                    Fecha de finalización
                    <span class="aa-date-control">
                        <input type="text" data-date-display="ends_on" placeholder="dd/mm/aaaa" readonly>
                        <button type="button" data-open-date="ends_on" aria-label="Seleccionar fecha de finalización"><i class="fa-regular fa-calendar"></i></button>
                    </span>
                    <input type="hidden" name="ends_on" data-date-value="ends_on">
                </label>
            </div>
        </div>

        <div data-fields="type">
            <label>Nombre del tipo de proyecto<input name="name" maxlength="120" required></label>
        </div>
        <div data-fields="type_description">
            <p class="aa-form-context">Tipo de proyecto: <strong data-description-type-name></strong></p>
            <label>Descripción para el registro<textarea name="description" rows="6" maxlength="2000" required></textarea></label>
            <div class="aa-description-counter" data-description-counter aria-live="polite">0 / 2000</div>
            <p class="aa-form-help">Este texto se mostrará al estudiante cuando seleccione este tipo al registrar un proyecto.</p>
        </div>
        <div data-fields="keyword">
            <label>Nombre de la palabra clave<input name="name" maxlength="120" required></label>
        </div>
        <div data-fields="material_type">
            <label>Nombre del tipo de material<input name="name" maxlength="100" required></label>
        </div>

        <p id="aaMessage" class="aa-form-message" hidden></p>
        <footer><button type="button" class="aa-secondary" data-close>Cancelar</button><button type="submit" id="aaSubmit">Guardar</button></footer>
        <div class="aa-date-picker" data-date-picker hidden role="dialog" aria-label="Seleccionar fecha">
            <header>
                <button type="button" data-date-prev aria-label="Mes anterior"><i class="fa-solid fa-chevron-left"></i></button>
                <strong data-date-heading></strong>
                <button type="button" data-date-next aria-label="Mes siguiente"><i class="fa-solid fa-chevron-right"></i></button>
            </header>
            <div class="aa-date-weekdays" aria-hidden="true"><span>Lu</span><span>Ma</span><span>Mi</span><span>Ju</span><span>Vi</span><span>Sá</span><span>Do</span></div>
            <div class="aa-date-days" data-date-days></div>
        </div>
    </form>
</div>

<div class="aa-confirm" id="aaConfirm" hidden>
    <div role="alertdialog" aria-modal="true" aria-labelledby="aaConfirmTitle">
        <span><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></span>
        <h2 id="aaConfirmTitle">Confirmar acción</h2>
        <p id="aaConfirmText"></p>
        <section class="aa-closure-pending" data-closure-pending hidden>
            <ul data-closure-pending-list></ul>
        </section>
        <div class="aa-confirm-actions"><button type="button" class="aa-secondary" data-cancel-confirm>Cancelar</button><button type="button" class="aa-warning" data-accept-confirm>Confirmar</button></div>
    </div>
</div>

<div
    id="aaConfig"
    data-save="<?= e($academicEndpoints['save']) ?>"
    data-promote="<?= e($academicEndpoints['promote']) ?>"
    data-revert="<?= e($academicEndpoints['revert']) ?>"
    data-reversal-hours="<?= $academicReversalHours ?>"
    data-csrf="<?= e($academicCsrf) ?>"
    data-target-period="<?= (int) ($plannedPeriod['id'] ?? 0) ?>"
    data-close-early="<?= $activePeriodClosesEarly ? '1' : '0' ?>"
    data-current-period="<?= e($activePeriod['name'] ?? '') ?>"
    data-next-period="<?= e($plannedPeriod['name'] ?? '') ?>"
    data-active-projects="<?= (int) ($academic['promotion']['projects'] ?? 0) ?>"
    data-suggested-term="<?= e($suggestedPeriod['term'] ?? 'I') ?>"
    data-suggested-year="<?= (int) ($suggestedPeriod['year'] ?? date('Y')) ?>"
    data-suggested-name="<?= e($suggestedPeriod['name'] ?? 'siguiente período') ?>"
></div>
<div class="aa-toast" id="aaToast" role="status" aria-live="polite" hidden></div>
<div class="aa-tooltip" id="aaTooltip" role="tooltip" hidden></div>
