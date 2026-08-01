<?php

declare(strict_types=1);

final class SupportMaterialController
{
    private const ALLOWED_EXTENSIONS = ['pdf', 'docx', 'xlsx', 'pptx', 'png', 'jpg', 'jpeg', 'webp', 'txt', 'zip'];

    // Inicio de presentación de materiales
    // Prepara el catálogo y el detalle con contadores, rutas y datos listos para sus vistas.
    public function index(): void
    {
        $this->ensureSession();
        $downloadModel = new SupportMaterialDownloadModel();
        $materials = array_map(static function (array $material) use ($downloadModel): array {
            $material['downloads'] = $downloadModel->getTotal($material['id'], $material['downloads']);
            $material['detail_url'] = base_url('index.php?page=support-material-detail&id=' . rawurlencode((string) $material['id']));
            return $material;
        }, (new SupportMaterialModel())->getAll());

        View::render('repository/materiales', [
            'currentPage' => 'repository',
            'title' => 'Material de apoyo | Repositorio Institucional',
            'pageScript' => asset('js/support-materials.js'),
            'materials' => $materials,
            'repositoryUrl' => route('repository'),
        ]);
    }

    public function detail(): void
    {
        $this->ensureSession();
        $session = new AuthSessionService();
        $isAdministrator = $session->hasAdminAccess();
        $materialId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        $materialModel = new SupportMaterialModel();
        $material = $materialId === false || $materialId === null ? null : $materialModel->findById((int) $materialId, $isAdministrator);
        $materialCategories = [];
        $restorableFiles = [];
        $documentEvolution = [];
        $hasUnreadAdministrativeActivity = false;
        if ($material === null) {
            http_response_code(404);
        } else {
            $material['downloads'] = (new SupportMaterialDownloadModel())->getTotal($material['id'], $material['downloads']);
            $material['information_updated_at'] = $materialModel->lastInformationUpdateAt((int) $material['id']);
            $material['editable_keywords'] = $materialModel->normalizeExistingKeywords((array) ($material['keywords'] ?? []));
            $packageService = new SupportMaterialPackageService();
            $material['package_descriptor'] = $packageService->describe($material);
            if (!empty($material['package_descriptor']['available'])) {
                $preparedPackage = null;
                try {
                    $preparedPackage = $packageService->prepare($material);
                    $material['package_descriptor']['size_bytes'] = (int) ($preparedPackage['size_bytes']
                        ?? filesize((string) $preparedPackage['path'])
                        ?: 0);
                    $material['package_descriptor']['size'] = ArchiveService::formatBytes(
                        (int) $material['package_descriptor']['size_bytes']
                    );
                    $material['package_descriptor']['source'] = (string) $preparedPackage['source'];
                } catch (Throwable $exception) {
                    error_log('Support material package prewarm: ' . $exception->getMessage());
                    $material['package_descriptor']['available'] = false;
                } finally {
                    if (is_array($preparedPackage) && !empty($preparedPackage['temporary'])
                        && is_file((string) ($preparedPackage['path'] ?? ''))) {
                        @unlink((string) $preparedPackage['path']);
                    }
                }
            }
            $documentEvolution = $materialModel->documentEvolution((int) $material['id']);
            if ($isAdministrator) {
                $materialCategories = $materialModel->categories();
                $restorableFiles = $materialModel->restorableFiles((int) $material['id']);
                $hasUnreadAdministrativeActivity = (new AdminActivityModel())->hasUnreadSupportMaterialEvents(
                    (int) $session->userId(), (int) $material['id']
                );
            }
        }

        View::render('repository/material-detalle', [
            'currentPage' => 'repository',
            'title' => $material === null ? 'Material no encontrado | Repositorio' : $material['title'] . ' | Material de apoyo',
            'pageScript' => asset('js/material-admin-actions.js'),
            'pageScripts' => [asset('js/repository-detail.js')],
            'material' => $material,
            'repositoryUrl' => route('repository'),
            'materialsUrl' => route('support-materials'),
            'previewActionUrl' => route('support-material-preview'),
            'previewContentActionUrl' => route('support-material-preview-content'),
            'downloadActionUrl' => route('support-material-download'),
            'zipListActionUrl' => route('support-material-zip-list'),
            'zipEntryPreviewActionUrl' => route('support-material-zip-entry-preview'),
            'zipEntryDownloadActionUrl' => route('support-material-zip-entry-download'),
            'packageDownloadActionUrl' => route('support-material-package-download'),
            'isAdministrator' => $isAdministrator,
            'materialEditUrl' => $material === null ? '' : route('support-material-detail') . '&id=' . (int) $material['id'] . '&mode=edit&tab=information',
            'materialSaveEndpoint' => route('admin-support-material-save'),
            'materialFileEndpoint' => $isAdministrator ? route('admin-support-material-file') : '',
            'materialStatusEndpoint' => $isAdministrator ? route('admin-support-material-status') : '',
            'materialHistoryEndpoint' => $isAdministrator ? route('admin-support-material-history') . '&id=' . (int) ($material['id'] ?? 0) : '',
            'materialHistoryCleanupEndpoint' => $isAdministrator ? route('admin-support-material-history-cleanup') : '',
            'materialCsrfToken' => $isAdministrator ? $session->csrfToken('admin_repository') : '',
            'materialCategories' => $materialCategories,
            'materialFileLimits' => $isAdministrator ? (new SupportMaterialFileService())->limits() : [],
            'restorableFiles' => $restorableFiles,
            'documentEvolution' => $documentEvolution,
            'hasUnreadAdministrativeActivity' => $hasUnreadAdministrativeActivity,
            'versionPreviewActionUrl' => route('support-material-version-preview'),
            'versionDownloadActionUrl' => route('support-material-version-download'),
        ]);
    }
    // Final de presentación de materiales

