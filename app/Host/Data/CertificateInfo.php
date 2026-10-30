<?php

namespace App\Host\Data;

use DateTimeImmutable;

final readonly class CertificateInfo
{
    /**
     * @param  list<string>  $domains
     */
    public function __construct(
        public string $name,
        public array $domains,
        public DateTimeImmutable $notBefore,
        public DateTimeImmutable $notAfter,
        public string $issuer = '',
    ) {}
}
