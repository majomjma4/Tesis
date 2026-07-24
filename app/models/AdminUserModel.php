<?php
declare(strict_types=1);

final class AdminUserModel
{
    private const ROLES=['student','teacher','administrator'];
    private const STATUSES=['active','inactive','blocked'];
    private const TEMPORARY_PASSWORD='Istel2026+';
    public const LAST_ADMIN_MESSAGE='No es posible realizar esta acción porque esta es la única cuenta con acceso administrativo. Asigna privilegios administrativos a otro docente antes de continuar.';

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
        if(($filters['role']??'')==='administrator'){$where[]='u.is_admin=1';}
        elseif(in_array($filters['role']??'',['student','teacher'],true)){$where[]='r.code=:role';$params['role']=$filters['role'];}
        if(in_array($filters['status']??'',self::STATUSES,true)){$where[]='u.status=:status';$params['status']=$filters['status'];}
        $from=" FROM users u INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN roles r ON r.id=ur.role_id LEFT JOIN student_profiles sp ON sp.user_id=u.id LEFT JOIN student_enrollments se ON se.student_id=u.id AND se.status='active' LEFT JOIN teacher_profiles tp ON tp.user_id=u.id".($where?' WHERE '.implode(' AND ',$where):'');
        $sql="SELECT u.id,u.username,u.full_name,u.email,u.status,u.must_change_password,u.last_login_at,u.created_at,u.is_admin,u.is_initial_admin,r.code role_code,COALESCE(sp.institutional_code,tp.institutional_code,'') institutional_code,sp.career_id,se.academic_period_id,se.semester,tp.academic_title,tp.can_tutor".$from.' ORDER BY u.created_at DESC,u.full_name';
        return PaginationService::run(Database::connection(),'SELECT COUNT(DISTINCT u.id)'.$from,$sql,$params,$pagination?:PaginationService::request());
    }

    public function summary():array
    {
        $row=Database::connection()->query("SELECT COUNT(DISTINCT u.id) total,COUNT(DISTINCT CASE WHEN u.status='active' THEN u.id END) active,COUNT(DISTINCT CASE WHEN u.status='blocked' THEN u.id END) blocked,COUNT(DISTINCT CASE WHEN r.code='student' THEN u.id END) students,COUNT(DISTINCT CASE WHEN r.code='teacher' THEN u.id END) teachers,COUNT(DISTINCT CASE WHEN u.is_admin=1 THEN u.id END) administrators FROM users u INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN roles r ON r.id=ur.role_id")->fetch()?:[];
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
            $previousRole=null;$previousAdmin=false;$initialAdmin=false;
            if($id>0){
                $read=$db->prepare("SELECT u.*,r.code role_code FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE u.id=:id LIMIT 1 FOR UPDATE");
                $read->execute(['id'=>$id]);$previous=$read->fetch();
                if(!$previous)throw new InvalidArgumentException('El usuario ya no existe.');
                $previousRole=(string)$previous['role_code'];$previousAdmin=(bool)$previous['is_admin'];$initialAdmin=(bool)$previous['is_initial_admin'];
                if($data['role']==='administrator'&&!$initialAdmin)throw new InvalidArgumentException('Administrador no es un tipo académico seleccionable.');
                if($initialAdmin)$data['is_admin']=1;
                $willRemainActiveAdmin=(bool)$data['is_admin']&&$data['status']==='active';
                if($previousAdmin&&$previous['status']==='active'&&!$willRemainActiveAdmin)$this->assertAnotherActiveAdministrator($db,$id);
                $statement=$db->prepare('UPDATE users SET username=:username,full_name=:name,email=:email,status=:status,is_admin=:admin,session_version=session_version+1 WHERE id=:id');
                $statement->execute(['username'=>$data['username']?:null,'name'=>$data['full_name'],'email'=>$data['email'],'status'=>$data['status'],'admin'=>$data['is_admin'],'id'=>$id]);
                $userId=$id;$action='user_updated';
            }else{
                $statement=$db->prepare('INSERT INTO users(email,username,password_hash,must_change_password,password_warning_count,temporary_password_expires_at,full_name,is_admin,status) VALUES(:email,:username,:hash,1,0,DATE_ADD(CURRENT_TIMESTAMP,INTERVAL 7 DAY),:name,:admin,:status)');
                $statement->execute(['email'=>$data['email'],'username'=>$data['username']?:null,'hash'=>password_hash(self::TEMPORARY_PASSWORD,PASSWORD_DEFAULT),'name'=>$data['full_name'],'admin'=>$data['is_admin'],'status'=>$data['status']]);
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
            if($previousRole!==null&&$previousRole!==$data['role'])$this->audit($db,$actorId,'user_role_changed',$userId,['from'=>$previousRole,'to'=>$data['role']]);
            if($previousAdmin!==((bool)$data['is_admin']))$this->audit($db,$actorId,$data['is_admin']?'admin_access_granted':'admin_access_revoked',$userId,['role'=>$data['role']]);
            if($initialAdmin&&$data['status']!=='active')$this->audit($db,$actorId,'initial_admin_deactivated',$userId,['status'=>$data['status']]);
            return ['id'=>$userId];
        });
    }

    public function changeStatus(int $id,string $status,int $actorId):void
    {
        if($id<1||!in_array($status,self::STATUSES,true))throw new InvalidArgumentException('El estado seleccionado no es válido.');
        Database::transaction(function(PDO $db)use($id,$status,$actorId):void{
            $read=$db->prepare('SELECT id,status,is_admin,is_initial_admin FROM users WHERE id=:id FOR UPDATE');$read->execute(['id'=>$id]);$user=$read->fetch();
            if(!$user)throw new InvalidArgumentException('El usuario ya no existe.');
            if((bool)$user['is_admin']&&$user['status']==='active'&&$status!=='active')$this->assertAnotherActiveAdministrator($db,$id);
            $db->prepare('UPDATE users SET status=:status,session_version=session_version+1 WHERE id=:id')->execute(['status'=>$status,'id'=>$id]);
            $this->audit($db,$actorId,'user_status_changed',$id,['status'=>$status]);
            if((bool)$user['is_initial_admin']&&$status!=='active')$this->audit($db,$actorId,'initial_admin_deactivated',$id,['status'=>$status]);
            if((bool)$user['is_admin']&&$user['status']!=='active'&&$status==='active')$this->audit($db,$actorId,'admin_access_reactivated',$id,[]);
        });
    }

    public function resetPassword(int $id,string $password,int $actorId):void
    {
        if($id<1)throw new InvalidArgumentException('El usuario no es válido.');
        Database::transaction(function(PDO $db)use($id,$password,$actorId):void{$this->ensureUser($db,$id);$check=$db->prepare('SELECT must_change_password FROM users WHERE id=:id');$check->execute(['id'=>$id]);if((int)$check->fetchColumn()===1)throw new InvalidArgumentException('El usuario todavía utiliza una clave temporal; no es necesario restablecerla.');$db->prepare('UPDATE users SET password_hash=:hash,must_change_password=1,password_warning_count=0,temporary_password_expires_at=DATE_ADD(CURRENT_TIMESTAMP,INTERVAL 7 DAY),session_version=session_version+1 WHERE id=:id')->execute(['hash'=>password_hash($password,PASSWORD_DEFAULT),'id'=>$id]);$this->audit($db,$actorId,'password_reset',$id,[]);});
    }

    public function previewImport(string $content,array $config):array
    {
        $config=$this->withInstitutionalDefaults($config);
        if(!in_array($config['role']??'',['student','teacher'],true))throw new InvalidArgumentException('Selecciona Estudiantes o Docentes para la importación.');
        if($config['role']==='student'&&(($config['semester']??0)<1||$config['semester']>4))throw new InvalidArgumentException('Selecciona un semestre válido entre primero y cuarto.');
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
        if($id<1&&$data['role']==='administrator')throw new InvalidArgumentException('Las cuentas nuevas deben ser Estudiante o Docente.');
        if(!empty($data['is_admin'])&&$data['role']!=='teacher')throw new InvalidArgumentException('El acceso administrativo solo puede asignarse a un docente.');
        if(!in_array($data['status'],self::STATUSES,true))throw new InvalidArgumentException('Selecciona un estado válido.');
        $check=Database::connection()->prepare('SELECT id FROM users WHERE email=:email AND id<>:id LIMIT 1');$check->execute(['email'=>$data['email'],'id'=>$id]);
        if($check->fetch())throw new InvalidArgumentException('Ya existe una cuenta con ese correo.');
        if($data['username']!==''&&!preg_match('/^[a-zA-Z0-9._-]{3,80}$/',$data['username']))throw new InvalidArgumentException('El usuario debe tener entre 3 y 80 caracteres y usar solo letras, números, punto, guion o guion bajo.');
        if($data['username']!==''){$check=Database::connection()->prepare('SELECT id FROM users WHERE username=:username AND id<>:id LIMIT 1');$check->execute(['username'=>$data['username'],'id'=>$id]);if($check->fetch())throw new InvalidArgumentException('Ese nombre de usuario ya está registrado.');}
        if(in_array($data['role'],['student','teacher'],true)&&!preg_match('/^\d{10}$/',$data['institutional_code']))throw new InvalidArgumentException('La cédula debe contener exactamente 10 dígitos.');
        if($data['role']==='student'&&($data['career_id']<1||$data['academic_period_id']<1||$data['semester']<1||$data['semester']>4))throw new InvalidArgumentException('Completa el semestre del estudiante entre primero y cuarto.');
    }

    private function withInstitutionalDefaults(array $data):array
    {
        $catalogs=$this->catalogs();
        if(!$catalogs['career']||!$catalogs['period'])throw new InvalidArgumentException('Configura la carrera Desarrollo de Software y un periodo académico activo antes de continuar.');
        $data['career_id']=(int)$catalogs['career']['id'];$data['academic_period_id']=(int)$catalogs['period']['id'];$data['period_name']=(string)$catalogs['period']['name'];$data['is_admin']=!empty($data['is_admin'])?1:0;
        return $data;
    }

    private function assertAnotherActiveAdministrator(PDO $db,int $excludedId):void
    {
        $statement=$db->prepare("SELECT COUNT(*) FROM users WHERE is_admin=1 AND status='active' AND deleted_at IS NULL AND purged_at IS NULL AND id<>:id");
        $statement->execute(['id'=>$excludedId]);
        if((int)$statement->fetchColumn()<1)throw new InvalidArgumentException(self::LAST_ADMIN_MESSAGE);
    }

    private function ensureUser(PDO $db,int $id):void{$statement=$db->prepare('SELECT id FROM users WHERE id=:id');$statement->execute(['id'=>$id]);if(!$statement->fetchColumn())throw new InvalidArgumentException('El usuario ya no existe.');}
    private function audit(PDO $db,int $actorId,string $action,int $targetId,array $details):void
    {
        $read=$db->prepare('SELECT full_name FROM users WHERE id=:id');$read->execute(['id'=>$targetId]);
        $element=(string)($read->fetchColumn()?:($targetId?'Usuario #'.$targetId:'Usuarios importados'));
        $status=(string)($details['status']??'');
        $labels=[
            'user_created'=>'Creó el usuario '.$element,
            'user_updated'=>'Editó el usuario '.$element,
            'user_role_changed'=>'Cambió el rol de '.$element,
            'admin_access_granted'=>'Asignó acceso administrativo a '.$element,
            'admin_access_revoked'=>'Retiró el acceso administrativo de '.$element,
            'admin_access_reactivated'=>'Reactivó una cuenta con acceso administrativo: '.$element,
            'initial_admin_deactivated'=>'Desactivó la cuenta administrativa inicial '.$element,
            'user_status_changed'=>($status==='blocked'?'Bloqueó':($status==='active'?'Desbloqueó':'Cambió el estado de')).' '.$element,
            'users_bulk_imported'=>'Creó '.(int)($details['count']??0).' usuarios mediante importación',
            'password_reset'=>'Restableció la contraseña de '.$element,
        ];
        (new AdminActivityService($db))->record($actorId,$action,$labels[$action]??$action,'Usuarios','user',$targetId?:null,$element,'correct',$details);
    }
}
