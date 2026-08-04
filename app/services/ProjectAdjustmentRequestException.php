<?php

declare(strict_types=1);

final class ProjectAdjustmentRequestException extends RuntimeException
{
    public function __construct(string $message, private readonly int $status = 422)
    {
        parent::__construct($message);
    }

    public function httpStatus(): int { return $this->status; }
}
