<?php
declare(strict_types=1);

final class AdminController
{
    public function settings():void{$model=new SystemSettingModel();$error=null;$temporaryPasswordConfigured=false;try{$settings=$model->all();$uploadPolicy=$model->fileUploadPolicy();$temporaryPasswordConfigured=$model->temporaryPasswordConfigured();}catch(Throwable $e){error_log('Admin settings: '.$e->getMessage());$error='No fue posible consultar la configuración.';$settings=$model->defaults();$uploadPolicy=['max_mb'=>20,'total_max_mb'=>35,'file_ceiling_mb'=>20,'operation_ceiling_mb'=>35,'application_file_ceiling_mb'=>500,'application_operation_ceiling_mb'=>1024];}$s=new AuthSessionService();View::render('admin/settings',['currentPage'=>'admin-settings','title'=>'Configuración | Administración','bodyClass'=>'admin-settings-page','pageStyles'=>[asset('css/admin-settings.css')],'pageScript'=>asset('js/admin-settings.js'),'settings'=>$settings,'uploadPolicy'=>$uploadPolicy,'temporaryPasswordConfigured'=>$temporaryPasswordConfigured,'settingsError'=>$error,'settingsCsrf'=>$s->csrfToken('admin_settings'),'settingsSaveEndpoint'=>route('admin-settings-save')]);}
    public function saveSettings():void{$this->requirePost();$s=new AuthSessionService();if(!$s->validateCsrf('admin_settings',(string)($_POST['_csrf']??'')))$this->json(false,'La solicitud ya no es válida. Recarga la página e inténtalo nuevamente.',[],403);try{(new SystemSettingModel())->save($_POST,(int)$s->userId());$this->json(true,'Configuración guardada y aplicada.');}catch(InvalidArgumentException $e){$this->activityFailure($s,'settings_updated','Intentó actualizar la configuración institucional','Configuración','settings',null,'Configuración del sistema',$e);$this->json(false,$e->getMessage(),[],422);}catch(Throwable $e){$this->activityFailure($s,'settings_updated','Intentó actualizar la configuración institucional','Configuración','settings',null,'Configuración del sistema',$e);error_log('Save settings: '.$e->getMessage());$this->json(false,'No fue posible guardar la configuración.',[],500);}}
    public function reports():void{$periods=(new AcademicPeriodModel())->all();$selection=$this->reportPeriodSelection($periods);$base=['from'=>$selection['from'],'to'=>$selection['to']];$from=$this->reportDate('from',$base['from']);$to=$this->reportDate('to',$base['to']);$manualDates=array_key_exists('from',$_GET)||array_key_exists('to',$_GET);if($manualDates&&$selection['id']!==null&&($from!==$selection['from']||$to!==$selection['to'])&&count($periods)>1)$selection=['id'=>null,'from'=>$from,'to'=>$to];$model=new AdminReportModel();$error=null;$paginationRequest=['page'=>(int)($_GET['report_page']??PaginationService::request()['page']??1),'size'=>(int)($_GET['reports_per_page']??PaginationService::request()['size']??10)];try{$data=$model->dashboard($from,$to,$paginationRequest);}catch(Throwable $e){error_log('Admin reports: '.$e->getMessage());$error='No fue posible generar los reportes.';$data=['summary'=>['users'=>0,'projects'=>0,'deliveries'=>0,'actions'=>0],'roles'=>[],'statuses'=>[],'reviewSituations'=>[],'activity'=>[],'pagination'=>['total'=>0]];}View::render('admin/reports',['currentPage'=>'admin-reports','title'=>'Reportes | Administración','bodyClass'=>'admin-reports-page','pageStyles'=>[asset('css/admin-reports.css')],'reportData'=>$data,'pagePaginationData'=>$data['pagination'],'reportFrom'=>$from,'reportTo'=>$to,'reportBaseFrom'=>$base['from'],'reportBaseTo'=>$base['to'],'reportPeriods'=>$periods,'reportSelectedPeriodId'=>$selection['id'],'reportPeriodsAreMultiple'=>count($periods)>1,'reportError'=>$error]);}
    public function exportReport():never{
        $type=(string)($_GET['type']??'');
        $format=(string)($_GET['format']??'word');
        $scope=(string)($_GET['scope']??'');
        $from=$this->reportDate('from',($this->reportBaseRange())['from']);
        $to=$this->reportDate('to',($this->reportBaseRange())['to']);
        if(!in_array($type,['users','projects','audit'],true)||!in_array($format,['word','csv'],true)){http_response_code(422);header('Content-Type: application/json; charset=UTF-8');echo json_encode(['success'=>false,'message'=>'El tipo o formato de reporte no es válido.']);exit;}
        try{
            $model=new AdminReportModel();
            $report=$model->export($type,$from,$to);
            try{$institutionName=(string)(new SystemSettingModel())->all()['institution_name'];}catch(Throwable){$institutionName=(string)(new SystemSettingModel())->defaults()['institution_name'];}

            // Si no hay registros, devolver respuesta HTTP 422 controlada sin descargar
            if (empty($report['rows'])) {
                http_response_code(422);
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode([
                    'success' => false,
                    'message' => 'No hay información para el período seleccionado. Prueba con otro alcance o un rango personalizado.'
                ]);
                exit;
            }

            $titleMap=['users'=>'Reporte de Usuarios','projects'=>'Reporte de Proyectos Académicos','audit'=>'Reporte de Auditoría y Trazabilidad'];
            $reportTitle=$titleMap[$type]??'Reporte Institucional';
            $formattedFrom=date('d/m/Y',strtotime($from));
            $formattedTo=date('d/m/Y',strtotime($to));
            $generatedAt=date('d/m/Y H:i');
            $subtitle="Período: $formattedFrom - $formattedTo | Generado: $generatedAt";

            // Nombres de meses sin tildes para archivos de SO
            $monthsMap = [1=>'Enero', 2=>'Febrero', 3=>'Marzo', 4=>'Abril', 5=>'Mayo', 6=>'Junio', 7=>'Julio', 8=>'Agosto', 9=>'Septiembre', 10=>'Octubre', 11=>'Noviembre', 12=>'Diciembre'];
            $fromTs = strtotime($from);
            $toTs = strtotime($to);

            $firstThisMonth = date('Y-m-01');
            $firstLastMonth = date('Y-m-01', strtotime('first day of last month'));
            $lastLastMonth = date('Y-m-t', strtotime('last month'));
            $sevenDaysAgo = date('Y-m-d', strtotime('-6 days'));

            if ($scope === 'this_month' || ($from === $firstThisMonth && $to === date('Y-m-d') && $scope !== 'custom' && $scope !== '7days')) {
                $mNum = (int)date('n', $fromTs);
                $yNum = date('Y', $fromTs);
                $suffix = ($monthsMap[$mNum] ?? 'Mes') . '-' . $yNum;
            } elseif ($scope === 'last_month' || ($from === $firstLastMonth && $to === $lastLastMonth)) {
                $mNum = (int)date('n', $fromTs);
                $yNum = date('Y', $fromTs);
                $suffix = ($monthsMap[$mNum] ?? 'Mes') . '-' . $yNum;
            } elseif ($scope === '7days' || ($from === $sevenDaysAgo && $to === date('Y-m-d'))) {
                $suffix = 'Ultimos-7-dias';
            } elseif ($scope === 'academic_period' || ($from === '2026-04-01' && $to === '2026-09-30')) {
                $suffix = 'I-PAO-2026';
            } else {
                $suffix = date('d-m-Y', $fromTs) . '-al-' . date('d-m-Y', $toTs);
            }

            $exportFilename = 'reporte-' . $type . '-' . $suffix . '.' . ($format === 'csv' ? 'csv' : 'doc');

            if ($format === 'word' || $format === 'doc') {
                $isAuditReport = $type === 'audit';
                $wordLandscape = $type !== 'users';
                $rootPath = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
                $logoLibertadorFile = $rootPath . '/public/assets/img/logo_libertador.png';
                $logoDsFile = $rootPath . '/public/assets/img/logo_ds.png';

                $logoLibertadorSrc = is_file($logoLibertadorFile) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoLibertadorFile)) : '';
                $logoDsSrc = is_file($logoDsFile) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoDsFile)) : '';

                while (ob_get_level() > 0) {
                    ob_end_clean();
                }

                header('Content-Type: application/msword; charset=UTF-8');
                header('Content-Disposition: attachment; filename="' . $exportFilename . '"');

                ob_start();
                ?>
<!DOCTYPE html>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta charset="UTF-8">
<title><?=e($reportTitle)?></title>
<!--[if gte mso 9]>
<xml>
 <w:WordDocument>
  <w:View>Print</w:View>
  <w:Zoom>100</w:Zoom>
  <w:DoNotOptimizeForBrowser/>
 </w:WordDocument>
