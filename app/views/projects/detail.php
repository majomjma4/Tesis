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
    $students = array_values(array_filter($project['participants'], static fn (array $row): bool => $row['role_code'] === 'student'));
    $academicTeam = array_values(array_filter($project['participants'], static fn (array $row): bool => in_array($row['role_code'], ['tutor','cotutor'], true)));
    $tribunal = array_values(array_filter($project['participants'], static fn (array $row): bool => in_array($row['role_code'], ['tribunal','jury'], true)));
    $participantRows = static function (array $rows) use ($roleLabels, $dateLabel): array {
        return array_map(static fn (array $row): array => [
            'label' => ($roleLabels[$row['role_code']] ?? ucfirst((string) $row['role_code'])) . (!empty($row['is_leader']) ? ' líder' : ''),
            'value' => (string) $row['full_name'],
        ], $rows);
    };
    $previewTypes = ['pdf'=>'pdf','docx'=>'docx','txt'=>'text','png'=>'image','jpg'=>'image','jpeg'=>'image','webp'=>'image'];
    $documents = array_map(static function (array $file) use ($projectId, $previewActionUrl, $downloadActionUrl, $previewTypes): array {
        $extension = strtolower((string) $file['extension']);
        $query = '&project_id=' . $projectId . '&file_id=' . (int) $file['id'];
        return ['id'=>(int)$file['id'],'name'=>(string)$file['original_name'],'type'=>strtoupper($extension ?: 'FILE'),
            'size'=>ArchiveService::formatBytes((int)$file['size_bytes']),'sort_order'=>(int)$file['id'],'extension'=>$extension,'available'=>true,
            'is_presentation'=>(int)($project['presentation_file_id'] ?? 0)===(int)$file['id'],'is_package'=>false,
            'preview_supported'=>isset($previewTypes[$extension]),'preview_type'=>$previewTypes[$extension] ?? 'unsupported',
            'preview_url'=>$previewActionUrl.$query,'download_url'=>$downloadActionUrl.$query];
    }, $project['files']);
    $archives = array_values(array_filter($documents, static fn(array $file): bool => $file['extension']==='zip'));
    $documents = array_values(array_filter($documents, static fn(array $file): bool => $file['extension']!=='zip'));
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
    $tabs = [
        ['id'=>'information','label'=>'Información','icon'=>'fa-file-lines'],['id'=>'files','label'=>'Documentos','icon'=>'fa-folder-open'],
        ['id'=>'evolution','label'=>'Evolución documental','icon'=>'fa-clock-rotate-left'],
    ];
    foreach ($tabs as &$tabItem) $tabItem['url']=$detailUrl.'&tab='.$tabItem['id']; unset($tabItem);
    $allowedTabs=array_column($tabs,'id'); $activeTab=in_array($activeTab,$allowedTabs,true)?$activeTab:'information';
    $importantDates=array_values(array_filter([
        ['label'=>'Registro','value'=>$dateLabel($project['created_at']??null)],['label'=>'Aprobación','value'=>$dateLabel($project['approved_at']??null)],
        ['label'=>'Aprobación del tribunal','value'=>$dateLabel($project['tribunal_approved_at']??null)],['label'=>'Publicación','value'=>$dateLabel($project['published_at']??null)],
    ],static fn(array $row):bool=>$row['value']!==''));
    $informationSections = [
        ['id'=>'description','title'=>'Resumen del proyecto','icon'=>'fa-align-left','type'=>'prose','content'=>(string)($project['summary'] ?: $project['subtitle'] ?: 'Este expediente no registra un resumen institucional.')],
        ['id'=>'institutional','title'=>'Información académica','icon'=>'fa-building-columns','type'=>'metadata','content'=>array_values(array_filter([
            ['label'=>'Código','value'=>(string)$project['code']],['label'=>'Tipo de proyecto','value'=>(string)$project['type_name']],['label'=>'Carrera','value'=>(string)$project['career_name']],
            ['label'=>'Período académico','value'=>(string)$project['period_name']],['label'=>'Estado','value'=>$statusLabel],['label'=>'Etapa académica','value'=>$stageLabel],
            ['label'=>'Asignatura académica','value'=>trim((string)($project['subject_code']??'').' · '.(string)($project['subject_name']??''),' ·')],['label'=>'Línea de investigación','value'=>(string)($project['research_line_name']??'')],
            ['label'=>'Fecha de registro','value'=>$dateLabel($project['created_at']??null)],['label'=>'Fecha de aprobación','value'=>$dateLabel($project['approved_at']??null)],['label'=>'Fecha de publicación','value'=>$dateLabel($project['published_at']??null)],
        ],static fn(array $row):bool=>$row['value']!==''))],
        ['id'=>'participants','title'=>'Participantes','icon'=>'fa-users','type'=>'metadata','content'=>array_merge($participantRows($students),$participantRows($academicTeam),$participantRows($tribunal))],
        ['id'=>'dates','title'=>'Fechas importantes','icon'=>'fa-calendar-days','type'=>'metadata','content'=>$importantDates],
    ];
    $actions=[];
    if ($isAdministrator) $actions[]=['id'=>'edit','label'=>'Editar proyecto','kind'=>'primary','icon'=>'fa-pen-to-square','enabled'=>true,'trigger'=>'project-editor'];
    elseif (!$publicContext && $canDeliver) $actions[]=['id'=>'delivery','label'=>'Registrar entrega','kind'=>'primary','icon'=>'fa-upload','url'=>$detailUrl.'&tab=review','enabled'=>true];
    if ($project['files']) $actions[]=['id'=>'download','label'=>'Descargar','kind'=>'secondary','icon'=>'fa-download','icon_style'=>'fa-solid','url'=>$downloadActionUrl.'&project_id='.$projectId.'&file_id='.(int)$project['files'][0]['id'],'enabled'=>true,'download'=>true];
    $menuActions=$isAdministrator&&$publicContext?[
        ['label'=>$project['is_available']?'Marcar como no disponible':'Marcar como disponible','icon'=>$project['is_available']?'fa-ban':'fa-circle-check','enabled'=>true,'action'=>'availability'],
        ['label'=>'Retirar publicación','icon'=>'fa-box-archive','enabled'=>true,'action'=>'publication'],
        ['label'=>'Ver historial administrativo','icon'=>'fa-clock-rotate-left','enabled'=>true,'action'=>'project-history','separator'=>true],
        ['label'=>'Enviar a Papelera','icon'=>'fa-trash-can','enabled'=>true,'action'=>'trash','danger'=>true,'separator'=>true],
    ]:[];
    $adminHistory=[];$academicHistory=[];
    if($publicContext){
        $adminLabels=['project_updated'=>'Información del proyecto actualizada','project_availability_changed'=>'Disponibilidad del proyecto actualizada','project_unpublished'=>'Publicación retirada','project_republished'=>'Publicación restaurada','project_trashed'=>'Proyecto enviado a Papelera','project_restored'=>'Proyecto restaurado','project.presentation_selected'=>'Archivo principal seleccionado','project.presentation_changed'=>'Archivo principal actualizado','project.presentation_removed'=>'Archivo principal retirado','project_file_replaced'=>'Archivo reemplazado','project_file_removed'=>'Archivo retirado'];
        foreach($project['activity'] as $event){$action=(string)$event['action'];if(!isset($adminLabels[$action]))continue;$detail=trim((string)($event['reason']??''));$state=json_decode((string)($event['new_state']??''),true);$changes=is_array($state)?($state['_history_changes']??[]):[];if($detail===''&&is_array($changes)&&$changes)$detail=implode('; ',array_map(static fn(array $change):string=>(string)($change['field']??'Dato').' de '.(string)($change['from']??'sin asignar').' a '.(string)($change['to']??'sin asignar'),$changes));$adminHistory[]=['title'=>$adminLabels[$action],'detail'=>$detail,'actor'=>(string)($event['actor_name']?:'Administración institucional'),'date'=>(string)$event['created_at']];}
        $academicHistory[]=['title'=>'Proyecto registrado','detail'=>'Se creó el expediente académico del proyecto.','actor'=>'Sistema académico','date'=>(string)$project['created_at']];
        foreach($project['deliveries'] as $event)$academicHistory[]=['title'=>'Entrega realizada · versión '.(int)$event['version_number'],'detail'=>(string)($event['comment']?:$event['title']),'actor'=>(string)$event['author_name'],'date'=>(string)$event['submitted_at']];
        foreach($project['observations'] as $event)$academicHistory[]=['title'=>'Observación académica · '.(string)$event['category'],'detail'=>(string)$event['body'],'actor'=>(string)$event['author_name'],'date'=>(string)$event['created_at']];
        foreach($project['responses'] as $event)$academicHistory[]=['title'=>'Respuesta a observación','detail'=>(string)$event['body'],'actor'=>(string)$event['author_name'],'date'=>(string)$event['created_at']];
        foreach($project['stages'] as $event)if($event['status']==='completed'&&!empty($event['completed_at']))$academicHistory[]=['title'=>'Etapa completada · '.(string)$event['label'],'detail'=>'La etapa académica fue completada.','actor'=>'Sistema académico','date'=>(string)$event['completed_at']];
        foreach($project['activity'] as $event){$label=['project_approved'=>'Proyecto aprobado','project_tribunal_approved'=>'Proyecto aprobado por el tribunal','tribunal_approved'=>'Proyecto aprobado por el tribunal','project_published'=>'Proyecto publicado','delivery_submitted'=>'Entrega registrada'][(string)$event['action']]??null;if($label)$academicHistory[]=['title'=>$label,'detail'=>(string)($event['reason']??''),'actor'=>(string)($event['actor_name']?:'Sistema académico'),'date'=>(string)$event['created_at']];}
        usort($adminHistory,static fn(array $a,array $b):int=>strcmp($b['date'],$a['date']));usort($academicHistory,static fn(array $a,array $b):int=>strcmp($b['date'],$a['date']));
    }
    $digitalRecord=['entity'=>['type'=>'project','id'=>$projectId,'query_key'=>'project_id'],'context'=>$publicContext?'repository':'academic','mode'=>'view','return_url'=>$returnUrl,
        'breadcrumbs'=>[['label'=>$publicContext?'Repositorio':'Proyectos','url'=>$returnUrl],['label'=>(string)$project['code'],'url'=>null]],
        'header'=>['title'=>(string)$project['title'],'description'=>(string)($project['subtitle']??''),'type_label'=>(string)$project['type_name'],'status_label'=>$statusLabel,'status_tone'=>$project['status']==='published'?'success':'neutral'],
        'metadata'=>array_values(array_filter([['label'=>'Código','value'=>(string)$project['code']],['label'=>'Carrera','value'=>(string)$project['career_name']],['label'=>'Período académico','value'=>(string)$project['period_name']],['label'=>'Tutor','value'=>(string)($project['tutor_name']??'')],['label'=>'Integrantes','value'=>count($students).''],['label'=>'Registro','value'=>$dateLabel($project['created_at']??null)],['label'=>'Disponibilidad','value'=>$project['is_available']?'Disponible':'No disponible']],static fn(array $row):bool=>$row['value']!=='')),
        'actions'=>$actions,'menu_actions'=>$menuActions,'tabs'=>$tabs,'active_tab'=>$activeTab,'information_sections'=>$informationSections,
        'documents'=>$documents,'archives'=>$archives,'versions'=>$versions,'can_manage_files'=>false,'package'=>[],
        'global_file_actions'=>array_map(static fn(array $file):array=>['label'=>'Descargar '.$file['name'],'icon'=>'fa-download','url'=>$file['download_url'],'download'=>true],array_merge($documents,$archives)),
        'project_histories'=>['administrative'=>$adminHistory,'academic'=>$academicHistory],
        'endpoints'=>['preview'=>$previewActionUrl,'download'=>$downloadActionUrl], 'version_endpoints'=>['preview'=>$previewActionUrl,'download'=>$downloadActionUrl],
        'admin_actions'=>['endpoint'=>(string)($projectAdminEndpoint??''),'trash_endpoint'=>(string)($projectTrashEndpoint??''),'csrf_token'=>(string)($projectAdminCsrf??''),'trash_csrf_token'=>(string)($projectTrashCsrf??''),'status'=>'published','is_available'=>!empty($project['is_available']),'redirect'=>$returnUrl],
        'return_label'=>$publicContext?'Volver al repositorio':'Volver a proyectos'];
    if($publicContext): ?><style>
    .digital-record[data-entity-type="project"] .ed-information{grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
    .digital-record[data-entity-type="project"] .ed-document-section{padding:19px}
    .digital-record[data-entity-type="project"] .ed-document-section[data-information-section="description"],.digital-record[data-entity-type="project"] .ed-document-section[data-information-section="institutional"]{grid-column:1/-1}
    .digital-record[data-entity-type="project"] .ed-document-section[data-information-section="description"]{padding:20px}
    .digital-record[data-entity-type="project"] .ed-document-section-header{margin-bottom:13px;padding-bottom:11px}
    .digital-record[data-entity-type="project"] .ed-prose{max-width:none;line-height:1.65}
    @media(max-width:800px){.digital-record[data-entity-type="project"] .ed-information{grid-template-columns:1fr}.digital-record[data-entity-type="project"] .ed-document-section[data-information-section="institutional"]{grid-column:auto}}
    </style><?php endif;
    require __DIR__.'/../repository/_ficha-institucional.php';
    if($publicContext&&$isAdministrator&&!empty($projectEditorCatalogs)){$projectEditorOnly=true;$catalogs=$projectEditorCatalogs;$projectCsrf=$projectTrashCsrf;$projectEndpoints=['save'=>$projectSaveEndpoint,'trash'=>$projectTrashEndpoint];$projectEditorPayload=array_merge($project,['presentation_files'=>array_values(array_map(static fn(array $file):array=>['id'=>(int)$file['id'],'name'=>(string)$file['original_name'],'extension'=>(string)$file['extension'],'format'=>strtoupper((string)$file['extension']),'icon'=>'fa-regular fa-file','size'=>ArchiveService::formatBytes((int)$file['size_bytes'])],$project['files']))]);require __DIR__.'/../admin/projects.php';}
endif;
