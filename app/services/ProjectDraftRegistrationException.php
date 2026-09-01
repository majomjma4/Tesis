<?php

declare(strict_types=1);

final class ProjectDraftRegistrationException extends RuntimeException
{
    public function __construct(string $message, public readonly array $errors = [])
    {
        parent::__construct($message);
    }
}
