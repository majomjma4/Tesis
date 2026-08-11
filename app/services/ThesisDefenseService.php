<?php
declare(strict_types=1);

final class ThesisDefenseException extends InvalidArgumentException { public function __construct(string $message, private int $status = 422) { parent::__construct($message); } public function httpStatus(): int { return $this->status; } }
final class ThesisDefenseService
{
    public function save(int $projectId, array $input, int $actor): array { return Database::transaction(fn(PDO $db) => $this->saveTx($db, $projectId, $input, $actor)); }
    public function current(int $projectId): ?array { $q = Database::connection()->prepare('SELECT * FROM project_defenses WHERE project_id=:id ORDER BY attempt_number DESC LIMIT 1'); $q->execute(['id'=>$projectId]); return $q->fetch() ?: null; }
    public function startNewAttempt(int $projectId, string $expectedStatus, int $actor): array { return Database::transaction(fn(PDO $db) => $this->startNewAttemptTx($db, $projectId, $expectedStatus, $actor)); }
    private function project(PDO $db, int $id): array { $q=$db->prepare("SELECT p.id,p.status,p.code,pt.code type_code FROM projects p JOIN project_types pt ON pt.id=p.project_type_id WHERE p.id=:id AND p.deleted_at IS NULL FOR UPDATE"); $q->execute(['id'=>$id]); $project=$q->fetch(); if(!$project || (string)$project['type_code']!=='thesis') throw new ThesisDefenseException('El proyecto de Titulación solicitado no está disponible.',404); return $project; }
    private function currentLocked(PDO $db, int $id): ?array { $q=$db->prepare('SELECT * FROM project_defenses WHERE project_id=:id ORDER BY attempt_number DESC LIMIT 1 FOR UPDATE'); $q->execute(['id'=>$id]); return $q->fetch() ?: null; }
    private function saveTx(PDO $db, int $id, array $input, int $actor): array {
        $project=$this->project($db,$id); if(!in_array((string)$project['status'],['approved','defense'],true)) throw new ThesisDefenseException('La información de defensa solo puede editarse mientras el proyecto está aprobado o en defensa.',403);
        $old=$this->currentLocked($db,$id); $state=$this->state($input);
        if($old){$q=$db->prepare('UPDATE project_defenses SET defense_date=:defense_date,defense_time=:defense_time,location=:location,modality=:modality,updated_at=CURRENT_TIMESTAMP WHERE id=:id');$q->execute($state+['id'=>$old['id']]);$entity=(int)$old['id'];$attempt=(int)$old['attempt_number'];}
        else {$attempt=1;$q=$db->prepare('INSERT INTO project_defenses(project_id,attempt_number,defense_date,defense_time,location,modality) VALUES(:project_id,:attempt_number,:defense_date,:defense_time,:location,:modality)');$q->execute($state+['project_id'=>$id,'attempt_number'=>$attempt]);$entity=(int)$db->lastInsertId();}
        (new ProjectAuditService($db))->record($id,$actor,'thesis_defense_information_updated','project_defense',$entity,$old?$this->audit($old):null,$state+['attempt'=>$attempt]); return $state+['attempt_number'=>$attempt];
    }
    private function startNewAttemptTx(PDO $db, int $id, string $expected, int $actor): array {
        $project=$this->project($db,$id); if((string)$project['status']!=='defense'||$expected!=='defense') throw new ThesisDefenseException('El proyecto cambió de estado; no puede iniciarse una nueva defensa.',409);
        $old=$this->currentLocked($db,$id); if(!$old || (string)$old['result']!=='rejected') throw new ThesisDefenseException('Solo puede iniciarse una nueva defensa después de un resultado no aprobado.',409);
        $attempt=(int)$old['attempt_number']+1; $q=$db->prepare('INSERT INTO project_defenses(project_id,attempt_number) VALUES(:project_id,:attempt_number)');$q->execute(['project_id'=>$id,'attempt_number'=>$attempt]);$entity=(int)$db->lastInsertId();
        (new ProjectAuditService($db))->record($id,$actor,'defense_attempt_started','project_defense',$entity,['previous_attempt'=>(int)$old['attempt_number'],'previous_result'=>'rejected'],['attempt'=>$attempt,'result'=>null]);
        return ['id'=>$entity,'attempt_number'=>$attempt,'status'=>'defense'];
    }
    private function state(array $v): array {$date=trim((string)($v['defense_date']??''));$time=trim((string)($v['defense_time']??''));$location=trim((string)($v['location']??''));$modality=trim((string)($v['modality']??''));if($date!==''&&(!($d=DateTimeImmutable::createFromFormat('!Y-m-d',$date))||$d->format('Y-m-d')!==$date))throw new ThesisDefenseException('La fecha no es válida.');if($time!==''&&(!($t=DateTimeImmutable::createFromFormat('!H:i',$time))||$t->format('H:i')!==$time))throw new ThesisDefenseException('La hora no es válida.');if(mb_strlen($location)>255)throw new ThesisDefenseException('El lugar no puede superar 255 caracteres.');if($modality!==''&&!in_array($modality,['presential','virtual','hybrid'],true))throw new ThesisDefenseException('La modalidad no es válida.');return ['defense_date'=>$date?:null,'defense_time'=>$time===''?null:$time.':00','location'=>$location?:null,'modality'=>$modality?:null];}
    private function audit(array $v): array { return ['defense_date'=>$v['defense_date'],'defense_time'=>$v['defense_time'],'location'=>$v['location'],'modality'=>$v['modality'],'attempt'=>(int)$v['attempt_number']]; }
}
