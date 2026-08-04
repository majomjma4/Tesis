<?php

declare(strict_types=1);

final class ProjectDocumentReviewController
{
    public function save(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') $this->json(false, 'Método no permitido.', [], 405);
        $session = new AuthSessionService();
        if (!$session->isAuthenticated() || (int)($session->userId() ?? 0) < 1) $this->json(false, 'La sesión no está activa.', [], 403);
        $input = $this->input();
        $context = (string)($input['context'] ?? '');
        if ($context !== 'academic') $this->json(false, 'La revisión documental no está disponible en este contexto.', [], 403);
        if (!$session->validateCsrf('project_document_review', (string)($input['_csrf'] ?? ''))) {
            $this->json(false, 'La solicitud contiene un token CSRF inválido.', [], 419);
        }
        $projectId = (int)($input['project_id'] ?? 0);
        $decisions = $input['decisions'] ?? [];
        if (is_string($decisions)) {
            try { $decisions = json_decode($decisions, true, 512, JSON_THROW_ON_ERROR); }
            catch (JsonException) { $decisions = []; }
        }
        if (!is_array($decisions)) $decisions = [];
        if (empty((new ProjectCapabilityService())->forProjectId($projectId, $context)['review_documents'])) {
            $this->json(false, 'No tienes autorización para revisar los documentos de este proyecto.', [], 403);
        }
        try {
            $result = (new ProjectDocumentReviewBatchService())->confirm(
                $projectId, (string)($input['expected_project_status'] ?? ''), $decisions,
                (int)$session->userId(), $context
            );
            $this->json(true, (string)$result['message'], $result);
        } catch (ProjectStatusTransitionException $exception) {
            $this->json(false, $exception->getMessage(), [], $exception->httpStatus());
        } catch (Throwable $exception) {
            error_log('Project document review: '.$exception->getMessage());
            $this->json(false, 'No fue posible confirmar la revisión documental. No se realizaron cambios.', [], 500);
        }
    }

    private function input(): array
    {
        if (!str_contains(strtolower((string)($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json')) return $_POST;
        try {
            $decoded = json_decode((string)file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (JsonException) { return []; }
    }

    private function json(bool $success, string $message, array $data = [], int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success'=>$success, 'message'=>$message, 'data'=>$data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
