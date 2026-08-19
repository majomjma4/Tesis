<header class="rp-head"><span>Administración</span><h1>Auditoría y reportes</h1><p>Indicadores institucionales y trazabilidad obtenidos desde MariaDB.</p></header>
<?php if($reportError):?><p class="rp-error"><?=e($reportError)?></p><?php endif;?>
<?php
    $defaultFrom = (string)($reportBaseFrom ?? $reportFrom);
    $defaultTo = (string)($reportBaseTo ?? $reportTo);
    $isCustomFilterActive = ($reportFrom !== $defaultFrom || $reportTo !== $defaultTo);
    $resetUrl = route('admin-reports');
?>
<div class="rp-filter-wrapper">
    <?php if ($isCustomFilterActive): ?>
        <div class="rp-active-filter-badge">
            <span>Período filtrado: <strong><?=e(date('d/m/Y', strtotime($reportFrom)))?> – <?=e(date('d/m/Y', strtotime($reportTo)))?></strong></span>
            <a href="<?=e($resetUrl)?>" title="Quitar filtro de fecha y restaurar predeterminado" aria-label="Quitar filtro de fecha">×</a>
        </div>
    <?php endif; ?>

    <button type="button" class="rp-filter-trigger <?= $isCustomFilterActive ? 'is-active' : '' ?>" id="rpFilterBtn" aria-expanded="false" aria-controls="rpDatePopover">
        <i class="fa-solid fa-calendar-days"></i>
        <span>Filtrar por fecha</span>
    </button>

    <!-- Popover Panel Contextual -->
    <div class="rp-popover-panel" id="rpDatePopover" hidden>
        <form class="rp-popover-form" id="rpPopoverForm" action="index.php" method="get">
            <input type="hidden" name="page" value="admin-reports">
            
            <div class="rp-popover-field">
                <label for="rpPopoverFromInput">Desde</label>
                <input type="date" name="from" id="rpPopoverFromInput" value="<?=e($reportFrom)?>" required>
            </div>

            <div class="rp-popover-field">
                <label for="rpPopoverToInput">Hasta</label>
                <input type="date" name="to" id="rpPopoverToInput" value="<?=e($reportTo)?>" max="<?=e(date('Y-m-d'))?>" required>
            </div>

            <div class="rp-popover-error" id="rpPopoverError" hidden style="display:none; color: #dc2626; font-size: 0.78rem; font-weight: 600; margin-top: 4px;"></div>

            <div class="rp-popover-actions">
                <button type="button" class="rp-popover-clear" id="rpPopoverClearBtn" <?=$isCustomFilterActive ? '' : 'disabled'?>>Limpiar filtros</button>
                <button type="submit" class="rp-popover-apply">Aplicar</button>
            </div>
        </form>
    </div>
</div>

<section class="rp-stats">
    <article><strong><?=$reportData['summary']['users']?></strong><span>Usuarios creados</span></article>
    <article><strong><?=$reportData['summary']['projects']?></strong><span>Proyectos registrados</span></article>
    <article><strong><?=$reportData['summary']['deliveries']?></strong><span>Entregas recibidas</span></article>
    <article><strong><?=$reportData['summary']['actions']?></strong><span>Acciones relevantes</span></article>
</section>

