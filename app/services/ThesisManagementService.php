<?php

declare(strict_types=1);

/** Consulta la bandeja operativa de Titulación sin mutar el flujo académico. */
final class ThesisManagementService
{
    private const ELIGIBLE_STATUSES = ['approved', 'defense'];

    /** @return array{projects:list<array<string,mixed>>,periods:list<array{id:int,name:string}>,summary:array<string,int>} */
    public function listing(): array
    {
        $db = Database::connection();
        $rows = $db->query(
            "SELECT p.id,p.code,p.title,p.status,p.updated_at,p.approved_at,p.created_at,
                    ap.id period_id,ap.name period_name,u.full_name tutor_name,pd.defense_date,pd.defense_time,pd.location defense_location,pd.modality defense_modality,pd.result defense_result,pd.result_notes
             FROM projects p
             INNER JOIN project_types pt ON pt.id=p.project_type_id AND pt.code='thesis'
             INNER JOIN academic_periods ap ON ap.id=p.academic_period_id
             LEFT JOIN users u ON u.id=p.tutor_id
             LEFT JOIN project_defenses pd ON pd.project_id=p.id
             WHERE p.deleted_at IS NULL AND p.status IN ('approved','defense')
             ORDER BY p.updated_at DESC,p.id DESC"
        )->fetchAll();
        if ($rows === []) return ['projects'=>[], 'periods'=>[], 'summary'=>$this->emptySummary()];

        $ids = array_map(static fn(array $row): int => (int) $row['id'], $rows);
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $people = $db->prepare(
            "SELECT pp.project_id,pp.user_id,pp.role_code,pp.is_leader,u.full_name
             FROM project_participants pp INNER JOIN users u ON u.id=pp.user_id
             WHERE pp.project_id IN ($marks) AND pp.status='active' AND pp.removed_at IS NULL
               AND LOWER(pp.role_code) IN ('student','tutor','cotutor','co_tutor','co-tutor','tribunal','jury')
             ORDER BY pp.project_id,pp.is_leader DESC,u.full_name"
        );
        $people->execute($ids);
        $byProject = [];
        foreach ($people->fetchAll() as $person) $byProject[(int) $person['project_id']][] = $person;

        $summary = $this->emptySummary();
        $projects = [];
        foreach ($rows as $row) {
            $members = $byProject[(int) $row['id']] ?? [];
            $students=[]; $cotutors=[]; $tribunal=[]; $tribunalIds=[];
            foreach ($members as $member) {
                $role = strtolower((string) $member['role_code']);
                if ($role === 'student') $students[] = (string) $member['full_name'];
                elseif (in_array($role, ['cotutor','co_tutor','co-tutor'], true)) $cotutors[] = (string) $member['full_name'];
                elseif (in_array($role, ['tribunal','jury'], true)) {$tribunal[] = (string) $member['full_name']; $tribunalIds[]=(int)$member['user_id'];}
            }
            $situation = $this->situation((string) $row['status'], count($tribunal));
            $summary[$situation['key']]++;
            $projects[] = $row + [
                'students'=>$students, 'students_label'=>implode(' · ', $students) ?: 'Sin estudiantes activos',
                'cotutors'=>$cotutors, 'tutor_label'=>$this->tutorLabel((string) ($row['tutor_name'] ?? ''), $cotutors),
                'tribunal_members'=>$tribunal, 'tribunal_member_ids'=>$tribunalIds, 'tribunal_count'=>count($tribunal),
                'tribunal_label'=>count($tribunal) ? count($tribunal).' '.(count($tribunal) === 1 ? 'miembro' : 'miembros') : 'Sin asignar',
                'situation'=>$situation,
            ];
        }
        $periods=[];
        foreach ($rows as $row) $periods[(int)$row['period_id']] = ['id'=>(int)$row['period_id'], 'name'=>(string)$row['period_name']];
        return ['projects'=>$projects, 'periods'=>array_values($periods), 'summary'=>$summary];
    }

    /** @return array{key:string,label:string,description:string,action:string} */
    private function situation(string $status, int $tribunalCount): array
    {
        if ($status === 'approved') return ['key'=>'pending_tribunal','label'=>'Pendiente de Tribunal','description'=>ThesisTribunalService::isValidMemberCount($tribunalCount)?'Tribunal listo para confirmar':'Se requieren entre 3 y 5 miembros activos','action'=>'Gestionar Tribunal'];
        if ($status === 'defense') return ['key'=>'defense','label'=>'Defensa en curso','description'=>'Proceso de evaluación en curso','action'=>'Ver proceso'];
        return ['key'=>'pending_publication','label'=>'Pendiente de publicación','description'=>'Aprobado por el Tribunal','action'=>'Continuar proceso'];
    }

    /** @return array<string,int> */
    private function emptySummary(): array { return ['pending_tribunal'=>0,'defense'=>0]; }
    private function tutorLabel(string $tutor, array $cotutors): string { $names=array_filter(array_merge([$tutor],$cotutors)); return $names ? implode(' · ', $names) : 'Sin tutor asignado'; }
}
