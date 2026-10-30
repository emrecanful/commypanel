<?php

namespace App\Console\Commands;

use App\Audit\AuditLogger;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class AdminResetCommand extends Command
{
    protected $signature = 'admin:reset {email? : Email of the account to reset}';

    protected $description = 'Set a new random password for an admin and switch off its two-factor authentication';

    public function handle(AuditLogger $audit): int
    {
        $email = $this->argument('email') ?? $this->ask('Email of the account to reset');

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->components->error('No user with that email.');

            return self::FAILURE;
        }

        $password = Str::password(20, symbols: false);

        $user->forceFill([
            'password' => $password,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'remember_token' => null,
        ])->save();

        $audit->record('admin.reset', $user, ['source' => 'cli', 'target' => $user->email]);

        $this->components->info("Account {$user->email} reset. Two-factor authentication is now off.");
        $this->line("  New password: {$password}");
        $this->line('Log in and change it, then enable two-factor authentication again.');

        return self::SUCCESS;
    }
}
