<?php

declare(strict_types=1);

final class RepositoryController
{
    /**
     * Renderiza el repositorio institucional de proyectos finalizados.
     */
    public function legacyIndex(): void
    {
        $this->ensureSession();
        if ((new AuthSessionService())->isAdminModeActive()) {
            header('Location: ' . route('admin-repository'));
            exit;
        }
        $session = new AuthSessionService();
        $repositoryModel = new RepositoryModel();
        $favoriteModel = new FavoriteModel();
        $downloadModel = new DownloadModel();
        $packageService = new ProjectRepositoryPackageService();
        $userId = $this->getCurrentUserId();
        $repositoryReturnUrl = (string) ($_SERVER['REQUEST_URI'] ?? route('repository'));
        $favoriteIds = $favoriteModel->getFavoriteIds($userId);
        $projects = array_map(static function (array $project) use ($favoriteIds, $downloadModel, $repositoryReturnUrl, $packageService): array {
            $project['is_favorite'] = in_array($project['id'], $favoriteIds, true);
            $project['downloads'] = $downloadModel->getTotal($project['id'], $project['downloads']);
            $project['detail_url'] = base_url('index.php?page=repository-detail&id=' . urlencode((string) $project['id']) . '&return=' . rawurlencode($repositoryReturnUrl));
            try {
                $package = $packageService->describe(
                    (int) $project['id'],
                    route('repository-download') . '&id=' . (int) $project['id']
                );
                $project['package_available'] = !empty($package['available']);
                $project['package_download_url'] = (string) ($package['download_url'] ?? '');
            } catch (Throwable $exception) {
                error_log('Repository package descriptor: ' . $exception->getMessage());
                $project['package_available'] = false;
                $project['package_download_url'] = '';
            }
            return $project;
        }, $repositoryModel->getPublishedProjects());

        View::render('repository/repositorio', [
            'currentPage' => 'repository',
            'title' => 'Repositorio Institucional | Gestión Documental Académica',
            'pageStyles' => [asset('css/repository-reader.css')],
            'pageScript' => asset('js/repository.js'),
            'pageScripts' => $this->teacherMaterialUi($session) ? [asset('js/teacher-support-materials.js')] : [],
            'projects' => $projects,
            'favoriteActionUrl' => route('repository-favorite'),
            'favoriteCsrfToken' => $this->getFavoriteCsrfToken(),
            'semesters' => $repositoryModel->getSemesters(),
            'teachers' => $repositoryModel->getTeachers(),
            'categories' => $repositoryModel->getCategories(),
            'projectTypes' => $repositoryModel->getProjectTypes(),
            'academicPeriods' => $repositoryModel->getAcademicPeriods(),
            'supportDocuments' => array_map(static function (array $material): array {
                $material['detail_url'] = base_url('index.php?page=support-material-detail&id=' . rawurlencode((string) $material['id']));
                return $material;
            }, $this->teacherMaterialUi($session) ? (new SupportMaterialModel())->getForTeacherManagement((int) $session->userId()) : (new SupportMaterialModel())->getAll()),
            'supportMaterialsUrl' => route('support-materials'),
            'canCreateSupportMaterial' => $this->teacherMaterialUi($session),
            'supportMaterialCategories' => $this->teacherMaterialUi($session) ? (new SupportMaterialModel())->categories() : [],
            'supportMaterialTypes' => $this->teacherMaterialUi($session) ? (new SupportMaterialModel())->materialTypeCatalog() : [],
            'supportMaterialManageFileEndpoint' => $this->teacherMaterialUi($session) ? route('support-material-manage-file') : '',
            'supportMaterialManageSaveEndpoint' => $this->teacherMaterialUi($session) ? route('support-material-manage-save') : '',
            'supportMaterialCsrf' => $this->teacherMaterialUi($session) ? $session->csrfToken('admin_repository') : '',
        ]);
    }

