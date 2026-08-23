<?php

declare(strict_types=1);

/** Valores persistidos que identifican cómo llegó un proyecto al Repository. */
final class ProjectPublicationOrigin
{
    public const WORKFLOW = 'workflow';
    public const DIRECT_REPOSITORY = 'direct_repository';

    public static function isValid(string $origin): bool
    {
        return in_array($origin, [self::WORKFLOW, self::DIRECT_REPOSITORY], true);
    }
}
