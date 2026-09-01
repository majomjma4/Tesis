<?php

declare(strict_types=1);

final class StudentProjectSubmissionException extends RuntimeException
{
    public function __construct(string $message, private readonly int $status = 422, private readonly array $data = [])
    {
        parent::__construct($message);
    }

    public function httpStatus(): int
    {
        return $this->status;
    }

    public function data(): array
    {
        return $this->data;
    }
}
