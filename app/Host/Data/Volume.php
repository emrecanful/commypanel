<?php

namespace App\Host\Data;

use InvalidArgumentException;

final readonly class Volume
{
    public function __construct(
        public string $source,
        public string $target,
        public bool $readOnly = false,
    ) {
        if (! str_starts_with($source, '/') || ! str_starts_with($target, '/')) {
            throw new InvalidArgumentException('Volume paths must be absolute.');
        }
    }
}
