<?php

namespace App\Deploy;

use App\Host\Exceptions\HostOperationFailed;
use App\Host\HostDriver;
use App\Models\App;

/**
 * Removes an app's containers and images, then the app itself.
 */
class DestroyApp
{
    public function __construct(private readonly HostDriver $host) {}

    /**
     * @return list<string> problems cleaning up the host; the app is deleted regardless
     */
    public function __invoke(App $app): array
    {
        $problems = [];
        $deployments = $app->deployments()->get();

        foreach ($deployments->pluck('container_name')->filter()->unique() as $container) {
            try {
                $this->host->removeContainer($container);
            } catch (HostOperationFailed $e) {
                $problems[] = "Container {$container}: {$e->getMessage()}";
            }
        }

        foreach ($deployments->where('image_available', true)->pluck('image_tag')->filter()->unique() as $tag) {
            try {
                $this->host->removeImage($tag);
            } catch (HostOperationFailed $e) {
                $problems[] = "Image {$tag}: {$e->getMessage()}";
            }
        }

        $app->delete();

        return $problems;
    }
}
