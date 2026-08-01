<?php /** Visor neutral de consulta; el contenido se obtiene siempre mediante endpoints protegidos. */ ?>
<section class="ed-files-panel ed-viewer viewer-normal" aria-labelledby="recordViewerTitle" data-record-viewer>
    <header class="ed-viewer-head">
        <button class="ed-back-to-files" type="button" data-back-to-files><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Volver al listado</button>
        <div><strong id="recordViewerTitle" data-viewer-name><?= e((string) ($selectedDocument['name'] ?? 'Archivo')) ?></strong><span data-viewer-meta><?= e((string) ($selectedDocument['type'] ?? 'Archivo')) ?> · <?= e((string) ($selectedDocument['size'] ?? '')) ?></span></div>
        <div class="ed-viewer-actions">
            <div class="ed-viewer-zoom" data-viewer-zoom-controls aria-label="Zoom del documento" hidden>
                <button type="button" data-viewer-zoom-out aria-label="Alejar documento" disabled><i class="fa-solid fa-minus" aria-hidden="true"></i></button>
                <button type="button" data-viewer-zoom-reset aria-label="Restablecer zoom"><span data-viewer-zoom-value>100 %</span></button>
                <button type="button" data-viewer-zoom-in aria-label="Acercar documento" disabled><i class="fa-solid fa-plus" aria-hidden="true"></i></button>
                <span class="sr-only" data-viewer-zoom-status aria-live="polite"></span>
            </div>
            <button class="ed-viewer-expand" type="button" data-viewer-expand aria-label="Expandir visor"><i class="fa-solid fa-expand" aria-hidden="true"></i><span>Expandir visor</span></button>
            <a class="ed-viewer-download" data-record-download data-viewer-download download href="<?= e((string) ($selectedDocument['download_url'] ?? '')) ?>" aria-label="Descargar <?= e((string) ($selectedDocument['name'] ?? 'archivo')) ?>"<?= empty($selectedDocument['download_url']) ? ' hidden' : '' ?>><i class="fa-solid fa-download" aria-hidden="true"></i><span data-viewer-download-label>Descargar archivo</span></a>
        </div>
    </header>
    <div class="ed-viewer-body" data-viewer-body aria-live="polite">
        <div class="ed-dialog-info-note ed-viewer-docx-note" data-viewer-docx-note role="note" hidden>
            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
            <span>La vista previa puede presentar diferencias en el formato, la posición de algunos elementos y la separación entre páginas respecto al documento original.</span>
        </div>
        <div class="ed-docx-top-scroll" data-viewer-docx-top-scroll aria-label="Desplazamiento horizontal del documento DOCX" tabindex="0" hidden>
            <div data-viewer-docx-top-scroll-track></div>
        </div>
        <div class="ed-viewer-state" data-viewer-state role="status">
            <div><i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i><h3>Preparando vista previa</h3><p>Estamos cargando el archivo seleccionado.</p></div>
        </div>
        <iframe class="ed-preview-frame" data-viewer-pdf title="Vista previa del documento PDF" hidden></iframe>
        <div class="ed-preview-image-shell" data-viewer-image-shell hidden><img class="ed-preview-image" data-viewer-image alt=""></div>
        <pre class="ed-preview-text" data-viewer-text tabindex="0" hidden></pre>
        <article
            class="ed-preview-docx"
            data-viewer-docx
            data-jszip-script="<?= e(asset('vendor/jszip/3.10.1/jszip.min.js')) ?>"
            data-docx-preview-script="<?= e(asset('vendor/docx-preview/0.4.0/docx-preview.min.js')) ?>"
            tabindex="0"
            hidden
        ></article>
    </div>
</section>
<div class="ed-viewer-overlay" data-record-viewer-overlay hidden>
    <section class="ed-viewer-expanded" data-record-viewer-expanded role="dialog" aria-modal="true" aria-labelledby="recordExpandedViewerTitle" aria-describedby="recordExpandedViewerDescription">
        <header>
            <div>
                <strong id="recordExpandedViewerTitle" data-expanded-viewer-name>Visor ampliado</strong>
                <span id="recordExpandedViewerDescription" data-expanded-viewer-meta>Consulta del archivo seleccionado</span>
            </div>
            <div class="ed-viewer-expanded-actions">
                <div class="ed-viewer-zoom" data-viewer-zoom-controls aria-label="Zoom del documento" hidden>
                    <button type="button" data-viewer-zoom-out aria-label="Alejar documento" disabled><i class="fa-solid fa-minus" aria-hidden="true"></i></button>
                    <button type="button" data-viewer-zoom-reset aria-label="Restablecer zoom"><span data-viewer-zoom-value>100 %</span></button>
                    <button type="button" data-viewer-zoom-in aria-label="Acercar documento" disabled><i class="fa-solid fa-plus" aria-hidden="true"></i></button>
                    <span class="sr-only" data-viewer-zoom-status aria-live="polite"></span>
                </div>
                <a class="ed-viewer-download" data-record-download data-expanded-viewer-download download href="" aria-label="Descargar archivo" hidden><i class="fa-solid fa-download" aria-hidden="true"></i><span data-expanded-download-label>Descargar archivo</span></a>
                <button class="ed-viewer-expanded-close" type="button" data-expanded-viewer-close aria-label="Cerrar visor ampliado"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            </div>
        </header>
        <div class="ed-viewer-expanded-body" data-expanded-viewer-body></div>
    </section>
</div>
