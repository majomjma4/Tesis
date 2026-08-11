<?php

declare(strict_types=1);

final class ProjectsController
{
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
        $legacyTabs = ['summary'=>'information','deliveries' => 'files', 'documents'=>'files','final-documents' => 'files', 'observations' => 'information', 'comments' => 'information', 'review'=>'information', 'history' => 'information', 'calendar' => 'information', 'activity'=>'information', 'participants' => 'information', 'more' => 'information'];
        $allowedTabs = ['information', 'files', 'evolution'];
        $requestedTab = strtolower(trim((string) ($_GET['tab'] ?? 'information')));
        $tab = $legacyTabs[$requestedTab] ?? $requestedTab;
        $tab = in_array($tab, $allowedTabs, true) ? $tab : 'information';
        $access = new ProjectAccessService();
        $session = new AuthSessionService();
        $isAdministrator = $session->hasAdminAccess();
        $isTeacher = !$isAdministrator && in_array('teacher', $access->currentRoles(), true);
        $isDevDemo = !empty($_GET['demo']) && app_is_development();
        // El Docente accede a cualquier proyecto activo desde Proyectos activos (no solo a los suyos).
        // Se consulta el registro con acceso amplio (como administrador) pero las capacidades
        // se calculan en contexto 'academic', que restringe las acciones disponibles.
        $findAsAdmin = $isAdministrator || $isTeacher;
        $project = $id && $access->can('project.view')
            ? (new ProjectRecordModel())->find((int)$id, $access->currentUserId(), $findAsAdmin)
            : null;

