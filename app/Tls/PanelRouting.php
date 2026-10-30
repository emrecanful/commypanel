<?php

namespace App\Tls;

use App\Dns\PanelDomain;
use App\Host\Data\NginxSite;
use App\Host\HostDriver;

/**
 * Makes nginx serve the panel on its own domain (443, with the certificate)
 * through the agent. The site proxies to the panel's loopback-only listener;
 * until a usable certificate exists there is no site, so the panel is never
 * offered over plain HTTP on the public name.
 */
class PanelRouting
{
    public function __construct(private readonly HostDriver $host, private readonly PanelDomain $panel) {}

    public function sync(): void
    {
        $domain = $this->panel->name();

        if ($domain === null || ! $this->panel->certificateUsable()) {
            $this->host->removeNginxSite(PanelDomain::SLUG);

            return;
        }

        $this->host->applyNginxSite(new NginxSite(
            PanelDomain::SLUG,
            [$domain],
            (int) config('commypanel.panel_internal_port'),
            PanelDomain::SLUG,
        ));
    }
}
