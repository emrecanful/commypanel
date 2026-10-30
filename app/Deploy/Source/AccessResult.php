<?php

namespace App\Deploy\Source;

final readonly class AccessResult
{
    /**
     * @param  string|null  $sshUrl  the address to use with a deploy key
     * @param  list<string>  $branches
     */
    public function __construct(
        public AccessState $state,
        public string $message,
        public GitProvider $provider,
        public ?string $sshUrl = null,
        public array $branches = [],
    ) {}
}
