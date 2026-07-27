<?php
/** Listado y visor visual de documentos normalizados. */
$documents = is_array($digitalRecord['documents'] ?? null) ? $digitalRecord['documents'] : [];
$selectedDocument = $documents[0] ?? null;
$iconForExtension = static fn (string $extension): string => match ($extension) {
    'pdf' => 'fa-file-pdf', 'doc', 'docx' => 'fa-file-word', 'xls', 'xlsx' => 'fa-file-excel',
    'ppt', 'pptx' => 'fa-file-powerpoint', 'zip' => 'fa-file-zipper',
    'png', 'jpg', 'jpeg', 'webp' => 'fa-file-image', 'txt' => 'fa-file-lines', default => 'fa-file',
};
?>
<style>
.ed-files-layout{display:grid;grid-template-columns:minmax(290px,.82fr) minmax(360px,1.18fr);gap:14px;align-items:start}.ed-files-panel{min-width:0;border:1px solid var(--line);border-radius:15px;background:var(--surface);overflow:hidden;box-shadow:0 5px 16px rgba(15,23,42,.035)}.ed-files-panel>header{padding:18px 19px;border-bottom:1px solid var(--line);background:var(--surface)}.ed-files-panel h2{margin:0;font-size:15px}.ed-files-panel header p{margin:5px 0 0;color:var(--muted);font-size:11px}.ed-document-list{display:grid;padding:7px}.ed-document-row{width:100%;min-height:66px;padding:10px 11px;border:0;border-radius:10px;background:transparent;color:var(--text);display:grid;grid-template-columns:40px minmax(0,1fr) auto;align-items:center;gap:11px;font:inherit;text-align:left;transition:.16s ease}.ed-document-row+.ed-document-row{margin-top:3px}.ed-document-row:hover{background:var(--surface-soft)}.ed-document-row.is-selected{background:#eff6ff;color:var(--primary);box-shadow:inset 3px 0 0 var(--primary)}.ed-document-row:focus-visible{position:relative;outline:3px solid color-mix(in srgb,var(--primary) 25%,transparent);outline-offset:-3px}.ed-document-row>i{width:36px;height:36px;border-radius:10px;background:var(--surface-soft);color:var(--primary);display:grid;place-items:center;font-size:16px}.ed-document-row.is-selected>i{background:#dbeafe}.ed-document-row strong{display:block;font-size:12px;overflow-wrap:anywhere}.ed-document-row small{display:block;margin-top:3px;color:var(--muted);font-size:10px}.ed-primary-mark{padding:4px 7px;border:1px solid rgba(37,99,235,.2);border-radius:999px;background:rgba(37,99,235,.07);color:var(--primary);font-size:9px;font-weight:800}.ed-viewer{min-height:292px;display:grid;grid-template-rows:auto 1fr}.ed-viewer-head{display:flex;align-items:center;justify-content:space-between;gap:14px}.ed-viewer-head div{min-width:0}.ed-viewer-head strong{display:block;font-size:12px;overflow-wrap:anywhere}.ed-viewer-head span{color:var(--muted);font-size:10px}.ed-viewer-body{display:grid;place-items:center;min-height:218px;padding:24px;background:var(--surface-soft);text-align:center}.ed-viewer-body i{width:52px;height:52px;margin:auto;border-radius:15px;background:var(--surface);color:var(--primary);display:grid;place-items:center;font-size:21px;box-shadow:0 5px 14px rgba(15,23,42,.06)}.ed-viewer-body h3{margin:12px 0 6px;font-size:15px}.ed-viewer-body p{max-width:400px;margin:0;color:var(--muted);font-size:11px;line-height:1.6}.ed-back-to-files{display:none;min-height:40px;padding:0 10px;border:1px solid var(--line);border-radius:9px;background:var(--surface-soft);color:var(--text);font:inherit;font-size:11px;font-weight:800}
html.theme-dark .ed-document-row.is-selected,body.dark-mode .ed-document-row.is-selected{background:#172554;color:#93c5fd}html.theme-dark .ed-document-row.is-selected>i,body.dark-mode .ed-document-row.is-selected>i{background:#1e3a5f}
@media(max-width:820px){.ed-files-layout{grid-template-columns:1fr}.ed-viewer{min-height:275px}.ed-back-to-files{display:inline-flex;align-items:center;gap:7px}.ed-viewer-body{min-height:200px}}@media(max-width:420px){.ed-files-panel{border-radius:12px}.ed-document-row{min-height:66px}.ed-viewer-head{align-items:flex-start;flex-direction:column}}
</style>
<?php if (!$documents): ?>
    <div class="ed-empty"><i class="fa-regular fa-folder-open" aria-hidden="true"></i><h2>Este material no contiene archivos</h2><p>Los documentos aparecerán aquí cuando sean incorporados al expediente.</p></div>
<?php else: ?>
<div class="ed-files-layout" data-record-files>
    <section class="ed-files-panel" aria-labelledby="recordFilesTitle">
        <header><h2 id="recordFilesTitle">Archivos del material</h2><p><?= count($documents) ?> <?= count($documents) === 1 ? 'archivo disponible' : 'archivos disponibles' ?></p></header>
        <div class="ed-document-list" role="list">
            <?php foreach ($documents as $index => $document): ?>
                <button class="ed-document-row<?= $index === 0 ? ' is-selected' : '' ?>" type="button" role="listitem" data-record-file data-file-index="<?= $index ?>" data-file-name="<?= e($document['name']) ?>" data-file-type="<?= e($document['type']) ?>" data-file-size="<?= e($document['size']) ?>" data-file-extension="<?= e($document['extension']) ?>" aria-pressed="<?= $index === 0 ? 'true' : 'false' ?>">
                    <i class="fa-solid <?= e($iconForExtension($document['extension'])) ?>" aria-hidden="true"></i>
                    <span><strong><?= e($document['name']) ?></strong><small><?= e($document['type']) ?> · <?= e($document['size']) ?></small></span>
                    <?php if (!empty($document['is_primary'])): ?><span class="ed-primary-mark">Principal</span><?php endif; ?>
                </button>
            <?php endforeach; ?>
        </div>
    </section>
    <?php require __DIR__ . '/_visor-documental.php'; ?>
</div>
<?php endif; ?>
