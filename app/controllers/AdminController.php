<?php
declare(strict_types=1);

final class AdminController
{
    public function settings():void{$model=new SystemSettingModel();$error=null;try{$settings=$model->all();}catch(Throwable $e){error_log('Admin settings: '.$e->getMessage());$error='No fue posible consultar la configuración.';$settings=$model->defaults();}$s=new AuthSessionService();View::render('admin/settings',['currentPage'=>'admin-settings','title'=>'Configuración | Administración','bodyClass'=>'admin-settings-page','pageStyles'=>[asset('css/admin-settings.css')],'pageScript'=>asset('js/admin-settings.js'),'settings'=>$settings,'settingsError'=>$error,'settingsCsrf'=>$s->csrfToken('admin_settings'),'settingsSaveEndpoint'=>route('admin-settings-save')]);}
    public function saveSettings():void{$this->requirePost();$s=new AuthSessionService();if(!$s->validateCsrf('admin_settings',(string)($_POST['_csrf']??'')))$this->json(false,'La sesión venció.',[],419);try{(new SystemSettingModel())->save($_POST,(int)$s->userId());$this->json(true,'Configuración guardada y aplicada.');}catch(InvalidArgumentException $e){$this->activityFailure($s,'settings_updated','Intentó actualizar la configuración institucional','Configuración','settings',null,'Configuración del sistema',$e);$this->json(false,$e->getMessage(),[],422);}catch(Throwable $e){$this->activityFailure($s,'settings_updated','Intentó actualizar la configuración institucional','Configuración','settings',null,'Configuración del sistema',$e);error_log('Save settings: '.$e->getMessage());$this->json(false,'No fue posible guardar la configuración.',[],500);}}
    public function reports():void{$from=$this->reportDate('from',date('Y-m-01'));$to=$this->reportDate('to',date('Y-m-d'));$model=new AdminReportModel();$error=null;try{$data=$model->dashboard($from,$to,PaginationService::request());}catch(Throwable $e){error_log('Admin reports: '.$e->getMessage());$error='No fue posible generar los reportes.';$data=['summary'=>['users'=>0,'projects'=>0,'deliveries'=>0,'actions'=>0],'roles'=>[],'statuses'=>[],'activity'=>[],'pagination'=>['total'=>0]];}View::render('admin/reports',['currentPage'=>'admin-reports','title'=>'Reportes | Administración','bodyClass'=>'admin-reports-page','pageStyles'=>[asset('css/admin-reports.css')],'reportData'=>$data,'pagePagination'=>$data['pagination'],'reportFrom'=>$from,'reportTo'=>$to,'reportError'=>$error]);}
    public function exportReport():never{$type=(string)($_GET['type']??'');$from=$this->reportDate('from',date('Y-m-01'));$to=$this->reportDate('to',date('Y-m-d'));if(!in_array($type,['users','projects','audit'],true)){http_response_code(422);exit('Reporte no válido.');}try{$report=(new AdminReportModel())->export($type,$from,$to);header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="reporte-'.$type.'-'.$from.'-'.$to.'.csv"');echo "\xEF\xBB\xBF";$out=fopen('php://output','wb');fputcsv($out,$report['headers'],';');foreach($report['rows'] as $row){$safe=array_map(static fn($cell):string=>preg_match('/^[=+\-@]/u',(string)$cell)?"'".(string)$cell:(string)$cell,array_values($row));fputcsv($out,$safe,';');}fclose($out);exit;}catch(Throwable $e){error_log('Export report: '.$e->getMessage());http_response_code(500);exit('No fue posible generar el reporte.');}}
    private function reportDate(string $key,string $fallback):string{$value=(string)($_GET[$key]??$fallback);$date=DateTimeImmutable::createFromFormat('Y-m-d',$value);return $date&&$date->format('Y-m-d')===$value?$value:$fallback;}
    public function trash():void
    {
        $model=new AdminTrashModel();$type=(string)($_GET['trash_type']??'users');$error=null;
        try{
            $data=$type==='materials'
                ?$model->supportMaterialDashboard(PaginationService::request())
                :$model->dashboard($type,PaginationService::request());
            $data['materials']=$data['materials']??[];
            $data['summary']['materials']=(int)Database::connection()->query(
                'SELECT COUNT(*) FROM support_materials WHERE deleted_at IS NOT NULL AND purged_at IS NULL'
            )->fetchColumn();
        }catch(Throwable $e){
            error_log('Admin trash: '.$e->getMessage());$error='No fue posible consultar la Papelera.';
            $data=['users'=>[],'projects'=>[],'materials'=>[],'pagination'=>['total'=>0],'active_type'=>'users','summary'=>['users'=>0,'projects'=>0,'materials'=>0,'expired'=>0]];
        }
        $s=new AuthSessionService();
        View::render('admin/trash',['currentPage'=>'admin-trash','title'=>'Papelera | Administración','bodyClass'=>'admin-trash-page','pageStyles'=>[asset('css/admin-trash.css')],'pageScript'=>asset('js/admin-trash.js'),'trashData'=>$data,'pagePagination'=>$data['pagination'],'trashError'=>$error,'trashCsrf'=>$s->csrfToken('admin_trash'),'trashEndpoints'=>['user'=>route('admin-trash-user'),'restore'=>route('admin-trash-restore'),'purge'=>route('admin-trash-purge')]]);
    }
    public function trashUser():void{$this->requirePost();$s=$this->trashSession();$id=(int)($_POST['id']??0);try{(new AdminTrashModel())->trashUser($id,(string)($_POST['reason']??''),(int)$s->userId());$this->json(true,'Usuario enviado a la Papelera y acceso revocado.');}catch(InvalidArgumentException $e){$this->activityFailure($s,'user_trashed','Intentó enviar un usuario a la papelera','Papelera','user',$id,'Usuario #'.$id,$e);$this->json(false,$e->getMessage(),[],422);}catch(Throwable $e){$this->activityFailure($s,'user_trashed','Intentó enviar un usuario a la papelera','Papelera','user',$id,'Usuario #'.$id,$e);error_log('Trash user: '.$e->getMessage());$this->json(false,'No fue posible eliminar el usuario.',[],500);}}
    public function restoreTrash():void{$this->requirePost();$s=$this->trashSession();$entity=(string)($_POST['entity']??'');$id=(int)($_POST['id']??0);try{$trash=new AdminTrashModel();if($entity==='support_material')$trash->restoreSupportMaterial($id,(int)$s->userId());else $trash->restore($entity,$id,(int)$s->userId());$this->json(true,'Elemento restaurado correctamente.');}catch(InvalidArgumentException $e){$this->activityFailure($s,$entity.'_restored','Intentó restaurar un elemento desde la papelera','Papelera',$entity,$id,ucfirst($entity).' #'.$id,$e);$this->json(false,$e->getMessage(),[],422);}catch(Throwable $e){$this->activityFailure($s,$entity.'_restored','Intentó restaurar un elemento desde la papelera','Papelera',$entity,$id,ucfirst($entity).' #'.$id,$e);$this->json(false,'No fue posible restaurar el elemento.',[],500);}}
    public function purgeTrash():void{$this->requirePost();$s=$this->trashSession();try{$r=(new AdminTrashModel())->purgeExpired((int)$s->userId());$this->json(true,'Se procesaron '.$r['users'].' usuarios y '.$r['projects'].' proyectos vencidos.',$r);}catch(Throwable $e){$this->activityFailure($s,'trash_purged','Intentó ejecutar la eliminación definitiva','Papelera','trash',null,'Elementos vencidos',$e);error_log('Purge trash: '.$e->getMessage());$this->json(false,'No fue posible procesar los elementos vencidos.',[],500);}}
    private function trashSession():AuthSessionService{$s=new AuthSessionService();if(!$s->validateCsrf('admin_trash',(string)($_POST['_csrf']??'')))$this->json(false,'La sesión venció.',[],419);return $s;}
    public function notifications():void{$model=new AdminNotificationModel();$error=null;try{$data=$model->dashboard(PaginationService::request());}catch(Throwable $e){error_log('Admin notifications: '.$e->getMessage());$error='No fue posible consultar el centro de notificaciones.';$data=['users'=>[],'projects'=>[],'sent'=>[],'pagination'=>['total'=>0],'summary'=>['sent'=>0,'recipients'=>0,'today'=>0]];}$s=new AuthSessionService();View::render('admin/notifications',['currentPage'=>'notifications','title'=>'Notificaciones | Administración','bodyClass'=>'admin-notifications-page','pageStyles'=>[asset('css/admin-notifications.css')],'pageScript'=>asset('js/admin-notifications.js'),'adminNotifications'=>$data,'pagePagination'=>$data['pagination'],'adminNotificationsError'=>$error,'adminNotificationCsrf'=>$s->csrfToken('admin_notifications'),'adminNotificationSendEndpoint'=>route('admin-notification-send')]);}
    public function sendNotification():void{$this->requirePost();$s=new AuthSessionService();if(!$s->validateCsrf('admin_notifications',(string)($_POST['_csrf']??'')))$this->json(false,'La sesión venció.',[],419);try{$result=(new AdminNotificationModel())->send($_POST,(int)$s->userId());$this->json(true,'Notificación enviada a '.$result['recipients'].' destinatarios.',$result);}catch(InvalidArgumentException $e){$this->json(false,$e->getMessage(),[],422);}catch(Throwable $e){error_log('Admin send notification: '.$e->getMessage());$this->json(false,'No fue posible enviar la notificación.',[],500);}}
    public function repository():void{$model=new AdminRepositoryModel();$error=null;try{$summary=$model->summary();$catalogRequest=PaginationService::request();$catalogRequest['size']=100;$published=$model->listing('published',$catalogRequest);$projects=$published['items'];$pagination=$published['pagination'];$catalogs=$model->filterCatalogs();$withdrawnPublications=$model->withdrawnPublications();$materialModel=new SupportMaterialModel();$supportMaterials=$materialModel->getAdminMaterials();$withdrawnMaterials=$materialModel->getWithdrawn();$materialCategories=$materialModel->categories();}catch(Throwable $e){error_log('Admin repository: '.$e->getMessage());$error='No fue posible consultar las publicaciones.';$projects=[];$pagination=['total'=>0];$summary=['eligible'=>0,'published'=>0,'incomplete'=>0];$catalogs=['types'=>[],'periods'=>[]];$withdrawnPublications=[];$supportMaterials=[];$withdrawnMaterials=[];$materialCategories=[];}$s=new AuthSessionService();View::render('admin/repository',['currentPage'=>'repository','title'=>'Repositorio | Administración','bodyClass'=>'admin-repository-page','pageStyles'=>[asset('css/admin-repository.css')],'pageScript'=>asset('js/admin-repository.js'),'repositoryProjects'=>$projects,'pagePagination'=>$pagination,'repositorySummary'=>$summary,'repositoryError'=>$error,'repositoryCatalogs'=>$catalogs,'withdrawnPublications'=>$withdrawnPublications,'supportMaterials'=>$supportMaterials,'withdrawnMaterials'=>$withdrawnMaterials,'materialCategories'=>$materialCategories,'repositoryCsrf'=>$s->csrfToken('admin_repository'),'repositoryPublishEndpoint'=>route('admin-repository-publish'),'materialSaveEndpoint'=>route('admin-support-material-save'),'materialStatusEndpoint'=>route('admin-support-material-status'),'materialFileEndpoint'=>route('admin-support-material-file')]);}
    public function publishProject():void{$this->requirePost();$s=new AuthSessionService();if(!$s->validateCsrf('admin_repository',(string)($_POST['_csrf']??'')))$this->json(false,'La sesión venció.',[],419);try{$action=(string)($_POST['action']??'');$model=new AdminRepositoryModel();if($action==='presentation'||$action==='unpresentation'){$model->setPresentationFile((int)($_POST['id']??0),$action==='presentation'?(int)($_POST['file_id']??0):null,(int)$s->userId());$this->json(true,$action==='unpresentation'?'Archivo de presentación eliminado.':'Archivo de presentación actualizado correctamente.');}if($action==='availability'){$available=filter_var($_POST['is_available']??null,FILTER_VALIDATE_BOOL,FILTER_NULL_ON_FAILURE);if($available===null)$this->json(false,'La disponibilidad solicitada no es válida.',[],422);$model->setAvailability((int)($_POST['id']??0),$available,(int)$s->userId());$this->json(true,$available?'Proyecto marcado como disponible.':'Proyecto marcado como no disponible.');}if($action==='publish')$this->json(false,'La publicación depende del flujo académico y no puede realizarse desde la administración.',[],403);if($action==='restore'){$model->restorePublication((int)($_POST['id']??0),(int)$s->userId());$this->json(true,'La publicación fue restaurada correctamente.');}$model->setPublished((int)($_POST['id']??0),false,(int)$s->userId());$this->json(true,'Proyecto retirado del repositorio. Permanece disponible en Proyectos.');}catch(InvalidArgumentException $e){$this->json(false,$e->getMessage(),[],422);}catch(Throwable $e){error_log('Publish project: '.$e->getMessage());$this->json(false,'No fue posible actualizar la publicación.',[],500);}}

    public function saveSupportMaterial():void
    {
        $this->requirePost();$session=new AuthSessionService();
        if($_POST===[]&&$_FILES===[]&&(int)($_SERVER['CONTENT_LENGTH']??0)>0){
            error_log('Support material file upload rejected before parsing: request body exceeded PHP limits or was malformed.');
            $this->json(false,'La carga supera los límites permitidos por el servidor.',[],422);
        }
        if(!$session->validateCsrf('admin_repository',(string)($_POST['_csrf']??'')))$this->json(false,'La solicitud contiene un token CSRF inválido.',[],419);
        $id=(int)($_POST['id']??0);$title=$this->normalizeAuditText($_POST['title']??'');
        try{
            $result=Database::transaction(function(PDO $database)use($id,$title,$session):array{
                $model=new SupportMaterialModel();
                $auditChanges=[];
                if($id>0){
                    $current=$model->findByIdForUpdate($id);
                    if($current===null)throw new InvalidArgumentException('El material ya no está disponible.');
                    $submittedMaterialType=$model->resolveMaterialType($_POST,(string)($_POST['controlled_material_type']??'')==='1');
                    $resolvedKeywords=$model->resolveKeywords(
                        $_POST,
                        (array)($current['keywords']??[]),
                        (string)($_POST['controlled_keywords']??'')==='1'
                    );
                    $submittedKeywords=$this->normalizeAuditKeywords($resolvedKeywords);
                    $currentKeywords=$this->normalizeAuditKeywords((array)($current['keywords']??[]));
                    $newCategoryId=(int)($_POST['category_id']??0);
                    $newCategoryName=$model->categoryName($newCategoryId);
                    if($newCategoryName===null)throw new InvalidArgumentException('Selecciona una categoría válida.');
                    $auditableFields=[
                        'title'=>['label'=>'Título','old'=>$this->normalizeAuditText($current['title']??''),'new'=>$title],
                        'material_type'=>['label'=>'Tipo de material','old'=>$this->normalizeAuditText($current['material_type']??''),'new'=>$this->normalizeAuditText($submittedMaterialType)],
                        'description'=>['label'=>'Descripción corta','old'=>$this->normalizeAuditText($current['description']??'',true),'new'=>$this->normalizeAuditText($_POST['description']??'',true)],
                        'full_description'=>['label'=>'Descripción completa','old'=>$this->normalizeAuditText($current['full_description']??'',true),'new'=>$this->normalizeAuditText($_POST['full_description']??'',true)],
                    ];
                    foreach($auditableFields as $field=>$change){
                        if($change['old']!==$change['new'])$auditChanges[]=['field'=>$field]+$change;
                    }
                    if((int)$current['category_id']!==$newCategoryId)$auditChanges[]=['field'=>'category','label'=>'Categoría','old'=>(string)($current['category_label']??''),'new'=>$newCategoryName];
                    if($currentKeywords['comparison']!==$submittedKeywords['comparison'])$auditChanges[]=['field'=>'keywords','label'=>'Palabras clave','old'=>$currentKeywords['display'],'new'=>$submittedKeywords['display']];
                    if($auditChanges===[])return ['id'=>$id,'no_changes'=>true];
                }
                $saved=$model->save($_POST,(int)$session->userId());
                (new AdminActivityService($database))->record(
                    (int)$session->userId(),
                    $id?'support_material.updated':'support_material.created',
                    $id?'Editó la información del material':'Creó el material de apoyo',
                    'Repositorio','support_material',$saved,$title?:'Material de apoyo','correct',
                    ['schema_version'=>1,'changes'=>$auditChanges]
                );
                return ['id'=>$saved,'no_changes'=>false];
            });
            if($result['no_changes'])$this->json(true,'La información ya se encuentra actualizada.',['id'=>$result['id'],'no_changes'=>true]);
            $saved=(int)$result['id'];
            $this->json(true,$id?'Material actualizado correctamente.':'Material creado correctamente.',['id'=>$saved]);
        }
        catch(InvalidArgumentException $error){$this->activityFailure($session,$id?'support_material.update_failed':'support_material.create_failed',$id?'Intentó editar material de apoyo':'Intentó crear material de apoyo','Repositorio','support_material',$id?:null,$title?:'Material de apoyo',$error);$this->json(false,$error->getMessage(),[],422);}
        catch(Throwable $error){$this->activityFailure($session,$id?'support_material.update_failed':'support_material.create_failed',$id?'Intentó editar material de apoyo':'Intentó crear material de apoyo','Repositorio','support_material',$id?:null,$title?:'Material de apoyo',$error);error_log('Support material save: '.$error->getMessage());$this->json(false,'No fue posible guardar el material.',[],500);}
    }

    public function supportMaterialHistory():void
    {
        if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET')$this->json(false,'Método no permitido.',[],405);
        $id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT);
        $offset=filter_var($_GET['offset']??0,FILTER_VALIDATE_INT);
        if($id===false||$id===null||(int)$id<1)$this->json(false,'El material solicitado no es válido.',[],422);
        if((new SupportMaterialModel())->findById((int)$id,true)===null)$this->json(false,'El material solicitado no existe.',[],404);
        try{
            $activityModel=new AdminActivityModel();
            $history=$activityModel->forEntity('support_material',(int)$id,20,$offset===false?0:(int)$offset);
            if(($offset===false?0:(int)$offset)===0){
                $session=new AuthSessionService();
                $activityModel->markSupportMaterialSeen((int)$session->userId(),(int)$id,(int)($history['max_audit_id']??0));
                $history['has_unread']=false;
            }
            $this->json(true,'Historial administrativo cargado.',$history);
        }catch(Throwable $error){
            error_log('Support material history: '.$error->getMessage());
            $this->json(false,'No fue posible cargar el historial administrativo.',[],500);
        }
    }

    public function cleanupSupportMaterialHistory():void
    {
        $this->requirePost();$session=new AuthSessionService();
        if(!$session->validateCsrf('admin_repository',(string)($_POST['_csrf']??'')))$this->json(false,'La sesión venció.',[],419);
        $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT);
        if($id===false||$id===null||(int)$id<1)$this->json(false,'El material solicitado no es válido.',[],422);
        if((string)($_POST['confirmation']??'')!=='ELIMINAR')$this->json(false,'Escribe ELIMINAR para confirmar la depuración.',[],422);
        try{
            $deleted=Database::transaction(function(PDO $database)use($id,$session):int{
                $material=(new SupportMaterialModel())->findByIdForUpdate((int)$id);
                if($material===null)throw new InvalidArgumentException('El material solicitado no existe.');
                $deleted=(new AdminActivityModel())->deleteIncompleteSupportMaterialEvents((int)$id);
                if($deleted>0){
                    (new AdminActivityService($database))->record(
                        (int)$session->userId(),'support_material.history_cleaned',
                        'Eliminó registros antiguos sin detalle','Repositorio','support_material',(int)$id,
                        (string)($material['title']??'Material de apoyo'),'correct',
                        ['schema_version'=>1,'deleted_count'=>$deleted,'reason'=>'legacy_events_without_change_details']
                    );
                }
                return $deleted;
            });
            if($deleted===0)$this->json(true,'No existen registros antiguos sin detalle para eliminar.',['deleted_count'=>0]);
            $this->json(true,$deleted===1?'Se eliminó 1 registro antiguo sin detalle.':'Se eliminaron '.$deleted.' registros antiguos sin detalle.',['deleted_count'=>$deleted]);
        }catch(InvalidArgumentException $error){
            $this->json(false,$error->getMessage(),[],422);
        }catch(Throwable $error){
            error_log('Support material history cleanup: '.$error->getMessage());
            $this->json(false,'No fue posible eliminar los registros antiguos.',[],500);
        }
    }

