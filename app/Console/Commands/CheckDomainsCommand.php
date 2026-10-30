<?php

namespace App\Console\Commands;

use App\Dns\CheckDomain;
use App\Enums\DomainStatus;
use App\Models\Domain;
use Illuminate\Console\Command;

class CheckDomainsCommand extends Command
{
    protected $signature = 'commypanel:check-domains';

    protected $description = 'Re-check DNS for domains that are not connected yet, and connected ones hourly';

    public function handle(CheckDomain $check): int
    {
        $domains = Domain::query()
            ->where(fn ($query) => $query
                ->where('status', '!=', DomainStatus::Verified)
                ->orWhereNull('last_checked_at')
                ->orWhere('last_checked_at', '<', now()->subHour()))
            ->get();

        foreach ($domains as $domain) {
            $check($domain);
        }

        $this->components->info("Checked {$domains->count()} domain(s).");

        return self::SUCCESS;
    }
}
