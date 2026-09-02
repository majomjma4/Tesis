<?php

declare(strict_types=1);

final class AdminNotificationModel
{
    private const TYPES = ['system', 'reminder', 'status_change', 'repository'];

    public function summary(): array
    {
        $db = Database::connection();
        return ['sent' => (int) $db->query("SELECT COUNT(*) FROM admin_audit_log WHERE action='notification_sent'")->fetchColumn(), 'recipients' => (int) $db->query("SELECT COUNT(*) FROM notifications WHERE JSON_EXTRACT(metadata,'$.admin_sender_id') IS NOT NULL")->fetchColumn(), 'today' => (int) $db->query("SELECT COUNT(*) FROM notifications WHERE DATE(created_at)=CURRENT_DATE AND JSON_EXTRACT(metadata,'$.admin_sender_id') IS NOT NULL")->fetchColumn()];
    }

    public function dashboard(array $pagination = []): array
    {
        $db = Database::connection();
        $sent = PaginationService::run($db, "SELECT COUNT(*) FROM admin_audit_log WHERE action='notification_sent'", "SELECT aal.id,aal.details,aal.created_at,u.full_name sender FROM admin_audit_log aal LEFT JOIN users u ON u.id=aal.actor_user_id WHERE aal.action='notification_sent' ORDER BY aal.created_at DESC", [], $pagination ?: PaginationService::request());
        return ['users' => $db->query("SELECT id,full_name,email FROM users WHERE status='active' AND deleted_at IS NULL AND purged_at IS NULL ORDER BY full_name")->fetchAll(), 'projects' => $db->query("SELECT id,code,title FROM projects WHERE deleted_at IS NULL ORDER BY updated_at DESC")->fetchAll(), 'sent' => $sent['items'], 'pagination' => $sent['pagination'], 'summary' => $this->summary()];
    }

