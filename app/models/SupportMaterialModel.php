<?php

declare(strict_types=1);

final class SupportMaterialModel
{
    public const RESTORE_HOURS = 24;
    private const PREVIEW_EXTENSIONS = ['pdf','docx','txt','png','jpg','jpeg','webp'];

    public function getAll(): array
    {
        return $this->listing('published');
    }

    public function getWithdrawn(): array
    {
        $materials = $this->listing('withdrawn');
        if ($materials === []) return [];

        $ids = array_column($materials, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = Database::connection()->prepare(
            "SELECT audit.entity_id,audit.created_at,u.full_name actor_name,audit.details
             FROM admin_audit_log audit
             INNER JOIN (
                SELECT entity_id,MAX(id) latest_id
                FROM admin_audit_log
                WHERE entity_type='support_material'
                  AND action='support_material_withdrawn'
                  AND result='correct'
                  AND entity_id IN ({$placeholders})
                GROUP BY entity_id
             ) latest ON latest.latest_id=audit.id
             LEFT JOIN users u ON u.id=audit.actor_user_id"
        );
        $statement->execute($ids);
        $withdrawals = [];
        foreach ($statement->fetchAll() as $event) {
            $details = json_decode((string) ($event['details'] ?? '{}'), true);
            $withdrawals[(int) $event['entity_id']] = [
                'withdrawn_event_at' => $event['created_at'],
                'withdrawn_by_name' => (string) ($event['actor_name'] ?? ''),
                'withdrawal_reason' => is_array($details) ? (string) ($details['reason'] ?? '') : '',
                'withdrawal_reason_detail' => is_array($details) ? (string) ($details['reason_detail'] ?? '') : '',
            ];
        }
        foreach ($materials as &$material) {
            $material += $withdrawals[(int) $material['id']] ?? [
                'withdrawn_event_at' => $material['withdrawn_at'] ?? null,
                'withdrawn_by_name' => '',
                'withdrawal_reason' => '',
                'withdrawal_reason_detail' => '',
            ];
        }
        unset($material);
        return $materials;
    }

    public function getAdminMaterials(): array
    {
        $statement = Database::connection()->query(
            $this->baseQuery()
            . " WHERE sm.status IN ('draft','published') AND sm.deleted_at IS NULL AND sm.purged_at IS NULL ORDER BY sm.publication_date DESC,sm.id DESC"
        );
        return array_map([$this, 'hydrate'], $statement->fetchAll());
    }

    public function categories(): array
    {
        return Database::connection()->query(
            'SELECT id,slug,name FROM support_material_categories
             WHERE is_active=1 ORDER BY name'
        )->fetchAll();
    }

    public function findById(int $materialId, bool $includeWithdrawn = false): ?array
    {
        if ($materialId < 1) return null;
        $where = $includeWithdrawn ? '' : " AND sm.status='published'";
        $statement = Database::connection()->prepare($this->baseQuery() . " WHERE sm.id=:id AND sm.deleted_at IS NULL AND sm.purged_at IS NULL{$where}");
        $statement->execute(['id' => $materialId]);
        $row = $statement->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function findByIdForUpdate(int $materialId): ?array
    {
        if ($materialId < 1) return null;
        $statement = Database::connection()->prepare($this->baseQuery() . ' WHERE sm.id=:id AND sm.deleted_at IS NULL AND sm.purged_at IS NULL FOR UPDATE');
        $statement->execute(['id' => $materialId]);
        $material = $statement->fetch();
        if (!$material) return null;
        $keywords = json_decode((string) ($material['keywords_json'] ?? '[]'), true);
        $material['category_id'] = (int) $material['category_id'];
        $material['publication_date_iso'] = (string) $material['publication_date'];
        $material['keywords'] = is_array($keywords) ? array_values(array_map('strval', $keywords)) : [];
        return $material;
    }

    public function categoryName(int $categoryId): ?string
    {
        $statement = Database::connection()->prepare(
            'SELECT name FROM support_material_categories WHERE id=:id AND is_active=1'
        );
        $statement->execute(['id' => $categoryId]);
        $name = $statement->fetchColumn();
        return $name === false ? null : (string) $name;
    }

    public function findFile(array $material, int $fileId): ?array
    {
        foreach ($material['files'] as $file) {
            if ($file['id'] === $fileId) return $file;
        }
        if (isset($material['package']) && $material['package']['id'] === $fileId) {
            return $material['package'];
        }
        return null;
    }

    public function save(array $input, int $actor): int
    {
        $id = (int) ($input['id'] ?? 0);
        $title = trim((string) ($input['title'] ?? ''));
        $type = trim((string) ($input['material_type'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $fullDescription = trim((string) ($input['full_description'] ?? ''));
        $publisher = trim((string) ($input['publisher'] ?? ''));
        $categoryId = (int) ($input['category_id'] ?? 0);
        $publicationDate = null;
        $keywords = array_values(array_unique(array_filter(array_map(
            'trim',
            preg_split('/[,;\n]+/u', (string) ($input['keywords'] ?? '')) ?: []
        ))));

        if ($title === '' || mb_strlen($title) > 220) {
            throw new InvalidArgumentException('Ingresa un título válido de hasta 220 caracteres.');
        }
        if ($type === '' || mb_strlen($type) > 100) {
            throw new InvalidArgumentException('Ingresa un tipo de material válido.');
        }
        if ($description === '' || mb_strlen($description) > 500) {
            throw new InvalidArgumentException('Ingresa una descripción corta de hasta 500 caracteres.');
        }
        if ($fullDescription === '') {
            throw new InvalidArgumentException('Ingresa la descripción completa del material.');
        }
        if ($publisher === '' || mb_strlen($publisher) > 180) {
            throw new InvalidArgumentException('Ingresa el responsable de la publicación.');
        }
        $database = Database::connection();
        $category = $database->prepare(
            'SELECT COUNT(*) FROM support_material_categories WHERE id=:id AND is_active=1'
        );
        $category->execute(['id' => $categoryId]);
        if ((int) $category->fetchColumn() !== 1) {
            throw new InvalidArgumentException('Selecciona una categoría válida.');
        }

        $payload = [
            'category_id' => $categoryId,
            'title' => $title,
            'material_type' => $type,
            'description' => $description,
            'full_description' => $fullDescription,
            'publisher' => $publisher,
            'publication_date' => $publicationDate,
            'keywords_json' => json_encode($keywords, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'updated_by' => $actor,
        ];

        if ($id > 0) {
            if ($this->findById($id, true) === null) {
                throw new InvalidArgumentException('El material ya no está disponible.');
            }
            $payload['id'] = $id;
            $updatePayload = $payload;
            unset($updatePayload['publication_date']);
            $statement = $database->prepare(
                'UPDATE support_materials SET
                 category_id=:category_id,title=:title,material_type=:material_type,
                 description=:description,full_description=:full_description,
                 publisher=:publisher,
                 keywords_json=:keywords_json,updated_by=:updated_by
                 WHERE id=:id'
            );
            $statement->execute($updatePayload);
            return $id;
        }

        $payload['created_by'] = $actor;
        $statement = $database->prepare(
            "INSERT INTO support_materials
             (category_id,title,material_type,description,full_description,publisher,
              publication_date,status,keywords_json,created_by,updated_by)
             VALUES
             (:category_id,:title,:material_type,:description,:full_description,:publisher,
              :publication_date,'draft',:keywords_json,:created_by,:updated_by)"
        );
        $statement->execute($payload);
        return (int) $database->lastInsertId();
    }

    public function setStatus(int $id, string $status, int $actor): array
    {
        if (!in_array($status, ['published', 'withdrawn'], true)) {
            throw new InvalidArgumentException('El estado solicitado no es válido.');
        }
        $material = $this->findByIdForUpdate($id);
        if ($material === null) {
            throw new InvalidArgumentException('El material ya no está disponible.');
        }
        if ((string) $material['status'] === $status) {
            throw new InvalidArgumentException($status === 'published'
                ? 'El material ya está publicado.'
                : 'El material ya está retirado.');
        }
        Database::connection()->prepare(
            "UPDATE support_materials SET status=:status,
             publication_date=IF(:status_for_publication_date='published',COALESCE(publication_date,UTC_DATE()),publication_date),
             published_at=IF(:status_for_publication='published',COALESCE(published_at,UTC_TIMESTAMP()),published_at),
             withdrawn_at=IF(:status_for_date='withdrawn',CURRENT_TIMESTAMP,NULL),
             withdrawn_by=IF(:status_for_actor='withdrawn',:withdrawn_actor,NULL),
             updated_by=:updated_actor
             WHERE id=:id"
        )->execute([
            'status' => $status,
            'status_for_publication_date' => $status,
            'status_for_publication' => $status,
            'status_for_date' => $status,
            'status_for_actor' => $status,
            'withdrawn_actor' => $actor,
            'updated_actor' => $actor,
            'id' => $id,
        ]);
        return [
            'previous_status' => (string) $material['status'],
            'was_previously_published' => !empty($material['published_at']),
        ];
    }

    public function setAvailability(int $id, bool $available, int $actor): bool
    {
        $material = $this->findByIdForUpdate($id);
        if ($material === null) throw new InvalidArgumentException('El material ya no está disponible.');
        if ((string) $material['status'] !== 'published') {
            throw new InvalidArgumentException('La disponibilidad solo puede cambiarse en materiales publicados.');
        }
        $previous = (bool) $material['is_available'];
        if ($previous === $available) {
            throw new InvalidArgumentException($available
                ? 'El material ya está disponible.'
                : 'El material ya está marcado como no disponible.');
        }
        Database::connection()->prepare(
            'UPDATE support_materials SET is_available=:available,updated_by=:actor WHERE id=:id'
        )->execute(['available' => $available ? 1 : 0, 'actor' => $actor, 'id' => $id]);
        return $previous;
    }

    public function addFile(int $materialId, array $file, int $actor): int
    {
        if ($this->findById($materialId, true) === null) {
            throw new InvalidArgumentException('El material ya no está disponible.');
        }
        $database = Database::connection();
        $statement = $database->prepare(
            'INSERT INTO support_material_files
             (material_id,original_name,storage_name,relative_path,extension,mime_type,
              size_bytes,is_package,sort_order,created_by)
             VALUES
             (:material_id,:original_name,:storage_name,:relative_path,:extension,:mime_type,
              :size_bytes,0,
              (SELECT COALESCE(MAX(existing.sort_order),0)+1 FROM support_material_files existing
               WHERE existing.material_id=:sort_material_id),:created_by)'
        );
        $statement->execute([
            'material_id' => $materialId,
            'original_name' => $file['original_name'],
            'storage_name' => $file['storage_name'],
            'relative_path' => $file['relative_path'],
            'extension' => $file['extension'],
            'mime_type' => $file['mime_type'],
            'size_bytes' => $file['size_bytes'],
            'sort_material_id' => $materialId,
            'created_by' => $actor,
        ]);
        return (int) $database->lastInsertId();
    }

    public function hasActiveFileEquivalent(int $materialId, string $originalName, int $sizeBytes): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT 1 FROM support_material_files
             WHERE material_id=:material_id AND original_name=:original_name
               AND size_bytes=:size_bytes AND deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute([
            'material_id' => $materialId,
            'original_name' => $originalName,
            'size_bytes' => $sizeBytes,
        ]);
        return $statement->fetchColumn() !== false;
    }

    public function replaceFile(int $materialId, int $fileId, array $replacement, int $actor): array
    {
        if ($materialId < 1 || $fileId < 1) {
            throw new InvalidArgumentException('El archivo seleccionado no es válido.');
        }
        $database = Database::connection();
        $statement = $database->prepare(
            'SELECT file.*,material.presentation_file_id
             FROM support_material_files file
             JOIN support_materials material ON material.id=file.material_id
             WHERE file.id=:file_id AND file.material_id=:material_id
             FOR UPDATE'
        );
        $statement->execute(['file_id' => $fileId, 'material_id' => $materialId]);
        $current = $statement->fetch();
        if (!$current) throw new InvalidArgumentException('El archivo no pertenece a este material.');
        if ($current['deleted_at'] !== null) throw new InvalidArgumentException('El archivo ya fue retirado.');
        if ((int) $current['is_package'] === 1) {
            throw new InvalidArgumentException('El paquete institucional no puede reemplazarse.');
        }
        if ((int) ($current['presentation_file_id'] ?? 0) === $fileId
            && !$this->isPreviewCompatible($replacement)) {
            throw new InvalidArgumentException(
                'La presentación solo puede reemplazarse por un archivo compatible con la vista previa.'
            );
        }
        $duplicate = $database->prepare(
            'SELECT 1 FROM support_material_files
             WHERE material_id=:material_id AND id<>:file_id
               AND original_name=:original_name AND size_bytes=:size_bytes
               AND deleted_at IS NULL LIMIT 1'
        );
        $duplicate->execute([
            'material_id' => $materialId,
            'file_id' => $fileId,
            'original_name' => $replacement['original_name'],
            'size_bytes' => $replacement['size_bytes'],
        ]);
        if ($duplicate->fetchColumn() !== false) {
            throw new InvalidArgumentException('Ya existe un archivo activo con el mismo nombre y tamaño.');
        }
        $database->prepare(
            'INSERT INTO support_material_file_versions
             (file_id,material_id,original_name,storage_name,relative_path,extension,mime_type,size_bytes,replaced_by)
             VALUES
             (:file_id,:material_id,:original_name,:storage_name,:relative_path,:extension,:mime_type,:size_bytes,:actor)'
        )->execute([
            'file_id' => $fileId,
            'material_id' => $materialId,
            'original_name' => $current['original_name'],
            'storage_name' => $current['storage_name'],
            'relative_path' => $current['relative_path'],
            'extension' => $current['extension'],
            'mime_type' => $current['mime_type'],
            'size_bytes' => $current['size_bytes'],
            'actor' => $actor,
        ]);
        $versionId = (int) $database->lastInsertId();
        $database->prepare(
            'UPDATE support_material_files
             SET original_name=:original_name,storage_name=:storage_name,
                 relative_path=:relative_path,extension=:extension,
                 mime_type=:mime_type,size_bytes=:size_bytes
             WHERE id=:file_id AND material_id=:material_id AND deleted_at IS NULL'
        )->execute([
            'original_name' => $replacement['original_name'],
            'storage_name' => $replacement['storage_name'],
            'relative_path' => $replacement['relative_path'],
            'extension' => $replacement['extension'],
            'mime_type' => $replacement['mime_type'],
            'size_bytes' => $replacement['size_bytes'],
            'file_id' => $fileId,
            'material_id' => $materialId,
        ]);
        return [
            'file_id' => $fileId,
            'version_id' => $versionId,
            'presentation' => (int) ($current['presentation_file_id'] ?? 0) === $fileId,
            'sort_order' => (int) $current['sort_order'],
            'old' => [
                'name' => (string) $current['original_name'],
                'extension' => (string) $current['extension'],
                'mime_type' => (string) $current['mime_type'],
                'size_bytes' => (int) $current['size_bytes'],
            ],
            'new' => [
                'name' => (string) $replacement['original_name'],
                'extension' => (string) $replacement['extension'],
                'mime_type' => (string) $replacement['mime_type'],
                'size_bytes' => (int) $replacement['size_bytes'],
            ],
        ];
    }

    public function removeAdditionalFile(int $materialId, int $fileId, int $actor): array
    {
        return $this->removeAdditionalFiles($materialId, [$fileId], $actor)[0];
    }

    public function removeAdditionalFiles(int $materialId, array $fileIds, int $actor): array
    {
        $database = Database::connection();
        $fileIds = array_values(array_unique(array_map('intval', $fileIds)));
        if ($materialId < 1 || $fileIds === [] || in_array(0, $fileIds, true)) {
            throw new InvalidArgumentException('Selecciona al menos un archivo válido.');
        }
        $placeholders = implode(',', array_fill(0, count($fileIds), '?'));
        $statement = $database->prepare(
            'SELECT id,original_name,extension,size_bytes,is_package,deleted_at
             FROM support_material_files
             WHERE material_id=? AND id IN (' . $placeholders . ')
             FOR UPDATE'
        );
        $statement->execute([$materialId, ...$fileIds]);
        $rows = $statement->fetchAll();
        if (count($rows) !== count($fileIds)) throw new InvalidArgumentException('Uno o más archivos no pertenecen al material o ya no existen.');
        $filesById = [];
        foreach ($rows as $file) {
            if ($file['deleted_at'] !== null) throw new InvalidArgumentException('Uno o más archivos ya fueron retirados.');
            if ((int) $file['is_package'] === 1) throw new InvalidArgumentException('El paquete institucional no puede retirarse.');
            $filesById[(int) $file['id']] = $file;
        }
        $update = $database->prepare(
            'UPDATE support_material_files
             SET deleted_at=CURRENT_TIMESTAMP,deleted_by=?
             WHERE material_id=? AND id IN (' . $placeholders . ') AND deleted_at IS NULL'
        );
        $update->execute([$actor, $materialId, ...$fileIds]);
        if ($update->rowCount() !== count($fileIds)) throw new RuntimeException('No fue posible aplicar la baja lógica a todos los archivos.');
        return array_map(static function (int $id) use ($filesById): array {
            $file = $filesById[$id];
            return [
                'id' => $id,
                'name' => (string) $file['original_name'],
                'extension' => (string) $file['extension'],
                'size_bytes' => (int) $file['size_bytes'],
            ];
        }, $fileIds);
    }

    public function restorableFiles(int $materialId): array
    {
        if ($materialId < 1) return [];
        $statement = Database::connection()->prepare(
            'SELECT file.id,file.material_id,file.original_name,file.relative_path,
                    file.extension,file.mime_type,file.size_bytes,file.sort_order,
                    file.deleted_at,file.deleted_by,user.full_name deleted_by_name
             FROM support_material_files file
             LEFT JOIN users user ON user.id=file.deleted_by
             WHERE file.material_id=:material_id
               AND file.deleted_at IS NOT NULL
               AND file.purged_at IS NULL
               AND file.deleted_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL ' . self::RESTORE_HOURS . ' HOUR)
               AND file.deleted_at<=UTC_TIMESTAMP()
               AND file.is_package=0
             ORDER BY file.deleted_at DESC,file.id DESC'
        );
        $statement->execute(['material_id' => $materialId]);
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $files = [];
        foreach ($statement->fetchAll() as $file) {
            $path = $this->supportFilePath((string) $file['relative_path']);
            if (!is_file($path)) continue;
            $deletedAt = new DateTimeImmutable((string) $file['deleted_at'], new DateTimeZone('UTC'));
            $expiresAt = $deletedAt->modify('+' . self::RESTORE_HOURS . ' hours');
            $remainingSeconds = max(0, $expiresAt->getTimestamp() - $now->getTimestamp());
            if ($remainingSeconds < 1) continue;
            $files[] = [
                'id' => (int) $file['id'],
                'material_id' => (int) $file['material_id'],
                'name' => (string) $file['original_name'],
                'extension' => $this->resolveFileExtension($file),
                'mime_type' => (string) $file['mime_type'],
                'size_bytes' => (int) $file['size_bytes'],
                'size' => ArchiveService::formatBytes((int) $file['size_bytes']),
                'sort_order' => (int) $file['sort_order'],
                'deleted_at' => $deletedAt->format(DATE_ATOM),
                'deleted_at_label' => $deletedAt->setTimezone(new DateTimeZone('America/Guayaquil'))->format('d/m/Y H:i'),
                'deleted_by' => $file['deleted_by'] === null ? null : (int) $file['deleted_by'],
                'deleted_by_name' => (string) ($file['deleted_by_name'] ?: 'Usuario no disponible'),
                'remaining_seconds' => $remainingSeconds,
                'remaining_label' => $this->restoreRemainingLabel($remainingSeconds),
            ];
        }
        return $files;
    }

    public function inspectFileRestore(int $materialId, int $fileId, bool $lock = false): array
    {
        if ($materialId < 1 || $fileId < 1) {
            throw new InvalidArgumentException('El archivo seleccionado no es válido.');
        }
        $database = Database::connection();
        $statement = $database->prepare(
            'SELECT file.*,user.full_name deleted_by_name
             FROM support_material_files file
             LEFT JOIN users user ON user.id=file.deleted_by
             WHERE file.id=:file_id' . ($lock ? ' FOR UPDATE' : '')
        );
        $statement->execute(['file_id' => $fileId]);
        $file = $statement->fetch();
        if (!$file) throw new InvalidArgumentException('El archivo solicitado no existe.');
        if ((int) $file['material_id'] !== $materialId) {
            throw new InvalidArgumentException('El archivo no pertenece a este material.');
        }
        if ($file['deleted_at'] === null) {
            throw new InvalidArgumentException('El archivo ya fue restaurado.');
        }
        if ($file['purged_at'] !== null) {
            throw new InvalidArgumentException('Este archivo fue eliminado definitivamente y ya no se encuentra disponible.');
        }
        if ((int) $file['is_package'] === 1) {
            throw new InvalidArgumentException('El paquete institucional no puede restaurarse desde esta opción.');
        }
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $deletedAt = new DateTimeImmutable((string) $file['deleted_at'], new DateTimeZone('UTC'));
        $expiresAt = $deletedAt->modify('+' . self::RESTORE_HOURS . ' hours');
        if ($deletedAt > $now || $expiresAt <= $now) {
            throw new InvalidArgumentException('El plazo de 24 horas para restaurar este archivo expiró.');
        }
        $path = $this->supportFilePath((string) $file['relative_path']);
        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidArgumentException('El archivo físico ya no está disponible y no puede restaurarse.');
        }
        $activeStatement = $database->prepare(
            'SELECT id,original_name,relative_path
             FROM support_material_files
             WHERE material_id=:material_id AND deleted_at IS NULL AND is_package=0' . ($lock ? ' FOR UPDATE' : '')
        );
        $activeStatement->execute(['material_id' => $materialId]);
        $activeFiles = $activeStatement->fetchAll();
        $normalizedOriginal = $this->normalizeRestoreName((string) $file['original_name']);
        $sameName = current(array_filter(
            $activeFiles,
            fn (array $active): bool => $this->normalizeRestoreName((string) $active['original_name']) === $normalizedOriginal
        )) ?: null;
        $conflict = false;
        $finalName = (string) $file['original_name'];
        if ($sameName !== null) {
            $activePath = $this->supportFilePath((string) $sameName['relative_path']);
            if (is_file($activePath) && is_readable($activePath)
                && hash_equals((string) hash_file('sha256', $path), (string) hash_file('sha256', $activePath))) {
                throw new InvalidArgumentException('Este archivo ya se encuentra disponible dentro del material.');
            }
            $conflict = true;
            $finalName = $this->suggestRestoredName((string) $file['original_name'], $activeFiles);
        }
        $remainingSeconds = max(0, $expiresAt->getTimestamp() - $now->getTimestamp());
        return [
            'file_id' => $fileId,
            'material_id' => $materialId,
            'original_name' => (string) $file['original_name'],
            'final_name' => $finalName,
            'conflict' => $conflict,
            'conflicting_name' => $sameName === null ? null : (string) $sameName['original_name'],
            'deleted_at' => $deletedAt->format(DATE_ATOM),
            'deleted_by' => $file['deleted_by'] === null ? null : (int) $file['deleted_by'],
            'deleted_by_name' => (string) ($file['deleted_by_name'] ?: 'Usuario no disponible'),
            'remaining_seconds' => $remainingSeconds,
            'remaining_label' => $this->restoreRemainingLabel($remainingSeconds),
            'extension' => $this->resolveFileExtension($file),
            'mime_type' => (string) $file['mime_type'],
            'size_bytes' => (int) $file['size_bytes'],
            'size' => ArchiveService::formatBytes((int) $file['size_bytes']),
            'sort_order' => (int) $file['sort_order'],
        ];
    }

    public function restoreFile(int $materialId, int $fileId, int $actor, string $confirmedName): array
    {
        $inspection = $this->inspectFileRestore($materialId, $fileId, true);
        if ($confirmedName === '' || $this->normalizeRestoreName($confirmedName)
            !== $this->normalizeRestoreName((string) $inspection['final_name'])) {
            throw new InvalidArgumentException('El conflicto cambió. Revisa nuevamente el nombre propuesto.');
        }
        $statement = Database::connection()->prepare(
            'UPDATE support_material_files
             SET original_name=:name,deleted_at=NULL,deleted_by=NULL
             WHERE id=:file_id AND material_id=:material_id AND deleted_at IS NOT NULL'
        );
        $statement->execute([
            'name' => $inspection['final_name'],
            'file_id' => $fileId,
            'material_id' => $materialId,
        ]);
        if ($statement->rowCount() !== 1) {
            throw new InvalidArgumentException('El archivo ya fue restaurado.');
        }
        return $inspection;
    }

    public function documentEvolution(int $materialId): array
    {
        if ($materialId < 1) return [];
        $statement = Database::connection()->prepare(
            'SELECT file.id file_id,file.material_id,file.original_name current_name,
                    file.relative_path current_path,file.extension current_extension,
                    file.mime_type current_mime,file.size_bytes current_size,
                    file.created_at,file.created_by,file.deleted_at,file.purged_at,
                    creator.full_name created_by_name,
                    version.id version_id,version.original_name version_name,
                    version.relative_path version_path,version.extension version_extension,
                    version.mime_type version_mime,version.size_bytes version_size,
                    version.replaced_at,version.replaced_by,replacer.full_name replaced_by_name
             FROM support_material_files file
             JOIN support_material_file_versions version
               ON version.file_id=file.id AND version.material_id=file.material_id
             LEFT JOIN users creator ON creator.id=file.created_by
             LEFT JOIN users replacer ON replacer.id=version.replaced_by
             WHERE file.material_id=:material_id
             ORDER BY file.sort_order,file.id,version.replaced_at,version.id'
        );
        $statement->execute(['material_id' => $materialId]);
        $rows = $statement->fetchAll();
        if (!$rows) return [];

        $auditStatement = Database::connection()->prepare(
            "SELECT audit.id,audit.actor_user_id,audit.created_at,audit.details,
                    actor.full_name actor_name
             FROM admin_audit_log
             audit LEFT JOIN users actor ON actor.id=audit.actor_user_id
             WHERE audit.action='support_material.file_replaced'
               AND audit.entity_type='support_material' AND audit.entity_id=:material_id
             ORDER BY audit.created_at,audit.id"
        );
        $auditStatement->execute(['material_id' => $materialId]);
        $audits = [];
        foreach ($auditStatement->fetchAll() as $audit) {
            $details = json_decode((string) ($audit['details'] ?? ''), true);
            $versionId = (int) ($details['version_id'] ?? 0);
            $fileId = (int) ($details['file_id'] ?? 0);
            if ($versionId > 0 && $fileId > 0) $audits[$fileId . ':' . $versionId] = $audit;
        }

        $grouped = [];
        foreach ($rows as $row) $grouped[(int) $row['file_id']][] = $row;
        $groups = [];
        foreach ($grouped as $fileId => $history) {
            $versions = [];
            foreach ($history as $index => $row) {
                $audit = $audits[$fileId . ':' . (int) $row['version_id']] ?? null;
                $createdBy = $index === 0
                    ? (string) ($row['created_by_name'] ?: 'Responsable no disponible')
                    : (string) ($history[$index - 1]['replaced_by_name']
                        ?: ($audits[$fileId . ':' . (int) $history[$index - 1]['version_id']]['actor_name'] ?? '')
                        ?: 'Responsable no disponible');
                $createdAt = $index === 0
                    ? (string) $row['created_at']
                    : (string) $history[$index - 1]['replaced_at'];
                $available = $this->storedSupportFileAvailable((string) $row['version_path']);
                $versions[] = [
                    'id' => (int) $row['version_id'],
                    'file_id' => $fileId,
                    'number' => $index + 1,
                    'current' => false,
                    'name' => (string) $row['version_name'],
                    'extension' => $this->resolveFileExtension([
                        'original_name' => $row['version_name'],
                        'extension' => $row['version_extension'],
                        'mime_type' => $row['version_mime'],
                    ]),
                    'size_bytes' => (int) $row['version_size'],
                    'size' => ArchiveService::formatBytes((int) $row['version_size']),
                    'created_at' => $createdAt,
                    'date' => $this->evolutionDate($createdAt),
                    'responsible' => $createdBy,
                    'available' => $available,
                    'preview_supported' => $available && $this->isPreviewCompatible([
                        'original_name' => $row['version_name'],
                        'extension' => $row['version_extension'],
                        'mime_type' => $row['version_mime'],
                    ]),
                    'replacement_audit_id' => $audit === null ? null : (int) $audit['id'],
                ];
            }
            $last = $history[count($history) - 1];
            $currentNumber = count($history) + 1;
            $currentAvailable = $last['deleted_at'] === null && $last['purged_at'] === null
                && $this->storedSupportFileAvailable((string) $last['current_path']);
            $versions[] = [
                'id' => null,
                'file_id' => $fileId,
                'number' => $currentNumber,
                'current' => true,
                'name' => (string) $last['current_name'],
                'extension' => $this->resolveFileExtension([
                    'original_name' => $last['current_name'],
                    'extension' => $last['current_extension'],
                    'mime_type' => $last['current_mime'],
                ]),
                'size_bytes' => (int) $last['current_size'],
                'size' => ArchiveService::formatBytes((int) $last['current_size']),
                'created_at' => (string) $last['replaced_at'],
                'date' => $this->evolutionDate((string) $last['replaced_at']),
                'responsible' => (string) ($last['replaced_by_name']
                    ?: ($audits[$fileId . ':' . (int) $last['version_id']]['actor_name'] ?? '')
                    ?: 'Responsable no disponible'),
                'available' => $currentAvailable,
                'preview_supported' => $currentAvailable && $this->isPreviewCompatible([
                    'original_name' => $last['current_name'],
                    'extension' => $last['current_extension'],
                    'mime_type' => $last['current_mime'],
                ]),
                'replacement_audit_id' => $audits[$fileId . ':' . (int) $last['version_id']]['id'] ?? null,
            ];
            $groups[] = [
                'file_id' => $fileId,
                'name' => (string) $last['current_name'],
                'extension' => $versions[count($versions) - 1]['extension'],
                'versions_count' => count($versions),
                'available' => $currentAvailable,
                'updated_at' => (string) $last['replaced_at'],
                'updated_date' => $this->evolutionDate((string) $last['replaced_at']),
                'responsible' => (string) ($last['replaced_by_name']
                    ?: ($audits[$fileId . ':' . (int) $last['version_id']]['actor_name'] ?? '')
                    ?: 'Responsable no disponible'),
                'versions' => array_reverse($versions),
            ];
        }
        return $groups;
    }

    public function findHistoricalVersion(int $materialId, int $fileId, int $versionId): ?array
    {
        if ($materialId < 1 || $fileId < 1 || $versionId < 1) return null;
        $statement = Database::connection()->prepare(
            'SELECT version.id,version.file_id,version.material_id,version.original_name name,
                    version.relative_path,version.extension,version.mime_type,version.size_bytes
             FROM support_material_file_versions version
             JOIN support_material_files file
               ON file.id=version.file_id AND file.material_id=version.material_id
             WHERE version.id=:version_id AND version.file_id=:file_id
               AND version.material_id=:material_id LIMIT 1'
        );
        $statement->execute([
            'version_id' => $versionId,
            'file_id' => $fileId,
            'material_id' => $materialId,
        ]);
        $version = $statement->fetch();
        if (!$version) return null;
        $path = $this->supportFilePath((string) $version['relative_path']);
        if (!$this->storedSupportFileAvailable((string) $version['relative_path'])) return null;
        return [
            'id' => (int) $version['id'],
            'file_id' => (int) $version['file_id'],
            'material_id' => (int) $version['material_id'],
            'name' => (string) $version['name'],
            'path' => $path,
            'extension' => $this->resolveFileExtension($version),
            'mime_type' => (string) $version['mime_type'],
            'size_bytes' => (int) $version['size_bytes'],
        ];
    }

    public function inspectPermanentFileDeletion(int $materialId, array $fileIds, bool $lock = false): array
    {
        $fileIds = array_values(array_unique(array_filter(array_map('intval', $fileIds), static fn (int $id): bool => $id > 0)));
        if ($materialId < 1 || !$fileIds || count($fileIds) > 20) {
            throw new InvalidArgumentException('Selecciona entre uno y veinte archivos para eliminar definitivamente.');
        }
        $placeholders = implode(',', array_fill(0, count($fileIds), '?'));
        $statement = Database::connection()->prepare(
            'SELECT file.id,file.material_id,file.original_name,file.relative_path,file.extension,
                    file.mime_type,file.size_bytes,file.created_by,file.deleted_at,file.deleted_by,file.purged_at,
                    creator.full_name created_by_name,remover.full_name deleted_by_name
             FROM support_material_files file
             LEFT JOIN users creator ON creator.id=file.created_by
             LEFT JOIN users remover ON remover.id=file.deleted_by
             WHERE file.id IN (' . $placeholders . ')' . ($lock ? ' FOR UPDATE' : '')
        );
        $statement->execute($fileIds);
        $rows = $statement->fetchAll();
        $byId = [];
        foreach ($rows as $row) $byId[(int) $row['id']] = $row;
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $files = [];
        foreach ($fileIds as $fileId) {
            $file = $byId[$fileId] ?? null;
            if (!$file) throw new InvalidArgumentException('Uno de los archivos solicitados no existe.');
            if ((int) $file['material_id'] !== $materialId) {
                throw new InvalidArgumentException('Uno de los archivos no pertenece a este material.');
            }
            if ($file['deleted_at'] === null) {
                throw new InvalidArgumentException('Solo pueden eliminarse definitivamente archivos retirados.');
            }
            if ($file['purged_at'] !== null) {
                throw new InvalidArgumentException('Uno de los archivos ya fue eliminado definitivamente.');
            }
            $deletedAt = new DateTimeImmutable((string) $file['deleted_at'], new DateTimeZone('UTC'));
            if ($deletedAt > $now || $deletedAt->modify('+' . self::RESTORE_HOURS . ' hours') <= $now) {
                throw new InvalidArgumentException('El plazo de restauración de uno de los archivos ya expiró.');
            }
            $path = $this->supportFilePath((string) $file['relative_path']);
            if (!is_file($path) || !is_readable($path)) {
                throw new InvalidArgumentException('El archivo físico ya no está disponible.');
            }
            $files[] = [
                'id' => $fileId,
                'name' => (string) $file['original_name'],
                'extension' => $this->resolveFileExtension($file),
                'size_bytes' => (int) $file['size_bytes'],
                'created_by' => $file['created_by'] === null ? null : (int) $file['created_by'],
                'created_by_name' => (string) ($file['created_by_name'] ?: 'Usuario no disponible'),
                'deleted_at' => $deletedAt->format(DATE_ATOM),
                'deleted_by' => $file['deleted_by'] === null ? null : (int) $file['deleted_by'],
                'deleted_by_name' => (string) ($file['deleted_by_name'] ?: 'Usuario no disponible'),
                'relative_path' => (string) $file['relative_path'],
                'absolute_path' => $path,
            ];
        }
        return $files;
    }

    public function markFilesPermanentlyDeleted(int $materialId, array $fileIds, int $actor): void
    {
        $fileIds = array_values(array_unique(array_map('intval', $fileIds)));
        $placeholders = implode(',', array_fill(0, count($fileIds), '?'));
        $statement = Database::connection()->prepare(
            'UPDATE support_material_files
             SET purged_at=UTC_TIMESTAMP(),purged_by=?
             WHERE material_id=? AND id IN (' . $placeholders . ')
               AND deleted_at IS NOT NULL AND purged_at IS NULL'
        );
        $statement->execute([$actor, $materialId, ...$fileIds]);
        if ($statement->rowCount() !== count($fileIds)) {
            throw new RuntimeException('No fue posible registrar la eliminación definitiva de todos los archivos.');
        }
    }

    public function expiredFilesPendingPurge(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        return Database::connection()->query(
            'SELECT id,material_id,original_name,relative_path,size_bytes,deleted_at,deleted_by
             FROM support_material_files
             WHERE deleted_at IS NOT NULL AND purged_at IS NULL
               AND deleted_at<=DATE_SUB(UTC_TIMESTAMP(),INTERVAL ' . self::RESTORE_HOURS . ' HOUR)
             ORDER BY deleted_at,id LIMIT ' . $limit
        )->fetchAll();
    }

    public function setPresentationFile(int $materialId, ?int $fileId, int $actor, ?int $expectedCurrentId = null): array
    {
        if ($materialId < 1 || ($fileId !== null && $fileId < 1)) {
            throw new InvalidArgumentException('El archivo seleccionado no es válido.');
        }
        $database = Database::connection();
        $material = $this->findByIdForUpdate($materialId);
        if ($material === null) throw new InvalidArgumentException('El material ya no está disponible.');
        $file = null;
        if ($fileId !== null) {
            $statement = $database->prepare(
                'SELECT id,original_name,extension,mime_type,is_package,deleted_at
                 FROM support_material_files
                 WHERE material_id=:material_id AND id=:file_id FOR UPDATE'
            );
            $statement->execute(['material_id' => $materialId, 'file_id' => $fileId]);
            $file = $statement->fetch();
            if (!$file || $file['deleted_at'] !== null || (int) $file['is_package'] === 1
                || !$this->isPreviewCompatible($file)) {
                throw new InvalidArgumentException(
                    'Selecciona un archivo activo y compatible con la vista previa.'
                );
            }
        }
        $previousId = isset($material['presentation_file_id'])
            ? (int) $material['presentation_file_id']
            : null;
        $previousName = null;
        if ($previousId) {
            $previousStatement = $database->prepare(
                'SELECT original_name FROM support_material_files WHERE id=:id AND material_id=:material_id'
            );
            $previousStatement->execute(['id'=>$previousId,'material_id'=>$materialId]);
            $previousName = $previousStatement->fetchColumn() ?: null;
        }
        if ($fileId === null) {
            if (!$previousId) {
                throw new InvalidArgumentException('La presentación ya había sido eliminada.');
            }
            if ($expectedCurrentId !== null && $previousId !== $expectedCurrentId) {
                throw new InvalidArgumentException('El archivo ya no es la presentación actual.');
            }
        }
        $database->prepare(
            'UPDATE support_materials
             SET presentation_file_id=:file_id,updated_by=:actor
             WHERE id=:material_id'
        )->execute(['file_id' => $fileId, 'actor' => $actor, 'material_id' => $materialId]);
        return [
            'previous_file_id' => $previousId ?: null,
            'file_id' => $fileId,
            'name' => $file === null ? null : (string) $file['original_name'],
            'previous_name' => $previousName,
            'new_name' => $file === null ? null : (string) $file['original_name'],
        ];
    }

    public function eligiblePresentationFiles(int $materialId, array $excludeIds = []): array
    {
        $material = $this->findById($materialId, true);
        if ($material === null) return [];
        $excluded = array_fill_keys(array_map('intval', $excludeIds), true);
        return array_values(array_filter(
            $material['files'],
            fn (array $file): bool => !isset($excluded[$file['id']])
                && !$file['package']
                && $this->isPreviewCompatible($file)
        ));
    }

    public function isPreviewCompatible(array $file): bool
    {
        return in_array($this->resolveFileExtension($file), self::PREVIEW_EXTENSIONS, true);
    }

    public function incrementDownloads(int $materialId): void
    {
        Database::connection()->prepare(
            "UPDATE support_materials SET download_count=download_count+1
             WHERE id=:id AND status='published'"
        )->execute(['id' => $materialId]);
    }

    private function listing(string $status): array
    {
        $statement = Database::connection()->prepare(
            $this->baseQuery() . ' WHERE sm.status=:status AND sm.deleted_at IS NULL AND sm.purged_at IS NULL ORDER BY sm.publication_date DESC,sm.id DESC'
        );
        $statement->execute(['status' => $status]);
        return array_map([$this, 'hydrate'], $statement->fetchAll());
    }

    private function baseQuery(): string
    {
        return "SELECT sm.*,category.slug category_slug,category.name category_label,
                period.name period_name
                FROM support_materials sm
                JOIN support_material_categories category ON category.id=sm.category_id
                LEFT JOIN academic_periods period ON period.id=sm.academic_period_id";
    }

    private function hydrate(array $material): array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM support_material_files
             WHERE material_id=:id AND deleted_at IS NULL
             ORDER BY is_package ASC,sort_order,id'
        );
        $statement->execute(['id' => $material['id']]);
        $presentationId = isset($material['presentation_file_id'])
            ? (int) $material['presentation_file_id']
            : 0;
        $fileService = new SupportMaterialFileService();
        $files = array_map(function (array $file) use ($presentationId, $fileService): array {
            $extension = $this->resolveFileExtension($file);
            $fallbackPath = ROOT_PATH . '/storage/support-materials/' . str_replace(
                ['/', '\\'],
                DIRECTORY_SEPARATOR,
                $file['relative_path']
            );
            $resolvedPath = $fileService->resolveRelativePath((string) $file['relative_path']);
            return [
                'id' => (int) $file['id'],
                'name' => $file['original_name'],
                'format' => $extension !== '' ? strtoupper($extension) : 'FILE',
                'path' => $resolvedPath ?? $fallbackPath,
                'available' => $resolvedPath !== null,
                'presentation' => (int) $file['id'] === $presentationId,
                'package' => (bool) $file['is_package'],
                'extension' => $extension,
                'mime_type' => strtolower((string) ($file['mime_type'] ?? '')),
                'size_bytes' => (int) $file['size_bytes'],
                'size' => ArchiveService::formatBytes((int) $file['size_bytes']),
                'sort_order' => (int) $file['sort_order'],
            ];
        }, $statement->fetchAll());

        $regularFiles = array_values(array_filter($files, static fn (array $file): bool => !$file['package']));
        $package = current(array_filter($files, static fn (array $file): bool => $file['package'])) ?: null;
        $presentation = current(array_filter(
            $regularFiles,
            static fn (array $file): bool => $file['presentation']
        )) ?: null;
        $publicationDateValue = trim((string) ($material['publication_date'] ?? ''));
        $date = new DateTimeImmutable($publicationDateValue !== '' ? $publicationDateValue : (string)($material['created_at'] ?? 'now'));
        $keywords = json_decode((string) ($material['keywords_json'] ?? '[]'), true);

        $material['id'] = (int) $material['id'];
        $material['type'] = $material['material_type'];
        $material['pao_label'] = $material['period_name'] ?: 'Sin período asociado';
        $material['year'] = $date->format('Y');
        $material['publication_date_iso'] = $publicationDateValue;
        $material['publication_date'] = $publicationDateValue !== '' ? $this->spanishDate($date) : 'Sin publicar';
        $material['status_key'] = $material['status'];
        $material['is_available'] = (bool) ($material['is_available'] ?? true);
        $material['published_at'] = $material['published_at'] ?? null;
        $material['status'] = match ($material['status']) {
            'published' => $material['is_available'] ? 'Disponible' : 'No disponible',
            'draft' => 'Borrador',
            default => 'Retirado',
        };
        $material['downloads'] = (int) $material['download_count'];
        $material['keywords'] = is_array($keywords) ? array_values($keywords) : [];
        $material['files'] = $regularFiles;
        $material['files_count'] = count($regularFiles);
        $material['presentation_file'] = $presentation;
        $material['additional_files'] = $regularFiles;
        $material['size_bytes'] = array_sum(array_column($regularFiles, 'size_bytes'));
        $material['size'] = ArchiveService::formatBytes($material['size_bytes']);
        if ($package !== null) $material['package'] = $package;
        return $material;
    }

    private function resolveFileExtension(array $file): string
    {
        $allowed = ['pdf','doc','docx','txt','zip','png','jpg','jpeg','webp','xls','xlsx','ppt','pptx'];
        $fromName = strtolower(pathinfo((string) ($file['original_name'] ?? ''), PATHINFO_EXTENSION));
        $stored = strtolower(ltrim((string) ($file['extension'] ?? ''), '.'));
        if ($fromName === 'jpeg') $fromName = 'jpg';
        if ($stored === 'jpeg') $stored = 'jpg';
        $mime = strtolower((string) ($file['mime_type'] ?? ''));
        $fromMime = match ($mime) {
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'text/plain' => 'txt',
            'application/zip', 'application/x-zip-compressed' => 'zip',
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default => '',
        };
        if (in_array($fromName, $allowed, true)) return $fromName;
        if ($fromMime !== '') return $fromMime;
        return in_array($stored, $allowed, true) ? $stored : '';
    }

    private function supportFilePath(string $relativePath): string
    {
        return ROOT_PATH . '/storage/support-materials/' . str_replace(
            ['/', '\\'],
            DIRECTORY_SEPARATOR,
            $relativePath
        );
    }

    private function storedSupportFileAvailable(string $relativePath): bool
    {
        $base = realpath(ROOT_PATH . '/storage/support-materials');
        $path = realpath($this->supportFilePath($relativePath));
        return $base !== false && $path !== false
            && str_starts_with(strtolower($path), strtolower($base . DIRECTORY_SEPARATOR))
            && is_file($path) && is_readable($path);
    }

    private function evolutionDate(string $date): string
    {
        try {
            return (new DateTimeImmutable($date, new DateTimeZone('UTC')))
                ->setTimezone(new DateTimeZone('America/Guayaquil'))
                ->format('d/m/Y H:i');
        } catch (Throwable) {
            return 'Fecha no disponible';
        }
    }

    private function normalizeRestoreName(string $name): string
    {
        return mb_strtolower(trim($name), 'UTF-8');
    }

    private function suggestRestoredName(string $originalName, array $activeFiles): string
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $base = pathinfo($originalName, PATHINFO_FILENAME);
        $activeNames = array_fill_keys(array_map(
            fn (array $file): string => $this->normalizeRestoreName((string) $file['original_name']),
            $activeFiles
        ), true);
        for ($number = 1; $number < 10000; $number++) {
            $suffix = $number === 1 ? ' (restaurado)' : ' (restaurado ' . $number . ')';
            $candidate = $base . $suffix . ($extension === '' ? '' : '.' . $extension);
            if (!isset($activeNames[$this->normalizeRestoreName($candidate)])) return $candidate;
        }
        throw new RuntimeException('No fue posible generar un nombre disponible para la restauración.');
    }

    private function restoreRemainingLabel(int $seconds): string
    {
        $totalMinutes = max(1, intdiv($seconds, 60));
        $hours = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;
        if ($hours > 0) {
            return $hours . ' h' . ($minutes > 0 ? ' ' . sprintf('%02d', $minutes) . ' min' : '');
        }
        return $minutes . ' min';
    }

    private function spanishDate(DateTimeImmutable $date): string
    {
        $months = [1=>'enero',2=>'febrero',3=>'marzo',4=>'abril',5=>'mayo',6=>'junio',
            7=>'julio',8=>'agosto',9=>'septiembre',10=>'octubre',11=>'noviembre',12=>'diciembre'];
        return (int) $date->format('j') . ' de ' . $months[(int) $date->format('n')]
            . ' de ' . $date->format('Y');
    }
}
