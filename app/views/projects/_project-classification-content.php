<?php
$projectClassificationTone = static function (string $label): string {
    $key = mb_strtolower($label, 'UTF-8');
    if (class_exists('Normalizer')) {
        $normalized = Normalizer::normalize($key, Normalizer::FORM_D);
        if (is_string($normalized)) $key = (string) preg_replace('/\p{Mn}+/u', '', $normalized);
    }
    return match ($key) {
        'tesis', 'perfil de tesis', 'guia documental' => 'blue',
        'titulacion', 'investigacion', 'proyecto pis' => 'purple',
        'metodologia', 'vinculacion', 'practicas preprofesionales' => 'green',
        default => 'yellow',
    };
};
$projectClassificationLabels = array_values(array_filter(array_map(
    static function (mixed $keyword): string {
        return trim((string) (is_array($keyword) ? ($keyword['name'] ?? '') : $keyword));
    },
    (array) ($projectClassificationLabels ?? [])
), static fn (string $label): bool => $label !== ''));
?>
<?php if ($projectClassificationLabels !== []): ?>
    <div class="ed-tags" data-project-classification-tags>
        <?php foreach ($projectClassificationLabels as $tag): ?>
            <span class="ed-classification-tag is-tone-<?= e($projectClassificationTone($tag)) ?>"><i class="fa-solid fa-tag" aria-hidden="true"></i><span><?= e($tag) ?></span></span>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <p class="ed-project-classification-empty">Este proyecto no tiene etiquetas de clasificación registradas.</p>
<?php endif; ?>
