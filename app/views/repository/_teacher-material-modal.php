<?php if (!empty($canCreateSupportMaterial)): ?>
<div class="ed-edit-modal teacher-material-modal" hidden data-teacher-material-modal>
    <section class="ed-edit-modal-card" role="dialog" aria-modal="true" aria-labelledby="teacherMaterialTitle">
        <header class="ed-edit-modal-header">
            <div class="ed-edit-modal-heading">
                <h2 id="teacherMaterialTitle">Nuevo material</h2>
                <p>Completa la información para crear el material.</p>
            </div>
            <button type="button" class="teacher-material-close" data-teacher-material-close aria-label="Cerrar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </header>
        <form class="ed-edit-modal-body" data-teacher-material-form
              data-endpoint="<?= e((string) $supportMaterialManageSaveEndpoint) ?>"
              data-file-endpoint="<?= e((string) $supportMaterialManageFileEndpoint) ?>"
              data-csrf="<?= e((string) $supportMaterialCsrf) ?>">
            <input type="hidden" name="controlled_keywords" value="1">

            <label class="teacher-material-field is-fullwidth">
                <span>Título <span class="field-required" aria-hidden="true">*</span></span>
                <input required maxlength="220" name="title" data-title placeholder="Ej. Guía práctica de desarrollo web">
            </label>

            <div class="teacher-material-row">
                <label class="teacher-material-field">
                    <span>Tipo de material <span class="field-required" aria-hidden="true">*</span></span>
                    <select required name="material_type">
                        <option value="">Selecciona un tipo</option>
                        <?php foreach ((array) $supportMaterialTypes as $type): ?>
                            <option value="<?= e((string)$type) ?>"><?= e((string)$type) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="teacher-material-field">
                    <span>Categoría <span class="field-required" aria-hidden="true">*</span></span>
                    <select required name="category_id">
                        <option value="">Selecciona una categoría</option>
                        <?php foreach ((array) $supportMaterialCategories as $category): ?>
                            <option value="<?= (int)$category['id'] ?>"><?= e((string)$category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <div class="teacher-material-row">
                <label class="teacher-material-field is-readonly">
                    <span>Responsable</span>
                    <input readonly value="<?= e((new AuthSessionService())->name()) ?>">
                </label>

                <div class="teacher-material-field">
                    <span id="teacherMaterialKeywordsLabel">Etiquetas <small>(opcional)</small></span>
                    <div class="ed-keyword-selector" data-teacher-material-keyword-selector>
                        <button class="ed-keyword-trigger" type="button" role="combobox" aria-haspopup="listbox" aria-expanded="false" aria-controls="teacherMaterialKeywordPanel" aria-labelledby="teacherMaterialKeywordsLabel teacherMaterialKeywordSummary" data-teacher-material-keyword-trigger>
                            <span id="teacherMaterialKeywordSummary" data-teacher-material-keyword-summary>Selecciona etiquetas de clasificación</span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                        </button>
                        <div class="ed-keyword-panel" id="teacherMaterialKeywordPanel" hidden data-teacher-material-keyword-panel>
                            <label class="ed-keyword-search"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><span class="ed-sr-only">Buscar etiquetas de clasificación</span><input type="search" placeholder="Buscar etiquetas..." autocomplete="off" data-teacher-material-keyword-search></label>
                            <div class="ed-keyword-options" role="listbox" aria-multiselectable="true" data-teacher-material-keyword-options>
                                <?php foreach ((array) ($supportMaterialKeywords ?? []) as $keyword): ?>
                                    <label class="ed-keyword-option" role="option" aria-selected="false" data-keyword-search="<?= e(mb_strtolower((string) $keyword, 'UTF-8')) ?>"><input type="checkbox" name="keywords_selected[]" value="<?= e((string) $keyword) ?>"><span><?= e((string) $keyword) ?></span></label>
                                <?php endforeach; ?>
                            </div>
                            <p class="ed-keyword-limit" role="status" aria-live="polite" hidden data-teacher-material-keyword-limit>Máximo 4 etiquetas de clasificación.</p>
                        </div>
                    </div>
                    <div class="ed-keyword-chips" aria-live="polite" data-teacher-material-keyword-chips></div>
                    <small class="ed-field-help">Máximo 4 etiquetas de clasificación.</small>
                </div>
            </div>

            <label class="teacher-material-field is-fullwidth">
                <span>Descripción <span class="field-required" aria-hidden="true">*</span></span>
                <textarea required name="description" placeholder="Escribe una breve descripción del material..."></textarea>
            </label>

            <div class="teacher-material-field is-fullwidth">
                <span>Archivo(s)</span>
                <label for="teacherMaterialFiles" class="teacher-material-dropzone" data-teacher-dropzone>
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    <strong>Arrastra archivos aquí</strong>
                    <span>o haz clic para seleccionarlos</span>
                    <small>PDF, DOCX, XLSX, imágenes, TXT o ZIP</small>
                    <input id="teacherMaterialFiles" class="teacher-material-file-input" type="file" multiple
                           accept=".pdf,.docx,.xlsx,.pptx,.png,.jpg,.jpeg,.webp,.txt,.zip" data-files>
                </label>
            </div>

            <ul class="teacher-material-selected-list" data-file-list></ul>

            <p class="teacher-material-error-message" data-teacher-material-error hidden></p>

            <div class="teacher-material-footer">
                <button class="teacher-material-btn teacher-material-btn-secondary" type="button" data-teacher-material-close>Cancelar</button>
                <button class="teacher-material-btn teacher-material-btn-primary" type="submit">Crear material</button>
            </div>
        </form>
    </section>
</div>
<?php endif; ?>
