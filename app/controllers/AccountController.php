<?php
declare(strict_types=1);

final class AccountController
{
    public function profile():void
    {
        $session=new AuthSessionService();$model=new AuthModel();$error=null;$success=null;
        if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
            $name=trim((string)($_POST['full_name']??''));$email=mb_strtolower(trim((string)($_POST['email']??'')));$password=(string)($_POST['current_password']??'');
            if(!$session->validateCsrf('profile',(string)($_POST['_csrf']??'')))$error='La sesión del formulario venció.';
            else try{$model->updateProfile((int)$session->userId(),$name,$email,$password);$identity=$model->sessionIdentity((int)$session->userId());if(!$identity)throw new RuntimeException('La cuenta dejó de estar disponible.');$session->refresh($identity);$success='Perfil actualizado correctamente.';}catch(InvalidArgumentException $e){$error=$e->getMessage();}catch(Throwable $e){error_log('Profile update: '.$e->getMessage());$error='No fue posible actualizar el perfil.';}
        }
        try{$profile=$model->profile((int)$session->userId());}catch(Throwable $e){$profile=['full_name'=>$session->name(),'email'=>$session->email(),'created_at'=>null,'last_login_at'=>null,'password_changed_at'=>null,'roles'=>$session->roles()];$error??='No fue posible consultar todos los datos del perfil.';}
        View::render('account/profile',['currentPage'=>'profile','title'=>'Mi perfil | Administración','bodyClass'=>'account-profile-page','pageStyles'=>[asset('css/account-profile.css')],'profile'=>$profile,'profileError'=>$error,'profileSuccess'=>$success,'profileCsrf'=>$session->csrfToken('profile')]);
    }

    public function changePassword(): void
    {
        $session=new AuthSessionService();$error=null;$success=null;
        if($_SERVER['REQUEST_METHOD']==='POST'){
            $current=(string)($_POST['current_password']??'');$new=(string)($_POST['new_password']??'');$confirmation=(string)($_POST['new_password_confirmation']??'');
            if(!$session->validateCsrf('change_password',(string)($_POST['_csrf']??'')))$error='La sesión del formulario venció.';
            elseif(strlen($new)<4||!preg_match('/[A-Z]/',$new)||!preg_match('/[a-z]/',$new)||!preg_match('/\d/',$new)||!preg_match('/[^A-Za-z0-9]/',$new))$error='La nueva contraseña debe tener 4 caracteres e incluir mayúscula, minúscula, número y símbolo.';
            elseif($new!==$confirmation)$error='La confirmación no coincide con la nueva contraseña.';
            elseif($current===$new)$error='La nueva contraseña debe ser diferente a la actual.';
            elseif(!(new AuthModel())->changePassword((int)$session->userId(),$current,$new))$error='La contraseña actual no es correcta.';
            else { $identity=(new AuthModel())->sessionIdentity((int)$session->userId());$session->refresh($identity);$success='Contraseña actualizada. Las demás sesiones fueron cerradas.'; }
        }
        $forcedPasswordChange=$session->mustChangePassword()&&!$session->hasAdminAccess();
        View::render('account/change-password',['currentPage'=>'profile','title'=>'Cambiar contraseña | Administración','bodyClass'=>'account-page','pageStyles'=>[asset('css/admin-access.css')],'passwordCsrfToken'=>$session->csrfToken('change_password'),'passwordError'=>$error,'passwordSuccess'=>$success,'forcedPasswordChange'=>$forcedPasswordChange]);
    }

    public function dismissTemporaryPasswordWarning(): void
    {
        $session = new AuthSessionService();
        $session->dismissTemporaryPasswordWarningToday();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true]);
        exit;
    }

    public function forbidden(): void
    {
        http_response_code(403);
        View::render('errors/403', [
            'currentPage' => '',
            'title' => 'Acceso no autorizado | Gestión Académica',
            'bodyClass' => 'error-page',
            'pageStyles' => [asset('css/admin-access.css')],
        ], 'error');
    }
}
