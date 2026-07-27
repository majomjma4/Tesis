<?php
/** Estructura visual neutral del Expediente Digital. Consume únicamente $digitalRecord. */
if (!isset($digitalRecord) || !is_array($digitalRecord)) {
    $legacyRecord = is_array($record ?? null) ? $record : [];
    $legacyInformation = is_array($information ?? null) ? $information : [];
    $legacyDocuments = is_array($documents ?? null) ? $documents : [];
    $digitalRecord = [
        'entity' => ['type' => 'legacy_record', 'id' => 0],
        'context' => 'repository',
        'mode' => 'view',
        'return_url' => (string) ($legacyRecord['repository_url'] ?? route('repository')),
        'breadcrumbs' => [
            ['label' => 'Repositorio', 'url' => (string) ($legacyRecord['repository_url'] ?? route('repository'))],
            ['label' => (string) ($legacyRecord['category'] ?? 'Expediente'), 'url' => null],
            ['label' => (string) ($legacyRecord['title'] ?? 'Expediente'), 'url' => null],
        ],
        'header' => [
            'title' => (string) ($legacyRecord['title'] ?? 'Expediente'),
            'description' => (string) ($legacyRecord['description'] ?? ''),
            'type_label' => (string) ($legacyRecord['type'] ?? 'Expediente'),
            'status_label' => (string) ($legacyRecord['status'] ?? 'Sin estado'),
            'status_tone' => 'neutral',
        ],
        'metadata' => array_values(array_filter([
            ['label' => 'Responsable', 'value' => (string) ($legacyRecord['responsible'] ?? '')],
            ['label' => 'Publicación', 'value' => (string) ($legacyRecord['publication_date'] ?? '')],
        ], static fn (array $item): bool => $item['value'] !== '')),
        'actions' => [],
        'menu_actions' => [],
        'tabs' => [['id' => 'information', 'label' => 'Información', 'icon' => 'fa-file-lines', 'url' => (string) ($_SERVER['REQUEST_URI'] ?? '')]],
        'active_tab' => 'information',
        'information_sections' => [
            ['id' => 'description', 'title' => 'Descripción', 'icon' => 'fa-align-left', 'type' => 'prose', 'content' => (string) ($legacyInformation['description'] ?? $legacyInformation['summary'] ?? '')],
        ],
        'documents' => $legacyDocuments,
        'versions' => [],
    ];
}
$header = is_array($digitalRecord['header'] ?? null) ? $digitalRecord['header'] : [];
$metadata = is_array($digitalRecord['metadata'] ?? null) ? $digitalRecord['metadata'] : [];
$actions = is_array($digitalRecord['actions'] ?? null) ? $digitalRecord['actions'] : [];
$menuActions = is_array($digitalRecord['menu_actions'] ?? null) ? $digitalRecord['menu_actions'] : [];
$tabs = is_array($digitalRecord['tabs'] ?? null) ? $digitalRecord['tabs'] : [];
$activeTab = in_array((string) ($digitalRecord['active_tab'] ?? ''), ['information', 'files', 'evolution'], true)
    ? (string) $digitalRecord['active_tab'] : 'information';
