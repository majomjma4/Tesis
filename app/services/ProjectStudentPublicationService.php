<?php

declare(strict_types=1);

/** Publicación institucional iniciada por un estudiante participante activo. */
final class ProjectStudentPublicationService
{
    /** @return array{project_id:int,title:string,file_count:int,files:list<array{id:int,name:string,size_bytes:int,extension:string}>} */
    public function preview(int $projectId, int $userId): array
    {
        return Database::transaction(fn (PDO $db): array => $this->context($db, $projectId, $userId, false));
    }

    /** @return array<string,mixed> */
    public function publish(int $projectId, int $userId, ?int $presentationFileId = null, bool $hasPresentationSelection = false): array
    {
        return Database::transaction(function (PDO $db) use ($projectId, $userId, $presentationFileId, $hasPresentationSelection): array {
            $context = $this->context($db, $projectId, $userId, true);
            if ($hasPresentationSelection) $this->setPresentationInTransaction($db, $projectId, $presentationFileId);
            $transition = (new ProjectStatusTransitionService())->transitionInTransaction(
                $db,
                $projectId,
                (string) $context['status'],
                'published',
                '',
                $userId,
                'student_publication'
            );
            return $transition + ['project_id'=>$projectId, 'file_count'=>$context['file_count']];
        });
    }

    /** Promueve una preparación temporal y publica exactamente su conjunto final de archivos. */
    public function publishPrepared(int $projectId,int $userId,string $preparationId,?int $presentationFileId = null,bool $hasPresentationSelection = false):array
    {
        $preparations=new ProjectPublicationPreparationService();$plan=$preparations->plan($preparationId,$projectId,$userId);$moved=[];
        try {
            $result=Database::transaction(function(PDO $db)use($projectId,$userId,$plan,$presentationFileId,$hasPresentationSelection,&$moved):array{
                $this->context($db,$projectId,$userId,true);$summary=(new ProjectPublicationPreparationService())->summary($plan);if((int)$summary['file_count']<1)throw new ProjectStudentPublicationException('Debes incluir al menos un archivo para publicar el proyecto.',422);
                $this->applyPreparation($db,$projectId,$userId,$plan,$moved);
                if ($hasPresentationSelection) $this->setPresentationInTransaction($db, $projectId, $presentationFileId);
                $transition=(new ProjectStatusTransitionService())->transitionInTransaction($db,$projectId,(string)$this->projectStatus($db,$projectId),'published','',$userId,'student_publication');
                return $transition+['project_id'=>$projectId,'file_count'=>(int)$summary['file_count']];
            });
        } catch(Throwable $error) {
            $storage=new ProjectDocumentFileService();foreach(array_reverse($moved) as $move)$storage->restorePublicationUpload($move['prepared'],$move['promoted']);throw $error;
        }
        $preparations->complete($plan);return $result;
    }

    private function applyPreparation(PDO $db,int $projectId,int $userId,array $plan,array &$moved):void
    {
        $model=new ProjectDocumentModel($db);$storage=new ProjectDocumentFileService();
        foreach((array)($plan['replacements']??[]) as $fileId=>$prepared){if(empty($plan['items'][$fileId]['included']))continue;$current=$model->findActiveFile($projectId,(int)$fileId,true);$promoted=$storage->promotePublicationUpload($projectId,$prepared);$moved[]=['prepared'=>$prepared,'promoted'=>$promoted];(new ProjectFileVersionChangeService())->replaceForPublicationInTransaction($db,$projectId,(int)$fileId,(string)$current['checksum_sha256'],$promoted,$userId);}
        foreach((array)($plan['additions']??[]) as $prepared){if(empty($prepared['included']))continue;$promoted=$storage->promotePublicationUpload($projectId,$prepared);$moved[]=['prepared'=>$prepared,'promoted'=>$promoted];$file=$model->add($projectId,$promoted,$userId);(new ProjectDocumentReviewService($db))->recordCurrentStatus($projectId,(int)$file['id'],(string)$file['checksum_sha256'],'approved',$userId);(new ProjectAuditService($db))->record($projectId,$userId,'project_publication_file_added','project_file',(int)$file['id'],null,['file_id'=>(int)$file['id'],'checksum'=>(string)$file['checksum_sha256'],'publication_final'=>true],'Archivo final agregado antes de publicación.');}
        $removed=[];foreach((array)($plan['items']??[]) as $fileId=>$item)if(empty($item['included']))$removed[]=(int)$fileId;if($removed!==[]){$files=$model->retire($projectId,$removed,$userId);foreach($files as $file)(new ProjectAuditService($db))->record($projectId,$userId,'project_publication_file_excluded','project_file',(int)$file['id'],['active'=>true],['active'=>false,'publication_final'=>true],'Archivo excluido de la publicación final.');}
    }

    private function projectStatus(PDO $db,int $projectId):string
    {
        $statement=$db->prepare('SELECT status FROM projects WHERE id=:id FOR UPDATE');$statement->execute(['id'=>$projectId]);$status=$statement->fetchColumn();if(!is_string($status))throw new ProjectStudentPublicationException('El proyecto ya no está disponible.',404);return $status;
    }

