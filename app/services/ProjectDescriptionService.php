<?php

declare(strict_types=1);

/** Gestiona la descripción pública opcional durante el ciclo académico. */
final class ProjectDescriptionService
{
    private const REMINDER_TYPES = ['thesis_profile', 'thesis', 'pis'];
    private const INSTITUTIONAL_DESCRIPTIONS = [
        'practice' => 'Este proyecto corresponde al proceso de prácticas preprofesionales desarrollado por el estudiante como parte de su formación académica, conforme a los requisitos y lineamientos establecidos por la institución.',
        'community' => 'Este proyecto forma parte de las actividades institucionales de vinculación con la sociedad y está orientado a contribuir al desarrollo del entorno mediante la participación académica de los estudiantes.',
    ];
    public const MIN_LENGTH = 30;
    public const MAX_BYTES = 65535;

    public function __construct(private readonly ?PDO $db = null) {}

    public function registerStatusReminder(int $projectId, int $auditId): void
    {
        if ($projectId < 1 || $auditId < 1) return;
        $db = $this->db ?? Database::connection();
        $project = $db->prepare("SELECT p.summary,pt.code type_code FROM projects p INNER JOIN project_types pt ON pt.id=p.project_type_id WHERE p.id=:id AND p.deleted_at IS NULL");
        $project->execute(['id' => $projectId]);
        $row = $project->fetch();
        if (!$row || trim((string)($row['summary'] ?? '')) !== '' || !in_array((string)$row['type_code'], self::REMINDER_TYPES, true)) return;
        $students = $db->prepare("SELECT DISTINCT pp.user_id FROM project_participants pp INNER JOIN student_profiles sp ON sp.user_id=pp.user_id INNER JOIN users u ON u.id=pp.user_id WHERE pp.project_id=:project_id AND pp.role_code='student' AND pp.status='active' AND pp.removed_at IS NULL AND u.status='active' AND u.deleted_at IS NULL");
        $students->execute(['project_id' => $projectId]);
        $insert = $db->prepare("INSERT IGNORE INTO notifications(user_id,project_id,type,title,message,action_url,action_label,metadata,deduplication_key) VALUES(:user_id,:project_id,'reminder','Descripción del proyecto pendiente','Tu proyecto aún no tiene registrada una descripción pública.',:url,'Completar descripción',:metadata,:deduplication_key)");
        $metadata = json_encode(['purpose'=>'project_description','status_audit_id'=>$auditId], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        foreach ($students->fetchAll(PDO::FETCH_COLUMN) as $userId) $insert->execute(['user_id'=>(int)$userId,'project_id'=>$projectId,'url'=>route('project-detail').'&id='.$projectId,'metadata'=>$metadata,'deduplication_key'=>'project-description-status-'.$auditId]);
    }

    /** Devuelve y consume el aviso que corresponde a esta apertura del proyecto. */
    public function consumePendingReminder(int $projectId, int $userId): ?array
    {
        if ($projectId < 1 || $userId < 1) return null;
        return Database::transaction(function(PDO $db) use($projectId,$userId):?array {
            $q=$db->prepare("SELECT n.id FROM notifications n INNER JOIN projects p ON p.id=n.project_id AND p.deleted_at IS NULL INNER JOIN project_types pt ON pt.id=p.project_type_id INNER JOIN project_participants pp ON pp.project_id=p.id AND pp.user_id=n.user_id INNER JOIN student_profiles sp ON sp.user_id=pp.user_id WHERE n.project_id=:project_id AND n.user_id=:user_id AND n.type='reminder' AND n.is_read=0 AND n.archived_at IS NULL AND n.deleted_at IS NULL AND JSON_UNQUOTE(JSON_EXTRACT(n.metadata,'$.purpose'))='project_description' AND TRIM(COALESCE(p.summary,''))='' AND pt.code IN ('thesis_profile','thesis','pis') AND pp.role_code='student' AND pp.status='active' AND pp.removed_at IS NULL ORDER BY n.created_at,n.id LIMIT 1 FOR UPDATE");
            $q->execute(['project_id'=>$projectId,'user_id'=>$userId]);$id=(int)$q->fetchColumn();if($id<1)return null;
            $db->prepare('UPDATE notifications SET is_read=1,read_at=NOW(),updated_at=NOW() WHERE id=:id')->execute(['id'=>$id]);
            return ['id'=>$id];
        });
    }

    public function saveForStudent(int $projectId,int $userId,string $description):void
    {
        $description=trim($description);
        if($description==='')throw new InvalidArgumentException('Escribe una descripción o selecciona “Omitir por ahora”.');
        if(mb_strlen($description,'UTF-8')>4000)throw new InvalidArgumentException('La descripción no puede superar los 4000 caracteres.');
        Database::transaction(function(PDO $db)use($projectId,$userId,$description):void{
            $q=$db->prepare("SELECT p.summary,pt.code type_code FROM projects p INNER JOIN project_types pt ON pt.id=p.project_type_id WHERE p.id=:project_id AND p.deleted_at IS NULL FOR UPDATE");$q->execute(['project_id'=>$projectId]);$project=$q->fetch();
            if(!$project||!in_array((string)$project['type_code'],self::REMINDER_TYPES,true))throw new InvalidArgumentException('Este proyecto no admite una descripción escrita por el estudiante.');
            $participant=$db->prepare("SELECT 1 FROM project_participants pp INNER JOIN student_profiles sp ON sp.user_id=pp.user_id WHERE pp.project_id=:project_id AND pp.user_id=:user_id AND pp.role_code='student' AND pp.status='active' AND pp.removed_at IS NULL LIMIT 1");$participant->execute(['project_id'=>$projectId,'user_id'=>$userId]);
            if(!$participant->fetchColumn())throw new InvalidArgumentException('No tienes permiso para modificar la descripción de este proyecto.');
            $previous=trim((string)($project['summary']??''));$db->prepare('UPDATE projects SET summary=:summary WHERE id=:id')->execute(['summary'=>$description,'id'=>$projectId]);
            (new ProjectAuditService($db))->record($projectId,$userId,'project_description_updated','project',$projectId,['summary'=>$previous?:null],['summary'=>$description]);
            $db->prepare("UPDATE notifications SET is_read=1,read_at=COALESCE(read_at,NOW()),updated_at=NOW() WHERE project_id=:project_id AND user_id=:user_id AND type='reminder' AND JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.purpose'))='project_description'")->execute(['project_id'=>$projectId,'user_id'=>$userId]);
        });
    }

    public function effectiveDescription(string $typeCode,?string $description):string
    {
        $description=trim((string)$description);
        return $description!==''?$description:(self::INSTITUTIONAL_DESCRIPTIONS[$typeCode]??'');
    }

    public function prepareForPublication(int $projectId):array
    {
        $q=($this->db??Database::connection())->prepare("SELECT p.id,p.summary,p.status,p.updated_at,pt.code type_code FROM projects p INNER JOIN project_types pt ON pt.id=p.project_type_id WHERE p.id=:id AND p.deleted_at IS NULL");
        $q->execute(['id'=>$projectId]);$project=$q->fetch();
        if(!$project)throw new InvalidArgumentException('El proyecto ya no está disponible.');
        if((string)$project['status']==='published')throw new InvalidArgumentException('Este proyecto ya fue publicado.');
        $required=(string)$project['type_code']==='thesis'?'tribunal_approved':'approved';
        if((string)$project['status']!==$required)throw new InvalidArgumentException('El proyecto ya no se encuentra en un estado válido para publicación.');
        if(trim((string)($project['summary']??''))!=='')return ['required'=>false,'proposal'=>'','origin'=>'existing','message'=>'','updated_at'=>(string)$project['updated_at']];
        $type=(string)$project['type_code'];
        if(isset(self::INSTITUTIONAL_DESCRIPTIONS[$type]))return ['required'=>true,'proposal'=>self::INSTITUTIONAL_DESCRIPTIONS[$type],'origin'=>'institutional','message'=>'Este proyecto todavía no cuenta con una descripción pública. Se preparó una descripción institucional según su tipo. Revísala y modifícala si es necesario antes de publicar.','updated_at'=>(string)$project['updated_at']];
        // El modelo actual no dispone de una introducción estructurada. No se leen archivos ni se inventa otra fuente.
        return ['required'=>true,'proposal'=>'','origin'=>'unavailable','message'=>'No fue posible preparar una propuesta automática porque el proyecto no cuenta con una introducción disponible. Escribe una descripción antes de publicar.','updated_at'=>(string)$project['updated_at']];
    }

    public function normalizePublicationDescription(string $description):string
    {
        $description=trim((string)preg_replace('/\s+/u',' ',$description));
        if($description==='')throw new InvalidArgumentException('Escribe una descripción antes de publicar.');
        if(mb_strlen($description,'UTF-8')<self::MIN_LENGTH)throw new InvalidArgumentException('La descripción es demasiado breve para presentar el proyecto.');
        if(strlen($description)>self::MAX_BYTES)throw new InvalidArgumentException('La descripción supera el límite permitido.');
        return $description;
    }

    /** Algoritmo determinista listo para usarse cuando exista una introducción estructurada. */
    public function proposalFromIntroduction(string $introduction):string
    {
        $text=trim((string)preg_replace('/\s+/u',' ',$introduction));
        $text=trim((string)preg_replace('/^(?:introducci[oó]n)\s*[:.\-–—]?\s*/iu','',$text));
        if($text==='')return '';
        preg_match_all('/[^.!?]+[.!?]+(?:[”"\']+)?/u',$text,$matches);
        $sentences=array_values(array_filter(array_map('trim',$matches[0]??[])));
        if(!$sentences)return '';
        $availableWords=count(preg_split('/\s+/u',implode(' ',$sentences),-1,PREG_SPLIT_NO_EMPTY));
        if($availableWords<20)return '';
        $proposal=[];$words=0;
        foreach($sentences as $sentence){
            $tokens=preg_split('/\s+/u',$sentence,-1,PREG_SPLIT_NO_EMPTY);$count=count($tokens);
            if($words+$count>130){
                if($words>=80)break;
                $space=130-$words;if($space>0)$proposal[]=rtrim(implode(' ',array_slice($tokens,0,$space))," \t\n\r\0\x0B:;").'…';$words=130;break;
            }
            $proposal[]=$sentence;$words+=$count;if($words>=80)break;
        }
        if(!$proposal)return '';
        $result=implode(' ',$proposal);
        return trim($result);
    }
}
