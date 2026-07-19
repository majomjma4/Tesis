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
    public function enrolledStudents(int $periodId, int $careerId, int $semester, string $search = ''): array
    {
        $sql = "SELECT u.id, u.full_name AS name, se.semester FROM student_enrollments se INNER JOIN users u ON u.id=se.student_id WHERE se.academic_period_id=:period_id AND se.career_id=:career_id AND se.semester=:semester AND se.status='active' AND u.status='active' AND (:search='' OR u.full_name LIKE :pattern) ORDER BY u.full_name LIMIT 30";
        $statement = Database::connection()->prepare($sql); $statement->execute(['period_id'=>$periodId,'career_id'=>$careerId,'semester'=>$semester,'search'=>$search,'pattern'=>'%'.$search.'%']); return $statement->fetchAll();
    }
}
