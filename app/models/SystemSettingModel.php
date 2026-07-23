<?php
declare(strict_types=1);
final class SystemSettingModel
{
 private const CODE_TYPES=['thesis'=>'TIT','thesis_profile'=>'PFT','pis'=>'PIS','practice'=>'PRA','community'=>'VIN'];
 public function defaults():array{return ['institution_name'=>'Instituto Superior Tecnológico El Libertador','file_max_mb'=>20,'file_total_max_mb'=>50,'file_extensions'=>['pdf','docx','zip'],'project_code_prefixes'=>self::CODE_TYPES,'project_code_digits'=>3];}
 public function all():array
 {
  $values=$this->defaults();
  foreach(Database::connection()->query('SELECT setting_key,setting_value FROM system_settings')->fetchAll() as $row){
   $key=(string)$row['setting_key'];
   if(!array_key_exists($key,$values))continue;
   if(in_array($key,['file_extensions','project_code_prefixes'],true)){
    $decoded=json_decode((string)$row['setting_value'],true);
    if(is_array($decoded))$values[$key]=$key==='project_code_prefixes'?array_replace(self::CODE_TYPES,$decoded):$decoded;
   }elseif($key==='institution_name')$values[$key]=(string)$row['setting_value'];
   else $values[$key]=(int)$row['setting_value'];
  }
  return $values;
 }
 public function save(array $input,int $actor):void
 {
  $name=trim((string)($input['institution_name']??''));$max=(int)($input['file_max_mb']??0);$total=(int)($input['file_total_max_mb']??0);
  $extensions=array_values(array_intersect(['pdf','docx','zip'],array_map('strval',(array)($input['file_extensions']??[]))));
  $digits=(int)($input['project_code_digits']??0);$submitted=(array)($input['project_code_prefixes']??[]);$prefixes=[];
  foreach(self::CODE_TYPES as $type=>$fallback)$prefixes[$type]=strtoupper(trim((string)($submitted[$type]??$fallback)));
  if(mb_strlen($name)<5||mb_strlen($name)>180)throw new InvalidArgumentException('Ingresa un nombre institucional válido.');
  if($max<1||$max>100||$total<$max||$total>500)throw new InvalidArgumentException('El límite individual debe estar entre 1 y 100 MB y el total entre ese valor y 500 MB.');
  if(!$extensions)throw new InvalidArgumentException('Mantén al menos un formato de archivo habilitado.');
  if($digits<2||$digits>6)throw new InvalidArgumentException('La numeración de proyectos debe tener entre 2 y 6 dígitos.');
  foreach($prefixes as $prefix)if(!preg_match('/^[A-Z0-9]{2,6}$/',$prefix))throw new InvalidArgumentException('Cada prefijo debe contener entre 2 y 6 letras o números.');
  if(count(array_unique($prefixes))!==count($prefixes))throw new InvalidArgumentException('Cada tipo de proyecto debe tener un prefijo diferente.');
  Database::transaction(function(PDO $d)use($name,$max,$total,$extensions,$prefixes,$digits,$actor):void{
   $q=$d->prepare('INSERT INTO system_settings(setting_key,setting_value,updated_by) VALUES(:key,:value,:actor) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by=VALUES(updated_by)');
   $values=['institution_name'=>$name,'file_max_mb'=>(string)$max,'file_total_max_mb'=>(string)$total,'file_extensions'=>json_encode($extensions),'project_code_prefixes'=>json_encode($prefixes),'project_code_digits'=>(string)$digits];
   foreach($values as $key=>$value)$q->execute(['key'=>$key,'value'=>$value,'actor'=>$actor]);
   $a=$d->prepare("INSERT INTO admin_audit_log(actor_user_id,action,entity_type,details) VALUES(:actor,'settings_updated','settings',:details)");
   $a->execute(['actor'=>$actor,'details'=>json_encode(['file_max_mb'=>$max,'file_total_max_mb'=>$total,'file_extensions'=>$extensions,'project_code_prefixes'=>$prefixes,'project_code_digits'=>$digits],JSON_UNESCAPED_UNICODE)]);
  });
 }
}
