<?php

declare(strict_types=1);

final class AdminActivityModel
{
    public function forEntity(string $entityType, int $entityId, int $limit = 20, int $offset = 0): array
    {
        $limit = max(1, min(20, $limit));
        $offset = max(0, $offset);
        $statement = Database::connection()->prepare(
            'SELECT audit.id,audit.actor_user_id,audit.action,audit.action_label,
                    audit.element_label,audit.result,audit.details,audit.created_at,
                    actor.full_name actor_name,actor.email actor_email,
                    GROUP_CONCAT(DISTINCT roles.name ORDER BY roles.name SEPARATOR ", ") actor_roles
             FROM admin_audit_log audit
             LEFT JOIN users actor ON actor.id=audit.actor_user_id
             LEFT JOIN user_roles actor_roles ON actor_roles.user_id=actor.id
             LEFT JOIN roles ON roles.id=actor_roles.role_id
             WHERE audit.entity_type=:entity_type AND audit.entity_id=:entity_id
               AND audit.result="correct"
             GROUP BY audit.id,audit.actor_user_id,audit.action,audit.action_label,
                      audit.element_label,audit.result,audit.details,audit.created_at,
                      actor.full_name,actor.email
             ORDER BY audit.created_at DESC,audit.id DESC
             LIMIT :limit OFFSET :offset'
        );
        $statement->bindValue('entity_type', $entityType);
        $statement->bindValue('entity_id', $entityId, PDO::PARAM_INT);
        $statement->bindValue('limit', $limit + 1, PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll();
        $hasMore = count($rows) > $limit;
        if ($hasMore) array_pop($rows);
        $snapshotMaxAuditId = $offset === 0 && $rows !== [] ? (int) $rows[0]['id'] : 0;

        return [
            'items' => array_map([$this, 'normalize'], $rows),
            'has_more' => $hasMore,
            'next_offset' => $offset + count($rows),
            'incomplete_count' => $this->countIncompleteSupportMaterialEvents($entityId),
            'max_audit_id' => $snapshotMaxAuditId,
        ];
    }

    public function hasUnreadSupportMaterialEvents(int $userId, int $materialId): bool
    {
        $statement = Database::connection()->prepare(
            "SELECT EXISTS(
                SELECT 1 FROM admin_audit_log audit
                LEFT JOIN support_material_audit_reads seen
                  ON seen.user_id=:user_id AND seen.material_id=:material_id
                WHERE audit.entity_type='support_material' AND audit.entity_id=:material_id_again
                  AND audit.result='correct' AND audit.id>COALESCE(seen.last_seen_audit_id,0)
            )"
        );
        $statement->execute(['user_id'=>$userId,'material_id'=>$materialId,'material_id_again'=>$materialId]);
        return (bool) $statement->fetchColumn();
    }

    public function markSupportMaterialSeen(int $userId, int $materialId, int $auditId): void
    {
        if ($userId < 1 || $materialId < 1 || $auditId < 1) return;
        Database::connection()->prepare(
            'INSERT INTO support_material_audit_reads(user_id,material_id,last_seen_audit_id)
             VALUES(:user_id,:material_id,:audit_id)
             ON DUPLICATE KEY UPDATE
               last_seen_audit_id=GREATEST(last_seen_audit_id,VALUES(last_seen_audit_id)),
               updated_at=UTC_TIMESTAMP()'
        )->execute(['user_id'=>$userId,'material_id'=>$materialId,'audit_id'=>$auditId]);
    }

    public function countIncompleteSupportMaterialEvents(int $materialId): int
    {
        return count($this->incompleteSupportMaterialEvents($materialId));
    }

    public function deleteIncompleteSupportMaterialEvents(int $materialId): int
    {
        $rows = $this->incompleteSupportMaterialEvents($materialId, true);
        $ids = array_values(array_map(static fn(array $row): int => (int) $row['id'], $rows));
        if ($ids === []) return 0;
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = Database::connection()->prepare(
            "DELETE FROM admin_audit_log
             WHERE id IN ({$placeholders})
               AND entity_type='support_material' AND entity_id=?
               AND module='Repositorio'
               AND action IN ('support_material.updated','support_material_updated')"
        );
        $statement->execute([...$ids, $materialId]);
        return $statement->rowCount();
    }

