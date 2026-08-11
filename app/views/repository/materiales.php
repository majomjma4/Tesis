<?php
$materialsList = is_array($materials ?? null)
    ? $materials
    : (($materials ?? null) instanceof SupportMaterialModel ? $materials->getAll() : []);
?>
<div class="ar-page" id="supportMaterialsPage">
    <header class="ar-head">
        <div>
            <span class="ar-eyebrow">Material de apoyo</span>
            <h1>Guías y documentos de apoyo</h1>
            <p>Consulta formatos, reglamentos, plantillas y recursos académicos.</p>
        </div>
    </header>

    <?php if (!empty($canCreateSupportMaterial)): ?>
        <div class="ar-tools" style="justify-content:flex-end;margin-bottom:14px">
            <button class="ar-primary-action" type="button" data-teacher-material-create><i class="fa-solid fa-plus"></i> Nuevo material</button>
        </div>
    <?php endif; ?>

    <main class="ar-catalog">
        <div class="ar-tools">
            <label class="ar-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input id="supportMaterialsSearch" type="search" placeholder="Buscar por título, tipo, PAO o palabra clave..." autocomplete="off">
            </label>
            <label class="ar-filter-control">
                <span>Categoría</span>
                <select id="supportMaterialsCategory">
                    <option value="all">Todas</option>
                    <option value="vinculacion">Vinculación</option>
                    <option value="practicas">Prácticas</option>
                    <option value="tesis">Tesis</option>
                    <option value="proyecto-pis">Proyectos PIS</option>
                </select>
            </label>
        </div>

        <section class="ar-panel">
            <header class="ar-section-head">
                <div><span>Recursos académicos</span><h2>Catálogo completo</h2></div>
                <p id="supportMaterialsCount" aria-live="polite"><?= count($materialsList) ?> <?= count($materialsList) === 1 ? 'resultado visible' : 'resultados visibles' ?></p>
            </header>

            <div class="ar-grid" id="supportMaterialsGrid">
                <?php foreach ($materialsList as $material): ?>
                    <article class="ar-material-card"
                        data-support-material
                        data-category-slug="<?= e($material['category_slug'] ?? '') ?>"
                        data-category="<?= e($material['category_slug'] ?? '') ?>"
                        data-search="<?= e(mb_strtolower(implode(' ', [$material['title'] ?? '', $material['description'] ?? '', $material['type'] ?? '', $material['pao_label'] ?? '', $material['year'] ?? '', implode(' ', (array) ($material['keywords'] ?? []))]), 'UTF-8')) ?>"
                    >
                        <header>
                            <span class="ar-material-icon"><i class="fa-regular fa-file-lines"></i></span>
                            <div><span><?= e($material['type'] ?? '') ?></span><strong><?= e($material['category_label'] ?? '') ?></strong></div>
                            <span class="ar-available">Disponible</span>
                        </header>
                        <div class="ar-card-copy">
                            <h3 title="<?= e($material['title'] ?? '') ?>"><?= e($material['title'] ?? '') ?></h3>
                            <p><?= e($material['description'] ?? '') ?></p>
                        </div>
                        <div class="ar-card-meta">
                            <span><i class="fa-regular fa-calendar"></i> <?= e(!empty($material['publication_date']) ? $material['publication_date'] : ($material['year'] ?? '')) ?></span>
                            <span><i class="fa-solid fa-download"></i> <?= number_format((int) ($material['downloads'] ?? 0), 0, ',', '.') ?> descargas</span>
                        </div>
                        <footer>
                            <a class="ar-primary-action" href="<?= e($material['detail_url'] ?? '#') ?>"><i class="fa-regular fa-eye"></i> Ver detalle</a>
                        </footer>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="ar-empty" id="supportMaterialsEmpty" <?= $materialsList ? 'hidden' : '' ?>>
                <span><i class="fa-solid fa-folder-open"></i></span>
                <h2>Aún no existen materiales de apoyo.</h2>
                <p>Los recursos institucionales publicados aparecerán en esta sección.</p>
            </div>
        </section>
    </main>
