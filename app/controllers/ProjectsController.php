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

        View::render('projects/index', [
            'currentPage' => 'projects',
            'title' => 'Mis Proyectos | Gestión Documental Académica',
            'pageScript' => asset('js/projects.js'),
            'project' => $projectModel->getProjectDetails(),
            'history' => $projectModel->getDocumentHistory(),
            'phases' => $projectModel->getCareerPhases()
        ]);
    }
}
