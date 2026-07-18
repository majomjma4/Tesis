<?php

declare(strict_types=1);

final class ProjectsController
{
    /**
     * Renderiza la pantalla principal de "Mis proyectos".
     */
    public function index(): void
    {
        $projectModel = new ProjectModel();
        $projects = $projectModel->getProjectsForUser(1);

        if (count($projects) === 1) {
            header('Location: ' . route('project-detail') . '&id=' . (int) $projects[0]['id']);
            exit;
        }

        View::render('projects/index', [
            'currentPage' => 'projects',
            'title' => 'Mis Proyectos | Gestión Documental Académica',
            'bodyClass' => 'projects-page',
            'pageStyles' => [asset('css/projects.css'), asset('css/projects-catalog.css')],
            'pageScript' => asset('js/projects.js'),
            'projects' => $projects,
            'metrics' => $projectModel->getProjectMetrics($projects),
        ]);
    }

    /** Presenta el espacio de seguimiento de un expediente concreto. */
    public function detail(): void
    {
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $allowedTabs = ['summary', 'deliveries', 'observations', 'comments', 'history', 'participants', 'calendar', 'final-documents'];
        $tab = strtolower(trim((string) ($_GET['tab'] ?? 'summary')));
        $tab = in_array($tab, $allowedTabs, true) ? $tab : 'summary';
        $model = new ProjectModel();
        $project = $id ? $model->findProjectForUser((int) $id, 1) : null;

        if ($project === null) {
            http_response_code(404);
        }

        View::render('projects/detail', [
            'currentPage' => 'projects',
            'title' => ($project['title'] ?? 'Proyecto no encontrado') . ' | Gestión Académica',
            'bodyClass' => 'project-detail-page',
            'pageStyles' => [asset('css/project-detail.css'), asset('css/project-summary.css'), asset('css/project-workspace.css')],
            'pageScript' => asset('js/project-detail.js'),
            'project' => $project,
            'activeTab' => $tab,
            'tabs' => $model->getDetailTabs(),
        ]);
    }

    /** Mantiene accesible la ruta global mientras se construye el formulario definitivo. */
    public function create(): void
    {
        View::render('projects/create-pending', [
            'currentPage' => 'projects',
            'title' => 'Nuevo proyecto | Gestión Académica',
            'bodyClass' => 'projects-page',
            'pageStyles' => [asset('css/projects.css')],
        ]);
    }
}
