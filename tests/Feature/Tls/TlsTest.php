<?php

use App\Deploy\Traffic\AppRouting;
use App\Dns\CheckDomain;
use App\Dns\DnsResolver;
use App\Dns\ServerAddress;
use App\Enums\CertificateStatus;
use App\Enums\DeploymentStatus;
use App\Enums\DomainStatus;
use App\Host\Data\NginxSite;
use App\Host\Drivers\FakeHostDriver;
use App\Host\Exceptions\HostOperationFailed;
use App\Host\HostDriver;
use App\Jobs\IssueAppCertificate;
use App\Models\App;
use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\Deployment;
use App\Models\Domain;
use App\Models\Setting;
use App\Models\User;
use App\Tls\HttpsSettings;
use App\Tls\IssueCertificate;
use App\Tls\TlsPlanner;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakeDnsResolver;

beforeEach(function () {
    config(['commypanel.https.staging' => true]);
    $this->host = app(HostDriver::class);
    $this->shop = App::factory()->create(['slug' => 'shop']);
    $deployment = Deployment::factory()->create(['app_id' => $this->shop->id, 'number' => 1, 'status' => DeploymentStatus::Active, 'host_port' => 20001]);
    $this->shop->update(['active_deployment_id' => $deployment->id]);
});

function enableHttps(?string $email = 'ops@example.com'): void
{
    app(HttpsSettings::class)->enable($email);
}

function connectedDomain(App $app, string $name = 'shop.example.com'): Domain
{
    return Domain::query()->create(['name' => $name, 'app_id' => $app->id, 'status' => DomainStatus::Verified]);
}

function certificateFor(App $app, array $attributes = []): Certificate
{
    return Certificate::query()->create([
        'app_id' => $app->id,
        'status' => CertificateStatus::Active,
        'domains' => ['shop.example.com'],
        'issued_at' => now()->subDays(10),
        'expires_at' => now()->addDays(80),
        'last_attempt_at' => now()->subDays(10),
        ...$attributes,
    ]);
}

describe('settings', function () {
    it('is off until the user agrees', function () {
        expect(app(HttpsSettings::class)->enabled())->toBeFalse();
    });

    it('turns on only with agreement and remembers the email', function () {
        $this->actingAs(User::factory()->create());

        $this->put('/settings/https', ['agree' => true, 'email' => 'ops@example.com'])->assertSessionHasNoErrors();

        expect(app(HttpsSettings::class)->enabled())->toBeTrue()
            ->and(app(HttpsSettings::class)->email())->toBe('ops@example.com')
            ->and(AuditLog::query()->where('action', 'https.enabled')->exists())->toBeTrue();

        $this->put('/settings/https', ['agree' => false])->assertSessionHasNoErrors();

        expect(app(HttpsSettings::class)->enabled())->toBeFalse();
    });

    it('validates input and requires login', function () {
        $this->put('/settings/https', ['agree' => true])->assertRedirect('/login');

        $this->actingAs(User::factory()->create());
        $this->put('/settings/https', ['agree' => true, 'email' => 'nope'])->assertSessionHasErrors('email');
        $this->put('/settings/https', [])->assertSessionHasErrors('agree');
    });

    it('queues certificates for apps with domains when it is switched on', function () {
        Queue::fake();
        $this->actingAs(User::factory()->create());
        connectedDomain($this->shop);

        $this->put('/settings/https', ['agree' => true])->assertSessionHasNoErrors();

        Queue::assertPushed(IssueAppCertificate::class, fn ($job) => $job->appId === $this->shop->id);
    });
});

describe('planner', function () {
    beforeEach(function () {
        Queue::fake();
        enableHttps();
        $this->planner = app(TlsPlanner::class);
    });

    it('needs nothing without a connected domain', function () {
        Domain::query()->create(['name' => 'shop.example.com', 'app_id' => $this->shop->id, 'status' => DomainStatus::Pending]);
        Domain::query()->create(['name' => 'proxied.example.com', 'app_id' => $this->shop->id, 'status' => DomainStatus::Proxied]);

        expect($this->planner->ensure($this->shop))->toBeFalse()
            ->and($this->planner->eligibleDomains($this->shop))->toBe([]);
        Queue::assertNothingPushed();
    });

    it('needs a first certificate as soon as a domain is connected', function () {
        connectedDomain($this->shop);

        expect($this->planner->ensure($this->shop))->toBeTrue();
        Queue::assertPushed(IssueAppCertificate::class);
    });

    it('does nothing while HTTPS is off', function () {
        app(HttpsSettings::class)->disable();
        connectedDomain($this->shop);

        expect($this->planner->ensure($this->shop))->toBeFalse();
    });

    it('leaves a healthy certificate alone', function () {
        connectedDomain($this->shop);
        certificateFor($this->shop);

        expect($this->planner->needsIssue($this->shop))->toBeFalse();
    });

    it('issues again for a domain that is not on the certificate', function () {
        connectedDomain($this->shop);
        connectedDomain($this->shop, 'www.shop.example.com');
        certificateFor($this->shop);

        expect($this->planner->needsIssue($this->shop))->toBeTrue();
    });

    it('does not reissue just because a domain went away', function () {
        connectedDomain($this->shop);
        certificateFor($this->shop, ['domains' => ['shop.example.com', 'old.example.com']]);

        expect($this->planner->needsIssue($this->shop))->toBeFalse();
    });

    it('renews inside the renewal window', function () {
        connectedDomain($this->shop);
        certificateFor($this->shop, ['expires_at' => now()->addDays(20)]);

        expect($this->planner->needsIssue($this->shop))->toBeTrue();
    });

    it('backs off after a recent failure and retries later', function () {
        connectedDomain($this->shop);
        $cert = certificateFor($this->shop, ['status' => CertificateStatus::Failed, 'issued_at' => null, 'expires_at' => null, 'last_attempt_at' => now()->subHour()]);

        expect($this->planner->needsIssue($this->shop))->toBeFalse();

        $cert->update(['last_attempt_at' => now()->subHours(7)]);
        expect($this->planner->needsIssue($this->shop))->toBeTrue();
    });

    it('does not start a second attempt while one is running', function () {
        connectedDomain($this->shop);
        certificateFor($this->shop, ['status' => CertificateStatus::Issuing, 'last_attempt_at' => now()->subMinutes(2)]);

        expect($this->planner->needsIssue($this->shop))->toBeFalse();
    });
});