<div class="rp-grid rp-grid-three">
    <section class="rp-card-equal">
        <h2>Usuarios por rol</h2>
        <div class="rp-roles-table-wrapper">
            <table class="rp-roles-table">
                <thead>
                    <tr>
                        <th>Rol</th>
                        <th class="rp-num"><i class="fa-solid fa-user-check" data-tooltip="Activos" aria-label="Activos" tabindex="0"></i></th>
                        <th class="rp-num"><i class="fa-solid fa-user-slash" data-tooltip="Bloqueados" aria-label="Bloqueados" tabindex="0"></i></th>
                        <th class="rp-num"><i class="fa-solid fa-trash-can" data-tooltip="En papelera" aria-label="En papelera" tabindex="0"></i></th>
                        <th class="rp-num"><i class="fa-solid fa-users" data-tooltip="Total" aria-label="Total" tabindex="0"></i></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($reportData['roles'] as $row):?>
                    <tr>
                        <td><strong><?=e($row['label'])?></strong></td>
                        <td class="rp-num"><?=$row['active']?></td>
                        <td class="rp-num"><?=$row['blocked']?></td>
                        <td class="rp-num"><?=$row['trash']?></td>
                        <td class="rp-num"><strong><?=$row['total']?></strong></td>
                    </tr>
                    <?php endforeach;?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="rp-card-equal">
        <h2>Proyectos por estado</h2>
        <div class="rp-equal-list">
            <?php if(!$reportData['statuses']):?>
                <p class="rp-empty">No hay registros para el período seleccionado.</p>
            <?php else:foreach($reportData['statuses'] as $row):?>
                <a class="rp-bar" href="<?=e($row['url'])?>"><span><?=e($row['label'])?></span><strong><?=$row['total']?></strong></a>
            <?php endforeach;endif;?>
        </div>
    </section>

    <section class="rp-card-equal">
        <h2>Situación de revisión</h2>
        <div class="rp-equal-list">
            <?php if(!$reportData['reviewSituations']):?>
                <p class="rp-empty">No hay registros para el período seleccionado.</p>
            <?php else:foreach($reportData['reviewSituations'] as $row):?>
                <a class="rp-bar is-review-situation" href="<?=e($row['url'])?>"><span><?=e($row['label'])?></span><strong><?=$row['total']?></strong></a>
            <?php endforeach;endif;?>
        </div>
    </section>
</div>

<section class="rp-activity">
    <header>
        <h2>Actividad auditada</h2>
        <span>Eventos del período</span>
    </header>
    <?php if(!$reportData['activity']):?>
        <p class="rp-empty">No se registraron acciones relevantes en este período.</p>
    <?php else:foreach($reportData['activity'] as $item):?>
        <article>
            <i class="fa-solid fa-shield-halved"></i>
            <div>
                <strong><?=e($item['action_label'])?></strong>
                <p><?=e($item['actor']?:'Sistema')?> · <?=e($item['entity_label'])?></p>
            </div>
            <time><?=e($item['created_at_local'] ?? '')?></time>
        </article>
    <?php endforeach;?>

    <footer class="rp-pagination-bar">
        <?php
            $pagination = $pagePaginationData ?? $reportData['pagination'] ?? [];
            $reportCurrentPage = (int)($pagination['page'] ?? 1);
            $totalPages = (int)($pagination['pages'] ?? 1);
            $perPage = (int)($pagination['per_page'] ?? 10);
            $totalItems = (int)($pagination['total'] ?? 0);
            $fromItem = (int)($pagination['from'] ?? 0);
            $toItem = (int)($pagination['to'] ?? 0);

            $buildUrl = static function(int $p, ?int $size = null) use ($reportFrom, $reportTo, $perPage): string {
                $sz = $size ?? $perPage;
                return route('admin-reports') . '&from=' . urlencode($reportFrom) . '&to=' . urlencode($reportTo) . '&report_page=' . $p . '&reports_per_page=' . $sz;
            };
        ?>
        <div class="rp-pagination-summary">
            Mostrando <strong><?=$toItem?></strong> de <strong><?=$totalItems?></strong> eventos
        </div>

        <div class="rp-pagination-controls">
            <label class="rp-size-label">
                Mostrar
                <select onchange="location.href=this.value;">
                    <?php foreach([10, 25, 50, 100] as $sz): ?>
                        <option value="<?=e($buildUrl(1, $sz))?>" <?=$sz === $perPage ? 'selected' : ''?>><?=$sz?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <nav class="rp-pagination-pages" aria-label="Navegación de páginas">
                <?php if ($reportCurrentPage > 1): ?>
                    <a class="rp-page-btn" href="<?=e($buildUrl($reportCurrentPage - 1))?>" title="Página anterior">‹</a>
                <?php else: ?>
                    <span class="rp-page-btn is-disabled">‹</span>
                <?php endif; ?>

                <?php
                    $range = [];
                    $delta = 2;
                    for ($i = 1; $i <= $totalPages; $i++) {
                        if ($i === 1 || $i === $totalPages || ($i >= $reportCurrentPage - $delta && $i <= $reportCurrentPage + $delta)) {
                            $range[] = $i;
                        }
                    }
                    $last = 0;
                    foreach ($range as $i):
                        if ($last > 0 && $i - $last > 1):
                ?>
                            <span class="rp-page-ellipsis">…</span>
                <?php
                        endif;
                        if ($i === $reportCurrentPage):
                ?>
                            <span class="rp-page-btn is-active"><?=$i?></span>
                <?php else: ?>
                            <a class="rp-page-btn" href="<?=e($buildUrl($i))?>"><?=$i?></a>
                <?php
                        endif;
                        $last = $i;
                    endforeach;
                ?>

                <?php if ($reportCurrentPage < $totalPages): ?>
                    <a class="rp-page-btn" href="<?=e($buildUrl($reportCurrentPage + 1))?>" title="Página siguiente">›</a>
                <?php else: ?>
                    <span class="rp-page-btn is-disabled">›</span>
                <?php endif; ?>
            </nav>

            <form class="rp-jump-form" method="GET" action="index.php">
                <input type="hidden" name="page" value="admin-reports">
                <input type="hidden" name="from" value="<?=e($reportFrom)?>">
                <input type="hidden" name="to" value="<?=e($reportTo)?>">
                <input type="hidden" name="reports_per_page" value="<?=$perPage?>">
                <label>
                    Ir a página:
                    <input type="number" name="report_page" min="1" max="<?=$totalPages?>" value="<?=$reportCurrentPage?>" required>
                </label>
                <button type="submit" class="rp-jump-btn">Ir</button>
            </form>
        </div>
    </footer>
    <?php endif;?>
