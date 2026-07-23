<?php
declare(strict_types=1);

final class AcademicPeriodReminderService
{
    public function __construct(private ?PDO $db=null,private ?DateTimeImmutable $today=null){}

    public function sync():int
    {
        $db=$this->db??Database::connection();
        $today=$this->today??new DateTimeImmutable('today');
        $periods=$db->query("SELECT id,name,starts_on,ends_on,status FROM academic_periods WHERE status IN ('active','planned')")->fetchAll();
        $events=[];
        foreach($periods as $period){
            if($period['status']==='active')$events=array_merge($events,$this->activeEvents($period,$today));
            if($period['status']==='planned')$events=array_merge($events,$this->plannedEvents($period,$today));
        }
        if(!$events)return 0;
        $admins=$db->query("SELECT DISTINCT u.id FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.code='administrator' AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL")->fetchAll(PDO::FETCH_COLUMN);
        if(!$admins)return 0;
        $insert=$db->prepare("INSERT IGNORE INTO notifications(user_id,type,title,message,action_url,action_label,metadata,deduplication_key) VALUES(:user,'reminder',:title,:message,:url,'Gestionar período',:metadata,:dedup)");
        $created=0;
        foreach($admins as $adminId)foreach($events as $event){
            $dedup='academic-period:'.(int)$event['period_id'].':'.$event['key'].':'.$event['event_date'];
            $insert->execute([
                'user'=>(int)$adminId,'title'=>$event['title'],'message'=>$event['message'],
                'url'=>route('admin-academic'),
                'metadata'=>json_encode(['source'=>'academic_period_reminder','event'=>$event['key'],'event_date'=>$event['event_date'],'period_id'=>(int)$event['period_id'],'period_name'=>$event['period_name']],JSON_UNESCAPED_UNICODE),
                'dedup'=>$dedup,
            ]);
            $created+=$insert->rowCount();
        }
        return $created;
    }

    private function activeEvents(array $period,DateTimeImmutable $today):array
    {
        $end=new DateTimeImmutable((string)$period['ends_on']);
        $days=(int)$today->diff($end)->format('%r%a');
        if($days>=1&&$days<=7)return [$this->event($period,'active_end_upcoming',(string)$period['ends_on'],'El período finaliza pronto',$period['name'].' finalizará en '.$days.' '.($days===1?'día':'días').'. Revisa o planifica el siguiente período; el cierre seguirá siendo manual.')];
        if($days===0)return [$this->event($period,'active_end_today',(string)$period['ends_on'],'El período académico finaliza hoy',$period['name'].' finaliza hoy. Confirma la planificación antes de realizar el cierre manual.')];
        if($days<0)return [$this->event($period,'active_end_overdue',(string)$period['ends_on'],'El período académico está pendiente de cierre',$period['name'].' superó su fecha de finalización. Revisa la planificación y realiza el cierre manual cuando corresponda.')];
        return [];
    }

    private function plannedEvents(array $period,DateTimeImmutable $today):array
    {
        $start=new DateTimeImmutable((string)$period['starts_on']);
        $days=(int)$today->diff($start)->format('%r%a');
        if($days>=1&&$days<=7)return [$this->event($period,'planned_start_upcoming',(string)$period['starts_on'],'El siguiente período inicia pronto',$period['name'].' comenzará en '.$days.' '.($days===1?'día':'días').'. Revisa si corresponde cerrar el período actual.')];
        if($days===0)return [$this->event($period,'planned_start_today',(string)$period['starts_on'],'El período planificado inicia hoy',$period['name'].' inicia hoy. El cambio de período debe confirmarse manualmente desde Gestión académica.')];
        if($days<0)return [$this->event($period,'planned_start_overdue',(string)$period['starts_on'],'El período planificado está pendiente de activación',$period['name'].' ya alcanzó su fecha de inicio y continúa planificado. Revisa el cierre manual del período actual.')];
        return [];
    }

    private function event(array $period,string $key,string $eventDate,string $title,string $message):array
    {
        return ['period_id'=>(int)$period['id'],'period_name'=>(string)$period['name'],'key'=>$key,'event_date'=>$eventDate,'title'=>$title,'message'=>$message];
    }
}
