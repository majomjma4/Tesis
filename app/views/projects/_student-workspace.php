<?php
/**
 * Vista Raíz del Workspace para Estudiante (_student-workspace.php)
 * Integrada en detail.php cuando $isStudentContext === true
 * @var array<string, mixed> $project
 */
$projectId = (int) ($project['id'] ?? 1);
$typeCode = mb_strtolower((string)($project['type_code'] ?? ''), 'UTF-8');
$typeName = mb_strtolower((string)($project['type_name'] ?? $project['type'] ?? ''), 'UTF-8');
$isDegreeProject = in_array($typeCode, ['thesis','tesis','degree','titulacion','titulación'], true) || str_contains($typeName, 'titul');

$statusKey = (string) ($project['status_key'] ?? 'development');
$statusLabel = (string) ($project['status'] ?? 'En desarrollo');
$situation = (string) ($project['review_situation_label'] ?? 'Preparando primera entrega');

// Control visual de simulaciones en entorno DEV
$isDevEnv = function_exists('app_is_development') ? app_is_development() : true;
?>
<div class="student-workspace">

    <!-- 1. Encabezado del proyecto -->
    <header class="sw-header-card">
        <div class="sw-header-top">
            <div>
                <div class="sw-header-meta">
                    <span class="sw-badge-type"><?= e($project['type'] ?? 'Prácticas preprofesionales') ?></span>
                    <span class="sw-code"><?= e($project['code'] ?? 'PRA-2026-001') ?></span>
                </div>
                <h1 class="sw-title"><?= e($project['title'] ?? 'Informe de prácticas preprofesionales') ?></h1>
            </div>
            
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <span class="sw-badge-status is-<?= e($statusKey) ?>">
                    <i class="fa-solid fa-circle-dot"></i> Estado: <?= e($statusLabel) ?>
                </span>
                <?php if ($statusKey === 'development'): ?>
                    <button type="button" class="sw-tab-btn" style="background: var(--sw-primary); color: #fff; border-radius: 8px; font-weight: 700; padding: 0.5rem 1rem;" data-sw-modal-open="swModalSendReview">
                        <i class="fa-solid fa-paper-plane"></i> Enviar a revisión
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="sw-header-info-grid">
            <div class="sw-info-item">
                <span class="sw-info-label">Situación</span>
                <span class="sw-info-value"><?= e($situation) ?></span>
            </div>
            <div class="sw-info-item">
                <span class="sw-info-label">Periodo Académico</span>
                <span class="sw-info-value"><?= e($project['period'] ?? '2026-I') ?></span>
            </div>
            <div class="sw-info-item">
                <span class="sw-info-label">Tutor</span>
                <span class="sw-info-value"><?= e($project['tutor'] ?? 'Lic. Diana Alegría') ?></span>
            </div>
            <div class="sw-info-item">
                <span class="sw-info-label">Última actualización</span>
                <span class="sw-info-value"><?= e($project['last_activity'] ?? date('d/m/Y H:i')) ?></span>
            </div>
        </div>
    </header>

    <!-- 2. Acción recomendada (Bloque Contextual) -->
    <section class="sw-action-banner">
        <div class="sw-action-content">
            <div class="sw-action-icon"><i class="fa-solid fa-lightbulb"></i></div>
            <div class="sw-action-text">
                <h4>Siguiente paso recomendado</h4>
                <p>Agrega o revisa los archivos de tu proyecto. Cuando todo esté listo, envía la entrega a tu tutor para su revisión.</p>
            </div>
        </div>
    </section>

    <!-- 3. Línea Horizontal del Recorrido de Estados -->
    <section class="sw-timeline-card">
        <div class="sw-timeline-header">
            <span class="sw-timeline-title"><i class="fa-solid fa-route" style="color: var(--sw-primary);"></i> Recorrido de estados del expediente</span>
            <button type="button" class="sw-tab-btn" id="swToggleTimeline" style="font-size: 0.8rem; padding: 0.2rem 0.5rem;">
                <span>Ver recorrido completo</span> <i class="fa-solid fa-chevron-down"></i>
            </button>
        </div>

        <div class="sw-timeline-horizontal">
            <div class="sw-timeline-step">
                <span class="sw-timeline-node">En desarrollo</span>
                <span class="sw-timeline-line"></span>
            </div>
            <div class="sw-timeline-step">
                <span class="sw-timeline-node">En revisión</span>
                <span class="sw-timeline-line"></span>
            </div>
            <div class="sw-timeline-step">
                <span class="sw-timeline-node is-active">En desarrollo (● Estado actual)</span>
                <span class="sw-timeline-line"></span>
            </div>
            <div class="sw-timeline-step">
                <span class="sw-timeline-node">Aprobado</span>
            </div>
        </div>

        <div class="sw-timeline-vertical" id="swVerticalTimeline" hidden>
            <div class="sw-timeline-vnode"><i class="fa-solid fa-circle-dot"></i> 1. En desarrollo — Entrega inicial creada</div>
            <div class="sw-timeline-vnode"><i class="fa-solid fa-circle-dot"></i> 2. En revisión — Enviado a tutor</div>
            <div class="sw-timeline-vnode"><i class="fa-solid fa-circle-dot"></i> 3. En desarrollo — Requiere correcciones (Actual)</div>
            <div class="sw-timeline-vnode" style="color: var(--sw-text-muted);"><i class="fa-regular fa-circle"></i> 4. En revisión — Pendiente</div>
            <div class="sw-timeline-vnode" style="color: var(--sw-text-muted);"><i class="fa-regular fa-circle"></i> 5. Aprobado — Pendiente</div>
        </div>
    </section>

    <!-- 4. Navegación por Pestañas Principales -->
    <nav class="sw-tabs-nav" aria-label="Navegación interna del expediente">
        <button type="button" class="sw-tab-btn is-active" data-sw-tab="summary"><i class="fa-solid fa-circle-info"></i> Resumen</button>
        <button type="button" class="sw-tab-btn" data-sw-tab="documents"><i class="fa-solid fa-folder-open"></i> Documentos</button>
        <button type="button" class="sw-tab-btn" data-sw-tab="observations"><i class="fa-solid fa-comments"></i> Observaciones</button>
        <button type="button" class="sw-tab-btn" data-sw-tab="versions"><i class="fa-solid fa-clock-rotate-left"></i> Versiones</button>
        <button type="button" class="sw-tab-btn" data-sw-tab="history"><i class="fa-solid fa-list-check"></i> Historial</button>
        <?php if ($isDegreeProject): ?>
            <button type="button" class="sw-tab-btn" data-sw-tab="tribunal"><i class="fa-solid fa-gavel"></i> Tribunal y defensa</button>
        <?php endif; ?>
    </nav>

    <!-- 5. Contenido de las Pestañas (Partiales Modulares) -->
    <main>
        <section class="sw-tab-pane is-active" id="swTab-summary">
            <?php require __DIR__ . '/student-workspace/_summary.php'; ?>
        </section>
        <section class="sw-tab-pane" id="swTab-documents">
            <?php require __DIR__ . '/student-workspace/_documents.php'; ?>
        </section>
        <section class="sw-tab-pane" id="swTab-observations">
            <?php require __DIR__ . '/student-workspace/_observations.php'; ?>
        </section>
        <section class="sw-tab-pane" id="swTab-versions">
            <?php require __DIR__ . '/student-workspace/_versions.php'; ?>
        </section>
        <section class="sw-tab-pane" id="swTab-history">
            <?php require __DIR__ . '/student-workspace/_history.php'; ?>
        </section>
        <?php if ($isDegreeProject): ?>
            <section class="sw-tab-pane" id="swTab-tribunal">
                <?php require __DIR__ . '/student-workspace/_tribunal.php'; ?>
            </section>
        <?php endif; ?>
    </main>

