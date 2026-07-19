<?php

declare(strict_types=1);

final class ProjectsController
{
    /**
     * Renderiza la pantalla principal de "Mis proyectos".
     */
    public function index(): void
    {
        if (in_array('administrator', (new AuthSessionService())->roles(), true)) {
            (new AdminController())->projects();
            return;
        }
        $projectModel = new ProjectModel();
        $access = new ProjectAccessService();
        $projects = $projectModel->getProjectsForUser($access->currentUserId());

        if (count($projects) === 1) {
            header('Location: ' . route('project-detail') . '&id=' . (int) $projects[0]['id']);
            exit;
        }

        View::render('projects/index', [
            'currentPage' => 'projects',
            'title' => 'Mis Proyectos | Gestión Documental Académica',
            'bodyClass' => 'projects-page',
            'pageStyles' => [asset('css/projects.css'), asset('css/projects-catalog.css'), asset('css/project-simplified.css')],
            'pageScript' => asset('js/projects.js'),
            'projects' => $projects,
        ]);
    }

    /** Presenta el espacio de seguimiento de un expediente concreto. */
    public function detail(): void
    {
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $legacyTabs = ['deliveries' => 'documents', 'final-documents' => 'documents', 'observations' => 'review', 'comments' => 'review', 'history' => 'activity', 'calendar' => 'activity', 'participants' => 'information', 'more' => 'information'];
        $allowedTabs = ['summary', 'documents', 'review', 'activity', 'information'];
        $requestedTab = strtolower(trim((string) ($_GET['tab'] ?? 'summary')));
        $tab = $legacyTabs[$requestedTab] ?? $requestedTab;
        $tab = in_array($tab, $allowedTabs, true) ? $tab : 'summary';
        $reviewView = strtolower(trim((string) ($_GET['view'] ?? ($requestedTab === 'comments' ? 'conversation' : 'observations'))));
        $reviewView = in_array($reviewView, ['observations', 'conversation'], true) ? $reviewView : 'observations';
        $activityView = strtolower(trim((string) ($_GET['view'] ?? ($requestedTab === 'calendar' ? 'calendar' : 'history'))));
        $activityView = in_array($activityView, ['history', 'calendar'], true) ? $activityView : 'history';
        $observationFilter = strtolower(trim((string) ($_GET['filter'] ?? 'pending')));
        $observationFilter = in_array($observationFilter, ['pending', 'addressed', 'resolved', 'all'], true) ? $observationFilter : 'pending';
        $selectedObservationId = filter_var($_GET['observation'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
        $model = new ProjectModel();
        $access = new ProjectAccessService();
        $project = $id && $access->can('project.view') ? $model->findProjectForUser((int) $id, $access->currentUserId()) : null;
        $projectEvents = [];
        if ($project !== null) {
            $projectEvents = array_values(array_filter((new CalendarModel())->getEvents(), static fn (array $event): bool => (int) ($event['projectId'] ?? 0) === (int) $project['id']));
        }

        if ($project === null) {
            (new ErrorController())->notFound();
            return;
        }

        View::render('projects/detail', [
            'currentPage' => 'projects',
            'title' => ($project['title'] ?? 'Proyecto no encontrado') . ' | Gestión Académica',
            'bodyClass' => 'project-detail-page',
            'pageStyles' => [asset('css/project-simplified.css')],
            'pageScript' => asset('js/project-detail.js'),
            'project' => $project,
            'activeTab' => $tab,
            'legacySection' => in_array($requestedTab, ['history', 'participants', 'calendar'], true) ? $requestedTab : null,
            'reviewView' => $reviewView,
            'activityView' => $activityView,
            'observationFilter' => $observationFilter,
            'selectedObservationId' => $selectedObservationId,
            'tabs' => $model->getDetailTabs(),
            'projectEvents' => $projectEvents,
            'projectPermissions' => $access->permissions(),
        ]);
    }

    /** Mantiene accesible la ruta global mientras se construye el formulario definitivo. */
    public function create(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $access = new ProjectAccessService();
        $policy = $access->projectCreationPolicy();
        if (!$policy['can_create']) http_response_code(403);
        if (!isset($_SESSION['project_draft_csrf'])) $_SESSION['project_draft_csrf'] = bin2hex(random_bytes(32));
        $draftService = new ProjectDraftService();
        $fileService = new PrivateProjectFileService();
        $draft = $draftService->normalize([], $policy); $errors = []; $validated = false; $confirmation = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $policy['can_create']) {
            $draft = $draftService->normalize($_POST, $policy);
            if (!hash_equals((string) $_SESSION['project_draft_csrf'], (string) ($_POST['_csrf'] ?? ''))) $errors['_form'] = 'La sesión del formulario venció. Recarga la página e inténtalo nuevamente.';
            $errors += $draftService->validate($draft, $policy);
            $fileResult = $draftService->validateFiles($_FILES['files'] ?? [], $fileService);
            $errors += $fileResult['errors'];
            if ($errors === []) { $validated = true; $confirmation = $draftService->confirmation($draft, $fileResult['valid']); }
        }
        View::render('projects/new', [
            'currentPage' => 'projects',
            'title' => 'Nuevo proyecto | Gestión Académica',
            'bodyClass' => 'project-wizard-page',
            'pageStyles' => [asset('css/project-wizard.css')],
            'pageScript' => asset('js/project-wizard.js'),
            'creationPolicy' => $policy, 'catalogs' => $draftService->catalogs(), 'fieldContract' => $draftService->fieldContract(),
            'fileLimits' => $fileService->limits(), 'draft' => $draft, 'errors' => $errors, 'draftValidated' => $validated,
            'confirmation' => $confirmation, 'projectDraftCsrf' => (string) $_SESSION['project_draft_csrf'],
        ]);
    }
}
