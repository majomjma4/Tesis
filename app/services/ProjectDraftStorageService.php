<?php
declare(strict_types=1);

/** Almacena borradores previos al registro definitivo y sus archivos privados. */
final class ProjectDraftStorageService
{
    private const EXPIRATION_DAYS = 7;
    private const FORBIDDEN_ZIP_EXTENSIONS = ['php','phtml','phar','exe','com','bat','cmd','ps1','vbs','js','jse','wsf','sh','dll','msi','scr'];
    private string $root;

    public function __construct(private readonly ?PDO $db = null)
    {
        $this->root = ROOT_PATH . '/storage/private/project-drafts';
    }

    public function active(int $userId): ?array
    {
        $this->cleanupExpired();
        $q = $this->connection()->prepare('SELECT id,payload,created_at,updated_at,expires_at FROM project_drafts WHERE user_id=:user AND expires_at>UTC_TIMESTAMP() LIMIT 1');
        $q->execute(['user' => $userId]); $draft = $q->fetch();
        if (!$draft) return null;
        $draft['payload'] = $this->decodePayload((string) $draft['payload']);
        $draft['files'] = $this->files((string) $draft['id'], $userId);
        return $draft;
    }

    public function save(int $userId, array $payload): array
    {
        $this->cleanupExpired();
        return Database::transaction(function(PDO $db) use ($userId, $payload): array {
            $q = $db->prepare('SELECT id FROM project_drafts WHERE user_id=:user FOR UPDATE'); $q->execute(['user' => $userId]); $id = (string) ($q->fetchColumn() ?: '');
            $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            if ($id === '') {
                $id = $this->uuid();
                $insert = $db->prepare('INSERT INTO project_drafts(id,user_id,payload,expires_at) VALUES(:id,:user,:payload,DATE_ADD(UTC_TIMESTAMP(), INTERVAL ' . self::EXPIRATION_DAYS . ' DAY))');
                $insert->execute(['id' => $id, 'user' => $userId, 'payload' => $encoded]);
            } else {
                $update = $db->prepare('UPDATE project_drafts SET payload=:payload,expires_at=DATE_ADD(UTC_TIMESTAMP(), INTERVAL ' . self::EXPIRATION_DAYS . ' DAY) WHERE id=:id AND user_id=:user');
                $update->execute(['id' => $id, 'user' => $userId, 'payload' => $encoded]);
            }
            return $this->activeInside($db, $id, $userId);
        });
    }

