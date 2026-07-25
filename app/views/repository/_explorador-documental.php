<?php
/**
 * Simulación visual compartida del recorrido documental.
 * Todos los escenarios reutilizan la misma estructura de breadcrumb y fila.
 */
$documentScenarios = [
    [
        'label' => 'Raíz del expediente',
        'path' => ['Expediente', 'Documentos'],
        'items' => [
            ['kind'=>'folder','name'=>'Código fuente','meta'=>'12 elementos'],
            ['kind'=>'folder','name'=>'Documentación','meta'=>'6 archivos'],
            ['kind'=>'folder','name'=>'Recursos','meta'=>'8 elementos'],
            ['kind'=>'folder','name'=>'Anexos','meta'=>'4 archivos'],
            ['kind'=>'file','name'=>'Informe final.pdf','extension'=>'pdf','type'=>'PDF','size'=>'3,2 MB','status'=>'Vista disponible'],
            ['kind'=>'file','name'=>'Manual de usuario.pdf','extension'=>'pdf','type'=>'PDF','size'=>'689 KB','status'=>'Vista disponible'],
        ],
    ],
    [
        'label' => 'Dentro de Código fuente',
        'path' => ['Expediente', 'Código fuente'],
        'items' => [
            ['kind'=>'folder','name'=>'Backend','meta'=>'4 carpetas'],
            ['kind'=>'folder','name'=>'Frontend','meta'=>'9 elementos'],
            ['kind'=>'file','name'=>'README.md','extension'=>'md','type'=>'README','size'=>'14 KB','status'=>'Vista disponible'],
            ['kind'=>'file','name'=>'composer.json','extension'=>'json','type'=>'JSON','size'=>'3 KB','status'=>'Vista disponible'],
        ],
    ],
    [
        'label' => 'Dentro de Backend',
        'path' => ['Expediente', 'Código fuente', 'Backend'],
        'items' => [
            ['kind'=>'folder','name'=>'Controllers','meta'=>'4 archivos','selected'=>true],
            ['kind'=>'folder','name'=>'Models','meta'=>'7 archivos'],
            ['kind'=>'folder','name'=>'Routes','meta'=>'3 archivos'],
            ['kind'=>'folder','name'=>'Helpers','meta'=>'5 archivos'],
        ],
    ],
    [
        'label' => 'Dentro de Controllers',
        'path' => ['Expediente', 'Código fuente', 'Backend', 'Controllers'],
        'items' => [
            ['kind'=>'file','name'=>'AuthController.php','extension'=>'php','type'=>'PHP','size'=>'18 KB','status'=>'Vista disponible','selected'=>true],
            ['kind'=>'file','name'=>'ProjectsController.php','extension'=>'php','type'=>'PHP','size'=>'31 KB','status'=>'Vista disponible'],
            ['kind'=>'file','name'=>'UsersController.php','extension'=>'php','type'=>'PHP','size'=>'24 KB','status'=>'Vista disponible'],
            ['kind'=>'file','name'=>'BaseController.php','extension'=>'php','type'=>'PHP','size'=>'9 KB','status'=>'Vista disponible'],
        ],
    ],
    [
        'label' => 'Contenido comprimido',
        'path' => ['Expediente', 'Documentos'],
        'items' => [
            ['kind'=>'archive','name'=>'codigo_fuente.zip','extension'=>'zip','type'=>'ZIP','size'=>'24 MB','status'=>'Contenido explorable','selected'=>true],
        ],
    ],
    [
        'label' => 'Carpeta vacía',
        'path' => ['Expediente', 'Anexos', 'Complementarios'],
        'items' => [],
    ],
];

