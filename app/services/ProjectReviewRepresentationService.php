<?php

declare(strict_types=1);

/** Resolves immutable, private PDF representations for a concrete document revision. */
final class ProjectReviewRepresentationService
{
    private const REVIEW_EXTENSIONS = ['pdf', 'docx'];
    private const BASE_DIRECTORY = 'storage/private/project-review-representations';

    public function forFile(array $file, bool $attemptConversion = true): array
    {
        $extension = strtolower((string)($file['extension'] ?? ''));
        if (!in_array($extension, self::REVIEW_EXTENSIONS, true)) {
            return ['required'=>false,'ready'=>true,'source'=>'not_required','path'=>null,'reason'=>null];
        }
        $projectId = (int)($file['project_id'] ?? 0);
        $fileId = (int)($file['id'] ?? 0);
        $checksum = (string)($file['checksum_sha256'] ?? '');
        $source = (new ProjectDocumentFileService())->resolveStoredFile($projectId, (string)($file['storage_name'] ?? ''));
        if (!$this->matchesChecksum($source, $checksum)) throw new RuntimeException('La integridad del archivo no pudo ser verificada.');

        if ($extension === 'pdf') {
            if (!$this->validPdf($source)) throw new RuntimeException('El PDF original no es válido para revisión.');
            return ['required'=>true,'ready'=>true,'source'=>'original_pdf','path'=>$source,'reason'=>null];
        }

        $supplemental = $this->supplementalPath($projectId, $fileId, $checksum);
        if ($supplemental !== null) return ['required'=>true,'ready'=>true,'source'=>'supplemental_pdf','path'=>$supplemental,'reason'=>null];

        $conversion = new DocumentPreviewConversionService();
        $cached = $conversion->cachedPath($projectId, $fileId, $checksum);
        if ($cached !== null) {
            $this->recordGenerated($file, $cached);
            return ['required'=>true,'ready'=>true,'source'=>'libreoffice_pdf','path'=>$cached,'reason'=>null];
        }
        if (!$attemptConversion) return ['required'=>true,'ready'=>false,'source'=>null,'path'=>null,'reason'=>'manual_pdf_required'];
        try {
            $result = $conversion->convertFile($source, $projectId, $fileId, $checksum);
            $this->recordGenerated($file, (string)$result['path']);
            return ['required'=>true,'ready'=>true,'source'=>'libreoffice_pdf','path'=>(string)$result['path'],'reason'=>null];
        } catch (Throwable $exception) {
            return ['required'=>true,'ready'=>false,'source'=>null,'path'=>null,'reason'=>'manual_pdf_required','technical_reason'=>$exception->getMessage()];
        }
    }

    public function supplementalPath(int $projectId, int $fileId, string $checksum): ?string
    {
        $query = Database::connection()->prepare(
            "SELECT storage_name,storage_path,pdf_checksum_sha256 FROM project_review_representations
             WHERE project_id=:project AND file_id=:file AND checksum_sha256=:checksum
               AND representation_type='supplemental_pdf' ORDER BY id DESC LIMIT 1"
        );
        $query->execute(['project'=>$projectId,'file'=>$fileId,'checksum'=>$checksum]);
        $row = $query->fetch();
        if (!$row) return null;
        $path = $this->resolveRepresentationPath((string)$row['storage_path'], (string)$row['storage_name']);
        if (!$this->validPdf($path) || !hash_equals((string)$row['pdf_checksum_sha256'], (string)hash_file('sha256', $path))) return null;
        return $path;
    }

    public function uploadSupplemental(int $projectId, int $fileId, array $upload, int $actor): array
    {
        $file = Database::transaction(function (PDO $db) use ($projectId, $fileId, $actor): array {
            $project = $db->prepare("SELECT id,status FROM projects WHERE id=:id AND deleted_at IS NULL AND status='development' FOR UPDATE");
            $project->execute(['id'=>$projectId]);
            if (!$project->fetch()) throw new InvalidArgumentException('El proyecto no está disponible para modificar la representación.');
            $student = $db->prepare("SELECT 1 FROM project_participants WHERE project_id=:project AND user_id=:user AND role_code='student' AND status='active' AND removed_at IS NULL LIMIT 1");
            $student->execute(['project'=>$projectId,'user'=>$actor]);
            if (!$student->fetchColumn()) throw new InvalidArgumentException('No tienes permiso para adjuntar esta representación.');
            $query = $db->prepare('SELECT * FROM project_files WHERE id=:file AND project_id=:project AND LOWER(extension)=\'docx\' AND deleted_at IS NULL AND purged_at IS NULL FOR UPDATE');
            $query->execute(['file'=>$fileId,'project'=>$projectId]);
            $file = $query->fetch();
            if (!$file) throw new InvalidArgumentException('El documento DOCX ya no está disponible.');
            return $file;
        });

        $this->validatePdfUpload($upload);
        $checksum = (string)$file['checksum_sha256'];
        if ($this->supplementalPath($projectId, $fileId, $checksum) !== null) throw new InvalidArgumentException('Ya existe una representación PDF para esta versión.');
        $temporary = (string)($upload['tmp_name'] ?? '');
        $pdfChecksum = hash_file('sha256', $temporary);
        if (!is_string($pdfChecksum)) throw new RuntimeException('No fue posible calcular la integridad del PDF.');
        $storageName = bin2hex(random_bytes(32)).'.pdf';
        $relative = self::BASE_DIRECTORY.'/'.$projectId.'/'.$fileId.'/'.$checksum.'/'.$storageName;
        $absolute = ROOT_PATH.'/'.$relative;
        if (!is_dir(dirname($absolute)) && !mkdir(dirname($absolute), 0700, true) && !is_dir(dirname($absolute))) throw new RuntimeException('No fue posible preparar el almacenamiento privado.');
        if (is_uploaded_file($temporary)) {
            if (!move_uploaded_file($temporary, $absolute)) throw new RuntimeException('No fue posible guardar la representación PDF.');
        } else {
            if (!copy($temporary, $absolute)) throw new RuntimeException('No fue posible guardar la representación PDF.');
        }
        try {
            $db = Database::connection();
            $version = $db->prepare('SELECT id FROM project_file_versions WHERE file_id=:file AND checksum_sha256=:checksum ORDER BY id DESC LIMIT 1');
            $version->execute(['file'=>$fileId,'checksum'=>$checksum]);
            $versionId = $version->fetchColumn();
            $insert = $db->prepare("INSERT INTO project_review_representations(project_id,file_id,project_file_version_id,checksum_sha256,representation_type,storage_name,storage_path,size_bytes,pdf_checksum_sha256,created_by) VALUES(:project,:file,:version,:checksum,'supplemental_pdf',:name,:path,:size,:pdf_checksum,:actor)");
            $insert->execute(['project'=>$projectId,'file'=>$fileId,'version'=>$versionId ?: null,'checksum'=>$checksum,'name'=>$storageName,'path'=>$relative,'size'=>(int)filesize($absolute),'pdf_checksum'=>$pdfChecksum,'actor'=>$actor]);
        } catch (Throwable $exception) { @unlink($absolute); throw $exception; }
        return ['file_id'=>$fileId,'checksum_sha256'=>$checksum,'representation_source'=>'supplemental_pdf'];
    }

