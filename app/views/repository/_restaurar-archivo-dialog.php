<?php $initialRestorableFiles = is_array($digitalRecord['restorable_files'] ?? null) ? $digitalRecord['restorable_files'] : []; ?>
<div class="ed-file-remove-overlay" data-file-restore-dialog hidden>
    <section class="ed-file-remove-dialog ed-file-restore-dialog" role="dialog" aria-modal="true"
        aria-labelledby="fileRestoreTitle">
        <header>
            <div>
                <h2 id="fileRestoreTitle">Archivos retirados recientemente</h2>
            </div>
            <button type="button" data-file-restore-close aria-label="Cerrar archivos retirados">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </header>
        <div class="ed-file-restore-body">
            <p class="ed-file-restore-notice" data-file-restore-notice>
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                <?php $restoreHours = (new SystemSettingModel())->retentionDays('withdrawn_file_restore_hours'); ?>
                <span><strong>Restauración disponible durante <?= $restoreHours ?> horas.</strong> Después de <?= $restoreHours ?> horas el archivo será eliminado definitivamente. Solo permanecerá la evidencia de su existencia en el historial y la auditoría.</span>
            </p>
            <div class="ed-file-restore-list" data-file-restore-list></div>
            <p class="ed-file-restore-empty" data-file-restore-empty hidden>No existen archivos disponibles para restaurar.</p>
            <section class="ed-file-restore-confirmation" data-file-restore-confirmation hidden>
                <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                <h3 data-file-restore-confirm-title>Restaurar archivo</h3>
                <p data-file-restore-confirm-message></p>
                <dl>
                    <div><dt>Archivo retirado</dt><dd data-file-restore-original></dd></div>
                    <div data-file-restore-conflict-row hidden><dt>Archivo activo en conflicto</dt><dd data-file-restore-conflict></dd></div>
                    <div data-file-restore-final-row hidden><dt>Nombre final</dt><dd data-file-restore-final></dd></div>
                </dl>
                <ul class="ed-file-purge-summary" data-file-purge-summary hidden></ul>
                <p class="ed-file-remove-error" data-file-restore-error role="alert" hidden></p>
            </section>
        </div>
        <footer>
            <button type="button" class="ed-file-remove-cancel" data-file-restore-back hidden>Volver</button>
            <button type="button" class="ed-file-purge-open" data-file-purge-open disabled>Eliminar seleccionados</button>
            <button type="button" class="ed-file-remove-cancel" data-file-restore-cancel>Cerrar</button>
            <button type="button" class="ed-file-remove-confirm ed-presentation-confirm-submit" data-file-restore-confirm hidden>Restaurar archivo</button>
        </footer>
    </section>
</div>
<script type="application/json" data-file-restore-initial><?= json_encode($initialRestorableFiles, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
