<?php

declare(strict_types=1);

/** Reconstruye la cronología académica desde las entidades reales, sin materializar eventos. */
final class ProjectAcademicTimelineService
{
    private const LOCAL_TIMEZONE = 'America/Guayaquil';
    private int $currentProjectId = 0;

    public function __construct(private readonly ?PDO $db = null) {}

    public function page(int $projectId, int $offset = 0, int $limit = 15): array
    {
        $offset=max(0,$offset);$limit=max(1,min(50,$limit));$this->currentProjectId=$projectId;$db=$this->db??Database::connection();
        $exists=$db->prepare('SELECT 1 FROM projects WHERE id=:id AND deleted_at IS NULL');$exists->execute(['id'=>$projectId]);
        if(!$exists->fetchColumn())return ['events'=>[],'total'=>0,'loaded'=>0,'has_more'=>false,'next_offset'=>$offset,'cursor'=>null];
        $union=$this->unionSql();$parameters=array_fill(0,substr_count($union,'?'),$projectId);
        $query=$db->prepare("SELECT timeline.*,COUNT(*) OVER() total_count FROM ($union) timeline ORDER BY occurred_at ASC,source_type ASC,source_id ASC,event_key ASC LIMIT $limit OFFSET $offset");
        $query->execute($parameters);$rows=$query->fetchAll();$total=isset($rows[0])?(int)$rows[0]['total_count']:0;
        if($rows===[]&&$offset>0){$count=$db->prepare("SELECT COUNT(*) FROM ($union) timeline_count");$count->execute($parameters);$total=(int)$count->fetchColumn();}
        $events=[];$seen=[];
        foreach($rows as $row){unset($row['total_count']);$event=$this->normalize($row);if(isset($seen[$event['event_key']]))continue;$seen[$event['event_key']]=true;$events[]=$event;}
        $next=$offset+count($events);
        return ['events'=>$events,'total'=>$total,'loaded'=>count($events),'has_more'=>$next<$total,'next_offset'=>$next,
            'cursor'=>$events===[]?null:$this->cursor($events[array_key_last($events)])];
    }