    public function changeSupportMaterialStatus():void
    {
        $this->requirePost();$session=new AuthSessionService();
        if(!$session->validateCsrf('admin_repository',(string)($_POST['_csrf']??'')))$this->json(false,'La sesión venció.',[],419);
        $id=(int)($_POST['id']??0);$status=(string)($_POST['status']??'');$action=(string)($_POST['action']??'publication');
        try{
            $model=new SupportMaterialModel();$actor=(int)$session->userId();
            $reasonCode=(string)($_POST['reason_code']??'');$reasonDetail=trim((string)($_POST['reason_detail']??''));
            $administrativeReasons=[
                'temporary_update'=>'Actualización temporal del contenido','administrative_review'=>'Revisión administrativa',
                'files_pending'=>'Archivos pendientes de corrección','outdated'=>'Información desactualizada',
                'temporary_suspension'=>'Acceso suspendido temporalmente','corrections_completed'=>'Correcciones completadas',
                'content_updated'=>'Contenido actualizado','files_verified'=>'Archivos verificados',
                'review_completed'=>'Revisión administrativa finalizada','incorrect'=>'Publicación incorrecta',
                'pending_review'=>'Material pendiente de revisión','incomplete_files'=>'Archivos incompletos',
                'replaced'=>'Material reemplazado','duplicate'=>'Contenido duplicado','not_required'=>'Ya no es requerido',
                'other'=>'Otro motivo',
            ];
            $reasonRequired=$action==='availability'||$action==='trash'||$status==='withdrawn';
            if($reasonRequired&&!isset($administrativeReasons[$reasonCode]))throw new InvalidArgumentException('Selecciona un motivo válido.');
            if($reasonCode!==''&&!isset($administrativeReasons[$reasonCode]))throw new InvalidArgumentException('Selecciona un motivo válido.');
            $allowedReasonCodes=$action==='trash'
                ?['duplicate','outdated','replaced','incorrect','not_required','other']
                :($action==='availability'
                    ?((string)($_POST['is_available']??'')==='1'
                        ?['corrections_completed','content_updated','files_verified','review_completed','other']
                        :['temporary_update','administrative_review','files_pending','outdated','temporary_suspension','other'])
                    :($status==='withdrawn'
                        ?['incorrect','outdated','pending_review','incomplete_files','replaced','other']
                        :['review_completed','content_updated','corrections_completed','other']));
            if($reasonCode!==''&&!in_array($reasonCode,$allowedReasonCodes,true))throw new InvalidArgumentException('El motivo no corresponde a la acción solicitada.');
            if($reasonCode==='other'&&mb_strlen($reasonDetail)<5)throw new InvalidArgumentException('Detalla el motivo con al menos cinco caracteres.');
            if(mb_strlen($reasonDetail)>300)throw new InvalidArgumentException('El detalle del motivo supera los 300 caracteres.');
            $reasonLabel=$reasonCode===''?'':$administrativeReasons[$reasonCode];
            if($action==='trash'){
                $redirect=route('admin-repository').'&tab=materials';
                Database::transaction(function(PDO $database)use($id,$reasonLabel,$actor,$reasonCode,$reasonDetail):void{
                    (new AdminTrashModel())->trashSupportMaterialAtomic($database,$id,$reasonLabel,$actor,$reasonCode,$reasonDetail);
                });
                $this->json(true,'Material enviado a Papelera correctamente.',['redirect'=>$redirect]);
            }
            $material=Database::transaction(function(PDO $database)use($model,$id,$status,$action,$actor,$reasonCode,$reasonLabel,$reasonDetail):array{
                $material=$model->findByIdForUpdate($id);
                if($material===null)throw new InvalidArgumentException('El material ya no está disponible.');
                if($action==='availability'){
                    $available=filter_var($_POST['is_available']??null,FILTER_VALIDATE_BOOL,FILTER_NULL_ON_FAILURE);
                    if($available===null)throw new InvalidArgumentException('La disponibilidad solicitada no es válida.');
                    $previous=$model->setAvailability($id,$available,$actor);
                    (new AdminActivityService($database))->record($actor,'support_material_availability_changed',$available?'Marcó material como disponible':'Marcó material como no disponible','Repositorio','support_material',$id,(string)$material['title'],'correct',['previous_available'=>$previous,'is_available'=>$available,'reason_code'=>$reasonCode,'reason'=>$reasonLabel,'reason_detail'=>$reasonDetail]);
                    $material['availability_result']=$available;
                    return $material;
                }
                if($status==='published'){
                    $requestedPresentation=(int)($_POST['presentation_file_id']??0);
                    if($requestedPresentation>0&&$requestedPresentation!==(int)($material['presentation_file_id']??0)){
                        $change=$model->setPresentationFile($id,$requestedPresentation,$actor);
                        (new AdminActivityService($database))->record(
                            $actor,empty($change['previous_file_id'])?'support_material.presentation_selected':'support_material.presentation_changed',
                            empty($change['previous_file_id'])?'Seleccionó el archivo de presentación':'Cambió el archivo de presentación',
                            'Repositorio','support_material',$id,$change['name'],'correct',
                            ['previous_file_id'=>$change['previous_file_id'],'new_file_id'=>$change['file_id'],'previous_name'=>$change['previous_name'],'new_name'=>$change['new_name'],'context'=>'publication']
                        );
                    }
                }
                $change=$model->setStatus($id,$status,$actor);
                $publishing=$status==='published';
                (new AdminActivityService($database))->record($actor,$publishing?'support_material_published':'support_material_withdrawn',$publishing?'Publicó material de apoyo':'Retiró material de apoyo','Repositorio','support_material',$id,$material['title']??'Material #'.$id,'correct',['previous_status'=>$change['previous_status'],'new_status'=>$change['new_status'],'previous_available'=>$change['previous_available'],'is_available'=>$change['is_available'],'reason_code'=>$reasonCode,'reason'=>$reasonLabel,'reason_detail'=>$reasonDetail,'republication'=>$publishing&&$change['was_previously_published']]);
                return $material;
            });
            if($action==='availability'){
                $available=(bool)$material['availability_result'];
                $this->json(true,$available?'El material volvió a estar disponible para consulta y descarga.':'El material permanece publicado, pero ya no admite consulta ni descarga.',['status_key'=>'published','status_label'=>'Publicado','is_available'=>$available,'availability_label'=>$available?'Disponible':'No disponible']);
            }
            $publishing=$status==='published';
            $updated=$model->findById($id,true);
            $this->json(true,$publishing?'Material publicado correctamente.':'Material retirado del repositorio.',['status_key'=>$updated['status_key'],'status_label'=>$publishing?'Publicado':'Retirado','is_available'=>(bool)$updated['is_available'],'availability_label'=>$updated['is_available']?'Disponible':'No disponible','published_at'=>$updated['published_at']]);
        }
        catch(InvalidArgumentException $error){$this->json(false,$error->getMessage(),[],422);}
        catch(Throwable $error){error_log('Support material status: '.$error->getMessage());$this->json(false,'No se pudo completar la acción. No se realizaron cambios.',[],500);}
    }