$iconForDocument = static function (array $item): string {
    if ($item['kind'] === 'folder') return 'fa-folder';
    return match ($item['extension'] ?? '') {
        'pdf' => 'fa-file-pdf',
        'md' => 'fa-file-lines',
        'json', 'php' => 'fa-file-code',
        'zip' => 'fa-file-zipper',
        default => 'fa-file',
    };
};
?>
<style>
.ed-explorer-simulation{display:grid;gap:34px;color:var(--text)}
.ed-scenario{min-width:0}.ed-scenario-label{margin:0 0 10px;color:var(--muted);font-size:9px;font-weight:850;letter-spacing:.09em;text-transform:uppercase}
.ed-explorer{min-width:0;border-top:1px solid var(--line)}
.ed-explorer-toolbar{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:15px 0;border-bottom:1px solid var(--line)}
.ed-location{min-width:0;display:grid;gap:7px}.ed-file-breadcrumb{display:flex;align-items:center;gap:8px;min-width:0;color:var(--muted);font-size:11px}
.ed-file-breadcrumb button{padding:2px 0;border:0;background:transparent;color:var(--muted);font:inherit;font-weight:700}.ed-file-breadcrumb button:disabled{opacity:1}.ed-file-breadcrumb button[aria-current="page"]{color:var(--text);font-weight:850}.ed-file-breadcrumb i{font-size:8px}
.ed-document-count{display:flex;align-items:center;gap:7px;margin:0;color:var(--muted);font-size:10px}.ed-document-count i{font-size:3px}
.ed-file-search{width:min(280px,100%);height:33px;padding:0 10px;border:1px solid var(--line);border-radius:8px;display:flex;align-items:center;gap:8px;color:var(--muted)}
.ed-file-search input{width:100%;border:0;outline:0;background:transparent;color:var(--muted);font:inherit;font-size:11px}.ed-file-search input::placeholder{color:var(--muted)}
.ed-document-list{display:grid;gap:3px;padding-top:9px}.ed-document-row{min-height:61px;padding:9px 10px;border:1px solid transparent;border-radius:9px;display:grid;grid-template-columns:minmax(0,1fr) 62px;align-items:center;gap:12px}
.ed-document-row.is-selected{border-color:color-mix(in srgb,var(--primary) 28%,var(--line));background:color-mix(in srgb,var(--primary) 6%,transparent)}
.ed-document-name{min-width:0;display:flex;align-items:center;gap:12px}.ed-document-icon{width:34px;height:34px;border:1px solid var(--line);border-radius:8px;display:grid;place-items:center;flex:0 0 auto;color:var(--muted);font-size:14px}
.ed-document-icon.is-folder{border-color:color-mix(in srgb,var(--primary) 12%,var(--line));background:color-mix(in srgb,var(--primary) 7%,transparent);color:var(--primary)}.ed-document-icon.is-archive{border-color:color-mix(in srgb,var(--primary) 22%,var(--line));color:var(--primary)}
.ed-document-copy{min-width:0;display:grid;gap:4px}.ed-document-copy strong{overflow:hidden;color:var(--text);font-size:12px;font-weight:800;text-overflow:ellipsis;white-space:nowrap}.ed-document-meta{display:flex;align-items:center;flex-wrap:wrap;gap:6px;color:var(--muted);font-size:10px}.ed-document-meta i{font-size:3px}
.ed-document-tail{display:flex;align-items:center;justify-content:flex-end;gap:2px}.ed-document-menu,.ed-folder-enter{width:28px;height:30px;border:0;border-radius:7px;background:transparent;color:var(--muted);display:grid;place-items:center}.ed-document-menu:disabled,.ed-folder-enter:disabled{opacity:1}.ed-folder-enter{font-size:10px;color:var(--text)}
.ed-archive-chip{display:inline-flex;align-items:center;width:max-content;padding:2px 7px;border:1px solid color-mix(in srgb,var(--primary) 22%,var(--line));border-radius:999px;color:var(--primary);font-size:9px;font-weight:800}
.ed-explorer-empty{padding:30px 18px;color:var(--muted);display:grid;justify-items:center;gap:8px;text-align:center}.ed-explorer-empty i{font-size:18px}.ed-explorer-empty strong{color:var(--text);font-size:12px}.ed-explorer-empty p{margin:0;font-size:11px}
@media(max-width:680px){.ed-explorer-simulation{gap:28px}.ed-explorer-toolbar{align-items:stretch;flex-direction:column}.ed-file-search{width:100%}.ed-file-breadcrumb{overflow-x:auto;white-space:nowrap;scrollbar-width:none}.ed-file-breadcrumb::-webkit-scrollbar{display:none}.ed-document-row{grid-template-columns:minmax(0,1fr) 56px;padding:10px 4px}.ed-document-row.is-selected{padding-right:8px;padding-left:8px}}
</style>

