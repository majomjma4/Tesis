<?php
declare(strict_types=1);

final class AdminUserModel
{
    private const ROLES=['student','teacher','administrator'];
    private const STATUSES=['active','inactive','blocked'];
    private const TEMPORARY_PASSWORD='Istel2026+';

    public function listing(array $filters=[],array $pagination=[]):array
    {
        $where=['u.deleted_at IS NULL','u.purged_at IS NULL'];$params=[];
        if(($filters['search']??'')!==''){
            $term='%'.$filters['search'].'%';
            if(ctype_digit((string)$filters['search'])){
                $where[]="COALESCE(sp.institutional_code,tp.institutional_code,'') LIKE :search_code";
                $params['search_code']=$term;
            }else{
                $where[]="(u.full_name LIKE :search_name OR u.email LIKE :search_email OR COALESCE(u.username,'') LIKE :search_username)";
                $params=['search_name'=>$term,'search_email'=>$term,'search_username'=>$term];
            }
        }
        if(in_array($filters['role']??'',self::ROLES,true)){$where[]='r.code=:role';$params['role']=$filters['role'];}
        if(in_array($filters['status']??'',self::STATUSES,true)){$where[]='u.status=:status';$params['status']=$filters['status'];}
        $from=" FROM users u INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN roles r ON r.id=ur.role_id LEFT JOIN student_profiles sp ON sp.user_id=u.id LEFT JOIN student_enrollments se ON se.student_id=u.id AND se.status='active' LEFT JOIN teacher_profiles tp ON tp.user_id=u.id".($where?' WHERE '.implode(' AND ',$where):'');
        $sql="SELECT u.id,u.username,u.full_name,u.email,u.status,u.must_change_password,u.last_login_at,u.created_at,r.code role_code,COALESCE(sp.institutional_code,tp.institutional_code,'') institutional_code,sp.career_id,se.academic_period_id,se.semester,tp.academic_title,tp.can_tutor".$from.' ORDER BY u.created_at DESC,u.full_name';
        return PaginationService::run(Database::connection(),'SELECT COUNT(DISTINCT u.id)'.$from,$sql,$params,$pagination?:PaginationService::request());
    }

    public function summary():array
    {
        $row=Database::connection()->query("SELECT COUNT(DISTINCT u.id) total,COUNT(DISTINCT CASE WHEN u.status='active' THEN u.id END) active,COUNT(DISTINCT CASE WHEN u.status='blocked' THEN u.id END) blocked,COUNT(DISTINCT CASE WHEN r.code='student' THEN u.id END) students,COUNT(DISTINCT CASE WHEN r.code='teacher' THEN u.id END) teachers,COUNT(DISTINCT CASE WHEN r.code='administrator' THEN u.id END) administrators FROM users u INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN roles r ON r.id=ur.role_id")->fetch()?:[];
        return array_map('intval',$row);
    }

    public function catalogs():array
    {
        $db=Database::connection();
        $career=$db->query("SELECT id,'Desarrollo de Software' name FROM careers WHERE is_active=1 AND (code='TDS' OR name LIKE '%Desarrollo de Software%') ORDER BY (code='TDS') DESC,id LIMIT 1")->fetch()?:null;
        $period=$db->query("SELECT id,name FROM academic_periods WHERE status='active' ORDER BY starts_on DESC,id DESC LIMIT 1")->fetch()?:null;
        return ['career'=>$career,'period'=>$period];
    }

