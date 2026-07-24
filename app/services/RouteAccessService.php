<?php
declare(strict_types=1);

final class RouteAccessService
{
    private const PUBLIC_ROUTES = ['login', 'logout', 'dev-reload'];
    private const ADMIN_ROUTES = ['admin-users','admin-user-save','admin-user-status','admin-user-password','admin-users-import','admin-project-save','admin-project-trash','admin-academic','admin-academic-save','admin-academic-promote','admin-repository','admin-repository-publish','admin-support-material-save','admin-support-material-status','admin-support-material-file','admin-notification-send','admin-reports','admin-report-export','admin-settings','admin-settings-save','admin-trash','admin-trash-user','admin-trash-restore','admin-trash-purge'];
    public function enforce(string $page): void
    {
        $config = $GLOBALS['config'] ?? [];
        if (!($config['auth_required'] ?? false) || in_array($page, self::PUBLIC_ROUTES, true)) return;
        $session=new AuthSessionService();
        if (!$session->isAuthenticated()) { header('Location: ' . route('login')); exit; }
        try { $identity=(new AuthModel())->sessionIdentity((int)$session->userId()); } catch(Throwable){ $identity=null; }
        if(!$identity||$identity['status']!=='active'||(int)$identity['session_version']!==(int)($_SESSION['session_version']??0)){ $session->logout();header('Location: '.route('login'));exit; }
        $session->refresh($identity);
        $expired=!empty($identity['temporary_password_expires_at'])&&strtotime((string)$identity['temporary_password_expires_at'])<=time();
        $requiresTemporaryPasswordChange=(bool)$identity['must_change_password']&&!(bool)$identity['is_admin'];
        if($requiresTemporaryPasswordChange&&((int)$identity['password_warning_count']>=3||$expired)&&!in_array($page,['change-password','logout'],true)){header('Location: '.route('change-password'));exit;}
        if(in_array($page,self::ADMIN_ROUTES,true)&&!(bool)$identity['is_admin']){header('Location: '.route('forbidden'));exit;}
        if((bool)$identity['is_admin']){
            try{(new AcademicPeriodReminderService())->sync();}
            catch(Throwable $exception){error_log('Academic period reminders: '.$exception->getMessage());}
        }
    }
}
