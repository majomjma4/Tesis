<?php
declare(strict_types=1);
final class AdminNotificationModel
{
 private const TYPES=['system','reminder','status_change','repository'];
 public function summary():array{$d=Database::connection();return ['sent'=>(int)$d->query("SELECT COUNT(*) FROM admin_audit_log WHERE action='notification_sent'")->fetchColumn(),'recipients'=>(int)$d->query("SELECT COUNT(*) FROM notifications WHERE JSON_EXTRACT(metadata,'$.admin_sender_id') IS NOT NULL")->fetchColumn(),'today'=>(int)$d->query("SELECT COUNT(*) FROM notifications WHERE DATE(created_at)=CURRENT_DATE AND JSON_EXTRACT(metadata,'$.admin_sender_id') IS NOT NULL")->fetchColumn()];}
 public function dashboard(array $pagination=[]):array{$d=Database::connection();$sent=PaginationService::run($d,"SELECT COUNT(*) FROM admin_audit_log WHERE action='notification_sent'","SELECT aal.id,aal.details,aal.created_at,u.full_name sender FROM admin_audit_log aal LEFT JOIN users u ON u.id=aal.actor_user_id WHERE aal.action='notification_sent' ORDER BY aal.created_at DESC",[],$pagination?:PaginationService::request());return ['users'=>$d->query("SELECT id,full_name,email FROM users WHERE status='active' ORDER BY full_name")->fetchAll(),'projects'=>$d->query("SELECT id,code,title FROM projects WHERE deleted_at IS NULL ORDER BY updated_at DESC")->fetchAll(),'sent'=>$sent['items'],'pagination'=>$sent['pagination'],'summary'=>['sent'=>(int)$d->query("SELECT COUNT(*) FROM admin_audit_log WHERE action='notification_sent'")->fetchColumn(),'recipients'=>(int)$d->query("SELECT COUNT(*) FROM notifications WHERE JSON_EXTRACT(metadata,'$.admin_sender_id') IS NOT NULL")->fetchColumn(),'today'=>(int)$d->query("SELECT COUNT(*) FROM notifications WHERE DATE(created_at)=CURRENT_DATE AND JSON_EXTRACT(metadata,'$.admin_sender_id') IS NOT NULL")->fetchColumn()]];}
 public function send(array $v,int $actor):array{$scope=(string)($v['scope']??'');$type=(string)($v['type']??'');$title=trim((string)($v['title']??''));$message=trim((string)($v['message']??''));$projectId=(int)($v['project_id']??0);if(!in_array($scope,['user','project','role','all'],true)||!in_array($type,self::TYPES,true))throw new InvalidArgumentException('Selecciona un alcance y tipo válidos.');if(mb_strlen($title)<4||mb_strlen($title)>180||mb_strlen($message)<8||mb_strlen($message)>2000)throw new InvalidArgumentException('Completa el título y el mensaje.');if($scope==='all'&&($type!=='system'||($v['confirm_all']??'')!=='1'))throw new InvalidArgumentException('Los avisos globales deben ser del sistema y requieren confirmación explícita.');return Database::transaction(function(PDO $d)use($v,$actor,$scope,$type,$title,$message,$projectId):array{$sql='';$params=[];if($scope==='user'){$sql="SELECT id FROM users WHERE id=:id AND status='active'";$params=['id'=>(int)($v['user_id']??0)];}elseif($scope==='role'){$role=(string)($v['role']??'');if(!in_array($role,['student','teacher','administrator'],true))throw new InvalidArgumentException('Selecciona un rol válido.');if($role==='administrator'){$sql="SELECT id FROM users WHERE status='active' AND is_admin=1 AND deleted_at IS NULL AND purged_at IS NULL";}else{$sql="SELECT DISTINCT u.id FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE u.status='active' AND r.code=:role";$params=['role'=>$role];}}elseif($scope==='project'){if($projectId<1)throw new InvalidArgumentException('Selecciona un proyecto.');$sql="SELECT DISTINCT id FROM (SELECT created_by id FROM projects WHERE id=:p1 AND deleted_at IS NULL UNION SELECT tutor_id FROM projects WHERE id=:p2 AND tutor_id IS NOT NULL UNION SELECT user_id FROM project_participants WHERE project_id=:p3 AND status='active') recipients";$params=['p1'=>$projectId,'p2'=>$projectId,'p3'=>$projectId];}else{$sql="SELECT id FROM users WHERE status='active'";}$q=$d->prepare($sql);$q->execute($params);$ids=array_map('intval',array_column($q->fetchAll(),'id'));if(!$ids)throw new InvalidArgumentException('No se encontraron destinatarios activos.');$insert=$d->prepare('INSERT INTO notifications(user_id,project_id,type,title,message,action_url,action_label,metadata) VALUES(:user,:project,:type,:title,:message,:url,:label,:metadata)');$url=$projectId?route('project-detail').'&id='.$projectId:null;$metadata=json_encode(['admin_sender_id'=>$actor,'scope'=>$scope],JSON_UNESCAPED_UNICODE);foreach($ids as $id)$insert->execute(['user'=>$id,'project'=>$projectId?:null,'type'=>$type,'title'=>$title,'message'=>$message,'url'=>$url,'label'=>$url?'Abrir proyecto':null,'metadata'=>$metadata]);$details=json_encode(['title'=>$title,'scope'=>$scope,'recipients'=>count($ids),'project_id'=>$projectId?:null],JSON_UNESCAPED_UNICODE);$d->prepare("INSERT INTO admin_audit_log(actor_user_id,action,entity_type,entity_id,details) VALUES(:actor,'notification_sent','notification',NULL,:details)")->execute(['actor'=>$actor,'details'=>$details]);return ['recipients'=>count($ids)];});}
 public function sendAudience(array $v,int $actor):array
 {
     $scope=(string)($v['scope']??''); $type=(string)($v['type']??''); $title=trim((string)($v['title']??'')); $message=trim((string)($v['message']??''));
     if(!in_array($scope,['student_one','student_many','teacher_one','teacher_many','semester_students','all_students','all_teachers'],true)||!in_array($type,self::TYPES,true)) throw new InvalidArgumentException('Selecciona destinatarios y tipo válidos.');
     if(mb_strlen($title)<4||mb_strlen($title)>180||mb_strlen($message)<8||mb_strlen($message)>2000) throw new InvalidArgumentException('Completa el título y el mensaje.');
     return Database::transaction(function(PDO $d)use($v,$actor,$scope,$type,$title,$message):array{
         $params=[]; $sql='';
         if(in_array($scope,['student_one','student_many','teacher_one','teacher_many'],true)){
             $kind=str_starts_with($scope,'student')?'student':'teacher'; $ids=array_values(array_unique(array_filter(array_map('intval',(array)($v['recipient_ids']??[])))));
             if(!$ids) throw new InvalidArgumentException('Selecciona al menos un destinatario.');
             $marks=implode(',',array_fill(0,count($ids),'?')); $sql="SELECT DISTINCT u.id FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL AND r.code=? AND u.id IN ($marks)"; $params=array_merge([$kind],$ids);
         }elseif($scope==='semester_students'){
             $semester=(int)($v['semester']??0); if($semester<1||$semester>10) throw new InvalidArgumentException('Selecciona un semestre válido.');
             $sql="SELECT DISTINCT se.student_id id FROM student_enrollments se JOIN academic_periods ap ON ap.id=se.academic_period_id AND ap.status='active' JOIN users u ON u.id=se.student_id AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL WHERE se.status='active' AND se.semester=?"; $params=[$semester];
         }else{
             $role=$scope==='all_students'?'student':'teacher'; if(($v['confirm_mass']??'')!=='1') throw new InvalidArgumentException('Confirma el envío masivo antes de continuar.');
             $sql="SELECT DISTINCT u.id FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL AND r.code=?"; $params=[$role];
         }
         $q=$d->prepare($sql);$q->execute($params);$ids=array_map('intval',array_column($q->fetchAll(),'id'));if(!$ids)throw new InvalidArgumentException('No se encontraron destinatarios activos.');
         $insert=$d->prepare('INSERT INTO notifications(user_id,project_id,type,title,message,action_url,action_label,metadata) VALUES(:user,NULL,:type,:title,:message,NULL,NULL,:metadata)');$metadata=json_encode(array_filter(['admin_sender_id'=>$actor,'scope'=>$scope,'custom_type_label'=>trim((string)($v['custom_type_label']??''))]),JSON_UNESCAPED_UNICODE);foreach($ids as $id)$insert->execute(['user'=>$id,'type'=>$type,'title'=>$title,'message'=>$message,'metadata'=>$metadata]);
         $details=json_encode(['title'=>$title,'scope'=>$scope,'recipients'=>count($ids)],JSON_UNESCAPED_UNICODE);$d->prepare("INSERT INTO admin_audit_log(actor_user_id,action,entity_type,entity_id,details) VALUES(:actor,'notification_sent','notification',NULL,:details)")->execute(['actor'=>$actor,'details'=>$details]);return ['recipients'=>count($ids)];
     });
 }
 public function recipientSearch(string $kind,string $query,int $semester=0):array
 {
     if(!in_array($kind,['student','teacher','semester'],true))throw new InvalidArgumentException('Tipo de destinatario no válido.');
     $d=Database::connection();
     if($kind==='semester'){
         $q=$d->prepare("SELECT se.semester,COUNT(DISTINCT se.student_id) total FROM student_enrollments se JOIN academic_periods ap ON ap.id=se.academic_period_id AND ap.status='active' JOIN users u ON u.id=se.student_id AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL WHERE se.status='active' AND se.semester BETWEEN 1 AND 4 GROUP BY se.semester ORDER BY se.semester");$q->execute();return ['semesters'=>$q->fetchAll()];
     }
     $query=trim(mb_substr($query,0,100));if(mb_strlen($query)<2)return ['recipients'=>[]];$role=$kind;
     $sql="SELECT DISTINCT u.id,u.full_name,u.email,COALESCE(sp.institutional_code,tp.institutional_code,'') identification,".($kind==='student'?"se.semester":"NULL")." semester FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id AND r.code=:role ".($kind==='student'?"LEFT JOIN student_profiles sp ON sp.user_id=u.id LEFT JOIN teacher_profiles tp ON 1=0 LEFT JOIN student_enrollments se ON se.student_id=u.id AND se.status='active' AND se.academic_period_id=(SELECT id FROM academic_periods WHERE status='active' ORDER BY id DESC LIMIT 1)":"LEFT JOIN teacher_profiles tp ON tp.user_id=u.id LEFT JOIN student_profiles sp ON 1=0 LEFT JOIN student_enrollments se ON 1=0")." WHERE u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL AND (u.full_name LIKE :search_name OR u.email LIKE :search_email OR COALESCE(sp.institutional_code,tp.institutional_code,'') LIKE :search_identification) ORDER BY u.full_name LIMIT 12";
     $q=$d->prepare($sql);$pattern='%'.$query.'%';$q->execute(['role'=>$role,'search_name'=>$pattern,'search_email'=>$pattern,'search_identification'=>$pattern]);$rows=$q->fetchAll();
     foreach($rows as &$row){$p=$d->prepare("SELECT DISTINCT p.code,p.title,p.status FROM projects p LEFT JOIN project_participants pp ON pp.project_id=p.id AND pp.user_id=:id AND pp.status='active' AND pp.removed_at IS NULL WHERE p.deleted_at IS NULL AND (pp.user_id IS NOT NULL OR p.tutor_id=:tutor) ORDER BY p.updated_at DESC LIMIT 3");$p->execute(['id'=>$row['id'],'tutor'=>$row['id']]);$row['projects']=$p->fetchAll();$row['id']=(int)$row['id'];$row['semester']=$row['semester']===null?null:(int)$row['semester'];}unset($row);return ['recipients'=>$rows];
 }
 public function getSentNotificationsPaginated(array $filters = [], array $pagination = []): array
 {
     $d = Database::connection();
     $conditions = ["JSON_EXTRACT(n.metadata, '$.admin_sender_id') IS NOT NULL"];
     $parameters = [];

     $search = trim((string) ($filters['search'] ?? ''));
     if ($search !== '') {
         $conditions[] = '(n.title LIKE :search_title OR n.message LIKE :search_message OR p.title LIKE :search_project)';
         $parameters['search_title'] = '%' . $search . '%';
         $parameters['search_message'] = '%' . $search . '%';
         $parameters['search_project'] = '%' . $search . '%';
     }

     $type = (string) ($filters['type'] ?? '');
     if ($type !== '' && $type !== 'all') {
         $conditions[] = 'n.type = :type';
         $parameters['type'] = $type;
     }

     $projectId = (int) ($filters['project_id'] ?? 0);
     if ($projectId > 0) {
         $conditions[] = 'n.project_id = :project_id';
         $parameters['project_id'] = $projectId;
     }

     $date = (string) ($filters['date'] ?? '');
     if ($date !== '') {
         $conditions[] = 'DATE(n.created_at) = :date';
         $parameters['date'] = $date;
     }

     $dateFrom = (string) ($filters['date_from'] ?? '');
     if ($dateFrom !== '') {
         $conditions[] = 'DATE(n.created_at) >= :date_from';
         $parameters['date_from'] = $dateFrom;
     }

     $dateTo = (string) ($filters['date_to'] ?? '');
     if ($dateTo !== '') {
         $conditions[] = 'DATE(n.created_at) <= :date_to';
         $parameters['date_to'] = $dateTo;
     }

     $where = ' FROM notifications n LEFT JOIN projects p ON p.id = n.project_id WHERE ' . implode(' AND ', $conditions);

     $sqlCount = "SELECT COUNT(DISTINCT n.title, n.message, n.project_id, n.created_at, n.metadata)" . $where;
     $sqlItems = "SELECT MIN(n.id) as id, n.type, n.title, n.message, n.project_id, n.created_at, n.metadata, COUNT(*) as recipients_count, COALESCE(p.title, 'Notificacion general') as project_name " . $where . " GROUP BY n.title, n.message, n.project_id, n.created_at, n.metadata, p.title ORDER BY n.created_at DESC, id DESC";

     $result = PaginationService::run(
         $d,
         $sqlCount,
         $sqlItems,
         $parameters,
         $pagination ?: PaginationService::request('notification_page', 'notifications_per_page')
     );

     $result['items'] = array_map(function (array $row) {
         $metadata = [];
         if (!empty($row['metadata'])) {
             $decoded = json_decode((string) $row['metadata'], true);
             $metadata = is_array($decoded) ? $decoded : [];
         }
         $row['id'] = (int) $row['id'];
         $row['project_id'] = $row['project_id'] === null ? null : (int) $row['project_id'];
         $row['metadata'] = $metadata;
         $row['is_read'] = true;
         $row['recipients_count'] = (int) $row['recipients_count'];
         return $row;
     }, $result['items']);

     return $result;
 }
}