    private function unionSql(): string
    {
        return <<<'SQL'
SELECT CONCAT('registration:',p.id) event_key,'project_registered' event_type,'project' source_type,p.id source_id,p.created_at occurred_at,u.id actor_id,u.full_name actor_name,NULL previous_state,NULL new_state,
       JSON_OBJECT('has_delivery',EXISTS(SELECT 1 FROM project_deliveries d WHERE d.project_id=p.id)) payload
FROM projects p LEFT JOIN users u ON u.id=p.created_by WHERE p.id=? AND p.deleted_at IS NULL
UNION ALL
SELECT CONCAT('delivery:',d.id),'delivery_registered','project_delivery',d.id,d.submitted_at,u.id,u.full_name,NULL,NULL,
       JSON_OBJECT('title',d.title,'comment',d.comment,'version_number',d.version_number,'status',d.status)
FROM project_deliveries d LEFT JOIN users u ON u.id=d.submitted_by WHERE d.project_id=?
UNION ALL
SELECT CONCAT('observation:',o.id),'observation_created','project_observation',o.id,o.created_at,u.id,u.full_name,NULL,JSON_OBJECT('status',o.status),
       JSON_OBJECT('body',o.body,'category',o.category,'reference',o.location_reference,'status',o.status,'delivery_id',o.delivery_id,'file_id',o.file_id,'file_name',f.original_name,'delivery_version',d.version_number)
FROM project_observations o LEFT JOIN users u ON u.id=o.author_id LEFT JOIN project_files f ON f.id=o.file_id LEFT JOIN project_deliveries d ON d.id=o.delivery_id WHERE o.project_id=?
UNION ALL
SELECT CONCAT('observation-response:',r.id),'observation_responded','observation_response',r.id,r.created_at,u.id,u.full_name,NULL,NULL,
       JSON_OBJECT('body',r.body,'observation_id',r.observation_id)
FROM observation_responses r JOIN project_observations o ON o.id=r.observation_id LEFT JOIN users u ON u.id=r.author_id WHERE o.project_id=?
UNION ALL
SELECT CONCAT('adjustment-requested:',a.id),'adjustment_requested','project_adjustment_request',a.id,a.created_at,u.id,u.full_name,NULL,JSON_OBJECT('status','pending'),
       JSON_OBJECT('request_type',a.request_type,'message',a.message,'section',a.related_section,'field',a.related_field,'file_id',a.file_id,'file_name',f.original_name)
FROM project_adjustment_requests a LEFT JOIN users u ON u.id=a.requested_by LEFT JOIN project_files f ON f.id=a.file_id WHERE a.project_id=?
UNION ALL
SELECT CONCAT('adjustment-responded:',r.id),'adjustment_responded','project_adjustment_response',r.id,r.created_at,u.id,u.full_name,NULL,NULL,
       JSON_OBJECT('message',r.message,'adjustment_id',r.request_id)
FROM project_adjustment_request_responses r JOIN project_adjustment_requests a ON a.id=r.request_id LEFT JOIN users u ON u.id=r.author_id WHERE a.project_id=?
UNION ALL
SELECT CONCAT('adjustment-addressed:',a.id),'adjustment_addressed','project_adjustment_request',a.id,a.addressed_at,NULL,NULL,JSON_OBJECT('status','pending'),JSON_OBJECT('status','addressed'),
       JSON_OBJECT('request_type',a.request_type,'message',a.message)
FROM project_adjustment_requests a WHERE a.project_id=? AND a.addressed_at IS NOT NULL
UNION ALL
SELECT CONCAT('adjustment-closed:',a.id),'adjustment_closed','project_adjustment_request',a.id,a.closed_at,u.id,u.full_name,JSON_OBJECT('status','addressed'),JSON_OBJECT('status','closed'),
       JSON_OBJECT('request_type',a.request_type,'message',a.message)
FROM project_adjustment_requests a LEFT JOIN users u ON u.id=a.closed_by WHERE a.project_id=? AND a.closed_at IS NOT NULL
UNION ALL
SELECT CONCAT('document-review:',l.id),'document_review_completed','project_audit_log',l.id,l.created_at,u.id,u.full_name,l.previous_state,l.new_state,
       JSON_OBJECT('reason',l.reason)
FROM project_audit_log l LEFT JOIN users u ON u.id=l.user_id WHERE l.project_id=? AND l.action='project_document_review_completed'
UNION ALL
SELECT CONCAT('document-status:',s.id),'document_status_recorded','project_file_review_state',s.id,s.updated_at,u.id,u.full_name,NULL,JSON_OBJECT('status',s.status),
       JSON_OBJECT('file_id',s.file_id,'file_name',f.original_name,'checksum',s.checksum_sha256,'status',s.status)
FROM project_file_review_states s JOIN project_files f ON f.id=s.file_id LEFT JOIN users u ON u.id=s.reviewed_by
WHERE s.project_id=? AND NOT EXISTS(SELECT 1 FROM project_audit_log l WHERE l.project_id=s.project_id AND l.action='project_document_review_completed' AND JSON_SEARCH(l.new_state,'one',s.checksum_sha256) IS NOT NULL)
UNION ALL
SELECT CONCAT('document-version:',c.id),'document_version_uploaded','project_file_version_change',c.id,c.changed_at,u.id,u.full_name,
       JSON_OBJECT('checksum',c.previous_checksum,'version_number',c.previous_version_number,'document_status',c.previous_document_status),
       JSON_OBJECT('checksum',c.new_checksum,'version_number',c.new_version_number,'document_status',c.new_document_status),
       JSON_OBJECT('file_id',c.file_id,'file_name',f.original_name,'previous_version_number',c.previous_version_number,'new_version_number',c.new_version_number,'previous_checksum',c.previous_checksum,'new_checksum',c.new_checksum,'reason',c.reason,'declared_summary',c.declared_summary,'sections_json',c.sections_json,'previous_document_status',c.previous_document_status,'new_document_status',c.new_document_status,'addressed_observation_count',(SELECT COUNT(*) FROM project_file_version_addressed_observations link WHERE link.change_id=c.id))
FROM project_file_version_changes c JOIN project_files f ON f.id=c.file_id LEFT JOIN users u ON u.id=c.changed_by WHERE c.project_id=?
UNION ALL
SELECT CONCAT('document-archive:',l.id),'document_version_archived','project_audit_log',l.id,l.created_at,u.id,u.full_name,l.previous_state,l.new_state,
       JSON_OBJECT('reason',l.reason,'archived_count',JSON_VALUE(l.new_state,'$.archived_count'),'unavailable_count',JSON_VALUE(l.new_state,'$.unavailable_count'))
FROM project_audit_log l LEFT JOIN users u ON u.id=l.user_id WHERE l.project_id=? AND l.action='project_document_versions_archived'
UNION ALL
SELECT CONCAT('file-version:',v.id),'file_version_registered','project_file_version',v.id,v.replaced_at,u.id,u.full_name,NULL,NULL,
       JSON_OBJECT('file_id',v.file_id,'file_name',v.original_name,'version_number',v.version_number,'checksum',v.checksum_sha256,'reason',v.replacement_reason)
FROM project_file_versions v LEFT JOIN users u ON u.id=v.replaced_by WHERE v.project_id=? AND NOT EXISTS(SELECT 1 FROM project_file_version_changes c WHERE c.previous_version_id=v.id)
UNION ALL
SELECT CONCAT('file-change:',l.id),l.action,'project_audit_log',l.id,l.created_at,u.id,u.full_name,l.previous_state,l.new_state,
       JSON_OBJECT('reason',l.reason,'entity_id',l.entity_id)
FROM project_audit_log l LEFT JOIN users u ON u.id=l.user_id WHERE l.project_id=? AND l.action IN ('project.file_added','project.file_replaced','project.file_removed','project.file_restored','project_file_added','project_file_replaced','project_file_removed','project_file_restored')
UNION ALL
SELECT CONCAT('academic:',l.id),l.action,'project_audit_log',l.id,l.created_at,u.id,u.full_name,l.previous_state,l.new_state,
       JSON_OBJECT('reason',l.reason)
FROM project_audit_log l LEFT JOIN users u ON u.id=l.user_id WHERE l.project_id=? AND (
 (l.action IN ('project_approved','project_tribunal_approved','tribunal_approved','project_published','project_unpublished','project_republished','project_publication_reverted','project_corrections_requested','project_status_changed','project_participants_updated','tribunal_assigned','tribunal_updated','thesis_defense_information_updated','tribunal_result_registered')
  AND NOT (l.action='project_unpublished' AND EXISTS(SELECT 1 FROM project_audit_log semantic WHERE semantic.project_id=l.project_id AND semantic.action='project_publication_reverted' AND semantic.created_at=l.created_at)))
 OR (l.action='project_updated' AND (JSON_EXTRACT(l.new_state,'$.status') IS NOT NULL OR JSON_EXTRACT(l.new_state,'$.Estado') IS NOT NULL OR JSON_SEARCH(l.new_state,'one','Estado') IS NOT NULL)
  AND NOT EXISTS(SELECT 1 FROM project_audit_log semantic WHERE semantic.project_id=l.project_id AND semantic.created_at=l.created_at AND semantic.action IN ('project_approved','project_tribunal_approved','tribunal_approved','project_published','project_republished','project_publication_reverted','project_corrections_requested'))))
UNION ALL
SELECT CONCAT('publication-fallback:',p.id),'project_published','project',p.id,p.published_at,NULL,NULL,NULL,JSON_OBJECT('status','published'),
       JSON_OBJECT('fallback',TRUE)
FROM projects p WHERE p.id=? AND p.published_at IS NOT NULL AND NOT EXISTS(SELECT 1 FROM project_audit_log l WHERE l.project_id=p.id AND l.action IN ('project_published','project_republished'))
SQL;
    }

