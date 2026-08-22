<?php

declare(strict_types=1);

final class RepositoryModel
{
    /**
     * Obtiene los proyectos académicos publicados en el repositorio.
     */
    public function getPublishedProjects(): array
    {
        if (!Database::isEnabled()) return [];

            $statement=Database::connection()->query("SELECT p.id,p.code,p.title,COALESCE(NULLIF(p.subtitle,''),NULLIF(p.summary,''),'Proyecto académico publicado.') AS description,
                c.name AS career,pt.name AS type,pt.code AS type_code,ap.name AS period_name,ap.code AS period_code,
                COALESCE(s.semester,1) AS semester,COALESCE(t.full_name,'Sin tutor asignado') AS tutor,
                GROUP_CONCAT(DISTINCT CASE WHEN pp.role_code='student' THEN u.full_name END ORDER BY pp.is_leader DESC,u.full_name SEPARATOR ' y ') AS authors,
                COALESCE(p.published_at,p.updated_at) AS published_at,
                YEAR(COALESCE(p.published_at,p.updated_at)) AS publication_year
                FROM projects p INNER JOIN project_types pt ON pt.id=p.project_type_id INNER JOIN careers c ON c.id=p.career_id
                INNER JOIN academic_periods ap ON ap.id=p.academic_period_id LEFT JOIN academic_subjects s ON s.id=p.academic_subject_id
                LEFT JOIN users t ON t.id=p.tutor_id LEFT JOIN project_participants pp ON pp.project_id=p.id AND pp.status='active' AND pp.removed_at IS NULL
                LEFT JOIN users u ON u.id=pp.user_id AND u.deleted_at IS NULL AND u.purged_at IS NULL
                WHERE p.status='published' AND p.is_available=1 AND p.withdrawn_at IS NULL AND p.deleted_at IS NULL
                  AND EXISTS (SELECT 1 FROM project_files pf WHERE pf.project_id=p.id AND pf.deleted_at IS NULL AND pf.purged_at IS NULL)
                  AND EXISTS (SELECT 1 FROM project_participants sp INNER JOIN student_profiles profile ON profile.user_id=sp.user_id INNER JOIN users su ON su.id=sp.user_id WHERE sp.project_id=p.id AND sp.role_code='student' AND sp.status='active' AND sp.removed_at IS NULL AND su.deleted_at IS NULL AND su.purged_at IS NULL)
                GROUP BY p.id,p.title,p.subtitle,p.summary,c.name,pt.name,pt.code,ap.name,ap.code,s.semester,t.full_name,p.published_at,p.updated_at
                ORDER BY COALESCE(p.published_at,p.updated_at) DESC,p.id DESC");
            return array_map(function(array $row):array{
                $slug=static function(string $value):string{$value=mb_strtolower($value,'UTF-8');if(class_exists('Normalizer')){$n=Normalizer::normalize($value,Normalizer::FORM_D);if(is_string($n))$value=(string)preg_replace('/\p{Mn}+/u','',$n);}return trim((string)preg_replace('/[^a-z0-9]+/','-',$value),'-');};
                return ['id'=>(int)$row['id'],'code'=>(string)$row['code'],'title'=>(string)$row['title'],'description'=>(string)$row['description'],'career'=>(string)$row['career'],'career_slug'=>$slug((string)$row['career']),
                    'authors'=>(string)($row['authors']?:'Autoría institucional no registrada'),'tutor'=>(string)$row['tutor'],'teacher_slug'=>$slug((string)$row['tutor']),
                    'semester'=>(string)$row['semester'],'category'=>(string)$row['type'],'category_slug'=>$slug((string)$row['type']),'year'=>(string)$row['publication_year'],
                    'pao'=>$slug((string)$row['period_code']),'pao_label'=>(string)$row['period_name'],'type'=>(string)$row['type'],'type_slug'=>$slug((string)$row['type']),'type_code'=>(string)($row['type_code']??''),
                    'published_at'=>(string)($row['published_at']??''),'downloads'=>0,'technologies'=>[],'keywords'=>[]];
            },$statement->fetchAll());
    }

    public function findPublishedProjectById(int $projectId): ?array
    {
        foreach ($this->getPublishedProjects() as $project) {
            if ($project['id'] === $projectId) {
                return $project;
            }
        }

        return null;
    }

    public function getPublishedProjectDetail(int $projectId): ?array
    {
        if (!Database::isEnabled() || $projectId < 1) return null;

        $project = $this->findPublishedProjectById($projectId);
        if ($project === null) return null;

            $project['archive'] = [
                'name' => 'Proyecto_' . $projectId . '_Final.zip',
                'path' => ROOT_PATH . '/storage/repository/project_' . $projectId . '.zip',
                'size' => '—',
                'files_count' => 0,
                'folders_count' => 0,
            ];
        return $project;
    }

    public function getSemesters(): array
    {
        return [
            ['value' => '1', 'label' => 'Primer semestre'],
            ['value' => '2', 'label' => 'Segundo semestre'],
            ['value' => '3', 'label' => 'Tercer semestre'],
            ['value' => '4', 'label' => 'Cuarto semestre']
        ];
    }

    public function getTeachers(): array
    {
        return [
            ['value' => 'maribel-fierro', 'label' => 'Msc. Maribel Fierro Montero'],
            ['value' => 'maria-navarrete', 'label' => 'Msc. Maria Elena Navarrete'],
            ['value' => 'diana-alegria', 'label' => 'Lic. Diana Alegría Camino'],
            ['value' => 'diana-ramirez', 'label' => 'Msc. Diana Anaid Ramirez'],
            ['value' => 'alex-galarza', 'label' => 'Abg. Alex Fabián Galarza'],
            ['value' => 'henrry-marino', 'label' => 'Msc. Henrry Mariño Acosta'],
        ];
    }

    public function getCategories(): array
    {
        $statement = Database::connection()->query(
            "SELECT DISTINCT c.slug value,c.name label
             FROM support_material_categories c
             LEFT JOIN support_materials sm ON sm.category_id=c.id
                AND sm.status='published' AND sm.deleted_at IS NULL AND sm.purged_at IS NULL
             WHERE c.is_active=1 OR sm.id IS NOT NULL
             ORDER BY c.name,c.id"
        );
        return $statement->fetchAll();

    }

    public function getProjectTypes(): array
    {
        $statement = Database::connection()->query(
            "SELECT DISTINCT pt.code value,pt.name label
             FROM project_types pt
             LEFT JOIN projects p ON p.project_type_id=pt.id
                AND p.status='published' AND p.is_available=1
                AND p.withdrawn_at IS NULL AND p.deleted_at IS NULL
             WHERE pt.is_active=1 OR p.id IS NOT NULL
             ORDER BY pt.name,pt.id"
        );
        return $statement->fetchAll();

    }

    public function getAcademicPeriods(): array
    {
        if (Database::isEnabled()) {
            $statement = Database::connection()->query(
                    "SELECT id, name, code, status
                     FROM academic_periods
                     WHERE status IN ('active', 'closed')
                     ORDER BY (status = 'active') DESC, starts_on DESC, id DESC"
            );
            $rows = $statement->fetchAll();
            if (!empty($rows)) {
                    $slug = static function (string $value): string {
                        $value = mb_strtolower($value, 'UTF-8');
                        if (class_exists('Normalizer')) {
                            $n = Normalizer::normalize($value, Normalizer::FORM_D);
                            if (is_string($n)) $value = (string) preg_replace('/\p{Mn}+/u', '', $n);
                        }
                        return trim((string) preg_replace('/[^a-z0-9]+/', '-', $value), '-');
                    };
                return array_map(static function (array $row) use ($slug): array {
                        return [
                            'id' => (int) $row['id'],
                            'value' => $slug((string) ($row['code'] ?? $row['name'])),
                            'label' => (string) $row['name'],
                            'code' => (string) ($row['code'] ?? ''),
                            'status' => (string) ($row['status'] ?? 'active'),
                        ];
                }, $rows);
            }
        }

        return [];
    }
}
