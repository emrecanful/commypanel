<?php

namespace App\Host\Data;

final readonly class HostInfo
{
    /**
     * @param  array{0: float, 1: float, 2: float}  $load  1, 5 and 15 minute load averages
     * @param  list<array{name: string, state: string}>  $services
     */
    public function __construct(
        public string $hostname,
        public string $os,
        public string $kernel,
        public string $arch,
        public int $cpus,
        public int $uptimeSeconds,
        public array $load,
        public int $memTotalBytes,
        public int $memAvailableBytes,
        public int $diskTotalBytes,
        public int $diskFreeBytes,
        public array $services,
        public string $agentVersion = '',
    ) {}
}
