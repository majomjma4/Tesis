<?php if (!empty($canPublishDirectProject)): ?>
<div class="teacher-repository-content-modal teacher-direct-project-modal" data-direct-project-modal hidden role="dialog" aria-modal="true" aria-labelledby="directProjectTitle">
    <div class="teacher-direct-project-modal__card">
        <div class="teacher-direct-project-modal__header">
            <div><span class="ar-eyebrow">Repositorio institucional</span><h2 id="directProjectTitle">Proyecto académico</h2></div>
            <button type="button" class="teacher-repository-content-modal__close" data-direct-project-close aria-label="Cerrar">&times;</button>
        </div>
        <section class="teacher-content-draft-resume" data-direct-draft-resume hidden aria-labelledby="directProjectDraftResumeTitle">
            <span class="ar-eyebrow">Borrador guardado</span>
            <h3 id="directProjectDraftResumeTitle">Tienes un borrador guardado</h3>
            <p>Encontramos información que dejaste sin terminar. ¿Deseas continuar desde donde lo dejaste?</p>
            <div class="teacher-content-draft-resume__actions">
                <button type="button" class="ar-secondary-action" data-direct-draft-start-new>Empezar de nuevo</button>
                <button type="button" class="ar-primary-action" data-direct-draft-continue>Recuperar borrador</button>
            </div>
        </section>
        <form data-direct-project-form data-draft-user-id="<?= (int) ((new AuthSessionService())->userId() ?? 0) ?>" data-endpoint="<?= e((string) $directProjectEndpoint) ?>" data-search-endpoint="<?= e((string) $directProjectSearchEndpoint) ?>" data-csrf="<?= e((string) $directProjectCsrf) ?>" novalidate>
            <nav class="teacher-direct-project-stepper" aria-label="Pasos del formulario">
                <button type="button" data-direct-step-indicator="1" aria-current="step"><span>1</span><b>Información</b></button>
                <button type="button" data-direct-step-indicator="2"><span>2</span><b>Participantes</b></button>
                <button type="button" data-direct-step-indicator="3" disabled><span>3</span><b>Archivos</b></button>
            </nav>
            <div class="teacher-direct-project-modal__body">
                <section class="teacher-direct-project-step is-active" data-direct-step-panel="1" aria-labelledby="directProjectStep1Title">
                    <p class="teacher-direct-project-step__kicker">Paso 1 de 3</p><h3 id="directProjectStep1Title" tabindex="-1">Información general</h3><p class="teacher-direct-project-step__help">Para proyectos de Prácticas preprofesionales o Vinculación, selecciona primero el tipo de proyecto. El título y la descripción se completarán automáticamente.</p>
                    <label>Título <input name="title" placeholder="Escribe el título del proyecto" required minlength="5" maxlength="240" autocomplete="off"><span data-direct-error-for="title"></span></label>
                    <div class="teacher-direct-project-grid teacher-direct-project-grid--two">
                        <label>Tipo de proyecto <select name="project_type_id" required><option value="">Selecciona un tipo</option><?php foreach ((array) ($directProjectTypes ?? []) as $type): ?><option value="<?= (int) $type['id'] ?>" data-default-title="<?= e((string) ($type['default_title'] ?? '')) ?>" data-registration-description="<?= e((string) ($type['registration_description'] ?? '')) ?>"><?= e((string) ($type['name'] ?? $type['code'] ?? '')) ?></option><?php endforeach; ?></select><span data-direct-error-for="project_type_id"></span></label>
                        <label>Periodo Académico <input value="<?= e((string) (($directProjectPeriod['name'] ?? $directProjectPeriod['code'] ?? 'No disponible'))) ?>" readonly aria-readonly="true"><small>El período se determina institucionalmente.</small></label>
                    </div>
                    <label class="teacher-direct-project-description">Descripción / resumen <textarea name="description" required minlength="30" maxlength="10000" rows="4" placeholder="Describe el propósito, alcance y aporte académico del proyecto..."></textarea><span data-direct-error-for="description"></span></label>
                </section>
                <section class="teacher-direct-project-step" data-direct-step-panel="2" aria-labelledby="directProjectStep2Title" hidden>
                    <p class="teacher-direct-project-step__kicker">Paso 2 de 3</p><h3 id="directProjectStep2Title" tabindex="-1">Participantes</h3>
                    <fieldset class="teacher-direct-project-picker"><legend>Autores</legend><input type="search" data-direct-people-search="students" placeholder="Buscar estudiantes por nombre o código" autocomplete="off"><div class="teacher-direct-project-results" data-direct-results="students" hidden></div><div class="teacher-direct-project-chips" data-direct-selected-authors></div><span data-direct-error-for="author_ids"></span></fieldset>
                    <fieldset class="teacher-direct-project-picker"><legend>Tutores</legend><input type="search" data-direct-people-search="tutors" placeholder="Buscar tutor por nombre o código" autocomplete="off"><div class="teacher-direct-project-results" data-direct-results="tutors" hidden></div><div class="teacher-direct-project-chips" data-direct-selected-tutors></div><span data-direct-error-for="tutoring_user_ids"></span><span data-direct-error-for="tutoring_primary_id"></span></fieldset>
                    <?php if (!empty($directProjectKeywords)): ?><fieldset class="teacher-direct-project-keyword-field"><legend>Etiquetas <small>(opcional)</small></legend><div class="teacher-direct-project-keyword-selector" data-direct-keyword-selector><button type="button" class="teacher-direct-project-keyword-trigger" role="combobox" aria-haspopup="listbox" aria-expanded="false" aria-controls="directKeywordPanel" data-direct-keyword-trigger><span data-direct-keyword-summary>Selecciona etiquetas de clasificación</span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></button><div class="teacher-direct-project-keyword-panel" id="directKeywordPanel" hidden data-direct-keyword-panel><label class="teacher-direct-project-keyword-search"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><span class="sr-only">Buscar etiquetas de clasificación</span><input type="search" placeholder="Buscar etiquetas..." autocomplete="off" data-direct-keyword-search></label><div class="teacher-direct-project-keyword-options" role="listbox" aria-multiselectable="true" data-direct-keyword-options><?php foreach ((array) $directProjectKeywords as $keyword): ?><label role="option" aria-selected="false" data-keyword-search="<?= e(mb_strtolower((string) $keyword['name'], 'UTF-8')) ?>"><input type="checkbox" name="keyword_ids[]" value="<?= (int) $keyword['id'] ?>"><span><?= e((string) $keyword['name']) ?></span></label><?php endforeach; ?></div></div></div><span data-direct-error-for="keyword_ids"></span></fieldset><?php endif; ?>
                </section>
                <section class="teacher-direct-project-step" data-direct-step-panel="3" aria-labelledby="directProjectStep3Title" hidden>
                    <p class="teacher-direct-project-step__kicker">Paso 3 de 3</p><h3 id="directProjectStep3Title" tabindex="-1">Archivos y publicación</h3>
                    <fieldset class="teacher-direct-project-picker"><legend>Archivos</legend><div class="teacher-direct-project-dropzone" data-direct-dropzone><input id="directProjectFiles" type="file" data-direct-files multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip"><label for="directProjectFiles"><i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i><strong>Selecciona o arrastra archivos aquí</strong><small>Se validarán tamaño y formato al publicar.</small></label></div><ul class="teacher-direct-project-file-list" data-direct-file-list></ul><span data-direct-error-for="files"></span></fieldset>
                    <p class="teacher-direct-project-draft-notice" data-direct-draft-notice hidden role="status"></p>
                    <div class="teacher-direct-project-summary" data-direct-project-summary aria-live="polite"></div>
                </section>
                <div class="teacher-direct-project-errors" data-direct-project-error hidden role="alert"></div>
            </div>
            <footer class="teacher-direct-project-modal__footer"><button type="button" class="ar-secondary-action" data-direct-project-close>Cancelar</button><button type="button" class="ar-secondary-action" data-direct-project-previous hidden>Anterior</button><button type="button" class="ar-primary-action" data-direct-project-next>Siguiente</button><button type="submit" class="ar-primary-action" data-direct-project-submit hidden>Publicar en repositorio</button></footer>
        </form>
    </div>
</div>
<?php endif; ?>
