<?php

declare(strict_types=1);

/** Reconstruye la cronología académica desde las entidades reales, sin materializar eventos. */
final class ProjectAcademicTimelineService
{
    private const LOCAL_TIMEZONE = 'America/Guayaquil';
    private const PUBLIC_EVENT_TYPES = [
        'project_registered', 'delivery_registered', 'observation_batch_created',
        'document_review_completed', 'document_status_recorded',
        'project_approved', 'project_tribunal_approved', 'tribunal_approved',
        'project_published', 'project_republished', 'project_publication_reverted',
        'project_status_changed', 'project_corrections_requested',
        'tribunal_assigned', 'tribunal_updated', 'thesis_defense_information_updated',
        'tribunal_result_registered', 'defense_attempt_started',
    ];
    private int $currentProjectId = 0;

    public function __construct(private readonly ?PDO $db = null) {}

    public function page(int $projectId, int $offset = 0, int $limit = 10): array
    {
        return $this->pageForEventTypes($projectId, $offset, $limit, null);
    }

    /** Página pública: conserva únicamente hitos académicos y no devuelve payloads internos. */
    public function publicPage(int $projectId, int $offset = 0, int $limit = 15): array
    {
        $page = $this->pageForEventTypes($projectId, $offset, $limit, self::PUBLIC_EVENT_TYPES, true);
        $page['events'] = array_map(fn (array $event): array => $this->publicEvent($event), $page['events']);
        $page['loaded'] = count($page['events']);
        $page['cursor'] = null;
        return $page;
    }

    /** @param list<string>|null $eventTypes */
    private function pageForEventTypes(int $projectId, int $offset, int $limit, ?array $eventTypes, bool $workflowOnly = false): array
    {
        $offset=max(0,$offset);$limit=max(1,min(100,$limit));$this->currentProjectId=$projectId;$db=$this->db??Database::connection();
        $exists=$db->prepare('SELECT publication_origin FROM projects WHERE id=:id AND deleted_at IS NULL');$exists->execute(['id'=>$projectId]);
        $origin = $exists->fetchColumn();
        if ($origin === false || ($workflowOnly ? $origin !== ProjectPublicationOrigin::WORKFLOW : $origin === ProjectPublicationOrigin::DIRECT_REPOSITORY)) {
            return ['events'=>[],'total'=>0,'loaded'=>0,'has_more'=>false,'next_offset'=>$offset,'cursor'=>null];
        }
        $union=$this->unionSql();$parameters=array_fill(0,substr_count($union,'?'),$projectId);
        $eventFilter = '';
        if ($eventTypes !== null && $eventTypes !== []) {
            $eventMarks = implode(',', array_fill(0, count($eventTypes), '?'));
            $eventFilter = " WHERE event_type IN ($eventMarks)";
            $parameters = array_merge($parameters, $eventTypes);
        }
        $query=$db->prepare("SELECT timeline.*,COUNT(*) OVER() total_count FROM ($union) timeline$eventFilter ORDER BY occurred_at DESC,source_type DESC,source_id DESC,event_key DESC LIMIT $limit OFFSET $offset");
        $query->execute($parameters);$rows=$query->fetchAll();$total=isset($rows[0])?(int)$rows[0]['total_count']:0;
        if($rows===[]&&$offset>0){$count=$db->prepare("SELECT COUNT(*) FROM ($union) timeline_count$eventFilter");$count->execute($parameters);$total=(int)$count->fetchColumn();}
        $events=[];$seen=[];
        foreach($rows as $row){unset($row['total_count']);$event=$this->normalize($row);if(isset($seen[$event['event_key']]))continue;$seen[$event['event_key']]=true;$events[]=$event;}
        $next=$offset+count($events);
        return ['events'=>$events,'total'=>$total,'loaded'=>count($events),'has_more'=>$next<$total,'next_offset'=>$next,
            'cursor'=>$events===[]?null:$this->cursor($events[array_key_last($events)])];
    }

