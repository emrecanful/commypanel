<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('sets a new password and turns off 2FA', function () {
    $user = User::factory()->create();
    $user->forceFill([
        'two_factor_secret' => encrypt('secret'),
        'two_factor_recovery_codes' => encrypt('[]'),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->artisan('admin:reset', ['email' => $user->email])->assertSuccessful();

    $user->refresh();
    expect(Hash::check('password', $user->password))->toBeFalse()
        ->and($user->two_factor_secret)->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull();
});

it('fails for an unknown email', function () {
    $this->artisan('admin:reset', ['email' => 'nobody@example.com'])->assertFailed();
});