    private function incompleteSupportMaterialEvents(int $materialId, bool $forUpdate = false): array
    {
        if ($materialId < 1) return [];
        $statement = Database::connection()->prepare(
            "SELECT id,action,module,entity_type,entity_id,details
             FROM admin_audit_log
             WHERE entity_type='support_material' AND entity_id=:entity_id
               AND module='Repositorio' AND result='correct'
               AND action IN ('support_material.updated','support_material_updated')
             ORDER BY id" . ($forUpdate ? ' FOR UPDATE' : '')
        );
        $statement->execute(['entity_id' => $materialId]);
        return array_values(array_filter(
            $statement->fetchAll(),
            fn(array $row): bool => $this->isIncompleteSupportMaterialEvent($row, $materialId)
        ));
    }

    private function isIncompleteSupportMaterialEvent(array $row, int $materialId): bool
    {
        if ((string) ($row['entity_type'] ?? '') !== 'support_material'
            || (int) ($row['entity_id'] ?? 0) !== $materialId
            || (string) ($row['module'] ?? '') !== 'Repositorio'
            || !in_array((string) ($row['action'] ?? ''), ['support_material.updated', 'support_material_updated'], true)) {
            return false;
        }
        return $this->recoverableChanges($row['details'] ?? null) === [];
    }

