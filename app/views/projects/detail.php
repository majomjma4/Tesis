<?php
if ($project === null): ?>
    <section class="repository-detail-not-found"><i class="fa-solid fa-folder-open"></i><h1>Proyecto no encontrado</h1><p>El expediente solicitado no existe o no está disponible para tu cuenta.</p><a class="open-btn" href="<?= e($returnUrl) ?>">Volver</a></section>
<?php else:
    $projectId = (int) $project['id'];
    $detailUrl = (string) $detailUrl;
    $projectContext = (string) ($projectContext ?? ($publicContext ? 'repository' : 'academic'));
    $projectCapabilities = array_replace([
        'view_project' => false,
        'edit_information' => false,
        'manage_files' => false,
        'view_academic_history' => false,
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
    $academicLabels = project_academic_labels((string) $project['status']);
    $statusLabel = $publicContext ? 'Publicado' : $academicLabels['status'];
    $isDegreeProject = in_array(mb_strtolower((string)($project['type_code'] ?? ''),'UTF-8'), ['thesis','tesis','degree','titulacion','titulación'], true)
        || str_contains(mb_strtolower((string)($project['type_name'] ?? ''),'UTF-8'), 'titul');
    $stageLabel = $academicLabels['stage'];
    $dateLabel = static fn (?string $value): string => $value ? date('d/m/Y', strtotime($value)) : '';
    $roleLabels = ['student'=>'Estudiante','tutor'=>'Tutor','cotutor'=>'Cotutor','tribunal'=>'Tribunal','jury'=>'Jurado'];
    $students = $project['student_authors'] ?? array_values(array_filter($project['participants'], static fn (array $row): bool => $row['role_code'] === 'student'));
    $academicTeam = array_values(array_filter($project['participants'], static fn (array $row): bool => in_array($row['role_code'], ['tutor','cotutor'], true)));
    $tribunal = array_values(array_filter($project['participants'], static fn (array $row): bool => in_array($row['role_code'], ['tribunal','jury'], true)));
    $participantRows = static function (array $rows) use ($roleLabels, $dateLabel): array {
        return array_map(static fn (array $row): array => [
            'label' => ($roleLabels[$row['role_code']] ?? ucfirst((string) $row['role_code'])) . (!empty($row['is_leader']) ? ' líder' : ''),
            'value' => (string) $row['full_name'],
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
    $documents = array_map(static function (array $file) use ($projectId, $presentationFileId, $previewActionUrl, $downloadActionUrl, $previewTypes): array {
        $extension = strtolower((string) $file['extension']);
        $query = '&project_id=' . $projectId . '&file_id=' . (int) $file['id'];
        return ['id'=>(int)$file['id'],'name'=>(string)$file['original_name'],'type'=>strtoupper($extension ?: 'FILE'),'mime_type'=>(string)($file['mime_type']??''),
            'size'=>ArchiveService::formatBytes((int)$file['size_bytes']),'sort_order'=>(int)($file['sort_order']??$file['id']),'extension'=>$extension,'available'=>true,
            'is_presentation'=>$presentationFileId===(int)$file['id'],'is_package'=>false,
            'preview_supported'=>isset($previewTypes[$extension]),'preview_type'=>$previewTypes[$extension] ?? 'unsupported',
            'preview_url'=>$previewActionUrl.$query,'download_url'=>$downloadActionUrl.$query];
    }, $project['files']);
    $archives = array_values(array_filter($documents, static fn(array $file): bool => $file['extension']==='zip'));
    $documents = array_values(array_filter($documents, static fn(array $file): bool => $file['extension']!=='zip'));
    $headerPackage = (new ProjectPackageService())->describe($projectId, (array) $project['files']);
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
    if(!empty($projectCapabilities['download_files']))$tabs[]=['id'=>'files','label'=>'Documentos','icon'=>'fa-folder-open'];
    if(!empty($projectCapabilities['view_academic_history']))$tabs[]=['id'=>'evolution','label'=>'Historial','icon'=>'fa-clock-rotate-left'];
    foreach ($tabs as &$tabItem) $tabItem['url']=$detailUrl.'&tab='.$tabItem['id']; unset($tabItem);
    $allowedTabs=array_column($tabs,'id'); $activeTab=in_array($activeTab,$allowedTabs,true)?$activeTab:'information';
    $importantDates=array_values(array_filter([
        ['label'=>'Registro','value'=>$dateLabel($project['created_at']??null)],['label'=>'Aprobación','value'=>$dateLabel($project['approved_at']??null)],
        ['label'=>'Aprobación del tribunal','value'=>$dateLabel($project['tribunal_approved_at']??null)],['label'=>'Publicación','value'=>$dateLabel($project['published_at']??null)],
    ],static fn(array $row):bool=>$row['value']!==''));
    $publishedDescription=trim((string)($project['summary']??''));
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
    if($publicContext||$isAcademicManagement){
        $participantTutor=array_values(array_filter($academicTeam,static fn(array $row):bool=>$row['role_code']==='tutor'))[0]??null;
        $richTribunal=$isDegreeProject?$tribunal:[];
        $usableEmail=static function(mixed $value):string{$email=trim((string)$value);$lower=mb_strtolower($email,'UTF-8');return filter_var($email,FILTER_VALIDATE_EMAIL)&&!str_ends_with($lower,'.invalid')?$email:'';};
        $person=static function(array $row,string $role,bool $includeEmail=false)use($usableEmail):array{return [
            'user_id'=>(int)($row['user_id']??0),'username'=>trim((string)($row['username']??'')),
            'name'=>trim((string)($row['full_name']??'')),'role'=>$role,
            'initial'=>mb_strtoupper(mb_substr(trim((string)($row['full_name']??'U')),0,1,'UTF-8'),'UTF-8'),
            'email'=>$includeEmail?$usableEmail($row['email']??''):'','avatar_url'=>'',
        ];};
        $richAuthors=array_map(static function(array $row)use($person):array{return $person($row,'Estudiante')+['leader'=>!empty($row['is_display_leader'])];},$students);
        $richTeachingParticipants=$publicContext
            ?array_values(array_filter($project['participants'],static fn(array $row):bool=>in_array((string)$row['role_code'],['tutor','cotutor'],true)||(!empty($row['is_teacher'])&&!in_array((string)$row['role_code'],['student','tribunal','jury'],true))))
            :$academicTeam;
        $tutoringByUser=[];
        foreach($richTeachingParticipants as $member){$role=$isAcademicManagement?'Tutor':($roleLabels[$member['role_code']]??ucfirst(str_replace('_',' ',(string)$member['role_code'])));$tutoringByUser[(int)$member['user_id']]=$person($member,$role,true);}
        $mainTutorId=(int)($project['tutor_user_id']??0);
        if($mainTutorId>0&&!isset($tutoringByUser[$mainTutorId]))$tutoringByUser[$mainTutorId]=$person(['user_id'=>$mainTutorId,'username'=>$project['tutor_username']??'','full_name'=>$project['tutor_name']??'','email'=>$project['tutor_email']??''],'Tutor',true);
        $richTutoring=array_values($tutoringByUser);
        $richTribunalMembers=array_map(static fn(array $row):array=>$person($row,$roleLabels[$row['role_code']]??'Miembro del tribunal',true),$richTribunal);
        $richAcademicFields=array_values(array_filter([
            $publicContext?['key'=>'code','icon'=>'fa-hashtag','label'=>'Código','value'=>(string)$project['code']]:null,
            ['key'=>'type','icon'=>'fa-folder-tree','label'=>'Tipo de proyecto','value'=>(string)$project['type_name']],
            ['key'=>'career','icon'=>'fa-graduation-cap','label'=>'Carrera','value'=>(string)$project['career_name']],
            ['key'=>'period','icon'=>'fa-calendar-days','label'=>'Período académico','value'=>(string)$project['period_name']],
            ['key'=>'research','icon'=>'fa-microscope','label'=>'Línea de investigación','value'=>(string)($project['research_line_name']??'')],
            ['key'=>'status','icon'=>'fa-circle-check','label'=>'Estado','value'=>$statusLabel],
            ['key'=>'stage','icon'=>'fa-flag-checkered','label'=>'Etapa académica','value'=>$stageLabel],
            $isAcademicManagement?['key'=>'registration','icon'=>'fa-calendar-plus','label'=>'Fecha de registro','value'=>$dateLabel($project['created_at']??null)]:null,
            $publicContext?['key'=>'completion','icon'=>'fa-calendar-check','label'=>'Fecha de finalización','value'=>$dateLabel($project['academic_completed_at']??null)]:null,
            $publicContext?['key'=>'publication','icon'=>'fa-building-columns','label'=>'Fecha de publicación','value'=>$dateLabel($project['repository_published_at']??null)]:null,
        ],static fn(?array $row):bool=>$row!==null&&$row['value']!==''));
        $informationSections=[
            ['id'=>'description','title'=>'Descripción del proyecto','icon'=>'fa-align-left','type'=>'prose','content'=>$publishedDescription!==''?$publishedDescription:($publicContext?'Este proyecto aún no cuenta con una descripción pública.':'Este proyecto aún no cuenta con una descripción registrada.')],
            ['id'=>'institutional','title'=>'Información académica','icon'=>'fa-building-columns','type'=>'metadata','content'=>$richAcademicFields],
            ['id'=>'participants','title'=>'Participantes','icon'=>'fa-users','type'=>'project_participants','content'=>[
                'authors'=>$richAuthors,'tutoring'=>$richTutoring,'tribunal'=>$richTribunalMembers,
                'show_tribunal'=>$isAcademicManagement&&$isDegreeProject,
            ]],
            ['id'=>'project-classification','title'=>'Clasificación','icon'=>'fa-tags','type'=>'project_tags','content'=>array_map(static fn(array $keyword):string=>(string)$keyword['name'],(array)($project['keywords']??[]))],
        ];
    }
    $actions=[];
    if (!empty($projectCapabilities['edit_information'])) $actions[]=['id'=>'edit','label'=>'Editar','kind'=>'primary','icon'=>'fa-pen-to-square','enabled'=>true,'trigger'=>'project-editor'];
    elseif (!$publicContext && !empty($projectCapabilities['register_delivery']) && $canDeliver) $actions[]=['id'=>'delivery','label'=>'Registrar entrega','kind'=>'primary','icon'=>'fa-upload','url'=>$detailUrl.'&tab=review','enabled'=>true];
    if (!$isAcademicManagement&&!empty($projectCapabilities['download_files'])&&!empty($headerPackage['available'])) $actions[]=['id'=>'download','label'=>'Descargar','kind'=>'secondary','icon'=>'fa-download','icon_style'=>'fa-solid','url'=>(string)$headerPackage['download_url'].($publicContext?'&scope=repository':''),'enabled'=>true,'download'=>true];
    if($isAcademicManagement&&(string)$project['status']==='published')$actions[]=['id'=>'repository','label'=>'Ver en Repositorio','kind'=>'secondary','icon'=>'fa-book-open','url'=>route('repository-detail').'&id='.$projectId,'enabled'=>true];
    $statusActionCount=$isAcademicManagement&&!empty($projectCapabilities['change_status'])?count($projectStatusTransitions):0;
    foreach($statusActionCount>0?$projectStatusTransitions:[] as $transition)$actions[]=['id'=>'status-'.$transition['target'],'label'=>(string)$transition['label'],'kind'=>'secondary','icon'=>(string)$transition['icon'],'icon_style'=>'fa-solid','enabled'=>true,'trigger'=>'status-transition','transition'=>$transition];
    $menuActions=[];
    if($publicContext&&!empty($projectCapabilities['manage_publication'])){
        $menuActions[]=['label'=>$project['is_available']?'Marcar como no disponible':'Marcar como disponible','icon'=>$project['is_available']?'fa-ban':'fa-circle-check','enabled'=>true,'action'=>'availability'];
        $menuActions[]=['label'=>'Retirar publicación','icon'=>'fa-box-archive','enabled'=>true,'action'=>'publication'];
    }
    if($publicContext&&!empty($projectCapabilities['view_admin_history']))$menuActions[]=['label'=>'Ver historial administrativo','icon'=>'fa-clock-rotate-left','enabled'=>true,'action'=>'admin-history','separator'=>$menuActions!==[]];
    if($publicContext&&!empty($projectCapabilities['edit_information']))$menuActions[]=['label'=>'Enviar a Papelera','icon'=>'fa-trash-can','enabled'=>true,'action'=>'trash','danger'=>true,'separator'=>$menuActions!==[]];
    $breadcrumbs = $isAcademicManagement ? [
        ['label'=>'Proyectos activos','url'=>$returnUrl],
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
        ['key'=>'code','label'=>'Código','value'=>trim((string)$project['code'])?:'Sin código','icon'=>'fa-hashtag'],
        ['key'=>'period','label'=>'Período académico','value'=>trim((string)$project['period_name'])?:'Sin período registrado','icon'=>'fa-calendar-days'],
        ['key'=>'registration','label'=>'Fecha de inicio','value'=>$dateLabel($project['created_at']??null)?:'Sin fecha registrada','icon'=>'fa-calendar-plus'],
        ['key'=>'tutor','label'=>'Tutor','value'=>trim((string)($project['tutor_name']??''))?:'Sin tutor asignado','icon'=>'fa-chalkboard-user'],
        ['key'=>'status','label'=>'Estado','value'=>$statusLabel,'icon'=>'fa-circle-check'],
        ['key'=>'stage','label'=>'Etapa académica','value'=>$stageLabel,'icon'=>'fa-flag-checkered'],
        ['key'=>'updated','label'=>'Última actualización','value'=>$dateLabel($project['updated_at']??null)?:'Sin actualización registrada','icon'=>'fa-clock'],
    ];
    $headerMetadata = (string)$project['status']==='published'
        ? $publishedHeaderMetadata
        : ($isAcademicManagement ? $academicManagementHeaderMetadata : [
            ['label'=>'Código','value'=>(string)$project['code']],['label'=>'Carrera','value'=>(string)$project['career_name']],['label'=>'Período académico','value'=>(string)$project['period_name']],['label'=>'Tutor','value'=>(string)($project['tutor_name']??'')],['label'=>'Integrantes','value'=>count($students).''],['label'=>'Registro','value'=>$dateLabel($project['created_at']??null)],['label'=>'Disponibilidad','value'=>$project['is_available']?'Disponible':'No disponible'],
        ]);
    $digitalRecord=['entity'=>['type'=>'project','id'=>$projectId,'query_key'=>'project_id'],'context'=>$projectContext,'mode'=>'view','return_url'=>$returnUrl,
        'capabilities'=>$projectCapabilities,
        'breadcrumbs'=>$breadcrumbs,
        'header'=>['title'=>(string)$project['title'],'description'=>(string)($project['subtitle']??''),'type_label'=>(string)$project['type_name'],'type_icon'=>$publicContext?'fa-folder-tree':null,'status_label'=>$statusLabel,'status_tone'=>$project['status']==='published'?'success':'neutral'],
        'metadata'=>array_values(array_filter($headerMetadata,static fn(?array $row):bool=>$row!==null&&$row['value']!=='')),
        'actions'=>$actions,'menu_actions'=>$menuActions,'tabs'=>$tabs,'active_tab'=>$activeTab,'information_sections'=>$informationSections,
        'documents'=>$documents,'archives'=>$archives,'versions'=>$versions,
        'can_manage_files'=>!empty($projectCapabilities['manage_files'])&&($publicContext||$isAcademicManagement)&&!empty($projectDocuments),
        'restorable_files'=>(array)($projectDocuments['restorable']??[]),
        'file_upload'=>!empty($projectDocuments)?['context'=>(string)($projectDocuments['context']??$projectContext),'endpoint'=>(string)$projectDocuments['endpoint'],'csrf_token'=>(string)$projectDocuments['csrf'],'limits'=>(array)$projectDocuments['limits']]:[],
        'package'=>(array)($projectDocuments['package']??[]),
        'global_file_actions'=>[],
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
        'review_notice'=>!empty($project['review_situation']['has_pending_observations'])?['message'=>'Este proyecto tiene observaciones pendientes de la última revisión.','count'=>(int)$project['review_situation']['pending_observations_count'],'date'=>$project['review_situation']['latest_corrections_requested_at']??null]:null,
        'return_label'=>$publicContext?'Volver al repositorio':($isAcademicManagement?'Volver a proyectos activos':'Volver a proyectos')];
    if($publicContext||$isAcademicManagement): ?><style>
    .digital-record[data-entity-type="project"] .ed-information{grid-template-columns:minmax(0,2fr) minmax(260px,1fr);gap:14px;align-items:start}
    .digital-record[data-entity-type="project"] .ed-document-section{padding:19px}
    .digital-record[data-entity-type="project"] .ed-document-section[data-information-section="description"]{grid-column:1/-1}
    .digital-record[data-entity-type="project"] .ed-document-section[data-information-section="description"]{padding:20px;border-top:3px solid var(--primary)}
    .digital-record[data-entity-type="project"] .ed-document-section-header{margin-bottom:13px;padding-bottom:11px}
    .digital-record[data-entity-type="project"] .ed-document-section[data-information-section="description"] .ed-prose{max-width:82ch;font-size:14px;line-height:1.72;overflow-wrap:anywhere}
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
    .digital-record[data-record-context="academic_management"] .ed-header{container:project-admin-header/inline-size}
    .digital-record[data-record-context="academic_management"] .ed-meta>div{min-width:0}
    .digital-record[data-record-context="academic_management"] .ed-meta :is(dt,dd){word-break:normal;overflow-wrap:break-word}
    .digital-record[data-record-context="academic_management"] .ed-meta :is([data-record-meta="code"],[data-record-meta="registration"],[data-record-meta="updated"]) dd{overflow-wrap:normal}
    @container project-admin-header (min-width:1050px){.digital-record[data-record-context="academic_management"] .ed-meta{grid-template-columns:repeat(7,minmax(0,1fr))}.digital-record[data-record-context="academic_management"] .ed-meta>div{grid-column:auto}.digital-record[data-record-context="academic_management"][data-record-status="published"] .ed-meta{grid-template-columns:repeat(5,minmax(0,1fr))}}
    @container project-admin-header (min-width:760px) and (max-width:1049.98px){.digital-record[data-record-context="academic_management"] .ed-meta{grid-template-columns:repeat(8,minmax(0,1fr))}.digital-record[data-record-context="academic_management"] .ed-meta>div{grid-column:span 2}.digital-record[data-record-context="academic_management"] .ed-meta>div:nth-child(5){grid-column:2/span 2}.digital-record[data-record-context="academic_management"][data-record-status="published"] .ed-meta{grid-template-columns:repeat(5,minmax(0,1fr))}.digital-record[data-record-context="academic_management"][data-record-status="published"] .ed-meta>div{grid-column:auto}}
    @container project-admin-header (min-width:540px) and (max-width:759.98px){.digital-record[data-record-context="academic_management"] .ed-meta{grid-template-columns:repeat(6,minmax(0,1fr))}.digital-record[data-record-context="academic_management"] .ed-meta>div:nth-child(-n+3){grid-column:span 2}.digital-record[data-record-context="academic_management"] .ed-meta>div:nth-child(n+4){grid-column:span 3}}
    @container project-admin-header (min-width:420px) and (max-width:539.98px){.digital-record[data-record-context="academic_management"] .ed-meta{grid-template-columns:repeat(4,minmax(0,1fr))}.digital-record[data-record-context="academic_management"] .ed-meta>div{grid-column:span 2}.digital-record[data-record-context="academic_management"] .ed-meta>div:last-child{grid-column:2/span 2}}
    @container project-admin-header (max-width:419.98px){.digital-record[data-record-context="academic_management"] .ed-meta{grid-template-columns:1fr}.digital-record[data-record-context="academic_management"] .ed-meta>div{grid-column:auto}}
    @media(max-width:800px){.digital-record[data-entity-type="project"] .ed-information{grid-template-columns:1fr}}
    </style><?php endif;
    require __DIR__.'/../repository/_ficha-institucional.php';
    if(!empty($digitalRecord['status_transition']['enabled'])) require __DIR__.'/../repository/_project-status-transition-dialog.php';
    if($publicContext):?><style>@media(min-width:801px){.digital-record[data-entity-type="project"][data-record-context="repository"] .ed-information{grid-template-columns:minmax(0,2fr) minmax(260px,1fr)}.digital-record[data-entity-type="project"][data-record-context="repository"] .ed-document-section[data-information-section="description"]{grid-column:1/-1}}</style><?php endif;
    if(!$publicContext&&!empty($descriptionReminder)) require __DIR__.'/_description-reminder.php';
    if($publicContext&&!empty($projectCapabilities['edit_information'])&&!empty($projectEditorCatalogs)){$projectEditorOnly=true;$catalogs=$projectEditorCatalogs;$projectCsrf=$projectTrashCsrf;$projectEndpoints=['save'=>$projectSaveEndpoint,'trash'=>$projectTrashEndpoint];$projectEditorPayload=array_merge($project,['status_label'=>$statusLabel,'stage_label'=>$stageLabel,'presentation_files'=>array_values(array_map(static fn(array $file):array=>['id'=>(int)$file['id'],'name'=>(string)$file['original_name'],'extension'=>(string)$file['extension'],'format'=>strtoupper((string)$file['extension']),'icon'=>'fa-regular fa-file','size'=>ArchiveService::formatBytes((int)$file['size_bytes'])],$project['files']))]);require __DIR__.'/../admin/projects.php';}
endif;
