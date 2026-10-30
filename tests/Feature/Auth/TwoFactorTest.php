<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use PragmaRX\Google2FA\Google2FA;

function userWithTwoFactor(): array
{
    $secret = (new Google2FA)->generateSecretKey();

    $user = User::factory()->create();
    $user->forceFill([
        'two_factor_secret' => encrypt($secret),
        'two_factor_recovery_codes' => encrypt(json_encode(['recovery-one', 'recovery-two'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    return [$user, $secret];
}

it('asks for a code after the password when 2FA is enabled', function () {
    [$user] = userWithTwoFactor();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/two-factor-challenge');
    $this->assertGuest();

    $this->get('/two-factor-challenge')
        ->assertInertia(fn (Assert $page) => $page->component('auth/two-factor-challenge'));
});

it('completes login with a valid authenticator code', function () {
    [$user, $secret] = userWithTwoFactor();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->post('/two-factor-challenge', ['code' => (new Google2FA)->getCurrentOtp($secret)])
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
});

it('rejects an invalid authenticator code', function () {
    [$user] = userWithTwoFactor();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->post('/two-factor-challenge', ['code' => '000000'])->assertSessionHasErrors('code');

    $this->assertGuest();
});

it('completes login with a recovery code and consumes it', function () {
    [$user] = userWithTwoFactor();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->post('/two-factor-challenge', ['recovery_code' => 'recovery-one'])
        ->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user);

    expect(json_decode(decrypt($user->fresh()->two_factor_recovery_codes)))->not->toContain('recovery-one');
});

it('enables and confirms 2FA after password confirmation', function () {
    $user = User::factory()->create();
    $confirmed = ['auth.password_confirmed_at' => time()];

    $this->actingAs($user)->withSession($confirmed)->post('/user/two-factor-authentication')->assertRedirect();

    $user->refresh();
    expect($user->two_factor_secret)->not->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull();

    $code = (new Google2FA)->getCurrentOtp(decrypt($user->two_factor_secret));
    $this->actingAs($user)->withSession($confirmed)->post('/user/confirmed-two-factor-authentication', ['code' => $code]);

    expect($user->fresh()->two_factor_confirmed_at)->not->toBeNull();
});

it('requires password confirmation before changing 2FA', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/user/two-factor-authentication')->assertStatus(423);

    expect($user->fresh()->two_factor_secret)->toBeNull();
});
