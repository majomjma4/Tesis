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
$editAction = null;
foreach ($actions as $candidateAction) {
    if (($candidateAction['id'] ?? '') === 'edit' && !empty($candidateAction['enabled'])) {
        $editAction = $candidateAction;
        break;
    }
}
$renderEditModal = ($digitalRecord['mode'] ?? 'view') === 'view'
    && is_array($editAction)
    && !empty($editAction['modal'])
    && !empty($digitalRecord['form']['action']);
$tabIds = array_values(array_filter(array_map(static fn (array $tab): string => (string) ($tab['id'] ?? ''), $tabs)));
$activeTab = in_array((string) ($digitalRecord['active_tab'] ?? ''), $tabIds, true)
    ? (string) $digitalRecord['active_tab'] : ($tabIds[0] ?? 'information');
$tabPartials = is_array($digitalRecord['tab_partials'] ?? null) ? $digitalRecord['tab_partials'] : [];
$renderRecordTab = static function (string $tabPanel) use (&$digitalRecord, $tabPartials): void {
    if ($tabPanel === 'files') require __DIR__ . '/_explorador-documental.php';
    elseif ($tabPanel === 'evolution') require __DIR__ . '/_evolucion-documental.php';
    elseif (isset($tabPartials[$tabPanel]) && is_file((string) $tabPartials[$tabPanel])) require (string) $tabPartials[$tabPanel];
    else require __DIR__ . '/_informacion-expediente.php';
};
?>
<style>
.digital-record{--ed-accent:var(--primary);width:min(1180px,100%);margin:0 auto;padding:22px 0 44px;color:var(--text)}
.ed-breadcrumb{display:flex;align-items:center;gap:9px;min-width:0;margin:0 0 13px;padding:0 2px;color:var(--muted);font-size:11px;line-height:1.5}.ed-breadcrumb a{color:var(--muted);font-weight:700}.ed-breadcrumb a:hover,.ed-breadcrumb a:focus-visible,.ed-back:hover,.ed-back:focus-visible{color:var(--ed-accent)}.ed-breadcrumb i{flex:0 0 auto;font-size:8px}.ed-breadcrumb span{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ed-back-links{display:flex;align-items:center;gap:18px;margin:0 0 17px 2px;flex-wrap:wrap}.ed-back{display:inline-flex;align-items:center;gap:8px;color:var(--muted);font-size:12px;font-weight:750}.ed-shell{overflow:visible;border:1px solid var(--line);border-radius:var(--radius);background:var(--surface);box-shadow:var(--shadow)}.ed-header{padding:30px 30px 27px;border-bottom:1px solid var(--line)}.ed-header-top{display:flex;align-items:flex-start;justify-content:space-between;gap:24px;margin-bottom:22px}.ed-labels{display:flex;flex-wrap:wrap;gap:8px}.ed-label{display:inline-flex;align-items:center;gap:6px;padding:5px 9px;border:1px solid var(--line);border-radius:999px;background:var(--surface-soft);color:var(--muted);font-size:10px;font-weight:850}.ed-label.is-success{border-color:rgba(34,197,94,.22);background:rgba(34,197,94,.09);color:#15803d}.ed-label.is-neutral{background:var(--surface-soft)}
.ed-header h1{max-width:940px;margin:0;font-size:clamp(29px,3.4vw,42px);line-height:1.12;letter-spacing:-.03em;overflow-wrap:anywhere}.ed-description{max-width:860px;margin:13px 0 0;color:var(--muted);font-size:14px;line-height:1.7;overflow-wrap:anywhere}.ed-meta{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin:25px 0 0}.ed-meta div{min-width:0;padding:12px 13px;border:1px solid var(--line);border-top:3px solid var(--ed-meta-accent,var(--line));border-radius:12px;background:var(--surface-soft);display:grid;gap:4px}.ed-meta div:first-child{padding-left:13px;border-left:1px solid var(--line)}.ed-meta [data-record-meta="responsible"]{--ed-meta-accent:#8b5cf6}.ed-meta [data-record-meta="publication"]{--ed-meta-accent:#2563eb}.ed-meta [data-record-meta="updated"]{--ed-meta-accent:#f59e0b}.ed-meta [data-record-meta="category"]{--ed-meta-accent:#0891b2}.ed-meta [data-record-meta="period"]{--ed-meta-accent:#6366f1}.ed-meta [data-record-meta="availability"]{--ed-meta-accent:#94a3b8}.digital-record[data-record-status="published"][data-record-available="1"] .ed-meta [data-record-meta="availability"]{--ed-meta-accent:#16a34a}.digital-record[data-record-status="published"][data-record-available="0"] .ed-meta [data-record-meta="availability"]{--ed-meta-accent:#f59e0b}.digital-record[data-record-status="withdrawn"] .ed-meta [data-record-meta="availability"]{--ed-meta-accent:#dc2626}.ed-meta dt{color:var(--muted);font-size:9px;font-weight:850;letter-spacing:.075em;text-transform:uppercase}.ed-meta dd{margin:0;font-size:12px;font-weight:780;overflow-wrap:anywhere}.ed-meta .is-secondary dd{color:var(--muted);font-weight:700}
.ed-actions,.ed-primary-actions{display:flex;align-items:center;justify-content:flex-end;gap:8px;flex-wrap:wrap}.ed-actions{position:relative}.ed-action{min-height:42px;padding:0 14px;border:1px solid var(--line);border-radius:10px;background:var(--surface-soft);color:var(--text);display:inline-flex;align-items:center;justify-content:center;gap:7px;font:inherit;font-size:11px;font-weight:800;white-space:nowrap;transition:.18s ease}.ed-action.is-primary{border-color:var(--ed-accent);background:var(--ed-accent);color:#fff;box-shadow:0 5px 12px rgba(15,61,145,.18)}.ed-action:not([disabled]):hover,.ed-action:not([disabled]):focus-visible{border-color:#60a5fa;background:#eff6ff;color:var(--primary);outline:0;transform:translateY(-1px)}.ed-action.is-primary:not([disabled]):hover,.ed-action.is-primary:not([disabled]):focus-visible{background:var(--primary-dark);color:#fff;box-shadow:0 8px 18px rgba(15,61,145,.23)}.ed-action:focus-visible,.ed-tab:focus-visible{outline:3px solid color-mix(in srgb,var(--ed-accent) 25%,transparent);outline-offset:2px}.ed-action[disabled]{cursor:not-allowed;opacity:.5}.ed-menu{position:relative}.ed-menu-panel{position:absolute;z-index:10;top:calc(100% + 7px);right:0;width:220px;padding:6px;border:1px solid var(--line);border-radius:13px;background:var(--surface);box-shadow:0 18px 45px rgba(15,23,42,.16)}.ed-menu-panel button{width:100%;min-height:40px;padding:8px 10px;border:0;border-radius:9px;background:transparent;color:var(--text);display:flex;align-items:center;gap:9px;font:inherit;font-size:11px;text-align:left}.ed-menu-panel button[disabled]{color:var(--muted);cursor:not-allowed}.ed-menu-panel .is-separated{margin-top:5px;border-top:1px solid var(--line);border-radius:0;padding-top:10px}.ed-menu-panel .is-danger{margin-top:5px;border-top:1px solid var(--line);border-radius:0 0 9px 9px;color:#b42318}
.ed-tabs{display:flex;align-items:stretch;gap:26px;min-height:56px;padding:0 30px;border-bottom:1px solid var(--line);background:var(--surface-soft);overflow-x:auto;scrollbar-width:none}.ed-tabs::-webkit-scrollbar{display:none}.ed-tab{position:relative;min-height:56px;padding:0 3px;color:var(--muted);display:inline-flex;align-items:center;gap:8px;font-size:12px;font-weight:780;white-space:nowrap}.ed-tab:hover{color:var(--text)}.ed-tab[aria-current="page"]{color:var(--primary);font-weight:850}.ed-tab[aria-current="page"]::after{position:absolute;right:0;bottom:-1px;left:0;height:3px;border-radius:3px 3px 0 0;background:var(--ed-accent);content:""}.ed-tab-panel[hidden]{display:none!important}.ed-content{min-height:280px;padding:28px 30px 32px;border-radius:0 0 var(--radius) var(--radius);background:var(--surface);background-clip:padding-box}.ed-empty{display:grid;justify-items:center;gap:8px;max-width:560px;margin:30px auto;padding:34px 24px;border:1px dashed var(--line);border-radius:14px;background:var(--surface-soft);text-align:center}.ed-empty i{width:48px;height:48px;border-radius:14px;background:var(--surface);color:var(--primary);display:grid;place-items:center;font-size:20px;box-shadow:0 5px 14px rgba(15,23,42,.06)}.ed-empty h2{margin:4px 0 0;font-size:16px}.ed-empty p{margin:0;color:var(--muted);font-size:12px;line-height:1.6}
body.dark-mode .ed-label.is-success{color:#86efac}
.ed-edit-modal{position:fixed!important;z-index:2100;inset:0!important;width:100vw!important;max-width:none!important;height:100dvh!important;margin:0!important;padding:0!important;box-sizing:border-box;background:rgba(15,23,42,.62);backdrop-filter:blur(4px);overflow:hidden;overscroll-behavior:contain}.ed-edit-modal[hidden]{display:none!important}.ed-edit-modal-card{position:fixed;top:50dvh;left:calc(50vw + 140px);width:min(1180px,calc(100vw - 280px - var(--app-shell-gutter) - var(--app-shell-gutter) - 48px));max-height:90dvh;margin:0;transform:translate(-50%,-50%);border:1px solid var(--line);border-radius:20px;background:var(--surface);box-shadow:0 30px 80px rgba(15,23,42,.34);display:grid;grid-template-rows:auto minmax(0,1fr);overflow:hidden}.ed-edit-modal-header{position:relative;z-index:3;min-width:0;padding:18px 22px;border-bottom:1px solid var(--line);background:var(--surface);display:flex;align-items:center;justify-content:space-between;gap:18px}.ed-edit-modal-heading{min-width:0}.ed-edit-modal-heading h2{margin:0;font-size:19px}.ed-edit-modal-heading p{margin:5px 0 0;color:var(--muted);font-size:11px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.ed-edit-modal-close{align-self:flex-start;flex:0 0 auto;width:38px;height:38px;margin-top:-2px;border:1px solid var(--line);border-radius:11px;background:var(--surface-soft);color:var(--muted);display:grid;place-items:center;cursor:pointer}.ed-edit-modal-close:hover,.ed-edit-modal-close:focus-visible{border-color:var(--primary);color:var(--primary);outline:3px solid rgba(37,99,235,.16);outline-offset:2px}.ed-edit-modal-body{min-height:0;padding:20px 22px 24px;overflow-y:auto;overscroll-behavior:contain;scrollbar-gutter:stable;scroll-padding-bottom:110px}.ed-edit-modal-body .ed-record-form{padding-bottom:12px}.ed-edit-modal-body .ed-record-form>.ed-form-card:last-of-type{margin-bottom:8px}.ed-edit-modal-body .ed-form-actions{position:sticky;bottom:0}.ed-edit-modal-body>.ed-form-error-summary,.ed-edit-modal-body>.ed-form-status{scroll-margin-top:12px}body.ed-edit-modal-open,html.ed-edit-modal-open{overflow:hidden!important;overscroll-behavior:none}
.ed-unread-dot{width:7px;height:7px;flex:0 0 auto;border-radius:50%;background:#dc2626;box-shadow:0 0 0 2px var(--surface)}.ed-menu>.ed-action{position:relative}.ed-menu>.ed-action>.ed-unread-dot{position:absolute;top:7px;right:7px}.ed-menu [data-record-history-trigger] .ed-unread-dot{margin-left:auto}.ed-unread-dot[hidden]{display:none!important}.ed-sr-only{position:absolute!important;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
@media(min-width:901px){.digital-record[data-entity-type="support_material"] .ed-meta div{padding-top:10px;padding-bottom:10px}}
.digital-record[data-entity-type="support_material"] .ed-meta{width:min(830px,100%);margin-right:auto;margin-left:auto;grid-template-columns:repeat(4,minmax(0,1fr))}
@media(min-width:1200px){.digital-record[data-entity-type="support_material"],.digital-record[data-entity-type="project"][data-record-context="repository"],.digital-record[data-entity-type="project"][data-record-context="academic_management"]{width:100%;max-width:none}.digital-record[data-entity-type="support_material"] .ed-header,.digital-record[data-entity-type="project"][data-record-context="repository"] .ed-header,.digital-record[data-entity-type="project"][data-record-context="academic_management"] .ed-header{padding-right:26px;padding-left:26px}.digital-record[data-entity-type="support_material"] .ed-tabs,.digital-record[data-entity-type="project"][data-record-context="repository"] .ed-tabs,.digital-record[data-entity-type="project"][data-record-context="academic_management"] .ed-tabs{padding-right:26px;padding-left:26px}.digital-record[data-entity-type="support_material"] .ed-content,.digital-record[data-entity-type="project"][data-record-context="repository"] .ed-content,.digital-record[data-entity-type="project"][data-record-context="academic_management"] .ed-content{padding-right:26px;padding-left:26px}}
@media(max-width:760px){.digital-record{padding-top:12px}.ed-header{padding:24px 20px}.ed-header-top{position:relative;display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:start;gap:14px 10px}.ed-labels{grid-column:1;grid-row:1;min-width:0}.ed-actions{display:contents}.ed-primary-actions{grid-column:1/-1;grid-row:2;justify-content:space-between}.ed-menu{grid-column:2;grid-row:1;justify-self:end}.ed-meta{grid-template-columns:repeat(2,minmax(0,1fr))}.ed-tabs{padding:0 20px}.ed-content{padding:24px 20px 28px}}
@media(max-width:760px){.digital-record[data-entity-type="support_material"] .ed-meta{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:1000px){.ed-edit-modal-card{left:50vw;width:calc(100vw - 20px)}}
@media(max-width:760px){.ed-edit-modal-card{max-height:calc(100dvh - 20px);border-radius:17px}.ed-edit-modal-header{padding:15px 17px}.ed-edit-modal-body{padding:16px 14px 20px}}
@media(max-width:480px){.digital-record{padding-bottom:28px}.ed-shell{border-radius:15px}.ed-header{padding:21px 16px}.ed-header h1{font-size:27px}.ed-primary-actions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));align-items:stretch}.ed-action{width:100%;min-height:44px}.ed-menu{position:static}.ed-menu .ed-action{width:44px}.ed-menu-panel{top:auto;right:0;left:0;width:auto;margin-top:7px}.ed-meta{grid-template-columns:1fr}.ed-tabs{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:4px;padding:0 10px;overflow:visible}.ed-tab{min-width:0;min-height:58px;justify-content:center;gap:5px;text-align:center;white-space:normal;font-size:10px;line-height:1.2}.ed-content{padding:20px 16px 24px;border-radius:0 0 14px 14px}.ed-breadcrumb span:not(:last-child){display:none}.ed-edit-modal-card{width:calc(100vw - 12px);max-height:calc(100dvh - 12px);border-radius:14px}.ed-edit-modal-header{padding:13px 14px}.ed-edit-modal-heading h2{font-size:17px}.ed-edit-modal-body{padding:13px 10px 18px}}
@media(max-width:480px){.digital-record[data-record-context="academic_management"] .ed-breadcrumb a{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.digital-record[data-record-context="academic_management"] .ed-breadcrumb a:first-of-type{max-width:42%}.digital-record[data-record-context="academic_management"] .ed-breadcrumb a:nth-of-type(2){max-width:28%}}
.ed-meta dt:has(i){display:flex;align-items:center;gap:7px}.ed-meta dt i{width:16px;color:var(--primary);font-size:11px;text-align:center}.ed-meta [data-record-meta="tutor"]{--ed-meta-accent:#8b5cf6}.ed-meta [data-record-meta="author"]{--ed-meta-accent:#0891b2}.ed-meta [data-record-meta="registration"]{--ed-meta-accent:#2563eb}
@media(max-width:480px){.digital-record[data-entity-type="support_material"] .ed-meta{width:100%;grid-template-columns:1fr}}
/* Escala tipográfica alineada con las vistas administrativas del sistema. */
.ed-breadcrumb{font-size:12px}.ed-breadcrumb i{font-size:9px}.ed-label{font-size:11px}.ed-meta dt{font-size:11px;line-height:1.35}.ed-meta dd{font-size:13px;line-height:1.45}.ed-action,.ed-menu-panel button{font-size:12px;line-height:1.4}.ed-tab{font-size:13px}.ed-edit-modal-heading p{font-size:12px;line-height:1.45}
.ed-menu-panel button:focus-visible{outline:3px solid color-mix(in srgb,var(--primary) 28%,transparent);outline-offset:-2px;background:var(--surface-soft)}
.ed-tab-panel:focus-visible{outline:3px solid color-mix(in srgb,var(--primary) 22%,transparent);outline-offset:-3px}
@media(max-width:480px){.ed-tab{font-size:11px}}
.digital-record .ed-action:not([disabled]),.digital-record .ed-tab,.digital-record .ed-menu-panel button:not([disabled]){cursor:pointer}
.digital-record .ed-menu-panel button:not([disabled]){transition:background .16s ease,color .16s ease,transform .16s ease}
.digital-record .ed-menu-panel button:not([disabled]):hover{background:var(--surface-soft);color:var(--primary);transform:translateX(2px)}
.ed-menu-panel a{width:100%;min-height:40px;padding:8px 10px;border:0;border-radius:9px;background:transparent;color:var(--text);display:flex;align-items:center;gap:9px;box-sizing:border-box;font:inherit;font-size:12px;line-height:1.4;text-align:left;text-decoration:none;cursor:pointer;transition:background .16s ease,color .16s ease,transform .16s ease}.ed-menu-panel a:hover{background:var(--surface-soft);color:var(--primary);transform:translateX(2px)}.ed-menu-panel a:focus-visible{outline:3px solid color-mix(in srgb,var(--primary) 28%,transparent);outline-offset:-2px;background:var(--surface-soft)}
.digital-record .ed-tab{transition:color .16s ease,transform .16s ease}.digital-record .ed-tab:hover{transform:translateY(-1px)}
</style>

<main class="digital-record" data-digital-record data-record-id="<?= (int) ($digitalRecord['entity']['id'] ?? 0) ?>" data-entity-type="<?= e((string) ($digitalRecord['entity']['type'] ?? 'record')) ?>" data-record-context="<?= e((string) ($digitalRecord['context'] ?? 'repository')) ?>" data-active-tab="<?= e($activeTab) ?>" data-persistent-tabs="<?= ($digitalRecord['mode'] ?? 'view') === 'view' ? 'true' : 'false' ?>" data-can-purge-files="<?= !empty($digitalRecord['can_permanently_delete_files']) ? 'true' : 'false' ?>" data-admin-endpoint="<?= e((string) ($digitalRecord['admin_actions']['endpoint'] ?? '')) ?>" data-admin-csrf="<?= e((string) ($digitalRecord['admin_actions']['csrf_token'] ?? '')) ?>" data-admin-trash-endpoint="<?= e((string) ($digitalRecord['admin_actions']['trash_endpoint'] ?? '')) ?>" data-admin-trash-csrf="<?= e((string) ($digitalRecord['admin_actions']['trash_csrf_token'] ?? '')) ?>" data-record-status="<?= e((string) ($digitalRecord['admin_actions']['status'] ?? '')) ?>" data-record-available="<?= !empty($digitalRecord['admin_actions']['is_available']) ? '1' : '0' ?>" data-admin-redirect="<?= e((string) ($digitalRecord['admin_actions']['redirect'] ?? '')) ?>">
    <nav class="ed-breadcrumb" aria-label="Ruta de navegación">
        <?php foreach (($digitalRecord['breadcrumbs'] ?? []) as $index => $crumb): ?>
            <?php if ($index > 0): ?><i class="fa-solid fa-chevron-right" aria-hidden="true"></i><?php endif; ?>
            <?php if (!empty($crumb['url'])): ?><a href="<?= e($crumb['url']) ?>"><?= e($crumb['label']) ?></a>
            <?php else: ?><span<?= $index === count($digitalRecord['breadcrumbs']) - 1 ? ' aria-current="page"' : '' ?> title="<?= e($crumb['label']) ?>"><?= e($crumb['label']) ?></span><?php endif; ?>
        <?php endforeach; ?>
    </nav>
    <div class="ed-back-links">
        <a class="ed-back" href="<?= e((string) ($digitalRecord['return_url'] ?? route('repository'))) ?>"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> <?= e((string)($digitalRecord['return_label'] ?? 'Volver al repositorio')) ?></a>
    </div>
    <article class="ed-shell" aria-labelledby="digitalRecordTitle">
        <header class="ed-header">
            <div class="ed-header-top">
                <div class="ed-labels">
                    <span class="ed-label"><?php if (!empty($header['type_icon'])): ?><i class="fa-solid <?= e((string)$header['type_icon']) ?>" aria-hidden="true"></i><?php endif; ?><?= e((string) ($header['type_label'] ?? 'Expediente')) ?></span>
                    <span class="ed-label is-<?= e((string) ($header['status_tone'] ?? 'neutral')) ?>" data-record-status-label><i class="fa-solid <?= ($header['status_tone'] ?? '') === 'success' ? 'fa-circle-check' : 'fa-circle-minus' ?>" aria-hidden="true"></i><span><?= e((string) ($header['status_label'] ?? 'Sin estado')) ?></span></span>
                </div>
                <div class="ed-actions" aria-label="Acciones del expediente">
                    <div class="ed-primary-actions">
                    <?php foreach ($actions as $action): $enabled = !empty($action['enabled']); $iconStyle = (string) ($action['icon_style'] ?? 'fa-regular'); $isDownload = !empty($action['download']); ?>
                        <?php if ($enabled && ($action['trigger'] ?? '') === 'project-editor'): ?><button class="ed-action<?= ($action['kind'] ?? '') === 'primary' ? ' is-primary' : '' ?>" type="button" data-project-editor-open><i class="<?= e($iconStyle) ?> <?= e($action['icon']) ?>" aria-hidden="true"></i><?= e($action['label']) ?></button>
                        <?php elseif ($enabled && ($action['trigger'] ?? '') === 'status-transition'): ?><button class="ed-action<?= ($action['kind'] ?? '') === 'primary' ? ' is-primary' : '' ?>" type="button" data-project-status-transition='<?=e(json_encode($action['transition']??[],JSON_UNESCAPED_UNICODE|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_TAG))?>'><i class="<?= e($iconStyle) ?> <?= e($action['icon']) ?>" aria-hidden="true"></i><?= e($action['label']) ?></button>
                        <?php elseif ($enabled && !empty($action['url']) && !empty($action['modal'])): ?><button class="ed-action<?= ($action['kind'] ?? '') === 'primary' ? ' is-primary' : '' ?>" type="button" data-record-edit-open data-edit-fallback-url="<?= e($action['url']) ?>"><i class="<?= e($iconStyle) ?> <?= e($action['icon']) ?>" aria-hidden="true"></i><?= e($action['label']) ?></button>
                        <?php elseif ($enabled && !empty($action['url'])): ?><a class="ed-action<?= ($action['kind'] ?? '') === 'primary' ? ' is-primary' : '' ?>" href="<?= e($action['url']) ?>"<?= $isDownload ? ' download data-record-download' : '' ?>><i class="<?= e($iconStyle) ?> <?= e($action['icon']) ?>" aria-hidden="true"></i><?= e($action['label']) ?></a>
                        <?php else: ?><button class="ed-action<?= ($action['kind'] ?? '') === 'primary' ? ' is-primary' : '' ?>" type="button" disabled title="Disponible en una fase posterior"><i class="<?= e($iconStyle) ?> <?= e($action['icon']) ?>" aria-hidden="true"></i><?= e($action['label']) ?></button><?php endif; ?>
                    <?php endforeach; ?>
                    </div>
                    <?php if ($menuActions): ?><div class="ed-menu" data-record-menu>
                        <button class="ed-action" type="button" aria-label="Más acciones" aria-haspopup="menu" aria-expanded="false" aria-controls="recordActionsMenu" data-record-menu-trigger><i class="fa-solid fa-ellipsis" aria-hidden="true"></i><span class="ed-unread-dot" data-record-unread-dot<?= empty($digitalRecord['admin_actions']['has_unread']) ? ' hidden' : '' ?> aria-hidden="true"></span><span class="ed-sr-only" data-record-unread-text<?= empty($digitalRecord['admin_actions']['has_unread']) ? ' hidden' : '' ?>>Hay actividad administrativa nueva</span></button>
                        <div class="ed-menu-panel" id="recordActionsMenu" role="menu" hidden data-record-menu-panel>
                            <?php foreach ($menuActions as $item): ?><?php if (!empty($item['url']) && !empty($item['enabled'])): ?><a role="menuitem" href="<?= e((string)$item['url']) ?>"<?= !empty($item['download'])?' download':'' ?> class="<?= !empty($item['separator'])?'is-separated':'' ?>"><i class="fa-solid <?= e($item['icon']) ?>" aria-hidden="true"></i><span><?= e($item['label']) ?></span></a><?php else: ?><button type="button" role="menuitem"<?= empty($item['enabled']) ? ' disabled' : '' ?><?= !empty($item['hidden']) ? ' hidden' : '' ?> class="<?= !empty($item['danger']) ? 'is-danger ' : '' ?><?= !empty($item['separator']) ? 'is-separated' : '' ?>"<?= ($item['action'] ?? '') === 'admin-history' ? ' data-record-history-trigger' : '' ?><?= ($item['action'] ?? '') === 'project-history' ? ' data-project-history-trigger' : '' ?><?= ($item['action'] ?? '') === 'status-transition' ? ' data-project-status-transition=\'' . e(json_encode($item['transition'] ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_TAG)) . '\'' : '' ?><?= !empty($item['action']) && !in_array(($item['action'] ?? ''),['admin-history','project-history','status-transition'],true) ? ' data-record-admin-action="' . e($item['action']) . '"' : '' ?>><i class="fa-solid <?= e($item['icon']) ?>" aria-hidden="true"></i><span><?= e($item['label']) ?></span><?php if (($item['action'] ?? '') === 'admin-history'): ?><span class="ed-unread-dot"<?= empty($digitalRecord['admin_actions']['has_unread']) ? ' hidden' : '' ?> data-record-history-unread-dot aria-hidden="true"></span><span class="ed-sr-only"<?= empty($digitalRecord['admin_actions']['has_unread']) ? ' hidden' : '' ?> data-record-unread-text>Hay actividad administrativa nueva</span><?php endif; ?></button><?php endif; ?><?php endforeach; ?>
                        </div>
                    </div><?php endif; ?>
                </div>
            </div>
            <h1 id="digitalRecordTitle"><?= e((string) ($header['title'] ?? 'Expediente')) ?></h1>
            <?php if (($header['description'] ?? '') !== ''): ?><p class="ed-description"><?= e($header['description']) ?></p><?php endif; ?>
            <?php if ($metadata): ?><dl class="ed-meta"><?php foreach ($metadata as $item): ?><div class="<?= ($item['tone'] ?? '') === 'secondary' ? 'is-secondary' : '' ?>"<?= !empty($item['key']) ? ' data-record-meta="' . e($item['key']) . '"' : '' ?>><dt><?php if (!empty($item['icon'])): ?><i class="fa-solid <?= e((string)$item['icon']) ?>" aria-hidden="true"></i><span><?= e($item['label']) ?></span><?php else: ?><?= e($item['label']) ?><?php endif; ?></dt><dd><?= e($item['value']) ?></dd></div><?php endforeach; ?></dl><?php endif; ?>
        </header>
        <?php if(!empty($digitalRecord['review_notice'])):$reviewNotice=$digitalRecord['review_notice'];?><aside class="ed-review-notice" role="status"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><div><strong><?=e((string)$reviewNotice['message'])?></strong><?php if(!empty($reviewNotice['count'])):?><span><?=(int)$reviewNotice['count']?> observación<?=((int)$reviewNotice['count']===1?'':'es')?> pendiente<?=((int)$reviewNotice['count']===1?'':'s')?>.</span><?php endif;?></div></aside><?php endif;?>
        <nav class="ed-tabs" aria-label="Secciones del expediente"<?= ($digitalRecord['mode'] ?? 'view') === 'view' ? ' role="tablist"' : '' ?>>
            <?php foreach ($tabs as $tabItem): ?><a class="ed-tab" id="recordTab-<?= e($tabItem['id']) ?>" data-record-tab-link data-tab-id="<?= e($tabItem['id']) ?>" href="<?= e($tabItem['url']) ?>"<?= $tabItem['id'] === $activeTab ? ' aria-current="page"' : '' ?><?= ($digitalRecord['mode'] ?? 'view') === 'view' ? ' role="tab" tabindex="' . ($tabItem['id'] === $activeTab ? '0' : '-1') . '" aria-selected="' . ($tabItem['id'] === $activeTab ? 'true' : 'false') . '" aria-controls="recordTabPanel-' . e($tabItem['id']) . '"' : '' ?>><i class="fa-solid <?= e($tabItem['icon']) ?>" aria-hidden="true"></i><?= e($tabItem['label']) ?></a><?php endforeach; ?>
        </nav>
        <?php if (($digitalRecord['mode'] ?? 'view') === 'view'): ?>
            <?php foreach ($tabIds as $tabPanel): ?>
                <section class="ed-content ed-tab-panel" id="recordTabPanel-<?= e($tabPanel) ?>" data-record-tab-panel="<?= e($tabPanel) ?>" role="tabpanel" tabindex="0" aria-labelledby="recordTab-<?= e($tabPanel) ?>"<?= $tabPanel !== $activeTab ? ' hidden' : '' ?>>
                    <?php
                    $renderRecordTab($tabPanel);
                    ?>
                </section>
            <?php endforeach; ?>
        <?php else: ?>
            <section class="ed-content" aria-label="Contenido de <?= e((string) (($tabs[array_search($activeTab, array_column($tabs, 'id'), true)]['label'] ?? 'expediente'))) ?>">
                <?php
                $renderRecordTab($activeTab);
                ?>
            </section>
        <?php endif; ?>
    </article>
    <?php if ($renderEditModal):
        $viewDigitalRecord = $digitalRecord;
        $digitalRecord['mode'] = 'edit';
    ?>
        <div class="ed-edit-modal" hidden data-record-edit-modal>
            <section class="ed-edit-modal-card" role="dialog" aria-modal="true" aria-labelledby="recordEditModalTitle" aria-describedby="recordEditModalReference" tabindex="-1" data-record-edit-dialog>
                <header class="ed-edit-modal-header">
                    <div class="ed-edit-modal-heading">
                        <h2 id="recordEditModalTitle">Editar material</h2>
                        <p id="recordEditModalReference"><?= e((string) ($header['title'] ?? 'Material de apoyo')) ?></p>
                    </div>
                    <button class="ed-edit-modal-close" type="button" aria-label="Cerrar edición" data-record-edit-close><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                </header>
                <div class="ed-edit-modal-body" data-record-edit-scroll>
                    <?php require __DIR__ . '/_informacion-expediente.php'; ?>
                </div>
            </section>
        </div>
    <?php
        $digitalRecord = $viewDigitalRecord;
        unset($viewDigitalRecord);
    endif; ?>
</main>
<?php if (($digitalRecord['endpoints']['admin_history'] ?? '') !== '') require __DIR__ . '/_admin-history-drawer.php'; ?>
<?php if (($digitalRecord['admin_actions']['endpoint'] ?? '') !== '') require __DIR__ . '/_material-admin-action-dialog.php'; ?>