    private function publicEvent(array $event): array
    {
        $eventType = (string) ($event['event_type'] ?? '');
        $detail = match ($eventType) {
            'project_registered' => 'Se registró el proyecto dentro del flujo académico institucional.',
            'delivery_registered' => 'Se registró una entrega documental para revisión académica.',
            default => (string) ($event['detail'] ?? $event['description'] ?? ''),
        };

        return [
            'type' => (string) ($event['type'] ?? 'status'),
            'title' => (string) ($event['title'] ?? 'Evento académico'),
            'detail' => $detail,
            'actor' => trim((string) ($event['actor'] ?? '')),
            'date' => (string) ($event['occurred_at_local'] ?? $event['date'] ?? ''),
            'meta' => array_values(array_filter((array) ($event['meta'] ?? []), static fn (mixed $value): bool => is_scalar($value) && (string) $value !== '')),
        ];
    }

    private function unionSql(): string
    {
        return <<<'SQL'
SELECT CONCAT('registration:',p.id) event_key,'project_registered' event_type,'project' source_type,p.id source_id,p.created_at occurred_at,u.id actor_id,u.full_name actor_name,NULL effective_context,NULL previous_state,NULL new_state,
       JSON_OBJECT('has_delivery',EXISTS(SELECT 1 FROM project_deliveries d WHERE d.project_id=p.id)) payload
FROM projects p LEFT JOIN users u ON u.id=p.created_by WHERE p.id=? AND p.deleted_at IS NULL
UNION ALL
SELECT CONCAT('delivery:',d.id),'delivery_registered','project_delivery',d.id,d.submitted_at,u.id,u.full_name,NULL,NULL,NULL,
       JSON_OBJECT('title',d.title,'comment',d.comment,'version_number',d.version_number,'status',d.status,
                   'file_count',COALESCE(
                       (SELECT CAST(JSON_UNQUOTE(JSON_EXTRACT(l.new_state,'$.submitted_file_count')) AS UNSIGNED)
                        FROM project_audit_log l
                        WHERE l.project_id=d.project_id
                          AND l.action='project_submitted_for_review'
                          AND (JSON_UNQUOTE(JSON_EXTRACT(l.new_state,'$.delivery_id'))=CAST(d.id AS CHAR) OR l.entity_id=d.id)
                        ORDER BY l.id DESC LIMIT 1),
                       (SELECT COUNT(*) FROM project_files f WHERE f.project_id=d.project_id AND f.delivery_id=d.id),
                       1
                   )) payload
FROM project_deliveries d LEFT JOIN users u ON u.id=d.submitted_by WHERE d.project_id=?
UNION ALL
SELECT CONCAT('observation-batch:',MIN(o.id)),'observation_batch_created','project_observation',MIN(o.id),MAX(o.created_at),u.id,u.full_name,NULL,NULL,NULL,
       JSON_OBJECT('observation_count',COUNT(o.id),'affected_file_count',COUNT(DISTINCT NULLIF(o.file_id,0)),'corrected_files_count',COALESCE((SELECT CAST(JSON_UNQUOTE(JSON_EXTRACT(l.new_state,'$.corrections_requested')) AS UNSIGNED) FROM project_audit_log l WHERE l.project_id=o.project_id AND l.action='project_document_review_completed' AND DATE_FORMAT(l.created_at,'%Y-%m-%d %H:%i:%s')=DATE_FORMAT(MAX(o.created_at),'%Y-%m-%d %H:%i:%s') LIMIT 1),COUNT(DISTINCT NULLIF(o.file_id,0))),'delivery_id',o.delivery_id)
FROM project_observations o LEFT JOIN users u ON u.id=o.author_id WHERE o.project_id=? GROUP BY DATE_FORMAT(o.created_at,'%Y-%m-%d %H:%i:%s'),o.author_id,o.delivery_id
UNION ALL
SELECT CONCAT('observation-response:',r.id),'observation_responded','observation_response',r.id,r.created_at,u.id,u.full_name,NULL,NULL,NULL,
       JSON_OBJECT('body',r.body,'observation_id',r.observation_id)
FROM observation_responses r JOIN project_observations o ON o.id=r.observation_id LEFT JOIN users u ON u.id=r.author_id WHERE o.project_id=?
UNION ALL
SELECT CONCAT('adjustment-requested:',a.id),'adjustment_requested','project_adjustment_request',a.id,a.created_at,u.id,u.full_name,NULL,NULL,JSON_OBJECT('status','pending'),
       JSON_OBJECT('request_type',a.request_type,'message',a.message,'section',a.related_section,'field',a.related_field,'file_id',a.file_id,'file_name',f.original_name)
FROM project_adjustment_requests a LEFT JOIN users u ON u.id=a.requested_by LEFT JOIN project_files f ON f.id=a.file_id WHERE a.project_id=?
UNION ALL
SELECT CONCAT('adjustment-responded:',r.id),'adjustment_responded','project_adjustment_response',r.id,r.created_at,u.id,u.full_name,NULL,NULL,NULL,
       JSON_OBJECT('message',r.message,'adjustment_id',r.request_id)
FROM project_adjustment_request_responses r JOIN project_adjustment_requests a ON a.id=r.request_id LEFT JOIN users u ON u.id=r.author_id WHERE a.project_id=?
UNION ALL
SELECT CONCAT('adjustment-addressed:',a.id),'adjustment_addressed','project_adjustment_request',a.id,a.addressed_at,NULL,NULL,NULL,JSON_OBJECT('status','pending'),JSON_OBJECT('status','addressed'),
       JSON_OBJECT('request_type',a.request_type,'message',a.message)
FROM project_adjustment_requests a WHERE a.project_id=? AND a.addressed_at IS NOT NULL
  AND NOT EXISTS(SELECT 1 FROM project_audit_log l WHERE l.entity_type='project_adjustment_request' AND l.entity_id=a.id AND l.action='project_adjustment_request_approved')
UNION ALL
SELECT CONCAT('adjustment-closed:',a.id),'adjustment_closed','project_adjustment_request',a.id,a.closed_at,u.id,u.full_name,NULL,JSON_OBJECT('status','addressed'),JSON_OBJECT('status','closed'),
       JSON_OBJECT('request_type',a.request_type,'message',a.message)
FROM project_adjustment_requests a LEFT JOIN users u ON u.id=a.closed_by WHERE a.project_id=? AND a.closed_at IS NOT NULL
  AND NOT EXISTS(SELECT 1 FROM project_audit_log l WHERE l.entity_type='project_adjustment_request' AND l.entity_id=a.id AND l.action='project_adjustment_request_rejected')
UNION ALL
SELECT CONCAT('adjustment-decision:',l.id),CASE WHEN l.action='project_adjustment_request_approved' THEN 'adjustment_approved' ELSE 'adjustment_rejected' END,'project_adjustment_request',l.entity_id,l.created_at,u.id,u.full_name,l.effective_context,l.previous_state,l.new_state,
       JSON_OBJECT('adjustment_id',l.entity_id,'request_type',a.request_type,'message',a.message,'decision',CASE WHEN l.action='project_adjustment_request_approved' THEN 'approved' ELSE 'rejected' END)
FROM project_audit_log l JOIN project_adjustment_requests a ON a.id=l.entity_id LEFT JOIN users u ON u.id=l.user_id
WHERE l.project_id=? AND l.entity_type='project_adjustment_request' AND l.action IN ('project_adjustment_request_approved','project_adjustment_request_rejected')
UNION ALL
SELECT CONCAT('document-review:',l.id),'document_review_completed','project_audit_log',l.id,l.created_at,u.id,u.full_name,l.effective_context,l.previous_state,l.new_state,
       JSON_OBJECT('reason',l.reason)
FROM project_audit_log l LEFT JOIN users u ON u.id=l.user_id WHERE l.project_id=? AND l.action='project_document_review_completed' AND NOT EXISTS(SELECT 1 FROM project_observations obs WHERE obs.project_id=l.project_id AND DATE_FORMAT(obs.created_at,'%Y-%m-%d %H:%i:%s')=DATE_FORMAT(l.created_at,'%Y-%m-%d %H:%i:%s'))
UNION ALL
SELECT CONCAT('document-status:',s.id),'document_status_recorded','project_file_review_state',s.id,s.updated_at,u.id,u.full_name,NULL,NULL,JSON_OBJECT('status',s.status),
       JSON_OBJECT('file_id',s.file_id,'file_name',f.original_name,'checksum',s.checksum_sha256,'status',s.status)
FROM project_file_review_states s JOIN project_files f ON f.id=s.file_id LEFT JOIN users u ON u.id=s.reviewed_by
WHERE s.project_id=?
  AND EXISTS(SELECT 1 FROM project_deliveries d WHERE d.project_id=s.project_id)
  AND NOT (s.status='under_review' AND EXISTS(SELECT 1 FROM project_deliveries d WHERE d.project_id=s.project_id))
  AND NOT EXISTS(SELECT 1 FROM project_audit_log l WHERE l.project_id=s.project_id AND l.action='project_document_review_completed' AND JSON_SEARCH(l.new_state,'one',s.checksum_sha256) IS NOT NULL)
UNION ALL
SELECT CONCAT('document-version:',c.id),'document_version_uploaded','project_file_version_change',c.id,c.changed_at,u.id,u.full_name,NULL,
       JSON_OBJECT('checksum',c.previous_checksum,'version_number',c.previous_version_number,'document_status',c.previous_document_status),
       JSON_OBJECT('checksum',c.new_checksum,'version_number',c.new_version_number,'document_status',c.new_document_status),
       JSON_OBJECT('file_id',c.file_id,'file_name',f.original_name,'previous_file_name',v.original_name,'previous_version_number',c.previous_version_number,'new_version_number',c.new_version_number,'previous_checksum',c.previous_checksum,'new_checksum',c.new_checksum,'reason',c.reason,'declared_summary',c.declared_summary,'sections_json',c.sections_json,'previous_document_status',c.previous_document_status,'new_document_status',c.new_document_status,'addressed_observation_count',(SELECT COUNT(*) FROM project_file_version_addressed_observations link WHERE link.change_id=c.id))
FROM project_file_version_changes c JOIN project_files f ON f.id=c.file_id LEFT JOIN project_file_versions v ON v.id=c.previous_version_id LEFT JOIN users u ON u.id=c.changed_by WHERE c.project_id=? AND EXISTS(SELECT 1 FROM project_deliveries d WHERE d.project_id=c.project_id AND d.submitted_at<=c.changed_at)
UNION ALL
SELECT CONCAT('document-archive:',l.id),'document_version_archived','project_audit_log',l.id,l.created_at,u.id,u.full_name,l.effective_context,l.previous_state,l.new_state,
       JSON_OBJECT('reason',l.reason,'archived_count',JSON_VALUE(l.new_state,'$.archived_count'),'unavailable_count',JSON_VALUE(l.new_state,'$.unavailable_count'))
FROM project_audit_log l LEFT JOIN users u ON u.id=l.user_id WHERE l.project_id=? AND l.action='project_document_versions_archived'
UNION ALL
SELECT CONCAT('file-version:',v.id),'file_version_registered','project_file_version',v.id,v.replaced_at,u.id,u.full_name,NULL,NULL,NULL,
       JSON_OBJECT('file_id',v.file_id,'file_name',v.original_name,'version_number',v.version_number,'checksum',v.checksum_sha256,'reason',v.replacement_reason)
FROM project_file_versions v LEFT JOIN users u ON u.id=v.replaced_by WHERE v.project_id=? AND EXISTS(SELECT 1 FROM project_deliveries d WHERE d.project_id=v.project_id AND d.submitted_at<=v.replaced_at) AND NOT EXISTS(SELECT 1 FROM project_file_version_changes c WHERE c.previous_version_id=v.id)
UNION ALL
SELECT CONCAT('file-change:',l.id),l.action,'project_audit_log',l.id,l.created_at,u.id,u.full_name,l.effective_context,l.previous_state,l.new_state,
       JSON_OBJECT('reason',l.reason,'entity_id',l.entity_id)
FROM project_audit_log l LEFT JOIN users u ON u.id=l.user_id WHERE l.project_id=? AND l.action IN ('project.file_added','project.file_replaced','project.file_removed','project.file_restored','project_file_added','project_file_replaced','project_file_removed','project_file_restored')
UNION ALL
SELECT CONCAT('academic:',l.id),l.action,'project_audit_log',l.id,l.created_at,u.id,u.full_name,l.effective_context,l.previous_state,l.new_state,
       JSON_OBJECT('reason',l.reason)
FROM project_audit_log l LEFT JOIN users u ON u.id=l.user_id WHERE l.project_id=? AND (
  (l.action IN ('project_approved','project_tribunal_approved','tribunal_approved','project_published','project_unpublished','project_republished','project_publication_reverted','project_status_changed','project_reopened_for_adjustment','project_participants_updated','tribunal_assigned','tribunal_updated','thesis_defense_information_updated','tribunal_result_registered','defense_attempt_started')
   AND NOT (l.action='project_unpublished' AND EXISTS(SELECT 1 FROM project_audit_log semantic WHERE semantic.project_id=l.project_id AND semantic.action='project_publication_reverted' AND semantic.created_at=l.created_at)))
  OR (l.action='project_corrections_requested'
   AND NOT EXISTS(SELECT 1 FROM project_observations obs WHERE obs.project_id=l.project_id AND DATE_FORMAT(obs.created_at,'%Y-%m-%d %H:%i:%s')=DATE_FORMAT(l.created_at,'%Y-%m-%d %H:%i:%s')))
  OR (l.action='project_updated'
   AND NOT EXISTS(SELECT 1 FROM project_audit_log semantic WHERE semantic.project_id=l.project_id AND semantic.created_at=l.created_at AND semantic.action IN ('project_approved','project_tribunal_approved','tribunal_approved','project_published','project_republished','project_publication_reverted','project_corrections_requested'))))
UNION ALL
SELECT CONCAT('publication-fallback:',p.id),'project_published','project',p.id,p.published_at,NULL,NULL,NULL,NULL,JSON_OBJECT('status','published'),
       JSON_OBJECT('fallback',TRUE)
FROM projects p WHERE p.id=? AND p.published_at IS NOT NULL AND NOT EXISTS(SELECT 1 FROM project_audit_log l WHERE l.project_id=p.id AND l.action IN ('project_published','project_republished'))
SQL;
    }

