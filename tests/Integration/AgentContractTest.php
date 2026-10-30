<?php

use App\Host\Data\ContainerSpec;
use App\Host\Data\ImageBuildSpec;
use App\Host\Data\NginxSite;
use App\Host\Drivers\AgentHostDriver;
use App\Host\Exceptions\HostOperationFailed;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Runs the real Go agent in dry-run mode and exercises AgentHostDriver
 * against it. Skipped when the Go toolchain is not available.
 */
beforeEach(function () {
    $go = trim((string) shell_exec('command -v go 2>/dev/null'));
    if ($go === '') {
        $this->markTestSkipped('Go toolchain not available.');
    }

    $this->dir = sys_get_temp_dir().'/cp-'.bin2hex(random_bytes(3));
    File::ensureDirectoryExists($this->dir);

    $build = Process::path(base_path('agent'))->run([$go, 'build', '-o', $this->dir.'/agent', './cmd/commypanel-agent']);
    expect($build->successful())->toBeTrue($build->errorOutput());

    $this->socket = $this->dir.'/agent.sock';
    $this->agent = Process::start([
        $this->dir.'/agent', '--dry-run',
        '--socket', $this->socket,
        '--data-dir', $this->dir.'/data',
        '--nginx-sites-dir', $this->dir.'/sites',
        '--allow-uid', (string) posix_geteuid(),
    ]);

    for ($i = 0; $i < 50 && ! file_exists($this->socket); $i++) {
        usleep(100_000);
    }
    expect(file_exists($this->socket))->toBeTrue('agent did not start');

    $this->driver = new AgentHostDriver($this->socket);
});

afterEach(function () {
    $this->agent?->signal(SIGTERM);
    $this->agent?->wait();
    File::deleteDirectory($this->dir);
});

it('reports system status', function () {
    $status = $this->driver->systemStatus();

    expect($status->diskFreeBytes)->toBeGreaterThan(0);
});

it('applies and removes an nginx site', function () {
    $this->driver->applyNginxSite(new NginxSite('demo', ['demo.example.com'], 20001));

    $file = $this->dir.'/sites/commypanel-demo.conf';
    expect($file)->toBeFile()
        ->and(file_get_contents($file))->toContain('server_name demo.example.com;')
        ->toContain('proxy_pass http://127.0.0.1:20001;');

    $this->driver->removeNginxSite('demo');
    expect($file)->not->toBeFile();
});

it('surfaces agent validation errors', function () {
    // PHP's hostname filter accepts uppercase, the agent's allow-list does not.
    expect(fn () => $this->driver->applyNginxSite(new NginxSite('demo', ['Demo.Example.com'], 20001)))
        ->toThrow(HostOperationFailed::class, 'invalid domain');

    expect($this->dir.'/sites/commypanel-demo.conf')->not->toBeFile();
});

it('validates and accepts Docker operations in dry-run mode', function () {
    $this->driver->runContainer(new ContainerSpec('demo-1', 'commypanel/demo:r1', 20001, 8080));
    $this->driver->stopContainer('demo-1');
    $this->driver->removeContainer('demo-1');

    expect($this->driver->listContainers())->toBe([])
        ->and($this->driver->pruneDanglingImages())->toBe(0);
});

it('surfaces agent-side Docker validation errors', function () {
    // The PHP DTO accepts this image; the agent's allow-list does not.
    expect(fn () => $this->driver->runContainer(new ContainerSpec('demo-1', 'Not A Valid Image', 20001, 8080)))
        ->toThrow(HostOperationFailed::class, 'invalid image');
});

it('streams a dry-run build', function () {
    $lines = [];
    $this->driver->buildImage(
        new ImageBuildSpec('commypanel/demo:r1', $this->dir.'/data/builds/demo'),
        function (string $line) use (&$lines) {
            $lines[] = $line;
        },
    );

    expect($lines)->toBe(['[dry-run] would build commypanel/demo:r1']);
});

it('rejects a build context outside the builds directory', function () {
    expect(fn () => $this->driver->buildImage(new ImageBuildSpec('commypanel/demo:r1', '/etc')))
        ->toThrow(HostOperationFailed::class, 'outside the allowed directory');
});

it('is refused when the caller is not on the allow-list', function () {
    $other = $this->dir.'/other.sock';
    $agent = Process::start([
        $this->dir.'/agent', '--dry-run', '--socket', $other,
        '--data-dir', $this->dir.'/data', '--nginx-sites-dir', $this->dir.'/sites',
        '--allow-uid', '1',
    ]);
    for ($i = 0; $i < 50 && ! file_exists($other); $i++) {
        usleep(100_000);
    }

    try {
        // On Linux the UID check denies us; on macOS dry-run accepts all local callers.
        if (PHP_OS_FAMILY !== 'Linux') {
            $this->markTestSkipped('Peer credentials are only checked on Linux.');
        }

        expect(fn () => (new AgentHostDriver($other))->systemStatus())
            ->toThrow(HostOperationFailed::class, 'forbidden');
    } finally {
        $agent->signal(SIGTERM);
        $agent->wait();
    }
});
