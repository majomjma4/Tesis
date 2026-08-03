<?php

declare(strict_types=1);

final class AdminReportModel
{
    private const OPERATIONAL_STATUSES = ['development','under_review','approved','defense','tribunal_approved','published'];

    public function dashboard(string $from, string $to, array $pagination = []): array
    {
        $db = Database::connection();
        $range = ['from'=>$from.' 00:00:00','to'=>$to.' 23:59:59'];
        $summary = [
            'users'=>$this->count($db,'SELECT COUNT(*) FROM users WHERE created_at BETWEEN :from AND :to',$range),
            'projects'=>$this->count($db,'SELECT COUNT(*) FROM projects WHERE created_at BETWEEN :from AND :to',$range),
            'deliveries'=>$this->count($db,'SELECT COUNT(*) FROM project_deliveries WHERE submitted_at BETWEEN :from AND :to',$range),
            'actions'=>$this->count($db,"SELECT (SELECT COUNT(*) FROM admin_audit_log WHERE created_at BETWEEN :from1 AND :to1)+(SELECT COUNT(*) FROM project_audit_log WHERE created_at BETWEEN :from2 AND :to2)",['from1'=>$range['from'],'to1'=>$range['to'],'from2'=>$range['from'],'to2'=>$range['to']]),
        ];
        $roles = $db->query('SELECT r.name label,COUNT(DISTINCT ur.user_id) total FROM roles r LEFT JOIN user_roles ur ON ur.role_id=r.id GROUP BY r.id ORDER BY total DESC')->fetchAll();
        $statusRows = $db->query("SELECT status,COUNT(*) total FROM projects WHERE deleted_at IS NULL AND status IN ('development','under_review','approved','defense','tribunal_approved','published') GROUP BY status")->fetchAll();
        $statusCounts = array_column($statusRows,'total','status');
        $statuses = array_map(static function(string $status) use ($statusCounts): array {
            $labels = project_academic_labels($status);
            return ['code'=>$status,'label'=>$labels['status'],'total'=>(int)($statusCounts[$status]??0),'url'=>$status==='published'?route('admin-repository'):route('projects').'&status='.rawurlencode($status)];
        }, self::OPERATIONAL_STATUSES);
        $reviewCounts = (new ProjectReviewSituationService())->aggregate($db,false);
        $reviewSituations = [
            ['code'=>'pending','label'=>'Con observaciones pendientes','total'=>$reviewCounts['pending'],'url'=>route('projects').'&review_situation=pending'],
            ['code'=>'addressed','label'=>'Observaciones atendidas','total'=>$reviewCounts['addressed'],'url'=>route('projects').'&review_situation=addressed'],
            ['code'=>'none','label'=>'Sin observaciones registradas','total'=>$reviewCounts['none'],'url'=>route('projects').'&review_situation=none'],
        ];
        $params=['from1'=>$range['from'],'to1'=>$range['to'],'from2'=>$range['from'],'to2'=>$range['to']];
        $union=" FROM (SELECT a.action,a.entity_type,a.created_at,u.full_name actor FROM admin_audit_log a LEFT JOIN users u ON u.id=a.actor_user_id WHERE a.created_at BETWEEN :from1 AND :to1 UNION ALL SELECT p.action,p.entity_type,p.created_at,u.full_name actor FROM project_audit_log p LEFT JOIN users u ON u.id=p.user_id WHERE p.created_at BETWEEN :from2 AND :to2) events";
        $activity=PaginationService::run($db,'SELECT COUNT(*)'.$union,'SELECT action,entity_type,created_at,actor'.$union.' ORDER BY created_at DESC',$params,$pagination?:PaginationService::request());
        return compact('summary','roles','statuses','reviewSituations')+['activity'=>$activity['items'],'pagination'=>$activity['pagination']];
    }

    public function export(string $type, string $from, string $to): array
    {
        $db=Database::connection();
        $params=['from'=>$from.' 00:00:00','to'=>$to.' 23:59:59'];
        if($type==='users'){
            $sql="SELECT u.id,u.full_name,u.email,r.name role,u.status,u.created_at,u.last_login_at FROM users u LEFT JOIN user_roles ur ON ur.user_id=u.id LEFT JOIN roles r ON r.id=ur.role_id WHERE u.created_at BETWEEN :from AND :to ORDER BY u.created_at";
            $headers=['ID','Nombre','Correo','Rol','Estado','Creado','Último acceso'];
        }elseif($type==='projects'){
            $sql="SELECT p.code,p.title,pt.name type,c.name career,ap.name period,p.status status_code,
                CASE
                  WHEN COALESCE(review.pending_count,0)>0 THEN 'pending'
                  WHEN COALESCE(review.addressed_count,0)>0 THEN 'addressed'
                  ELSE 'none'
                END review_situation,
                u.full_name tutor,p.created_at,p.updated_at
                FROM projects p JOIN project_types pt ON pt.id=p.project_type_id JOIN careers c ON c.id=p.career_id JOIN academic_periods ap ON ap.id=p.academic_period_id LEFT JOIN users u ON u.id=p.tutor_id
                LEFT JOIN (SELECT project_id,SUM(status='pending') pending_count,SUM(status IN ('addressed','resolved')) addressed_count FROM project_observations GROUP BY project_id) review ON review.project_id=p.id
                WHERE p.created_at BETWEEN :from AND :to AND p.deleted_at IS NULL AND p.status IN ('development','under_review','approved','defense','tribunal_approved','published') ORDER BY p.created_at";
            $headers=['Código','Título','Tipo','Carrera','Periodo','Estado','Situación de revisión','Tutor','Creado','Actualizado'];
        }else{
            $sql="SELECT action,entity_type,entity_id,actor,created_at FROM (SELECT a.action,a.entity_type,a.entity_id,u.full_name actor,a.created_at FROM admin_audit_log a LEFT JOIN users u ON u.id=a.actor_user_id UNION ALL SELECT p.action,p.entity_type,p.entity_id,u.full_name actor,p.created_at FROM project_audit_log p LEFT JOIN users u ON u.id=p.user_id) audit WHERE created_at BETWEEN :from AND :to ORDER BY created_at";
            $headers=['Acción','Entidad','ID de entidad','Responsable','Fecha'];
        }
        $query=$db->prepare($sql);$query->execute($params);$rows=$query->fetchAll();
        if($type==='projects'){
            foreach($rows as &$row){
                $row['status_code']=project_academic_labels((string)$row['status_code'])['status'];
                $row['review_situation']=match((string)$row['review_situation']){'pending'=>'Observaciones pendientes','addressed'=>'Observaciones atendidas',default=>'Sin observaciones pendientes'};
            }
            unset($row);
        }
        return ['headers'=>$headers,'rows'=>$rows];
    }

    private function count(PDO $db,string $sql,array $params):int
    {
        $query=$db->prepare($sql);$query->execute($params);return (int)$query->fetchColumn();
    }
}
