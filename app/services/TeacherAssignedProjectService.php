<?php

declare(strict_types=1);

/** Builds the authenticated teacher's real academic-assignment inbox. */
final class TeacherAssignedProjectService
{
    /** @var array<string,string> */
    private const RELATION_LABELS = ['tutor'=>'Tutor','cotutor'=>'Cotutor','tribunal'=>'Tribunal'];
    /** @var list<string> */
    private const RELATION_ORDER = ['tutor','cotutor','tribunal'];
    /** @var list<string> */
    private const TERMINAL_STATUSES = ['completed','published','closed','withdrawn'];

    /** @return array{projects:list<array<string,mixed>>,types:list<array{value:string,label:string}>,periods:list<array{value:string,label:string}>,relations:list<array{value:string,label:string}>} */
    public function forTeacher(int $teacherId, ?int $periodId = null): array
    {
        if ($teacherId < 1) return ['projects'=>[], 'types'=>[], 'periods'=>[], 'relations'=>[]];
        $db = Database::connection();
        $projects = $this->projects($db, $teacherId, $periodId);
        $projects = array_merge($projects, $this->directRepositoryProjects($db, $teacherId, $periodId));
        if ($projects === []) return ['projects'=>[], 'types'=>[], 'periods'=>[], 'relations'=>[]];
        $ids = array_map(static fn(array $row): int => (int)$row['id'], $projects);
        $relations = $this->relations($db, $teacherId, $ids);
        $authors = $this->authors($db, $ids);
        $situationRelations = [];
        foreach ($projects as $project) {
            $projectId = (int)$project['id'];
            $codes = $relations[$projectId] ?? [];
            if ((int)$project['tutor_id'] === $teacherId) $codes[] = 'tutor';
            $situationRelations[$projectId] = array_values(array_unique($codes));
        }
        try {
            $workflowProjects = array_values(array_filter($projects, static fn(array $project): bool => (string)($project['publication_origin'] ?? 'workflow') === 'workflow'));
            $workflowRelations = array_intersect_key($situationRelations, array_flip(array_map(static fn(array $project): int => (int)$project['id'], $workflowProjects)));
            $reviewSituations = (new ProjectReviewSituationService())->teacherSituations($workflowProjects, $workflowRelations, $db);
        } catch (Throwable $error) {
            error_log('Teacher assigned project situation: ' . $error->getMessage());
            $reviewSituations = [];
        }
        $items = [];
        foreach ($projects as $project) {
            $id = (int)$project['id'];
            $codes = $relations[$id] ?? [];
            if ((int)$project['tutor_id'] === $teacherId) $codes[] = 'tutor';
            $codes = $this->orderedUnique($codes);
            $studentRows = $authors[$id] ?? [];
            $names = array_map(static fn(array $row): string => (string)$row['name'], $studentRows);
            $status = (string)$project['status'];
            $labels = project_academic_labels($status);
            $isDirect = (string)($project['publication_origin'] ?? 'workflow') === 'direct_repository';
            $teacherSituation = $isDirect ? ['key'=>'direct_repository','label'=>'Publicado directamente en el repositorio','description'=>'Consulta institucional de solo lectura.','requires_attention'=>false,'actor'=>'repository','review_units'=>0] : ($reviewSituations[$id] ?? [
                'key'=>'waiting_process', 'label'=>'En seguimiento',
                'description'=>'No fue posible determinar una acción docente; se requiere revisión del expediente.',
                'requires_attention'=>false, 'actor'=>'unknown', 'review_units'=>0,
            ]);
            $items[] = [
                'id'=>$id, 'code'=>(string)$project['code'], 'title'=>(string)$project['title'],
                'type'=>(string)$project['type_name'], 'type_code'=>(string)$project['type_code'], 'updated_at'=>(string)$project['updated_at'],
                'period'=>(string)$project['period_name'], 'period_id'=>(string)$project['period_id'],
                'students'=>$this->compactNames($names), 'student_rows'=>$studentRows, 'students_search'=>implode(' ', $names),
                'relationships'=>array_map(static fn(string $code): array => ['code'=>$code, 'label'=>self::RELATION_LABELS[$code]], $codes),
                'status'=>(string)$labels['status'], 'status_key'=>$status,
                'publication_origin'=>(string)($project['publication_origin'] ?? 'workflow'),
                'is_direct_repository'=>$isDirect, 'publisher'=>(string)($project['publisher'] ?? ''),
                'teacher_situation'=>$teacherSituation['label'],
                'teacher_situation_requires_attention'=>$teacherSituation['requires_attention'],
                'teacher_situation_data'=>$teacherSituation,
                'review_units'=>(int)($teacherSituation['review_units'] ?? 0),
                'tab'=>in_array($status, self::TERMINAL_STATUSES, true) ? 'completed' : 'course',
            ];
        }
        $relationCatalog = [];
        foreach ($items as $item) foreach ((array)$item['relationships'] as $relation) $relationCatalog[(string)$relation['code']] = (string)$relation['label'];
        $orderedRelations = [];
        foreach (self::RELATION_ORDER as $code) if (isset($relationCatalog[$code])) $orderedRelations[] = ['value'=>$code, 'label'=>$relationCatalog[$code]];
        return ['projects'=>$items, 'types'=>$this->catalog($items, 'type_code', 'type'), 'periods'=>$this->catalog($items, 'period_id', 'period'), 'relations'=>$orderedRelations];
    }

