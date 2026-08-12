<?php
declare(strict_types=1);

/** Prepara y valida el borrador previo al registro definitivo. */
final class ProjectDraftService
{
    private const TYPE_RULES = [
        'thesis' => ['prefix' => 'TIT', 'additional' => ['research_line'], 'semesters' => [4], 'availability' => 'Disponible para 4.º semestre.'],
        'thesis_profile' => ['prefix' => 'PFT', 'additional' => ['research_line'], 'semesters' => [4], 'availability' => 'Disponible para 4.º semestre.'],
        'pis' => ['prefix' => 'PIS', 'additional' => [], 'semesters' => null, 'availability' => 'Disponible para todos los semestres.'],
        'practice' => ['prefix' => 'PRA', 'additional' => [], 'semesters' => [3, 4], 'availability' => 'Disponible para 3.er y 4.º semestre.', 'default_title' => 'Prácticas preprofesionales', 'default_description' => 'Desarrollo de prácticas preprofesionales orientadas a fortalecer competencias profesionales mediante actividades planificadas, supervisadas y vinculadas con el perfil de formación.'],
        'community' => ['prefix' => 'VIN', 'additional' => [], 'semesters' => [2], 'availability' => 'Disponible para 2.º semestre.', 'default_title' => 'Proyecto de vinculación', 'default_description' => 'Desarrollo de un proyecto de vinculación orientado a responder necesidades de la comunidad mediante actividades planificadas, participativas y relacionadas con la formación académica.'],
    ];

    public function catalogs(int $userId, array $policy): array
    {
        $db = Database::connection();
        $activePeriod = $this->activePeriod($db);
        $student = $policy['actor_type'] === 'student' && $activePeriod !== null ? $this->studentContext($db, $userId, (int) $activePeriod['id']) : null;
        $types = $this->types($db, $student);

        return [
            'types' => $types,
            'active_period' => $activePeriod,
            'student' => $student,
            'availability_message' => $activePeriod === null
                ? 'No existe un periodo académico activo. Comunícate con la administración.'
                : ($policy['actor_type'] === 'student' && $student === null ? 'No se encontró una matrícula activa para el periodo académico actual.' : ''),
            'modalities' => ['individual' => 'Individual', 'group' => 'Grupal'],
            'research_lines' => $this->researchLines($db),
            'teachers' => $this->teachers($db),
            'students' => $activePeriod !== null && $student !== null ? $this->students($db, (int) $activePeriod['id'], (int) $student['career_id']) : [],
            'keywords' => $this->keywords($db),
        ];
    }

    public function fieldContract(array $catalogs): array
    {
        $contract = [];
        foreach ($catalogs['types'] as $code => $type) {
            $contract[$code] = [
                'required' => $code === 'thesis' ? ['title', 'description', 'period', 'modality', 'research_line', 'tutor_id'] : ['title', 'description', 'period', 'tutor_id'],
                'additional' => $type['additional'],
                'uses_description' => true,
                'default_title' => $type['default_title'] ?? '',
                'default_description' => $type['default_description'] ?? '',
                'allows_cross_semester_members' => in_array($code, ['thesis', 'thesis_profile', 'community'], true),
            ];
        }
        return $contract;
    }

    public function normalize(array $payload, array $policy, array $catalogs): array
    {
        $value = static fn(string $key): string => trim((string) ($payload[$key] ?? ''));
        $rawMembers = array_values(array_filter(array_map('strval', (array) ($payload['members'] ?? []))));
        $members = array_values(array_unique($rawMembers));
        $actorId = (string) ($catalogs['student']['user_id'] ?? '');
        if ($policy['auto_leader'] && $actorId !== '' && !in_array($actorId, $members, true)) array_unshift($members, $actorId);
        return [
            'type' => $value('type'), 'title' => $value('title'), 'description' => $value('description'),
            'period' => (string) ($catalogs['active_period']['code'] ?? ''), 'submitted_period' => $value('period'), 'modality' => $value('modality'),
            'research_line' => $value('research_line'), 'tutor_id' => $value('tutor_id'),
            'leader_id' => $policy['auto_leader'] ? $actorId : $value('leader_id'),
            'members' => $members, 'raw_member_count' => count($rawMembers),
            'tags' => array_slice(array_values(array_filter(array_map(static fn($tag): string => trim((string) $tag), (array) ($payload['tags'] ?? [])))), 0, 8), 'raw_tag_count' => count((array) ($payload['tags'] ?? [])),
        ];
    }

