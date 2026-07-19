<?php
declare(strict_types=1);

final class AdminController
{
    public function users(): void
    {
        $model = new AdminUserModel();
        $filters = ['search'=>mb_substr(trim((string)($_GET['search']??'')),0,100),'role'=>(string)($_GET['role']??''),'status'=>(string)($_GET['status']??'')];
        $error = null;
        try { $users=$model->listing($filters); $summary=$model->summary(); $catalogs=$model->catalogs(); }
        catch(Throwable $exception){ error_log('Admin users error: '.$exception->getMessage());$error='No fue posible consultar los usuarios.';$users=[];$summary=['total'=>0,'active'=>0,'blocked'=>0,'students'=>0,'teachers'=>0,'administrators'=>0];$catalogs=['careers'=>[],'periods'=>[]]; }
        $session=new AuthSessionService();
        View::render('admin/users',['currentPage'=>'admin-users','title'=>'Usuarios | Administración','bodyClass'=>'admin-users-page','pageStyles'=>[asset('css/admin-users.css')],'pageScript'=>asset('js/admin-users.js'),'users'=>$users,'userSummary'=>$summary,'catalogs'=>$catalogs,'filters'=>$filters,'adminUserCsrf'=>$session->csrfToken('admin_users'),'adminUserEndpoints'=>['save'=>route('admin-user-save'),'status'=>route('admin-user-status'),'password'=>route('admin-user-password')],'adminUsersError'=>$error]);
    }

    public function saveUser(): void
    {
        $this->requirePost(); $session=$this->sessionAndCsrf();
        try {
            $id=(int)($_POST['id']??0);$payload=$this->userPayload();
            if($id===(int)$session->userId()&&($payload['role']!=='administrator'||$payload['status']!=='active'))throw new InvalidArgumentException('No puedes retirar tu propio acceso administrativo.');
            $saved=(new AdminUserModel())->save($payload,$id,(int)$session->userId());
            $this->json(true,$id>0?'Usuario actualizado correctamente.':'Usuario creado correctamente.',['user'=>$saved]);
        } catch(InvalidArgumentException $exception){$this->json(false,$exception->getMessage(),[],422);}
        catch(Throwable $exception){error_log('Save admin user: '.$exception->getMessage());$this->json(false,'No fue posible guardar el usuario.',[],500);}
    }

    public function changeUserStatus(): void
    {
        $this->requirePost();$session=$this->sessionAndCsrf();$id=(int)($_POST['id']??0);$status=(string)($_POST['status']??'');
        if($id===(int)$session->userId())$this->json(false,'No puedes bloquear o desactivar tu propia cuenta.',[],422);
        try{(new AdminUserModel())->changeStatus($id,$status,(int)$session->userId());$this->json(true,'Estado de acceso actualizado.');}
        catch(InvalidArgumentException $exception){$this->json(false,$exception->getMessage(),[],422);}
        catch(Throwable $exception){error_log('Admin user status: '.$exception->getMessage());$this->json(false,'No fue posible actualizar el acceso.',[],500);}
    }

    public function resetUserPassword(): void
    {
        $this->requirePost();$session=$this->sessionAndCsrf();$id=(int)($_POST['id']??0);
        try{(new AdminUserModel())->resetPassword($id,'Istel2026+',(int)$session->userId());$this->json(true,'Contraseña temporal restablecida. El usuario deberá cambiarla.');}
        catch(InvalidArgumentException $exception){$this->json(false,$exception->getMessage(),[],422);}
        catch(Throwable $exception){error_log('Admin password reset: '.$exception->getMessage());$this->json(false,'No fue posible restablecer la contraseña.',[],500);}
    }

    public function module(string $section): void
    {
        $modules=['academic'=>['Gestión académica','fa-graduation-cap','Periodos, matrículas y catálogos se habilitarán en la Fase 4.'],'reports'=>['Reportes','fa-chart-column','Los reportes administrativos se habilitarán al completar los módulos de datos.'],'settings'=>['Configuración','fa-gear','Los parámetros institucionales se incorporarán en la Fase 6.'],'trash'=>['Papelera','fa-trash-can','La restauración y purga a 60 días se incorporará en la Fase 7.']];
        if(!isset($modules[$section])){$this->users();return;}$item=$modules[$section];
        View::render('admin/module-pending',['currentPage'=>'admin-'.$section,'title'=>$item[0].' | Administración','bodyClass'=>'admin-page','pageStyles'=>[asset('css/admin-access.css')],'moduleTitle'=>$item[0],'moduleIcon'=>$item[1],'moduleMessage'=>$item[2]]);
    }

    private function userPayload(): array
    {
        return ['full_name'=>trim((string)($_POST['full_name']??'')),'email'=>mb_strtolower(trim((string)($_POST['email']??''))),'role'=>(string)($_POST['role']??''),'status'=>(string)($_POST['status']??'active'),'institutional_code'=>trim((string)($_POST['institutional_code']??'')),'career_id'=>(int)($_POST['career_id']??0),'academic_period_id'=>(int)($_POST['academic_period_id']??0),'semester'=>(int)($_POST['semester']??0),'academic_title'=>trim((string)($_POST['academic_title']??'')),'can_tutor'=>isset($_POST['can_tutor'])?1:0];
    }
    private function sessionAndCsrf(): AuthSessionService{$session=new AuthSessionService();if(!$session->validateCsrf('admin_users',(string)($_POST['_csrf']??'')))$this->json(false,'La sesión del formulario venció.',[],419);return $session;}
    private function requirePost(): void{if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){$this->json(false,'Método no permitido.',[],405);}}
    private function json(bool $success,string $message,array $data=[],int $status=200): never{http_response_code($status);header('Content-Type: application/json; charset=utf-8');echo json_encode(['success'=>$success,'message'=>$message,'data'=>$data],JSON_UNESCAPED_UNICODE);exit;}
}
