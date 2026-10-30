<?php

namespace Tests\Support;

use App\Deploy\Traffic\TrafficSwitcher;
use App\Models\Deployment;

class RecordingTrafficSwitcher implements TrafficSwitcher
{
    /** @var list<int> */
    public array $switched = [];

    public function switchTo(Deployment $deployment): void
    {
        $this->switched[] = $deployment->id;
    }
}