    public function validate(array $draft, array $policy, array $catalogs): array
    {
        $errors = [];
        $activePeriod = $catalogs['active_period'];
        $student = $catalogs['student'];
        if ($activePeriod === null) $errors['period'] = 'No existe un periodo académico activo. Comunícate con la administración.';
        if ($policy['actor_type'] === 'student' && $student === null) $errors['academic'] = 'No se encontró una matrícula activa para el periodo académico actual.';

        $type = $catalogs['types'][$draft['type']] ?? null;
        if ($type === null) $errors['type'] = 'Selecciona un tipo de proyecto válido y activo.';
        elseif (!$type['enabled']) $errors['type'] = 'Este tipo de proyecto no está disponible para tu semestre actual.';
        if (mb_strlen($draft['title']) < 8) $errors['title'] = 'Escribe un título de al menos 8 caracteres.';
        elseif (mb_strlen($draft['title']) > 180) $errors['title'] = 'El título no puede superar 180 caracteres.';
        if (mb_strlen($draft['description']) < 30) $errors['description'] = 'Describe el proyecto con al menos 30 caracteres.';
        if ($activePeriod !== null && $draft['submitted_period'] !== (string) $activePeriod['code']) $errors['period'] = 'El periodo académico enviado no es válido.';
        foreach (($this->fieldContract($catalogs)[$draft['type']]['required'] ?? []) as $field) {
            if (($draft[$field] ?? '') === '' && !isset($errors[$field])) $errors[$field] = 'Este campo es obligatorio para el tipo seleccionado.';
        }
        if ($draft['type'] === 'thesis' && !in_array($draft['modality'], ['individual', 'group'], true)) $errors['modality'] = 'Selecciona una modalidad válida.';
        if (in_array($draft['type'], ['thesis', 'thesis_profile'], true) && !isset(array_column($catalogs['research_lines'], null, 'id')[(int) $draft['research_line']])) $errors['research_line'] = 'Selecciona una línea de investigación válida.';
        if ($draft['type'] === 'thesis' && $draft['modality'] === 'individual' && count($draft['members']) > 1) $errors['members'] = 'La modalidad individual solo admite un estudiante.';
        if ($draft['raw_member_count'] !== count($draft['members'])) $errors['members'] = 'No puedes agregar el mismo integrante más de una vez.';

        $teachers = array_column($catalogs['teachers'], null, 'id');
        if ($draft['tutor_id'] !== '' && !isset($teachers[(int) $draft['tutor_id']])) $errors['tutor_id'] = 'El tutor seleccionado ya no se encuentra disponible. Selecciona otro tutor.';
        $students = array_column($catalogs['students'], null, 'id');
        foreach ($draft['members'] as $memberId) {
            if (!isset($students[(int) $memberId])) { $errors['members'] = 'Uno o más integrantes ya no están disponibles para este proyecto.'; break; }
        }
        if ($policy['auto_leader'] && $student !== null && !in_array((string) $student['user_id'], $draft['members'], true)) $errors['members'] = 'La persona que crea el proyecto debe formar parte del equipo.';
        if ($type !== null && !$this->allowsCrossSemester((string) $draft['type']) && $student !== null) {
            foreach ($draft['members'] as $memberId) {
                $member = $students[(int) $memberId] ?? null;
                if ($member !== null && (int) $member['semester'] !== (int) $student['semester']) { $errors['members'] = 'Este tipo de proyecto requiere integrantes del mismo semestre.'; break; }
            }
        }
        $errors += $this->validateTags($draft['tags'], $catalogs['keywords'], (int) $draft['raw_tag_count']);
        return $errors;
    }

