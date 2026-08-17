<?php
declare(strict_types=1);

/** Operaciones de preparación documental del estudiante, aisladas del gestor administrativo. */
final class ProjectStudentDocumentController
{
    public function change(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') $this->json(false, 'Método no permitido.', [], 405);
        $session = new AuthSessionService();
        $actor = (int) ($session->userId() ?? 0);
        if ($actor < 1 || !$session->validateCsrf('student_project_documents', (string) ($_POST['_csrf'] ?? ''))) $this->json(false, 'La solicitud no está autorizada.', [], 419);
        $projectId = (int) ($_POST['project_id'] ?? 0);
        $action = (string) ($_POST['action'] ?? '');
        if (!in_array($action, ['add','replace','remove'], true)) $this->json(false, 'La acción documental no es válida.', [], 422);
        if (empty((new ProjectCapabilityService())->forProjectId($projectId, 'academic')['manage_workspace_files'])) $this->json(false, 'No tienes permiso para modificar los archivos de este proyecto.', [], 403);
        $storage = new ProjectDocumentFileService();
        $stored = [];
        try {
            if ($action === 'remove') {
                $result = Database::transaction(fn(PDO $db): array => $this->remove($db, $projectId, (int) ($_POST['file_id'] ?? 0), $actor));
                $this->json(true, 'Archivo quitado.', $result);
            }
            $upload = $this->singleUpload($_FILES['file'] ?? []);
            $stored = $storage->storeUpload($projectId, $upload);
            if (($stored['extension'] ?? '') === 'zip' && empty((new ArchiveService())->inspectPackage((string) $stored['absolute_path'])['success'])) throw new InvalidArgumentException('No fue posible validar el contenido del archivo ZIP.');
            $result = $action === 'add'
                ? Database::transaction(fn(PDO $db): array => $this->add($db, $projectId, $stored, $actor))
                : Database::transaction(fn(PDO $db): array => $this->replace($db, $projectId, (int) ($_POST['file_id'] ?? 0), (string) ($_POST['expected_checksum'] ?? ''), $stored, $actor, $_POST));
            $stored = [];
            $this->json(true, $action === 'add' ? 'Archivo agregado correctamente.' : 'Archivo reemplazado correctamente.', $result);
        } catch (ProjectDocumentVersionException $error) {
            $storage->discardStored($stored); $this->json(false, $error->getMessage(), [], $error->httpStatus());
        } catch (InvalidArgumentException $error) {
            $storage->discardStored($stored); $this->json(false, $error->getMessage(), [], 422);
        } catch (Throwable $error) {
            $storage->discardStored($stored); error_log('Student project document: ' . $error->getMessage()); $this->json(false, 'No fue posible completar la operación.', [], 500);
        }
    }

    private function add(PDO $db, int $projectId, array $stored, int $actor): array
    {
        $this->lockWorkspace($db, $projectId, $actor);
        $model = new ProjectDocumentModel($db);
        $sameName = $db->prepare('SELECT id,original_name,checksum_sha256 FROM project_files WHERE project_id=:project AND deleted_at IS NULL AND purged_at IS NULL AND original_name=:name LIMIT 1 FOR UPDATE');
        $sameName->execute(['project'=>$projectId,'name'=>(string)$stored['original_name']]);
        $existing = $sameName->fetch();
        if ($existing) {
            if (hash_equals((string)$existing['checksum_sha256'], (string)$stored['checksum_sha256'])) throw new ProjectDocumentVersionException('Este archivo ya existe y es idéntico al seleccionado.', 409);
            throw new ProjectDocumentVersionException('Ya existe un archivo llamado “'.(string)$existing['original_name'].'”. ¿Deseas reemplazarlo?', 409);
        }
        $conflict = $model->activeFileConflict($projectId, (string)$stored['original_name'], (int)$stored['size_bytes'], (string)$stored['checksum_sha256']);
        if ($conflict) throw new ProjectDocumentVersionException('Este archivo ya existe dentro del proyecto.', 409);
        $file = $model->add($projectId, $stored, $actor);
        (new ProjectAuditService($db))->record($projectId, $actor, 'project_workspace_file_added', 'project_file', (int)$file['id'], null, ['file_id'=>(int)$file['id'],'checksum'=>(string)$file['checksum_sha256']], 'Archivo agregado durante la preparación documental.');
        return ['file_id'=>(int)$file['id']];
    }

