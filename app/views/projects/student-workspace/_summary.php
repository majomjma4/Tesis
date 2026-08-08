<?php
/**
 * Pestaña Resumen - Workspace Estudiante
 * @var array<string, mixed> $project
 */
$tutorName = e($project['tutor'] ?? 'Lic. Diana Alegría');
$students = $project['student_authors'] ?? $project['participants'] ?? [];
$isDegree = in_array(mb_strtolower((string)($project['type_code'] ?? ''), 'UTF-8'), ['thesis','tesis','degree','titulacion','titulación'], true);
?>
<div class="sw-summary-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
    <!-- Tarjeta Descripción y Metadatos -->
    <div style="background: var(--surface, #fff); border: 1px solid var(--sw-border); border-radius: 12px; padding: 1.25rem; display: flex; flex-direction: column; gap: 1rem;">
        <h3 style="font-size: 1rem; font-weight: 800; color: var(--sw-text); margin: 0;"><i class="fa-solid fa-align-left" style="color: var(--sw-primary); margin-right: 0.4rem;"></i> Descripción del proyecto</h3>
        <p style="font-size: 0.9rem; color: var(--sw-text-muted); line-height: 1.5; margin: 0;">
            <?= e($project['description'] ?? 'Este proyecto aborda la investigación, diseño e implementación del expediente académico institucional, cumpliendo con los estándares normativos vigentes para la entrega final.') ?>
        </p>
        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.5rem;">
            <span style="font-size: 0.75rem; background: var(--sw-bg-soft); border: 1px solid var(--sw-border); padding: 0.25rem 0.6rem; border-radius: 6px; font-weight: 600;"><i class="fa-solid fa-tag"></i> Gestión Académica</span>
            <span style="font-size: 0.75rem; background: var(--sw-bg-soft); border: 1px solid var(--sw-border); padding: 0.25rem 0.6rem; border-radius: 6px; font-weight: 600;"><i class="fa-solid fa-calendar"></i> Periodo 2026-I</span>
        </div>
    </div>

    <!-- Tarjeta Tutor Único -->
    <div style="background: var(--surface, #fff); border: 1px solid var(--sw-border); border-radius: 12px; padding: 1.25rem; display: flex; flex-direction: column; gap: 0.85rem;">
        <h3 style="font-size: 1rem; font-weight: 800; color: var(--sw-text); margin: 0;"><i class="fa-solid fa-chalkboard-user" style="color: var(--sw-primary); margin-right: 0.4rem;"></i> Tutor asignado</h3>
        <div style="display: flex; align-items: center; gap: 0.85rem; background: var(--sw-bg-soft); padding: 0.75rem; border-radius: 10px; border: 1px solid var(--sw-border);">
            <div style="width: 40px; height: 40px; border-radius: 50%; background: #e0f2fe; color: #0369a1; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.1rem;">
                <i class="fa-solid fa-user-tie"></i>
            </div>
            <div>
                <strong style="font-size: 0.95rem; color: var(--sw-text); display: block;"><?= $tutorName ?></strong>
                <span style="font-size: 0.75rem; color: var(--sw-text-muted);">Tutor Principal de Seguimiento</span>
            </div>
        </div>
    </div>

    <!-- Tarjeta Integrantes -->
    <div style="background: var(--surface, #fff); border: 1px solid var(--sw-border); border-radius: 12px; padding: 1.25rem; display: flex; flex-direction: column; gap: 0.85rem;">
        <h3 style="font-size: 1rem; font-weight: 800; color: var(--sw-text); margin: 0;"><i class="fa-solid fa-users" style="color: var(--sw-primary); margin-right: 0.4rem;"></i> Integrantes del proyecto</h3>
        <div style="display: flex; flex-direction: column; gap: 0.6rem;">
            <div style="display: flex; align-items: center; justify-content: space-between; background: var(--sw-bg-soft); padding: 0.6rem 0.8rem; border-radius: 8px; border: 1px solid var(--sw-border);">
                <div style="display: flex; align-items: center; gap: 0.6rem;">
                    <i class="fa-solid fa-user-graduate" style="color: var(--sw-primary);"></i>
                    <span style="font-size: 0.85rem; font-weight: 700; color: var(--sw-text);">María José Monteros</span>
                </div>
                <span style="font-size: 0.7rem; background: #dcfce7; color: #15803d; font-weight: 800; padding: 0.15rem 0.5rem; border-radius: 9999px;">Líder</span>
            </div>
        </div>
    </div>

    <!-- Bloque Relacional Perfil de Tesis / Trabajo de Titulación -->
    <?php if ($isDegree): ?>
    <div style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border: 1px dashed #cbd5e1; border-radius: 12px; padding: 1.25rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; grid-column: 1 / -1;">
        <div style="display: flex; align-items: center; gap: 0.85rem;">
            <i class="fa-solid fa-link" style="font-size: 1.25rem; color: var(--sw-primary);"></i>
            <div>
                <h4 style="margin: 0; font-size: 0.9rem; font-weight: 700; color: var(--sw-text);">Perfil de Tesis relacionado</h4>
                <p style="margin: 0.15rem 0 0 0; font-size: 0.8rem; color: var(--sw-text-muted);">Perfil de aprobación previa: PRF-2025-089 · Aprobado</p>
            </div>
        </div>
        <button type="button" class="sw-tab-btn" style="border: 1px solid var(--sw-border); background: #fff; border-radius: 8px; padding: 0.4rem 0.8rem; font-size: 0.8rem;" onclick="alert('Abriendo expediente de Perfil de Tesis relacionado (Simulación visual)...');">
            Ver perfil relacionado <i class="fa-solid fa-arrow-up-right-from-square"></i>
        </button>
    </div>
    <?php endif; ?>
</div>
