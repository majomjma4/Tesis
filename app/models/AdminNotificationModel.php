<?php
declare(strict_types=1);
final class AdminNotificationModel
{
 private const TYPES=['system','reminder','status_change','repository'];
 public function dashboard(array $pagination=[]):array{$d=Database::connection();$sent=PaginationService::run($d,"SELECT COUNT(*) FROM admin_audit_log WHERE action='notification_sent'","SELECT aal.id,aal.details,aal.created_at,u.full_name sender FROM admin_audit_log aal LEFT JOIN users u ON u.id=aal.actor_user_id WHERE aal.action='notification_sent' ORDER BY aal.created_at DESC",[],$pagination?:PaginationService::request());return ['users'=>$d->query("SELECT id,full_name,email FROM users WHERE status='active' ORDER BY full_name")->fetchAll(),'projects'=>$d->query("SELECT id,code,title FROM projects WHERE deleted_at IS NULL ORDER BY updated_at DESC")->fetchAll(),'sent'=>$sent['items'],'pagination'=>$sent['pagination'],'summary'=>['sent'=>(int)$d->query("SELECT COUNT(*) FROM admin_audit_log WHERE action='notification_sent'")->fetchColumn(),'recipients'=>(int)$d->query("SELECT COUNT(*) FROM notifications WHERE JSON_EXTRACT(metadata,'$.admin_sender_id') IS NOT NULL")->fetchColumn(),'today'=>(int)$d->query("SELECT COUNT(*) FROM notifications WHERE DATE(created_at)=CURRENT_DATE AND JSON_EXTRACT(metadata,'$.admin_sender_id') IS NOT NULL")->fetchColumn()]];}
 public function send(array $v,int $actor):array{$scope=(string)($v['scope']??'');$type=(string)($v['type']??'');$title=trim((string)($v['title']??''));$message=trim((string)($v['message']??''));$projectId=(int)($v['project_id']??0);if(!in_array($scope,['user','project','role','all'],true)||!in_array($type,self::TYPES,true))throw new InvalidArgumentException('Selecciona un alcance y tipo válidos.');if(mb_strlen($title)<4||mb_strlen($title)>180||mb_strlen($message)<8||mb_strlen($message)>2000)throw new InvalidArgumentException('Completa el título y el mensaje.');if($scope==='all'&&($type!=='system'||($v['confirm_all']??'')!=='1'))throw new InvalidArgumentException('Los avisos globales deben ser del sistema y requieren confirmación explícita.');return Database::transaction(function(PDO $d)use($v,$actor,$scope,$type,$title,$message,$projectId):array{$sql='';$params=[];if($scope==='user'){$sql="SELECT id FROM users WHERE id=:id AND status='active'";$params=['id'=>(int)($v['user_id']??0)];}elseif($scope==='role'){$role=(string)($v['role']??'');if(!in_array($role,['student','teacher','administrator'],true))throw new InvalidArgumentException('Selecciona un rol válido.');if($role==='administrator'){$sql="SELECT id FROM users WHERE status='active' AND is_admin=1 AND deleted_at IS NULL AND purged_at IS NULL";}else{$sql="SELECT DISTINCT u.id FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE u.status='active' AND r.code=:role";$params=['role'=>$role];}}elseif($scope==='project'){if($projectId<1)throw new InvalidArgumentException('Selecciona un proyecto.');$sql="SELECT DISTINCT id FROM (SELECT created_by id FROM projects WHERE id=:p1 AND deleted_at IS NULL UNION SELECT tutor_id FROM projects WHERE id=:p2 AND tutor_id IS NOT NULL UNION SELECT user_id FROM project_participants WHERE project_id=:p3 AND status='active') recipients";$params=['p1'=>$projectId,'p2'=>$projectId,'p3'=>$projectId];}else{$sql="SELECT id FROM users WHERE status='active'";}$q=$d->prepare($sql);$q->execute($params);$ids=array_map('intval',array_column($q->fetchAll(),'id'));if(!$ids)throw new InvalidArgumentException('No se encontraron destinatarios activos.');$insert=$d->prepare('INSERT INTO notifications(user_id,project_id,type,title,message,action_url,action_label,metadata) VALUES(:user,:project,:type,:title,:message,:url,:label,:metadata)');$url=$projectId?route('project-detail').'&id='.$projectId:null;$metadata=json_encode(['admin_sender_id'=>$actor,'scope'=>$scope],JSON_UNESCAPED_UNICODE);foreach($ids as $id)$insert->execute(['user'=>$id,'project'=>$projectId?:null,'type'=>$type,'title'=>$title,'message'=>$message,'url'=>$url,'label'=>$url?'Abrir proyecto':null,'metadata'=>$metadata]);$details=json_encode(['title'=>$title,'scope'=>$scope,'recipients'=>count($ids),'project_id'=>$projectId?:null],JSON_UNESCAPED_UNICODE);$d->prepare("INSERT INTO admin_audit_log(actor_user_id,action,entity_type,entity_id,details) VALUES(:actor,'notification_sent','notification',NULL,:details)")->execute(['actor'=>$actor,'details'=>$details]);return ['recipients'=>count($ids)];});}
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
