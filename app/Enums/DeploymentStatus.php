<?php

namespace App\Enums;

enum DeploymentStatus: string
{
    case Queued = 'queued';
    case Building = 'building';
    case Starting = 'starting';
    case Active = 'active';
    case Superseded = 'superseded';
    case Failed = 'failed';
    case Stopped = 'stopped';

    public function isFinished(): bool
    {
        return in_array($this, [self::Active, self::Superseded, self::Failed, self::Stopped], true);
    }
}
