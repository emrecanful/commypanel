<?php

namespace App\Deploy\Traffic;

use App\Models\Deployment;

interface TrafficSwitcher
{
    /**
     * Send the app's public traffic to the deployment's container. This is the
     * moment the new version goes live.
     */
    public function switchTo(Deployment $deployment): void;
}
