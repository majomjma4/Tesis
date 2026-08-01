<?php
if ($project === null): ?>
    <section class="repository-detail-not-found"><i class="fa-solid fa-folder-open"></i><h1>Proyecto no encontrado</h1><p>El expediente solicitado no existe o no está disponible para tu cuenta.</p><a class="open-btn" href="<?= e($returnUrl) ?>">Volver</a></section>
<?php else:
    $projectId = (int) $project['id'];
    $detailUrl = (string) $detailUrl;
    $statusLabels = ['development'=>'En desarrollo','under_review'=>'En revisión','changes_required'=>'Requiere cambios','approved'=>'Aprobado','defense'=>'En tribunal','tribunal_approved'=>'Aprobado por el Tribunal','published'=>'Publicado'];
    $statusLabel = $publicContext ? 'Publicado' : ($statusLabels[(string) $project['status']] ?? (string) $project['status']);
    $isDegreeProject = in_array(mb_strtolower((string)($project['type_code'] ?? ''),'UTF-8'), ['thesis','tesis','degree','titulacion','titulación'], true)
        || str_contains(mb_strtolower((string)($project['type_name'] ?? ''),'UTF-8'), 'titul');
    $stageLabel = $publicContext ? 'Finalizado' : match ((string)$project['status']) {
        'development' => 'En desarrollo',
        'under_review', 'changes_required' => 'Revisión académica',
        'approved' => $isDegreeProject ? 'Preparación para tribunal' : 'Por publicar',
        'defense' => 'Defensa',
        'tribunal_approved' => 'Por publicar',
        'published' => 'Finalizado',
        default => 'Etapa no definida',
    };
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
    $documents = array_map(static function (array $file) use ($projectId, $previewActionUrl, $downloadActionUrl, $previewTypes): array {
        $extension = strtolower((string) $file['extension']);
        $query = '&project_id=' . $projectId . '&file_id=' . (int) $file['id'];
        return ['id'=>(int)$file['id'],'name'=>(string)$file['original_name'],'type'=>strtoupper($extension ?: 'FILE'),'mime_type'=>(string)($file['mime_type']??''),
            'size'=>ArchiveService::formatBytes((int)$file['size_bytes']),'sort_order'=>(int)($file['sort_order']??$file['id']),'extension'=>$extension,'available'=>true,
            'is_presentation'=>(int)($project['presentation_file_id'] ?? 0)===(int)$file['id'],'is_package'=>false,
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
    $tabs = [
        ['id'=>'information','label'=>'Información','icon'=>'fa-file-lines'],['id'=>'files','label'=>'Documentos','icon'=>'fa-folder-open'],
        ['id'=>'evolution','label'=>'Historial','icon'=>'fa-clock-rotate-left'],
    ];
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
            ['label'=>'Asignatura académica','value'=>trim((string)($project['subject_code']??'').' · '.(string)($project['subject_name']??''),' ·')],['label'=>'Línea de investigación','value'=>(string)($project['research_line_name']??'')],
            ['label'=>'Fecha de registro','value'=>$dateLabel($project['created_at']??null)],['label'=>'Fecha de aprobación','value'=>$dateLabel($project['approved_at']??null)],['label'=>'Fecha de publicación','value'=>$dateLabel($project['published_at']??null)],
        ],static fn(array $row):bool=>$row['value']!==''))],
        ['id'=>'participants','title'=>'Participantes','icon'=>'fa-users','type'=>'metadata','content'=>array_merge($participantRows($students),$participantRows($academicTeam),$participantRows($tribunal))],
        ['id'=>'dates','title'=>'Fechas importantes','icon'=>'fa-calendar-days','type'=>'metadata','content'=>$importantDates],
    ];
    if($publicContext){
        $participantTutor=array_values(array_filter($academicTeam,static fn(array $row):bool=>$row['role_code']==='tutor'))[0]??null;
        $cotutors=array_values(array_filter($academicTeam,static fn(array $row):bool=>$row['role_code']==='cotutor'));
        $tutorName=trim((string)($project['tutor_name']??($participantTutor['full_name']??'')));
        $publicTribunal=$isDegreeProject?$tribunal:[];
        $usableEmail=static function(mixed $value):string{$email=trim((string)$value);$lower=mb_strtolower($email,'UTF-8');return filter_var($email,FILTER_VALIDATE_EMAIL)&&!str_ends_with($lower,'.invalid')?$email:'';};
        $person=static function(array $row,string $role,bool $includeEmail=false)use($usableEmail):array{return [
            'user_id'=>(int)($row['user_id']??0),'username'=>trim((string)($row['username']??'')),
            'name'=>trim((string)($row['full_name']??'')),'role'=>$role,
            'initial'=>mb_strtoupper(mb_substr(trim((string)($row['full_name']??'U')),0,1,'UTF-8'),'UTF-8'),
            'email'=>$includeEmail?$usableEmail($row['email']??''):'','avatar_url'=>'',
        ];};
        $publicAuthors=array_map(static function(array $row)use($person):array{return $person($row,'Estudiante')+['leader'=>!empty($row['is_display_leader'])];},$students);
        $publicTeachingParticipants=array_values(array_filter($project['participants'],static fn(array $row):bool=>in_array((string)$row['role_code'],['tutor','cotutor'],true)||(!empty($row['is_teacher'])&&!in_array((string)$row['role_code'],['student','tribunal','jury'],true))));
        $tutoringByUser=[];
        foreach($publicTeachingParticipants as $member){$role=$roleLabels[$member['role_code']]??ucfirst(str_replace('_',' ',(string)$member['role_code']));$tutoringByUser[(int)$member['user_id']]=$person($member,$role,true);}
        $mainTutorId=(int)($project['tutor_user_id']??0);
        if($mainTutorId>0&&!isset($tutoringByUser[$mainTutorId]))$tutoringByUser[$mainTutorId]=$person(['user_id'=>$mainTutorId,'username'=>$project['tutor_username']??'','full_name'=>$project['tutor_name']??'','email'=>$project['tutor_email']??''],'Tutor',true);
        $publicTutoring=array_values($tutoringByUser);
        $publicTribunalMembers=array_map(static fn(array $row):array=>$person($row,$roleLabels[$row['role_code']]??'Miembro del tribunal',true),$publicTribunal);
        $informationSections=[
            ['id'=>'description','title'=>'Descripción del proyecto','icon'=>'fa-align-left','type'=>'prose','content'=>$publishedDescription!==''?$publishedDescription:'Este proyecto aún no cuenta con una descripción pública.'],
            ['id'=>'institutional','title'=>'Información académica','icon'=>'fa-building-columns','type'=>'metadata','content'=>array_values(array_filter([
                ['key'=>'code','icon'=>'fa-hashtag','label'=>'Código','value'=>(string)$project['code']],['key'=>'type','icon'=>'fa-folder-tree','label'=>'Tipo de proyecto','value'=>(string)$project['type_name']],['key'=>'career','icon'=>'fa-graduation-cap','label'=>'Carrera','value'=>(string)$project['career_name']],
                ['key'=>'subject','icon'=>'fa-book-open','label'=>'Asignatura académica','value'=>trim((string)($project['subject_code']??'').' · '.(string)($project['subject_name']??''),' ·')],['key'=>'period','icon'=>'fa-calendar-days','label'=>'Período académico','value'=>(string)$project['period_name']],
                ['key'=>'research','icon'=>'fa-microscope','label'=>'Línea de investigación','value'=>(string)($project['research_line_name']??'')],['key'=>'status','icon'=>'fa-circle-check','label'=>'Estado','value'=>'Publicado'],['key'=>'stage','icon'=>'fa-flag-checkered','label'=>'Etapa académica','value'=>'Finalizado'],
                ['key'=>'completion','icon'=>'fa-calendar-check','label'=>'Fecha de finalización','value'=>$dateLabel($project['academic_completed_at']??null)],['key'=>'publication','icon'=>'fa-building-columns','label'=>'Fecha de publicación','value'=>$dateLabel($project['repository_published_at']??null)],
            ],static fn(array $row):bool=>$row['value']!==''))],
            ['id'=>'participants','title'=>'Participantes','icon'=>'fa-users','type'=>'project_participants','content'=>[
                'authors'=>$publicAuthors,'tutoring'=>$publicTutoring,'tribunal'=>$publicTribunalMembers,
            ]],
            ['id'=>'project-classification','title'=>'Clasificación','icon'=>'fa-tags','type'=>'project_tags','content'=>array_map(static fn(array $keyword):string=>(string)$keyword['name'],(array)($project['keywords']??[]))],
        ];
    }
    $actions=[];
    if ($isAdministrator) $actions[]=['id'=>'edit','label'=>'Editar proyecto','kind'=>'primary','icon'=>'fa-pen-to-square','enabled'=>true,'trigger'=>'project-editor'];
    elseif (!$publicContext && $canDeliver) $actions[]=['id'=>'delivery','label'=>'Registrar entrega','kind'=>'primary','icon'=>'fa-upload','url'=>$detailUrl.'&tab=review','enabled'=>true];
    if (!empty($headerPackage['available'])) $actions[]=['id'=>'download','label'=>'Descargar','kind'=>'secondary','icon'=>'fa-download','icon_style'=>'fa-solid','url'=>(string)$headerPackage['download_url'].($publicContext?'&scope=repository':''),'enabled'=>true,'download'=>true];
    $menuActions=$isAdministrator&&$publicContext?[
        ['label'=>$project['is_available']?'Marcar como no disponible':'Marcar como disponible','icon'=>$project['is_available']?'fa-ban':'fa-circle-check','enabled'=>true,'action'=>'availability'],
        ['label'=>'Retirar publicación','icon'=>'fa-box-archive','enabled'=>true,'action'=>'publication'],
        ['label'=>'Ver historial administrativo','icon'=>'fa-clock-rotate-left','enabled'=>true,'action'=>'admin-history','separator'=>true],
        ['label'=>'Enviar a Papelera','icon'=>'fa-trash-can','enabled'=>true,'action'=>'trash','danger'=>true,'separator'=>true],
    ]:[];
    $digitalRecord=['entity'=>['type'=>'project','id'=>$projectId,'query_key'=>'project_id'],'context'=>$publicContext?'repository':'academic','mode'=>'view','return_url'=>$returnUrl,
        'breadcrumbs'=>[['label'=>$publicContext?'Repositorio':'Proyectos','url'=>$returnUrl],['label'=>(string)$project['code'],'url'=>null]],
        'header'=>['title'=>(string)$project['title'],'description'=>(string)($project['subtitle']??''),'type_label'=>(string)$project['type_name'],'type_icon'=>$publicContext?'fa-folder-tree':null,'status_label'=>$statusLabel,'status_tone'=>$project['status']==='published'?'success':'neutral'],
        'metadata'=>array_values(array_filter($publicContext?[
            ['key'=>'period','label'=>'Período académico','value'=>(string)$project['period_name'],'icon'=>'fa-calendar-days'],
            ['key'=>'tutor','label'=>'Tutor','value'=>(string)($project['tutor_name']??''),'icon'=>'fa-chalkboard-user'],
            $authorMetadata,
            ['key'=>'registration','label'=>'Registro','value'=>$dateLabel($project['created_at']??null),'icon'=>'fa-calendar-plus'],
            ['key'=>'availability','label'=>'Disponibilidad','value'=>$project['is_available']?'Disponible':'No disponible','icon'=>$project['is_available']?'fa-circle-check':'fa-circle-minus'],
        ]:[
            ['label'=>'Código','value'=>(string)$project['code']],['label'=>'Carrera','value'=>(string)$project['career_name']],['label'=>'Período académico','value'=>(string)$project['period_name']],['label'=>'Tutor','value'=>(string)($project['tutor_name']??'')],['label'=>'Integrantes','value'=>count($students).''],['label'=>'Registro','value'=>$dateLabel($project['created_at']??null)],['label'=>'Disponibilidad','value'=>$project['is_available']?'Disponible':'No disponible']
        ],static fn(?array $row):bool=>$row!==null&&$row['value']!=='')),
        'actions'=>$actions,'menu_actions'=>$menuActions,'tabs'=>$tabs,'active_tab'=>$activeTab,'information_sections'=>$informationSections,
        'documents'=>$documents,'archives'=>$archives,'versions'=>$versions,
        'can_manage_files'=>$publicContext&&$isAdministrator&&!empty($projectDocuments),
        'restorable_files'=>(array)($projectDocuments['restorable']??[]),
        'file_upload'=>!empty($projectDocuments)?['endpoint'=>(string)$projectDocuments['endpoint'],'csrf_token'=>(string)$projectDocuments['csrf'],'limits'=>(array)$projectDocuments['limits']]:[],
        'package'=>(array)($projectDocuments['package']??[]),
        'global_file_actions'=>[],
        'project_histories'=>[
            'academic'=>(array)($project['academic_history']??[]),
            'academic_total'=>(int)($project['academic_history_total']??count((array)($project['academic_history']??[]))),
            'academic_endpoint'=>(string)($academicHistoryEndpoint??''),
            'modifications'=>(array)($project['post_publication_modifications']??[]),
        ],
        'endpoints'=>['preview'=>$previewActionUrl,'download'=>$downloadActionUrl,'admin_history'=>(string)($projectHistoryEndpoint??'')], 'version_endpoints'=>['preview'=>$previewActionUrl,'download'=>$downloadActionUrl],
         'admin_actions'=>['endpoint'=>(string)($projectAdminEndpoint??''),'trash_endpoint'=>(string)($projectTrashEndpoint??''),'csrf_token'=>(string)($projectAdminCsrf??''),'trash_csrf_token'=>(string)($projectTrashCsrf??''),'status'=>'published','is_available'=>!empty($project['is_available']),'redirect'=>$returnUrl],
        'return_label'=>$publicContext?'Volver al repositorio':'Volver a proyectos'];
    if($publicContext): ?><style>
    .digital-record[data-entity-type="project"] .ed-information{grid-template-columns:minmax(0,2fr) minmax(260px,1fr);gap:14px;align-items:start}
    .digital-record[data-entity-type="project"] .ed-document-section{padding:19px}
    .digital-record[data-entity-type="project"] .ed-document-section[data-information-section="description"]{grid-column:1/-1}
    .digital-record[data-entity-type="project"] .ed-document-section[data-information-section="description"]{padding:20px;border-top:3px solid var(--primary)}
    .digital-record[data-entity-type="project"] .ed-document-section-header{margin-bottom:13px;padding-bottom:11px}
    .digital-record[data-entity-type="project"] .ed-document-section[data-information-section="description"] .ed-prose{max-width:82ch;font-size:14px;line-height:1.72;overflow-wrap:anywhere}
    .digital-record[data-entity-type="project"] .ed-document-section[data-information-section="description"] .ed-prose p{margin-bottom:12px}
    @media(max-width:800px){.digital-record[data-entity-type="project"] .ed-information{grid-template-columns:1fr}}
    </style><?php endif;
    require __DIR__.'/../repository/_ficha-institucional.php';
    if($publicContext):?><style>@media(min-width:801px){.digital-record[data-entity-type="project"][data-record-context="repository"] .ed-information{grid-template-columns:minmax(0,2fr) minmax(260px,1fr)}.digital-record[data-entity-type="project"][data-record-context="repository"] .ed-document-section[data-information-section="description"]{grid-column:1/-1}}</style><?php endif;
    if(!$publicContext&&!empty($descriptionReminder)) require __DIR__.'/_description-reminder.php';
    if($publicContext&&$isAdministrator&&!empty($projectEditorCatalogs)){$projectEditorOnly=true;$catalogs=$projectEditorCatalogs;$projectCsrf=$projectTrashCsrf;$projectEndpoints=['save'=>$projectSaveEndpoint,'trash'=>$projectTrashEndpoint];$projectEditorPayload=array_merge($project,['presentation_files'=>array_values(array_map(static fn(array $file):array=>['id'=>(int)$file['id'],'name'=>(string)$file['original_name'],'extension'=>(string)$file['extension'],'format'=>strtoupper((string)$file['extension']),'icon'=>'fa-regular fa-file','size'=>ArchiveService::formatBytes((int)$file['size_bytes'])],$project['files']))]);require __DIR__.'/../admin/projects.php';}
endif;
