<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
$GLOBALS['config'] = require APP_PATH . '/config/app.php';
require APP_PATH . '/helpers.php';
require APP_PATH . '/Core/Autoloader.php';
Autoloader::register();

$action = strtolower(trim((string) ($argv[1] ?? '')));
if (!in_array($action, ['setup', 'verify', 'cleanup'], true)) {
    fwrite(STDERR, "Uso: php scripts/qa_prefinal_direct_multifile.php [setup|verify|cleanup]\n");
    exit(1);
}

$title = 'QA-PREFINAL-DIRECT-MULTIFILE-001';
$token = 'qa-prefinal-direct-multifile-001';
$db = Database::connection();
$find = static function (PDO $connection) use ($title): ?array {
    $query = $connection->prepare("SELECT * FROM projects WHERE title=:title AND publication_origin='direct_repository' AND deleted_at IS NULL LIMIT 1");
    $query->execute(['title' => $title]);
    return $query->fetch() ?: null;
};

$makeFile = static function (string $extension, string $name): array {
    $directory = ROOT_PATH . '/storage/private/project-publication-preparations/qa-prefinal';
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) throw new RuntimeException('No se pudo crear el directorio QA.');
    $storage = bin2hex(random_bytes(32)) . '.' . $extension;
    $path = $directory . DIRECTORY_SEPARATOR . $storage;
    if ($extension === 'pdf') {
        file_put_contents($path, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n");
    } else {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('No se pudo construir el DOCX QA.');
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>QA prefinal multi-file</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();
    }
    return ['original_name' => $name, 'storage_name' => $storage, 'absolute_path' => $path, 'extension' => $extension, 'mime_type' => $extension === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'size_bytes' => (int) filesize($path), 'checksum_sha256' => hash_file('sha256', $path)];
};

if ($action === 'setup') {
    if ($find($db)) throw new RuntimeException('El fixture QA ya existe; ejecuta cleanup antes de repetirlo.');
    $students = $db->query("SELECT DISTINCT u.id,sp.career_id FROM users u INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN roles r ON r.id=ur.role_id AND r.code='student' INNER JOIN student_profiles sp ON sp.user_id=u.id INNER JOIN student_enrollments se ON se.student_id=u.id AND se.status='active' WHERE u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL ORDER BY u.id")->fetchAll();
    $authors = [];
    foreach ($students as $student) { if ($authors === [] || (int) $student['career_id'] === (int) $authors[0]['career_id']) $authors[] = $student; if (count($authors) === 2) break; }
    $tutors = $db->query("SELECT DISTINCT u.id FROM users u INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN roles r ON r.id=ur.role_id AND r.code='teacher' INNER JOIN teacher_profiles tp ON tp.user_id=u.id WHERE u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL AND tp.can_tutor=1 ORDER BY u.id LIMIT 3")->fetchAll(PDO::FETCH_COLUMN);
    $type = (int) $db->query("SELECT id FROM project_types WHERE is_active=1 ORDER BY id LIMIT 1")->fetchColumn();
    $keywords = array_map('intval', $db->query("SELECT id FROM keywords WHERE is_active=1 ORDER BY id LIMIT 3")->fetchAll(PDO::FETCH_COLUMN));
    if (count($authors) < 2 || count($tutors) < 2 || $type < 1) throw new RuntimeException('No existen catálogos QA suficientes.');
    $tutorIds = [(int) $tutors[0], (int) $tutors[1]];
    $publisher = count($tutors) > 2 ? (int) $tutors[2] : $tutorIds[0];
    $files = [$makeFile('pdf', 'QA-prefinal-documento-a.pdf'), $makeFile('docx', 'QA-prefinal-documento-b.docx')];
    try {
        $result = (new RepositoryDirectProjectService())->publishPrepared($publisher, ['title'=>$title,'project_type_id'=>$type,'description'=>'Fixture QA prefinal para verificar publicación directa multi-archivo, detalle, visor y paquete institucional.','author_ids'=>[(int)$authors[0]['id'],(int)$authors[1]['id']],'tutoring_user_ids'=>$tutorIds,'tutoring_primary_id'=>$tutorIds[0],'keyword_ids'=>$keywords], $files, $token);
        echo 'created_project=' . (int) $result['project_id'] . PHP_EOL;
    } finally {
        foreach ($files as $file) { if (is_file($file['absolute_path'])) @unlink($file['absolute_path']); }
        @rmdir(dirname($files[0]['absolute_path']));
    }
    exit(0);
}

