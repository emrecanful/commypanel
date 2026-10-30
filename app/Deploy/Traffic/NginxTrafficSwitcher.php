<?php

namespace App\Deploy\Traffic;

use App\Models\Deployment;

class NginxTrafficSwitcher implements TrafficSwitcher
{
    public function __construct(private readonly AppRouting $routing) {}

    public function switchTo(Deployment $deployment): void
    {
        $this->routing->sync($deployment->app, $deployment);
    }
}
