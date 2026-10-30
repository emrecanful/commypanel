<?php

namespace App\Audit;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Events\Dispatcher;
use Laravel\Fortify\Events\PasswordUpdatedViaController;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;

/**
 * Turns authentication and security events into audit log entries.
 */
class AuditEventSubscriber
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @return array<class-string, callable>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => fn (Login $e) => $this->audit->record('auth.login', $this->user($e->user)),
            Logout::class => fn (Logout $e) => $this->audit->record('auth.logout', $this->user($e->user)),
            Failed::class => fn (Failed $e) => $this->audit->record(
                'auth.login_failed',
                null,
                ['email' => $e->credentials['email'] ?? null],
            ),
            TwoFactorAuthenticationFailed::class => fn ($e) => $this->audit->record('auth.two_factor_failed', $this->user($e->user)),
            TwoFactorAuthenticationEnabled::class => fn ($e) => $this->audit->record('auth.two_factor_enabled', $this->user($e->user)),
            TwoFactorAuthenticationConfirmed::class => fn ($e) => $this->audit->record('auth.two_factor_confirmed', $this->user($e->user)),
            TwoFactorAuthenticationDisabled::class => fn ($e) => $this->audit->record('auth.two_factor_disabled', $this->user($e->user)),
            RecoveryCodesGenerated::class => fn ($e) => $this->audit->record('auth.recovery_codes_generated', $this->user($e->user)),
            RecoveryCodeReplaced::class => fn ($e) => $this->audit->record('auth.recovery_code_used', $this->user($e->user)),
            PasswordUpdatedViaController::class => fn ($e) => $this->audit->record('auth.password_changed', $this->user($e->user)),
        ];
    }

    private function user(mixed $user): ?User
    {
        return $user instanceof User ? $user : null;
    }
}
