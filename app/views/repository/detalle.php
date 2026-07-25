<?php if ($project === null): ?>
    <section class="repository-detail-not-found"><i class="fa-solid fa-folder-open"></i><h1>Contenido no encontrado</h1><p>El contenido solicitado no existe o ya no se encuentra disponible.</p><a class="open-btn" href="<?= e($repositoryUrl) ?>">Volver al repositorio</a></section>
<?php else:
    $record = [
        'repository_url' => $repositoryUrl,
        'category' => $project['category'],
        'type' => $project['type'],
        'status' => 'Publicado',
        'title' => $project['title'],
        'description' => $project['description'] ?? '',
        'responsible' => $project['tutor'],
        'publication_date' => $project['publication_date'],
    ];
    $information = [
        'summary' => $project['description'] ?? '',
        'description' => $project['summary'] ?? '',
        'objectives' => [],
        'keywords' => array_values(array_unique($project['keywords'] ?? [])),
        'institutional' => [
            'Carrera' => $project['career'] ?? '',
            'Periodo' => $project['pao_label'] ?? '',
        ],
        'technical' => array_values(array_unique($project['technologies'] ?? [])),
    ];
    $documents = array_map(static function (array $item): array {
        $extension = mb_strtolower(pathinfo($item['name'], PATHINFO_EXTENSION), 'UTF-8');
        $isFolder = $item['kind'] === 'folder';
        return [
            'kind' => $item['kind'],
            'name' => $item['name'],
            'type' => $isFolder ? 'Carpeta' : $item['type'],
            'size' => $isFolder ? '—' : $item['size'],
            'extension' => $extension,
        ];
    }, $archiveState['items'] ?? []);
    require __DIR__ . '/_ficha-institucional.php';
endif; ?>
