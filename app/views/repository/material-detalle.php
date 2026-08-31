<?php if ($material === null): ?>
    <section class="repository-detail-not-found"><i class="fa-solid fa-folder-open"></i><h1>Contenido no encontrado</h1><p>El contenido solicitado no existe o ya no se encuentra disponible.</p><a class="open-btn" href="<?= e($repositoryUrl) ?>">Volver al repositorio</a></section>
<?php else:
    $administratorView = !empty($isAdministrator) || !empty($administratorView);
    $teacherOwnedReadOnly = !empty($teacherOwnedReadOnly);
    $teacherOwnedDownloadAllowed = $teacherOwnedReadOnly && !empty($canDownloadOwnedDetail);
    $resourceContextQuery = $teacherOwnedDownloadAllowed ? '&context=teacher_owner' : '';
    $effectivePreviewActionUrl = (string) $previewActionUrl . $resourceContextQuery;
    $effectiveDownloadActionUrl = (string) $downloadActionUrl . $resourceContextQuery;
    $effectiveZipListActionUrl = (string) $zipListActionUrl . $resourceContextQuery;
    $effectiveZipEntryPreviewActionUrl = (string) $zipEntryPreviewActionUrl . $resourceContextQuery;
    $effectiveZipEntryDownloadActionUrl = (string) $zipEntryDownloadActionUrl . $resourceContextQuery;
    $effectivePackageDownloadActionUrl = (string) $packageDownloadActionUrl . $resourceContextQuery;
    $canEditInfo = !empty($canEditInformation) || $administratorView;
    $canFiles = !empty($canManageFiles) || $administratorView;
    $canStatus = !empty($canChangeStatus) || $administratorView;
    $canDel = !empty($canDelete) || $administratorView;
    $canManageMaterial = $canEditInfo || $canFiles || $canStatus || $canDel || !empty($canManageSupportMaterial);
    $allowedTabs = $administratorView ? ['information', 'files', 'evolution'] : ['information', 'files'];
    $requestedTab = strtolower(trim((string) ($_GET['tab'] ?? 'information')));
    $activeTab = in_array($requestedTab, $allowedTabs, true) ? $requestedTab : 'information';
    $materialId = (int) ($material['id'] ?? 0);
    $detailUrl = ($teacherOwnedReadOnly ? route('support-material-teacher-detail') : route('support-material-detail')) . '&id=' . $materialId;
    $requestedMode = strtolower(trim((string) ($_GET['mode'] ?? 'view')));
    $mode = $requestedMode === 'edit' && $canEditInfo ? 'edit' : 'view';
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
    $catalogModel = new SupportMaterialModel();
    $materialTypeEntries = $catalogModel->catalogEntries('material_type', true);
    $materialTypeCatalog = array_column($materialTypeEntries, 'name');
    $keywordCatalog = $catalogModel->keywordCatalog();
    $materialTypeKey = mb_strtolower($storedMaterialType, 'UTF-8');
    if (class_exists('Normalizer')) {
        $decomposedType = Normalizer::normalize($materialTypeKey, Normalizer::FORM_D);
        if (is_string($decomposedType)) {
            $materialTypeKey = (string) preg_replace('/\p{Mn}+/u', '', $decomposedType);
        }
    }
    $materialTypeKey = trim((string) preg_replace('/\s+/u', ' ', $materialTypeKey));
    $materialTypeMap = [];
    foreach ($materialTypeEntries as $entry) {
        foreach (array_merge([$entry['name']], $entry['aliases']) as $catalogType) {
            $key = mb_strtolower($catalogType, 'UTF-8');
            if (class_exists('Normalizer')) {
                $decomposed = Normalizer::normalize($key, Normalizer::FORM_D);
                if (is_string($decomposed)) $key = (string) preg_replace('/\p{Mn}+/u', '', $decomposed);
            }
            $materialTypeMap[trim((string) preg_replace('/\s+/u', ' ', $key))] = $entry['name'];
        }
    }
    $materialTypeChoice = $materialTypeMap[$materialTypeKey] ?? 'Otros';
    $materialTypeCustom = $materialTypeChoice === 'Otros' ? $storedMaterialType : '';
    $selectedKeywords = array_values(array_map('strval', (array) ($material['editable_keywords'] ?? $material['keywords'] ?? [])));
    $legacyKeywords = array_values(array_filter(
        $selectedKeywords,
        static fn (string $keyword): bool => !in_array($keyword, $keywordCatalog, true)
    ));
    $classificationLabels = array_values(array_filter(
        array_map('strval', (array) ($material['keywords'] ?? [])),
        static fn (string $label): bool => trim($label) !== ''
    ));
    $relatedResources = array_values(array_filter(
        (array) ($material['related_resources'] ?? $material['resources'] ?? []),
        static fn (mixed $resource): bool => is_array($resource)
            ? trim((string) ($resource['title'] ?? $resource['label'] ?? $resource['name'] ?? $resource['url'] ?? '')) !== ''
            : trim((string) $resource) !== ''
    ));

    $materialFiles = array_values(array_filter(
        is_array($material['files'] ?? null) ? $material['files'] : [],
        'is_array'
    ));
    $materialAvailable = !empty($material['is_available']);
    $documents = array_map(static function (array $file) use (
        $materialAvailable,
        $teacherOwnedDownloadAllowed,
        $materialId,
        $effectivePreviewActionUrl,
        $effectiveDownloadActionUrl,
        $effectiveZipListActionUrl,
        $effectiveZipEntryPreviewActionUrl,
        $effectiveZipEntryDownloadActionUrl
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
        $physicalAvailable = !empty($file['available']);
        $available = ($materialAvailable || $teacherOwnedDownloadAllowed) && $physicalAvailable;
        return [
            'id' => $fileId,
            'name' => $name,
            'type' => (string) ($file['format'] ?? ($extension !== '' ? mb_strtoupper($extension, 'UTF-8') : 'Archivo')),
            'size' => (string) ($file['size'] ?? 'Tamaño no disponible'),
            'size_bytes' => (int) ($file['size_bytes'] ?? $file['size_raw'] ?? 0),
            'sort_order' => (int) ($file['sort_order'] ?? $fileId),
            'extension' => $extension,
            'mime_type' => (string) ($file['mime_type'] ?? ''),
            'available' => $available,
            'physical_available' => $physicalAvailable,
            'material_available' => $materialAvailable,
            'is_presentation' => !empty($file['presentation']),
            'is_package' => false,
            'preview_supported' => $available && (isset($previewTypes[$extension]) || $isZip),
            'preview_type' => $isZip ? 'zip' : ($previewTypes[$extension] ?? 'unsupported'),
            'preview_url' => $available && $fileId > 0 ? (string) $effectivePreviewActionUrl . $query : '',
            'zip_url' => $available && $isZip && $fileId > 0 ? (string) $effectiveZipListActionUrl . $query : '',
            'zip_entry_preview_url' => $available && $isZip && $fileId > 0 ? (string) $effectiveZipEntryPreviewActionUrl . $query : '',
            'zip_entry_download_url' => $available && $isZip && $fileId > 0 ? (string) $effectiveZipEntryDownloadActionUrl . $query : '',
            'download_url' => $available && $fileId > 0 ? (string) $effectiveDownloadActionUrl . $query : '',
        ];
    }, $materialFiles);
    $regularArchives = array_values(array_filter($documents, static fn (array $document): bool => ($document['extension'] ?? '') === 'zip'));
    $documents = array_values(array_filter($documents, static fn (array $document): bool => ($document['extension'] ?? '') !== 'zip'));
    $packageDescriptor = is_array($material['package_descriptor'] ?? null) ? $material['package_descriptor'] : [];
    $archives = $regularArchives;
    $packageAvailable = ($materialAvailable || $teacherOwnedDownloadAllowed) && !empty($packageDescriptor['available']);
    $packageDownloadUrl = $packageAvailable
        ? (string) $effectivePackageDownloadActionUrl . '&material_id=' . $materialId
        : '';

    $publicationState = (string) ($material['status_key'] ?? 'draft');
    $isPublished = $publicationState === 'published';
    $statusLabel = !empty($material['deleted_at']) ? 'Papelera' : match ($publicationState) {
        'published' => 'Publicado',
        'draft' => 'Borrador',
        default => 'Retirado',
    };
    $materialFiles = array_values(array_filter(
        (array) ($material['files'] ?? []),
        static fn (array $file): bool => ($file['state'] ?? 'available') === 'available'
            && !empty($file['available'])
    ));
    $presentationFile = array_values(array_filter(
        $materialFiles,
        static fn (array $file): bool => !empty($file['presentation'])
    ))[0] ?? ($materialFiles[0] ?? null);
    $downloadUrl = $presentationFile !== null
        ? $effectiveDownloadActionUrl . '&material_id=' . $materialId . '&file_id=' . (int) $presentationFile['id']
        : ($packageAvailable
            ? $effectivePackageDownloadActionUrl . '&id=' . $materialId
            : null);
    $resourceCount = count(array_filter(
        (array) ($material['resources'] ?? []),
        static fn (mixed $resource): bool => is_array($resource)
            ? !empty($resource['url'])
            : trim((string) $resource) !== ''
    ));
    $registeredFileCount = count($materialFiles);

    $digitalRecord = [
        'entity' => ['type' => 'support_material', 'id' => $materialId],
        'context' => 'repository',
        'mode' => $mode,
        'capabilities' => [
            'download_files' => $materialAvailable || $teacherOwnedDownloadAllowed,
        ],
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
            'status_label' => $statusLabel,
            'status_tone' => !empty($material['deleted_at']) ? 'neutral' : ($isPublished ? 'success' : 'neutral'),
        ],
        'metadata' => array_values(array_filter([
            ['key' => 'publication', 'label' => 'Publicación', 'value' => (string) ($material['publication_date'] ?? '')],
            ['key' => 'updated', 'label' => 'Última actualización', 'value' => $updatedAtLabel],
            ['key' => 'period', 'label' => 'Periodo académico', 'value' => (string) ($material['pao_label'] ?? '')],
            ['key' => 'availability', 'label' => 'Disponibilidad', 'value' => $isPublished ? ($materialAvailable ? 'Disponible' : 'No disponible') : 'No aplica', 'tone' => 'secondary'],
        ], static fn (array $item): bool => $item['value'] !== '')),
        'actions' => $mode === 'edit' ? [] : array_values(array_filter([
            $canEditInfo ? ['id' => 'edit', 'label' => 'Editar', 'kind' => 'primary', 'icon' => 'fa-pen-to-square', 'url' => $materialEditUrl ?: null, 'enabled' => $materialEditUrl !== '', 'modal' => true] : null,
            $downloadUrl !== null ? ['id' => 'download', 'label' => 'Descargar', 'kind' => 'secondary', 'icon' => 'fa-download', 'icon_style' => 'fa-solid', 'url' => $downloadUrl, 'enabled' => true, 'download' => true] : null,
        ])),
        'menu_actions' => $mode === 'view' && ($administratorView || $canStatus || $canDel) ? array_values(array_filter([
            ($administratorView || $canStatus) ? ['label' => $materialAvailable ? 'Marcar como no disponible' : 'Marcar como disponible', 'icon' => $materialAvailable ? 'fa-ban' : 'fa-circle-check', 'enabled' => true, 'hidden' => !$isPublished, 'danger' => false, 'action' => 'availability'] : null,
            ($administratorView || $canStatus) ? ['label' => $isPublished ? 'Retirar publicación' : 'Publicar material', 'icon' => $isPublished ? 'fa-box-archive' : 'fa-box-open', 'enabled' => true, 'danger' => false, 'action' => 'publication'] : null,
            $administratorView ? ['label' => 'Ver historial administrativo', 'icon' => 'fa-clock-rotate-left', 'enabled' => true, 'danger' => false, 'action' => 'admin-history', 'separator' => true, 'unread' => !empty($hasUnreadAdministrativeActivity)] : null,
            ($administratorView || $canDel) ? ['label' => 'Enviar a Papelera', 'icon' => 'fa-trash-can', 'enabled' => true, 'danger' => true, 'action' => 'trash'] : null,
        ])) : [],
        'tabs' => array_values(array_filter([
            ['id' => 'information', 'label' => 'Información', 'icon' => 'fa-file-lines', 'url' => $detailUrl . $modeQuery . '&tab=information'],
            ['id' => 'files', 'label' => 'Archivos', 'icon' => 'fa-folder-open', 'url' => $detailUrl . $modeQuery . '&tab=files'],
            $administratorView ? ['id' => 'evolution', 'label' => 'Evolución documental', 'icon' => 'fa-clock-rotate-left', 'url' => $detailUrl . $modeQuery . '&tab=evolution'] : null,
        ])),
        'active_tab' => $activeTab,
        'admin_actions' => [
            'endpoint' => (string) ($materialStatusEndpoint ?? ''),
            'csrf_token' => (string) ($materialCsrfToken ?? ''),
            'status' => $publicationState,
            'is_available' => $materialAvailable,
            'redirect' => $administratorView ? route('admin-repository') . '&tab=materials' : (string) $repositoryUrl,
            'has_unread' => !empty($hasUnreadAdministrativeActivity),
        ],
        'teacher_owner_status_management' => !$administratorView && $canStatus,
        'information_sections' => [
            ['id' => 'description', 'title' => 'Descripción', 'icon' => 'fa-align-left', 'type' => 'prose', 'content' => (string) ($material['full_description'] ?? $material['description'] ?? '')],
            ['id' => 'institutional', 'title' => 'Ficha del material', 'icon' => 'fa-building-columns', 'type' => 'metadata', 'content' => array_values(array_filter([
                ['key' => 'document-type', 'icon' => 'fa-file-lines', 'label' => 'Tipo documental', 'value' => (string) ($material['type'] ?? '')],
                ['key' => 'category', 'icon' => 'fa-folder-tree', 'label' => 'Categoría', 'value' => (string) ($material['category_label'] ?? '')],
                ['key' => 'responsible', 'icon' => 'fa-user-tie', 'label' => 'Responsable', 'value' => (string) ($material['publisher'] ?? '')],
                ['key' => 'files', 'icon' => 'fa-folder-open', 'label' => 'Archivos registrados', 'value' => $registeredFileCount . ($registeredFileCount === 1 ? ' archivo' : ' archivos')],
            ], static fn (array $item): bool => $item['value'] !== ''))],
            ['id' => 'keywords', 'title' => 'Clasificación', 'icon' => 'fa-tags', 'type' => 'tags', 'content' => $classificationLabels],
            ...($relatedResources !== [] ? [[
                'id' => 'related',
                'title' => 'Recursos relacionados',
                'icon' => 'fa-link',
                'type' => 'related',
                'content' => $relatedResources,
            ]] : []),
        ],
        'documents' => $documents,
        'archives' => $archives,
        'can_manage_files' => $canManageMaterial,
        'can_permanently_delete_files' => $administratorView,
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
            'size' => (string) ($packageDescriptor['size'] ?? ''),
            'size_bytes' => (int) ($packageDescriptor['size_bytes'] ?? 0),
            'source' => (string) ($packageDescriptor['source'] ?? 'generated'),
            'browsable' => false,
        ],
        'versions' => is_array($documentEvolution ?? null) ? $documentEvolution : [],
        'document_evolution_events' => is_array($documentEvolutionEvents ?? null) ? $documentEvolutionEvents : [],
        'document_evolution_total' => (int) ($documentEvolutionTotal ?? 0),
        'document_evolution_endpoint' => route('support-material-evolution-events') . '&material_id=' . $materialId,
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
            'material_type_catalog' => $materialTypeCatalog,
            'keyword_catalog' => $keywordCatalog,
            'legacy_keywords' => $legacyKeywords,
            'errors' => [],
        ],
        'endpoints' => [
            'preview' => $effectivePreviewActionUrl ?? '',
            'preview_content' => $previewContentActionUrl ?? '',
            'download' => $effectiveDownloadActionUrl ?? '',
            'admin_history' => $materialHistoryEndpoint ?? '',
            'admin_history_cleanup' => $materialHistoryCleanupEndpoint ?? '',
        ],
    ];
    require __DIR__ . '/_ficha-institucional.php';
