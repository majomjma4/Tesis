<?php
/**
 * Pestaña Documentos - Workspace Documental (3 Columnas Flexibles)
 */
?>
<div class="sw-doc-workspace">
    <!-- Columna 1: Explorador de Archivos (22%) -->
    <aside class="sw-explorer-panel" id="swExplorerPanel">
        <div class="sw-explorer-header">
            <span class="sw-explorer-title"><i class="fa-solid fa-folder-open" style="color: var(--sw-primary);"></i> Archivos</span>
            <button type="button" class="sw-tab-btn" id="swToggleExplorer" title="Contraer / Expandir Explorador" style="padding: 0.2rem 0.4rem;">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
        </div>
        
        <!-- Acciones en estado En desarrollo -->
        <div class="sw-explorer-toolbar" style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
            <button type="button" class="sw-tab-btn" style="border: 1px solid var(--sw-border); background: var(--sw-bg-soft); border-radius: 6px; padding: 0.3rem 0.5rem; font-size: 0.75rem;" data-sw-modal-open="swModalUploadVersion">
                <i class="fa-solid fa-plus"></i> Agregar
            </button>
        </div>

        <ul class="sw-tree-list">
            <li class="sw-tree-item is-selected" data-file-type="docx">
                <div class="sw-tree-item-info">
                    <i class="fa-solid fa-file-word" style="color: #2563eb;"></i>
                    <span style="overflow: hidden; text-overflow: ellipsis;">Informe_practicas.docx</span>
                </div>
                <span class="sw-file-badge obs">3 obs</span>
            </li>
            <li class="sw-tree-item" data-file-type="pdf">
                <div class="sw-tree-item-info">
                    <i class="fa-solid fa-file-pdf" style="color: #dc2626;"></i>
                    <span style="overflow: hidden; text-overflow: ellipsis;">Cronograma.pdf</span>
                </div>
                <span class="sw-file-badge ok">Sin obs</span>
            </li>
            <li class="sw-tree-item" data-file-type="zip">
                <div class="sw-tree-item-info">
                    <i class="fa-solid fa-file-zipper" style="color: #d97706;"></i>
                    <span style="overflow: hidden; text-overflow: ellipsis;">Evidencias.zip</span>
                </div>
                <span class="sw-file-badge pending">Aprobado</span>
            </li>
        </ul>
    </aside>

    <!-- Columna 2: Visor de Documento / Estado Vacío (53%) -->
    <main class="sw-viewer-panel">
        <div class="sw-viewer-toolbar">
            <div class="sw-viewer-doc-info">
                <i class="fa-solid fa-file-word" style="font-size: 1.25rem; color: #2563eb;"></i>
                <div>
                    <span class="sw-viewer-doc-title">Informe_practicas.docx</span>
                    <span style="font-size: 0.75rem; color: var(--sw-text-muted); display: block;">Versión 4 · Modificado 06/08/2026</span>
                </div>
            </div>
            <div class="sw-viewer-actions">
                <button type="button" class="sw-tab-btn" style="border: 1px solid var(--sw-border); background: #fff; border-radius: 6px; padding: 0.35rem 0.75rem; font-size: 0.8rem;" data-sw-modal-open="swModalWorkWord">
                    <i class="fa-solid fa-file-word" style="color: #2563eb;"></i> Trabajar en Word
                </button>
                <button type="button" class="sw-tab-btn" style="border: 1px solid var(--sw-border); background: #fff; border-radius: 6px; padding: 0.35rem 0.75rem; font-size: 0.8rem;" data-sw-modal-open="swModalBasicEditor">
                    <i class="fa-solid fa-pen-to-square"></i> Editar cambios sencillos
                </button>
                <button type="button" class="sw-tab-btn" style="border: 1px solid var(--sw-border); background: #fff; border-radius: 6px; padding: 0.35rem 0.75rem; font-size: 0.8rem;" onclick="alert('Descargando archivo simulado...');">
                    <i class="fa-solid fa-download"></i>
                </button>
            </div>
        </div>

        <div class="sw-viewer-canvas">
            <!-- Mock Hoja DOCX -->
            <article class="sw-docx-paper">
                <h1 class="sw-docx-title">Informe de Prácticas Preprofesionales</h1>
                <p style="font-size: 0.95rem; margin-bottom: 1.25rem;">
                    El presente informe detalla las actividades desarrolladas durante el periodo académico 2026-I en la institución receptora. 
                    <span class="sw-docx-marker" data-marker-id="obs-1">Especificar qué medio de transporte fue utilizado para los traslados de campo.<span class="sw-docx-tag">1</span></span>
                </p>
                
                <h2 style="font-size: 1.1rem; font-weight: 700; color: #1e293b; margin: 1.5rem 0 0.75rem 0;">1. Introducción y Objetivos</h2>
                <p style="font-size: 0.95rem; margin-bottom: 1.25rem;">
                    Se cumplieron las metas fijadas en el plan de trabajo preliminar. 
                    <span class="sw-docx-marker" data-marker-id="obs-2">Incluir la tabla de métricas de desempeño cuantitativo en la sección de anexos.<span class="sw-docx-tag">2</span></span>
                </p>

                <!-- Ejemplo de Tabla Sencilla -->
                <table style="width: 100%; border-collapse: collapse; margin: 1.5rem 0; font-size: 0.85rem;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                            <th style="padding: 0.6rem; text-align: left;">Fase</th>
                            <th style="padding: 0.6rem; text-align: left;">Horas</th>
                            <th style="padding: 0.6rem; text-align: left;">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="border-bottom: 1px solid #e2e8f0;">
                            <td style="padding: 0.6rem;">Planificación</td>
                            <td style="padding: 0.6rem;">40 hrs</td>
                            <td style="padding: 0.6rem; color: #15803d; font-weight: 700;">Completado</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #e2e8f0;">
                            <td style="padding: 0.6rem;">Ejecución de campo</td>
                            <td style="padding: 0.6rem;">120 hrs</td>
                            <td style="padding: 0.6rem; color: #0369a1; font-weight: 700;">En proceso</td>
                        </tr>
                    </tbody>
                </table>
            </article>
        </div>
    </main>

    <!-- Columna 3: Panel de Observaciones (25%) -->
    <aside class="sw-observations-panel" id="swObsPanel">
        <div class="sw-obs-header">
            <span class="sw-obs-title"><i class="fa-solid fa-comments" style="color: #b45309;"></i> Observaciones (3)</span>
            <button type="button" class="sw-tab-btn" id="swToggleObs" title="Contraer / Expandir Panel" style="padding: 0.2rem 0.4rem;">
                <i class="fa-solid fa-chevron-right"></i>
            </button>
        </div>

        <div style="display: flex; flex-direction: column; gap: 0.75rem; overflow-y: auto;">
            <!-- Observación Específica 1 -->
            <article class="sw-obs-card" data-obs-id="obs-1">
                <div class="sw-obs-top">
                    <span class="sw-obs-num">Observación 1 · Pág. 1</span>
                    <label style="display: flex; align-items: center; gap: 0.3rem; font-size: 0.7rem; cursor: pointer;">
                        <input type="checkbox" class="sw-obs-check"> ☐ Hecha
                    </label>
                </div>
                <p class="sw-obs-text">“Especificar qué medio de transporte fue utilizado para los traslados de campo.”</p>
                <span style="font-size: 0.7rem; color: var(--sw-text-muted);">Lic. Diana Alegría · Hace 2 días</span>
            </article>

            <!-- Observación Específica 2 -->
            <article class="sw-obs-card" data-obs-id="obs-2">
                <div class="sw-obs-top">
                    <span class="sw-obs-num">Observación 2 · Pág. 1</span>
                    <label style="display: flex; align-items: center; gap: 0.3rem; font-size: 0.7rem; cursor: pointer;">
                        <input type="checkbox" class="sw-obs-check"> ☐ Hecha
                    </label>
                </div>
                <p class="sw-obs-text">“Incluir la tabla de métricas de desempeño cuantitativo en la sección de anexos.”</p>
                <span style="font-size: 0.7rem; color: var(--sw-text-muted);">Lic. Diana Alegría · Hace 2 días</span>
            </article>

            <!-- Observación General Neutral en Proyecto Aprobado / Recomendación -->
            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 0.85rem; display: flex; flex-direction: column; gap: 0.4rem; margin-top: 0.5rem;">
                <span style="font-size: 0.75rem; font-weight: 800; color: #15803d;"><i class="fa-solid fa-circle-check"></i> Observación general del tutor</span>
                <p style="font-size: 0.8rem; color: #166534; margin: 0; line-height: 1.4;">
                    El documento se encuentra aprobado en su estructura principal. Considera esta recomendación final de ortotipografía antes de la impresión definitiva.
                </p>
            </div>
        </div>
    </aside>
</div>
