<?php
declare(strict_types=1);

final class RouteAccessService
{
    private const PUBLIC_ROUTES = ['login', 'login-session-replace', 'login-session-replace-cancel', 'logout', 'dev-reload', 'forgot-password', 'reset-password'];
    private const ADMIN_ROUTES = ['admin-users','admin-user-save','admin-user-status','admin-user-password','admin-users-import','admin-project-save','admin-project-trash','admin-project-history','admin-project-file','admin-academic','admin-academic-save','admin-academic-promote','admin-academic-revert','admin-repository','admin-repository-publish','admin-support-material-save','admin-support-material-history','admin-support-material-history-cleanup','admin-support-material-status','admin-support-material-file','admin-notification-send','admin-notification-audience-send','admin-notification-recipients','notifications/purge-expired','admin-reports','admin-report-export','admin-settings','admin-settings-save','admin-trash','admin-trash-user','admin-trash-restore','admin-trash-restore-batch','admin-trash-restore-all','admin-trash-delete','admin-trash-delete-batch','admin-trash-empty-category','admin-trash-purge'];
    public function enforce(string $page): void
    {
        if (in_array($page, self::PUBLIC_ROUTES, true)) return;
        $session=new AuthSessionService();
        if (!$session->isAuthenticated()) {
            if($this->isAjaxOrJsonRequest($page))$this->denyJson('Tu sesión expiró. Inicia sesión nuevamente para continuar.', 401);
            $session->logout('Tu sesión expiró. Inicia sesión nuevamente para continuar.');
            header('Location: ' . route('login')); exit;
        }
        try { $identity=(new AuthModel())->sessionIdentity((int)$session->userId()); } catch(Throwable){ $identity=null; }
        $background=$this->isBackgroundRequest($page);
        $logicalStatus=$identity&&$identity['status']==='active'&&(int)$identity['session_version']===(int)($_SESSION['session_version']??0)?$session->validateLogicalSession(!$background):'invalid';
        if(!$identity||$identity['status']!=='active'||(int)$identity['session_version']!==(int)($_SESSION['session_version']??0)||$logicalStatus!=='valid'){
            $reason=$logicalStatus==='inactivity'||$session->invalidSessionReason()==='inactivity'?'inactivity':'invalid';
            $notice=$reason==='inactivity'?'La sesión expiró por inactividad. Por seguridad, vuelve a iniciar sesión.':($session->logicalSessionWasRevoked()?'Esta sesión fue cerrada porque tu cuenta inició sesión en otro dispositivo.':'Tu sesión dejó de estar disponible. Inicia sesión nuevamente.');
            if($this->isAjaxOrJsonRequest($page)){ $session->rememberInvalidSessionReason($reason); $this->denyJson($reason==='inactivity'?'La sesión expiró por inactividad.':$notice, 401); }
            $session->logout($notice);
            header('Location: '.route('login'));exit;
        }
        $session->refresh($identity);
        $expired=!empty($identity['temporary_password_expires_at'])&&strtotime((string)$identity['temporary_password_expires_at'])<=time();
        $requiresTemporaryPasswordChange=(bool)$identity['must_change_password']&&!(bool)$identity['is_admin'];
        if($requiresTemporaryPasswordChange&&$expired&&!in_array($page,['change-password','logout'],true)){header('Location: '.route('change-password'));exit;}
        if((in_array($page,self::ADMIN_ROUTES,true)||$page==='admin-repository-trash')&&(!(bool)$identity['is_admin']||!$session->isAdminModeActive())){
            if($this->isAjaxOrJsonRequest($page))$this->denyJson('No tienes permiso para administrar archivos.', 403);
            header('Location: '.route('forbidden'));exit;
        }
    }

    private function isAjaxOrJsonRequest(string $page = ''): bool
    {
        $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        if ($requestedWith === 'xmlhttprequest' || str_contains($accept, 'application/json') || str_contains($contentType, 'application/json')) {
            return true;
        }
        return $page !== '' && $this->isSupportMaterialFileJsonRequest($page);
    }

    private function isBackgroundRequest(string $page): bool
    {
        return strtolower((string) ($_SERVER['HTTP_X_SESSION_ACTIVITY'] ?? '')) === 'background' || $page === 'dev-reload';
    }

    private function isSupportMaterialFileJsonRequest(string $page): bool
    {
        if (in_array($page, ['project-draft-preflight','project-draft-register','student-project-publish'], true)) return true;
        return in_array($page,['dismiss-temp-password-warning','profile-avatar-update','profile-avatar-remove','admin-support-material-file','support-material-manage-save','support-material-manage-file','admin-project-file','admin-trash-restore','admin-trash-restore-batch','admin-trash-delete','admin-trash-delete-batch','admin-trash-empty-category','project-document-review-save','project-adjustment-create','project-adjustment-respond','project-adjustment-address','project-adjustment-close','project-adjustment-list','thesis-tribunal-suggest','thesis-tribunal-save','thesis-defense-schedule-save','thesis-defense-new-attempt','project-draft-save','project-draft-upload','project-draft-file-remove','project-draft-reset','student-project-review-representation','repository-direct-project-publish'],true);
    }

    private function denyJson(string $message, int $status = 401): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success'=>false,'authenticated'=>false,'message'=>$message,'data'=>[]],JSON_UNESCAPED_UNICODE);
        exit;
    }
}
