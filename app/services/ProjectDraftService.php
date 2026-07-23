<?php
declare(strict_types=1);

/** Define y valida el borrador temporal. No contiene persistencia. */
final class ProjectDraftService
{
    private const TYPES = [
        'thesis' => ['label' => 'Titulación', 'prefix' => 'TIT', 'additional' => 'research_line'],
        'thesis_profile' => ['label' => 'Perfil de tesis', 'prefix' => 'PFT', 'additional' => 'research_line'],
        'pis' => ['label' => 'Proyecto integrador de saberes (PIS)', 'prefix' => 'PIS', 'additional' => 'academic_subject'],
        'practice' => ['label' => 'Prácticas preprofesionales', 'prefix' => 'PRA', 'additional' => null],
        'community' => ['label' => 'Proyecto de vinculación', 'prefix' => 'VIN', 'additional' => null],
    ];

    public function catalogs(): array
    {
        return ['types' => self::TYPES, 'periods' => ['2026-I', '2026-II', '2027-I'], 'modalities' => ['individual' => 'Individual', 'group' => 'Grupal'],
            'research_lines' => ['Sistemas de información', 'Innovación tecnológica', 'Transformación digital'],
            'subjects' => ['Integración curricular', 'Proyecto integrador', 'Nivel académico por definir'],
            'community_programs' => ['Alfabetización digital', 'Innovación comunitaria', 'Programa por definir'],
            'teachers' => [
                ['id' => 'teacher-1', 'name' => 'Msc. Maribel Fierro Montero'],
                ['id' => 'teacher-2', 'name' => 'Msc. Maria Elena Navarrete'],
                ['id' => 'teacher-3', 'name' => 'Lic. Diana Alegría Camino'],
                ['id' => 'teacher-4', 'name' => 'Msc. Diana Anaid Ramirez'],
                ['id' => 'teacher-5', 'name' => 'Abg. Alex Fabián Galarza'],
                ['id' => 'teacher-6', 'name' => 'Msc. Henrry Mariño Acosta'],
            ],
            'semesters' => ['5' => 'Quinto semestre', '6' => 'Sexto semestre'],
            'students' => [['id' => 'student-1', 'name' => 'María José Monteros', 'semester' => '6'], ['id' => 'student-2', 'name' => 'Juan Pérez', 'semester' => '6'], ['id' => 'student-3', 'name' => 'Camila Torres', 'semester' => '5'], ['id' => 'student-4', 'name' => 'Luis Mendoza', 'semester' => '6']]];
    }

    public function fieldContract(): array
    {
        return [
            'thesis' => ['required' => ['title','description','period','modality','research_line','tutor_id'], 'additional' => ['research_line'], 'uses_description' => true],
            'thesis_profile' => ['required' => ['title','description','period','research_line','tutor_id'], 'additional' => ['research_line'], 'uses_description' => true],
            'pis' => ['required' => ['title','description','period','academic_subject','tutor_id'], 'additional' => ['academic_subject'], 'uses_description' => true],
            'practice' => ['required' => ['title','period','tutor_id'], 'additional' => [], 'uses_description' => false, 'institution_scope' => 'Instituto Superior Tecnológico El Libertador'],
            'community' => ['required' => ['title','period','tutor_id'], 'additional' => [], 'uses_description' => false],
        ];
    }

    public function normalize(array $payload, array $policy): array
    {
        $value = static fn (string $key): string => trim((string) ($payload[$key] ?? ''));
        $members = array_values(array_unique(array_filter(array_map('strval', (array) ($payload['members'] ?? [])))));
        if ($policy['auto_leader'] && !in_array('student-1', $members, true)) array_unshift($members, 'student-1');
        return ['type'=>$value('type'),'title'=>$value('title'),'description'=>$value('description'),'period'=>$value('period'),'modality'=>$value('modality'),
            'research_line'=>$value('research_line'),'academic_subject'=>$value('academic_subject'),'receiving_institution'=>$value('receiving_institution'),
            'community_program'=>$value('community_program'),'tutor_id'=>$value('tutor_id'),'leader_id'=>$policy['auto_leader']?'student-1':$value('leader_id'),
            'members'=>$members,'tags'=>array_slice(array_values(array_unique(array_filter(array_map(static fn($v)=>trim((string)$v),(array)($payload['tags']??[]))))),0,8)];
    }

