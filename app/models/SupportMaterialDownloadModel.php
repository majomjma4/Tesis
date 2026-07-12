<?php

declare(strict_types=1);

final class SupportMaterialDownloadModel
{
    private const SESSION_KEY = 'support_material_download_increments';

    public function getTotal(int $materialId, int $baseTotal): int
    {
        return $baseTotal + (int) ($_SESSION[self::SESSION_KEY][$materialId] ?? 0);
    }

    public function increment(int $materialId): void
    {
        $_SESSION[self::SESSION_KEY][$materialId] = (int) ($_SESSION[self::SESSION_KEY][$materialId] ?? 0) + 1;
    }
}
