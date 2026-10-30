<?php

namespace App\Tls;

use App\Enums\CertificateStatus;
use App\Enums\DomainStatus;
use App\Jobs\IssueAppCertificate;
use App\Models\App;

/**
 * Decides whether an app needs a (new) certificate and queues one. Called
 * whenever something that affects the answer changes, and daily for renewals.
 */
class TlsPlanner
{
    public function __construct(private readonly HttpsSettings $https) {}

    /**
     * Domains that point at this server, and so can pass validation.
     *
     * @return list<string>
     */
    public function eligibleDomains(App $app): array
    {
        return array_values(array_map(
            fn (mixed $name): string => (string) $name,
            $app->domains()->where('status', DomainStatus::Verified)->orderBy('name')->pluck('name')->all(),
        ));
    }

    public function ensure(App $app): bool
    {
        if (! $this->https->enabled() || ! $this->needsIssue($app)) {
            return false;
        }

        IssueAppCertificate::dispatch($app->id);

        return true;
    }

    public function needsIssue(App $app): bool
    {
        $domains = $this->eligibleDomains($app);

        if ($domains === []) {
            return false;
        }

        $cert = $app->certificate()->first();

        if ($cert === null) {
            return true;
        }

        // An attempt is already running (or the worker died a while ago).
        if ($cert->status === CertificateStatus::Issuing && $cert->last_attempt_at?->isAfter(now()->subMinutes(15))) {
            return false;
        }

        if ($cert->status === CertificateStatus::Failed && $cert->last_attempt_at?->isAfter(now()->subHours((int) config('commypanel.https.retry_after_hours')))) {
            return false;
        }

        // A domain that is not on the certificate yet.
        if (array_diff($domains, $cert->domains ?? []) !== []) {
            return true;
        }

        if ($cert->status === CertificateStatus::Failed) {
            return true;
        }

        return $cert->expires_at === null
            || $cert->expires_at->isBefore(now()->addDays((int) config('commypanel.https.renew_before_days')));
    }
}
