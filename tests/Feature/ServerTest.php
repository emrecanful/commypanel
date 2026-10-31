<?php

use App\Host\Data\ContainerSpec;
use App\Host\Data\DockerInfo;
use App\Host\Data\HostInfo;
use App\Host\Data\ImageBuildSpec;
use App\Host\Data\NginxSite;
use App\Host\Data\NginxStatus;
use App\Host\Data\NginxTestResult;
use App\Host\Drivers\FakeHostDriver;
use App\Host\Exceptions\HostOperationFailed;
use App\Host\HostDriver;
use App\Models\App;
use App\Models\AuditLog;
use App\Models\Deployment;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->host = app(HostDriver::class);
});

it('requires login for every server page', function (string $method, string $url) {
    auth()->logout();

    $this->{$method}($url)->assertRedirect('/login');
})->with([
    ['get', '/server'], ['get', '/server/docker'], ['get', '/server/nginx'],
    ['post', '/server/nginx/test'], ['post', '/server/nginx/reload'], ['post', '/server/docker/prune'],
    ['get', '/server/docker/containers/shop-1/logs'],
]);

describe('overview', function () {
    it('shows the host and its services', function () {
        $this->get('/server')->assertInertia(fn (Assert $page) => $page
            ->component('server/overview')
            ->where('error', null)
            ->where('host.hostname', 'fake-host')
            ->where('host.cpus', 4)
            ->where('host.memory.total', 4 * 1024 ** 3)
            ->where('host.services.0', ['name' => 'nginx', 'state' => 'active']));
    });

    it('shows the problem when the agent cannot be reached', function () {
        app()->instance(HostDriver::class, new class extends FakeHostDriver
        {
            public function systemInfo(): HostInfo
            {
                throw new HostOperationFailed('The CommyPanel agent is not reachable.');
            }
        });

        $this->get('/server')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('host', null)
            ->where('error', 'The CommyPanel agent is not reachable.'));
    });
});

describe('docker', function () {
    it('shows the daemon, containers with their app, and images', function () {
        $app = App::factory()->create(['slug' => 'shop', 'name' => 'Shop']);
        Deployment::factory()->create(['app_id' => $app->id, 'number' => 3, 'container_name' => 'shop-3']);
        $this->host->runContainer(new ContainerSpec('shop-3', 'commypanel/shop:3', 20001, 8080));
        $this->host->buildImage(new ImageBuildSpec('commypanel/shop:3', '/tmp/x', 'Dockerfile'));

        $this->get('/server/docker')->assertInertia(fn (Assert $page) => $page
            ->component('server/docker')
            ->where('docker.available', true)
            ->where('docker.server_version', '27.0.0-fake')
            ->where('docker.running', 1)
            ->where('containers.0.name', 'shop-3')
            ->where('containers.0.app.slug', 'shop')
            ->where('containers.0.release', 3)
            ->where('images.0.tags', ['commypanel/shop:3']));
    });

    it('does not list anything when Docker is down, and says why', function () {
        app()->instance(HostDriver::class, new class extends FakeHostDriver
        {
            public function dockerInfo(): DockerInfo
            {
                return new DockerInfo(available: false, error: 'Cannot connect to the Docker daemon');
            }
        });

        $this->get('/server/docker')->assertInertia(fn (Assert $page) => $page
            ->where('docker.available', false)
            ->where('docker.error', 'Cannot connect to the Docker daemon')
            ->where('containers', [])
            ->where('images', []));
    });

    it('prunes unused images and records it', function () {
        $this->post('/server/docker/prune')->assertSessionHasNoErrors()->assertSessionHas('status');

        expect(collect($this->host->actions())->pluck('action')->all())->toContain('pruneDanglingImages')
            ->and(AuditLog::query()->where('action', 'docker.images_pruned')->exists())->toBeTrue();
    });

    it('reports a failed prune', function () {
        $this->host->failOn('pruneDanglingImages');

        $this->post('/server/docker/prune')->assertSessionHasErrors('docker');
    });

    it('returns container logs and rejects bad names', function () {
        $this->host->runContainer(new ContainerSpec('shop-3', 'commypanel/shop:3', 20001, 8080));

        $this->getJson('/server/docker/containers/shop-3/logs')->assertOk()->assertJson(['logs' => "[fake] logs for shop-3\n"]);
        $this->getJson('/server/docker/containers/-bad/logs')->assertNotFound();
    });

    it('reports a failing log request', function () {
        $this->host->failOn('containerLogs');

        $this->getJson('/server/docker/containers/shop-3/logs')->assertStatus(502)->assertJsonStructure(['error']);
    });
});

describe('nginx', function () {
    it('shows status, version, config verdict and the sites CommyPanel manages', function () {
        $this->host->applyNginxSite(new NginxSite('shop', ['shop.example.com'], 20001, 'shop'));

        $this->get('/server/nginx')->assertInertia(fn (Assert $page) => $page
            ->component('server/nginx')
            ->where('nginx.running', true)
            ->where('nginx.version', '1.26.0-fake')
            ->where('nginx.config_ok', true)
            ->where('nginx.sites.0.name', 'shop')
            ->where('nginx.sites.0.domains', ['shop.example.com'])
            ->where('nginx.sites.0.https', true));
    });

    it('shows a configuration problem', function () {
        app()->instance(HostDriver::class, new class extends FakeHostDriver
        {
            public function nginxStatus(): NginxStatus
            {
                return new NginxStatus(true, true, '1.24.0', false, 'unexpected "}" in /etc/nginx/x.conf:3', []);
            }
        });

        $this->get('/server/nginx')->assertInertia(fn (Assert $page) => $page
            ->where('nginx.config_ok', false)
            ->where('nginx.config_output', 'unexpected "}" in /etc/nginx/x.conf:3'));
    });

    it('tests the configuration without reloading', function () {
        $this->post('/server/nginx/test')->assertSessionHasNoErrors()->assertSessionHas('status');

        expect(collect($this->host->actions())->pluck('action')->all())->toContain('testNginx')->not->toContain('reloadNginx');
    });

    it('shows the output when the configuration is invalid', function () {
        app()->instance(HostDriver::class, new class extends FakeHostDriver
        {
            public function testNginx(): NginxTestResult
            {
                return new NginxTestResult(false, 'emerg: bad directive');
            }
        });

        $this->post('/server/nginx/test')->assertSessionHasErrors(['nginx' => 'The nginx configuration has a problem: emerg: bad directive']);
    });

    it('reloads and records it, or reports why not', function () {
        $this->post('/server/nginx/reload')->assertSessionHasNoErrors();
        expect(AuditLog::query()->where('action', 'nginx.reloaded')->exists())->toBeTrue();

        $this->host->failOn('reloadNginx');
        $this->post('/server/nginx/reload')->assertSessionHasErrors('nginx');
    });
});