    private function normalize(array $row): array
    {
        $type=(string)$row['event_type'];$payload=$this->json($row['payload']??null);$previous=$this->json($row['previous_state']??null);$new=$this->json($row['new_state']??null);
        [$title,$description,$viewType]=$this->copy($type,$payload,$previous,$new);
        $utc=$this->utc((string)$row['occurred_at']);$local=$utc->setTimezone(new DateTimeZone(self::LOCAL_TIMEZONE));$badges=$this->badges($type,$payload,$previous,$new);
        $delivery=null;$file=null;$version=null;$observation=null;$adjustment=null;
        if(str_starts_with($type,'delivery_'))$delivery=['id'=>(int)$row['source_id'],'version_number'=>(int)($payload['version_number']??0),'status'=>$payload['status']??null];
        if(isset($payload['file_id'])||str_contains($type,'file_'))$file=['id'=>isset($payload['file_id'])?(int)$payload['file_id']:null,'name'=>$payload['file_name']??null,'checksum'=>$payload['checksum']??null];
        if($type==='file_version_registered')$version=['number'=>(int)($payload['version_number']??0),'checksum'=>$payload['checksum']??null];
        if($type==='document_version_uploaded')$version=['previous_number'=>(int)($payload['previous_version_number']??0),'new_number'=>(int)($payload['new_version_number']??0),'previous_checksum'=>$payload['previous_checksum']??null,'new_checksum'=>$payload['new_checksum']??null];
        if(str_starts_with($type,'observation_'))$observation=['id'=>(int)($payload['observation_id']??$row['source_id']),'category'=>$payload['category']??null,'reference'=>$payload['reference']??null,'status'=>$payload['status']??null];
        if(str_starts_with($type,'adjustment_'))$adjustment=['id'=>(int)($payload['adjustment_id']??$row['source_id']),'request_type'=>$payload['request_type']??null,'section'=>$payload['section']??null,'field'=>$payload['field']??null];
        $event=[
            'event_key'=>(string)$row['event_key'],'event_type'=>$type,'occurred_at_utc'=>$utc->format('Y-m-d\TH:i:s\Z'),'occurred_at_local'=>$local->format('Y-m-d\TH:i:sP'),
            'source_type'=>(string)$row['source_type'],'source_id'=>(int)$row['source_id'],'actor'=>trim((string)($row['actor_name']??'')),'title'=>$title,'description'=>$description,
            'project_id'=>null,'delivery'=>$delivery,'file'=>$file,'version'=>$version,'observation'=>$observation,'adjustment'=>$adjustment,
            'previous_state'=>$this->translateHistoricalState($previous),'new_state'=>$this->translateHistoricalState($new),'badges'=>$badges,
            'metadata'=>array_filter(['actor_id'=>isset($row['actor_id'])?(int)$row['actor_id']:null,'payload'=>$payload],static fn(mixed $value):bool=>$value!==null&&$value!==[]),
            'is_download_available'=>false,
        ];
        $event['project_id']=$this->projectIdFromPayload($payload);$event['key']=$event['event_key'];$event['type']=$viewType;$event['date']=$event['occurred_at_utc'];$event['detail']=$description;$event['meta']=$badges;
        return $event;
    }

