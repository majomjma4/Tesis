<?php
declare(strict_types=1);

final class ThesisTribunalException extends InvalidArgumentException
{
    public function __construct(string $message, private int $status = 422) { parent::__construct($message); }
    public function httpStatus(): int { return $this->status; }
}

final class ThesisTribunalService
{
    private const MIN_MEMBERS = 3;
    private const MAX_MEMBERS = 5;
    private const ACTIVE_PROJECT_STATUSES = ['development', 'under_review', 'approved', 'defense', 'tribunal_approved'];

    public static function isValidMemberCount(int $count): bool { return $count >= self::MIN_MEMBERS && $count <= self::MAX_MEMBERS; }
    public static function memberRangeLabel(): string { return self::MIN_MEMBERS . ' y ' . self::MAX_MEMBERS; }

    /** @return array<int,array{id:int,full_name:string,email:string,academic_title:?string,institutional_code:string}> */
    public function candidates(int $id): array
    {
        $project = $this->availableProject(Database::connection(), $id);
        return array_map(static fn(array $candidate): array => [
            'id' => $candidate['user_id'], 'full_name' => $candidate['name'], 'email' => $candidate['email'],
            'academic_title' => $candidate['academic_title'], 'institutional_code' => $candidate['institutional_code'],
        ], $this->candidatesWithLoadForProject(Database::connection(), $project, []));
    }

    /**
     * Solo lectura. $replacement permite solicitar un único sustituto de forma explícita.
     * @return array{project_id:int,desired_count:int,available_count:int,members:array<int,array<string,int|string|null>>}
     */
    public function suggest(int $id, int $desiredCount, array $exclusions = [], bool $replacement = false): array
    {
        if ($replacement) {
            if ($desiredCount !== 1) throw new ThesisTribunalException('La sugerencia de reemplazo debe solicitar un único docente.');
        } elseif (!self::isValidMemberCount($desiredCount)) {
            throw new ThesisTribunalException('Selecciona una cantidad de Tribunal entre ' . self::memberRangeLabel() . ' docentes.');
        }

        $db = Database::connection();
        $project = $this->availableProject($db, $id);
        $candidates = $this->candidatesWithLoadForProject($db, $project, $this->normalizeIds($exclusions));
        $available = count($candidates);

        if ($available < $desiredCount) {
            if ($replacement) throw new ThesisTribunalException('No existen otros docentes disponibles para realizar el reemplazo.');
            if ($available < self::MIN_MEMBERS) throw new ThesisTribunalException('No existen suficientes docentes disponibles para conformar el Tribunal.');
            throw new ThesisTribunalException('Solo hay ' . $available . ' docentes disponibles para conformar este Tribunal.');
        }

        $byLoad = [];
        foreach ($candidates as $candidate) $byLoad[(int) $candidate['effective_load']][] = $candidate;
        ksort($byLoad, SORT_NUMERIC);
        $ordered = [];
        foreach ($byLoad as $group) {
            if (count($group) > 1) shuffle($group);
            array_push($ordered, ...$group);
        }

        return ['project_id' => (int) $project['id'], 'desired_count' => $desiredCount, 'available_count' => $available, 'members' => array_slice($ordered, 0, $desiredCount)];
    }

    /** Catálogo de docentes elegibles con carga, sin selección ni persistencia. */
    public function availableWithLoad(int $id): array
    {
        $db = Database::connection();
        $project = $this->availableProject($db, $id);
        return $this->candidatesWithLoadForProject($db, $project, []);
    }

    public function save(int $id, string $expected, array $memberIds, string $reason, int $actor): array
    {
        return Database::transaction(fn(PDO $db) => $this->saveInTransaction($db, $id, $expected, $memberIds, $reason, $actor));
    }

