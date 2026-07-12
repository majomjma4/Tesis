$ErrorActionPreference = 'Stop'

$storagePath = Join-Path $PSScriptRoot '..\storage\repository'
$storagePath = [System.IO.Path]::GetFullPath($storagePath)
[System.IO.Directory]::CreateDirectory($storagePath) | Out-Null

$codigoFuente = 'C' + [char]0x00F3 + 'digo Fuente'
$carpetaVacia = 'Carpeta Vac' + [char]0x00ED + 'a'
$version = 'versi' + [char]0x00F3 + 'n'
$pngBytes = [Convert]::FromBase64String('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2nQAAAABJRU5ErkJggg==')

function New-PdfBytes {
    $ascii = [System.Text.Encoding]::ASCII
    $content = "BT`n/F1 18 Tf`n72 760 Td`n(Documento de prueba del repositorio) Tj`n0 -28 Td`n/F1 11 Tf`n(Vista previa PDF disponible correctamente.) Tj`nET"
    $objects = @(
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
        ("<< /Length {0} >>`nstream`n{1}`nendstream" -f $ascii.GetByteCount($content), $content),
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'
    )
    $pdf = "%PDF-1.4`n"
    $offsets = @()
    for ($index = 0; $index -lt $objects.Count; $index++) {
        $offsets += $ascii.GetByteCount($pdf)
        $pdf += ("{0} 0 obj`n{1}`nendobj`n" -f ($index + 1), $objects[$index])
    }
    $xrefOffset = $ascii.GetByteCount($pdf)
    $pdf += "xref`n0 6`n0000000000 65535 f `n"
    foreach ($offset in $offsets) {
        $pdf += ($offset.ToString('0000000000') + " 00000 n `n")
    }
    $pdf += "trailer`n<< /Size 6 /Root 1 0 R >>`nstartxref`n$xrefOffset`n%%EOF`n"
    return ,$ascii.GetBytes($pdf)
}

$pdfBytes = New-PdfBytes

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

function New-DocxBytes {
    $memory = New-Object System.IO.MemoryStream
    $docx = New-Object System.IO.Compression.ZipArchive($memory, [System.IO.Compression.ZipArchiveMode]::Create, $true)
    try {
        $docxEntries = [ordered]@{
            '[Content_Types].xml' = '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>'
            '_rels/.rels' = '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>'
            'word/document.xml' = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>Informe de proyecto</w:t></w:r></w:p><w:p><w:r><w:t>Este documento DOCX permite comprobar la vista previa segura del repositorio.</w:t></w:r></w:p><w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr></w:pPr><w:r><w:t>Primer objetivo acad&#x00E9;mico</w:t></w:r></w:p><w:tbl><w:tr><w:tc><w:p><w:r><w:t>Tecnolog&#x00ED;a</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>PHP</w:t></w:r></w:p></w:tc></w:tr><w:tr><w:tc><w:p><w:r><w:t>Arquitectura</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>MVC</w:t></w:r></w:p></w:tc></w:tr></w:tbl><w:sectPr/></w:body></w:document>'
        }
        foreach ($docxEntryName in $docxEntries.Keys) {
            $docxEntry = $docx.CreateEntry($docxEntryName)
            $docxStream = $docxEntry.Open()
            $docxWriter = New-Object System.IO.StreamWriter($docxStream, [System.Text.UTF8Encoding]::new($false))
            try { $docxWriter.Write($docxEntries[$docxEntryName]) } finally { $docxWriter.Dispose() }
        }
    } finally {
        $docx.Dispose()
    }
    $bytes = $memory.ToArray()
    $memory.Dispose()
    return ,$bytes
}

$docxBytes = New-DocxBytes

