<?php

declare(strict_types=1);

/** Emite avisos académicos dentro de la transacción que confirmó el cambio. */
final class ProjectAcademicNotificationService
{
    public function tutorAssigned(PDO $db, int $projectId, string $code, string $title, array $addedTutors, int $actorId): void
    {
        foreach ($addedTutors as $tutor) {
            $userId = (int) ($tutor['user_id'] ?? 0);
            if ($userId < 1) continue;
            $name = trim((string) ($tutor['name'] ?? 'El docente asignado'));
            $metadata = $this->metadata($code, $title, ['assignment'=>'tutor','tutor_id'=>$userId]);
            if ($userId !== $actorId) $this->forUser($db,$userId,$projectId,'review','Has sido asignado como tutor','Has sido asignado como tutor del proyecto '.$this->label($code,$title),'project-tutor-assigned:'.$projectId.':'.$userId,$metadata,'Ver proyecto');
            $this->forStudents($db,$projectId,'system','Tutor asignado',$name.' ha sido asignado como tutor del proyecto '.$this->label($code,$title),'project-tutor-assigned-students:'.$projectId.':'.$userId,$metadata,'Ver proyecto');
        }
    }

    public function tribunalAssigned(PDO $db, int $projectId, string $code, string $title, array $addedTeachers, int $actorId): void
    {
        foreach ($addedTeachers as $teacher) {
            $userId = (int) ($teacher['user_id'] ?? 0);
            if ($userId < 1) continue;
            $metadata = $this->metadata($code, $title, ['assignment'=>'tribunal','teacher_id'=>$userId]);
            if ($userId !== $actorId) $this->forUser($db,$userId,$projectId,'tribunal','Asignación a tribunal','Has sido asignado al tribunal del proyecto '.$this->label($code,$title),'project-tribunal-assigned:'.$projectId.':'.$userId,$metadata,'Ver tribunal','participants');
            $this->forStudents($db,$projectId,'tribunal','Tribunal asignado','El tribunal del proyecto '.$this->label($code,$title).' fue conformado o actualizado.','project-tribunal-assigned-students:'.$projectId.':'.$userId,$metadata,'Ver tribunal','participants');
        }
    }

    public function finalApproval(PDO $db, int $projectId, string $code, string $title, string $status, string $statusLabel, int $auditId): void
    {
        if ($auditId < 1) return;
        $metadata = $this->metadata($code, $title, ['transition'=>$status,'audit_id'=>$auditId]);
        $this->forStudents($db,$projectId,'status_change','Proyecto '.mb_strtolower($statusLabel),'El proyecto '.$this->label($code,$title).' alcanzó el estado “'.$statusLabel.'”.','project-final-status:'.$projectId.':'.$status.':'.$auditId,$metadata,'Ver proyecto');
    }

    public function defenseStarted(PDO $db,int $projectId,string $code,string $title,int $actorId): void
    {
        $metadata=$this->metadata($code,$title,['transition'=>'defense']);
        $this->forStudents($db,$projectId,'tribunal','Etapa de defensa iniciada','El proyecto '.$this->label($code,$title).' fue enviado a Tribunal para su defensa.','project-defense-started-students:'.$projectId,$metadata,'Ver proyecto');
        $q=$db->prepare("INSERT IGNORE INTO notifications(user_id,project_id,type,title,message,action_url,action_label,metadata,deduplication_key) SELECT DISTINCT pp.user_id,:project,'tribunal','Etapa de defensa iniciada',:message,:url,'Ver proyecto',:metadata,CONCAT('project-defense-started-member:',:project,':',pp.user_id) FROM project_participants pp JOIN users u ON u.id=pp.user_id WHERE pp.project_id=:participants AND LOWER(pp.role_code) IN ('tribunal','jury') AND pp.status='active' AND pp.removed_at IS NULL AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL");
        $q->execute(['project'=>$projectId,'message'=>'El proyecto '.$this->label($code,$title).' inició la etapa de defensa.','url'=>route('project-detail').'&id='.$projectId,'metadata'=>json_encode($metadata,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'participants'=>$projectId]);
    }

    private function forStudents(PDO $db,int $projectId,string $type,string $title,string $message,string $dedup,array $metadata,string $actionLabel,string $tab='summary'): void
    {
        $q=$db->prepare("INSERT IGNORE INTO notifications(user_id,project_id,type,title,message,action_url,action_label,metadata,deduplication_key) SELECT DISTINCT pp.user_id,:project,:type,:title,:message,:url,:label,:metadata,:dedup FROM project_participants pp INNER JOIN users u ON u.id=pp.user_id WHERE pp.project_id=:participants AND pp.role_code='student' AND pp.status='active' AND pp.removed_at IS NULL AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL");
        $q->execute(['project'=>$projectId,'type'=>$type,'title'=>$title,'message'=>$message,'url'=>route('project-detail').'&id='.$projectId.'&tab='.$tab,'label'=>$actionLabel,'metadata'=>json_encode($metadata,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'dedup'=>$dedup,'participants'=>$projectId]);
    }

    private function forUser(PDO $db,int $userId,int $projectId,string $type,string $title,string $message,string $dedup,array $metadata,string $actionLabel,string $tab='summary'): void
    {
        $q=$db->prepare("INSERT IGNORE INTO notifications(user_id,project_id,type,title,message,action_url,action_label,metadata,deduplication_key) SELECT u.id,:project,:type,:title,:message,:url,:label,:metadata,:dedup FROM users u WHERE u.id=:user AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL");
        $q->execute(['user'=>$userId,'project'=>$projectId,'type'=>$type,'title'=>$title,'message'=>$message,'url'=>route('project-detail').'&id='.$projectId.'&tab='.$tab,'label'=>$actionLabel,'metadata'=>json_encode($metadata,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'dedup'=>$dedup]);
    }

    private function metadata(string $code,string $title,array $extra): array { return $extra+['project_code'=>$code,'project_name'=>$title,'source'=>isset($extra['transition'])?'academic_status_transition':'academic_assignment']; }
    private function label(string $code,string $title): string { return $code!==''?$code.' · '.$title:$title; }
}
