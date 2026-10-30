<?php

namespace App\Presenters;

use App\Models\App;
use App\Models\Deployment;

/**
 * Shapes models into the arrays the React pages receive. Secrets (env values,
 * private deploy keys) are never included.
 */
class AppPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function summary(App $app): array
    {
        $latest = $app->latestDeployment;

        return [
            'slug' => $app->slug,
            'name' => $app->name,
            'stack' => $app->stack?->label(),
            'status' => $app->status()->value,
            'git_url' => $app->git_url,
            'git_branch' => $app->git_branch,
            'active_release' => $app->activeDeployment?->number,
            'last_deployed_at' => $latest?->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function settings(App $app): array
    {
        return [
            ...self::summary($app),
            'container_port' => $app->container_port,
            'health_path' => $app->health_path,
            'memory_limit_mb' => $app->memory_limit_mb,
            'cpu_limit' => $app->cpu_limit === null ? null : (float) $app->cpu_limit,
            'deploy_key_public' => $app->deploy_key_public,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function deployment(Deployment $deployment, bool $withLog = false): array
    {
        return [
            'number' => $deployment->number,
            'status' => $deployment->status->value,
            'finished' => $deployment->status->isFinished(),
            'commit' => $deployment->commit_sha === null ? null : substr($deployment->commit_sha, 0, 8),
            'is_rollback' => $deployment->rolled_back_from_id !== null,
            'can_roll_back' => $deployment->image_tag !== null && $deployment->image_available,
            'error' => $deployment->error,
            'created_at' => $deployment->created_at?->toIso8601String(),
            'finished_at' => $deployment->finished_at?->toIso8601String(),
            ...($withLog ? ['log' => $deployment->log ?? ''] : []),
        ];
    }
}
