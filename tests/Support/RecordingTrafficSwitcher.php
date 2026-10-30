<?php

namespace Tests\Support;

use App\Deploy\Traffic\TrafficSwitcher;
use App\Models\Deployment;
use Throwable;

class RecordingTrafficSwitcher implements TrafficSwitcher
{
    /** @var list<int> */
    public array $switched = [];

    /** When set, the next switch fails with this exception. */
    public ?Throwable $throw = null;

    public function switchTo(Deployment $deployment): void
    {
        if ($this->throw !== null) {
            throw $this->throw;
        }

        $this->switched[] = $deployment->id;
    }
}