    public function save(array $data,int $id,int $actorId):array
    {
        $data=$this->withInstitutionalDefaults($data);$this->validate($data,$id);
        return Database::transaction(function(PDO $db)use($data,$id,$actorId):array{
            if($id>0){
                $this->ensureUser($db,$id);
                $statement=$db->prepare('UPDATE users SET username=:username,full_name=:name,email=:email,status=:status,session_version=session_version+1 WHERE id=:id');
                $statement->execute(['username'=>$data['username']?:null,'name'=>$data['full_name'],'email'=>$data['email'],'status'=>$data['status'],'id'=>$id]);
                $userId=$id;$action='user_updated';
            }else{
                $statement=$db->prepare('INSERT INTO users(email,username,password_hash,must_change_password,password_warning_count,temporary_password_expires_at,full_name,status) VALUES(:email,:username,:hash,1,0,DATE_ADD(CURRENT_TIMESTAMP,INTERVAL 7 DAY),:name,:status)');
                $statement->execute(['email'=>$data['email'],'username'=>$data['username']?:null,'hash'=>password_hash(self::TEMPORARY_PASSWORD,PASSWORD_DEFAULT),'name'=>$data['full_name'],'status'=>$data['status']]);
                $userId=(int)$db->lastInsertId();$action='user_created';
            }
            $role=$db->prepare('SELECT id FROM roles WHERE code=:code');$role->execute(['code'=>$data['role']]);$roleId=(int)$role->fetchColumn();
            if(!$roleId)throw new InvalidArgumentException('El rol seleccionado no está disponible.');
            $db->prepare('DELETE FROM user_roles WHERE user_id=:id')->execute(['id'=>$userId]);
            $db->prepare('INSERT INTO user_roles(user_id,role_id) VALUES(:user,:role)')->execute(['user'=>$userId,'role'=>$roleId]);
            $db->prepare('DELETE FROM student_enrollments WHERE student_id=:id')->execute(['id'=>$userId]);
            $db->prepare('DELETE FROM student_profiles WHERE user_id=:id')->execute(['id'=>$userId]);
            $db->prepare('DELETE FROM teacher_profiles WHERE user_id=:id')->execute(['id'=>$userId]);
            if($data['role']==='student'){
                $db->prepare('INSERT INTO student_profiles(user_id,institutional_code,career_id) VALUES(:id,:code,:career)')->execute(['id'=>$userId,'code'=>$data['institutional_code'],'career'=>$data['career_id']]);
                $db->prepare("INSERT INTO student_enrollments(student_id,academic_period_id,career_id,semester,status) VALUES(:id,:period,:career,:semester,'active')")->execute(['id'=>$userId,'period'=>$data['academic_period_id'],'career'=>$data['career_id'],'semester'=>$data['semester']]);
            }elseif($data['role']==='teacher'){
                $db->prepare('INSERT INTO teacher_profiles(user_id,institutional_code,academic_title,can_tutor) VALUES(:id,:code,:title,:tutor)')->execute(['id'=>$userId,'code'=>$data['institutional_code'],'title'=>$data['academic_title']?:null,'tutor'=>$data['can_tutor']]);
            }
            $this->audit($db,$actorId,$action,$userId,['role'=>$data['role'],'status'=>$data['status']]);
            return ['id'=>$userId];
        });
    }

    public function changeStatus(int $id,string $status,int $actorId):void
    {
        if($id<1||!in_array($status,self::STATUSES,true))throw new InvalidArgumentException('El estado seleccionado no es válido.');
        Database::transaction(function(PDO $db)use($id,$status,$actorId):void{$this->ensureUser($db,$id);$db->prepare('UPDATE users SET status=:status,session_version=session_version+1 WHERE id=:id')->execute(['status'=>$status,'id'=>$id]);$this->audit($db,$actorId,'user_status_changed',$id,['status'=>$status]);});
    }

    public function resetPassword(int $id,string $password,int $actorId):void
    {
        if($id<1)throw new InvalidArgumentException('El usuario no es válido.');
        Database::transaction(function(PDO $db)use($id,$password,$actorId):void{$this->ensureUser($db,$id);$db->prepare('UPDATE users SET password_hash=:hash,must_change_password=1,password_warning_count=0,temporary_password_expires_at=DATE_ADD(CURRENT_TIMESTAMP,INTERVAL 7 DAY),session_version=session_version+1 WHERE id=:id')->execute(['hash'=>password_hash($password,PASSWORD_DEFAULT),'id'=>$id]);$this->audit($db,$actorId,'password_reset',$id,[]);});
    }