    // Inicio de vistas previas seguras
    // Clasifica el archivo y transmite en línea únicamente los formatos autorizados.
    public function preview(): void
    {
        $this->ensureGetJson();
        [$material, $file, $stream] = $this->resolveFileRequest(true);
        $fileData = $this->buildPreviewFile($file, $stream);
        $materialId = rawurlencode((string) $material['id']);
        $fileId = rawurlencode((string) $file['id']);
        $preview = (new FilePreviewService())->prepare(
            $fileData,
            base_url("index.php?page=support-material-preview-content&material_id={$materialId}&file_id={$fileId}"),
            base_url("index.php?page=support-material-download&material_id={$materialId}&file_id={$fileId}")
        );
        fclose($stream);
        $this->sendJson(true, $preview['message'], ['preview' => $preview]);
    }


    public function previewContent(): void
    {
        $this->ensureSession();
        $this->ensureGet();
        [, $file, $stream] = $this->resolveFileRequest();
        $fileData = $this->buildPreviewFile($file, $stream);
        if (!(new FilePreviewService())->canStreamInline($fileData)) {
            fclose($stream);
            http_response_code(415);
            $this->renderError('Este formato debe descargarse para consultarlo.');
        }

        session_write_close();
        header('Content-Type: ' . $this->mimeFor($file['extension']));
        header('Content-Length: ' . $file['size_bytes']);
        header('Content-Disposition: inline; filename*=UTF-8\'\'' . rawurlencode($file['name']));
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'");
        header('Cache-Control: private, no-store, max-age=0');
        fpassthru($stream);
        fclose($stream);
        exit;
    }

    public function versionPreview(): void
    {
        $this->ensureGetJson();
        [$material, $version, $stream] = $this->resolveHistoricalVersionRequest(true);
        $fileData = $this->buildPreviewFile($version, $stream);
        $query = '&material_id=' . rawurlencode((string) $material['id'])
            . '&file_id=' . rawurlencode((string) $version['file_id'])
            . '&version_id=' . rawurlencode((string) $version['id']);
        $preview = (new FilePreviewService())->prepare(
            $fileData,
            base_url('index.php?page=support-material-version-content' . $query),
            base_url('index.php?page=support-material-version-download' . $query)
        );
        fclose($stream);
        $this->sendJson(true, $preview['message'], ['preview' => $preview]);
    }

    public function versionContent(): void
    {
        $this->ensureSession();
        $this->ensureGet();
        [, $version, $stream] = $this->resolveHistoricalVersionRequest();
        $fileData = $this->buildPreviewFile($version, $stream);
        if (!(new FilePreviewService())->canStreamInline($fileData)) {
            fclose($stream);
            http_response_code(415);
            $this->renderError('Este formato debe descargarse para consultarlo.');
        }
        session_write_close();
        header('Content-Type: ' . $this->mimeFor($version['extension']));
        header('Content-Length: ' . $version['size_bytes']);
        header('Content-Disposition: inline; filename*=UTF-8\'\'' . rawurlencode($version['name']));
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'");
        header('Cache-Control: private, no-store, max-age=0');
        fpassthru($stream);
        fclose($stream);
        exit;
    }

