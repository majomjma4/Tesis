<?php
declare(strict_types=1);

final class AccountController
{
    public function changePassword(): void
    {
        $session=new AuthSessionService();$error=null;$success=null;
        if($_SERVER['REQUEST_METHOD']==='POST'){
            $current=(string)($_POST['current_password']??'');$new=(string)($_POST['new_password']??'');$confirmation=(string)($_POST['new_password_confirmation']??'');
            if(!$session->validateCsrf('change_password',(string)($_POST['_csrf']??'')))$error='La sesión del formulario venció.';
            elseif(strlen($new)<10||!preg_match('/[A-Z]/',$new)||!preg_match('/[a-z]/',$new)||!preg_match('/\d/',$new)||!preg_match('/[^A-Za-z0-9]/',$new))$error='La nueva contraseña debe tener 10 caracteres e incluir mayúscula, minúscula, número y símbolo.';
            elseif($new!==$confirmation)$error='La confirmación no coincide con la nueva contraseña.';
            elseif($current===$new)$error='La nueva contraseña debe ser diferente a la actual.';
            elseif(!(new AuthModel())->changePassword((int)$session->userId(),$current,$new))$error='La contraseña actual no es correcta.';
            else { $identity=(new AuthModel())->sessionIdentity((int)$session->userId());$session->refresh($identity);$success='Contraseña actualizada. Las demás sesiones fueron cerradas.'; }
        }
        View::render('account/change-password',['currentPage'=>'profile','title'=>'Cambiar contraseña | Administración','bodyClass'=>'account-page','pageStyles'=>[asset('css/admin-access.css')],'passwordCsrfToken'=>$session->csrfToken('change_password'),'passwordError'=>$error,'passwordSuccess'=>$success,'forcedPasswordChange'=>$session->mustChangePassword()]);
    }

    public function forbidden(): void
    {
        http_response_code(403);View::render('errors/403',['currentPage'=>'','title'=>'Acceso no autorizado','bodyClass'=>'error-page','pageStyles'=>[asset('css/admin-access.css')]]);
    }
}
