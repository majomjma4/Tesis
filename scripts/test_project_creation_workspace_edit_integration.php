<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
require APP_PATH . '/helpers.php';
require APP_PATH . '/Core/Autoloader.php';
Autoloader::register();

function expectIntegration(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function expectEditRejection(callable $operation): void
{
    try {
        $operation();
    } catch (StudentProjectInformationException $exception) {
        expectIntegration($exception->httpStatus() === 422, 'El rechazo debe devolver 422.');
        return;
    }
    throw new RuntimeException('La edición debía ser rechazada.');
}

/** Replica el payload inicial que el modal del workspace obtiene de ProjectRecordModel. */
function workspaceEditorPayload(array $project): array
{
    $participants = (array) ($project['participants'] ?? []);
    $tutors = array_values(array_filter($participants, static fn (array $participant): bool =>
        in_array(strtolower((string) ($participant['role_code'] ?? '')), ['tutor', 'cotutor', 'co_tutor', 'co-tutor'], true)
    ));
    $authors = array_values(array_filter($participants, static fn (array $participant): bool =>
        strtolower((string) ($participant['role_code'] ?? '')) === 'student'
    ));
    $primaryTutorId = (int) ($project['tutor_id'] ?? 0);
    if ($primaryTutorId === 0 && $tutors !== []) $primaryTutorId = (int) $tutors[0]['user_id'];
    $leaderId = 0;
    foreach ($authors as $author) if (!empty($author['is_leader'])) { $leaderId = (int) $author['user_id']; break; }
    if ($leaderId === 0 && $authors !== []) $leaderId = (int) $authors[0]['user_id'];

    return [
        'title' => (string) ($project['title'] ?? ''),
        'summary' => (string) ($project['summary'] ?? ''),
        'tutor_id' => $primaryTutorId,
        'tutoring_user_ids' => array_values(array_map(static fn (array $tutor): int => (int) $tutor['user_id'], $tutors)),
        'author_user_ids' => array_values(array_map(static fn (array $author): int => (int) $author['user_id'], $authors)),
        'author_leader_id' => $leaderId,
    ];
}

$db = Database::connection();
$projectId = 0;
$actorId = 0;
$sequenceTypeId = 0;
$sequenceBefore = null;
$draftStorage = new ProjectDraftStorageService($db);

try {
    $actorQuery = $db->query("SELECT u.id
        FROM users u
        INNER JOIN student_profiles sp ON sp.user_id=u.id
        INNER JOIN student_enrollments se ON se.student_id=u.id AND se.career_id=sp.career_id AND se.status='active'
        INNER JOIN academic_periods ap ON ap.id=se.academic_period_id AND ap.status='active'
        INNER JOIN user_roles ur ON ur.user_id=u.id
        INNER JOIN roles r ON r.id=ur.role_id AND r.code='student'
        LEFT JOIN project_drafts draft ON draft.user_id=u.id AND draft.expires_at>UTC_TIMESTAMP()
        WHERE u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL AND draft.id IS NULL
        ORDER BY u.id LIMIT 1");
    $actorId = (int) $actorQuery->fetchColumn();
    expectIntegration($actorId > 0, 'No existe un estudiante activo sin borrador para la prueba de integración.');

    $policy = [
        'role' => 'student', 'actor_type' => 'student', 'can_create' => true, 'auto_leader' => true,
        'can_add_students' => true, 'must_select_leader' => false, 'can_configure_full_team' => false,
        'student_tutor_mode' => 'proposed', 'can_self_assign_tutor' => false,
    ];
    $draftService = new ProjectDraftService();
    $catalogs = $draftService->catalogs($actorId, $policy);
    $type = $catalogs['types']['pis'] ?? null;
    $tutor = $catalogs['teachers'][0] ?? null;
    expectIntegration($type !== null && !empty($type['enabled']) && $tutor !== null, 'Faltan catálogos válidos para registrar el proyecto de integración.');
    expectIntegration(in_array($actorId, array_map(static fn (array $student): int => (int) $student['id'], $catalogs['students']), true), 'El estudiante de prueba no está disponible como integrante del borrador.');
    $sequenceTypeId = (int) $type['id'];
    $sequenceQuery = $db->prepare('SELECT next_number FROM project_code_sequences WHERE project_type_id=:type AND code_year=:year');
    $sequenceQuery->execute(['type' => $sequenceTypeId, 'year' => (int) date('Y')]);
    $sequenceValue = $sequenceQuery->fetchColumn();
    $sequenceBefore = $sequenceValue === false ? null : (int) $sequenceValue;

    $createdTitle = 'Proyecto de integración crear workspace editar';
    $createdSummary = 'Resumen inicial de integración con más de treinta caracteres válidos.';
    $draft = $draftStorage->save($actorId, [
        'type' => 'pis', 'title' => $createdTitle, 'description' => $createdSummary,
        'period' => (string) $catalogs['active_period']['code'], 'modality' => '', 'research_line' => '',
        'tutor_id' => (string) $tutor['id'], 'members' => [(string) $actorId], 'tags' => [],
    ]);
    $registered = (new ProjectDraftRegistrationService())->register($actorId, $policy, (string) $draft['id']);
    $projectId = (int) $registered['project_id'];
    expectIntegration($projectId > 0, 'El registro no devolvió un proyecto persistido.');

    $recordModel = new ProjectRecordModel();
    $createdProject = $recordModel->find($projectId, $actorId, false);
    expectIntegration($createdProject !== null, 'El workspace no puede recuperar el proyecto recién registrado.');
    expectIntegration((string) $createdProject['status'] === 'development', 'El registro debe mantener el proyecto en development hasta un envío explícito.');
    $workspace = workspaceEditorPayload($createdProject);
    expectIntegration($workspace['title'] === $createdTitle, 'El título no coincide entre creación y workspace.');
    expectIntegration($workspace['summary'] === $createdSummary, 'El resumen no coincide entre creación y workspace.');
    expectIntegration($workspace['tutor_id'] === (int) $tutor['id'] && $workspace['tutoring_user_ids'] === [(int) $tutor['id']], 'El tutor no coincide entre creación y workspace.');
    expectIntegration($workspace['author_user_ids'] === [$actorId] && $workspace['author_leader_id'] === $actorId, 'Los integrantes o líder no coinciden entre creación y workspace.');

    $editor = new StudentProjectInformationService();
    $editInput = [
        'title' => 'Proyecto de integración actualizado',
        'summary' => 'Resumen actualizado de integración con más de treinta caracteres válidos.',
        'tutoring_user_ids' => [(int) $tutor['id']], 'tutoring_primary_id' => (int) $tutor['id'],
        'author_user_ids' => [$actorId], 'author_leader_id' => $actorId,
    ];
    $saved = $editor->save($projectId, $editInput, $actorId);
    expectIntegration(!empty($saved['changed']), 'La edición válida no se registró.');

    $updatedProject = $recordModel->find($projectId, $actorId, false);
    expectIntegration($updatedProject !== null, 'No se pudo recargar el proyecto editado.');
    $updatedWorkspace = workspaceEditorPayload($updatedProject);
    expectIntegration($updatedWorkspace['title'] === $editInput['title'] && $updatedWorkspace['summary'] === $editInput['summary'], 'La edición no actualizó title/summary en projects.');
    expectIntegration($updatedWorkspace['tutor_id'] === (int) $tutor['id'] && $updatedWorkspace['tutoring_user_ids'] === [(int) $tutor['id']], 'La edición alteró la fuente de tutoría.');
    expectIntegration($updatedWorkspace['author_user_ids'] === [$actorId] && $updatedWorkspace['author_leader_id'] === $actorId, 'La edición alteró integrantes o líder.');

    foreach ([
        'descripción vacía' => ['summary' => ''],
        'descripción menor de 30' => ['summary' => 'Descripción demasiado corta'],
        'título menor de 5' => ['title' => 'abc'],
        'título mayor de 240' => ['title' => str_repeat('a', 241)],
    ] as $label => $override) {
        expectEditRejection(fn () => $editor->save($projectId, $override + $editInput, $actorId));
        echo "OK   $label rechazado con 422\n";
    }

    $auditCount = $db->prepare("SELECT COUNT(*) FROM project_audit_log WHERE project_id=:id AND action='project_updated'");
    $auditCount->execute(['id' => $projectId]);
    $before = (int) $auditCount->fetchColumn();
    $unchanged = $editor->save($projectId, $editInput, $actorId);
    $auditCount->execute(['id' => $projectId]);
    expectIntegration(empty($unchanged['changed']) && (int) $auditCount->fetchColumn() === $before, 'La edición sin cambios creó una auditoría artificial.');

    echo "OK   crear → workspace → editar conserva las fuentes de datos\n";
    echo "Resultado: 6 OK, 0 FAIL\n";
} finally {
    if ($projectId > 0) {
        $db->prepare('DELETE FROM projects WHERE id=:id')->execute(['id' => $projectId]);
    }
    if ($sequenceTypeId > 0) {
        if ($sequenceBefore === null) {
            $db->prepare('DELETE FROM project_code_sequences WHERE project_type_id=:type AND code_year=:year')
                ->execute(['type' => $sequenceTypeId, 'year' => (int) date('Y')]);
        } else {
            $db->prepare('UPDATE project_code_sequences SET next_number=:next WHERE project_type_id=:type AND code_year=:year')
                ->execute(['next' => $sequenceBefore, 'type' => $sequenceTypeId, 'year' => (int) date('Y')]);
        }
    }
    if ($actorId > 0) {
        try { $draftStorage->delete($actorId); } catch (Throwable) { }
    }
}