    public function addUpload(int $userId, array $upload, bool $replace = false): array
    {
        $stage = 'draft_resolution'; $draftId = ''; $metadata = null; $directory = ''; $path = ''; $storedSize = null; $cleanup = 'not_required';
        try {
        $this->cleanupExpired();
        $draft = $this->active($userId) ?? $this->save($userId, []);
        $draftId = (string) $draft['id'];
        $stage = 'initial_validation'; $validator = new PrivateProjectFileService();
        $metadata = $validator->validateUpload($upload);
        $tmp = (string) ($upload['tmp_name'] ?? '');
        $stage = 'is_uploaded_file'; if (!is_uploaded_file($tmp)) throw new InvalidArgumentException('El archivo no se recibió correctamente.');
        $stage = 'hash_file_sha256'; $hash = hash_file('sha256', $tmp);
        if (!is_string($hash) || !preg_match('/^[a-f0-9]{64}$/', $hash)) throw new RuntimeException('No fue posible validar la integridad del archivo.');
        $existing = $this->findConflict($draftId, $userId, $metadata['original_name'], (int) $metadata['size_bytes'], $hash);
        if ($existing !== null && !$replace) {
            if ((string) $existing['checksum_sha256'] === $hash) throw new InvalidArgumentException('Este archivo ya fue agregado.');
            throw new ProjectDraftFileConflictException((int) $existing['id'], 'Ya existe un archivo con este nombre. ¿Deseas reemplazarlo?');
        }
        $stage = 'temporary_directory'; $directory = $this->directory($userId, $draftId); $this->ensureDirectory($directory);
        $storageName = bin2hex(random_bytes(32)) . '.' . $metadata['extension']; $path = $directory . DIRECTORY_SEPARATOR . $storageName;
        $stage = 'move_uploaded_file'; if (!move_uploaded_file($tmp, $path)) throw new RuntimeException('No se pudo subir ' . $metadata['original_name'] . '.');
        $stage = 'file_exists_after_move'; $existsAfterMove = is_file($path);
        $stage = 'filesize_after_move'; $storedSize = $existsAfterMove ? @filesize($path) : false;
        $zip = null;
        try {
            $stage = 'post_storage_metadata'; if ($metadata['extension'] === 'zip') $zip = $this->inspectZip($path);
            $stage = 'insert_project_draft_files';
            $result = Database::transaction(function(PDO $db) use ($replace, $existing, $draftId, $userId, $metadata, $storageName, $path, $hash, $zip): array {
                if ($replace && $existing !== null) {
                    $delete = $db->prepare('DELETE FROM project_draft_files WHERE id=:id AND draft_id=:draft AND user_id=:user');
                    $delete->execute(['id' => (int) $existing['id'], 'draft' => $draftId, 'user' => $userId]);
                }
                $this->assertTotalLimit($db, $draftId, (int) $metadata['size_bytes']);
                $insert = $db->prepare('INSERT INTO project_draft_files(draft_id,user_id,original_name,storage_name,storage_path,mime_type,extension,size_bytes,checksum_sha256,zip_meta) VALUES(:draft,:user,:name,:storage,:path,:mime,:extension,:size,:hash,:zip)');
                $insert->execute(['draft' => $draftId, 'user' => $userId, 'name' => $metadata['original_name'], 'storage' => $storageName, 'path' => $this->relativePath($userId, $draftId, $storageName), 'mime' => $metadata['mime_type'], 'extension' => $metadata['extension'], 'size' => $metadata['size_bytes'], 'hash' => $hash, 'zip' => $zip === null ? null : json_encode($zip, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
                $fileId = (int) $db->lastInsertId();
                $touch = $db->prepare('UPDATE project_drafts SET expires_at=DATE_ADD(UTC_TIMESTAMP(), INTERVAL ' . self::EXPIRATION_DAYS . ' DAY) WHERE id=:draft AND user_id=:user');
                $touch->execute(['draft' => $draftId, 'user' => $userId]);
                return $this->file($fileId, $draftId, $userId, $db);
            });
        } catch (Throwable $exception) {
            $cleanup = is_file($path) ? (@unlink($path) ? 'rollback_cleanup_removed' : 'rollback_cleanup_failed') : 'rollback_cleanup_not_needed';
            throw $exception;
        }
        if ($replace && $existing !== null) $this->discardFile($userId, $draftId, (string) $existing['storage_name']);
        $stage = 'commit_complete';
        return $result;
        } catch (Throwable $exception) {
            $this->logUploadFailure($exception, $stage, $userId, $draftId, $upload, $metadata, $directory, $path, $storedSize, $cleanup);
            throw $exception;
        }
    }

    public function removeFile(int $userId, int $fileId): void
    {
        $file = Database::transaction(function(PDO $db) use ($userId, $fileId): array {
            $q = $db->prepare('SELECT id,draft_id,storage_name FROM project_draft_files WHERE id=:id AND user_id=:user FOR UPDATE'); $q->execute(['id' => $fileId, 'user' => $userId]); $row = $q->fetch();
            if (!$row) throw new InvalidArgumentException('El archivo temporal no está disponible.');
            $delete = $db->prepare('DELETE FROM project_draft_files WHERE id=:id AND user_id=:user'); $delete->execute(['id' => $fileId, 'user' => $userId]);
            return $row;
        });
        $this->discardFile($userId, (string) $file['draft_id'], (string) $file['storage_name']);
    }

    public function delete(int $userId): void
    {
        $draft = $this->active($userId); if ($draft === null) return;
        Database::transaction(function(PDO $db) use ($userId, $draft): void { $q = $db->prepare('DELETE FROM project_drafts WHERE id=:id AND user_id=:user'); $q->execute(['id' => $draft['id'], 'user' => $userId]); });
        $this->discardDirectory($this->directory($userId, (string) $draft['id']));
        $this->discardEmptyUserDirectory($userId);
    }

    /** Resuelve de forma segura un archivo de borrador ya registrado. */
    public function resolveStoredFile(int $userId,string $draftId,string $storageName): string
    {
        if(!preg_match('/^[a-f0-9]{64}\.(pdf|docx|zip)$/',$storageName))throw new InvalidArgumentException('Nombre de almacenamiento inválido.');
        $directory=$this->directory($userId,$draftId);$base=realpath($directory);$path=realpath($directory.DIRECTORY_SEPARATOR.$storageName);
        if($base===false||$path===false||!is_file($path)||!str_starts_with(strtolower($path),strtolower($base.DIRECTORY_SEPARATOR)))throw new RuntimeException('El archivo temporal no está disponible.');
        return $path;
    }

    /** Limpia el directorio de un borrador ya consumido sin afectar otros borradores. */
    public function cleanupConsumedDirectory(int $userId,string $draftId): void
    {
        $this->discardDirectory($this->directory($userId,$draftId));$this->discardEmptyUserDirectory($userId);
    }

    /** Ejecutable de manera oportunista; puede llamarse también desde una tarea programada. */
    public function cleanupExpired(): int
    {
        $db = $this->connection();
        $q = $db->query('SELECT id,user_id FROM project_drafts WHERE expires_at<=UTC_TIMESTAMP()'); $expired = $q->fetchAll();
        if ($expired === []) return 0;
        Database::transaction(function(PDO $connection) use ($expired): void { $ids = array_column($expired, 'id'); $marks = implode(',', array_fill(0, count($ids), '?')); $connection->prepare('DELETE FROM project_drafts WHERE id IN (' . $marks . ')')->execute($ids); });
        foreach ($expired as $draft) { try { $this->discardDirectory($this->directory((int) $draft['user_id'], (string) $draft['id'])); $this->discardEmptyUserDirectory((int) $draft['user_id']); } catch (Throwable $exception) { error_log('Project draft cleanup: ' . $exception->getMessage()); } }
        return count($expired);
    }

    private function activeInside(PDO $db, string $id, int $userId): array
    {
        $q = $db->prepare('SELECT id,payload,created_at,updated_at,expires_at FROM project_drafts WHERE id=:id AND user_id=:user'); $q->execute(['id' => $id, 'user' => $userId]); $draft = $q->fetch();
        if (!$draft) throw new RuntimeException('No fue posible recuperar el borrador.');
        $draft['payload'] = $this->decodePayload((string) $draft['payload']); $draft['files'] = $this->files($id, $userId, $db); return $draft;
    }

    private function files(string $draftId, int $userId, ?PDO $db = null): array
    {
        $db ??= $this->connection(); $q = $db->prepare('SELECT id,draft_id,original_name,storage_name,mime_type,extension,size_bytes,checksum_sha256,zip_meta,created_at FROM project_draft_files WHERE draft_id=:draft AND user_id=:user ORDER BY created_at,id'); $q->execute(['draft' => $draftId, 'user' => $userId]);
        return array_map(function(array $file) use ($userId, $draftId): array { $file['id'] = (int) $file['id']; $file['size_bytes'] = (int) $file['size_bytes']; $file['zip_meta'] = $file['zip_meta'] ? $this->decodePayload((string) $file['zip_meta']) : null; $file['available'] = is_file($this->directory($userId, $draftId) . DIRECTORY_SEPARATOR . $file['storage_name']); return $file; }, $q->fetchAll());
    }

    private function file(int $fileId, string $draftId, int $userId, PDO $db): array
    {
        foreach ($this->files($draftId, $userId, $db) as $file) if ((int) $file['id'] === $fileId) return $file;
        throw new RuntimeException('No fue posible recuperar el archivo temporal.');
    }

    private function findConflict(string $draftId, int $userId, string $name, int $size, string $hash): ?array
    {
        $q = $this->connection()->prepare('SELECT id,storage_name,checksum_sha256 FROM project_draft_files WHERE draft_id=:draft AND user_id=:user AND (original_name=:name OR checksum_sha256=:hash) ORDER BY id LIMIT 1');
        $q->execute(['draft' => $draftId, 'user' => $userId, 'name' => $name, 'hash' => $hash]); return $q->fetch() ?: null;
    }

    private function assertTotalLimit(PDO $db, string $draftId, int $incoming): void
    {
        $q = $db->prepare('SELECT COALESCE(SUM(size_bytes),0) FROM project_draft_files WHERE draft_id=:draft'); $q->execute(['draft' => $draftId]);
        $total = (int) $q->fetchColumn() + $incoming; $limit = (new PrivateProjectFileService())->limits()['max_total_bytes'];
        if ($total > $limit) throw new InvalidArgumentException('El conjunto supera el límite total de ' . (new PrivateProjectFileService())->limits()['max_total_mb'] . ' MB.');
    }

    private function inspectZip(string $path): array
    {
        $inspection = (new ArchiveService())->inspectPackage($path);
        if (empty($inspection['success'])) throw new InvalidArgumentException((string) ($inspection['message'] ?? 'El archivo ZIP no es válido.'));
        $entries = $this->rawZipEntries($path); if ($entries === []) throw new InvalidArgumentException('El archivo ZIP no contiene archivos válidos.');
        $files = 0; $folders = []; $preview = [];
        foreach ($entries as $entry) {
            $name = (string) ($entry['name'] ?? ''); $isDir = (bool) ($entry['is_dir'] ?? false);
            if (!$isDir) { $files++; $extension = mb_strtolower(pathinfo($name, PATHINFO_EXTENSION)); if (in_array($extension, self::FORBIDDEN_ZIP_EXTENSIONS, true)) throw new InvalidArgumentException('El ZIP contiene un formato interno no permitido.'); }
            $directory = dirname($name); while ($directory !== '.' && $directory !== '') { $folders[$directory] = true; $directory = dirname($directory); }
            if (count($preview) < 100) $preview[] = ['name' => $name, 'is_dir' => $isDir, 'size' => (int) ($entry['size'] ?? 0)];
        }
        if ($files < 1) throw new InvalidArgumentException('El archivo ZIP no contiene archivos válidos.');
        return ['valid' => true, 'files_count' => $files, 'folders_count' => count($folders), 'entries' => $preview];
    }

    /** Lee el directorio central incluso cuando el fallback PharData no expone todas las entradas. */
    private function rawZipEntries(string $path): array
    {
        $handle = fopen($path, 'rb'); if ($handle === false) throw new InvalidArgumentException('No fue posible abrir el archivo ZIP.');
        try {
            $size = filesize($path); if ($size === false || $size < 22) throw new InvalidArgumentException('El archivo ZIP no es válido.');
            $tailLength = min((int) $size, 65557); fseek($handle, -$tailLength, SEEK_END); $tail = fread($handle, $tailLength); $eocd = strrpos($tail, "PK\x05\x06");
            if ($eocd === false || strlen($tail) - $eocd < 22) throw new InvalidArgumentException('El archivo ZIP no es válido.');
            $footer = unpack('vdisk/vstart_disk/ventries_disk/ventries/Vdirectory_size/Vdirectory_offset/vcomment', substr($tail, $eocd + 4, 18));
            if (!is_array($footer) || $footer['disk'] !== 0 || $footer['start_disk'] !== 0 || $footer['entries'] !== $footer['entries_disk'] || $footer['entries'] > 2000) throw new InvalidArgumentException('El ZIP utiliza una estructura no permitida.');
            if ((int) $footer['directory_offset'] + (int) $footer['directory_size'] > (int) $size) throw new InvalidArgumentException('El archivo ZIP no es válido.');
            fseek($handle, (int) $footer['directory_offset']); $entries = []; $total = 0;
            for ($index = 0; $index < (int) $footer['entries']; $index++) {
                $fixed = fread($handle, 46); if ($fixed === false || strlen($fixed) !== 46 || substr($fixed, 0, 4) !== "PK\x01\x02") throw new InvalidArgumentException('El archivo ZIP no es válido.');
                $lengths = unpack('vname/vextra/vcomment', substr($fixed, 28, 6)); $compressed = unpack('Vvalue', substr($fixed, 20, 4))['value']; $uncompressed = unpack('Vvalue', substr($fixed, 24, 4))['value']; $attributes = unpack('Vvalue', substr($fixed, 38, 4))['value'];
                if (!is_array($lengths) || $lengths['name'] < 1 || $lengths['name'] > 512 || $uncompressed > 104857600) throw new InvalidArgumentException('El ZIP contiene una entrada no permitida.');
                $name = fread($handle, (int) $lengths['name']); if ($name === false || strlen($name) !== (int) $lengths['name']) throw new InvalidArgumentException('El archivo ZIP no es válido.');
                if (fseek($handle, (int) $lengths['extra'] + (int) $lengths['comment'], SEEK_CUR) !== 0) throw new InvalidArgumentException('El archivo ZIP no es válido.');
                $isDir = str_ends_with($name, '/'); $trimmed = rtrim(str_replace('\\', '/', $name), '/');
                if ($trimmed === '' || str_contains($trimmed, "\0") || str_starts_with($trimmed, '/') || preg_match('/^[A-Za-z]:/', $trimmed) || in_array('..', explode('/', $trimmed), true) || in_array('.', explode('/', $trimmed), true) || count(explode('/', $trimmed)) > 12 || (($attributes >> 16) & 0170000) === 0120000) throw new InvalidArgumentException('El ZIP contiene rutas o enlaces no permitidos.');
                $total += (int) $uncompressed; if ($total > 524288000 || ((int) $uncompressed > 1048576 && (int) $compressed > 0 && ((int) $uncompressed / (int) $compressed) > 200)) throw new InvalidArgumentException('El ZIP supera los límites permitidos.');
                $entries[] = ['name' => $trimmed, 'is_dir' => $isDir, 'size' => $isDir ? 0 : (int) $uncompressed];
            }
            return $entries;
        } finally { fclose($handle); }
    }

    /** Registra diagnóstico seguro del servidor; nunca se envía al navegador. */
    private function logUploadFailure(Throwable $exception, string $stage, int $userId, string $draftId, array $upload, ?array $metadata, string $directory, string $path, mixed $storedSize, string $cleanup): void
    {
        $context = [
            'stage'=>$stage,
            'exception_class'=>$exception::class,
            'exception_message'=>$exception->getMessage(),
            'exception_file'=>basename($exception->getFile()),
            'exception_line'=>$exception->getLine(),
            'user_id'=>$userId,
            'draft_id'=>$draftId !== '' ? $draftId : null,
            'extension'=>$metadata['extension'] ?? mb_strtolower(pathinfo((string)($upload['name'] ?? ''), PATHINFO_EXTENSION)),
            'size_bytes'=>$metadata['size_bytes'] ?? (int)($upload['size'] ?? 0),
            'mime_type'=>$metadata['mime_type'] ?? null,
            'upload_error'=>(int)($upload['error'] ?? UPLOAD_ERR_NO_FILE),
            'is_uploaded_file'=>($tmp = (string)($upload['tmp_name'] ?? '')) !== '' && is_uploaded_file($tmp),
            'destination_exists'=>$path !== '' && is_file($path),
            'destination_directory_exists'=>$directory !== '' && is_dir($directory),
            'destination_directory_writable'=>$directory !== '' && is_writable($directory),
            'stored_size_bytes'=>$storedSize === false ? null : $storedSize,
            'cleanup'=>$cleanup,
        ];
        error_log('Project draft upload failure: ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function connection(): PDO { return $this->db ?? Database::connection(); }
    private function directory(int $userId, string $draftId): string { return $this->root . DIRECTORY_SEPARATOR . $userId . DIRECTORY_SEPARATOR . $draftId; }
    private function relativePath(int $userId, string $draftId, string $storage): string { return 'storage/private/project-drafts/' . $userId . '/' . $draftId . '/' . $storage; }
    private function ensureDirectory(string $directory): void { if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) throw new RuntimeException('No fue posible preparar el almacenamiento temporal.'); }
    private function discardFile(int $userId, string $draftId, string $storage): void { $path = $this->directory($userId, $draftId) . DIRECTORY_SEPARATOR . $storage; if (is_file($path)) @unlink($path); }
    private function discardEmptyUserDirectory(int $userId): void { $dir = $this->root . DIRECTORY_SEPARATOR . $userId; if (is_dir($dir) && (new FilesystemIterator($dir))->valid() === false) @rmdir($dir); }
    private function discardDirectory(string $directory): void { $realRoot = realpath($this->root); $realDirectory = realpath($directory); if ($realDirectory === false || $realRoot === false || !str_starts_with(strtolower($realDirectory), strtolower($realRoot . DIRECTORY_SEPARATOR))) return; $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($realDirectory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($iterator as $node) $node->isDir() ? @rmdir($node->getPathname()) : @unlink($node->getPathname()); @rmdir($realDirectory); }
    private function decodePayload(string $json): array { try { $value = json_decode($json, true, 512, JSON_THROW_ON_ERROR); return is_array($value) ? $value : []; } catch (Throwable) { return []; } }
    private function uuid(): string { $hex = bin2hex(random_bytes(16)); return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4' . substr($hex, 13, 3) . '-' . dechex((hexdec($hex[16]) & 0x3) | 0x8) . substr($hex, 17, 3) . '-' . substr($hex, 20); }
}

final class ProjectDraftFileConflictException extends InvalidArgumentException
{
    public function __construct(public readonly int $fileId, string $message) { parent::__construct($message); }
}