    public function validate(array $draft, array $policy): array
    {
        $errors=[]; $type=$draft['type'];
        if (!isset(self::TYPES[$type])) $errors['type']='Selecciona un tipo de proyecto válido.';
        if (mb_strlen($draft['title'])<8) $errors['title']='Escribe un título de al menos 8 caracteres.';
        elseif (mb_strlen($draft['title'])>180) $errors['title']='El título no puede superar 180 caracteres.';
        if (($this->fieldContract()[$type]['uses_description'] ?? false) && mb_strlen($draft['description'])<30) $errors['description']='Describe el proyecto con al menos 30 caracteres.';
        if (!in_array($draft['period'],$this->catalogs()['periods'],true)) $errors['period']='Selecciona un periodo válido.';
        foreach (($this->fieldContract()[$type]['required']??[]) as $field) if (($draft[$field]??'')===''&&!isset($errors[$field])) $errors[$field]='Este campo es obligatorio para el tipo seleccionado.';
        if ($policy['must_select_leader']&&$draft['leader_id']==='') $errors['leader_id']='Selecciona al estudiante líder.';
        if ($draft['leader_id']!==''&&!in_array($draft['leader_id'],$draft['members'],true)) $errors['leader_id']='El líder debe formar parte del equipo.';
        if ($draft['modality']==='individual'&&count($draft['members'])>1) $errors['members']='La modalidad individual solo admite un estudiante.';
        return $errors;
    }

    public function validateFiles(array $files, PrivateProjectFileService $service): array
    {
        $errors=[];$valid=[];$seen=[];$total=0;
        foreach ($this->flattenFiles($files) as $i=>$file) try { $item=$service->validateUpload($file);$key=mb_strtolower($item['original_name']);if(isset($seen[$key]))throw new InvalidArgumentException('Hay dos archivos con el mismo nombre.');$seen[$key]=1;$total+=$item['size_bytes'];$valid[]=$item; } catch(InvalidArgumentException $e){$errors['files.'.$i]=$e->getMessage();}
        if($total>$service->limits()['max_total_bytes'])$errors['files']='El conjunto supera el límite total de '.$service->limits()['max_total_mb'].' MB.';
        return ['errors'=>$errors,'valid'=>$valid];
    }

    public function confirmation(array $draft,array $files):array
    {
        $teachers=array_column($this->catalogs()['teachers'],'name','id');
        try{$codeSettings=(new SystemSettingModel())->all();}catch(Throwable){$codeSettings=(new SystemSettingModel())->defaults();}
        $prefix=$codeSettings['project_code_prefixes'][$draft['type']]??'PRY';
        $digits=(int)($codeSettings['project_code_digits']??3);
        return $draft+['type_label'=>self::TYPES[$draft['type']]['label']??'Sin definir','tutor_label'=>$teachers[$draft['tutor_id']]??'Pendiente de asignación','provisional_code'=>$prefix.'-'.date('Y').'-'.str_repeat('X',$digits),'file_count'=>count($files)];
    }
    private function flattenFiles(array $files):array {if(!isset($files['name'])||!is_array($files['name']))return[];$out=[];foreach($files['name'] as $i=>$name)if(($files['error'][$i]??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)$out[]=['name'=>$name,'type'=>$files['type'][$i]??'','tmp_name'=>$files['tmp_name'][$i]??'','error'=>$files['error'][$i]??UPLOAD_ERR_NO_FILE,'size'=>$files['size'][$i]??0];return$out;}
}
