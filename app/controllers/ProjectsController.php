<?php

declare(strict_types=1);

final class ProjectsController
{
    /** Renderiza la maqueta visual de la bandeja docente de proyectos asignados. */
    public function assigned(): void
    {
        $session = new AuthSessionService();
        $access = new ProjectAccessService();
        $isTeacher = in_array('teacher', $access->currentRoles(), true);
        if (!$isTeacher) {
            (new AccountController())->forbidden();
            return;
        }

        $assigned = (new TeacherAssignedProjectService())->forTeacher($access->currentUserId());
        $capabilities = (new ProjectCapabilityService())->forProjectIds(array_map(static fn(array $project): int => (int)$project['id'], $assigned['projects']), 'academic');
        $packages = new ProjectRepositoryPackageService();
        foreach ($assigned['projects'] as &$project) {
            $project['package'] = ['available'=>false,'download_url'=>'','file_count'=>0,'size'=>''];
            if (empty($capabilities[(int)$project['id']]['download_academic_package'])) continue;
            try {
                $project['package'] = $packages->describeAcademic(
                    (int)$project['id'],
                    route('project-package-download') . '&id=' . (int)$project['id']
                );
            } catch (Throwable $error) {
                error_log('Assigned project package: ' . $error->getMessage());
            }
        }
        unset($project);

        View::render('projects/assigned', [
            'currentPage' => 'assigned-projects',
            'title' => 'Proyectos asignados | Gestión Documental Académica',
            'bodyClass' => 'assigned-projects-page',
            'pageStyles' => [asset('css/repository-reader.css'), asset('css/assigned-projects.css')],
            'pageScript' => asset('js/assigned-projects.js'),
            'assignedProjects' => $assigned['projects'],
            'assignedProjectTypes' => $assigned['types'],
            'assignedProjectPeriods' => $assigned['periods'],
            'assignedProjectRelations' => $assigned['relations'],
        ]);
    }

    /** Pantalla base para el cargo especial de Gestión de Titulación. */
    public function thesisManagement(): void
    {
        $thesisCapabilities = (new TeacherThesisCapabilityService())->capabilitiesForCurrentUser();
        if (empty($thesisCapabilities['manage_thesis_process'])) {
            (new AccountController())->forbidden();
            return;
        }
        $listing = (new ThesisManagementService())->listing();
        $session = new AuthSessionService();

        View::render('projects/thesis-management', [
            'currentPage' => 'thesis-management',
            'title' => 'Gestión de Titulación | Gestión Documental Académica',
            'bodyClass' => 'assigned-projects-page',
            'pageStyles' => [asset('css/repository-reader.css'), asset('css/assigned-projects.css'), asset('css/thesis-management.css'), asset('css/thesis-tribunal.css')],
            'pageScript' => asset('js/thesis-management.js'),
            'thesisProjects' => $listing['projects'],
            'thesisPeriods' => $listing['periods'],
            'thesisSummary' => $listing['summary'],
            'thesisDefenseSchedules' => $listing['defenseSchedules'],
            'thesisTribunalConfig' => ['suggest'=>route('thesis-tribunal-suggest'),'save'=>route('thesis-tribunal-save'),'defenseInfo'=>route('thesis-defense-information-save'),'scheduleSave'=>route('thesis-defense-schedule-save'),'resultSave'=>route('thesis-tribunal-result-save'),'publish'=>route('thesis-publish'),'csrf'=>$session->csrfToken('thesis_management')],
        ]);
    }

    public function thesisTribunalCandidates(): void
    {
        if (!(new TeacherThesisCapabilityService())->canManageCurrentUser()) { http_response_code(403); $this->json(['success'=>false,'message'=>'No tienes autorización para gestionar Tribunal.','data'=>[]]); }
        $id=(int)($_GET['project_id']??0);try{$items=(new ThesisTribunalService())->candidates($id);$this->json(['success'=>true,'message'=>'','data'=>['items'=>$items]]);}catch(ThesisTribunalException $e){http_response_code($e->httpStatus());$this->json(['success'=>false,'message'=>$e->getMessage(),'data'=>[]]);}
    }

    /** Genera una propuesta de Tribunal sin persistir cambios. */
    public function suggestThesisTribunal(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') { http_response_code(405); $this->json(['success'=>false,'message'=>'Método no permitido.','data'=>[]]); }
        if (!(new TeacherThesisCapabilityService())->canManageCurrentUser()) { http_response_code(403); $this->json(['success'=>false,'message'=>'No tienes autorización para gestionar Tribunal.','data'=>[]]); }
        $replacement = (string) ($_GET['mode'] ?? '') === 'replacement';
        try {
            $service = new ThesisTribunalService();
            $projectId = (int) ($_GET['project_id'] ?? 0);
            if ((string) ($_GET['mode'] ?? '') === 'catalog') {
                $this->json(['success'=>true,'message'=>'','data'=>['items'=>$service->availableWithLoad($projectId)]]);
            }
            $data = $service->suggest(
                $projectId,
                (int) ($_GET['desired_count'] ?? 0),
                (array) ($_GET['exclude_user_ids'] ?? []),
                $replacement,
                (array) ($_GET['load_overrides'] ?? []),
                (string) ($_GET['position'] ?? '')
            );
            $this->json(['success'=>true,'message'=>'','data'=>$data]);
        } catch (ThesisTribunalException $e) {
            http_response_code($e->httpStatus());
            $this->json(['success'=>false,'message'=>$e->getMessage(),'data'=>[]]);
        } catch (Throwable $e) {
            error_log('Thesis tribunal suggestion: ' . $e->getMessage());
            http_response_code(500);
            $this->json(['success'=>false,'message'=>'No fue posible generar la propuesta de Tribunal.','data'=>[]]);
        }
    }

    public function saveThesisTribunal(): void
    {
        if (($_SERVER['REQUEST_METHOD']??'GET')!=='POST') { http_response_code(405); $this->json(['success'=>false,'message'=>'Método no permitido.','data'=>[]]); }
        $session=new AuthSessionService();if(!(new TeacherThesisCapabilityService())->canManageCurrentUser()){http_response_code(403);$this->json(['success'=>false,'message'=>'No tienes autorización para gestionar Tribunal.','data'=>[]]);}
        if(!$session->validateCsrf('thesis_management',(string)($_POST['_csrf']??''))){http_response_code(419);$this->json(['success'=>false,'message'=>'La sesión del formulario venció.','data'=>[]]);}
        $assignments=['president'=>(int)($_POST['president_id']??0),'member_1'=>(int)($_POST['member_1_id']??0),'member_2'=>(int)($_POST['member_2_id']??0)];try{$result=(new ThesisTribunalService())->save((int)($_POST['project_id']??0),(string)($_POST['expected_status']??''),$assignments,(string)($_POST['reason']??''),(int)$session->userId());$message=($result['status']??'')==='defense'&&(string)($_POST['expected_status']??'')==='approved'?'Tribunal conformado correctamente. El proyecto avanzó a la etapa de defensa.':'Tribunal actualizado correctamente.';$this->json(['success'=>true,'message'=>$message,'data'=>$result]);}catch(ThesisTribunalException|ProjectStatusTransitionException $e){http_response_code($e->httpStatus());$this->json(['success'=>false,'message'=>$e->getMessage(),'data'=>[]]);}catch(Throwable $e){error_log('Thesis tribunal: '.$e->getMessage());http_response_code(500);$this->json(['success'=>false,'message'=>'No fue posible actualizar el Tribunal.','data'=>[]]);}
    }

