<?php

namespace App\Enums;

enum AppStatus: string
{
    /** The creation wizard has not been finished. */
    case Setup = 'setup';
    case New = 'new';
    case Deploying = 'deploying';
    case Running = 'running';
    case Failed = 'failed';
}
