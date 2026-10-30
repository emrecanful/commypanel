<?php

namespace App\Deploy\Traffic;

use App\Models\Deployment;

/**
 * Placeholder until domains exist (Phase 3): the container runs on its
 * localhost port, with nothing routed to it yet.
 */
class NullTrafficSwitcher implements TrafficSwitcher
{
    public function switchTo(Deployment $deployment): void {}
}
