<?php

declare(strict_types=1);

/** Corrección administrativa excepcional de una publicación reciente. */
final class ProjectPublicationReversionService
{
    private const WINDOW_SECONDS = 86400;
    private const WARNING = 'Esta acción retirará el proyecto del estado Publicado y reabrirá su flujo académico. Solo puede realizarse durante las primeras 24 horas posteriores a la publicación y quedará registrada en el historial administrativo.';

    /** @return array<string,mixed> */
    public function availability(array $project): array
    {
        $published = (string)($project['status']??'') === 'published';
        $publishedAt = trim((string)($project['published_at']??''));
        $elapsed = isset($project['publication_reversion_elapsed_seconds'])
            ? (int)$project['publication_reversion_elapsed_seconds'] : null;
        $available = $published && $publishedAt !== '' && $elapsed !== null && $elapsed >= 0 && $elapsed < self::WINDOW_SECONDS;
        $remaining = $available ? self::WINDOW_SECONDS - $elapsed : 0;
        $target = (string)($project['type_code']??'') === 'thesis' ? 'tribunal_approved' : 'approved';
        $labels = project_academic_labels($target);
        $message = $available
            ? 'Disponible durante '.$this->remainingLabel($remaining).' más.'
            : ($published ? 'La ventana de corrección de la publicación ha finalizado.' : '');

        return [
            'available'=>$available,'remaining_seconds'=>$remaining,'message'=>$message,
            'action'=>$available?[
                'action'=>'revert_publication','target'=>$target,'label'=>'Revertir publicación','icon'=>'fa-rotate-left',
                'dialog_title'=>'¿Revertir la publicación?','effect'=>'El proyecto dejará de estar disponible en el Repositorio y retomará su etapa académica anterior.',
                'warning'=>self::WARNING,'current_label'=>'Publicado','target_label'=>$labels['status'],'target_stage'=>$labels['stage'],
                'reason_required'=>true,'reason_label'=>'Motivo de la reversión','reason_help'=>'Explica por qué es necesario revertir esta publicación.',
                'requirements'=>[],'requirements_met'=>true,'expected_published_at'=>$publishedAt,
            ]:null,
        ];
    }

    /** @return array<string,mixed> */
    public function revert(int $projectId,string $expectedStatus,string $expectedPublishedAt,string $reason,int $actor): array
    {
        if($projectId<1||$actor<1)throw new ProjectStatusTransitionException('La solicitud de reversión no es válida.');
        $reason=trim($reason);
        if(mb_strlen($reason)<5||mb_strlen($reason)>500)throw new ProjectStatusTransitionException('Indica un motivo de entre 5 y 500 caracteres.');

        return Database::transaction(fn(PDO $db):array=>$this->revertInTransaction($db,$projectId,$expectedStatus,$expectedPublishedAt,$reason,$actor));
    }

    /** Ejecuta la reversión dentro de una transacción ya abierta para composición y pruebas con rollback. */
    public function revertInTransaction(PDO $db,int $projectId,string $expectedStatus,string $expectedPublishedAt,string $reason,int $actor): array
    {
        if($projectId<1||$actor<1)throw new ProjectStatusTransitionException('La solicitud de reversión no es válida.');
        $reason=trim($reason);
        if(mb_strlen($reason)<5||mb_strlen($reason)>500)throw new ProjectStatusTransitionException('Indica un motivo de entre 5 y 500 caracteres.');
            $query=$db->prepare(
                "SELECT p.id,p.title,p.status,p.published_at,p.is_available,pt.code type_code,
                 TIMESTAMPDIFF(SECOND,p.published_at,CURRENT_TIMESTAMP) elapsed_seconds,
                 CURRENT_TIMESTAMP database_now
                 FROM projects p INNER JOIN project_types pt ON pt.id=p.project_type_id
                 WHERE p.id=:id AND p.deleted_at IS NULL FOR UPDATE"
            );
            $query->execute(['id'=>$projectId]);
            $project=$query->fetch();
            if(!$project)throw new ProjectStatusTransitionException('El proyecto no existe o fue eliminado.',404);
            $publishedAt=(string)($project['published_at']??'');
            $elapsed=$project['elapsed_seconds']===null?null:(int)$project['elapsed_seconds'];
            if($expectedStatus!=='published'||(string)$project['status']!=='published'||$publishedAt===''||$expectedPublishedAt===''||$publishedAt!==$expectedPublishedAt||$elapsed===null||$elapsed<0||$elapsed>=self::WINDOW_SECONDS){
                throw new ProjectStatusTransitionException('La publicación ya no puede revertirse porque el estado cambió o finalizó la ventana de 24 horas.',409);
            }

            $target=(string)$project['type_code']==='thesis'?'tribunal_approved':'approved';
            $update=$db->prepare("UPDATE projects SET status=:status,is_available=0 WHERE id=:id AND status='published' AND published_at=:published_at");
            $update->execute(['status'=>$target,'id'=>$projectId,'published_at'=>$publishedAt]);
            if($update->rowCount()!==1)throw new ProjectStatusTransitionException('La publicación ya no puede revertirse porque el estado cambió o finalizó la ventana de 24 horas.',409);

            (new ProjectAuditService($db))->record(
                $projectId,$actor,'project_publication_reverted','project',$projectId,
                ['status'=>'published','published_at'=>$publishedAt,'is_available'=>(bool)$project['is_available']],
                ['status'=>$target,'published_at'=>$publishedAt,'is_available'=>false,'original_published_at'=>$publishedAt,'publication_reverted_at'=>(string)$project['database_now']],
                $reason
            );
            $labels=project_academic_labels($target);
            return ['id'=>$projectId,'previous_status'=>'published','status'=>$target,'status_label'=>$labels['status'],'stage_label'=>$labels['stage'],'published_at'=>$publishedAt,'is_available'=>false];
    }

    private function remainingLabel(int $seconds): string
    {
        $hours=intdiv(max(0,$seconds),3600);
        $minutes=intdiv(max(0,$seconds)%3600,60);
        return $hours.' h '.$minutes.' min';
    }
}
