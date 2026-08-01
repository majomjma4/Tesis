<?php

declare(strict_types=1);

final class ProjectsController
{
    /**
     * Renderiza la pantalla principal de "Mis proyectos".
     */
    public function index(): void
    {
        if ((new AuthSessionService())->hasAdminAccess()) {
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
        $legacyTabs = ['summary'=>'information','deliveries' => 'files', 'documents'=>'files','final-documents' => 'files', 'observations' => 'information', 'comments' => 'information', 'review'=>'information', 'history' => 'information', 'calendar' => 'information', 'activity'=>'information', 'participants' => 'information', 'more' => 'information'];
        $allowedTabs = ['information', 'files', 'evolution'];
        $requestedTab = strtolower(trim((string) ($_GET['tab'] ?? 'information')));
        $tab = $legacyTabs[$requestedTab] ?? $requestedTab;
        $tab = in_array($tab, $allowedTabs, true) ? $tab : 'information';
        $access = new ProjectAccessService();
        $session = new AuthSessionService();
        $isAdministrator = $session->hasAdminAccess();
        $project = $id && $access->can('project.view')
            ? (new ProjectRecordModel())->find((int)$id, $access->currentUserId(), $isAdministrator)
            : null;

        if ($project === null) {
            (new ErrorController())->notFound();
            return;
        }
        $academicPage = (new ProjectRecordModel())->academicHistoryPage((int) $project['id']);
        $project['academic_history'] = $academicPage['events'];
        $project['academic_history_total'] = $academicPage['total'];

        $descriptionService = new ProjectDescriptionService();
        $isStudentParticipant = !$isAdministrator && in_array('student', $access->currentRoles(), true)
            && count(array_filter($project['participants'], static fn (array $participant): bool =>
                (int) $participant['user_id'] === $access->currentUserId() && (string) $participant['role_code'] === 'student')) > 0;
        $descriptionReminder = $isStudentParticipant
            ? $descriptionService->consumePendingReminder((int) $project['id'], $access->currentUserId())
            : null;

        View::render('projects/detail', [
            'currentPage' => 'projects',
            'title' => ($project['title'] ?? 'Proyecto no encontrado') . ' | Gestión Académica',
            'bodyClass' => 'project-detail-page',
            'pageStyles' => [asset('css/project-simplified.css'), asset('css/project-description.css')],
            'pageScript' => asset('js/repository-detail.js'),
            'pageScripts' => $descriptionReminder ? [asset('js/project-description.js')] : [],
            'project' => $project,
            'activeTab' => $tab,
            'isAdministrator' => $isAdministrator,
            'publicContext' => false,
            'canReview' => $isAdministrator || $access->can('delivery.review') || $access->can('observation.reply'),
            'canDeliver' => $access->can('delivery.create'),
            'projectEditUrl' => route('projects') . '&edit=' . (int) $project['id'],
            'detailUrl' => route('project-detail') . '&id=' . (int)$project['id'],
            'returnUrl' => route('projects'),
            'previewActionUrl' => route('project-file-preview'),
            'downloadActionUrl' => route('project-file-download'),
            'descriptionReminder' => $descriptionReminder,
            'descriptionCsrf' => $session->csrfToken('project_description'),
            'descriptionSaveEndpoint' => route('project-description-save'),
            'lifecycleDescription' => $descriptionService->effectiveDescription((string) $project['type_code'], $project['summary'] ?? null),
            'academicHistoryEndpoint' => route('project-academic-history-events') . '&project_id=' . (int) $project['id'],
        ]);
    }

    public function academicHistoryEvents(): void
    {
        $projectId=filter_var($_GET['project_id']??null,FILTER_VALIDATE_INT);$offset=max(0,(int)($_GET['offset']??0));
        $session=new AuthSessionService();$access=new ProjectAccessService();$administrator=$session->hasAdminAccess();
        if(!$projectId||!$access->can('project.view')){$this->json(['success'=>false,'message'=>'El proyecto solicitado no está disponible.','data'=>[]]);}
        $db=Database::connection();$allowed=$db->prepare("SELECT 1 FROM projects p WHERE p.id=:id AND p.deleted_at IS NULL".($administrator?'':" AND ((p.status='published' AND p.is_available=1) OR p.created_by=:viewer OR EXISTS(SELECT 1 FROM project_participants pp WHERE pp.project_id=p.id AND pp.user_id=:viewer2 AND pp.status='active'))"));
        $params=['id'=>(int)$projectId];if(!$administrator){$params['viewer']=$access->currentUserId();$params['viewer2']=$access->currentUserId();}$allowed->execute($params);
        if(!$allowed->fetchColumn()){http_response_code(404);$this->json(['success'=>false,'message'=>'El proyecto solicitado no está disponible.','data'=>[]]);}
        $page=(new ProjectRecordModel())->academicHistoryPage((int)$projectId,$offset,15);
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
        $scope=(string)($_GET['scope']??'')==='repository'?'&scope=repository':'';
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

    private function resolveFile(bool $json=false): array
    {
        if (session_status()!==PHP_SESSION_ACTIVE) session_start();
        if (($_SERVER['REQUEST_METHOD']??'GET')!=='GET') { http_response_code(405); exit; }
        $projectId=filter_var($_GET['project_id']??null,FILTER_VALIDATE_INT); $fileId=filter_var($_GET['file_id']??null,FILTER_VALIDATE_INT);
        $access=new ProjectAccessService(); $admin=(new AuthSessionService())->hasAdminAccess();
        $model=new ProjectRecordModel();
        $repositoryScope=(string)($_GET['scope']??'')==='repository';
        $project=($projectId && $access->can('project.view'))?$model->find((int)$projectId,$access->currentUserId(),$admin,$repositoryScope):null;
        $file=($project && $fileId)?$model->findFile((int)$projectId,(int)$fileId):null;
        if(!$project||!$file){http_response_code(404);if($json)$this->json(['success'=>false,'message'=>'El archivo solicitado no está disponible.','data'=>[]]);exit('El archivo solicitado no está disponible.');}
        try{$fileStorage=$admin||$repositoryScope?new ProjectDocumentFileService():new PrivateProjectFileService();$path=$fileStorage->resolveStoredFile((int)$projectId,(string)$file['storage_name']);$stream=fopen($path,'rb');}catch(Throwable){$stream=false;}
        if($stream===false){http_response_code(404);exit('El archivo solicitado no está disponible.');}
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
