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
    $updatedAtValue = trim((string) ($material['information_updated_at'] ?? ''));
    $updatedAtLabel = 'Sin actualizaciones registradas';
    if ($updatedAtValue !== '') {
        try {
            $updatedAtUtc = new DateTimeImmutable($updatedAtValue, new DateTimeZone('UTC'));
            $updatedAtLocal = $updatedAtUtc->setTimezone(new DateTimeZone(date_default_timezone_get()));
            $shortMonths = [1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr', 5 => 'may', 6 => 'jun',
                7 => 'jul', 8 => 'ago', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic'];
            $updatedAtLabel = $updatedAtLocal->format('d') . ' '
                . $shortMonths[(int) $updatedAtLocal->format('n')] . ' '
                . $updatedAtLocal->format('Y') . ' · ' . $updatedAtLocal->format('H:i');
        } catch (Throwable) {
            $updatedAtLabel = 'Sin actualizaciones registradas';
        }
    }
    $storedMaterialType = trim((string) ($material['material_type'] ?? $material['type'] ?? ''));
    $materialTypeKey = mb_strtolower($storedMaterialType, 'UTF-8');
    if (class_exists('Normalizer')) {
        $decomposedType = Normalizer::normalize($materialTypeKey, Normalizer::FORM_D);
        if (is_string($decomposedType)) {
            $materialTypeKey = (string) preg_replace('/\p{Mn}+/u', '', $decomposedType);
        }
    }
    $materialTypeKey = trim((string) preg_replace('/\s+/u', ' ', $materialTypeKey));
    $materialTypeMap = [
        'normativa' => 'Normativa',
        'formato' => 'Formato',
        'guía' => 'Guía documental',
        'guía documental' => 'Guía documental',
        'guia' => 'Guía documental',
        'guia documental' => 'Guía documental',
        'plantilla' => 'Plantilla',
    ];
    $materialTypeChoice = $materialTypeMap[$materialTypeKey] ?? 'Otros';
    $materialTypeCustom = $materialTypeChoice === 'Otros' ? $storedMaterialType : '';
    $selectedKeywords = array_values(array_map('strval', (array) ($material['editable_keywords'] ?? $material['keywords'] ?? [])));
    $legacyKeywords = array_values(array_filter(
        $selectedKeywords,
        static fn (string $keyword): bool => !in_array($keyword, SupportMaterialModel::KEYWORD_CATALOG, true)
    ));

    $materialFiles = array_values(array_filter(
        is_array($material['files'] ?? null) ? $material['files'] : [],
        'is_array'
    ));
    $materialAvailable = !empty($material['is_available']);
    $documents = array_map(static function (array $file) use (
        $materialAvailable,
        $materialId,
        $previewActionUrl,
        $downloadActionUrl,
        $zipListActionUrl,
        $zipEntryPreviewActionUrl,
        $zipEntryDownloadActionUrl
    ): array {
        $name = (string) ($file['name'] ?? 'Archivo sin nombre');
        $extension = mb_strtolower((string) ($file['extension'] ?? pathinfo($name, PATHINFO_EXTENSION)), 'UTF-8');
        $fileId = (int) ($file['id'] ?? 0);
        $query = '&material_id=' . $materialId . '&file_id=' . $fileId;
        $previewTypes = [
            'pdf' => 'pdf', 'jpg' => 'image', 'jpeg' => 'image', 'png' => 'image',
            'webp' => 'image', 'docx' => 'docx', 'txt' => 'text',
        ];
        $isZip = $extension === 'zip';
        $available = $materialAvailable && !empty($file['available']);
        return [
            'id' => $fileId,
            'name' => $name,
            'type' => (string) ($file['format'] ?? ($extension !== '' ? mb_strtoupper($extension, 'UTF-8') : 'Archivo')),
            'size' => (string) ($file['size'] ?? 'Tamaño no disponible'),
            'sort_order' => (int) ($file['sort_order'] ?? $fileId),
            'extension' => $extension,
            'available' => $available,
            'is_presentation' => !empty($file['presentation']),
            'is_package' => false,
            'preview_supported' => $available && (isset($previewTypes[$extension]) || $isZip),
            'preview_type' => $isZip ? 'zip' : ($previewTypes[$extension] ?? 'unsupported'),
            'preview_url' => $available && $fileId > 0 ? (string) $previewActionUrl . $query : '',
            'zip_url' => $available && $isZip && $fileId > 0 ? (string) $zipListActionUrl . $query : '',
            'zip_entry_preview_url' => $available && $isZip && $fileId > 0 ? (string) $zipEntryPreviewActionUrl . $query : '',
            'zip_entry_download_url' => $available && $isZip && $fileId > 0 ? (string) $zipEntryDownloadActionUrl . $query : '',
            'download_url' => $available && $fileId > 0 ? (string) $downloadActionUrl . $query : '',
        ];
    }, $materialFiles);
    $regularArchives = array_values(array_filter($documents, static fn (array $document): bool => ($document['extension'] ?? '') === 'zip'));
    $documents = array_values(array_filter($documents, static fn (array $document): bool => ($document['extension'] ?? '') !== 'zip'));
    $packageDescriptor = is_array($material['package_descriptor'] ?? null) ? $material['package_descriptor'] : [];
    $archives = $regularArchives;
    $packageAvailable = $materialAvailable && !empty($packageDescriptor['available']);
    $packageDownloadUrl = $packageAvailable
        ? (string) $packageDownloadActionUrl . '&material_id=' . $materialId
        : '';

    $publicationState = (string) ($material['status_key'] ?? 'published');
    $isPublished = $publicationState === 'published';
    $presentationFile = $material['presentation_file'] ?? null;
    $downloadUrl = is_array($presentationFile) && !empty($presentationFile['id'])
        ? $downloadActionUrl . '&material_id=' . $materialId . '&file_id=' . (int) $presentationFile['id']
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
            ['label' => 'Última actualización', 'value' => $updatedAtLabel],
            ['label' => 'Categoría', 'value' => (string) ($material['category_label'] ?? '')],
            ['label' => 'Periodo', 'value' => (string) ($material['pao_label'] ?? '')],
            ['key' => 'availability', 'label' => 'Disponibilidad', 'value' => $isPublished ? ($materialAvailable ? 'Disponible' : 'No disponible') : 'No aplica', 'tone' => 'secondary'],
        ], static fn (array $item): bool => $item['value'] !== '')),
        'actions' => $mode === 'edit' ? [] : array_values(array_filter([
            $administratorView ? ['id' => 'edit', 'label' => 'Editar', 'kind' => 'primary', 'icon' => 'fa-pen-to-square', 'url' => $materialEditUrl ?: null, 'enabled' => $materialEditUrl !== ''] : null,
            ['id' => 'download', 'label' => 'Descargar', 'kind' => 'secondary', 'icon' => 'fa-download', 'url' => $downloadUrl, 'enabled' => $downloadUrl !== null],
        ])),
        'menu_actions' => $administratorView && $mode === 'view' ? [
            ['label' => $materialAvailable ? 'Marcar como no disponible' : 'Marcar como disponible', 'icon' => 'fa-toggle-on', 'enabled' => true, 'hidden' => !$isPublished, 'danger' => false, 'action' => 'availability'],
            ['label' => $isPublished ? 'Retirar publicación' : 'Publicar material', 'icon' => $isPublished ? 'fa-eye-slash' : 'fa-eye', 'enabled' => true, 'danger' => false, 'action' => 'publication'],
            ['label' => 'Ver historial administrativo', 'icon' => 'fa-clock-rotate-left', 'enabled' => true, 'danger' => false, 'action' => 'admin-history', 'separator' => true, 'unread' => !empty($hasUnreadAdministrativeActivity)],
            ['label' => 'Enviar a Papelera', 'icon' => 'fa-trash-can', 'enabled' => true, 'danger' => true, 'action' => 'trash'],
        ] : [],
        'tabs' => [
            ['id' => 'information', 'label' => 'Información', 'icon' => 'fa-file-lines', 'url' => $detailUrl . $modeQuery . '&tab=information'],
            ['id' => 'files', 'label' => 'Archivos', 'icon' => 'fa-folder-open', 'url' => $detailUrl . $modeQuery . '&tab=files'],
            ['id' => 'evolution', 'label' => 'Evolución documental', 'icon' => 'fa-clock-rotate-left', 'url' => $detailUrl . $modeQuery . '&tab=evolution'],
        ],
        'active_tab' => $activeTab,
        'admin_actions' => [
            'endpoint' => (string) ($materialStatusEndpoint ?? ''),
            'csrf_token' => (string) ($materialCsrfToken ?? ''),
            'status' => $publicationState,
            'is_available' => $materialAvailable,
            'redirect' => $administratorView ? route('admin-repository') . '&tab=materials' : (string) $repositoryUrl,
            'has_unread' => !empty($hasUnreadAdministrativeActivity),
        ],
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
        'restorable_files' => is_array($restorableFiles ?? null) ? $restorableFiles : [],
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
        'versions' => is_array($documentEvolution ?? null) ? $documentEvolution : [],
        'version_endpoints' => [
            'preview' => (string) ($versionPreviewActionUrl ?? ''),
            'download' => (string) ($versionDownloadActionUrl ?? ''),
        ],
        'form' => [
            'action' => (string) ($materialSaveEndpoint ?? ''),
            'method' => 'POST',
            'csrf_token' => (string) ($materialCsrfToken ?? ''),
            'cancel_url' => $viewUrl,
            'detail_url' => $viewUrl,
            'success_url' => $viewUrl,
            'success_message' => '',
            'categories' => is_array($materialCategories ?? null) ? $materialCategories : [],
            'values' => [
                'id' => $materialId,
                'title' => (string) ($material['title'] ?? ''),
                'category_id' => (int) ($material['category_id'] ?? 0),
                'material_type_choice' => $materialTypeChoice,
                'material_type_custom' => $materialTypeCustom,
                'description' => (string) ($material['description'] ?? ''),
                'full_description' => (string) ($material['full_description'] ?? ''),
                'publisher' => (string) ($material['publisher'] ?? ''),
                'publication_date_label' => (string) ($material['publication_date'] ?? 'Sin publicar'),
                'updated_at_label' => $updatedAtLabel,
                'period' => (string) ($material['pao_label'] ?? ''),
                'keywords_selected' => $selectedKeywords,
            ],
            'keyword_catalog' => SupportMaterialModel::KEYWORD_CATALOG,
            'legacy_keywords' => $legacyKeywords,
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
