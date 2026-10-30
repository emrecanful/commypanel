<?php

namespace App\Enums;

/**
 * The steps a deployment goes through, in order. A rollback starts at Start,
 * because the image already exists.
 */
enum DeploymentStage: string
{
    case Fetch = 'fetch';
    case Build = 'build';
    case Start = 'start';
    case Health = 'health';
    case Switch = 'switch';
    case Done = 'done';
}