    public function index(): void
    {
        $this->ensureSession();
        $session = new AuthSessionService();
        if ($session->isAdminModeActive()) {
            header('Location: ' . route('admin-repository'));
            exit;
        }

        $repositoryModel = new RepositoryModel();
        $projectTypes = [];
        $academicPeriods = [];
        $categories = [];
        $supportMaterialCategories = [];
        $supportMaterialTypes = [];
        $supportMaterialKeywords = [];
        $result = [
            'status' => 'error', 'items' => [], 'total' => 0,
            'pagination' => ['page'=>1,'per_page'=>10,'page_size'=>10,'total'=>0,'pages'=>1,'from'=>0,'to'=>0,'page_key'=>'page','size_key'=>'page_size'],
            'filters' => ['search'=>'','type'=>'all','period'=>'all','period_code'=>''],
            'message' => 'No fue posible consultar el repositorio en este momento.',
        ];
        $repositoryError = null;
        $supportDocuments = [];
        $supportStatus = 'error';
        $supportError = null;
        $teacherMaterialUi = false;
        $directProjectUi = false;
        $directProjectTypes = [];
        $directProjectKeywords = [];
        $directProjectPeriod = null;
        try {
            $projectTypes = $repositoryModel->getProjectTypes();
            $academicPeriods = $repositoryModel->getAcademicPeriods();
            $type = trim((string) ($_GET['type'] ?? 'all')) ?: 'all';
            $period = trim((string) ($_GET['period'] ?? 'all')) ?: 'all';
            if ($type !== 'all' && !in_array($type, array_column($projectTypes, 'value'), true)) $type = 'all';
            $periodByValue = [];
            foreach ($academicPeriods as $academicPeriod) $periodByValue[(string) $academicPeriod['value']] = (string) ($academicPeriod['code'] ?? '');
            if ($period !== 'all' && !array_key_exists($period, $periodByValue)) $period = 'all';
            $result = $repositoryModel->getPublishedProjectsResult([
                'search' => (string) ($_GET['search'] ?? ''), 'type' => $type, 'period' => $period,
                'period_code' => $period === 'all' ? '' : $periodByValue[$period],
            ], PaginationService::request('repository_page', 'page_size'));
            $repositoryError = $result['status'] === 'error' ? (string) $result['message'] : null;
        } catch (Throwable $exception) {
            error_log('Repository index: ' . $exception->getMessage());
            $repositoryError = 'No fue posible consultar el repositorio en este momento.';
            $result['status'] = 'error';
        }

        try {
            $supportModel = new SupportMaterialModel();
            $teacherMaterialUi = $this->teacherMaterialUi($session);
            $supportDocuments = $teacherMaterialUi
                ? $supportModel->getForTeacherManagement((int) $session->userId())
                : $supportModel->getAll();
            $supportStatus = $supportDocuments === [] ? 'empty' : 'loaded';
            try {
                $categories = $repositoryModel->getCategories();
                if ($teacherMaterialUi) {
                    $supportMaterialCategories = $supportModel->categories();
                    $supportMaterialTypes = $supportModel->materialTypeCatalog();
                    $supportMaterialKeywords = $supportModel->keywordCatalog();
                }
            } catch (Throwable $exception) {
                error_log('Repository support material catalogs: ' . $exception->getMessage());
            }
        } catch (Throwable $exception) {
            error_log('Repository support materials: ' . $exception->getMessage());
            $supportDocuments = [];
            $supportStatus = 'error';
            $supportError = 'No fue posible cargar los materiales de apoyo en este momento. Intenta nuevamente más tarde.';
        }

        try {
            $directProjectUi = (new ProjectCapabilityService())->canPublishDirectRepository($session);
            if ($directProjectUi) {
                $catalog = new AcademicCatalogService();
                $directProjectTypes = $catalog->directProjectTypes();
                $directProjectKeywords = $catalog->activeKeywords();
                $directProjectPeriod = $catalog->activePeriod();
            }
        } catch (Throwable $exception) {
            error_log('Repository direct project catalogs: ' . $exception->getMessage());
            $directProjectUi = false;
        }

        $favoriteIds = [];
        try { $favoriteIds = (new FavoriteModel())->getFavoriteIds($this->getCurrentUserId()); } catch (Throwable $exception) { error_log('Repository favorites: ' . $exception->getMessage()); }
        $repositoryReturnUrl = (string) ($_SERVER['REQUEST_URI'] ?? route('repository'));
        $packageService = new ProjectRepositoryPackageService();
        $projects = array_map(function (array $project) use ($favoriteIds, $repositoryReturnUrl, $packageService): array {
            $project['is_favorite'] = in_array($project['id'], $favoriteIds, true);
            $project['detail_url'] = base_url('index.php?page=repository-detail&id=' . urlencode((string) $project['id']) . '&return=' . rawurlencode($repositoryReturnUrl));
            $project['published_at_label'] = format_utc_datetime((string) ($project['published_at'] ?? ''), false);
            try {
                $package = $packageService->describe((int) $project['id'], route('repository-download') . '&id=' . (int) $project['id']);
                $project['package_available'] = !empty($package['available']);
                $project['package_download_url'] = (string) ($package['download_url'] ?? '');
            } catch (Throwable $exception) {
                error_log('Repository package descriptor: ' . $exception->getMessage());
                $project['package_available'] = false;
                $project['package_download_url'] = '';
            }
            return $project;
        }, (array) ($result['items'] ?? []));

        View::render('repository/repositorio', [
            'currentPage'=>'repository', 'title'=>'Repositorio Institucional | Gestión Documental Académica',
            'pageStyles'=>[asset('css/repository-reader.css')], 'pageScript'=>asset('js/repository.js'),
            'pageScripts'=>array_values(array_filter([
                $teacherMaterialUi ? asset('js/teacher-support-materials.js') : null,
                ($teacherMaterialUi || $directProjectUi) ? asset('js/teacher-repository-content.js') : null,
            ])),
            'projects'=>$projects, 'repositoryStatus'=>(string) ($result['status'] ?? 'error'),
            'repositoryError'=>$repositoryError, 'repositoryPagination'=>(array) ($result['pagination'] ?? []),
            'repositoryFilters'=>(array) ($result['filters'] ?? []), 'favoriteActionUrl'=>route('repository-favorite'),
            'favoriteCsrfToken'=>$this->getFavoriteCsrfToken(), 'semesters'=>$repositoryModel->getSemesters(),
            'teachers'=>$repositoryModel->getTeachers(), 'categories'=>$categories,
            'projectTypes'=>$projectTypes, 'academicPeriods'=>$academicPeriods,
            'supportDocuments'=>array_map(static function (array $material): array { $material['detail_url']=base_url('index.php?page=support-material-detail&id='.rawurlencode((string)$material['id'])); return $material; }, $supportDocuments),
            'supportStatus'=>$supportStatus, 'supportError'=>$supportError,
            'supportMaterialsUrl'=>route('support-materials'), 'canCreateSupportMaterial'=>$teacherMaterialUi,
            'canPublishDirectProject'=>$directProjectUi, 'canAddRepositoryContent'=>$teacherMaterialUi || $directProjectUi,
            'directProjectTypes'=>$directProjectTypes, 'directProjectKeywords'=>$directProjectKeywords,
            'directProjectPeriod'=>$directProjectPeriod,
            'directProjectEndpoint'=>route('repository-direct-project-publish'),
            'directProjectSearchEndpoint'=>route('repository-direct-project-search'),
            'directProjectCsrf'=>$directProjectUi ? $session->csrfToken('repository_direct_publish') : '',
            'supportMaterialCategories'=>$supportMaterialCategories,
            'supportMaterialTypes'=>$supportMaterialTypes,
            'supportMaterialKeywords'=>$supportMaterialKeywords,
            'supportMaterialManageFileEndpoint'=>$teacherMaterialUi ? route('support-material-manage-file') : '',
            'supportMaterialManageSaveEndpoint'=>$teacherMaterialUi ? route('support-material-manage-save') : '',
            'supportMaterialCsrf'=>$teacherMaterialUi ? $session->csrfToken('admin_repository') : '',
        ]);
    }

