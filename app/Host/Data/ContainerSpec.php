<?php

namespace App\Host\Data;

use InvalidArgumentException;

final readonly class ContainerSpec
{
    /**
     * @param  array<string, string>  $env
     * @param  list<Volume>  $volumes
     */
    public function __construct(
        public string $name,
        public string $image,
        public int $hostPort,
        public int $containerPort,
        public array $env = [],
        public ?int $memoryLimitMb = null,
        public ?float $cpuLimit = null,
        public array $volumes = [],
        public ?string $network = null,
    ) {
        ResourceName::assertValid($name);

        foreach (array_keys($env) as $key) {
            if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', (string) $key)) {
                throw new InvalidArgumentException("Invalid environment variable name [{$key}].");
            }
        }

        if ($network !== null) {
            ResourceName::assertValid($network);
        }

        if ($hostPort < 1024 || $hostPort > 65535 || $containerPort < 1 || $containerPort > 65535) {
            throw new InvalidArgumentException('Invalid container port mapping.');
        }
    }
}