    /** Valida referencias seguras sin exigir que un borrador incompleto esté listo para registrarse. */
    public function validateForStorage(array $draft, array $policy, array $catalogs): array
    {
        $errors = [];
        $activePeriod = $catalogs['active_period']; $student = $catalogs['student'];
        if ($activePeriod === null) return ['period' => 'No existe un periodo académico activo. Comunícate con la administración.'];
        if ($policy['actor_type'] === 'student' && $student === null) return ['academic' => 'No se encontró una matrícula activa para el periodo académico actual.'];
        if ($draft['submitted_period'] !== '' && $draft['submitted_period'] !== (string) $activePeriod['code']) $errors['period'] = 'El periodo académico enviado no es válido.';
        if ($draft['type'] !== '') {
            $type = $catalogs['types'][$draft['type']] ?? null;
            if ($type === null || !$type['enabled']) $errors['type'] = 'Este tipo de proyecto no está disponible para tu semestre actual.';
        }
        $teachers = array_column($catalogs['teachers'], null, 'id');
        if ($draft['tutor_id'] !== '' && !isset($teachers[(int) $draft['tutor_id']])) $errors['tutor_id'] = 'El tutor seleccionado ya no se encuentra disponible. Selecciona otro tutor.';
        if ($draft['research_line'] !== '' && !isset(array_column($catalogs['research_lines'], null, 'id')[(int) $draft['research_line']])) $errors['research_line'] = 'Selecciona una línea de investigación válida.';
        $students = array_column($catalogs['students'], null, 'id');
        if ($draft['raw_member_count'] !== count($draft['members'])) $errors['members'] = 'No puedes agregar el mismo integrante más de una vez.';
        foreach ($draft['members'] as $memberId) if (!isset($students[(int) $memberId])) { $errors['members'] = 'Uno o más integrantes ya no están disponibles para este proyecto.'; break; }
        if ($draft['type'] !== '' && !$this->allowsCrossSemester($draft['type']) && $student !== null) foreach ($draft['members'] as $memberId) { $member = $students[(int) $memberId] ?? null; if ($member !== null && (int) $member['semester'] !== (int) $student['semester']) { $errors['members'] = 'Este tipo de proyecto requiere integrantes del mismo semestre.'; break; } }
        return $errors + $this->validateTags($draft['tags'], $catalogs['keywords'], (int) $draft['raw_tag_count']);
    }

    public function validateFiles(array $files, PrivateProjectFileService $service): array
    {
        $errors = []; $valid = []; $seen = []; $total = 0;
        foreach ($this->flattenFiles($files) as $i => $file) try {
            $item = $service->validateUpload($file); $key = mb_strtolower($item['original_name']);
            if (isset($seen[$key])) throw new InvalidArgumentException('Hay dos archivos con el mismo nombre.');
            $seen[$key] = true; $total += $item['size_bytes']; $valid[] = $item;
        } catch (InvalidArgumentException $exception) { $errors['files.' . $i] = $exception->getMessage(); }
        if ($total > $service->limits()['max_total_bytes']) $errors['files'] = 'El conjunto supera el límite total de ' . $service->limits()['max_total_mb'] . ' MB.';
        return ['errors' => $errors, 'valid' => $valid];
    }

    public function confirmation(array $draft, array $files, array $catalogs): array
    {
        $teachers = array_column($catalogs['teachers'], 'name', 'id');
        try { $settings = (new SystemSettingModel())->all(); } catch (Throwable) { $settings = (new SystemSettingModel())->defaults(); }
        $prefix = $catalogs['types'][$draft['type']]['prefix'] ?? ($settings['project_code_prefixes'][$draft['type']] ?? 'PRY');
        $digits = (int) ($settings['project_code_digits'] ?? 3);
        return $draft + ['type_label' => $catalogs['types'][$draft['type']]['label'] ?? 'Sin definir', 'tutor_label' => $teachers[(int) $draft['tutor_id']] ?? 'Pendiente de asignación', 'provisional_code' => $prefix . '-' . date('Y') . '-' . str_repeat('X', $digits), 'file_count' => count($files)];
    }

    private function activePeriod(PDO $db): ?array
    {
        $q = $db->query("SELECT id,code,name,starts_on,ends_on FROM academic_periods WHERE status='active' ORDER BY starts_on DESC,id DESC LIMIT 1");
        return $q->fetch() ?: null;
    }

