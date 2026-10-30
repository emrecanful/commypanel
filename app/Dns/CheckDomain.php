<?php

namespace App\Dns;

use App\Audit\AuditLogger;
use App\Enums\DomainStatus;
use App\Models\Domain;
use App\Tls\TlsPlanner;

class CheckDomain
{
    public function __construct(
        private readonly DomainVerifier $verifier,
        private readonly AuditLogger $audit,
        private readonly TlsPlanner $tls,
    ) {}

    public function __invoke(Domain $domain): Domain
    {
        $before = $domain->status;
        $check = $this->verifier->check($domain->name);

        $domain->forceFill([
            'status' => $check->status,
            'last_check' => $check->toArray(),
            'last_checked_at' => now(),
            'verified_at' => $check->status === DomainStatus::Verified ? ($domain->verified_at ?? now()) : null,
        ])->save();

        if ($before !== DomainStatus::Verified && $check->status === DomainStatus::Verified) {
            $this->audit->record('domain.verified', null, ['domain' => $domain->name], $domain);

            if ($domain->app !== null) {
                $this->tls->ensure($domain->app);
            }
        }

        return $domain;
    }
}
