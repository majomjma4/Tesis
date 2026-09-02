<?php

declare(strict_types=1);

final class ProjectAdjustmentController
{
    public function create(): void { $this->write('create'); }
    public function respond(): void { $this->write('respond'); }
    public function address(): void { $this->write('address'); }
    public function close(): void { $this->write('close'); }
    public function approve(): void { $this->write('approve'); }
    public function reject(): void { $this->write('reject'); }

    public function listing(): void
    {
        $session=$this->session('GET'); $input=$_GET; $this->csrf($session,$input);
        if ((string)($input['context'] ?? '') === 'academic_management'
            && (!$session->hasAdminAccess() || !$session->isAdminModeActive())) {
            $this->json(false,'La gestión administrativa requiere Admin Mode activo.',[],403);
        }
        try {
            $result=(new ProjectAdjustmentRequestService())->listForProject((int)($input['project_id']??0),(string)($input['expected_project_status']??''),(int)$session->userId(),(string)($input['context']??''));
            $this->json(true,'Solicitudes administrativas obtenidas.',$result);
        } catch(ProjectAdjustmentRequestException $e){$this->json(false,$e->getMessage(),[],$e->httpStatus());}
        catch(Throwable $e){error_log('Project adjustment list: '.$e->getMessage());$this->json(false,'No fue posible consultar las solicitudes administrativas.',[],500);}
    }

    private function write(string $operation): void
    {
        $session=$this->session('POST');$input=$this->input();$this->csrf($session,$input);
        $project=(int)($input['project_id']??0);$request=(int)($input['request_id']??0);$version=(int)($input['lock_version']??0);
        $expected=(string)($input['expected_project_status']??'');$actor=(int)$session->userId();$context=(string)($input['context']??'');
        $rejectionReason=(string)($input['rejection_reason']??'');
        if ($context === 'academic_management'
            && (!$session->hasAdminAccess() || !$session->isAdminModeActive())) {
            $this->json(false,'La gestión administrativa requiere Admin Mode activo.',[],403);
        }
        if (in_array($operation, ['approve', 'reject'], true)
            && $context !== 'academic_management') {
            $this->json(false,'Esta decisión sólo está disponible para Administración.',[],403);
        }
        try {
            $service=new ProjectAdjustmentRequestService();
            $result=match($operation){
                'create'=>$service->create($project,$expected,$actor,$context,$input),
                'respond'=>$service->respond($project,$request,$version,$expected,$actor,$context,(string)($input['message']??'')),
                'address'=>$service->address($project,$request,$version,$expected,$actor,$context),
                'close'=>$service->close($project,$request,$version,$expected,$actor,$context),
                'approve'=>$service->approve($project,$request,$version,$expected,$actor,$context),
                'reject'=>$service->reject($project,$request,$version,$expected,$actor,$context,$rejectionReason),
            };
            $this->json(true,(string)$result['message'],$result);
        } catch(ProjectAdjustmentRequestException $e){$this->json(false,$e->getMessage(),[],$e->httpStatus());}
        catch(Throwable $e){error_log('Project adjustment '.$operation.': '.$e->getMessage());$this->json(false,'No fue posible completar la operación. No se realizaron cambios.',[],500);}
    }

    private function session(string $method): AuthSessionService
    {
        if(($_SERVER['REQUEST_METHOD']??'GET')!==$method)$this->json(false,'Método no permitido.',[],405);
        $session=new AuthSessionService();if(!$session->isAuthenticated()||(int)($session->userId()??0)<1)$this->json(false,'La sesión no está activa.',[],403);
        return $session;
    }
    private function csrf(AuthSessionService $session,array $input): void
    { $token=(string)($input['_csrf']??($_SERVER['HTTP_X_CSRF_TOKEN']??''));if(!$session->validateCsrf('project_adjustment',$token))$this->json(false,'La solicitud contiene un token CSRF inválido.',[],419); }
    private function input(): array
    { if(!str_contains(strtolower((string)($_SERVER['CONTENT_TYPE']??'')),'application/json'))return $_POST;try{$data=json_decode((string)file_get_contents('php://input'),true,512,JSON_THROW_ON_ERROR);return is_array($data)?$data:[];}catch(JsonException){return [];} }
    private function json(bool $success,string $message,array $data=[],int $status=200): never
    { http_response_code($status);header('Content-Type: application/json; charset=utf-8');echo json_encode(['success'=>$success,'message'=>$message,'data'=>$data],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit; }
}
