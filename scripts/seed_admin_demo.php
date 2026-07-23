<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
require APP_PATH . '/helpers.php';
require APP_PATH . '/Core/Autoloader.php';
Autoloader::register();

/** Datos demostrativos reutilizables para probar exclusivamente el rol Administrador. */
final class AdminDemoSeeder
{
    private PDO $db;
    private string $temporaryPassword = 'Istel2026+';
    private array $userIds = [];
    private array $projectIds = [];

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function run(): array
    {
        $this->db->beginTransaction();
        try {
            $adminId = $this->requiredId("SELECT id FROM users WHERE email='tesisad@gmail.com' LIMIT 1", 'No se encontró la cuenta administradora tesisad@gmail.com.');
            $catalogs = $this->seedCatalogs();
            $this->seedUsers($catalogs);
            $this->seedSubjects($catalogs);
            $this->removePreviousDemoProjects();
            $this->seedProjects($catalogs);
            $this->seedProjectDetails($adminId);
            $this->seedAdminActivity($adminId);
            $this->db->commit();
            $files = $this->createPrivateFiles();

            return [
                'users' => count($this->userIds),
                'teachers' => 6,
                'students' => 6,
                'projects' => count($this->projectIds),
                'files' => $files,
                'temporary_password' => $this->temporaryPassword,
            ];
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    private function seedCatalogs(): array
    {
        $this->db->exec("INSERT INTO roles(code,name) VALUES ('student','Estudiante'),('teacher','Docente'),('administrator','Administrador') ON DUPLICATE KEY UPDATE name=VALUES(name)");
        $this->db->exec("INSERT INTO careers(code,name,is_active) VALUES ('TDS','Desarrollo de Software',1) ON DUPLICATE KEY UPDATE name=VALUES(name),is_active=1");
        $this->db->exec("INSERT INTO academic_periods(code,name,starts_on,ends_on,status) VALUES
            ('2026-I','I PAO 2026','2026-01-01','2026-06-30','closed'),
            ('2026-II','II PAO 2026','2026-07-01','2026-12-31','active'),
            ('2027-I','I PAO 2027','2027-01-01','2027-06-30','planned')
            ON DUPLICATE KEY UPDATE name=VALUES(name),starts_on=VALUES(starts_on),ends_on=VALUES(ends_on),status=VALUES(status)");
        $this->db->exec("INSERT INTO project_types(code,name,is_active) VALUES
            ('thesis','Titulación',1),('thesis_profile','Perfil de tesis',1),('pis','Proyecto integrador de saberes',1),
            ('practice','Prácticas preprofesionales',1),('community','Proyecto de vinculación',1)
            ON DUPLICATE KEY UPDATE name=VALUES(name),is_active=1");

        $career = $this->requiredId("SELECT id FROM careers WHERE code='TDS'", 'No se pudo preparar la carrera.');
        $period = $this->requiredId("SELECT id FROM academic_periods WHERE code='2026-II'", 'No se pudo preparar el periodo activo.');
        $this->execute("INSERT INTO research_lines(career_id,name,is_active)
            SELECT :career,:name,1 WHERE NOT EXISTS(SELECT 1 FROM research_lines WHERE career_id=:career2 AND name=:name2)",
            ['career'=>$career,'name'=>'Desarrollo de software y transformación digital','career2'=>$career,'name2'=>'Desarrollo de software y transformación digital']);
        $this->execute("INSERT INTO research_lines(career_id,name,is_active)
            SELECT :career,:name,1 WHERE NOT EXISTS(SELECT 1 FROM research_lines WHERE career_id=:career2 AND name=:name2)",
            ['career'=>$career,'name'=>'Seguridad, datos e infraestructura tecnológica','career2'=>$career,'name2'=>'Seguridad, datos e infraestructura tecnológica']);

        return [
            'career' => $career,
            'period' => $period,
            'previous_period' => $this->requiredId("SELECT id FROM academic_periods WHERE code='2026-I'", 'Falta el periodo anterior.'),
            'line' => $this->requiredId("SELECT id FROM research_lines WHERE career_id={$career} ORDER BY id LIMIT 1", 'No se pudo preparar la línea de investigación.'),
            'types' => $this->pairs('SELECT code,id FROM project_types'),
            'roles' => $this->pairs('SELECT code,id FROM roles'),
        ];
    }

    private function seedUsers(array $catalogs): void
    {
        $users = [
            ['ana.torres.demo@correo.com','Ana Lucía Torres','student','1750010001',4,'active',null],
            ['carlos.mendoza.demo@correo.com','Carlos Andrés Mendoza','student','1750010002',4,'active',null],
            ['sofia.lopez.demo@correo.com','Sofía López Herrera','student','1750010003',2,'active',null],
            ['diego.paredes.demo@correo.com','Diego Paredes Ruiz','student','1750010004',6,'active',null],
            ['valentina.mora.demo@correo.com','Valentina Mora Cedeño','student','1750010005',8,'active',null],
            ['mateo.silva.demo@correo.com','Mateo Silva Ortiz','student','1750010006',4,'blocked',null],
            ['maribel.fierro.pendiente@local.invalid','Msc. Maribel Fierro Montero','teacher','0202053801',0,'active','Msc. (por confirmar)'],
            ['maria.navarrete.pendiente@local.invalid','Msc. Maria Elena Navarrete','teacher','0202053802',0,'active','Msc. (por confirmar)'],
            ['diana.alegria.pendiente@local.invalid','Lic. Diana Alegría Camino','teacher','0202053803',0,'active','Lic. (por confirmar)'],
            ['diana.ramirez.pendiente@local.invalid','Msc. Diana Anaid Ramirez','teacher','0202053804',0,'active','Msc. (por confirmar)'],
            ['alex.galarza.pendiente@local.invalid','Abg. Alex Fabián Galarza','teacher','0202053805',0,'active','Abg. (por confirmar)'],
            ['henrry.marino.pendiente@local.invalid','Msc. Henrry Mariño Acosta','teacher','0202053806',0,'active','Msc. (por confirmar)'],
        ];
        $hash = password_hash($this->temporaryPassword, PASSWORD_DEFAULT);
        foreach ($users as [$email,$name,$role,$code,$semester,$status,$title]) {
            $this->execute("INSERT INTO users(email,password_hash,must_change_password,password_warning_count,temporary_password_expires_at,full_name,status,created_at)
                VALUES(:email,:hash,1,0,DATE_ADD(CURRENT_TIMESTAMP,INTERVAL 7 DAY),:name,:status,DATE_SUB(CURRENT_TIMESTAMP,INTERVAL 5 DAY))
                ON DUPLICATE KEY UPDATE full_name=VALUES(full_name),status=VALUES(status),deleted_at=NULL,purged_at=NULL",
                compact('email','hash','name','status'));
            $id = $this->requiredId('SELECT id FROM users WHERE email='.$this->db->quote($email), 'No se pudo crear '.$email);
            $this->userIds[$email] = $id;
            $this->execute('INSERT IGNORE INTO user_roles(user_id,role_id) VALUES(:user,:role)', ['user'=>$id,'role'=>$catalogs['roles'][$role]]);
            if ($role === 'student') {
                $this->execute("INSERT INTO student_profiles(user_id,institutional_code,career_id) VALUES(:id,:code,:career)
                    ON DUPLICATE KEY UPDATE institutional_code=VALUES(institutional_code),career_id=VALUES(career_id)", ['id'=>$id,'code'=>$code,'career'=>$catalogs['career']]);
                $this->execute("INSERT INTO student_enrollments(student_id,academic_period_id,career_id,semester,status) VALUES(:student,:period,:career,:semester,'active')
                    ON DUPLICATE KEY UPDATE career_id=VALUES(career_id),semester=VALUES(semester),status='active'",
                    ['student'=>$id,'period'=>$catalogs['period'],'career'=>$catalogs['career'],'semester'=>$semester]);
            } else {
                $this->execute("INSERT INTO teacher_profiles(user_id,institutional_code,academic_title,can_tutor) VALUES(:id,:code,:title,1)
                    ON DUPLICATE KEY UPDATE institutional_code=VALUES(institutional_code),academic_title=VALUES(academic_title),can_tutor=1",
                    ['id'=>$id,'code'=>$code,'title'=>$title]);
            }
        }
    }

    private function seedSubjects(array &$catalogs): void
    {
        $subjects = [
            ['PROY-401','Proyecto Integrador IV',4,'maribel.fierro.pendiente@local.invalid'],
            ['SEG-601','Seguridad de Aplicaciones',6,'maria.navarrete.pendiente@local.invalid'],
            ['TIT-801','Unidad de Integración Curricular',8,'diana.alegria.pendiente@local.invalid'],
        ];
        foreach ($subjects as [$code,$name,$semester,$teacher]) {
            $this->execute("INSERT INTO academic_subjects(career_id,academic_period_id,semester,code,name,responsible_teacher_id)
                VALUES(:career,:period,:semester,:code,:name,:teacher)
                ON DUPLICATE KEY UPDATE semester=VALUES(semester),name=VALUES(name),responsible_teacher_id=VALUES(responsible_teacher_id)",
                ['career'=>$catalogs['career'],'period'=>$catalogs['period'],'semester'=>$semester,'code'=>$code,'name'=>$name,'teacher'=>$this->userIds[$teacher]]);
        }
        $catalogs['subjects'] = $this->pairs("SELECT code,id FROM academic_subjects WHERE academic_period_id={$catalogs['period']}");
    }

    private function removePreviousDemoProjects(): void
    {
        $codes = ['TIT-2026-001','PIS-2026-001','PRA-2026-001','VIN-2026-001','TIT-2026-002','PIS-2026-002','PIS-2026-003'];
        $marks = implode(',', array_fill(0, count($codes), '?'));
        $statement = $this->db->prepare("SELECT id FROM projects WHERE code IN ({$marks})");
        $statement->execute($codes);
        $ids = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
        if (!$ids) return;
        $projectMarks = implode(',', array_fill(0, count($ids), '?'));
        $deliveryIds = $this->idsFor("SELECT id FROM project_deliveries WHERE project_id IN ({$projectMarks})", $ids);
        $fileIds = $this->idsFor("SELECT id FROM project_files WHERE project_id IN ({$projectMarks})", $ids);
        $observationIds = $this->idsFor("SELECT id FROM project_observations WHERE project_id IN ({$projectMarks})", $ids);
        if ($observationIds) $this->deleteIn('observation_responses', 'observation_id', $observationIds);
        $this->deleteIn('project_comments', 'project_id', $ids);
        if ($observationIds) $this->deleteIn('project_observations', 'id', $observationIds);
        if ($fileIds) $this->deleteIn('project_files', 'id', $fileIds);
        if ($deliveryIds) $this->deleteIn('project_deliveries', 'id', $deliveryIds);
        foreach (['notifications','project_favorites','project_downloads','project_events','project_audit_log','project_participants','project_stages'] as $table) {
            $this->deleteIn($table, 'project_id', $ids);
        }
        $this->deleteIn('projects', 'id', $ids);
    }

    private function seedProjects(array $catalogs): void
    {
        $projects = [
            ['TIT-2026-001','thesis','Sistema inteligente para la gestión de turnos médicos','Plataforma web accesible para centros de salud','under_review','review','valentina.mora.demo@correo.com','diana.alegria.pendiente@local.invalid','TIT-801'],
            ['PIS-2026-001','pis','Aplicación móvil para rutas de transporte urbano','Seguimiento de recorridos y alertas para usuarios','development','development','ana.torres.demo@correo.com','maribel.fierro.pendiente@local.invalid','PROY-401'],
            ['PRA-2026-001','practice','Automatización del inventario de equipos tecnológicos','Práctica preprofesional aplicada al control institucional','changes_required','review','diego.paredes.demo@correo.com','maria.navarrete.pendiente@local.invalid','SEG-601'],
            ['VIN-2026-001','community','Alfabetización digital para emprendedores locales','Proyecto de vinculación con herramientas de comercio electrónico','approved','final_documents','sofia.lopez.demo@correo.com','maribel.fierro.pendiente@local.invalid','PROY-401'],
            ['TIT-2026-002','thesis','Modelo de detección temprana de riesgos académicos','Análisis de indicadores para acompañamiento estudiantil','completed','closed','valentina.mora.demo@correo.com','diana.alegria.pendiente@local.invalid','TIT-801'],
            ['PIS-2026-002','pis','Panel de monitoreo energético para laboratorios','Visualización en tiempo real de consumo eléctrico','published','published','carlos.mendoza.demo@correo.com','maria.navarrete.pendiente@local.invalid','PROY-401'],
            ['PIS-2026-003','pis','Prototipo descartado de control de asistencia','Registro creado para probar la papelera administrativa','development','registration','mateo.silva.demo@correo.com','maribel.fierro.pendiente@local.invalid','PROY-401'],
        ];
        foreach ($projects as [$code,$type,$title,$subtitle,$status,$stage,$student,$teacher,$subject]) {
            $deleted = $code === 'PIS-2026-003' ? 'DATE_SUB(CURRENT_TIMESTAMP,INTERVAL 12 DAY)' : 'NULL';
            $sql = "INSERT INTO projects(code,project_type_id,career_id,academic_period_id,title,subtitle,summary,modality,research_line_id,academic_subject_id,proposed_tutor_id,tutor_id,status,current_stage,created_by,approved_at,closed_at,published_at,created_at,updated_at,deleted_at,deletion_reason)
                VALUES(:code,:type,:career,:period,:title,:subtitle,:summary,'group',:line,:subject,:proposed_tutor,:tutor,:status,:stage,:creator,".
                ($status === 'approved' ? 'DATE_SUB(CURRENT_TIMESTAMP,INTERVAL 2 DAY)' : 'NULL').','.
                (in_array($status,['completed','published'],true) ? 'DATE_SUB(CURRENT_TIMESTAMP,INTERVAL 8 DAY)' : 'NULL').','.
                ($status === 'published' ? 'DATE_SUB(CURRENT_TIMESTAMP,INTERVAL 3 DAY)' : 'NULL').",
                DATE_SUB(CURRENT_TIMESTAMP,INTERVAL 35 DAY),DATE_SUB(CURRENT_TIMESTAMP,INTERVAL ".random_int(1, 9)." DAY),{$deleted},".($deleted !== 'NULL' ? "'Registro demostrativo para comprobar restauración y purga.'" : 'NULL').')';
            $this->execute($sql, ['code'=>$code,'type'=>$catalogs['types'][$type],'career'=>$catalogs['career'],'period'=>$catalogs['period'],'title'=>$title,'subtitle'=>$subtitle,'summary'=>'Proyecto demostrativo con información realista para validar las herramientas administrativas.','line'=>$catalogs['line'],'subject'=>$catalogs['subjects'][$subject] ?? null,'proposed_tutor'=>$this->userIds[$teacher],'tutor'=>$this->userIds[$teacher],'status'=>$status,'stage'=>$stage,'creator'=>$this->userIds[$student]]);
            $id = (int)$this->db->lastInsertId();
            $this->projectIds[$code] = $id;
            $this->participant($id, $this->userIds[$student], 'student', 'manage', true);
            $this->participant($id, $this->userIds[$teacher], 'tutor', 'review', false);
        }
        $this->participant($this->projectIds['PIS-2026-001'], $this->userIds['carlos.mendoza.demo@correo.com'], 'student', 'contribute', false);
    }

    private function seedProjectDetails(int $adminId): void
    {
        foreach ($this->projectIds as $code => $projectId) {
            $statuses = $code === 'PIS-2026-002' ? ['completed','completed','completed'] : ['completed','current','upcoming'];
            foreach ([['registration','Registro'],['development','Desarrollo'],['review','Revisión']] as $position => [$stageCode,$label]) {
                $this->execute('INSERT INTO project_stages(project_id,stage_code,label,position,status,completed_at) VALUES(:project,:code,:label,:position,:status,:completed)', [
                    'project'=>$projectId,'code'=>$stageCode,'label'=>$label,'position'=>$position + 1,'status'=>$statuses[$position],
                    'completed'=>$statuses[$position] === 'completed' ? date('Y-m-d H:i:s', strtotime('-12 days')) : null,
                ]);
            }
        }

        $reviewProject = $this->projectIds['TIT-2026-001'];
        $student = $this->userIds['valentina.mora.demo@correo.com'];
        $teacher = $this->userIds['diana.alegria.pendiente@local.invalid'];
        $stage = $this->requiredId("SELECT id FROM project_stages WHERE project_id={$reviewProject} AND stage_code='review'", 'Falta etapa de revisión.');
        $this->execute("INSERT INTO project_deliveries(project_id,stage_id,version_number,title,comment,status,submitted_by,submitted_at) VALUES(:project,:stage,1,'Informe de avance v1','Primera versión para revisión.','changes_required',:student,DATE_SUB(CURRENT_TIMESTAMP,INTERVAL 6 DAY))", ['project'=>$reviewProject,'stage'=>$stage,'student'=>$student]);
        $delivery = (int)$this->db->lastInsertId();
        $this->execute("INSERT INTO project_observations(project_id,delivery_id,author_id,category,location_reference,body,status,created_at) VALUES
            (:project,:delivery,:teacher,'Metodología','Página 18','Explicar el criterio utilizado para seleccionar la muestra y justificar su tamaño.','pending',DATE_SUB(CURRENT_TIMESTAMP,INTERVAL 4 DAY)),
            (:project2,:delivery2,:teacher2,'Referencias','Página 31','Unificar las referencias bibliográficas con el formato APA 7.','addressed',DATE_SUB(CURRENT_TIMESTAMP,INTERVAL 3 DAY))",
            ['project'=>$reviewProject,'delivery'=>$delivery,'teacher'=>$teacher,'project2'=>$reviewProject,'delivery2'=>$delivery,'teacher2'=>$teacher]);
        $observation = (int)$this->db->lastInsertId();
        $this->execute("INSERT INTO observation_responses(observation_id,author_id,body,created_at) VALUES(:observation,:student,'La justificación fue incorporada en la versión que estamos preparando.',DATE_SUB(CURRENT_TIMESTAMP,INTERVAL 1 DAY))", ['observation'=>$observation,'student'=>$student]);
        $this->execute("INSERT INTO project_comments(project_id,author_id,body,created_at) VALUES(:project,:teacher,'El avance general es correcto. Prioricen las dos observaciones antes de la próxima entrega.',DATE_SUB(CURRENT_TIMESTAMP,INTERVAL 2 DAY))", ['project'=>$reviewProject,'teacher'=>$teacher]);

        $events = [
            ['TIT-2026-001','Entrega de versión corregida','delivery','high','2026-07-28'],
            ['VIN-2026-001','Validación de documentos finales','review','medium','2026-08-04'],
            ['PIS-2026-001','Reunión de seguimiento','meeting','medium','2026-08-11'],
        ];
        foreach ($events as [$code,$title,$type,$priority,$date]) {
            $this->execute('INSERT INTO project_events(project_id,title,event_type,priority,event_date,description,created_by) VALUES(:project,:title,:type,:priority,:date,:description,:creator)', [
                'project'=>$this->projectIds[$code],'title'=>$title,'type'=>$type,'priority'=>$priority,'date'=>$date,
                'description'=>'Fecha académica demostrativa para validar calendario y alertas.','creator'=>$adminId,
            ]);
        }

        foreach ([['TIT-2026-001',$student,'observation','Tienes observaciones nuevas','Revisa los comentarios registrados por tu tutora.'],['VIN-2026-001',$this->userIds['sofia.lopez.demo@correo.com'],'status_change','Proyecto aprobado','El proyecto está listo para cargar sus documentos finales.'],['PIS-2026-002',$this->userIds['carlos.mendoza.demo@correo.com'],'repository','Proyecto publicado','El proyecto ya se encuentra publicado en el repositorio.']] as [$code,$user,$type,$title,$message]) {
            $this->execute("INSERT INTO notifications(user_id,project_id,type,title,message,action_url,action_label,metadata,created_at) VALUES(:user,:project,:type,:title,:message,:url,'Abrir proyecto',:metadata,DATE_SUB(CURRENT_TIMESTAMP,INTERVAL 1 DAY))", [
                'user'=>$user,'project'=>$this->projectIds[$code],'type'=>$type,'title'=>$title,'message'=>$message,
                'url'=>'index.php?page=project-detail&id='.$this->projectIds[$code],'metadata'=>json_encode(['demo'=>true], JSON_UNESCAPED_UNICODE),
            ]);
        }

        foreach ([['TIT-2026-001','delivery_submitted','delivery',$delivery],['VIN-2026-001','project_approved','project',$this->projectIds['VIN-2026-001']],['PIS-2026-002','project_published','project',$this->projectIds['PIS-2026-002']]] as [$code,$action,$entity,$entityId]) {
            $this->execute("INSERT INTO project_audit_log(project_id,user_id,action,entity_type,entity_id,new_state,reason,created_at) VALUES(:project,:user,:action,:entity,:entity_id,:state,'Actividad demostrativa',DATE_SUB(CURRENT_TIMESTAMP,INTERVAL 1 DAY))", [
                'project'=>$this->projectIds[$code],'user'=>$adminId,'action'=>$action,'entity'=>$entity,'entity_id'=>$entityId,'state'=>json_encode(['demo'=>true]),
            ]);
        }
    }

    private function seedAdminActivity(int $adminId): void
    {
        $this->db->exec("DELETE FROM admin_audit_log WHERE action LIKE 'demo_%'");
        foreach ([['demo_users_imported','user',$this->userIds['ana.torres.demo@correo.com']],['demo_teacher_updated','user',$this->userIds['maribel.fierro.pendiente@local.invalid']],['demo_catalog_configured','project_type',null]] as $index => [$action,$entity,$entityId]) {
            $this->execute("INSERT INTO admin_audit_log(actor_user_id,action,entity_type,entity_id,details,created_at) VALUES(:actor,:action,:entity,:entity_id,:details,DATE_SUB(CURRENT_TIMESTAMP,INTERVAL {$index} DAY))", [
                'actor'=>$adminId,'action'=>$action,'entity'=>$entity,'entity_id'=>$entityId,'details'=>json_encode(['demo'=>true], JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    private function createPrivateFiles(): int
    {
        $specifications = [
            ['TIT-2026-001','Informe_avance_v1.pdf','pdf','application/pdf','delivery'],
            ['TIT-2026-001','Anexos_investigacion.zip','zip','application/zip','annex'],
            ['VIN-2026-001','Informe_final_vinculacion.docx','docx','application/vnd.openxmlformats-officedocument.wordprocessingml.document','final'],
            ['TIT-2026-002','Trabajo_titulacion_final.pdf','pdf','application/pdf','final'],
            ['PIS-2026-002','Informe_proyecto_publicado.pdf','pdf','application/pdf','final'],
            ['PIS-2026-002','Codigo_fuente_demostrativo.zip','zip','application/zip','source'],
        ];
        $inserted = 0;
        foreach ($specifications as [$code,$original,$extension,$mime,$category]) {
            $projectId = $this->projectIds[$code];
            $storageName = hash('sha256', 'tesis-demo-'.$code.'-'.$original).'.'.$extension;
            $directory = ROOT_PATH.'/storage/private/projects/'.$projectId;
            if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) throw new RuntimeException('No se pudo crear el directorio privado de archivos.');
            $path = $directory.'/'.$storageName;
            foreach (glob(ROOT_PATH.'/storage/private/projects/*/'.$storageName) ?: [] as $previousPath) {
                if (realpath($previousPath) !== realpath($path) && is_file($previousPath)) unlink($previousPath);
            }
            if ($extension === 'pdf') $this->writePdf($path, $original);
            elseif ($extension === 'docx') $this->writeDocx($path, $original);
            else $this->writeZip($path, $original);
            $deliveryId = $code === 'TIT-2026-001' ? $this->requiredId("SELECT id FROM project_deliveries WHERE project_id={$projectId} ORDER BY id DESC LIMIT 1", 'Falta la entrega demostrativa.') : null;
            $this->execute("INSERT INTO project_files(project_id,delivery_id,category,original_name,storage_name,storage_path,mime_type,extension,size_bytes,checksum_sha256,uploaded_by)
                VALUES(:project,:delivery,:category,:original,:storage,:path,:mime,:extension,:size,:checksum,:uploader)", [
                'project'=>$projectId,'delivery'=>$deliveryId,'category'=>$category,'original'=>$original,'storage'=>$storageName,
                'path'=>'storage/private/projects/'.$projectId.'/'.$storageName,'mime'=>$mime,'extension'=>$extension,
                'size'=>filesize($path),'checksum'=>hash_file('sha256',$path),'uploader'=>$this->projectCreator($projectId),
            ]);
            $inserted++;
        }
        return $inserted;
    }

    private function writePdf(string $path, string $title): void
    {
        $safe = str_replace(['\\','(',')'], ['\\\\','\(','\)'], $title);
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $stream = "BT /F1 18 Tf 72 700 Td ({$safe}) Tj 0 -34 Td /F1 11 Tf (Documento demostrativo para pruebas administrativas.) Tj ET";
        $objects[] = "<< /Length ".strlen($stream)." >>\nstream\n{$stream}\nendstream";
        $pdf = "%PDF-1.4\n"; $offsets = [0];
        foreach ($objects as $number => $object) { $offsets[] = strlen($pdf); $pdf .= ($number + 1)." 0 obj\n{$object}\nendobj\n"; }
        $xref = strlen($pdf); $pdf .= "xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) $pdf .= sprintf('%010d 00000 n ', $offset)."\n";
        $pdf .= "trailer << /Size ".(count($objects)+1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
        if (file_put_contents($path, $pdf) === false) throw new RuntimeException('No se pudo crear el PDF demostrativo.');
    }

    private function writeDocx(string $path, string $title): void
    {
        $text = htmlspecialchars($title.' — Documento demostrativo para pruebas administrativas.', ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $this->writeStoredZip($path, [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>',
            'word/document.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>'.$text.'</w:t></w:r></w:p><w:sectPr/></w:body></w:document>',
        ]);
    }

    private function writeZip(string $path, string $title): void
    {
        $this->writeStoredZip($path, [
            'LEEME.txt' => $title."\nArchivo de prueba: no contiene información real.\n",
            'src/ejemplo.php' => "<?php\n// Código ficticio para validar la navegación interna del ZIP.\n",
            'docs/notas.md' => "# Documentación de prueba\n\nContenido demostrativo.\n",
        ]);
    }

    /** ZIP sin compresión, suficiente para fixtures y compatible aunque ext-zip no esté activa. */
    private function writeStoredZip(string $path, array $entries): void
    {
        $body = ''; $central = ''; $offset = 0; $count = 0;
        foreach ($entries as $name => $content) {
            $name = str_replace('\\', '/', (string)$name);
            $content = (string)$content;
            $crc = (int)sprintf('%u', crc32($content));
            $size = strlen($content); $nameLength = strlen($name);
            $local = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, 0, 0, $crc, $size, $size, $nameLength, 0).$name.$content;
            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, 0, 0, $crc, $size, $size, $nameLength, 0, 0, 0, 0, 0, $offset).$name;
            $body .= $local; $offset += strlen($local); $count++;
        }
        $archive = $body.$central.pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, strlen($central), strlen($body), 0);
        if (file_put_contents($path, $archive) === false) throw new RuntimeException('No se pudo crear el archivo ZIP demostrativo.');
    }

    private function participant(int $project, int $user, string $role, string $permission, bool $leader): void
    {
        $this->execute('INSERT INTO project_participants(project_id,user_id,role_code,permission_level,is_leader) VALUES(:project,:user,:role,:permission,:leader)', compact('project','user','role','permission','leader'));
    }

    private function projectCreator(int $projectId): int
    {
        return $this->requiredId("SELECT created_by FROM projects WHERE id={$projectId}", 'No se encontró el creador del proyecto.');
    }

    private function execute(string $sql, array $parameters = []): void
    {
        try {
            $statement = $this->db->prepare($sql);
            $statement->execute($parameters);
        } catch (PDOException $exception) {
            throw new RuntimeException($exception->getMessage().' [SQL: '.preg_replace('/\s+/', ' ', trim($sql)).']', 0, $exception);
        }
    }

    private function requiredId(string $sql, string $message): int
    {
        $value = (int)$this->db->query($sql)->fetchColumn();
        if ($value < 1) throw new RuntimeException($message);
        return $value;
    }

    private function pairs(string $sql): array
    {
        $rows = $this->db->query($sql)->fetchAll();
        $result = [];
        foreach ($rows as $row) $result[(string)$row['code']] = (int)$row['id'];
        return $result;
    }

    private function idsFor(string $sql, array $parameters): array
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($parameters);
        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    private function deleteIn(string $table, string $column, array $ids): void
    {
        if (!$ids) return;
        $allowed = [
            'observation_responses'=>'observation_id','project_comments'=>'project_id','project_observations'=>'id',
            'project_files'=>'id','project_deliveries'=>'id','notifications'=>'project_id','project_favorites'=>'project_id',
            'project_downloads'=>'project_id','project_events'=>'project_id','project_audit_log'=>'project_id',
            'project_participants'=>'project_id','project_stages'=>'project_id','projects'=>'id',
        ];
        if (($allowed[$table] ?? null) !== $column) throw new LogicException('Destino de limpieza no permitido.');
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->db->prepare("DELETE FROM {$table} WHERE {$column} IN ({$marks})");
        $statement->execute($ids);
    }
}

try {
    $result = (new AdminDemoSeeder())->run();
    echo "Datos de prueba cargados correctamente.\n";
    echo "Usuarios: {$result['users']} ({$result['students']} estudiantes y {$result['teachers']} docentes)\n";
    echo "Proyectos: {$result['projects']}\n";
    echo "Archivos privados: {$result['files']}\n";
    echo "Contraseña temporal: {$result['temporary_password']}\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "No fue posible cargar los datos de prueba: {$exception->getMessage()}\n");
    exit(1);
}
