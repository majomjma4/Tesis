<?php

declare(strict_types=1);

require_once APP_PATH . '/services/fpdf.php';

final class AppPdfReportService extends FPDF
{
    private string $reportTitle;
    private string $subtitle;
    private array $headers;
    private array $colWidths;

    public function __construct(string $orientation = 'P', string $reportTitle = '', string $subtitle = '', array $headers = [], array $colWidths = [])
    {
        parent::__construct($orientation, 'mm', 'A4');
        $this->w = $this->CurOrientation === 'P' ? 210 : 297;
        $this->h = $this->CurOrientation === 'P' ? 297 : 210;
        $this->wPt = $this->w * $this->k;
        $this->hPt = $this->h * $this->k;
        $this->PageBreakTrigger = $this->h - 15;
        $this->reportTitle = $reportTitle;
        $this->subtitle = $subtitle;
        $this->headers = $headers;
        $this->colWidths = $colWidths;
        $this->AliasNbPages('{nb}');
        $this->SetAutoPageBreak(true, 15);
    }

    public function Header(): void
    {
        $pageWidth = $this->w;
        $logoWidth = 24; // mm
        $logoHeight = 24; // mm
        $topY = 8;

        $rootPath = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
        $logoLibertadorPath = $rootPath . '/public/assets/img/logo_libertador.png';
        $logoDsPath = $rootPath . '/public/assets/img/logo_ds.png';

        // Logo Izquierdo: Instituto El Libertador
        if (is_file($logoLibertadorPath)) {
            $this->Image($logoLibertadorPath, 12, $topY, $logoWidth, $logoHeight, 'PNG');
        }

        // Logo Derecho: Desarrollo de Software
        if (is_file($logoDsPath)) {
            $rightX = $pageWidth - 12 - $logoWidth;
            $this->Image($logoDsPath, $rightX, $topY, $logoWidth, $logoHeight, 'PNG');
        }

        // Bloque de texto central
        $this->SetY($topY + 1);
        $this->SetFont('Helvetica', 'B', 11);
        $this->SetTextColor(30, 58, 138); // Azul institucional #1E3A8A
        $this->Cell(0, 5, $this->iconvText('INSTITUTO SUPERIOR TECNOLÓGICO "EL LIBERTADOR"'), 0, 1, 'C');

        $this->SetFont('Helvetica', 'B', 9);
        $this->SetTextColor(5, 150, 105); // Verde institucional #059669
        $this->Cell(0, 4, $this->iconvText('Tecnología en Desarrollo de Software'), 0, 1, 'C');

        $this->SetFont('Helvetica', 'B', 10);
        $this->SetTextColor(15, 23, 42);
        $this->Cell(0, 5, $this->iconvText(strtoupper($this->reportTitle)), 0, 1, 'C');

        $this->SetFont('Helvetica', 'I', 8);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(0, 4, $this->iconvText($this->subtitle), 0, 1, 'C');

        // Garantizar posición Y por debajo del alto del logo (topY + logoHeight = 32mm)
        $this->SetY($topY + $logoHeight + 2);

        // Línea divisoria horizontal sutil debajo del membrete
        $this->SetDrawColor(203, 213, 225);
        $this->SetLineWidth(0.4);
        $this->Line(12, $this->GetY(), $pageWidth - 12, $this->GetY());
        $this->Ln(3);

        // Renderizar Cabecera de Tabla si existen encabezados configurados
        if (!empty($this->headers)) {
            $this->renderTableHeaders();
        }
    }

    public function Footer(): void
    {
        $this->SetY(-12);
        $this->SetFont('Helvetica', 'I', 8);
        $this->SetTextColor(100, 116, 139);
        
        // Izquierda: Firma discreta del sistema
        $this->SetX(12);
        $this->Cell(120, 8, $this->iconvText('Sistema de Gestión Documental Académica'), 0, 0, 'L');
        
        // Derecha: Conteo de páginas
        $this->Cell(0, 8, $this->iconvText('Página ') . $this->PageNo() . ' de {nb}', 0, 0, 'R');
    }

    public function renderTableHeaders(): void
    {
        $this->SetFont('Helvetica', 'B', 8);
        $this->SetFillColor(30, 58, 138); // Azul marino #1E3A8A
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(203, 213, 225);
        $this->SetLineWidth(0.2);

        foreach ($this->headers as $i => $h) {
            $w = $this->colWidths[$i] ?? 30;
            $this->Cell($w, 7, $this->iconvText($h), 1, 0, 'L', true);
        }
        $this->Ln();
    }

    public function buildReport(array $rows): void
    {
        $this->AddPage();
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(15, 23, 42);
        $this->SetDrawColor(226, 232, 240);

        if (empty($rows)) {
            $this->Ln(6);
            $this->SetFont('Helvetica', 'I', 9);
            $this->SetTextColor(100, 116, 139);
            $this->SetFillColor(248, 250, 252);
            $this->Cell(0, 12, $this->iconvText('No existen registros para el período seleccionado.'), 1, 1, 'C', true);
            return;
        }

        $fill = false;
        foreach ($rows as $row) {
            $this->SetFillColor($fill ? 248 : 255, $fill ? 250 : 255, $fill ? 252 : 255); // Alternancia de color #F8FAFC
            
            // Calcular altura máxima requerida para la fila
            $maxLines = 1;
            $cellTexts = array_values($row);
            foreach ($cellTexts as $i => $text) {
                $w = $this->colWidths[$i] ?? 30;
                $lines = $this->NbLines($w, (string)$text);
                if ($lines > $maxLines) {
                    $maxLines = $lines;
                }
            }
            $h = 5 * $maxLines;

            // Verificar si la fila cabe en la página actual
            if ($this->GetY() + $h > $this->PageBreakTrigger) {
                $this->AddPage($this->CurOrientation);
            }

            $startX = $this->GetX();
            $startY = $this->GetY();

            foreach ($cellTexts as $i => $text) {
                $w = $this->colWidths[$i] ?? 30;
                // Dibujar fondo de celda
                $this->Rect($startX, $startY, $w, $h, $fill ? 'DF' : 'D');
                // Escribir texto
                $this->SetXY($startX, $startY);
                $this->MultiCell($w, 5, $this->iconvText((string)$text), 0, 'L', false);
                $startX += $w;
            }

            $this->SetXY($this->lMargin, $startY + $h);
            $fill = !$fill;
        }
    }

    private function iconvText(string $str): string
    {
        return (string)@iconv('UTF-8', 'Windows-1252//TRANSLIT', $str);
    }

    private function NbLines(float $w, string $txt): int
    {
        $cw = &$this->CurrentFont['cw'];
        if ($w == 0) $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $s = $this->iconvText($s);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb - 1] == "\n") $nb--;
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") {
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
                continue;
            }
            if ($c == ' ') $sep = $i;
            $l += $cw[$c] ?? 500;
            if ($l > $wmax) {
                if ($sep == -1) {
                    if ($i == $j) $i++;
                } else $i = $sep + 1;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
            } else $i++;
        }
        return $nl;
    }
}
