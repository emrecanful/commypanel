<?php

namespace App\Enums;

enum SourceType: string
{
    case Git = 'git';
    case Upload = 'upload';
}
