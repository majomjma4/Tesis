<div class="project-description-reminder" data-project-description-reminder role="dialog" aria-modal="true" aria-labelledby="projectDescriptionTitle" aria-describedby="projectDescriptionText" data-endpoint="<?= e($descriptionSaveEndpoint) ?>">
    <form class="project-description-dialog" data-project-description-form novalidate>
        <input type="hidden" name="_csrf" value="<?= e($descriptionCsrf) ?>">
        <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
        <div class="project-description-icon" aria-hidden="true"><i class="fa-solid fa-align-left"></i></div>
        <h2 id="projectDescriptionTitle">Descripción del proyecto</h2>
        <p id="projectDescriptionText">Tu proyecto aún no cuenta con una descripción pública.</p>
        <p>Puedes escribir una breve descripción que permita presentar tu trabajo cuando sea publicado en el Repositorio institucional.</p>
        <p>Esta acción es completamente opcional y podrás modificarla más adelante.</p>
        <label for="projectDescriptionField">Descripción pública</label>
        <textarea id="projectDescriptionField" name="description" rows="6" maxlength="4000" placeholder="Escribe una breve descripción de tu proyecto"></textarea>
        <p class="project-description-error" data-project-description-error role="alert" hidden></p>
        <div class="project-description-actions">
            <button type="button" class="project-description-skip" data-project-description-skip>Omitir por ahora</button>
            <button type="submit" class="project-description-save">Guardar descripción</button>
        </div>
    </form>
</div>
