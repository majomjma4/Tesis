<?php

declare(strict_types=1);

final class ThesisDefenseException extends InvalidArgumentException
{
    public function __construct(string $message, private int $status = 422)
    {
        parent::__construct($message);
    }

    public function httpStatus(): int
    {
        return $this->status;
    }
}
