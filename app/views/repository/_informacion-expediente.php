<?php
/** Secciones neutrales de información del expediente. */
$sections = array_values(array_filter(
    (array) ($digitalRecord['information_sections'] ?? []),
    static function (array $section): bool {
        $content = $section['content'] ?? null;
        return $section['type'] === 'empty' || (is_array($content) ? $content !== [] : trim((string) $content) !== '');
    }
));
?>
<style>
.ed-information{width:100%;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.ed-document-section{min-width:0;padding:20px;margin:0;border:1px solid var(--line);border-radius:15px;background:var(--surface);box-shadow:0 5px 16px rgba(15,23,42,.035)}.ed-document-section:first-child{grid-column:1/-1}.ed-document-section:last-child{padding:20px;margin:0;border:1px solid var(--line)}.ed-document-heading{display:flex;align-items:center;gap:10px;margin:0 0 15px;font-size:14px;font-weight:850}.ed-document-heading i{width:34px;height:34px;border-radius:10px;background:var(--surface-soft);color:var(--primary);display:grid;place-items:center;font-size:12px}.ed-prose{max-width:860px;color:var(--muted);font-size:14px;line-height:1.78}.ed-prose p{margin:0 0 12px}.ed-information-meta{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px 24px;margin:0}.ed-information-meta div{display:grid;gap:5px}.ed-information-meta dt{color:var(--muted);font-size:9px;font-weight:850;letter-spacing:.07em;text-transform:uppercase}.ed-information-meta dd{margin:0;font-size:13px;font-weight:780;overflow-wrap:anywhere}.ed-tags{display:flex;flex-wrap:wrap;gap:7px}.ed-tags span{padding:6px 9px;border:1px solid var(--line);border-radius:999px;background:var(--surface-soft);color:var(--muted);font-size:10px;font-weight:750}.ed-related-empty{display:flex;align-items:center;gap:9px;margin:0;color:var(--muted);font-size:12px;line-height:1.5}
@media(max-width:800px){.ed-information{grid-template-columns:1fr}.ed-document-section:first-child{grid-column:auto}}@media(max-width:520px){.ed-document-section{padding:17px}.ed-information-meta{grid-template-columns:1fr;gap:15px}}
</style>
<?php if (!$sections): ?>
    <div class="ed-empty"><i class="fa-regular fa-file-lines" aria-hidden="true"></i><h2>Información no disponible</h2><p>Este material todavía no posee información descriptiva para mostrar.</p></div>
<?php else: ?>
<div class="ed-information">
    <?php foreach ($sections as $section): $content = $section['content']; ?>
        <section class="ed-document-section" aria-labelledby="ed-section-<?= e($section['id']) ?>">
            <h2 class="ed-document-heading" id="ed-section-<?= e($section['id']) ?>"><i class="fa-solid <?= e($section['icon']) ?>" aria-hidden="true"></i><?= e($section['title']) ?></h2>
            <?php if ($section['type'] === 'prose'): ?>
                <div class="ed-prose"><?php foreach (preg_split('/(?:\r?\n){2,}/', trim((string) $content), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $paragraph): ?><p><?= nl2br(e(trim($paragraph))) ?></p><?php endforeach; ?></div>
            <?php elseif ($section['type'] === 'metadata'): ?>
                <dl class="ed-information-meta"><?php foreach ($content as $item): ?><div><dt><?= e($item['label']) ?></dt><dd><?= e($item['value']) ?></dd></div><?php endforeach; ?></dl>
            <?php elseif ($section['type'] === 'tags'): ?>
                <div class="ed-tags"><?php foreach ($content as $tag): ?><span><?= e($tag) ?></span><?php endforeach; ?></div>
            <?php else: ?>
                <p class="ed-related-empty"><i class="fa-regular fa-folder-open" aria-hidden="true"></i><?= e((string) $content) ?></p>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</div>
<?php endif; ?>