    private function studentContext(PDO $db, int $userId, int $periodId): ?array
    {
        $q = $db->prepare("SELECT u.id user_id,u.full_name,sp.career_id,c.code career_code,c.name career_name,se.semester
            FROM users u INNER JOIN student_profiles sp ON sp.user_id=u.id INNER JOIN student_enrollments se ON se.student_id=u.id
            INNER JOIN careers c ON c.id=se.career_id
            WHERE u.id=:user AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL
              AND se.academic_period_id=:period AND se.status='active' AND se.career_id=sp.career_id LIMIT 1");
        $q->execute(['user' => $userId, 'period' => $periodId]); return $q->fetch() ?: null;
    }

    private function types(PDO $db, ?array $student): array
    {
        $rows = $db->query("SELECT id,code,name FROM project_types WHERE is_active=1 ORDER BY id")->fetchAll();
        $rowsByCode = [];
        foreach ($rows as $row) $rowsByCode[(string) $row['code']] = $row;
        $types = [];
        foreach (self::TYPE_RULES as $code => $rule) {
            $row = $rowsByCode[$code] ?? null; if ($row === null) continue;
            $allowed = $rule['semesters'] === null || ($student !== null && in_array((int) $student['semester'], $rule['semesters'], true));
            $types[$code] = ['id' => (int) $row['id'], 'label' => (string) $row['name'], 'prefix' => $rule['prefix'], 'additional' => $rule['additional'], 'enabled' => $allowed, 'availability' => $rule['availability'], 'default_title' => $rule['default_title'] ?? '', 'default_description' => $rule['default_description'] ?? ''];
        }
        return $types;
    }

    private function teachers(PDO $db): array
    {
        return $db->query("SELECT DISTINCT u.id,u.full_name name,u.email FROM users u INNER JOIN teacher_profiles tp ON tp.user_id=u.id
            INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN roles r ON r.id=ur.role_id AND r.code='teacher'
            WHERE u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL AND tp.can_tutor=1 ORDER BY u.full_name")->fetchAll();
    }

    private function students(PDO $db, int $periodId, int $careerId): array
    {
        $q = $db->prepare("SELECT DISTINCT u.id,u.full_name name,u.email,se.semester FROM users u
            INNER JOIN student_profiles sp ON sp.user_id=u.id INNER JOIN student_enrollments se ON se.student_id=u.id
            INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN roles r ON r.id=ur.role_id AND r.code='student'
            WHERE u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL AND se.status='active'
              AND se.academic_period_id=:period AND se.career_id=:enrollment_career AND sp.career_id=:profile_career ORDER BY se.semester,u.full_name");
        $q->execute(['period' => $periodId, 'enrollment_career' => $careerId, 'profile_career' => $careerId]); return $q->fetchAll();
    }

    private function researchLines(PDO $db): array
    {
        try { return $db->query("SELECT id,name FROM research_lines WHERE is_active=1 ORDER BY name")->fetchAll(); } catch (Throwable) { return []; }
    }

    private function keywords(PDO $db): array
    {
        return $db->query("SELECT id,name,normalized_name FROM keywords WHERE is_active=1 ORDER BY name")->fetchAll();
    }

    private function allowsCrossSemester(string $type): bool { return in_array($type, ['thesis', 'thesis_profile', 'community'], true); }

    private function validateTags(array $tags, array $keywords, int $rawCount): array
    {
        $errors = []; if ($rawCount > 8 || count($tags) > 8) return ['tags' => 'Puedes agregar hasta 8 etiquetas.'];
        $known = [];
        foreach ($keywords as $keyword) {
            $known[mb_strtolower(trim((string) ($keyword['normalized_name'] ?? '')))] = true;
            $known[mb_strtolower(trim((string) ($keyword['name'] ?? '')))] = true;
        }
        $seen = [];
        foreach ($tags as $tag) {
            $normalized = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $tag) ?? ''));
            if (isset($known[$normalized])) {
                if (isset($seen[$normalized])) return ['tags' => 'No puedes repetir etiquetas.'];
                $seen[$normalized] = true;
                continue;
            }
            if (mb_strlen($normalized) < 2 || mb_strlen($normalized) > 120 || !preg_match('/^[\pL\pN][\pL\pN ._-]*$/u', $normalized)) return ['tags' => 'Cada etiqueta debe tener entre 2 y 120 caracteres válidos.'];
            if (isset($seen[$normalized])) return ['tags' => 'No puedes repetir etiquetas.'];
            $seen[$normalized] = true;
        }
        return $errors;
    }

    private function flattenFiles(array $files): array
    {
        if (!isset($files['name']) || !is_array($files['name'])) return [];
        $out = []; foreach ($files['name'] as $i => $name) if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) $out[] = ['name' => $name, 'type' => $files['type'][$i] ?? '', 'tmp_name' => $files['tmp_name'][$i] ?? '', 'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE, 'size' => $files['size'][$i] ?? 0];
        return $out;
    }
}
