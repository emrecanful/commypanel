<?php

namespace App\Host\Data;

final readonly class NginxStatus
{
    /**
     * @param  list<array{name: string, domains: list<string>, https: bool, modified: int}>  $sites  the configs CommyPanel wrote
     */
    public function __construct(
        public bool $available,
        public bool $running,
        public string $version,
        public bool $configOk,
        public string $configOutput,
        public array $sites,
    ) {}
}
