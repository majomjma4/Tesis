<?php

declare(strict_types=1);

/** Private endpoint for student-supplied PDF review representations. */
final class ProjectReviewRepresentationController
{
    public function change(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') $this->json(false,'Método no permitido.',[],405);
        $session = new AuthSessionService(); $actor = (int)($session->userId() ?? 0);
        if ($actor < 1 || !$session->validateCsrf('student_project_review_representation',(string)($_POST['_csrf']??''))) $this->json(false,'La solicitud no está autorizada.',[],419);
        $projectId=(int)($_POST['project_id']??0);$action=(string)($_POST['action']??'');
        try {
            if (empty((new ProjectCapabilityService())->forProjectId($projectId,'academic')['view_project'])) $this->json(false,'No tienes acceso a este proyecto.',[],403);
            if ($action==='upload') $this->json(true,'La representación PDF fue asociada correctamente.',(new ProjectReviewRepresentationService())->uploadSupplemental($projectId,(int)($_POST['file_id']??0),$_FILES['file']??[],$actor));
            if ($action==='readiness') $this->json(true,'Validación completada.',(new ProjectReviewReadinessService())->check($projectId,false));
            $this->json(false,'La operación no es válida.',[],422);
        } catch (InvalidArgumentException $exception) { $this->json(false,$exception->getMessage(),[],422); }
        catch (Throwable $exception) { error_log('Review representation: '.$exception->getMessage()); $this->json(false,'No fue posible preparar la representación PDF.',[],500); }
    }
    private function json(bool $success,string $message,array $data=[],int $status=200):never{http_response_code($status);header('Content-Type: application/json; charset=utf-8');echo json_encode(['success'=>$success,'message'=>$message,'data'=>$data],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
}
