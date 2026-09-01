<?php

declare(strict_types=1);

final class CalendarEventException extends RuntimeException
{
    public function __construct(string $message, private readonly int $httpStatus)
    {
        parent::__construct($message);
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}
