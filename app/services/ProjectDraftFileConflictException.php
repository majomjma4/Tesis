<?php

declare(strict_types=1);

final class ProjectDraftFileConflictException extends InvalidArgumentException
{
    public function __construct(public readonly int $fileId, string $message)
    {
        parent::__construct($message);
    }
}
