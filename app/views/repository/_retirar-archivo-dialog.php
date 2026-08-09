<?php $fileEndpoint = (string) ($digitalRecord['file_upload']['endpoint'] ?? ''); ?>
<div class="ed-file-remove-overlay" data-file-remove-dialog<?= !empty($recordIsProject) ? ' data-file-remove-delay="3"' : '' ?> hidden>
    <section class="ed-file-remove-dialog" role="dialog" aria-modal="true" aria-labelledby="fileRemoveTitle" aria-describedby="fileRemoveDescription">
        <header>
            <div><h2 id="fileRemoveTitle" data-file-remove-title>Retirar archivo</h2><p id="fileRemoveDescription" data-file-remove-description>El archivo dejará de estar disponible en el Expediente Digital, pero permanecerá almacenado y podrá restaurarse posteriormente.</p></div>
            <button type="button" data-file-remove-close aria-label="Cerrar confirmación"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </header>
        <div class="ed-file-remove-body">
            <i class="fa-solid fa-box-archive" aria-hidden="true"></i>
            <p>¿Deseas continuar?</p>
            <strong data-file-remove-name></strong>
            <ul class="ed-file-remove-list" data-file-remove-list hidden></ul>
            <p class="ed-file-remove-more" data-file-remove-more hidden></p>
            <p class="ed-file-remove-warning" data-file-remove-presentation-warning hidden></p>
            <p class="ed-dialog-info-note" data-file-remove-history-note>
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                <?php $restoreHours = (new SystemSettingModel())->retentionDays('withdrawn_file_restore_hours'); ?>
                <span><?= !empty($recordIsProject) ? 'El archivo podrá recuperarse durante ' . $restoreHours . ' horas. El retiro quedará registrado en el historial y los autores activos recibirán una notificación.' : 'El retiro de este archivo quedará registrado en el historial del expediente y podrá reflejarse en los reportes administrativos.' ?></span>
            </p>
            <p class="ed-file-remove-error" data-file-remove-error role="alert" hidden></p>
        </div>
        <footer>
            <button type="button" class="ed-file-remove-cancel" data-file-remove-cancel>Cancelar</button>
            <button type="button" class="ed-file-remove-confirm" data-file-remove-confirm aria-live="polite">Retirar archivo</button>
        </footer>
        <div data-file-remove-config data-endpoint="<?= e($fileEndpoint) ?>" data-csrf="<?= e((string) ($digitalRecord['file_upload']['csrf_token'] ?? '')) ?>" data-context="<?= e((string) ($digitalRecord['file_upload']['context'] ?? '')) ?>" data-material-id="<?= (int) ($digitalRecord['entity']['id'] ?? 0) ?>" data-restore-hours="<?= (int)(new SystemSettingModel())->retentionDays('withdrawn_file_restore_hours') ?>" hidden></div>
    </section>
</div>