    public function versionDownload(): void
    {
        $this->ensureSession();
        $this->ensureGet();
        [, $version, $stream] = $this->resolveHistoricalVersionRequest();
        session_write_close();
        $fallbackName = preg_replace('/[^A-Za-z0-9._-]/', '_', $version['name']) ?: 'version';
        header('Content-Type: ' . $this->mimeFor($version['extension']));
        header('Content-Length: ' . $version['size_bytes']);
        header('Content-Disposition: attachment; filename="' . $fallbackName . '"; filename*=UTF-8\'\'' . rawurlencode($version['name']));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, max-age=0');
        fpassthru($stream);
        fclose($stream);
        exit;
    }

    public function zipList(): void
    {
        $this->ensureGetJson();
        [$material, $file, $stream] = $this->resolveFileRequest(true);
        fclose($stream);
        if ($file['extension'] !== 'zip') {
            http_response_code(422);
            $this->sendJson(false, 'El archivo seleccionado no es un paquete navegable.');
        }
        $packageDescription = (new SupportMaterialPackageService())->describe($material);
        if (!empty($file['package']) && empty($packageDescription['browsable'])) {
            http_response_code(404);
            $this->sendJson(false, 'Este paquete no contiene recursos adicionales navegables.');
        }
        $archive = (new ArchiveService())->listDirectory($file['path'], (string) ($_GET['path'] ?? ''));
        if (!$archive['success']) {
            $status = match ($archive['status']) {
                'not_found' => 404,
                'invalid_path' => 400,
                'unsafe' => 422,
                default => 422,
            };
            http_response_code($status);
            $this->sendJson(false, $archive['message'], ['archive' => $archive]);
        }
        $this->sendJson(true, $archive['message'], ['archive' => $archive]);
    }

    public function zipEntryPreview(): void
    {
        $this->ensureGetJson();
        [$material, $zipFile, $entry] = $this->resolveZipEntryRequest(true);
        $query = '&material_id=' . rawurlencode((string) $material['id'])
            . '&file_id=' . rawurlencode((string) $zipFile['id'])
            . '&path=' . rawurlencode((string) $entry['path']);
        $preview = (new FilePreviewService())->prepare(
            $entry,
            base_url('index.php?page=support-material-zip-entry-content' . $query),
            base_url('index.php?page=support-material-zip-entry-download' . $query)
        );
        $this->closeArchiveEntry($entry);
        $this->sendJson(true, $preview['message'], ['preview' => $preview]);
    }

    public function zipEntryContent(): void
    {
        $this->ensureSession();
        $this->ensureGet();
        [, , $entry] = $this->resolveZipEntryRequest();
        if (!(new FilePreviewService())->canStreamInline($entry)) {
            $this->closeArchiveEntry($entry);
            http_response_code(415);
            $this->renderError('Este formato no puede visualizarse dentro de la plataforma.');
        }
        session_write_close();
        header('Content-Type: ' . $entry['mime']);
        header('Content-Length: ' . $entry['size']);
        header('Content-Disposition: inline; filename*=UTF-8\'\'' . rawurlencode($entry['name']));
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'");
        header('Cache-Control: private, no-store, max-age=0');
        fpassthru($entry['stream']);
        $this->closeArchiveEntry($entry);
        exit;
    }

    public function zipEntryDownload(): void
    {
        $this->ensureSession();
        $this->ensureGet();
        [, , $entry] = $this->resolveZipEntryRequest();
        session_write_close();
        $originalName = basename(str_replace('\\', '/', (string) $entry['name']));
        $quotedName = str_replace(['\\', '"'], ['\\\\', '\\"'], preg_replace('/[\x00-\x1F\x7F]/u', '', $originalName) ?: 'archivo');
        header('Content-Type: ' . $entry['mime']);
        header('Content-Length: ' . $entry['size']);
        header('Content-Disposition: attachment; filename="' . $quotedName . '"; filename*=UTF-8\'\'' . rawurlencode($originalName));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, max-age=0');
        fpassthru($entry['stream']);
        $this->closeArchiveEntry($entry);
        exit;
    }

