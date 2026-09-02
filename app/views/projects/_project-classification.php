<?php
$projectClassificationLabels = (array) ($project['keywords'] ?? []);
?>
<section class="sw-card sw-project-classification" data-project-classification aria-labelledby="swProjectClassificationTitle">
    <header class="sw-section-heading">
        <div>
            <h2 id="swProjectClassificationTitle"><i class="fa-solid fa-tags" aria-hidden="true"></i> Clasificación</h2>
            <p>Etiquetas de clasificación registradas para este proyecto.</p>
        </div>
    </header>
    <?php require __DIR__ . '/_project-classification-content.php'; ?>
</section>
