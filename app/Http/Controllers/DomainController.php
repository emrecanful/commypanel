<?php

namespace App\Http\Controllers;

use App\Audit\AuditLogger;
use App\Deploy\Traffic\AppRouting;
use App\Dns\CheckDomain;
use App\Dns\ServerAddress;
use App\Models\Domain;
use App\Presenters\AppPresenter;
use App\Tls\HttpsSettings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DomainController extends Controller
{
    public function index(ServerAddress $server, HttpsSettings $https): Response
    {
        return Inertia::render('domains/index', [
            'server' => ['ipv4' => $server->ipv4(), 'ipv6' => $server->ipv6()],
            'https' => ['enabled' => $https->enabled(), 'email' => $https->email(), 'staging' => $https->staging()],
            'domains' => Domain::query()->with('app')->orderBy('name')->get()
                ->map(fn (Domain $domain) => AppPresenter::domain($domain))->values(),
        ]);
    }

    public function check(Domain $domain, CheckDomain $check): RedirectResponse
    {
        $check($domain);

        return back();
    }

    public function destroy(Domain $domain, AppRouting $routing, AuditLogger $audit): RedirectResponse
    {
        $app = $domain->app;
        $name = $domain->name;

        // Domains that redirected here serve the app themselves from now on.
        Domain::query()->where('redirect_to', $name)->update(['redirect_to' => null]);
        $domain->delete();
        $audit->record('domain.removed', null, ['domain' => $name]);

        if ($app !== null) {
            $routing->sync($app);
        }

        return back()->with('status', "{$name} removed.");
    }
}
