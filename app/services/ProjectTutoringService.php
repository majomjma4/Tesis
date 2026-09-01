<?php

declare(strict_types=1);

/** Sincroniza la Tutoría de un proyecto usando usuarios docentes reales. */
final class ProjectTutoringService
{
    private const ROLE_CODES = ['tutor', 'co_tutor', 'cotutor', 'co-tutor'];

    /**
     * @return array{changed:bool,composition_changed:bool,primary_changed:bool,before:array,after:array,before_primary:?int,after_primary:int,added:array,removed:array}
     */
    public function sync(PDO $db, int $projectId, array $submittedIds, int $primaryId, ?int $storedPrimaryId): array
    {
        $finalIds = array_values(array_unique(array_filter(array_map('intval', $submittedIds), static fn(int $id): bool => $id > 0)));
        sort($finalIds, SORT_NUMERIC);
        if (!$finalIds) throw new ProjectTutoringException('El proyecto debe conservar al menos un tutor.');
        if (count($finalIds) !== count(array_filter(array_map('intval', $submittedIds), static fn(int $id): bool => $id > 0))) {
            throw new ProjectTutoringException('La Tutoría no puede contener docentes repetidos.');
        }
        if ($primaryId < 1 || !in_array($primaryId, $finalIds, true)) {
            throw new ProjectTutoringException('Selecciona una referencia válida para la Tutoría.');
        }

        $placeholders = implode(',', array_fill(0, count($finalIds), '?'));
        $teachers = $db->prepare("SELECT u.id,u.full_name FROM users u INNER JOIN teacher_profiles tp ON tp.user_id=u.id WHERE u.id IN ($placeholders) AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL AND tp.can_tutor=1 FOR UPDATE");
        $teachers->execute($finalIds);
        $teacherRows = $teachers->fetchAll();
        if (count($teacherRows) !== count($finalIds)) throw new ProjectTutoringException('Uno o más docentes ya no están disponibles para Tutoría.');
        $names = [];
        foreach ($teacherRows as $teacher) $names[(int)$teacher['id']] = (string)$teacher['full_name'];

        $roles = "'" . implode("','", self::ROLE_CODES) . "'";
        $current = $db->prepare("SELECT pp.id,pp.user_id,pp.role_code,pp.status,u.full_name FROM project_participants pp INNER JOIN users u ON u.id=pp.user_id WHERE pp.project_id=:project_id AND LOWER(pp.role_code) IN ($roles) FOR UPDATE");
        $current->execute(['project_id'=>$projectId]);
        $rows = $current->fetchAll();
        $currentIds = [];
        $currentNames = [];
        foreach ($rows as $row) {
            if ((string)$row['status'] !== 'active') continue;
            $userId = (int)$row['user_id'];
            $currentIds[$userId] = $userId;
            $currentNames[$userId] = (string)$row['full_name'];
        }
        if ($storedPrimaryId && !isset($currentIds[$storedPrimaryId])) {
            $currentIds[$storedPrimaryId] = $storedPrimaryId;
            $label = $db->prepare('SELECT full_name FROM users WHERE id=:id');
            $label->execute(['id'=>$storedPrimaryId]);
            $currentNames[$storedPrimaryId] = (string)($label->fetchColumn() ?: 'Docente no disponible');
        }
        $beforeIds = array_values($currentIds);
        sort($beforeIds, SORT_NUMERIC);
        $compositionChanged = $beforeIds !== $finalIds;
        $primaryChanged = (int)$storedPrimaryId !== $primaryId;
        $addedIds = array_values(array_diff($finalIds, $beforeIds));
        $removedIds = array_values(array_diff($beforeIds, $finalIds));

        if ($compositionChanged) {
            $db->prepare("UPDATE project_participants SET status='inactive',removed_at=COALESCE(removed_at,CURRENT_TIMESTAMP) WHERE project_id=:project_id AND LOWER(role_code) IN ($roles)")
                ->execute(['project_id'=>$projectId]);
            $find = $db->prepare("SELECT id FROM project_participants WHERE project_id=:project_id AND user_id=:user_id AND role_code='tutor' LIMIT 1 FOR UPDATE");
            $reactivate = $db->prepare("UPDATE project_participants SET status='active',removed_at=NULL,permission_level='review',is_leader=0,assigned_at=CURRENT_TIMESTAMP WHERE id=:id");
            $insert = $db->prepare("INSERT INTO project_participants(project_id,user_id,role_code,permission_level,is_leader,status,removed_at) VALUES(:project_id,:user_id,'tutor','review',0,'active',NULL)");
            foreach ($finalIds as $userId) {
                $find->execute(['project_id'=>$projectId,'user_id'=>$userId]);
                $rowId = (int)($find->fetchColumn() ?: 0);
                if ($rowId) $reactivate->execute(['id'=>$rowId]);
                else $insert->execute(['project_id'=>$projectId,'user_id'=>$userId]);
            }
        }

        return [
            'changed'=>$compositionChanged||$primaryChanged,
            'composition_changed'=>$compositionChanged,
            'primary_changed'=>$primaryChanged,
            'before'=>array_values(array_map(static fn(int $id):array=>['user_id'=>$id,'name'=>$currentNames[$id]??'Docente no disponible'],$beforeIds)),
            'after'=>array_values(array_map(static fn(int $id):array=>['user_id'=>$id,'name'=>$names[$id]],$finalIds)),
            'before_primary'=>$storedPrimaryId,
            'after_primary'=>$primaryId,
            'added'=>array_values(array_map(static fn(int $id):array=>['user_id'=>$id,'name'=>$names[$id]],$addedIds)),
            'removed'=>array_values(array_map(static fn(int $id):array=>['user_id'=>$id,'name'=>$currentNames[$id]??'Docente no disponible'],$removedIds)),
        ];
    }
}
