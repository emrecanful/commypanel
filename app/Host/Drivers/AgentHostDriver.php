<?php

namespace App\Host\Drivers;

use App\Host\Data\ContainerSpec;
use App\Host\Data\ImageBuildSpec;
use App\Host\Data\NginxSite;
use App\Host\Data\ResourceName;
use App\Host\Data\SystemStatus;
use App\Host\Exceptions\HostOperationFailed;
use App\Host\HostDriver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Performs host operations through commypanel-agent over its Unix socket.
 */
class AgentHostDriver implements HostDriver
{
    public function __construct(private readonly string $socketPath) {}

    public function systemStatus(): SystemStatus
    {
        $data = $this->send('get', '/v1/status');

        return new SystemStatus(
            dockerAvailable: (bool) ($data['docker_available'] ?? false),
            nginxAvailable: (bool) ($data['nginx_available'] ?? false),
            diskFreeBytes: (int) ($data['disk_free_bytes'] ?? 0),
        );
    }

    public function applyNginxSite(NginxSite $site): void
    {
        $this->send('put', '/v1/nginx/sites', array_filter([
            'name' => $site->name,
            'domains' => $site->domains,
            'upstream_port' => $site->upstreamPort,
            'certificate' => $site->certificate,
        ], fn ($value) => $value !== null));
    }

    public function removeNginxSite(string $name): void
    {
        ResourceName::assertValid($name);

        $this->send('delete', '/v1/nginx/sites/'.$name);
    }

    public function buildImage(ImageBuildSpec $spec, ?callable $onOutput = null): void
    {
        $this->notAvailable();
    }

    public function runContainer(ContainerSpec $spec): void
    {
        $this->notAvailable();
    }

    public function stopContainer(string $name): void
    {
        $this->notAvailable();
    }

    public function removeContainer(string $name): void
    {
        $this->notAvailable();
    }

    private function notAvailable(): never
    {
        throw new HostOperationFailed('This operation is not available in the agent yet.');
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function send(string $method, string $path, array $body = []): array
    {
        try {
            $response = $this->client()->{$method}($path, $body === [] ? null : $body);
        } catch (ConnectionException $e) {
            throw new HostOperationFailed("The CommyPanel agent is not reachable at {$this->socketPath}.", 0, $e);
        }

        return $this->handle($response);
    }

    /**
     * @return array<string, mixed>
     */
    private function handle(Response $response): array
    {
        if ($response->failed()) {
            $message = $response->json('error') ?? "HTTP {$response->status()}";

            throw new HostOperationFailed("Agent error: {$message}");
        }

        return $response->json() ?? [];
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl('http://agent')
            ->withOptions(['curl' => [CURLOPT_UNIX_SOCKET_PATH => $this->socketPath]])
            ->acceptJson()
            ->asJson()
            ->connectTimeout(3)
            ->timeout(60);
    }
}
