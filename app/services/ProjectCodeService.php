<?php
declare(strict_types=1);

/** Genera códigos dentro de la misma transacción que crea el proyecto. */
final class ProjectCodeService
{
    public function next(PDO $db, int $projectTypeId, string $typeCode, int $year): string
    {
        if (!$db->inTransaction()) throw new LogicException('El código debe reservarse dentro de una transacción.');
        $insert = $db->prepare('INSERT IGNORE INTO project_code_sequences (project_type_id,code_year,next_number) VALUES (:type_id,:year,1)');
        $insert->execute(['type_id'=>$projectTypeId,'year'=>$year]);
        $select = $db->prepare('SELECT next_number FROM project_code_sequences WHERE project_type_id=:type_id AND code_year=:year FOR UPDATE');
        $select->execute(['type_id'=>$projectTypeId,'year'=>$year]); $number=(int)$select->fetchColumn();
        $update = $db->prepare('UPDATE project_code_sequences SET next_number=next_number+1 WHERE project_type_id=:type_id AND code_year=:year');
        $update->execute(['type_id'=>$projectTypeId,'year'=>$year]);
        $prefix = match ($typeCode) { 'thesis'=>'TIT','pis'=>'PIS','practice'=>'PRA','community'=>'VIN',default=>'PRY' };
        return sprintf('%s-%d-%04d',$prefix,$year,$number);
    }
}
