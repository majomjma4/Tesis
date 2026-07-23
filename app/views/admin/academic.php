<?php
$periods = $academic['periods'] ?? [];
$activePeriod = $academic['promotion']['source'] ?? null;
$plannedPeriod = $academic['promotion']['target'] ?? null;
$closedPeriods = array_values(array_filter($periods, static fn(array $period): bool => ($period['status'] ?? '') === 'closed'));
$suggestedPeriod = $academic['promotion']['suggested'] ?? null;
$activeTypeCount = count(array_filter($academic['types'] ?? [], static fn(array $type): bool => (int) ($type['is_active'] ?? 0) === 1));
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
    <p>Configura los períodos institucionales y los tipos disponibles para los proyectos.</p>
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
                    <div><strong><?= (int) ($activePeriod['projects'] ?? 0) ?></strong><small>Proyectos del período</small></div>
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

        <?php if ($closedPeriods): ?>
            <div class="aa-history">
                <header><div><small>Registro institucional</small><h3>Historial de períodos</h3></div><span><?= count($closedPeriods) ?></span></header>
                <div class="aa-history-list" role="table" aria-label="Historial de períodos cerrados">
                    <?php foreach ($closedPeriods as $period): ?>
                        <div class="aa-history-row" role="row">
                            <strong role="cell"><?= e($period['name']) ?></strong>
                            <span role="cell"><?= e($friendlyRange($period['starts_on'], $period['ends_on'])) ?></span>
                            <span role="cell" class="aa-status closed">Cerrado</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <section class="aa-section" aria-labelledby="aaProjectTypesTitle">
        <header class="aa-section-heading">
            <div>
                <span>Catálogo de proyectos</span>
                <h2 id="aaProjectTypesTitle">Tipos de proyecto</h2>
                <small><?= $activeTypeCount ?> tipo<?= $activeTypeCount === 1 ? '' : 's' ?> registrado<?= $activeTypeCount === 1 ? '' : 's' ?></small>
                <p>Define las categorías disponibles sin mostrar códigos internos del sistema.</p>
            </div>
            <button type="button" data-form="type"><i class="fa-solid fa-plus" aria-hidden="true"></i> Agregar tipo</button>
        </header>

        <div class="aa-type-list">
            <?php foreach ($academic['types'] as $type): ?>
                <article class="<?= (int) $type['is_active'] === 1 ? '' : 'is-inactive' ?>">
                    <span class="aa-type-icon"><i class="fa-regular fa-folder" aria-hidden="true"></i></span>
                    <div>
                        <strong><?= e($type['name']) ?></strong>
                        <small><?= (int) $type['projects'] ?> proyecto<?= (int) $type['projects'] === 1 ? '' : 's' ?> asociado<?= (int) $type['projects'] === 1 ? '' : 's' ?></small>
                    </div>
                    <span class="aa-status <?= (int) $type['is_active'] === 1 ? 'active' : 'closed' ?>">
                        <?= (int) $type['is_active'] === 1 ? 'Activo' : 'Inactivo' ?>
                    </span>
                    <div class="aa-type-actions">
                        <button type="button" class="aa-secondary" data-edit-type data-id="<?= (int) $type['id'] ?>" data-name="<?= e($type['name']) ?>">Editar</button>
                        <?php if ((int) $type['is_active'] === 1): ?>
                            <button type="button" class="aa-danger-text" data-deactivate-type data-id="<?= (int) $type['id'] ?>" data-name="<?= e($type['name']) ?>">Desactivar</button>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
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
        <div><button type="button" class="aa-secondary" data-cancel-confirm>Cancelar</button><button type="button" class="aa-warning" data-accept-confirm>Confirmar</button></div>
    </div>
</div>

<div
    id="aaConfig"
    data-save="<?= e($academicEndpoints['save']) ?>"
    data-promote="<?= e($academicEndpoints['promote']) ?>"
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