    public function saveInTransaction(PDO $db, int $id, string $expected, array $memberIds, string $reason, int $actor): array
    {
        $p = $this->project($db, $id, true); $status = (string) $p['status'];
        if (!in_array($status, ['approved', 'defense'], true)) throw new ThesisTribunalException('El Tribunal solo puede gestionarse mientras el proyecto está aprobado o en defensa.');
        if ($expected !== $status) throw new ThesisTribunalException('El estado del proyecto cambió. Recarga el proceso antes de continuar.', 409);
        $ids = $this->normalizeIds($memberIds);
        if (!self::isValidMemberCount(count($ids))) throw new ThesisTribunalException('Selecciona entre ' . self::memberRangeLabel() . ' docentes para conformar el Tribunal.');
        $q = $db->prepare("SELECT pp.id,pp.user_id,LOWER(pp.role_code) role_code,pp.status,pp.removed_at,u.full_name FROM project_participants pp JOIN users u ON u.id=pp.user_id WHERE pp.project_id=:project AND (LOWER(pp.role_code) IN ('tutor','cotutor','co_tutor','co-tutor','tribunal','jury') OR pp.user_id=:tutor) FOR UPDATE");
        $q->execute(['project'=>$id, 'tutor'=>(int)$p['tutor_id']]); $rows = $q->fetchAll(); $current=[]; $blocked=[];
        foreach ($rows as $r) { $role=$r['role_code']; $uid=(int)$r['user_id']; if($uid===(int)$p['tutor_id'] || in_array($role,['tutor','cotutor','co_tutor','co-tutor'],true)) $blocked[]=$uid; if(in_array($role,['tribunal','jury'],true)&&$r['status']==='active'&&!$r['removed_at']) $current[$uid]=['user_id'=>$uid,'name'=>$r['full_name']]; }
        if (array_intersect($ids, $blocked)) throw new ThesisTribunalException('El Tutor o Cotutor del proyecto no puede formar parte del Tribunal.');
        $marks=implode(',',array_fill(0,count($ids),'?'));
        $valid=$db->prepare("SELECT DISTINCT u.id,u.full_name FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id AND r.code='teacher' JOIN teacher_profiles tp ON tp.user_id=u.id WHERE u.id IN ($marks) AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL FOR UPDATE");
        $valid->execute($ids); $next=[]; foreach($valid->fetchAll() as $r) $next[(int)$r['id']]=['user_id'=>(int)$r['id'],'name'=>$r['full_name']];
        if(count($next)!==count($ids)) throw new ThesisTribunalException('Uno o más integrantes seleccionados no son docentes activos válidos.');
        ksort($next); $currentIds=array_keys($current); sort($currentIds); $changed=$currentIds!==$ids; if(!$changed&&$status==='defense') throw new ThesisTribunalException('No se detectaron cambios en la composición del Tribunal.');
        $reason=trim($reason); if($status==='defense'&&(mb_strlen($reason)<5||mb_strlen($reason)>500)) throw new ThesisTribunalException('Indica un motivo de entre 5 y 500 caracteres para modificar el Tribunal durante la defensa.');
        if($changed){ if($current)$db->prepare("UPDATE project_participants SET status='inactive',removed_at=CURRENT_TIMESTAMP WHERE project_id=:id AND status='active' AND removed_at IS NULL AND LOWER(role_code) IN ('tribunal','jury')")->execute(['id'=>$id]); $find=$db->prepare("SELECT id FROM project_participants WHERE project_id=:p AND user_id=:u AND LOWER(role_code) IN ('tribunal','jury') ORDER BY id DESC LIMIT 1 FOR UPDATE"); $reactivate=$db->prepare("UPDATE project_participants SET role_code='tribunal',permission_level='review',status='active',removed_at=NULL,assigned_at=CURRENT_TIMESTAMP WHERE id=:id"); $insert=$db->prepare("INSERT INTO project_participants(project_id,user_id,role_code,permission_level,is_leader,status) VALUES(:p,:u,'tribunal','review',0,'active')"); foreach($ids as $uid){$find->execute(['p'=>$id,'u'=>$uid]);$row=(int)$find->fetchColumn();$row?$reactivate->execute(['id'=>$row]):$insert->execute(['p'=>$id,'u'=>$uid]);} }
        $action=$current?'tribunal_updated':'tribunal_assigned'; (new ProjectAuditService($db))->record($id,$actor,$action,'project_participants',$id,['members'=>array_values($current),'status'=>$status],['members'=>array_values($next),'status'=>$status,'reason'=>$reason?:null],$reason?:null); (new ProjectAcademicNotificationService())->tribunalAssigned($db,$id,$p['code'],$p['title'],array_values(array_diff_key($next,$current)),$actor);
        if($status==='approved') $status=(new ProjectStatusTransitionService())->transitionInTransaction($db,$id,'approved','defense','',$actor,'thesis_tribunal_assignment')['status'];
        return ['member_count'=>count($ids),'members'=>array_values($next),'action'=>$action,'status'=>$status];
    }

