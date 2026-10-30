<?php

use App\Actions\Setup\IssueSetupToken;
use App\Models\SetupToken;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function validSetupInput(array $overrides = []): array
{
    return [
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'a-long-password-123',
        'password_confirmation' => 'a-long-password-123',
        ...$overrides,
    ];
}

it('prints a setup url that renders the setup page', function () {
    $this->artisan('commypanel:setup-url')->assertSuccessful();

    $token = app(IssueSetupToken::class)();

    $this->get("/setup/{$token}")
        ->assertInertia(fn (Assert $page) => $page->component('auth/setup')->where('token', $token));
});

it('creates the first admin, logs in and sends them to the welcome steps', function () {
    $token = app(IssueSetupToken::class)();

    $this->post("/setup/{$token}", validSetupInput())
        ->assertRedirect('/welcome');

    $user = User::query()->firstOrFail();
    expect($user->email)->toBe('admin@example.com');
    $this->assertAuthenticatedAs($user);
});

it('works only once', function () {
    $token = app(IssueSetupToken::class)();

    $this->post("/setup/{$token}", validSetupInput());
    auth()->logout();

    $this->get("/setup/{$token}")->assertNotFound();
    $this->post("/setup/{$token}", validSetupInput(['email' => 'second@example.com']))->assertNotFound();
    expect(User::query()->count())->toBe(1);
});

it('rejects unknown and expired tokens', function () {
    $this->get('/setup/not-a-real-token')->assertNotFound();

    $token = app(IssueSetupToken::class)();
    SetupToken::query()->update(['expires_at' => now()->subMinute()]);

    $this->get("/setup/{$token}")->assertNotFound();
});

it('invalidates earlier tokens when a new one is issued', function () {
    $first = app(IssueSetupToken::class)();
    $second = app(IssueSetupToken::class)();

    $this->get("/setup/{$first}")->assertNotFound();
    $this->get("/setup/{$second}")->assertOk();
});

it('is disabled once any user exists', function () {
    $token = app(IssueSetupToken::class)();
    User::factory()->create();

    $this->get("/setup/{$token}")->assertNotFound();
    $this->artisan('commypanel:setup-url')->assertFailed();
});

it('stores only a hash of the token', function () {
    $token = app(IssueSetupToken::class)();

    expect(SetupToken::query()->value('token_hash'))->not->toBe($token)
        ->and(SetupToken::query()->value('token_hash'))->toBe(hash('sha256', $token));
});

it('validates the admin details', function () {
    $token = app(IssueSetupToken::class)();

    $this->post("/setup/{$token}", validSetupInput(['password_confirmation' => 'different']))
        ->assertSessionHasErrors('password');
    $this->post("/setup/{$token}", validSetupInput(['email' => 'nope']))
        ->assertSessionHasErrors('email');

    expect(User::query()->count())->toBe(0);
});
