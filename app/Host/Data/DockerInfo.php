<?php

namespace App\Host\Data;

/**
 * The Docker daemon as the agent sees it. An unreachable daemon is a state
 * (available: false with a reason), not an error, so pages can show it.
 */
final readonly class DockerInfo
{
    public function __construct(
        public bool $available,
        public ?string $error = null,
        public string $serverVersion = '',
        public string $apiVersion = '',
        public string $os = '',
        public string $arch = '',
        public string $kernelVersion = '',
        public string $storageDriver = '',
        public string $cgroupDriver = '',
        public int $containers = 0,
        public int $running = 0,
        public int $paused = 0,
        public int $stopped = 0,
        public int $images = 0,
        public int $cpus = 0,
        public int $memTotalBytes = 0,
    ) {}
}
