<?php

namespace App\Host\Data;

final readonly class ContainerInfo
{
    public function __construct(
        public string $name,
        public string $image,
        public string $state,
        public string $status,
        public int $created = 0,
    ) {}
}
