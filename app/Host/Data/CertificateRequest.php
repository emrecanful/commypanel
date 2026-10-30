<?php

namespace App\Host\Data;

use InvalidArgumentException;

final readonly class CertificateRequest
{
    /**
     * @param  list<string>  $domains
     */
    public function __construct(
        public string $name,
        public array $domains,
        public ?string $email = null,
        public bool $staging = false,
    ) {
        ResourceName::assertValid($name);

        if ($domains === []) {
            throw new InvalidArgumentException('A certificate needs at least one domain.');
        }
    }
}