    private function replace(PDO $db, int $projectId, int $fileId, string $checksum, array $stored, int $actor, array $input = []): array
    {
        $this->lockWorkspace($db, $projectId, $actor);
        $model = new ProjectDocumentModel($db);
        $current = $model->findActiveFile($projectId, $fileId, true);

        $newChecksum = (string) ($stored['checksum_sha256'] ?? '');
        $expectedChecksum = (string) ($current['checksum_sha256'] ?? '');

        if (hash_equals($expectedChecksum, $newChecksum)) {
            throw new InvalidArgumentException('El archivo seleccionado tiene el mismo contenido que la versión actual. Realiza las correcciones necesarias antes de reemplazarlo.', 422);
        }

        $currentName = (string) ($current['original_name'] ?? '');
        $newName = (string) ($stored['original_name'] ?? '');
        $currentExt = strtolower((string) ($current['extension'] ?? ''));
        $newExt = strtolower((string) ($stored['extension'] ?? ''));

        $isNameOrExtChanged = ($currentName !== $newName) || ($currentExt !== $newExt);
        $reasonType = trim((string) ($input['reason_type'] ?? ''));
        $reasonDetail = trim((string) ($input['reason_detail'] ?? $input['reason_other_detail'] ?? ''));

        $allowedReasonTypes = [
            'name_change' => 'Cambio de nombre del documento',
            'format_change' => 'Cambio de formato',
            'restructuring' => 'Reestructuración del documento',
            'substitution' => 'Sustitución por versión actualizada',
            'wrong_file' => 'Corrección del archivo equivocado',
            'other' => 'Otro',
        ];

        $finalReason = 'Reemplazo de archivo observada';

        if ($isNameOrExtChanged) {
            if ($reasonType === '' || !isset($allowedReasonTypes[$reasonType])) {
                throw new InvalidArgumentException('El motivo del cambio es obligatorio cuando cambia el nombre o formato del archivo.', 422);
            }

            if ($reasonType === 'other') {
                if (mb_strlen($reasonDetail) < 5) {
                    throw new InvalidArgumentException('Describe el motivo del cambio (mínimo 5 caracteres).', 422);
                }
                $finalReason = 'Otro: ' . $reasonDetail;
            } else {
                $finalReason = $allowedReasonTypes[$reasonType];
            }
        }

        return (new ProjectFileVersionChangeService())->replaceWorkspaceInTransaction($db, $projectId, $fileId, $checksum, $stored, $actor, $finalReason);
    }

    private function remove(PDO $db, int $projectId, int $fileId, int $actor): array
    {
        $this->lockWorkspace($db, $projectId, $actor);
        $model = new ProjectDocumentModel($db); $file = $model->findActiveFile($projectId, $fileId, true);
        $this->assertEditableFile($db, $projectId, $file);
        $removed = $model->retire($projectId, [$fileId], $actor);
        (new ProjectAuditService($db))->record($projectId, $actor, 'project_workspace_file_removed', 'project_file', $fileId, ['checksum'=>(string)$file['checksum_sha256']], ['removed'=>true], 'Archivo retirado del espacio de trabajo.');
        return ['file_id'=>$fileId,'removed'=>count($removed) === 1];
    }

    private function lockWorkspace(PDO $db, int $projectId, int $actor): void
    {
        $project = $db->prepare('SELECT id,status,deleted_at FROM projects WHERE id=:id FOR UPDATE'); $project->execute(['id'=>$projectId]); $row=$project->fetch();
        if (!$row || !empty($row['deleted_at'])) throw new InvalidArgumentException('El proyecto no está disponible.');
        if ((string)$row['status'] !== 'development') throw new ProjectDocumentVersionException('Los archivos solo pueden modificarse mientras el proyecto está En desarrollo.', 409);
        $student=$db->prepare("SELECT 1 FROM project_participants pp JOIN user_roles ur ON ur.user_id=pp.user_id JOIN roles r ON r.id=ur.role_id AND r.code='student' WHERE pp.project_id=:project AND pp.user_id=:actor AND pp.role_code='student' AND pp.status='active' AND pp.removed_at IS NULL LIMIT 1");
        $student->execute(['project'=>$projectId,'actor'=>$actor]); if (!$student->fetchColumn()) throw new ProjectDocumentVersionException('No tienes permiso para modificar los archivos de este proyecto.', 403);
    }

    private function assertEditableFile(PDO $db, int $projectId, array $file): void
    {
        $state=$db->prepare('SELECT status FROM project_file_review_states WHERE project_id=:project AND file_id=:file AND checksum_sha256=:checksum');
        $state->execute(['project'=>$projectId,'file'=>(int)$file['id'],'checksum'=>(string)$file['checksum_sha256']]);
        if (($state->fetchColumn() ?: 'development') !== 'development') throw new ProjectDocumentVersionException('Este archivo está protegido por una revisión previa y no puede modificarse en esta fase.', 409);
    }

    private function singleUpload(array $file): array { if (is_array($file['name'] ?? null)) throw new InvalidArgumentException('Selecciona un archivo por operación.'); return $file; }
    private function json(bool $success, string $message, array $data = [], int $status = 200): never { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['success'=>$success,'message'=>$message,'data'=>$data], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
}
