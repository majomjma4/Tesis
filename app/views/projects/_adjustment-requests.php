<?php
/** @var array<string, mixed> $project */

$adjustmentItems = (array)($adjustmentData['items'] ?? []);
$adjustmentSummary = (array)($adjustmentData['summary'] ?? []);
$adjustmentContext = (string)($adjustmentContext ?? $projectContext);
$pendingAdjustments = array_values(array_filter($adjustmentItems, static fn(array $item): bool => ($item['status'] ?? '') === 'pending'));
$addressedAdjustments = array_values(array_filter($adjustmentItems, static fn(array $item): bool => ($item['status'] ?? '') === 'addressed'));
$latestAdjustment = (array)($adjustmentSummary['latest'] ?? $adjustmentSummary['latest_request'] ?? []);
$typeLabels = ['incomplete_information'=>'Información incompleta','incorrect_data'=>'Datos incorrectos','inconsistency'=>'Inconsistencia','other'=>'Otro ajuste'];
$studentNames = array_values(array_filter(array_map(static fn(array $student): string => trim((string)($student['full_name'] ?? $student['name'] ?? '')), (array)($project['student_authors'] ?? []))));
$adjustmentDate = static function (?string $value): string {
    if (!$value) return '';
    return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('America/Guayaquil'))->format('d/m/Y H:i');
};
?>
<template data-adjustment-banner-template>
<?php if(!empty($adjustmentSummary['has_pending_adjustments'])): ?>
<aside class="project-adjustment-banner" data-adjustment-banner role="status" aria-labelledby="projectAdjustmentBannerTitle">
    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
    <div><strong id="projectAdjustmentBannerTitle">Solicitud de ajuste pendiente</strong><p><?=(int)$adjustmentSummary['pending_count']?> solicitud<?=((int)$adjustmentSummary['pending_count']===1?'':'es')?> pendiente<?=((int)$adjustmentSummary['pending_count']===1?'':'s')?>.</p>
    <?php if($latestAdjustment):?><p><?=e((string)($latestAdjustment['message']??''))?></p><small><?=e((string)($latestAdjustment['requested_by']??''))?> · <?=e($adjustmentDate($latestAdjustment['created_at']??null))?></small><?php endif;?></div>
    <a href="<?=e($detailUrl.'&tab=evolution')?>">Ver en Historial</a>
</aside>
<?php endif; ?>
<?php if($pendingAdjustments || $addressedAdjustments): ?>
<section class="project-adjustment-list" aria-labelledby="projectAdjustmentListTitle"><header><h2 id="projectAdjustmentListTitle">Solicitudes de ajuste</h2><p>Seguimiento de cambios o correcciones solicitadas para el proyecto.</p></header>
<?php foreach(array_merge($pendingAdjustments, $addressedAdjustments) as $item): ?>
<article data-adjustment-request="<?=(int)$item['id']?>"><div><span class="project-adjustment-status is-<?=e((string)$item['status'])?>"><?=($item['status']==='addressed'?'Atendida':'Pendiente')?></span><h3><?=e($typeLabels[$item['request_type']]??'Solicitud de ajuste')?></h3><p><?=e((string)$item['message'])?></p><small><?=e((string)$item['requested_by_name'])?> · <?=e($adjustmentDate($item['created_at']??null))?></small><?php if(!empty($item['related_section'])):?><small>Sección: <?=e((string)$item['related_section'])?></small><?php endif;?><?php foreach((array)($item['responses']??[]) as $response):?><blockquote><strong><?=e((string)$response['author_name'])?> respondió:</strong> <?=e((string)$response['message'])?></blockquote><?php endforeach;?></div>
<div class="project-adjustment-actions">
<?php if($item['status']==='pending'&&!empty($projectCapabilities['respond_adjustment_request'])):?><button type="button" data-adjustment-respond data-request-id="<?=(int)$item['id']?>" data-lock-version="<?=(int)$item['lock_version']?>">Responder</button><?php endif;?>
<?php if($item['status']==='pending'&&!empty($projectCapabilities['address_adjustment_request'])):?><button type="button" data-adjustment-address data-request-id="<?=(int)$item['id']?>" data-lock-version="<?=(int)$item['lock_version']?>">Marcar atendida</button><?php endif;?>
<?php if($item['status']==='addressed'&&!empty($projectCapabilities['close_adjustment_request'])):?><button type="button" data-adjustment-close data-request-id="<?=(int)$item['id']?>" data-lock-version="<?=(int)$item['lock_version']?>">Cerrar solicitud</button><?php endif;?>
</div></article><?php endforeach;?></section>
<?php endif; ?>
</template>