    /** @return list<array<string,mixed>> */
    private function projects(PDO $db, int $teacherId, ?int $periodId): array
    {
        $period = $periodId !== null ? ' AND p.academic_period_id=:period_id' : '';
        $query = $db->prepare("SELECT p.id,p.code,p.title,p.status,p.tutor_id,p.updated_at,pt.code type_code,pt.name type_name,ap.id period_id,ap.name period_name FROM projects p INNER JOIN project_types pt ON pt.id=p.project_type_id INNER JOIN academic_periods ap ON ap.id=p.academic_period_id WHERE p.deleted_at IS NULL AND p.publication_origin='workflow'{$period} AND (p.tutor_id=:teacher_tutor OR EXISTS (SELECT 1 FROM project_participants pp WHERE pp.project_id=p.id AND pp.user_id=:teacher_participant AND pp.status='active' AND pp.removed_at IS NULL AND LOWER(pp.role_code) IN ('tutor','cotutor','co_tutor','co-tutor','tribunal','jury'))) ORDER BY p.updated_at DESC,p.id DESC");
        $params=['teacher_tutor'=>$teacherId, 'teacher_participant'=>$teacherId]; if ($periodId !== null) $params['period_id']=$periodId;
        $query->execute($params);
        return $query->fetchAll();
    }

    /** Direct repository projects are historical read-only assignments, never active assignments. */
    private function directRepositoryProjects(PDO $db, int $teacherId, ?int $periodId): array
    {
        $period = $periodId !== null ? ' AND p.academic_period_id=:period_id' : '';
        $query = $db->prepare("SELECT p.id,p.code,p.title,p.status,p.tutor_id,p.updated_at,p.publication_origin,p.repository_added_by,
            pt.code type_code,pt.name type_name,ap.id period_id,ap.name period_name,publisher.full_name publisher
            FROM projects p INNER JOIN project_types pt ON pt.id=p.project_type_id INNER JOIN academic_periods ap ON ap.id=p.academic_period_id
            LEFT JOIN users publisher ON publisher.id=p.repository_added_by
            WHERE p.deleted_at IS NULL AND p.withdrawn_at IS NULL AND p.status='published' AND p.is_available=1
              AND p.publication_origin='direct_repository' AND (p.tutor_id=:teacher_tutor OR EXISTS (SELECT 1 FROM project_participants pp WHERE pp.project_id=p.id AND pp.user_id=:teacher_participant_direct AND pp.status='active' AND pp.removed_at IS NULL AND LOWER(pp.role_code) IN ('tutor','cotutor','co_tutor','co-tutor','tribunal','jury'))){$period}
            ORDER BY p.published_at DESC,p.id DESC");
        $params=['teacher_tutor'=>$teacherId,'teacher_participant_direct'=>$teacherId]; if ($periodId !== null) $params['period_id']=$periodId;
        $query->execute($params);
        return $query->fetchAll();
    }

    /** @param list<int> $ids @return array<int,list<string>> */
    private function relations(PDO $db, int $teacherId, array $ids): array
    {
        $params = ['teacher'=>$teacherId]; $placeholders = $this->placeholders($ids, 'relation', $params);
        $query = $db->prepare("SELECT project_id,LOWER(role_code) role_code FROM project_participants WHERE user_id=:teacher AND status='active' AND removed_at IS NULL AND project_id IN ({$placeholders}) AND LOWER(role_code) IN ('tutor','cotutor','co_tutor','co-tutor','tribunal','jury')");
        $query->execute($params); $result=[];
        foreach ($query->fetchAll() as $row) $result[(int)$row['project_id']][]=$this->canonical((string)$row['role_code']);
        return $result;
    }

    /** @param list<int> $ids @return array<int,list<string>> */
    private function authors(PDO $db, array $ids): array
    {
        $params=[]; $placeholders=$this->placeholders($ids, 'author', $params);
        $query=$db->prepare("SELECT pp.project_id,pp.user_id,u.full_name FROM project_participants pp INNER JOIN users u ON u.id=pp.user_id WHERE pp.project_id IN ({$placeholders}) AND pp.role_code='student' AND pp.status='active' AND pp.removed_at IS NULL AND u.deleted_at IS NULL AND u.purged_at IS NULL ORDER BY pp.project_id,pp.is_leader DESC,u.full_name");
        $query->execute($params); $result=[];
        foreach ($query->fetchAll() as $row) $result[(int)$row['project_id']][]=['id'=>(int)$row['user_id'],'name'=>(string)$row['full_name']];
        return $result;
    }

    /** @param list<int> $ids @param array<string,int> $params */
    private function placeholders(array $ids, string $prefix, array &$params): string
    {
        $keys=[]; foreach ($ids as $index=>$id) { $key=$prefix.'_'.$index; $keys[]=':'.$key; $params[$key]=$id; }
        return implode(',', $keys);
    }

    /** @param list<string> $codes @return list<string> */
    private function orderedUnique(array $codes): array
    {
        $unique=[]; foreach ($codes as $code) $unique[$this->canonical($code)]=true;
        return array_values(array_filter(self::RELATION_ORDER, static fn(string $code): bool => isset($unique[$code])));
    }

    private function canonical(string $code): string
    {
        return match (strtolower($code)) { 'co_tutor','co-tutor'=>'cotutor', 'jury'=>'tribunal', default=>strtolower($code) };
    }

    /** @param list<string> $names */
    private function compactNames(array $names): string
    {
        if ($names===[]) return 'Sin autores activos registrados';
        if (count($names)<=2) return implode(' y ', $names);
        return implode(', ', array_slice($names,0,2)).' y '.(count($names)-2).' más';
    }

    /** @param list<array<string,mixed>> $items @return list<array{value:string,label:string}> */
    private function catalog(array $items, string $valueKey, string $labelKey): array
    {
        $values=[]; foreach ($items as $item) $values[(string)$item[$valueKey]]=(string)$item[$labelKey];
        asort($values, SORT_NATURAL|SORT_FLAG_CASE); $result=[];
        foreach ($values as $value=>$label) $result[]=['value'=>$value,'label'=>$label];
        return $result;
    }
}
