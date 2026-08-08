<?php

declare(strict_types=1);

/** Selecciona el documento de descarga rápida sin consultar ni transformar archivos. */
final class PrimaryRecordDocumentService
{
    public function select(array $documents): ?array
    {
        $downloadable = array_values(array_filter(
            $documents,
            static fn (mixed $document): bool => is_array($document)
                && empty($document['is_package'])
                && mb_strtolower((string) ($document['extension'] ?? ''), 'UTF-8') !== 'zip'
                && (!array_key_exists('available', $document) || !empty($document['available']))
                && trim((string) ($document['download_url'] ?? '')) !== ''
        ));

        if ($downloadable === []) return null;

        return current(array_filter(
            $downloadable,
            static fn (array $document): bool => !empty($document['is_presentation'])
        )) ?: $downloadable[0];
    }
}
