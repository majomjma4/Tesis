<?php
/** @var array<string, mixed>|null $project */
$institutionalReadOnly = array_key_exists('institutionalReadOnly', get_defined_vars())
    ? (bool) $institutionalReadOnly
    : true;
if ($project === null): ?>
    <section class="repository-detail-not-found"><i class="fa-solid fa-folder-open"></i><h1>Proyecto no encontrado</h1><p>El expediente solicitado no existe o no está disponible para tu cuenta.</p><a class="open-btn" href="<?= e($returnUrl) ?>">Volver</a></section>
<?php else:
    $useModernWorkspace = !empty($isStudentContext)
        || ($projectContext === 'academic' && empty($institutionalReadOnly) && !(new AuthSessionService())->isAdminModeActive());
    if ($useModernWorkspace) {
        require __DIR__ . '/_student-workspace.php';
        return;
    }
    $projectId = (int) $project['id'];
    $detailUrl = (string) $detailUrl;
    $projectContext = (string) ($projectContext ?? ($publicContext ? 'repository' : 'academic'));
    $projectCapabilities = array_replace([
        'view_project' => false,
        'edit_information' => false,
        'manage_files' => false,
        'view_academic_history' => false,
        'view_institutional_files' => false,
        'view_admin_history' => false,
        'manage_publication' => false,
        'change_status' => false,
        'manage_participants' => false,
        'manage_tutoring' => false,
        'manage_tribunal' => false,
        'register_delivery' => false,
        'review_delivery' => false,
        'create_observation' => false,
        'respond_observation' => false,
        'download_files' => false,
    ], is_array($projectCapabilities ?? null) ? $projectCapabilities : []);
    $projectStatusTransitions = is_array($projectStatusTransitions ?? null) ? $projectStatusTransitions : [];
    $isAcademicManagement = $projectContext === 'academic_management';
    $isTrackingContext = $isAcademicManagement || !empty($isTeacherContext);
    $academicLabels = project_academic_labels((string) $project['status']);
    $statusLabel = $publicContext ? 'Publicado' : $academicLabels['status'];
    $isDegreeProject = in_array(mb_strtolower((string)($project['type_code'] ?? ''),'UTF-8'), ['thesis','tesis','degree','titulacion','titulación'], true)
        || str_contains(mb_strtolower((string)($project['type_name'] ?? ''),'UTF-8'), 'titul');
    $stageLabel = $academicLabels['stage'];
    $dateLabel = static fn (?string $value): string => $value ? date('d/m/Y', strtotime($value)) : '';
    $publisherName = trim((string) ($project['publisher_academic_title'] ?? '') . ' ' . (string) ($project['publisher_name'] ?? ''));
    $roleLabels = ['student'=>'Estudiante','tutor'=>'Tutor','cotutor'=>'Cotutor','tribunal'=>'Tribunal','jury'=>'Jurado'];
    $tribunalPositionLabels = ['president'=>'Presidente','member_1'=>'Miembro 1','member_2'=>'Miembro 2'];
    $students = $project['student_authors'] ?? array_values(array_filter($project['participants'], static fn (array $row): bool => $row['role_code'] === 'student'));
    $academicTeam = array_values(array_filter($project['participants'], static fn (array $row): bool => in_array($row['role_code'], ['tutor','cotutor'], true)));
    $tribunal = array_values(array_filter($project['participants'], static fn (array $row): bool => in_array($row['role_code'], ['tribunal','jury'], true)));
    $participantRows = static function (array $rows) use ($roleLabels, $dateLabel): array {
        return array_map(static fn (array $row): array => [
            'label' => (in_array((string)($row['role_code']??''),['tribunal','jury'],true) ? ($tribunalPositionLabels[$row['tribunal_position']??''] ?? 'Cargo no especificado') : ($roleLabels[$row['role_code']] ?? ucfirst((string) $row['role_code']))) . (!empty($row['is_leader']) ? ' líder' : ''),
            'value' => trim((string) ($row['academic_title'] ?? '') . ' ' . (string) $row['full_name']),
        ], $rows);
    };
    $authorMetadata = null;
    if (count($students) === 1) {
        $authorMetadata = ['key'=>'author','label'=>'Autor','value'=>(string)$students[0]['full_name'],'icon'=>'fa-user-graduate'];
    } elseif (count($students) > 1) {
        $authorMetadata = ['key'=>'author','label'=>'Autores','value'=>count($students).' integrantes','icon'=>'fa-users'];
    }
    $previewTypes = ['pdf'=>'pdf','docx'=>'docx','txt'=>'text','png'=>'image','jpg'=>'image','jpeg'=>'image','webp'=>'image'];
    $presentationFileId = (int) ($project['presentation_file_id'] ?? 0);
    $zipContextQuery=$publicContext?'&scope=repository':($isAcademicManagement?'&context=academic_management':(!empty($isTeacherContext)?'&context=academic':''));
    $zipListActionUrl=route('project-zip-list').$zipContextQuery;
    $zipEntryPreviewActionUrl=route('project-zip-entry-preview').$zipContextQuery;
    $zipEntryDownloadActionUrl=route('project-zip-entry-download').$zipContextQuery;
    $documents = array_map(static function (array $file) use ($projectId, $presentationFileId, $previewActionUrl, $downloadActionUrl, $previewTypes, $zipContextQuery, $zipListActionUrl, $zipEntryPreviewActionUrl, $zipEntryDownloadActionUrl): array {
        $extension = strtolower((string) $file['extension']);
        $query = '&project_id=' . $projectId . '&file_id=' . (int) $file['id'];
        $isBrowsableZip=$extension==='zip'&&$zipContextQuery!=='';
        return ['id'=>(int)$file['id'],'name'=>(string)$file['original_name'],'type'=>strtoupper($extension ?: 'FILE'),'mime_type'=>(string)($file['mime_type']??''),
            'size'=>ArchiveService::formatBytes((int)$file['size_bytes']),'size_bytes'=>(int)$file['size_bytes'],'sort_order'=>(int)($file['sort_order']??$file['id']),'extension'=>$extension,'available'=>true,
            'is_presentation'=>$presentationFileId===(int)$file['id'],'is_package'=>false,
            'presentation_eligible'=>$extension!=='zip',
            'preview_supported'=>isset($previewTypes[$extension])||$isBrowsableZip,'preview_type'=>$isBrowsableZip?'zip':($previewTypes[$extension]??'unsupported'),
            'preview_url'=>$previewActionUrl.$query,'download_url'=>$downloadActionUrl.$query,
            'zip_url'=>$isBrowsableZip?$zipListActionUrl.$query:'',
            'zip_entry_preview_url'=>$isBrowsableZip?$zipEntryPreviewActionUrl.$query:'',
            'zip_entry_download_url'=>$isBrowsableZip?$zipEntryDownloadActionUrl.$query:'',
            'document_status'=>(string)($file['document_status']??''),
            'document_status_label'=>(string)($file['document_status_label']??''),
            'current_version_number'=>(int)($file['current_version_number']??1),
            'current_updated_at'=>(string)($file['current_updated_at']??$file['created_at']??''),
            'current_updated_label'=>!empty($file['current_updated_at']??$file['created_at']??null)?date('d/m/Y H:i',strtotime((string)($file['current_updated_at']??$file['created_at']))):''];
    }, $project['files']);
    if (!empty($institutionalReadOnly) && empty($projectCapabilities['download_files'])) {
        $documents = array_values(array_filter($documents, static fn (array $file): bool => !in_array(strtolower((string) ($file['extension'] ?? '')), ProjectCapabilityService::INSTITUTIONAL_ARCHIVE_EXTENSIONS, true)));
        foreach ($documents as &$institutionalDocument) {
            $institutionalDocument['download_url'] = '';
            $institutionalDocument['zip_url'] = '';
            $institutionalDocument['zip_entry_preview_url'] = '';
            $institutionalDocument['zip_entry_download_url'] = '';
        }
        unset($institutionalDocument);
    }
    $archives = array_values(array_filter($documents, static fn(array $file): bool => $file['extension']==='zip'));
    $documents = array_values(array_filter($documents, static fn(array $file): bool => $file['extension']!=='zip'));
    // Publication and academic follow-up use intentionally separate ZIP packages.
    $packageService = new ProjectRepositoryPackageService();
    try {
        $headerPackage = $publicContext
            ? $packageService->describe($projectId, route('repository-download') . '&id=' . $projectId)
            : ($isTrackingContext && !empty($projectCapabilities['download_academic_package'])
                ? $packageService->describeAcademic($projectId, route('project-package-download') . '&id=' . $projectId)
                : ['available'=>false,'download_url'=>'','file_count'=>0,'size_bytes'=>0,'size'=>'','source'=>'academic']);
    } catch (Throwable $packageError) {
        error_log('Academic project package descriptor: ' . $packageError->getMessage());
        $headerPackage = ['available'=>false,'download_url'=>'','file_count'=>0,'size_bytes'=>0,'size'=>'','source'=>'academic'];
    }
    $versions = [];
    foreach ($project['deliveries'] as $delivery) {
        $deliveryFiles = array_values(array_filter($project['files'], static fn(array $file): bool => (int)($file['delivery_id'] ?? 0)===(int)$delivery['id']));
        foreach ($deliveryFiles as $file) {
            $extension=strtolower((string)$file['extension']);
            $versions[] = ['name'=>(string)$delivery['title'],'versions_count'=>1,'updated_date'=>date('d/m/Y H:i',strtotime((string)$delivery['submitted_at'])),
                'responsible'=>(string)$delivery['author_name'],'available'=>true,'versions'=>[[
                    'file_id'=>(int)$file['id'],'id'=>(int)$delivery['id'],'number'=>(int)$delivery['version_number'],'name'=>(string)$file['original_name'],
                    'date'=>date('d/m/Y H:i',strtotime((string)$delivery['submitted_at'])),'responsible'=>(string)$delivery['author_name'],
                    'current'=>true,'available'=>true,'extension'=>$extension,'size'=>ArchiveService::formatBytes((int)$file['size_bytes']),
                    'preview_supported'=>isset($previewTypes[$extension])]]];
        }
    }
    foreach ((array)($projectDocuments['versions']??[]) as $version) {
        $extension=strtolower((string)$version['extension']);
        $versions[]=['name'=>(string)$version['original_name'],'versions_count'=>1,'updated_date'=>date('d/m/Y H:i',strtotime((string)$version['replaced_at'])),'responsible'=>(string)($version['responsible']?:'Administración institucional'),'available'=>true,'versions'=>[ ['file_id'=>(int)$version['file_id'],'id'=>(int)$version['id'],'number'=>(int)$version['version_number'],'name'=>(string)$version['original_name'],'date'=>date('d/m/Y H:i',strtotime((string)$version['replaced_at'])),'responsible'=>(string)($version['responsible']?:'Administración institucional'),'current'=>false,'available'=>true,'extension'=>$extension,'size'=>ArchiveService::formatBytes((int)$version['size_bytes']),'preview_supported'=>false] ]];
    }
    $tabs = [['id'=>'information','label'=>'Información','icon'=>'fa-file-lines']];
    if(!empty($projectCapabilities['download_files']) || !empty($projectCapabilities['view_institutional_files']))$tabs[]=['id'=>'files','label'=>'Documentos','icon'=>'fa-folder-open'];
    if(!empty($projectCapabilities['view_academic_history']))$tabs[]=['id'=>'evolution','label'=>'Historial','icon'=>'fa-clock-rotate-left'];
    foreach ($tabs as &$tabItem) $tabItem['url']=$detailUrl.'&tab='.$tabItem['id']; unset($tabItem);
    $allowedTabs=array_column($tabs,'id'); $activeTab=in_array($activeTab,$allowedTabs,true)?$activeTab:'information';
    $importantDates=array_values(array_filter([
        ['label'=>'Registro','value'=>$dateLabel($project['created_at']??null)],['label'=>'Aprobación','value'=>$dateLabel($project['approved_at']??null)],
        ['label'=>'Aprobación del tribunal','value'=>$dateLabel($project['tribunal_approved_at']??null)],['label'=>'Publicación','value'=>$dateLabel($project['published_at']??null)],
    ],static fn(array $row):bool=>$row['value']!==''));
    $publishedDescription=trim((string)($project['summary']??''));
    $reviewNotice=!empty($project['review_situation']['has_pending_observations'])?[
        'message'=>'Este proyecto tiene observaciones pendientes de la última revisión.',
        'count'=>(int)$project['review_situation']['pending_observations_count'],
        'date'=>$project['review_situation']['latest_corrections_requested_at']??null,
    ]:null;
    $informationSections = [
        ['id'=>'description','title'=>$publicContext?'Descripción del proyecto':'Descripción','icon'=>'fa-align-left','type'=>'prose','content'=>$publicContext?($publishedDescription!==''?$publishedDescription:'Este proyecto aún no cuenta con una descripción pública.'):(string)($project['summary'] ?: $project['subtitle'] ?: 'Este expediente no registra un resumen institucional.')],
        ['id'=>'institutional','title'=>'Información académica','icon'=>'fa-building-columns','type'=>'metadata','content'=>array_values(array_filter([
            ['label'=>'Código','value'=>(string)$project['code']],['label'=>'Tipo de proyecto','value'=>(string)$project['type_name']],['label'=>'Carrera','value'=>(string)$project['career_name']],
            ['label'=>'Período académico','value'=>(string)$project['period_name']],['label'=>'Estado','value'=>$statusLabel],['label'=>'Etapa académica','value'=>$stageLabel],
            ['label'=>'Línea de investigación','value'=>(string)($project['research_line_name']??'')],
            ['label'=>'Fecha de registro','value'=>$dateLabel($project['created_at']??null)],['label'=>'Fecha de aprobación','value'=>$dateLabel($project['approved_at']??null)],['label'=>'Fecha de publicación','value'=>$dateLabel($project['published_at']??null)],
        ],static fn(array $row):bool=>$row['value']!==''))],
        ['id'=>'participants','title'=>'Participantes','icon'=>'fa-users','type'=>'metadata','content'=>array_merge($participantRows($students),$participantRows($academicTeam),$participantRows($tribunal))],
        ['id'=>'dates','title'=>'Fechas importantes','icon'=>'fa-calendar-days','type'=>'metadata','content'=>$importantDates],
    ];
    if($publicContext||$isTrackingContext){
        $participantTutor=array_values(array_filter($academicTeam,static fn(array $row):bool=>$row['role_code']==='tutor'))[0]??null;
        $richTribunal=$isDegreeProject?$tribunal:[];
        $usableEmail=static function(mixed $value):string{$email=trim((string)$value);$lower=mb_strtolower($email,'UTF-8');return filter_var($email,FILTER_VALIDATE_EMAIL)&&!str_ends_with($lower,'.invalid')?$email:'';};
        $person=static function(array $row,string $role,bool $includeEmail=false)use($usableEmail):array{return [
            'user_id'=>(int)($row['user_id']??0),'username'=>trim((string)($row['username']??'')),
            'name'=>trim((string)($row['full_name']??'')),'role'=>$role,
            'initial'=>mb_strtoupper(mb_substr(trim((string)($row['full_name']??'U')),0,1,'UTF-8'),'UTF-8'),
            'email'=>$includeEmail?$usableEmail($row['email']??''):'','tribunal_position'=>$row['tribunal_position']??null,'avatar_url'=>'',
        ];};
        $richAuthors=array_map(static function(array $row)use($person):array{return $person($row,'Estudiante')+['leader'=>!empty($row['is_display_leader'])];},$students);
        $richTeachingParticipants=$publicContext
            ?array_values(array_filter($project['participants'],static fn(array $row):bool=>in_array((string)$row['role_code'],['tutor','cotutor'],true)||(!empty($row['is_teacher'])&&!in_array((string)$row['role_code'],['student','tribunal','jury'],true))))
            :$academicTeam;
        $tutoringByUser=[];
        foreach($richTeachingParticipants as $member){$role=$isTrackingContext?'Tutor':($roleLabels[$member['role_code']]??ucfirst(str_replace('_',' ',(string)$member['role_code'])));$tutoringByUser[(int)$member['user_id']]=$person($member,$role,true);}
        $mainTutorId=(int)($project['tutor_user_id']??0);
        if($mainTutorId>0&&$tutoringByUser===[])$tutoringByUser[$mainTutorId]=$person(['user_id'=>$mainTutorId,'username'=>$project['tutor_username']??'','full_name'=>$project['tutor_name']??'','email'=>$project['tutor_email']??''],'Tutor',true);
        $richTutoring=array_values($tutoringByUser);
        $primaryActiveTutor=$richTutoring[0]??null;
        $headerTutorNames=array_values(array_filter(array_map(static fn(array $tutor):string=>trim((string)($tutor['name']??'')),$richTutoring)));
        $headerTutorValue=$headerTutorNames!==[]?implode(', ',$headerTutorNames):(trim((string)($project['tutor_name']??''))?:'Sin tutor asignado');
        $richTribunalMembers=array_map(static fn(array $row):array=>$person($row,$tribunalPositionLabels[$row['tribunal_position']??'']??'Cargo no especificado',true),$richTribunal);
        $academicProgressStatuses=$isDegreeProject
            ?['development','under_review','approved','defense','tribunal_approved','published']
            :['development','under_review','approved','published'];
        $academicProgressCurrentIndex=array_search((string)$project['status'],$academicProgressStatuses,true);
        $academicProgressSteps=[];
        foreach($academicProgressStatuses as $progressIndex=>$progressStatus){
            $academicProgressSteps[]=[
                'status'=>$progressStatus,
                'label'=>project_academic_labels($progressStatus)['status'],
                'state'=>$academicProgressCurrentIndex!==false&&$progressIndex<$academicProgressCurrentIndex?'completed':($progressIndex===$academicProgressCurrentIndex?'current':'pending'),
            ];
        }
        $richAcademicFields=array_values(array_filter([
            ($publicContext||$isTrackingContext)?['key'=>'code','icon'=>'fa-hashtag','label'=>'Código','value'=>(string)$project['code']]:null,
            ['key'=>'type','icon'=>'fa-folder-tree','label'=>'Tipo de proyecto','value'=>(string)$project['type_name']],
            ['key'=>'career','icon'=>'fa-graduation-cap','label'=>'Carrera','value'=>(string)$project['career_name']],
            !$isTrackingContext?['key'=>'period','icon'=>'fa-calendar-days','label'=>'Período académico','value'=>(string)$project['period_name']]:null,
            ['key'=>'research','icon'=>'fa-microscope','label'=>'Línea de investigación','value'=>(string)($project['research_line_name']??'')],
            ['key'=>'status','icon'=>'fa-circle-check','label'=>'Estado','value'=>$statusLabel],
            !$isTrackingContext?['key'=>'stage','icon'=>'fa-flag-checkered','label'=>'Etapa académica','value'=>$stageLabel]:null,
            $isTrackingContext?['key'=>'registration','icon'=>'fa-calendar-plus','label'=>'Fecha de registro','value'=>$dateLabel($project['created_at']??null)]:null,
            $publicContext?['key'=>'completion','icon'=>'fa-calendar-check','label'=>'Fecha de finalización','value'=>$dateLabel($project['academic_completed_at']??null)]:null,
            $publicContext?['key'=>'publication','icon'=>'fa-building-columns','label'=>'Fecha de publicación','value'=>$dateLabel($project['repository_published_at']??null)]:null,
        ],static fn(?array $row):bool=>$row!==null&&$row['value']!==''));
        $informationSections=array_values(array_filter([
            ['id'=>'description','title'=>'Descripción del proyecto','icon'=>'fa-align-left','type'=>'prose','content'=>$publishedDescription!==''?$publishedDescription:($publicContext?'Este proyecto aún no cuenta con una descripción pública.':'Este proyecto aún no cuenta con una descripción registrada.')],
            ['id'=>'institutional','title'=>'Información académica','icon'=>'fa-building-columns','type'=>'metadata','content'=>$richAcademicFields],
            $isTrackingContext?['id'=>'academic-progress','title'=>'Progreso académico','icon'=>'fa-route','type'=>'academic_progress','content'=>$academicProgressSteps]:null,
            $isAcademicManagement&&$reviewNotice!==null?['id'=>'review-notice','title'=>'Observaciones pendientes','icon'=>'fa-triangle-exclamation','type'=>'review_notice','content'=>$reviewNotice]:null,
            ['id'=>'participants','title'=>'Participantes','icon'=>'fa-users','type'=>'project_participants','content'=>[
                'authors'=>$richAuthors,'tutoring'=>$richTutoring,'tribunal'=>$richTribunalMembers,
                'show_tribunal'=>$isAcademicManagement&&$isDegreeProject,
            ]],
            ['id'=>'project-classification','title'=>'Clasificación','icon'=>'fa-tags','type'=>'project_tags','content'=>array_map(static fn(array $keyword):string=>(string)$keyword['name'],(array)($project['keywords']??[]))],
        ],static fn(?array $section):bool=>$section!==null));
    }
    $actions=[];
    if ($publicContext && !empty($projectCapabilities['edit_information'])) {
        $actions[]=['id'=>'edit','label'=>'Editar','kind'=>'primary','icon'=>'fa-pen-to-square','enabled'=>true,'trigger'=>'project-editor'];
    }
    if (!$isAdministrator && !empty($projectCapabilities['create_adjustment_request'])) {
        $isPublishedStudentRequest = $publicContext && $projectContext === 'repository';
        if ($isPublishedStudentRequest && !empty($hasPendingModificationRequest)) {
            $actions[] = ['id' => 'modification-pending', 'label' => 'Solicitud pendiente', 'kind' => 'secondary', 'icon' => 'fa-clock', 'enabled' => false];
        } else {
            $actions[] = ['id' => $isPublishedStudentRequest ? 'modification-request' : 'adjustment', 'label' => $isPublishedStudentRequest ? 'Solicitar modificación' : 'Solicitar cambios', 'kind' => 'secondary', 'icon' => 'fa-comment-dots', 'enabled' => true, 'url' => '#projectAdjustmentDialog'];
        }
    } elseif (!$publicContext && !empty($projectCapabilities['register_delivery']) && $canDeliver) {
        $actions[] = ['id' => 'delivery', 'label' => 'Registrar entrega', 'kind' => 'primary', 'icon' => 'fa-upload', 'url' => $detailUrl . '&tab=review', 'enabled' => true];
    }
    if (!$isAcademicManagement && empty($isTeacherContext) && !empty($projectCapabilities['download_files']) && !empty($headerPackage['available'])) $actions[]=['id'=>'download','label'=>'Descargar','kind'=>'secondary','icon'=>'fa-download','icon_style'=>'fa-solid','url'=>(string)$headerPackage['download_url'],'enabled'=>true,'download'=>true];
    if($isAcademicManagement&&(string)$project['status']==='published')$actions[]=['id'=>'repository','label'=>'Ver en Repositorio','kind'=>'secondary','icon'=>'fa-book-open','url'=>route('repository-detail').'&id='.$projectId,'enabled'=>true];
    $statusActionCount=$isAcademicManagement&&!empty($projectCapabilities['change_status'])?count($projectStatusTransitions):0;
    foreach($statusActionCount>0?$projectStatusTransitions:[] as $transition)$actions[]=['id'=>'status-'.$transition['target'],'label'=>(string)$transition['label'],'kind'=>'secondary','icon'=>(string)$transition['icon'],'icon_style'=>'fa-solid','enabled'=>true,'trigger'=>'status-transition','transition'=>$transition];
    if($isAcademicManagement)$actions=array_values(array_filter($actions,static fn(array $action):bool=>($action['id']??'')==='adjustment'));
    $menuActions=[];
    if($publicContext&&!empty($projectCapabilities['manage_publication'])){
        $menuActions[]=['label'=>$project['is_available']?'Marcar como no disponible':'Marcar como disponible','icon'=>$project['is_available']?'fa-ban':'fa-circle-check','enabled'=>true,'action'=>'availability'];
        $menuActions[]=['label'=>'Retirar publicación','icon'=>'fa-box-archive','enabled'=>true,'action'=>'publication'];
    }
    if($publicContext&&!empty($projectCapabilities['view_admin_history']))$menuActions[]=['label'=>'Ver historial administrativo','icon'=>'fa-clock-rotate-left','enabled'=>true,'action'=>'admin-history','separator'=>$menuActions!==[]];
    if($publicContext&&!empty($projectCapabilities['edit_information']))$menuActions[]=['label'=>'Enviar a Papelera','icon'=>'fa-trash-can','enabled'=>true,'action'=>'trash','danger'=>true,'separator'=>$menuActions!==[]];
    $returnPage = 'projects';
    if (!empty($returnUrl)) {
        $parsedReturn = parse_url($returnUrl);
        if ($parsedReturn !== false && isset($parsedReturn['query'])) {
            parse_str($parsedReturn['query'], $returnQuery);
            $returnPage = strtolower(trim((string)($returnQuery['page'] ?? '')));
        }
    }
    $breadcrumbLabel = 'Proyectos activos';
    $backLabel = 'Volver a proyectos activos';
    if ($returnPage === 'thesis-management') {
        $breadcrumbLabel = 'Gestión de Titulación';
        $backLabel = 'Volver a Gestión de Titulación';
    } elseif ($returnPage === 'assigned-projects') {
        $breadcrumbLabel = 'Proyectos asignados';
        $backLabel = 'Volver a proyectos asignados';
    }

    $breadcrumbs = $isTrackingContext ? [
        ['label'=>$breadcrumbLabel,'url'=>$returnUrl],
        ['label'=>(string)$project['code'],'url'=>null],
    ] : [
        ['label'=>$publicContext?'Repositorio':'Proyectos','url'=>$returnUrl],
        ['label'=>(string)$project['code'],'url'=>null],
    ];
    $publishedHeaderMetadata = [
        ['key'=>'period','label'=>'Período académico','value'=>(string)$project['period_name'],'icon'=>'fa-calendar-days'],
        ['key'=>'tutor','label'=>'Tutor','value'=>(string)($project['tutor_name']??''),'icon'=>'fa-chalkboard-user'],
        $authorMetadata,
        ['key'=>'registration','label'=>'Registro','value'=>$dateLabel($project['created_at']??null),'icon'=>'fa-calendar-plus'],
        ['key'=>'availability','label'=>'Disponibilidad','value'=>$project['is_available']?'Disponible':'No disponible','icon'=>$project['is_available']?'fa-circle-check':'fa-circle-minus'],
    ];
    $academicManagementHeaderMetadata = [
        $authorMetadata??['key'=>'author','label'=>'Autor','value'=>'Sin autor registrado','icon'=>'fa-user-graduate'],
        ['key'=>'tutor','label'=>'Tutor','value'=>$headerTutorValue??(trim((string)($project['tutor_name']??''))?:'Sin tutor asignado'),'icon'=>'fa-chalkboard-user'],
        ['key'=>'period','label'=>'Período académico','value'=>trim((string)$project['period_name'])?:'Sin período registrado','icon'=>'fa-calendar-days'],
        ['key'=>'registration','label'=>'Fecha de inicio','value'=>$dateLabel($project['created_at']??null)?:'Sin fecha registrada','icon'=>'fa-calendar-plus'],
        ['key'=>'updated','label'=>'Última actualización','value'=>$dateLabel($project['updated_at']??null)?:'Sin actualización registrada','icon'=>'fa-clock'],
    ];
    $headerMetadata = $isTrackingContext
        ? $academicManagementHeaderMetadata
        : ((string)$project['status']==='published' ? $publishedHeaderMetadata : [
            ['label'=>'Código','value'=>(string)$project['code']],['label'=>'Carrera','value'=>(string)$project['career_name']],['label'=>'Período académico','value'=>(string)$project['period_name']],['label'=>'Tutor','value'=>(string)($project['tutor_name']??'')],['label'=>'Integrantes','value'=>count($students).''],['label'=>'Registro','value'=>$dateLabel($project['created_at']??null)],['label'=>'Disponibilidad','value'=>$project['is_available']?'Disponible':'No disponible'],
        ]);
    $adjustmentNotice = $projectContext === 'academic' && !empty($isStudentParticipant) && !empty($adjustmentData['summary']['has_pending_adjustments'])
        ? $adjustmentData['summary']
        : null;
    if (is_array($adjustmentNotice)) $adjustmentNotice['history_url'] = $detailUrl . '&tab=evolution';
    $digitalRecord=['entity'=>['type'=>'project','id'=>$projectId,'query_key'=>'project_id'],'context'=>$projectContext,'mode'=>'view','return_url'=>$returnUrl,
        'capabilities'=>$projectCapabilities,
        'breadcrumbs'=>$breadcrumbs,
        'header'=>['title'=>(string)$project['title'],'description'=>(string)($project['subtitle']??''),'type_label'=>(string)$project['type_name'],'type_icon'=>$publicContext?'fa-folder-tree':null,'status_label'=>$statusLabel,'status_tone'=>in_array((string)$project['status'],['approved','published'],true)?'success':'neutral','publisher_name'=>$publicContext&&$publisherName!==''?$publisherName:''],
        'metadata'=>array_values(array_filter($headerMetadata,static fn(?array $row):bool=>$row!==null&&$row['value']!=='')),
        'actions'=>$actions,'menu_actions'=>$menuActions,'tabs'=>$tabs,'active_tab'=>$activeTab,'information_sections'=>$informationSections,
        'adjustment_notice'=>$adjustmentNotice,
        'documents'=>$documents,'archives'=>$archives,'versions'=>$versions,
        // Proyectos activos es una vista de seguimiento: la gestión documental
        // institucional permanece disponible en sus contextos específicos, pero
        // no se expone desde este detalle académico.
        'can_manage_files'=>!empty($projectCapabilities['manage_files'])&&$publicContext&&!empty($projectDocuments),
        'restorable_files'=>(array)($projectDocuments['restorable']??[]),
        'file_upload'=>!empty($projectCapabilities['manage_files'])&&$publicContext&&!empty($projectDocuments)?['context'=>(string)($projectDocuments['context']??$projectContext),'endpoint'=>(string)$projectDocuments['endpoint'],'csrf_token'=>(string)$projectDocuments['csrf'],'limits'=>(array)$projectDocuments['limits']]:[],
        'package'=>$headerPackage,
        'global_file_actions'=>[],
        'document_review'=>$isAcademicManagement&&(string)$project['status']==='development'?(array)($documentReview??[]):[],
        'project_histories'=>[
            'academic'=>(array)($project['academic_history']??[]),
            'academic_total'=>(int)($project['academic_history_total']??count((array)($project['academic_history']??[]))),
            'academic_endpoint'=>(string)($academicHistoryEndpoint??''),
            'modifications'=>$publicContext?(array)($project['post_publication_modifications']??[]):[],
        ],
        'endpoints'=>['preview'=>$previewActionUrl,'download'=>$downloadActionUrl,'admin_history'=>(string)($projectHistoryEndpoint??'')], 'version_endpoints'=>['preview'=>$previewActionUrl,'download'=>$downloadActionUrl],
         'admin_actions'=>['endpoint'=>(string)($projectAdminEndpoint??''),'trash_endpoint'=>(string)($projectTrashEndpoint??''),'csrf_token'=>(string)($projectAdminCsrf??''),'trash_csrf_token'=>(string)($projectTrashCsrf??''),'status'=>(string)$project['status'],'is_available'=>!empty($project['is_available']),'redirect'=>$returnUrl],
        'status_transition'=>[
            'enabled'=>$isAcademicManagement&&$projectStatusTransitions!==[],
            'endpoint'=>(string)($projectStatusEndpoint??''),'csrf_token'=>(string)($projectStatusCsrf??''),
            'current_status'=>(string)$project['status'],'items'=>$projectStatusTransitions,
        ],
        'review_notice'=>null,
        'contextual_review_notice'=>$isAcademicManagement || $institutionalReadOnly ? null : $reviewNotice,
        'return_label'=>$publicContext?'Volver al repositorio':($isTrackingContext?$backLabel:'Volver a proyectos')];
    if($publicContext||$isTrackingContext): ?><style>
    .digital-record[data-entity-type="project"] .ed-information{grid-template-columns:minmax(0,2fr) minmax(260px,1fr);gap:14px;align-items:start}
    .digital-record[data-entity-type="project"] .ed-document-section{padding:19px}
    .digital-record[data-entity-type="project"] .ed-document-section[data-information-section="description"]{grid-column:1/-1}
    .digital-record[data-entity-type="project"] .ed-document-section[data-information-section="description"]{padding:20px;border-top:3px solid var(--primary)}
    .digital-record[data-entity-type="project"] .ed-document-section-header{margin-bottom:13px;padding-bottom:11px}
    .digital-record[data-entity-type="project"] .ed-document-section[data-information-section="description"] .ed-prose{max-width:82ch;font-size:var(--font-md);line-height:1.72;overflow-wrap:anywhere}
    .digital-record[data-entity-type="project"] .ed-document-section[data-information-section="description"] .ed-prose p{margin-bottom:12px}
    .digital-record[data-record-context="academic_management"] :is(.ed-information-meta dd,.ed-participant-identity strong,.ed-participant-identity span,.ed-participant-identity small,.ed-classification-tag>span){word-break:normal;overflow-wrap:break-word}
    .digital-record[data-record-context="academic_management"] .ed-meta [data-record-meta="code"]{--ed-meta-accent:#0891b2}
    .digital-record[data-record-context="academic_management"] .ed-meta [data-record-meta="status"]{--ed-meta-accent:#16a34a}
    .digital-record[data-record-context="academic_management"] .ed-meta [data-record-meta="stage"]{--ed-meta-accent:#2563eb}
    .digital-record[data-record-context="academic_management"] .ed-label{min-height:25px;padding:6px 11px;border-color:var(--line);background:var(--surface-soft);color:var(--muted)}
    .digital-record[data-record-context="academic_management"] .ed-label[data-record-status-label]{--project-status-color:#2563eb;background:color-mix(in srgb,var(--project-status-color) 14%,var(--surface));border-color:color-mix(in srgb,var(--project-status-color) 38%,var(--line));color:color-mix(in srgb,var(--project-status-color) 88%,var(--text));box-shadow:0 2px 8px color-mix(in srgb,var(--project-status-color) 12%,transparent)}
    .digital-record[data-record-context="academic_management"][data-record-status="development"] .ed-label[data-record-status-label]{--project-status-color:#2563eb}
    .digital-record[data-record-context="academic_management"][data-record-status="under_review"] .ed-label[data-record-status-label]{--project-status-color:#d97706}
    .digital-record[data-record-context="academic_management"][data-record-status="approved"] .ed-label[data-record-status-label]{--project-status-color:#16a34a}
    .digital-record[data-record-context="academic_management"][data-record-status="defense"] .ed-label[data-record-status-label]{--project-status-color:#7c3aed}
    .digital-record[data-record-context="academic_management"][data-record-status="tribunal_approved"] .ed-label[data-record-status-label]{--project-status-color:#0f766e}
    .digital-record[data-record-context="academic_management"][data-record-status="published"] .ed-label[data-record-status-label]{--project-status-color:#15803d}
    /* El seguimiento Docente comparte el mismo lenguaje visual del detalle Admin. */
    .digital-record[data-record-context="academic"] .ed-label{min-height:25px;padding:6px 11px;border-color:var(--line);background:var(--surface-soft);color:var(--muted)}
    .digital-record[data-record-context="academic"] .ed-label[data-record-status-label]{--project-status-color:#2563eb;background:color-mix(in srgb,var(--project-status-color) 14%,var(--surface));border-color:color-mix(in srgb,var(--project-status-color) 38%,var(--line));color:color-mix(in srgb,var(--project-status-color) 88%,var(--text));box-shadow:0 2px 8px color-mix(in srgb,var(--project-status-color) 12%,transparent)}
    .digital-record[data-record-context="academic"][data-record-status="development"] .ed-label[data-record-status-label]{--project-status-color:#2563eb}
    .digital-record[data-record-context="academic"][data-record-status="under_review"] .ed-label[data-record-status-label]{--project-status-color:#d97706}
    .digital-record[data-record-context="academic"][data-record-status="approved"] .ed-label[data-record-status-label]{--project-status-color:#16a34a}
    .digital-record[data-record-context="academic"][data-record-status="defense"] .ed-label[data-record-status-label]{--project-status-color:#7c3aed}
    .digital-record[data-record-context="academic"][data-record-status="tribunal_approved"] .ed-label[data-record-status-label]{--project-status-color:#0f766e}
    .digital-record[data-record-context="academic"][data-record-status="published"] .ed-label[data-record-status-label]{--project-status-color:#15803d}
    .digital-record[data-record-context="academic"] .ed-information{grid-template-areas:"description description" "institutional participants" "progress classification";grid-template-rows:auto max-content max-content;row-gap:18px}
    .digital-record[data-record-context="academic"] .ed-document-section[data-information-section="description"]{grid-area:description}
    .digital-record[data-record-context="academic"] .ed-document-section[data-information-section="institutional"]{grid-area:institutional}
    .digital-record[data-record-context="academic"] .ed-document-section[data-information-section="participants"]{grid-area:participants}
    .digital-record[data-record-context="academic"] .ed-document-section[data-information-section="academic-progress"]{position:relative;z-index:0;grid-area:progress;margin:0;border-top:3px solid var(--primary)}
    .digital-record[data-record-context="academic"] .ed-document-section[data-information-section="project-classification"]{grid-area:classification}
    .digital-record[data-record-context="academic"] .ed-academic-progress{min-width:0;margin:0;padding:4px 0 2px;display:grid;grid-template-columns:repeat(6,minmax(0,1fr));list-style:none}
    .digital-record[data-record-context="academic"] .ed-academic-progress[data-step-count="4"]{grid-template-columns:repeat(4,minmax(0,1fr))}
    .digital-record[data-record-context="academic"] .ed-academic-progress li{position:relative;min-width:0;display:grid;grid-template-rows:30px auto;justify-items:center;gap:8px;color:var(--muted);text-align:center}
    .digital-record[data-record-context="academic"] .ed-academic-progress li:not(:last-child)::after{position:absolute;z-index:0;top:14px;left:calc(50% + 15px);width:calc(100% - 30px);height:2px;background:var(--line);content:""}
    .digital-record[data-record-context="academic"] .ed-academic-progress li[data-step-state="completed"]:not(:last-child)::after{background:#16a34a}
    .digital-record[data-record-context="academic"] .ed-academic-progress-marker{position:relative;z-index:1;width:30px;height:30px;border:2px solid var(--line);border-radius:50%;background:var(--surface);color:var(--muted);display:grid;place-items:center;font-size:var(--font-xs);font-weight:850}
    .digital-record[data-record-context="academic"] .ed-academic-progress li[data-step-state="completed"] .ed-academic-progress-marker{border-color:#16a34a;background:#16a34a;color:#fff}
    .digital-record[data-record-context="academic"] .ed-academic-progress li[aria-current="step"] .ed-academic-progress-marker{border-color:var(--primary);background:var(--primary);color:#fff;box-shadow:0 0 0 4px color-mix(in srgb,var(--primary) 14%,transparent)}
    .digital-record[data-record-context="academic"] .ed-academic-progress-label{max-width:112px;font-size:var(--font-xs);font-weight:780;line-height:1.35;word-break:normal;overflow-wrap:break-word}
    .digital-record[data-record-context="academic"] .ed-academic-progress li[data-step-state="completed"] .ed-academic-progress-label{color:#15803d}
    .digital-record[data-record-context="academic"] .ed-academic-progress li[aria-current="step"] .ed-academic-progress-label{color:var(--primary);font-weight:900}
    .digital-record[data-record-context="academic"] .ed-academic-progress-current{display:block;margin-top:2px;font-size:var(--font-xs);font-weight:900;letter-spacing:.06em;text-transform:uppercase}
    .digital-record[data-record-context="academic_management"] .ed-header{container:project-admin-header/inline-size}
    .digital-record[data-record-context="academic_management"] .ed-meta>div{min-width:0}
    .digital-record[data-record-context="academic_management"] .ed-meta :is(dt,dd){word-break:normal;overflow-wrap:break-word}
    .digital-record[data-record-context="academic_management"] .ed-meta :is([data-record-meta="code"],[data-record-meta="registration"],[data-record-meta="updated"]) dd{overflow-wrap:normal}
    .digital-record[data-record-context="academic_management"] .ed-document-section[data-information-section="institutional"]{grid-column:1;grid-row:auto}
    .digital-record[data-record-context="academic_management"] .ed-document-section[data-information-section="academic-progress"]{grid-column:1;grid-row:auto;margin-top:4px;border-top:3px solid var(--primary)}
    .digital-record[data-record-context="academic_management"] .ed-document-section[data-information-section="review-notice"]{grid-column:1;grid-row:4;margin-top:4px;padding:13px 15px;border-color:#fde68a;background:#fffbeb;color:#854d0e;box-shadow:none}
    .digital-record[data-record-context="academic_management"] .ed-review-notice-inline{min-width:0;display:flex;align-items:flex-start;gap:10px}
    .digital-record[data-record-context="academic_management"] .ed-review-notice-inline>i{flex:0 0 auto;margin-top:2px}
    .digital-record[data-record-context="academic_management"] .ed-review-notice-inline>div{min-width:0;display:grid;gap:3px}
    .digital-record[data-record-context="academic_management"] .ed-review-notice-inline strong{font-size:var(--font-sm);line-height:1.5}
    .digital-record[data-record-context="academic_management"] .ed-review-notice-inline span{font-size:var(--font-xs);line-height:1.45}
    .digital-record[data-record-context="academic_management"] .ed-academic-progress-scroll{max-width:100%;min-width:0;overflow:visible}
    .digital-record[data-record-context="academic_management"] .ed-academic-progress{min-width:0;margin:0;padding:4px 0 2px;display:grid;grid-template-columns:repeat(6,minmax(0,1fr));list-style:none}
    .digital-record[data-record-context="academic_management"] .ed-academic-progress[data-step-count="4"]{min-width:0;grid-template-columns:repeat(4,minmax(0,1fr))}
    .digital-record[data-record-context="academic_management"] .ed-academic-progress li{position:relative;min-width:0;display:grid;grid-template-rows:30px auto;justify-items:center;gap:8px;color:var(--muted);text-align:center}
    .digital-record[data-record-context="academic_management"] .ed-academic-progress li:not(:last-child)::after{position:absolute;z-index:0;top:14px;left:calc(50% + 15px);width:calc(100% - 30px);height:2px;background:var(--line);content:""}
    .digital-record[data-record-context="academic_management"] .ed-academic-progress li[data-step-state="completed"]:not(:last-child)::after{background:#16a34a}
    .digital-record[data-record-context="academic_management"] .ed-academic-progress-marker{position:relative;z-index:1;width:30px;height:30px;border:2px solid var(--line);border-radius:50%;background:var(--surface);color:var(--muted);display:grid;place-items:center;font-size:var(--font-xs);font-weight:850}
    .digital-record[data-record-context="academic_management"] .ed-academic-progress li[data-step-state="completed"] .ed-academic-progress-marker{border-color:#16a34a;background:#16a34a;color:#fff}
    .digital-record[data-record-context="academic_management"] .ed-academic-progress li[aria-current="step"] .ed-academic-progress-marker{border-color:var(--primary);background:var(--primary);color:#fff;box-shadow:0 0 0 4px color-mix(in srgb,var(--primary) 14%,transparent)}
    .digital-record[data-record-context="academic_management"] .ed-academic-progress-label{max-width:112px;font-size:var(--font-xs);font-weight:780;line-height:1.35;word-break:normal;overflow-wrap:break-word}
    .digital-record[data-record-context="academic_management"] .ed-academic-progress li[data-step-state="completed"] .ed-academic-progress-label{color:#15803d}
    .digital-record[data-record-context="academic_management"] .ed-academic-progress li[aria-current="step"] .ed-academic-progress-label{color:var(--primary);font-weight:900}
    .digital-record[data-record-context="academic_management"] .ed-academic-progress-current{display:block;margin-top:2px;font-size:var(--font-xs);font-weight:900;letter-spacing:.06em;text-transform:uppercase}
    @container project-admin-header (min-width:900px){.digital-record[data-record-context="academic_management"] .ed-meta{grid-template-columns:repeat(5,minmax(0,1fr))}.digital-record[data-record-context="academic_management"] .ed-meta>div{grid-column:auto}}
    @container project-admin-header (min-width:650px) and (max-width:899.98px){.digital-record[data-record-context="academic_management"] .ed-meta{grid-template-columns:repeat(6,minmax(0,1fr))}.digital-record[data-record-context="academic_management"] .ed-meta>div:nth-child(-n+3){grid-column:span 2}.digital-record[data-record-context="academic_management"] .ed-meta>div:nth-child(n+4){grid-column:span 3}}
    @container project-admin-header (min-width:480px) and (max-width:649.98px){.digital-record[data-record-context="academic_management"] .ed-meta{grid-template-columns:repeat(2,minmax(0,1fr))}.digital-record[data-record-context="academic_management"] .ed-meta>div{grid-column:auto}}
    @container project-admin-header (max-width:479.98px){.digital-record[data-record-context="academic_management"] .ed-meta{grid-template-columns:1fr}.digital-record[data-record-context="academic_management"] .ed-meta>div{grid-column:auto}}
    @media(max-width:1100px){
        .digital-record[data-record-context="academic_management"] .ed-academic-progress[data-step-count="6"]{grid-template-columns:repeat(3,minmax(0,1fr));row-gap:22px}
        .digital-record[data-record-context="academic_management"] .ed-academic-progress[data-step-count="6"] li:nth-child(3)::after{display:none}
        .digital-record[data-record-context="academic"] .ed-academic-progress[data-step-count="6"]{grid-template-columns:repeat(3,minmax(0,1fr));row-gap:22px}
        .digital-record[data-record-context="academic"] .ed-academic-progress[data-step-count="6"] li:nth-child(3)::after{display:none}
    }
    @media(max-width:800px){.digital-record[data-entity-type="project"] .ed-information{grid-template-columns:1fr}.digital-record[data-record-context="academic_management"] .ed-document-section[data-information-section="academic-progress"],.digital-record[data-record-context="academic_management"] .ed-document-section[data-information-section="review-notice"]{grid-column:auto;grid-row:auto}.digital-record[data-record-context="academic"] .ed-information{grid-template-areas:none}.digital-record[data-record-context="academic"] .ed-document-section[data-information-section="description"],.digital-record[data-record-context="academic"] .ed-document-section[data-information-section="institutional"],.digital-record[data-record-context="academic"] .ed-document-section[data-information-section="participants"],.digital-record[data-record-context="academic"] .ed-document-section[data-information-section="academic-progress"],.digital-record[data-record-context="academic"] .ed-document-section[data-information-section="project-classification"]{grid-area:auto}}
    @media(max-width:520px){
        .digital-record[data-record-context="academic_management"] .ed-academic-progress,.digital-record[data-record-context="academic_management"] .ed-academic-progress[data-step-count="4"],.digital-record[data-record-context="academic_management"] .ed-academic-progress[data-step-count="6"]{grid-template-columns:1fr;row-gap:0;padding:0}
        .digital-record[data-record-context="academic_management"] .ed-academic-progress li{min-height:48px;padding:0 0 14px;grid-template-columns:30px minmax(0,1fr);grid-template-rows:auto;align-items:start;justify-items:start;gap:11px;text-align:left}
        .digital-record[data-record-context="academic_management"] .ed-academic-progress li:not(:last-child)::after,.digital-record[data-record-context="academic_management"] .ed-academic-progress[data-step-count="6"] li:nth-child(3)::after{display:block;top:30px;left:14px;width:2px;height:calc(100% - 30px)}
        .digital-record[data-record-context="academic_management"] .ed-academic-progress-label{max-width:none;padding-top:6px}
        .digital-record[data-record-context="academic"] .ed-academic-progress,.digital-record[data-record-context="academic"] .ed-academic-progress[data-step-count="4"],.digital-record[data-record-context="academic"] .ed-academic-progress[data-step-count="6"]{grid-template-columns:1fr;row-gap:0;padding:0}
        .digital-record[data-record-context="academic"] .ed-academic-progress li{min-height:48px;padding:0 0 14px;grid-template-columns:30px minmax(0,1fr);grid-template-rows:auto;align-items:start;justify-items:start;gap:11px;text-align:left}
        .digital-record[data-record-context="academic"] .ed-academic-progress li:not(:last-child)::after,.digital-record[data-record-context="academic"] .ed-academic-progress[data-step-count="6"] li:nth-child(3)::after{display:block;top:30px;left:14px;width:2px;height:calc(100% - 30px)}
        .digital-record[data-record-context="academic"] .ed-academic-progress-label{max-width:none;padding-top:6px}
    }
    @media(max-width:400px){
        .digital-record[data-record-context="academic_management"] .ed-back-links{margin-left:0;gap:10px}
        .digital-record[data-record-context="academic_management"] .ed-header{padding:18px 12px}
        .digital-record[data-record-context="academic_management"] .ed-header-top{grid-template-columns:minmax(0,1fr);gap:11px}
        .digital-record[data-record-context="academic_management"] .ed-labels,.digital-record[data-record-context="academic_management"] .ed-menu{grid-column:1;grid-row:auto;justify-self:start}
        .digital-record[data-record-context="academic_management"] .ed-primary-actions{grid-column:1;grid-row:auto;grid-template-columns:1fr}
        .digital-record[data-record-context="academic_management"] .ed-action{white-space:normal;text-align:center}
        .digital-record[data-record-context="academic_management"] .ed-content{padding:16px 12px 20px}
        .digital-record[data-record-context="academic_management"] .ed-tabs{grid-template-columns:1fr;gap:0;padding:0 12px;overflow-x:auto}
        .digital-record[data-record-context="academic_management"] .ed-tab{min-height:48px;justify-content:flex-start;padding:0 4px;white-space:nowrap}
        .digital-record[data-record-context="academic_management"] .ed-document-section{padding:14px}
        .digital-record[data-record-context="academic_management"] .ed-information-meta{grid-template-columns:1fr}
        .digital-record[data-record-context="academic_management"] .ed-document-section[data-information-section="project-classification"] .ed-tags{grid-template-columns:1fr}
        .digital-record[data-record-context="academic_management"] .ed-classification-tag,.digital-record[data-record-context="academic_management"] .ed-classification-tag>span{word-break:keep-all;overflow-wrap:normal;white-space:nowrap}
    }
    @media(max-width:280px){
        .digital-record[data-record-context="academic_management"] .ed-header{padding-right:10px;padding-left:10px}
        .digital-record[data-record-context="academic_management"] .ed-header h1{font-size:clamp(1.35rem,10vw,1.65rem)}
        .digital-record[data-record-context="academic_management"] .ed-content{padding-right:10px;padding-left:10px}
        .digital-record[data-record-context="academic_management"] .ed-document-section{padding:12px}
        .digital-record[data-record-context="academic_management"] .ed-document-heading{align-items:flex-start;font-size:var(--font-md);line-height:1.35}
    }
    </style><?php endif;
    require __DIR__.'/../repository/_ficha-institucional.php';
    /* Dos columnas reales: evita que la altura de Información académica
       reserve espacio en la columna de Descripción/Progreso. */
    if ($isAcademicManagement || !empty($isTeacherContext)): ?><style>
    .digital-record:is([data-record-context="academic_management"],[data-record-context="academic"]) .ed-information{display:grid;grid-template-columns:minmax(0,2fr) minmax(250px,1fr);gap:18px;align-items:start}
    .digital-record:is([data-record-context="academic_management"],[data-record-context="academic"]) .ed-contextual-notices{grid-column:1/-1}
    .digital-record:is([data-record-context="academic_management"],[data-record-context="academic"]) .ed-information>.ed-document-section[data-information-section="description"]{grid-column:1/-1;grid-row:auto}
    .digital-record:is([data-record-context="academic_management"],[data-record-context="academic"]) .ed-information-column{min-width:0;display:grid;align-content:start;gap:18px}
    .digital-record:is([data-record-context="academic_management"],[data-record-context="academic"]) .ed-information-column-left{grid-column:1}
    .digital-record:is([data-record-context="academic_management"],[data-record-context="academic"]) .ed-information-column-right{grid-column:2}
    .digital-record:is([data-record-context="academic_management"],[data-record-context="academic"]) .ed-information-column .ed-document-section{grid-area:auto;grid-column:auto;grid-row:auto}
    @media(min-width:801px){
        .digital-record:is([data-record-context="academic_management"],[data-record-context="academic"]) .ed-information{grid-template-areas:"description description" "institutional participants" "progress classification";grid-template-rows:auto auto auto}
        .digital-record:is([data-record-context="academic_management"],[data-record-context="academic"]) .ed-information-column{display:contents}
        .digital-record:is([data-record-context="academic_management"],[data-record-context="academic"]) .ed-information>.ed-document-section[data-information-section="description"]{grid-area:description;grid-column:1/-1}
        .digital-record:is([data-record-context="academic_management"],[data-record-context="academic"]) .ed-information-column .ed-document-section[data-information-section="institutional"]{grid-area:institutional}
        .digital-record:is([data-record-context="academic_management"],[data-record-context="academic"]) .ed-information-column .ed-document-section[data-information-section="participants"]{grid-area:participants}
        .digital-record:is([data-record-context="academic_management"],[data-record-context="academic"]) .ed-information-column .ed-document-section[data-information-section="academic-progress"]{grid-area:progress}
        .digital-record:is([data-record-context="academic_management"],[data-record-context="academic"]) .ed-information-column .ed-document-section[data-information-section="project-classification"]{grid-area:classification}
    }
    @media(max-width:800px){
        .digital-record:is([data-record-context="academic_management"],[data-record-context="academic"]) .ed-information{grid-template-columns:1fr}
        .digital-record:is([data-record-context="academic_management"],[data-record-context="academic"]) .ed-information>.ed-document-section[data-information-section="description"]{grid-column:auto}
        .digital-record:is([data-record-context="academic_management"],[data-record-context="academic"]) .ed-information-column{grid-column:auto;gap:18px}
    }
    </style><?php endif;
    if (!empty(array_filter([
        $projectCapabilities['create_adjustment_request'] ?? false,
        $projectCapabilities['view_adjustment_requests'] ?? false,
        $projectCapabilities['respond_adjustment_request'] ?? false,
        $projectCapabilities['address_adjustment_request'] ?? false,
        $projectCapabilities['close_adjustment_request'] ?? false,
    ]))) require __DIR__.'/_adjustment-requests.php';
    if(!empty($digitalRecord['status_transition']['enabled'])) require __DIR__.'/../repository/_project-status-transition-dialog.php';
    if($publicContext):?><style>@media(min-width:801px){.digital-record[data-entity-type="project"][data-record-context="repository"] .ed-information{grid-template-columns:minmax(0,2fr) minmax(260px,1fr)}.digital-record[data-entity-type="project"][data-record-context="repository"] .ed-document-section[data-information-section="description"]{grid-column:1/-1}}</style><?php endif;
    if(!$publicContext&&!empty($descriptionReminder)) require __DIR__.'/_description-reminder.php';
    if(($publicContext||$isAcademicManagement)&&!empty($projectCapabilities['edit_information'])&&!empty($projectEditorCatalogs)){$projectEditorOnly=true;$catalogs=$projectEditorCatalogs;$projectCsrf=$projectTrashCsrf;$projectEndpoints=['save'=>$projectSaveEndpoint,'trash'=>$projectTrashEndpoint];$projectStatusDialog=['enabled'=>false];$projectEditorPayload=array_merge($project,['tutor_id'=>(int)($primaryActiveTutor['user_id']??$project['tutor_id']??0),'tutor_user_id'=>(int)($primaryActiveTutor['user_id']??$project['tutor_user_id']??0),'tutor_username'=>(string)($primaryActiveTutor['username']??$project['tutor_username']??''),'tutor_name'=>(string)($primaryActiveTutor['name']??$project['tutor_name']??''),'tutor_email'=>(string)($primaryActiveTutor['email']??$project['tutor_email']??''),'status_label'=>$statusLabel,'stage_label'=>$stageLabel,'capabilities'=>$projectCapabilities,'status_actions'=>$projectStatusTransitions,'status_transitions'=>$projectStatusTransitions,'presentation_files'=>array_values(array_map(static fn(array $file):array=>['id'=>(int)$file['id'],'name'=>(string)$file['original_name'],'extension'=>(string)$file['extension'],'format'=>strtoupper((string)$file['extension']),'icon'=>'fa-regular fa-file','size'=>ArchiveService::formatBytes((int)$file['size_bytes'])],$project['files']))]);require __DIR__.'/../admin/projects.php';}
endif;
