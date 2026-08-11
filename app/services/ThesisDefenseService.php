<?php
declare(strict_types=1);

final class ThesisDefenseException extends InvalidArgumentException { public function __construct(string $message,private int $status=422){parent::__construct($message);} public function httpStatus():int{return $this->status;} }
/** Persiste información opcional de defensa sin alterar Tribunal ni estado. */
final class ThesisDefenseService
{
 public function save(int $id,array $input,int $actor):array{return Database::transaction(fn(PDO $db)=>$this->saveTx($db,$id,$input,$actor));}
 public function current(int $id):?array{$q=Database::connection()->prepare('SELECT defense_date,defense_time,location,modality FROM project_defenses WHERE project_id=:id');$q->execute(['id'=>$id]);return $q->fetch()?:null;}
 private function saveTx(PDO $db,int $id,array $input,int $actor):array{
  $q=$db->prepare("SELECT p.id,p.status,pt.code type_code FROM projects p JOIN project_types pt ON pt.id=p.project_type_id WHERE p.id=:id AND p.deleted_at IS NULL FOR UPDATE");$q->execute(['id'=>$id]);$p=$q->fetch();if(!$p||(string)$p['type_code']!=='thesis')throw new ThesisDefenseException('El proyecto de Titulación solicitado no está disponible.',404);if(!in_array((string)$p['status'],['approved','defense'],true))throw new ThesisDefenseException('La información de defensa solo puede editarse mientras el proyecto está aprobado o en defensa.',403);
  $oldQ=$db->prepare('SELECT * FROM project_defenses WHERE project_id=:id FOR UPDATE');$oldQ->execute(['id'=>$id]);$old=$oldQ->fetch()?:null;$state=$this->state($input);
  if($old){$s=$db->prepare('UPDATE project_defenses SET defense_date=:defense_date,defense_time=:defense_time,location=:location,modality=:modality,updated_at=CURRENT_TIMESTAMP WHERE id=:id');$s->execute($state+['id'=>$old['id']]);$entity=(int)$old['id'];}else{$s=$db->prepare('INSERT INTO project_defenses(project_id,defense_date,defense_time,location,modality) VALUES(:project_id,:defense_date,:defense_time,:location,:modality)');$s->execute($state+['project_id'=>$id]);$entity=(int)$db->lastInsertId();}
  (new ProjectAuditService($db))->record($id,$actor,'thesis_defense_information_updated','project_defense',$entity,$old?$this->audit($old):null,$state);return $state;
 }
 private function state(array $v):array{$date=trim((string)($v['defense_date']??''));$time=trim((string)($v['defense_time']??''));$location=trim((string)($v['location']??''));$modality=trim((string)($v['modality']??''));if($date!==''&&(!($d=DateTimeImmutable::createFromFormat('!Y-m-d',$date))||$d->format('Y-m-d')!==$date))throw new ThesisDefenseException('La fecha no es válida.');if($time!==''&&(!($t=DateTimeImmutable::createFromFormat('!H:i',$time))||$t->format('H:i')!==$time))throw new ThesisDefenseException('La hora no es válida.');if(mb_strlen($location)>255)throw new ThesisDefenseException('El lugar no puede superar 255 caracteres.');if($modality!==''&&!in_array($modality,['presential','virtual','hybrid'],true))throw new ThesisDefenseException('La modalidad no es válida.');return ['defense_date'=>$date?:null,'defense_time'=>$time===''?null:$time.':00','location'=>$location?:null,'modality'=>$modality?:null];}
 private function audit(array $v):array{return ['defense_date'=>$v['defense_date'],'defense_time'=>$v['defense_time'],'location'=>$v['location'],'modality'=>$v['modality']];}
}