    /** @return array<int,array<string,int|string|null>> */
    private function candidatesWithLoadForProject(PDO $db, array $project, array $additionalExclusions): array
    {
        $excluded = array_values(array_unique(array_merge($this->incompatibleIds($db, (int)$project['id'], (int)$project['tutor_id']), $additionalExclusions)));
        $active = "'" . implode("','", self::ACTIVE_PROJECT_STATUSES) . "'";
        $where = "u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL";
        $params = ['tutor_project'=>(int)$project['id'], 'cotutor_project'=>(int)$project['id'], 'tribunal_project'=>(int)$project['id']];
        if ($excluded) { $holders=[]; foreach($excluded as $index=>$userId){$key='excluded'.$index;$holders[]=':'.$key;$params[$key]=$userId;} $where.=' AND u.id NOT IN ('.implode(',',$holders).')'; }
        $sql = "SELECT DISTINCT u.id user_id,u.full_name name,u.email,tp.academic_title,tp.institutional_code,
                COALESCE(t.tutor_projects_count,0) tutor_projects_count,
                COALESCE(c.cotutor_projects_count,0) cotutor_projects_count,
                COALESCE(j.tribunal_projects_count,0) tribunal_projects_count
            FROM users u
            JOIN user_roles ur ON ur.user_id=u.id
            JOIN roles r ON r.id=ur.role_id AND r.code='teacher'
            JOIN teacher_profiles tp ON tp.user_id=u.id
            LEFT JOIN (SELECT p.tutor_id user_id,COUNT(DISTINCT p.id) tutor_projects_count FROM projects p WHERE p.deleted_at IS NULL AND p.id<>:tutor_project AND p.status IN ($active) AND p.tutor_id IS NOT NULL GROUP BY p.tutor_id) t ON t.user_id=u.id
            LEFT JOIN (SELECT pp.user_id,COUNT(DISTINCT p.id) cotutor_projects_count FROM project_participants pp JOIN projects p ON p.id=pp.project_id WHERE pp.status='active' AND pp.removed_at IS NULL AND LOWER(pp.role_code) IN ('cotutor','co_tutor','co-tutor') AND p.deleted_at IS NULL AND p.id<>:cotutor_project AND p.status IN ($active) GROUP BY pp.user_id) c ON c.user_id=u.id
            LEFT JOIN (SELECT pp.user_id,COUNT(DISTINCT p.id) tribunal_projects_count FROM project_participants pp JOIN projects p ON p.id=pp.project_id WHERE pp.status='active' AND pp.removed_at IS NULL AND LOWER(pp.role_code) IN ('tribunal','jury') AND p.deleted_at IS NULL AND p.id<>:tribunal_project AND p.status IN ($active) GROUP BY pp.user_id) j ON j.user_id=u.id
            WHERE $where ORDER BY u.full_name";
        $query=$db->prepare($sql); $query->execute($params); $rows=$query->fetchAll();
        foreach($rows as &$row){$row['user_id']=(int)$row['user_id'];$row['tutor_projects_count']=(int)$row['tutor_projects_count'];$row['cotutor_projects_count']=(int)$row['cotutor_projects_count'];$row['tribunal_projects_count']=(int)$row['tribunal_projects_count'];$row['total_active_assignments']=$row['tutor_projects_count']+$row['cotutor_projects_count']+$row['tribunal_projects_count'];$row['effective_load']=$row['total_active_assignments'];} unset($row);
        return $rows;
    }

    private function availableProject(PDO $db, int $id): array
    {
        $project=$this->project($db,$id,false);
        if(!in_array($project['status'],['approved','defense'],true)) throw new ThesisTribunalException('El Tribunal no puede gestionarse en el estado actual del proyecto.');
        return $project;
    }
    private function project(PDO $db,int $id,bool $lock):array { $q=$db->prepare("SELECT p.id,p.code,p.title,p.status,p.tutor_id,pt.code type_code FROM projects p JOIN project_types pt ON pt.id=p.project_type_id WHERE p.id=:id AND p.deleted_at IS NULL".($lock?' FOR UPDATE':''));$q->execute(['id'=>$id]);$p=$q->fetch();if(!$p||$p['type_code']!=='thesis')throw new ThesisTribunalException('El proceso de Titulación solicitado no está disponible.',404);return $p; }
    private function incompatibleIds(PDO $db,int $id,int $tutor):array { $q=$db->prepare("SELECT user_id FROM project_participants WHERE project_id=:id AND status='active' AND removed_at IS NULL AND LOWER(role_code) IN ('tutor','cotutor','co_tutor','co-tutor')");$q->execute(['id'=>$id]);$out=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));if($tutor)$out[]=$tutor;return array_values(array_unique($out)); }
    private function normalizeIds(array $ids): array { $normalized=array_values(array_unique(array_filter(array_map('intval',$ids),static fn(int $id):bool=>$id>0))); sort($normalized); return $normalized; }
}
