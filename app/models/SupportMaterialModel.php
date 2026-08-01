<?php

declare(strict_types=1);

final class SupportMaterialModel
{
    public const RESTORE_HOURS = 24;
    private const MAX_CLASSIFICATION_TAGS = 4;
    private const CATALOG_SETTINGS = [
        'material_type' => 'support_material_types',
        'keyword' => 'support_material_keywords',
    ];
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

    public function materialTypeCatalog(bool $activeOnly = true): array
    {
        return array_column($this->catalogEntries('material_type', $activeOnly), 'name');
    }

    public function keywordCatalog(bool $activeOnly = true): array
    {
        return array_column($this->catalogEntries('keyword', $activeOnly), 'name');
    }

    public function catalogEntries(string $kind, bool $activeOnly = false, ?PDO $db = null): array
    {
        $key = self::CATALOG_SETTINGS[$kind] ?? null;
        if (!$key) throw new InvalidArgumentException('El catálogo de materiales seleccionado no es válido.');
        $connection = $db ?? Database::connection();
        $statement = $connection->prepare('SELECT setting_value FROM system_settings WHERE setting_key=:key');
        $statement->execute(['key' => $key]);
        $decoded = json_decode((string) ($statement->fetchColumn() ?: '[]'), true);
        if (!is_array($decoded)) return [];
        $entries = [];
        foreach ($decoded as $entry) {
            if (!is_array($entry) || (int) ($entry['id'] ?? 0) < 1) continue;
            $name = $this->compactCatalogText((string) ($entry['name'] ?? ''));
            if ($name === '') continue;
            $active = !empty($entry['is_active']);
            if ($activeOnly && !$active) continue;
            $entries[] = [
                'id' => (int) $entry['id'],
                'name' => $name,
                'is_active' => $active ? 1 : 0,
                'aliases' => array_values(array_unique(array_filter(array_map(
                    fn (mixed $alias): string => $this->compactCatalogText((string) $alias),
                    (array) ($entry['aliases'] ?? [])
                )))),
            ];
        }
        return $entries;
    }

    public function administrativeCatalog(string $kind, ?PDO $db = null): array
    {
        $connection = $db ?? Database::connection();
        $entries = $this->catalogEntries($kind, false, $connection);
        $materials = $connection->query(
            'SELECT material_type,keywords_json FROM support_materials
             WHERE deleted_at IS NULL AND purged_at IS NULL'
        )->fetchAll();
        foreach ($entries as &$entry) {
            $accepted = array_fill_keys(array_map(
                fn (string $name): string => $this->keywordKey($name),
                array_merge([$entry['name']], $entry['aliases'])
            ), true);
            $associated = 0;
            foreach ($materials as $material) {
                if ($kind === 'material_type') {
                    if (isset($accepted[$this->keywordKey((string) $material['material_type'])])) $associated++;
                    continue;
                }
                $keywords = json_decode((string) ($material['keywords_json'] ?? '[]'), true);
                if (!is_array($keywords)) continue;
                foreach ($keywords as $keyword) {
                    if (!isset($accepted[$this->keywordKey((string) $keyword)])) continue;
                    $associated++;
                    break;
                }
            }
            $entry['materials'] = $associated;
        }
        unset($entry);
        usort($entries, static function (array $left, array $right): int {
            $activeOrder = (int) $right['is_active'] <=> (int) $left['is_active'];
            return $activeOrder !== 0 ? $activeOrder : strnatcasecmp((string) $left['name'], (string) $right['name']);
        });
        return $entries;
    }

