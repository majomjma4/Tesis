<?php
$upload = is_array($digitalRecord['file_upload'] ?? null) ? $digitalRecord['file_upload'] : [];
$limits = is_array($upload['limits'] ?? null) ? $upload['limits'] : [];
$extensions = is_array($limits['extensions'] ?? null) ? $limits['extensions'] : [];
?>
<div class="ed-upload-overlay" data-record-file-upload-dialog hidden>
    <section class="ed-upload-dialog" role="dialog" aria-modal="true" aria-labelledby="recordUploadTitle" aria-describedby="recordUploadDescription">
        <header class="ed-upload-dialog__header">
            <div>
                <h2 id="recordUploadTitle">Agregar archivos</h2>
                <p id="recordUploadDescription">Selecciona hasta <?= (int) ($limits['max_operation_files'] ?? 5) ?> archivos. Formatos permitidos: <?= e(strtoupper(implode(', ', $extensions))) ?>. Máximo <?= (int) ($limits['max_file_mb'] ?? 25) ?> MB por archivo y 35 MB por operación.</p>
            </div>
            <button class="ed-upload-close" type="button" data-upload-close aria-label="Cerrar carga de archivos"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </header>
        <form data-record-file-upload-form action="<?= e((string) ($upload['endpoint'] ?? '')) ?>" method="post" enctype="multipart/form-data"
            data-max-files="<?= (int) ($limits['max_operation_files'] ?? 5) ?>" data-max-bytes="<?= (int) ($limits['max_file_bytes'] ?? 26214400) ?>"
            data-max-operation-bytes="<?= (int) ($limits['max_operation_bytes'] ?? 36700160) ?>"
            data-max-name="<?= (int) ($limits['max_name_length'] ?? 200) ?>" data-extensions="<?= e(implode(',', $extensions)) ?>">
            <div class="ed-upload-dialog__body">
                <input type="hidden" name="_csrf" value="<?= e((string) ($upload['csrf_token'] ?? '')) ?>">
                <input type="hidden" name="material_id" value="<?= (int) ($digitalRecord['entity']['id'] ?? 0) ?>">
                <input type="hidden" name="action" value="add">
                <label class="ed-upload-picker" for="recordUploadInput">
                    <strong>Seleccionar uno o varios archivos</strong>
                    <span>Los archivos válidos se agregarán como documentos normales; el archivo de presentación solo cambia cuando lo indiques desde su menú.</span>
                    <input id="recordUploadInput" type="file" multiple data-upload-input accept="<?= e('.' . implode(',.', $extensions)) ?>">
                </label>
                <p class="ed-dialog-info-note">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    <span>Los archivos agregados quedarán registrados en el historial del expediente y podrán reflejarse en los reportes administrativos.</span>
                </p>
                <div class="ed-upload-list" data-upload-file-list></div>
                <p class="ed-upload-status" data-upload-status role="status" aria-live="polite"></p>
                <ul class="ed-upload-results" data-upload-results aria-label="Resultado de la carga"></ul>
            </div>
            <footer class="ed-upload-dialog__footer">
                <button class="ed-upload-cancel" type="button" data-upload-cancel>Cancelar</button>
                <button class="ed-upload-submit" type="submit" data-upload-submit disabled><span data-upload-submit-label>Agregar archivos</span></button>
            </footer>
        </form>
    </section>
</div>
