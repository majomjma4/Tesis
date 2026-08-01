<?php
/** Historia académica de proyectos o historial funcional de versiones para otros materiales. */
$versionGroups = is_array($digitalRecord['versions'] ?? null) ? $digitalRecord['versions'] : [];
$versionEndpoints = is_array($digitalRecord['version_endpoints'] ?? null) ? $digitalRecord['version_endpoints'] : [];
$regularEndpoints = is_array($digitalRecord['endpoints'] ?? null) ? $digitalRecord['endpoints'] : [];
$materialId = (int) ($digitalRecord['entity']['id'] ?? 0);
$entityQueryKey = (string) ($digitalRecord['entity']['query_key'] ?? 'material_id');
$evolutionOwner = (string) ($digitalRecord['entity']['type'] ?? '') === 'project' ? 'proyecto' : 'material';
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
$documentEvents = is_array($digitalRecord['document_evolution_events'] ?? null) ? $digitalRecord['document_evolution_events'] : [];
if ((string) ($digitalRecord['entity']['type'] ?? '') === 'support_material') {
    $groupedEvolution = [];
    foreach ($versionGroups as $group) {
        $matchedAuditIds = [];
        foreach ((array) ($group['versions'] ?? []) as $version) {
            if (!empty($version['replacement_audit_id'])) $matchedAuditIds[] = (int) $version['replacement_audit_id'];
        }
        $group['matched_audit_ids'] = array_values(array_unique($matchedAuditIds));
        $groupedEvolution[] = $group;
    }
    foreach ($documentEvents as &$event) {
        if ((string) ($event['type'] ?? '') !== 'file-replaced') continue;
        foreach ($groupedEvolution as $groupIndex => $group) {
            $matchedIds = (array) $group['matched_audit_ids'];
            $latestMatchedId = $matchedIds !== [] ? max($matchedIds) : 0;
            if (($latestMatchedId > 0 && (int) $event['id'] === $latestMatchedId)
                || ($latestMatchedId === 0 && (int) ($event['file_id'] ?? 0) > 0
                    && (int) $event['file_id'] === (int) ($group['file_id'] ?? 0))) {
                $event['version_group_index'] = $groupIndex;
                break;
            }
        }
    }
    unset($event);
    $attachedGroups = array_map('intval', array_column($documentEvents, 'version_group_index'));
    foreach ($groupedEvolution as $groupIndex => $group) {
        if (in_array($groupIndex, $attachedGroups, true)) continue;
        $documentEvents[] = [
            'id' => 0, 'type' => 'file-replaced', 'title' => 'Archivo reemplazado · nueva versión',
            'date' => (string) ($group['updated_at'] ?? ''), 'responsible' => (string) ($group['responsible'] ?? ''),
            'file_name' => (string) ($group['name'] ?? ''), 'version_group_index' => $groupIndex,
        ];
    }
    usort($documentEvents, static fn (array $left, array $right): int =>
        strcmp((string) ($left['date'] ?? ''), (string) ($right['date'] ?? ''))
        ?: ((int) ($left['id'] ?? 0) <=> (int) ($right['id'] ?? 0))
    );
}
?>
<style>
.ed-evolution{width:100%}.ed-evolution-head{margin-bottom:18px;padding:20px;border:1px solid color-mix(in srgb,var(--primary) 34%,var(--line));border-radius:15px;background:var(--surface);background-clip:padding-box;overflow:hidden}.ed-evolution-head h2{margin:0;font-size:18px}.ed-evolution-head p{margin:6px 0 0;color:var(--muted);font-size:12px;line-height:1.55}
.ed-evolution-groups{display:grid;gap:14px}.ed-evolution-group{border:1px solid color-mix(in srgb,#16a34a 34%,var(--line));border-radius:15px;background:var(--surface);background-clip:padding-box;overflow:hidden;box-shadow:0 5px 16px rgba(15,23,42,.035)}.ed-evolution-group>summary{min-height:88px;padding:16px 18px;border-radius:14px 14px 0 0;background-clip:padding-box;cursor:pointer;display:grid;grid-template-columns:42px minmax(0,1fr) auto;align-items:center;gap:12px;list-style:none}.ed-evolution-group:not([open])>summary{border-radius:14px}.ed-evolution-group>summary::-webkit-details-marker{display:none}.ed-evolution-group>summary:hover,.ed-evolution-group>summary:focus-visible{background:var(--surface-soft);outline:none}.ed-evolution-group>summary:focus-visible{box-shadow:inset 0 0 0 3px color-mix(in srgb,var(--primary) 22%,transparent)}.ed-evolution-group-icon{width:42px;height:42px;border-radius:11px;background:color-mix(in srgb,var(--primary) 9%,var(--surface));color:var(--primary);display:grid;place-items:center}.ed-evolution-group-copy{min-width:0}.ed-evolution-group-copy strong{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:14px}.ed-evolution-group-meta{display:grid;gap:2px;margin-top:6px;color:var(--muted);font-size:11px;line-height:1.35}.ed-evolution-group-meta span{display:block}.ed-evolution-group-state{display:flex;align-items:center;gap:9px}.ed-evolution-status{padding:5px 8px;border-radius:999px;background:rgba(34,197,94,.1);color:#15803d;font-size:10px;font-weight:800}.ed-evolution-status.is-unavailable{background:rgba(148,163,184,.16);color:var(--muted)}.ed-evolution-chevron{color:var(--muted);transition:transform .18s ease}.ed-evolution-group[open] .ed-evolution-chevron{transform:rotate(180deg)}
.ed-evolution-unavailable{margin:0 18px 15px;padding:10px 12px;border:1px solid var(--line);border-radius:10px;background:var(--surface-soft);background-clip:padding-box;color:var(--muted);font-size:11px}.ed-version-timeline{position:relative;display:grid;gap:10px;margin:0 18px 18px 28px;padding:2px 0 0 24px}.ed-version-timeline::before{position:absolute;top:10px;bottom:10px;left:5px;width:2px;border-radius:999px;background:var(--line);content:""}.ed-version-entry{position:relative;border:1px solid var(--line);border-radius:12px;background:var(--surface);background-clip:padding-box}.ed-version-entry::before{position:absolute;top:18px;left:-25px;width:10px;height:10px;border:3px solid var(--surface);border-radius:50%;background:#94a3b8;content:""}.ed-version-entry.is-current{border-color:color-mix(in srgb,var(--primary) 42%,var(--line));background:color-mix(in srgb,var(--primary) 5%,var(--surface));box-shadow:0 4px 12px color-mix(in srgb,var(--primary) 8%,transparent)}.ed-version-entry.is-current::before{width:12px;height:12px;left:-26px;background:var(--primary);box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 12%,transparent)}.ed-version-select{width:100%;padding:13px 14px;border:0;border-radius:11px;background:transparent;background-clip:padding-box;color:var(--text);display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:start;gap:11px;text-align:left;font:inherit}.ed-version-select:hover,.ed-version-select:focus-visible,.ed-version-select[aria-expanded="true"]{background:var(--surface-soft);outline:none}.ed-version-number{min-width:72px;padding-top:1px;color:var(--primary);font-size:11px;font-weight:850}.ed-version-main{min-width:0}.ed-version-main strong{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px}.ed-version-meta{display:grid;gap:2px;margin-top:5px;color:var(--muted);font-size:10px;line-height:1.35}.ed-version-meta span{display:block}.ed-version-badges{display:flex;align-items:center;justify-content:flex-end;gap:6px;flex-wrap:wrap}.ed-version-badge{padding:4px 7px;border-radius:999px;background:var(--surface-soft);color:var(--muted);font-size:9px;font-weight:800}.ed-version-badge.is-current{background:color-mix(in srgb,var(--primary) 13%,var(--surface));color:var(--primary)}.ed-version-badge.is-available{background:rgba(34,197,94,.1);color:#15803d}.ed-version-detail{padding:0 14px 14px}.ed-version-detail[hidden]{display:none!important}.ed-version-actions{display:flex;justify-content:flex-end;gap:8px;padding-top:10px;border-top:1px solid var(--line)}.ed-version-actions a,.ed-version-actions button{min-height:38px;padding:0 11px;border:1px solid var(--line);border-radius:9px;background:var(--surface);color:var(--text);display:inline-flex;align-items:center;justify-content:center;gap:7px;font:inherit;font-size:11px;font-weight:800;text-decoration:none}.ed-version-actions button{border-color:var(--primary);color:var(--primary)}.ed-version-missing{margin:0;padding:10px 11px;border-radius:9px;background:var(--surface-soft);color:var(--muted);font-size:11px;line-height:1.5}
body.dark-mode .ed-evolution-status{color:#86efac}@media(max-width:760px){.ed-evolution-group>summary{grid-template-columns:38px minmax(0,1fr);padding:14px}.ed-evolution-group-state{grid-column:2;justify-content:space-between}.ed-version-timeline{margin-right:14px;margin-left:20px;padding-left:20px}.ed-version-select{grid-template-columns:1fr}.ed-version-badges{justify-content:flex-start}}@media(max-width:480px){.ed-evolution-head{padding:16px}.ed-evolution-group-copy strong,.ed-version-main strong{white-space:normal}.ed-version-actions{display:grid}.ed-version-actions a,.ed-version-actions button{width:100%}}
/* Las versiones conservan una jerarquía legible sin alterar la línea temporal. */
.ed-evolution-head p{font-size:13px;line-height:1.6}.ed-evolution-group-copy strong{font-size:15px;line-height:1.4}.ed-evolution-group-meta{font-size:12px;line-height:1.5}.ed-evolution-status{font-size:11px}.ed-evolution-unavailable{font-size:12px;line-height:1.5}.ed-version-number{font-size:12px}.ed-version-main strong{font-size:14px;line-height:1.4}.ed-version-meta{font-size:12px;line-height:1.5}.ed-version-badge{font-size:10px}.ed-version-actions a,.ed-version-actions button{font-size:12px}.ed-version-missing{font-size:12px;line-height:1.55}
.ed-version-select:focus-visible,.ed-version-actions a:focus-visible,.ed-version-actions button:focus-visible{outline:3px solid color-mix(in srgb,var(--primary) 28%,transparent);outline-offset:2px}
.ed-evolution-group>summary,.ed-version-select,.ed-version-actions a,.ed-version-actions button:not(:disabled){cursor:pointer}
.ed-version-select,.ed-version-actions a,.ed-version-actions button{transition:background .16s ease,border-color .16s ease,color .16s ease,transform .16s ease}
.ed-version-select:hover,.ed-version-actions a:hover,.ed-version-actions button:not(:disabled):hover{transform:translateY(-1px)}
.ed-version-actions a:hover,.ed-version-actions button:not(:disabled):hover{border-color:var(--primary);background:var(--surface-soft);color:var(--primary)}
</style>
<style>
.ed-document-timeline{position:relative;min-width:0;margin:0;padding:0 0 0 58px;display:grid;list-style:none}.ed-document-timeline::before{position:absolute;top:20px;bottom:20px;left:25px;width:2px;border-radius:999px;background:color-mix(in srgb,var(--primary) 20%,var(--line));content:""}.ed-document-event{--document-tone:var(--primary);position:relative;min-width:0;padding:0 0 20px}.ed-document-event:last-child{padding-bottom:0}.ed-document-event[data-document-event="file-removed"]{--document-tone:#64748b}.ed-document-event[data-document-event="file-restored"]{--document-tone:#15803d}.ed-document-event[data-document-event^="presentation"]{--document-tone:#7c3aed}.ed-document-marker{position:absolute;z-index:1;top:1px;left:-50px;width:36px;height:36px;border:5px solid var(--surface);border-radius:50%;background:color-mix(in srgb,var(--document-tone) 10%,var(--surface));color:var(--document-tone);display:grid;place-items:center;box-shadow:0 0 0 1px color-mix(in srgb,var(--document-tone) 24%,var(--line))}.ed-document-marker i{font-size:13px}.ed-document-event-content{min-width:0;padding:2px 2px 18px;border-bottom:1px solid var(--line)}.ed-document-event:last-child .ed-document-event-content{border-bottom:0}.ed-document-event-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}.ed-document-event-head h3{margin:0;font-size:14px;line-height:1.4;overflow-wrap:anywhere}.ed-document-event-head p{margin:5px 0 0;color:var(--muted);font-size:11px;line-height:1.45}.ed-document-event-head p i{width:15px;color:var(--document-tone);text-align:center}.ed-document-event-head time{flex:0 0 auto;color:var(--muted);font-size:10px;line-height:1.5}.ed-document-event-file{margin:8px 0 0;color:var(--text);font-size:12px;font-weight:750;line-height:1.5;overflow-wrap:anywhere}.ed-document-event-file i{margin-right:7px;color:var(--document-tone)}.ed-document-transition{width:min(100%,420px);margin:9px 0 0;padding:9px 12px;border-left:3px solid color-mix(in srgb,var(--document-tone) 50%,var(--line));border-radius:0 9px 9px 0;background:var(--surface-soft);display:grid;gap:4px;font-size:11px;line-height:1.45;overflow-wrap:anywhere}.ed-document-transition i{color:var(--muted);font-size:9px}.ed-document-event.is-major .ed-evolution-group{margin-top:12px;border-color:color-mix(in srgb,var(--document-tone) 25%,var(--line));box-shadow:none}.ed-document-event.is-major .ed-evolution-group>summary{min-height:76px}.ed-document-event.is-major .ed-evolution-group-icon{display:none}.ed-document-event.is-major .ed-evolution-group>summary{grid-template-columns:minmax(0,1fr) auto}.ed-document-evolution-empty{min-height:88px;padding:16px 18px;border:1px dashed var(--line);border-radius:13px;background:var(--surface);display:flex;align-items:center;gap:12px}.ed-document-evolution-empty>i{width:34px;height:34px;border-radius:50%;background:var(--surface-soft);color:var(--muted);display:grid;place-items:center}.ed-document-evolution-empty h3{margin:0;font-size:13px}.ed-document-evolution-empty p{margin:4px 0 0;color:var(--muted);font-size:11px;line-height:1.45}@media(max-width:700px){.ed-document-event-head{display:grid;gap:3px}.ed-document-event-head time{grid-row:2}}@media(max-width:520px){.ed-document-timeline{padding-left:45px}.ed-document-timeline::before{left:18px}.ed-document-marker{left:-43px;width:32px;height:32px;border-width:4px}.ed-document-event-content{padding-bottom:16px}.ed-document-event.is-major .ed-evolution-group>summary{grid-template-columns:minmax(0,1fr)}.ed-document-event.is-major .ed-evolution-group-state{grid-column:1}.ed-document-evolution-empty{padding:13px}}@media(max-width:360px){.ed-document-timeline{padding-left:39px}.ed-document-timeline::before{left:15px}.ed-document-marker{left:-39px;width:30px;height:30px}.ed-document-event-head h3{font-size:13px}}
</style>
<style>.ed-timeline-more{margin:14px 0 0;padding:12px;border-top:1px solid var(--line);display:grid;place-items:center;gap:7px}.ed-timeline-more p{margin:0;color:var(--muted);font-size:10px}.ed-timeline-more button{min-width:130px;min-height:38px;padding:0 14px;border:1px solid var(--line);border-radius:10px;background:var(--surface-soft);color:var(--text);font:inherit;font-size:11px;font-weight:850;cursor:pointer}.ed-timeline-more button:hover{border-color:var(--primary);color:var(--primary)}.ed-timeline-more button:focus-visible{outline:3px solid color-mix(in srgb,var(--primary) 25%,transparent);outline-offset:2px}.ed-timeline-more button:disabled{cursor:wait;opacity:.65}</style>
<style>.ed-document-event-file{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.ed-document-event-file>a,.ed-document-event-file>button,.ed-document-event-file>span:first-child{min-width:0;border:0;background:transparent;color:var(--text);display:inline-flex;align-items:center;gap:7px;font:inherit;font-size:12px;font-weight:750;text-decoration:none;overflow-wrap:anywhere}.ed-document-event-file>a,.ed-document-event-file>button{cursor:pointer}.ed-document-event-file>a:hover,.ed-document-event-file>button:hover{color:var(--primary)}</style>
<?php if ((string)($digitalRecord['entity']['type']??'')==='project' && isset($digitalRecord['project_histories'])):
    $projectHistories=(array)$digitalRecord['project_histories'];
    $academicEntries=(array)($projectHistories['academic']??[]);
    $academicTotal=(int)($projectHistories['academic_total']??count($academicEntries));
    $modificationEntries=(array)($projectHistories['modifications']??[]);
    $historyDate=static fn(string $value):string=>$value!==''?date('d/m/Y H:i',strtotime($value)):'';
    $historyIcons=[
        'registration'=>'fa-file-circle-plus','delivery'=>'fa-cloud-arrow-up','observation'=>'fa-comment-dots',
        'status'=>'fa-arrow-right-arrow-left','tribunal'=>'fa-user-group','tribunal-approval'=>'fa-award',
        'publication'=>'fa-building-columns',
    ];
?>
<style>
.ed-academic-history{min-width:0;display:grid;gap:18px}.ed-academic-history>section{min-width:0;display:grid;gap:18px}.ed-academic-head{padding:19px 20px;border:1px solid var(--line);border-radius:15px;background:var(--surface);display:flex;align-items:center;justify-content:space-between;gap:14px}.ed-academic-head h2{margin:0;font-size:18px}.ed-academic-head p{margin:5px 0 0;color:var(--muted);font-size:12px;line-height:1.5}.ed-academic-count{flex:0 0 auto;padding:5px 9px;border-radius:999px;background:color-mix(in srgb,var(--primary) 8%,var(--surface-soft));color:var(--primary);font-size:10px;font-weight:850}.ed-academic-timeline{position:relative;min-width:0;margin:0;padding:0 0 0 58px;list-style:none}.ed-academic-timeline::before{position:absolute;top:22px;bottom:22px;left:25px;width:2px;border-radius:999px;background:color-mix(in srgb,var(--primary) 20%,var(--line));content:""}.ed-academic-event{--event-tone:var(--primary);position:relative;min-width:0;padding:0 0 22px}.ed-academic-event:last-child{padding-bottom:0}.ed-academic-marker{position:absolute;z-index:1;top:2px;left:-50px;width:36px;height:36px;border:5px solid var(--surface);border-radius:50%;background:color-mix(in srgb,var(--event-tone) 11%,var(--surface));color:var(--event-tone);display:grid;place-items:center;box-shadow:0 0 0 1px color-mix(in srgb,var(--event-tone) 24%,var(--line))}.ed-academic-marker i{font-size:13px}.ed-academic-event[data-event-type="observation"]{--event-tone:#b7791f}.ed-academic-event[data-event-type="status"]{--event-tone:#64748b}.ed-academic-event[data-event-type="tribunal"],.ed-academic-event[data-event-type="tribunal-approval"]{--event-tone:#7c3aed}.ed-academic-event[data-event-type="publication"]{--event-tone:#15803d}.ed-academic-content{min-width:0;padding:2px 2px 20px;border-bottom:1px solid var(--line)}.ed-academic-event:last-child .ed-academic-content{padding-bottom:2px;border-bottom:0}.ed-academic-event[data-event-type="publication"] .ed-academic-content{padding:14px 16px;border:1px solid color-mix(in srgb,#15803d 25%,var(--line));border-radius:12px;background:color-mix(in srgb,#15803d 5%,var(--surface))}.ed-academic-title-row{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}.ed-academic-title-row h3{min-width:0;margin:0;font-size:14px;line-height:1.4;overflow-wrap:anywhere}.ed-academic-date{flex:0 0 auto;color:var(--muted);font-size:10px;line-height:1.5}.ed-academic-actor{margin:5px 0 0;color:var(--muted);font-size:11px;line-height:1.45;overflow-wrap:anywhere}.ed-academic-actor i{width:15px;color:var(--event-tone);text-align:center}.ed-academic-description{max-width:78ch;margin:9px 0 0;font-size:12px;line-height:1.6;white-space:pre-line;overflow-wrap:anywhere}.ed-academic-event[data-event-type="status"] .ed-academic-description{display:inline-block;min-width:170px;padding:9px 14px;border-left:3px solid color-mix(in srgb,var(--event-tone) 55%,var(--line));border-radius:0 9px 9px 0;background:var(--surface-soft);font-weight:750;text-align:center;line-height:1.7}.ed-academic-meta{margin:9px 0 0;padding:0;display:flex;gap:6px;flex-wrap:wrap;list-style:none}.ed-academic-meta li{padding:4px 8px;border:1px solid var(--line);border-radius:999px;background:var(--surface);color:var(--muted);font-size:10px;font-weight:750}.ed-project-history-empty{margin:0;padding:18px;border:1px dashed var(--line);border-radius:11px;color:var(--muted);font-size:12px;text-align:center}.ed-project-modifications{border:1px solid var(--line);border-radius:15px;background:var(--surface);overflow:hidden}.ed-project-modifications>summary{min-height:66px;padding:14px 18px;display:grid;grid-template-columns:minmax(0,1fr) auto auto;align-items:center;gap:10px;cursor:pointer;list-style:none}.ed-project-modifications>summary::-webkit-details-marker{display:none}.ed-project-modifications>summary:hover,.ed-project-modifications>summary:focus-visible{background:var(--surface-soft);outline:none}.ed-project-modifications>summary i{color:var(--muted);transition:transform .18s ease}.ed-project-modifications[open]>summary i{transform:rotate(180deg)}.ed-modification-list{margin:0;padding:0 18px 18px;display:grid;gap:0;list-style:none}.ed-modification-item{min-width:0;padding:12px 0;border-top:1px solid var(--line)}.ed-modification-item strong{font-size:12px;line-height:1.45}.ed-modification-item small{display:block;margin-top:4px;color:var(--muted);font-size:10px}.ed-modification-item p{margin:7px 0 0;font-size:11px;line-height:1.55;white-space:pre-line;overflow-wrap:anywhere}@media(max-width:700px){.ed-academic-title-row{display:grid;gap:3px}.ed-academic-date{grid-row:2}.ed-academic-head{align-items:flex-start}}@media(max-width:520px){.ed-academic-head{padding:16px}.ed-academic-head p{font-size:11px}.ed-academic-timeline{padding-left:45px}.ed-academic-timeline::before{left:18px}.ed-academic-marker{left:-43px;width:32px;height:32px;border-width:4px}.ed-academic-content{padding-bottom:18px}.ed-academic-event[data-event-type="publication"] .ed-academic-content{padding:12px}.ed-academic-event[data-event-type="status"] .ed-academic-description{min-width:0;width:min(100%,220px)}.ed-project-modifications>summary{grid-template-columns:minmax(0,1fr) auto;padding:13px 15px}.ed-project-modifications>summary .ed-academic-count{grid-row:2}.ed-project-modifications>summary>i{grid-column:2;grid-row:1}.ed-modification-list{padding:0 15px 15px}}@media(max-width:360px){.ed-academic-head{display:grid}.ed-academic-timeline{padding-left:39px}.ed-academic-timeline::before{left:15px}.ed-academic-marker{left:-39px;width:30px;height:30px}.ed-academic-description{font-size:11px}.ed-academic-title-row h3{font-size:13px}}
</style>
<style>.ed-history-quick-nav{padding:7px;border:1px solid var(--line);border-radius:12px;background:var(--surface);display:flex;gap:6px;flex-wrap:wrap}.ed-history-quick-nav a{min-height:34px;padding:0 11px;border-radius:8px;color:var(--muted);display:inline-flex;align-items:center;font-size:11px;font-weight:800;text-decoration:none}.ed-history-quick-nav a:hover,.ed-history-quick-nav a:focus-visible{background:var(--surface-soft);color:var(--primary);outline:none}.ed-history-quick-nav a:focus-visible{box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 22%,transparent)}.ed-academic-head-actions{flex:0 0 auto;display:flex;align-items:center;gap:7px}.ed-academic-head-actions button{width:34px;height:34px;border:1px solid var(--line);border-radius:9px;background:var(--surface-soft);color:var(--muted);display:grid;place-items:center;cursor:pointer}.ed-academic-head-actions button:hover{border-color:color-mix(in srgb,var(--primary) 35%,var(--line));color:var(--primary)}.ed-academic-head-actions button:focus-visible{outline:3px solid color-mix(in srgb,var(--primary) 25%,transparent);outline-offset:2px}.ed-academic-head-actions button i{transition:transform .18s ease}.ed-academic-head-actions button[aria-expanded="false"] i{transform:rotate(180deg)}[data-history-collapsible][hidden]{display:none!important}#academicHistorySection,#projectModificationHistory{scroll-margin-top:18px}@media(max-width:520px){.ed-history-quick-nav{display:grid}.ed-history-quick-nav a{width:100%}.ed-academic-head-actions{align-self:start}}</style>
<div class="ed-academic-history" data-project-histories>
    <?php if($modificationEntries):?><nav class="ed-history-quick-nav" aria-label="Secciones del historial"><a href="#academicHistorySection" data-history-jump="academicHistorySection">Historial académico</a><a href="#projectModificationHistory" data-history-jump="projectModificationHistory">Historial de modificaciones</a></nav><?php endif;?>
    <section id="academicHistorySection" aria-labelledby="academicHistoryTitle">
        <header class="ed-academic-head"><div><h2 id="academicHistoryTitle">Historial académico</h2><p>Recorrido institucional del proyecto desde su registro hasta la publicación.</p></div><div class="ed-academic-head-actions"><span class="ed-academic-count"><?=$academicTotal?> eventos</span><?php if($modificationEntries):?><button type="button" data-history-toggle aria-expanded="true" aria-controls="academicHistoryBody" aria-label="Plegar Historial académico"><i class="fa-solid fa-chevron-up" aria-hidden="true"></i></button><?php endif;?></div></header>
        <div id="academicHistoryBody" data-history-collapsible>
        <?php if(!$academicEntries):?><p class="ed-project-history-empty">Este expediente no registra todavía un recorrido académico.</p><?php else:?>
        <ol class="ed-academic-timeline" data-progressive-timeline="academic" data-history-endpoint="<?=e((string)($projectHistories['academic_endpoint']??''))?>" data-history-total="<?=$academicTotal?>" data-history-offset="<?=count($academicEntries)?>">
            <?php foreach($academicEntries as $entry):$type=(string)($entry['type']??'status');$actor=trim((string)($entry['actor']??''));?>
            <li class="ed-academic-event" data-event-type="<?=e($type)?>">
                <span class="ed-academic-marker" aria-hidden="true"><i class="fa-solid <?=e($historyIcons[$type]??'fa-circle')?>"></i></span>
                <article class="ed-academic-content">
                    <div class="ed-academic-title-row"><h3><?=e((string)$entry['title'])?></h3><time class="ed-academic-date" datetime="<?=e((string)$entry['date'])?>"><?=e($historyDate((string)$entry['date']))?></time></div>
                    <?php if($actor!==''):?><p class="ed-academic-actor"><i class="fa-regular fa-user" aria-hidden="true"></i><?=e($actor)?></p><?php endif;?>
                    <?php if(trim((string)($entry['detail']??''))!==''):?><p class="ed-academic-description"><?=e((string)$entry['detail'])?></p><?php endif;?>
                    <?php if(!empty($entry['meta'])):?><ul class="ed-academic-meta"><?php foreach((array)$entry['meta'] as $meta):?><li><?=e((string)$meta)?></li><?php endforeach;?></ul><?php endif;?>
                </article>
            </li>
            <?php endforeach;?>
        </ol>
        <?php if($academicTotal>count($academicEntries)):?><div class="ed-timeline-more"><p data-timeline-progress>Mostrando <?=count($academicEntries)?> de <?=$academicTotal?> eventos</p><button type="button" data-timeline-more="academic"><span>Ver más</span></button></div><?php endif;?>
        <?php endif;?>
        </div>
    </section>
    <?php if($modificationEntries):?>
    <details class="ed-project-modifications" id="projectModificationHistory" data-project-history="modifications"><summary><strong>Historial de modificaciones</strong><span class="ed-academic-count"><?=count($modificationEntries)?></span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary><ol class="ed-modification-list"><?php foreach($modificationEntries as $entry):?><li class="ed-modification-item"><strong><?=e((string)$entry['title'])?></strong><small><?=e((string)$entry['actor'])?> · <?=e($historyDate((string)$entry['date']))?></small><?php if(trim((string)($entry['detail']??''))!==''):?><p><?=e((string)$entry['detail'])?></p><?php endif;?></li><?php endforeach;?></ol></details>
    <?php endif;?>
</div>
<?php else: ?>
<div class="ed-evolution" data-document-evolution>
    <header class="ed-evolution-head">
        <h2>Evolución documental</h2>
        <p>Consulta la secuencia de versiones registrada para los documentos del <?= e($evolutionOwner) ?>.</p>
    </header>
    <?php if (!$documentEvents): ?>
        <div class="ed-document-evolution-empty"><i class="fa-regular fa-clock" aria-hidden="true"></i><div><h3>Este material aún no registra evolución documental.</h3></div></div>
    <?php else: ?>
        <ol class="ed-document-timeline" data-progressive-timeline="document" data-history-endpoint="<?=e((string)($digitalRecord['document_evolution_endpoint']??''))?>" data-history-total="<?=(int)($digitalRecord['document_evolution_total']??count($documentEvents))?>" data-history-offset="<?=count($documentEvents)?>">
            <?php foreach ($documentEvents as $eventIndex => $documentEvent):
                $eventType=(string)($documentEvent['type']??'file-added');
                $eventGroupIndex=$documentEvent['version_group_index']??null;
                $group=$eventGroupIndex!==null?($groupedEvolution[(int)$eventGroupIndex]??null):null;
                $eventDate=$evolutionDateParts(!empty($documentEvent['date'])?date('d/m/Y H:i',strtotime((string)$documentEvent['date'])):'Fecha no disponible');
                $eventIcons=['file-added'=>'fa-file-circle-plus','file-replaced'=>'fa-file-pen','file-removed'=>'fa-file-circle-minus','file-restored'=>'fa-trash-arrow-up','presentation-changed'=>'fa-star','presentation-removed'=>'fa-star-half-stroke'];
            ?>
            <?php $majorEvent=$group||in_array($eventType,['file-replaced','presentation-changed','presentation-removed'],true);?>
            <li class="ed-document-event<?= $majorEvent?' is-major':' is-compact'?>" data-document-event="<?=e($eventType)?>">
                <span class="ed-document-marker" aria-hidden="true"><i class="fa-solid <?=e($eventIcons[$eventType]??'fa-file-lines')?>"></i></span>
                <div class="ed-document-event-content">
                    <div class="ed-document-event-head"><div><h3><?=e((string)($documentEvent['title']??'Cambio documental'))?></h3><?php if(trim((string)($documentEvent['responsible']??''))!==''):?><p><i class="fa-regular fa-user" aria-hidden="true"></i><?=e((string)$documentEvent['responsible'])?></p><?php endif;?></div><time><?=e($eventDate['date'])?><?= $eventDate['time']!==''?' · '.e($eventDate['time']):''?></time></div>
                    <?php if(!$group):?>
                        <?php if(trim((string)($documentEvent['file_name']??''))!==''):$fileState=(string)($documentEvent['file_state']??'unavailable');?><div class="ed-document-event-file"><?php if($fileState==='available'&&!empty($documentEvent['preview_url'])):?><button type="button" data-evolution-preview data-file-id="event:<?=(int)$documentEvent['id']?>" data-file-name="<?=e((string)$documentEvent['file_name'])?>" data-file-type="<?=e(mb_strtoupper((string)($documentEvent['extension']??'FILE'),'UTF-8'))?>" data-file-size="<?=e((string)($documentEvent['size']??''))?>" data-file-extension="<?=e((string)($documentEvent['extension']??''))?>" data-version-current="true" data-preview-type="<?=e((string)($documentEvent['extension']??''))?>" data-preview-supported="true" data-preview-url="<?=e((string)$documentEvent['preview_url'])?>" data-download-url="<?=e((string)$documentEvent['download_url'])?>"><i class="fa-regular fa-file" aria-hidden="true"></i><?=e((string)$documentEvent['file_name'])?></button><?php elseif($fileState==='available'&&!empty($documentEvent['download_url'])):?><a href="<?=e((string)$documentEvent['download_url'])?>" download><i class="fa-regular fa-file" aria-hidden="true"></i><?=e((string)$documentEvent['file_name'])?></a><?php else:?><span><i class="fa-regular fa-file" aria-hidden="true"></i><?=e((string)$documentEvent['file_name'])?></span><?php endif;?><span class="ed-version-badge<?= $fileState==='available'?' is-available':''?>"><?= $fileState==='available'?'Disponible':($fileState==='deleted'?'Eliminado':'No disponible')?></span></div><?php endif;?>
                        <?php if($eventType==='presentation-changed'&&trim((string)($documentEvent['previous_name']??''))!==''):?><p class="ed-document-transition"><?=e((string)$documentEvent['previous_name'])?><i class="fa-solid fa-arrow-down" aria-hidden="true"></i><?=e((string)($documentEvent['new_name']??$documentEvent['file_name']??''))?></p><?php endif;?>
                    <?php else:$groupDate = $evolutionDateParts((string) ($group['updated_date'] ?? 'Fecha no disponible')); ?>
                <details class="ed-evolution-group"<?= $eventIndex === 0 ? ' open' : '' ?>>
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
                            $previewUrl = (string) ($version['preview_url'] ?? '');
                            $downloadUrl = (string) ($version['download_url'] ?? '');
                            $previewType = $previewTypes[$extension] ?? 'unsupported';
                            $versionDate = $evolutionDateParts((string) ($version['date'] ?? 'Fecha no disponible'));
                        ?>
                            <?php $versionControlId = 'recordVersionControl-' . (int) $version['file_id'] . '-' . ($current ? 'current' : (int) $version['id']); $versionDetailId = $versionControlId . '-detail'; ?>
                            <article class="ed-version-entry<?= $current ? ' is-current' : '' ?>">
                                <button class="ed-version-select" id="<?= e($versionControlId) ?>" type="button" data-evolution-version aria-expanded="false" aria-controls="<?= e($versionDetailId) ?>">
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
                                        <?php $versionState=(string)($version['state']??($available?'available':'unavailable'));?><span class="ed-version-badge<?= $available ? ' is-available' : '' ?>"><?= $versionState==='available'?'Disponible':($versionState==='deleted'?'Eliminado':'No disponible') ?></span>
                                    </span>
                                </button>
                                <div class="ed-version-detail" id="<?= e($versionDetailId) ?>" role="region" aria-labelledby="<?= e($versionControlId) ?>" data-evolution-version-detail hidden>
                                    <?php if ($available): ?>
                                        <div class="ed-version-actions">
                                            <?php if (!empty($version['preview_supported']) && $previewUrl !== ''): ?>
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
                                            <?php if($downloadUrl!==''):?><a href="<?= e($downloadUrl) ?>" download><i class="fa-solid fa-download" aria-hidden="true"></i>Descargar</a><?php endif;?>
                                        </div>
                                    <?php else: ?>
                                        <p class="ed-version-missing">Esta versión ya no se encuentra disponible en el sistema. Solo permanece su registro histórico.</p>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </details>
                    <?php endif;?>
                </div>
            </li>
            <?php endforeach; ?>
        </ol>
        <?php $documentTotal=(int)($digitalRecord['document_evolution_total']??count($documentEvents));if($documentTotal>count($documentEvents)):?><div class="ed-timeline-more"><p data-timeline-progress>Mostrando <?=count($documentEvents)?> de <?=$documentTotal?> eventos</p><button type="button" data-timeline-more="document"><span>Ver más</span></button></div><?php endif;?>
    <?php endif; ?>
</div>
<?php endif; ?>
