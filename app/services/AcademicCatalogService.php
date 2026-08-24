<?php
declare(strict_types=1);

/** Consultas de catálogos institucionales que sustituirán las opciones temporales. */
final class AcademicCatalogService
{
    public function activePeriods(): array
    {
        $statement = Database::connection()->prepare("SELECT id, code, name FROM academic_periods WHERE status IN ('active','planned') ORDER BY starts_on");
        $statement->execute(); return $statement->fetchAll();
    }
    public function activeCareers(): array
    {
        $statement = Database::connection()->prepare('SELECT id, code, name FROM careers WHERE is_active = 1 ORDER BY name');
        $statement->execute(); return $statement->fetchAll();
    }
    public function tutors(): array
    {
        $statement = Database::connection()->prepare("SELECT u.id, u.full_name AS name, tp.academic_title FROM users u INNER JOIN teacher_profiles tp ON tp.user_id=u.id WHERE u.status='active' AND tp.can_tutor=1 ORDER BY u.full_name");
        $statement->execute(); return $statement->fetchAll();
    }

    public function directProjectTypes(): array
    {
        $statement = Database::connection()->query("SELECT id, code, name, COALESCE(registration_description, '') AS registration_description FROM project_types WHERE is_active=1 ORDER BY name,id");
        return array_map(static function (array $type): array {
            $type['default_title'] = ProjectDraftService::defaultTitleFor((string) ($type['code'] ?? ''));
            return $type;
        }, $statement->fetchAll());
    }

    public function activePeriod(): ?array
    {
        $statement = Database::connection()->query("SELECT id, code, name FROM academic_periods WHERE status='active' ORDER BY starts_on DESC,id DESC LIMIT 1");
        $period = $statement->fetch();
        return $period ?: null;
    }

    public function activeKeywords(): array
    {
        $statement = Database::connection()->query("SELECT id, name FROM keywords WHERE is_active=1 ORDER BY name,id");
        return $statement->fetchAll();
    }

    public function directProjectPeople(string $kind, string $search): array
    {
        $search = trim($search);
        if ($search === '') return [];
        $pattern = '%' . $search . '%';
        if ($kind === 'tutors') {
            $statement = Database::connection()->prepare("SELECT DISTINCT u.id,u.full_name AS name,COALESCE(tp.institutional_code,'') AS identification FROM users u INNER JOIN teacher_profiles tp ON tp.user_id=u.id INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN roles r ON r.id=ur.role_id AND r.code='teacher' WHERE u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL AND tp.can_tutor=1 AND (u.full_name LIKE :name OR COALESCE(u.username,'') LIKE :username OR COALESCE(tp.institutional_code,'') LIKE :identification) ORDER BY u.full_name LIMIT 20");
        } else {
            $statement = Database::connection()->prepare("SELECT DISTINCT u.id,u.full_name AS name,COALESCE(sp.institutional_code,'') AS identification FROM users u INNER JOIN student_profiles sp ON sp.user_id=u.id INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN roles r ON r.id=ur.role_id AND r.code='student' INNER JOIN student_enrollments se ON se.student_id=u.id INNER JOIN academic_periods ap ON ap.id=se.academic_period_id AND ap.status='active' WHERE u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL AND se.status='active' AND (u.full_name LIKE :name OR COALESCE(u.username,'') LIKE :username OR COALESCE(sp.institutional_code,'') LIKE :identification) ORDER BY u.full_name LIMIT 20");
        }
        $statement->execute(['name'=>$pattern,'username'=>$pattern,'identification'=>$pattern]);
        return $statement->fetchAll();
    }
    public function enrolledStudents(int $periodId, int $careerId, int $semester, string $search = ''): array
    {
        $sql = "SELECT u.id, u.full_name AS name, se.semester FROM student_enrollments se INNER JOIN users u ON u.id=se.student_id WHERE se.academic_period_id=:period_id AND se.career_id=:career_id AND se.semester=:semester AND se.status='active' AND u.status='active' AND (:search='' OR u.full_name LIKE :pattern) ORDER BY u.full_name LIMIT 30";
        $statement = Database::connection()->prepare($sql); $statement->execute(['period_id'=>$periodId,'career_id'=>$careerId,'semester'=>$semester,'search'=>$search,'pattern'=>'%'.$search.'%']); return $statement->fetchAll();
    }
}