<div class="ed-explorer-simulation">
<?php foreach ($documentScenarios as $scenario):
    $folderCount = count(array_filter($scenario['items'], static fn (array $item): bool => $item['kind'] === 'folder'));
    $fileCount = count($scenario['items']) - $folderCount;
    $currentFolder = $scenario['path'][array_key_last($scenario['path'])];
?>
    <section class="ed-scenario" aria-label="<?= e($scenario['label']) ?>">
        <p class="ed-scenario-label"><?= e($scenario['label']) ?></p>
        <div class="ed-explorer">
            <header class="ed-explorer-toolbar">
                <div class="ed-location">
                    <nav class="ed-file-breadcrumb" aria-label="Ruta del explorador documental">
                        <?php foreach ($scenario['path'] as $pathIndex => $pathLevel): ?>
                            <?php if ($pathIndex > 0): ?><i class="fa-solid fa-chevron-right" aria-hidden="true"></i><?php endif; ?>
                            <button type="button" disabled<?= $pathIndex === array_key_last($scenario['path']) ? ' aria-current="page"' : '' ?>><?= e($pathLevel) ?></button>
                        <?php endforeach; ?>
                    </nav>
                    <p class="ed-document-count"><span><?= $folderCount ?> <?= $folderCount === 1 ? 'carpeta' : 'carpetas' ?></span><i class="fa-solid fa-circle" aria-hidden="true"></i><span><?= $fileCount ?> <?= $fileCount === 1 ? 'archivo' : 'archivos' ?></span></p>
                </div>
                <label class="ed-file-search"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><span class="sr-only">Buscar archivos y carpetas</span><input type="search" placeholder="Buscar en <?= e($currentFolder) ?>" readonly aria-disabled="true"></label>
            </header>

            <?php if ($scenario['items'] !== []): ?>
                <div class="ed-document-list" role="list" aria-label="Contenido de <?= e($currentFolder) ?>">
                <?php foreach ($scenario['items'] as $item):
                    $isFolder = $item['kind'] === 'folder';
                    $isArchive = $item['kind'] === 'archive';
                ?>
                    <article class="ed-document-row<?= !empty($item['selected']) ? ' is-selected' : '' ?>" role="listitem"<?= !empty($item['selected']) ? ' aria-current="true"' : '' ?>>
                        <div class="ed-document-name">
                            <span class="ed-document-icon<?= $isFolder ? ' is-folder' : ($isArchive ? ' is-archive' : '') ?>"><i class="fa-solid <?= e($iconForDocument($item)) ?>" aria-hidden="true"></i></span>
                            <span class="ed-document-copy">
                                <strong><?= e($item['name']) ?></strong>
                                <span class="ed-document-meta">
                                    <?php if ($isFolder): ?>
                                        <span><?= e($item['meta']) ?></span>
                                    <?php else: ?>
                                        <span><?= e($item['status']) ?></span><i class="fa-solid fa-circle" aria-hidden="true"></i><span><?= e($item['type']) ?></span><i class="fa-solid fa-circle" aria-hidden="true"></i><span><?= e($item['size']) ?></span>
                                    <?php endif; ?>
                                </span>
                                <?php if ($isArchive): ?><span class="ed-archive-chip"><i class="fa-solid fa-box-archive" aria-hidden="true"></i> Contenido explorable</span><?php endif; ?>
                            </span>
                        </div>
                        <span class="ed-document-tail"><button class="ed-document-menu" type="button" disabled aria-label="Acciones para <?= e($item['name']) ?>"><i class="fa-solid fa-ellipsis" aria-hidden="true"></i></button><?php if ($isFolder): ?><button class="ed-folder-enter" type="button" disabled aria-label="Entrar en <?= e($item['name']) ?>"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button><?php endif; ?></span>
                    </article>
                <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="ed-explorer-empty"><i class="fa-regular fa-folder-open" aria-hidden="true"></i><strong>Carpeta vacía</strong><p>Esta carpeta existe, pero todavía no contiene archivos.</p></div>
            <?php endif; ?>
        </div>
    </section>
<?php endforeach; ?>
</div>
