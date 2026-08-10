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
