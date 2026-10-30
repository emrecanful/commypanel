<?php

use App\Audit\AuditLogger;
use App\Models\AuditLog;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use PragmaRX\Google2FA\Google2FA;

it('records login, failed login and logout with the ip address', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->post('/logout');

    expect(AuditLog::query()->orderBy('id')->pluck('action')->all())
        ->toBe(['auth.login_failed', 'auth.login', 'auth.logout']);

    $failed = AuditLog::query()->where('action', 'auth.login_failed')->first();
    expect($failed->metadata)->toBe(['email' => $user->email])
        ->and($failed->ip_address)->toBe('127.0.0.1');
});

it('records two-factor changes', function () {
    $user = User::factory()->create();
    $confirmed = ['auth.password_confirmed_at' => time()];

    $this->actingAs($user)->withSession($confirmed)->post('/user/two-factor-authentication');
    $code = (new Google2FA)->getCurrentOtp(decrypt($user->fresh()->two_factor_secret));
    $this->actingAs($user)->withSession($confirmed)->post('/user/confirmed-two-factor-authentication', ['code' => $code]);
    $this->actingAs($user)->withSession($confirmed)->delete('/user/two-factor-authentication');

    expect(AuditLog::query()->orderBy('id')->pluck('action')->all())->toBe([
        'auth.two_factor_enabled',
        'auth.two_factor_confirmed',
        'auth.two_factor_disabled',
    ]);
});

it('records password changes without storing passwords', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put('/user/password', [
        'current_password' => 'password',
        'password' => 'a-long-new-password-1',
        'password_confirmation' => 'a-long-new-password-1',
    ]);

    $log = AuditLog::query()->where('action', 'auth.password_changed')->firstOrFail();
    expect($log->user_email)->toBe($user->email)
        ->and(json_encode($log->getAttributes()))->not->toContain('a-long-new-password-1');
});

it('strips sensitive keys from metadata', function () {
    app(AuditLogger::class)->record('test.action', null, [
        'password' => 'hunter2',
        'code' => '123456',
        'name' => 'kept',
    ]);

    expect(AuditLog::query()->firstOrFail()->metadata)->toBe(['name' => 'kept']);
});

it('marks entries created from the console', function () {
    $user = User::factory()->create();

    $this->artisan('admin:reset', ['email' => $user->email])->assertSuccessful();

    $log = AuditLog::query()->where('action', 'admin.reset')->firstOrFail();
    expect($log->metadata)->toBe(['source' => 'cli', 'target' => $user->email]);
});

it('does not allow entries to be changed', function () {
    $log = app(AuditLogger::class)->record('test.action');

    expect(fn () => $log->update(['action' => 'tampered']))->toThrow(LogicException::class);
});

it('shows the audit log to a logged in admin only', function () {
    $this->get('/settings/audit-log')->assertRedirect('/login');

    app(AuditLogger::class)->record('test.action');

    $this->actingAs(User::factory()->create())
        ->get('/settings/audit-log')
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/audit-log')
            ->has('logs.data', 1)
            ->where('logs.data.0.action', 'test.action'));
});
