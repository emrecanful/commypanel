<?php

namespace App\Host\Data;

use InvalidArgumentException;

final class ResourceName
{
    private const PATTERN = '/^[a-z0-9][a-z0-9-]{0,61}[a-z0-9]$/';

    public static function assertValid(string $name): void
    {
        if (! preg_match(self::PATTERN, $name)) {
            throw new InvalidArgumentException("Invalid resource name [{$name}].");
        }
    }
}
