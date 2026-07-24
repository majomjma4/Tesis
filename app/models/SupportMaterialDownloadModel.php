<?php

declare(strict_types=1);

final class SupportMaterialDownloadModel
{
    public function getTotal(int $materialId, int $baseTotal): int
    {
        return $baseTotal;
    }

    public function increment(int $materialId): void
    {
        (new SupportMaterialModel())->incrementDownloads($materialId);
    }
}