    public function changeSupportMaterialFile():void
    {
        $this->requirePost();$session=new AuthSessionService();
        if(!$session->validateCsrf('admin_repository',(string)($_POST['_csrf']??'')))$this->json(false,'La solicitud contiene un token CSRF inválido.',[],419);
        $materialId=(int)($_POST['material_id']??0);$action=(string)($_POST['action']??'add');
        try{
            $model=new SupportMaterialModel();
            if($action==='list_restorable'){
                if($model->findById($materialId,true)===null)$this->json(false,'El material ya no está disponible.',[],404);
                $files=$model->restorableFiles($materialId);
                $this->json(true,'Archivos retirados consultados correctamente.',[
                    'files'=>$files,'count'=>count($files),'restore_hours'=>SupportMaterialModel::RESTORE_HOURS,
                ]);
            }
            if($action==='inspect_restore'){
                $fileId=(int)($_POST['file_id']??0);
                $inspection=$model->inspectFileRestore($materialId,$fileId);
                $this->json(true,'Archivo disponible para restaurar.',$inspection);
            }
            if($action==='purge_restorable'){
                $fileIds=array_values(array_unique(array_map('intval',(array)($_POST['file_ids']??[]))));
                $actor=(int)$session->userId();
                $database=Database::connection();
                $staged=[];
                try{
                    $database->beginTransaction();
                    if($model->findByIdForUpdate($materialId)===null){
                        throw new InvalidArgumentException('El material ya no está disponible.');
                    }
                    $files=$model->inspectPermanentFileDeletion($materialId,$fileIds,true);
                    foreach($files as $file){
                        $source=(string)$file['absolute_path'];
                        $temporary=$source.'.purge-'.bin2hex(random_bytes(8));
                        if(!rename($source,$temporary)){
                            throw new RuntimeException('No fue posible preparar la eliminación física del archivo.');
                        }
                        $staged[]=['source'=>$source,'temporary'=>$temporary];
                    }
                    $model->markFilesPermanentlyDeleted($materialId,$fileIds,$actor);
                    $auditFiles=array_map(static fn(array $file):array=>[
                        'file_id'=>$file['id'],'original_name'=>$file['name'],
                        'extension'=>$file['extension'],'size_bytes'=>$file['size_bytes'],
                        'created_by'=>$file['created_by'],'created_by_name'=>$file['created_by_name'],
                        'deleted_at'=>$file['deleted_at'],'deleted_by'=>$file['deleted_by'],
                        'deleted_by_name'=>$file['deleted_by_name'],
                    ],$files);
                    (new AdminActivityService($database))->record(
                        $actor,'support_material.file_purged',
                        count($files)===1?'Eliminó definitivamente un archivo retirado':'Eliminó definitivamente archivos retirados',
                        'Repositorio','support_material',$materialId,
                        count($files)===1?$files[0]['name']:count($files).' archivos','correct',[
                            'material_id'=>$materialId,'file_ids'=>$fileIds,'files'=>$auditFiles,
                            'purged_at'=>(new DateTimeImmutable('now',new DateTimeZone('UTC')))->format(DATE_ATOM),
                            'purged_by'=>$actor,'restore_hours'=>SupportMaterialModel::RESTORE_HOURS,
                            'result'=>'correct',
                        ]
                    );
                    $database->commit();
                }catch(Throwable $error){
                    if($database->inTransaction())$database->rollBack();
                    foreach(array_reverse($staged) as $stagedFile){
                        if(is_file($stagedFile['temporary'])&&!is_file($stagedFile['source'])){
                            @rename($stagedFile['temporary'],$stagedFile['source']);
                        }
                    }
                    throw $error;
                }
                foreach($staged as $stagedFile){
                    if(is_file($stagedFile['temporary'])&&!unlink($stagedFile['temporary'])){
                        error_log('Support material purge cleanup failed: '.$stagedFile['temporary']);
                    }
                }
                $remaining=$model->restorableFiles($materialId);
                $this->json(true,count($fileIds)===1?'Archivo eliminado definitivamente.':'Archivos eliminados definitivamente.',[
                    'purged_file_ids'=>$fileIds,'restorable_count'=>count($remaining),
                ]);
            }
            if($action==='restore'){
                $fileId=(int)($_POST['file_id']??0);
                $confirmedName=trim((string)($_POST['final_name']??''));
                $actor=(int)$session->userId();
                $restored=Database::transaction(function(PDO $database)use($model,$materialId,$fileId,$actor,$confirmedName):array{
                    if($model->findByIdForUpdate($materialId)===null){
                        throw new InvalidArgumentException('El material ya no está disponible.');
                    }
                    $restored=$model->restoreFile($materialId,$fileId,$actor,$confirmedName);
                    (new AdminActivityService($database))->record(
                        $actor,'support_material.file_restored','Restauró un archivo del material',
                        'Repositorio','support_material',$materialId,$restored['final_name'],'correct',[
                            'material_id'=>$materialId,
                            'file_id'=>$fileId,
                            'original_name'=>$restored['original_name'],
                            'final_name'=>$restored['final_name'],
                            'deleted_at'=>$restored['deleted_at'],
                            'deleted_by'=>$restored['deleted_by'],
                            'deleted_by_name'=>$restored['deleted_by_name'],
                            'name_conflict'=>$restored['conflict'],
                            'renamed'=>$restored['original_name']!==$restored['final_name'],
                            'restore_hours'=>SupportMaterialModel::RESTORE_HOURS,
                            'result'=>'correct',
                        ]
                    );
                    return $restored;
                });
                $query='&material_id='.$materialId.'&file_id='.$fileId;
                $extension=(string)$restored['extension'];
                $material=$model->findById($materialId,true);
                $activeFile=current(array_filter(
                    (array)($material['files']??[]),
                    static fn(array $file):bool=>(int)$file['id']===$fileId
                ))?:null;
                $package=$material?(new SupportMaterialPackageService())->describe($material):['available'=>false,'file_count'=>0,'source'=>'generated'];
                $remaining=$model->restorableFiles($materialId);
                $this->json(true,'Archivo restaurado correctamente.',[
                    'file'=>[
                        'id'=>$fileId,'name'=>$restored['final_name'],'extension'=>$extension,
                        'type'=>mb_strtoupper($extension),'size_label'=>$restored['size'],
                        'size_bytes'=>(int)$restored['size_bytes'],'is_archive'=>$extension==='zip',
                        'preview_supported'=>in_array($extension,['pdf','docx','png','jpg','jpeg','webp','txt'],true)||$extension==='zip',
                        'preview_type'=>$extension==='zip'?'zip':(in_array($extension,['jpg','jpeg','png','webp'],true)?'image':$extension),
                        'preview_url'=>route('support-material-preview').$query,
                        'zip_url'=>$extension==='zip'?route('support-material-zip-list').$query:'',
                        'zip_entry_preview_url'=>$extension==='zip'?route('support-material-zip-entry-preview').$query:'',
                        'zip_entry_download_url'=>$extension==='zip'?route('support-material-zip-entry-download').$query:'',
                        'download_url'=>route('support-material-download').$query,
                        'presentation'=>(bool)($activeFile['presentation']??false),
                        'sort_order'=>(int)$restored['sort_order'],
                    ],
                    'restorable_count'=>count($remaining),
                    'package'=>[
                        'available'=>(bool)$package['available'],'file_count'=>(int)$package['file_count'],
                        'source'=>(string)$package['source'],
                        'download_url'=>!empty($package['available'])?route('support-material-package-download').'&material_id='.$materialId:'',
                    ],
                ]);
            }
            if($action==='presentation'||$action==='unpresentation'){
                $requestedFileId=(int)($_POST['file_id']??0);
                if($requestedFileId<1)throw new InvalidArgumentException('El archivo seleccionado no es válido.');
                $fileId=$action==='presentation'?$requestedFileId:null;$actor=(int)$session->userId();
                $change=Database::transaction(function(PDO $database)use($model,$materialId,$fileId,$requestedFileId,$actor):array{
                    if($model->findByIdForUpdate($materialId)===null)throw new InvalidArgumentException('El material ya no está disponible.');
                    $change=$model->setPresentationFile($materialId,$fileId,$actor,$fileId===null?$requestedFileId:null);
                    $elementLabel=$change['name']??'Archivo #'.$requestedFileId;
                    (new AdminActivityService($database))->record(
                        $actor,
                        $fileId===null?'support_material.presentation_removed':(empty($change['previous_file_id'])?'support_material.presentation_selected':'support_material.presentation_changed'),
                        $fileId===null?'Quitó el archivo de presentación':(empty($change['previous_file_id'])?'Seleccionó el archivo de presentación':'Cambió el archivo de presentación'),
                        'Repositorio','support_material',$materialId,$elementLabel,'correct',
                        ['previous_file_id'=>$change['previous_file_id'],'new_file_id'=>$change['file_id'],'previous_name'=>$change['previous_name'],'new_name'=>$change['new_name']]
                    );
                    return $change;
                });
                $this->json(true,$fileId===null?'Archivo de presentación eliminado correctamente.':'Archivo de presentación actualizado correctamente.',
                    ['file_id'=>$requestedFileId,'presentation'=>$fileId!==null]+$change);
            }
            if($action==='replace'){
                $fileId=(int)($_POST['file_id']??0);
                if($fileId<1)throw new InvalidArgumentException('El archivo seleccionado no es válido.');
                $upload=$_FILES['file']??null;
                if(!is_array($upload))throw new InvalidArgumentException('Selecciona el archivo de reemplazo.');
                $actor=(int)$session->userId();
                $fileService=new SupportMaterialFileService();
                $stored=null;
                try{
                    $stored=$fileService->store($materialId,$upload);
                    $replacement=Database::transaction(function(PDO $database)use($model,$materialId,$fileId,$stored,$actor):array{
                        if($model->findByIdForUpdate($materialId)===null){
                            throw new InvalidArgumentException('El material ya no está disponible.');
                        }
                        $replacement=$model->replaceFile($materialId,$fileId,$stored,$actor);
                        (new AdminActivityService($database))->record(
                            $actor,'support_material.file_replaced','Reemplazó un archivo del material',
                            'Repositorio','support_material',$materialId,$stored['original_name'],'correct',[
                                'file_id'=>$fileId,
                                'version_id'=>$replacement['version_id'],
                                'previous_file'=>$replacement['old'],
                                'new_file'=>$replacement['new'],
                                'presentation_unchanged'=>$replacement['presentation'],
                            ]
                        );
                        return $replacement;
                    });
                }catch(Throwable $error){
                    if(is_array($stored)&&!$fileService->discard($stored)){
                        error_log('Support material replacement cleanup failed material='.$materialId.' file='.$fileId);
                    }
                    throw $error;
                }
                $extension=(string)$stored['extension'];
                $query='&material_id='.$materialId.'&file_id='.$fileId;
                $material=$model->findById($materialId,true);
                $package=$material?(new SupportMaterialPackageService())->describe($material):['available'=>false,'file_count'=>0,'source'=>'generated'];
                $this->json(true,'Archivo reemplazado correctamente.',[
                    'file'=>[
                        'id'=>$fileId,'name'=>$stored['original_name'],'extension'=>$extension,
                        'type'=>mb_strtoupper($extension),'size_label'=>ArchiveService::formatBytes((int)$stored['size_bytes']),
                        'size_bytes'=>(int)$stored['size_bytes'],'is_archive'=>$extension==='zip',
                        'preview_supported'=>in_array($extension,['pdf','docx','png','jpg','jpeg','webp','txt'],true)||$extension==='zip',
                        'preview_type'=>$extension==='zip'?'zip':(in_array($extension,['jpg','jpeg','png','webp'],true)?'image':$extension),
                        'preview_url'=>route('support-material-preview').$query,
                        'zip_url'=>$extension==='zip'?route('support-material-zip-list').$query:'',
                        'download_url'=>route('support-material-download').$query,
                        'presentation'=>(bool)$replacement['presentation'],
                        'sort_order'=>(int)$replacement['sort_order'],
                    ],
                    'previous_file'=>$replacement['old'],
                    'version_id'=>$replacement['version_id'],
                    'package'=>[
                        'available'=>(bool)$package['available'],'file_count'=>(int)$package['file_count'],
                        'source'=>(string)$package['source'],
                        'download_url'=>!empty($package['available'])?route('support-material-package-download').'&material_id='.$materialId:'',
                    ],
                ]);
            }
            if($action==='remove'||$action==='remove_multiple'){
                $rawIds=$action==='remove_multiple'?($_POST['file_ids']??[]):[$_POST['file_id']??0];
                if(!is_array($rawIds))$this->json(false,'La selección de archivos no es válida.',[],422);
                if(count($rawIds)>20)$this->json(false,'Puedes retirar hasta 20 archivos por operación.',[],422);
                $fileIds=array_values(array_unique(array_map(static fn(mixed $id):int=>(int)$id,$rawIds)));
                if($fileIds===[]||in_array(0,$fileIds,true))$this->json(false,'Selecciona al menos un archivo válido.',[],422);
                $actor=(int)$session->userId();
                $removal=Database::transaction(function(PDO $database)use($model,$materialId,$fileIds,$actor):array{
                    $material=$model->findByIdForUpdate($materialId);
                    if($material===null)throw new InvalidArgumentException('El material ya no está disponible.');
                    $presentationId=(int)($material['presentation_file_id']??0);
                    $presentationChange=null;
                    if($presentationId>0&&in_array($presentationId,$fileIds,true)){
                        $presentationChange=$model->setPresentationFile($materialId,null,$actor,$presentationId);
                    }
                    $files=$model->removeAdditionalFiles($materialId,$fileIds,$actor);
                    $activity=new AdminActivityService($database);
                    if($presentationChange!==null){
                        $presentationFile=current(array_filter(
                            $files,
                            static fn(array $file):bool=>(int)$file['id']===$presentationId
                        ))?:null;
                        $activity->record(
                            $actor,'support_material.presentation_removed','Quitó el archivo de presentación',
                            'Repositorio','support_material',$materialId,
                            (string)($presentationFile['name']??'Archivo #'.$presentationId),'correct',
                            ['previous_file_id'=>$presentationChange['previous_file_id'],'new_file_id'=>null,'previous_name'=>$presentationChange['previous_name'],'new_name'=>null,'reason'=>'presentation_file_retired']
                        );
                    }
                    foreach($files as $file)$activity->record(
                            $actor,'support_material.file_removed','Retiró un archivo del material',
                            'Repositorio','support_material',$materialId,$file['name'],'correct',[
                                'file_id'=>$file['id'],'name'=>$file['name'],'extension'=>$file['extension'],
                                'size_bytes'=>$file['size_bytes'],'presentation'=>(int)$file['id']===$presentationId,
                            ]
                        );
                    return ['files'=>$files,'presentation_removed'=>$presentationChange!==null];
                });
                $removed=$removal['files'];
                $material=$model->findById($materialId,true);
                $package=$material?(new SupportMaterialPackageService())->describe($material):['available'=>false,'file_count'=>0,'source'=>'generated'];
                $removedIds=array_values(array_map(static fn(array $file):int=>(int)$file['id'],$removed));
                $removedCount=count($removedIds);$availableCount=count((array)($material['files']??[]));
                $message=$removedCount===1?'Archivo retirado correctamente.':$removedCount.' archivos retirados correctamente.';
                $packageDescriptor=[
                    'available'=>(bool)$package['available'],'file_count'=>(int)$package['file_count'],
                    'source'=>(string)$package['source'],
                    'download_url'=>!empty($package['available'])?route('support-material-package-download').'&material_id='.$materialId:'',
                ];
                $currentPresentationId=(int)($material['presentation_file_id']??0);
                $this->json(true,$message,['removed'=>$removed,'removed_file_ids'=>$removedIds,'removed_count'=>$removedCount,
                    'available_count'=>$availableCount,'updated_available_count'=>$availableCount,
                    'presentation_file_id'=>$currentPresentationId,
                    'presentation_removed'=>(bool)$removal['presentation_removed'],
                    'package'=>$packageDescriptor,'updated_package_descriptor'=>$packageDescriptor,
                ]);
            }
            if($model->findById($materialId,true)===null)$this->json(false,'El material ya no está disponible.',[],404);
            $fileService=new SupportMaterialFileService();$limits=$fileService->limits();$uploads=[];
            if(isset($_FILES['files']['name'])&&is_array($_FILES['files']['name'])){
                foreach(array_keys($_FILES['files']['name']) as $index)$uploads[]=[
                    'name'=>$_FILES['files']['name'][$index]??'','type'=>$_FILES['files']['type'][$index]??'',
                    'tmp_name'=>$_FILES['files']['tmp_name'][$index]??'','error'=>$_FILES['files']['error'][$index]??UPLOAD_ERR_NO_FILE,
                    'size'=>$_FILES['files']['size'][$index]??0,
                ];
            }elseif(isset($_FILES['file']))$uploads[]=$_FILES['file'];
            if($uploads===[])$this->json(false,'Selecciona al menos un archivo.',[],422);
            if(count($uploads)>(int)$limits['max_operation_files'])$this->json(false,'Puedes agregar hasta '.$limits['max_operation_files'].' archivos por operación.',[],422);
            if(array_sum(array_map(static fn(array $upload):int=>(int)($upload['size']??0),$uploads))>(int)$limits['max_operation_bytes']){
                $this->json(false,'La selección completa supera el límite de 35 MB por operación.',[],422);
            }
            $added=[];$failed=[];$actor=(int)$session->userId();
            foreach($uploads as $upload){
                $displayName=mb_substr(basename(str_replace('\\','/',(string)($upload['name']??'Archivo'))),0,200);
                $stored=null;
                try{
                    $stored=$fileService->store($materialId,$upload);
                    $fileId=Database::transaction(function(PDO $database)use($model,$materialId,$stored,$actor):int{
                        if($model->findByIdForUpdate($materialId)===null)throw new InvalidArgumentException('El material ya no está disponible.');
                        if($model->hasActiveFileEquivalent($materialId,(string)$stored['original_name'],(int)$stored['size_bytes'])){
                            throw new InvalidArgumentException('Ya existe un archivo activo con el mismo nombre y tamaño.');
                        }
                        $id=$model->addFile($materialId,$stored,$actor);
                        (new AdminActivityService($database))->record(
                            $actor,'support_material.file_added','Agregó un archivo al material',
                            'Repositorio','support_material',$materialId,$stored['original_name'],'correct',[
                                'file_id'=>$id,'name'=>$stored['original_name'],'extension'=>$stored['extension'],
                                'mime_type'=>$stored['mime_type'],'size_bytes'=>$stored['size_bytes'],
                                'is_package'=>false,
                            ]
                        );
                        return $id;
                    });
                    $query='&material_id='.$materialId.'&file_id='.$fileId;
                    $extension=(string)$stored['extension'];
                    $added[]=[
                        'id'=>$fileId,'name'=>$stored['original_name'],'extension'=>$extension,
                        'type'=>mb_strtoupper($extension),'size_label'=>ArchiveService::formatBytes((int)$stored['size_bytes']),
                        'size_bytes'=>(int)$stored['size_bytes'],
                        'is_archive'=>$extension==='zip',
                        'preview_supported'=>in_array($extension,['pdf','docx','png','jpg','jpeg','webp','txt'],true)||$extension==='zip',
                        'preview_type'=>$extension==='zip'?'zip':(in_array($extension,['jpg','jpeg','png','webp'],true)?'image':$extension),
                        'preview_url'=>route('support-material-preview').$query,
                        'zip_url'=>$extension==='zip'?route('support-material-zip-list').$query:'',
                        'zip_entry_preview_url'=>$extension==='zip'?route('support-material-zip-entry-preview').$query:'',
                        'zip_entry_download_url'=>$extension==='zip'?route('support-material-zip-entry-download').$query:'',
                        'download_url'=>route('support-material-download').$query,
                    ];
                }catch(Throwable $error){
                    if(is_array($stored)&&!$fileService->discard($stored)){
                        error_log('Support material orphan cleanup failed for upload '.$displayName);
                    }
                    error_log(sprintf(
                        'Support material file add failed [%s] material=%d file=%s upload_error=%d: %s',
                        $error::class,$materialId,$displayName?:'Archivo',(int)($upload['error']??UPLOAD_ERR_NO_FILE),$error->getMessage()
                    ));
                    $failed[]=['name'=>$displayName?:'Archivo','message'=>$error instanceof InvalidArgumentException?$error->getMessage():'No fue posible procesar este archivo.'];
                }
            }
            $requested=count($uploads);$addedCount=count($added);$failedCount=count($failed);
            $material=$model->findById($materialId,true);$package=$material?(new SupportMaterialPackageService())->describe($material):['available'=>false,'file_count'=>0,'source'=>'generated'];
            $data=['summary'=>['requested'=>$requested,'added'=>$addedCount,'failed'=>$failedCount],'added'=>$added,'failed'=>$failed,'package'=>[
                'available'=>(bool)$package['available'],'file_count'=>(int)$package['file_count'],'source'=>(string)$package['source'],
                'download_url'=>!empty($package['available'])?route('support-material-package-download').'&material_id='.$materialId:'',
            ]];
            if($addedCount===0)$this->json(false,'No se pudo agregar ningún archivo.',$data,422);
            $message=$failedCount>0
                ?$addedCount.($addedCount===1?' archivo agregado y ':' archivos agregados y ').$failedCount.($failedCount===1?' no pudo procesarse.':' no pudieron procesarse.')
                :$addedCount.($addedCount===1?' archivo agregado correctamente.':' archivos agregados correctamente.');
            $this->json(true,$message,$data,$failedCount>0?207:200);
        }
        catch(InvalidArgumentException $error){$this->json(false,$error->getMessage(),[],422);}
        catch(Throwable $error){
            error_log(sprintf(
                'Support material file endpoint=admin-support-material-file action=%s material_id=%d file_id=%d actor=%d: %s in %s:%d code=%s',
                $action,$materialId,(int)($_POST['file_id']??0),(int)($session->userId()??0),
                $error->getMessage(),$error->getFile(),$error->getLine(),(string)$error->getCode()
            ));
            $this->json(false,'No fue posible completar la acción sobre el archivo.',[],500);
        }
    }
    public function academic():void{$model=new AdminAcademicModel();$error=null;try{$data=$model->dashboard();}catch(Throwable $e){error_log('Admin academic: '.$e->getMessage());$error='No fue posible consultar la configuración académica.';$data=['periods'=>[],'types'=>[],'promotion'=>['source'=>null,'target'=>null,'projects'=>0,'suggested'=>null]];}$s=new AuthSessionService();View::render('admin/academic',['currentPage'=>'admin-academic','title'=>'Gestión académica | Administración','bodyClass'=>'admin-academic-page','pageStyles'=>[asset('css/admin-academic.css')],'pageScript'=>asset('js/admin-academic.js'),'academic'=>$data,'academicError'=>$error,'academicCsrf'=>$s->csrfToken('admin_academic'),'academicEndpoints'=>['save'=>route('admin-academic-save'),'promote'=>route('admin-academic-promote')]]);}
    public function saveAcademic():void{$this->requirePost();$s=new AuthSessionService();if(!$s->validateCsrf('admin_academic',(string)($_POST['_csrf']??'')))$this->json(false,'La sesión venció.',[],419);$entity=(string)($_POST['entity']??'');$id=(int)($_POST['id']??0);$action=(string)($_POST['action']??'save');$failedLabel=$entity==='period'?($action==='delete'?'Intentó eliminar una planificación':($id?'Intentó editar una planificación':'Intentó planificar el siguiente período')):($action==='deactivate'?'Intentó desactivar un tipo de proyecto':($action==='activate'?'Intentó activar un tipo de proyecto':($id?'Intentó editar un tipo de proyecto':'Intentó crear un tipo de proyecto')));$element=$entity==='period'?'Período académico':(string)($_POST['name']??'Tipo de proyecto');try{(new AdminAcademicModel())->save($entity,$_POST,(int)$s->userId());$this->json(true,'Información académica guardada correctamente.');}catch(InvalidArgumentException $e){$this->activityFailure($s,'academic_'.$entity.'_'.$action,$failedLabel,'Gestión académica',$entity,$id?:null,$element,$e);$this->json(false,$e->getMessage(),[],422);}catch(Throwable $e){$this->activityFailure($s,'academic_'.$entity.'_'.$action,$failedLabel,'Gestión académica',$entity,$id?:null,$element,$e);error_log('Save academic: '.$e->getMessage());$this->json(false,'No fue posible guardar la información.',[],500);}}
    public function promoteAcademicPeriod():void{$this->requirePost();$s=new AuthSessionService();if(!$s->validateCsrf('admin_academic',(string)($_POST['_csrf']??'')))$this->json(false,'La sesión venció.',[],419);try{$result=(new AdminAcademicModel())->promote((int)($_POST['target_period_id']??0),(int)$s->userId(),($_POST['confirm_early_close']??'')==='1');$this->json(true,$result['closed'].' fue cerrado y '.$result['activated'].' quedó activo. Los proyectos conservaron su período original.',$result);}catch(InvalidArgumentException $e){$this->activityFailure($s,'academic_period_closed','Intentó cerrar el período académico','Gestión académica','period',null,'Período académico actual',$e);$this->json(false,$e->getMessage(),[],422);}catch(Throwable $e){$this->activityFailure($s,'academic_period_closed','Intentó cerrar el período académico','Gestión académica','period',null,'Período académico actual',$e);error_log('Promote academic: '.$e->getMessage());$this->json(false,'No fue posible cerrar el período.',[],500);}}
    public function projects(): void
    {
        $model=new AdminProjectModel();$filters=['search'=>mb_substr(trim((string)($_GET['search']??'')),0,100),'status'=>(string)($_GET['status']??''),'type_id'=>(int)($_GET['type_id']??0),'period_id'=>(int)($_GET['period_id']??0),'group'=>(string)($_GET['group']??''),'attention'=>(string)($_GET['attention']??'')];$error=null;
        try{$result=$model->listing($filters,PaginationService::request());$projects=$result['items'];$pagination=$result['pagination'];$summary=$model->summary();$catalogs=$model->catalogs();}catch(Throwable $exception){error_log('Admin projects: '.$exception->getMessage());$error='No fue posible consultar los proyectos.';$projects=[];$pagination=['total'=>0];$summary=['total'=>0,'development'=>0,'review'=>0,'approved'=>0,'defense'=>0];$catalogs=['types'=>[],'careers'=>[],'periods'=>[],'teachers'=>[]];}
        $session=new AuthSessionService();View::render('admin/projects',['currentPage'=>'projects','title'=>'Proyectos | Administración','bodyClass'=>'admin-projects-page','pageStyles'=>[asset('css/admin-projects.css')],'pageScript'=>asset('js/admin-projects.js'),'projects'=>$projects,'pagePagination'=>$pagination,'projectSummary'=>$summary,'catalogs'=>$catalogs,'filters'=>$filters,'projectError'=>$error,'projectCsrf'=>$session->csrfToken('admin_projects'),'projectEndpoints'=>['save'=>route('admin-project-save'),'trash'=>route('admin-project-trash')]]);
    }
    public function saveProject():void{$this->requirePost();$session=new AuthSessionService();if(!$session->validateCsrf('admin_projects',(string)($_POST['_csrf']??'')))$this->json(false,'La sesión del formulario venció.',[],419);$id=(int)($_POST['id']??0);$title=trim((string)($_POST['title']??''));try{$payload=['title'=>$title,'subtitle'=>trim((string)($_POST['subtitle']??'')),'project_type_id'=>(int)($_POST['project_type_id']??0),'career_id'=>(int)($_POST['career_id']??0),'academic_period_id'=>(int)($_POST['academic_period_id']??0),'tutor_id'=>(int)($_POST['tutor_id']??0),'status'=>(string)($_POST['status']??'development'),'presentation_file_id'=>(int)($_POST['presentation_file_id']??0)];$saved=(new AdminProjectModel())->save($payload,$id,(int)$session->userId());$this->json(true,$id?'Proyecto actualizado correctamente.':'Proyecto creado correctamente.',['id'=>$saved]);}catch(InvalidArgumentException $exception){if($id)$this->activityFailure($session,'project_status_changed','Intentó modificar el estado de un proyecto','Proyectos','project',$id,$title?:'Proyecto #'.$id,$exception);$this->json(false,$exception->getMessage(),[],422);}catch(Throwable $exception){if($id)$this->activityFailure($session,'project_status_changed','Intentó modificar el estado de un proyecto','Proyectos','project',$id,$title?:'Proyecto #'.$id,$exception);error_log('Admin save project: '.$exception->getMessage());$this->json(false,'No fue posible guardar el proyecto.',[],500);}}
    public function trashProject():void{$this->requirePost();$session=new AuthSessionService();if(!$session->validateCsrf('admin_projects',(string)($_POST['_csrf']??'')))$this->json(false,'La sesión del formulario venció.',[],419);try{(new AdminProjectModel())->trash((int)($_POST['id']??0),(string)($_POST['reason']??''),(int)$session->userId());$this->json(true,'Proyecto enviado a la Papelera. Se conservará para restauración.');}catch(InvalidArgumentException $exception){$this->json(false,$exception->getMessage(),[],422);}catch(Throwable $exception){error_log('Admin trash project: '.$exception->getMessage());$this->json(false,'No fue posible enviar el proyecto a la Papelera.',[],500);}}
    public function users(): void
    {
        $model = new AdminUserModel();
        $filters = ['search'=>mb_substr(trim((string)($_GET['search']??'')),0,100),'role'=>(string)($_GET['role']??''),'status'=>(string)($_GET['status']??'')];
        $error = null;
        try { $result=$model->listing($filters,PaginationService::request());$users=$result['items'];$pagination=$result['pagination']; $summary=$model->summary(); $catalogs=$model->catalogs(); }
        catch(Throwable $exception){ error_log('Admin users error: '.$exception->getMessage());$error='No fue posible consultar los usuarios.';$users=[];$pagination=['total'=>0];$summary=['total'=>0,'active'=>0,'blocked'=>0,'students'=>0,'teachers'=>0,'administrators'=>0];$catalogs=['career'=>null,'period'=>null]; }
        $session=new AuthSessionService();
        View::render('admin/users',['currentPage'=>'admin-users','title'=>'Usuarios | Administración','bodyClass'=>'admin-users-page','pageStyles'=>[asset('css/admin-users.css'),asset('css/admin-user-import.css')],'pageScript'=>asset('js/admin-users.js'),'pageScripts'=>[asset('js/admin-user-import.js')],'users'=>$users,'pagePagination'=>$pagination,'userSummary'=>$summary,'catalogs'=>$catalogs,'filters'=>$filters,'adminUserCsrf'=>$session->csrfToken('admin_users'),'adminUserEndpoints'=>['save'=>route('admin-user-save'),'status'=>route('admin-user-status'),'password'=>route('admin-user-password'),'import'=>route('admin-users-import')],'adminUsersError'=>$error]);
    }

