<?php
declare(strict_types=1);
final class SystemSettingModel
{
    private const APPLICATION_FILE_CEILING_MB=500;
    private const APPLICATION_OPERATION_CEILING_MB=1024;
    private const MULTIPART_MARGIN_MB=16;
    private const CODE_TYPES=['thesis'=>'TIT','thesis_profile'=>'PFT','pis'=>'PIS','practice'=>'PRA','community'=>'VIN'];
    private const ALLOWED_PRIVATE=['pdf','docx','zip'];
    private const ALLOWED_PROJECT=['pdf','docx','xlsx','pptx','png','jpg','jpeg','webp','txt','zip'];
    private const ALLOWED_SUPPORT=['pdf','docx','xlsx','pptx','png','jpg','jpeg','webp','txt','zip'];

    private static ?array $cache=null;

    public function defaults():array
    {
        return [
            'institution_name'=>'Instituto Superior Tecnológico El Libertador',
            'file_max_mb'=>20,
            'file_total_max_mb'=>35,
            'file_extensions'=>['pdf','docx','zip'],
            'file_extensions_private'=>['pdf','docx','zip'],
            'file_extensions_project'=>['pdf','docx','xlsx','pptx','png','jpg','jpeg','webp','txt','zip'],
            'file_extensions_support'=>['pdf','docx','xlsx','pptx','png','jpg','jpeg','webp','txt','zip'],
            'project_code_prefixes'=>self::CODE_TYPES,
            'project_code_digits'=>3,
            'temporary_password_days'=>7,
            'temporary_password_force_change'=>1,
            'retention_users_days'=>60,
            'retention_projects_days'=>60,
            'retention_materials_days'=>60,
            'notification_trash_retention_days'=>60,
            'withdrawn_file_restore_hours'=>24,
            'academic_period_reversal_hours'=>24,
            'academic_period_reminder_days'=>7,
            'calendar_reminder_days'=>3
            ,'session_inactivity_minutes'=>30
        ];
    }

    public function all():array
    {
        if(self::$cache!==null)return self::$cache;
        $values=$this->defaults();
        $loaded=[];
        foreach(Database::connection()->query('SELECT setting_key,setting_value FROM system_settings')->fetchAll() as $row){
            $key=(string)$row['setting_key'];
            if(!array_key_exists($key,$values))continue;
            $loaded[$key]=true;
            if(in_array($key,['file_extensions','file_extensions_private','file_extensions_project','file_extensions_support','project_code_prefixes'],true)){
                $decoded=json_decode((string)$row['setting_value'],true);
                if(is_array($decoded)){
                    $values[$key]=$key==='project_code_prefixes'?array_replace(self::CODE_TYPES,$decoded):$decoded;
                }
            }elseif($key==='institution_name'){
                $values[$key]=(string)$row['setting_value'];
            }else{
                $values[$key]=(int)$row['setting_value'];
            }
        }

        // Compatibilidad retroactiva: si file_extensions_private no existe en DB, usar file_extensions
        if (!isset($loaded['file_extensions_private']) && isset($values['file_extensions']) && is_array($values['file_extensions'])) {
            $values['file_extensions_private'] = array_values(array_intersect(self::ALLOWED_PRIVATE, $values['file_extensions']));
        }
        foreach (['file_extensions_private'=>self::ALLOWED_PRIVATE,'file_extensions_project'=>self::ALLOWED_PROJECT,'file_extensions_support'=>self::ALLOWED_SUPPORT] as $key=>$allowed) {
            $values[$key]=array_values(array_unique(array_intersect($allowed,(array)$values[$key])));
            if (($key==='file_extensions_project'||$key==='file_extensions_support') && (in_array('jpg',$values[$key],true)||in_array('jpeg',$values[$key],true))) {
                $values[$key]=array_values(array_unique([...$values[$key],'jpg','jpeg']));
            }
        }

        return self::$cache=$values;
    }

    public function retentionDays(string $key): int
    {
        $all = $this->all();
        return (int) ($all[$key] ?? $this->defaults()[$key] ?? 60);
    }

    public function sessionInactivityMinutes(): int
    {
        try { $value = (int) ($this->all()['session_inactivity_minutes'] ?? 30); }
        catch (Throwable) { $value = 30; }
        return $value >= 1 && $value <= 1440 ? $value : 30;
    }

    public function temporaryPasswordConfigured(): bool
    {
        try {
            $secret = $this->secretValue('temporary_password_secret');
            return $secret !== '' && $this->decryptSecret($secret) !== '';
        } catch (Throwable) {
            return false;
        }
    }