</section>

<section class="rp-exports">
    <div>
        <h2>Exportar información</h2>
        <p>Genera reportes administrativos en PDF formal o descarga datos en CSV.</p>
    </div>
    <nav class="rp-compact-exports">
        <button type="button" class="rp-export-trigger" data-export-type="users"><i class="fa-solid fa-users"></i> Usuarios</button>
        <button type="button" class="rp-export-trigger" data-export-type="projects"><i class="fa-solid fa-folder-open"></i> Proyectos</button>
        <button type="button" class="rp-export-trigger" data-export-type="audit"><i class="fa-solid fa-clock-rotate-left"></i> Auditoría</button>
    </nav>
</section>

<!-- Modal Generar Reporte -->
<?php
    $monthsEs = [1=>'Enero', 2=>'Febrero', 3=>'Marzo', 4=>'Abril', 5=>'Mayo', 6=>'Junio', 7=>'Julio', 8=>'Agosto', 9=>'Septiembre', 10=>'Octubre', 11=>'Noviembre', 12=>'Diciembre'];
    $currentMonthNum = (int)date('n');
    $currentYearNum = (int)date('Y');
    $currentMonthName = $monthsEs[$currentMonthNum] . ' ' . $currentYearNum;
    
    $prevMonthNum = $currentMonthNum === 1 ? 12 : $currentMonthNum - 1;
    $prevYearNum = $currentMonthNum === 1 ? $currentYearNum - 1 : $currentYearNum;
    $prevMonthName = $monthsEs[$prevMonthNum] . ' ' . $prevYearNum;

    $currentDay = (int)date('j');
