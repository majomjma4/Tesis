<?php
/**
 * Pestaña Versiones - Historial de Entregas y Documentos
 */
?>
<div style="background: var(--surface, #fff); border: 1px solid var(--sw-border); border-radius: 12px; padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
    <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--sw-text); margin: 0;"><i class="fa-solid fa-clock-rotate-left" style="color: var(--sw-primary);"></i> Historial de Versiones guardadas</h3>
    
    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
        <div style="display: flex; align-items: center; justify-content: space-between; background: var(--sw-bg-soft); padding: 0.85rem 1rem; border-radius: 8px; border: 1px solid var(--sw-border);">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <span style="font-size: 0.8rem; font-weight: 800; background: #e0f2fe; color: #0369a1; padding: 0.2rem 0.5rem; border-radius: 4px;">v4.0 (Actual)</span>
                <div>
                    <strong style="font-size: 0.9rem; color: var(--sw-text); display: block;">Correcciones enviadas por María José Monteros</strong>
                    <span style="font-size: 0.75rem; color: var(--sw-text-muted);">06/08/2026 14:30 · 3 archivos incluidos</span>
                </div>
            </div>
            <button type="button" class="sw-tab-btn" style="border: 1px solid var(--sw-border); background: #fff; border-radius: 6px; padding: 0.3rem 0.6rem; font-size: 0.8rem;" onclick="alert('Descargando copia de versión v4.0...');">
                <i class="fa-solid fa-download"></i> Descargar
            </button>
        </div>

        <div style="display: flex; align-items: center; justify-content: space-between; background: var(--sw-bg-soft); padding: 0.85rem 1rem; border-radius: 8px; border: 1px solid var(--sw-border); opacity: 0.85;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <span style="font-size: 0.8rem; font-weight: 800; background: #f1f5f9; color: #475569; padding: 0.2rem 0.5rem; border-radius: 4px;">v3.0</span>
                <div>
                    <strong style="font-size: 0.9rem; color: var(--sw-text); display: block;">Revisión registrada por Tutor Lic. Diana Alegría</strong>
                    <span style="font-size: 0.75rem; color: var(--sw-text-muted);">04/08/2026 10:15 · Con observaciones</span>
                </div>
            </div>
            <button type="button" class="sw-tab-btn" style="border: 1px solid var(--sw-border); background: #fff; border-radius: 6px; padding: 0.3rem 0.6rem; font-size: 0.8rem;" onclick="alert('Descargando copia de versión v3.0...');">
                <i class="fa-solid fa-download"></i> Descargar
            </button>
        </div>
    </div>
</div>
