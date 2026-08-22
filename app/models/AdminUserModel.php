<?php
declare(strict_types=1);

final class AdminUserModel
{
    private const ROLES=['student','teacher','administrator'];
    private const STATUSES=['active','inactive','blocked'];
    public const LAST_ADMIN_MESSAGE='No es posible realizar esta acción porque esta es la única cuenta con acceso administrativo. Asigna privilegios administrativos a otro docente antes de continuar.';

    public function listing(array $filters=[],array $pagination=[]):array
    {
        $where=['u.deleted_at IS NULL','u.purged_at IS NULL'];$params=[];
        if(($filters['search']??'')!==''){
            $term='%'.$this->escapeLikeTerm((string)$filters['search']).'%';
            $where[]="(u.full_name LIKE :search_name ESCAPE '!' OR u.email LIKE :search_email ESCAPE '!' OR COALESCE(u.username,'') LIKE :search_username ESCAPE '!' OR COALESCE(sp.institutional_code,tp.institutional_code,'') LIKE :search_code ESCAPE '!')";
            $params['search_name']=$term;
            $params['search_email']=$term;
            $params['search_username']=$term;
            $params['search_code']=$term;
        }
        if(($filters['role']??'')==='administrator'){$where[]='u.is_admin=1';}
        elseif(in_array($filters['role']??'',['student','teacher'],true)){$where[]='r.code=:role';$params['role']=$filters['role'];}
        if(in_array($filters['status']??'',self::STATUSES,true)){$where[]='u.status=:status';$params['status']=$filters['status'];}
        $from=" FROM users u INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN roles r ON r.id=ur.role_id LEFT JOIN student_profiles sp ON sp.user_id=u.id LEFT JOIN student_enrollments se ON se.student_id=u.id AND se.status='active' LEFT JOIN teacher_profiles tp ON tp.user_id=u.id".($where?' WHERE '.implode(' AND ',$where):'');
        $sql="SELECT u.id,u.username,u.full_name,u.email,u.status,u.must_change_password,u.last_login_at,u.created_at,u.is_admin,u.is_initial_admin,r.code role_code,COALESCE(sp.institutional_code,tp.institutional_code,'') institutional_code,sp.career_id,se.academic_period_id,se.semester,tp.academic_title,tp.can_tutor,tp.can_manage_thesis".$from.' ORDER BY u.created_at DESC,u.full_name';
        return PaginationService::run(Database::connection(),'SELECT COUNT(DISTINCT u.id)'.$from,$sql,$params,$pagination?:PaginationService::request());
    }

    public function summary():array
    {
        $row=Database::connection()->query("SELECT COUNT(DISTINCT u.id) total,COUNT(DISTINCT CASE WHEN u.status='active' THEN u.id END) active,COUNT(DISTINCT CASE WHEN u.status='blocked' THEN u.id END) blocked,COUNT(DISTINCT CASE WHEN r.code='student' THEN u.id END) students,COUNT(DISTINCT CASE WHEN r.code='teacher' THEN u.id END) teachers,COUNT(DISTINCT CASE WHEN u.is_admin=1 THEN u.id END) administrators FROM users u INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN roles r ON r.id=ur.role_id WHERE u.deleted_at IS NULL AND u.purged_at IS NULL")->fetch()?:[];
        return array_map('intval',$row);
    }

    private function escapeLikeTerm(string $value):string
    {
        return str_replace(['!','%','_'],['!!','!%','!_'],$value);
    }

    public function catalogs():array
    {
        $db=Database::connection();
        $career=$db->query("SELECT id,'Desarrollo de Software' name FROM careers WHERE is_active=1 AND (code='TDS' OR name LIKE '%Desarrollo de Software%') ORDER BY (code='TDS') DESC,id LIMIT 1")->fetch()?:null;
        $periodRows=$db->query("SELECT id,name FROM academic_periods WHERE status='active' ORDER BY starts_on DESC,id DESC")->fetchAll();
        if(count($periodRows)>1)throw new RuntimeException('La configuracion academica tiene mas de un periodo activo.');
        $period=$periodRows[0]??null;
        return ['career'=>$career,'period'=>$period];
    }