    public function mutateCatalog(PDO $db, string $kind, array $values, int $actor): array
    {
        $settingKey = self::CATALOG_SETTINGS[$kind] ?? null;
        if (!$settingKey) throw new InvalidArgumentException('El catálogo de materiales seleccionado no es válido.');
        $id = (int) ($values['id'] ?? 0);
        $action = (string) ($values['action'] ?? 'save');
        $statement = $db->prepare('SELECT setting_value FROM system_settings WHERE setting_key=:key FOR UPDATE');
        $statement->execute(['key' => $settingKey]);
        $stored = json_decode((string) ($statement->fetchColumn() ?: '[]'), true);
        $entries = is_array($stored) ? array_values(array_filter($stored, 'is_array')) : [];
        $index = null;
        foreach ($entries as $position => $entry) if ((int) ($entry['id'] ?? 0) === $id) $index = $position;
        if ($action !== 'save' && $index === null) throw new InvalidArgumentException('El registro seleccionado ya no está disponible.');
        $before = $index === null ? null : $entries[$index];
        $resultName = (string) ($before['name'] ?? '');

        if ($action === 'delete') {
            $catalog = $this->administrativeCatalog($kind, $db);
            $selected = array_values(array_filter($catalog, static fn (array $entry): bool => (int) $entry['id'] === $id))[0] ?? null;
            if (!$selected) throw new InvalidArgumentException('El registro seleccionado ya no está disponible.');
            if ((int) $selected['materials'] > 0) {
                throw new InvalidArgumentException('No puede eliminarse porque tiene elementos asociados.');
            }
            array_splice($entries, $index, 1);
        } elseif (in_array($action, ['activate', 'deactivate'], true)) {
            $entries[$index]['is_active'] = $action === 'activate';
        } else {
            $name = $this->compactCatalogText((string) ($values['name'] ?? ''));
            $maximum = $kind === 'material_type' ? 100 : 120;
            if (mb_strlen($name) < 2 || mb_strlen($name) > $maximum) {
                throw new InvalidArgumentException($kind === 'material_type'
                    ? 'Ingresa un tipo de material válido.'
                    : 'Ingresa una palabra clave válida.');
            }
            $normalized = $this->keywordKey($name);
            foreach ($entries as $position => $entry) {
                if ($position === $index) continue;
                $names = array_merge([(string) ($entry['name'] ?? '')], (array) ($entry['aliases'] ?? []));
                foreach ($names as $candidate) {
                    if ($this->keywordKey((string) $candidate) === $normalized) {
                        throw new InvalidArgumentException('Ya existe un registro equivalente en este catálogo.');
                    }
                }
            }
            if ($index === null) {
                $id = 1 + max([0, ...array_map(static fn (array $entry): int => (int) ($entry['id'] ?? 0), $entries)]);
                $entries[] = ['id' => $id, 'name' => $name, 'is_active' => true, 'aliases' => []];
            } else {
                $oldName = $this->compactCatalogText((string) ($entries[$index]['name'] ?? ''));
                $aliases = (array) ($entries[$index]['aliases'] ?? []);
                if ($oldName !== '' && $this->keywordKey($oldName) !== $normalized) $aliases[] = $oldName;
                $entries[$index]['name'] = $name;
                $entries[$index]['aliases'] = array_values(array_unique($aliases));
            }
            $resultName = $name;
        }
        $update = $db->prepare(
            'UPDATE system_settings SET setting_value=:value,updated_by=:actor WHERE setting_key=:key'
        );
        $update->execute([
            'value' => json_encode(array_values($entries), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'actor' => $actor,
            'key' => $settingKey,
        ]);
        return [
            'id' => $id,
            'name' => $resultName,
            'previous' => $before,
        ];
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

    public function resolveMaterialType(array $input, bool $controlled = false): string
    {
        if (!$controlled) {
            return trim((string) preg_replace('/\s+/u', ' ', (string) ($input['material_type'] ?? '')));
        }
        $choice = trim((string) ($input['material_type_choice'] ?? ''));
        $predefined = $this->materialTypeCatalog();
        if (in_array($choice, $predefined, true)) return $choice;
        if ($choice !== 'Otros') {
            throw new InvalidArgumentException('Selecciona un tipo de material válido.');
        }
        $custom = trim((string) preg_replace('/\s+/u', ' ', (string) ($input['material_type_custom'] ?? '')));
        if ($custom === '') {
            throw new InvalidArgumentException('Especifica el tipo de material.');
        }
        if (mb_strlen($custom) > 100) {
            throw new InvalidArgumentException('El tipo de material personalizado no puede superar 100 caracteres.');
        }
        return $custom;
    }

    public function normalizeExistingKeywords(array $keywords): array
    {
        $catalog = $this->keywordCatalogMap();
        $normalized = [];
        $seen = [];
        foreach ($keywords as $keyword) {
            $display = trim((string) preg_replace('/\s+/u', ' ', (string) $keyword));
            if ($display === '') continue;
            $key = $this->keywordKey($display);
            if ($key === 'perfil') $key = $this->keywordKey('Perfil de tesis');
            $display = $catalog[$key] ?? $display;
            $dedupeKey = $this->keywordKey($display);
            if (isset($seen[$dedupeKey])) continue;
            $seen[$dedupeKey] = true;
            $normalized[] = $display;
        }
        return $normalized;
    }

    public function resolveKeywords(array $input, array $existing = [], bool $controlled = false): array
    {
        if (!$controlled) {
            $keywords = array_map(
                static fn (string $keyword): string => trim((string) preg_replace('/\s+/u', ' ', $keyword)),
                preg_split('/[,;\n]+/u', (string) ($input['keywords'] ?? '')) ?: []
            );
            $resolved = array_values(array_unique(array_filter($keywords, static fn (string $keyword): bool => $keyword !== '')));
            if (count($resolved) > self::MAX_CLASSIFICATION_TAGS) {
                throw new InvalidArgumentException('Máximo 4 etiquetas de clasificación.');
            }
            return $resolved;
        }
        $submitted = $input['keywords_selected'] ?? [];
        if (!is_array($submitted)) {
            throw new InvalidArgumentException('La selección de clasificación no es válida.');
        }
        $existingNormalized = $this->normalizeExistingKeywords($existing);
        $catalog = $this->keywordCatalogMap();
        $legacy = [];
        foreach ($existingNormalized as $keyword) {
            $key = $this->keywordKey($keyword);
            if (!isset($catalog[$key])) $legacy[$key] = $keyword;
        }
        $resolved = [];
        $seen = [];
        foreach ($submitted as $keyword) {
            $key = $this->keywordKey((string) $keyword);
            if ($key === 'perfil') $key = $this->keywordKey('Perfil de tesis');
            $display = $catalog[$key] ?? $legacy[$key] ?? null;
            if ($display === null) {
                throw new InvalidArgumentException('La selección contiene una etiqueta de clasificación no permitida.');
            }
            $dedupeKey = $this->keywordKey($display);
            if (isset($seen[$dedupeKey])) continue;
            $seen[$dedupeKey] = true;
            $resolved[] = $display;
        }
        if (count($resolved) > self::MAX_CLASSIFICATION_TAGS) {
            throw new InvalidArgumentException('Máximo 4 etiquetas de clasificación.');
        }
        return $resolved;
    }

    public function lastInformationUpdateAt(int $materialId): ?string
    {
        if ($materialId < 1) return null;
        $statement = Database::connection()->prepare(
            "SELECT MAX(created_at)
             FROM admin_audit_log
             WHERE entity_type='support_material' AND entity_id=:id
               AND module='Repositorio' AND result='correct'
               AND action IN ('support_material.updated','support_material_updated')"
        );
        $statement->execute(['id' => $materialId]);
        $updatedAt = $statement->fetchColumn();
        return $updatedAt === false || $updatedAt === null || trim((string) $updatedAt) === ''
            ? null
            : (string) $updatedAt;
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
        $controlledType = $id > 0 && (string) ($input['controlled_material_type'] ?? '') === '1';
        $controlledKeywords = $id > 0 && (string) ($input['controlled_keywords'] ?? '') === '1';
        $type = $this->resolveMaterialType($input, $controlledType);
        $description = trim((string) ($input['description'] ?? ''));
        $fullDescription = trim((string) ($input['full_description'] ?? ''));
        $publisher = trim((string) ($input['publisher'] ?? ''));
        $categoryId = (int) ($input['category_id'] ?? 0);
        $publicationDate = null;
        $keywords = [];

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
        $database = Database::connection();
        if ($id > 0) {
            $storedPublisher = $database->prepare(
                'SELECT publisher,keywords_json FROM support_materials
                 WHERE id=:id AND deleted_at IS NULL AND purged_at IS NULL'
            );
            $storedPublisher->execute(['id' => $id]);
            $storedMaterial = $storedPublisher->fetch();
            if (!$storedMaterial) {
                throw new InvalidArgumentException('El material ya no está disponible.');
            }
            $publisher = (string) $storedMaterial['publisher'];
            $existingKeywords = json_decode((string) ($storedMaterial['keywords_json'] ?? '[]'), true);
            $keywords = $this->resolveKeywords(
                $input,
                is_array($existingKeywords) ? $existingKeywords : [],
                $controlledKeywords
            );
        } else {
            $keywords = $this->resolveKeywords($input);
        }
        if ($publisher === '' || mb_strlen($publisher) > 180) {
            throw new InvalidArgumentException('Ingresa el responsable de la publicación.');
        }
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
            $payload['id'] = $id;
            $updatePayload = $payload;
            unset($updatePayload['publication_date'], $updatePayload['publisher']);
            $statement = $database->prepare(
                'UPDATE support_materials SET
                 category_id=:category_id,title=:title,material_type=:material_type,
                 description=:description,full_description=:full_description,
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
        $targetAvailability = $status === 'published';
        if ((string) $material['status'] === $status
            && (bool) $material['is_available'] === $targetAvailability) {
            throw new InvalidArgumentException($status === 'published'
                ? 'El material ya está publicado.'
                : 'El material ya está retirado.');
        }
        Database::connection()->prepare(
            "UPDATE support_materials SET status=:status,
             is_available=:is_available,
             publication_date=IF(:status_for_publication_date='published',COALESCE(publication_date,UTC_DATE()),publication_date),
             published_at=IF(:status_for_publication='published',COALESCE(published_at,UTC_TIMESTAMP()),published_at),
             withdrawn_at=IF(:status_for_date='withdrawn',CURRENT_TIMESTAMP,NULL),
             withdrawn_by=IF(:status_for_actor='withdrawn',:withdrawn_actor,NULL),
             updated_by=:updated_actor
             WHERE id=:id"
        )->execute([
            'status' => $status,
            'is_available' => $targetAvailability ? 1 : 0,
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
            'new_status' => $status,
            'previous_available' => (bool) $material['is_available'],
            'is_available' => $targetAvailability,
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
              size_bytes,sha256,is_package,sort_order,created_by)
             VALUES
             (:material_id,:original_name,:storage_name,:relative_path,:extension,:mime_type,
              :size_bytes,:sha256,0,
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
            'sha256' => $this->normalizedSha256($file['sha256'] ?? null),
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
        $currentSha256 = $this->normalizedSha256($current['sha256'] ?? null)
            ?? $this->storedFileSha256((string) $current['relative_path']);
        if ($currentSha256 === null) {
            throw new RuntimeException('No fue posible verificar la integridad del archivo anterior.');
        }
        $replacementSha256 = $this->normalizedSha256($replacement['sha256'] ?? null)
            ?? $this->storedFileSha256((string) ($replacement['relative_path'] ?? ''));
        if ($replacementSha256 === null) {
            throw new RuntimeException('No fue posible verificar la integridad del archivo nuevo.');
        }
        $nextVersionStatement = $database->prepare(
            'SELECT COALESCE(MAX(version_number),0)+1
             FROM support_material_file_versions
             WHERE file_id=:file_id AND material_id=:material_id'
        );
        $nextVersionStatement->execute(['file_id' => $fileId, 'material_id' => $materialId]);
        $versionNumber = (int) $nextVersionStatement->fetchColumn();
        if ($versionNumber < 1) {
            throw new RuntimeException('No fue posible asignar el número de versión.');
        }
        $database->prepare(
            'INSERT INTO support_material_file_versions
             (file_id,material_id,version_number,original_name,storage_name,relative_path,extension,mime_type,size_bytes,sha256,replaced_by)
             VALUES
             (:file_id,:material_id,:version_number,:original_name,:storage_name,:relative_path,:extension,:mime_type,:size_bytes,:sha256,:actor)'
        )->execute([
            'file_id' => $fileId,
            'material_id' => $materialId,
            'version_number' => $versionNumber,
            'original_name' => $current['original_name'],
            'storage_name' => $current['storage_name'],
            'relative_path' => $current['relative_path'],
            'extension' => $current['extension'],
            'mime_type' => $current['mime_type'],
            'size_bytes' => $current['size_bytes'],
            'sha256' => $currentSha256,
            'actor' => $actor,
        ]);
        $versionId = (int) $database->lastInsertId();
        $database->prepare(
            'UPDATE support_material_files
             SET original_name=:original_name,storage_name=:storage_name,
                 relative_path=:relative_path,extension=:extension,
                 mime_type=:mime_type,size_bytes=:size_bytes,sha256=:sha256
             WHERE id=:file_id AND material_id=:material_id AND deleted_at IS NULL'
        )->execute([
            'original_name' => $replacement['original_name'],
            'storage_name' => $replacement['storage_name'],
            'relative_path' => $replacement['relative_path'],
            'extension' => $replacement['extension'],
            'mime_type' => $replacement['mime_type'],
            'size_bytes' => $replacement['size_bytes'],
            'sha256' => $replacementSha256,
            'file_id' => $fileId,
            'material_id' => $materialId,
        ]);
        return [
            'file_id' => $fileId,
            'version_id' => $versionId,
            'version_number' => $versionNumber,
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

    public function documentEvolution(int $materialId, array $fileIds = []): array
    {
        if ($materialId < 1) return [];
        $fileIds = array_values(array_unique(array_filter(array_map('intval', $fileIds), static fn (int $id): bool => $id > 0)));
        $fileFilter = $fileIds ? ' AND file.id IN (' . implode(',', array_fill(0, count($fileIds), '?')) . ')' : '';
        $statement = Database::connection()->prepare(
            'SELECT file.id file_id,file.material_id,file.original_name current_name,
                    file.relative_path current_path,file.extension current_extension,
                    file.mime_type current_mime,file.size_bytes current_size,
                    file.created_at,file.created_by,file.deleted_at,file.purged_at,
                    creator.full_name created_by_name,
                    version.id version_id,version.version_number,version.original_name version_name,
                    version.relative_path version_path,version.extension version_extension,
                    version.mime_type version_mime,version.size_bytes version_size,
                    version.replaced_at,version.replaced_by,replacer.full_name replaced_by_name
             FROM support_material_files file
             JOIN support_material_file_versions version
               ON version.file_id=file.id AND version.material_id=file.material_id
             LEFT JOIN users creator ON creator.id=file.created_by
             LEFT JOIN users replacer ON replacer.id=version.replaced_by
             WHERE file.material_id=?' . $fileFilter . '
             ORDER BY file.sort_order,file.id,version.version_number'
        );
        $statement->execute([$materialId, ...$fileIds]);
        $rows = $statement->fetchAll();
        if (!$rows) return [];

        $auditStatement = Database::connection()->prepare(
            "SELECT audit.id,audit.actor_user_id,audit.created_at,audit.details,
                    actor.full_name actor_name
             FROM admin_audit_log
             audit LEFT JOIN users actor ON actor.id=audit.actor_user_id
             WHERE audit.action='support_material.file_replaced'
               AND audit.entity_type='support_material' AND audit.entity_id=?" . ($fileIds
                   ? " AND CAST(JSON_UNQUOTE(JSON_EXTRACT(audit.details,'$.file_id')) AS UNSIGNED) IN (" . implode(',', array_fill(0, count($fileIds), '?')) . ')'
                   : '') . "
             ORDER BY audit.created_at,audit.id"
        );
        $auditStatement->execute([$materialId, ...$fileIds]);
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
                    'number' => (int) $row['version_number'],
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
                    'state' => $available ? 'available' : 'deleted',
                    'preview_supported' => $available && $this->isPreviewCompatible([
                        'original_name' => $row['version_name'],
                        'extension' => $row['version_extension'],
                        'mime_type' => $row['version_mime'],
                    ]),
                    'replacement_audit_id' => $audit === null ? null : (int) $audit['id'],
                ];
            }
            $last = $history[count($history) - 1];
            $currentNumber = max(array_map(
                static fn (array $item): int => (int) $item['version_number'],
                $history
            )) + 1;
            $currentAvailable = $last['deleted_at'] === null && $last['purged_at'] === null
                && $this->storedSupportFileAvailable((string) $last['current_path']);
            $currentState = $last['purged_at'] !== null || !$this->storedSupportFileAvailable((string) $last['current_path'])
                ? 'deleted' : ($last['deleted_at'] !== null ? 'unavailable' : 'available');
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
                'state' => $currentState,
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

    /** Cambios estrictamente documentales; excluye consultas y actividad administrativa no documental. */
    public function documentEvolutionEvents(int $materialId, int $limit = 0, int $offset = 0): array
    {
        if ($materialId < 1) return [];
        $actions = [
            'support_material.file_added', 'support_material.file_replaced',
            'support_material.file_removed', 'support_material.file_restored',
        ];
        $placeholders = implode(',', array_fill(0, count($actions), '?'));
        $limit = max(0, min(16, $limit));
        $offset = max(0, $offset);
        $pagination = $limit > 0 ? ' LIMIT ' . $limit . ' OFFSET ' . $offset : '';
        $statement = Database::connection()->prepare(
            "SELECT audit.id,audit.action,audit.action_label,audit.element_label,audit.details,audit.created_at,
                    actor.full_name actor_name
             FROM admin_audit_log audit
             LEFT JOIN users actor ON actor.id=audit.actor_user_id
             WHERE audit.entity_type='support_material' AND audit.entity_id=?
               AND audit.result='correct' AND audit.action IN ({$placeholders})
             ORDER BY audit.created_at ASC,audit.id ASC" . $pagination
        );
        $statement->execute([$materialId, ...$actions]);
        $events = [];
        foreach ($statement->fetchAll() as $row) {
            $details = json_decode((string) ($row['details'] ?? ''), true);
            $details = is_array($details) ? $details : [];
            $action = (string) $row['action'];
            $type = match ($action) {
                'support_material.file_added' => 'file-added',
                'support_material.file_replaced' => 'file-replaced',
                'support_material.file_removed' => 'file-removed',
                'support_material.file_restored' => 'file-restored',
                'support_material.presentation_removed' => 'presentation-removed',
                default => 'presentation-changed',
            };
            $title = match ($type) {
                'file-added' => 'Archivo agregado', 'file-replaced' => 'Archivo reemplazado · nueva versión',
                'file-removed' => 'Archivo retirado', 'file-restored' => 'Archivo restaurado',
                'presentation-removed' => 'Archivo de presentación quitado',
                default => 'Archivo de presentación cambiado',
            };
            $fileName = match ($type) {
                'file-replaced' => (string) ($details['new_file']['original_name'] ?? $details['new_file']['name'] ?? $row['element_label'] ?? ''),
                'file-restored' => (string) ($details['final_name'] ?? $details['original_name'] ?? $row['element_label'] ?? ''),
                'presentation-changed' => (string) ($details['new_name'] ?? $row['element_label'] ?? ''),
                'presentation-removed' => (string) ($details['previous_name'] ?? $row['element_label'] ?? ''),
                default => (string) ($details['name'] ?? $details['original_name'] ?? $details['file_name'] ?? $row['element_label'] ?? ''),
            };
            $events[] = [
                'id' => (int) $row['id'], 'type' => $type, 'title' => $title,
                'date' => (string) $row['created_at'],
                'responsible' => trim((string) ($row['actor_name'] ?? '')) ?: 'Responsable no disponible',
                'file_id' => (int) ($details['file_id'] ?? 0),
                'version_id' => (int) ($details['version_id'] ?? 0),
                'file_name' => trim($fileName),
                'previous_name' => trim((string) ($details['previous_name'] ?? $details['previous_file']['original_name'] ?? '')),
                'new_name' => trim((string) ($details['new_name'] ?? $details['new_file']['original_name'] ?? '')),
            ];
        }
        $fileIds = array_values(array_unique(array_filter(array_column($events, 'file_id'))));
        if ($fileIds) {
            $files = Database::connection()->prepare(
                'SELECT id,original_name,relative_path,extension,mime_type,size_bytes,deleted_at,purged_at
                 FROM support_material_files WHERE material_id=? AND id IN (' . implode(',', array_fill(0, count($fileIds), '?')) . ')'
            );
            $files->execute([$materialId, ...$fileIds]);
            $byId = [];
            foreach ($files->fetchAll() as $file) $byId[(int) $file['id']] = $file;
            foreach ($events as &$event) {
                $file = $byId[(int) ($event['file_id'] ?? 0)] ?? null;
                if (!$file) continue;
                $physical = $this->storedSupportFileAvailable((string) $file['relative_path']);
                $event['file_name'] = $event['file_name'] ?: (string) $file['original_name'];
                $event['extension'] = $this->resolveFileExtension($file);
                $event['size'] = ArchiveService::formatBytes((int) $file['size_bytes']);
                $event['file_state'] = !empty($file['purged_at']) || !$physical
                    ? 'deleted' : (!empty($file['deleted_at']) ? 'unavailable' : 'available');
            }
            unset($event);
        }
        return $events;
    }

    public function documentEvolutionEventCount(int $materialId): int
    {
        if ($materialId < 1) return 0;
        $statement = Database::connection()->prepare(
            "SELECT COUNT(*) FROM admin_audit_log
             WHERE entity_type='support_material' AND entity_id=:material_id AND result='correct'
               AND action IN ('support_material.file_added','support_material.file_replaced',
                   'support_material.file_removed','support_material.file_restored')"
        );
        $statement->execute(['material_id' => $materialId]);
        return (int) $statement->fetchColumn();
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

    private function normalizedSha256(mixed $value): ?string
    {
        if (!is_string($value)) return null;
        $hash = strtolower(trim($value));
        return preg_match('/^[a-f0-9]{64}$/', $hash) === 1 ? $hash : null;
    }

    private function storedFileSha256(string $relativePath): ?string
    {
        $path = (new SupportMaterialFileService())->resolveRelativePath($relativePath);
        if ($path === null) return null;
        $hash = hash_file('sha256', $path);
        return is_string($hash) && preg_match('/^[a-f0-9]{64}$/', $hash) === 1
            ? strtolower($hash)
            : null;
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

    private function keywordCatalogMap(): array
    {
        $map = [];
        foreach ($this->catalogEntries('keyword', true) as $entry) {
            $map[$this->keywordKey($entry['name'])] = $entry['name'];
            foreach ($entry['aliases'] as $alias) $map[$this->keywordKey($alias)] = $entry['name'];
        }
        return $map;
    }

    private function compactCatalogText(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', trim($value)));
    }

    private function keywordKey(string $value): string
    {
        $normalized = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $value)), 'UTF-8');
        if (class_exists('Normalizer')) {
            $decomposed = Normalizer::normalize($normalized, Normalizer::FORM_D);
            if (is_string($decomposed)) $normalized = (string) preg_replace('/\p{Mn}+/u', '', $decomposed);
        }
        return $normalized;
    }
}
