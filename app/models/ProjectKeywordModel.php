<?php

declare(strict_types=1);

/** Catálogo y asociaciones estructurales de palabras clave de proyectos. */
final class ProjectKeywordModel
{
    public const MAX_PER_PROJECT = 4;

    public function create(PDO $db, string $name): int
    {
        $displayName = preg_replace('/\s+/u', ' ', trim($name)) ?? '';
        $normalizedName = $this->normalizeName($displayName);
        if (mb_strlen($displayName, 'UTF-8') < 2 || mb_strlen($displayName, 'UTF-8') > 120) {
            throw new InvalidArgumentException('La palabra clave debe tener entre 2 y 120 caracteres.');
        }
        try {
            $statement = $db->prepare('INSERT INTO keywords(name,normalized_name) VALUES(:name,:normalized_name)');
            $statement->execute(['name' => $displayName, 'normalized_name' => $normalizedName]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') throw new InvalidArgumentException('Ya existe una palabra clave equivalente.');
            throw $exception;
        }
        return (int) $db->lastInsertId();
    }

    public function listActive(?PDO $db = null): array
    {
        $connection = $db ?? Database::connection();
        return $connection->query('SELECT id,name FROM keywords WHERE is_active=1 ORDER BY name')->fetchAll();
    }

    public function forProject(int $projectId, ?PDO $db = null): array
    {
        if ($projectId < 1) return [];
        $connection = $db ?? Database::connection();
        $statement = $connection->prepare(
            'SELECT k.id,k.name,k.is_active
             FROM project_keywords pk
             INNER JOIN keywords k ON k.id=pk.keyword_id
             WHERE pk.project_id=:project_id AND k.is_active=1
             ORDER BY k.name
             LIMIT ' . self::MAX_PER_PROJECT
        );
        $statement->execute(['project_id' => $projectId]);
        return $statement->fetchAll();
    }

    public function attach(PDO $db, int $projectId, array $keywordIds): void
    {
        $keywordIds = $this->validatedActiveIds($db, $keywordIds);
        if (!$keywordIds) return;
        $existing = array_map('intval', array_column($this->forProject($projectId, $db), 'id'));
        if (count(array_unique(array_merge($existing, $keywordIds))) > self::MAX_PER_PROJECT) {
            throw new InvalidArgumentException('Un proyecto puede tener como máximo cuatro palabras clave.');
        }
        $insert = $db->prepare('INSERT IGNORE INTO project_keywords(project_id,keyword_id) VALUES(:project_id,:keyword_id)');
        foreach ($keywordIds as $keywordId) $insert->execute(['project_id' => $projectId, 'keyword_id' => $keywordId]);
    }

    public function replace(PDO $db, int $projectId, array $keywordIds): void
    {
        if ($projectId < 1) throw new InvalidArgumentException('El proyecto no es válido.');
        $keywordIds = $this->validatedActiveIds($db, $keywordIds);
        if (count($keywordIds) > self::MAX_PER_PROJECT) throw new InvalidArgumentException('Un proyecto puede tener como máximo cuatro palabras clave.');
        $db->prepare('DELETE FROM project_keywords WHERE project_id=:project_id')->execute(['project_id' => $projectId]);
        $this->attach($db, $projectId, $keywordIds);
    }

    /**
     * Sincroniza únicamente las asociaciones agregadas o retiradas.
     *
     * @return array{changed:bool,before:array,after:array,inserted:array,deleted:array}
     */
    public function syncDifferential(PDO $db, int $projectId, array $submittedNames, array $allowedNames): array
    {
        if ($projectId < 1) throw new InvalidArgumentException('El proyecto no es válido.');
        $allowed = [];
        foreach ($allowedNames as $name) {
            $display = preg_replace('/\s+/u', ' ', trim((string) $name)) ?? '';
            if ($display !== '') $allowed[$this->normalizeName($display)] = $display;
        }
        $current = $this->forProject($projectId, $db);
        $currentIds = array_map('intval', array_column($current, 'id'));
        $currentNames = array_values(array_map('strval', array_column($current, 'name')));
        foreach ($currentNames as $name) $allowed[$this->normalizeName($name)] = $name;
        $requested = [];
        foreach ($submittedNames as $name) {
            $key = $this->normalizeName((string) $name);
            if ($key === '') continue;
            if (!isset($allowed[$key])) throw new InvalidArgumentException('La selección contiene una etiqueta de clasificación no permitida.');
            $requested[$key] = $allowed[$key];
        }
        if (count($requested) > self::MAX_PER_PROJECT) throw new InvalidArgumentException('Un proyecto puede tener como máximo cuatro palabras clave.');

        $selectedIds = [];
        $selectedNames = [];
        $find = $db->prepare('SELECT id,name FROM keywords WHERE normalized_name=:normalized_name AND is_active=1 LIMIT 1');
        foreach ($requested as $key => $display) {
            $find->execute(['normalized_name' => $key]);
            $keyword = $find->fetch();
            $keywordId = $keyword ? (int) $keyword['id'] : $this->create($db, $display);
            $selectedIds[] = $keywordId;
            $selectedNames[] = $keyword ? (string) $keyword['name'] : $display;
        }
        $inserted = array_values(array_diff($selectedIds, $currentIds));
        $deleted = array_values(array_diff($currentIds, $selectedIds));
        if ($deleted) {
            $placeholders = implode(',', array_fill(0, count($deleted), '?'));
            $delete = $db->prepare("DELETE FROM project_keywords WHERE project_id=? AND keyword_id IN ($placeholders)");
            $delete->execute(array_merge([$projectId], $deleted));
        }
        if ($inserted) {
            $insert = $db->prepare('INSERT IGNORE INTO project_keywords(project_id,keyword_id) VALUES(:project_id,:keyword_id)');
            foreach ($inserted as $keywordId) $insert->execute(['project_id' => $projectId, 'keyword_id' => $keywordId]);
        }
        return ['changed'=>$inserted!==[]||$deleted!==[],'before'=>$currentNames,'after'=>$selectedNames,'inserted'=>$inserted,'deleted'=>$deleted];
    }

    public function normalizeName(string $name): string
    {
        $name = preg_replace('/\s+/u', ' ', trim($name)) ?? '';
        return mb_strtolower($name, 'UTF-8');
    }

    private function validatedActiveIds(PDO $db, array $keywordIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $keywordIds), static fn (int $id): bool => $id > 0)));
        if (!$ids) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $db->prepare("SELECT id FROM keywords WHERE is_active=1 AND id IN ($placeholders) ORDER BY id");
        $statement->execute($ids);
        $active = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
        if (count($active) !== count($ids)) throw new InvalidArgumentException('Una o más palabras clave ya no están disponibles.');
        return $active;
    }
}