    public function downloadPackage(): void
    {
        $this->ensureSession();
        $this->ensureGet();
        $materialId = filter_var($_GET['material_id'] ?? $_GET['id'] ?? null, FILTER_VALIDATE_INT);
        $material = $materialId === false || $materialId === null
            ? null
            : (new SupportMaterialModel())->findById((int) $materialId);
        if ($material === null) {
            http_response_code(404);
            $this->renderError('El material solicitado no está disponible.');
        }
        if (empty($material['is_available'])) {
            http_response_code(404);
            $this->renderError('El material está publicado, pero temporalmente no está disponible para consulta o descarga.');
        }
        try {
            $package = (new SupportMaterialPackageService())->prepare($material);
            $stream = fopen($package['path'], 'rb');
            $size = filesize($package['path']);
            if ($stream === false || $size === false) throw new RuntimeException('No fue posible abrir el paquete.');
            if (!empty($package['temporary'])) {
                $temporaryPath = $package['path'];
                register_shutdown_function(static function () use ($temporaryPath): void {
                    if (is_file($temporaryPath)) @unlink($temporaryPath);
                });
            }
            (new SupportMaterialDownloadModel())->increment((int) $material['id']);
            session_write_close();
            $name = 'material_' . (int) $material['id'] . '.zip';
            header('Content-Type: application/zip');
            header('Content-Length: ' . $size);
            header('Content-Disposition: attachment; filename="' . $name . '"');
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: private, no-store, max-age=0');
            fpassthru($stream);
            fclose($stream);
            if (!empty($package['temporary']) && is_file($package['path'])) @unlink($package['path']);
            exit;
        } catch (InvalidArgumentException $exception) {
            http_response_code(404);
            $this->renderError($exception->getMessage());
        } catch (Throwable $exception) {
            error_log('Support material package: ' . $exception->getMessage());
            http_response_code(422);
            $this->renderError('No fue posible generar el paquete completo porque uno o más archivos no están disponibles. No se descargó un paquete incompleto.');
        }
    }
    // Final de vistas previas seguras

    // Inicio de descarga de materiales
    // Valida el recurso, actualiza el contador correspondiente y transmite el archivo solicitado.
    public function download(): void
    {
        $this->ensureSession();
        $this->ensureGet();
        [$material, $file, $stream] = $this->resolveFileRequest();
        if ($file['presentation'] || ($file['package'] ?? false)) {
            (new SupportMaterialDownloadModel())->increment($material['id']);
        }
        session_write_close();
        $fallbackName = preg_replace('/[^A-Za-z0-9._-]/', '_', $file['name']) ?: 'documento';
        header('Content-Type: ' . $this->mimeFor($file['extension']));
        header('Content-Length: ' . $file['size_bytes']);
        header('Content-Disposition: attachment; filename="' . $fallbackName . '"; filename*=UTF-8\'\'' . rawurlencode($file['name']));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, max-age=0');
        fpassthru($stream);
        fclose($stream);
        exit;
    }
    // Final de descarga de materiales

    // Inicio de resolución segura de archivos
    // Comprueba identificadores, extensiones, ubicación privada, MIME y apertura del flujo de lectura.
    private function resolveFileRequest(bool $jsonResponse = false): array
    {
        $materialId = filter_var($_GET['material_id'] ?? $_GET['id'] ?? null, FILTER_VALIDATE_INT);
        $fileId = filter_var($_GET['file_id'] ?? $_GET['path'] ?? null, FILTER_VALIDATE_INT);
        $model = new SupportMaterialModel();
        $material = $materialId === false || $materialId === null ? null : $model->findById((int) $materialId);
        if ($material === null) {
            $this->failFileRequest(404, 'El material solicitado no está disponible.', $jsonResponse);
        }
        if (empty($material['is_available'])) {
            $this->failFileRequest(404, 'El material está publicado, pero temporalmente no está disponible para consulta o descarga.', $jsonResponse);
        }
        $file = $fileId === false || $fileId === null ? null : $model->findFile($material, (int) $fileId);
        if ($file === null || !in_array($file['extension'], self::ALLOWED_EXTENSIONS, true)) {
            $this->failFileRequest(404, 'El archivo solicitado no está disponible.', $jsonResponse);
        }

        $basePath = realpath(ROOT_PATH . '/storage/support-materials');
        $realPath = realpath($file['path']);
        if ($basePath === false || $realPath === false || !str_starts_with(strtolower($realPath), strtolower($basePath . DIRECTORY_SEPARATOR)) || !is_readable($realPath)) {
            error_log('Support material file unavailable material=' . (int) $material['id'] . ' file=' . (int) $file['id']);
            $this->failFileRequest(404, 'El archivo solicitado no está disponible.', $jsonResponse);
        }
        $stream = fopen($realPath, 'rb');
        if ($stream === false) {
            $this->failFileRequest(422, 'No fue posible abrir el archivo solicitado.', $jsonResponse);
        }
        return [$material, $file, $stream];
    }

