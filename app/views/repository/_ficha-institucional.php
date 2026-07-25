<?php
/**
 * Fase 1: estructura visual compartida del Expediente Digital Institucional.
 * Requiere únicamente los datos de presentación contenidos en $record.
 */
?>
<style>
.digital-record{--ed-accent:var(--primary);width:min(1140px,100%);margin:0 auto;padding:18px 0 40px;color:var(--text)}
.ed-breadcrumb{display:flex;align-items:center;gap:9px;min-width:0;margin:0 0 13px;padding:0 2px;color:var(--muted);font-size:11px;line-height:1.5}
.ed-breadcrumb a{color:var(--muted);font-weight:700}.ed-breadcrumb a:hover,.ed-breadcrumb a:focus-visible{color:var(--ed-accent)}
.ed-breadcrumb i{flex:0 0 auto;font-size:8px}.ed-breadcrumb span{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ed-back{display:inline-flex;align-items:center;gap:8px;margin:0 0 17px 2px;color:var(--muted);font-size:12px;font-weight:750}
.ed-back:hover,.ed-back:focus-visible{color:var(--ed-accent)}
.ed-shell{overflow:hidden;border-top:1px solid var(--line);border-bottom:1px solid var(--line);background:transparent}
.ed-header{padding:24px 2px 22px;border-bottom:1px solid var(--line)}
.ed-header-top{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:16px}
.ed-labels{display:flex;flex-wrap:wrap;gap:7px}
.ed-label{display:inline-flex;align-items:center;gap:6px;padding:4px 9px;border:1px solid var(--line);border-radius:999px;background:transparent;color:var(--muted);font-size:10px;font-weight:800;letter-spacing:.02em}
.ed-label.is-status{border-color:rgba(34,197,94,.22);background:rgba(34,197,94,.09);color:#15803d}
.ed-header h1{max-width:980px;margin:0;color:var(--text);font-size:clamp(27px,3.4vw,40px);line-height:1.12;letter-spacing:-.025em;overflow-wrap:anywhere}
.ed-description{max-width:860px;margin:10px 0 0;color:var(--muted);font-size:14px;line-height:1.65}
.ed-meta{display:flex;align-items:stretch;flex-wrap:wrap;gap:0;margin:19px 0 0}.ed-meta div{display:grid;gap:3px;min-width:160px;padding:0 22px;border-left:1px solid var(--line)}.ed-meta div:first-child{padding-left:0;border-left:0}.ed-meta dt{color:var(--muted);font-size:9px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.ed-meta dd{margin:0;color:var(--text);font-size:12px;font-weight:750}
.ed-actions{display:flex;align-items:center;justify-content:flex-end;gap:8px;flex:0 0 auto}
.ed-action{height:32px;padding:0 10px;border:1px solid var(--line);border-radius:8px;background:transparent;color:var(--muted);display:inline-flex;align-items:center;justify-content:center;gap:6px;font:inherit;font-size:11px;font-weight:750;white-space:nowrap;cursor:default;transition:border-color .16s ease,color .16s ease,background-color .16s ease}
.ed-action:hover,.ed-action:focus-visible{border-color:color-mix(in srgb,var(--ed-accent) 38%,var(--line));background:var(--surface-soft);color:var(--text);outline:0}.ed-action:focus-visible{box-shadow:0 0 0 3px color-mix(in srgb,var(--ed-accent) 14%,transparent)}.ed-action i{color:currentColor}
.ed-tabs{display:flex;align-items:center;gap:30px;height:49px;padding:0 2px;border-bottom:1px solid var(--line);background:transparent}
.ed-tab{position:relative;height:100%;padding:0 2px;border:0;background:transparent;color:var(--muted);display:inline-flex;align-items:center;gap:7px;font:inherit;font-size:12px;font-weight:750;white-space:nowrap}
.ed-tab[aria-selected="true"]{color:var(--text)}.ed-tab[aria-selected="true"]::after{position:absolute;right:0;bottom:-1px;left:0;height:2px;border-radius:2px 2px 0 0;background:var(--ed-accent);content:""}
.ed-content{min-height:280px;padding:26px 2px;background:transparent}.ed-content-placeholder{width:100%;height:100%;min-height:228px}
body.dark-mode .ed-label.is-status{border-color:rgba(74,222,128,.22);background:rgba(74,222,128,.1);color:#86efac}
@media(max-width:760px){.digital-record{padding-top:12px}.ed-header{padding:21px 2px}.ed-header-top{align-items:flex-start;flex-direction:column;margin-bottom:15px}.ed-actions{width:100%;justify-content:flex-start;flex-wrap:wrap}.ed-meta{row-gap:14px}.ed-meta div{min-width:50%;padding:0 16px}.ed-meta div:nth-child(odd){padding-left:0;border-left:0}.ed-tabs{gap:24px;padding:0 2px;overflow-x:auto;scrollbar-width:none}.ed-tabs::-webkit-scrollbar{display:none}.ed-content{min-height:250px;padding:22px 2px}.ed-content-placeholder{min-height:206px}}
@media(max-width:440px){.ed-header{padding:18px 2px}.ed-actions{display:grid;grid-template-columns:1fr 1fr 34px}.ed-action{padding:0 8px}.ed-action.is-more{width:34px}.ed-meta{display:grid;grid-template-columns:1fr 1fr}.ed-meta div{min-width:0;padding:0}.ed-meta div:nth-child(even){padding-left:14px;border-left:1px solid var(--line)}.ed-tabs{gap:22px}.ed-tab{font-size:12px}.ed-content{padding:16px 2px}.ed-breadcrumb span:not(:last-child){display:none}}
</style>

<main class="digital-record">
    <nav class="ed-breadcrumb" aria-label="Ruta de navegación">
        <a href="<?= e($record['repository_url']) ?>">Repositorio</a>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <span><?= e($record['category']) ?></span>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <span aria-current="page" title="<?= e($record['title']) ?>"><?= e($record['title']) ?></span>
    </nav>

    <a class="ed-back" href="<?= e($record['repository_url']) ?>"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Volver al repositorio</a>

    <article class="ed-shell" aria-labelledby="digitalRecordTitle">
        <header class="ed-header">
            <div class="ed-header-top">
                <div class="ed-labels">
                    <span class="ed-label"><?= e($record['type']) ?></span>
                    <span class="ed-label is-status"><i class="fa-solid fa-circle-check" aria-hidden="true"></i><?= e($record['status']) ?></span>
                </div>
                <div class="ed-actions" aria-label="Acciones del expediente">
                    <button class="ed-action" type="button" aria-disabled="true"><i class="fa-solid fa-share-nodes" aria-hidden="true"></i> Compartir</button>
                    <button class="ed-action" type="button" aria-disabled="true"><i class="fa-solid fa-download" aria-hidden="true"></i> Descargar</button>
                    <button class="ed-action is-more" type="button" aria-disabled="true" aria-label="Más opciones"><i class="fa-solid fa-ellipsis" aria-hidden="true"></i></button>
                </div>
            </div>
            <h1 id="digitalRecordTitle"><?= e($record['title']) ?></h1>
            <?php if ($record['description'] !== ''): ?><p class="ed-description"><?= e($record['description']) ?></p><?php endif; ?>
            <dl class="ed-meta">
                <div><dt>Responsable</dt><dd><?= e($record['responsible']) ?></dd></div>
                <div><dt>Publicación</dt><dd><?= e($record['publication_date']) ?></dd></div>
            </dl>
        </header>

        <nav class="ed-tabs" aria-label="Secciones del expediente">
            <button class="ed-tab" type="button" aria-selected="false" disabled><i class="fa-regular fa-file-lines" aria-hidden="true"></i> Información</button>
            <button class="ed-tab" type="button" aria-selected="false" disabled><i class="fa-regular fa-folder-open" aria-hidden="true"></i> Archivos</button>
            <button class="ed-tab" type="button" aria-selected="true" disabled><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Historial</button>
        </nav>

        <section class="ed-content" aria-label="Contenido del expediente">
            <?php require __DIR__ . '/_asistente-nueva-version.php'; ?>
        </section>
    </article>
</main>
