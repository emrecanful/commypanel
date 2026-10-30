<?php

use App\Host\Data\ContainerSpec;
use App\Host\Data\NginxSite;
use App\Host\Drivers\AgentHostDriver;
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

it('refuses Docker operations until the agent supports them', function () {
    expect(fn () => agentDriver()->runContainer(new ContainerSpec('demo-1', 'demo:1', 20001, 8080)))
        ->toThrow(HostOperationFailed::class, 'not available');
});
