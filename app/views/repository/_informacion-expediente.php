<?php
/**
 * Contenido compartido de la pestaña Información.
 * Consume exclusivamente el arreglo normalizado $information.
 */
$institutionalData = array_filter(
    $information['institutional'],
    static fn ($value): bool => $value !== null && $value !== ''
);
$descriptionParagraphs = preg_split(
    '/(?:\r?\n){2,}/',
    trim($information['description']),
    -1,
    PREG_SPLIT_NO_EMPTY
) ?: [];
?>
<style>
.ed-information{width:min(880px,100%);color:var(--text)}
.ed-document-section{padding:0 0 28px;margin:0 0 28px;border-bottom:1px solid var(--line)}
.ed-document-section:last-child{padding-bottom:0;margin-bottom:0;border-bottom:0}
.ed-document-heading{display:flex;align-items:center;gap:9px;margin:0 0 13px;color:var(--text);font-size:14px;font-weight:800;letter-spacing:-.01em}
.ed-document-heading i{width:16px;color:var(--muted);font-size:12px;text-align:center}
.ed-lead{max-width:790px;margin:0;color:var(--text);font-size:16px;line-height:1.72}
.ed-prose{max-width:820px;color:var(--muted);font-size:14px;line-height:1.8}
.ed-prose p{margin:0 0 13px}.ed-prose p:last-child{margin-bottom:0}
.ed-objectives{display:grid;gap:12px;margin:0;padding:0;list-style:none;counter-reset:objective}
.ed-objectives li{position:relative;padding-left:28px;color:var(--muted);font-size:14px;line-height:1.7;counter-increment:objective}
.ed-objectives li::before{position:absolute;top:1px;left:0;color:var(--text);font-size:11px;font-weight:850;content:counter(objective,decimal-leading-zero)}
.ed-keywords,.ed-technical{display:flex;flex-wrap:wrap;gap:7px}
.ed-keywords span{padding:4px 8px;border:1px solid var(--line);border-radius:999px;background:transparent;color:var(--muted);font-size:10px;font-weight:650}
.ed-technical span{padding:6px 9px;border:1px solid var(--line);border-radius:999px;background:transparent;color:var(--muted);font-size:11px;font-weight:700}
.ed-institutional{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px 38px;margin:0}
.ed-institutional div{display:grid;gap:5px;align-content:start}
.ed-institutional dt{color:var(--muted);font-size:10px;font-weight:800;letter-spacing:.06em;text-transform:uppercase}.ed-institutional dd{margin:0;color:var(--text);font-size:13px;font-weight:750;line-height:1.5;overflow-wrap:anywhere}
.ed-related-empty{display:flex;align-items:center;gap:9px;color:var(--muted);font-size:12px;line-height:1.5}.ed-related-empty i{font-size:12px}
@media(max-width:700px){.ed-document-section{padding-bottom:24px;margin-bottom:24px}.ed-institutional{grid-template-columns:1fr;gap:17px}.ed-lead{font-size:15px}}
@media(max-width:420px){.ed-objectives li{padding-left:25px}.ed-document-heading{font-size:13px}}
</style>

<div class="ed-information">
    <?php if ($information['summary'] !== ''): ?>
        <section class="ed-document-section" aria-labelledby="recordSummaryTitle">
            <h2 class="ed-document-heading" id="recordSummaryTitle"><i class="fa-regular fa-file-lines" aria-hidden="true"></i> Resumen</h2>
            <p class="ed-lead"><?= e($information['summary']) ?></p>
        </section>
    <?php endif; ?>

    <?php if ($information['description'] !== ''): ?>
        <section class="ed-document-section" aria-labelledby="recordDescriptionTitle">
            <h2 class="ed-document-heading" id="recordDescriptionTitle"><i class="fa-solid fa-align-left" aria-hidden="true"></i> Descripción</h2>
            <div class="ed-prose"><?php foreach ($descriptionParagraphs as $paragraph): ?><p><?= nl2br(e(trim($paragraph))) ?></p><?php endforeach; ?></div>
        </section>
    <?php endif; ?>

    <?php if ($information['objectives'] !== []): ?>
        <section class="ed-document-section" aria-labelledby="recordObjectivesTitle">
            <h2 class="ed-document-heading" id="recordObjectivesTitle"><i class="fa-solid fa-bullseye" aria-hidden="true"></i> Objetivos</h2>
            <ol class="ed-objectives"><?php foreach ($information['objectives'] as $objective): ?><li><?= e($objective) ?></li><?php endforeach; ?></ol>
        </section>
    <?php endif; ?>

    <?php if ($information['keywords'] !== []): ?>
        <section class="ed-document-section" aria-labelledby="recordKeywordsTitle">
            <h2 class="ed-document-heading" id="recordKeywordsTitle"><i class="fa-solid fa-tags" aria-hidden="true"></i> Palabras clave</h2>
            <div class="ed-keywords"><?php foreach ($information['keywords'] as $keyword): ?><span><?= e($keyword) ?></span><?php endforeach; ?></div>
        </section>
    <?php endif; ?>

    <?php if ($institutionalData !== []): ?>
        <section class="ed-document-section" aria-labelledby="recordInstitutionalTitle">
            <h2 class="ed-document-heading" id="recordInstitutionalTitle"><i class="fa-solid fa-building-columns" aria-hidden="true"></i> Información institucional</h2>
            <dl class="ed-institutional"><?php foreach ($institutionalData as $label => $value): ?><div><dt><?= e($label) ?></dt><dd><?= e((string) $value) ?></dd></div><?php endforeach; ?></dl>
        </section>
    <?php endif; ?>

    <?php if ($information['technical'] !== []): ?>
        <section class="ed-document-section" aria-labelledby="recordTechnicalTitle">
            <h2 class="ed-document-heading" id="recordTechnicalTitle"><i class="fa-solid fa-code" aria-hidden="true"></i> Información técnica</h2>
            <div class="ed-technical"><?php foreach ($information['technical'] as $item): ?><span><?= e($item) ?></span><?php endforeach; ?></div>
        </section>
    <?php endif; ?>

    <section class="ed-document-section" aria-labelledby="recordRelatedTitle">
        <h2 class="ed-document-heading" id="recordRelatedTitle"><i class="fa-solid fa-link" aria-hidden="true"></i> Recursos relacionados</h2>
        <p class="ed-related-empty"><i class="fa-regular fa-folder-open" aria-hidden="true"></i> No existen recursos relacionados para este expediente.</p>
    </section>
</div>