    public function saveThesisDefenseInformation(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); $this->json(['success'=>false,'message'=>'Método no permitido.','data'=>[]]); return; }
        $session=new AuthSessionService();
        if (!(new TeacherThesisCapabilityService())->canManageCurrentUser()) { http_response_code(403); $this->json(['success'=>false,'message'=>'No tienes autorización para editar información de defensa.','data'=>[]]); return; }
        if (!$session->validateCsrf('thesis_management',(string)($_POST['_csrf']??''))) { http_response_code(419); $this->json(['success'=>false,'message'=>'La sesión del formulario venció.','data'=>[]]); return; }
        try {$data=(new ThesisDefenseService())->save((int)($_POST['project_id']??0),$_POST,(int)$session->userId());$this->json(['success'=>true,'message'=>'Información de defensa guardada.','data'=>$data]);}
        catch(ThesisDefenseException $e){http_response_code($e->httpStatus());$this->json(['success'=>false,'message'=>$e->getMessage(),'data'=>[]]);}
        catch(Throwable $e){error_log('Defense info: '.$e->getMessage());http_response_code(500);$this->json(['success'=>false,'message'=>'No fue posible guardar la información de defensa.','data'=>[]]);}
    }

    public function saveThesisDefenseSchedule(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); $this->json(['success'=>false,'message'=>'Método no permitido.','data'=>[]]); }
        $session = new AuthSessionService();
        if (!(new TeacherThesisCapabilityService())->canManageCurrentUser()) { http_response_code(403); $this->json(['success'=>false,'message'=>'No tienes autorización para programar la jornada de defensa.','data'=>[]]); }
        if (!$session->validateCsrf('thesis_management', (string) ($_POST['_csrf'] ?? ''))) { http_response_code(419); $this->json(['success'=>false,'message'=>'La sesión del formulario venció.','data'=>[]]); }
        try {
            $data = (new ThesisDefenseScheduleService())->save((int) ($_POST['academic_period_id'] ?? 0), $_POST, (int) $session->userId());
            $messages = ['created'=>'Programación global de defensas guardada.', 'updated'=>'Programación global de defensas actualizada.', 'cleared'=>'Programación global de defensas eliminada.', 'unchanged'=>'La programación no tiene cambios.'];
            $this->json(['success'=>true,'message'=>$messages[$data['action']] ?? 'Programación guardada.','data'=>$data]);
        } catch (ThesisDefenseScheduleException $e) {
            http_response_code($e->httpStatus());
            $this->json(['success'=>false,'message'=>$e->getMessage(),'data'=>[]]);
        } catch (Throwable $e) {
            error_log('Thesis defense schedule: ' . $e->getMessage());
            http_response_code(500);
            $this->json(['success'=>false,'message'=>'No fue posible guardar la programación global de defensas.','data'=>[]]);
        }
    }

    public function startThesisDefenseAttempt(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); $this->json(['success'=>false,'message'=>'Método no permitido.','data'=>[]]); return; }
        $session = new AuthSessionService();
        if (!(new TeacherThesisCapabilityService())->canManageCurrentUser()) { http_response_code(403); $this->json(['success'=>false,'message'=>'No tienes autorización para iniciar una nueva defensa.','data'=>[]]); return; }
        if (!$session->validateCsrf('thesis_management',(string)($_POST['_csrf']??''))) { http_response_code(419); $this->json(['success'=>false,'message'=>'La sesión del formulario venció.','data'=>[]]); return; }
        try {$data=(new ThesisDefenseService())->startNewAttempt((int)($_POST['project_id']??0),(string)($_POST['expected_status']??''),(int)$session->userId());$this->json(['success'=>true,'message'=>'Se habilitó un nuevo intento de defensa.','data'=>$data]);}
        catch(ThesisDefenseException $e){http_response_code($e->httpStatus());$this->json(['success'=>false,'message'=>$e->getMessage(),'data'=>[]]);}
        catch(Throwable $e){error_log('New defense attempt: '.$e->getMessage());http_response_code(500);$this->json(['success'=>false,'message'=>'No fue posible iniciar una nueva defensa.','data'=>[]]);}
    }

    public function saveThesisTribunalResult(): void
    {
        if (($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){http_response_code(405);$this->json(['success'=>false,'message'=>'Método no permitido.','data'=>[]]);return;}$session=new AuthSessionService();if(!(new TeacherThesisCapabilityService())->canManageCurrentUser()){http_response_code(403);$this->json(['success'=>false,'message'=>'No tienes autorización para registrar el resultado.','data'=>[]]);return;}if(!$session->validateCsrf('thesis_management',(string)($_POST['_csrf']??''))){http_response_code(419);$this->json(['success'=>false,'message'=>'La sesión del formulario venció.','data'=>[]]);return;}
        try{$data=(new ThesisDefenseResultService())->save((int)($_POST['project_id']??0),(string)($_POST['expected_status']??''),(string)($_POST['result']??''),(string)($_POST['result_notes']??''),(int)$session->userId());$this->json(['success'=>true,'message'=>'Resultado registrado correctamente.','data'=>$data]);}catch(ThesisDefenseResultException $e){http_response_code($e->httpStatus());$this->json(['success'=>false,'message'=>$e->getMessage(),'data'=>[]]);}catch(Throwable $e){error_log('Tribunal result: '.$e->getMessage());http_response_code(500);$this->json(['success'=>false,'message'=>'No fue posible registrar el resultado.','data'=>[]]);}
    }
    public function publishThesis(): void
    {
        if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){http_response_code(405);$this->json(['success'=>false,'message'=>'Método no permitido.','data'=>[]]);return;}$s=new AuthSessionService();if(!(new TeacherThesisCapabilityService())->canManageCurrentUser()){http_response_code(403);$this->json(['success'=>false,'message'=>'No tienes autorización para publicar el proyecto.','data'=>[]]);return;}if(!$s->validateCsrf('thesis_management',(string)($_POST['_csrf']??''))){http_response_code(419);$this->json(['success'=>false,'message'=>'La sesión del formulario venció.','data'=>[]]);return;}try{$data=(new ThesisPublicationService())->publish((int)($_POST['project_id']??0),(string)($_POST['expected_status']??''),(int)$s->userId());$this->json(['success'=>true,'message'=>'Proyecto publicado correctamente.','data'=>$data]);}catch(ThesisPublicationException|ProjectStatusTransitionException $e){http_response_code($e->httpStatus());$this->json(['success'=>false,'message'=>$e->getMessage(),'data'=>[]]);}catch(Throwable $e){error_log('Thesis publish: '.$e->getMessage());http_response_code(500);$this->json(['success'=>false,'message'=>'No fue posible publicar el proyecto.','data'=>[]]);}
    }

    /**
     * Renderiza la pantalla principal de "Mis proyectos".
     */
    public function index(): void
    {
        $session = new AuthSessionService();
        $access = new ProjectAccessService();
        $roles = $access->currentRoles();

        if ($session->hasAdminAccess() || in_array('teacher', $roles, true)) {
            (new AdminController())->projects();
            return;
        }

        $projectModel = new ProjectModel();
        $studentProjects = $projectModel->getStudentProjectsResult($access->currentUserId());
        $projects = (array) ($studentProjects['items'] ?? []);
        $directFinished = $projectModel->getDirectRepositoryFinishedProjectsResult($access->currentUserId());
        $directProjects = (array) ($directFinished['items'] ?? []);
        $projects = array_merge($projects, $directProjects);
        $totalProjects = count($projects);
        if (($studentProjects['status'] ?? 'error') === 'loaded' && $totalProjects === 1 && empty($projects[0]['is_direct_repository'])) {
            $project = $projects[0];
            $capabilities = (new ProjectCapabilityService())->forProjectId((int) $project['id'], 'academic');
            $navigation = (new StudentProjectNavigationService())->resolve($project, $capabilities);
            header('Location: ' . $navigation['action_url']);
            exit;
        }
        if (($studentProjects['status'] ?? '') === 'loaded' || $directProjects !== []) {
            $policy = new ProjectCapabilityService();
            $navigation = new StudentProjectNavigationService();
            foreach ($projects as &$project) {
                $capabilities = $policy->forProjectId((int) $project['id'], 'academic');
                $project['capabilities'] = $capabilities;
                $project['navigation'] = $navigation->resolve($project, $capabilities);
            }
            unset($project);
        }
        $projectGroups = [
            'development' => ['key' => 'development', 'title' => 'En desarrollo', 'description' => 'Proyectos que todavía se encuentran en seguimiento o tienen acciones pendientes.', 'items' => [], 'empty' => 'No tienes proyectos en seguimiento actualmente.'],
            'finished' => ['key' => 'finished', 'title' => 'Terminados', 'description' => 'Proyectos que ya finalizaron su proceso de seguimiento.', 'items' => [], 'empty' => 'Todavía no tienes proyectos terminados.'],
        ];
        foreach ($projects as $project) {
            $key = (string) ($project['navigation']['category'] ?? 'development');
            if (isset($projectGroups[$key])) $projectGroups[$key]['items'][] = $project;
        }
        $projectGroups = array_values(array_filter($projectGroups, static fn(array $group): bool => $group['items'] !== []));

        View::render('projects/index', [
            'currentPage' => 'projects',
            'title' => 'Mis Proyectos | Gestión Documental Académica',
            'bodyClass' => 'projects-page',
            'pageStyles' => [asset('css/projects.css'), asset('css/projects-catalog.css'), asset('css/project-simplified.css')],
            'pageScript' => asset('js/projects.js'),
            'projects' => $projects,
            'projectGroups' => $projectGroups,
            'projectsStatus' => (($studentProjects['status'] ?? 'error') === 'loaded' || $directProjects !== []) ? 'loaded' : (string) ($studentProjects['status'] ?? 'error'),
            'projectsMessage' => (string) ($studentProjects['message'] ?? ''),
            'projectsTotal' => (($studentProjects['status'] ?? 'error') === 'loaded' || $directProjects !== []) ? $totalProjects : null,
            'projectsHeading' => (($studentProjects['status'] ?? 'error') === 'loaded' && $totalProjects === 1) ? 'Mi proyecto' : 'Mis proyectos',
            'canCreateStudentProject' => $projects === [] && $access->can('project.create'),
            'newStudentProjectUrl' => route('new-project'),
            'studentProjectPublishEndpoint' => route('student-project-publish'),
            'studentProjectPublishCsrf' => $session->csrfToken('student_project_publish'),
        ]);
    }

    /** Presenta el espacio de seguimiento de un expediente concreto. */
    public function detail(): void
    {
        $session = new AuthSessionService();
        if (!$session->isAuthenticated()) {
            header('Location: ' . route('login'));
            exit;
        }
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $requestedTab = strtolower(trim((string) ($_GET['tab'] ?? 'summary')));
        $access = new ProjectAccessService();
        $isAdministrator = $session->isAdminModeActive();
        $roles = array_map('strtolower', array_map('strval', $access->currentRoles()));
        $isTeacher = in_array('teacher', $roles, true);
        $isStudent = in_array('student', $roles, true);
        $resourceContext = $isAdministrator ? 'academic_management' : 'academic';
        $policy = new ProjectCapabilityService();
        $canViewPrivateResource = $id !== false && $id !== null
            && $policy->canViewProjectResource((int) $id, $resourceContext);
        $canViewInstitutionalProject = $id !== false && $id !== null
            && $isTeacher && $policy->canViewActiveProject((int) $id, $resourceContext);
        $canViewResource = $canViewPrivateResource || $canViewInstitutionalProject;
        $institutionalReadOnly = $canViewInstitutionalProject && !$canViewPrivateResource;
        // Un docente puede leer el catálogo institucional activo; la relación
        // académica sigue siendo necesaria para revisión y recursos privados.
        $project = $id && $access->can('project.view') && $canViewResource
            ? (new ProjectRecordModel())->find((int)$id, $access->currentUserId(), $isAdministrator, false, $institutionalReadOnly, !$isAdministrator && !$isTeacher)
            : null;

        if ($project === null) {
            (new ErrorController())->notFound();
            return;
        }

        if (!$isAdministrator && !$isTeacher && $isStudent && (string) ($project['status'] ?? '') === 'published') {
            $publishedProject = (new ProjectRecordModel())->find((int) $project['id'], null, false, true);
            if ($publishedProject !== null) {
                header('Location: ' . route('repository-detail') . '&id=' . (int) $project['id']);
                exit;
            }
        }

        /* Legacy visual demo intentionally disabled from the real project route. */
        if (false) {
            $project = [
                'id' => 999,
                'code' => 'PRA-2026-001',
                'type_code' => 'thesis',
                'type_name' => 'Titulación',
                'type' => 'Titulación',
                'title' => 'Sistema de Gestión Documental Académica (Simulación visual)',
                'description' => 'Proyecto de demostración visual para revisión de la maqueta de interfaz del estudiante.',
                'status' => 'En desarrollo',
                'status_key' => 'development',
                'period' => '2026-I',
                'tutor' => 'Lic. Diana Alegría',
                'last_activity' => date('d/m/Y H:i'),
                'participants' => [],
                'files' => [],
                'deliveries' => [],
                'review_situation_label' => 'Preparando primera entrega',
            ];
        }

        $projectContext = $isAdministrator ? 'academic_management' : 'academic';
        $studentTabs = ['documents','history','tribunal'];
        $studentTabAliases = ['summary'=>'documents','information'=>'documents','participants'=>'documents','more'=>'documents','files'=>'documents','deliveries'=>'documents','final-documents'=>'documents','observations'=>'documents','review'=>'documents','comments'=>'documents','versions'=>'history','activity'=>'history','calendar'=>'history'];
        $studentTab = $studentTabAliases[$requestedTab] ?? $requestedTab;
        $studentTab = in_array($studentTab, $studentTabs, true) ? $studentTab : 'documents';
        $legacyTabs = ['summary'=>'information','deliveries'=>'files','documents'=>'files','final-documents'=>'files','observations'=>'information','comments'=>'information','review'=>'information','history'=>'information','calendar'=>'information','activity'=>'information','participants'=>'information','more'=>'information'];
        $tab = $legacyTabs[$requestedTab] ?? $requestedTab;
        $tab = in_array($tab, ['information','files','evolution'], true) ? $tab : 'information';
        $project['review_situation']=$institutionalReadOnly ? [] : (new ProjectReviewSituationService())->forProject((int)$project['id']);
        $rawHistoryPerPage = (int) ($_GET['history_per_page'] ?? $this->query['history_per_page'] ?? 10);
        $historyLimit = ($rawHistoryPerPage >= 1 && $rawHistoryPerPage <= 100) ? $rawHistoryPerPage : 10;
        $historyPageNumber = max(1, (int) ($_GET['history_page'] ?? $this->query['history_page'] ?? 1));
        $historyOffset = ($historyPageNumber - 1) * $historyLimit;
        $academicPage = $institutionalReadOnly
            ? ['events'=>[],'total'=>0,'loaded'=>0,'has_more'=>false,'next_offset'=>0]
            : (new ProjectAcademicTimelineService())->page((int) $project['id'], $historyOffset, $historyLimit);

        $totalEvents = (int) ($academicPage['total'] ?? 0);
        $totalPages = max(1, (int) ceil($totalEvents / $historyLimit));
        $currentPage = min($historyPageNumber, $totalPages);
        $actualOffset = ($currentPage - 1) * $historyLimit;

        if ($currentPage !== $historyPageNumber) {
            $academicPage = $institutionalReadOnly
                ? ['events'=>[],'total'=>0,'loaded'=>0,'has_more'=>false,'next_offset'=>0]
                : (new ProjectAcademicTimelineService())->page((int) $project['id'], $actualOffset, $historyLimit);
        }

        foreach ($academicPage['events'] as &$academicEvent) {
            $academicEvent['date'] = (string)($academicEvent['occurred_at_local'] ?? $academicEvent['date'] ?? '');
            $visibleMeta = (array)($academicEvent['meta'] ?? []);
            if (!empty($academicEvent['file']['name'])) $visibleMeta[] = 'Archivo: ' . (string)$academicEvent['file']['name'];
            if (($academicEvent['event_type'] ?? '') === 'document_version_uploaded') $visibleMeta[] = 'Activa';
            if (($academicEvent['event_type'] ?? '') === 'document_version_archived') {
                $visibleMeta[] = 'Archivada';
                if ((int)($academicEvent['metadata']['payload']['unavailable_count'] ?? 0) > 0) $visibleMeta[] = 'No disponible';
            }
            $academicEvent['meta'] = array_values(array_unique($visibleMeta));
        }
        unset($academicEvent);

        $project['academic_history'] = $academicPage['events'];
        $project['academic_history_total'] = $totalEvents;
        $project['academic_history_pagination'] = [
            'page' => $currentPage,
            'per_page' => $historyLimit,
            'total' => $totalEvents,
            'pages' => $totalPages,
            'from' => $totalEvents > 0 ? $actualOffset + 1 : 0,
            'to' => min($actualOffset + count($academicPage['events']), $totalEvents),
            'page_key' => 'history_page',
            'size_key' => 'history_per_page',
        ];

        $descriptionService = new ProjectDescriptionService();
        $isStudentParticipant = !$isAdministrator && in_array('student', $access->currentRoles(), true)
            && count(array_filter($project['participants'], static fn (array $participant): bool =>
                (int) $participant['user_id'] === $access->currentUserId()
                && (string) $participant['role_code'] === 'student'
                && (string) ($participant['status'] ?? 'active') === 'active'
                && empty($participant['removed_at']))) > 0;
        $descriptionReminder = $isStudentParticipant
            ? $descriptionService->pendingReminder((int) $project['id'], $access->currentUserId())
            : null;
        $projectCapabilities = (new ProjectCapabilityService())->forCurrentUser($project, $projectContext);
        $institutionalReadOnly = !$isAdministrator && $isTeacher && empty($projectCapabilities['review_documents']);
        $adjustmentData = ['items' => [], 'summary' => ['has_pending_adjustments' => false, 'pending_count' => 0, 'latest' => null]];
        if (!empty($projectCapabilities['view_adjustment_requests'])) {
            try {
                $adjustmentData = (new ProjectAdjustmentRequestService())->listForProject(
                    (int) $project['id'],
                    (string) $project['status'],
                    (int) $session->userId(),
                    $projectContext
                );
            } catch (ProjectAdjustmentRequestException $exception) {
                error_log('Project adjustment UI: ' . $exception->getMessage());
            }
        }
        $hasAdjustmentUi = (bool) array_filter([
            $projectCapabilities['create_adjustment_request'] ?? false,
            $projectCapabilities['view_adjustment_requests'] ?? false,
            $projectCapabilities['respond_adjustment_request'] ?? false,
            $projectCapabilities['address_adjustment_request'] ?? false,
            $projectCapabilities['close_adjustment_request'] ?? false,
            $projectCapabilities['approve_adjustment_request'] ?? false,
            $projectCapabilities['reject_adjustment_request'] ?? false,
        ]);
        $documentReview = null;
        if ($projectContext === 'academic_management' && (string)$project['status'] === 'development') {
            $documentReview = (new ProjectDocumentReviewService())->describeCurrentFiles((int)$project['id'], (array)$project['files'], true);
            $project['files'] = $documentReview['files'];
        }
        $studentDocumentReview = null;
        $correctionReadiness = null;
        $studentVersions = [];
        $historicalVersion = null;
        $studentDefense = null;
        if (!$isAdministrator && !($isTeacher && $institutionalReadOnly)) {
            $correctionReadiness = (new ProjectCorrectionReadinessService())->forProject((int) $project['id']);
            $studentDocumentReview = (new ProjectDocumentReviewService())->describeCurrentFiles(
                (int) $project['id'], (array) $project['files'], $isTeacher, $isTeacher
            );
            $project['files'] = $studentDocumentReview['files'];
            $studentVersions = (new ProjectDocumentModel())->academicVersions((int) $project['id']);
            $historicalId = (int)($_GET['version_id'] ?? 0);
            if ($historicalId > 0) {
                try { $historicalVersion = (new ProjectFileVersionHistoryService())->accessibleVersion((int)$project['id'], $historicalId, $access->currentUserId(), 'academic'); }
                catch (Throwable $exception) { error_log('Historical workspace version: '.$exception->getMessage()); }
            }
            if ((string) $project['type_code'] === 'thesis') $studentDefense = (new ThesisDefenseService())->current((int) $project['id']);
        }
        $returnUrl = ($isAdministrator || $isTeacher)
            ? $this->academicManagementReturnUrl((string) ($_GET['return'] ?? ''))
            : route('projects');
        // La variante fullscreen pertenece a la capacidad efectiva de revisión,
        // no al lugar desde el que se abrió el expediente. Así Dashboard y
        // Proyectos asignados comparten exactamente el mismo workspace.
        $projectReviewFullscreen = !$isAdministrator
            && $isTeacher
            && !empty($projectCapabilities['review_documents']);
        $studentBackUrl = route('dashboard');
        $studentBackLabel = 'Volver al inicio';
        if (!$isAdministrator && !$isTeacher && $isStudentParticipant) {
            try {
                $studentProjects = (new ProjectModel())->getStudentProjectsResult($access->currentUserId());
                if (($studentProjects['status'] ?? 'error') === 'loaded' && count((array) ($studentProjects['items'] ?? [])) > 1) {
                    $studentBackUrl = route('projects');
                    $studentBackLabel = 'Volver a Proyectos';
                }
            } catch (Throwable $exception) {
                error_log('Student project back link: ' . $exception->getMessage());
            }
        }
        $detailUrl = route('project-detail') . '&id=' . (int) $project['id'];
        if ($isAdministrator || $isTeacher) $detailUrl .= '&return=' . rawurlencode($returnUrl);
        $studentAcademicPackage = ['available' => false, 'download_url' => '', 'file_count' => 0, 'size_bytes' => 0, 'size' => '', 'source' => 'academic'];
        if ($isStudentParticipant && !$isAdministrator && !$isTeacher) {
            try {
                $studentAcademicPackage = (new ProjectRepositoryPackageService())->describeAcademic(
                    (int) $project['id'],
                    route('project-package-download') . '&id=' . (int) $project['id']
                );
            } catch (Throwable $exception) {
                error_log('Student academic package descriptor: ' . $exception->getMessage());
            }
        }
        $projectDocuments = null;
        $projectStatusTransitions = [];
        if (!empty($projectCapabilities['manage_files']) || $documentReview !== null) {
            $documentModel = new ProjectDocumentModel();
            $projectDocuments = [
                'context' => 'academic_management',
                'restorable' => !empty($projectCapabilities['manage_files']) ? $documentModel->restorable((int) $project['id']) : [],
                'versions' => $documentModel->versions((int) $project['id']),
                'limits' => (new ProjectDocumentFileService())->limits(),
                'endpoint' => !empty($projectCapabilities['manage_files']) ? route('admin-project-file') : '',
                'csrf' => !empty($projectCapabilities['manage_files']) ? $session->csrfToken('admin_projects') : '',
            ];
        }
        if (!empty($projectCapabilities['change_status'])) {
            $projectStatusTransitions = (new ProjectStatusTransitionService())->availableTransitions($project);
        }
        if (!empty($projectCapabilities['request_corrections'])) {
            $correctionAction=(new ProjectReviewService())->availableCorrectionAction($project);
            if($correctionAction!==null)$projectStatusTransitions[]=$correctionAction;
        }

        View::render('projects/detail', [
            'currentPage' => 'projects',
            'title' => ($isAdministrator || $isTeacher)
                ? (string) $project['code'] . ' · Gestión académica'
                : ($project['title'] ?? 'Proyecto no encontrado') . ' | Gestión Académica',
            'bodyClass' => 'project-detail-page'
                . (!$isAdministrator && !$isTeacher ? ' student-project-workspace-page workspace-fullscreen' : '')
                . ($projectReviewFullscreen ? ' project-review-fullscreen' : ''),
            'studentWorkspaceFullscreen' => !$isAdministrator && !$isTeacher,
            'isTeacherContext' => $isTeacher,
            'institutionalReadOnly' => $institutionalReadOnly,
            'pageStyles' => array_values(array_filter([
                asset('css/project-simplified.css'), asset('css/project-description.css'),
                asset('css/project-adjustments.css'), asset('css/project-academic-timeline.css'),
                $isAdministrator ? asset('css/admin-projects.css') : asset('css/student-project-workspace.css'),
                !empty($projectCapabilities['publish_project']) ? asset('css/projects.css') : null,
            ])),
            'pageScript' => asset('js/repository-detail.js'),
            'pageScripts' => array_values(array_filter([
                $descriptionReminder ? asset('js/project-description.js') : null,
                $hasAdjustmentUi ? asset('js/project-adjustments.js') : null,
                !$isAdministrator ? asset('vendor/jszip/3.10.1/jszip.min.js') : null,
                $isAdministrator ? asset('js/admin-projects.js') : ($isStudent && !$isTeacher ? asset('js/student-project-workspace.js') : null),
                !$isAdministrator && !empty($projectCapabilities['review_documents']) ? asset('js/teacher-project-review.js') : null,
                !empty($projectCapabilities['publish_project']) ? asset('js/projects.js') : null,
                $isAdministrator && $projectStatusTransitions !== [] ? asset('js/project-status-transition.js') : null,
            ])),
            'project' => $project,
            'activeTab' => $tab,
            'studentActiveTab' => $studentTab,
            'studentDocumentReview' => $studentDocumentReview,
            'correctionReadiness' => $correctionReadiness,
            'studentVersions' => $studentVersions,
            'historicalVersion' => $historicalVersion,
            'studentDefense' => $studentDefense,
            'studentDocumentEndpoint' => route('student-project-document'),
            'studentDocumentCsrf' => $session->csrfToken('student_project_documents'),
            'isAdministrator' => $isAdministrator,
            'publicContext' => false,
            'projectContext' => $projectContext,
            'projectCapabilities' => $projectCapabilities,
            'canReview' => !empty($projectCapabilities['review_delivery']) || !empty($projectCapabilities['create_observation']) || !empty($projectCapabilities['respond_observation']),
            'canDeliver' => !empty($projectCapabilities['register_delivery']),
            'projectEditUrl' => route('projects') . '&edit=' . (int) $project['id'],
            'detailUrl' => $detailUrl,
            'returnUrl' => $returnUrl,
            'studentBackUrl' => $studentBackUrl,
            'studentBackLabel' => $studentBackLabel,
            'previewActionUrl' => route('project-file-preview') . ($isAdministrator ? '&context=academic_management' : ''),
            'downloadActionUrl' => route('project-file-download') . ($isAdministrator ? '&context=academic_management' : ''),
            'projectDocuments' => $projectDocuments,
            'documentReview' => $documentReview,
            // La auditoría administrativa permanece interna; no se expone como acción del historial académico.
            'projectHistoryEndpoint' => '',
            'studentProjectSaveEndpoint' => !empty($projectCapabilities['edit_information']) ? route('student-project-save-information') : '',
            'studentProjectEditCsrf' => !empty($projectCapabilities['edit_information']) ? $session->csrfToken('student_project_edit_info') : '',
            'studentProjectSubmitEndpoint' => !empty($projectCapabilities['send_for_review']) ? route('student-project-submit-review') : '',
            'studentProjectSubmitCsrf' => !empty($projectCapabilities['send_for_review']) ? $session->csrfToken('student_project_submit_review') : '',
            'studentProjectPublishEndpoint' => !empty($projectCapabilities['publish_project']) ? route('student-project-publish') : '',
            'studentProjectPublishCsrf' => !empty($projectCapabilities['publish_project']) ? $session->csrfToken('student_project_publish') : '',
            'studentProjectEditorCatalogs' => !empty($projectCapabilities['edit_information']) ? (new AdminProjectModel())->catalogs() : [],
            'currentUserId' => (int) $session->userId(),
            'projectStatusTransitions' => $projectStatusTransitions,
            'projectStatusEndpoint' => $isAdministrator ? route('admin-project-save') : '',
            'projectStatusCsrf' => $isAdministrator ? $session->csrfToken('admin_projects') : '',
            'projectSaveEndpoint' => $isAdministrator ? route('admin-project-save') : '',
            'projectTrashEndpoint' => $isAdministrator ? route('admin-project-trash') : '',
            'projectTrashCsrf' => $isAdministrator ? $session->csrfToken('admin_projects') : '',
            'projectEditorCatalogs' => $isAdministrator ? (new AdminProjectModel())->catalogs() : [],
            'descriptionReminder' => $descriptionReminder,
            'studentAcademicPackage' => $studentAcademicPackage,
            'descriptionCsrf' => $session->csrfToken('project_description'),
            'descriptionSaveEndpoint' => route('project-description-save'),
            'lifecycleDescription' => $descriptionService->effectiveDescription((string) $project['type_code'], $project['summary'] ?? null),
            'academicHistoryEndpoint' => !empty($projectCapabilities['view_academic_history']) ? route('project-academic-history-events') . '&project_id=' . (int) $project['id'] . '&context=' . rawurlencode($projectContext) : '',
            'adjustmentData' => $adjustmentData,
            'adjustmentContext' => $projectContext,
            'adjustmentCsrf' => $session->csrfToken('project_adjustment'),
            'hasPendingModificationRequest' => false,
            'adjustmentEndpoints' => [
                'create' => route('project-adjustment-create'), 'respond' => route('project-adjustment-respond'),
                'address' => route('project-adjustment-address'), 'close' => route('project-adjustment-close'),
                'approve' => route('project-adjustment-approve'), 'reject' => route('project-adjustment-reject'),
            ],
        ]);
    }

    private function academicManagementReturnUrl(string $candidate): string
    {
        $fallback = route('projects');
        if ($candidate === '' || strlen($candidate) > 2048) return $fallback;
        $parts = parse_url($candidate);
        if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])) return $fallback;
        parse_str((string) ($parts['query'] ?? ''), $query);
        $page = strtolower(trim((string) ($query['page'] ?? '')));
        if (!in_array($page, ['projects', 'proyectos', 'mis-proyectos', 'thesis-management', 'assigned-projects'], true)) return $fallback;

        $safe = ['page' => $page];
        foreach (['p', 'type_id', 'period_id'] as $key) {
            $value = filter_var($query[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($value !== false && $value !== null) $safe[$key] = (int) $value;
        }
        $perPage = filter_var($query['per_page'] ?? null, FILTER_VALIDATE_INT);
        if (in_array($perPage, [10, 25, 50, 75, 100], true)) $safe['per_page'] = (int) $perPage;
        foreach (['search' => 100, 'status' => 32, 'situation' => 32, 'review_situation' => 32, 'sort' => 32, 'group' => 32, 'tab' => 32] as $key => $limit) {
            if (!isset($query[$key]) || !is_scalar($query[$key])) continue;
            $value = mb_substr(trim((string) $query[$key]), 0, $limit);
            if ($value !== '') $safe[$key] = $value;
        }
        return base_url('index.php?' . http_build_query($safe));
    }

    public function academicHistoryEvents(): void
    {
        $projectId=filter_var($_GET['project_id']??null,FILTER_VALIDATE_INT);$offset=max(0,(int)($_GET['offset']??0));
        $context=(string)($_GET['context']??'academic');
        $policy = new ProjectCapabilityService();
        $historyResource = $context === 'academic' ? 'academic_history' : 'private';
        if (!$projectId || !$policy->canViewProjectResource((int)$projectId, $context, $historyResource)) { http_response_code(403); $this->json(['success'=>false,'message'=>'No tienes autorización para consultar el historial académico de este proyecto.','data'=>[]]); }
        $capabilities=$policy->forProjectId((int)$projectId,$context);
        if(empty($capabilities['view_academic_history'])){http_response_code(403);$this->json(['success'=>false,'message'=>'No tienes autorización para consultar el historial académico de este proyecto.','data'=>[]]);}
        $page=$context==='repository'
            ?(new ProjectRecordModel())->academicHistoryPage((int)$projectId,$offset,15)
            :(new ProjectAcademicTimelineService())->page((int)$projectId,$offset,15);
        $this->json(['success'=>true,'message'=>'Historial académico cargado.','data'=>$page]);
    }

    /** Guarda exclusivamente la información académica editable por un estudiante participante activo. */
    public function saveStudentProjectInformation(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            $this->json(['success'=>false,'message'=>'Método no permitido.','data'=>[]]);
        }
        $session = new AuthSessionService();
        $actorId = (int) ($session->userId() ?? 0);
        if ($actorId < 1) {
            http_response_code(401);
            $this->json(['success'=>false,'message'=>'Tu sesión expiró. Inicia sesión nuevamente para continuar.','data'=>[]]);
        }
        if (!$session->validateCsrf('student_project_edit_info', (string) ($_POST['_csrf'] ?? ''))) {
            http_response_code(419);
            $this->json(['success'=>false,'message'=>'Tu sesión expiró. Inicia sesión nuevamente para continuar.','data'=>[]]);
        }
        $projectId = filter_var($_POST['project_id'] ?? $_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if ($projectId === false || $projectId === null) {
            http_response_code(422);
            $this->json(['success'=>false,'message'=>'El proyecto solicitado no es válido.','data'=>[]]);
        }
        $exists = Database::connection()->prepare('SELECT 1 FROM projects WHERE id=:id AND deleted_at IS NULL');
        $exists->execute(['id'=>(int) $projectId]);
        if (!$exists->fetchColumn()) {
            http_response_code(404);
            $this->json(['success'=>false,'message'=>'El proyecto solicitado no está disponible.','data'=>[]]);
        }
        if (empty((new ProjectCapabilityService())->forProjectId((int) $projectId, 'academic')['edit_information'])) {
            http_response_code(403);
            $this->json(['success'=>false,'message'=>'No tienes autorización para editar este proyecto.','data'=>[]]);
        }
        $input = [
            'title' => $_POST['title'] ?? null,
            'summary' => $_POST['summary'] ?? null,
            'tutoring_user_ids' => $_POST['tutoring_user_ids'] ?? [],
            'tutoring_primary_id' => $_POST['tutoring_primary_id'] ?? null,
            'author_user_ids' => $_POST['author_user_ids'] ?? [],
            'author_leader_id' => $_POST['author_leader_id'] ?? null,
        ];
        try {
            $result = (new StudentProjectInformationService())->save((int) $projectId, $input, $actorId);
            if (!empty($result['changed'])) {
                $db = Database::connection();
                $projectQuery = $db->prepare('SELECT p.id, p.title, p.summary, p.tutor_id, u.full_name AS tutor_name, u.email AS tutor_email FROM projects p LEFT JOIN users u ON u.id = p.tutor_id WHERE p.id = :id');
                $projectQuery->execute(['id' => (int) $projectId]);
                $pRow = $projectQuery->fetch();
                $participantsQuery = $db->prepare("SELECT pp.user_id, pp.role_code, pp.is_leader, u.full_name, u.email, sp.institutional_code FROM project_participants pp INNER JOIN users u ON u.id = pp.user_id LEFT JOIN student_profiles sp ON sp.user_id = u.id WHERE pp.project_id = :id AND pp.status = 'active' AND pp.removed_at IS NULL ORDER BY pp.assigned_at, pp.user_id");
                $participantsQuery->execute(['id' => (int) $projectId]);
                $pList = $participantsQuery->fetchAll();
                $result['updated_data'] = [
                    'title' => (string) ($pRow['title'] ?? ''),
                    'summary' => (string) ($pRow['summary'] ?? ''),
                    'tutors' => array_values(array_map(static fn($item) => ['user_id' => (int) $item['user_id'], 'full_name' => (string) $item['full_name'], 'email' => (string) $item['email']], array_filter($pList, static fn($item) => in_array(strtolower((string) $item['role_code']), ['tutor', 'cotutor', 'co_tutor', 'co-tutor'], true)))),
                    'authors' => array_values(array_map(static fn($item) => ['user_id' => (int) $item['user_id'], 'full_name' => (string) $item['full_name'], 'email' => (string) $item['email'], 'is_leader' => !empty($item['is_leader']), 'institutional_code' => (string) ($item['institutional_code'] ?? '')], array_filter($pList, static fn($item) => strtolower((string) $item['role_code']) === 'student'))),
                ];
            }
            $this->json(['success'=>true,'message'=>!empty($result['changed'])?'Información del proyecto actualizada.':'No se detectaron cambios en el proyecto.','data'=>$result]);
        } catch (StudentProjectInformationException $exception) {
            http_response_code($exception->httpStatus());
            $this->json(['success'=>false,'message'=>$exception->getMessage(),'data'=>[]]);
        } catch (ProjectTutoringException|ProjectAuthorException $exception) {
            http_response_code(422);
            $this->json(['success'=>false,'message'=>$exception->getMessage(),'data'=>[]]);
        } catch (Throwable $exception) {
            error_log('Student project information: '.$exception->getMessage());
            http_response_code(500);
            $this->json(['success'=>false,'message'=>'No fue posible actualizar la información del proyecto.','data'=>[]]);
        }
    }

    public function saveDescription(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            $this->json(['success' => false, 'message' => 'Método no permitido.', 'data' => []]);
        }
        $session = new AuthSessionService();
        $actorId = (int) ($session->userId() ?? 0);
        if (!$session->isAuthenticated() || $actorId < 1) {
            http_response_code(401);
            $this->json(['success' => false, 'message' => 'Tu sesión expiró. Inicia sesión nuevamente para continuar.', 'data' => []]);
        }
        if (!$session->validateCsrf('project_description', (string) ($_POST['_csrf'] ?? ''))) {
            http_response_code(403);
            $this->json(['success' => false, 'message' => 'La sesión del formulario venció.', 'data' => []]);
        }
        try {
            (new ProjectDescriptionService())->saveForStudent((int) ($_POST['project_id'] ?? 0), $actorId, (string) ($_POST['description'] ?? ''));
            $this->json(['success' => true, 'message' => 'Descripción guardada correctamente.', 'data' => []]);
        } catch (InvalidArgumentException $exception) {
            http_response_code(422);
            $this->json(['success' => false, 'message' => $exception->getMessage(), 'data' => []]);
        } catch (Throwable $exception) {
            error_log('Project description: ' . $exception->getMessage());
            http_response_code(500);
            $this->json(['success' => false, 'message' => 'No fue posible guardar la descripción.', 'data' => []]);
        }
    }

    /** Envía documentos pendientes a revisión usando la identidad de la sesión. */
    public function submitStudentProjectForReview(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            $this->json(['success'=>false,'message'=>'Método no permitido.','data'=>[]]);
        }
        $session = new AuthSessionService();
        $actorId = (int) ($session->userId() ?? 0);
        if ($actorId < 1) {
            http_response_code(401);
            $this->json(['success'=>false,'message'=>'Tu sesión expiró. Inicia sesión nuevamente para continuar.','data'=>[]]);
        }
        if (!$session->validateCsrf('student_project_submit_review', (string) ($_POST['_csrf'] ?? ''))) {
            http_response_code(419);
            $this->json(['success'=>false,'message'=>'Tu sesión expiró. Inicia sesión nuevamente para continuar.','data'=>[]]);
        }
        $projectId = filter_var($_POST['project_id'] ?? $_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if ($projectId === false || $projectId === null) {
            http_response_code(422);
            $this->json(['success'=>false,'message'=>'El proyecto solicitado no es válido.','data'=>[]]);
        }
        if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
        try {
            $result = (new StudentProjectSubmissionService())->submitForReview((int) $projectId, $actorId);
            $result['capabilities'] = (new ProjectCapabilityService())->forProjectId((int) $projectId, 'academic');
            $this->json(['success'=>true,'message'=>'Documentos enviados a revisión correctamente.','data'=>$result]);
        } catch (StudentProjectSubmissionException $exception) {
            http_response_code($exception->httpStatus());
            $this->json(['success'=>false,'message'=>$exception->getMessage(),'data'=>$exception->data()]);
        } catch (Throwable $exception) {
            error_log('Student project submission: '.$exception->getMessage());
            http_response_code(500);
            $this->json(['success'=>false,'message'=>'No fue posible enviar los documentos a revisión.','data'=>[]]);
        }
    }

    /** Permite al estudiante participante alternar el estado de una observación entre pendiente y atendida. */
    /*
     * Observation status is controlled by the formal document-correction flow.
     * Students must not mutate pending/addressed directly from the workspace.
     */
    private function toggleStudentObservationStatus(): void
    {
        http_response_code(410);
        $this->json(['success'=>false,'message'=>'El estado de la observación se actualiza mediante el flujo documental.','data'=>[]]);
        } /* Legacy implementation intentionally removed; route removed.
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            $this->json(['success'=>false,'message'=>'Método no permitido.','data'=>[]]);
        }
        $session = new AuthSessionService();
        $actorId = (int) ($session->userId() ?? 0);
        if ($actorId < 1) {
            http_response_code(401);
            $this->json(['success'=>false,'message'=>'Tu sesión expiró. Inicia sesión nuevamente para continuar.','data'=>[]]);
        }
        if (!$session->validateCsrf('student_project_documents', (string) ($_POST['_csrf'] ?? ''))) {
            http_response_code(419);
            $this->json(['success'=>false,'message'=>'No fue posible validar la solicitud. Recarga la página e inténtalo nuevamente.','data'=>[]]);
        }
        $projectId = filter_var($_POST['project_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        $observationId = filter_var($_POST['observation_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        $targetStatus = strtolower(trim((string)($_POST['status'] ?? '')));

        if ($projectId === false || $projectId === null || $observationId === false || $observationId === null) {
            http_response_code(422);
            $this->json(['success'=>false,'message'=>'La solicitud no es válida.','data'=>[]]);
        }
        if (!in_array($targetStatus, ['pending', 'addressed'], true)) {
            http_response_code(422);
            $this->json(['success'=>false,'message'=>'El estado de la observación no es válido.','data'=>[]]);
        }

        $db = Database::connection();
        $queryParticipant = $db->prepare("SELECT 1 FROM project_participants WHERE project_id=:project AND user_id=:user AND role_code='student' AND status='active' AND removed_at IS NULL LIMIT 1");
        $queryParticipant->execute(['project'=>$projectId, 'user'=>$actorId]);
        if (!$queryParticipant->fetchColumn()) {
            http_response_code(403);
            $this->json(['success'=>false,'message'=>'No tienes autorización para modificar observaciones en este proyecto.','data'=>[]]);
        }

        $queryObs = $db->prepare("SELECT id, status FROM project_observations WHERE id=:id AND project_id=:project LIMIT 1");
        $queryObs->execute(['id'=>$observationId, 'project'=>$projectId]);
        $obs = $queryObs->fetch();
        if (!$obs) {
            http_response_code(404);
            $this->json(['success'=>false,'message'=>'La observación solicitada no existe.','data'=>[]]);
        }

        $update = $db->prepare("UPDATE project_observations SET status=:status WHERE id=:id AND project_id=:project");
        $update->execute(['status'=>$targetStatus, 'id'=>$observationId, 'project'=>$projectId]);

        $this->json(['success'=>true, 'message'=>'Estado de la observación actualizado correctamente.', 'data'=>['id'=>$observationId, 'status'=>$targetStatus]]);
    }

    */
    public function filePreview(): void
    {
        [$project, $file, $stream, $source, $institutionalAccess] = $this->resolveFile(true, true);
        $query='&project_id='.(int)$project['id'].'&file_id='.(int)$file['id'];
        $requestedContext = (string) ($_GET['context'] ?? '');
        $scope=(string)($_GET['scope']??'')==='repository'?'&scope=repository':($requestedContext==='academic_management'?'&context=academic_management':($requestedContext==='repository_owner'?'&context=repository_owner':''));
        $version=!empty($file['checksum_sha256'])?'&v='.rawurlencode(substr((string)$file['checksum_sha256'],0,16)):'';
        $forceRetry = !empty($_GET['retry_preview']) || !empty($_GET['retry']);
        if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
        $useClientSideDocxPreview = $scope === '&scope=repository';
        if (strtolower((string)$file['extension']) === 'docx' && !$useClientSideDocxPreview) {
            $preview = $this->docxPreview($project, $file, $source, (string)$file['checksum_sha256'], route('project-file-preview-pdf').$query.$scope.$version, $forceRetry, !$institutionalAccess);
        } else {
            $preview=(new FilePreviewService())->prepare($this->previewFile($file,$stream),route('project-file-content').$query.$scope.$version,$institutionalAccess?'':route('project-file-download').$query.$scope);
        }
        fclose($stream);header('Cache-Control: private, no-store, max-age=0');$this->json(['success'=>true,'message'=>$preview['message'],'data'=>['preview'=>$preview]]);
    }

    /** Streams only a current, authorized, private DOCX-derived PDF; physical preview paths stay hidden. */
    public function filePreviewPdf(): void
    {
        [$project, $file, $stream, $source, $institutionalAccess] = $this->resolveFile(false, true); fclose($stream);
        $checksum = (string)$file['checksum_sha256'];
        if (!hash_equals($checksum, (string)($_GET['v'] ?? $checksum)) && !hash_equals(substr($checksum, 0, 16), (string)($_GET['v'] ?? ''))) { http_response_code(404); exit; }
        $path = strtolower((string)$file['extension']) === 'docx'
            ? ((!$institutionalAccess ? (new ProjectReviewRepresentationService())->supplementalPath((int)$project['id'], (int)$file['id'], $checksum) : null) ?? (new DocumentPreviewConversionService())->cachedPath((int)$project['id'], (int)$file['id'], $checksum))
            : (new DocumentPreviewConversionService())->cachedPath((int)$project['id'], (int)$file['id'], $checksum);
        if ($path === null) { http_response_code(404); exit('La vista previa no está disponible.'); }
        $pdf = fopen($path, 'rb'); if ($pdf === false) { http_response_code(404); exit; }
        $this->stream(['original_name'=>(string)$file['original_name'].'.pdf','size_bytes'=>(int)filesize($path),'mime_type'=>'application/pdf'], $pdf, 'inline', 'application/pdf');
    }

    public function fileVersionPreview(): void
    {
        try {
            $version=$this->historicalVersion();
            $ext=strtolower((string)($version['extension']??''));
            $isZip=$ext==='zip';
            $url=route('project-file-version-preview-pdf').'&project_id='.(int)$version['project_id'].'&version_id='.(int)$version['id'].'&v='.rawurlencode(substr((string)$version['checksum_sha256'],0,16));
            $observations=(new ProjectDocumentReviewService())->observationsForRevision((int)$version['project_id'],(int)$version['file_id'],(string)$version['checksum_sha256']);
            $this->json(['success'=>true,'message'=>'Vista histórica disponible.','data'=>['preview'=>['preview_type'=>$isZip?'zip':'pdf','extension'=>$ext,'file_id'=>(int)$version['file_id'],'version_id'=>(int)$version['id'],'review_representation'=>true,'content_url'=>$url,'original_name'=>$version['original_name'],'size_bytes'=>(int)$version['size_bytes'],'historical'=>true,'version_number'=>(int)$version['version_number'],'checksum_sha256'=>$version['checksum_sha256'],'observations'=>$observations]]]);
        } catch (Throwable $e) { error_log('Historical preview metadata: '.$e->getMessage()); http_response_code(404); $this->json(['success'=>false,'message'=>'La versión histórica no está disponible.','data'=>[]]); }
    }

    public function fileVersionPreviewPdf(): void
    {
        try {
            $version=$this->historicalVersion(); $verified=(new ProjectDocumentStorageService())->verifyHistoricalBinary($version);
            if (empty($verified['verified'])) throw new RuntimeException((string)($verified['reason']??'Binario no verificable.'));
            $path=(new ProjectDocumentFileService())->resolveStoredFile((int)$version['project_id'],(string)$version['storage_name']);
            if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
            if (strtolower((string)$version['extension'])==='docx') {
                $checksum=(string)$version['checksum_sha256'];
                $path=(new ProjectReviewRepresentationService())->supplementalPath((int)$version['project_id'],(int)$version['file_id'],$checksum)
                    ?? (new DocumentPreviewConversionService())->cachedPath((int)$version['project_id'],(int)$version['file_id'],$checksum);
                if ($path === null) throw new RuntimeException('La vista previa histórica no está preparada.');
            }
            $stream=fopen($path,'rb'); if ($stream===false) throw new RuntimeException('Preview no disponible.');
            $this->stream(['original_name'=>(string)$version['original_name'].'.pdf','size_bytes'=>(int)filesize($path),'mime_type'=>'application/pdf'],$stream,'inline','application/pdf');
        } catch (Throwable $e) { error_log('Historical preview PDF: '.$e->getMessage()); http_response_code(404); exit('La vista previa histórica no está disponible.'); }
    }

    private function historicalVersion(): array
    {
        $session=new AuthSessionService(); $projectId=(int)($_GET['project_id']??0); $versionId=(int)($_GET['version_id']??0);
        if (!$session->isAuthenticated()||$projectId<1||$versionId<1) throw new InvalidArgumentException('Solicitud no válida.');
        $context=$session->isAdminModeActive()?'academic_management':'academic';
        if (!(new ProjectCapabilityService())->canViewProjectResource($projectId, $context)) throw new RuntimeException('No tienes autorización para consultar esta versión.');
        throw new InvalidArgumentException('Las versiones historicas no estan disponibles para archivos.');
    }

    public function fileContent(): void
    {
        [, $file, $stream] = $this->resolveFile(false, true); $data=$this->previewFile($file,$stream);
        $service=new FilePreviewService();$prepared=$service->prepare($data,'','');
        if (!$service->canStreamInline($data)) { fclose($stream); http_response_code(415); exit('Este formato debe descargarse para consultarlo.'); }
        session_write_close(); $this->stream($file,$stream,'inline',(string)$prepared['mime']);
    }

    public function fileDownload(): void
    {
        [, $file, $stream] = $this->resolveFile(); session_write_close(); $this->stream($file,$stream,'attachment');
    }

    /** Descarga el paquete privado de archivos activos de un expediente académico. */
    public function downloadAcademicPackage(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') { http_response_code(405); exit; }
        $session = new AuthSessionService();
        $projectId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if (!$session->isAuthenticated() || !$projectId) { http_response_code(403); exit; }
        $access = new ProjectAccessService();
        $administrator = $session->isAdminModeActive();
        $requestedContext = strtolower(trim((string) ($_GET['context'] ?? '')));
        $context = $administrator ? 'academic_management' : ($requestedContext === 'repository_owner' ? 'repository_owner' : 'academic');
        $policy = new ProjectCapabilityService();
        $isTeacher = in_array('teacher', array_map('strtolower', array_map('strval', $access->currentRoles())), true);
        $canViewPrivate = $policy->canViewProjectResource((int)$projectId, $context);
        $canViewInstitutional = !$administrator && $isTeacher && $policy->canViewActiveProject((int)$projectId, $context);
        if (!$canViewPrivate && !$canViewInstitutional) { http_response_code(403); exit('No tienes autorización para descargar este paquete.'); }
        $institutionalReadOnly = $canViewInstitutional && !$canViewPrivate;
        $capabilities = $policy->forProjectId((int)$projectId, $context);
        if (empty($capabilities['download_academic_package'])) { http_response_code(403); exit('No tienes autorización para descargar este paquete.'); }
        $project = (new ProjectRecordModel())->find(
            (int) $projectId,
            $access->currentUserId(),
            $administrator,
            false,
            $institutionalReadOnly,
            false,
            $context === 'repository_owner'
        );
        if ($project === null) { http_response_code(404); exit('El proyecto solicitado no está disponible.'); }
        try {
            $packages = new ProjectRepositoryPackageService();
            $isRepositoryOwner = $context === 'repository_owner';
            $packageUrl = route('project-package-download') . '&id=' . (int)$projectId . ($isRepositoryOwner ? '&context=repository_owner' : '');
            $descriptor = $isRepositoryOwner
                ? $packages->describeRepositoryOwner((int)$projectId, $packageUrl)
                : $packages->describeAcademic((int)$projectId, $packageUrl);
            if (!$isRepositoryOwner && empty($descriptor['available']) && (int)($descriptor['file_count'] ?? 0) > 0) {
                $descriptor = $packages->prepareAcademic((int)$projectId, $packageUrl);
            }
            $path = $isRepositoryOwner
                ? ProjectRepositoryPackageService::packagePath((int)$projectId)
                : ProjectRepositoryPackageService::academicPackagePath((int)$projectId);
            if ((int)($descriptor['file_count'] ?? 0) < 1) {
                http_response_code(422);
                exit('No hay archivos descargables en este proyecto.');
            }
            if (empty($descriptor['available']) || !is_file($path) || !is_readable($path)) throw new RuntimeException('El paquete no está disponible.');
            clearstatcache(true, $path);
            $size = (int)(filesize($path) ?: 0);
            if ($size < 1) throw new RuntimeException('El paquete no está disponible.');
            session_write_close();
            header('Content-Type: application/zip');
            header('Content-Length: ' . $size);
            header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode('proyecto-' . (string)$project['code'] . '.zip'));
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: private, no-store, max-age=0');
            readfile($path);
            exit;
        } catch (Throwable $exception) {
            error_log('Academic project package: ' . $exception->getMessage());
            http_response_code(500);
            exit('No fue posible preparar el paquete de archivos.');
        }
    }

    public function zipList(): void
    {
        [$project,$file,$path,$temporary]=$this->resolveProjectArchive(true);
        try{$archive=(new ArchiveService())->listDirectory($path,(string)($_GET['path']??''));}
        finally{$this->discardProjectArchive($path,$temporary);}
        if(empty($archive['success'])){$status=match($archive['status']??''){'not_found'=>404,'invalid_path'=>400,default=>422};http_response_code($status);$this->json(['success'=>false,'message'=>$archive['message'],'data'=>['archive'=>$archive]]);}
        $this->json(['success'=>true,'message'=>$archive['message'],'data'=>['archive'=>$archive]]);
    }

    public function zipEntryPreview(): void
    {
        [$project,$file,$entry]=$this->resolveProjectArchiveEntry(true);
        $query=$this->projectArchiveQuery($project,$file,(string)$entry['path']);
        $forceRetry = !empty($_GET['retry_preview']) || !empty($_GET['retry']);
        try {
            if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
            if (strtolower(pathinfo((string)$entry['name'], PATHINFO_EXTENSION)) === 'docx') {
                $identity = $this->zipPreviewIdentity($file, $entry);
                $preview = $this->docxPreviewStream($project, $file, $entry, $identity, route('project-zip-entry-preview-pdf').$query . '&v=' . rawurlencode(substr($identity, 0, 16)), $forceRetry);
            } else $preview=(new FilePreviewService())->prepare($entry,route('project-zip-entry-content').$query,route('project-zip-entry-download').$query);
        }
        finally{$this->closeProjectArchiveEntry($entry);}
        $this->json(['success'=>true,'message'=>$preview['message'],'data'=>['preview'=>$preview]]);
    }

    public function zipEntryPreviewPdf(): void
    {
        [$project,$file,$entry]=$this->resolveProjectArchiveEntry();
        try {
            if (strtolower(pathinfo((string)$entry['name'], PATHINFO_EXTENSION)) !== 'docx') { http_response_code(404); exit; }
            $identity = $this->zipPreviewIdentity($file, $entry);
            if (!hash_equals(substr($identity, 0, 16), (string)($_GET['v'] ?? ''))) { http_response_code(404); exit; }
            $path = (new DocumentPreviewConversionService())->cachedPath((int)$project['id'], (int)$file['id'], $identity);
            if ($path === null) { http_response_code(404); exit('La vista previa no está disponible.'); }
            $pdf = fopen($path, 'rb'); if ($pdf === false) { http_response_code(404); exit; }
            $this->stream(['original_name'=>(string)$entry['name'].'.pdf','size_bytes'=>(int)filesize($path),'mime_type'=>'application/pdf'], $pdf, 'inline', 'application/pdf');
        } finally { $this->closeProjectArchiveEntry($entry); }
    }

    public function zipEntryContent(): void
    {
        [,,$entry]=$this->resolveProjectArchiveEntry();$previewService=new FilePreviewService();
        if(!$previewService->canStreamInline($entry)){$this->closeProjectArchiveEntry($entry);http_response_code(415);exit('Este formato no puede visualizarse dentro de la plataforma.');}
        session_write_close();$this->streamProjectArchiveEntry($entry,'inline');
    }

    public function zipEntryDownload(): void
    {
        [,,$entry]=$this->resolveProjectArchiveEntry();session_write_close();$this->streamProjectArchiveEntry($entry,'attachment');
    }

    private function resolveProjectArchive(bool $json=false):array
    {
        [$project,$context]=$this->resolveProjectArchiveAccess($json);$projectId=(int)$project['id'];
        $fileId=filter_var($_GET['file_id']??null,FILTER_VALIDATE_INT);
        $file=$fileId?(new ProjectRecordModel())->findFile($projectId,(int)$fileId):null;
        $extension=strtolower((string)($file['extension']??''));$mime=strtolower((string)($file['mime_type']??''));
        if($file===null||($extension!=='zip'&&!in_array($mime,['application/zip','application/x-zip-compressed'],true)))$this->failProjectArchive(404,'El paquete solicitado no está disponible.',$json);
        try{$path=(new ProjectDocumentFileService())->resolveStoredFile($projectId,(string)$file['storage_name']);}
        catch(Throwable){$this->failProjectArchive(404,'El paquete solicitado no está disponible.',$json);}
        $file['context']=$context;
        return [$project,$file,$path,false];
    }

    private function resolveProjectArchiveEntry(bool $json=false):array
    {
        [$project,$file,$path,$temporary]=$this->resolveProjectArchive($json);
        try{$entry=(new ArchiveService())->openFileStream($path,(string)($_GET['path']??''));}
        finally{$this->discardProjectArchive($path,$temporary);}
        if(empty($entry['success'])){$status=match($entry['status']??''){'invalid_path'=>400,'not_found'=>404,default=>422};$this->failProjectArchive($status,(string)($entry['message']??'No fue posible abrir el archivo interno.'),$json);}
        return [$project,$file,$entry];
    }

    private function resolveProjectArchiveAccess(bool $json):array
    {
        if(session_status()!==PHP_SESSION_ACTIVE)session_start();
        if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET')$this->failProjectArchive(405,'Método no permitido.',$json);
        $scope=(string)($_GET['scope']??'');$requestedContext=(string)($_GET['context']??'');
        $repository=$scope==='repository'&&$requestedContext==='';$academicManagement=$scope===''&&$requestedContext==='academic_management';$academic=$scope===''&&$requestedContext==='academic';$repositoryOwner=$scope===''&&$requestedContext==='repository_owner';
        if(!$repository&&!$academicManagement&&!$academic&&!$repositoryOwner)$this->failProjectArchive(422,'El contexto documental no es válido.',$json);
        $context=$repository?'repository':($academicManagement?'academic_management':($repositoryOwner?'repository_owner':'academic'));$projectId=filter_var($_GET['project_id']??null,FILTER_VALIDATE_INT);
        $policy = new ProjectCapabilityService();
        if (!$projectId || !$policy->canViewProjectResource((int)$projectId, $context)) $this->failProjectArchive(403,'No tienes autorización para consultar archivos de este proyecto.',$json);
        $capabilities=$policy->forProjectId((int)$projectId,$context);
        if(empty($capabilities['download_files']))$this->failProjectArchive(403,'No tienes autorización para consultar archivos de este proyecto.',$json);
        $access=new ProjectAccessService();$session=new AuthSessionService();
        $project=$projectId&&$access->can('project.view')?(new ProjectRecordModel())->find((int)$projectId,$access->currentUserId(),$session->isAdminModeActive(),$repository,false,false,$repositoryOwner):null;
        if($project===null)$this->failProjectArchive(404,'El proyecto solicitado no está disponible en este contexto.',$json);
        return [$project,$context];
    }

    private function projectArchiveQuery(array $project,array $file,string $path):string
    {
        $context=(string)($file['context']??'');$query='&project_id='.(int)$project['id'];
        $query.='&file_id='.(int)$file['id'];
        $query.=$context==='repository'?'&scope=repository':'&context='.rawurlencode($context);
        return $query.'&path='.rawurlencode($path);
    }

    private function discardProjectArchive(string $path,bool $temporary):void{if($temporary&&is_file($path))@unlink($path);}
    private function closeProjectArchiveEntry(array $entry):void{if(isset($entry['stream'])&&is_resource($entry['stream']))fclose($entry['stream']);if(isset($entry['archive'])&&$entry['archive'] instanceof ZipArchive)$entry['archive']->close();}
    private function streamProjectArchiveEntry(array $entry,string $disposition):never
    {
        $name=basename(str_replace('\\','/',(string)$entry['name']));header('Content-Type: '.(string)$entry['mime']);header('Content-Length: '.(int)$entry['size']);header("Content-Disposition: {$disposition}; filename*=UTF-8''".rawurlencode($name));header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store, max-age=0');if($disposition==='inline')header("Content-Security-Policy: default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'");fpassthru($entry['stream']);$this->closeProjectArchiveEntry($entry);exit;
    }
    private function failProjectArchive(int $status,string $message,bool $json):never{http_response_code($status);if($json)$this->json(['success'=>false,'message'=>$message,'data'=>[]]);header('Content-Type: text/plain; charset=UTF-8');exit($message);}

    private function currentFileVersionRequestIsValid(string $currentChecksum): bool
    {
        $currentChecksum = strtolower(trim($currentChecksum));
        if (!preg_match('/\A[a-f0-9]{64}\z/', $currentChecksum)) return false;
        if (array_key_exists('version_id', $_GET)) return false;
        foreach (['v', 'checksum'] as $key) {
            if (!array_key_exists($key, $_GET)) continue;
            $requested = strtolower(trim((string) $_GET[$key]));
            if (!preg_match('/\A[a-f0-9]{16,64}\z/', $requested)
                || !hash_equals(substr($currentChecksum, 0, strlen($requested)), $requested)) return false;
        }
        return true;
    }

    private function rejectHistoricalFileRequest(bool $json): never
    {
        http_response_code(404);
        if ($json) $this->json(['success'=>false,'message'=>'El archivo solicitado no está disponible.','data'=>[]]);
        exit('El archivo solicitado no está disponible.');
    }

    private function resolveFile(bool $json=false, bool $allowInstitutionalRead=false): array
    {
        if (session_status()!==PHP_SESSION_ACTIVE) session_start();
        if (($_SERVER['REQUEST_METHOD']??'GET')!=='GET') { http_response_code(405); exit; }
        $projectId=filter_var($_GET['project_id']??null,FILTER_VALIDATE_INT); $fileId=filter_var($_GET['file_id']??null,FILTER_VALIDATE_INT);
        $access=new ProjectAccessService(); $session = new AuthSessionService(); $admin=$session->isAdminModeActive();
        $isTeacher = in_array('teacher', array_map('strtolower', array_map('strval', $access->currentRoles())), true);
        $teacher = $isTeacher;
        $model=new ProjectRecordModel();
        $repositoryScope=(string)($_GET['scope']??'')==='repository';
        $academicManagement=(string)($_GET['context']??'')==='academic_management';
        $repositoryOwner=(string)($_GET['context']??'')==='repository_owner';
        $context=$repositoryScope?'repository':($academicManagement?'academic_management':($repositoryOwner?'repository_owner':'academic'));
        $policy = new ProjectCapabilityService();
        $resolvedFile = $fileId ? $model->findFileById((int) $fileId) : null;
        if ($fileId && (!$resolvedFile || (int) ($resolvedFile['project_id'] ?? 0) !== (int) $projectId)) {
            http_response_code(403);
            if ($json) $this->json(['success'=>false,'message'=>'No tienes autorización para consultar este archivo.','data'=>[]]);
            exit('No tienes autorización para consultar este archivo.');
        }
        // Institutional teacher reads resolve the parent from file_id and never
        // fall back to a client-supplied project relationship.
        if ($allowInstitutionalRead && $context === 'academic') {
            $institutionalFile = $resolvedFile;
            $institutionalProjectId = (int) ($institutionalFile['project_id'] ?? 0);
            $privateAccess = $institutionalProjectId > 0 && $policy->canViewProjectResource($institutionalProjectId, $context);
            if (!$privateAccess && (!$projectId || !$institutionalFile || $institutionalProjectId !== (int) $projectId || !$policy->canViewInstitutionalFile((int) $fileId, $context))) {
                http_response_code(403);
                if ($json) $this->json(['success'=>false,'message'=>'No tienes autorización para consultar este archivo.','data'=>[]]);
                exit('No tienes autorización para consultar este archivo.');
            }
            if (!$privateAccess) {
                if (!$this->currentFileVersionRequestIsValid((string) ($institutionalFile['checksum_sha256'] ?? ''))) $this->rejectHistoricalFileRequest($json);
                $institutionalProject = $access->can('project.view')
                    ? $model->find($institutionalProjectId, $access->currentUserId(), $admin, false, true)
                    : null;
                try {
                    $institutionalPath = (new ProjectDocumentFileService())->resolveStoredFile($institutionalProjectId, (string) $institutionalFile['storage_name']);
                    $institutionalStream = fopen($institutionalPath, 'rb');
                } catch (Throwable) {
                    $institutionalStream = false;
                }
                if (!$institutionalProject || $institutionalStream === false) {
                    http_response_code(404);
                    if ($json) $this->json(['success'=>false,'message'=>'El archivo solicitado no está disponible.','data'=>[]]);
                    exit('El archivo solicitado no está disponible.');
                }
                return [$institutionalProject, $institutionalFile, $institutionalStream, $institutionalPath, true];
            }
        }
        if(!$projectId || !$policy->canViewProjectResource((int)$projectId,$context)){http_response_code(403);if($json)$this->json(['success'=>false,'message'=>'No tienes autorización para consultar archivos de este proyecto.','data'=>[]]);exit('No tienes autorización para consultar archivos de este proyecto.');}
        $capabilities=$policy->forProjectId((int)$projectId,$context);
        if(empty($capabilities['download_files'])){http_response_code(403);if($json)$this->json(['success'=>false,'message'=>'No tienes autorización para consultar archivos de este proyecto.','data'=>[]]);exit('No tienes autorización para consultar archivos de este proyecto.');}
        $project=($projectId && $access->can('project.view'))?$model->find((int)$projectId,$access->currentUserId(),$admin,$repositoryScope,false,false,$repositoryOwner):null;
        $file=($project && $fileId)?$model->findFile((int)$projectId,(int)$fileId):null;
        if(!$project||!$file){http_response_code(404);if($json)$this->json(['success'=>false,'message'=>'El archivo solicitado no está disponible.','data'=>[]]);exit('El archivo solicitado no está disponible.');}
        if (!$this->currentFileVersionRequestIsValid((string) ($file['checksum_sha256'] ?? ''))) $this->rejectHistoricalFileRequest($json);
        try{$fileStorage=$admin||$teacher||$repositoryScope?new ProjectDocumentFileService():new PrivateProjectFileService();$path=$fileStorage->resolveStoredFile((int)$projectId,(string)$file['storage_name']);$stream=fopen($path,'rb');}catch(Throwable){$stream=false;}
        if($stream===false){http_response_code(404);if($json)$this->json(['success'=>false,'message'=>'El archivo solicitado no está disponible.','data'=>[]]);exit('El archivo solicitado no está disponible.');}
        return [$project,$file,$stream,$path,false];
    }

    private function docxPreview(array $project, array $file, string $source, string $identity, string $url, bool $forceRetry = false, bool $allowReviewRepresentation = true): array
    {
        try {
            if ($allowReviewRepresentation) {
                $representation = new ProjectReviewRepresentationService();
                if ($representation->supplementalPath((int)$project['id'], (int)$file['id'], $identity) !== null) return $this->docxPdfPayload($file, $url, ['cached'=>true], 'supplemental_pdf');
            }
            $conversion = new DocumentPreviewConversionService();
            if ($forceRetry) $conversion->clearFailure((int)$project['id'], (int)$file['id'], $identity);
            $cached = $conversion->cachedPath((int)$project['id'], (int)$file['id'], $identity);
            if ($cached !== null) {
                $payload = $this->docxPdfPayload($file, $url, ['cached'=>true], 'cached_pdf');
                if (!$allowReviewRepresentation) $payload['review_representation'] = false;
                return $payload;
            }
            $converted = $conversion->convertFile($source, (int)$project['id'], (int)$file['id'], $identity);
            $payload = $this->docxPdfPayload($file, $url, $converted, 'libreoffice_pdf');
            if (!$allowReviewRepresentation) $payload['review_representation'] = false;
            return $payload;
        }
        catch (Throwable $error) {
            error_log('Project DOCX preview: project='.(int)$project['id'].' file='.(int)$file['id'].' checksum='.$identity.' error='.$error->getMessage());
            return $this->docxPreviewFailure($file, $error->getMessage());
        }
    }

    private function docxPreviewStream(array $project, array $file, array $entry, string $identity, string $url, bool $forceRetry = false): array
    {
        try {
            $service = new DocumentPreviewConversionService();
            if ($forceRetry) { $service->clearFailure((int)$project['id'], (int)$file['id'], $identity); }
            $result = $service->convertStream($entry['stream'], (int)$project['id'], (int)$file['id'], $identity);
            return $this->docxPdfPayload(['original_name'=>$entry['name'],'size_bytes'=>$entry['size']], $url, $result);
        }
        catch (Throwable $error) {
            error_log('Project ZIP DOCX preview: project='.(int)$project['id'].' file='.(int)$file['id'].' entry='.$entry['path'].' error='.$error->getMessage());
            return $this->docxPreviewFailure(['original_name'=>$entry['name'],'size_bytes'=>$entry['size']], $error->getMessage());
        }
    }

    private function docxPdfPayload(array $file, string $url, array $result, string $source = 'libreoffice_pdf'): array { return ['status'=>'ready','message'=>'','name'=>(string)$file['original_name'],'path'=>(string)$file['original_name'],'size'=>ArchiveService::formatBytes((int)($file['size_bytes']??0)),'size_bytes'=>(int)($file['size_bytes']??0),'extension'=>'docx','mime'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document','download_url'=>'','content_url'=>$url,'preview_type'=>'pdf','type_label'=>'Documento de Word','review_representation'=>true,'review_representation_source'=>$source,'review_representation_label'=>$source==='supplemental_pdf'?'Vista PDF proporcionada para revisión':'','cached'=>(bool)($result['cached']??false),'blocks'=>[],'truncated'=>false]; }
    private function docxPreviewFailure(array $file, string $reason = ''): array
    {
        $message = str_contains($reason, 'en proceso') ? 'La vista previa ya se está preparando.' : 'No fue posible generar la vista previa de este documento.';
        return ['status'=>'unavailable','message'=>$message,'name'=>(string)$file['original_name'],'size'=>ArchiveService::formatBytes((int)($file['size_bytes']??0)),'extension'=>'docx','preview_type'=>'docx','type_label'=>'Documento de Word','content_url'=>'','blocks'=>[],'truncated'=>false,'manual_pdf_required'=>isset($file['id'],$file['project_id'],$file['checksum_sha256']),'file_id'=>(int)($file['id']??0),'checksum_sha256'=>(string)($file['checksum_sha256']??'')];
    }
    private function zipPreviewIdentity(array $file, array $entry): string { rewind($entry['stream']); $entryHash=hash('sha256', stream_get_contents($entry['stream']) ?: ''); rewind($entry['stream']); return hash('sha256',(string)$file['checksum_sha256']."\0".(string)$entry['path']."\0".$entryHash); }

    private function previewFile(array $file,$stream):array{return ['name'=>(string)$file['original_name'],'path'=>(string)$file['original_name'],'size'=>(int)$file['size_bytes'],'stream'=>$stream];}
    private function stream(array $file,$stream,string $disposition,?string $verifiedMime=null):never{$stat=fstat($stream);$size=is_array($stat)?(int)($stat['size']??$file['size_bytes']):(int)$file['size_bytes'];header('Content-Type: '.($verifiedMime?:((string)$file['mime_type'])));header('Content-Length: '.$size);header("Content-Disposition: {$disposition}; filename*=UTF-8''".rawurlencode((string)$file['original_name']));header('X-Content-Type-Options: nosniff');if($disposition==='inline')header("Content-Security-Policy: default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'");header('Cache-Control: private, no-store, max-age=0');fpassthru($stream);fclose($stream);exit;}
    private function json(array $payload):never{header('Content-Type: application/json; charset=UTF-8');echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}

    public function saveProjectDraft(): void
    {
        [$session, $access] = $this->draftRequest(); $userId = $access->currentUserId();
        try {
            $draftService = new ProjectDraftService(); $policy = $access->projectCreationPolicy(); $catalogs = $draftService->catalogs($userId, $policy);
            $draft = $draftService->normalize($_POST, $policy, $catalogs); $errors = $draftService->validateForStorage($draft, $policy, $catalogs);
            if ($errors !== []) $this->json(['success'=>false,'message'=>reset($errors),'data'=>['errors'=>$errors]]);
            $storage = new ProjectDraftStorageService();
            $existing = $storage->active($userId);
            if (empty($existing['files']) && !$draftService->hasSubstantialInformation($draft, $catalogs)) {
                $existingDraft = null;
                if ($existing !== null) {
                    $existingPayload = $draftService->normalize((array) ($existing['payload'] ?? []), $policy, $catalogs);
                    if ($draftService->hasSubstantialInformation($existingPayload, $catalogs)) $existingDraft = $existing;
                }
                $this->json(['success'=>true,'message'=>'No hay información sustancial para guardar todavía.','data'=>['draft'=>$existingDraft]]);
            }
            $payload = $this->draftPayload($draft, $catalogs, (string) ($_POST['current_step'] ?? 'type'));
            $saved = $storage->save($userId, $payload);
            $this->json(['success'=>true,'message'=>'Cambios guardados temporalmente.','data'=>['draft'=>$saved]]);
        } catch (Throwable $exception) { error_log('Project draft save: ' . $exception->getMessage()); http_response_code(500); $this->json(['success'=>false,'message'=>'No fue posible guardar el borrador.','data'=>[]]); }
    }

    public function uploadProjectDraftFile(): void
    {
        [, $access] = $this->draftRequest(); $userId = $access->currentUserId();
        try {
            $catalogs = (new ProjectDraftService())->catalogs($userId, $access->projectCreationPolicy());
            if ($catalogs['active_period'] === null || $catalogs['student'] === null) throw new InvalidArgumentException((string) $catalogs['availability_message']);
            $uploads = $this->draftUploads($_FILES['files'] ?? []); if ($uploads === []) throw new InvalidArgumentException('Selecciona al menos un archivo.');
            $storage = new ProjectDraftStorageService(); $activeDraft = $storage->active($userId); $draftId = (string) ($activeDraft['id'] ?? ''); $added = []; $failed = [];
            foreach ($uploads as $upload) try { $added[] = $storage->addUpload($userId, $upload, (bool) ((int) ($_POST['replace'] ?? 0))); }
            catch (ProjectDraftFileConflictException $exception) { $failed[] = ['name'=>(string)($upload['name'] ?? 'Archivo'),'message'=>$exception->getMessage(),'replace_file_id'=>$exception->fileId]; }
            catch (Throwable $exception) {
                $failed[] = ['name'=>(string)($upload['name'] ?? 'Archivo'),'message'=>$exception instanceof InvalidArgumentException ? $exception->getMessage() : 'No se pudo subir el archivo.'];
            }
            $draft = $storage->active($userId); $status = $added === [] ? 422 : ($failed === [] ? 200 : 207);
            http_response_code($status); $this->json(['success'=>$added !== [],'message'=>$added === [] ? 'No se pudo subir ningún archivo.' : 'Archivo temporal agregado.','data'=>['added'=>$added,'failed'=>$failed,'draft'=>$draft]]);
        } catch (Throwable $exception) { error_log('Project draft upload: ' . $exception->getMessage()); http_response_code(422); $this->json(['success'=>false,'message'=>$exception instanceof InvalidArgumentException ? $exception->getMessage() : 'No se pudo subir el archivo.','data'=>[]]); }
    }

    public function removeProjectDraftFile(): void
    {
        [, $access] = $this->draftRequest();
        try { (new ProjectDraftStorageService())->removeFile($access->currentUserId(), (int) ($_POST['file_id'] ?? 0)); $this->json(['success'=>true,'message'=>'Archivo eliminado.','data'=>['draft'=>(new ProjectDraftStorageService())->active($access->currentUserId())]]); }
        catch (Throwable $exception) { http_response_code(422); $this->json(['success'=>false,'message'=>$exception instanceof InvalidArgumentException ? $exception->getMessage() : 'No se pudo eliminar el archivo.','data'=>[]]); }
    }

    public function resetProjectDraft(): void
    {
        [, $access] = $this->draftRequest();
        try { (new ProjectDraftStorageService())->delete($access->currentUserId()); $this->json(['success'=>true,'message'=>'Borrador eliminado correctamente.','data'=>[]]); }
        catch (Throwable $exception) { error_log('Project draft reset: ' . $exception->getMessage()); http_response_code(500); $this->json(['success'=>false,'message'=>'No fue posible eliminar el borrador.','data'=>[]]); }
    }

    /** Revalida el borrador temporal; no registra ni promueve datos definitivos. */
    public function preflightProjectDraft(): void
    {
        [, $access] = $this->draftRequest();
        $userId = $access->currentUserId();
        try {
            $draftService = new ProjectDraftService();
            $catalogs = $draftService->catalogs($userId, $access->projectCreationPolicy());
            $stored = (new ProjectDraftStorageService())->active($userId);
            if ($stored === null) throw new InvalidArgumentException('No se encontró un borrador temporal para revisar.');
            $payload = is_array($stored['payload'] ?? null) ? $stored['payload'] : [];
            $draft = $draftService->normalize($payload, $access->projectCreationPolicy(), $catalogs);
            $errors = $draftService->validate($draft, $access->projectCreationPolicy(), $catalogs);
            foreach ((array) ($stored['files'] ?? []) as $file) {
                if (($file['available'] ?? false) !== true) {
                    $errors['files'] = 'Uno o más archivos temporales ya no están disponibles. Quítalos o vuelve a subirlos.';
                    break;
                }
            }
            if ($errors !== []) {
                http_response_code(422);
                $this->json(['success'=>false,'message'=>'Revisa la información indicada antes de registrar el proyecto.','data'=>['errors'=>$errors,'draft'=>$stored]]);
            }
            $this->json(['success'=>true,'message'=>'El borrador está listo para confirmar.','data'=>['draft'=>$stored,'summary'=>$draftService->confirmation($draft, (array) ($stored['files'] ?? []), $catalogs)]]);
        } catch (Throwable $exception) {
            error_log('Project draft preflight: ' . $exception->getMessage());
            http_response_code($exception instanceof InvalidArgumentException ? 422 : 500);
            $this->json(['success'=>false,'message'=>$exception instanceof InvalidArgumentException ? $exception->getMessage() : 'No fue posible validar el borrador.','data'=>[]]);
        }
    }

    /** Consume un borrador revalidado y crea el proyecto definitivo. */
    public function registerProjectDraft(): void
    {
        [, $access] = $this->draftRequest();
        $userId = $access->currentUserId();
        try {
            $action = (string) ($_POST['action'] ?? 'save');
            if (!in_array($action, ['save', 'submit'], true)) {
                http_response_code(422);
                $this->json(['success'=>false,'message'=>'La acción solicitada no es válida.','data'=>[]]);
            }
            if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
            $result = (new ProjectDraftRegistrationService())->register($userId, $access->projectCreationPolicy(), (string) ($_POST['draft_id'] ?? ''), $action === 'submit');
            $this->json(['success'=>true,'message'=>$action === 'submit' ? 'Proyecto enviado a revisión correctamente.' : 'Proyecto guardado en preparación correctamente.','data'=>$result]);
        } catch (ProjectDraftRegistrationException $exception) {
            http_response_code(422);
            $this->json(['success'=>false,'message'=>$exception->getMessage(),'data'=>['errors'=>$exception->errors]]);
        } catch (StudentProjectSubmissionException $exception) {
            http_response_code($exception->httpStatus());
            $this->json(['success'=>false,'message'=>$exception->getMessage(),'data'=>$exception->data()]);
        } catch (Throwable $exception) {
            error_log('Project draft registration: '.$exception->getMessage());
            http_response_code(500);
            $this->json(['success'=>false,'message'=>'No fue posible registrar el proyecto. Tu borrador continúa disponible.','data'=>[]]);
        }
    }

    /** Publica un expediente elegible a petición de un estudiante participante activo. */
    public function publishProjectAsStudent(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            $this->json(['success'=>false,'message'=>'Método no permitido.','data'=>[]]);
        }
        $session = new AuthSessionService();
        $access = new ProjectAccessService();
        if (!$access->can('project.view') || !$session->validateCsrf('student_project_publish', (string) ($_POST['_csrf'] ?? ''))) {
            http_response_code($access->can('project.view') ? 419 : 403);
            $this->json(['success'=>false,'message'=>$access->can('project.view') ? 'Tu sesión expiró. Inicia sesión nuevamente para continuar.' : 'No tienes autorización para publicar proyectos.','data'=>[]]);
        }
        $projectId = filter_var($_POST['project_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if ($projectId === false || $projectId === null) {
            http_response_code(422);
            $this->json(['success'=>false,'message'=>'El proyecto solicitado no es válido.','data'=>[]]);
        }
        try {
            $service = new ProjectStudentPublicationService();
            $mode = (string) ($_POST['mode'] ?? 'preview');
            if ($mode === 'preview') {
                $this->json(['success'=>true,'message'=>'El proyecto está listo para publicar.','data'=>$service->preview((int)$projectId, $access->currentUserId())]);
            }
            $preparations = new ProjectPublicationPreparationService();
            if ($mode === 'prepare') $this->json(['success'=>true,'message'=>'Puedes preparar los archivos finales.','data'=>$preparations->begin((int)$projectId,$access->currentUserId())]);
            $preparationId = (string) ($_POST['preparation_id'] ?? '');
            if ($mode === 'prepare-add') $this->json(['success'=>true,'message'=>'Archivo agregado a la preparación.','data'=>$preparations->add((int)$projectId,$access->currentUserId(),$preparationId,$_FILES['file'] ?? [])]);
            if ($mode === 'prepare-replace') {
                $fileId=filter_var($_POST['file_id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if($fileId===false||$fileId===null)throw new ProjectStudentPublicationException('El archivo seleccionado no es válido.',422);
                $this->json(['success'=>true,'message'=>'Archivo actualizado en la preparación.','data'=>$preparations->replace((int)$projectId,$access->currentUserId(),$preparationId,(int)$fileId,$_FILES['file'] ?? [])]);
            }
            if ($mode === 'prepare-include') {
                $fileId=filter_var($_POST['file_id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if($fileId===false||$fileId===null)throw new ProjectStudentPublicationException('El archivo seleccionado no es válido.',422);
                $included=filter_var($_POST['included'] ?? null,FILTER_VALIDATE_BOOLEAN,FILTER_NULL_ON_FAILURE);if($included===null)throw new ProjectStudentPublicationException('La acción solicitada no es válida.',422);
                $this->json(['success'=>true,'message'=>$included?'Archivo incluido en la preparación.':'El archivo no se incluirá en la publicación final.','data'=>$preparations->setIncluded((int)$projectId,$access->currentUserId(),$preparationId,(int)$fileId,$included)]);
            }
            if ($mode === 'prepare-remove-add') $this->json(['success'=>true,'message'=>'Archivo retirado de la preparación.','data'=>$preparations->removeAddition((int)$projectId,$access->currentUserId(),$preparationId,(string)($_POST['file_key'] ?? ''))]);
            if ($mode === 'prepare-cancel') {$preparations->cancel((int)$projectId,$access->currentUserId(),$preparationId);$this->json(['success'=>true,'message'=>'Preparación cancelada.','data'=>[]]);}
            if ($mode !== 'publish') throw new ProjectStudentPublicationException('La operación de publicación no es válida.', 422);
            $hasPresentationSelection = array_key_exists('presentation_file_id', $_POST);
            $presentationFileId = null;
            if ($hasPresentationSelection) {
                $rawPresentationFileId = trim((string) ($_POST['presentation_file_id'] ?? ''));
                if ($rawPresentationFileId !== '') {
                    $presentationFileId = filter_var($rawPresentationFileId, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
                    if ($presentationFileId === false || $presentationFileId === null) throw new ProjectStudentPublicationException('El archivo de presentación seleccionado no es válido.', 422);
                    $presentationFileId = (int) $presentationFileId;
                }
            }
            $data=$preparationId!==''
                ?$service->publishPrepared((int)$projectId,$access->currentUserId(),$preparationId,$presentationFileId,$hasPresentationSelection)
                :$service->publish((int)$projectId, $access->currentUserId(),$presentationFileId,$hasPresentationSelection);
            $data['detail_url'] = route('repository-detail') . '&id=' . (int) $projectId;
            $this->json(['success'=>true,'message'=>'Proyecto publicado correctamente.','data'=>$data]);
        } catch (ProjectStudentPublicationException $exception) {
            http_response_code($exception->getCode() >= 400 && $exception->getCode() < 600 ? $exception->getCode() : 422);
            $this->json(['success'=>false,'message'=>$exception->getMessage(),'data'=>[]]);
        } catch (ProjectStatusTransitionException $exception) {
            http_response_code(422);
            $this->json(['success'=>false,'message'=>$exception->getMessage(),'data'=>[]]);
        } catch (ProjectDocumentVersionException|InvalidArgumentException $exception) {
            http_response_code(422);
            $this->json(['success'=>false,'message'=>$exception->getMessage(),'data'=>[]]);
        } catch (Throwable $exception) {
            error_log('Student project publication: '.$exception->getMessage());
            http_response_code(500);
            $this->json(['success'=>false,'message'=>'No fue posible publicar el proyecto. Inténtalo nuevamente.','data'=>[]]);
        }
    }

    private function draftRequest(): array
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); $this->json(['success'=>false,'message'=>'Método no permitido.','data'=>[]]); }
        $session = new AuthSessionService(); $access = new ProjectAccessService();
        if (!$access->can('project.create')) { http_response_code(403); $this->json(['success'=>false,'message'=>'No tienes autorización para gestionar este borrador.','data'=>[]]); }
        if (!$session->validateCsrf('project_draft', (string) ($_POST['_csrf'] ?? ''))) { http_response_code(419); $this->json(['success'=>false,'message'=>'Tu sesión expiró. Inicia sesión nuevamente para continuar.','data'=>[]]); }
        return [$session, $access];
    }

    private function draftPayload(array $draft, array $catalogs, string $step): array
    {
        return ['type'=>$draft['type'],'title'=>$draft['title'],'description'=>$draft['description'],'period'=>(string)($catalogs['active_period']['code'] ?? ''),'career_id'=>(int)($catalogs['student']['career_id'] ?? 0),'semester'=>(int)($catalogs['student']['semester'] ?? 0),'modality'=>$draft['modality'],'research_line'=>$draft['research_line'],'tutor_id'=>$draft['tutor_id'],'members'=>$draft['members'],'leader_id'=>$draft['leader_id'],'tags'=>$draft['tags'],'current_step'=>in_array($step,['type','details','team','files','confirm'],true)?$step:'type'];
    }

    private function draftUploads(array $files): array
    {
        if (!is_array($files['name'] ?? null)) return $files ? [$files] : [];
        $out = []; foreach ($files['name'] as $index => $name) $out[] = ['name'=>$name,'type'=>$files['type'][$index] ?? '','tmp_name'=>$files['tmp_name'][$index] ?? '','error'=>$files['error'][$index] ?? UPLOAD_ERR_NO_FILE,'size'=>$files['size'][$index] ?? 0]; return $out;
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
        $userId = $access->currentUserId();
        try {
            $catalogs = $draftService->catalogs($userId, $policy);
        } catch (Throwable $exception) {
            error_log('ProjectDraftService catalogs: ' . $exception->getMessage());
            $catalogs = ['types'=>[], 'active_period'=>null, 'student'=>null, 'availability_message'=>'No fue posible cargar la información académica. Inténtalo nuevamente.', 'modalities'=>['individual'=>'Individual','group'=>'Grupal'], 'research_lines'=>[], 'teachers'=>[], 'students'=>[], 'keywords'=>[]];
        }
        try { $storedDraft = (new ProjectDraftStorageService())->active($userId); }
        catch (Throwable $exception) { error_log('Project draft load: ' . $exception->getMessage()); $storedDraft = null; }
        if ($storedDraft !== null && empty($storedDraft['files'])) {
            try {
                $storedPayload = $draftService->normalize((array) ($storedDraft['payload'] ?? []), $policy, $catalogs);
                if (!$draftService->hasSubstantialInformation($storedPayload, $catalogs)) $storedDraft = null;
            } catch (Throwable) {
                $storedDraft = null;
            }
        }
        $draft = $draftService->normalize([], $policy, $catalogs); $errors = []; $validated = false; $confirmation = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $policy['can_create']) {
            $draft = $draftService->normalize($_POST, $policy, $catalogs);
            if (!hash_equals((string) $_SESSION['project_draft_csrf'], (string) ($_POST['_csrf'] ?? ''))) $errors['_form'] = 'La sesión del formulario venció. Recarga la página e inténtalo nuevamente.';
            $errors += $draftService->validate($draft, $policy, $catalogs);
            $fileResult = $draftService->validateFiles($_FILES['files'] ?? [], $fileService);
            $errors += $fileResult['errors'];
            if ($errors === []) { $validated = true; $confirmation = $draftService->confirmation($draft, $fileResult['valid'], $catalogs); }
        }
        View::render('projects/new', [
            'currentPage' => 'projects',
            'title' => 'Nuevo proyecto | Gestión Académica',
            'bodyClass' => 'project-wizard-page',
            'pageStyles' => [asset('css/project-wizard.css')],
            'pageScript' => asset('js/project-wizard.js'),
            'creationPolicy' => $policy, 'catalogs' => $catalogs, 'fieldContract' => $draftService->fieldContract($catalogs),
            'fileLimits' => $fileService->limits(), 'draft' => $draft, 'errors' => $errors, 'draftValidated' => $validated,
            'confirmation' => $confirmation, 'projectDraftCsrf' => (string) $_SESSION['project_draft_csrf'], 'draftStorageKey' => 'academic_project_draft_v1_' . $userId,
            'storedDraft' => $storedDraft, 'projectDraftApiCsrf' => (new AuthSessionService())->csrfToken('project_draft'),
            'projectDraftEndpoints' => ['save'=>route('project-draft-save'),'upload'=>route('project-draft-upload'),'remove'=>route('project-draft-file-remove'),'reset'=>route('project-draft-reset'),'preflight'=>route('project-draft-preflight'),'register'=>route('project-draft-register')],
        ]);
    }
}
