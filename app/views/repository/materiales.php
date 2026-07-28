<div class="repository-content support-materials-page" id="supportMaterialsPage">
    <nav class="repository-detail-breadcrumb" aria-label="Ruta de navegación">
        <a href="<?= e($repositoryUrl) ?>">Repositorio</a><i class="fa-solid fa-chevron-right"></i><span>Material de apoyo</span>
    </nav>

    <header class="repository-hero support-materials-hero">
        <div><span class="section-eyebrow">Repositorio institucional</span><h1>Material de apoyo</h1><p>Consulta guías, formatos, reglamentos, plantillas e instructivos académicos.</p></div>
        <span class="repository-hero-icon"><i class="fa-solid fa-book-open"></i></span>
    </header>

    <section class="repository-support-tools support-materials-tools" aria-label="Buscar y filtrar materiales">
        <div class="repository-search"><i class="fa-solid fa-magnifying-glass"></i><input id="supportMaterialsSearch" type="search" placeholder="Buscar por título, tipo, PAO o palabra clave" aria-label="Buscar material de apoyo"></div>
        <label class="support-materials-filter"><span>Categoría</span><select id="supportMaterialsCategory"><option value="all">Todas</option><option value="vinculacion">Vinculación</option><option value="practicas">Prácticas</option><option value="tesis">Tesis</option><option value="proyecto-pis">Proyectos PIS</option></select></label>
    </section>

    <div class="section-heading support-materials-heading"><div><span class="section-eyebrow">Documentos disponibles</span><h2 class="section-title">Catálogo completo</h2></div><span class="repository-count" id="supportMaterialsCount" aria-live="polite"><?= count($materials) ?> materiales</span></div>

    <div class="repository-grid support-materials-grid" id="supportMaterialsGrid">
        <?php foreach ($materials as $material): ?>
            <article class="repository-card repository-support-card" data-support-material data-category="<?= e($material['category_slug']) ?>" data-search="<?= e(implode(' ', [$material['title'], $material['description'], $material['type'], $material['pao_label'], $material['year'], implode(' ', $material['keywords'])])) ?>">
                <div class="repository-card-top"><span class="repository-document-icon"><i class="fa-solid fa-file-circle-check"></i></span><span class="project-status approved"><?= e($material['status']) ?></span></div>
                <span class="repository-type"><?= e($material['type']) ?> · <?= e($material['pao_label']) ?></span>
                <h3><?= e($material['title']) ?></h3><p><?= e($material['description']) ?></p>
                <?php $representativeFile = $material['presentation_file'] ?? ($material['files'][0] ?? null); ?>
                <div class="repository-meta"><span><i class="fa-solid fa-folder-open"></i> <?= e($material['category_label']) ?></span><span><i class="fa-solid fa-file"></i> <?= e((string) ($representativeFile['format'] ?? 'Sin archivos')) ?></span><span><i class="fa-solid fa-download"></i> <?= number_format($material['downloads'], 0, ',', '.') ?> descargas</span></div>
                <div class="repository-card-actions"><a class="open-btn repository-open-btn" href="<?= e($material['detail_url']) ?>">Ver documento <i class="fa-solid fa-arrow-right"></i></a></div>
            </article>
        <?php endforeach; ?>
    </div>

    <div class="repository-empty" id="supportMaterialsEmpty" hidden><i class="fa-solid fa-folder-open"></i><h3>No se encontraron materiales</h3><p>Prueba con otros términos o selecciona una categoría diferente.</p></div>
</div>
