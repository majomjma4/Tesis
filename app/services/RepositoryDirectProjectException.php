<?php

declare(strict_types=1);

final class RepositoryDirectProjectException extends RuntimeException
{
    public function __construct(string $message, public readonly array $errors = [], public readonly int $status = 422, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