    public function saveUser(): void
    {
        $this->requirePost(); $session=$this->sessionAndCsrf();$id=(int)($_POST['id']??0);$name=trim((string)($_POST['full_name']??''));
        try {
            $payload=$this->userPayload();
            $saved=(new AdminUserModel())->save($payload,$id,(int)$session->userId());
            $this->json(true,$id>0?'Usuario actualizado correctamente.':'Usuario creado correctamente.',['user'=>$saved]);
        } catch(InvalidArgumentException $exception){$this->activityFailure($session,$id?'user_updated':'user_created',$id?'Intentó editar un usuario':'Intentó crear un usuario','Usuarios','user',$id?:null,$name?:'Usuario',$exception);$this->json(false,$exception->getMessage(),[],422);}
        catch(Throwable $exception){$this->activityFailure($session,$id?'user_updated':'user_created',$id?'Intentó editar un usuario':'Intentó crear un usuario','Usuarios','user',$id?:null,$name?:'Usuario',$exception);error_log('Save admin user: '.$exception->getMessage());$this->json(false,'No fue posible guardar el usuario.',[],500);}
    }

    public function changeUserStatus(): void
    {
        $this->requirePost();$session=$this->sessionAndCsrf();$id=(int)($_POST['id']??0);$status=(string)($_POST['status']??'');
        try{(new AdminUserModel())->changeStatus($id,$status,(int)$session->userId());$this->json(true,'Estado de acceso actualizado.');}
        catch(InvalidArgumentException $exception){$this->activityFailure($session,'user_status_changed','Intentó cambiar el acceso de un usuario','Usuarios','user',$id,'Usuario #'.$id,$exception);$this->json(false,$exception->getMessage(),[],422);}
        catch(Throwable $exception){$this->activityFailure($session,'user_status_changed','Intentó cambiar el acceso de un usuario','Usuarios','user',$id,'Usuario #'.$id,$exception);error_log('Admin user status: '.$exception->getMessage());$this->json(false,'No fue posible actualizar el acceso.',[],500);}
    }

