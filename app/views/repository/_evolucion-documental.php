<?php
/** Evolución documental neutral; no contiene datos académicos ni persistencia. */
$versions = is_array($digitalRecord['versions'] ?? null) ? $digitalRecord['versions'] : [];
?>
<style>
.ed-evolution{width:100%}.ed-evolution-head{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:18px;padding:20px;border:1px solid var(--line);border-radius:15px;background:var(--surface)}.ed-evolution-head h2{margin:0;font-size:18px}.ed-evolution-head p{margin:6px 0 0;color:var(--muted);font-size:11px}.ed-version-action{min-height:42px;padding:0 14px;border:1px solid var(--primary);border-radius:10px;background:var(--primary);color:#fff;display:inline-flex;align-items:center;gap:7px;font:inherit;font-size:11px;font-weight:850;box-shadow:0 5px 12px rgba(15,61,145,.16)}.ed-version-action[disabled]{cursor:not-allowed;box-shadow:none;filter:saturate(.7);opacity:.55}.ed-timeline{position:relative;display:grid;gap:14px;margin-left:9px;padding-left:28px}.ed-timeline::before{position:absolute;top:8px;bottom:8px;left:5px;width:1px;background:var(--line);content:""}.ed-version-card{position:relative;padding:18px;border:1px solid var(--line);border-radius:14px;background:var(--surface);box-shadow:0 5px 16px rgba(15,23,42,.035)}.ed-version-card::before{position:absolute;top:22px;left:-29px;width:11px;height:11px;border:3px solid var(--surface);border-radius:50%;background:var(--primary);content:""}.ed-version-card header{display:flex;justify-content:space-between;gap:14px}.ed-version-card h3{margin:0;font-size:14px}.ed-version-card time,.ed-version-card small{color:var(--muted);font-size:10px}.ed-version-card p{margin:10px 0 0;color:var(--muted);font-size:12px;line-height:1.6}
@media(max-width:620px){.ed-evolution-head{align-items:stretch;flex-direction:column;padding:17px}.ed-version-action{min-height:44px;justify-content:center}.ed-version-card header{flex-direction:column}.ed-timeline{padding-left:24px}}
</style>
<div class="ed-evolution">
    <header class="ed-evolution-head"><div><h2>Evolución documental</h2><p>Consulta las versiones registradas para este material.</p></div><button class="ed-version-action" type="button" disabled title="Disponible en una fase posterior"><i class="fa-solid fa-plus" aria-hidden="true"></i> Registrar nueva versión</button></header>
    <?php if (!$versions): ?>
        <div class="ed-empty"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i><h2>Aún no existen versiones registradas</h2><p>La evolución documental aparecerá cuando este material incorpore una nueva versión.</p></div>
    <?php else: ?>
        <section class="ed-timeline" aria-label="Versiones del material">
            <?php foreach ($versions as $version): ?><article class="ed-version-card"><header><div><small><?= e((string) ($version['responsible'] ?? 'Responsable no disponible')) ?></small><h3><?= e((string) ($version['label'] ?? 'Versión')) ?></h3></div><time><?= e((string) ($version['date'] ?? 'Fecha no disponible')) ?></time></header><?php if (!empty($version['change'])): ?><p><?= e($version['change']) ?></p><?php endif; ?></article><?php endforeach; ?>
        </section>
    <?php endif; ?>
</div>
