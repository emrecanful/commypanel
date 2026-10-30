<?php

namespace App\Http\Controllers;

use App\Audit\AuditLogger;
use App\Deploy\Traffic\AppRouting;
use App\Dns\CheckDomain;
use App\Dns\DomainName;
use App\Dns\ServerAddress;
use App\Models\App;
use App\Models\Domain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DomainController extends Controller
{
    public function index(ServerAddress $server): Response
    {
        return Inertia::render('domains/index', [
            'server' => ['ipv4' => $server->ipv4(), 'ipv6' => $server->ipv6()],
            'domains' => Domain::query()->with('app')->orderBy('name')->get()->map(fn (Domain $domain) => [
                'id' => $domain->id,
                'name' => $domain->name,
                'status' => $domain->status->value,
                'status_label' => $domain->status->label(),
                'app' => $domain->app?->slug,
                'found' => ['a' => $domain->last_check['a'] ?? [], 'aaaa' => $domain->last_check['aaaa'] ?? []],
                'problems' => $domain->last_check['problems'] ?? [],
                'last_checked_at' => $domain->last_checked_at?->toIso8601String(),
            ])->values(),
            'apps' => App::query()->orderBy('name')->get(['slug', 'name'])->map(fn (App $app) => [
                'slug' => $app->slug,
                'name' => $app->name,
            ])->values(),
        ]);
    }

    public function store(Request $request, CheckDomain $check, AppRouting $routing, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:300'],
            'app' => ['nullable', 'string', 'exists:apps,slug'],
        ]);

        $name = DomainName::normalize($data['name']);

        if ($name === null) {
            throw ValidationException::withMessages(['name' => 'Enter a domain such as app.example.com, without http://, paths or wildcards.']);
        }

        if (Domain::query()->where('name', $name)->exists()) {
            throw ValidationException::withMessages(['name' => 'That domain has already been added.']);
        }

        $app = isset($data['app']) ? App::query()->where('slug', $data['app'])->first() : null;
        $domain = Domain::query()->create(['name' => $name, 'app_id' => $app?->id]);

        $audit->record('domain.added', null, ['domain' => $name, 'app' => $app?->slug], $domain);
        $check($domain);

        if ($app !== null) {
            $routing->sync($app);
        }

        return back()->with('status', "{$name} added. Create the DNS records below, then check again.");
    }

    public function update(Request $request, Domain $domain, AppRouting $routing, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['app' => ['nullable', 'string', 'exists:apps,slug']]);

        $previous = $domain->app;
        $next = isset($data['app']) ? App::query()->where('slug', $data['app'])->first() : null;

        $domain->update(['app_id' => $next?->id]);
        $audit->record('domain.assigned', null, ['domain' => $domain->name, 'from' => $previous?->slug, 'to' => $next?->slug], $domain);

        foreach (array_filter([$previous, $next]) as $app) {
            $routing->sync($app);
        }

        return back()->with('status', $next === null ? "{$domain->name} is no longer assigned to an app." : "{$domain->name} now points to {$next->name}.");
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

        $domain->delete();
        $audit->record('domain.removed', null, ['domain' => $name]);

        if ($app !== null) {
            $routing->sync($app);
        }

        return back()->with('status', "{$name} removed.");
    }
}