if ($action === 'verify') {
    $project = $find($db); if (!$project) { echo "fixtures=0\nphysical_QA_files=0\n"; exit(0); }
    $id = (int) $project['id'];
    $filesQuery = $db->prepare("SELECT * FROM project_files WHERE project_id=:id AND deleted_at IS NULL AND purged_at IS NULL ORDER BY sort_order,id"); $filesQuery->execute(['id'=>$id]); $files = $filesQuery->fetchAll();
    $tutorCount = (int) $db->query("SELECT COUNT(*) FROM project_participants WHERE project_id={$id} AND role_code='tutor' AND status='active' AND removed_at IS NULL")->fetchColumn();
    $authorCount = (int) $db->query("SELECT COUNT(*) FROM project_participants WHERE project_id={$id} AND role_code='student' AND status='active' AND removed_at IS NULL")->fetchColumn();
    $packagePath = ProjectRepositoryPackageService::packagePath($id); $zipCount = 0; if (is_file($packagePath)) { $zip = new ZipArchive(); if ($zip->open($packagePath) === true) { for ($i=0;$i<$zip->numFiles;$i++) if (substr((string)$zip->getNameIndex($i), -1) !== '/') $zipCount++; $zip->close(); } }
    $physical = 0; foreach ($files as $file) { try { if (is_file((new ProjectDocumentFileService())->resolveStoredFile($id, (string)$file['storage_name']))) $physical++; } catch (Throwable) {} }
    $record = (new ProjectRecordModel())->find($id, null, false, true); $preview = []; foreach ($files as $file) { $path = (new ProjectDocumentFileService())->resolveStoredFile($id, (string)$file['storage_name']); $stream = fopen($path, 'rb'); $prepared = (new FilePreviewService())->prepare(['name'=>$file['original_name'],'path'=>$file['original_name'],'size'=>(int)$file['size_bytes'],'stream'=>$stream], 'https://qa.invalid/content', 'https://qa.invalid/download'); $preview[$file['extension']] = $prepared['status'] . ':' . $prepared['preview_type']; fclose($stream); }
    foreach (['project_id'=>$id,'authors'=>$authorCount,'tutors'=>$tutorCount,'active_files'=>count($files),'physical_files'=>$physical,'zip_files'=>$zipCount,'detail_files'=>count((array)($record['files']??[])),'preview_pdf'=>$preview['pdf']??'missing','preview_docx'=>$preview['docx']??'missing'] as $key=>$value) echo $key . '=' . (is_scalar($value) ? $value : json_encode($value)) . PHP_EOL;
    exit(0);
}

$project = $find($db); if (!$project) { echo "cleanup no-op\n"; exit(0); }
$id = (int) $project['id']; $filesQuery = $db->prepare('SELECT storage_name FROM project_files WHERE project_id=:id'); $filesQuery->execute(['id'=>$id]); $storage = new ProjectDocumentFileService(); foreach ($filesQuery->fetchAll(PDO::FETCH_COLUMN) as $name) { try { $path=$storage->resolveStoredFile($id,(string)$name); if(is_file($path)) @unlink($path); } catch(Throwable) {} }
$db->beginTransaction(); try { $db->prepare('DELETE FROM repository_direct_publish_requests WHERE project_id=:id')->execute(['id'=>$id]); $db->prepare("DELETE FROM projects WHERE id=:id AND title=:title AND publication_origin='direct_repository'")->execute(['id'=>$id,'title'=>$title]); $db->commit(); } catch(Throwable $error) { $db->rollBack(); throw $error; }
$package = ProjectRepositoryPackageService::packagePath($id); if (is_file($package)) @unlink($package); @rmdir(ROOT_PATH . '/storage/private/projects/' . $id); echo 'cleaned_project=' . $id . PHP_EOL;
