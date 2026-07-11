<?php

declare(strict_types=1);

/**
 * Contador temporal de descargas completas respaldado por sesión.
 * Se reemplazará por persistencia global en MySQL.
 */
final class DownloadModel
{
    private const SESSION_KEY = 'repository_download_increments';

    public function getTotal(int $projectId, int $baseDownloads): int
    {
        return $baseDownloads + (int) ($_SESSION[self::SESSION_KEY][$projectId] ?? 0);
    }

    public function increment(int $projectId): void
    {
        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }

        $_SESSION[self::SESSION_KEY][$projectId] = (int) ($_SESSION[self::SESSION_KEY][$projectId] ?? 0) + 1;
    }
}
