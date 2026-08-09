<?php
$replaceUpload = is_array($digitalRecord['file_upload'] ?? null) ? $digitalRecord['file_upload'] : [];
$replaceLimits = is_array($replaceUpload['limits'] ?? null) ? $replaceUpload['limits'] : [];
$replaceExtensions = is_array($replaceLimits['extensions'] ?? null) ? $replaceLimits['extensions'] : [];
?>
<input type="file" data-file-replace-input hidden
    accept="<?= e('.' . implode(',.', $replaceExtensions)) ?>">
<div class="ed-file-remove-overlay" data-file-replace-dialog
    data-max-bytes="<?= (int) ($replaceLimits['max_file_bytes'] ?? 20971520) ?>" data-max-mb="<?= (int) ($replaceLimits['max_file_mb'] ?? 20) ?>"
    data-max-name="<?= (int) ($replaceLimits['max_name_length'] ?? 200) ?>"
    data-extensions="<?= e(implode(',', $replaceExtensions)) ?>" hidden>
    <section class="ed-file-remove-dialog ed-file-replace-dialog" role="dialog" aria-modal="true"
        aria-labelledby="fileReplaceTitle" aria-describedby="fileReplaceDescription">
        <header>
            <div>
                <h2 id="fileReplaceTitle">Reemplazar archivo</h2>
                <p id="fileReplaceDescription"><?= !empty($recordIsProject) ? 'El documento activo del expediente será sustituido. La versión anterior permanecerá almacenada en el historial documental y el reemplazo conservará toda su trazabilidad.' : 'El archivo actual dejará de estar disponible para uso normal y permanecerá registrado en el historial de auditoría, conservando la trazabilidad del reemplazo.' ?></p>
            </div>
            <button type="button" data-file-replace-close aria-label="Cerrar confirmación">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </header>
        <div class="ed-file-remove-body ed-file-replace-body">
            <i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i>
            <div class="ed-presentation-change-details">
                <div><span>Archivo actual</span><strong data-file-replace-current></strong></div>
                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                <div><span>Archivo nuevo</span><strong data-file-replace-new></strong><small data-file-replace-meta></small></div>
            </div>
            <?php if (!empty($recordIsProject)): ?><p class="ed-dialog-info-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><span>La acción quedará registrada en la auditoría y el historial administrativo. Los autores activos del proyecto recibirán una notificación automática.</span></p><?php endif; ?>
            <p class="ed-file-remove-error" data-file-replace-error role="alert" hidden></p>
        </div>
        <footer>
            <button type="button" class="ed-file-remove-cancel" data-file-replace-cancel>Cancelar</button>
            <button type="button" class="ed-file-remove-confirm ed-presentation-confirm-submit" data-file-replace-confirm>Reemplazar archivo</button>
        </footer>
    </section>
</div>
