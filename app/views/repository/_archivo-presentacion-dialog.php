<div class="ed-file-remove-overlay" data-presentation-confirm-dialog hidden>
    <section class="ed-file-remove-dialog ed-presentation-confirm-dialog" role="dialog" aria-modal="true"
        aria-labelledby="presentationConfirmTitle" aria-describedby="presentationConfirmDescription">
        <header>
            <div>
                <h2 id="presentationConfirmTitle" data-presentation-confirm-title>Archivo de presentación</h2>
                <p id="presentationConfirmDescription" data-presentation-confirm-description></p>
            </div>
            <button type="button" data-presentation-confirm-close aria-label="Cerrar confirmación">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </header>
        <div class="ed-file-remove-body ed-presentation-confirm-body">
            <i class="fa-solid fa-display" aria-hidden="true"></i>
            <div data-presentation-establish-details>
                <span>Archivo seleccionado</span>
                <strong data-presentation-new-name></strong>
                <small data-presentation-new-meta></small>
            </div>
            <div class="ed-presentation-change-details" data-presentation-change-details hidden>
                <div><span>Presentación actual</span><strong data-presentation-current-name></strong></div>
                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                <div><span>Nueva presentación</span><strong data-presentation-change-name></strong></div>
            </div>
            <p class="ed-file-remove-error" data-presentation-confirm-error role="alert" hidden></p>
        </div>
        <footer>
            <button type="button" class="ed-file-remove-cancel" data-presentation-confirm-cancel>Cancelar</button>
            <button type="button" class="ed-file-remove-confirm ed-presentation-confirm-submit" data-presentation-confirm-submit>Confirmar</button>
        </footer>
    </section>
</div>