    private function setPresentationInTransaction(PDO $db, int $projectId, ?int $fileId): void
    {
        if ($fileId !== null) {
            $extensions = ProjectDocumentModel::PRESENTATION_EXTENSIONS;
            $marks = implode(',', array_fill(0, count($extensions), '?'));
            $query = $db->prepare("SELECT f.id
                FROM project_files f
                INNER JOIN project_file_review_states s
                  ON s.project_id=f.project_id AND s.file_id=f.id AND s.checksum_sha256=f.checksum_sha256
                WHERE f.project_id=? AND f.id=? AND f.deleted_at IS NULL AND f.purged_at IS NULL
                  AND LOWER(f.extension) IN ($marks) AND s.status='approved'
                LIMIT 1 FOR UPDATE");
            $query->execute([$projectId, $fileId, ...$extensions]);
            if (!$query->fetchColumn()) throw new ProjectStudentPublicationException('El archivo de presentación seleccionado no es elegible para este proyecto.', 422);
        }
        $update = $db->prepare('UPDATE projects SET presentation_file_id=:file WHERE id=:project');
        $update->execute(['file'=>$fileId, 'project'=>$projectId]);
    }

    /** @return array{project_id:int,title:string,status:string,file_count:int,files:list<array{id:int,name:string,size_bytes:int,extension:string}>} */
    private function context(PDO $db, int $projectId, int $userId, bool $forUpdate): array
    {
        if ($projectId < 1) throw new ProjectStudentPublicationException('El proyecto solicitado no es válido.', 422);
        $project = $db->prepare(
            "SELECT p.id,p.title,p.status,p.presentation_file_id,p.is_available,p.withdrawn_at,p.deleted_at,pt.code AS type_code
             FROM projects p INNER JOIN project_types pt ON pt.id=p.project_type_id
             WHERE p.id=:id" . ($forUpdate ? ' FOR UPDATE' : '')
        );
        $project->execute(['id'=>$projectId]);
        $row = $project->fetch();
        if (!$row || !empty($row['deleted_at'])) throw new ProjectStudentPublicationException('El proyecto no está disponible.', 404);
        if (!(new ProjectCapabilityService())->canPublishAsActiveStudentInTransaction($db, $projectId, $userId)) {
            throw new ProjectStudentPublicationException('No tienes autorización para publicar este proyecto.', 403);
        }
        if (empty($row['is_available']) || !empty($row['withdrawn_at'])) {
            throw new ProjectStudentPublicationException('El proyecto no está disponible para publicación.', 422);
        }
        $expected = (string) $row['type_code'] === 'thesis' ? 'tribunal_approved' : 'approved';
        if ((string) $row['status'] !== $expected) {
            throw new ProjectStudentPublicationException('El proyecto no se encuentra en un estado válido para publicar.', 422);
        }

        $files = $db->prepare(
            "SELECT f.id,f.original_name,f.size_bytes,f.extension
             FROM project_files f
             INNER JOIN project_file_review_states s
               ON s.project_id=f.project_id AND s.file_id=f.id AND s.checksum_sha256=f.checksum_sha256
             WHERE f.project_id=:project AND f.deleted_at IS NULL AND f.purged_at IS NULL
               AND s.status='approved'
             ORDER BY f.sort_order,f.id" . ($forUpdate ? ' FOR UPDATE' : '')
        );
        $files->execute(['project'=>$projectId]);
        $approved = array_map(static fn (array $file): array => ['id'=>(int)$file['id'], 'name'=>(string)$file['original_name'],'size_bytes'=>(int)$file['size_bytes'],'extension'=>(string)$file['extension']], $files->fetchAll());

        $active = $db->prepare('SELECT COUNT(*) FROM project_files WHERE project_id=:project AND deleted_at IS NULL AND purged_at IS NULL');
        $active->execute(['project'=>$projectId]);
        $activeCount = (int) $active->fetchColumn();
        if ($approved === []) throw new ProjectStudentPublicationException('No hay archivos aprobados disponibles para publicar este proyecto.', 422);
        if ($activeCount !== count($approved)) {
            throw new ProjectStudentPublicationException('Existen archivos activos pendientes de aprobación. Revísalos antes de publicar el proyecto.', 422);
        }
        $presentationFiles = array_values(array_filter($approved, static fn (array $file): bool => in_array(strtolower((string) $file['extension']), ProjectDocumentModel::PRESENTATION_EXTENSIONS, true)));
        $currentPresentationId = $row['presentation_file_id'] === null ? null : (int) $row['presentation_file_id'];
        if ($currentPresentationId !== null && !in_array($currentPresentationId, array_column($presentationFiles, 'id'), true)) $currentPresentationId = null;
        return ['project_id'=>$projectId, 'title'=>(string)$row['title'], 'status'=>(string)$row['status'], 'file_count'=>count($approved), 'files'=>$approved, 'presentation_file_id'=>$currentPresentationId, 'presentation_files'=>$presentationFiles];
    }
}