</div>

<!-- Modales Simulados Reutilizables -->
<!-- Modal Enviar a Revisión -->
<div class="sw-modal-overlay" id="swModalSendReview" hidden>
    <div class="sw-modal-dialog">
        <header class="sw-modal-header">
            <h3>Enviar proyecto a revisión</h3>
            <button type="button" class="sw-tab-btn" data-sw-modal-close="swModalSendReview"><i class="fa-solid fa-xmark"></i></button>
        </header>
        <div class="sw-modal-body">
            <p><strong>Tutor de seguimiento:</strong> Lic. Diana Alegría</p>
            <p><strong>Archivos incluidos:</strong></p>
            <ul style="margin: 0; padding-left: 1.25rem; font-size: 0.85rem;">
                <li>Informe_practicas.docx</li>
                <li>Cronograma.pdf</li>
                <li>Evidencias.zip</li>
            </ul>
            <div style="background: #fffbeeb; border: 1px solid #fde68a; color: #92400e; padding: 0.85rem; border-radius: 8px; font-size: 0.85rem; display: flex; gap: 0.5rem; align-items: flex-start;">
                <i class="fa-solid fa-triangle-exclamation" style="margin-top: 0.2rem;"></i>
                <span>Una vez enviada esta entrega, los archivos quedarán bloqueados hasta que el tutor finalice la revisión.</span>
            </div>
        </div>
        <footer class="sw-modal-footer">
            <button type="button" class="sw-tab-btn" style="border: 1px solid var(--sw-border);" data-sw-modal-close="swModalSendReview">Cancelar</button>
            <button type="button" class="sw-tab-btn" style="background: var(--sw-primary); color: #fff;" id="swConfirmSend">Confirmar envío</button>
        </footer>
    </div>
</div>

