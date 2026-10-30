<?php

namespace App\Host\Data;

use InvalidArgumentException;

final readonly class NginxSite
{
    /**
     * @param  list<string>  $domains
     */
    public function __construct(
        public string $name,
        public array $domains,
        public int $upstreamPort,
        public ?string $certificate = null,
    ) {
        ResourceName::assertValid($name);

        if ($certificate !== null) {
            ResourceName::assertValid($certificate);
        }

        if ($domains === []) {
            throw new InvalidArgumentException('An nginx site needs at least one domain.');
        }

        foreach ($domains as $domain) {
            if (! filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                throw new InvalidArgumentException("Invalid domain [{$domain}].");
            }
        }

        if ($upstreamPort < 1024 || $upstreamPort > 65535) {
            throw new InvalidArgumentException("Invalid upstream port [{$upstreamPort}].");
        }
    }
}