    private function buildPreviewFile(array $file, $stream): array
    {
        return ['name' => $file['name'], 'path' => (string) $file['id'], 'size' => $file['size_bytes'], 'mime' => $this->mimeFor($file['extension']), 'stream' => $stream, 'archive' => null];
    }

    private function resolveZipEntryRequest(bool $jsonResponse = false): array
    {
        [$material, $zipFile, $zipStream] = $this->resolveFileRequest($jsonResponse);
        fclose($zipStream);
        if (($zipFile['extension'] ?? '') !== 'zip') {
            $this->failFileRequest(422, 'El archivo seleccionado no es un paquete navegable.', $jsonResponse);
        }
        $entry = (new ArchiveService())->openFileStream($zipFile['path'], (string) ($_GET['path'] ?? ''));
        if (empty($entry['success'])) {
            $status = match ($entry['status'] ?? '') {
                'invalid_path' => 400,
                'not_found' => 404,
                default => 422,
            };
            $this->failFileRequest($status, (string) ($entry['message'] ?? 'No fue posible abrir el archivo interno.'), $jsonResponse);
        }
        return [$material, $zipFile, $entry];
    }

    private function resolveHistoricalVersionRequest(bool $jsonResponse = false): array
    {
        $materialId = filter_var($_GET['material_id'] ?? null, FILTER_VALIDATE_INT);
        $fileId = filter_var($_GET['file_id'] ?? null, FILTER_VALIDATE_INT);
        $versionId = filter_var($_GET['version_id'] ?? null, FILTER_VALIDATE_INT);
        if ($materialId === false || $fileId === false || $versionId === false
            || $materialId === null || $fileId === null || $versionId === null) {
            $this->failFileRequest(400, 'La versión solicitada no es válida.', $jsonResponse);
        }
        $model = new SupportMaterialModel();
        $material = $model->findById((int) $materialId);
        if ($material === null) {
            $this->failFileRequest(404, 'El material solicitado no está disponible.', $jsonResponse);
        }
        $version = $model->findHistoricalVersion((int) $materialId, (int) $fileId, (int) $versionId);
        if ($version === null) {
            $this->failFileRequest(
                404,
                'Esta versión ya no se encuentra disponible en el sistema.',
                $jsonResponse
            );
        }
        $stream = fopen($version['path'], 'rb');
        if ($stream === false) {
            $this->failFileRequest(404, 'Esta versión ya no se encuentra disponible en el sistema.', $jsonResponse);
        }
        return [$material, $version, $stream];
    }

    private function closeArchiveEntry(array $entry): void
    {
        if (isset($entry['stream']) && is_resource($entry['stream'])) fclose($entry['stream']);
        if (isset($entry['archive']) && $entry['archive'] instanceof ZipArchive) $entry['archive']->close();
    }

    private function mimeFor(string $extension): string
    {
        return match ($extension) {
            'pdf' => 'application/pdf', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'png' => 'image/png', 'jpg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'txt' => 'text/plain; charset=UTF-8', 'zip' => 'application/zip',
            default => 'application/octet-stream',
        };
    }
    // Final de resolución segura de archivos

    // Inicio de validación de solicitudes y respuestas
    // Centraliza sesión, método HTTP y formatos de error o respuesta JSON del controlador.
    private function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    }

    private function ensureGet(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            http_response_code(405);
            $this->renderError('Método no permitido.');
        }
    }

    private function ensureGetJson(): void
    {
        $this->ensureSession();
        header('Content-Type: application/json; charset=UTF-8');
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            http_response_code(405);
            $this->sendJson(false, 'Método no permitido.');
        }
    }

    private function renderError(string $message): never
    {
        header('Content-Type: text/plain; charset=UTF-8');
        echo $message;
        exit;
    }

    private function failFileRequest(int $status, string $message, bool $jsonResponse): never
    {
        http_response_code($status);
        if ($jsonResponse) $this->sendJson(false, $message);
        $this->renderError($message);
    }

    private function sendJson(bool $success, string $message, array $data = []): never
    {
        echo json_encode(['success' => $success, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    // Final de validación de solicitudes y respuestas
}
