<?php
/** Historial funcional de reemplazos; no mezcla eventos de la auditoría administrativa general. */
$versionGroups = is_array($digitalRecord['versions'] ?? null) ? $digitalRecord['versions'] : [];
$versionEndpoints = is_array($digitalRecord['version_endpoints'] ?? null) ? $digitalRecord['version_endpoints'] : [];
$regularEndpoints = is_array($digitalRecord['endpoints'] ?? null) ? $digitalRecord['endpoints'] : [];
$materialId = (int) ($digitalRecord['entity']['id'] ?? 0);
$previewTypes = [
    'pdf' => 'pdf', 'docx' => 'docx', 'txt' => 'text',
    'png' => 'image', 'jpg' => 'image', 'jpeg' => 'image', 'webp' => 'image',
];
$evolutionDateParts = static function (string $value): array {
    if (preg_match('/^(\d{2}\/\d{2}\/\d{4})\s+(\d{2}:\d{2})$/', trim($value), $matches) === 1) {
        return ['date' => $matches[1], 'time' => $matches[2]];
    }
    return ['date' => $value, 'time' => ''];
};
?>
<style>
.ed-evolution{width:100%}.ed-evolution-head{margin-bottom:18px;padding:20px;border:1px solid var(--line);border-radius:15px;background:var(--surface)}.ed-evolution-head h2{margin:0;font-size:18px}.ed-evolution-head p{margin:6px 0 0;color:var(--muted);font-size:12px;line-height:1.55}
.ed-evolution-groups{display:grid;gap:14px}.ed-evolution-group{border:1px solid var(--line);border-radius:15px;background:var(--surface);overflow:hidden;box-shadow:0 5px 16px rgba(15,23,42,.035)}.ed-evolution-group>summary{min-height:88px;padding:16px 18px;cursor:pointer;display:grid;grid-template-columns:42px minmax(0,1fr) auto;align-items:center;gap:12px;list-style:none}.ed-evolution-group>summary::-webkit-details-marker{display:none}.ed-evolution-group>summary:hover,.ed-evolution-group>summary:focus-visible{background:var(--surface-soft);outline:none}.ed-evolution-group>summary:focus-visible{box-shadow:inset 0 0 0 3px color-mix(in srgb,var(--primary) 22%,transparent)}.ed-evolution-group-icon{width:42px;height:42px;border-radius:11px;background:color-mix(in srgb,var(--primary) 9%,var(--surface));color:var(--primary);display:grid;place-items:center}.ed-evolution-group-copy{min-width:0}.ed-evolution-group-copy strong{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:14px}.ed-evolution-group-meta{display:grid;gap:2px;margin-top:6px;color:var(--muted);font-size:11px;line-height:1.35}.ed-evolution-group-meta span{display:block}.ed-evolution-group-state{display:flex;align-items:center;gap:9px}.ed-evolution-status{padding:5px 8px;border-radius:999px;background:rgba(34,197,94,.1);color:#15803d;font-size:10px;font-weight:800}.ed-evolution-status.is-unavailable{background:rgba(148,163,184,.16);color:var(--muted)}.ed-evolution-chevron{color:var(--muted);transition:transform .18s ease}.ed-evolution-group[open] .ed-evolution-chevron{transform:rotate(180deg)}
.ed-evolution-unavailable{margin:0 18px 15px;padding:10px 12px;border:1px solid var(--line);border-radius:10px;background:var(--surface-soft);color:var(--muted);font-size:11px}.ed-version-timeline{position:relative;display:grid;gap:10px;margin:0 18px 18px 28px;padding:2px 0 0 24px}.ed-version-timeline::before{position:absolute;top:10px;bottom:10px;left:5px;width:2px;border-radius:2px;background:var(--line);content:""}.ed-version-entry{position:relative;border:1px solid var(--line);border-radius:12px;background:var(--surface)}.ed-version-entry::before{position:absolute;top:18px;left:-25px;width:10px;height:10px;border:3px solid var(--surface);border-radius:50%;background:#94a3b8;content:""}.ed-version-entry.is-current{border-color:color-mix(in srgb,var(--primary) 42%,var(--line));background:color-mix(in srgb,var(--primary) 5%,var(--surface));box-shadow:0 4px 12px color-mix(in srgb,var(--primary) 8%,transparent)}.ed-version-entry.is-current::before{width:12px;height:12px;left:-26px;background:var(--primary);box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 12%,transparent)}.ed-version-select{width:100%;padding:13px 14px;border:0;border-radius:12px;background:transparent;color:var(--text);display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:start;gap:11px;text-align:left;font:inherit}.ed-version-select:hover,.ed-version-select:focus-visible,.ed-version-select[aria-expanded="true"]{background:var(--surface-soft);outline:none}.ed-version-number{min-width:72px;padding-top:1px;color:var(--primary);font-size:11px;font-weight:850}.ed-version-main{min-width:0}.ed-version-main strong{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px}.ed-version-meta{display:grid;gap:2px;margin-top:5px;color:var(--muted);font-size:10px;line-height:1.35}.ed-version-meta span{display:block}.ed-version-badges{display:flex;align-items:center;justify-content:flex-end;gap:6px;flex-wrap:wrap}.ed-version-badge{padding:4px 7px;border-radius:999px;background:var(--surface-soft);color:var(--muted);font-size:9px;font-weight:800}.ed-version-badge.is-current{background:color-mix(in srgb,var(--primary) 13%,var(--surface));color:var(--primary)}.ed-version-badge.is-available{background:rgba(34,197,94,.1);color:#15803d}.ed-version-detail{padding:0 14px 14px}.ed-version-detail[hidden]{display:none!important}.ed-version-actions{display:flex;justify-content:flex-end;gap:8px;padding-top:10px;border-top:1px solid var(--line)}.ed-version-actions a,.ed-version-actions button{min-height:38px;padding:0 11px;border:1px solid var(--line);border-radius:9px;background:var(--surface);color:var(--text);display:inline-flex;align-items:center;justify-content:center;gap:7px;font:inherit;font-size:11px;font-weight:800;text-decoration:none}.ed-version-actions button{border-color:var(--primary);color:var(--primary)}.ed-version-missing{margin:0;padding:10px 11px;border-radius:9px;background:var(--surface-soft);color:var(--muted);font-size:11px;line-height:1.5}
body.dark-mode .ed-evolution-status{color:#86efac}@media(max-width:760px){.ed-evolution-group>summary{grid-template-columns:38px minmax(0,1fr);padding:14px}.ed-evolution-group-state{grid-column:2;justify-content:space-between}.ed-version-timeline{margin-right:14px;margin-left:20px;padding-left:20px}.ed-version-select{grid-template-columns:1fr}.ed-version-badges{justify-content:flex-start}}@media(max-width:480px){.ed-evolution-head{padding:16px}.ed-evolution-group-copy strong,.ed-version-main strong{white-space:normal}.ed-version-actions{display:grid}.ed-version-actions a,.ed-version-actions button{width:100%}}
</style>
<div class="ed-evolution" data-document-evolution>
    <header class="ed-evolution-head">
        <h2>Evolución documental</h2>
        <p>Consulta la secuencia de versiones generada cuando un archivo del material fue reemplazado.</p>
    </header>
    <?php if (!$versionGroups): ?>
        <div class="ed-empty"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i><h2>Aún no existe evolución documental registrada</h2><p>Las versiones aparecerán cuando un archivo sea reemplazado.</p></div>
    <?php else: ?>
        <div class="ed-evolution-groups">
            <?php foreach ($versionGroups as $groupIndex => $group): ?>
                <?php $groupDate = $evolutionDateParts((string) ($group['updated_date'] ?? 'Fecha no disponible')); ?>
                <details class="ed-evolution-group"<?= $groupIndex === 0 ? ' open' : '' ?>>
                    <summary>
                        <span class="ed-evolution-group-icon"><i class="fa-solid fa-file-lines" aria-hidden="true"></i></span>
                        <span class="ed-evolution-group-copy">
                            <strong title="<?= e((string) $group['name']) ?>"><?= e((string) $group['name']) ?></strong>
                            <span class="ed-evolution-group-meta">
                                <span><?= (int) $group['versions_count'] ?> versiones</span>
                                <span>Actualizado el <?= e($groupDate['date']) ?><?= $groupDate['time'] !== '' ? ' a las ' . e($groupDate['time']) : '' ?></span>
                                <span><?= e((string) $group['responsible']) ?></span>
                            </span>
                        </span>
                        <span class="ed-evolution-group-state">
                            <span class="ed-evolution-status<?= empty($group['available']) ? ' is-unavailable' : '' ?>"><?= !empty($group['available']) ? 'Disponible' : 'No disponible' ?></span>
                            <i class="fa-solid fa-chevron-down ed-evolution-chevron" aria-hidden="true"></i>
                        </span>
                    </summary>
                    <?php if (empty($group['available'])): ?><p class="ed-evolution-unavailable">Este archivo ya no se encuentra disponible. Se conserva únicamente su registro histórico.</p><?php endif; ?>
                    <div class="ed-version-timeline">
                        <?php foreach ($group['versions'] as $version):
                            $current = !empty($version['current']);
                            $available = !empty($version['available']);
                            $extension = mb_strtolower((string) ($version['extension'] ?? ''), 'UTF-8');
                            $query = '&material_id=' . $materialId . '&file_id=' . (int) $version['file_id'];
                            if ($current) {
                                $previewUrl = (string) ($regularEndpoints['preview'] ?? '') . $query;
                                $downloadUrl = (string) ($regularEndpoints['download'] ?? '') . $query;
                            } else {
                                $query .= '&version_id=' . (int) $version['id'];
                                $previewUrl = (string) ($versionEndpoints['preview'] ?? '') . $query;
                                $downloadUrl = (string) ($versionEndpoints['download'] ?? '') . $query;
                            }
                            $previewType = $previewTypes[$extension] ?? 'unsupported';
                            $versionDate = $evolutionDateParts((string) ($version['date'] ?? 'Fecha no disponible'));
                        ?>
                            <article class="ed-version-entry<?= $current ? ' is-current' : '' ?>">
                                <button class="ed-version-select" type="button" data-evolution-version aria-expanded="false">
                                    <span class="ed-version-number">Versión <?= (int) $version['number'] ?></span>
                                    <span class="ed-version-main">
                                        <strong title="<?= e((string) $version['name']) ?>"><?= e((string) $version['name']) ?></strong>
                                        <span class="ed-version-meta">
                                            <span><?= e($versionDate['date']) ?><?= $versionDate['time'] !== '' ? ' · ' . e($versionDate['time']) : '' ?></span>
                                            <span><?= e((string) $version['responsible']) ?></span>
                                            <span><?= e(mb_strtoupper($extension ?: 'FILE', 'UTF-8')) ?> · <?= e((string) $version['size']) ?></span>
                                        </span>
                                    </span>
                                    <span class="ed-version-badges">
                                        <?php if ($current): ?><span class="ed-version-badge is-current">Versión actual</span><?php endif; ?>
                                        <span class="ed-version-badge<?= $available ? ' is-available' : '' ?>"><?= $available ? 'Disponible' : 'No disponible' ?></span>
                                    </span>
                                </button>
                                <div class="ed-version-detail" data-evolution-version-detail hidden>
                                    <?php if ($available): ?>
                                        <div class="ed-version-actions">
                                            <?php if (!empty($version['preview_supported'])): ?>
                                                <button type="button" data-evolution-preview
                                                    data-file-id="version:<?= (int) $version['file_id'] ?>:<?= $current ? 'current' : (int) $version['id'] ?>"
                                                    data-file-name="<?= e((string) $version['name']) ?>"
                                                    data-file-type="<?= e(mb_strtoupper($extension ?: 'FILE', 'UTF-8')) ?>"
                                                    data-file-size="<?= e((string) $version['size']) ?>"
                                                    data-file-extension="<?= e($extension) ?>"
                                                    data-version-current="<?= $current ? 'true' : 'false' ?>"
                                                    data-preview-type="<?= e($previewType) ?>"
                                                    data-preview-supported="true"
                                                    data-preview-url="<?= e($previewUrl) ?>"
                                                    data-download-url="<?= e($downloadUrl) ?>"><i class="fa-solid fa-eye" aria-hidden="true"></i>Vista previa</button>
                                            <?php endif; ?>
                                            <a href="<?= e($downloadUrl) ?>" download><i class="fa-solid fa-download" aria-hidden="true"></i>Descargar</a>
                                        </div>
                                    <?php else: ?>
                                        <p class="ed-version-missing">Esta versión ya no se encuentra disponible en el sistema. Solo permanece su registro histórico.</p>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
