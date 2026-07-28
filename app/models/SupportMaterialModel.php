<?php

declare(strict_types=1);

final class SupportMaterialModel
{
    private const PREVIEW_EXTENSIONS = ['pdf','docx','txt','png','jpg','jpeg','webp'];

    public function getAll(): array
    {
        return $this->listing('published');
    }

    public function getWithdrawn(): array
    {
        return $this->listing('withdrawn');
    }

    public function getAdminMaterials(): array
    {
        $statement = Database::connection()->query(
            $this->baseQuery()
            . " WHERE sm.status IN ('draft','published') ORDER BY sm.publication_date DESC,sm.id DESC"
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
        $statement = Database::connection()->prepare($this->baseQuery() . " WHERE sm.id=:id{$where}");
        $statement->execute(['id' => $materialId]);
        $row = $statement->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function findByIdForUpdate(int $materialId): ?array
    {
        if ($materialId < 1) return null;
        $statement = Database::connection()->prepare($this->baseQuery() . ' WHERE sm.id=:id FOR UPDATE');
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
        $publicationDate = trim((string) ($input['publication_date'] ?? ''));
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
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $publicationDate);
        if (!$date || $date->format('Y-m-d') !== $publicationDate) {
            throw new InvalidArgumentException('La fecha de publicación no es válida.');
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
            $statement = $database->prepare(
                'UPDATE support_materials SET
                 category_id=:category_id,title=:title,material_type=:material_type,
                 description=:description,full_description=:full_description,
                 publisher=:publisher,publication_date=:publication_date,
                 keywords_json=:keywords_json,updated_by=:updated_by
                 WHERE id=:id'
            );
            $statement->execute($payload);
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

    public function setStatus(int $id, string $status, int $actor): void
    {
        if (!in_array($status, ['published', 'withdrawn'], true)) {
            throw new InvalidArgumentException('El estado solicitado no es válido.');
        }
        $material = $this->findById($id, true);
        if ($material === null) {
            throw new InvalidArgumentException('El material ya no está disponible.');
        }
        Database::connection()->prepare(
            "UPDATE support_materials SET status=:status,
             withdrawn_at=IF(:status_for_date='withdrawn',CURRENT_TIMESTAMP,NULL),
             withdrawn_by=IF(:status_for_actor='withdrawn',:withdrawn_actor,NULL),
             updated_by=:updated_actor
             WHERE id=:id"
        )->execute([
            'status' => $status,
            'status_for_date' => $status,
            'status_for_actor' => $status,
            'withdrawn_actor' => $actor,
            'updated_actor' => $actor,
            'id' => $id,
        ]);
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
            $this->baseQuery() . ' WHERE sm.status=:status ORDER BY sm.publication_date DESC,sm.id DESC'
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
        $files = array_map(function (array $file) use ($presentationId): array {
            $extension = $this->resolveFileExtension($file);
            $path = ROOT_PATH . '/storage/support-materials/' . str_replace(
                ['/', '\\'],
                DIRECTORY_SEPARATOR,
                $file['relative_path']
            );
            return [
                'id' => (int) $file['id'],
                'name' => $file['original_name'],
                'format' => $extension !== '' ? strtoupper($extension) : 'FILE',
                'path' => $path,
                'presentation' => (int) $file['id'] === $presentationId,
                'package' => (bool) $file['is_package'],
                'extension' => $extension,
                'mime_type' => strtolower((string) ($file['mime_type'] ?? '')),
                'size_bytes' => (int) $file['size_bytes'],
                'size' => ArchiveService::formatBytes((int) $file['size_bytes']),
            ];
        }, $statement->fetchAll());

        $regularFiles = array_values(array_filter($files, static fn (array $file): bool => !$file['package']));
        $package = current(array_filter($files, static fn (array $file): bool => $file['package'])) ?: null;
        $presentation = current(array_filter(
            $regularFiles,
            static fn (array $file): bool => $file['presentation']
        )) ?: null;
        $date = new DateTimeImmutable($material['publication_date']);
        $keywords = json_decode((string) ($material['keywords_json'] ?? '[]'), true);

        $material['id'] = (int) $material['id'];
        $material['type'] = $material['material_type'];
        $material['pao_label'] = $material['period_name'] ?: 'Sin período asociado';
        $material['year'] = $date->format('Y');
        $material['publication_date_iso'] = $date->format('Y-m-d');
        $material['publication_date'] = $this->spanishDate($date);
        $material['status_key'] = $material['status'];
        $material['status'] = match ($material['status']) {
            'published' => 'Disponible',
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

    private function spanishDate(DateTimeImmutable $date): string
    {
        $months = [1=>'enero',2=>'febrero',3=>'marzo',4=>'abril',5=>'mayo',6=>'junio',
            7=>'julio',8=>'agosto',9=>'septiembre',10=>'octubre',11=>'noviembre',12=>'diciembre'];
        return (int) $date->format('j') . ' de ' . $months[(int) $date->format('n')]
            . ' de ' . $date->format('Y');
    }
}
