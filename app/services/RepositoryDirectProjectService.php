<?php

declare(strict_types=1);

/** Publica proyectos directamente en Repository sin entrar al workflow académico. */
final class RepositoryDirectProjectService
{
    public function publish(int $userId, array $input, array $uploads, string $idempotencyToken): array
    {
        return $this->publishInternal($userId, $input, $uploads, [], $idempotencyToken);
    }

    /** Uso controlado por QA para probar el servicio con archivos ya preparados. */
    public function publishPrepared(int $userId, array $input, array $preparedFiles, string $idempotencyToken): array
    {
        return $this->publishInternal($userId, $input, [], $preparedFiles, $idempotencyToken);
    }

    private function publishInternal(int $userId, array $input, array $uploads, array $preparedFiles, string $idempotencyToken): array
    {
        if ($userId < 1) throw new RepositoryDirectProjectException('La sesión no es válida.', [], 403);
        $token = trim($idempotencyToken);
        if ($token === '' || mb_strlen($token, '8bit') < 16 || mb_strlen($token, '8bit') > 128) {
            throw new RepositoryDirectProjectException('La solicitud no tiene un identificador válido.', ['idempotency_token'=>'Genera nuevamente la solicitud.'], 422);
        }

        $fileService = new PrivateProjectFileService();
        $tokenHash = hash('sha256', $token);
        $db = Database::connection();
        $projectId = 0;
        $moved = [];
        $package = ProjectRepositoryPackageService::packagePath(0);
        try {
            $db->beginTransaction();
            $files = $this->validateFiles($uploads, $preparedFiles, $fileService);
            $period = $this->lockActivePeriod($db);
            $type = $this->type($db, (int) ($input['project_type_id'] ?? 0));
            $authors = $this->authors($db, $input['author_ids'] ?? [], (int) $period['id']);
            $tutorId = $this->tutor($db, $input['tutor_id'] ?? null);
            $keywordIds = $this->keywords($db, $input['keyword_ids'] ?? []);
            $title = trim((string) ($input['title'] ?? ''));
            $description = trim((string) ($input['description'] ?? ''));
            $errors = [];
            if (mb_strlen($title) < 5 || mb_strlen($title) > 240) $errors['title'] = 'El título debe tener entre 5 y 240 caracteres.';
            if (mb_strlen($description) < 30) $errors['description'] = 'La descripción debe tener al menos 30 caracteres.';
            if ($errors !== []) throw new RepositoryDirectProjectException('Revisa la información indicada.', $errors, 422);
            $careerId = (int) $authors[0]['career_id'];
            foreach ($authors as $author) if ((int) $author['career_id'] !== $careerId) throw new RepositoryDirectProjectException('Todos los autores deben pertenecer a la misma carrera.', ['author_ids'=>'Selecciona autores de una misma carrera.'], 422);
            $payloadHash = $this->payloadHash($title, (int) $type['id'], $description, $authors, $tutorId, $keywordIds, (int) $period['id'], $files);
            $existing = $db->prepare('SELECT project_id,response_json,payload_hash FROM repository_direct_publish_requests WHERE user_id=:user AND request_token=:token FOR UPDATE');
            $existing->execute(['user'=>$userId,'token'=>$tokenHash]);
            $replay = $existing->fetch();
            if ($replay && (int) ($replay['project_id'] ?? 0) > 0 && is_string($replay['response_json'] ?? null)) {
                $storedHash = (string) ($replay['payload_hash'] ?? '');
                if ($storedHash === '' || !hash_equals($storedHash, $payloadHash)) {
                    throw new RepositoryDirectProjectException('El identificador de esta solicitud ya fue utilizado con datos diferentes.', [], 409);
                }
                $db->commit();
                $decoded = json_decode((string) $replay['response_json'], true);
                if (is_array($decoded)) return $decoded + ['idempotent_replay'=>true];
            }
            if ($replay) throw new RepositoryDirectProjectException('La solicitud de publicación ya está siendo procesada o quedó en conflicto.', [], 409);
            if (!$replay) {
                $insertRequest = $db->prepare('INSERT INTO repository_direct_publish_requests(user_id,request_token,payload_hash) VALUES(:user,:token,:payload_hash)');
                $insertRequest->execute(['user'=>$userId,'token'=>$tokenHash,'payload_hash'=>$payloadHash]);
            }

            $code = (new ProjectCodeService())->next($db, (int) $type['id'], (string) $type['code'], (int) gmdate('Y'));
            $insertProject = $db->prepare("INSERT INTO projects (code,project_type_id,career_id,academic_period_id,title,subtitle,summary,tutor_id,status,current_stage,is_available,published_at,publication_origin,repository_added_by,repository_added_at,created_by,created_at,updated_at) VALUES (:code,:type,:career,:period,:title,NULL,:summary,:tutor,'published','repository_direct',0,NULL,'direct_repository',:added_by,UTC_TIMESTAMP(),:creator,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
            $insertProject->execute(['code'=>$code,'type'=>(int)$type['id'],'career'=>$careerId,'period'=>(int)$period['id'],'title'=>$title,'summary'=>$description,'tutor'=>$tutorId,'added_by'=>$userId,'creator'=>$userId]);
            $projectId = (int) $db->lastInsertId();
            $participant = $db->prepare("INSERT INTO project_participants(project_id,user_id,role_code,permission_level,is_leader,status) VALUES(:project,:user,'student','read',:leader,'active')");
            foreach ($authors as $index => $author) $participant->execute(['project'=>$projectId,'user'=>(int)$author['id'],'leader'=>$index === 0 ? 1 : 0]);

            $insertFile = $db->prepare("INSERT INTO project_files(project_id,delivery_id,category,original_name,storage_name,storage_path,mime_type,extension,size_bytes,checksum_sha256,sort_order,uploaded_by) VALUES(:project,NULL,'repository',:original,:storage,:path,:mime,:extension,:size,:checksum,:sort_order,:uploaded_by)");
            foreach ($files as $index => $file) {
                $metadata = $file['kind'] === 'prepared'
                    ? $this->promotePrepared($file['value'], $projectId, $fileService, $moved)
                    : $this->storeUpload($file['value'], $projectId, $fileService, $moved);
                $insertFile->execute(['project'=>$projectId,'original'=>$metadata['original_name'],'storage'=>$metadata['storage_name'],'path'=>$metadata['storage_path'],'mime'=>$metadata['mime_type'],'extension'=>$metadata['extension'],'size'=>(int)$metadata['size_bytes'],'checksum'=>$metadata['checksum_sha256'],'sort_order'=>$index,'uploaded_by'=>$userId]);
            }
            if ($keywordIds !== []) (new ProjectKeywordModel())->replace($db, $projectId, $keywordIds);
            $package = (new ProjectRepositoryPackageService())->buildForProject($projectId)['path'] ?? '';
            if ($package === '' || !is_file($package)) throw new RuntimeException('No fue posible construir el paquete institucional.');
            $publish = $db->prepare("UPDATE projects SET is_available=1,published_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE id=:id AND publication_origin='direct_repository' AND status='published' AND is_available=0");
            $publish->execute(['id'=>$projectId]);
            if ($publish->rowCount() !== 1) throw new RuntimeException('No fue posible activar la publicación.');
            (new ProjectAuditService($db))->record($projectId, $userId, 'repository_direct_publish', 'project', $projectId, null, ['origin'=>ProjectPublicationOrigin::DIRECT_REPOSITORY,'status'=>'published','is_available'=>1]);
            $response = ['project_id'=>$projectId,'project_code'=>$code,'detail_url'=>route('repository-detail').'&id='.$projectId];
            $saveRequest = $db->prepare('UPDATE repository_direct_publish_requests SET project_id=:project,response_json=:response WHERE user_id=:user AND request_token=:token');
            $saveRequest->execute(['project'=>$projectId,'response'=>json_encode($response, JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'user'=>$userId,'token'=>$tokenHash]);
            $db->commit();
            return $response;
        } catch (Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            if ($projectId > 0) (new ProjectRepositoryPackageService())->invalidate($projectId);
            $this->cleanupMoved($moved);
            if ($exception instanceof RepositoryDirectProjectException) throw $exception;
            error_log('Repository direct project publish: ' . $exception->getMessage());
            throw new RepositoryDirectProjectException('No fue posible publicar el proyecto en este momento.', [], 500, $exception);
        }
    }

    private function lockActivePeriod(PDO $db): array
    {
        $query = $db->query("SELECT id,code,name FROM academic_periods WHERE status='active' ORDER BY starts_on DESC,id DESC FOR UPDATE");
        $rows = $query->fetchAll();
        if (count($rows) !== 1) throw new RepositoryDirectProjectException(count($rows) === 0 ? 'No existe un periodo académico activo.' : 'La configuración académica tiene más de un periodo activo.', ['period'=>'El periodo académico actual no está disponible.'], 422);
        return $rows[0];
    }

    private function type(PDO $db, int $id): array
    {
        $query = $db->prepare('SELECT id,code,name FROM project_types WHERE id=:id AND is_active=1 LIMIT 1'); $query->execute(['id'=>$id]);
        $type = $query->fetch(); if (!$type) throw new RepositoryDirectProjectException('El tipo de proyecto seleccionado no está disponible.', ['project_type_id'=>'Selecciona un tipo válido.'], 422); return $type;
    }

    private function authors(PDO $db, mixed $rawIds, int $periodId): array
    {
        $ids = $this->ids($rawIds, 'author_ids'); if ($ids === []) throw new RepositoryDirectProjectException('Selecciona al menos un autor Student.', ['author_ids'=>'Selecciona al menos un autor.'], 422);
        $params=['period'=>$periodId]; $marks=[]; foreach ($ids as $i=>$id) {$key='author'.$i; $marks[]=':'.$key; $params[$key]=$id;}
        $query=$db->prepare("SELECT DISTINCT u.id,u.full_name,sp.career_id FROM users u INNER JOIN student_profiles sp ON sp.user_id=u.id INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN roles r ON r.id=ur.role_id AND r.code='student' INNER JOIN student_enrollments se ON se.student_id=u.id AND se.academic_period_id=:period AND se.status='active' WHERE u.id IN (".implode(',',$marks).") AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL"); $query->execute($params); $rows=$query->fetchAll();
        if (count($rows) !== count($ids)) throw new RepositoryDirectProjectException('Uno o más autores no están disponibles como estudiantes activos.', ['author_ids'=>'Revisa los autores seleccionados.'], 422);
        usort($rows, static fn(array $a,array $b): int => array_search((int)$a['id'],$ids,true) <=> array_search((int)$b['id'],$ids,true)); return $rows;
    }

    private function tutor(PDO $db, mixed $value): ?int
    {
        $id=(int)$value; if ($id < 1) return null;
        $query=$db->prepare("SELECT u.id FROM users u INNER JOIN teacher_profiles tp ON tp.user_id=u.id INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN roles r ON r.id=ur.role_id AND r.code='teacher' WHERE u.id=:id AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL AND tp.can_tutor=1 LIMIT 1"); $query->execute(['id'=>$id]);
        if (!$query->fetchColumn()) throw new RepositoryDirectProjectException('El tutor seleccionado no está disponible.', ['tutor_id'=>'Selecciona un docente válido.'], 422); return $id;
    }

    private function keywords(PDO $db, mixed $rawIds): array
    {
        $ids=$this->ids($rawIds,'keyword_ids'); if ($ids===[]) return [];
        $marks=[];$params=[];foreach($ids as $i=>$id){$key='keyword'.$i;$marks[]=':'.$key;$params[$key]=$id;}
        $query=$db->prepare('SELECT id FROM keywords WHERE id IN ('.implode(',',$marks).') AND is_active=1');$query->execute($params);$valid=array_map('intval',$query->fetchAll(PDO::FETCH_COLUMN));sort($valid,SORT_NUMERIC);
        if (count($valid)!==count($ids)) throw new RepositoryDirectProjectException('Una o más palabras clave no están disponibles.', ['keyword_ids'=>'Selecciona palabras clave válidas.'], 422); return $valid;
    }

    private function payloadHash(string $title, int $typeId, string $description, array $authors, ?int $tutorId, array $keywordIds, int $periodId, array $files): string
    {
        $canonicalFiles = array_map(static fn(array $file): array => [
            'original_name' => (string) $file['metadata']['original_name'],
            'size_bytes' => (int) $file['metadata']['size_bytes'],
            'checksum_sha256' => (string) $file['metadata']['checksum_sha256'],
            'mime_type' => (string) $file['metadata']['mime_type'],
            'extension' => (string) $file['metadata']['extension'],
        ], $files);
        $canonical = [
            'title' => $title,
            'project_type_id' => $typeId,
            'description' => $description,
            'author_ids' => array_map(static fn(array $author): int => (int) $author['id'], $authors),
            'tutor_id' => $tutorId,
            'keyword_ids' => array_values($keywordIds),
            'period_id' => $periodId,
            'files' => $canonicalFiles,
        ];
        return hash('sha256', json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function ids(mixed $raw,string $field): array
    {
        if (!is_array($raw)) $raw=$raw===null||$raw===''?[]:[$raw];$ids=[];
        foreach($raw as $value){if(!is_scalar($value)||filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])===false)throw new RepositoryDirectProjectException('La solicitud contiene identificadores inválidos.',[$field=>'Selecciona opciones válidas.'],422);$ids[]=(int)$value;}
        if(count($ids)!==count(array_unique($ids)))throw new RepositoryDirectProjectException('No se permiten opciones duplicadas.',[$field=>'Elimina duplicados.'],422);return $ids;
    }

    private function validateFiles(array $uploads,array $prepared,PrivateProjectFileService $service): array
    {
        $items=[];$names=[];$total=0;$raw=$this->flatten($uploads);foreach($raw as $file){$metadata=$service->validateUpload($file);$metadata['checksum_sha256']=hash_file('sha256',(string)$file['tmp_name']);$key=mb_strtolower($metadata['original_name'],'UTF-8');if(isset($names[$key]))throw new RepositoryDirectProjectException('No se permiten archivos duplicados.',['files'=>'Hay archivos con el mismo nombre.'],422);$names[$key]=true;$total+=(int)$metadata['size_bytes'];$items[]=['kind'=>'upload','value'=>$file,'metadata'=>$metadata];}
        foreach($prepared as $file){$metadata=$service->validateStoredFile((string)$file['absolute_path'],$file);$key=mb_strtolower((string)$file['original_name'],'UTF-8');if(isset($names[$key]))throw new RepositoryDirectProjectException('No se permiten archivos duplicados.',['files'=>'Hay archivos con el mismo nombre.'],422);$names[$key]=true;$total+=(int)$metadata['size_bytes'];$items[]=['kind'=>'prepared','value'=>$file,'metadata'=>array_merge($file,$metadata)];}
        $limits=$service->limits();if($items===[])throw new RepositoryDirectProjectException('Agrega al menos un archivo.',['files'=>'Agrega al menos un archivo.'],422);if(count($items)>5||$total>(int)$limits['max_total_bytes'])throw new RepositoryDirectProjectException('El conjunto de archivos supera los límites permitidos.',['files'=>'Reduce la cantidad o tamaño de archivos.'],422);return $items;
    }

    private function flatten(array $files): array
    { $out=[];if(isset($files['name'])&&is_array($files['name']))foreach(array_keys($files['name']) as $key){$item=[];foreach($files as $field=>$value)$item[$field]=$value[$key]??null;$out[]=$item;}elseif(isset($files['name']))$out[]=$files;return array_values(array_filter($out,static fn(array $file):bool=>(int)($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)); }

    private function storeUpload(array $upload,int $projectId,PrivateProjectFileService $service,array &$moved): array
    { $stored=(new ProjectDocumentFileService())->storeUpload($projectId,$upload);$moved[]=['source'=>null,'destination'=>$stored['absolute_path']];return $stored; }

    private function promotePrepared(array $file,int $projectId,PrivateProjectFileService $service,array &$moved): array
    { $stored=$service->promoteStoredFile($projectId,(string)$file['absolute_path'],(string)$file['extension']);$moved[]=['source'=>(string)$file['absolute_path'],'destination'=>$stored['absolute_path']];return array_merge($file,$stored); }

    private function cleanupMoved(array $moved): void
    { foreach(array_reverse($moved) as $file){$destination=(string)($file['destination']??'');$source=(string)($file['source']??'');if($source!==''&&$destination!==''&&is_file($destination)){if(!is_dir(dirname($source)))@mkdir(dirname($source),0775,true);@rename($destination,$source);}elseif($destination!==''&&is_file($destination)){@unlink($destination);}} }
}

final class RepositoryDirectProjectException extends RuntimeException
{
    public function __construct(string $message, public readonly array $errors = [], public readonly int $status = 422, ?Throwable $previous = null) { parent::__construct($message, 0, $previous); }
}