    public function previewImport(string $content,array $config):array
    {
        $config=$this->withInstitutionalDefaults($config);
        if(!in_array($config['role']??'',['student','teacher'],true))throw new InvalidArgumentException('Selecciona Estudiantes o Docentes para la importación.');
        if($config['role']==='student'&&(($config['semester']??0)<1||$config['semester']>10))throw new InvalidArgumentException('Selecciona el semestre antes de continuar.');
        $rows=$this->parseImportRows($content);$emails=[];$codes=[];$usernames=[];$result=[];$valid=0;$db=Database::connection();
        $emailCheck=$db->prepare('SELECT COUNT(*) FROM users WHERE email=:email');
        $usernameCheck=$db->prepare('SELECT COUNT(*) FROM users WHERE username=:username');
        $codeCheck=$db->prepare('SELECT (SELECT COUNT(*) FROM student_profiles WHERE institutional_code=:student)+(SELECT COUNT(*) FROM teacher_profiles WHERE institutional_code=:teacher)');
        foreach($rows as $index=>$row){
            $errors=[];$name=trim((string)($row[0]??''));$email=mb_strtolower(trim((string)($row[1]??'')));$code=trim((string)($row[2]??''));$extra=trim((string)($row[3]??''));
            $username=$config['role']==='student'?$extra:'';$title=$config['role']==='teacher'?$extra:'';
            if(mb_strlen($name)<3)$errors[]='Nombre incompleto';
            if(!filter_var($email,FILTER_VALIDATE_EMAIL))$errors[]='Correo inválido';
            if(!preg_match('/^\d{10}$/',$code))$errors[]='La cédula debe tener 10 dígitos';
            if($username!==''&&!preg_match('/^[a-zA-Z0-9._-]{3,80}$/',$username))$errors[]='Usuario inválido';
            if(isset($emails[$email]))$errors[]='Correo repetido en la lista';
            if(isset($codes[$code]))$errors[]='Identificación repetida en la lista';
            if($username!==''&&isset($usernames[mb_strtolower($username)]))$errors[]='Usuario repetido en la lista';
            if(!$errors&&$email!==''){$emailCheck->execute(['email'=>$email]);if((int)$emailCheck->fetchColumn()>0)$errors[]='El correo ya está registrado';}
            if(!$errors&&$username!==''){$usernameCheck->execute(['username'=>$username]);if((int)$usernameCheck->fetchColumn()>0)$errors[]='El usuario ya está registrado';}
            if(!$errors&&$code!==''){$codeCheck->execute(['student'=>$code,'teacher'=>$code]);if((int)$codeCheck->fetchColumn()>0)$errors[]='La identificación ya está registrada';}
            $emails[$email]=true;$codes[$code]=true;if($username!=='')$usernames[mb_strtolower($username)]=true;if(!$errors)$valid++;
            $result[]=['line'=>$index+1,'name'=>$name,'email'=>$email,'code'=>$code,'username'=>$username,'academic_title'=>$title,'password'=>self::TEMPORARY_PASSWORD,'valid'=>!$errors,'error'=>implode('. ',$errors)];
        }
        return ['rows'=>$result,'total'=>count($result),'valid'=>$valid,'invalid'=>count($result)-$valid,'config'=>['role'=>$config['role'],'career'=>'Desarrollo de Software','period'=>$config['period_name'],'semester'=>$config['semester']??null,'can_tutor'=>(int)($config['can_tutor']??0)]];
    }

    public function bulkImport(string $content,array $config,int $actorId):array
    {
        $config=$this->withInstitutionalDefaults($config);$preview=$this->previewImport($content,$config);
        if($preview['invalid']>0)throw new InvalidArgumentException('Corrige las filas inválidas antes de importar.');
        if($preview['total']<1)throw new InvalidArgumentException('La lista no contiene usuarios.');
        return Database::transaction(function(PDO $db)use($preview,$config,$actorId):array{
            $role=$db->prepare('SELECT id FROM roles WHERE code=:code');$role->execute(['code'=>$config['role']]);$roleId=(int)$role->fetchColumn();
            if(!$roleId)throw new InvalidArgumentException('El rol seleccionado no está disponible.');
            $userInsert=$db->prepare('INSERT INTO users(email,username,password_hash,must_change_password,password_warning_count,temporary_password_expires_at,full_name,status) VALUES(:email,:username,:hash,1,0,DATE_ADD(CURRENT_TIMESTAMP,INTERVAL 7 DAY),:name,\'active\')');
            $userRole=$db->prepare('INSERT INTO user_roles(user_id,role_id) VALUES(:user,:role)');
            $student=$db->prepare('INSERT INTO student_profiles(user_id,institutional_code,career_id) VALUES(:id,:code,:career)');
            $enrollment=$db->prepare("INSERT INTO student_enrollments(student_id,academic_period_id,career_id,semester,status) VALUES(:id,:period,:career,:semester,'active')");
            $teacher=$db->prepare('INSERT INTO teacher_profiles(user_id,institutional_code,academic_title,can_tutor) VALUES(:id,:code,:title,:tutor)');
            $hash=password_hash(self::TEMPORARY_PASSWORD,PASSWORD_DEFAULT);
            foreach($preview['rows'] as $row){
                $userInsert->execute(['email'=>$row['email'],'username'=>$row['username']?:null,'hash'=>$hash,'name'=>$row['name']]);$id=(int)$db->lastInsertId();
                $userRole->execute(['user'=>$id,'role'=>$roleId]);
                if($config['role']==='student'){$student->execute(['id'=>$id,'code'=>$row['code'],'career'=>$config['career_id']]);$enrollment->execute(['id'=>$id,'period'=>$config['academic_period_id'],'career'=>$config['career_id'],'semester'=>$config['semester']]);}
                else{$teacher->execute(['id'=>$id,'code'=>$row['code'],'title'=>$row['academic_title']?:null,'tutor'=>$config['can_tutor']]);}
            }
            $this->audit($db,$actorId,'users_bulk_imported',0,['role'=>$config['role'],'count'=>$preview['total']]);
            return ['created'=>$preview['total'],'role'=>$config['role']];
        });
    }

