<?php

declare(strict_types=1);

final class RepositoryController
{
    /**
     * Renderiza el repositorio institucional de proyectos finalizados.
     */
    public function index(): void
    {
        $repositoryModel = new RepositoryModel();

        View::render('repository/index', [
            'currentPage' => 'repository',
            'title' => 'Repositorio Institucional | Gestión Documental Académica',
            'pageScript' => asset('js/repository.js'),
            'projects' => $repositoryModel->getPublishedProjects(),
            'semesters' => $repositoryModel->getSemesters(),
            'teachers' => $repositoryModel->getTeachers(),
            'categories' => $repositoryModel->getCategories(),
            'projectTypes' => $repositoryModel->getProjectTypes(),
            'academicPeriods' => $repositoryModel->getAcademicPeriods(),
            'supportDocuments' => $repositoryModel->getSupportDocuments()
        ]);
    }
}