    public function send(array $values, int $actor): array
    {
        $scope = (string) ($values['scope'] ?? ''); $type = (string) ($values['type'] ?? ''); $title = trim((string) ($values['title'] ?? '')); $message = trim((string) ($values['message'] ?? '')); $projectId = (int) ($values['project_id'] ?? 0);
        if (!in_array($scope, ['user', 'project', 'role', 'all'], true) || !in_array($type, self::TYPES, true)) throw new InvalidArgumentException('Selecciona un alcance y tipo válidos.');
        if (mb_strlen($title) < 4 || mb_strlen($title) > 180 || mb_strlen($message) < 8 || mb_strlen($message) > 2000) throw new InvalidArgumentException('Completa el título y el mensaje.');
        if ($scope === 'all' && ($type !== 'system' || ($values['confirm_all'] ?? '') !== '1')) throw new InvalidArgumentException('Los avisos globales requieren confirmación explícita.');
        return Database::transaction(function (PDO $db) use ($values, $actor, $scope, $type, $title, $message, $projectId): array {
            $parameters = [];
            if ($scope === 'user') {
                $sql = "SELECT u.id FROM users u WHERE u.id=:id AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL";
                $parameters = ['id' => (int) ($values['user_id'] ?? 0)];
            } elseif ($scope === 'role') {
                $role = (string) ($values['role'] ?? '');
                if (!in_array($role, ['student', 'teacher', 'administrator'], true)) throw new InvalidArgumentException('Selecciona un rol válido.');
                if ($role === 'administrator') {
                    $sql = "SELECT DISTINCT u.id FROM users u WHERE u.status='active' AND u.is_admin=1 AND u.deleted_at IS NULL AND u.purged_at IS NULL";
                    $parameters = [];
                } else {
                    $sql = "SELECT DISTINCT u.id FROM users u INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN roles r ON r.id=ur.role_id AND r.code=:role WHERE u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL";
                    $parameters = ['role' => $role];
                }
            } elseif ($scope === 'project') {
                if ($projectId < 1) throw new InvalidArgumentException('Selecciona un proyecto.');
                $sql = "SELECT DISTINCT recipients.id FROM (SELECT created_by id FROM projects WHERE id=:p1 AND deleted_at IS NULL UNION SELECT tutor_id FROM projects WHERE id=:p2 AND tutor_id IS NOT NULL UNION SELECT user_id FROM project_participants WHERE project_id=:p3 AND status='active') recipients INNER JOIN users u ON u.id=recipients.id AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL";
                $parameters = ['p1' => $projectId, 'p2' => $projectId, 'p3' => $projectId];
            } else {
                $sql = "SELECT u.id FROM users u WHERE u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL";
            }
            $query = $db->prepare($sql); $query->execute($parameters);
            $recipientIds = array_values(array_unique(array_map('intval', array_column($query->fetchAll(), 'id'))));
            if ($recipientIds === []) throw new InvalidArgumentException('No se encontraron destinatarios activos.');
            $insert = $db->prepare('INSERT INTO notifications(user_id,project_id,type,title,message,action_url,action_label,metadata) VALUES(:user,:project,:type,:title,:message,:url,:label,:metadata)');
            $url = $projectId > 0 ? route('project-detail') . '&id=' . $projectId : null;
            $metadata = json_encode(['admin_sender_id' => $actor, 'scope' => $scope], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            foreach ($recipientIds as $recipientId) $insert->execute(['user' => $recipientId, 'project' => $projectId ?: null, 'type' => $type, 'title' => $title, 'message' => $message, 'url' => $url, 'label' => $url ? 'Abrir proyecto' : null, 'metadata' => $metadata]);
            $details = json_encode(['title' => $title, 'scope' => $scope, 'recipients' => count($recipientIds), 'project_id' => $projectId ?: null], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $db->prepare("INSERT INTO admin_audit_log(actor_user_id,action,entity_type,entity_id,details) VALUES(:actor,'notification_sent','notification',NULL,:details)")->execute(['actor' => $actor, 'details' => $details]);
            return ['recipients' => count($recipientIds)];
        });
    }

    public function sendAudience(array $values, int $actor): array
    {
        $scope=(string)($values['scope']??''); $type=(string)($values['type']??''); $title=trim((string)($values['title']??'')); $message=trim((string)($values['message']??''));
        if(!in_array($scope,['student_one','student_many','teacher_one','teacher_many','semester_students','all_students','all_teachers'],true)||!in_array($type,self::TYPES,true))throw new InvalidArgumentException('Selecciona destinatarios y tipo válidos.');
        if(mb_strlen($title)<4||mb_strlen($title)>180||mb_strlen($message)<8||mb_strlen($message)>2000)throw new InvalidArgumentException('Completa el título y el mensaje.');
        return Database::transaction(function(PDO $db)use($values,$actor,$scope,$type,$title,$message):array{
            $parameters=[];
            if(in_array($scope,['student_one','student_many','teacher_one','teacher_many'],true)){$kind=str_starts_with($scope,'student')?'student':'teacher';$ids=array_values(array_unique(array_filter(array_map('intval',(array)($values['recipient_ids']??[])))));if(!$ids)throw new InvalidArgumentException('Selecciona al menos un destinatario.');$marks=implode(',',array_fill(0,count($ids),'?'));$sql="SELECT DISTINCT u.id FROM users u INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN roles r ON r.id=ur.role_id WHERE u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL AND r.code=? AND u.id IN ($marks)";$parameters=array_merge([$kind],$ids);
            }elseif($scope==='semester_students'){$semester=(int)($values['semester']??0);if($semester<1||$semester>10)throw new InvalidArgumentException('Selecciona un semestre válido.');$sql="SELECT DISTINCT se.student_id id FROM student_enrollments se INNER JOIN academic_periods ap ON ap.id=se.academic_period_id AND ap.status='active' INNER JOIN users u ON u.id=se.student_id AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL WHERE se.status='active' AND se.semester=?";$parameters=[$semester];
            }else{$role=$scope==='all_students'?'student':'teacher';if(($values['confirm_mass']??'')!=='1')throw new InvalidArgumentException('Confirma el envío masivo antes de continuar.');$sql="SELECT DISTINCT u.id FROM users u INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN roles r ON r.id=ur.role_id WHERE u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL AND r.code=?";$parameters=[$role];}
            $query=$db->prepare($sql);$query->execute($parameters);$recipientIds=array_values(array_unique(array_map('intval',array_column($query->fetchAll(),'id'))));if(!$recipientIds)throw new InvalidArgumentException('No se encontraron destinatarios activos.');$insert=$db->prepare('INSERT INTO notifications(user_id,project_id,type,title,message,action_url,action_label,metadata) VALUES(:user,NULL,:type,:title,:message,NULL,NULL,:metadata)');$metadata=json_encode(array_filter(['admin_sender_id'=>$actor,'scope'=>$scope,'custom_type_label'=>trim((string)($values['custom_type_label']??''))]),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);foreach($recipientIds as $recipientId)$insert->execute(['user'=>$recipientId,'type'=>$type,'title'=>$title,'message'=>$message,'metadata'=>$metadata]);$details=json_encode(['title'=>$title,'scope'=>$scope,'recipients'=>count($recipientIds)],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);$db->prepare("INSERT INTO admin_audit_log(actor_user_id,action,entity_type,entity_id,details) VALUES(:actor,'notification_sent','notification',NULL,:details)")->execute(['actor'=>$actor,'details'=>$details]);return ['recipients'=>count($recipientIds)];
        });
    }

    public function recipientSearch(string $kind,string $query,int $semester=0):array
    {
        if(!in_array($kind,['student','teacher','semester'],true))throw new InvalidArgumentException('Tipo de destinatario no válido.');$db=Database::connection();
        if($kind==='semester'){$q=$db->prepare("SELECT se.semester,COUNT(DISTINCT se.student_id) total FROM student_enrollments se INNER JOIN academic_periods ap ON ap.id=se.academic_period_id AND ap.status='active' INNER JOIN users u ON u.id=se.student_id AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL WHERE se.status='active' AND se.semester BETWEEN 1 AND 4 GROUP BY se.semester ORDER BY se.semester");$q->execute();return ['semesters'=>$q->fetchAll()];}
        $query=trim(mb_substr($query,0,100));if(mb_strlen($query)<2)return ['recipients'=>[]];$sql="SELECT DISTINCT u.id,u.full_name,u.email,COALESCE(sp.institutional_code,tp.institutional_code,'') identification,".($kind==='student'?"se.semester":"NULL")." semester FROM users u INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN roles r ON r.id=ur.role_id AND r.code=:role ".($kind==='student'?"LEFT JOIN student_profiles sp ON sp.user_id=u.id LEFT JOIN teacher_profiles tp ON 1=0 LEFT JOIN student_enrollments se ON se.student_id=u.id AND se.status='active' AND se.academic_period_id=(SELECT id FROM academic_periods WHERE status='active' ORDER BY id DESC LIMIT 1)":"LEFT JOIN teacher_profiles tp ON tp.user_id=u.id LEFT JOIN student_profiles sp ON 1=0 LEFT JOIN student_enrollments se ON 1=0")." WHERE u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL AND (u.full_name LIKE :search_name OR u.email LIKE :search_email OR COALESCE(sp.institutional_code,tp.institutional_code,'') LIKE :search_identification) ORDER BY u.full_name LIMIT 12";$q=$db->prepare($sql);$pattern='%'.$query.'%';$q->execute(['role'=>$kind,'search_name'=>$pattern,'search_email'=>$pattern,'search_identification'=>$pattern]);$rows=$q->fetchAll();foreach($rows as &$row){$p=$db->prepare("SELECT DISTINCT p.code,p.title,p.status FROM projects p LEFT JOIN project_participants pp ON pp.project_id=p.id AND pp.user_id=:id AND pp.status='active' AND pp.removed_at IS NULL WHERE p.deleted_at IS NULL AND (pp.user_id IS NOT NULL OR p.tutor_id=:tutor) ORDER BY p.updated_at DESC LIMIT 3");$p->execute(['id'=>$row['id'],'tutor'=>$row['id']]);$row['projects']=$p->fetchAll();$row['id']=(int)$row['id'];$row['semester']=$row['semester']===null?null:(int)$row['semester'];}unset($row);return ['recipients'=>$rows];
    }

    public function getSentNotificationsPaginated(array $filters=[],array $pagination=[]):array
    {
        $db=Database::connection();$conditions=["JSON_EXTRACT(n.metadata, '$.admin_sender_id') IS NOT NULL"];$parameters=[];$search=trim((string)($filters['search']??''));if($search!==''){$conditions[]='(n.title LIKE :search_title OR n.message LIKE :search_message OR p.title LIKE :search_project)';$parameters['search_title']='%'.$search.'%';$parameters['search_message']='%'.$search.'%';$parameters['search_project']='%'.$search.'%';}$type=(string)($filters['type']??'');if($type!==''&&$type!=='all'){$conditions[]='n.type=:type';$parameters['type']=$type;}$projectId=(int)($filters['project_id']??0);if($projectId>0){$conditions[]='n.project_id=:project_id';$parameters['project_id']=$projectId;}foreach(['date'=>'=','date_from'=>'>=','date_to'=>'<='] as $key=>$operator){$value=(string)($filters[$key]??'');if($value!==''){$conditions[]="DATE(n.created_at) {$operator} :{$key}";$parameters[$key]=$value;}}$where=' FROM notifications n LEFT JOIN projects p ON p.id=n.project_id WHERE '.implode(' AND ',$conditions);$groupBy=' GROUP BY n.title,n.message,n.project_id,n.created_at,n.metadata,p.title';$countSql='SELECT COUNT(*) FROM (SELECT n.title,n.message,n.project_id,n.created_at,n.metadata,p.title AS project_title'.$where.$groupBy.') sent_groups';$result=PaginationService::run($db,$countSql,"SELECT MIN(n.id) id,n.type,n.title,n.message,n.project_id,n.created_at,n.metadata,COUNT(*) recipients_count,COALESCE(p.title,'Notificacion general') project_name".$where.$groupBy.' ORDER BY n.created_at DESC,id DESC',$parameters,$pagination?:PaginationService::request('notification_page','notifications_per_page'));$result['items']=array_map(static function(array $row):array{$row['id']=(int)$row['id'];$row['project_id']=$row['project_id']===null?null:(int)$row['project_id'];$decoded=!empty($row['metadata'])?json_decode((string)$row['metadata'],true):[];$row['metadata']=is_array($decoded)?$decoded:[];$row['is_read']=true;$row['recipients_count']=(int)$row['recipients_count'];return $row;},$result['items']);return $result;
    }
}