    private function recoverableChanges(mixed $details): array
    {
        if (is_array($details)) {
            $decoded = $details;
        } else {
            $raw = trim((string) $details);
            if ($raw === '') return [];
            $decoded = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) return [];
        }
        $source = is_array($decoded['changes'] ?? null) ? $decoded['changes'] : [];
        if ($source === [] && isset($decoded['field']) && (array_key_exists('old', $decoded) || array_key_exists('previous', $decoded)) && array_key_exists('new', $decoded)) {
            $source = [$decoded];
        }
        $changes = [];
        foreach ($source as $change) {
            if (!is_array($change) || !isset($change['field']) || !array_key_exists('new', $change)
                || (!array_key_exists('old', $change) && !array_key_exists('previous', $change))) continue;
            $changes[] = [
                'field' => (string) $change['field'],
                'label' => (string) ($change['label'] ?? $change['field']),
                'old' => $change['old'] ?? $change['previous'] ?? null,
                'new' => $change['new'],
            ];
        }
        return $changes;
    }

    private function normalize(array $row): array
    {
        $changes = $this->recoverableChanges($row['details'] ?? null);
        $changes = array_map(function(array $change): array {
            $field=(string)($change['field']??'');
            $change['label']=$this->fieldLabel($field,(string)($change['label']??''));
            $change['old']=$this->displayValue($field,$change['old']??null);
            $change['new']=$this->displayValue($field,$change['new']??null);
            return $change;
        },$changes);
        $decodedDetails = json_decode((string) ($row['details'] ?? ''), true);
        $cleanupDetails = (string) $row['action'] === 'support_material.history_cleaned' && is_array($decodedDetails)
            ? [
                'deleted_count' => max(0, (int) ($decodedDetails['deleted_count'] ?? 0)),
                'reason' => (string) ($decodedDetails['reason'] ?? ''),
            ]
            : null;
        $metadata = is_array($decodedDetails) ? $decodedDetails : [];
        unset($metadata['changes'], $metadata['schema_version']);
        if((array_key_exists('reason',$metadata)||array_key_exists('reason_label',$metadata)||array_key_exists('reason_code',$metadata))
            && trim((string)($metadata['reason']??''))===''){
            $metadata['reason']=(string)($metadata['reason_label']??$metadata['reason_code']??'');
        }
        unset($metadata['reason_label']);
        $legacyWithoutDetails = in_array((string) $row['action'], ['support_material.updated', 'support_material_updated'], true)
            && $changes === [];

        $utc = new DateTimeImmutable((string) $row['created_at'], new DateTimeZone('UTC'));
        $config = require APP_PATH . '/config/app.php';
        $timezoneName = (string) ($config['timezone'] ?? 'America/Guayaquil');
        if (!in_array($timezoneName, timezone_identifiers_list(), true)) $timezoneName = 'America/Guayaquil';
        $localDate = $utc->setTimezone(new DateTimeZone($timezoneName));

        return [
            'id' => (int) $row['id'],
            'action' => (string) $row['action'],
            'action_label' => $this->actionLabel((string) $row['action'], (string) ($row['action_label'] ?? '')),
            'summary' => (string) ($row['element_label'] ?? 'Material de apoyo'),
            'actor' => [
                'name' => trim((string) ($row['actor_name'] ?? '')) ?: 'Usuario no disponible',
                'email' => (string) ($row['actor_email'] ?? ''),
                'role' => (string) ($row['actor_roles'] ?? ''),
            ],
            'created_at' => $localDate->format(DateTimeInterface::ATOM),
            'created_at_label' => $this->formatDate($localDate),
            'changes' => $changes,
            'has_details' => $changes !== [],
            'legacy_without_details' => $legacyWithoutDetails,
            'cleanup' => $cleanupDetails,
            'details' => $this->detailRows((string) $row['action'], $metadata),
        ];
    }

    private function detailRows(string $action, array $details): array
    {
        $details = $this->relevantPresentationDetails($action, $details);
        $labels = [
            'name'=>'Archivo','original_name'=>'Archivo retirado','final_name'=>'Nombre final restaurado',
            'file_name'=>'Archivo','old_file_name'=>'Archivo anterior','new_file_name'=>'Archivo nuevo',
            'extension'=>'Tipo','mime_type'=>'Tipo MIME','size'=>'Tamaño','size_bytes'=>'Tamaño','reason'=>'Motivo',
            'reason_detail'=>'Detalle','previous_status'=>'Estado anterior','new_status'=>'Estado nuevo',
            'previous_availability'=>'Disponibilidad anterior','new_availability'=>'Disponibilidad nueva',
            'previous_available'=>'Disponibilidad anterior','is_available'=>'Disponibilidad nueva',
            'available'=>'Disponibilidad','availability'=>'Disponibilidad','renamed'=>'Renombrado por conflicto',
            'presentation'=>'Era presentación','presentation_unchanged'=>'Presentación conservada',
            'presentation_assignment'=>'Presentación',
            'previous_file'=>'Archivo anterior','new_file'=>'Archivo nuevo',
            'presentation_previous'=>'Presentación anterior','presentation_new'=>'Presentación nueva',
            'previous_name'=>'Presentación anterior','new_name'=>'Presentación nueva',
            'name_conflict'=>'Conflicto de nombre','deleted_by_name'=>'Retirado por','actor'=>'Realizado por',
            'destination'=>'Destino','previous_trash_reason'=>'Motivo anterior de Papelera',
        ];
        $rows = [];
        foreach ($details as $key=>$value) {
            if (str_ends_with((string)$key, '_id') || in_array($key, ['restore_hours','deleted_by','deleted_at','purged_at','purged_by','republication','context','is_package','reason_code','result'], true)) continue;
            if ($value===null || (is_string($value) && trim($value)==='')) continue;
            if (is_array($value)) {
                if (in_array($key, ['previous_file','new_file'], true)) {
                    $name=(string)($value['original_name']??$value['name']??'');
                    $size=isset($value['size_bytes'])?ArchiveService::formatBytes((int)$value['size_bytes']):'';
                    $value=trim($name.($size!==''?' · '.$size:''));
                } elseif ($key==='files') {
                    $value=implode(', ',array_map(static fn(array $file):string=>
                        (string)($file['original_name']??$file['name']??'Archivo')
                        .(isset($file['size_bytes'])?' · '.ArchiveService::formatBytes((int)$file['size_bytes']):''),
                        array_filter($value,'is_array')
                    ));
                    $labels[$key]='Archivos';
                } else continue;
            }
            if (in_array($key,['size','size_bytes'],true)) $value=ArchiveService::formatBytes((int)$value);
            $rows[]=['key'=>(string)$key,'label'=>$labels[$key]??$this->fieldLabel((string)$key),'value'=>$this->displayValue((string)$key,$value)];
        }
        return $rows;
    }

    private function relevantPresentationDetails(string $action, array $details): array
    {
        if ($action !== 'support_material.file_replaced') return $details;

        $previousKey = array_key_exists('presentation_previous', $details)
            ? 'presentation_previous'
            : (array_key_exists('presentation', $details) ? 'presentation' : null);
        $newKey = array_key_exists('presentation_new', $details) ? 'presentation_new' : null;
        $previous = $previousKey !== null ? $this->booleanDetailValue($details[$previousKey]) : null;
        $new = $newKey !== null ? $this->booleanDetailValue($details[$newKey]) : null;

        if ($previous !== null && $new !== null) {
            unset($details['presentation_previous'], $details['presentation_new'], $details['presentation'], $details['presentation_unchanged']);
            if ($previous && $new) {
                $details['presentation_unchanged'] = true;
            } elseif ($previous && !$new) {
                $details['presentation_unchanged'] = false;
            } elseif (!$previous && $new) {
                $details['presentation_assignment'] = 'Convertido en archivo de presentación';
            }
            return $details;
        }

        if (array_key_exists('presentation_unchanged', $details)
            && $this->booleanDetailValue($details['presentation_unchanged']) !== true) {
            unset($details['presentation_unchanged']);
        }
        return $details;
    }

    private function booleanDetailValue(mixed $value): ?bool
    {
        if (is_bool($value)) return $value;
        if (is_int($value) || is_float($value)) return (int) $value !== 0;
        if (!is_string($value)) return null;
        return match (mb_strtolower(trim($value), 'UTF-8')) {
            '1', 'true', 'yes', 'sí', 'si' => true,
            '0', 'false', 'no' => false,
            default => null,
        };
    }

    private function actionLabel(string $action, string $fallback): string
    {
        return match ($action) {
            'support_material.updated', 'support_material_updated' => 'Editó la información del material',
            'support_material.created', 'support_material_created' => 'Creó el material de apoyo',
            'support_material.history_cleaned' => 'Realizó una depuración del historial administrativo',
            'support_material.published', 'support_material_published' => 'Publicó el material de apoyo',
            'support_material.withdrawn', 'support_material_withdrawn' => 'Retiró la publicación del material',
            'support_material.availability_changed', 'support_material_availability_changed' => 'Cambió la disponibilidad del material',
            'support_material.trashed', 'support_material_trashed' => 'Envió el material a Papelera',
            'support_material.restored', 'support_material_restored' => 'Restauró el material',
            'support_material.file_added' => 'Agregó un archivo al material',
            'support_material.file_removed' => 'Retiró un archivo del material',
            'support_material.file_replaced' => 'Reemplazó un archivo del material',
            'support_material.file_restored' => 'Restauró un archivo del material',
            'support_material.file_purged' => 'Eliminó definitivamente un archivo',
            'support_material.presentation_selected' => 'Seleccionó el archivo de presentación',
            'support_material.presentation_changed' => 'Cambió el archivo de presentación',
            'support_material.presentation_removed' => 'Quitó el archivo de presentación',
            'project.file_added' => 'Agregó un archivo al proyecto',
            'project.file_removed' => 'Retiró un archivo del proyecto',
            'project.file_replaced' => 'Reemplazó un archivo del proyecto',
            'project.file_restored' => 'Restauró un archivo del proyecto',
            'project.file_purged' => 'Eliminó definitivamente un archivo del proyecto',
            'project.presentation_changed' => 'Cambió el archivo de presentación del proyecto',
            'project.presentation_removed' => 'Quitó el archivo de presentación del proyecto',
            default => $fallback !== '' ? $fallback : 'Se actualizó el material',
        };
    }

    private function fieldLabel(string $field,string $fallback=''):string
    {
        $labels=[
            'previous_status'=>'Estado anterior','new_status'=>'Estado nuevo','status'=>'Estado',
            'previous_availability'=>'Disponibilidad anterior','new_availability'=>'Disponibilidad nueva',
            'previous_available'=>'Disponibilidad anterior','is_available'=>'Disponibilidad nueva',
            'reason'=>'Motivo','reason_detail'=>'Detalle','file_name'=>'Archivo',
            'old_file_name'=>'Archivo anterior','new_file_name'=>'Archivo nuevo',
            'presentation_previous'=>'Presentación anterior','presentation_new'=>'Presentación nueva',
            'mime_type'=>'Tipo MIME','size'=>'Tamaño','size_bytes'=>'Tamaño','actor'=>'Realizado por',
        ];
        if(isset($labels[$field]))return $labels[$field];
        if($fallback!==''&&!preg_match('/^[a-z0-9_.-]+$/i',$fallback))return $fallback;
        return $this->readableTechnicalText($fallback!==''?$fallback:$field);
    }

    private function displayValue(string $field,mixed $value,int $depth=0):string
    {
        if(is_array($value))return $this->displayArrayValue($field,$value,$depth);
        if($value===null||$value==='')return 'Vacío';
        if(is_bool($value)){
            return in_array($field,['available','availability','previous_available','is_available','previous_availability','new_availability'],true)
                ?($value?'Disponible':'No disponible')
                :($value?'Sí':'No');
        }
        if(is_int($value)||is_float($value))return (string)$value;
        if($value instanceof Stringable){
            try{
                $value=(string)$value;
            }catch(Throwable){
                return 'Objeto no representable';
            }
        }elseif(is_object($value)){
            return 'Objeto '.get_debug_type($value);
        }elseif(is_resource($value)){
            return 'Recurso '.get_resource_type($value);
        }elseif(!is_string($value)){
            return 'Valor no representable';
        }
        $text=trim($value);$normalized=mb_strtolower($text);
        $values=[
            'published'=>'Publicado','withdrawn'=>'Publicación retirada','draft'=>'Borrador',
            'trash'=>'En Papelera','trashed'=>'En Papelera','papelera'=>'En Papelera',
            'available'=>'Disponible','unavailable'=>'No disponible','active'=>'Activo','inactive'=>'Inactivo',
            'true'=>'Sí','false'=>'No','yes'=>'Sí','no'=>'No',
            'outdated'=>'Información desactualizada','outdated_information'=>'Información desactualizada',
            'incorrect'=>'Publicación incorrecta','incorrect_publication'=>'Publicación incorrecta',
            'replaced'=>'Material reemplazado','material_replaced'=>'Material reemplazado',
            'administrative_review'=>'Revisión administrativa','temporary_review'=>'Revisión administrativa',
            'pending_review'=>'Revisión pendiente','temporary_update'=>'Actualización temporal del contenido',
            'files_pending'=>'Archivos pendientes de corrección','temporary_suspension'=>'Acceso suspendido temporalmente',
            'corrections_completed'=>'Correcciones completadas','content_updated'=>'Contenido actualizado',
            'files_verified'=>'Archivos verificados','review_completed'=>'Revisión administrativa finalizada',
            'incomplete_files'=>'Archivos incompletos','duplicate'=>'Contenido duplicado',
            'not_required'=>'Ya no es requerido','other'=>'Otro motivo',
        ];
        if(isset($values[$normalized]))return $values[$normalized];
        $technicalFields=['status','previous_status','new_status','availability','previous_availability','new_availability','reason','reason_code','destination'];
        return in_array($field,$technicalFields,true)?$this->readableTechnicalText($text):$text;
    }

    private function displayArrayValue(string $field,array $value,int $depth):string
    {
        if($value===[])return 'Vacío';
        if($depth>=8)return 'Estructura anidada';

        $isList=$this->isListArray($value);
        $formatted=[];
        foreach($value as $key=>$item){
            $itemField=$isList?$field:(string)$key;
            $itemText=$this->displayValue($itemField,$item,$depth+1);
            if(is_array($item)){
                $itemIsList=$this->isListArray($item);
                $itemText=($itemIsList?'[':'{').$itemText.($itemIsList?']':'}');
            }
            $formatted[]=$isList
                ?$itemText
                :$this->fieldLabel((string)$key).': '.$itemText;
        }
        return implode($isList?', ':'; ',$formatted);
    }

    private function isListArray(array $value):bool
    {
        return $value===[]||array_keys($value)===range(0,count($value)-1);
    }

    private function readableTechnicalText(string $value):string
    {
        $text=mb_strtolower(trim(str_replace(['.','-','_'],' ',$value)));
        if($text==='')return 'Sin información';
        $words=['pending'=>'pendiente','review'=>'revisión','temporary'=>'temporal','administrative'=>'administrativa',
            'information'=>'información','outdated'=>'desactualizada','incorrect'=>'incorrecta','publication'=>'publicación',
            'material'=>'material','replaced'=>'reemplazado','available'=>'disponible','unavailable'=>'no disponible',
            'previous'=>'anterior','new'=>'nuevo','status'=>'estado','reason'=>'motivo','detail'=>'detalle',
            'file'=>'archivo','name'=>'nombre','presentation'=>'presentación','active'=>'activo','inactive'=>'inactivo'];
        $translated=array_map(static fn(string $word):string=>$words[$word]??$word,preg_split('/\s+/u',$text)?:[]);
        return ucfirst(implode(' ',$translated));
    }

    private function formatDate(DateTimeImmutable $date): string
    {
        $months = [1=>'enero',2=>'febrero',3=>'marzo',4=>'abril',5=>'mayo',6=>'junio',
            7=>'julio',8=>'agosto',9=>'septiembre',10=>'octubre',11=>'noviembre',12=>'diciembre'];
        return (int) $date->format('j') . ' de ' . $months[(int) $date->format('n')]
            . ' de ' . $date->format('Y') . ', ' . $date->format('H:i');
    }
}
