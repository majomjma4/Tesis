<?php

declare(strict_types=1);

final class DashboardController
{
    public function index(): void
    {
        $dashboard = new DashboardModel();

        if ((new AuthSessionService())->isAdminModeActive()) {
            $error = null;
            try {
                $adminDashboard = $dashboard->getAdminDashboard();
            } catch (Throwable $exception) {
                error_log('Admin dashboard error: ' . $exception->getMessage());
                $error = 'No fue posible consultar los indicadores administrativos en este momento.';
                $adminDashboard = $dashboard->emptyAdminDashboard();
            }

            View::render('dashboard/admin', [
                'currentPage' => 'dashboard',
                'title' => 'Inicio administrativo | Gestión Documental Académica',
                'bodyClass' => 'admin-dashboard-page',
                'pageStyles' => [asset('css/admin-dashboard.css')],
                'pageScript' => null,
                'dashboard' => $adminDashboard,
                'dashboardError' => $error,
            ]);
            return;
        }

        $session = new AuthSessionService();
        if ($session->isTeacher()) {
            $error = null;
            try {
                $teacherDashboard = $dashboard->getTeacherDashboard((int) $session->userId());
            } catch (Throwable $exception) {
                error_log('Teacher dashboard error: ' . $exception->getMessage());
                $error = 'No fue posible consultar la información docente en este momento.';
                $teacherDashboard = $dashboard->emptyTeacherDashboard((int) $session->userId());
            }

            View::render('dashboard/teacher', [
                'currentPage' => 'dashboard',
                'title' => 'Dashboard docente | Gestión Documental Académica',
                'bodyClass' => 'teacher-dashboard-page',
                'pageStyles' => [asset('css/teacher-dashboard.css')],
                'pageScript' => asset('js/teacher-dashboard.js'),
                'teacherDashboard' => $teacherDashboard,
                'teacherDashboardError' => $error,
            ]);
            return;
        }

        $studentId = (int) ($session->userId() ?? 0);
        $studentDashboardError = null;
        try {
            $studentDashboard = $dashboard->getStudentDashboard($studentId);
        } catch (Throwable $exception) {
            error_log('Student dashboard error: ' . $exception->getMessage());
            $studentDashboard = [
                'projects' => ['status' => 'error', 'items' => [], 'message' => 'No fue posible cargar tu dashboard en este momento.'],
                'upcoming' => ['status' => 'error', 'items' => [], 'message' => 'No fue posible cargar tus próximas fechas.'],
                'notifications' => ['status' => 'error', 'unread_count' => null, 'items' => [], 'message' => 'No fue posible cargar tus notificaciones.'],
                'resources' => ['status' => 'error', 'items' => [], 'message' => 'No fue posible cargar los recursos institucionales.'],
            ];
            $studentDashboardError = 'No fue posible cargar tu dashboard en este momento.';
        }
        $projectItems = (array) ($studentDashboard['projects']['items'] ?? []);
        $requestedIndex = filter_var($_GET['project_index'] ?? 0, FILTER_VALIDATE_INT);
        $selectedIndex = $requestedIndex === false ? 0 : max(0, min((int) $requestedIndex, max(0, count($projectItems) - 1)));
        View::render('dashboard/student', [
            'currentPage' => 'dashboard',
            'title' => 'Dashboard | Gestion Documental Academica',
            'bodyClass' => 'student-dashboard-page',
            'pageStyles' => [asset('css/student-dashboard.css'), asset('css/teacher-dashboard.css')],
            'pageScript' => asset('js/student-dashboard.js'),
            'studentDashboard' => $studentDashboard,
            'studentDashboardError' => $studentDashboardError,
            'studentProjects' => $projectItems,
            'studentProject' => $projectItems[$selectedIndex] ?? null,
            'studentProjectIndex' => $selectedIndex,
        ]);
    }
}
