<?php

declare(strict_types=1);

final class ProjectStatusTransitionException extends InvalidArgumentException
{
    public function __construct(string $message, private readonly int $httpStatus = 422)
    {
        parent::__construct($message);
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}
