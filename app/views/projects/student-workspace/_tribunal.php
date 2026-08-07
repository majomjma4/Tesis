<?php
/**
 * Pestaña Tribunal y Defensa (Visible ÚNICAMENTE en Titulación y fases avanzadas)
 */
?>
<div style="background: var(--surface, #fff); border: 1px solid var(--sw-border); border-radius: 12px; padding: 1.5rem; display: flex; flex-direction: column; gap: 1.25rem;">
    <div>
        <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--sw-text); margin: 0;"><i class="fa-solid fa-gavel" style="color: #6b21a8;"></i> Tribunal Evaluador y Programación de Defensa</h3>
        <p style="font-size: 0.85rem; color: var(--sw-text-muted); margin: 0.2rem 0 0 0;">Los tres miembros del tribunal evaluador se encuentran registrados al mismo nivel institucional.</p>
    </div>

    <!-- 3 Docentes del Tribunal -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
        <div style="background: var(--sw-bg-soft); border: 1px solid var(--sw-border); border-radius: 10px; padding: 1rem; display: flex; align-items: center; gap: 0.75rem;">
            <div style="width: 38px; height: 38px; border-radius: 50%; background: #f3e8ff; color: #6b21a8; display: flex; align-items: center; justify-content: center; font-weight: 800;">
                <i class="fa-solid fa-user-graduate"></i>
            </div>
            <div>
                <strong style="font-size: 0.85rem; color: var(--sw-text); display: block;">Dr. Roberto Carlos</strong>
                <span style="font-size: 0.75rem; color: var(--sw-text-muted);">Miembro de Tribunal</span>
            </div>
        </div>

        <div style="background: var(--sw-bg-soft); border: 1px solid var(--sw-border); border-radius: 10px; padding: 1rem; display: flex; align-items: center; gap: 0.75rem;">
            <div style="width: 38px; height: 38px; border-radius: 50%; background: #f3e8ff; color: #6b21a8; display: flex; align-items: center; justify-content: center; font-weight: 800;">
                <i class="fa-solid fa-user-graduate"></i>
            </div>
            <div>
                <strong style="font-size: 0.85rem; color: var(--sw-text); display: block;">Ing. Carmen Benítez</strong>
                <span style="font-size: 0.75rem; color: var(--sw-text-muted);">Miembro de Tribunal</span>
            </div>
        </div>

        <div style="background: var(--sw-bg-soft); border: 1px solid var(--sw-border); border-radius: 10px; padding: 1rem; display: flex; align-items: center; gap: 0.75rem;">
            <div style="width: 38px; height: 38px; border-radius: 50%; background: #f3e8ff; color: #6b21a8; display: flex; align-items: center; justify-content: center; font-weight: 800;">
                <i class="fa-solid fa-user-graduate"></i>
            </div>
            <div>
                <strong style="font-size: 0.85rem; color: var(--sw-text); display: block;">Mgs. Fernando Torres</strong>
                <span style="font-size: 0.75rem; color: var(--sw-text-muted);">Miembro de Tribunal</span>
            </div>
        </div>
    </div>

    <!-- Bloque Defensa Programada -->
    <div style="background: #fdf4ff; border: 1px solid #f5d0fe; border-radius: 10px; padding: 1.25rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
        <div style="display: flex; align-items: center; gap: 0.85rem;">
            <i class="fa-solid fa-calendar-check" style="font-size: 1.5rem; color: #a21caf;"></i>
            <div>
                <strong style="font-size: 0.95rem; color: #701a75; display: block;">Defensa programada</strong>
                <span style="font-size: 0.85rem; color: #86198f;">10 de septiembre de 2026 · 09:00 AM · Auditorio Principal</span>
            </div>
        </div>
        <span style="font-size: 0.8rem; background: #fae8ff; color: #86198f; font-weight: 800; padding: 0.35rem 0.75rem; border-radius: 9999px;">Aprobado por Tribunal</span>
    </div>
</div>
