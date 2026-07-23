<?php
declare(strict_types=1);

/** Genera códigos dentro de la misma transacción que crea el proyecto. */
final class ProjectCodeService
{
    public function next(PDO $db, int $projectTypeId, string $typeCode, int $year): string
    {
        if (!$db->inTransaction()) throw new LogicException('El código debe reservarse dentro de una transacción.');
        $defaults=(new SystemSettingModel())->defaults();
        $settings=$defaults;
        $query=$db->prepare("SELECT setting_key,setting_value FROM system_settings WHERE setting_key IN ('project_code_prefixes','project_code_digits')");
        $query->execute();
        foreach($query->fetchAll() as $row){
            if($row['setting_key']==='project_code_prefixes'){
                $decoded=json_decode((string)$row['setting_value'],true);
                if(is_array($decoded))$settings['project_code_prefixes']=array_replace($defaults['project_code_prefixes'],$decoded);
            }else $settings['project_code_digits']=max(2,min(6,(int)$row['setting_value']));
        }
        $insert = $db->prepare('INSERT IGNORE INTO project_code_sequences (project_type_id,code_year,next_number) VALUES (:type_id,:year,1)');
        $insert->execute(['type_id'=>$projectTypeId,'year'=>$year]);
        $select = $db->prepare('SELECT next_number FROM project_code_sequences WHERE project_type_id=:type_id AND code_year=:year FOR UPDATE');
        $select->execute(['type_id'=>$projectTypeId,'year'=>$year]); $number=(int)$select->fetchColumn();
        $exists=$db->prepare('SELECT 1 FROM projects WHERE code=:code LIMIT 1');
        $prefix=(string)($settings['project_code_prefixes'][$typeCode]??'PRY');
        $digits=(int)$settings['project_code_digits'];
        do{
            $code=sprintf('%s-%d-%0'.$digits.'d',$prefix,$year,$number++);
            $exists->execute(['code'=>$code]);
        }while($exists->fetchColumn());
        $sync=$db->prepare('UPDATE project_code_sequences SET next_number=:next WHERE project_type_id=:type_id AND code_year=:year');
        $sync->execute(['next'=>$number,'type_id'=>$projectTypeId,'year'=>$year]);
        return $code;
    }
}
