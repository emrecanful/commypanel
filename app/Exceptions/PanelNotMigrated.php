<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class PanelNotMigrated extends RuntimeException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct(
            'The CommyPanel databases do not exist yet. Run: php artisan commypanel:migrate',
            0,
            $previous,
        );
    }
}