    public function save(array $data,int $id,int $actorId):array
    {
        $data=$this->withInstitutionalDefaults($data);
        if($id<1)$data['status']='active';
        if($id<1&&$data['role']==='teacher')$data['can_tutor']=1;
        $this->validate($data,$id);
        $temporary=$id<1?$this->temporaryPolicy():null;
        $operation=function()use($data,$id,$actorId,$temporary):array{return Database::transaction(function(PDO $db)use($data,$id,$actorId,$temporary):array{
            if(in_array($data['role'],['student','teacher'],true)&&$this->institutionalCodeExists((string)$data['institutional_code'],$id>0?$id:null))throw new InvalidArgumentException('La cédula ya está registrada en otra cuenta.');
            $previous=[];$previousRole=null;$previousAdmin=false;$initialAdmin=false;
            if($id>0){
                $read=$db->prepare("SELECT u.*,r.code role_code,tp.can_tutor,tp.can_manage_thesis FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id LEFT JOIN teacher_profiles tp ON tp.user_id=u.id WHERE u.id=:id AND u.deleted_at IS NULL AND u.purged_at IS NULL LIMIT 1 FOR UPDATE");
                $read->execute(['id'=>$id]);$previous=$read->fetch();
                if(!$previous)throw new InvalidArgumentException('El usuario ya no está disponible para esta operación.');
                if(($data['status']??'')==='')$data['status']=(string)$previous['status'];
                if($data['can_tutor']===null)$data['can_tutor']=(int)($previous['can_tutor']??0);
                $previousRole=(string)$previous['role_code'];$previousAdmin=(bool)$previous['is_admin'];$initialAdmin=(bool)$previous['is_initial_admin'];
                if($data['role']==='administrator'&&!$initialAdmin)throw new InvalidArgumentException('Administrador no es un tipo académico seleccionable.');
                if($initialAdmin)$data['is_admin']=1;
                $willRemainActiveAdmin=(bool)$data['is_admin']&&$data['status']==='active';
                if($previousAdmin&&$previous['status']==='active'&&!$willRemainActiveAdmin)$this->assertAnotherActiveAdministrator($db,$id);
                $profileChanged=(string)($previous['username']??'')!==($data['username']?:'')||(string)$previous['full_name']!==$data['full_name']||(string)$previous['email']!==$data['email'];
                $profileVersionSql=$profileChanged?',profile_version=profile_version+1':'';
                $statement=$db->prepare('UPDATE users SET username=:username,full_name=:name,email=:email,status=:status,is_admin=:admin,session_version=session_version+1'.$profileVersionSql.' WHERE id=:id');
                $statement->execute(['username'=>$data['username']?:null,'name'=>$data['full_name'],'email'=>$data['email'],'status'=>$data['status'],'admin'=>$data['is_admin'],'id'=>$id]);
                $userId=$id;$action='user_updated';
            }else{
                $statement=$db->prepare('INSERT INTO users(email,username,password_hash,must_change_password,password_warning_count,temporary_password_expires_at,full_name,is_admin,status) VALUES(:email,:username,:hash,:force,0,DATE_ADD(CURRENT_TIMESTAMP,INTERVAL '.$temporary['days'].' DAY),:name,:admin,:status)');
                $statement->execute(['email'=>$data['email'],'username'=>$data['username']?:null,'hash'=>password_hash($temporary['password'],PASSWORD_DEFAULT),'force'=>$temporary['force_change']?1:0,'name'=>$data['full_name'],'admin'=>$data['is_admin'],'status'=>$data['status']]);
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
                $db->prepare('INSERT INTO teacher_profiles(user_id,institutional_code,academic_title,can_tutor,can_manage_thesis) VALUES(:id,:code,:title,:tutor,:thesis)')->execute(['id'=>$userId,'code'=>$data['institutional_code'],'title'=>$data['academic_title']?:null,'tutor'=>$data['can_tutor'],'thesis'=>$data['can_manage_thesis']]);
            }
            $this->audit($db,$actorId,$action,$userId,['role'=>$data['role'],'status'=>$data['status']]);
            if($previousRole!==null&&$previousRole!==$data['role'])$this->audit($db,$actorId,'user_role_changed',$userId,['from'=>$previousRole,'to'=>$data['role']]);
            if($previousAdmin!==((bool)$data['is_admin']))$this->audit($db,$actorId,$data['is_admin']?'admin_access_granted':'admin_access_revoked',$userId,['role'=>$data['role']]);
            $previousThesis=(int)($previous['can_manage_thesis']??0);
            if($previousThesis!==$data['can_manage_thesis'])$this->audit($db,$actorId,$data['can_manage_thesis']?'thesis_management_granted':'thesis_management_revoked',$userId,['role'=>$data['role'],'can_manage_thesis'=>$data['can_manage_thesis']]);
            if($initialAdmin&&$data['status']!=='active')$this->audit($db,$actorId,'initial_admin_deactivated',$userId,['status'=>$data['status']]);
            return ['id'=>$userId];
        });};
        if(in_array($data['role'],['student','teacher'],true)){
            $currentCode=$id>0?$this->institutionalCodeForUser($id):'';
            if($id<1||$currentCode!==(string)$data['institutional_code'])return $this->withIdentityLock((string)$data['institutional_code'],$operation);
        }
        return $operation();
    }

