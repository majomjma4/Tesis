<?php

declare(strict_types=1);

final class SupportMaterialModel
{
    public function getAll(): array
    {
        $storage = ROOT_PATH . '/storage/support-materials/';
        $materials = [
            [
                'id' => 1,
                'title' => 'Guía para la elaboración del perfil de tesis',
                'description' => 'Orientaciones para estructurar correctamente el perfil y preparar el proceso de titulación.',
                'full_description' => "Esta guía reúne los criterios institucionales para elaborar el perfil de tesis.\n\nIncluye recomendaciones para delimitar el tema, formular objetivos, organizar antecedentes y presentar la propuesta académica.",
                'category_slug' => 'tesis', 'category_label' => 'Tesis', 'type' => 'Guía', 'pao_label' => 'PAO I 2026', 'year' => '2026',
                'publication_date' => '8 de julio de 2026', 'status' => 'Disponible', 'downloads' => 86,
                'keywords' => ['Perfil de tesis', 'Titulación', 'Metodología'],
                'files' => [
                    ['id' => 1, 'name' => 'guia_perfil_tesis.pdf', 'format' => 'PDF', 'path' => $storage . 'guia_perfil_tesis.pdf', 'primary' => true],
                    ['id' => 2, 'name' => 'lista_de_verificacion_para_elaboracion_del_perfil_de_tesis.txt', 'format' => 'TXT', 'path' => $storage . 'lista_de_verificacion_para_elaboracion_del_perfil_de_tesis.txt', 'primary' => false],
                ],
                'package' => ['id' => 99, 'name' => 'material_tesis_completo.zip', 'format' => 'ZIP', 'path' => $storage . 'material_tesis_completo.zip', 'primary' => false, 'package' => true],
            ],
            [
                'id' => 2, 'title' => 'Formato de seguimiento de prácticas preprofesionales',
                'description' => 'Formato institucional para registrar actividades, horas cumplidas y evidencias de prácticas.',
                'full_description' => "Documento editable destinado al seguimiento periódico de las prácticas preprofesionales.\n\nPermite registrar actividades, resultados, evidencias y validaciones del responsable institucional.",
                'category_slug' => 'practicas', 'category_label' => 'Prácticas', 'type' => 'Formato', 'pao_label' => 'PAO I 2026', 'year' => '2026',
                'publication_date' => '20 de junio de 2026', 'status' => 'Disponible', 'downloads' => 63,
                'keywords' => ['Prácticas', 'Seguimiento', 'Evidencias'],
                'files' => [['id' => 1, 'name' => 'seguimiento_practicas.docx', 'format' => 'DOCX', 'path' => $storage . 'seguimiento_practicas.docx', 'primary' => true]],
            ],
            [
                'id' => 3, 'title' => 'Instructivo para proyectos PIS',
                'description' => 'Pasos y criterios para organizar entregables, evidencias y presentación de proyectos integradores.',
                'full_description' => "Este instructivo explica el flujo recomendado para desarrollar proyectos PIS.\n\nDetalla la organización de equipos, entregables mínimos, evidencias y criterios generales de presentación.",
                'category_slug' => 'proyecto-pis', 'category_label' => 'Proyectos PIS', 'type' => 'Instructivo', 'pao_label' => 'PAO II 2025', 'year' => '2025',
                'publication_date' => '12 de diciembre de 2025', 'status' => 'Disponible', 'downloads' => 49,
                'keywords' => ['PIS', 'Entregables', 'Proyectos'],
                'files' => [['id' => 1, 'name' => 'instructivo_proyectos_pis.pdf', 'format' => 'PDF', 'path' => $storage . 'instructivo_proyectos_pis.pdf', 'primary' => true]],
            ],
            [
                'id' => 4, 'title' => 'Formato de informe de vinculación',
                'description' => 'Plantilla editable para documentar actividades, beneficiarios, resultados e impacto comunitario.',
                'full_description' => "Plantilla institucional para presentar el informe de las actividades de vinculación.\n\nOrganiza objetivos, participantes, resultados, evidencias e indicadores de impacto comunitario.",
                'category_slug' => 'vinculacion', 'category_label' => 'Vinculación', 'type' => 'Plantilla', 'pao_label' => 'PAO II 2025', 'year' => '2025',
                'publication_date' => '30 de noviembre de 2025', 'status' => 'Disponible', 'downloads' => 38,
                'keywords' => ['Vinculación', 'Informe', 'Impacto'],
                'files' => [['id' => 1, 'name' => 'informe_vinculacion.docx', 'format' => 'DOCX', 'path' => $storage . 'informe_vinculacion.docx', 'primary' => true]],
            ],
            [
                'id' => 5, 'title' => 'Reglamento de uso del material académico',
                'description' => 'Disposiciones generales para consultar y utilizar responsablemente los recursos institucionales.',
                'full_description' => "Documento informativo sobre el uso responsable del material académico institucional.\n\nResume las condiciones de consulta, atribución y distribución de los recursos disponibles.",
                'category_slug' => 'tesis', 'category_label' => 'Tesis', 'type' => 'Reglamento', 'pao_label' => 'PAO I 2025', 'year' => '2025',
                'publication_date' => '14 de mayo de 2025', 'status' => 'Disponible', 'downloads' => 21,
                'keywords' => ['Reglamento', 'Recursos', 'Uso académico'],
                'files' => [['id' => 1, 'name' => 'reglamento_material_apoyo.txt', 'format' => 'TXT', 'path' => $storage . 'reglamento_material_apoyo.txt', 'primary' => true]],
            ],
        ];

        return array_map([$this, 'hydrateFiles'], $materials);
    }

    public function findById(int $materialId): ?array
    {
        foreach ($this->getAll() as $material) {
            if ($material['id'] === $materialId) return $material;
        }
        return null;
    }

    public function findFile(array $material, int $fileId): ?array
    {
        foreach ($material['files'] as $file) {
            if ($file['id'] === $fileId) return $file;
        }
        if (isset($material['package']) && $material['package']['id'] === $fileId) return $material['package'];
        return null;
    }

    private function hydrateFiles(array $material): array
    {
        $material['publisher'] ??= 'Instituto Superior Tecnológico "El Libertador"';
        foreach ($material['files'] as &$file) {
            $size = is_file($file['path']) ? (int) filesize($file['path']) : 0;
            $file['size_bytes'] = $size;
            $file['size'] = ArchiveService::formatBytes($size);
            $file['extension'] = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        }
        unset($file);
        $material['primary_file'] = current(array_filter($material['files'], static fn (array $file): bool => $file['primary'])) ?: $material['files'][0];
        $material['additional_files'] = array_values(array_filter($material['files'], static fn (array $file): bool => !$file['primary']));
        $material['files_count'] = count($material['files']);
        $material['size_bytes'] = array_sum(array_column($material['files'], 'size_bytes'));
        $material['size'] = ArchiveService::formatBytes($material['size_bytes']);
        if (isset($material['package'])) {
            $packageSize = is_file($material['package']['path']) ? (int) filesize($material['package']['path']) : 0;
            $material['package']['size_bytes'] = $packageSize;
            $material['package']['size'] = ArchiveService::formatBytes($packageSize);
            $material['package']['extension'] = 'zip';
        }
        return $material;
    }
}
