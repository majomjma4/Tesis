<?php
declare(strict_types=1);

final class AdminActivityService
{
    public function __construct(private ?PDO $db = null) {}

    public function record(int $actorId,string $action,string $actionLabel,string $module,string $entityType,?int $entityId,string $elementLabel,string $result='correct',array $details=[]):void
    {
        $connection=$this->db??Database::connection();
        $statement=$connection->prepare('INSERT INTO admin_audit_log (actor_user_id,action,action_label,module,entity_type,entity_id,element_label,result,details,ip_address,user_agent) VALUES (:actor,:action,:label,:module,:type,:id,:element,:result,:details,:ip,:agent)');
        $statement->execute([
            'actor'=>$actorId>0?$actorId:null,'action'=>mb_substr($action,0,100),'label'=>mb_substr($actionLabel,0,180),
            'module'=>mb_substr($module,0,80),'type'=>mb_substr($entityType,0,80),'id'=>$entityId,
            'element'=>mb_substr($elementLabel,0,255),'result'=>$result==='failed'?'failed':'correct',
            'details'=>json_encode($details,JSON_UNESCAPED_UNICODE),
            'ip'=>mb_substr((string)($_SERVER['REMOTE_ADDR']??''),0,45)?:null,
            'agent'=>mb_substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500)?:null,
        ]);
    }

    public function recordFailure(int $actorId,string $action,string $actionLabel,string $module,string $entityType,?int $entityId,string $elementLabel,Throwable $error):void
    {
        try{$this->record($actorId,$action,$actionLabel,$module,$entityType,$entityId,$elementLabel,'failed',['reason'=>mb_substr($error->getMessage(),0,500)]);}
        catch(Throwable $loggingError){error_log('Admin activity log failure: '.$loggingError->getMessage());}
    }
}