?>
<style>
.digital-record{--ed-accent:var(--primary);width:min(1180px,100%);margin:0 auto;padding:22px 0 44px;color:var(--text)}
.ed-breadcrumb{display:flex;align-items:center;gap:9px;min-width:0;margin:0 0 13px;padding:0 2px;color:var(--muted);font-size:11px;line-height:1.5}.ed-breadcrumb a{color:var(--muted);font-weight:700}.ed-breadcrumb a:hover,.ed-breadcrumb a:focus-visible,.ed-back:hover,.ed-back:focus-visible{color:var(--ed-accent)}.ed-breadcrumb i{flex:0 0 auto;font-size:8px}.ed-breadcrumb span{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ed-back{display:inline-flex;align-items:center;gap:8px;margin:0 0 17px 2px;color:var(--muted);font-size:12px;font-weight:750}.ed-shell{overflow:visible;border:1px solid var(--line);border-radius:var(--radius);background:var(--surface);box-shadow:var(--shadow)}.ed-header{padding:30px 30px 27px;border-bottom:1px solid var(--line)}.ed-header-top{display:flex;align-items:flex-start;justify-content:space-between;gap:24px;margin-bottom:22px}.ed-labels{display:flex;flex-wrap:wrap;gap:8px}.ed-label{display:inline-flex;align-items:center;gap:6px;padding:5px 9px;border:1px solid var(--line);border-radius:999px;background:var(--surface-soft);color:var(--muted);font-size:10px;font-weight:850}.ed-label.is-success{border-color:rgba(34,197,94,.22);background:rgba(34,197,94,.09);color:#15803d}.ed-label.is-neutral{background:var(--surface-soft)}
.ed-header h1{max-width:940px;margin:0;font-size:clamp(29px,3.4vw,42px);line-height:1.12;letter-spacing:-.03em;overflow-wrap:anywhere}.ed-description{max-width:860px;margin:13px 0 0;color:var(--muted);font-size:14px;line-height:1.7;overflow-wrap:anywhere}.ed-meta{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin:25px 0 0}.ed-meta div{min-width:0;padding:12px 13px;border:1px solid var(--line);border-radius:12px;background:var(--surface-soft);display:grid;gap:4px}.ed-meta div:first-child{padding-left:13px;border-left:1px solid var(--line)}.ed-meta dt{color:var(--muted);font-size:9px;font-weight:850;letter-spacing:.075em;text-transform:uppercase}.ed-meta dd{margin:0;font-size:12px;font-weight:780;overflow-wrap:anywhere}.ed-meta .is-secondary dd{color:var(--muted);font-weight:700}
.ed-actions{position:relative;display:flex;align-items:center;justify-content:flex-end;gap:8px;flex-wrap:wrap}.ed-action{min-height:42px;padding:0 14px;border:1px solid var(--line);border-radius:10px;background:var(--surface-soft);color:var(--text);display:inline-flex;align-items:center;justify-content:center;gap:7px;font:inherit;font-size:11px;font-weight:800;white-space:nowrap;transition:.18s ease}.ed-action.is-primary{border-color:var(--ed-accent);background:var(--ed-accent);color:#fff;box-shadow:0 5px 12px rgba(15,61,145,.18)}.ed-action:not([disabled]):hover,.ed-action:not([disabled]):focus-visible{border-color:#60a5fa;background:#eff6ff;color:var(--primary);outline:0;transform:translateY(-1px)}.ed-action.is-primary:not([disabled]):hover,.ed-action.is-primary:not([disabled]):focus-visible{background:var(--primary-dark);color:#fff;box-shadow:0 8px 18px rgba(15,61,145,.23)}.ed-action:focus-visible,.ed-tab:focus-visible{outline:3px solid color-mix(in srgb,var(--ed-accent) 25%,transparent);outline-offset:2px}.ed-action[disabled]{cursor:not-allowed;opacity:.5}.ed-menu{position:relative}.ed-menu-panel{position:absolute;z-index:10;top:calc(100% + 7px);right:0;width:220px;padding:6px;border:1px solid var(--line);border-radius:13px;background:var(--surface);box-shadow:0 18px 45px rgba(15,23,42,.16)}.ed-menu-panel button{width:100%;min-height:40px;padding:8px 10px;border:0;border-radius:9px;background:transparent;color:var(--text);display:flex;align-items:center;gap:9px;font:inherit;font-size:11px;text-align:left}.ed-menu-panel button[disabled]{color:var(--muted);cursor:not-allowed}.ed-menu-panel .is-separated{margin-top:5px;border-top:1px solid var(--line);border-radius:0;padding-top:10px}.ed-menu-panel .is-danger{margin-top:5px;border-top:1px solid var(--line);border-radius:0 0 9px 9px;color:#b42318}
.ed-tabs{display:flex;align-items:stretch;gap:26px;min-height:56px;padding:0 30px;border-bottom:1px solid var(--line);background:var(--surface-soft);overflow-x:auto;scrollbar-width:none}.ed-tabs::-webkit-scrollbar{display:none}.ed-tab{position:relative;min-height:56px;padding:0 3px;color:var(--muted);display:inline-flex;align-items:center;gap:8px;font-size:12px;font-weight:780;white-space:nowrap}.ed-tab:hover{color:var(--text)}.ed-tab[aria-current="page"]{color:var(--primary);font-weight:850}.ed-tab[aria-current="page"]::after{position:absolute;right:0;bottom:-1px;left:0;height:3px;border-radius:3px 3px 0 0;background:var(--ed-accent);content:""}.ed-content{min-height:280px;padding:28px 30px 32px;background:var(--surface)}.ed-empty{display:grid;justify-items:center;gap:8px;max-width:560px;margin:30px auto;padding:34px 24px;border:1px dashed var(--line);border-radius:14px;background:var(--surface-soft);text-align:center}.ed-empty i{width:48px;height:48px;border-radius:14px;background:var(--surface);color:var(--primary);display:grid;place-items:center;font-size:20px;box-shadow:0 5px 14px rgba(15,23,42,.06)}.ed-empty h2{margin:4px 0 0;font-size:16px}.ed-empty p{margin:0;color:var(--muted);font-size:12px;line-height:1.6}
body.dark-mode .ed-label.is-success{color:#86efac}
@media(max-width:760px){.digital-record{padding-top:12px}.ed-header{padding:24px 20px}.ed-header-top{flex-direction:column}.ed-actions{width:100%;justify-content:flex-start}.ed-meta{grid-template-columns:repeat(2,minmax(0,1fr))}.ed-tabs{padding:0 20px}.ed-content{padding:24px 20px 28px}}
@media(max-width:480px){.digital-record{padding-bottom:28px}.ed-shell{border-radius:15px}.ed-header{padding:21px 16px}.ed-header h1{font-size:27px}.ed-actions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));align-items:stretch}.ed-action{width:100%;min-height:44px}.ed-menu{position:static}.ed-menu .ed-action{width:44px}.ed-menu-panel{top:auto;right:0;left:0;width:auto;margin-top:7px}.ed-meta{grid-template-columns:1fr}.ed-tabs{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:4px;padding:0 10px;overflow:visible}.ed-tab{min-width:0;min-height:58px;justify-content:center;gap:5px;text-align:center;white-space:normal;font-size:10px;line-height:1.2}.ed-content{padding:20px 16px 24px}.ed-breadcrumb span:not(:last-child){display:none}}
</style>

<main class="digital-record" data-digital-record data-record-id="<?= (int) ($digitalRecord['entity']['id'] ?? 0) ?>" data-entity-type="<?= e((string) ($digitalRecord['entity']['type'] ?? 'record')) ?>" data-active-tab="<?= e($activeTab) ?>">
    <nav class="ed-breadcrumb" aria-label="Ruta de navegación">
        <?php foreach (($digitalRecord['breadcrumbs'] ?? []) as $index => $crumb): ?>
            <?php if ($index > 0): ?><i class="fa-solid fa-chevron-right" aria-hidden="true"></i><?php endif; ?>
            <?php if (!empty($crumb['url'])): ?><a href="<?= e($crumb['url']) ?>"><?= e($crumb['label']) ?></a>
            <?php else: ?><span<?= $index === count($digitalRecord['breadcrumbs']) - 1 ? ' aria-current="page"' : '' ?> title="<?= e($crumb['label']) ?>"><?= e($crumb['label']) ?></span><?php endif; ?>
        <?php endforeach; ?>
    </nav>
    <a class="ed-back" href="<?= e((string) ($digitalRecord['return_url'] ?? route('repository'))) ?>"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Volver al repositorio</a>
    <article class="ed-shell" aria-labelledby="digitalRecordTitle">
        <header class="ed-header">
            <div class="ed-header-top">
                <div class="ed-labels">
                    <span class="ed-label"><?= e((string) ($header['type_label'] ?? 'Expediente')) ?></span>
                    <span class="ed-label is-<?= e((string) ($header['status_tone'] ?? 'neutral')) ?>"><i class="fa-solid <?= ($header['status_tone'] ?? '') === 'success' ? 'fa-circle-check' : 'fa-circle-minus' ?>" aria-hidden="true"></i><?= e((string) ($header['status_label'] ?? 'Sin estado')) ?></span>
                </div>
                <div class="ed-actions" aria-label="Acciones del expediente">
                    <?php foreach ($actions as $action): $enabled = !empty($action['enabled']); ?>
                        <?php if ($enabled && !empty($action['url'])): ?><a class="ed-action<?= ($action['kind'] ?? '') === 'primary' ? ' is-primary' : '' ?>" href="<?= e($action['url']) ?>"><i class="fa-regular <?= e($action['icon']) ?>" aria-hidden="true"></i><?= e($action['label']) ?></a>
                        <?php else: ?><button class="ed-action<?= ($action['kind'] ?? '') === 'primary' ? ' is-primary' : '' ?>" type="button" disabled title="Disponible en una fase posterior"><i class="fa-solid <?= e($action['icon']) ?>" aria-hidden="true"></i><?= e($action['label']) ?></button><?php endif; ?>
                    <?php endforeach; ?>
                    <?php if ($menuActions): ?><div class="ed-menu" data-record-menu>
                        <button class="ed-action" type="button" aria-label="Más acciones" aria-haspopup="menu" aria-expanded="false" data-record-menu-trigger><i class="fa-solid fa-ellipsis" aria-hidden="true"></i></button>
                        <div class="ed-menu-panel" role="menu" hidden data-record-menu-panel>
                            <?php foreach ($menuActions as $item): ?><button type="button" role="menuitem"<?= empty($item['enabled']) ? ' disabled' : '' ?> class="<?= !empty($item['danger']) ? 'is-danger ' : '' ?><?= !empty($item['separator']) ? 'is-separated' : '' ?>"<?= ($item['action'] ?? '') === 'admin-history' ? ' data-record-history-trigger' : '' ?>><i class="fa-solid <?= e($item['icon']) ?>" aria-hidden="true"></i><?= e($item['label']) ?></button><?php endforeach; ?>
                        </div>
                    </div><?php endif; ?>
                </div>
            </div>
            <h1 id="digitalRecordTitle"><?= e((string) ($header['title'] ?? 'Expediente')) ?></h1>
            <?php if (($header['description'] ?? '') !== ''): ?><p class="ed-description"><?= e($header['description']) ?></p><?php endif; ?>
            <?php if ($metadata): ?><dl class="ed-meta"><?php foreach ($metadata as $item): ?><div class="<?= ($item['tone'] ?? '') === 'secondary' ? 'is-secondary' : '' ?>"><dt><?= e($item['label']) ?></dt><dd><?= e($item['value']) ?></dd></div><?php endforeach; ?></dl><?php endif; ?>
        </header>
        <nav class="ed-tabs" aria-label="Secciones del expediente">
            <?php foreach ($tabs as $tabItem): ?><a class="ed-tab" href="<?= e($tabItem['url']) ?>"<?= $tabItem['id'] === $activeTab ? ' aria-current="page"' : '' ?>><i class="fa-solid <?= e($tabItem['icon']) ?>" aria-hidden="true"></i><?= e($tabItem['label']) ?></a><?php endforeach; ?>
        </nav>
        <section class="ed-content" aria-label="Contenido de <?= e((string) (($tabs[array_search($activeTab, array_column($tabs, 'id'), true)]['label'] ?? 'expediente'))) ?>">
            <?php
            if ($activeTab === 'files') require __DIR__ . '/_explorador-documental.php';
            elseif ($activeTab === 'evolution') require __DIR__ . '/_evolucion-documental.php';
            else require __DIR__ . '/_informacion-expediente.php';
            ?>
        </section>
    </article>
</main>
<?php if (($digitalRecord['endpoints']['admin_history'] ?? '') !== '') require __DIR__ . '/_admin-history-drawer.php'; ?>
