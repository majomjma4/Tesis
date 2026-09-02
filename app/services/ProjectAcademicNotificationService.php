<?php
declare(strict_types=1);

final class ProjectAcademicNotificationService
{
    /** Notifica al tutor únicamente después de que el registro inicial quedó confirmado. */
    public function notifyProjectRegisteredForReview(PDO $db,int $projectId,int $tutorId,string $code,string $title):void
    {
        if($projectId<1||$tutorId<1)return;
        $metadata=$this->metadata($code,$title,['event'=>'project_registered_for_review','tutor_id'=>$tutorId]);
        $this->forUser($db,$tutorId,$projectId,'review','Nueva entrega para revisión','Se ha registrado el proyecto para tu revisión.','project-registered-for-review:'.$projectId.':'.$tutorId,$metadata,'Revisar proyecto');
    }
    /** Notifica a todos los tutores activos tras una entrega formal confirmada. */
    public function projectSubmittedForReview(PDO $db,int $projectId,string $code,string $title,int $deliveryNumber,int $fileCount):void
    {
        if($projectId<1||$deliveryNumber<1||$fileCount<1)return;
        $q=$db->prepare("SELECT DISTINCT pp.user_id FROM project_participants pp INNER JOIN users u ON u.id=pp.user_id WHERE pp.project_id=:project AND LOWER(pp.role_code) IN ('tutor','cotutor','co_tutor','co-tutor') AND pp.status='active' AND pp.removed_at IS NULL AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL");
        $q->execute(['project'=>$projectId]);
        foreach($q->fetchAll(PDO::FETCH_COLUMN) as $tutorId){$id=(int)$tutorId;$metadata=$this->metadata($code,$title,['event'=>'project_submitted_for_review','delivery_number'=>$deliveryNumber,'file_count'=>$fileCount]);$message = "Se ha enviado una nueva entrega para tu revisión. Incluye " . $fileCount . " " . ($fileCount === 1 ? "documento pendiente" : "documentos pendientes") . " de revisar.";$this->forUser($db,$id,$projectId,'review','Nueva entrega para revisión',$message,'project-submitted-for-review:'.$projectId.':'.$deliveryNumber.':'.$id,$metadata,'Revisar proyecto');}
    }
    public function tutorAssigned(PDO $db,int $projectId,string $code,string $title,array $addedTutors,int $actorId):void { foreach($addedTutors as $tutor){$userId=(int)($tutor['user_id']??0);if($userId<1)continue;$metadata=$this->metadata($code,$title,['assignment'=>'tutor','tutor_id'=>$userId]);if($userId!==$actorId)$this->forUser($db,$userId,$projectId,'review','Asignación como tutor','Has sido asignado como tutor de este proyecto.','project-tutor-assigned:'.$projectId.':'.$userId,$metadata,'Ver proyecto');$this->forStudents($db,$projectId,'system','Tutor asignado',trim((string)($tutor['name']??'El docente asignado')).' ha sido asignado como tutor del proyecto.','project-tutor-assigned-students:'.$projectId.':'.$userId,$metadata,'Ver proyecto');} }
    public function tribunalAssigned(PDO $db,int $projectId,string $code,string $title,array $addedTeachers,int $actorId):void {
        if($addedTeachers===[])return;$metadata=$this->metadata($code,$title,['assignment'=>'tribunal']);
        foreach($addedTeachers as $teacher){$userId=(int)($teacher['user_id']??0);$position=(string)($teacher['tribunal_position']??'');$label=['president'=>'Presidente','member_1'=>'Miembro 1','member_2'=>'Miembro 2'][$position]??'integrante';if($userId>0&&$userId!==$actorId)$this->forUser($db,$userId,$projectId,'tribunal','Asignación a tribunal','Has sido asignado como '.$label.' del Tribunal de este proyecto.','project-tribunal-assigned:'.$projectId.':'.$position.':'.$userId,$metadata,'Ver tribunal','participants');}
        $this->forStudents($db,$projectId,'tribunal','Tribunal asignado','El Tribunal evaluador ha sido conformado. El proyecto avanza a la etapa de defensa.','project-tribunal-assigned-students:'.$projectId,$metadata,'Ver tribunal','participants');
        $q=$db->prepare("SELECT DISTINCT user_id FROM project_participants WHERE project_id=:project AND status='active' AND removed_at IS NULL AND LOWER(role_code) IN ('tutor','cotutor','co_tutor','co-tutor')");$q->execute(['project'=>$projectId]);foreach($q->fetchAll(PDO::FETCH_COLUMN) as $userId)$this->forUser($db,(int)$userId,$projectId,'tribunal','Tribunal asignado','El Tribunal evaluador del proyecto ha sido conformado.','project-tribunal-assigned-tutor:'.$projectId.':'.(int)$userId,$metadata,'Ver proyecto');
    }
    public function tribunalUpdated(PDO $db,int $projectId,string $code,string $title,array $addedTeachers,int $actorId,int $auditId):void {
        if($projectId<1||$auditId<1)return;$metadata=$this->metadata($code,$title,['assignment'=>'tribunal_updated','audit_id'=>$auditId]);
        foreach($addedTeachers as $teacher){$userId=(int)($teacher['user_id']??0);$position=(string)($teacher['tribunal_position']??'');$label=['president'=>'Presidente','member_1'=>'Miembro 1','member_2'=>'Miembro 2'][$position]??'integrante';if($userId>0&&$userId!==$actorId)$this->forUserIdempotent($db,$userId,$projectId,'tribunal','Tribunal actualizado','Has sido incorporado como '.$label.' del Tribunal de este proyecto.','project-tribunal-updated-member:'.$auditId.':'.$projectId.':'.$position.':'.$userId,$metadata,'Ver tribunal','participants');}
        $this->forStudents($db,$projectId,'tribunal','Tribunal actualizado','La composición del Tribunal evaluador ha sido actualizada.','project-tribunal-updated-students:'.$auditId.':'.$projectId,$metadata,'Ver tribunal','participants');
        $q=$db->prepare("SELECT DISTINCT user_id FROM project_participants WHERE project_id=:project AND status='active' AND removed_at IS NULL AND LOWER(role_code) IN ('tutor','cotutor','co_tutor','co-tutor')");$q->execute(['project'=>$projectId]);foreach($q->fetchAll(PDO::FETCH_COLUMN) as $userId)$this->forUserIdempotent($db,(int)$userId,$projectId,'tribunal','Tribunal actualizado','La composición del Tribunal evaluador del proyecto ha sido actualizada.','project-tribunal-updated-tutor:'.$auditId.':'.$projectId.':'.(int)$userId,$metadata,'Ver proyecto');
    }
    public function finalApproval(PDO $db,int $projectId,string $code,string $title,string $status,string $statusLabel,int $auditId):void {if($auditId<1)return;$metadata=$this->metadata($code,$title,['transition'=>$status,'audit_id'=>$auditId]);$this->forStudents($db,$projectId,'status_change','Estado de proyecto actualizado','El proyecto ha alcanzado el estado de “'.mb_strtolower($statusLabel).'”.','project-final-status:'.$projectId.':'.$status.':'.$auditId,$metadata,'Ver proyecto');}
    /** Notifica la publicación final a estudiantes activos y al tutor relacionado. */
    public function projectPublished(PDO $db,int $projectId,string $code,string $title,int $auditId):void {if($auditId<1)return;$metadata=$this->metadata($code,$title,['transition'=>'published','audit_id'=>$auditId]);$message='El documento final ha sido publicado correctamente en el Repositorio Académico.';$this->forStudents($db,$projectId,'repository','Proyecto publicado',$message,'project-published-students:'.$projectId.':'.$auditId,$metadata,'Ver en Repositorio');$tutors=$db->prepare("SELECT DISTINCT pp.user_id FROM project_participants pp INNER JOIN users u ON u.id=pp.user_id WHERE pp.project_id=:project AND LOWER(pp.role_code) IN ('tutor','cotutor','co_tutor','co-tutor') AND pp.status='active' AND pp.removed_at IS NULL AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL");$tutors->execute(['project'=>$projectId]);foreach($tutors->fetchAll(PDO::FETCH_COLUMN) as $tutorId)$this->forUser($db,(int)$tutorId,$projectId,'repository','Proyecto publicado',$message,'project-published-tutor:'.$projectId.':'.(int)$tutorId.':'.$auditId,$metadata,'Ver en Repositorio');}
    /** Informa a administración de una edición realizada después de publicar. */
    public function postPublicationModification(PDO $db,int $projectId,int $actorId,int $auditId,string $scope):void
    {
        if ($projectId < 1 || $actorId < 1 || $auditId < 1 || trim($scope) === '') return;
        $project = $db->prepare('SELECT code,title,published_at FROM projects WHERE id=:project AND deleted_at IS NULL LIMIT 1');
        $project->execute(['project' => $projectId]);
        $row = $project->fetch();
        if (!$row || empty($row['published_at'])) return;

        $actor = $db->prepare("SELECT full_name FROM users WHERE id=:actor AND status='active' AND deleted_at IS NULL AND purged_at IS NULL LIMIT 1");
        $actor->execute(['actor' => $actorId]);
        $actorName = trim((string) $actor->fetchColumn()) ?: 'Un estudiante';
        $metadata = $this->metadata((string) $row['code'], (string) $row['title'], [
            'event' => 'post_publication_modification',
            'audit_id' => $auditId,
            'actor_id' => $actorId,
            'scope' => $scope,
        ]);
        $message = $actorName . ' registró una modificación posterior a la publicación del proyecto. La versión publicada anterior se conserva en el historial.';
        $admins = $db->query("SELECT id FROM users WHERE is_admin=1 AND status='active' AND deleted_at IS NULL AND purged_at IS NULL");
        $insert = $db->prepare("INSERT IGNORE INTO notifications(user_id,project_id,type,title,message,action_url,action_label,metadata,deduplication_key)
            VALUES(:user,:project,'adjustment','Modificación posterior a publicación',:message,:url,'Revisar proyecto',:metadata,:dedup)");
        foreach ($admins->fetchAll(PDO::FETCH_COLUMN) as $adminId) {
            $id = (int) $adminId;
            $insert->execute([
                'user' => $id,
                'project' => $projectId,
                'message' => $message,
                'url' => route('project-detail') . '&id=' . $projectId,
                'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'dedup' => 'project-post-publication-modification:' . $auditId . ':' . $id,
            ]);
        }
    }
    public function defenseStarted(PDO $db,int $projectId,string $code,string $title,int $actorId):void {$metadata=$this->metadata($code,$title,['transition'=>'defense']);$this->forStudents($db,$projectId,'tribunal','Defensa de proyecto iniciada','El proyecto ha sido derivado al Tribunal para la sustentación de la defensa.','project-defense-started-students:'.$projectId,$metadata,'Ver proyecto');$q=$db->prepare("INSERT IGNORE INTO notifications(user_id,project_id,type,title,message,action_url,action_label,metadata,deduplication_key) SELECT DISTINCT pp.user_id,:project,'tribunal','Defensa de proyecto iniciada',:message,:url,'Ver proyecto',:metadata,CONCAT('project-defense-started-member:',:project,':',pp.user_id) FROM project_participants pp JOIN users u ON u.id=pp.user_id WHERE pp.project_id=:participants AND LOWER(pp.role_code) IN ('tribunal','jury') AND pp.status='active' AND pp.removed_at IS NULL AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL");$q->execute(['project'=>$projectId,'message'=>'Se ha dado inicio a la etapa de defensa del proyecto para tu evaluación.','url'=>route('project-detail').'&id='.$projectId,'metadata'=>json_encode($metadata,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'participants'=>$projectId]);}
    private function forStudents(PDO $db,int $projectId,string $type,string $title,string $message,string $dedup,array $metadata,string $actionLabel,string $tab='summary'):void {$q=$db->prepare("INSERT IGNORE INTO notifications(user_id,project_id,type,title,message,action_url,action_label,metadata,deduplication_key) SELECT DISTINCT pp.user_id,:project,:type,:title,:message,:url,:label,:metadata,:dedup FROM project_participants pp INNER JOIN users u ON u.id=pp.user_id WHERE pp.project_id=:participants AND pp.role_code='student' AND pp.status='active' AND pp.removed_at IS NULL AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL");$q->execute(['project'=>$projectId,'type'=>$type,'title'=>$title,'message'=>$message,'url'=>$this->projectNotificationUrl($type,$projectId,$tab),'label'=>$actionLabel,'metadata'=>json_encode($metadata,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'dedup'=>$dedup,'participants'=>$projectId]);}
    private function forUser(PDO $db,int $userId,int $projectId,string $type,string $title,string $message,string $dedup,array $metadata,string $actionLabel,string $tab='summary'):void {$q=$db->prepare("INSERT INTO notifications(user_id,project_id,type,title,message,action_url,action_label,metadata,deduplication_key) SELECT u.id,:project,:type,:title,:message,:url,:label,:metadata,:dedup FROM users u WHERE u.id=:user AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL");$q->execute(['user'=>$userId,'project'=>$projectId,'type'=>$type,'title'=>$title,'message'=>$message,'url'=>$this->projectNotificationUrl($type,$projectId,$tab),'label'=>$actionLabel,'metadata'=>json_encode($metadata,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'dedup'=>$dedup]);}
    private function forUserIdempotent(PDO $db,int $userId,int $projectId,string $type,string $title,string $message,string $dedup,array $metadata,string $actionLabel,string $tab='summary'):void {$q=$db->prepare("INSERT IGNORE INTO notifications(user_id,project_id,type,title,message,action_url,action_label,metadata,deduplication_key) SELECT u.id,:project,:type,:title,:message,:url,:label,:metadata,:dedup FROM users u WHERE u.id=:user AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL");$q->execute(['user'=>$userId,'project'=>$projectId,'type'=>$type,'title'=>$title,'message'=>$message,'url'=>$this->projectNotificationUrl($type,$projectId,$tab),'label'=>$actionLabel,'metadata'=>json_encode($metadata,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'dedup'=>$dedup]);}
    private function projectNotificationUrl(string $type,int $projectId,string $tab):string { return ($type === 'repository' ? route('repository-detail') : route('project-detail')) . '&id=' . $projectId . ($type === 'repository' ? '' : '&tab=' . $tab); }
    private function metadata(string $code,string $title,array $extra):array{return $extra+['project_code'=>$code,'project_name'=>$title,'source'=>isset($extra['transition'])?'academic_status_transition':'academic_assignment'];}
    private function label(string $code,string $title):string{return $code!==''?$code.' · '.$title:$title;}
}
