<?php

use App\Host\Data\ContainerSpec;
use App\Host\Data\ImageBuildSpec;
use App\Host\Data\NginxSite;
use App\Host\Data\Volume;
use App\Host\Drivers\AgentHostDriver;
use App\Host\Exceptions\DockerUnavailable;
use App\Host\Exceptions\HostOperationFailed;
use Illuminate\Support\Facades\Http;

function agentDriver(): AgentHostDriver
{
    return new AgentHostDriver('/nonexistent/agent.sock');
}

it('maps agent errors to HostOperationFailed', function () {
    Http::fake(['agent/*' => Http::response(['error' => 'invalid domain "x"'], 400)]);

    expect(fn () => agentDriver()->applyNginxSite(new NginxSite('demo', ['demo.example.com'], 20001)))
        ->toThrow(HostOperationFailed::class, 'invalid domain');
});

it('maps unexpected status codes to HostOperationFailed', function () {
    Http::fake(['agent/*' => Http::response('boom', 502)]);

    expect(fn () => agentDriver()->systemStatus())->toThrow(HostOperationFailed::class, 'HTTP 502');
});

it('reports an unreachable agent clearly', function () {
    expect(fn () => agentDriver()->systemStatus())
        ->toThrow(HostOperationFailed::class, 'not reachable');
});

it('sends the site as the agent expects it and omits an empty certificate', function () {
    Http::fake(['agent/*' => Http::response(['ok' => true])]);

    agentDriver()->applyNginxSite(new NginxSite('demo', ['demo.example.com'], 20001));

    Http::assertSent(fn ($request) => $request->method() === 'PUT'
        && $request->url() === 'http://agent/v1/nginx/sites'
        && $request->data() === ['name' => 'demo', 'domains' => ['demo.example.com'], 'upstream_port' => 20001]);
});

it('sends container specs without empty optional fields', function () {
    Http::fake(['agent/*' => Http::response(['ok' => true])]);

    agentDriver()->runContainer(new ContainerSpec(
        'demo-1', 'commypanel/demo:r1', 20001, 8080,
        env: ['A' => '1'],
        volumes: [new Volume('/var/lib/commypanel/volumes/demo', '/data', true)],
        memoryLimitMb: 256,
    ));
    agentDriver()->runContainer(new ContainerSpec('demo-2', 'commypanel/demo:r1', 20002, 8080));

    $sent = [];
    Http::assertSent(function ($request) use (&$sent) {
        $sent[] = $request->data();

        return true;
    });

    expect($sent[0])->toBe([
        'name' => 'demo-1',
        'image' => 'commypanel/demo:r1',
        'host_port' => 20001,
        'container_port' => 8080,
        'env' => ['A' => '1'],
        'volumes' => [['source' => '/var/lib/commypanel/volumes/demo', 'target' => '/data', 'read_only' => true]],
        'memory_mb' => 256,
    ])->and($sent[1])->not->toHaveKeys(['env', 'volumes', 'memory_mb', 'network']);
});

it('rejects invalid env names and volume paths before calling the agent', function () {
    Http::fake();

    expect(fn () => new ContainerSpec('demo-1', 'img:1', 20001, 8080, env: ['A=B' => 'x']))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => new Volume('relative', '/data'))
        ->toThrow(InvalidArgumentException::class);

    Http::assertNothingSent();
});

it('maps a 503 to DockerUnavailable', function () {
    Http::fake(['agent/*' => Http::response(['error' => 'docker is not available'], 503)]);

    expect(fn () => agentDriver()->runContainer(new ContainerSpec('demo-1', 'img:1', 20001, 8080)))
        ->toThrow(DockerUnavailable::class);
});

it('streams build output and finishes on ok', function () {
    Http::fake(['agent/*' => Http::response(
        "{\"log\":\"Step 1\"}\n{\"log\":\"Step 2\"}\n{\"ok\":true}\n"
    )]);

    $lines = [];
    agentDriver()->buildImage(new ImageBuildSpec('commypanel/demo:r1', '/var/lib/commypanel/builds/demo'), function (string $line) use (&$lines) {
        $lines[] = $line;
    });

    expect($lines)->toBe(['Step 1', 'Step 2']);
});

it('throws the build error reported by the agent', function () {
    Http::fake(['agent/*' => Http::response("{\"log\":\"Step 1\"}\n{\"error\":\"build failed: boom\"}\n")]);

    expect(fn () => agentDriver()->buildImage(new ImageBuildSpec('commypanel/demo:r1', '/x')))
        ->toThrow(HostOperationFailed::class, 'boom');
});

it('fails when the build stream ends without a result', function () {
    Http::fake(['agent/*' => Http::response("{\"log\":\"Step 1\"}\n")]);

    expect(fn () => agentDriver()->buildImage(new ImageBuildSpec('commypanel/demo:r1', '/x')))
        ->toThrow(HostOperationFailed::class, 'ended unexpectedly');
});

it('maps build validation errors', function () {
    Http::fake(['agent/*' => Http::response(['error' => 'build tags must start with commypanel/'], 400)]);

    expect(fn () => agentDriver()->buildImage(new ImageBuildSpec('library/alpine', '/x')))
        ->toThrow(HostOperationFailed::class, 'commypanel/');
});

it('lists containers, reads logs and prunes images', function () {
    Http::fake([
        'agent/v1/docker/containers/demo-1/logs*' => Http::response(['logs' => "hello\n"]),
        'agent/v1/docker/containers' => Http::response(['containers' => [['name' => 'demo-1', 'image' => 'img:1', 'state' => 'running', 'status' => 'Up']]]),
        'agent/v1/docker/images/prune' => Http::response(['ok' => true, 'space_reclaimed' => 1234]),
    ]);

    $driver = agentDriver();

    expect($driver->listContainers()[0]->name)->toBe('demo-1')
        ->and($driver->containerLogs('demo-1', 50))->toBe("hello\n")
        ->and($driver->pruneDanglingImages())->toBe(1234);
});
