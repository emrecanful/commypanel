<?php

namespace App\Host\Drivers;

use App\Host\Data\CertificateInfo;
use App\Host\Data\CertificateRequest;
use App\Host\Data\ContainerInfo;
use App\Host\Data\ContainerSpec;
use App\Host\Data\ImageBuildSpec;
use App\Host\Data\NginxSite;
use App\Host\Data\ResourceName;
use App\Host\Data\SystemStatus;
use App\Host\Exceptions\HostOperationFailed;
use App\Host\HostDriver;
use DateTimeImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Records host operations instead of performing them.
 */
class FakeHostDriver implements HostDriver
{
    /** @var list<array{action: string, args: array<string, mixed>}> */
    private array $actions = [];

    private ?string $failOn = null;

    /** @var array<string, NginxSite> */
    private array $sites = [];

    /** @var array<string, ContainerSpec> */
    private array $containers = [];

    /** @var array<string, CertificateInfo> */
    private array $certificates = [];

    public function systemStatus(): SystemStatus
    {
        return new SystemStatus(dockerAvailable: true, nginxAvailable: true, diskFreeBytes: 50 * 1024 ** 3);
    }

    public function applyNginxSite(NginxSite $site): void
    {
        $this->record('applyNginxSite', ['site' => $site]);
        $this->sites[$site->name] = $site;
    }

    public function removeNginxSite(string $name): void
    {
        ResourceName::assertValid($name);
        $this->record('removeNginxSite', ['name' => $name]);
        unset($this->sites[$name]);
    }

    public function buildImage(ImageBuildSpec $spec, ?callable $onOutput = null): void
    {
        $this->record('buildImage', ['spec' => $spec]);

        if ($onOutput !== null) {
            $onOutput("[fake] building {$spec->tag}");
        }
    }

    public function runContainer(ContainerSpec $spec): void
    {
        $this->record('runContainer', ['spec' => $spec]);
        $this->containers[$spec->name] = $spec;
    }

    public function stopContainer(string $name): void
    {
        ResourceName::assertValid($name);
        $this->record('stopContainer', ['name' => $name]);
    }

    public function removeContainer(string $name): void
    {
        ResourceName::assertValid($name);
        $this->record('removeContainer', ['name' => $name]);
        unset($this->containers[$name]);
    }

    public function issueCertificate(CertificateRequest $request): CertificateInfo
    {
        $this->record('issueCertificate', ['request' => $request]);

        return $this->certificates[$request->name] = new CertificateInfo(
            $request->name,
            $request->domains,
            new DateTimeImmutable('-1 minute'),
            new DateTimeImmutable('+90 days'),
            $request->staging ? '(STAGING) Fake CA' : 'Fake CA',
        );
    }

    public function certificateInfo(string $name): ?CertificateInfo
    {
        return $this->certificates[$name] ?? null;
    }

    public function removeCertificate(string $name): void
    {
        ResourceName::assertValid($name);
        $this->record('removeCertificate', ['name' => $name]);
        unset($this->certificates[$name]);
    }

    /**
     * @return array<string, CertificateInfo>
     */
    public function certificates(): array
    {
        return $this->certificates;
    }

    public function removeImage(string $tag): void
    {
        $this->record('removeImage', ['tag' => $tag]);
    }

    public function listContainers(): array
    {
        return array_values(array_map(
            fn (ContainerSpec $spec) => new ContainerInfo($spec->name, $spec->image, 'running', 'Up (fake)'),
            $this->containers,
        ));
    }

    public function containerLogs(string $name, int $tail = 200): string
    {
        ResourceName::assertValid($name);
        $this->record('containerLogs', ['name' => $name, 'tail' => $tail]);

        return "[fake] logs for {$name}\n";
    }

    public function pruneDanglingImages(): int
    {
        $this->record('pruneDanglingImages', []);

        return 0;
    }

    /**
     * Make the next call of the given action throw, to test failure paths.
     */
    public function failOn(string $action): static
    {
        $this->failOn = $action;

        return $this;
    }

    /**
     * @return list<array{action: string, args: array<string, mixed>}>
     */
    public function actions(): array
    {
        return $this->actions;
    }

    /**
     * @return list<string>
     */
    public function actionNames(): array
    {
        return array_column($this->actions, 'action');
    }

    /**
     * @return array<string, NginxSite>
     */
    public function sites(): array
    {
        return $this->sites;
    }

    /**
     * @return array<string, ContainerSpec>
     */
    public function containers(): array
    {
        return $this->containers;
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function record(string $action, array $args): void
    {
        if ($this->failOn === $action) {
            $this->failOn = null;

            throw new HostOperationFailed("Fake failure on [{$action}].");
        }

        $this->actions[] = ['action' => $action, 'args' => $args];

        Log::debug("[fake host] {$action}");
    }
}