    private function recordGenerated(array $file, string $path): void
    {
        if (!$this->validPdf($path)) return;
        $db = Database::connection();
        $exists = $db->prepare("SELECT id FROM project_review_representations WHERE file_id=:file AND checksum_sha256=:checksum AND representation_type='libreoffice_pdf' LIMIT 1");
        $exists->execute(['file'=>(int)$file['id'],'checksum'=>(string)$file['checksum_sha256']]);
        if ($exists->fetchColumn()) return;
        $version = $db->prepare('SELECT id FROM project_file_versions WHERE file_id=:file AND checksum_sha256=:checksum ORDER BY id DESC LIMIT 1');
        $version->execute(['file'=>(int)$file['id'],'checksum'=>(string)$file['checksum_sha256']]);
        $versionId = $version->fetchColumn();
        $relative = 'storage/private/project-previews/'.(int)$file['project_id'].'/'.(int)$file['id'].'_'.(string)$file['checksum_sha256'].'.pdf';
        $insert = $db->prepare("INSERT IGNORE INTO project_review_representations(project_id,file_id,project_file_version_id,checksum_sha256,representation_type,storage_name,storage_path,size_bytes,pdf_checksum_sha256) VALUES(:project,:file,:version,:checksum,'libreoffice_pdf',:name,:path,:size,:pdf_checksum)");
        $insert->execute(['project'=>(int)$file['project_id'],'file'=>(int)$file['id'],'version'=>$versionId ?: null,'checksum'=>(string)$file['checksum_sha256'],'name'=>basename($path),'path'=>$relative,'size'=>(int)filesize($path),'pdf_checksum'=>(string)hash_file('sha256',$path)]);
    }

    private function validatePdfUpload(array $upload): void
    {
        if ((int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new InvalidArgumentException('Selecciona un archivo PDF.');
        $path = (string)($upload['tmp_name'] ?? ''); $size = (int)($upload['size'] ?? 0); $limit = (new ProjectDocumentFileService())->limits()['max_file_bytes'];
        if ($size < 5 || $size > (int)$limit) throw new InvalidArgumentException('El PDF está vacío o supera el tamaño permitido.');
        $mime = is_file($path) ? (string)(new finfo(FILEINFO_MIME_TYPE))->file($path) : '';
        if ($mime !== 'application/pdf' || !$this->validPdf($path)) throw new InvalidArgumentException('El archivo seleccionado no es un PDF válido.');
    }

    private function resolveRepresentationPath(string $storagePath, string $storageName): string
    {
        if (!preg_match('/^'.preg_quote(self::BASE_DIRECTORY,'/').'\/[0-9]+\/[0-9]+\/[a-f0-9]{64}\/[a-f0-9]{64}\.pdf$/', $storagePath) || !preg_match('/^[a-f0-9]{64}\.pdf$/', $storageName)) return '';
        $base = realpath(ROOT_PATH.'/'.self::BASE_DIRECTORY); $candidate = realpath(ROOT_PATH.'/'.$storagePath);
        if ($base === false || $candidate === false) return '';
        return is_file($candidate) && str_starts_with(strtolower($candidate), strtolower($base.DIRECTORY_SEPARATOR)) ? $candidate : '';
    }

    private function matchesChecksum(string $path, string $checksum): bool { $actual = hash_file('sha256',$path); return is_string($actual) && hash_equals($checksum,$actual); }
    private function validPdf(string $path): bool { if ($path===''||!is_file($path)||filesize($path)<5)return false;$handle=fopen($path,'rb');if($handle===false)return false;$signature=fread($handle,5);fclose($handle);return $signature==='%PDF-'; }
}
