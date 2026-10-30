<?php

namespace App\Deploy\Health;

interface HealthChecker
{
    /**
     * Wait until the app answers on the given localhost port.
     */
    public function waitUntilHealthy(int $port, string $path, int $timeoutSeconds): bool;
}