describe('issuing', function () {
    beforeEach(function () {
        enableHttps('ops@example.com');
        connectedDomain($this->shop, 'b.example.com');
        connectedDomain($this->shop, 'a.example.com');
        Domain::query()->create(['name' => 'pending.example.com', 'app_id' => $this->shop->id, 'status' => DomainStatus::Pending]);
    });

    it('requests a certificate for the connected domains and switches nginx to HTTPS', function () {
        app(IssueCertificate::class)($this->shop);

        $request = collect($this->host->actions())->firstWhere('action', 'issueCertificate')['args']['request'];
        expect($request->name)->toBe('shop')
            ->and($request->domains)->toBe(['a.example.com', 'b.example.com'])
            ->and($request->email)->toBe('ops@example.com')
            ->and($request->staging)->toBeTrue();

        $cert = $this->shop->certificate()->first();
        expect($cert->status)->toBe(CertificateStatus::Active)
            ->and($cert->domains)->toBe(['a.example.com', 'b.example.com'])
            ->and($cert->staging)->toBeTrue()
            ->and($cert->expires_at->isFuture())->toBeTrue()
            ->and($cert->last_error)->toBeNull();

        $site = $this->host->sites()['shop'];
        expect($site->certificate)->toBe('shop')
            ->and($site->domains)->toBe(['a.example.com', 'b.example.com', 'pending.example.com'])
            ->and(AuditLog::query()->where('action', 'tls.issued')->exists())->toBeTrue();
    });

    it('stays on HTTP until a certificate exists', function () {
        app(AppRouting::class)->sync($this->shop);

        expect($this->host->sites()['shop']->certificate)->toBeNull();
    });

    it('does not use an expired or failed certificate for HTTPS', function () {
        $cert = certificateFor($this->shop, ['expires_at' => now()->subDay()]);
        app(AppRouting::class)->sync($this->shop);
        expect($this->host->sites()['shop']->certificate)->toBeNull();

        $cert->update(['expires_at' => now()->addDays(60), 'status' => CertificateStatus::Failed]);
        app(AppRouting::class)->sync($this->shop->fresh());
        expect($this->host->sites()['shop']->certificate)->toBeNull();
    });

    it('records the CA\'s reason when issuing fails and keeps HTTP working', function () {
        $this->host->failOn('issueCertificate');

        app(IssueCertificate::class)($this->shop);

        $cert = $this->shop->certificate()->first();
        expect($cert->status)->toBe(CertificateStatus::Failed)
            ->and($cert->last_error)->toContain('Fake failure')
            ->and($this->host->sites())->toBe([])
            ->and(AuditLog::query()->where('action', 'tls.failed')->exists())->toBeTrue();
    });

    it('keeps serving a still-valid certificate when a renewal fails', function () {
        certificateFor($this->shop, ['domains' => ['a.example.com', 'b.example.com'], 'expires_at' => now()->addDays(10)]);
        $this->host->failOn('issueCertificate');

        app(IssueCertificate::class)($this->shop);

        $cert = $this->shop->certificate()->first();
        expect($cert->status)->toBe(CertificateStatus::Active)
            ->and($cert->last_error)->toContain('Fake failure');
    });

    it('does nothing when HTTPS is off or no domain is connected', function () {
        app(HttpsSettings::class)->disable();
        app(IssueCertificate::class)($this->shop);
        expect($this->host->actionNames())->toBe([]);

        enableHttps();
        Domain::query()->update(['status' => DomainStatus::Pending]);
        app(IssueCertificate::class)($this->shop);
        expect($this->host->actionNames())->toBe([])
            ->and($this->shop->certificate()->exists())->toBeFalse();
    });

    it('reports an nginx problem after issuing without losing the certificate', function () {
        $host = new class extends FakeHostDriver
        {
            public function applyNginxSite(NginxSite $site): void
            {
                throw new HostOperationFailed('nginx -t failed');
            }
        };
        app()->instance(HostDriver::class, $host);

        app(IssueCertificate::class)($this->shop);

        $cert = $this->shop->certificate()->first();
        expect($cert->status)->toBe(CertificateStatus::Active)
            ->and($cert->last_error)->toContain('nginx could not use it');
    });
});

