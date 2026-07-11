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
        $repositoryModel = new RepositoryModel();
        $favoriteModel = new FavoriteModel();
        $downloadModel = new DownloadModel();
        $userId = $this->getCurrentUserId();
        $favoriteIds = $favoriteModel->getFavoriteIds($userId);
        $projects = array_map(static function (array $project) use ($favoriteIds, $downloadModel): array {
            $project['is_favorite'] = in_array($project['id'], $favoriteIds, true);
            $project['downloads'] = $downloadModel->getTotal($project['id'], $project['downloads']);
            $project['detail_url'] = base_url('index.php?page=repository-detail&id=' . urlencode((string) $project['id']));
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
            'supportDocuments' => $repositoryModel->getSupportDocuments()
        ]);
    }

    public function detail(): void
    {
        $this->ensureSession();
        $projectId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        $repositoryModel = new RepositoryModel();
        $project = $projectId === false || $projectId === null
            ? null
            : $repositoryModel->getPublishedProjectDetail((int) $projectId);

        if ($project === null) {
            http_response_code(404);
            $archiveState = null;
        } else {
            $favoriteModel = new FavoriteModel();
            $project['is_favorite'] = $favoriteModel->isFavorite($this->getCurrentUserId(), $project['id']);
            $project['downloads'] = (new DownloadModel())->getTotal($project['id'], $project['downloads']);
            $archiveState = (new ArchiveService())->listDirectory($project['archive']['path']);
            $project['archive'] = array_merge($project['archive'], $archiveState['meta']);
        }

        View::render('repository/detalle', [
            'currentPage' => 'repository',
            'title' => $project === null ? 'Proyecto no encontrado | Repositorio' : $project['title'] . ' | Repositorio',
            'pageScript' => asset('js/repository-detail.js'),
            'project' => $project,
            'repositoryUrl' => route('repository'),
            'favoriteActionUrl' => route('repository-favorite'),
            'favoriteCsrfToken' => $this->getFavoriteCsrfToken(),
            'archiveState' => $archiveState,
            'filesActionUrl' => route('repository-files'),
            'projectDownloadUrl' => base_url('index.php?page=repository-download&id=' . urlencode((string) ($project['id'] ?? 0))),
            'fileDownloadActionUrl' => route('repository-file-download'),
        ]);
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
