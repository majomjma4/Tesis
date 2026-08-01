<?php

declare(strict_types=1);

final class RepositoryController
{
    /**
     * Renderiza el repositorio institucional de proyectos finalizados.
     */
    public function index(): void
    {
        $this->ensureSession();
        if ((new AuthSessionService())->hasAdminAccess()) {
            header('Location: ' . route('admin-repository'));
            exit;
        }
        $repositoryModel = new RepositoryModel();
        $favoriteModel = new FavoriteModel();
        $downloadModel = new DownloadModel();
        $userId = $this->getCurrentUserId();
        $repositoryReturnUrl = (string) ($_SERVER['REQUEST_URI'] ?? route('repository'));
        $favoriteIds = $favoriteModel->getFavoriteIds($userId);
        $projects = array_map(static function (array $project) use ($favoriteIds, $downloadModel, $repositoryReturnUrl): array {
            $project['is_favorite'] = in_array($project['id'], $favoriteIds, true);
            $project['downloads'] = $downloadModel->getTotal($project['id'], $project['downloads']);
            $project['detail_url'] = base_url('index.php?page=repository-detail&id=' . urlencode((string) $project['id']) . '&return=' . rawurlencode($repositoryReturnUrl));
            return $project;
        }, $repositoryModel->getPublishedProjects());

        View::render('repository/repositorio', [
            'currentPage' => 'repository',
            'title' => 'Repositorio Institucional | Gestión Documental Académica',
            'pageScript' => asset('js/repository.js'),
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
            }, (new SupportMaterialModel())->getAll()),
            'supportMaterialsUrl' => route('support-materials'),
        ]);
    }

    public function detail(): void
    {
        $this->ensureSession();
        $session=new AuthSessionService();
        $isAdministrator=$session->hasAdminAccess();
        $projectId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        $access = new ProjectAccessService();
        $project = ($projectId !== false && $projectId !== null && $access->can('project.view'))
            ? (new ProjectRecordModel())->find((int)$projectId, $access->currentUserId(), $isAdministrator, true) : null;
        if ($project === null) http_response_code(404);
        if($project!==null){$academicPage=(new ProjectRecordModel())->academicHistoryPage((int)$project['id']);$project['academic_history']=$academicPage['events'];$project['academic_history_total']=$academicPage['total'];}
        $requestedTab=strtolower(trim((string)($_GET['tab']??'information')));
        $activeTab=in_array($requestedTab,['information','files','evolution'],true)?$requestedTab:'information';
        $returnUrl=$this->repositoryReturnUrl((string)($_GET['return']??''));
        $projectDocuments=null;
        if($project!==null&&$isAdministrator){
            $documentModel=new ProjectDocumentModel();
            $documentFiles=$documentModel->activeFiles((int)$project['id']);
            $projectDocuments=['restorable'=>$documentModel->restorable((int)$project['id']),'versions'=>$documentModel->versions((int)$project['id']),'package'=>(new ProjectPackageService())->describe((int)$project['id'],$documentFiles),'limits'=>(new ProjectDocumentFileService())->limits(),'endpoint'=>route('admin-project-file'),'csrf'=>$session->csrfToken('admin_repository')];
        }
        View::render('projects/detail', [
            'currentPage' => 'repository',
            'title' => $project === null ? 'Proyecto no encontrado | Repositorio' : $project['title'] . ' | Repositorio',
            'pageStyles'=>$isAdministrator?[asset('css/admin-projects.css')]:[],
            'pageScript' => $isAdministrator?asset('js/admin-projects.js'):asset('js/repository-detail.js'),
            'pageScripts'=>$isAdministrator?[asset('js/material-admin-actions.js'),asset('js/repository-detail.js')]:[],
            'project' => $project,
            'activeTab'=>$activeTab,'isAdministrator'=>$isAdministrator,'publicContext'=>true,'canReview'=>false,'canDeliver'=>false,
            'projectEditUrl'=>'','detailUrl'=>route('repository-detail').'&id='.(int)($project['id']??0),
            'returnUrl'=>$returnUrl,'previewActionUrl'=>route('project-file-preview').'&scope=repository',
            'downloadActionUrl'=>route('project-file-download').'&scope=repository',
            'projectAdminEndpoint'=>$isAdministrator?route('admin-repository-publish'):'',
            'projectHistoryEndpoint'=>$isAdministrator?route('admin-project-history').'&id='.(int)($project['id']??0):'',
            'projectTrashEndpoint'=>$isAdministrator?route('admin-project-trash'):'',
            'projectSaveEndpoint'=>$isAdministrator?route('admin-project-save'):'',
            'projectAdminCsrf'=>$isAdministrator?$session->csrfToken('admin_repository'):'',
            'projectTrashCsrf'=>$isAdministrator?$session->csrfToken('admin_projects'):'',
            'projectEditorCatalogs'=>$isAdministrator?(new AdminProjectModel())->catalogs():[],
            'projectDocuments'=>$projectDocuments,
            'academicHistoryEndpoint'=>route('project-academic-history-events').'&project_id='.(int)($project['id']??0),
        ]);
    }

    private function repositoryReturnUrl(string $candidate): string
    {
        if ($candidate==='') return (new AuthSessionService())->hasAdminAccess()?route('admin-repository'):route('repository');
        $parts=parse_url($candidate); if($parts===false||isset($parts['scheme'])||isset($parts['host'])) return route('repository');
        parse_str((string)($parts['query']??''),$query); $page=strtolower((string)($query['page']??''));
        return in_array($page,['repository','repositorio','admin-repository'],true)?$candidate:route('repository');
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
        $project = $projectId === false || $projectId === null
            ? null
            : $repositoryModel->getPublishedProjectDetail((int) $projectId);

        if ($project === null) {
            http_response_code(404);
            $this->sendJson(false, 'El proyecto solicitado no está disponible.');
        }

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

        (new DownloadModel())->increment($project['id']);
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
        $project = $projectId === false || $projectId === null
            ? null
            : (new RepositoryModel())->getPublishedProjectDetail((int) $projectId);

        if ($project === null) {
            http_response_code(404);
            $this->renderDownloadError('El proyecto solicitado no está disponible.');
        }

        return $project;
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
        header('Content-Type: text/plain; charset=UTF-8');
        echo $message;
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