    public function resetUserPassword(): void
    {
        $this->requirePost();$session=$this->sessionAndCsrf();$id=(int)($_POST['id']??0);
        try{(new AdminUserModel())->resetPassword($id,'Istel2026+',(int)$session->userId());$this->json(true,'Contraseña temporal restablecida. El usuario deberá cambiarla.');}
        catch(InvalidArgumentException $exception){$this->json(false,$exception->getMessage(),[],422);}
        catch(Throwable $exception){error_log('Admin password reset: '.$exception->getMessage());$this->json(false,'No fue posible restablecer la contraseña.',[],500);}
    }

    public function importUsers(): void
    {
        $this->requirePost();$session=$this->sessionAndCsrf();$content=trim((string)($_POST['content']??''));
        if(isset($_FILES['file'])&&($_FILES['file']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){$file=$_FILES['file'];if(($file['error']??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK)$this->json(false,'No fue posible leer el archivo.',[],422);$extension=mb_strtolower(pathinfo((string)$file['name'],PATHINFO_EXTENSION));if(!in_array($extension,['csv','txt'],true))$this->json(false,'Utiliza un archivo CSV o TXT.',[],422);if((int)$file['size']>1048576)$this->json(false,'El archivo no puede superar 1 MB.',[],422);$content=(string)file_get_contents((string)$file['tmp_name']);}
        try{$model=new AdminUserModel();$config=['role'=>(string)($_POST['role']??''),'career_id'=>(int)($_POST['career_id']??0),'academic_period_id'=>(int)($_POST['academic_period_id']??0),'semester'=>(int)($_POST['semester']??0),'can_tutor'=>isset($_POST['can_tutor'])?1:0];$preview=$model->previewImport($content,$config);if(($_POST['mode']??'preview')==='import'){$result=$model->bulkImport($content,$config,(int)$session->userId());$this->json(true,$result['created'].' usuarios fueron creados correctamente.',$result);} $this->json(true,'Vista previa generada.',$preview);}
        catch(InvalidArgumentException $exception){$this->json(false,$exception->getMessage(),[],422);}
        catch(Throwable $exception){error_log('Admin bulk import: '.$exception->getMessage());$this->json(false,'No fue posible procesar la lista.',[],500);}
    }

    public function module(string $section): void
    {
        $modules=['academic'=>['Gestión académica','fa-graduation-cap','Periodos, matrículas y catálogos se habilitarán en la Fase 4.'],'reports'=>['Reportes','fa-chart-column','Los reportes administrativos se habilitarán al completar los módulos de datos.'],'settings'=>['Configuración','fa-gear','Los parámetros institucionales se incorporarán en la Fase 6.'],'trash'=>['Papelera','fa-trash-can','La restauración y purga a 60 días se incorporará en la Fase 7.']];
        if(!isset($modules[$section])){$this->users();return;}$item=$modules[$section];
        View::render('admin/module-pending',['currentPage'=>'admin-'.$section,'title'=>$item[0].' | Administración','bodyClass'=>'admin-page','pageStyles'=>[asset('css/admin-access.css')],'moduleTitle'=>$item[0],'moduleIcon'=>$item[1],'moduleMessage'=>$item[2]]);
    }

    private function userPayload(): array
    {
        return ['full_name'=>trim((string)($_POST['full_name']??'')),'email'=>mb_strtolower(trim((string)($_POST['email']??''))),'username'=>trim((string)($_POST['username']??'')),'role'=>(string)($_POST['role']??''),'status'=>(string)($_POST['status']??'active'),'institutional_code'=>trim((string)($_POST['institutional_code']??'')),'career_id'=>(int)($_POST['career_id']??0),'academic_period_id'=>(int)($_POST['academic_period_id']??0),'semester'=>(int)($_POST['semester']??0),'academic_title'=>trim((string)($_POST['academic_title']??'')),'can_tutor'=>isset($_POST['can_tutor'])?1:0,'is_admin'=>isset($_POST['is_admin'])?1:0];
    }
    private function normalizeAuditText(mixed $value,bool $multiline=false):string
    {
        $normalized=str_replace(["\r\n","\r"],"\n",(string)$value);
        if(class_exists('Normalizer')){
            $unicode=Normalizer::normalize($normalized,Normalizer::FORM_C);
            if(is_string($unicode))$normalized=$unicode;
        }
        $normalized=(string)preg_replace('/[\p{Z}\t\f\v]+/u',' ',$normalized);
        if(!$multiline)return trim((string)preg_replace('/\s+/u',' ',$normalized));
        $lines=array_map(static fn(string $line):string=>trim($line),explode("\n",$normalized));
        $normalized=(string)preg_replace('/\n{3,}/',"\n\n",implode("\n",$lines));
        return trim($normalized);
    }
    private function normalizeAuditDate(mixed $value):string
    {
        $normalized=trim((string)$value);
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$normalized);
        return $date&&$date->format('Y-m-d')===$normalized?$date->format('Y-m-d'):$normalized;
    }
    private function normalizeAuditKeywords(mixed $value):array
    {
        $source=is_array($value)?$value:(preg_split('/[,;\n]+/u',(string)$value)?:[]);
        $display=[];$seen=[];
        foreach($source as $keyword){
            $normalized=$this->normalizeAuditText($keyword);
            if($normalized==='')continue;
            $key=mb_strtolower($normalized,'UTF-8');
            if(isset($seen[$key]))continue;
            $seen[$key]=true;$display[]=$normalized;
        }
        $comparison=array_keys($seen);
        sort($comparison,SORT_STRING);
        return ['display'=>$display,'comparison'=>$comparison];
    }
    private function sessionAndCsrf(): AuthSessionService{$session=new AuthSessionService();if(!$session->validateCsrf('admin_users',(string)($_POST['_csrf']??'')))$this->json(false,'La sesión del formulario venció.',[],419);return $session;}
    private function activityFailure(AuthSessionService $session,string $action,string $label,string $module,string $entityType,?int $entityId,string $element,Throwable $error):void
    {
        (new AdminActivityService())->recordFailure((int)$session->userId(),$action,$label,$module,$entityType,$entityId,$element,$error);
    }
    private function requirePost(): void{if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){$this->json(false,'Método no permitido.',[],405);}}
    private function json(bool $success,string $message,array $data=[],int $status=200): never{$status=$status===419?403:$status;http_response_code($status);header('Content-Type: application/json; charset=utf-8');echo json_encode(['success'=>$success,'message'=>$message,'data'=>$data],JSON_UNESCAPED_UNICODE);exit;}
}