    public function save(array $input,int $actor):array
    {
        $currentSettings=$this->all();
        $name=trim((string)($input['institution_name']??''));
        $max=(int)($input['file_max_mb']??0);
        $total=(int)($input['file_total_max_mb']??0);
        $temporaryPassword=trim((string)($input['temporary_password']??''));
        $temporaryDays=(int)($input['temporary_password_days']??0);
        $forceChange=isset($input['temporary_password_force_change'])?1:0;
        $sessionInactivityMinutes=(int)($input['session_inactivity_minutes']??0);
        $retentions=[
            'retention_users_days'=>(int)($input['retention_users_days']??0),
            'retention_projects_days'=>(int)($input['retention_projects_days']??0),
            'retention_materials_days'=>(int)($input['retention_materials_days']??0),
            'notification_trash_retention_days'=>(int)($input['notification_trash_retention_days']??0),
            'withdrawn_file_restore_hours'=>(int)($input['withdrawn_file_restore_hours']??0),
            'academic_period_reversal_hours'=>(int)($input['academic_period_reversal_hours']??0),
            'academic_period_reminder_days'=>(int)($input['academic_period_reminder_days']??0),
            'calendar_reminder_days'=>(int)($input['calendar_reminder_days']??0)
        ];

        // Normalización y filtrado de extensiones por flujo
        $rawPrivate = isset($input['_file_extensions_private_present']) ? (array)($input['file_extensions_private'] ?? []) : (array)($input['file_extensions_private'] ?? $input['file_extensions'] ?? $currentSettings['file_extensions_private']);
        $rawProject = isset($input['_file_extensions_project_present']) ? (array)($input['file_extensions_project'] ?? []) : (array)($input['file_extensions_project'] ?? $currentSettings['file_extensions_project']);
        $rawSupport = isset($input['_file_extensions_support_present']) ? (array)($input['file_extensions_support'] ?? []) : (array)($input['file_extensions_support'] ?? $currentSettings['file_extensions_support']);

        // Si se envió jpg o jpeg, asegurarse de sincronizar ambas variantes si aplica
        if (in_array('jpg', $rawProject, true) || in_array('jpeg', $rawProject, true)) {
            $rawProject[] = 'jpg';
            $rawProject[] = 'jpeg';
        }
        if (in_array('jpg', $rawSupport, true) || in_array('jpeg', $rawSupport, true)) {
            $rawSupport[] = 'jpg';
            $rawSupport[] = 'jpeg';
        }

        $extensionsPrivate = array_values(array_unique(array_intersect(self::ALLOWED_PRIVATE, array_map('strval', $rawPrivate))));
        $extensionsProject = array_values(array_unique(array_intersect(self::ALLOWED_PROJECT, array_map('strval', $rawProject))));
        $extensionsSupport = array_values(array_unique(array_intersect(self::ALLOWED_SUPPORT, array_map('strval', $rawSupport))));

        $digits=(int)($input['project_code_digits']??0);
        $submittedPrefixes=(array)($input['project_code_prefixes']??[]);
        $prefixes=[];
        foreach(self::CODE_TYPES as $type=>$fallback)$prefixes[$type]=strtoupper(trim((string)($submittedPrefixes[$type]??$fallback)));

        if(mb_strlen($name)<5||mb_strlen($name)>180)throw new InvalidArgumentException('Ingresa un nombre institucional válido.');
        $fileCeiling=$this->fileUploadCeilingMb();$operationCeiling=$this->operationUploadCeilingMb();
        if($max<1||$max>$fileCeiling||$total<$max||$total>$operationCeiling)throw new InvalidArgumentException('El tamaño máximo por archivo debe estar entre 1 y '.$fileCeiling.' MB; el límite total por operación debe estar entre ese valor y '.$operationCeiling.' MB, según la capacidad actual del servidor.');
        if(!$extensionsPrivate)throw new InvalidArgumentException('Mantén al menos un formato habilitado para Borrador privado.');
        if(!$extensionsProject)throw new InvalidArgumentException('Mantén al menos un formato habilitado para Documentos del proyecto.');
        if(!$extensionsSupport)throw new InvalidArgumentException('Mantén al menos un formato habilitado para Materiales de apoyo.');
        if($digits<2||$digits>6)throw new InvalidArgumentException('La numeración de proyectos debe tener entre 2 y 6 dígitos.');
        if($temporaryDays<1||$temporaryDays>30)throw new InvalidArgumentException('La vigencia de la contraseña temporal debe estar entre 1 y 30 días.');
        if($retentions['retention_users_days']<1||$retentions['retention_users_days']>365)throw new InvalidArgumentException('La retención de usuarios debe estar entre 1 y 365 días.');
        if($retentions['retention_projects_days']<1||$retentions['retention_projects_days']>365)throw new InvalidArgumentException('La retención de proyectos debe estar entre 1 y 365 días.');
        if($retentions['retention_materials_days']<1||$retentions['retention_materials_days']>365)throw new InvalidArgumentException('La retención de materiales debe estar entre 1 y 365 días.');
        if($retentions['notification_trash_retention_days']<1||$retentions['notification_trash_retention_days']>365)throw new InvalidArgumentException('La retención de notificaciones debe estar entre 1 y 365 días.');
        if($retentions['withdrawn_file_restore_hours']<1||$retentions['withdrawn_file_restore_hours']>72)throw new InvalidArgumentException('La ventana de recuperación de archivos retirados debe estar entre 1 y 72 horas.');
        if($retentions['academic_period_reversal_hours']<1||$retentions['academic_period_reversal_hours']>72)throw new InvalidArgumentException('La ventana de reversión de período debe estar entre 1 y 72 horas.');
        if($retentions['academic_period_reminder_days']<1||$retentions['academic_period_reminder_days']>30)throw new InvalidArgumentException('El aviso de período académico debe estar entre 1 y 30 días.');
        if($retentions['calendar_reminder_days']<0||$retentions['calendar_reminder_days']>30)throw new InvalidArgumentException('Los recordatorios de calendario deben estar entre 0 y 30 días.');
        if($sessionInactivityMinutes<1||$sessionInactivityMinutes>1440)throw new InvalidArgumentException('El tiempo de inactividad de sesión debe estar entre 1 y 1440 minutos.');

        if($temporaryPassword!==''&&(mb_strlen($temporaryPassword)<10||mb_strlen($temporaryPassword)>128))throw new InvalidArgumentException('La contraseña temporal debe tener entre 10 y 128 caracteres.');
        foreach($prefixes as $prefix)if(!preg_match('/^[A-Z]{2,6}$/',$prefix))throw new InvalidArgumentException('Cada prefijo debe contener entre 2 y 6 letras mayúsculas.');
        if(count(array_unique($prefixes))!==count($prefixes))throw new InvalidArgumentException('Cada tipo de proyecto debe tener un prefijo diferente.');

        $submitted=[
            'institution_name'=>$name,
            'file_max_mb'=>(string)$max,
            'file_total_max_mb'=>(string)$total,
            'file_extensions'=>json_encode($extensionsPrivate,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
            'file_extensions_private'=>json_encode($extensionsPrivate,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
            'file_extensions_project'=>json_encode($extensionsProject,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
            'file_extensions_support'=>json_encode($extensionsSupport,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
            'project_code_prefixes'=>json_encode($prefixes,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
            'project_code_digits'=>(string)$digits,
            'temporary_password_days'=>(string)$temporaryDays,
            'temporary_password_force_change'=>(string)$forceChange,
            'session_inactivity_minutes'=>(string)$sessionInactivityMinutes
        ]+array_map('strval',$retentions);

        if($temporaryPassword!=='')$submitted['temporary_password_secret']=$this->encryptSecret($temporaryPassword);

        $changed=Database::transaction(function(PDO $d)use($submitted,$actor):array{
            $current=$this->defaults();
            foreach($d->query('SELECT setting_key,setting_value FROM system_settings FOR UPDATE')->fetchAll() as $row){
                $key=(string)$row['setting_key'];
                if(array_key_exists($key,$current))$current[$key]=(string)$row['setting_value'];
            }
            $q=$d->prepare('INSERT INTO system_settings(setting_key,setting_value,updated_by) VALUES(:key,:value,:actor) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by=VALUES(updated_by)');
            $changed=[];
            foreach($submitted as $key=>$value){
                $oldValue=$current[$key]??'';
                $old=is_array($oldValue)?json_encode($oldValue,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR):(string)$oldValue;
                if($old===$value)continue;
                $q->execute(['key'=>$key,'value'=>$value,'actor'=>$actor]);
                $changed[$key]=['old'=>$old,'new'=>$value];
            }
            $activity=new AdminActivityService($d);
            foreach($changed as $key=>$values){
                if($key==='file_extensions')continue; // Alias de compatibilidad del flujo Borrador.
                $secret=$key==='temporary_password_secret';
                if(in_array($key,['file_extensions_private','file_extensions_project','file_extensions_support'],true)){
                    foreach($this->formatChanges($key,$values['old'],$values['new']) as $change){
                        $activity->record($actor,'settings_updated',$change['message'],'Configuración','settings',null,$change['format'],'correct',['setting_key'=>$key,'format'=>$change['format'],'flow'=>$change['flow'],'enabled'=>$change['enabled']]);
                    }
                    continue;
                }
                if($key==='project_code_prefixes'){
                    foreach($this->prefixChanges($values['old'],$values['new']) as $change){
                        $activity->record($actor,'settings_updated',$change['message'],'Configuración','settings',null,$change['label'],'correct',['setting_key'=>$key,'prefix_key'=>$change['key'],'previous_value'=>$change['old'],'new_value'=>$change['new'],'previous_prefixes'=>$values['old'],'new_prefixes'=>$values['new']]);
                    }
                    continue;
                }
                $unit = $key === 'session_inactivity_minutes' ? 'minutos' : (str_contains($key, 'hours') ? 'horas' : 'días');
                $formattedOld = $secret ? 'sensible' : $values['old'] . ' ' . $unit;
                $formattedNew = $secret ? 'sensible' : $values['new'] . ' ' . $unit;
                $message = $secret ? 'Política de contraseña temporal actualizada' : $this->label($key) . ': ' . $values['old'] . ' → ' . $values['new'];
                $activity->record($actor,'settings_updated',$message,'Configuración','settings',null,$secret?'Política de contraseña temporal':$this->label($key),'correct',$secret?['setting_key'=>'temporary_password','sensitive'=>true]:['setting_key'=>$key,'previous_value'=>$values['old'],'new_value'=>$values['new']]);
            }
            return $changed;
        });

        self::$cache=null;
        return $changed;
    }

    public function temporaryPasswordPolicy():array
    {
        try {
            $secret=$this->secretValue('temporary_password_secret');
            if($secret==='')throw new RuntimeException('Temporary password secret is not configured.');
            $password=$this->decryptSecret($secret);
            $settings=$this->all();
            return ['password'=>$password,'days'=>(int)$settings['temporary_password_days'],'force_change'=>(bool)$settings['temporary_password_force_change']];
        } catch (Throwable $exception) {
            error_log('Temporary password policy unavailable: '.$exception->getMessage());
            throw new TemporaryPasswordPolicyException('No fue posible obtener la política de contraseña temporal.',0,$exception);
        }
    }
    public function fileUploadPolicy():array
    {
        $settings=$this->all();$fileCeiling=$this->fileUploadCeilingMb();$operationCeiling=$this->operationUploadCeilingMb();
        $max=max(1,min((int)$settings['file_max_mb'],$fileCeiling));
        $total=max($max,min((int)$settings['file_total_max_mb'],$operationCeiling));
        return ['max_mb'=>$max,'total_max_mb'=>$total,'max_bytes'=>$max*1024*1024,'max_total_bytes'=>$total*1024*1024,'file_ceiling_mb'=>$fileCeiling,'operation_ceiling_mb'=>$operationCeiling,'application_file_ceiling_mb'=>self::APPLICATION_FILE_CEILING_MB,'application_operation_ceiling_mb'=>self::APPLICATION_OPERATION_CEILING_MB,'server_file_ceiling_mb'=>$this->serverFileCeilingMb(),'server_operation_ceiling_mb'=>$this->serverOperationCeilingMb()];
    }
    public function fileUploadCeilingMb():int
    {
        return min(self::APPLICATION_FILE_CEILING_MB,$this->serverFileCeilingMb());
    }
    public function operationUploadCeilingMb():int
    {
        return min(self::APPLICATION_OPERATION_CEILING_MB,$this->serverOperationCeilingMb());
    }
    private function serverFileCeilingMb():int
    {
        return max(1,(int)floor($this->iniBytes((string)ini_get('upload_max_filesize'))/(1024*1024)));
    }
    private function serverOperationCeilingMb():int
    {
        return max(1,(int)floor($this->iniBytes((string)ini_get('post_max_size'))/(1024*1024))-self::MULTIPART_MARGIN_MB);
    }
    private function iniBytes(string $value):int
    {
        $toBytes=static function(string $value):int{$value=trim(strtolower($value));if($value===''||$value==='-1')return PHP_INT_MAX;$unit=substr($value,-1);$number=(float)$value;return (int)round($number*match($unit){'g'=>1024**3,'m'=>1024**2,'k'=>1024,default=>1});};
        return $toBytes($value);
    }
    private function secretValue(string $key):string{$q=Database::connection()->prepare('SELECT setting_value FROM system_settings WHERE setting_key=:key');$q->execute(['key'=>$key]);return (string)($q->fetchColumn()?:'');}
    private function encryptionKey():string{$raw=(string)($GLOBALS['config']['settings_encryption_key']??'');$key=base64_decode($raw,true);if($key===false||strlen($key)!==32)throw new RuntimeException('Configura APP_SETTINGS_ENCRYPTION_KEY con una clave Base64 de 32 bytes para actualizar la contraseña temporal.');return $key;}
    private function encryptSecret(string $value):string{$iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($value,'aes-256-gcm',$this->encryptionKey(),OPENSSL_RAW_DATA,$iv,$tag);if($cipher===false)throw new RuntimeException('No fue posible proteger la contraseña temporal.');return base64_encode($iv.$tag.$cipher);}
    private function decryptSecret(string $value):string{$raw=base64_decode($value,true);if($raw===false||strlen($raw)<29)throw new RuntimeException('La contraseña temporal almacenada no es válida.');$plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',$this->encryptionKey(),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16));if(!is_string($plain)||$plain==='')throw new RuntimeException('No fue posible leer la contraseña temporal.');return $plain;}
    private function label(string $key):string{return ['institution_name'=>'Nombre de la institución','file_max_mb'=>'Límite por archivo','file_total_max_mb'=>'Límite total por operación','file_extensions'=>'Formatos permitidos (Borrador)','file_extensions_private'=>'Formatos permitidos en Borrador','file_extensions_project'=>'Formatos permitidos en Proyecto','file_extensions_support'=>'Formatos permitidos en Materiales de apoyo','project_code_prefixes'=>'Prefijos de proyectos','project_code_digits'=>'Dígitos de códigos de proyectos','temporary_password_days'=>'Vigencia de contraseña temporal','temporary_password_force_change'=>'Cambio obligatorio de contraseña temporal','retention_users_days'=>'Retención de usuarios','retention_projects_days'=>'Retención de proyectos','retention_materials_days'=>'Retención de materiales','notification_trash_retention_days'=>'Retención de notificaciones','withdrawn_file_restore_hours'=>'Recuperación de archivos retirados','academic_period_reversal_hours'=>'Reversión de cierre de período','academic_period_reminder_days'=>'Aviso de período académico','calendar_reminder_days'=>'Recordatorios de calendario','session_inactivity_minutes'=>'Tiempo de inactividad de sesión'][$key]??$key;}
    private function formatChanges(string $key,string $old,string $new):array
    {
        $oldValues=json_decode($old,true);$newValues=json_decode($new,true);
        $oldValues=is_array($oldValues)?$oldValues:[];$newValues=is_array($newValues)?$newValues:[];
        $flow=['file_extensions_private'=>'Borrador','file_extensions_project'=>'Documentos del proyecto','file_extensions_support'=>'Materiales de apoyo'][$key];
        $formats=['pdf'=>'PDF','docx'=>'DOCX','txt'=>'TXT','png'=>'PNG','jpg'=>'JPG / JPEG','webp'=>'WEBP','xlsx'=>'XLSX','pptx'=>'PPTX','zip'=>'ZIP'];
        $enabled=static function(array $values,string $format):bool{return $format==='jpg'?(in_array('jpg',$values,true)||in_array('jpeg',$values,true)):in_array($format,$values,true);};
        $changes=[];
        foreach($formats as $format=>$label){$before=$enabled($oldValues,$format);$after=$enabled($newValues,$format);if($before===$after)continue;$changes[]=['format'=>$label,'flow'=>$flow,'enabled'=>$after,'message'=>$label.' '.($after?'habilitado':'deshabilitado').' para '.$flow];}
        return $changes;
    }
    private function prefixChanges(string $old,string $new):array
    {
        $previous=json_decode($old,true);$current=json_decode($new,true);
        $previous=is_array($previous)?array_replace(self::CODE_TYPES,$previous):self::CODE_TYPES;
        $current=is_array($current)?array_replace(self::CODE_TYPES,$current):self::CODE_TYPES;
        $labels=['thesis'=>'Titulación','thesis_profile'=>'Perfil de tesis','pis'=>'Proyecto integrador de saberes','practice'=>'Prácticas preprofesionales','community'=>'Proyecto de vinculación'];
        $changes=[];
        foreach(self::CODE_TYPES as $key=>$fallback){$from=(string)($previous[$key]??$fallback);$to=(string)($current[$key]??$fallback);if($from!==$to)$changes[]=['key'=>$key,'label'=>'Prefijo de '.$labels[$key],'old'=>$from,'new'=>$to,'message'=>'Prefijo de '.$labels[$key].': '.$from.' → '.$to];}
        return $changes;
    }
}