</div>
<?php if (!empty($canCreateSupportMaterial)): ?>
<style>
#supportMaterialsPage~.ed-edit-modal{position:fixed;z-index:2100;inset:0;background:rgba(15,23,42,.62);display:grid;place-items:center;padding:12px}#supportMaterialsPage~.ed-edit-modal[hidden]{display:none}#supportMaterialsPage~.ed-edit-modal-card{width:min(620px,100%);max-height:calc(100dvh - 24px);overflow:auto;border:1px solid var(--line);border-radius:18px;background:var(--surface);box-shadow:0 30px 80px rgba(15,23,42,.34)}#supportMaterialsPage~.ed-edit-modal-header{padding:18px 20px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;gap:14px}#supportMaterialsPage~.ed-edit-modal-heading h2{margin:0}#supportMaterialsPage~.ed-edit-modal-heading p{margin:5px 0 0;color:var(--muted);font-size:12px}#supportMaterialsPage~.ed-edit-modal-body{display:grid;gap:12px;padding:20px}#supportMaterialsPage~.ed-edit-modal-body label{display:grid;gap:6px;color:var(--text);font-size:12px;font-weight:700}#supportMaterialsPage~.ed-edit-modal-body input,#supportMaterialsPage~.ed-edit-modal-body select,#supportMaterialsPage~.ed-edit-modal-body textarea{width:100%;box-sizing:border-box;padding:10px;border:1px solid var(--line);border-radius:9px;background:var(--surface);color:var(--text);font:inherit}#supportMaterialsPage~.ed-edit-modal-body textarea{min-height:78px;resize:vertical}#supportMaterialsPage~.ed-form-actions{display:flex;justify-content:flex-end;gap:8px}#supportMaterialsPage~[data-teacher-material-error]{color:#b42318;font-size:12px}@media(max-width:480px){#supportMaterialsPage~.ed-edit-modal{padding:6px}#supportMaterialsPage~.ed-edit-modal-header,#supportMaterialsPage~.ed-edit-modal-body{padding:14px}#supportMaterialsPage~.ed-form-actions{display:grid;grid-template-columns:1fr 1fr}}
</style>
<div class="ed-edit-modal" hidden data-teacher-material-modal>
    <section class="ed-edit-modal-card" role="dialog" aria-modal="true" aria-labelledby="teacherMaterialTitle">
        <header class="ed-edit-modal-header"><div class="ed-edit-modal-heading"><h2 id="teacherMaterialTitle">Nuevo material</h2><p>Completa la información para crear el material.</p></div><button type="button" class="ed-edit-modal-close" data-teacher-material-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button></header>
        <form class="ed-edit-modal-body" data-teacher-material-form data-endpoint="<?= e((string) $supportMaterialManageSaveEndpoint) ?>" data-csrf="<?= e((string) $supportMaterialCsrf) ?>">
            <label>Título<input required maxlength="220" name="title"></label>
            <label>Tipo de material<input required maxlength="100" name="material_type"></label>
            <label>Categoría<select required name="category_id"><option value="">Selecciona una categoría</option><?php foreach ((array) $supportMaterialCategories as $category): ?><option value="<?= (int)$category['id'] ?>"><?= e((string)$category['name']) ?></option><?php endforeach; ?></select></label>
            <label>Responsable de la publicación<input required maxlength="180" name="publisher" value="<?= e((new AuthSessionService())->name()) ?>"></label>
            <label>Descripción corta<textarea required maxlength="500" name="description"></textarea></label>
            <label>Descripción completa<textarea required name="full_description"></textarea></label>
            <p data-teacher-material-error hidden></p><div class="ed-form-actions"><button type="button" data-teacher-material-close>Cancelar</button><button class="ed-action is-primary" type="submit">Crear material</button></div>
        </form>
    </section>
</div>
<script src="<?= e(asset('js/teacher-support-materials.js')) ?>"></script>
<?php endif; ?>