</xml>
<![endif]-->
<style>
    @page Section1 {
        size: <?= $wordLandscape ? '841.9pt 595.3pt' : '595.3pt 841.9pt' ?>;
        margin: <?= $isAuditReport ? '42.5pt 42.5pt 42.5pt 42.5pt' : '56.7pt 42.5pt 56.7pt 42.5pt' ?>;
        <?= $isAuditReport ? 'mso-footer: f1;' : '' ?>
    }
    div.Section1 { page: Section1; }
    body {
        font-family: 'Segoe UI', Arial, sans-serif;
        font-size: 10pt;
        color: #0f172a;
        background-color: #ffffff;
        margin: 0;
        padding: 0;
    }
    .header-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 12px;
    }
    .header-table td {
        vertical-align: middle;
        border: none;
        padding: 4px;
    }
    .logo-img {
        max-width: 90px;
        max-height: 70px;
        height: auto;
    }
    .institution-name {
        font-size: 13pt;
        font-weight: bold;
        color: #1e3a8a;
        text-align: center;
        margin: 0;
        text-transform: uppercase;
    }
    .career-name {
        font-size: 10pt;
        font-weight: bold;
        color: #059669;
        text-align: center;
        margin: 2px 0 0 0;
    }
    .divider-line {
        border-bottom: 2px solid #cbd5e1;
        margin-bottom: 16px;
    }
    .report-title {
        font-size: 14pt;
        font-weight: bold;
        color: #0f172a;
        text-align: center;
        margin: 10px 0 4px 0;
        text-transform: uppercase;
    }
    .report-meta {
        font-size: 9pt;
        color: #64748b;
        text-align: center;
        margin-bottom: 16px;
        font-style: italic;
    }
    .data-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }
    .data-table th {
        background-color: #1e3a8a;
        color: #ffffff;
        font-size: 9pt;
        font-weight: bold;
        text-align: left;
        padding: 8px 10px;
        border: 1px solid #cbd5e1;
    }
    .data-table td {
        font-size: 9pt;
        padding: 7px 10px;
        border: 1px solid #e2e8f0;
        vertical-align: top;
    }
    .data-table tr:nth-child(even) td {
        background-color: #f8fafc;
    }
    .audit-table { width: 100%; table-layout: fixed; border-collapse: collapse; border: 1px solid #cbd5e1; }
    .audit-table th { background-color: #1e3a8a; color: #ffffff; font-weight: bold; border: 1px solid #cbd5e1; padding: 8px 10px; }
    .audit-table td { border: 1px solid #e2e8f0; padding: 7px 10px; vertical-align: top; }
    .audit-table th, .audit-table td { overflow-wrap: break-word; word-wrap: break-word; }
    .audit-table thead { display: table-header-group; }
    .audit-table tr { page-break-inside: avoid; }
    .footer-note {
        margin-top: 24px;
        font-size: 8pt;
        color: #94a3b8;
        text-align: right;
        border-top: 1px solid #e2e8f0;
        padding-top: 6px;
    }
</style>
</head>
<body>

<div class="Section1">
<table class="header-table">
    <tr>
        <td style="width: 20%; text-align: left;">
            <?php if ($logoLibertadorSrc): ?>
                <img src="<?=$logoLibertadorSrc?>" class="logo-img" alt="<?=e($institutionName)?>">
            <?php endif; ?>
        </td>
        <td style="width: 60%; text-align: center;">
            <div class="institution-name"><?=e($institutionName)?></div>
            <div class="career-name">Tecnología en Desarrollo de Software</div>
        </td>
        <td style="width: 20%; text-align: right;">
            <?php if ($logoDsSrc): ?>
                <img src="<?=$logoDsSrc?>" class="logo-img" alt="Desarrollo de Software">
            <?php endif; ?>
        </td>
    </tr>
</table>

<div class="divider-line"></div>

<div class="report-title"><?=e($reportTitle)?></div>
<div class="report-meta"><?=e($subtitle)?></div>

<?php
    $auditWidths = ['13%', '12%', '35%', '18%', '22%'];
    $auditTableStyle = 'width:96%; margin-left:auto; margin-right:auto; border-collapse:collapse; table-layout:fixed; border:1px solid #cbd5e1; mso-table-lspace:0pt; mso-table-rspace:0pt;';
    $auditHeaderStyle = 'background:#1e3a8a; background-color:#1e3a8a; color:#ffffff; border:1px solid #cbd5e1; padding:8pt 10pt; font-family:Segoe UI, Arial, sans-serif; font-size:9pt; font-weight:bold; text-align:left; vertical-align:middle;';
    $auditCellStyle = 'border:1px solid #e2e8f0; padding:7pt 10pt; font-family:Segoe UI, Arial, sans-serif; font-size:9pt; vertical-align:top; text-align:left; word-wrap:break-word;';
?>
<table class="data-table<?= $isAuditReport ? ' audit-table' : '' ?>"<?= $isAuditReport ? ' border="1" cellspacing="0" cellpadding="0" width="96%" align="center" style="' . $auditTableStyle . '"' : '' ?>>
    <?php if ($isAuditReport): ?>
        <colgroup>
            <col style="width:13%"><col style="width:12%"><col style="width:35%"><col style="width:18%"><col style="width:22%">
        </colgroup>
    <?php endif; ?>
    <thead>
        <tr<?= $isAuditReport ? ' style="page-break-inside:avoid;"' : '' ?>>
            <?php foreach ($report['headers'] as $index => $header): ?>
                <th<?= $isAuditReport ? ' bgcolor="#1e3a8a" width="' . $auditWidths[$index] . '" style="width:' . $auditWidths[$index] . '; ' . $auditHeaderStyle . '"' : '' ?>><?=e($header)?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($report['rows'] as $row): ?>
            <tr<?= $isAuditReport ? ' style="page-break-inside:avoid;"' : '' ?>>
                <?php foreach (array_values($row) as $index => $cell): ?>
                    <td<?= $isAuditReport ? ' width="' . $auditWidths[$index] . '" style="width:' . $auditWidths[$index] . '; ' . $auditCellStyle . '"' : '' ?>><?=nl2br(e((string)$cell))?></td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="footer-note">
    Documento institucional generado automáticamente por el Sistema de Gestión Documental Académica - <?=e(date('d/m/Y H:i'))?>
</div>
</div>
<?php if ($isAuditReport): ?>
<div style="mso-element:footer" id="f1">
    <p style="margin:0; text-align:center; font-family:Segoe UI, Arial, sans-serif; font-size:8pt; color:#475569;">Página <span style="mso-field-code: PAGE"></span> de <span style="mso-field-code: NUMPAGES"></span></p>
</div>
<?php endif; ?>

</body>
</html>
                <?php
                echo ob_get_clean();
                exit;
            }

            // Formato CSV con Encabezado Contextual
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $exportFilename . '"');
            echo "\xEF\xBB\xBF";
            $out=fopen('php://output','wb');
            fputcsv($out,[$institutionName],';');
            fputcsv($out,[$reportTitle],';');
            fputcsv($out,["Período: $formattedFrom al $formattedTo"],';');
            fputcsv($out,["Generado: $generatedAt"],';');
            fputcsv($out,[],';'); // Fila en blanco
            fputcsv($out,$report['headers'],';');
            foreach($report['rows'] as $row){
                $safe=array_map(static fn($cell):string=>preg_match('/^[=+\-@]/u',(string)$cell)?"'".(string)$cell:(string)$cell,array_values($row));
                fputcsv($out,$safe,';');
            }
            fclose($out);
            exit;
        }catch(Throwable $e){
            error_log('Export report: '.$e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success'=>false,'message'=>'No fue posible generar el reporte.']);
            exit;
        }
    }
    private function reportPeriodSelection(array $periods): array
    {
        $count = count($periods);
        if ($count === 0) {
            $today = date('Y-m-d');
            return ['id'=>null,'from'=>$today,'to'=>$today];
        }
        $requested = (int) ($_GET['period_id'] ?? 0);
        if ($count === 1) $requested = (int) $periods[0]['id'];
        if ($requested > 0) {
            foreach ($periods as $period) {
                if ((int) $period['id'] === $requested) return ['id'=>$requested,'from'=>(string)$period['starts_on'],'to'=>(string)$period['ends_on']];
            }
        }
        $starts = array_map(static fn(array $period): string => (string) $period['starts_on'], $periods);
        $ends = array_map(static fn(array $period): string => (string) $period['ends_on'], $periods);
        return ['id'=>null,'from'=>min($starts),'to'=>max($ends)];
    }

    private function reportBaseRange():array{$period=(new AcademicPeriodModel())->active();if($period&&$period['starts_on']&&$period['ends_on'])return ['from'=>(string)$period['starts_on'],'to'=>(string)$period['ends_on']];$today=date('Y-m-d');return ['from'=>$today,'to'=>$today];}
    private function reportDate(string $key,string $fallback):string{$value=(string)($_GET[$key]??$fallback);$date=DateTimeImmutable::createFromFormat('Y-m-d',$value);return $date&&$date->format('Y-m-d')===$value?$value:$fallback;}
    public function trash():void
    {
        $model=new AdminTrashModel();$type=(string)($_GET['trash_type']??'users');$error=null;
        try{
            $data=$type==='materials'
                ?$model->supportMaterialDashboard(PaginationService::request())
                :$model->dashboard($type,PaginationService::request());
            $data['materials']=$data['materials']??[];
        }catch(Throwable $e){
            error_log('Admin trash: '.$e->getMessage());$error='No fue posible consultar la Papelera.';
            $data=['loaded'=>false,'users'=>null,'projects'=>null,'materials'=>null,'pagination'=>['total'=>null,'pages'=>null],'active_type'=>$type==='materials'?'materials':($type==='projects'?'projects':'users'),'summary'=>null,'retention'=>[]];
        }
        $s=new AuthSessionService();
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || !empty($_GET['ajax']);
        if ($isAjax) {
            ob_start();
            $trashData = $data;
            $trashError = $error;
            $trashCsrf = $s->csrfToken('admin_trash');
            $trashEndpoints = $this->trashEndpoints();
            include __DIR__ . '/../views/admin/trash.php';
            $html = ob_get_clean();
            if ($error !== null) {
                $this->json(false, $error, ['active_type' => $type], 503);
            }
            $this->json(true, '', [
                'ok' => true,
                'active_type' => $type,
                'html' => $html,
                'summary' => $data['summary']
            ]);
            return;
        }
        View::render('admin/trash',['currentPage'=>'admin-trash','title'=>'Papelera | Administración','bodyClass'=>'admin-trash-page','pageStyles'=>[asset('css/admin-trash.css')],'pageScript'=>asset('js/admin-trash.js'),'trashData'=>$data,'pagePagination'=>$data['pagination'],'trashError'=>$error,'trashCsrf'=>$s->csrfToken('admin_trash'),'trashEndpoints'=>$this->trashEndpoints()]);
    }
    public function trashUser():void{$this->requirePost();$s=$this->trashSession();$id=(int)($_POST['id']??0);try{(new AdminTrashModel())->trashUser($id,(string)($_POST['reason']??''),(int)$s->userId());$this->json(true,'Usuario enviado a la Papelera y acceso revocado.');}catch(InvalidArgumentException $e){$this->activityFailure($s,'user_trashed','Intentó enviar un usuario a la papelera','Papelera','user',$id,'Usuario #'.$id,$e);$this->json(false,$e->getMessage(),[],422);}catch(Throwable $e){$this->activityFailure($s,'user_trashed','Intentó enviar un usuario a la papelera','Papelera','user',$id,'Usuario #'.$id,$e);error_log('Trash user: '.$e->getMessage());$this->json(false,'No fue posible eliminar el usuario.',[],500);}}
    public function restoreTrash():void{$this->trashOperation('restore');}
    public function restoreTrashBatch():void{$this->trashOperation('restore_batch');}
    public function restoreTrashAll():void{$this->trashOperation('restore_all');}
    public function deleteTrashPermanently():void{$this->trashOperation('delete');}
    public function deleteTrashPermanentlyBatch():void{$this->trashOperation('delete_batch');}
    public function emptyTrashCategory():void{$this->trashOperation('empty');}
    private function trashOperation(string $operation):void
    {
        $this->requirePost();$s=$this->trashSession();$entity=(string)($_POST['entity']??'');$ids=$operation==='restore_batch'||$operation==='delete_batch'?(array)($_POST['ids']??[]):[(string)($_POST['id']??'')];
        try{$trash=new AdminTrashModel();$category=$this->trashCategory($entity);$actor=(int)$s->userId();$count=match($operation){'restore'=>$trash->restoreBatch($category,$ids,$actor),'restore_batch'=>$trash->restoreBatch($category,$ids,$actor),'restore_all'=>$trash->restoreAll($category,$actor),'delete'=>$trash->deletePermanentlyBatch($category,$ids,$actor),'delete_batch'=>$trash->deletePermanentlyBatch($category,$ids,$actor),'empty'=>$trash->emptyCategory($category,$actor)};$pending=$trash->consumePhysicalCleanupPending();$message=match($operation){'restore'=>'Elemento restaurado correctamente.','restore_batch'=>'Elementos restaurados correctamente.','restore_all'=>'Categoría restaurada correctamente.','delete'=>'Elemento eliminado definitivamente.','delete_batch'=>'Elementos eliminados definitivamente.','empty'=>'Categoría vaciada correctamente.'};if($pending)$message.=' Los registros se procesaron, pero algunos archivos requieren una limpieza posterior.';$this->json(true,$message,['count'=>$count,'summary'=>$trash->summary(),'filesystem_cleanup_pending'=>count($pending)]);}
        catch(InvalidArgumentException $e){$this->json(false,$e->getMessage(),[],422);}catch(Throwable $e){error_log('Trash operation: '.$e->getMessage());$this->json(false,'No fue posible completar la operación.',[],500);}
    }
    private function trashCategory(string $entity):string{return match($entity){'user','users'=>'users','project','projects'=>'projects','support_material','materials'=>'materials',default=>throw new InvalidArgumentException('La categoría solicitada no es válida.')};}
    private function trashEndpoints():array{return ['user'=>route('admin-trash-user'),'restore'=>route('admin-trash-restore'),'restoreBatch'=>route('admin-trash-restore-batch'),'restoreAll'=>route('admin-trash-restore-all'),'delete'=>route('admin-trash-delete'),'deleteBatch'=>route('admin-trash-delete-batch'),'emptyCategory'=>route('admin-trash-empty-category'),'purge'=>route('admin-trash-purge')];}
    public function purgeTrash():void{$this->requirePost();$s=$this->trashSession();try{$r=(new AdminTrashModel())->purgeExpired((int)$s->userId());$total=(int)$r['users']+(int)$r['projects']+(int)$r['materials'];$message='Se procesaron '.$total.' elementos vencidos.';if(!empty($r['filesystem_cleanup_pending']))$message.=' Los registros se procesaron, pero algunos archivos requieren una limpieza posterior.';if(!empty($r['failed']))$message.=' Algunas entidades no pudieron procesarse.';$this->json(true,$message,$r);}catch(Throwable $e){$this->activityFailure($s,'trash_purged','Intentó ejecutar la eliminación definitiva','Papelera','trash',null,'Elementos vencidos',$e);error_log('Purge trash: '.$e->getMessage());$this->json(false,'No fue posible procesar los elementos vencidos.',[],500);}}
    private function trashSession():AuthSessionService{$s=new AuthSessionService();if(!$s->validateCsrf('admin_trash',(string)($_POST['_csrf']??'')))$this->json(false,'No fue posible validar la solicitud. Recarga la página e inténtalo nuevamente.',[],419);return $s;}
    public function notifications(): void
    {
        $model = new AdminNotificationModel();
        $error = null;
        $data = ['users' => [], 'projects' => [], 'sent' => [], 'pagination' => ['total' => 0, 'pages' => 1], 'summary' => ['sent' => '—', 'recipients' => '—', 'today' => '—'], 'loaded' => false];
        try {
            $data = $model->dashboard(PaginationService::request());
            $data['loaded'] = true;
        } catch (Throwable $exception) {
            error_log('Admin notifications: ' . $exception->getMessage());
            $error = 'No fue posible consultar el centro de notificaciones.';
        }
        $s = new AuthSessionService();
        View::render('admin/notifications', ['currentPage' => 'notifications', 'title' => 'Notificaciones | Administración', 'bodyClass' => 'admin-notifications-page', 'pageStyles' => [asset('css/admin-notifications.css')], 'pageScript' => asset('js/admin-notifications.js'), 'adminNotifications' => $data, 'pagePagination' => $data['pagination'], 'adminNotificationsError' => $error, 'adminNotificationCsrf' => $s->csrfToken('admin_notifications'), 'adminNotificationSendEndpoint' => route('admin-notification-send')]);
    }
    public function sendNotification():void{$this->requirePost();$s=new AuthSessionService();if(!$s->validateCsrf('admin_notifications',(string)($_POST['_csrf']??'')))$this->json(false,'La sesión venció.',[],419);try{$result=(new AdminNotificationModel())->send($_POST,(int)$s->userId());$this->json(true,'Notificación enviada a '.$result['recipients'].' destinatarios.',$result);}catch(InvalidArgumentException $e){$this->json(false,$e->getMessage(),[],422);}catch(Throwable $e){error_log('Admin send notification: '.$e->getMessage());$this->json(false,'No fue posible enviar la notificación.',[],500);}}
    public function notificationRecipients():void{if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET')$this->json(false,'Método no permitido.',[],405);try{$data=(new AdminNotificationModel())->recipientSearch((string)($_GET['kind']??''),(string)($_GET['q']??''),(int)($_GET['semester']??0));$this->json(true,'',$data);}catch(InvalidArgumentException $e){$this->json(false,$e->getMessage(),[],422);}catch(Throwable $e){error_log('Notification recipients: '.$e->getMessage());$this->json(false,'No fue posible consultar destinatarios.',[],500);}}
    public function sendNotificationAudience():void{$this->requirePost();$s=new AuthSessionService();if(!$s->validateCsrf('admin_notifications',(string)($_POST['_csrf']??'')))$this->json(false,'La sesión venció.',[],419);try{$result=(new AdminNotificationModel())->sendAudience($_POST,(int)$s->userId());$this->json(true,'Notificación enviada a '.$result['recipients'].' destinatarios.',$result);}catch(InvalidArgumentException $e){$this->json(false,$e->getMessage(),[],422);}catch(Throwable $e){error_log('Admin audience notification: '.$e->getMessage());$this->json(false,'No fue posible enviar la notificación.',[],500);}}
    public function publishProject():void{$this->requirePost();$s=new AuthSessionService();if(!$s->validateCsrf('admin_repository',(string)($_POST['_csrf']??'')))$this->json(false,'La sesión venció.',[],419);$id=(int)($_POST['id']??0);$action=(string)($_POST['action']??'');$capabilities=(new ProjectCapabilityService())->forProjectId($id,'repository');$required=in_array($action,['presentation','unpresentation'],true)?'manage_files':'manage_publication';if(empty($capabilities[$required]))$this->json(false,'No tienes autorización para completar esta acción sobre el proyecto.',[],403);try{$model=new AdminRepositoryModel();if($action==='presentation'||$action==='unpresentation'){$model->setPresentationFile($id,$action==='presentation'?(int)($_POST['file_id']??0):null,(int)$s->userId());$this->json(true,$action==='unpresentation'?'Archivo de presentación eliminado.':'Archivo de presentación actualizado correctamente.');}if($action==='availability'){$available=filter_var($_POST['is_available']??null,FILTER_VALIDATE_BOOL,FILTER_NULL_ON_FAILURE);if($available===null)$this->json(false,'La disponibilidad solicitada no es válida.',[],422);$model->setAvailability($id,$available,(int)$s->userId());$this->json(true,$available?'Proyecto marcado como disponible.':'Proyecto marcado como no disponible.');}if($action==='publish')$this->json(false,'La publicación depende del flujo académico y no puede realizarse desde la administración.',[],403);if($action==='restore'){$model->restorePublication($id,(int)$s->userId());$this->json(true,'La publicación fue restaurada correctamente.');}$model->setPublished($id,false,(int)$s->userId());$this->json(true,'Proyecto retirado del repositorio. Permanece disponible en Proyectos.');}catch(InvalidArgumentException $e){$this->json(false,$e->getMessage(),[],422);}catch(Throwable $e){error_log('Publish project: '.$e->getMessage());$this->json(false,'No fue posible actualizar la publicación.',[],500);}}

    public function trashRepositoryProject(): void
    {
        $this->requirePost();
        $session = new AuthSessionService();
        if (!$session->validateCsrf('admin_repository', (string) ($_POST['_csrf'] ?? ''))) {
            $this->json(false, 'La sesión venció.', [], 419);
        }
        $id = (int) ($_POST['id'] ?? 0);
        $capabilities = (new ProjectCapabilityService())->forProjectId($id, 'repository');
        if (empty($capabilities['manage_publication'])) {
            $this->json(false, 'No tienes autorización para completar esta acción sobre el proyecto.', [], 403);
        }
        try {
            $trash = new AdminTrashModel();
            $trash->trashRepositoryProject($id, (string) ($_POST['reason'] ?? ''), (int) $session->userId());
            $this->json(true, 'Proyecto enviado a Papelera correctamente.', ['summary' => $trash->summary()]);
        } catch (InvalidArgumentException $exception) {
            $this->json(false, $exception->getMessage(), [], 422);
        } catch (Throwable $exception) {
            error_log('Repository trash project: ' . $exception->getMessage());
            $this->json(false, 'No fue posible enviar el proyecto a Papelera.', [], 500);
        }
    }

    public function saveSupportMaterial():void
    {
        $this->requirePost();$session=new AuthSessionService();
        // RequestSizeGuard handles oversized multipart bodies before this controller runs.
        if(!$session->validateCsrf('admin_repository',(string)($_POST['_csrf']??'')))$this->json(false,'La solicitud contiene un token CSRF inválido.',[],419);
        $id=(int)($_POST['id']??0);$title=$this->normalizeAuditText($_POST['title']??'');
        if($id===0&&!$session->hasAdminAccess()){
            $_POST['publisher']=trim((string)$session->name());
        }
        $initialUploads=[];
        if($id===0&&isset($_FILES['initial_files']['name'])&&is_array($_FILES['initial_files']['name'])){
            foreach(array_keys($_FILES['initial_files']['name']) as $index)$initialUploads[]=[
                'name'=>$_FILES['initial_files']['name'][$index]??'',
                'type'=>$_FILES['initial_files']['type'][$index]??'',
                'tmp_name'=>$_FILES['initial_files']['tmp_name'][$index]??'',
                'error'=>$_FILES['initial_files']['error'][$index]??UPLOAD_ERR_NO_FILE,
                'size'=>$_FILES['initial_files']['size'][$index]??0,
            ];
        }
        if($initialUploads!==[]){
            $limits=(new SupportMaterialFileService())->limits();
            if(count($initialUploads)>(int)$limits['max_operation_files'])$this->json(false,'Puedes agregar hasta '.$limits['max_operation_files'].' archivos por operación.',[],422);
            if(array_sum(array_map(static fn(array $upload):int=>(int)($upload['size']??0),$initialUploads))>(int)$limits['max_operation_bytes'])$this->json(false,'La selección completa supera el límite de '.$limits['max_operation_mb'].' MB por operación.',[],422);
        }
        $capabilities=new SupportMaterialCapabilityService();
        try{
            if($id===0)$capabilities->assertCanCreate($session);
            $storedInitialFiles=[];
            try{
            $result=Database::transaction(function(PDO $database)use($id,$title,$session,$capabilities,$initialUploads,&$storedInitialFiles):array{
                $model=new SupportMaterialModel();
                $auditChanges=[];
                if($id>0){
                    $current=$model->findByIdForUpdate($id);
                    if($current===null)throw new InvalidArgumentException('El material ya no está disponible.');
                    $capabilities->assertCanManage($session,$current);
                    $submittedMaterialType=$model->resolveMaterialType($_POST,(string)($_POST['controlled_material_type']??'')==='1');
                    $resolvedKeywords=$model->resolveKeywords(
                        $_POST,
                        (array)($current['keywords']??[]),
                        (string)($_POST['controlled_keywords']??'')==='1'
                    );
                    $submittedKeywords=$this->normalizeAuditKeywords($resolvedKeywords);
                    $currentKeywords=$this->normalizeAuditKeywords((array)($current['keywords']??[]));
                    $newCategoryId=(int)($_POST['category_id']??0);
                    $newCategoryName=$model->categoryName($newCategoryId);
                    if($newCategoryName===null)throw new InvalidArgumentException('Selecciona una categoría válida.');
                    $auditableFields=[
                        'title'=>['label'=>'Título','old'=>$this->normalizeAuditText($current['title']??''),'new'=>$title],
                        'material_type'=>['label'=>'Tipo de material','old'=>$this->normalizeAuditText($current['material_type']??''),'new'=>$this->normalizeAuditText($submittedMaterialType)],
                        'description'=>['label'=>'Descripción corta','old'=>$this->normalizeAuditText($current['description']??'',true),'new'=>$this->normalizeAuditText($_POST['description']??'',true)],
                        'full_description'=>['label'=>'Descripción completa','old'=>$this->normalizeAuditText($current['full_description']??'',true),'new'=>$this->normalizeAuditText($_POST['full_description']??'',true)],
                    ];
                    foreach($auditableFields as $field=>$change){
                        if($change['old']!==$change['new'])$auditChanges[]=['field'=>$field]+$change;
                    }
                    if((int)$current['category_id']!==$newCategoryId)$auditChanges[]=['field'=>'category','label'=>'Categoría','old'=>(string)($current['category_label']??''),'new'=>$newCategoryName];
                    if($currentKeywords['comparison']!==$submittedKeywords['comparison'])$auditChanges[]=['field'=>'keywords','label'=>'Palabras clave','old'=>$currentKeywords['display'],'new'=>$submittedKeywords['display']];
                    if($auditChanges===[])return ['id'=>$id,'no_changes'=>true];
                }
                $saved=$model->save($_POST,(int)$session->userId());
                if($id===0&&$initialUploads!==[]){
                    $fileService=new SupportMaterialFileService();
                    foreach($initialUploads as $upload){
                        $stored=$fileService->store($saved,$upload);
                        $storedInitialFiles[]=$stored;
                        $fileId=$model->addFile($saved,$stored,(int)$session->userId());
                        (new AdminActivityService($database))->record(
                            (int)$session->userId(),'support_material.file_added','Agregó un archivo al material',
                            'Repositorio','support_material',$saved,$stored['original_name'],'correct',[
                                'file_id'=>$fileId,'name'=>$stored['original_name'],'extension'=>$stored['extension'],
                                'mime_type'=>$stored['mime_type'],'size_bytes'=>$stored['size_bytes'],'is_package'=>false,
                            ]
                        );
                    }
                }
                (new AdminActivityService($database))->record(
                    (int)$session->userId(),
                    $id?'support_material.updated':'support_material.created',
                    $id?'Editó la información del material':'Creó el material de apoyo',
                    'Repositorio','support_material',$saved,$title?:'Material de apoyo','correct',
                    ['schema_version'=>1,'changes'=>$auditChanges]
                );
                return ['id'=>$saved,'no_changes'=>false];
            });
            }catch(Throwable $error){
                $fileService=new SupportMaterialFileService();
                foreach($storedInitialFiles as $stored){
                    if(!$fileService->discard($stored))error_log('Support material initial upload cleanup failed.');
                }
                throw $error;
            }
            if($result['no_changes'])$this->json(true,'La información ya se encuentra actualizada.',['id'=>$result['id'],'no_changes'=>true]);
            $saved=(int)$result['id'];
            $this->json(true,$id?'Material actualizado correctamente.':'Material creado correctamente.',['id'=>$saved]);
        }
        catch(SupportMaterialAccessException $error){$this->json(false,$error->getMessage(),[],$error->httpStatus);}
        catch(InvalidArgumentException $error){$this->activityFailure($session,$id?'support_material.update_failed':'support_material.create_failed',$id?'Intentó editar material de apoyo':'Intentó crear material de apoyo','Repositorio','support_material',$id?:null,$title?:'Material de apoyo',$error);$this->json(false,$error->getMessage(),[],422);}
        catch(Throwable $error){$this->activityFailure($session,$id?'support_material.update_failed':'support_material.create_failed',$id?'Intentó editar material de apoyo':'Intentó crear material de apoyo','Repositorio','support_material',$id?:null,$title?:'Material de apoyo',$error);error_log('Support material save: '.$error->getMessage());$this->json(false,'No fue posible guardar el material.',[],500);}
    }

    public function supportMaterialHistory():void
    {
        $session = new AuthSessionService();
        if (!$session->isAdminModeActive() || !$session->hasAdminAccess()) $this->json(false, 'No tienes permiso para consultar el historial administrativo.', [], 403);
        if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET')$this->json(false,'Método no permitido.',[],405);
        $id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT);
        $offset=filter_var($_GET['offset']??0,FILTER_VALIDATE_INT);
        if($id===false||$id===null||(int)$id<1)$this->json(false,'El material solicitado no es válido.',[],422);
        if((new SupportMaterialModel())->findById((int)$id,true)===null)$this->json(false,'El material solicitado no existe.',[],404);
        try{
            $activityModel=new AdminActivityModel();
            $history=$activityModel->forEntity('support_material',(int)$id,20,$offset===false?0:(int)$offset);
            $this->json(true,'Historial administrativo cargado.',$history);
        }catch(Throwable $error){
            error_log('Support material history: '.$error->getMessage());
            $this->json(false,'No fue posible cargar el historial administrativo.',[],500);
        }
    }

    public function projectHistory(): void
    {
        $session = new AuthSessionService();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') $this->json(false, 'Método no permitido.', [], 405);
        if (!$session->hasAdminAccess()) $this->json(false, 'No tienes autorización para consultar el historial administrativo.', [], 403);
        $context = strtolower(trim((string) ($_GET['context'] ?? '')));
        if (!in_array($context, ['academic_management', 'repository'], true)) $this->json(false, 'El contexto del historial no es válido.', [], 422);
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $offset = filter_var($_GET['offset'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($id === false || $id === null) $this->json(false, 'El proyecto solicitado no es válido.', [], 422);
        if ($offset === false) $this->json(false, 'El desplazamiento solicitado no es válido.', [], 422);
        $capabilities = (new ProjectCapabilityService())->forProjectId((int) $id, $context);
        if (empty($capabilities['view_admin_history'])) $this->json(false, 'No tienes autorización para consultar el historial administrativo.', [], 403);
        try {
            $exists = Database::connection()->prepare('SELECT status FROM projects WHERE id=:id AND deleted_at IS NULL');
            $exists->execute(['id' => (int) $id]);
            $project = $exists->fetch();
            if (!$project) $this->json(false, 'El proyecto solicitado no existe o fue eliminado.', [], 404);
            if ($context === 'repository' && (string) $project['status'] !== 'published') $this->json(false, 'El proyecto no está disponible en el Repositorio.', [], 404);
            $history = (new ProjectAuditHistoryModel())->forProject((int) $id, 20, (int) $offset, $context);
            $history['context'] = $context;
            $this->json(true, 'Historial administrativo cargado.', $history);
        } catch (Throwable $error) {
            error_log('Project administrative history: ' . $error->getMessage());
            $this->json(false, 'No fue posible cargar el historial administrativo.', [], 500);
        }
    }

    public function cleanupSupportMaterialHistory():void
    {
        $this->requirePost();$session=new AuthSessionService();
        if(!$session->validateCsrf('admin_repository',(string)($_POST['_csrf']??'')))$this->json(false,'La sesión venció.',[],419);
        $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT);
        if($id===false||$id===null||(int)$id<1)$this->json(false,'El material solicitado no es válido.',[],422);
        if((string)($_POST['confirmation']??'')!=='ELIMINAR')$this->json(false,'Escribe ELIMINAR para confirmar la depuración.',[],422);
        try{
            $deleted=Database::transaction(function(PDO $database)use($id,$session):int{
                $material=(new SupportMaterialModel())->findByIdForUpdate((int)$id);
                if($material===null)throw new InvalidArgumentException('El material solicitado no existe.');
                $deleted=(new AdminActivityModel())->deleteIncompleteSupportMaterialEvents((int)$id);
                if($deleted>0){
                    (new AdminActivityService($database))->record(
                        (int)$session->userId(),'support_material.history_cleaned',
                        'Eliminó registros antiguos sin detalle','Repositorio','support_material',(int)$id,
                        (string)($material['title']??'Material de apoyo'),'correct',
                        ['schema_version'=>1,'deleted_count'=>$deleted,'reason'=>'legacy_events_without_change_details']
                    );
                }
                return $deleted;
            });
            if($deleted===0)$this->json(true,'No existen registros antiguos sin detalle para eliminar.',['deleted_count'=>0]);
            $this->json(true,$deleted===1?'Se eliminó 1 registro antiguo sin detalle.':'Se eliminaron '.$deleted.' registros antiguos sin detalle.',['deleted_count'=>$deleted]);
        }catch(InvalidArgumentException $error){
            $this->json(false,$error->getMessage(),[],422);
        }catch(Throwable $error){
            error_log('Support material history cleanup: '.$error->getMessage());
            $this->json(false,'No fue posible eliminar los registros antiguos.',[],500);
        }
    }

    public function changeSupportMaterialStatus():void
    {
        $this->requirePost();$session=new AuthSessionService();
        if(!$session->validateCsrf('admin_repository',(string)($_POST['_csrf']??'')))$this->json(false,'La sesión venció.',[],419);
        $id=(int)($_POST['id']??0);$status=(string)($_POST['status']??'');$action=(string)($_POST['action']??'publication');
        try{
            $model=new SupportMaterialModel();$actor=(int)$session->userId();
            $reasonCode=(string)($_POST['reason_code']??'');$reasonDetail=trim((string)($_POST['reason_detail']??''));
            $administrativeReasons=[
                'temporary_update'=>'Actualización temporal del contenido','administrative_review'=>'Revisión administrativa',
                'files_pending'=>'Archivos pendientes de corrección','outdated'=>'Información desactualizada',
                'temporary_suspension'=>'Acceso suspendido temporalmente','corrections_completed'=>'Correcciones completadas',
                'content_updated'=>'Contenido actualizado','files_verified'=>'Archivos verificados',
                'review_completed'=>'Revisión administrativa finalizada','incorrect'=>'Publicación incorrecta',
                'pending_review'=>'Material pendiente de revisión','incomplete_files'=>'Archivos incompletos',
                'replaced'=>'Material reemplazado','duplicate'=>'Contenido duplicado','not_required'=>'Ya no es requerido',
                'other'=>'Otro motivo',
            ];
            $reasonRequired=$action==='availability'||$action==='trash'||$status==='withdrawn';
            if($reasonRequired&&!isset($administrativeReasons[$reasonCode]))throw new InvalidArgumentException('Selecciona un motivo válido.');
            if($reasonCode!==''&&!isset($administrativeReasons[$reasonCode]))throw new InvalidArgumentException('Selecciona un motivo válido.');
            $allowedReasonCodes=$action==='trash'
                ?['duplicate','outdated','replaced','incorrect','not_required','other']
                :($action==='availability'
                    ?((string)($_POST['is_available']??'')==='1'
                        ?['corrections_completed','content_updated','files_verified','review_completed','other']
                        :['temporary_update','administrative_review','files_pending','outdated','temporary_suspension','other'])
                    :($status==='withdrawn'
                        ?['incorrect','outdated','pending_review','incomplete_files','replaced','other']
                        :['review_completed','content_updated','corrections_completed','other']));
            if($reasonCode!==''&&!in_array($reasonCode,$allowedReasonCodes,true))throw new InvalidArgumentException('El motivo no corresponde a la acción solicitada.');
            if($reasonCode==='other'&&mb_strlen($reasonDetail)<5)throw new InvalidArgumentException('Detalla el motivo con al menos cinco caracteres.');
            if(mb_strlen($reasonDetail)>300)throw new InvalidArgumentException('El detalle del motivo supera los 300 caracteres.');
            $reasonLabel=$reasonCode===''?'':$administrativeReasons[$reasonCode];
            if($action==='trash'){
                $redirect=route('admin-repository').'&tab=materials';
                Database::transaction(function(PDO $database)use($id,$reasonLabel,$actor,$reasonCode,$reasonDetail):void{
                    (new AdminTrashModel())->trashSupportMaterialAtomic($database,$id,$reasonLabel,$actor,$reasonCode,$reasonDetail);
                });
                $this->json(true,'Material enviado a Papelera correctamente.',['redirect'=>$redirect]);
            }
            $material=Database::transaction(function(PDO $database)use($model,$id,$status,$action,$actor,$reasonCode,$reasonLabel,$reasonDetail):array{
                $material=$model->findByIdForUpdate($id);
                if($material===null)throw new InvalidArgumentException('El material ya no está disponible.');
                if($action==='availability'){
                    $available=filter_var($_POST['is_available']??null,FILTER_VALIDATE_BOOL,FILTER_NULL_ON_FAILURE);
                    if($available===null)throw new InvalidArgumentException('La disponibilidad solicitada no es válida.');
                    $previous=$model->setAvailability($id,$available,$actor);
                    (new AdminActivityService($database))->record($actor,'support_material_availability_changed',$available?'Marcó material como disponible':'Marcó material como no disponible','Repositorio','support_material',$id,(string)$material['title'],'correct',['previous_available'=>$previous,'is_available'=>$available,'reason_code'=>$reasonCode,'reason'=>$reasonLabel,'reason_detail'=>$reasonDetail]);
                    $material['availability_result']=$available;
                    return $material;
                }
                if($status==='published'){
                    $requestedPresentation=(int)($_POST['presentation_file_id']??0);
                    if($requestedPresentation>0&&$requestedPresentation!==(int)($material['presentation_file_id']??0)){
                        $change=$model->setPresentationFile($id,$requestedPresentation,$actor);
                        (new AdminActivityService($database))->record(
                            $actor,empty($change['previous_file_id'])?'support_material.presentation_selected':'support_material.presentation_changed',
                            empty($change['previous_file_id'])?'Seleccionó el archivo de presentación':'Cambió el archivo de presentación',
                            'Repositorio','support_material',$id,$change['name'],'correct',
                            ['previous_file_id'=>$change['previous_file_id'],'new_file_id'=>$change['file_id'],'previous_name'=>$change['previous_name'],'new_name'=>$change['new_name'],'context'=>'publication']
                        );
                    }
                }
                $change=$model->setStatus($id,$status,$actor);
                $publishing=$status==='published';
                (new AdminActivityService($database))->record($actor,$publishing?'support_material_published':'support_material_withdrawn',$publishing?'Publicó material de apoyo':'Retiró material de apoyo','Repositorio','support_material',$id,$material['title']??'Material #'.$id,'correct',['previous_status'=>$change['previous_status'],'new_status'=>$change['new_status'],'previous_available'=>$change['previous_available'],'is_available'=>$change['is_available'],'reason_code'=>$reasonCode,'reason'=>$reasonLabel,'reason_detail'=>$reasonDetail,'republication'=>$publishing&&$change['was_previously_published']]);
                return $material;
            });
            if($action==='availability'){
                $available=(bool)$material['availability_result'];
                $this->json(true,$available?'El material volvió a estar disponible para consulta y descarga.':'El material permanece publicado, pero ya no admite consulta ni descarga.',['status_key'=>'published','status_label'=>'Publicado','is_available'=>$available,'availability_label'=>$available?'Disponible':'No disponible']);
            }
            $publishing=$status==='published';
            $updated=$model->findById($id,true);
            $this->json(true,$publishing?'Material publicado correctamente.':'Material retirado del repositorio.',['status_key'=>$updated['status_key'],'status_label'=>$publishing?'Publicado':'Retirado','is_available'=>(bool)$updated['is_available'],'availability_label'=>$updated['is_available']?'Disponible':'No disponible','published_at'=>$updated['published_at']]);
        }
        catch(InvalidArgumentException $error){$this->json(false,$error->getMessage(),[],422);}
        catch(Throwable $error){error_log('Support material status: '.$error->getMessage());$this->json(false,'No se pudo completar la acción. No se realizaron cambios.',[],500);}
    }

    public function changeSupportMaterialFile():void
    {
        $this->requirePost();$session=new AuthSessionService();
        if(!$session->validateCsrf('admin_repository',(string)($_POST['_csrf']??'')))$this->json(false,'La solicitud contiene un token CSRF inválido.',[],419);
        $materialId=(int)($_POST['material_id']??0);$action=(string)($_POST['action']??'add');
        try{
            $model=new SupportMaterialModel();
            (new SupportMaterialCapabilityService())->assertCanManage($session,$model->findById($materialId,true));
            if($action==='purge_restorable'&&!$session->hasAdminAccess()){
                throw new SupportMaterialAccessException('No tienes autorización para eliminar archivos definitivamente.');
            }
            if($action==='list_restorable'){
                if($model->findById($materialId,true)===null)$this->json(false,'El material ya no está disponible.',[],404);
                $settings=(new SystemSettingModel())->all();
                $hours=(int)($settings['withdrawn_file_restore_hours']??24);
                $files=$model->restorableFiles($materialId);
                $this->json(true,'Archivos retirados consultados correctamente.',['files'=>$files,'count'=>count($files),'restore_hours'=>$hours]);
            }
            if($action==='inspect_restore'){
                $fileId=(int)($_POST['file_id']??0);
                $inspection=$model->inspectFileRestore($materialId,$fileId);
                $this->json(true,'Archivo disponible para restaurar.',$inspection);
            }
            if($action==='purge_restorable'){
                $fileIds=array_values(array_unique(array_map('intval',(array)($_POST['file_ids']??[]))));
                $actor=(int)$session->userId();
                $database=Database::connection();
                $staged=[];
                try{
                    $database->beginTransaction();
                    if($model->findByIdForUpdate($materialId)===null){
                        throw new InvalidArgumentException('El material ya no está disponible.');
                    }
                    $files=$model->inspectPermanentFileDeletion($materialId,$fileIds,true);
                    foreach($files as $file){
                        $source=(string)$file['absolute_path'];
                        $temporary=$source.'.purge-'.bin2hex(random_bytes(8));
                        if(!rename($source,$temporary)){
                            throw new RuntimeException('No fue posible preparar la eliminación física del archivo.');
                        }
                        $staged[]=['source'=>$source,'temporary'=>$temporary];
                    }
                    $model->markFilesPermanentlyDeleted($materialId,$fileIds,$actor);
                    $auditFiles=array_map(static fn(array $file):array=>[
                        'file_id'=>$file['id'],'original_name'=>$file['name'],
                        'extension'=>$file['extension'],'size_bytes'=>$file['size_bytes'],
                        'created_by'=>$file['created_by'],'created_by_name'=>$file['created_by_name'],
                        'deleted_at'=>$file['deleted_at'],'deleted_by'=>$file['deleted_by'],
                        'deleted_by_name'=>$file['deleted_by_name'],
                    ],$files);
                    (new AdminActivityService($database))->record(
                        $actor,'support_material.file_purged',
                        count($files)===1?'Eliminó definitivamente un archivo retirado':'Eliminó definitivamente archivos retirados',
                        'Repositorio','support_material',$materialId,
                        count($files)===1?$files[0]['name']:count($files).' archivos','correct',[
                            'material_id'=>$materialId,'file_ids'=>$fileIds,'files'=>$auditFiles,
                            'purged_at'=>(new DateTimeImmutable('now',new DateTimeZone('UTC')))->format(DATE_ATOM),
                            'purged_by'=>$actor,'restore_hours'=>SupportMaterialModel::RESTORE_HOURS,
                            'result'=>'correct',
                        ]
                    );
                    $database->commit();
                }catch(Throwable $error){
                    if($database->inTransaction())$database->rollBack();
                    foreach(array_reverse($staged) as $stagedFile){
                        if(is_file($stagedFile['temporary'])&&!is_file($stagedFile['source'])){
                            @rename($stagedFile['temporary'],$stagedFile['source']);
                        }
                    }
                    throw $error;
                }
                foreach($staged as $stagedFile){
                    if(is_file($stagedFile['temporary'])&&!unlink($stagedFile['temporary'])){
                        error_log('Support material purge cleanup failed: '.$stagedFile['temporary']);
                    }
                }
                $remaining=$model->restorableFiles($materialId);
                $this->json(true,count($fileIds)===1?'Archivo eliminado definitivamente.':'Archivos eliminados definitivamente.',[
                    'purged_file_ids'=>$fileIds,'restorable_count'=>count($remaining),
                ]);
            }
            if($action==='restore'){
                $fileId=(int)($_POST['file_id']??0);
                $confirmedName=trim((string)($_POST['final_name']??''));
                $actor=(int)$session->userId();
                $restored=Database::transaction(function(PDO $database)use($model,$materialId,$fileId,$actor,$confirmedName):array{
                    if($model->findByIdForUpdate($materialId)===null){
                        throw new InvalidArgumentException('El material ya no está disponible.');
                    }
                    $restored=$model->restoreFile($materialId,$fileId,$actor,$confirmedName);
                    (new AdminActivityService($database))->record(
                        $actor,'support_material.file_restored','Restauró un archivo del material',
                        'Repositorio','support_material',$materialId,$restored['final_name'],'correct',[
                            'material_id'=>$materialId,
                            'file_id'=>$fileId,
                            'original_name'=>$restored['original_name'],
                            'final_name'=>$restored['final_name'],
                            'deleted_at'=>$restored['deleted_at'],
                            'deleted_by'=>$restored['deleted_by'],
                            'deleted_by_name'=>$restored['deleted_by_name'],
                            'name_conflict'=>$restored['conflict'],
                            'renamed'=>$restored['original_name']!==$restored['final_name'],
                            'restore_hours'=>SupportMaterialModel::RESTORE_HOURS,
                            'result'=>'correct',
                        ]
                    );
                    return $restored;
                });
                $query='&material_id='.$materialId.'&file_id='.$fileId;
                $extension=(string)$restored['extension'];
                $material=$model->findById($materialId,true);
                $activeFile=current(array_filter(
                    (array)($material['files']??[]),
                    static fn(array $file):bool=>(int)$file['id']===$fileId
                ))?:null;
                $package=$material?(new SupportMaterialPackageService())->describe($material):['available'=>false,'file_count'=>0,'source'=>'generated'];
                $remaining=$model->restorableFiles($materialId);
                $this->json(true,'Archivo restaurado correctamente.',[
                    'file'=>[
                        'id'=>$fileId,'name'=>$restored['final_name'],'extension'=>$extension,
                        'type'=>mb_strtoupper($extension),'size_label'=>$restored['size'],
                        'size_bytes'=>(int)$restored['size_bytes'],'is_archive'=>$extension==='zip',
                        'preview_supported'=>in_array($extension,['pdf','docx','png','jpg','jpeg','webp','txt'],true)||$extension==='zip',
                        'preview_type'=>$extension==='zip'?'zip':(in_array($extension,['jpg','jpeg','png','webp'],true)?'image':$extension),
                        'preview_url'=>route('support-material-preview').$query,
                        'zip_url'=>$extension==='zip'?route('support-material-zip-list').$query:'',
                        'zip_entry_preview_url'=>$extension==='zip'?route('support-material-zip-entry-preview').$query:'',
                        'zip_entry_download_url'=>$extension==='zip'?route('support-material-zip-entry-download').$query:'',
                        'download_url'=>route('support-material-download').$query,
                        'presentation'=>(bool)($activeFile['presentation']??false),
                        'sort_order'=>(int)$restored['sort_order'],
                    ],
                    'restorable_count'=>count($remaining),
                    'package'=>[
                        'available'=>(bool)$package['available'],'file_count'=>(int)$package['file_count'],
                        'size_bytes'=>(int)($package['size_bytes']??0),'size'=>(string)($package['size']??''),
                        'source'=>(string)$package['source'],
                        'download_url'=>!empty($package['available'])?route('support-material-package-download').'&material_id='.$materialId:'',
                    ],
                ]);
            }
            if($action==='presentation'||$action==='unpresentation'){
                $requestedFileId=(int)($_POST['file_id']??0);
                if($requestedFileId<1)throw new InvalidArgumentException('El archivo seleccionado no es válido.');
                $fileId=$action==='presentation'?$requestedFileId:null;$actor=(int)$session->userId();
                $change=Database::transaction(function(PDO $database)use($model,$materialId,$fileId,$requestedFileId,$actor):array{
                    if($model->findByIdForUpdate($materialId)===null)throw new InvalidArgumentException('El material ya no está disponible.');
                    $change=$model->setPresentationFile($materialId,$fileId,$actor,$fileId===null?$requestedFileId:null);
                    $elementLabel=$change['name']??'Archivo #'.$requestedFileId;
                    (new AdminActivityService($database))->record(
                        $actor,
                        $fileId===null?'support_material.presentation_removed':(empty($change['previous_file_id'])?'support_material.presentation_selected':'support_material.presentation_changed'),
                        $fileId===null?'Quitó el archivo de presentación':(empty($change['previous_file_id'])?'Seleccionó el archivo de presentación':'Cambió el archivo de presentación'),
                        'Repositorio','support_material',$materialId,$elementLabel,'correct',
                        ['previous_file_id'=>$change['previous_file_id'],'new_file_id'=>$change['file_id'],'previous_name'=>$change['previous_name'],'new_name'=>$change['new_name']]
                    );
                    return $change;
                });
                $this->json(true,$fileId===null?'Archivo de presentación eliminado correctamente.':'Archivo de presentación actualizado correctamente.',
                    ['file_id'=>$requestedFileId,'presentation'=>$fileId!==null]+$change);
            }
            if($action==='replace'){
                $fileId=(int)($_POST['file_id']??0);
                if($fileId<1)throw new InvalidArgumentException('El archivo seleccionado no es válido.');
                $upload=$_FILES['file']??null;
                if(!is_array($upload))throw new InvalidArgumentException('Selecciona el archivo de reemplazo.');
                $actor=(int)$session->userId();
                $fileService=new SupportMaterialFileService();
                $stored=null;
                try{
                    $stored=$fileService->store($materialId,$upload);
                    $replacement=Database::transaction(function(PDO $database)use($model,$materialId,$fileId,$stored,$actor):array{
                        if($model->findByIdForUpdate($materialId)===null){
                            throw new InvalidArgumentException('El material ya no está disponible.');
                        }
                        $replacement=$model->replaceFile($materialId,$fileId,$stored,$actor);
                        (new AdminActivityService($database))->record(
                            $actor,'support_material.file_replaced','Reemplazó un archivo del material',
                            'Repositorio','support_material',$materialId,$stored['original_name'],'correct',[
                                'file_id'=>$fileId,
                                'version_id'=>$replacement['version_id'],
                                'previous_file'=>$replacement['old'],
                                'new_file'=>$replacement['new'],
                                'presentation_unchanged'=>$replacement['presentation'],
                            ]
                        );
                        return $replacement;
                    });
                }catch(Throwable $error){
                    if(is_array($stored)&&!$fileService->discard($stored)){
                        error_log('Support material replacement cleanup failed material='.$materialId.' file='.$fileId);
                    }
                    throw $error;
                }
                $extension=(string)$stored['extension'];
                $query='&material_id='.$materialId.'&file_id='.$fileId;
                $material=$model->findById($materialId,true);
                $package=$material?(new SupportMaterialPackageService())->describe($material):['available'=>false,'file_count'=>0,'source'=>'generated'];
                $this->json(true,'Archivo reemplazado correctamente.',[
                    'file'=>[
                        'id'=>$fileId,'name'=>$stored['original_name'],'extension'=>$extension,
                        'type'=>mb_strtoupper($extension),'size_label'=>ArchiveService::formatBytes((int)$stored['size_bytes']),
                        'size_bytes'=>(int)$stored['size_bytes'],'is_archive'=>$extension==='zip',
                        'preview_supported'=>in_array($extension,['pdf','docx','png','jpg','jpeg','webp','txt'],true)||$extension==='zip',
                        'preview_type'=>$extension==='zip'?'zip':(in_array($extension,['jpg','jpeg','png','webp'],true)?'image':$extension),
                        'preview_url'=>route('support-material-preview').$query,
                        'zip_url'=>$extension==='zip'?route('support-material-zip-list').$query:'',
                        'download_url'=>route('support-material-download').$query,
                        'presentation'=>(bool)$replacement['presentation'],
                        'sort_order'=>(int)$replacement['sort_order'],
                    ],
                    'previous_file'=>$replacement['old'],
                    'version_id'=>$replacement['version_id'],
                    'package'=>[
                        'available'=>(bool)$package['available'],'file_count'=>(int)$package['file_count'],
                        'size_bytes'=>(int)($package['size_bytes']??0),'size'=>(string)($package['size']??''),
                        'source'=>(string)$package['source'],
                        'download_url'=>!empty($package['available'])?route('support-material-package-download').'&material_id='.$materialId:'',
                    ],
                ]);
            }
            if($action==='remove'||$action==='remove_multiple'){
                $rawIds=$action==='remove_multiple'?($_POST['file_ids']??[]):[$_POST['file_id']??0];
                if(!is_array($rawIds))$this->json(false,'La selección de archivos no es válida.',[],422);
                if(count($rawIds)>20)$this->json(false,'Puedes retirar hasta 20 archivos por operación.',[],422);
                $fileIds=array_values(array_unique(array_map(static fn(mixed $id):int=>(int)$id,$rawIds)));
                if($fileIds===[]||in_array(0,$fileIds,true))$this->json(false,'Selecciona al menos un archivo válido.',[],422);
                $actor=(int)$session->userId();
                $removal=Database::transaction(function(PDO $database)use($model,$materialId,$fileIds,$actor):array{
                    $material=$model->findByIdForUpdate($materialId);
                    if($material===null)throw new InvalidArgumentException('El material ya no está disponible.');
                    $presentationId=(int)($material['presentation_file_id']??0);
                    $presentationChange=null;
                    if($presentationId>0&&in_array($presentationId,$fileIds,true)){
                        $presentationChange=$model->setPresentationFile($materialId,null,$actor,$presentationId);
                    }
                    $files=$model->removeAdditionalFiles($materialId,$fileIds,$actor);
                    $activity=new AdminActivityService($database);
                    if($presentationChange!==null){
                        $presentationFile=current(array_filter(
                            $files,
                            static fn(array $file):bool=>(int)$file['id']===$presentationId
                        ))?:null;
                        $activity->record(
                            $actor,'support_material.presentation_removed','Quitó el archivo de presentación',
                            'Repositorio','support_material',$materialId,
                            (string)($presentationFile['name']??'Archivo #'.$presentationId),'correct',
                            ['previous_file_id'=>$presentationChange['previous_file_id'],'new_file_id'=>null,'previous_name'=>$presentationChange['previous_name'],'new_name'=>null,'reason'=>'presentation_file_retired']
                        );
                    }
                    foreach($files as $file)$activity->record(
                            $actor,'support_material.file_removed','Retiró un archivo del material',
                            'Repositorio','support_material',$materialId,$file['name'],'correct',[
                                'file_id'=>$file['id'],'name'=>$file['name'],'extension'=>$file['extension'],
                                'size_bytes'=>$file['size_bytes'],'presentation'=>(int)$file['id']===$presentationId,
                            ]
                        );
                    return ['files'=>$files,'presentation_removed'=>$presentationChange!==null];
                });
                $removed=$removal['files'];
                $material=$model->findById($materialId,true);
                $package=$material?(new SupportMaterialPackageService())->describe($material):['available'=>false,'file_count'=>0,'source'=>'generated'];
                $removedIds=array_values(array_map(static fn(array $file):int=>(int)$file['id'],$removed));
                $removedCount=count($removedIds);$availableCount=count((array)($material['files']??[]));
                $message=$removedCount===1?'Archivo retirado correctamente.':$removedCount.' archivos retirados correctamente.';
                $packageDescriptor=[
                    'available'=>(bool)$package['available'],'file_count'=>(int)$package['file_count'],
                    'size_bytes'=>(int)($package['size_bytes']??0),'size'=>(string)($package['size']??''),
                    'source'=>(string)$package['source'],
                    'download_url'=>!empty($package['available'])?route('support-material-package-download').'&material_id='.$materialId:'',
                ];
                $currentPresentationId=(int)($material['presentation_file_id']??0);
                $this->json(true,$message,['removed'=>$removed,'removed_file_ids'=>$removedIds,'removed_count'=>$removedCount,
                    'available_count'=>$availableCount,'updated_available_count'=>$availableCount,
                    'presentation_file_id'=>$currentPresentationId,
                    'presentation_removed'=>(bool)$removal['presentation_removed'],
                    'package'=>$packageDescriptor,'updated_package_descriptor'=>$packageDescriptor,
                ]);
            }
            if($model->findById($materialId,true)===null)$this->json(false,'El material ya no está disponible.',[],404);
            $fileService=new SupportMaterialFileService();$limits=$fileService->limits();$uploads=[];
            if(isset($_FILES['files']['name'])&&is_array($_FILES['files']['name'])){
                foreach(array_keys($_FILES['files']['name']) as $index)$uploads[]=[
                    'name'=>$_FILES['files']['name'][$index]??'','type'=>$_FILES['files']['type'][$index]??'',
                    'tmp_name'=>$_FILES['files']['tmp_name'][$index]??'','error'=>$_FILES['files']['error'][$index]??UPLOAD_ERR_NO_FILE,
                    'size'=>$_FILES['files']['size'][$index]??0,
                ];
            }elseif(isset($_FILES['file']))$uploads[]=$_FILES['file'];
            if($uploads===[])$this->json(false,'Selecciona al menos un archivo.',[],422);
            if(count($uploads)>(int)$limits['max_operation_files'])$this->json(false,'Puedes agregar hasta '.$limits['max_operation_files'].' archivos por operación.',[],422);
            if(array_sum(array_map(static fn(array $upload):int=>(int)($upload['size']??0),$uploads))>(int)$limits['max_operation_bytes']){
                $this->json(false,'La selección completa supera el límite de '.$limits['max_operation_mb'].' MB por operación.',[],422);
            }
            $added=[];$failed=[];$actor=(int)$session->userId();
            foreach($uploads as $upload){
                $displayName=mb_substr(basename(str_replace('\\','/',(string)($upload['name']??'Archivo'))),0,200);
                $stored=null;
                try{
                    $stored=$fileService->store($materialId,$upload);
                    $fileId=Database::transaction(function(PDO $database)use($model,$materialId,$stored,$actor):int{
                        if($model->findByIdForUpdate($materialId)===null)throw new InvalidArgumentException('El material ya no está disponible.');
                        if($model->hasActiveFileEquivalent($materialId,(string)$stored['original_name'],(int)$stored['size_bytes'])){
                            throw new InvalidArgumentException('Ya existe un archivo activo con el mismo nombre y tamaño. Utiliza Reemplazar archivo para actualizarlo.');
                        }
                        $id=$model->addFile($materialId,$stored,$actor);
                        (new AdminActivityService($database))->record(
                            $actor,'support_material.file_added','Agregó un archivo al material',
                            'Repositorio','support_material',$materialId,$stored['original_name'],'correct',[
                                'file_id'=>$id,'name'=>$stored['original_name'],'extension'=>$stored['extension'],
                                'mime_type'=>$stored['mime_type'],'size_bytes'=>$stored['size_bytes'],
                                'is_package'=>false,
                            ]
                        );
                        return $id;
                    });
                    $query='&material_id='.$materialId.'&file_id='.$fileId;
                    $extension=(string)$stored['extension'];
                    $added[]=[
                        'id'=>$fileId,'name'=>$stored['original_name'],'extension'=>$extension,
                        'type'=>mb_strtoupper($extension),'size_label'=>ArchiveService::formatBytes((int)$stored['size_bytes']),
                        'size_bytes'=>(int)$stored['size_bytes'],
                        'is_archive'=>$extension==='zip',
                        'preview_supported'=>in_array($extension,['pdf','docx','png','jpg','jpeg','webp','txt'],true)||$extension==='zip',
                        'preview_type'=>$extension==='zip'?'zip':(in_array($extension,['jpg','jpeg','png','webp'],true)?'image':$extension),
                        'preview_url'=>route('support-material-preview').$query,
                        'zip_url'=>$extension==='zip'?route('support-material-zip-list').$query:'',
                        'zip_entry_preview_url'=>$extension==='zip'?route('support-material-zip-entry-preview').$query:'',
                        'zip_entry_download_url'=>$extension==='zip'?route('support-material-zip-entry-download').$query:'',
                        'download_url'=>route('support-material-download').$query,
                    ];
                }catch(Throwable $error){
                    if(is_array($stored)&&!$fileService->discard($stored)){
                        error_log('Support material orphan cleanup failed for upload '.$displayName);
                    }
                    error_log(sprintf(
                        'Support material file add failed [%s] material=%d file=%s upload_error=%d: %s',
                        $error::class,$materialId,$displayName?:'Archivo',(int)($upload['error']??UPLOAD_ERR_NO_FILE),$error->getMessage()
                    ));
                    $failed[]=['name'=>$displayName?:'Archivo','message'=>$error instanceof InvalidArgumentException?$error->getMessage():'No fue posible procesar este archivo.'];
                }
            }
            $requested=count($uploads);$addedCount=count($added);$failedCount=count($failed);
            $material=$model->findById($materialId,true);$package=$material?(new SupportMaterialPackageService())->describe($material):['available'=>false,'file_count'=>0,'source'=>'generated'];
            $data=['summary'=>['requested'=>$requested,'added'=>$addedCount,'failed'=>$failedCount],'added'=>$added,'failed'=>$failed,'package'=>[
                'available'=>(bool)$package['available'],'file_count'=>(int)$package['file_count'],
                'size_bytes'=>(int)($package['size_bytes']??0),'size'=>(string)($package['size']??''),'source'=>(string)$package['source'],
                'download_url'=>!empty($package['available'])?route('support-material-package-download').'&material_id='.$materialId:'',
            ]];
            if($addedCount===0)$this->json(false,'No se pudo agregar ningún archivo.',$data,422);
            $message=$failedCount>0
                ?$addedCount.($addedCount===1?' archivo agregado y ':' archivos agregados y ').$failedCount.($failedCount===1?' no pudo procesarse.':' no pudieron procesarse.')
                :$addedCount.($addedCount===1?' archivo agregado correctamente.':' archivos agregados correctamente.');
            $this->json(true,$message,$data,$failedCount>0?207:200);
        }
        catch(SupportMaterialAccessException $error){$this->json(false,$error->getMessage(),[],$error->httpStatus);}
        catch(InvalidArgumentException $error){$this->json(false,$error->getMessage(),[],422);}
        catch(Throwable $error){
            error_log(sprintf(
                'Support material file endpoint=admin-support-material-file action=%s material_id=%d file_id=%d actor=%d: %s in %s:%d code=%s',
                $action,$materialId,(int)($_POST['file_id']??0),(int)($session->userId()??0),
                $error->getMessage(),$error->getFile(),$error->getLine(),(string)$error->getCode()
            ));
            $this->json(false,'No fue posible completar la acción sobre el archivo.',[],500);
        }
    }
    public function academic():void{$model=new AdminAcademicModel();$error=null;$s=new AuthSessionService();try{$data=$model->dashboard((int)$s->userId());}catch(Throwable $e){error_log('Admin academic: '.$e->getMessage());$error='No fue posible consultar la configuración académica.';$data=['periods'=>[],'types'=>[],'material_types'=>[],'keywords'=>[],'promotion'=>['source'=>null,'target'=>null,'projects'=>0,'suggested'=>null],'reversal'=>null];}View::render('admin/academic',['currentPage'=>'admin-academic','title'=>'Gestión académica | Administración','bodyClass'=>'admin-academic-page','pageStyles'=>[asset('css/admin-academic.css')],'pageScript'=>asset('js/admin-academic.js'),'academic'=>$data,'academicError'=>$error,'academicCsrf'=>$s->csrfToken('admin_academic'),'academicEndpoints'=>['save'=>route('admin-academic-save'),'promote'=>route('admin-academic-promote'),'revert'=>route('admin-academic-revert')]]);}
    public function saveAcademic():void
    {
        $this->requirePost();
        $session=new AuthSessionService();
        if(!$session->validateCsrf('admin_academic',(string)($_POST['_csrf']??'')))$this->json(false,'La sesión venció.',[],419);
        $entity=(string)($_POST['entity']??'');
        $id=(int)($_POST['id']??0);
        $action=(string)($_POST['action']??'save');
        $names=['type'=>'tipo de proyecto','type_description'=>'descripción para el registro','material_type'=>'tipo de material','keyword'=>'palabra clave'];
        $entityName=$names[$entity]??'información académica';
        $verb=['activate'=>'activar','deactivate'=>'desactivar','delete'=>'eliminar'][$action]??($id?'editar':'crear');
        $element=$entity==='period'?'Período académico':(string)($_POST['name']??ucfirst($entityName));
        try{
            (new AdminAcademicModel())->save($entity,$_POST,(int)$session->userId());
            $messages=[
                'material_type'=>['save'=>$id?'Tipo de material actualizado correctamente.':'Tipo de material creado correctamente.','activate'=>'Tipo de material activado correctamente.','deactivate'=>'Tipo de material desactivado correctamente.','delete'=>'Tipo de material eliminado correctamente.'],
                'keyword'=>['save'=>$id?'Palabra clave actualizada correctamente.':'Palabra clave creada correctamente.','activate'=>'Palabra clave activada correctamente.','deactivate'=>'Palabra clave desactivada correctamente.','delete'=>'Palabra clave eliminada correctamente.'],
                'type'=>['save'=>$id?'Tipo de proyecto actualizado correctamente.':'Tipo de proyecto creado correctamente.','activate'=>'Tipo de proyecto activado correctamente.','deactivate'=>'Tipo de proyecto desactivado correctamente.','delete'=>'Tipo de proyecto eliminado correctamente.'],
                'type_description'=>['save'=>'Descripción guardada correctamente.','delete'=>'Descripción eliminada correctamente.'],
            ];
            $this->json(true,$messages[$entity][$action]??'Información académica guardada correctamente.');
        }catch(InvalidArgumentException $e){
            $this->activityFailure($session,'academic_'.$entity.'_'.$action,'Intentó '.$verb.' '.$entityName,'Gestión académica',$entity,$id?:null,$element,$e);
            $this->json(false,$e->getMessage(),[],422);
        }catch(Throwable $e){
            $this->activityFailure($session,'academic_'.$entity.'_'.$action,'Intentó '.$verb.' '.$entityName,'Gestión académica',$entity,$id?:null,$element,$e);
            error_log('Save academic: '.$e->getMessage());
            $this->json(false,'No fue posible completar la acción académica.',[],500);
        }
    }
    public function promoteAcademicPeriod():void{$this->requirePost();$s=new AuthSessionService();if(!$s->validateCsrf('admin_academic',(string)($_POST['_csrf']??'')))$this->json(false,'La sesión venció.',[],419);try{$result=(new AdminAcademicModel())->promote((int)($_POST['target_period_id']??0),(int)$s->userId(),($_POST['confirm_early_close']??'')==='1');if(!empty($result['blocked']))$this->json(false,'No es posible cerrar el período académico.',['reason'=>$result['reason']??'unfinished_projects','pending_projects'=>$result['pending_projects']??[],'projects'=>$result['projects']??0],422);$this->json(true,$result['closed'].' fue cerrado y '.$result['activated'].' quedó activo. Los proyectos conservaron su período original.',$result);}catch(InvalidArgumentException $e){$this->activityFailure($s,'academic_period_closed','Intentó cerrar el período académico','Gestión académica','period',null,'Período académico actual',$e);$this->json(false,$e->getMessage(),[],422);}catch(Throwable $e){$this->activityFailure($s,'academic_period_closed','Intentó cerrar el período académico','Gestión académica','period',null,'Período académico actual',$e);error_log('Promote academic: '.$e->getMessage());$this->json(false,'No fue posible cerrar el período.',[],500);}}
    public function revertAcademicPeriod():void{$this->requirePost();$s=new AuthSessionService();if(!$s->validateCsrf('admin_academic',(string)($_POST['_csrf']??'')))$this->json(false,'La sesión venció.',[],419);$transitionId=(int)($_POST['transition_id']??0);try{$result=(new AdminAcademicModel())->reverseTransition($transitionId,(int)$s->userId());$this->json(true,'El cierre del período se revirtió correctamente.',$result);}catch(InvalidArgumentException $e){$this->activityFailure($s,'academic_period_closure_revert_failed','Intentó revertir el cierre de un período','Gestión académica','academic_period_transition',$transitionId?:null,'Transición académica #'.$transitionId,$e);$this->json(false,$e->getMessage(),[],422);}catch(Throwable $e){$this->activityFailure($s,'academic_period_closure_revert_failed','Intentó revertir el cierre de un período','Gestión académica','academic_period_transition',$transitionId?:null,'Transición académica #'.$transitionId,$e);error_log('Revert academic period: '.$e->getMessage());$this->json(false,'No fue posible revertir el cierre del período.',[],500);}}
    public function projects(): void
    {
        $session=new AuthSessionService();
        $access=new ProjectAccessService();
        $isAdministrator=$session->isAdminModeActive();
        $isTeacher=in_array('teacher',$access->currentRoles(),true);

        if (!$isAdministrator && !$isTeacher) {
            header('Location: ' . route('forbidden'));
            exit;
        }

        $requestedStatus=(string)($_GET['status']??'');
        $filters=['search'=>mb_substr(trim((string)($_GET['search']??'')),0,100),'status'=>$requestedStatus==='changes_required'?'':$requestedStatus,'type_id'=>(int)($_GET['type_id']??0),'period_id'=>(int)($_GET['period_id']??0),'group'=>(string)($_GET['group']??''),'review_situation'=>(string)($_GET['review_situation']??'')];
        $model=new AdminProjectModel();$error=null;
        try{$catalogs=$model->catalogs();if(count($catalogs['periods'])===1)$filters['period_id']=(int)$catalogs['periods'][0]['id'];$result=$model->listing($filters,PaginationService::request());$projects=$result['items'];$pagination=$result['pagination'];$summary=$model->summary($filters);}catch(Throwable $exception){error_log('Admin projects: '.$exception->getMessage());$error='No fue posible consultar los proyectos.';$projects=[];$pagination=['total'=>0];$summary=['total'=>0,'development'=>0,'review'=>0,'approved'=>0,'defense'=>0];$catalogs=['types'=>[],'careers'=>[],'periods'=>[],'teachers'=>[]];}
        $transitionService=new ProjectStatusTransitionService();
        $publicationReversionService=new ProjectPublicationReversionService();
        $capabilityService=new ProjectCapabilityService();
        $context=$isAdministrator?'academic_management':'academic';
        foreach($projects as &$project){
            $capabilities=$capabilityService->forCurrentUser($project,$context);
            $labels=project_academic_labels((string)($project['status']??''));
            $project['status_label']=$labels['status'];
            $project['stage_label']=$labels['stage'];
            $project['capabilities']=['change_status'=>!empty($capabilities['change_status']),'request_corrections'=>!empty($capabilities['request_corrections']),'manage_publication'=>!empty($capabilities['manage_publication']),'review_documents'=>!empty($capabilities['review_documents'])];
            $project['status_transitions']=!empty($capabilities['change_status'])?$transitionService->availableTransitions($project):[];
            $correctionAction=!empty($capabilities['request_corrections'])?(new ProjectReviewService())->availableCorrectionAction($project):null;
            $project['publication_reversion']=!empty($capabilities['manage_publication'])?$publicationReversionService->availability($project):['available'=>false,'message'=>'','action'=>null];
            $project['status_actions']=array_values(array_filter([...$project['status_transitions'],$correctionAction,$project['publication_reversion']['action']??null]));
        }
        unset($project);
        View::render('admin/projects',[
            'currentPage'=>'projects',
            'title'=>$isAdministrator ? 'Proyectos activos | Administración' : 'Proyectos activos | Gestión Académica',
            'bodyClass'=>'admin-projects-page',
            'isAdministrator'=>$isAdministrator,
            'pageStyles'=>[asset('css/admin-projects.css')],
            'pageScript'=>asset('js/admin-projects.js'),
            'pageScripts'=>[asset('js/project-status-transition.js')],
            'projects'=>$projects,
            'pagePagination'=>$pagination,
            'projectSummary'=>$summary,
            'catalogs'=>$catalogs,
            'filters'=>$filters,
            'projectError'=>$error,
            'projectCsrf'=>$isAdministrator ? $session->csrfToken('admin_projects') : '',
            'projectEndpoints'=>$isAdministrator ? ['save'=>route('admin-project-save'),'trash'=>route('admin-project-trash')] : ['save'=>'','trash'=>''],
            'projectStatusDialog'=>['enabled'=>$isAdministrator,'endpoint'=>$isAdministrator ? route('admin-project-save') : '','csrf_token'=>$isAdministrator ? $session->csrfToken('admin_projects') : '','close_editor_on_success'=>true]
        ]);
    }
    public function saveProject():void
    {
        $this->requirePost();$session=new AuthSessionService();
        if(!$session->hasAdminAccess())$this->json(false,'No tienes autorización para administrar proyectos.',[],403);
        if(!$session->validateCsrf('admin_projects',(string)($_POST['_csrf']??'')))$this->json(false,'La sesión del formulario venció.',[],419);
        $id=(int)($_POST['id']??0);$title=trim((string)($_POST['title']??''));
        $publicationIntent=(string)($_POST['publication_intent']??'')==='1';$requestedAction=(string)($_POST['action']??'');
        if($id>0){
            $capabilities=(new ProjectCapabilityService())->forProjectId($id,'academic_management');
            $required=$requestedAction==='request_corrections'?'request_corrections':($requestedAction==='change_status'?'change_status':($requestedAction==='revert_publication'||$publicationIntent||$requestedAction==='prepare_public_description'?'manage_publication':((string)($_POST['authors_managed']??'')==='1'?'manage_participants':'edit_information')));
            if(empty($capabilities[$required]))$this->json(false,'No tienes autorización para completar esta acción sobre el proyecto.',[],403);
        }
        try{
            if($requestedAction==='prepare_public_description')$this->json(true,'Descripción pública consultada.',(new ProjectDescriptionService())->prepareForPublication($id));
            if($requestedAction==='change_status'){
                $result=(new ProjectStatusTransitionService())->transition($id,(string)($_POST['expected_status']??''),(string)($_POST['target_status']??''),(string)($_POST['reason']??''),(int)$session->userId());
                $this->json(true,$result['published']?'El proyecto fue publicado correctamente y ahora puede consultarse desde el Repositorio.':'Estado académico actualizado correctamente.',$result);
            }
            if($requestedAction==='request_corrections'){
                $observations=$_POST['observations']??[];
                if(is_string($observations)){
                    $decoded=json_decode($observations,true);
                    $observations=is_array($decoded)?$decoded:[];
                }
                $deliveryId=(int)($_POST['delivery_id']??0);
                $result=(new ProjectReviewService())->requestCorrections($id,(string)($_POST['expected_status']??''),$deliveryId>0?$deliveryId:null,(array)$observations,(int)$session->userId());
                $this->json(true,'Las correcciones fueron solicitadas y el proyecto volvió a En desarrollo.',$result);
            }
            if($requestedAction==='revert_publication'){
                $result=(new ProjectPublicationReversionService())->revert($id,(string)($_POST['expected_status']??''),(string)($_POST['expected_published_at']??''),(string)($_POST['reason']??''),(int)$session->userId());
                $this->json(true,'La publicación se revirtió correctamente.',$result);
            }
            $payload=['title'=>$title,'subtitle'=>trim((string)($_POST['subtitle']??'')),'summary'=>trim((string)($_POST['summary']??'')),'project_type_id'=>(int)($_POST['project_type_id']??0),'career_id'=>(int)($_POST['career_id']??0),'academic_period_id'=>(int)($_POST['academic_period_id']??0),'tutor_id'=>(int)($_POST['tutor_id']??0),'tutoring_managed'=>(string)($_POST['tutoring_managed']??'')==='1','tutoring_user_ids'=>(array)($_POST['tutoring_user_ids']??[]),'tutoring_primary_id'=>(int)($_POST['tutoring_primary_id']??0),'authors_managed'=>(string)($_POST['authors_managed']??'')==='1','author_user_ids'=>(array)($_POST['author_user_ids']??[]),'author_leader_id'=>(int)($_POST['author_leader_id']??0),'status'=>(string)($_POST['status']??'development'),'presentation_file_id'=>(int)($_POST['presentation_file_id']??0),'public_description'=>(string)($_POST['public_description']??''),'description_origin'=>(string)($_POST['description_origin']??''),'keywords'=>(array)($_POST['project_keywords']??[])];
            $saved=(new AdminProjectModel())->save($payload,$id,(int)$session->userId(),$publicationIntent);
            $this->json(true,$publicationIntent?'El proyecto fue publicado correctamente y ahora puede consultarse desde el Repositorio.':($id?'Proyecto actualizado correctamente.':'Proyecto creado correctamente.'),['id'=>$saved]);
        }catch(ProjectStatusTransitionException $exception){$this->json(false,$exception->getMessage(),$exception->details(),$exception->httpStatus());}
        catch(ProjectTutoringException $exception){$this->json(false,$exception->getMessage(),[],422);}
        catch(ProjectAuthorException $exception){$this->json(false,$exception->getMessage(),[],422);}
        catch(InvalidArgumentException $exception){if($id)$this->activityFailure($session,'project_status_changed','Intentó modificar el estado de un proyecto','Proyectos','project',$id,$title?:'Proyecto #'.$id,$exception);$this->json(false,$exception->getMessage(),[],422);}
        catch(Throwable $exception){if($id)$this->activityFailure($session,'project_status_changed','Intentó modificar el estado de un proyecto','Proyectos','project',$id,$title?:'Proyecto #'.$id,$exception);error_log('Admin save project: '.$exception->getMessage());$this->json(false,$publicationIntent?'No fue posible completar la publicación. No se realizaron cambios.':'No fue posible guardar el proyecto.',[],500);}
    }
    public function trashProject():void{$this->requirePost();$session=new AuthSessionService();if(!$session->validateCsrf('admin_projects',(string)($_POST['_csrf']??'')))$this->json(false,'La sesión del formulario venció.',[],419);$id=(int)($_POST['id']??0);$capabilities=(new ProjectCapabilityService())->forProjectId($id,'academic_management');if(empty($capabilities['edit_information']))$this->json(false,'No tienes autorización para modificar este proyecto.',[],403);try{(new AdminProjectModel())->trash($id,(string)($_POST['reason']??''),(int)$session->userId());$this->json(true,'Proyecto enviado a la Papelera. Se conservará para restauración.');}catch(InvalidArgumentException $exception){$this->json(false,$exception->getMessage(),[],422);}catch(Throwable $exception){error_log('Admin trash project: '.$exception->getMessage());$this->json(false,'No fue posible enviar el proyecto a la Papelera.',[],500);}}
    public function users(): void
    {
        $model = new AdminUserModel();
        $filters = ['search'=>mb_substr(trim((string)($_GET['search']??'')),0,100),'role'=>(string)($_GET['role']??''),'status'=>(string)($_GET['status']??'')];
        $error = null;
        try { $result=$model->listing($filters,PaginationService::request());$users=$result['items'];$pagination=$result['pagination']; $summary=$model->summary(); $catalogs=$model->catalogs(); }
        catch(Throwable $exception){ error_log('Admin users error: '.$exception->getMessage());$error='No fue posible consultar los usuarios.';$users=[];$pagination=['total'=>0];$summary=['total'=>0,'active'=>0,'blocked'=>0,'students'=>0,'teachers'=>0,'administrators'=>0];$catalogs=['career'=>null,'period'=>null]; }
        $session=new AuthSessionService();
        View::render('admin/users',['currentPage'=>'admin-users','title'=>'Usuarios | Administración','bodyClass'=>'admin-users-page','pageStyles'=>[asset('css/admin-users.css'),asset('css/admin-user-import.css')],'pageScript'=>asset('js/admin-users.js'),'pageScripts'=>[asset('js/admin-user-import.js')],'users'=>$users,'pagePagination'=>$pagination,'userSummary'=>$summary,'catalogs'=>$catalogs,'filters'=>$filters,'adminUserCsrf'=>$session->csrfToken('admin_users'),'adminUserEndpoints'=>['save'=>route('admin-user-save'),'status'=>route('admin-user-status'),'password'=>route('admin-user-password'),'import'=>route('admin-users-import')],'adminUsersError'=>$error]);
    }

    public function saveUser(): void
    {
        $this->requirePost(); $session=$this->sessionAndCsrf();$id=(int)($_POST['id']??0);$name=trim((string)($_POST['full_name']??''));
        try {
            $payload=$this->userPayload();
            $saved=(new AdminUserModel())->save($payload,$id,(int)$session->userId());
            $this->json(true,$id>0?'Usuario actualizado correctamente.':'Usuario creado correctamente.',['user'=>$saved]);
        } catch(TemporaryPasswordPolicyException $exception){$this->activityFailure($session,$id?'user_updated':'user_created',$id?'Intentó editar un usuario':'Intentó crear un usuario','Usuarios','user',$id?:null,$name?:'Usuario',$exception);$this->json(false,$exception->getMessage(),[],503);}
        catch(InvalidArgumentException $exception){$this->activityFailure($session,$id?'user_updated':'user_created',$id?'Intentó editar un usuario':'Intentó crear un usuario','Usuarios','user',$id?:null,$name?:'Usuario',$exception);$this->json(false,$exception->getMessage(),[],422);}
        catch(Throwable $exception){$this->activityFailure($session,$id?'user_updated':'user_created',$id?'Intentó editar un usuario':'Intentó crear un usuario','Usuarios','user',$id?:null,$name?:'Usuario',$exception);error_log('Save admin user: '.$exception->getMessage());$this->json(false,'No fue posible guardar el usuario.',[],500);}
    }

    public function changeUserStatus(): void
    {
        $this->requirePost();$session=$this->sessionAndCsrf();$id=(int)($_POST['id']??0);$status=(string)($_POST['status']??'');
        try{(new AdminUserModel())->changeStatus($id,$status,(int)$session->userId());$this->json(true,'Estado de acceso actualizado.');}
        catch(InvalidArgumentException $exception){$this->activityFailure($session,'user_status_changed','Intentó cambiar el acceso de un usuario','Usuarios','user',$id,'Usuario #'.$id,$exception);$this->json(false,$exception->getMessage(),[],422);}
        catch(Throwable $exception){$this->activityFailure($session,'user_status_changed','Intentó cambiar el acceso de un usuario','Usuarios','user',$id,'Usuario #'.$id,$exception);error_log('Admin user status: '.$exception->getMessage());$this->json(false,'No fue posible actualizar el acceso.',[],500);}
    }

    public function resetUserPassword(): void
    {
        $this->requirePost();$session=$this->sessionAndCsrf();$id=(int)($_POST['id']??0);
        try{(new AdminUserModel())->resetPassword($id,(int)$session->userId());$this->json(true,'Contraseña temporal restablecida según la política vigente.');}
        catch(TemporaryPasswordPolicyException $exception){$this->json(false,$exception->getMessage(),[],503);}
        catch(InvalidArgumentException $exception){$this->json(false,$exception->getMessage(),[],422);}
        catch(Throwable $exception){error_log('Admin password reset: '.$exception->getMessage());$this->json(false,'No fue posible restablecer la contraseña.',[],500);}
    }

    public function importUsers(): void
    {
        $this->requirePost();$session=$this->sessionAndCsrf();$content=trim((string)($_POST['content']??''));
        if(isset($_FILES['file'])&&($_FILES['file']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){$file=$_FILES['file'];if(($file['error']??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK)$this->json(false,'No fue posible leer el archivo.',[],422);$extension=mb_strtolower(pathinfo((string)$file['name'],PATHINFO_EXTENSION));if(!in_array($extension,['csv','txt'],true))$this->json(false,'Utiliza un archivo CSV o TXT.',[],422);if((int)$file['size']>1048576)$this->json(false,'El archivo no puede superar 1 MB.',[],422);$content=(string)file_get_contents((string)$file['tmp_name']);}
        try{$model=new AdminUserModel();$config=['role'=>(string)($_POST['role']??''),'career_id'=>(int)($_POST['career_id']??0),'academic_period_id'=>(int)($_POST['academic_period_id']??0),'semester'=>(int)($_POST['semester']??0)];$preview=$model->previewImport($content,$config);if(($_POST['mode']??'preview')==='import'){$result=$model->bulkImport($content,$config,(int)$session->userId());$this->json(true,$result['created'].' usuarios fueron creados correctamente.',$result);} $this->json(true,'Vista previa generada.',$preview);}
        catch(TemporaryPasswordPolicyException $exception){$this->json(false,$exception->getMessage(),[],503);}
        catch(InvalidArgumentException $exception){$this->json(false,$exception->getMessage(),[],422);}
        catch(Throwable $exception){error_log('Admin bulk import: '.$exception->getMessage());$this->json(false,'No fue posible procesar la lista.',[],500);}
    }

    public function module(string $section): void
    {
        $modules=['academic'=>['Gestión académica','fa-graduation-cap','Periodos, matrículas y catálogos se habilitarán en la Fase 4.'],'reports'=>['Reportes','fa-chart-column','Los reportes administrativos se habilitarán al completar los módulos de datos.'],'settings'=>['Configuración','fa-gear','Los parámetros institucionales se incorporarán en la Fase 6.'],'trash'=>['Papelera','fa-trash-can','La restauración y purga se rigen por la política de retención configurada.']];
        if(!isset($modules[$section])){$this->users();return;}$item=$modules[$section];
        View::render('admin/module-pending',['currentPage'=>'admin-'.$section,'title'=>$item[0].' | Administración','bodyClass'=>'admin-page','pageStyles'=>[asset('css/admin-access.css')],'moduleTitle'=>$item[0],'moduleIcon'=>$item[1],'moduleMessage'=>$item[2]]);
    }

    private function userPayload(): array
    {
        return ['full_name'=>trim((string)($_POST['full_name']??'')),'email'=>mb_strtolower(trim((string)($_POST['email']??''))),'username'=>trim((string)($_POST['username']??'')),'role'=>(string)($_POST['role']??''),'status'=>array_key_exists('status',$_POST)?(string)$_POST['status']:'','institutional_code'=>trim((string)($_POST['institutional_code']??'')),'career_id'=>(int)($_POST['career_id']??0),'academic_period_id'=>(int)($_POST['academic_period_id']??0),'semester'=>(int)($_POST['semester']??0),'academic_title'=>trim((string)($_POST['academic_title']??'')),'can_tutor'=>array_key_exists('can_tutor',$_POST)?1:null,'can_manage_thesis'=>isset($_POST['can_manage_thesis'])?1:0,'is_admin'=>isset($_POST['is_admin'])?1:0];
    }
    private function normalizeAuditText(mixed $value,bool $multiline=false):string
    {
        $normalized=str_replace(["\r\n","\r"],"\n",(string)$value);
        if(class_exists('Normalizer')){
            $unicode=Normalizer::normalize($normalized,Normalizer::FORM_C);
            if(is_string($unicode))$normalized=$unicode;
        }
        $normalized=(string)preg_replace('/[\p{Z}\t\f\v]+/u',' ',$normalized);
        if(!$multiline)return trim((string)preg_replace('/\s+/u',' ',$normalized));
        $lines=array_map(static fn(string $line):string=>trim($line),explode("\n",$normalized));
        $normalized=(string)preg_replace('/\n{3,}/',"\n\n",implode("\n",$lines));
        return trim($normalized);
    }
    private function normalizeAuditDate(mixed $value):string
    {
        $normalized=trim((string)$value);
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$normalized);
        return $date&&$date->format('Y-m-d')===$normalized?$date->format('Y-m-d'):$normalized;
    }
    private function normalizeAuditKeywords(mixed $value):array
    {
        $source=is_array($value)?$value:(preg_split('/[,;\n]+/u',(string)$value)?:[]);
        $display=[];$seen=[];
        foreach($source as $keyword){
            $normalized=$this->normalizeAuditText($keyword);
            if($normalized==='')continue;
            $key=mb_strtolower($normalized,'UTF-8');
            if(isset($seen[$key]))continue;
            $seen[$key]=true;$display[]=$normalized;
        }
        $comparison=array_keys($seen);
        sort($comparison,SORT_STRING);
        return ['display'=>$display,'comparison'=>$comparison];
    }
    private function sessionAndCsrf(): AuthSessionService{$session=new AuthSessionService();if(!$session->validateCsrf('admin_users',(string)($_POST['_csrf']??'')))$this->json(false,'La solicitud expiró o ya no es válida. Recarga la página e inténtalo nuevamente.',[],419);return $session;}
    private function activityFailure(AuthSessionService $session,string $action,string $label,string $module,string $entityType,?int $entityId,string $element,Throwable $error):void
    {
        (new AdminActivityService())->recordFailure((int)$session->userId(),$action,$label,$module,$entityType,$entityId,$element,$error);
    }
    private function requirePost(): void{if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){$this->json(false,'Método no permitido.',[],405);}}
    public function repositoryDashboard(): void
    {
        $model = new AdminRepositoryModel();
        $projects = [];
        $pagination = ['total' => null];
        $summary = ['published' => null, 'pending' => null, 'pending_by_period' => []];
        $catalogs = ['types' => [], 'periods' => []];
        $withdrawnPublications = [];
        $supportMaterials = [];
        $withdrawnMaterials = [];
        $materialCategories = [];
        $sectionErrors = [];

        try {
            $summary = $model->summary();
        } catch (Throwable $exception) {
            error_log('Admin repository summary: ' . $exception->getMessage());
            $sectionErrors['summary'] = 'No fue posible consultar el resumen.';
        }
        try {
            $catalogRequest = PaginationService::request();
            $catalogRequest['size'] = 100;
            $published = $model->listing('published', $catalogRequest);
            $projects = $published['items'];
            $pagination = $published['pagination'];
        } catch (Throwable $exception) {
            error_log('Admin repository projects: ' . $exception->getMessage());
            $sectionErrors['projects'] = 'No fue posible consultar los proyectos publicados.';
        }
        try {
            $catalogs = $model->filterCatalogs();
        } catch (Throwable $exception) {
            error_log('Admin repository catalogs: ' . $exception->getMessage());
            $sectionErrors['catalogs'] = 'No fue posible cargar los filtros del repositorio.';
        }
        try {
            $withdrawnPublications = $model->withdrawnPublications();
        } catch (Throwable $exception) {
            error_log('Admin repository withdrawn projects: ' . $exception->getMessage());
            $sectionErrors['withdrawn_projects'] = 'No fue posible consultar los proyectos retirados.';
        }
        $materialModel = new SupportMaterialModel();
        try {
            $supportMaterials = $materialModel->getAdminMaterials();
        } catch (Throwable $exception) {
            error_log('Admin repository materials: ' . $exception->getMessage());
            $sectionErrors['materials'] = 'No fue posible consultar los materiales de apoyo.';
        }
        try {
            $withdrawnMaterials = $materialModel->getWithdrawn();
        } catch (Throwable $exception) {
            error_log('Admin repository withdrawn materials: ' . $exception->getMessage());
            $sectionErrors['withdrawn_materials'] = 'No fue posible consultar los materiales retirados.';
        }
        try {
            $materialCategories = $materialModel->categories();
        } catch (Throwable $exception) {
            error_log('Admin repository material categories: ' . $exception->getMessage());
            $sectionErrors['material_categories'] = 'No fue posible cargar las categorías de materiales.';
        }

        $s = new AuthSessionService();
        View::render('admin/repository', [
            'currentPage'=>'repository','title'=>'Repositorio | AdministraciÃ³n','bodyClass'=>'admin-repository-page',
            'pageStyles'=>[asset('css/admin-repository.css')],'pageScript'=>asset('js/admin-repository.js'),
            'repositoryProjects'=>$projects,'pagePagination'=>$pagination,'repositorySummary'=>$summary,
            'repositoryError'=>null,'repositorySectionErrors'=>$sectionErrors,'repositoryCatalogs'=>$catalogs,
            'withdrawnPublications'=>$withdrawnPublications,'supportMaterials'=>$supportMaterials,
            'withdrawnMaterials'=>$withdrawnMaterials,'materialCategories'=>$materialCategories,
            'repositoryCsrf'=>$s->csrfToken('admin_repository'),'repositoryPublishEndpoint'=>route('admin-repository-publish'),
            'materialSaveEndpoint'=>route('admin-support-material-save'),'materialStatusEndpoint'=>route('admin-support-material-status'),
            'materialFileEndpoint'=>route('admin-support-material-file')
        ]);
    }

    private function json(bool $success,string $message,array $data=[],int $status=200): never{http_response_code($status);header('Content-Type: application/json; charset=utf-8');echo json_encode(['success'=>$success,'message'=>$message,'data'=>$data],JSON_UNESCAPED_UNICODE);exit;}
}