describe('triggers', function () {
    beforeEach(function () {
        Queue::fake();
        enableHttps();
        app(ServerAddress::class)->save('203.0.113.7', null);
        app()->instance(DnsResolver::class, new FakeDnsResolver(['shop.example.com' => ['a' => ['203.0.113.7']]]));
    });

    it('queues a certificate when a domain of an app becomes connected', function () {
        $domain = Domain::query()->create(['name' => 'shop.example.com', 'app_id' => $this->shop->id]);

        app(CheckDomain::class)($domain);

        Queue::assertPushed(IssueAppCertificate::class);
    });

    it('does not queue anything for a domain that is not assigned to an app', function () {
        $domain = Domain::query()->create(['name' => 'shop.example.com']);

        app(CheckDomain::class)($domain);

        Queue::assertNothingPushed();
    });

    it('queues a certificate when an already connected domain is assigned', function () {
        $this->actingAs(User::factory()->create());
        $domain = Domain::query()->create(['name' => 'shop.example.com', 'status' => DomainStatus::Verified]);

        $this->patch("/domains/{$domain->id}", ['app' => 'shop'])->assertSessionHasNoErrors();

        Queue::assertPushed(IssueAppCertificate::class);
    });

    it('renews through the scheduled command', function () {
        connectedDomain($this->shop);
        $other = App::factory()->create(['slug' => 'other']);
        connectedDomain($other, 'other.example.com');
        certificateFor($other, ['domains' => ['other.example.com']]);

        $this->artisan('commypanel:renew-certificates')->assertSuccessful();

        Queue::assertPushed(IssueAppCertificate::class, 1);
        Queue::assertPushed(IssueAppCertificate::class, fn ($job) => $job->appId === $this->shop->id);
    });
});

describe('requests from the UI', function () {
    beforeEach(function () {
        Queue::fake();
        $this->actingAs(User::factory()->create());
    });

    it('explains what is missing before issuing', function () {
        $this->post('/apps/shop/certificate')->assertSessionHasErrors(['certificate' => 'Turn on automatic HTTPS on the Domains page first.']);

        enableHttps();
        $this->post('/apps/shop/certificate')->assertSessionHasErrors('certificate');

        Queue::assertNothingPushed();
    });

    it('queues a manual request and audits it', function () {
        enableHttps();
        connectedDomain($this->shop);

        $this->post('/apps/shop/certificate')->assertSessionHasNoErrors();

        Queue::assertPushed(IssueAppCertificate::class);
        expect(AuditLog::query()->where('action', 'tls.requested')->exists())->toBeTrue();
    });

    it('refuses a duplicate request while one is running', function () {
        enableHttps();
        connectedDomain($this->shop);
        certificateFor($this->shop, ['status' => CertificateStatus::Issuing, 'last_attempt_at' => now()->subMinute()]);

        $this->post('/apps/shop/certificate')->assertSessionHasErrors('certificate');

        Queue::assertNothingPushed();
    });

    it('shows certificate and HTTPS state on the pages', function () {
        enableHttps('ops@example.com');
        connectedDomain($this->shop);
        certificateFor($this->shop, ['staging' => true]);

        $this->get('/apps/shop')->assertInertia(fn (Assert $page) => $page
            ->where('https.enabled', true)
            ->where('app.certificate.status', 'active')
            ->where('app.certificate.staging', true)
            ->where('app.certificate.domains', ['shop.example.com']));

        $this->get('/domains')->assertInertia(fn (Assert $page) => $page
            ->where('https', ['enabled' => true, 'email' => 'ops@example.com', 'staging' => true]));
    });

    it('marks an interrupted attempt as failed', function () {
        $cert = certificateFor($this->shop, ['status' => CertificateStatus::Issuing]);

        (new IssueAppCertificate($this->shop->id))->failed(new RuntimeException('killed'));

        expect($cert->fresh()->status)->toBe(CertificateStatus::Failed);
    });
});

it('removes the certificate files when an app is deleted', function () {
    $this->actingAs(User::factory()->create());

    $this->delete('/apps/shop', ['confirm' => 'shop'])->assertRedirect('/apps');

    expect(collect($this->host->actions())->where('action', 'removeCertificate')->pluck('args.name')->all())->toContain('shop');
});

it('stores nothing without the user agreeing first', function () {
    expect(Setting::read('acme.tos_agreed_at'))->toBeNull();
});
