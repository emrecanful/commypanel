<?php

namespace App\Console\Commands;

use App\Actions\Setup\IssueSetupToken;
use App\Audit\AuditLogger;
use App\Models\User;
use Illuminate\Console\Command;

class SetupUrlCommand extends Command
{
    protected $signature = 'commypanel:setup-url';

    protected $description = 'Print a one-time URL to create the first admin account';

    public function handle(IssueSetupToken $issue, AuditLogger $audit): int
    {
        if (User::query()->exists()) {
            $this->components->error('Setup is already complete. To regain access run: php artisan admin:reset');

            return self::FAILURE;
        }

        $token = $issue();
        $audit->record('setup.url_issued', null, ['source' => 'cli']);

        $this->components->info('Open this URL to finish setting up CommyPanel:');
        $this->line('  '.url("/setup/{$token}"));
        $this->newLine();
        $this->line('It works once and expires in '.IssueSetupToken::LIFETIME_MINUTES.' minutes. Run this command again for a new one.');

        return self::SUCCESS;
    }
}