$entries = [ordered]@{
    "$codigoFuente/" = $null
    "$codigoFuente/app/" = $null
    "$codigoFuente/app/Controllers/" = $null
    "$codigoFuente/app/Controllers/InventoryController.php" = "<?php`n`ndeclare(strict_types=1);`n`nfinal class InventoryController {}`n"
    "$codigoFuente/public/" = $null
    "$codigoFuente/public/app.js" = "console.log('Proyecto');`n"
    'Informe/' = $null
    'Informe/Informe_Final.txt' = "Informe academico de demostracion.`nContenido utilizado para probar el explorador ZIP.`n"
    "Informe/Anexo ($version-final).txt" = "Anexo con espacios, parentesis, tilde y guion.`n"
    'Base de Datos/' = $null
    'Base de Datos/esquema.sql' = "CREATE TABLE productos (id INT PRIMARY KEY, nombre VARCHAR(120));`n"
    "$carpetaVacia/" = $null
    'README.md' = "# Proyecto`n`nArchivo de demostracion para el repositorio institucional.`n"
    'config.json' = '{"name":"Proyecto","version":1}'
    'respaldo.zip' = 'nested-archive-placeholder'
    'Vista Previa/' = $null
    'Vista Previa/Documento.pdf' = $pdfBytes
    'Vista Previa/Informe.docx' = $docxBytes
    'Vista Previa/Documento_danado.docx' = [byte[]](0x50, 0x4B, 0x03, 0x04, 0x00, 0x00, 0x00, 0x00)
    'Vista Previa/imagen.png' = $pngBytes
    'Vista Previa/datos.json' = '{"proyecto":"Repositorio","activo":true,"items":[1,2,3]}'
    'Vista Previa/codigo.php' = "<?php`n`ndeclare(strict_types=1);`n`necho 'Vista segura';`n"
    'Vista Previa/texto.txt' = "Archivo de texto para vista previa.`nSegunda linea.`n"
    'Vista Previa/vacio.txt' = ''
    'Vista Previa/grande.txt' = ('A' * 530000)
    'Vista Previa/instalador.exe' = 'binary-placeholder'
    'Vista Previa/vector.svg' = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'
}

1..5 | ForEach-Object {
    $zipPath = Join-Path $storagePath ("project_{0}.zip" -f $_)
    if (Test-Path -LiteralPath $zipPath) {
        Remove-Item -LiteralPath $zipPath -Force
    }

    $stream = [System.IO.File]::Open($zipPath, [System.IO.FileMode]::CreateNew)
    try {
        $archive = New-Object System.IO.Compression.ZipArchive($stream, [System.IO.Compression.ZipArchiveMode]::Create)
        try {
            foreach ($entryName in $entries.Keys) {
                $entry = $archive.CreateEntry($entryName)
                if ($null -eq $entries[$entryName]) {
                    continue
                }
                $entryStream = $entry.Open()
                try {
                    if ($entries[$entryName] -is [byte[]]) {
                        $entryStream.Write($entries[$entryName], 0, $entries[$entryName].Length)
                    } else {
                        $writer = New-Object System.IO.StreamWriter($entryStream, [System.Text.UTF8Encoding]::new($false))
                        $writer.Write($entries[$entryName])
                        $writer.Flush()
                    }
                } finally {
                    if ($null -ne $writer) { $writer.Dispose(); $writer = $null }
                    else { $entryStream.Dispose() }
                }
            }
        } finally {
            $archive.Dispose()
        }
    } finally {
        $stream.Dispose()
    }
}

$emptyZipPath = Join-Path $storagePath 'empty.zip'
if (Test-Path -LiteralPath $emptyZipPath) { Remove-Item -LiteralPath $emptyZipPath -Force }
$emptyStream = [System.IO.File]::Open($emptyZipPath, [System.IO.FileMode]::CreateNew)
try {
    $emptyArchive = New-Object System.IO.Compression.ZipArchive($emptyStream, [System.IO.Compression.ZipArchiveMode]::Create)
    $emptyArchive.Dispose()
} finally {
    $emptyStream.Dispose()
}

[System.IO.File]::WriteAllText((Join-Path $storagePath 'damaged.zip'), 'not-a-valid-zip', [System.Text.UTF8Encoding]::new($false))

Write-Output "Fixtures ZIP creados en $storagePath"
