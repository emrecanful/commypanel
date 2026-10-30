<?php

namespace App\Deploy\Traffic;

use App\Enums\CertificateStatus;
use App\Host\Data\NginxSite;
use App\Host\HostDriver;
use App\Models\App;
use App\Models\Deployment;

/**
 * Makes nginx match the database: an app with domains and a live deployment
 * gets a site that proxies to its container; anything else has none.
 */
class AppRouting
{
    public function __construct(private readonly HostDriver $host) {}

    /**
     * @param  Deployment|null  $target  the deployment about to go live, when it is not recorded as active yet
     */
    public function sync(App $app, ?Deployment $target = null): void
    {
        $live = $target ?? $app->activeDeployment()->first();
        $domains = array_values(array_map(
            fn (mixed $name): string => (string) $name,
            $app->domains()->orderBy('name')->pluck('name')->all(),
        ));

        if ($live === null || $live->host_port === null || $domains === []) {
            $this->host->removeNginxSite($app->slug);

            return;
        }

        $certificate = $app->certificate()->first();
        $https = $certificate !== null && $certificate->status === CertificateStatus::Active && $certificate->isUsable();

        $this->host->applyNginxSite(new NginxSite($app->slug, $domains, $live->host_port, $https ? $app->slug : null));
    }
}