    private function teacherMaterialUi(AuthSessionService $session): bool
    {
        return !$session->isAdminModeActive() && (new SupportMaterialCapabilityService())->canCreate($session);
    }

    public function detail(): void
    {
        $this->ensureSession();
        $session = new AuthSessionService();
        $hasRealAdminAccess = $session->hasAdminAccess();
        $isAdministratorView = $session->isAdminModeActive() && $hasRealAdminAccess;
        $projectId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        $access = new ProjectAccessService();
        try {
            $project = ($projectId !== false && $projectId !== null && $access->can('project.view'))
                ? (new ProjectRecordModel())->find((int)$projectId, $access->currentUserId(), $isAdministratorView, true) : null;
        } catch (Throwable $exception) {
            error_log('Repository detail: ' . $exception->getMessage());
            http_response_code(500);
            $this->renderDetailError();
        }
        if ($project === null) http_response_code(404);
        $capabilityContext = $isAdministratorView ? 'academic_management' : 'repository';
        $projectCapabilities = $project !== null
            ? (new ProjectCapabilityService())->resolve($project, $capabilityContext, (int) ($session->userId() ?? 0), $access->currentRoles(), $isAdministratorView)
            : (new ProjectCapabilityService())->none();
        if ($project !== null && !empty($projectCapabilities['view_academic_history'])) {
            $academicPage = (new ProjectRecordModel())->academicHistoryPage((int)$project['id']);
            $project['academic_history'] = $academicPage['events'];
            $project['academic_history_total'] = $academicPage['total'];
        } elseif ($project !== null) {
            $project['academic_history'] = [];
            $project['academic_history_total'] = 0;
        }
        $requestedTab = strtolower(trim((string)($_GET['tab'] ?? 'information')));
        $activeTab = in_array($requestedTab, ['information', 'files', 'evolution'], true) ? $requestedTab : 'information';
        $returnUrl = $this->repositoryReturnUrl((string)($_GET['return'] ?? ''));
        $projectDocuments = null;
        if ($project !== null && !empty($projectCapabilities['manage_files'])) {
            $documentModel = new ProjectDocumentModel();
            $projectDocuments = [
                'context' => 'repository',
                'restorable' => $documentModel->restorable((int)$project['id']),
                'versions' => $documentModel->versions((int)$project['id']),
                'limits' => (new ProjectDocumentFileService())->limits(),
                'endpoint' => route('admin-project-file'),
                'csrf' => $session->csrfToken('admin_repository')
            ];
        }
        $adjustmentData = ['items' => [], 'summary' => ['has_pending_adjustments' => false, 'pending_count' => 0, 'latest' => null]];
        $adjustmentContext = $isAdministratorView ? 'academic_management' : 'repository';
        if ($project !== null && !empty($projectCapabilities['view_adjustment_requests'])) {
            try {
                $adjustmentData = (new ProjectAdjustmentRequestService())->listForProject(
                    (int) $project['id'],
                    (string) $project['status'],
                    (int) ($session->userId() ?? 0),
                    $adjustmentContext
                );
            } catch (Throwable $e) {
                error_log('Repository detail adjustment list: ' . $e->getMessage());
            }
        }
        $adjustmentEndpoints = [
            'create' => route('project-adjustment-create'),
            'respond' => route('project-adjustment-respond'),
            'address' => route('project-adjustment-address'),
            'close' => route('project-adjustment-close'),
        ];
        View::render('projects/detail', [
            'currentPage' => 'repository',
            'title' => $project === null ? 'Proyecto no encontrado | Repositorio' : $project['title'] . ' | Repositorio',
            'pageStyles' => array_values(array_filter([
                $isAdministratorView ? asset('css/admin-projects.css') : null,
                !empty($projectCapabilities['view_adjustment_requests']) ? asset('css/project-adjustments.css') : null,
            ])),
            'pageScript' => $isAdministratorView ? asset('js/admin-projects.js') : asset('js/repository-detail.js'),
            'pageScripts' => array_values(array_filter([
                $isAdministratorView ? asset('js/material-admin-actions.js') : null,
                !empty($projectCapabilities['view_adjustment_requests']) ? asset('js/project-adjustments.js') : null,
            ])),
            'project' => $project,
            'activeTab' => $activeTab,
            'isAdministrator' => $isAdministratorView,
            'publicContext' => true,
            'institutionalReadOnly' => !$isAdministratorView,
            'canReview' => false,
            'canDeliver' => false,
            'projectContext' => 'repository',
            'projectCapabilities' => $projectCapabilities,
            'adjustmentData' => $adjustmentData,
            'adjustmentCsrf' => $session->csrfToken('project_adjustment'),
            'adjustmentContext' => $adjustmentContext,
            'adjustmentEndpoints' => $adjustmentEndpoints,
            'projectEditUrl' => '',
            'detailUrl' => route('repository-detail') . '&id=' . (int)($project['id'] ?? 0),
            'returnUrl' => $returnUrl,
            'previewActionUrl' => route('project-file-preview') . '&scope=repository',
            'downloadActionUrl' => route('project-file-download') . '&scope=repository',
            'projectAdminEndpoint' => !empty($projectCapabilities['manage_publication']) ? route('admin-repository-publish') : '',
            'projectHistoryEndpoint' => !empty($projectCapabilities['view_admin_history']) ? route('admin-project-history') . '&id=' . (int)($project['id'] ?? 0) . '&context=repository' : '',
            'projectTrashEndpoint' => !empty($projectCapabilities['edit_information']) ? route('admin-project-trash') : '',
            'projectSaveEndpoint' => !empty($projectCapabilities['edit_information']) ? route('admin-project-save') : '',
            'projectAdminCsrf' => !empty($projectCapabilities['manage_publication']) ? $session->csrfToken('admin_repository') : '',
            'projectTrashCsrf' => !empty($projectCapabilities['edit_information']) ? $session->csrfToken('admin_projects') : '',
            'projectEditorCatalogs' => !empty($projectCapabilities['edit_information']) ? (new AdminProjectModel())->catalogs() : [],
            'projectDocuments' => $projectDocuments,
            'academicHistoryEndpoint' => !empty($projectCapabilities['view_academic_history']) ? route('project-academic-history-events') . '&project_id=' . (int)($project['id'] ?? 0) . '&context=repository' : '',
        ]);
    }