<?php if(!empty($projectCapabilities['create_adjustment_request'])): ?>
<div class="project-adjustment-dialog" id="projectAdjustmentDialog" data-adjustment-dialog hidden>
 <section role="dialog" aria-modal="true" aria-labelledby="projectAdjustmentTitle" aria-describedby="projectAdjustmentHelp">
  <header><div><span>Seguimiento del proyecto</span><h2 id="projectAdjustmentTitle">Solicitar ajuste</h2></div><button type="button" data-adjustment-cancel aria-label="Cerrar diálogo"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></header>
  <form data-adjustment-create-form><div class="modal-body"><input type="hidden" name="_csrf" value="<?=e((string)$adjustmentCsrf)?>"><input type="hidden" name="project_id" value="<?=(int)$projectId?>"><input type="hidden" name="expected_project_status" value="<?=e((string)$project['status'])?>"><input type="hidden" name="context" value="<?=e($adjustmentContext)?>">
   <p id="projectAdjustmentHelp">Indica qué cambio necesitas solicitar.</p>
   <div class="project-adjustment-fields"><label>Tipo de ajuste<select name="request_type" required><option value="">Selecciona</option><?php foreach($typeLabels as $value=>$label):?><option value="<?=e($value)?>"><?=e($label)?></option><?php endforeach;?></select></label>
   <label>Sección<select name="related_section"><option value="">Sin sección específica</option><option>Descripción del proyecto</option><option>Información académica</option><option>Participantes</option><option>Clasificación</option><option>Documentación</option></select></label>
   <label class="project-adjustment-document-field">Documento relacionado (opcional)<select name="file_id"><option value="">Ninguno</option><?php foreach((array)$project['files'] as $file):?><option value="<?=(int)$file['id']?>"><?=e((string)$file['original_name'])?></option><?php endforeach;?></select></label></div>
   <label>Mensaje<textarea name="message" maxlength="2000" rows="5" required></textarea></label>
   <p class="project-adjustment-recipients"><strong>Destinatarios:</strong> <?=e($studentNames ? implode(', ',$studentNames) : 'estudiantes relacionados con el proyecto')?>. Se determinan automáticamente.</p>
   <p data-adjustment-message role="status" aria-live="polite" hidden></p></div>
   <footer><button type="button" data-adjustment-cancel>Cancelar</button><button type="submit" class="is-primary">Enviar solicitud</button></footer>
  </form>
 </section>
</div>
<?php endif; ?>

<?php if(!empty($projectCapabilities['respond_adjustment_request'])): ?>
<div class="project-adjustment-dialog" data-adjustment-response-dialog hidden><section role="dialog" aria-modal="true" aria-labelledby="projectAdjustmentResponseTitle"><header><h2 id="projectAdjustmentResponseTitle">Responder solicitud</h2><button type="button" data-adjustment-response-cancel aria-label="Cerrar diálogo"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></header><form data-adjustment-response-form><label>Respuesta<textarea name="message" maxlength="2000" rows="5" required></textarea></label><p data-adjustment-message role="status" aria-live="polite" hidden></p><footer><button type="button" data-adjustment-response-cancel>Cancelar</button><button class="is-primary" type="submit">Enviar respuesta</button></footer></form></section></div>
<?php endif; ?>
<div data-adjustment-config data-project-id="<?=(int)$projectId?>" data-status="<?=e((string)$project['status'])?>" data-context="<?=e($adjustmentContext)?>" data-csrf="<?=e((string)$adjustmentCsrf)?>" data-create="<?=e((string)($adjustmentEndpoints['create']??''))?>" data-respond="<?=e((string)($adjustmentEndpoints['respond']??''))?>" data-address="<?=e((string)($adjustmentEndpoints['address']??''))?>" data-close="<?=e((string)($adjustmentEndpoints['close']??''))?>"></div>
