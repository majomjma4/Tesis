<?php
declare(strict_types=1);
// Ejecutar después de aplicar 20260828_project_repository_withdrawal.sql.
// Este QA crea un proyecto publicado temporal y lo elimina en finally.
define('ROOT_PATH', dirname(__DIR__)); define('APP_PATH', ROOT_PATH.'/app');
$GLOBALS['config']=require APP_PATH.'/config/app.php'; require APP_PATH.'/helpers.php'; require APP_PATH.'/Core/Autoloader.php'; Autoloader::register();
$db=Database::connection();$projectId=0;$tag='QA_REPO_'.gmdate('YmdHis').'_'.bin2hex(random_bytes(3));
try{
 $column=$db->query("SHOW COLUMNS FROM projects LIKE 'withdrawn_at'")->fetch();if(!$column)throw new RuntimeException('Aplica primero la migración de retiro del Repositorio.');
 $actor=(int)$db->query("SELECT id FROM users WHERE is_admin=1 AND status='active' AND deleted_at IS NULL LIMIT 1")->fetchColumn();$type=(int)$db->query("SELECT id FROM project_types WHERE is_active=1 AND code<>'thesis' LIMIT 1")->fetchColumn();$career=(int)$db->query('SELECT id FROM careers WHERE is_active=1 LIMIT 1')->fetchColumn();$period=(int)$db->query("SELECT id FROM academic_periods WHERE status='active' LIMIT 1")->fetchColumn();if(!$actor||!$type||!$career||!$period)throw new RuntimeException('Catálogos QA no disponibles.');
 $db->prepare("INSERT INTO projects(code,project_type_id,career_id,academic_period_id,title,status,published_at,is_available,created_by) VALUES(?,?,?,?,?,'published',UTC_TIMESTAMP(),1,?)")->execute([$tag,$type,$career,$period,$tag,$actor]);$projectId=(int)$db->lastInsertId();$published=(string)$db->query('SELECT published_at FROM projects WHERE id='.$projectId)->fetchColumn();
 $model=new AdminRepositoryModel();$model->setAvailability($projectId,false,$actor);$row=$db->query('SELECT status,is_available,withdrawn_at,published_at FROM projects WHERE id='.$projectId)->fetch();if($row['status']!=='published'||(int)$row['is_available']!==0||$row['withdrawn_at']!==null)throw new RuntimeException('Disponibilidad alteró estado académico o retiro.');
 $model->setPublished($projectId,false,$actor);$row=$db->query('SELECT status,is_available,withdrawn_at,published_at FROM projects WHERE id='.$projectId)->fetch();if($row['status']!=='published'||(int)$row['is_available']!==0||$row['withdrawn_at']===null||$row['published_at']!==$published)throw new RuntimeException('Retiro no preservó publicación/disponibilidad.');
 $model->restorePublication($projectId,$actor);$row=$db->query('SELECT status,is_available,withdrawn_at,published_at FROM projects WHERE id='.$projectId)->fetch();if($row['status']!=='published'||(int)$row['is_available']!==0||$row['withdrawn_at']!==null||$row['published_at']!==$published)throw new RuntimeException('Reincorporación alteró la disponibilidad o publicación.');
 echo json_encode(['ok'=>true,'availability'=>true,'withdrawal'=>true,'reincorporation'=>true],JSON_UNESCAPED_UNICODE).PHP_EOL;
}finally{if($projectId){$db->prepare('DELETE FROM project_audit_log WHERE project_id=?')->execute([$projectId]);$db->prepare('DELETE FROM projects WHERE id=?')->execute([$projectId]);}}
