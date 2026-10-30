<?php

use App\Enums\DeploymentStatus;
use App\Host\Data\SystemStatus;
use App\Host\Drivers\FakeHostDriver;
use App\Host\Exceptions\HostOperationFailed;
use App\Host\HostDriver;
use App\Models\App;
use App\Models\Deployment;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('summarises apps, server and recent deployments', function () {
    $running = App::factory()->create(['name' => 'Alpha']);
    $live = Deployment::factory()->create(['app_id' => $running->id, 'number' => 1, 'status' => DeploymentStatus::Active, 'commit_sha' => 'abcdef1234']);
    $running->update(['active_deployment_id' => $live->id]);

    $failed = App::factory()->create();
    Deployment::factory()->create(['app_id' => $failed->id, 'number' => 1, 'status' => DeploymentStatus::Failed]);

    App::factory()->create();

    $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('stats', ['apps' => 3, 'running' => 1, 'deploying' => 0, 'failed' => 1])
        ->where('server.docker', true)
        ->where('server.nginx', true)
        ->where('server.error', null)
        ->has('recent', 2)
        ->where('recent.1.app_name', 'Alpha')
        ->where('recent.1.commit', 'abcdef12'));
});

it('shows the host error instead of failing when the agent is unreachable', function () {
    app()->instance(HostDriver::class, new class extends FakeHostDriver
    {
        public function systemStatus(): SystemStatus
        {
            throw new HostOperationFailed('The CommyPanel agent is not reachable.');
        }
    });

    $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page
        ->where('server.error', 'The CommyPanel agent is not reachable.'));
});

it('renders the appearance settings', function () {
    $this->get('/settings/appearance')->assertInertia(fn (Assert $page) => $page->component('settings/appearance'));
});