    private function parseImportRows(string $content):array
    {
        $content=preg_replace('/^\xEF\xBB\xBF/','',trim($content));
        if($content==='')throw new InvalidArgumentException('Pega una lista o selecciona un archivo.');
        if(strlen($content)>1048576)throw new InvalidArgumentException('La lista no puede superar 1 MB.');
        $lines=preg_split('/\R/u',$content)?:[];$lines=array_values(array_filter($lines,static fn(string $line):bool=>trim($line)!==''));
        if(count($lines)>500)throw new InvalidArgumentException('Puedes importar máximo 500 usuarios por operación.');
        $first=$lines[0]??'';$counts=[','=>substr_count($first,','),';'=>substr_count($first,';'),"\t"=>substr_count($first,"\t")];arsort($counts);$delimiter=(string)array_key_first($counts);
        if(($counts[$delimiter]??0)<1)throw new InvalidArgumentException('Usa columnas separadas por coma, punto y coma o tabulación.');
        $rows=array_map(static fn(string $line):array=>str_getcsv($line,$delimiter),$lines);$header=mb_strtolower(implode(' ',array_map('trim',$rows[0]??[])));
        if(str_contains($header,'correo')||str_contains($header,'email'))array_shift($rows);
        if(!$rows)throw new InvalidArgumentException('El archivo solo contiene encabezados.');
        return $rows;
    }

    private function validate(array $data,int $id):void
    {
        if(mb_strlen($data['full_name'])<3)throw new InvalidArgumentException('Ingresa el nombre completo.');
        if(!filter_var($data['email'],FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Ingresa un correo válido.');
        if(!in_array($data['role'],self::ROLES,true))throw new InvalidArgumentException('Selecciona un rol válido.');
        if(!in_array($data['status'],self::STATUSES,true))throw new InvalidArgumentException('Selecciona un estado válido.');
        $check=Database::connection()->prepare('SELECT id FROM users WHERE email=:email AND id<>:id LIMIT 1');$check->execute(['email'=>$data['email'],'id'=>$id]);
        if($check->fetch())throw new InvalidArgumentException('Ya existe una cuenta con ese correo.');
        if($data['username']!==''&&!preg_match('/^[a-zA-Z0-9._-]{3,80}$/',$data['username']))throw new InvalidArgumentException('El usuario debe tener entre 3 y 80 caracteres y usar solo letras, números, punto, guion o guion bajo.');
        if($data['username']!==''){$check=Database::connection()->prepare('SELECT id FROM users WHERE username=:username AND id<>:id LIMIT 1');$check->execute(['username'=>$data['username'],'id'=>$id]);if($check->fetch())throw new InvalidArgumentException('Ese nombre de usuario ya está registrado.');}
        if(in_array($data['role'],['student','teacher'],true)&&!preg_match('/^\d{10}$/',$data['institutional_code']))throw new InvalidArgumentException('La cédula debe contener exactamente 10 dígitos.');
        if($data['role']==='student'&&($data['career_id']<1||$data['academic_period_id']<1||$data['semester']<1||$data['semester']>10))throw new InvalidArgumentException('Completa el semestre del estudiante.');
    }

    private function withInstitutionalDefaults(array $data):array
    {
        $catalogs=$this->catalogs();
        if(!$catalogs['career']||!$catalogs['period'])throw new InvalidArgumentException('Configura la carrera Desarrollo de Software y un periodo académico activo antes de continuar.');
        $data['career_id']=(int)$catalogs['career']['id'];$data['academic_period_id']=(int)$catalogs['period']['id'];$data['period_name']=(string)$catalogs['period']['name'];
        return $data;
    }

    private function ensureUser(PDO $db,int $id):void{$statement=$db->prepare('SELECT id FROM users WHERE id=:id');$statement->execute(['id'=>$id]);if(!$statement->fetchColumn())throw new InvalidArgumentException('El usuario ya no existe.');}
    private function audit(PDO $db,int $actorId,string $action,int $targetId,array $details):void{$statement=$db->prepare('INSERT INTO admin_audit_log(actor_user_id,action,entity_type,entity_id,details,ip_address,user_agent) VALUES(:actor,:action,\'user\',:entity,:details,:ip,:agent)');$statement->execute(['actor'=>$actorId,'action'=>$action,'entity'=>$targetId,'details'=>json_encode($details,JSON_UNESCAPED_UNICODE),'ip'=>mb_substr((string)($_SERVER['REMOTE_ADDR']??''),0,45)?:null,'agent'=>mb_substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500)?:null]);}
}
