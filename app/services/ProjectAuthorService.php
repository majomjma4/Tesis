<?php

declare(strict_types=1);

/** Sincroniza los autores estudiantes de un proyecto sin destruir su historial. */
final class ProjectAuthorService
{
    /**
     * @return array{changed:bool,composition_changed:bool,leader_changed:bool,before:array,after:array,before_leader:?int,after_leader:int,added:array,removed:array}
     */
    public function sync(PDO $db, int $projectId, array $submittedIds, int $leaderId): array
    {
        $rawIds = array_values(array_filter(array_map('intval', $submittedIds), static fn(int $id): bool => $id > 0));
        $finalIds = array_values(array_unique($rawIds));
        sort($finalIds, SORT_NUMERIC);
        if (!$finalIds) throw new ProjectAuthorException('El proyecto debe conservar al menos un autor.');
        if (count($finalIds) !== count($rawIds)) throw new ProjectAuthorException('Los autores no pueden repetirse.');
        if ($leaderId < 1 || !in_array($leaderId, $finalIds, true)) {
            throw new ProjectAuthorException('Selecciona un autor principal válido.');
        }

        $placeholders = implode(',', array_fill(0, count($finalIds), '?'));
        $students = $db->prepare("SELECT u.id,u.full_name,sp.institutional_code
            FROM users u
            INNER JOIN student_profiles sp ON sp.user_id=u.id
            INNER JOIN user_roles ur ON ur.user_id=u.id
            INNER JOIN roles r ON r.id=ur.role_id AND r.code='student'
            WHERE u.id IN ($placeholders)
              AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL
            FOR UPDATE");
        $students->execute($finalIds);
        $studentRows = $students->fetchAll();
        if (count($studentRows) !== count($finalIds)) {
            throw new ProjectAuthorException('Uno o más estudiantes ya no están disponibles como autores.');
        }
        $studentsById = [];
        foreach ($studentRows as $student) $studentsById[(int)$student['id']] = $student;

        $current = $db->prepare("SELECT pp.id,pp.user_id,pp.is_leader,pp.status,pp.removed_at,u.full_name,sp.institutional_code
            FROM project_participants pp
            INNER JOIN users u ON u.id=pp.user_id
            LEFT JOIN student_profiles sp ON sp.user_id=u.id
            WHERE pp.project_id=:project_id AND pp.role_code='student'
            FOR UPDATE");
        $current->execute(['project_id'=>$projectId]);
        $currentRows = $current->fetchAll();
        $currentByUser = [];
        $beforeIds = [];
        $beforeRows = [];
        $beforeLeader = null;
        foreach ($currentRows as $row) {
            $currentByUser[(int)$row['user_id']] = $row;
            if ((string)$row['status'] !== 'active' || $row['removed_at'] !== null) continue;
            $userId = (int)$row['user_id'];
            $beforeIds[] = $userId;
            $beforeRows[$userId] = $row;
            if ((int)$row['is_leader'] === 1) $beforeLeader = $userId;
        }
        sort($beforeIds, SORT_NUMERIC);
        $compositionChanged = $beforeIds !== $finalIds;
        $leaderChanged = $beforeLeader !== $leaderId;
        $addedIds = array_values(array_diff($finalIds, $beforeIds));
        $removedIds = array_values(array_diff($beforeIds, $finalIds));

        if ($compositionChanged) {
            $db->prepare("UPDATE project_participants
                SET status='inactive',removed_at=COALESCE(removed_at,CURRENT_TIMESTAMP),is_leader=0
                WHERE project_id=:project_id AND role_code='student' AND status='active' AND removed_at IS NULL")
                ->execute(['project_id'=>$projectId]);
            $reactivate = $db->prepare("UPDATE project_participants
                SET status='active',removed_at=NULL,permission_level='contribute',is_leader=:is_leader,assigned_at=CURRENT_TIMESTAMP
                WHERE id=:id");
            $insert = $db->prepare("INSERT INTO project_participants(project_id,user_id,role_code,permission_level,is_leader,status,removed_at)
                VALUES(:project_id,:user_id,'student','contribute',:is_leader,'active',NULL)");
            foreach ($finalIds as $userId) {
                $isLeader = $userId === $leaderId ? 1 : 0;
                $existing = $currentByUser[$userId] ?? null;
                if ($existing) $reactivate->execute(['id'=>(int)$existing['id'],'is_leader'=>$isLeader]);
                else $insert->execute(['project_id'=>$projectId,'user_id'=>$userId,'is_leader'=>$isLeader]);
            }
        } elseif ($leaderChanged) {
            $db->prepare("UPDATE project_participants
                SET is_leader=CASE WHEN user_id=:leader_id THEN 1 ELSE 0 END
                WHERE project_id=:project_id AND role_code='student' AND status='active' AND removed_at IS NULL")
                ->execute(['leader_id'=>$leaderId,'project_id'=>$projectId]);
        }

        $describe = static function(int $id, array $source): array {
            return ['user_id'=>$id,'name'=>(string)($source['full_name'] ?? 'Estudiante no disponible'),'institutional_code'=>(string)($source['institutional_code'] ?? '')];
        };
        return [
            'changed'=>$compositionChanged || $leaderChanged,
            'composition_changed'=>$compositionChanged,
            'leader_changed'=>$leaderChanged,
            'before'=>array_values(array_map(static fn(int $id):array => $describe($id, $beforeRows[$id] ?? []), $beforeIds)),
            'after'=>array_values(array_map(static fn(int $id):array => $describe($id, $studentsById[$id]), $finalIds)),
            'before_leader'=>$beforeLeader,
            'after_leader'=>$leaderId,
            'added'=>array_values(array_map(static fn(int $id):array => $describe($id, $studentsById[$id]), $addedIds)),
            'removed'=>array_values(array_map(static fn(int $id):array => $describe($id, $beforeRows[$id] ?? []), $removedIds)),
        ];
    }
}
