<?php

namespace App\Console\Commands;

use App\Models\App;
use App\Tls\TlsPlanner;
use Illuminate\Console\Command;

class RenewCertificatesCommand extends Command
{
    protected $signature = 'commypanel:renew-certificates';

    protected $description = 'Queue certificates that are missing, expiring soon, failed earlier or need new domains';

    public function handle(TlsPlanner $planner): int
    {
        $queued = 0;

        foreach (App::query()->has('domains')->cursor() as $app) {
            $queued += $planner->ensure($app) ? 1 : 0;
        }

        $this->components->info("Queued {$queued} certificate(s).");

        return self::SUCCESS;
    }
}