        if ($project === null && $isDevDemo) {
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

        if ($project === null) {
            (new ErrorController())->notFound();
            return;
        }
        $projectContext = $isAdministrator ? 'academic_management' : 'academic';
        $project['review_situation']=(new ProjectReviewSituationService())->forProject((int)$project['id']);
        $academicPage = (new ProjectAcademicTimelineService())->page((int) $project['id']);
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
        $project['academic_history_total'] = $academicPage['total'];

        $descriptionService = new ProjectDescriptionService();
        $isStudentParticipant = !$isAdministrator && in_array('student', $access->currentRoles(), true)
            && count(array_filter($project['participants'], static fn (array $participant): bool =>
                (int) $participant['user_id'] === $access->currentUserId()
                && (string) $participant['role_code'] === 'student'
                && (string) ($participant['status'] ?? 'active') === 'active'
                && empty($participant['removed_at']))) > 0;
        $descriptionReminder = $isStudentParticipant
            ? $descriptionService->consumePendingReminder((int) $project['id'], $access->currentUserId())
            : null;
        $projectCapabilities = (new ProjectCapabilityService())->forCurrentUser($project, $projectContext);
        $adjustmentData = ['items' => [], 'summary' => ['has_pending_adjustments' => false, 'pending_count' => 0, 'latest' => null]];
        if ($projectContext === 'academic' && $isStudentParticipant && !empty($projectCapabilities['view_adjustment_requests'])) {
            try {
                $adjustmentData['summary'] = (new ProjectAdjustmentSituationService())->forProject((int) $project['id']);
            } catch (ProjectAdjustmentRequestException $exception) {
                error_log('Project adjustment UI: ' . $exception->getMessage());
            }
        }
        $documentReview = null;
        if ($projectContext === 'academic_management' && (string)$project['status'] === 'development') {
            $documentReview = (new ProjectDocumentReviewService())->describeCurrentFiles((int)$project['id'], (array)$project['files']);
            $project['files'] = $documentReview['files'];
        }
        $returnUrl = ($isAdministrator || $isTeacher)
            ? $this->academicManagementReturnUrl((string) ($_GET['return'] ?? ''))
            : route('projects');
        $detailUrl = route('project-detail') . '&id=' . (int) $project['id'];
        if ($isAdministrator || $isTeacher) $detailUrl .= '&return=' . rawurlencode($returnUrl);
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
            'bodyClass' => 'project-detail-page',
            'isTeacherContext' => $isTeacher,
            'pageStyles' => array_values(array_filter([
                asset('css/project-simplified.css'), asset('css/project-description.css'),
                asset('css/project-adjustments.css'), asset('css/project-academic-timeline.css'),
                ($isAdministrator || $isTeacher) ? asset('css/admin-projects.css') : asset('css/student-project-workspace.css'),
            ])),
            'pageScript' => asset('js/repository-detail.js'),
            'pageScripts' => array_values(array_filter([
                $descriptionReminder ? asset('js/project-description.js') : null,
                !empty($projectCapabilities['create_adjustment_request']) ? asset('js/project-adjustments.js') : null,
                $isAdministrator ? asset('js/admin-projects.js') : (!$isTeacher ? asset('js/student-project-workspace.js') : null),
                $isAdministrator && $projectStatusTransitions !== [] ? asset('js/project-status-transition.js') : null,
            ])),
            'project' => $project,
            'activeTab' => $tab,
            'isAdministrator' => $isAdministrator,
            'publicContext' => false,
            'projectContext' => $projectContext,
            'projectCapabilities' => $projectCapabilities,
            'canReview' => !empty($projectCapabilities['review_delivery']) || !empty($projectCapabilities['create_observation']) || !empty($projectCapabilities['respond_observation']),
            'canDeliver' => !empty($projectCapabilities['register_delivery']),
            'projectEditUrl' => route('projects') . '&edit=' . (int) $project['id'],
            'detailUrl' => $detailUrl,
            'returnUrl' => $returnUrl,
            'previewActionUrl' => route('project-file-preview') . ($isAdministrator ? '&context=academic_management' : ''),
            'downloadActionUrl' => route('project-file-download') . ($isAdministrator ? '&context=academic_management' : ''),
            'projectDocuments' => $projectDocuments,
            'documentReview' => $documentReview,
            // La auditoría administrativa permanece interna; no se expone como acción del historial académico.
            'projectHistoryEndpoint' => '',
            'projectStatusTransitions' => $projectStatusTransitions,
            'projectStatusEndpoint' => $isAdministrator ? route('admin-project-save') : '',
            'projectStatusCsrf' => $isAdministrator ? $session->csrfToken('admin_projects') : '',
            'projectSaveEndpoint' => $isAdministrator ? route('admin-project-save') : '',
            'projectTrashEndpoint' => $isAdministrator ? route('admin-project-trash') : '',
            'projectTrashCsrf' => $isAdministrator ? $session->csrfToken('admin_projects') : '',
            'projectEditorCatalogs' => $isAdministrator ? (new AdminProjectModel())->catalogs() : [],
            'descriptionReminder' => $descriptionReminder,
            'descriptionCsrf' => $session->csrfToken('project_description'),
            'descriptionSaveEndpoint' => route('project-description-save'),
            'lifecycleDescription' => $descriptionService->effectiveDescription((string) $project['type_code'], $project['summary'] ?? null),
            'academicHistoryEndpoint' => !empty($projectCapabilities['view_academic_history']) ? route('project-academic-history-events') . '&project_id=' . (int) $project['id'] . '&context=' . rawurlencode($projectContext) : '',
            'adjustmentData' => $adjustmentData,
            'adjustmentCsrf' => $session->csrfToken('project_adjustment'),
            'adjustmentEndpoints' => [
                'create' => route('project-adjustment-create'), 'respond' => route('project-adjustment-respond'),
                'address' => route('project-adjustment-address'), 'close' => route('project-adjustment-close'),
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
        if (!in_array($page, ['projects', 'proyectos', 'mis-proyectos'], true)) return $fallback;

        $safe = ['page' => 'projects'];
        foreach (['p', 'type_id', 'period_id'] as $key) {
            $value = filter_var($query[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($value !== false && $value !== null) $safe[$key] = (int) $value;
        }
        $perPage = filter_var($query['per_page'] ?? null, FILTER_VALIDATE_INT);
        if (in_array($perPage, [10, 25, 50, 75, 100], true)) $safe['per_page'] = (int) $perPage;
        foreach (['search' => 100, 'status' => 32, 'situation' => 32, 'sort' => 32, 'group' => 32] as $key => $limit) {
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
        $capabilities=$projectId?(new ProjectCapabilityService())->forProjectId((int)$projectId,$context):(new ProjectCapabilityService())->none();
        if(empty($capabilities['view_academic_history'])){http_response_code(403);$this->json(['success'=>false,'message'=>'No tienes autorización para consultar el historial académico de este proyecto.','data'=>[]]);}
        $page=$context==='repository'
            ?(new ProjectRecordModel())->academicHistoryPage((int)$projectId,$offset,15)
            :(new ProjectAcademicTimelineService())->page((int)$projectId,$offset,15);
        $this->json(['success'=>true,'message'=>'Historial académico cargado.','data'=>$page]);
    }

    public function saveDescription(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            $this->json(['success' => false, 'message' => 'Método no permitido.', 'data' => []]);
        }
        $session = new AuthSessionService();
        if (!$session->validateCsrf('project_description', (string) ($_POST['_csrf'] ?? ''))) {
            http_response_code(403);
            $this->json(['success' => false, 'message' => 'La sesión del formulario venció.', 'data' => []]);
        }
        try {
            (new ProjectDescriptionService())->saveForStudent((int) ($_POST['project_id'] ?? 0), (int) $session->userId(), (string) ($_POST['description'] ?? ''));
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

    public function filePreview(): void
    {
        [$project, $file, $stream] = $this->resolveFile(true);
        $query='&project_id='.(int)$project['id'].'&file_id='.(int)$file['id'];
        $scope=(string)($_GET['scope']??'')==='repository'?'&scope=repository':((string)($_GET['context']??'')==='academic_management'?'&context=academic_management':'');
        $version=!empty($file['checksum_sha256'])?'&v='.rawurlencode(substr((string)$file['checksum_sha256'],0,16)):'';
        $preview=(new FilePreviewService())->prepare($this->previewFile($file,$stream),route('project-file-content').$query.$scope.$version,route('project-file-download').$query.$scope);
        fclose($stream);header('Cache-Control: private, no-store, max-age=0');$this->json(['success'=>true,'message'=>$preview['message'],'data'=>['preview'=>$preview]]);
    }

    public function fileContent(): void
    {
        [, $file, $stream] = $this->resolveFile(); $data=$this->previewFile($file,$stream);
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
        $administrator = $session->hasAdminAccess();
        $teacher = !$administrator && in_array('teacher', $access->currentRoles(), true);
        $context = $administrator ? 'academic_management' : 'academic';
        $capabilities = (new ProjectCapabilityService())->forProjectId((int)$projectId, $context);
        if (empty($capabilities['download_files'])) { http_response_code(403); exit('No tienes autorización para descargar este paquete.'); }
        $project = (new ProjectRecordModel())->find((int)$projectId, $access->currentUserId(), $administrator || $teacher);
        if ($project === null) { http_response_code(404); exit('El proyecto solicitado no está disponible.'); }
        try {
            $packages = new ProjectRepositoryPackageService();
            $descriptor = $packages->prepareAcademic((int)$projectId, route('project-package-download') . '&id=' . (int)$projectId);
            $path = ProjectRepositoryPackageService::academicPackagePath((int)$projectId);
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
        try{$preview=(new FilePreviewService())->prepare($entry,route('project-zip-entry-content').$query,route('project-zip-entry-download').$query);}
        finally{$this->closeProjectArchiveEntry($entry);}
        $this->json(['success'=>true,'message'=>$preview['message'],'data'=>['preview'=>$preview]]);
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
        $repository=$scope==='repository'&&$requestedContext==='';$academicManagement=$scope===''&&$requestedContext==='academic_management';$academic=$scope===''&&$requestedContext==='academic';
        if(!$repository&&!$academicManagement&&!$academic)$this->failProjectArchive(422,'El contexto documental no es válido.',$json);
        $context=$repository?'repository':($academicManagement?'academic_management':'academic');$projectId=filter_var($_GET['project_id']??null,FILTER_VALIDATE_INT);
        $capabilities=$projectId?(new ProjectCapabilityService())->forProjectId((int)$projectId,$context):(new ProjectCapabilityService())->none();
        if(empty($capabilities['download_files']))$this->failProjectArchive(403,'No tienes autorización para consultar archivos de este proyecto.',$json);
        $access=new ProjectAccessService();$session=new AuthSessionService();$teacher=!$session->hasAdminAccess()&&in_array('teacher',$access->currentRoles(),true);
        $project=$projectId&&$access->can('project.view')?(new ProjectRecordModel())->find((int)$projectId,$access->currentUserId(),$session->hasAdminAccess()||$teacher,$repository):null;
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

    private function resolveFile(bool $json=false): array
    {
        if (session_status()!==PHP_SESSION_ACTIVE) session_start();
        if (($_SERVER['REQUEST_METHOD']??'GET')!=='GET') { http_response_code(405); exit; }
        $projectId=filter_var($_GET['project_id']??null,FILTER_VALIDATE_INT); $fileId=filter_var($_GET['file_id']??null,FILTER_VALIDATE_INT);
        $access=new ProjectAccessService(); $admin=(new AuthSessionService())->hasAdminAccess(); $teacher=!$admin&&in_array('teacher',$access->currentRoles(),true);
        $model=new ProjectRecordModel();
        $repositoryScope=(string)($_GET['scope']??'')==='repository';
        $academicManagement=(string)($_GET['context']??'')==='academic_management';
        $context=$repositoryScope?'repository':($academicManagement?'academic_management':'academic');
        $capabilities=$projectId?(new ProjectCapabilityService())->forProjectId((int)$projectId,$context):(new ProjectCapabilityService())->none();
        if(empty($capabilities['download_files'])){http_response_code(403);if($json)$this->json(['success'=>false,'message'=>'No tienes autorización para consultar archivos de este proyecto.','data'=>[]]);exit('No tienes autorización para consultar archivos de este proyecto.');}
        $project=($projectId && $access->can('project.view'))?$model->find((int)$projectId,$access->currentUserId(),$admin||$teacher,$repositoryScope):null;
        $file=($project && $fileId)?$model->findFile((int)$projectId,(int)$fileId):null;
        if(!$project||!$file){http_response_code(404);if($json)$this->json(['success'=>false,'message'=>'El archivo solicitado no está disponible.','data'=>[]]);exit('El archivo solicitado no está disponible.');}
        try{$fileStorage=$admin||$teacher||$repositoryScope?new ProjectDocumentFileService():new PrivateProjectFileService();$path=$fileStorage->resolveStoredFile((int)$projectId,(string)$file['storage_name']);$stream=fopen($path,'rb');}catch(Throwable){$stream=false;}
        if($stream===false){http_response_code(404);if($json)$this->json(['success'=>false,'message'=>'El archivo solicitado no está disponible.','data'=>[]]);exit('El archivo solicitado no está disponible.');}
        return [$project,$file,$stream];
    }

    private function previewFile(array $file,$stream):array{return ['name'=>(string)$file['original_name'],'path'=>(string)$file['original_name'],'size'=>(int)$file['size_bytes'],'stream'=>$stream];}
    private function stream(array $file,$stream,string $disposition,?string $verifiedMime=null):never{$stat=fstat($stream);$size=is_array($stat)?(int)($stat['size']??$file['size_bytes']):(int)$file['size_bytes'];header('Content-Type: '.($verifiedMime?:((string)$file['mime_type'])));header('Content-Length: '.$size);header("Content-Disposition: {$disposition}; filename*=UTF-8''".rawurlencode((string)$file['original_name']));header('X-Content-Type-Options: nosniff');if($disposition==='inline')header("Content-Security-Policy: default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'");header('Cache-Control: private, no-store, max-age=0');fpassthru($stream);fclose($stream);exit;}
    private function json(array $payload):never{header('Content-Type: application/json; charset=UTF-8');echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}

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
