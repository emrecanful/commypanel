<?php

namespace App\Dns;

use App\Enums\DomainStatus;

final readonly class DomainCheck
{
    /**
     * @param  list<string>  $a  IPv4 addresses found
     * @param  list<string>  $aaaa  IPv6 addresses found
     * @param  list<string>  $problems  explanations for the user
     */
    public function __construct(
        public DomainStatus $status,
        public array $a = [],
        public array $aaaa = [],
        public array $problems = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['a' => $this->a, 'aaaa' => $this->aaaa, 'problems' => $this->problems];
    }
}
