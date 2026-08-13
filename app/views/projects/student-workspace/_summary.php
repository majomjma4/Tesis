<?php
$authors = (array) ($project['student_authors'] ?? []);
$pendingObservations = count(array_filter((array) ($project['observations'] ?? []), static fn(array $item): bool => (string) ($item['status'] ?? '') === 'pending'));
?>
<section class="sw-card"><header class="sw-section-heading"><div><h2>Resumen del proyecto</h2><p>Información registrada actualmente en el expediente académico.</p></div></header>
    <dl class="sw-summary-grid">
        <div><dt>Tipo</dt><dd><?= e((string) ($project['type_name'] ?? 'No disponible')) ?></dd></div><div><dt>Estado</dt><dd><?= e($statusLabel) ?></dd></div>
        <div><dt>Tutor</dt><dd><?= e((string) ($project['tutor_name'] ?? 'No asignado')) ?></dd></div><div><dt>Periodo académico</dt><dd><?= e((string) ($project['period_name'] ?? 'No disponible')) ?></dd></div>
        <div><dt>Carrera</dt><dd><?= e((string) ($project['career_name'] ?? 'No disponible')) ?></dd></div><div><dt>Fecha de registro</dt><dd><?= e($formatDate((string) ($project['created_at'] ?? ''))) ?></dd></div>
        <div><dt>Integrantes</dt><dd><?= $authors ? e(implode(', ', array_map(static fn(array $author): string => (string) ($author['full_name'] ?? ''), $authors))) : 'No disponible' ?></dd></div><div><dt>Última actualización</dt><dd><?= e($formatDate((string) ($project['updated_at'] ?? ''), true)) ?></dd></div>
    </dl>
    <div class="sw-metrics"><div><strong><?= count((array) ($project['files'] ?? [])) ?></strong><span>Archivos actuales</span></div><div><strong><?= $pendingObservations ?></strong><span>Observaciones pendientes</span></div></div>
</section>
