<?php

namespace App\Enums;

enum AppStatus: string
{
    case New = 'new';
    case Deploying = 'deploying';
    case Running = 'running';
    case Failed = 'failed';
}
