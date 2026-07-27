<?php /** Visor neutral; en Fase 1A no solicita contenido real. */ ?>
<section class="ed-files-panel ed-viewer" aria-labelledby="recordViewerTitle" data-record-viewer>
    <header class="ed-viewer-head">
        <button class="ed-back-to-files" type="button" data-back-to-files><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Volver al listado</button>
        <div><strong id="recordViewerTitle" data-viewer-name><?= e((string) ($selectedDocument['name'] ?? 'Archivo')) ?></strong><span data-viewer-meta><?= e((string) ($selectedDocument['type'] ?? 'Archivo')) ?> · <?= e((string) ($selectedDocument['size'] ?? '')) ?></span></div>
    </header>
    <div class="ed-viewer-body" tabindex="-1" data-viewer-body>
        <div><i class="fa-regular fa-file-lines" aria-hidden="true"></i><h3>Vista documental</h3><p>Selecciona un archivo para consultar sus datos. La vista previa real se conectará en una fase posterior.</p></div>
    </div>
</section>
