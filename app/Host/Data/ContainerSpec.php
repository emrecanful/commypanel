<?php

namespace App\Host\Data;

use InvalidArgumentException;

final readonly class ContainerSpec
{
    /**
     * @param  array<string, string>  $env
     */
    public function __construct(
        public string $name,
        public string $image,
        public int $hostPort,
        public int $containerPort,
        public array $env = [],
        public ?int $memoryLimitMb = null,
        public ?float $cpuLimit = null,
    ) {
        ResourceName::assertValid($name);

        if ($hostPort < 1024 || $hostPort > 65535 || $containerPort < 1 || $containerPort > 65535) {
            throw new InvalidArgumentException('Invalid container port mapping.');
        }
    }
}
