<?php

namespace App\Http\Controllers;

use App\Audit\AuditLogger;
use App\Host\Data\ContainerInfo;
use App\Host\Data\ImageInfo;
use App\Host\Data\ResourceName;
use App\Host\Exceptions\HostOperationFailed;
use App\Host\HostDriver;
use App\Models\Deployment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class ServerDockerController extends Controller
{
    public function index(HostDriver $host): Response
    {
        try {
            $info = $host->dockerInfo();
            $containers = $info->available ? $host->listContainers() : [];
            $images = $info->available ? $host->listImages() : [];
            $error = null;
        } catch (HostOperationFailed $e) {
            $info = null;
            $containers = $images = [];
            $error = $e->getMessage();
        }

        // Which app each container belongs to, from the deployments that started them.
        $owners = Deployment::query()->with('app:id,slug,name')
            ->whereIn('container_name', array_map(fn (ContainerInfo $c) => $c->name, $containers))
            ->get()->keyBy('container_name');

        return Inertia::render('server/docker', [
            'error' => $error,
            'docker' => $info === null ? null : [
                'available' => $info->available,
                'error' => $info->error,
                'server_version' => $info->serverVersion,
                'api_version' => $info->apiVersion,
                'os' => $info->os,
                'arch' => $info->arch,
                'kernel_version' => $info->kernelVersion,
                'storage_driver' => $info->storageDriver,
                'cgroup_driver' => $info->cgroupDriver,
                'containers' => $info->containers,
                'running' => $info->running,
                'paused' => $info->paused,
                'stopped' => $info->stopped,
                'images' => $info->images,
                'cpus' => $info->cpus,
                'mem_total' => $info->memTotalBytes,
            ],
            'containers' => array_map(fn (ContainerInfo $c) => [
                'name' => $c->name,
                'image' => $c->image,
                'state' => $c->state,
                'status' => $c->status,
                'created' => $c->created,
                'app' => $owners->get($c->name)?->app?->only(['slug', 'name']),
                'release' => $owners->get($c->name)?->number,
            ], $containers),
            'images' => array_map(fn (ImageInfo $i) => [
                'tags' => $i->tags,
                'size' => $i->sizeBytes,
                'created' => $i->created,
            ], $images),
        ]);
    }

    public function prune(HostDriver $host, AuditLogger $audit): RedirectResponse
    {
        try {
            $reclaimed = $host->pruneDanglingImages();
        } catch (HostOperationFailed $e) {
            return back()->withErrors(['docker' => $e->getMessage()]);
        }

        $audit->record('docker.images_pruned', null, ['reclaimed_bytes' => $reclaimed]);

        return back()->with('status', 'Unused images removed ('.round($reclaimed / 1024 ** 2, 1).' MB freed).');
    }

    public function logs(HostDriver $host, string $name): JsonResponse
    {
        try {
            ResourceName::assertValid($name);
            $logs = $host->containerLogs($name, 200);
        } catch (InvalidArgumentException) {
            return response()->json(['error' => 'Invalid container name.'], 422);
        } catch (HostOperationFailed $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        return response()->json(['logs' => $logs]);
    }
}
