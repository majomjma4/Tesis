<?php if ($material === null): ?>
    <section class="repository-detail-not-found"><i class="fa-solid fa-folder-open"></i><h1>Contenido no encontrado</h1><p>El contenido solicitado no existe o ya no se encuentra disponible.</p><a class="open-btn" href="<?= e($repositoryUrl) ?>">Volver al repositorio</a></section>
<?php else:
    $allowedTabs = ['information', 'files', 'evolution'];
    $requestedTab = strtolower(trim((string) ($_GET['tab'] ?? 'information')));
    $activeTab = in_array($requestedTab, $allowedTabs, true) ? $requestedTab : 'information';
    $materialId = (int) ($material['id'] ?? 0);
    $detailUrl = route('support-material-detail') . '&id=' . $materialId;
    $requestedMode = strtolower(trim((string) ($_GET['mode'] ?? 'view')));
    $mode = $requestedMode === 'edit' && !empty($isAdministrator) ? 'edit' : 'view';
    $modeQuery = $mode === 'edit' ? '&mode=edit' : '&mode=view';
    $viewUrl = $detailUrl . '&mode=view&tab=information';

    $materialFiles = array_values(array_filter(array_merge(
        !empty($material['primary_file']) ? [$material['primary_file']] : [],
        is_array($material['additional_files'] ?? null) ? $material['additional_files'] : []
    ), 'is_array'));
    $documents = array_map(static function (array $file) use ($materialId, $previewActionUrl, $downloadActionUrl, $zipListActionUrl): array {
        $name = (string) ($file['name'] ?? 'Archivo sin nombre');
        $extension = mb_strtolower((string) ($file['extension'] ?? pathinfo($name, PATHINFO_EXTENSION)), 'UTF-8');
        $fileId = (int) ($file['id'] ?? 0);
        $query = '&material_id=' . $materialId . '&file_id=' . $fileId;
        $previewTypes = [
            'pdf' => 'pdf', 'jpg' => 'image', 'jpeg' => 'image', 'png' => 'image',
            'webp' => 'image', 'docx' => 'docx', 'txt' => 'text',
        ];
        $isZip = $extension === 'zip';
        return [
            'id' => $fileId,
            'name' => $name,
            'type' => (string) ($file['format'] ?? ($extension !== '' ? mb_strtoupper($extension, 'UTF-8') : 'Archivo')),
            'size' => (string) ($file['size'] ?? 'Tamaño no disponible'),
            'extension' => $extension,
            'is_primary' => !empty($file['primary']),
            'is_package' => false,
            'preview_supported' => isset($previewTypes[$extension]) || $isZip,
            'preview_type' => $isZip ? 'zip' : ($previewTypes[$extension] ?? 'unsupported'),
            'preview_url' => $fileId > 0 ? (string) $previewActionUrl . $query : '',
            'zip_url' => $isZip && $fileId > 0 ? (string) $zipListActionUrl . $query : '',
            'download_url' => $fileId > 0 ? (string) $downloadActionUrl . $query : '',
        ];
    }, $materialFiles);
    $regularArchives = array_values(array_filter($documents, static fn (array $document): bool => ($document['extension'] ?? '') === 'zip'));
    $documents = array_values(array_filter($documents, static fn (array $document): bool => ($document['extension'] ?? '') !== 'zip'));
    $packageDescriptor = is_array($material['package_descriptor'] ?? null) ? $material['package_descriptor'] : [];
    $archives = $regularArchives;
    $packageAvailable = !empty($packageDescriptor['available']);
    $packageDownloadUrl = $packageAvailable
        ? (string) $packageDownloadActionUrl . '&material_id=' . $materialId
        : '';

    $publicationState = (string) ($material['status_key'] ?? 'published');
    $isPublished = $publicationState === 'published';
    $primaryFile = $material['primary_file'] ?? null;
    $downloadUrl = is_array($primaryFile) && !empty($primaryFile['id'])
        ? $downloadActionUrl . '&material_id=' . $materialId . '&file_id=' . (int) $primaryFile['id']
        : null;
    $administratorView = !empty($isAdministrator);

    $digitalRecord = [
        'entity' => ['type' => 'support_material', 'id' => $materialId],
        'context' => 'repository',
        'mode' => $mode,
        'return_url' => $repositoryUrl,
        'breadcrumbs' => [
            ['label' => 'Repositorio', 'url' => $repositoryUrl],
            ['label' => (string) ($material['category_label'] ?? 'Materiales de apoyo'), 'url' => null],
            ['label' => (string) ($material['title'] ?? 'Material de apoyo'), 'url' => null],
        ],
        'header' => [
            'title' => (string) ($material['title'] ?? 'Material de apoyo'),
            'description' => $mode === 'edit' ? 'Editando información del material.' : (string) ($material['description'] ?? ''),
            'type_label' => 'Material de apoyo',
            'status_label' => $isPublished ? 'Publicado' : 'Retirado',
            'status_tone' => $isPublished ? 'success' : 'neutral',
        ],
        'metadata' => array_values(array_filter([
            ['label' => 'Responsable', 'value' => (string) ($material['publisher'] ?? '')],
            ['label' => 'Publicación', 'value' => (string) ($material['publication_date'] ?? '')],
            ['label' => 'Categoría', 'value' => (string) ($material['category_label'] ?? '')],
            ['label' => 'Periodo', 'value' => (string) ($material['pao_label'] ?? '')],
            ['label' => 'Disponibilidad', 'value' => $isPublished ? 'Disponible' : 'No disponible', 'tone' => 'secondary'],
        ], static fn (array $item): bool => $item['value'] !== '')),
        'actions' => $mode === 'edit' ? [] : array_values(array_filter([
            $administratorView ? ['id' => 'edit', 'label' => 'Editar', 'kind' => 'primary', 'icon' => 'fa-pen-to-square', 'url' => $materialEditUrl ?: null, 'enabled' => $materialEditUrl !== ''] : null,
            ['id' => 'download', 'label' => 'Descargar', 'kind' => 'secondary', 'icon' => 'fa-download', 'url' => $downloadUrl, 'enabled' => $downloadUrl !== null],
            ['id' => 'share', 'label' => 'Compartir', 'kind' => 'secondary', 'icon' => 'fa-share-nodes', 'url' => null, 'enabled' => false],
        ])),
        'menu_actions' => $administratorView && $mode === 'view' ? [
            ['label' => 'Cambiar disponibilidad', 'icon' => 'fa-toggle-on', 'enabled' => false, 'danger' => false],
            ['label' => $isPublished ? 'Retirar publicación' : 'Publicar material', 'icon' => $isPublished ? 'fa-eye-slash' : 'fa-eye', 'enabled' => false, 'danger' => false],
            ['label' => 'Ver historial administrativo', 'icon' => 'fa-clock-rotate-left', 'enabled' => true, 'danger' => false, 'action' => 'admin-history', 'separator' => true],
            ['label' => 'Enviar a Papelera', 'icon' => 'fa-trash-can', 'enabled' => false, 'danger' => true],
        ] : [],
        'tabs' => [
            ['id' => 'information', 'label' => 'Información', 'icon' => 'fa-file-lines', 'url' => $detailUrl . $modeQuery . '&tab=information'],
            ['id' => 'files', 'label' => 'Archivos', 'icon' => 'fa-folder-open', 'url' => $detailUrl . $modeQuery . '&tab=files'],
            ['id' => 'evolution', 'label' => 'Evolución documental', 'icon' => 'fa-clock-rotate-left', 'url' => $detailUrl . $modeQuery . '&tab=evolution'],
        ],
        'active_tab' => $activeTab,
        'information_sections' => [
            ['id' => 'description', 'title' => 'Descripción', 'icon' => 'fa-align-left', 'type' => 'prose', 'content' => (string) ($material['full_description'] ?? $material['description'] ?? '')],
            ['id' => 'institutional', 'title' => 'Información institucional', 'icon' => 'fa-building-columns', 'type' => 'metadata', 'content' => array_values(array_filter([
                ['label' => 'Categoría', 'value' => (string) ($material['category_label'] ?? '')],
                ['label' => 'Tipo', 'value' => (string) ($material['type'] ?? '')],
                ['label' => 'Responsable', 'value' => (string) ($material['publisher'] ?? '')],
                ['label' => 'Periodo', 'value' => (string) ($material['pao_label'] ?? '')],
            ], static fn (array $item): bool => $item['value'] !== ''))],
            ['id' => 'keywords', 'title' => 'Palabras clave', 'icon' => 'fa-tags', 'type' => 'tags', 'content' => array_values(array_filter(array_map('strval', (array) ($material['keywords'] ?? []))))],
            ['id' => 'related', 'title' => 'Recursos relacionados', 'icon' => 'fa-link', 'type' => 'empty', 'content' => 'No existen recursos relacionados para este expediente.'],
        ],
        'documents' => $documents,
        'archives' => $archives,
        'can_manage_files' => $administratorView,
        'file_upload' => [
            'endpoint' => (string) ($materialFileEndpoint ?? ''),
            'csrf_token' => (string) ($materialCsrfToken ?? ''),
            'limits' => is_array($materialFileLimits ?? null) ? $materialFileLimits : [],
        ],
        'package' => [
            'available' => $packageAvailable,
            'download_url' => $packageDownloadUrl,
            'file_count' => (int) ($packageDescriptor['file_count'] ?? count($documents)),
            'source' => (string) ($packageDescriptor['source'] ?? 'generated'),
            'browsable' => false,
        ],
        'versions' => [],
        'form' => [
            'action' => (string) ($materialSaveEndpoint ?? ''),
            'method' => 'POST',
            'csrf_token' => (string) ($materialCsrfToken ?? ''),
            'cancel_url' => $viewUrl,
            'success_url' => $viewUrl . '&saved=1',
            'success_message' => (string) ($_GET['saved'] ?? '') === '1' ? 'Material actualizado correctamente.' : '',
            'categories' => is_array($materialCategories ?? null) ? $materialCategories : [],
            'values' => [
                'id' => $materialId,
                'title' => (string) ($material['title'] ?? ''),
                'category_id' => (int) ($material['category_id'] ?? 0),
                'material_type' => (string) ($material['material_type'] ?? $material['type'] ?? ''),
                'description' => (string) ($material['description'] ?? ''),
                'full_description' => (string) ($material['full_description'] ?? ''),
                'publisher' => (string) ($material['publisher'] ?? ''),
                'publication_date' => (string) ($material['publication_date_iso'] ?? ''),
                'period' => (string) ($material['pao_label'] ?? ''),
                'keywords' => implode(', ', array_map('strval', (array) ($material['keywords'] ?? []))),
            ],
            'errors' => [],
        ],
        'endpoints' => [
            'preview' => $previewActionUrl ?? '',
            'preview_content' => $previewContentActionUrl ?? '',
            'download' => $downloadActionUrl ?? '',
            'admin_history' => $materialHistoryEndpoint ?? '',
            'admin_history_cleanup' => $materialHistoryCleanupEndpoint ?? '',
        ],
    ];
    require __DIR__ . '/_ficha-institucional.php';
endif; ?>