    public function changeStatus(int $id,string $status,int $actorId):void
    {
        if($id<1||!in_array($status,self::STATUSES,true))throw new InvalidArgumentException('El estado seleccionado no es válido.');
        Database::transaction(function(PDO $db)use($id,$status,$actorId):void{
            $read=$db->prepare('SELECT id,status,is_admin,is_initial_admin FROM users WHERE id=:id AND deleted_at IS NULL AND purged_at IS NULL FOR UPDATE');$read->execute(['id'=>$id]);$user=$read->fetch();
            if(!$user)throw new InvalidArgumentException('El usuario ya no está disponible para esta operación.');
            if((bool)$user['is_admin']&&$user['status']==='active'&&$status!=='active')$this->assertAnotherActiveAdministrator($db,$id);
            $db->prepare('UPDATE users SET status=:status,session_version=session_version+1 WHERE id=:id')->execute(['status'=>$status,'id'=>$id]);
            $this->audit($db,$actorId,'user_status_changed',$id,['status'=>$status]);
            if((bool)$user['is_initial_admin']&&$status!=='active')$this->audit($db,$actorId,'initial_admin_deactivated',$id,['status'=>$status]);
            if((bool)$user['is_admin']&&$user['status']!=='active'&&$status==='active')$this->audit($db,$actorId,'admin_access_reactivated',$id,[]);
        });
    }

    public function resetPassword(int $id,int $actorId):void
    {
        if($id<1)throw new InvalidArgumentException('El usuario no es válido.');
        $temporary=$this->temporaryPolicy();Database::transaction(function(PDO $db)use($id,$actorId,$temporary):void{$this->ensureManageableUser($db,$id);$check=$db->prepare('SELECT must_change_password FROM users WHERE id=:id FOR UPDATE');$check->execute(['id'=>$id]);if((int)$check->fetchColumn()===1)throw new InvalidArgumentException('El usuario todavía utiliza una clave temporal; no es necesario restablecerla.');$db->prepare('UPDATE users SET password_hash=:hash,must_change_password=:force,password_warning_count=0,temporary_password_expires_at=DATE_ADD(CURRENT_TIMESTAMP,INTERVAL '.$temporary['days'].' DAY),session_version=session_version+1 WHERE id=:id')->execute(['hash'=>password_hash($temporary['password'],PASSWORD_DEFAULT),'force'=>$temporary['force_change']?1:0,'id'=>$id]);$this->audit($db,$actorId,'password_reset',$id,[]);});
    }