endif; ?>
<?php if ($material !== null && empty($administratorView)): ?>
<style>
/* Fase 2 visual: detalle público de materiales; no altera el expediente administrativo compartido. */
.digital-record[data-entity-type="support_material"][data-record-context="repository"] .ed-shell{border-radius:20px;box-shadow:var(--shadow-soft)}
.digital-record[data-entity-type="support_material"][data-record-context="repository"] .ed-header{background:linear-gradient(180deg,color-mix(in srgb,var(--surface) 94%,var(--primary-soft)),var(--surface))}
.digital-record[data-entity-type="support_material"][data-record-context="repository"] .ed-files-panel{border-radius:16px;box-shadow:0 10px 24px rgba(15,23,42,.05)}
.digital-record[data-entity-type="support_material"][data-record-context="repository"] .ed-files-panel>header{background:var(--surface-soft)}
.digital-record[data-entity-type="support_material"][data-record-context="repository"] .ed-document-row{border:1px solid transparent;border-radius:12px}
.digital-record[data-entity-type="support_material"][data-record-context="repository"] .ed-document-row:hover{border-color:color-mix(in srgb,var(--primary) 20%,var(--line));background:color-mix(in srgb,var(--primary) 5%,var(--surface))}
.digital-record[data-entity-type="support_material"][data-record-context="repository"] .ed-viewer{overflow:hidden;border:1px solid var(--line);border-radius:16px;background:var(--surface)}
.digital-record[data-entity-type="support_material"][data-record-context="repository"] .ed-viewer-head{padding:16px 18px;border-bottom:1px solid var(--line);background:var(--surface)}
.digital-record[data-entity-type="support_material"][data-record-context="repository"] .ed-viewer-body{background:var(--surface-soft)}
.digital-record[data-entity-type="support_material"][data-record-context="repository"] .ed-zip-tree{margin:0 8px 8px;padding:8px;border:1px solid var(--line);border-radius:12px;background:var(--surface-soft);overflow:auto;overscroll-behavior:contain}
.digital-record[data-entity-type="support_material"][data-record-context="repository"] .ed-zip-tree-row{border-radius:8px}
.digital-record[data-entity-type="support_material"][data-record-context="repository"] .ed-zip-tree-row:hover,.digital-record[data-entity-type="support_material"][data-record-context="repository"] .ed-zip-tree-row.is-selected{background:color-mix(in srgb,var(--primary) 9%,var(--surface));color:var(--primary)}
.digital-record[data-entity-type="support_material"][data-record-context="repository"] .ed-viewer-state{background:var(--surface-soft)}
body.dark-mode .digital-record[data-entity-type="support_material"][data-record-context="repository"] .ed-header{background:var(--surface)}
body.dark-mode .digital-record[data-entity-type="support_material"][data-record-context="repository"] .ed-document-row:hover,body.dark-mode .digital-record[data-entity-type="support_material"][data-record-context="repository"] .ed-zip-tree-row:hover{background:rgba(96,165,250,.1)}
@media(max-width:600px){.digital-record[data-entity-type="support_material"][data-record-context="repository"] .ed-shell{border-radius:16px}.digital-record[data-entity-type="support_material"][data-record-context="repository"] .ed-viewer-head{padding:14px}.digital-record[data-entity-type="support_material"][data-record-context="repository"] .ed-zip-tree{margin:0 5px 6px;max-height:46vh}.digital-record[data-entity-type="support_material"][data-record-context="repository"] .ed-files-panel{border-radius:14px}}
</style>
<?php endif; ?>
