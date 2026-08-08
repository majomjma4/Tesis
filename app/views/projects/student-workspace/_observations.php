<?php
/**
 * Pestaña Observaciones - Vista de seguimiento detallado
 */
?>
<div style="background: var(--surface, #fff); border: 1px solid var(--sw-border); border-radius: 12px; padding: 1.5rem; display: flex; flex-direction: column; gap: 1.25rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--sw-text); margin: 0;">Observaciones registradas por el Tutor</h3>
            <p style="font-size: 0.85rem; color: var(--sw-text-muted); margin: 0.2rem 0 0 0;">Utiliza la lista de verificación para organizar tus correcciones antes de la siguiente entrega.</p>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <span style="font-size: 0.8rem; background: #fef3c7; color: #b45309; font-weight: 700; padding: 0.3rem 0.65rem; border-radius: 6px;">3 Pendientes</span>
            <span style="font-size: 0.8rem; background: #dcfce7; color: #15803d; font-weight: 700; padding: 0.3rem 0.65rem; border-radius: 6px;">1 Realizada</span>
        </div>
    </div>

    <!-- Lista Completa de Observaciones -->
    <div style="display: flex; flex-direction: column; gap: 1rem;">
        <article style="background: var(--sw-bg-soft); border: 1px solid var(--sw-border); border-radius: 10px; padding: 1rem; display: flex; flex-direction: column; gap: 0.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <strong style="font-size: 0.9rem; color: #b45309;">Observación #1 — Informe_practicas.docx (Pág. 1)</strong>
                <span style="font-size: 0.75rem; color: var(--sw-text-muted);">04/08/2026</span>
            </div>
            <p style="font-size: 0.9rem; color: var(--sw-text); margin: 0;">Especificar qué medio de transporte fue utilizado para los traslados de campo durante la fase 2.</p>
        </article>

        <article style="background: var(--sw-bg-soft); border: 1px solid var(--sw-border); border-radius: 10px; padding: 1rem; display: flex; flex-direction: column; gap: 0.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <strong style="font-size: 0.9rem; color: #b45309;">Observación #2 — Informe_practicas.docx (Pág. 1)</strong>
                <span style="font-size: 0.75rem; color: var(--sw-text-muted);">04/08/2026</span>
            </div>
            <p style="font-size: 0.9rem; color: var(--sw-text); margin: 0;">Incluir la tabla de métricas de desempeño cuantitativo en la sección de anexos institucionales.</p>
        </article>
    </div>
</div>