    private function normalize(array $row): array
    {
        $type=(string)$row['event_type'];$payload=$this->json($row['payload']??null);$previous=$this->json($row['previous_state']??null);$new=$this->json($row['new_state']??null);
        $effectiveContext=trim((string)($row['effective_context']??''));
        if(!in_array($effectiveContext,['admin','admin_mode','teacher','student','system'],true))$effectiveContext=null;
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
            'effective_context'=>$effectiveContext,
            'metadata'=>array_filter(['actor_id'=>isset($row['actor_id'])?(int)$row['actor_id']:null,'effective_context'=>$effectiveContext,'payload'=>$payload],static fn(mixed $value):bool=>$value!==null&&$value!==[]),
            'is_download_available'=>false,
        ];
        $event['project_id']=$this->projectIdFromPayload($payload);$event['key']=$event['event_key'];$event['type']=$viewType;$event['date']=$event['occurred_at_utc'];$event['detail']=$description;$event['meta']=$badges;
        return $event;
    }

    private function copy(string $type,array $p,array $previous,array $new): array
    {
        return match($type){
            'project_registered'=>['Proyecto registrado',!empty($p['has_delivery'])?'Se registró el proyecto.':'Se registró el proyecto sin una entrega documental inicial.','registration'],
            'delivery_registered'=>$this->deliveryCopy($p),
            'observation_batch_created'=>['Enviado a correcciones',$this->observationBatchDescription($p),'observation'],
            'observation_created'=>['Observación académica registrada',(string)($p['body']??''),'observation'],
            'observation_responded'=>['Respuesta a observación registrada',(string)($p['body']??''),'response'],
            'adjustment_requested'=>['Solicitud de ajuste',(string)($p['message']??''),'adjustment'],
            'adjustment_responded'=>['Estudiante respondió la solicitud',(string)($p['message']??''),'adjustment'],
            'adjustment_addressed'=>['Solicitud marcada como atendida',(string)($p['message']??''),'adjustment'],
            'adjustment_closed'=>['Solicitud de ajuste cerrada',(string)($p['message']??''),'adjustment'],
            'adjustment_approved'=>['Solicitud de modificación aprobada','La solicitud fue aprobada y el proyecto volvió a estar disponible para edición.','adjustment'],
            'adjustment_rejected'=>['Solicitud de modificación rechazada','La solicitud fue rechazada y el proyecto conserva su estado anterior.','adjustment'],
            'project_reopened_for_adjustment'=>['Proyecto reabierto para modificación','El proyecto volvió a preparación por una solicitud aprobada.','status'],
            'document_review_completed'=>['Revisión documental confirmada',$this->reviewDescription($new),'document-review'],
            'document_status_recorded'=>['Estado documental registrado',$this->documentStatusLabel((string)($p['status']??'')),'document-review'],
            'file_version_registered'=>['Nueva versión documental registrada',!empty($p['reason'])?(string)$p['reason']:'Archivo actualizado durante la preparación documental.','file'],
            'document_version_uploaded'=>['Nueva versión documental registrada',!empty($p['declared_summary'])?(string)$p['declared_summary']:'Archivo actualizado durante la preparación documental.','file'],
            'document_version_archived'=>['Versiones documentales archivadas','Se archivaron '.(int)($p['archived_count']??0).' versiones históricas.','file'],
            'project_publication_reverted','project_unpublished'=>['Publicación revertida',$this->transitionDescription($previous,$new),'status'],
            'project_published','project_republished'=>['Proyecto publicado','El expediente fue publicado institucionalmente.','publication'],
            'project_tribunal_approved','tribunal_approved'=>['Proyecto aprobado por el Tribunal',$this->transitionDescription($previous,$new),'tribunal-approval'],
            'tribunal_assigned','tribunal_updated','project_participants_updated'=>['Tribunal registrado','Se registró una asignación o modificación de Tribunal.','tribunal'],
            'thesis_defense_information_updated'=>['Información de defensa actualizada','Se actualizó información organizativa de la defensa.','tribunal'],
            'defense_attempt_started'=>['Nueva defensa iniciada','Se habilitó un nuevo intento de defensa'.(!empty($new['attempt'])?' (Intento '.(int)$new['attempt'].'):':'.'),'tribunal'],
            'tribunal_result_registered'=>['Resultado del Tribunal registrado',($new['result']??'')==='approved'?'El Tribunal aprobó el proyecto.':'El Tribunal registró el proyecto como no aprobado.','tribunal'],
            'project_corrections_requested'=>['Enviado a correcciones',$this->correctionsRequestedDescription($new),'observation'],
            'project_updated'=>['Información del proyecto actualizada',$this->projectInformationDescription($new),'project'],
            default=>[$this->statusTitle($new),$this->transitionDescription($previous,$new),'status'],
        };
    }

    private function deliveryCopy(array $p): array
    {
        $version = max(1, (int)($p['version_number'] ?? 1));
        $fileCount = max(1, (int)($p['file_count'] ?? 1));
        $docText = $fileCount === 1 ? '1 documento enviado a revisión.' : $fileCount . ' documentos enviados a revisión.';
        $description = 'Se registró la entrega N.º ' . $version . ' con ' . $docText;

        $comment = trim((string)($p['comment'] ?? ''));
        if ($comment !== '' && !in_array(mb_strtolower($comment), ['entrega inicial', 'entrega de prueba', 'contenido de entrega', 'documentos enviados a revisión por el estudiante.'], true)) {
            $description .= ' ' . $comment;
        }

        return ['Entrega documental registrada', $description, 'delivery'];
    }

    private function observationBatchDescription(array $p): string
    {
        $obsCount = (int)($p['observation_count'] ?? 0);
        $correctedFilesCount = (int)($p['corrected_files_count'] ?? $p['corrections_requested'] ?? $p['affected_file_count'] ?? 0);
        $obsText = $obsCount === 1 ? '1 observación' : $obsCount . ' observaciones';
        if ($correctedFilesCount > 0) {
            $fileText = $correctedFilesCount === 1 ? '1 archivo con correcciones' : $correctedFilesCount . ' archivos con correcciones';
            return $obsText . ' · ' . $fileText;
        }
        return $obsText;
    }

    private function correctionsRequestedDescription(array $new): string
    {
        $obsCount = (int)($new['observation_count'] ?? 0);
        $correctedFilesCount = (int)($new['corrected_files_count'] ?? $new['corrections_requested'] ?? $new['affected_file_count'] ?? 0);
        $obsText = $obsCount === 1 ? '1 observación' : ($obsCount > 0 ? $obsCount . ' observaciones' : 'Correcciones solicitadas por el tutor.');
        if ($correctedFilesCount > 0 && $obsCount > 0) {
            $fileText = $correctedFilesCount === 1 ? '1 archivo con correcciones' : $correctedFilesCount . ' archivos con correcciones';
            return $obsText . ' · ' . $fileText;
        }
        return $obsText;
    }

    private function deliveryDescription(array $p): string
    {
        $count = (int)($p['file_count'] ?? 0);
        if ($count <= 0) $count = 1;
        $filesLabel = $count === 1 ? '1 archivo enviado a revisión por el estudiante.' : $count . ' archivos enviados a revisión por el estudiante.';
        $comment = trim((string)($p['comment'] ?? ''));
        if ($comment !== '' && !in_array(strtolower($comment), ['entrega inicial', 'entrega de prueba', 'contenido de entrega'], true)) {
            return $filesLabel . ' ' . $comment;
        }
        return $filesLabel;
    }

    private function badges(string $type,array $p,array $previous,array $new): array
    {
        $values=[];
        if($type==='delivery_registered'){
            if(isset($p['version_number'])&&(int)$p['version_number']>0)$values[]='Versión '.(int)$p['version_number'];
            $values[]='En revisión';
        } elseif(isset($p['version_number'])&&(int)$p['version_number']>0) {
            $values[]='Versión '.(int)$p['version_number'];
        }
        if($type==='document_version_uploaded'){
            $prevVer = (int)($p['previous_version_number'] ?? 0);
            $newVer = (int)($p['new_version_number'] ?? 0);
            if ($prevVer > 0 && $newVer > 0) $values[] = 'Versión ' . $prevVer . ' → ' . $newVer;
            $prevStatus = (string)($p['previous_document_status'] ?? '');
            $newStatus = (string)($p['new_document_status'] ?? '');
            if ($prevStatus !== '' && $newStatus !== '' && $prevStatus !== $newStatus) {
                $values[] = $this->documentStatusLabel($prevStatus) . ' → ' . $this->documentStatusLabel($newStatus);
            }
            if((int)($p['addressed_observation_count']??0)>0)$values[]=(int)$p['addressed_observation_count'].' observaciones atendidas';
            foreach($this->json($p['sections_json']??null) as $section)$values[]=(string)$section;
        }
        if(!empty($p['status']) && $type!=='delivery_registered')$values[]=$this->documentStatusLabel((string)$p['status']);
        foreach(['section'=>'Sección: ','field'=>'Campo: ','category'=>'Categoría: ','reference'=>'Referencia: '] as $key=>$prefix)if(!empty($p[$key]))$values[]=$prefix.(string)$p[$key];
        $from=$this->stateCode($previous);$to=$this->stateCode($new);if($from!==null&&$to!==null&&$from!==$to)$values[]=$this->stateLabel($from).' → '.$this->stateLabel($to);
        return array_values(array_unique(array_filter($values)));
    }

    private function reviewDescription(array $state): string
    {
        $reviewed = (int)($state['reviewed_documents'] ?? 0);
        $approved = (int)($state['approved'] ?? 0);
        $underReview = (int)($state['under_review'] ?? 0);
        $corrections = (int)($state['corrections_requested'] ?? 0);
        $obsCount = (int)($state['observation_count'] ?? 0);

        if ($reviewed > 0 || $approved > 0 || $corrections > 0) {
            return sprintf(
                'Documentos revisados: %d. Aprobados: %d. En revisión: %d. Con correcciones: %d. Observaciones creadas: %d.',
                $reviewed, $approved, $underReview, $corrections, $obsCount
            );
        }

        return 'El docente finalizó la revisión documental.';
    }

    private function projectInformationDescription(array $state): string
    {
        $changes = (array)($state['_history_changes'] ?? []);
        if ($changes === []) return 'Se actualizaron datos académicos del proyecto.';

        $labels = [
            'title' => 'Título',
            'Título' => 'Título',
            'tutor_id' => 'Tutoría',
            'tutor_reference_id' => 'Tutoría',
            'Tutoría' => 'Tutoría',
            'description' => 'Descripción',
            'summary' => 'Descripción',
            'Descripción' => 'Descripción',
            'career' => 'Carrera',
            'academic_period' => 'Período académico',
            'project_type' => 'Tipo de proyecto',
        ];

        $fieldNames = [];
        foreach ($changes as $change) {
            $raw = (string)($change['field'] ?? '');
            if ($raw === '') continue;
            $name = $labels[$raw] ?? ucfirst(str_replace(['_', '.'], ' ', $raw));
            if ($name !== '' && !in_array($name, $fieldNames, true)) {
                $fieldNames[] = $name;
            }
        }

        if ($fieldNames === []) return 'Se actualizaron datos académicos del proyecto.';

        if (count($fieldNames) === 1) {
            return 'Se actualizó: ' . $fieldNames[0] . '.';
        }

        $last = array_pop($fieldNames);
        return 'Se actualizaron: ' . implode(', ', $fieldNames) . ' y ' . $last . '.';
    }

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
