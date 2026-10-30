<?php

namespace App\Host;

use App\Host\Data\ContainerInfo;
use App\Host\Data\ContainerSpec;
use App\Host\Data\ImageBuildSpec;
use App\Host\Data\NginxSite;
use App\Host\Data\SystemStatus;
use App\Host\Exceptions\DockerUnavailable;
use App\Host\Exceptions\HostOperationFailed;

/**
 * Every privileged operation on the server goes through this interface.
 * The real implementation talks to commypanel-agent; tests and macOS
 * development use the fake driver. Never run host commands outside of it.
 */
interface HostDriver
{
    public function systemStatus(): SystemStatus;

    /**
     * Validate, write and gracefully reload an nginx site.
     *
     * @throws HostOperationFailed
     */
    public function applyNginxSite(NginxSite $site): void;

    /**
     * @throws HostOperationFailed
     */
    public function removeNginxSite(string $name): void;

    /**
     * @param  callable(string): void|null  $onOutput  receives build log lines
     *
     * @throws HostOperationFailed
     */
    public function buildImage(ImageBuildSpec $spec, ?callable $onOutput = null): void;

    /**
     * @throws DockerUnavailable
     * @throws HostOperationFailed
     */
    public function runContainer(ContainerSpec $spec): void;

    /**
     * @throws HostOperationFailed
     */
    public function stopContainer(string $name): void;

    /**
     * @throws HostOperationFailed
     */
    public function removeContainer(string $name): void;

    /**
     * Managed containers only; containers the agent did not create are never listed.
     *
     * @return list<ContainerInfo>
     *
     * @throws DockerUnavailable
     */
    public function listContainers(): array;

    /**
     * @throws HostOperationFailed
     */
    public function containerLogs(string $name, int $tail = 200): string;

    /**
     * Remove dangling images and return the bytes reclaimed.
     *
     * @throws HostOperationFailed
     */
    public function pruneDanglingImages(): int;
}