    public function previewImport(string $content,array $config):array
    {
        $config=$this->withInstitutionalDefaults($config);
        if(!in_array($config['role']??'',['student','teacher'],true))throw new InvalidArgumentException('Selecciona Estudiantes o Docentes para la importación.');
        if($config['role']==='student'&&(($config['semester']??0)<1||$config['semester']>4))throw new InvalidArgumentException('Selecciona un semestre válido entre primero y cuarto.');
        $parsed=$this->parseImportRows($content,(string)$config['role']);$rows=$parsed['rows'];$emails=[];$codes=[];$usernames=[];$result=[];$valid=0;$db=Database::connection();
        $emailCheck=$db->prepare('SELECT COUNT(*) FROM users WHERE email=:email');
        $usernameCheck=$db->prepare('SELECT COUNT(*) FROM users WHERE username=:username');
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
            if(!$errors&&$code!==''){if($this->institutionalCodeExists($code))$errors[]='La identificación ya está registrada';}
            $emails[$email]=true;$codes[$code]=true;if($username!=='')$usernames[mb_strtolower($username)]=true;if(!$errors)$valid++;
            $result[]=['line'=>$index+1,'name'=>$name,'email'=>$email,'code'=>$code,'username'=>$username,'academic_title'=>$title,'valid'=>!$errors,'error'=>implode('. ',$errors)];
        }
        return ['rows'=>$result,'total'=>count($result),'valid'=>$valid,'invalid'=>count($result)-$valid,'header_detected'=>(bool)$parsed['header_detected'],'unused_columns'=>(int)$parsed['unused_columns'],'config'=>['role'=>$config['role'],'career'=>'Desarrollo de Software','period'=>$config['period_name'],'semester'=>$config['semester']??null,'can_tutor'=>1]];
    }

    public function bulkImport(string $content,array $config,int $actorId):array
    {
        $config=$this->withInstitutionalDefaults($config);$preview=$this->previewImport($content,$config);
        if($preview['invalid']>0)throw new InvalidArgumentException('Corrige las filas inválidas antes de importar.');
        if($preview['total']<1)throw new InvalidArgumentException('La lista no contiene usuarios.');
        $temporary=$this->temporaryPolicy();$locks=[];
        try{
            $codes=array_values(array_unique(array_map(static fn(array $row):string=>(string)$row['code'],$preview['rows'])));sort($codes,SORT_STRING);
            foreach($codes as $code){$this->acquireInstitutionalCodeLock($code);$locks[]=$code;}
            return Database::transaction(function(PDO $db)use($preview,$config,$actorId,$temporary):array{
            foreach($preview['rows'] as $row){if($this->institutionalCodeExists((string)$row['code']))throw new InvalidArgumentException('La identificación ya está registrada.');}
            $role=$db->prepare('SELECT id FROM roles WHERE code=:code');$role->execute(['code'=>$config['role']]);$roleId=(int)$role->fetchColumn();
            if(!$roleId)throw new InvalidArgumentException('El rol seleccionado no está disponible.');
            $userInsert=$db->prepare('INSERT INTO users(email,username,password_hash,must_change_password,password_warning_count,temporary_password_expires_at,full_name,status) VALUES(:email,:username,:hash,:force,0,DATE_ADD(CURRENT_TIMESTAMP,INTERVAL '.$temporary['days'].' DAY),:name,\'active\')');
            $userRole=$db->prepare('INSERT INTO user_roles(user_id,role_id) VALUES(:user,:role)');
            $student=$db->prepare('INSERT INTO student_profiles(user_id,institutional_code,career_id) VALUES(:id,:code,:career)');
            $enrollment=$db->prepare("INSERT INTO student_enrollments(student_id,academic_period_id,career_id,semester,status) VALUES(:id,:period,:career,:semester,'active')");
            $teacher=$db->prepare('INSERT INTO teacher_profiles(user_id,institutional_code,academic_title,can_tutor,can_manage_thesis) VALUES(:id,:code,:title,:tutor,0)');
            $hash=password_hash($temporary['password'],PASSWORD_DEFAULT);
            foreach($preview['rows'] as $row){
                $userInsert->execute(['email'=>$row['email'],'username'=>$row['username']?:null,'hash'=>$hash,'force'=>$temporary['force_change']?1:0,'name'=>$row['name']]);$id=(int)$db->lastInsertId();
                $userRole->execute(['user'=>$id,'role'=>$roleId]);
                if($config['role']==='student'){$student->execute(['id'=>$id,'code'=>$row['code'],'career'=>$config['career_id']]);$enrollment->execute(['id'=>$id,'period'=>$config['academic_period_id'],'career'=>$config['career_id'],'semester'=>$config['semester']]);}
            else{$teacher->execute(['id'=>$id,'code'=>$row['code'],'title'=>$row['academic_title']?:null,'tutor'=>1]);}
            }
            $this->audit($db,$actorId,'users_bulk_imported',0,['role'=>$config['role'],'count'=>$preview['total']]);
            return ['created'=>$preview['total'],'role'=>$config['role']];
            });
        }finally{foreach(array_reverse($locks) as $code){$this->releaseInstitutionalCodeLock($code);}}
    }

    private function temporaryPolicy():array{return (new SystemSettingModel())->temporaryPasswordPolicy();}
    private function parseImportRows(string $content,string $role):array
    {
        $content=preg_replace('/^\xEF\xBB\xBF/','',trim($content));
        if($content==='')throw new InvalidArgumentException('Pega una lista o selecciona un archivo.');
        if(strlen($content)>1048576)throw new InvalidArgumentException('La lista no puede superar 1 MB.');
        $lines=preg_split('/\R/u',$content)?:[];$lines=array_values(array_filter($lines,static fn(string $line):bool=>trim($line)!==''));
        $delimiter=$this->detectImportDelimiter($lines,$role);
        $rows=array_map(static fn(string $line):array=>str_getcsv($line,$delimiter),$lines);$headerMap=[];$headerDetected=false;$unusedColumns=0;
        foreach(($rows[0]??[]) as $index=>$cell){$canonical=$this->importHeaderKey((string)$cell,$role);if($canonical===null)continue;if(isset($headerMap[$canonical]))throw new InvalidArgumentException('El encabezado contiene columnas repetidas.');$headerMap[$canonical]=$index;}
        $nonEmptyHeader=array_values(array_filter($rows[0]??[],static fn(mixed $cell):bool=>trim((string)$cell)!==''));
        if(count($headerMap)>=2&&count($nonEmptyHeader)>=2){$headerDetected=true;$missing=array_values(array_diff(['name','email','code'],array_keys($headerMap)));if($missing!==[])throw new InvalidArgumentException('El encabezado debe incluir nombre, correo y cedula.');$unusedColumns=max(0,count($rows[0])-count($headerMap));array_shift($rows);}
        if(count($rows)>500)throw new InvalidArgumentException('Puedes importar maximo 500 usuarios por operacion.');
        if(!$rows)throw new InvalidArgumentException('El archivo solo contiene encabezados.');
        if(!$headerDetected)return ['rows'=>$rows,'header_detected'=>false,'unused_columns'=>0];
        $mapped=array_map(function(array $row)use($headerMap):array{$extra=isset($headerMap['username'])?($row[$headerMap['username']]??''):(isset($headerMap['academic_title'])?($row[$headerMap['academic_title']]??''):'');return [$row[$headerMap['name']]??'',$row[$headerMap['email']]??'',$row[$headerMap['code']]??'',$extra];},$rows);
        return ['rows'=>$mapped,'header_detected'=>true,'unused_columns'=>$unusedColumns];
    }
    private function detectImportDelimiter(array $lines,string $role):string
    {
        $best=null;$samples=array_slice($lines,0,8);
        foreach([',',';',"\t"] as $priority=>$candidate){
            $parsed=array_map(static fn(string $line):array=>str_getcsv($line,$candidate),$samples);
            $counts=array_map('count',$parsed);$columns=max($counts?:[0]);
            if($columns<2)continue;
            $consistent=0;foreach($counts as $count)if($count===$columns)$consistent++;
            $headerMatches=0;foreach(($parsed[0]??[]) as $cell)if($this->importHeaderKey((string)$cell,$role)!==null)$headerMatches++;
            $score=[$consistent,$columns,$headerMatches,-$priority];
            if($best===null||$score>$best['score'])$best=['delimiter'=>$candidate,'score'=>$score];
        }
        if($best===null)throw new InvalidArgumentException('Usa columnas separadas por coma, punto y coma o tabulacion.');
        return $best['delimiter'];
    }
    private function importHeaderKey(string $value,string $role):?string
    {
        $value=mb_strtolower(trim(str_replace(['_','-'],' ',preg_replace('/\s+/u',' ',preg_replace('/^\xEF\xBB\xBF/','',$value)))),'UTF-8');$aliases=['name'=>['nombre','nombre completo','full name'],'email'=>['correo','email','correo electronico','correo electrónico'],'code'=>['cedula','cédula','identificacion','identificación']];if($role==='student')$aliases['username']=['usuario','username','nombre de usuario'];else$aliases['academic_title']=['titulo','título','titulo academico','título académico'];foreach($aliases as $key=>$values)if(in_array($value,$values,true))return $key;return null;
    }

    private function institutionalLockName(string $code):string
    {
        return 'user_identity:'.trim($code);
    }
    private function acquireInstitutionalCodeLock(string $code):void
    {
        $name=$this->institutionalLockName($code);$statement=Database::connection()->prepare('SELECT GET_LOCK(:lock_name,5)');$statement->execute(['lock_name'=>$name]);$result=$statement->fetchColumn();if($result===false||$result===null||(int)$result!==1)throw new InvalidArgumentException('No fue posible validar la cédula en este momento. Inténtalo nuevamente.');
    }
    private function releaseInstitutionalCodeLock(string $code):void
    {
        try{$name=$this->institutionalLockName($code);$statement=Database::connection()->prepare('SELECT RELEASE_LOCK(:lock_name)');$statement->execute(['lock_name'=>$name]);$result=$statement->fetchColumn();if($result===false||$result===null||(int)$result!==1)error_log('Admin institutional code lock release returned '.var_export($result,true).' for '.$name);}catch(Throwable $exception){error_log('Admin identity lock release: '.$exception->getMessage());}
    }
    private function withIdentityLock(string $code,callable $operation):mixed
    {
        $this->acquireInstitutionalCodeLock($code);
        try{return $operation();}finally{$this->releaseInstitutionalCodeLock($code);}
    }
    private function institutionalCodeForUser(int $userId):string
    {
        $statement=Database::connection()->prepare("SELECT COALESCE(sp.institutional_code,tp.institutional_code,'') FROM users u LEFT JOIN student_profiles sp ON sp.user_id=u.id LEFT JOIN teacher_profiles tp ON tp.user_id=u.id WHERE u.id=:id LIMIT 1");$statement->execute(['id'=>$userId]);return trim((string)($statement->fetchColumn()?:''));
    }

    private function validate(array $data,int $id):void
    {
        if(mb_strlen($data['full_name'])<3)throw new InvalidArgumentException('Ingresa el nombre completo.');
        if(!filter_var($data['email'],FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Ingresa un correo válido.');
        if(!in_array($data['role'],self::ROLES,true))throw new InvalidArgumentException('Selecciona un rol válido.');
        if($id<1&&$data['role']==='administrator')throw new InvalidArgumentException('Las cuentas nuevas deben ser Estudiante o Docente.');
        if(!empty($data['is_admin'])&&$data['role']!=='teacher')throw new InvalidArgumentException('El acceso administrativo solo puede asignarse a un docente.');
        if(($id<1||$data['status']!=='')&&!in_array($data['status'],self::STATUSES,true))throw new InvalidArgumentException('Selecciona un estado válido.');
        $check=Database::connection()->prepare('SELECT id FROM users WHERE email=:email AND id<>:id LIMIT 1');$check->execute(['email'=>$data['email'],'id'=>$id]);
        if($check->fetch())throw new InvalidArgumentException('Ya existe una cuenta con ese correo.');
        if($data['username']!==''&&!preg_match('/^[a-zA-Z0-9._-]{3,80}$/',$data['username']))throw new InvalidArgumentException('El usuario debe tener entre 3 y 80 caracteres y usar solo letras, números, punto, guion o guion bajo.');
        if($data['username']!==''){$check=Database::connection()->prepare('SELECT id FROM users WHERE username=:username AND id<>:id LIMIT 1');$check->execute(['username'=>$data['username'],'id'=>$id]);if($check->fetch())throw new InvalidArgumentException('Ese nombre de usuario ya está registrado.');}
        if(in_array($data['role'],['student','teacher'],true)){
            if(!preg_match('/^\d{10}$/',$data['institutional_code']))throw new InvalidArgumentException('La cédula debe contener exactamente 10 dígitos.');
            if($this->institutionalCodeExists($data['institutional_code'],$id > 0 ? $id : null)){
                throw new InvalidArgumentException('La cédula ingresada ya está asociada a otra cuenta.');
            }
        }
        if($data['role']==='student'&&($data['career_id']<1||$data['academic_period_id']<1||$data['semester']<1||$data['semester']>4))throw new InvalidArgumentException('Completa el semestre del estudiante entre primero y cuarto.');
    }

    private function withInstitutionalDefaults(array $data):array
    {
        $catalogs=$this->catalogs();
        if(!$catalogs['career']||!$catalogs['period'])throw new InvalidArgumentException('Configura la carrera Desarrollo de Software y un periodo académico activo antes de continuar.');
        $data['career_id']=(int)$catalogs['career']['id'];$data['academic_period_id']=(int)$catalogs['period']['id'];$data['period_name']=(string)$catalogs['period']['name'];$data['is_admin']=!empty($data['is_admin'])?1:0;$data['can_manage_thesis']=$data['role']==='teacher'&&!empty($data['can_manage_thesis'])?1:0;
        return $data;
    }

    private function assertAnotherActiveAdministrator(PDO $db,int $excludedId):void
    {
        $statement=$db->prepare("SELECT COUNT(*) FROM users WHERE is_admin=1 AND status='active' AND deleted_at IS NULL AND purged_at IS NULL AND id<>:id");
        $statement->execute(['id'=>$excludedId]);
        if((int)$statement->fetchColumn()<1)throw new InvalidArgumentException(self::LAST_ADMIN_MESSAGE);
    }

    public function institutionalCodeExists(string $code, ?int $excludeUserId = null): bool
    {
        $db = Database::connection();
        $code = trim($code);
        if ($code === '') {
            return false;
        }
        $stmt = $db->prepare('
            SELECT user_id FROM student_profiles WHERE institutional_code = :code1
            UNION
            SELECT user_id FROM teacher_profiles WHERE institutional_code = :code2
        ');
        $stmt->execute(['code1' => $code, 'code2' => $code]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $uId = (int)$row['user_id'];
            if ($excludeUserId === null || $uId !== $excludeUserId) {
                return true;
            }
        }
        return false;
    }

    private function ensureUser(PDO $db,int $id):void{$statement=$db->prepare('SELECT id FROM users WHERE id=:id');$statement->execute(['id'=>$id]);if(!$statement->fetchColumn())throw new InvalidArgumentException('El usuario ya no existe.');}
    private function ensureManageableUser(PDO $db,int $id):void{$statement=$db->prepare('SELECT id FROM users WHERE id=:id AND deleted_at IS NULL AND purged_at IS NULL FOR UPDATE');$statement->execute(['id'=>$id]);if(!$statement->fetchColumn())throw new InvalidArgumentException('El usuario ya no está disponible para esta operación.');}
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
            'thesis_management_granted'=>'Asignó Gestión de Titulación a '.$element,
            'thesis_management_revoked'=>'Retiró Gestión de Titulación a '.$element,
            'initial_admin_deactivated'=>'Desactivó la cuenta administrativa inicial '.$element,
            'user_status_changed'=>($status==='blocked'?'Bloqueó':($status==='active'?'Desbloqueó':'Cambió el estado de')).' '.$element,
            'users_bulk_imported'=>'Creó '.(int)($details['count']??0).' usuarios mediante importación',
            'password_reset'=>'Restableció la contraseña de '.$element,
        ];
        (new AdminActivityService($db))->record($actorId,$action,$labels[$action]??$action,'Usuarios','user',$targetId?:null,$element,'correct',$details);
    }
}
