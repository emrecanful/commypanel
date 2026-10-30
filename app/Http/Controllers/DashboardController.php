<?php

namespace App\Http\Controllers;

use App\Enums\AppStatus;
use App\Host\Exceptions\HostOperationFailed;
use App\Host\HostDriver;
use App\Models\App;
use App\Models\Deployment;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(HostDriver $host): Response
    {
        $statuses = App::query()->with(['activeDeployment', 'latestDeployment'])->get()
            ->map(fn (App $app) => $app->status());

        return Inertia::render('dashboard', [
            'stats' => [
                'apps' => $statuses->count(),
                'running' => $statuses->filter(fn ($s) => $s === AppStatus::Running)->count(),
                'deploying' => $statuses->filter(fn ($s) => $s === AppStatus::Deploying)->count(),
                'failed' => $statuses->filter(fn ($s) => $s === AppStatus::Failed)->count(),
            ],
            'server' => $this->server($host),
            'recent' => Deployment::query()->with('app')->latest('id')->limit(8)->get()
                ->map(fn (Deployment $d) => [
                    'app_slug' => $d->app->slug,
                    'app_name' => $d->app->name,
                    'number' => $d->number,
                    'status' => $d->status->value,
                    'commit' => $d->commit_sha === null ? null : substr($d->commit_sha, 0, 8),
                    'created_at' => $d->created_at?->toIso8601String(),
                ])->values(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function server(HostDriver $host): array
    {
        try {
            $status = $host->systemStatus();
        } catch (HostOperationFailed $e) {
            return ['error' => $e->getMessage()];
        }

        return [
            'error' => null,
            'docker' => $status->dockerAvailable,
            'nginx' => $status->nginxAvailable,
            'disk_free_gb' => round($status->diskFreeBytes / 1024 ** 3, 1),
        ];
    }
}
