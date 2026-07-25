<?php if ($material === null): ?>
    <section class="repository-detail-not-found"><i class="fa-solid fa-folder-open"></i><h1>Contenido no encontrado</h1><p>El contenido solicitado no existe o ya no se encuentra disponible.</p><a class="open-btn" href="<?= e($repositoryUrl) ?>">Volver al repositorio</a></section>
<?php else:
    $record = [
        'repository_url' => $repositoryUrl,
        'category' => $material['category_label'],
        'type' => $material['type'],
        'status' => $material['status'],
        'title' => $material['title'],
        'description' => $material['description'],
        'responsible' => $material['publisher'],
        'publication_date' => $material['publication_date'],
    ];
    $information = [
        'summary' => $material['description'] ?? '',
        'description' => $material['full_description'] ?? '',
        'objectives' => [],
        'keywords' => array_values(array_unique($material['keywords'] ?? [])),
        'institutional' => [
            'Periodo' => $material['pao_label'] ?? '',
        ],
        'technical' => [],
    ];
    $materialDocuments = array_merge(
        isset($material['primary_file']) ? [$material['primary_file']] : [],
        $material['additional_files'] ?? []
    );
    $documents = array_map(static function (array $file): array {
        $extension = mb_strtolower((string) ($file['extension'] ?? pathinfo($file['name'], PATHINFO_EXTENSION)), 'UTF-8');
        return [
            'kind' => 'file',
            'name' => $file['name'],
            'type' => $file['format'] ?? strtoupper($extension),
            'size' => $file['size'] ?? '—',
            'extension' => $extension,
        ];
    }, $materialDocuments);
    require __DIR__ . '/_ficha-institucional.php';
endif; ?>
