<?php

namespace App\Console\Commands;

use App\Auth\SyncRolesAndPermissions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class MigrateCommand extends Command
{
    protected $signature = 'commypanel:migrate {--force : Run in production without confirmation}';

    protected $description = 'Create the panel SQLite files and migrate the main, queue and cache databases';

    /**
     * Connection name => migration path (null uses the default path).
     *
     * @var array<string, string|null>
     */
    private const DATABASES = [
        'main' => null,
        'queue' => 'database/migrations/queue',
        'cache' => 'database/migrations/cache',
    ];

    public function handle(): int
    {
        foreach (self::DATABASES as $connection => $path) {
            $this->ensureDatabaseFile($connection);

            $arguments = ['--database' => $connection, '--force' => (bool) $this->option('force')];

            if ($path !== null) {
                $arguments['--path'] = $path;
            }

            $this->components->info("Migrating [{$connection}]");

            $status = $this->call('migrate', $arguments);

            if ($status !== self::SUCCESS) {
                return $status;
            }
        }

        // New releases may add permissions; keep them and the admin role current.
        // Before the first user exists there is nothing to protect yet, and the setup
        // step creates them, but doing it here as well is harmless.
        app(SyncRolesAndPermissions::class)();

        return self::SUCCESS;
    }

    private function ensureDatabaseFile(string $connection): void
    {
        $file = config("database.connections.{$connection}.database");

        if ($file === ':memory:') {
            return;
        }

        File::ensureDirectoryExists(dirname($file), 0700);

        if (! File::exists($file)) {
            File::put($file, '');
            chmod($file, 0600);
        }

        DB::purge($connection);
    }
}