<!-- Modal Trabajar en Word (Caso A vs B) -->
<div class="sw-modal-overlay" id="swModalWorkWord" hidden>
    <div class="sw-modal-dialog">
        <header class="sw-modal-header">
            <h3>Trabajar en Word</h3>
            <button type="button" class="sw-tab-btn" data-sw-modal-close="swModalWorkWord"><i class="fa-solid fa-xmark"></i></button>
        </header>
        <div class="sw-modal-body">
            <div style="background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; padding: 0.85rem; border-radius: 8px; font-size: 0.85rem;">
                <strong><i class="fa-solid fa-info-circle"></i> El tutor realizó cambios en tu documento:</strong>
                <ol style="margin: 0.5rem 0 0 0; padding-left: 1.25rem;">
                    <li>Descarga el documento actualizado.</li>
                    <li>Busca tu archivo de trabajo anterior.</li>
                    <li>Reemplázalo por el archivo descargado.</li>
                    <li>Continúa realizando las correcciones en Word.</li>
                </ol>
            </div>
            <p style="font-size: 0.8rem; color: var(--sw-text-muted); margin: 0;">No perderás la versión anterior. El sistema conserva automáticamente el historial.</p>
        </div>
        <footer class="sw-modal-footer">
            <button type="button" class="sw-tab-btn" style="border: 1px solid var(--sw-border);" data-sw-modal-close="swModalWorkWord">Cancelar</button>
            <button type="button" class="sw-tab-btn" style="background: var(--sw-primary); color: #fff;" id="swConfirmWordDownload">Descargar documento actualizado</button>
        </footer>
    </div>
</div>

<!-- Modal Editor Básico -->
<div class="sw-modal-overlay" id="swModalBasicEditor" hidden>
    <div class="sw-modal-dialog" style="max-width: 680px;">
        <header class="sw-modal-header">
            <h3>Editor básico de correcciones</h3>
            <button type="button" class="sw-tab-btn" data-sw-modal-close="swModalBasicEditor"><i class="fa-solid fa-xmark"></i></button>
        </header>
        <div class="sw-modal-body">
            <div style="background: #fffbeeb; border: 1px solid #fde68a; color: #92400e; padding: 0.75rem; border-radius: 8px; font-size: 0.8rem;">
                <i class="fa-solid fa-triangle-exclamation"></i> Utiliza esta herramienta únicamente para cambios sencillos de texto y formato. Para modificar imágenes, tablas, portada o formato avanzado, trabaja directamente en Word.
            </div>
            <div style="border: 1px solid var(--sw-border); border-radius: 8px; overflow: hidden;">
                <div style="background: var(--sw-bg-soft); padding: 0.4rem 0.6rem; border-bottom: 1px solid var(--sw-border); display: flex; gap: 0.4rem;">
                    <button type="button" class="sw-tab-btn" style="padding: 0.2rem 0.5rem; border: 1px solid var(--sw-border); background: #fff;" onclick="document.execCommand('bold', false, null);"><i class="fa-solid fa-bold"></i></button>
                    <button type="button" class="sw-tab-btn" style="padding: 0.2rem 0.5rem; border: 1px solid var(--sw-border); background: #fff;" onclick="document.execCommand('italic', false, null);"><i class="fa-solid fa-italic"></i></button>
                    <button type="button" class="sw-tab-btn" style="padding: 0.2rem 0.5rem; border: 1px solid var(--sw-border); background: #fff;" onclick="document.execCommand('underline', false, null);"><i class="fa-solid fa-underline"></i></button>
                </div>
                <div contenteditable="true" style="min-height: 200px; padding: 1rem; font-family: sans-serif; font-size: 0.9rem; outline: none; background: #fff;">
                    Informe de prácticas preprofesionales con correcciones directas aplicadas...
                </div>
            </div>
        </div>
        <footer class="sw-modal-footer">
            <button type="button" class="sw-tab-btn" style="border: 1px solid var(--sw-border);" data-sw-modal-close="swModalBasicEditor">Cancelar</button>
            <button type="button" class="sw-tab-btn" style="background: var(--sw-primary); color: #fff;" onclick="alert('Cambios sencillos guardados.'); document.getElementById('swModalBasicEditor').hidden = true;">Guardar cambios</button>
        </footer>
    </div>
</div>

<!-- Selector Flotante de Simulación en Entorno DEV -->
<?php if ($isDevEnv): ?>
<div class="sw-dev-simulator">
    <span><i class="fa-solid fa-vial-circle-check" style="color: #38bdf8;"></i> Simulación visual DEV:</span>
    <select id="swDevScenarioSelector">
        <option value="development">1. En desarrollo</option>
        <option value="review">2. En revisión</option>
        <option value="corrections">3. Requiere correcciones</option>
        <option value="approved">4. Aprobado por tutor</option>
        <option value="tribunal">5. En tribunal</option>
        <option value="tribunal_approved">6. Aprobado por el tribunal</option>
        <option value="published">7. Publicado</option>
    </select>
</div>
<?php endif; ?>