    private function copy(string $type,array $p,array $previous,array $new): array
    {
        return match($type){
            'project_registered'=>['Proyecto registrado',!empty($p['has_delivery'])?'Se registró el proyecto. La entrega inicial consta como un evento independiente.':'Se registró el proyecto sin una entrega documental inicial.','registration'],
            'delivery_registered'=>[(int)($p['version_number']??0)>1?'Nueva versión enviada':'Entrega documental registrada',trim((string)($p['comment']??$p['title']??'')),'delivery'],
            'observation_created'=>['Observación académica registrada',(string)($p['body']??''),'observation'],
            'observation_responded'=>['Respuesta a observación registrada',(string)($p['body']??''),'response'],
            'adjustment_requested'=>['Solicitud de ajuste',(string)($p['message']??''),'adjustment'],
            'adjustment_responded'=>['Estudiante respondió la solicitud',(string)($p['message']??''),'adjustment'],
            'adjustment_addressed'=>['Solicitud marcada como atendida',(string)($p['message']??''),'adjustment'],
            'adjustment_closed'=>['Solicitud de ajuste cerrada',(string)($p['message']??''),'adjustment'],
            'document_review_completed'=>['Revisión documental confirmada',$this->reviewDescription($new),'document-review'],
            'document_status_recorded'=>['Estado documental registrado',$this->documentStatusLabel((string)($p['status']??'')),'document-review'],
            'file_version_registered'=>['Nueva versión documental registrada',trim((string)($p['reason']??$p['file_name']??'')),'file'],
            'document_version_uploaded'=>['Nueva versión documental registrada',(string)($p['declared_summary']??''),'file'],
            'document_version_archived'=>['Versiones documentales archivadas','Se archivaron '.(int)($p['archived_count']??0).' versiones históricas.','file'],
            'project.file_added','project_file_added'=>['Archivo agregado','Se registró un cambio documental relevante.','file'],
            'project.file_replaced','project_file_replaced'=>['Archivo reemplazado','Se registró un cambio documental relevante.','file'],
            'project.file_removed','project_file_removed'=>['Archivo retirado','Se registró un cambio documental relevante.','file'],
            'project.file_restored','project_file_restored'=>['Archivo restaurado','Se registró un cambio documental relevante.','file'],
            'project_publication_reverted','project_unpublished'=>['Publicación revertida',$this->transitionDescription($previous,$new),'status'],
            'project_published','project_republished'=>['Proyecto publicado','El expediente fue publicado institucionalmente.','publication'],
            'project_tribunal_approved','tribunal_approved'=>['Proyecto aprobado por el Tribunal',$this->transitionDescription($previous,$new),'tribunal-approval'],
            'tribunal_assigned','tribunal_updated','project_participants_updated'=>['Tribunal registrado','Se registró una asignación o modificación de Tribunal.','tribunal'],
            'thesis_defense_information_updated'=>['Información de defensa actualizada','Se actualizó información organizativa de la defensa.','tribunal'],
            'tribunal_result_registered'=>['Resultado del Tribunal registrado',($new['result']??'')==='approved'?'El Tribunal aprobó el proyecto.':'El Tribunal registró el proyecto como no aprobado.','tribunal'],
            'project_corrections_requested'=>['Tutor solicitó correcciones','El proyecto volvió a En desarrollo.','observation'],
            default=>[$this->statusTitle($new),$this->transitionDescription($previous,$new),'status'],
        };
    }

