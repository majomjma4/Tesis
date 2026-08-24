<?php if (!empty($canAddRepositoryContent)): ?>
<div class="teacher-repository-content-modal" data-repository-content-selector hidden role="dialog" aria-modal="true" aria-labelledby="repositoryContentSelectorTitle">
    <div class="teacher-repository-content-modal__card">
        <button type="button" class="teacher-repository-content-modal__close" data-repository-content-close aria-label="Cerrar">&times;</button>
        <span class="ar-eyebrow">Repositorio institucional</span>
        <h2 id="repositoryContentSelectorTitle">¿Qué deseas agregar?</h2>
        <p class="teacher-repository-content-modal__intro">Selecciona el tipo de contenido que deseas incorporar al repositorio.</p>
        <div class="teacher-repository-content-modal__options">
            <?php if (!empty($canPublishDirectProject)): ?>
                <button type="button" class="teacher-repository-content-option" data-repository-content-project>
                    <i class="fa-solid fa-diagram-project" aria-hidden="true"></i>
                    <span><strong>Proyecto académico</strong><small>Publica directamente un proyecto académico en el repositorio institucional.</small></span>
                </button>
            <?php endif; ?>
            <?php if (!empty($canCreateSupportMaterial)): ?>
                <button type="button" class="teacher-repository-content-option" data-repository-content-material>
                    <i class="fa-solid fa-folder-open" aria-hidden="true"></i>
                    <span><strong>Material de apoyo</strong><small>Agrega guías, formatos, instructivos y otros recursos académicos.</small></span>
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>