    private function repositoryReturnUrl(string $candidate): string
    {
        $session = new AuthSessionService();
        $fallback = $session->isAdminModeActive() ? route('admin-repository') : route('repository');

        if ($candidate === '') {
            return $fallback;
        }

        $parts = parse_url($candidate);
        if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) {
            return $fallback;
        }

        parse_str((string) ($parts['query'] ?? ''), $query);
        $page = strtolower((string) ($query['page'] ?? ''));

        if (in_array($page, ['repository', 'repositorio'], true)) {
            return $candidate;
        }

        if ($page === 'admin-repository' && $session->isAdminModeActive()) {
            return $candidate;
        }

        return $fallback;
    }

    public function files(): void
    {
        $this->ensureSession();
        header('Content-Type: application/json; charset=UTF-8');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            http_response_code(405);
            $this->sendJson(false, 'Método no permitido.');
        }

        $projectId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        $repositoryModel = new RepositoryModel();
        try {
            $project = $projectId === false || $projectId === null
                ? null
                : $repositoryModel->getPublishedProjectDetail((int) $projectId);
        } catch (Throwable $exception) {
            error_log('Repository files: ' . $exception->getMessage());
            http_response_code(500);
            $this->sendJson(false, 'No fue posible cargar el proyecto en este momento.');
        }

        if ($project === null) {
            http_response_code(404);
            $this->sendJson(false, 'El proyecto solicitado no está disponible.');
        }

        $this->ensureRepositoryPackage($project);
        $archiveState = (new ArchiveService())->listDirectory($project['archive']['path'], (string) ($_GET['path'] ?? ''));
        if (!$archiveState['success']) {
            http_response_code($archiveState['status'] === 'not_found' ? 404 : ($archiveState['status'] === 'invalid_path' ? 400 : 422));
            $this->sendJson(false, $archiveState['message'], ['archive' => $archiveState]);
        }

        $this->sendJson(true, $archiveState['message'], ['archive' => $archiveState]);
    }

    public function downloadProject(): void
    {
        $this->ensureSession();
        $project = $this->resolvePublishedProjectFromRequest();
        $zipPath = $project['archive']['path'];

        if (!is_file($zipPath) || !is_readable($zipPath)) {
            http_response_code(404);
            $this->renderDownloadError('El archivo del proyecto no se encuentra disponible.');
        }

        $fileSize = filesize($zipPath);
        if ($fileSize === false) {
            http_response_code(422);
            $this->renderDownloadError('No fue posible preparar la descarga del proyecto.');
        }

        session_write_close();
        $this->sendDownloadHeaders($project['archive']['name'], 'application/zip', $fileSize);
        readfile($zipPath);
        exit;
    }

    public function downloadFile(): void
    {
        $this->ensureSession();
        $project = $this->resolvePublishedProjectFromRequest();
        $download = (new ArchiveService())->openFileStream($project['archive']['path'], (string) ($_GET['path'] ?? ''));

        if (!$download['success']) {
            http_response_code($download['status'] === 'invalid_path' ? 400 : ($download['status'] === 'not_found' ? 404 : 422));
            $this->renderDownloadError($download['message']);
        }

        session_write_close();
        $this->sendDownloadHeaders($download['name'], $download['mime'], $download['size']);
        fpassthru($download['stream']);
        fclose($download['stream']);
        if (isset($download['archive']) && $download['archive'] instanceof ZipArchive) {
            $download['archive']->close();
        }
        exit;
    }

    public function preview(): void
    {
        $this->ensureSession();
        header('Content-Type: application/json; charset=UTF-8');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            http_response_code(405);
            $this->sendJson(false, 'Método no permitido.');
        }

        $project = $this->resolvePublishedProjectFromRequest();
        $path = (string) ($_GET['path'] ?? '');
        $file = (new ArchiveService())->openFileStream($project['archive']['path'], $path);
        if (!$file['success']) {
            http_response_code($file['status'] === 'invalid_path' ? 400 : ($file['status'] === 'not_found' ? 404 : 422));
            $this->sendJson(false, $file['message']);
        }

        $encodedProjectId = rawurlencode((string) $project['id']);
        $encodedPath = rawurlencode($file['path']);
        $contentUrl = base_url('index.php?page=repository-preview-content&id=' . $encodedProjectId . '&path=' . $encodedPath);
        $downloadUrl = base_url('index.php?page=repository-file-download&id=' . $encodedProjectId . '&path=' . $encodedPath);
        $preview = (new FilePreviewService())->prepare($file, $contentUrl, $downloadUrl);
        $this->closeArchiveFile($file);

        $this->sendJson(true, $preview['message'], ['preview' => $preview]);
    }

    public function previewContent(): void
    {
        $this->ensureSession();
        $project = $this->resolvePublishedProjectFromRequest();
        $file = (new ArchiveService())->openFileStream($project['archive']['path'], (string) ($_GET['path'] ?? ''));
        if (!$file['success']) {
            http_response_code($file['status'] === 'invalid_path' ? 400 : ($file['status'] === 'not_found' ? 404 : 422));
            $this->renderDownloadError($file['message']);
        }

        if (!(new FilePreviewService())->canStreamInline($file)) {
            $this->closeArchiveFile($file);
            http_response_code(415);
            $this->renderDownloadError('Este formato no puede visualizarse dentro de la plataforma.');
        }

        session_write_close();
        header('Content-Type: ' . $file['mime']);
        header('Content-Length: ' . $file['size']);
        header('Content-Disposition: inline; filename*=UTF-8\'\'' . rawurlencode($file['name']));
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'");
        header('Cache-Control: private, no-store, max-age=0');
        fpassthru($file['stream']);
        $this->closeArchiveFile($file);
        exit;
    }

    public function toggleFavorite(): void
    {
        $this->ensureSession();
        header('Content-Type: application/json; charset=UTF-8');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            $this->sendJson(false, 'Método no permitido.');
        }

        $submittedToken = (string) ($_POST['csrf_token'] ?? '');
        if ($submittedToken === '' || !hash_equals($this->getFavoriteCsrfToken(), $submittedToken)) {
            http_response_code(403);
            $this->sendJson(false, 'No fue posible validar la solicitud.');
        }

        $projectId = filter_var($_POST['project_id'] ?? null, FILTER_VALIDATE_INT);
        $repositoryModel = new RepositoryModel();
        if ($projectId === false || $projectId === null || $repositoryModel->findPublishedProjectById((int) $projectId) === null) {
            http_response_code(404);
            $this->sendJson(false, 'El proyecto solicitado no está disponible.');
        }

        $favoriteModel = new FavoriteModel();
        $userId = $this->getCurrentUserId();
        $isFavorite = $favoriteModel->toggle($userId, (int) $projectId);

        $this->sendJson(true, $isFavorite ? 'Proyecto guardado en favoritos.' : 'Proyecto eliminado de favoritos.', [
            'favorite' => $isFavorite,
            'favoritesCount' => $favoriteModel->count($userId),
        ]);
    }

    public function searchDirectProjectPeople(): void
    {
        $this->ensureSession();
        header('Content-Type: application/json; charset=UTF-8');
        $session = new AuthSessionService();
        if (!$this->directProjectCapability($session)) {
            http_response_code(403);
            $this->sendJson(false, 'No tienes autorización para consultar este catálogo.');
        }
        $kind = strtolower(trim((string) ($_GET['kind'] ?? '')));
        $search = trim((string) ($_GET['q'] ?? ''));
        if (!in_array($kind, ['students','tutors'], true) || mb_strlen($search) < 2) {
            $this->sendJson(true, '', ['items'=>[]]);
        }
        try {
            $items = (new AcademicCatalogService())->directProjectPeople($kind, $search);
            $this->sendJson(true, '', ['items'=>$items]);
        } catch (Throwable $exception) {
            error_log('Repository direct project search: ' . $exception->getMessage());
            http_response_code(500);
            $this->sendJson(false, 'No fue posible consultar las personas disponibles.');
        }
    }

    /** Publica directamente un proyecto en Repository; no representa una transición académica. */
    public function publishDirectProject(): void
    {
        $this->ensureSession();
        header('Content-Type: application/json; charset=UTF-8');
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            $this->sendJson(false, 'Método no permitido.');
        }
        $session = new AuthSessionService();
        if (!$this->directProjectCapability($session)) {
            http_response_code(403);
            $this->sendJson(false, 'No tienes autorización para publicar proyectos directamente en el repositorio.');
        }
        if (!$session->validateCsrf('repository_direct_publish', (string) ($_POST['_csrf'] ?? ''))) {
            http_response_code(419);
            $this->sendJson(false, 'La sesión del formulario expiró. Recarga la página e inténtalo nuevamente.');
        }
        $input = [
            'title' => $_POST['title'] ?? '',
            'project_type_id' => $_POST['project_type_id'] ?? null,
            'description' => $_POST['description'] ?? '',
            'author_ids' => $_POST['author_ids'] ?? [],
            'tutoring_user_ids' => $_POST['tutoring_user_ids'] ?? [],
            'tutoring_primary_id' => $_POST['tutoring_primary_id'] ?? null,
            'tutor_id' => $_POST['tutor_id'] ?? null,
            'keyword_ids' => $_POST['keyword_ids'] ?? [],
        ];
        try {
            $result = (new RepositoryDirectProjectService())->publish(
                (int) ($session->userId() ?? 0),
                $input,
                (array) ($_FILES['files'] ?? []),
                (string) ($_POST['idempotency_token'] ?? '')
            );
            $this->sendJson(true, 'Proyecto publicado correctamente en el repositorio.', $result);
        } catch (RepositoryDirectProjectException $exception) {
            http_response_code($exception->status);
            $this->sendJson(false, $exception->getMessage(), ['errors' => $exception->errors]);
        } catch (Throwable $exception) {
            error_log('Repository direct publish endpoint: ' . $exception->getMessage());
            http_response_code(500);
            $this->sendJson(false, 'No fue posible publicar el proyecto en este momento.');
        }
    }

    private function directProjectCapability(AuthSessionService $session): bool
    {
        return (new ProjectCapabilityService())->canPublishDirectRepository($session);
    }

    private function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    private function getCurrentUserId(): string
    {
        if (!isset($_SESSION['repository_demo_user_id'])) {
            $_SESSION['repository_demo_user_id'] = 'demo-user-' . session_id();
        }

        return (string) $_SESSION['repository_demo_user_id'];
    }

    private function getFavoriteCsrfToken(): string
    {
        if (!isset($_SESSION['repository_favorite_csrf'])) {
            $_SESSION['repository_favorite_csrf'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['repository_favorite_csrf'];
    }

    private function resolvePublishedProjectFromRequest(): array
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            http_response_code(405);
            $this->renderDownloadError('Método no permitido.');
        }

        $projectId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        try {
            $project = $projectId === false || $projectId === null
                ? null
                : (new RepositoryModel())->getPublishedProjectDetail((int) $projectId);
        } catch (Throwable $exception) {
            error_log('Repository project resource: ' . $exception->getMessage());
            http_response_code(500);
            $this->renderDownloadError('No fue posible cargar el proyecto en este momento.');
        }

        if ($project === null) {
            http_response_code(404);
            $this->renderDownloadError('El proyecto solicitado no está disponible.');
        }

        $this->ensureRepositoryPackage($project);
        return $project;
    }

    private function ensureRepositoryPackage(array $project): void
    {
        try {
            $descriptor = (new ProjectRepositoryPackageService())->describe((int) ($project['id'] ?? 0));
        } catch (Throwable $exception) {
            error_log('Repository package validation: ' . $exception->getMessage());
            http_response_code(503);
            $this->renderDownloadError('El paquete del proyecto no está disponible en este momento.');
        }

        if (empty($descriptor['available'])) {
            http_response_code(409);
            $this->renderDownloadError('El paquete del proyecto no está disponible o necesita actualización.');
        }
    }

    private function sendDownloadHeaders(string $fileName, string $mimeType, int $fileSize): void
    {
        $fallbackName = preg_replace('/[^A-Za-z0-9._-]/', '_', $fileName) ?: 'archivo';
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . $fileSize);
        header('Content-Disposition: attachment; filename="' . $fallbackName . '"; filename*=UTF-8\'\'' . rawurlencode($fileName));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, max-age=0');
    }

    private function renderDetailError(): never
    {
        $safeBackUrl = e($this->repositoryReturnUrl((string) ($_GET['return'] ?? '')));
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Proyecto no disponible</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f4f7fb;color:#17233d;font:16px system-ui,sans-serif}.card{max-width:560px;margin:24px;padding:32px;border:1px solid #dfe6f1;border-radius:18px;background:#fff;box-shadow:0 18px 45px rgba(23,35,61,.12);text-align:center}.card h1{margin:0 0 12px;font-size:1.4rem}.card p{margin:0 0 24px;color:#5c6b84;line-height:1.55}.card a{display:inline-flex;padding:11px 18px;border-radius:10px;background:#2563eb;color:#fff;text-decoration:none;font-weight:700}.card a:focus-visible{outline:3px solid #93c5fd;outline-offset:3px}</style></head><body><main class="card" role="alert"><h1>No fue posible cargar el proyecto</h1><p>Ocurrió un problema temporal. Inténtalo nuevamente o vuelve al repositorio.</p><a href="' . $safeBackUrl . '">Volver al repositorio</a></main></body></html>';
        exit;
    }

    private function closeArchiveFile(array $file): void
    {
        if (isset($file['stream']) && is_resource($file['stream'])) {
            fclose($file['stream']);
        }
        if (isset($file['archive']) && $file['archive'] instanceof ZipArchive) {
            $file['archive']->close();
        }
    }

    private function renderDownloadError(string $message): never
    {
        $candidate = (string) ($_GET['return'] ?? '');
        $backUrl = route('repository');
        $parts = $candidate === '' ? false : parse_url($candidate);
        if (is_array($parts) && !isset($parts['scheme']) && !isset($parts['host'])) {
            parse_str((string) ($parts['query'] ?? ''), $query);
            if (in_array(strtolower((string) ($query['page'] ?? '')), ['repository', 'repositorio', 'admin-repository'], true)) {
                $backUrl = $candidate;
            }
        }
        $safeMessage = e($message);
        $safeBackUrl = e($backUrl);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Descarga no disponible</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f4f7fb;color:#17233d;font:16px system-ui,sans-serif}.card{max-width:560px;margin:24px;padding:32px;border:1px solid #dfe6f1;border-radius:18px;background:#fff;box-shadow:0 18px 45px rgba(23,35,61,.12);text-align:center}.card h1{margin:0 0 12px;font-size:1.4rem}.card p{margin:0 0 24px;color:#5c6b84;line-height:1.55}.card a{display:inline-flex;padding:11px 18px;border-radius:10px;background:#2563eb;color:#fff;text-decoration:none;font-weight:700}.card a:focus-visible{outline:3px solid #93c5fd;outline-offset:3px}</style></head><body><main class="card" role="alert"><h1>Descarga no disponible</h1><p>' . $safeMessage . '</p><a href="' . $safeBackUrl . '">Volver al repositorio</a></main></body></html>';
        exit;
    }

    private function sendJson(bool $success, string $message, array $data = []): never
    {
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
