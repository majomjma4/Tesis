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

            <label class="teacher-material-field is-fullwidth is-readonly">
                <span>Responsable</span>
                <input readonly value="<?= e((new AuthSessionService())->name()) ?>">
            </label>

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