    private function badges(string $type,array $p,array $previous,array $new): array
    {
        $values=[];
        if(isset($p['version_number'])&&(int)$p['version_number']>0)$values[]='Versión '.(int)$p['version_number'];
        if($type==='document_version_uploaded'){$values[]='Versión '.(int)($p['previous_version_number']??0).' → '.(int)($p['new_version_number']??0);$values[]=$this->documentStatusLabel((string)($p['previous_document_status']??'')).' → '.$this->documentStatusLabel((string)($p['new_document_status']??''));if((int)($p['addressed_observation_count']??0)>0)$values[]=(int)$p['addressed_observation_count'].' observaciones atendidas';foreach($this->json($p['sections_json']??null) as $section)$values[]=(string)$section;}
        if(!empty($p['status']))$values[]=$type==='delivery_registered'?project_delivery_status_label((string)$p['status']):$this->documentStatusLabel((string)$p['status']);
        foreach(['section'=>'Sección: ','field'=>'Campo: ','category'=>'Categoría: ','reference'=>'Referencia: '] as $key=>$prefix)if(!empty($p[$key]))$values[]=$prefix.(string)$p[$key];
        $from=$this->stateCode($previous);$to=$this->stateCode($new);if($from!==null&&$to!==null&&$from!==$to)$values[]=$this->stateLabel($from).' → '.$this->stateLabel($to);
        return array_values(array_unique(array_filter($values)));
    }

    private function reviewDescription(array $state): string
    { return sprintf('Documentos revisados: %d. Aprobados: %d. En revisión: %d. Con correcciones: %d. Observaciones creadas: %d.',(int)($state['reviewed_documents']??0),(int)($state['approved']??0),(int)($state['under_review']??0),(int)($state['corrections_requested']??0),(int)($state['observation_count']??0)); }
    private function transitionDescription(array $previous,array $new): string { $from=$this->stateCode($previous);$to=$this->stateCode($new);return $from!==null&&$to!==null?$this->stateLabel($from)."\n↓\n".$this->stateLabel($to):''; }
    private function statusTitle(array $new): string { return $this->stateCode($new)==='under_review'?'Proyecto enviado a revisión':($this->stateCode($new)==='approved'?'Proyecto aprobado':($this->stateCode($new)==='defense'?'Proyecto enviado a Tribunal':'Cambio de estado del proyecto')); }
    private function stateCode(array $state): ?string
    {
        $value=$state['status']??$state['project_status']??$state['Estado']??null;
        $labels=['En desarrollo'=>'development','En revisión'=>'under_review','Correcciones solicitadas'=>'corrections_requested','Aprobado'=>'approved','En tribunal'=>'defense','Aprobado por el Tribunal'=>'tribunal_approved','Publicado'=>'published'];
        if(is_string($value)&&$value!=='')return $labels[$value]??$value;
        foreach((array)($state['_history_changes']??[]) as $change)if(($change['field']??'')==='Estado')return $labels[(string)($change['to']??'')]??null;
        return null;
    }
    private function stateLabel(string $status): string { return project_academic_labels($status)['status']; }
    private function documentStatusLabel(string $status): string { return match($status){'development'=>'En desarrollo','under_review'=>'En revisión','approved'=>'Aprobado','corrections_requested','changes_required'=>'Correcciones solicitadas',default=>$status}; }
    private function translateHistoricalState(array $state): array { foreach(['status','project_status'] as $key)if(($state[$key]??null)==='changes_required')$state[$key]='corrections_requested';return $state; }
    private function json(mixed $value): array { if(is_array($value))return $value;if(!is_string($value)||$value==='')return [];$decoded=json_decode($value,true);return is_array($decoded)?$decoded:[]; }
    private function utc(string $value): DateTimeImmutable { return (new DateTimeImmutable($value,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC')); }
    private function cursor(array $event): string { return rtrim(strtr(base64_encode(json_encode([$event['occurred_at_utc'],$event['source_type'],$event['source_id'],$event['event_key']],JSON_THROW_ON_ERROR)),'+/','-_'),'='); }
    private function projectIdFromPayload(array $payload): ?int { return isset($payload['project_id'])?(int)$payload['project_id']:($this->currentProjectId>0?$this->currentProjectId:null); }
}
