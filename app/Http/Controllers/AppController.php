<?php

namespace App\Http\Controllers;

use App\Audit\AuditLogger;
use App\Deploy\DestroyApp;
use App\Enums\SourceType;
use App\Http\Requests\StoreAppRequest;
use App\Http\Requests\UpdateAppRequest;
use App\Models\App;
use App\Presenters\AppPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AppController extends Controller
{
    public function index(): Response
    {
        $apps = App::query()->with(['activeDeployment', 'latestDeployment'])->orderBy('name')->get();

        return Inertia::render('apps/index', [
            'apps' => $apps->map(fn (App $app) => AppPresenter::summary($app))->values(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('apps/create');
    }

    public function store(StoreAppRequest $request, AuditLogger $audit): RedirectResponse
    {
        $app = App::query()->create([
            ...$request->validated(),
            'slug' => App::generateSlug($request->string('name')->toString()),
            'source_type' => SourceType::Git,
        ]);

        $audit->record('app.created', null, ['app' => $app->slug], $app);

        return redirect()->route('apps.show', $app)->with('status', 'App created. Deploy it when you are ready.');
    }

    public function show(App $app): Response
    {
        $app->load(['activeDeployment', 'latestDeployment']);

        return Inertia::render('apps/show', [
            'app' => AppPresenter::settings($app),
            'deployments' => $app->deployments()->latest('number')->limit(15)->get()
                ->map(fn ($deployment) => AppPresenter::deployment($deployment))->values(),
            'env_keys' => $app->envVars()->orderBy('key')->pluck('key')->values(),
        ]);
    }

    public function update(UpdateAppRequest $request, App $app, AuditLogger $audit): RedirectResponse
    {
        $app->update($request->validated());
        $audit->record('app.updated', null, ['app' => $app->slug], $app);

        return back()->with('status', 'Settings saved. They apply from the next deployment.');
    }

    public function destroy(Request $request, App $app, DestroyApp $destroy, AuditLogger $audit): RedirectResponse
    {
        $request->validate(['confirm' => ['required', 'in:'.$app->slug]], [
            'confirm.in' => 'Type the app name exactly to confirm.',
        ]);

        $slug = $app->slug;
        $problems = $destroy($app);
        $audit->record('app.deleted', null, ['app' => $slug, 'cleanup_problems' => $problems]);

        $redirect = redirect()->route('apps.index')->with('status', "App {$slug} deleted.");

        return $problems === []
            ? $redirect
            : $redirect->with('warning', 'Some resources could not be removed: '.implode('; ', $problems));
    }
}
