<?php

namespace App\Host\Drivers;

use App\Host\Data\ContainerInfo;
use App\Host\Data\ContainerSpec;
use App\Host\Data\ImageBuildSpec;
use App\Host\Data\NginxSite;
use App\Host\Data\ResourceName;
use App\Host\Data\SystemStatus;
use App\Host\Exceptions\DockerUnavailable;
use App\Host\Exceptions\HostOperationFailed;
use App\Host\HostDriver;
use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\Utils;
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
        $stream = new BuildStream($onOutput);

        // Guzzle's `stream` option would switch to a handler that ignores the
        // Unix socket, so the response is written into a sink that feeds the parser.
        $sink = FnStream::decorate(Utils::streamFor(''), [
            'write' => function (string $chunk) use ($stream): int {
                $stream->write($chunk);

                return strlen($chunk);
            },
        ]);

        $response = $this->request(fn () => $this->client(timeout: 3600)
            ->withOptions(['sink' => $sink])
            ->post('/v1/docker/images/build', [
                'tag' => $spec->tag,
                'context_path' => $spec->contextPath,
                'dockerfile' => $spec->dockerfile,
            ]));

        if (! $stream->received) {
            $stream->write($response->body());
        }

        $stream->finish();

        if ($response->failed()) {
            $this->throwFor($response->status(), $stream->error);
        }

        if ($stream->error !== null) {
            throw new HostOperationFailed($stream->error);
        }

        if (! $stream->finished) {
            throw new HostOperationFailed('The build output ended unexpectedly.');
        }
    }

    public function runContainer(ContainerSpec $spec): void
    {
        $this->send('post', '/v1/docker/containers', array_filter([
            'name' => $spec->name,
            'image' => $spec->image,
            'host_port' => $spec->hostPort,
            'container_port' => $spec->containerPort,
            'env' => $spec->env,
            'volumes' => array_map(fn ($volume) => [
                'source' => $volume->source,
                'target' => $volume->target,
                'read_only' => $volume->readOnly,
            ], $spec->volumes),
            'memory_mb' => $spec->memoryLimitMb,
            'cpus' => $spec->cpuLimit,
            'network' => $spec->network,
        ], fn ($value) => $value !== null && $value !== []));
    }

    public function stopContainer(string $name): void
    {
        ResourceName::assertValid($name);

        $this->send('post', "/v1/docker/containers/{$name}/stop");
    }

    public function removeContainer(string $name): void
    {
        ResourceName::assertValid($name);

        $this->send('delete', "/v1/docker/containers/{$name}");
    }

    public function listContainers(): array
    {
        $data = $this->send('get', '/v1/docker/containers');

        return array_values(array_map(fn (array $c) => new ContainerInfo(
            name: (string) ($c['name'] ?? ''),
            image: (string) ($c['image'] ?? ''),
            state: (string) ($c['state'] ?? ''),
            status: (string) ($c['status'] ?? ''),
        ), $data['containers'] ?? []));
    }

    public function containerLogs(string $name, int $tail = 200): string
    {
        ResourceName::assertValid($name);

        $data = $this->send('get', "/v1/docker/containers/{$name}/logs?tail={$tail}");

        return (string) ($data['logs'] ?? '');
    }

    public function pruneDanglingImages(): int
    {
        $data = $this->send('post', '/v1/docker/images/prune');

        return (int) ($data['space_reclaimed'] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function send(string $method, string $path, array $body = []): array
    {
        $response = $this->request(fn () => $this->client()->{$method}($path, $body === [] ? null : $body));

        return $this->handle($response);
    }

    private function request(callable $call): Response
    {
        try {
            return $call();
        } catch (ConnectionException $e) {
            throw new HostOperationFailed("The CommyPanel agent is not reachable at {$this->socketPath}.", 0, $e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function handle(Response $response): array
    {
        if ($response->failed()) {
            $this->throwFor($response->status(), $response->json('error'));
        }

        return $response->json() ?? [];
    }

    private function throwFor(int $status, ?string $error): never
    {
        $message = 'Agent error: '.($error ?? "HTTP {$status}");

        throw $status === 503 ? new DockerUnavailable($message) : new HostOperationFailed($message);
    }

    private function client(int $timeout = 60): PendingRequest
    {
        return Http::baseUrl('http://agent')
            ->withOptions(['curl' => [CURLOPT_UNIX_SOCKET_PATH => $this->socketPath]])
            ->acceptJson()
            ->asJson()
            ->connectTimeout(3)
            ->timeout($timeout);
    }
}
