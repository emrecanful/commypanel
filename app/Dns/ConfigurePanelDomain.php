<?php

namespace App\Dns;

use App\Audit\AuditLogger;
use App\Jobs\IssuePanelCertificateJob;
use App\Models\Domain;
use App\Tls\HttpsSettings;
use App\Tls\IssuePanelCertificate;
use App\Tls\PanelRouting;
use Illuminate\Validation\ValidationException;

/**
 * Chooses the domain the panel is served on, checks its DNS and queues the
 * certificate. Serving on the domain begins once the certificate exists.
 */
class ConfigurePanelDomain
{
    public function __construct(
        private readonly PanelDomain $panel,
        private readonly DomainVerifier $verifier,
        private readonly HttpsSettings $https,
        private readonly IssuePanelCertificate $issue,
        private readonly PanelRouting $routing,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws ValidationException
     */
    public function set(string $input): void
    {
        $name = DomainName::normalize($input);

        if ($name === null) {
            throw ValidationException::withMessages(['domain' => 'Enter a domain such as panel.example.com, without http://, paths or wildcards.']);
        }

        if (Domain::query()->where('name', $name)->exists()) {
            throw ValidationException::withMessages(['domain' => "{$name} is already connected to an app. Use a different name for the panel."]);
        }

        $this->panel->set($name);
        $this->audit->record('panel.domain_set', null, ['domain' => $name]);
        $this->check();
    }

    public function check(bool $retry = false): void
    {
        $name = $this->panel->name();

        if ($name === null) {
            return;
        }

        $result = $this->verifier->check($name);
        $this->panel->recordDns($result->status->value, $result->toArray());

        $this->requestCertificate($retry);
    }

    /**
     * Queue issuance when everything it needs is in place.
     *
     * @param  bool  $retry  the user asked again, so a recent failure does not hold it back
     */
    public function requestCertificate(bool $retry = false): void
    {
        if (! $this->https->enabled() || ! $this->panel->dnsVerified()) {
            return;
        }

        $again = $retry && ! $this->panel->certificateUsable() && $this->panel->certificateStatus() !== 'issuing';

        if ($again || $this->issue->needed()) {
            IssuePanelCertificateJob::dispatch();
        }
    }

    public function clear(): void
    {
        $this->panel->set(null);
        $this->routing->sync();
        $this->audit->record('panel.domain_removed');
    }
}
