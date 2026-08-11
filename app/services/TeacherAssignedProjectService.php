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
    private const TERMINAL_STATUSES = ['tribunal_approved','published'];

    /** @return array{projects:list<array<string,mixed>>,types:list<array{value:string,label:string}>,periods:list<array{value:string,label:string}>,relations:list<array{value:string,label:string}>} */
    public function forTeacher(int $teacherId): array
    {
        if ($teacherId < 1) return ['projects'=>[], 'types'=>[], 'periods'=>[], 'relations'=>[]];
        $db = Database::connection();
        $projects = $this->projects($db, $teacherId);
        if ($projects === []) return ['projects'=>[], 'types'=>[], 'periods'=>[], 'relations'=>[]];
        $ids = array_map(static fn(array $row): int => (int)$row['id'], $projects);
        $relations = $this->relations($db, $teacherId, $ids);
        $authors = $this->authors($db, $ids);
        $reviewSituations = (new ProjectReviewSituationService())->forProjects($ids, $db);
        $items = [];
        foreach ($projects as $project) {
            $id = (int)$project['id'];
            $codes = $relations[$id] ?? [];
            if ((int)$project['tutor_id'] === $teacherId) $codes[] = 'tutor';
            $codes = $this->orderedUnique($codes);
            $names = $authors[$id] ?? [];
            $status = (string)$project['status'];
            $labels = project_academic_labels($status);
            $teacherSituation = $this->teacherSituation($status, $reviewSituations[$id] ?? ProjectReviewSituationService::emptySituation());
            $items[] = [
                'id'=>$id, 'code'=>(string)$project['code'], 'title'=>(string)$project['title'],
                'type'=>(string)$project['type_name'], 'type_code'=>(string)$project['type_code'],
                'period'=>(string)$project['period_name'], 'period_id'=>(string)$project['period_id'],
                'students'=>$this->compactNames($names), 'students_search'=>implode(' ', $names),
                'relationships'=>array_map(static fn(string $code): array => ['code'=>$code, 'label'=>self::RELATION_LABELS[$code]], $codes),
                'status'=>(string)$labels['status'], 'status_key'=>$status,
                'teacher_situation'=>$teacherSituation['label'],
                'teacher_situation_requires_attention'=>$teacherSituation['requires_attention'],
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
    private function projects(PDO $db, int $teacherId): array
    {
        $query = $db->prepare("SELECT p.id,p.code,p.title,p.status,p.tutor_id,p.updated_at,pt.code type_code,pt.name type_name,ap.id period_id,ap.name period_name FROM projects p INNER JOIN project_types pt ON pt.id=p.project_type_id INNER JOIN academic_periods ap ON ap.id=p.academic_period_id WHERE p.deleted_at IS NULL AND (p.tutor_id=:teacher_tutor OR EXISTS (SELECT 1 FROM project_participants pp WHERE pp.project_id=p.id AND pp.user_id=:teacher_participant AND pp.status='active' AND pp.removed_at IS NULL AND LOWER(pp.role_code) IN ('tutor','cotutor','co_tutor','co-tutor','tribunal','jury'))) ORDER BY p.updated_at DESC,p.id DESC");
        $query->execute(['teacher_tutor'=>$teacherId, 'teacher_participant'=>$teacherId]);
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
        $query=$db->prepare("SELECT pp.project_id,u.full_name FROM project_participants pp INNER JOIN users u ON u.id=pp.user_id WHERE pp.project_id IN ({$placeholders}) AND pp.role_code='student' AND pp.status='active' AND pp.removed_at IS NULL ORDER BY pp.project_id,pp.is_leader DESC,u.full_name");
        $query->execute($params); $result=[];
        foreach ($query->fetchAll() as $row) $result[(int)$row['project_id']][]=(string)$row['full_name'];
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

    /** @param array<string,mixed> $review */
    private function teacherSituation(string $status, array $review): array
    {
        if ($status === 'development') {
            if ((int)($review['pending_observations_count'] ?? 0) > 0) {
                return ['label'=>'En espera de ajustes por parte del estudiante', 'requires_attention'=>false];
            }
            if (!empty($review['has_new_delivery_after_corrections'])) {
                return ['label'=>'Nueva versión recibida · Revisión pendiente', 'requires_attention'=>true];
            }
            return ['label'=>'Trabajo en preparación por el estudiante', 'requires_attention'=>false];
        }
        if ($status === 'under_review') return ['label'=>'Revisión docente pendiente', 'requires_attention'=>true];
        if ($status === 'approved') return ['label'=>'Revisión académica completada', 'requires_attention'=>false];
        return ['label'=>null, 'requires_attention'=>false];
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