?>
<div class="rp-modal-overlay" id="rpExportModal" hidden>
    <div class="rp-modal" role="dialog" aria-modal="true" aria-labelledby="rpModalTitle">
        <header class="rp-modal-head">
            <div>
                <h2 id="rpModalTitle">Generar reporte</h2>
                <div class="rp-modal-subtitle" id="rpModalSubtitleDisplay">
                    <i class="fa-solid fa-users"></i> <span>Reporte de Usuarios</span>
                </div>
            </div>
            <button type="button" class="rp-modal-close" id="rpModalCloseBtn" aria-label="Cerrar modal"><i class="fa-solid fa-xmark"></i></button>
        </header>
        <form class="rp-modal-form" id="rpExportForm" action="<?=e(route('admin-report-export'))?>" method="get">
            <div class="rp-modal-body">
                <input type="hidden" name="page" value="admin-report-export">
                <input type="hidden" name="type" id="rpModalTypeInput" value="users">
                
                <div class="rp-modal-field">
                    <label for="rpModalScopeSelect">Alcance</label>
                    <select name="scope" id="rpModalScopeSelect" class="rp-modal-select" data-native-select>
                        <option value="this_month" selected><?=e($currentMonthName)?></option>
                        <option value="last_month"><?=e($prevMonthName)?></option>
                        <option value="7days">Últimos 7 días</option>
                        <option value="academic_period">Período académico actual</option>
                        <option value="custom">Personalizado...</option>
                    </select>
                </div>

                <div class="rp-modal-warning-banner" id="rpModalWarningBanner">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <div>
                        <strong>El mes de <?=e(mb_strtolower($monthsEs[$currentMonthNum]))?> aún no ha finalizado.</strong><br>
                        El reporte incluirá únicamente la información registrada hasta el <?=e(date('j \d\e ') . mb_strtolower($monthsEs[$currentMonthNum]) . date(' \d\e Y'))?>.
                    </div>
                </div>

                <div class="rp-modal-empty-banner" id="rpModalEmptyBanner" hidden style="display:none;">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div id="rpModalEmptyText">
                        No hay información para el período seleccionado. Prueba con otro alcance o un rango personalizado.
                    </div>
                </div>

                <div class="rp-modal-grid-dates" id="rpModalCustomDates" hidden style="display:none;">
                    <div class="rp-modal-field">
                        <label for="rpModalFromInput">Desde</label>
                        <input type="date" name="from" id="rpModalFromInput" value="<?=e(date('Y-m-01'))?>" class="rp-modal-input">
                    </div>
                    <div class="rp-modal-field">
                        <label for="rpModalToInput">Hasta</label>
                        <input type="date" name="to" id="rpModalToInput" value="<?=e(date('Y-m-d'))?>" max="<?=e(date('Y-m-d'))?>" class="rp-modal-input">
                    </div>
                </div>

                <div class="rp-modal-field">
                    <label>Formato</label>
                    <div class="rp-modal-formats">
                        <label class="rp-format-option">
                            <input type="radio" name="format" value="word" checked>
                            <div class="rp-format-info">
                                <strong><i class="fa-solid fa-file-word" style="color: #2b579a;"></i> WORD</strong>
                                <span class="rp-format-tag">Reporte formal</span>
                                <small>Documento diseñado para presentar, imprimir o guardar posteriormente como PDF.</small>
                            </div>
                        </label>
                        <label class="rp-format-option">
                            <input type="radio" name="format" value="csv">
                            <div class="rp-format-info">
                                <strong><i class="fa-solid fa-file-csv"></i> CSV</strong>
                                <span class="rp-format-tag">Datos editables</span>
                                <small>Datos simples para trabajar en Excel u otras hojas de cálculo.</small>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <footer class="rp-modal-footer">
                <button type="button" class="rp-btn-cancel" id="rpModalCancelBtn">Cancelar</button>
                <button type="submit" class="rp-btn-submit" id="rpModalSubmitBtn" data-no-skeleton data-record-download>Generar reporte</button>
            </footer>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Popover Date Filter Controller
    const filterBtn = document.getElementById('rpFilterBtn');
    const popover = document.getElementById('rpDatePopover');
    const popoverForm = document.getElementById('rpPopoverForm');
    const popoverFromInput = document.getElementById('rpPopoverFromInput');
    const popoverToInput = document.getElementById('rpPopoverToInput');
    const popoverClearBtn = document.getElementById('rpPopoverClearBtn');

    const defaultFromStr = '<?=e($defaultFrom)?>';
    const defaultToStr = '<?=e($defaultTo)?>';
    const resetUrlStr = '<?=e($resetUrl)?>';

    function togglePopover() {
        if (!popover) return;
        const isHidden = popover.hidden;
        popover.hidden = !isHidden;
        filterBtn.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
        if (isHidden) {
            popoverFromInput.focus();
        }
    }

    function closePopover() {
        if (!popover) return;
        popover.hidden = true;
        filterBtn.setAttribute('aria-expanded', 'false');
    }

    if (filterBtn) {
        filterBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            togglePopover();
        });
    }

    if (popover) {
        popover.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }

    document.addEventListener('click', function(e) {
        if (popover && !popover.hidden && !popover.contains(e.target) && e.target !== filterBtn) {
            closePopover();
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closePopover();
            closeModal();
        }
    });

    if (popoverClearBtn) {
        popoverClearBtn.addEventListener('click', function() {
            window.location.href = resetUrlStr;
        });
    }

    const popoverError = document.getElementById('rpPopoverError');

    if (popoverForm) {
        popoverForm.addEventListener('submit', function(e) {
            const fromVal = popoverFromInput.value;
            const toVal = popoverToInput.value;

            if (popoverError) {
                popoverError.hidden = true;
                popoverError.style.display = 'none';
            }

            if (!fromVal || !toVal) {
                e.preventDefault();
                if (popoverError) {
                    popoverError.textContent = 'Por favor selecciona ambas fechas.';
                    popoverError.hidden = false;
                    popoverError.style.display = 'block';
                }
                return false;
            }

            if (fromVal > toVal) {
                e.preventDefault();
                if (popoverError) {
                    popoverError.textContent = 'La fecha inicial no puede ser posterior a la fecha final.';
                    popoverError.hidden = false;
                    popoverError.style.display = 'block';
                }
                return false;
            }

            closePopover();
        });
    }

    // Controlador del Modal de Exportación
    const modal = document.getElementById('rpExportModal');
    const closeBtn = document.getElementById('rpModalCloseBtn');
    const cancelBtn = document.getElementById('rpModalCancelBtn');
    const exportForm = document.getElementById('rpExportForm');
    const typeInput = document.getElementById('rpModalTypeInput');
    const subtitleDisplay = document.getElementById('rpModalSubtitleDisplay');
    const scopeSelect = document.getElementById('rpModalScopeSelect');
    const customDatesGroup = document.getElementById('rpModalCustomDates');
    const modalFromInput = document.getElementById('rpModalFromInput');
    const modalToInput = document.getElementById('rpModalToInput');
    const warningBanner = document.getElementById('rpModalWarningBanner');

    const todayStr = '<?=e(date('Y-m-d'))?>';
    const firstDayThisMonthStr = '<?=e(date('Y-m-01'))?>';
    const firstDayLastMonthStr = '<?=e(date('Y-m-01', strtotime('first day of last month')))?>';
    const lastDayLastMonthStr = '<?=e(date('Y-m-t', strtotime('last month')))?>';

    const typeIcons = {
        'users': 'fa-users',
        'projects': 'fa-folder-open',
        'audit': 'fa-clock-rotate-left'
    };
    const typeNames = {
        'users': 'Reporte de Usuarios',
        'projects': 'Reporte de Proyectos',
        'audit': 'Reporte de Auditoría'
    };

    function getSevenDaysAgoStr() {
        const d = new Date();
        d.setDate(d.getDate() - 6);
        const yyyy = d.getFullYear();
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const dd = String(d.getDate()).padStart(2, '0');
        return `${yyyy}-${mm}-${dd}`;
    }

    const emptyBanner = document.getElementById('rpModalEmptyBanner');

    function updateScopeDates() {
        if (emptyBanner) {
            emptyBanner.hidden = true;
            emptyBanner.style.display = 'none';
        }

        const val = scopeSelect ? scopeSelect.value : 'this_month';
        if (val === 'custom') {
            customDatesGroup.hidden = false;
            customDatesGroup.style.display = 'grid';
            warningBanner.style.display = 'none';
            return;
        }
        
        customDatesGroup.hidden = true;
        customDatesGroup.style.display = 'none';

        if (val === 'this_month') {
            modalFromInput.value = firstDayThisMonthStr;
            modalToInput.value = todayStr;
            warningBanner.style.display = 'flex';
        } else if (val === 'last_month') {
            modalFromInput.value = firstDayLastMonthStr;
            modalToInput.value = lastDayLastMonthStr;
            warningBanner.style.display = 'none';
        } else if (val === '7days') {
            modalFromInput.value = getSevenDaysAgoStr();
            modalToInput.value = todayStr;
            warningBanner.style.display = 'none';
        } else if (val === 'academic_period') {
            modalFromInput.value = '2026-04-01';
            modalToInput.value = '2026-09-30';
            warningBanner.style.display = 'none';
        }
    }

    // Relocalizar el modal al <body> para escapar de cualquier stacking context o transform de contenedor
    if (modal && modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }

    let lastActiveTrigger = null;

    function openModal(type, triggerEl = null) {
        if (!modal) return;
        closePopover();
        if (triggerEl) {
            lastActiveTrigger = triggerEl;
        } else if (document.activeElement && document.activeElement !== document.body) {
            lastActiveTrigger = document.activeElement;
        }

        typeInput.value = type;
        if (subtitleDisplay) {
            const iconClass = typeIcons[type] || 'fa-file-lines';
            const nameLabel = typeNames[type] || 'Reporte';
            subtitleDisplay.innerHTML = `<i class="fa-solid ${iconClass}"></i> <span>${nameLabel}</span>`;
        }
        scopeSelect.value = 'this_month';
        updateScopeDates();
        
        modal.hidden = false;
        modal.removeAttribute('hidden');
        modal.setAttribute('aria-hidden', 'false');
        modal.classList.add('is-open');
        document.body.classList.add('rp-modal-active');

        // Mover foco al primer control del modal después de remover aria-hidden
        setTimeout(function() {
            if (scopeSelect) scopeSelect.focus();
        }, 30);
    }

    function closeModal() {
        if (!modal) return;

        // 1. Quitar el foco de cualquier elemento dentro del modal ANTES de ocultarlo para evitar el aviso de aria-hidden
        if (document.activeElement && modal.contains(document.activeElement)) {
            document.activeElement.blur();
        }

        if (emptyBanner) {
            emptyBanner.hidden = true;
            emptyBanner.style.display = 'none';
        }

        // 2. Ocultar modal y aplicar aria-hidden/hidden
        modal.hidden = true;
        modal.setAttribute('hidden', '');
        modal.setAttribute('aria-hidden', 'true');
        modal.classList.remove('is-open');
        document.body.classList.remove('rp-modal-active');

        // 3. Devolver el foco al botón disparador original si existe
        if (lastActiveTrigger && typeof lastActiveTrigger.focus === 'function') {
            lastActiveTrigger.focus();
        }
    }

    document.querySelectorAll('.rp-export-trigger').forEach(function(trigger) {
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const type = this.getAttribute('data-export-type');
            openModal(type, this);
        });
    });

    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeModal();
        });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal && modal.classList.contains('is-open')) {
            closeModal();
        }
    });

    if (scopeSelect) {
        scopeSelect.addEventListener('change', updateScopeDates);
    }

    if (exportForm) {
        exportForm.addEventListener('submit', function(e) {
            const fromVal = modalFromInput.value;
            const toVal = modalToInput.value;
            
            if (!fromVal || !toVal) {
                e.preventDefault();
                alert('Por favor selecciona las fechas del rango.');
                return false;
            }

            if (fromVal > toVal) {
                e.preventDefault();
                alert('La fecha "Desde" no puede ser posterior a la fecha "Hasta".');
                return false;
            }

            if (toVal > todayStr && scopeSelect.value === 'custom') {
                e.preventDefault();
                alert('La fecha "Hasta" no puede ser posterior a la fecha actual.');
                return false;
            }

            // Ocultar banner previo de vacio
            if (emptyBanner) {
                emptyBanner.hidden = true;
                emptyBanner.style.display = 'none';
            }

            // Peticion previa AJAX de verificación para evitar descargas sin registros
            e.preventDefault();
            const submitBtn = document.getElementById('rpModalSubmitBtn');
            if (submitBtn) submitBtn.disabled = true;

            const url = new URL(exportForm.action, window.location.origin);
            url.searchParams.set('page', 'admin-report-export');
            url.searchParams.set('type', typeInput.value);
            url.searchParams.set('scope', scopeSelect.value);
            url.searchParams.set('from', fromVal);
            url.searchParams.set('to', toVal);
            url.searchParams.set('format', exportForm.querySelector('input[name="format"]:checked').value);

            fetch(url.toString(), {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function(res) {
                if (!res.ok) {
                    return res.json().then(function(data) {
                        throw new Error(data.message || 'No hay información para el período seleccionado.');
                    });
                }
                // Si la respuesta fue OK (200), se descarga mediante iframe o redirección de ventana sin cerrar si da error
                window.location.href = url.toString();
                setTimeout(closeModal, 600);
            }).catch(function(err) {
                if (emptyBanner) {
                    const textContainer = document.getElementById('rpModalEmptyText');
                    if (textContainer) textContainer.textContent = err.message || 'No hay información para el período seleccionado. Prueba con otro alcance o un rango personalizado.';
                    emptyBanner.hidden = false;
                    emptyBanner.style.display = 'flex';
                } else {
                    alert(err.message);
                }
            }).finally(function() {
                if (submitBtn) submitBtn.disabled = false;
            });
        });
    }
});
</script>
