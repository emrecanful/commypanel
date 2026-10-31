<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * A real server starts with no database files and uses the database cache
 * store. A migration that clears the cache (the permission tables do) then
 * needs the cache database and its table to exist before the main database
 * is migrated. The test suite itself uses an in-memory cache, which hid this
 * once, so this runs the real command in a clean directory with production's stores.
 */
it('migrates from nothing with the database cache store', function () {
    $dir = storage_path('framework/testing/fresh-'.bin2hex(random_bytes(4)));
    File::ensureDirectoryExists($dir.'/storage/framework/cache');
    File::ensureDirectoryExists($dir.'/storage/framework/views');
    File::ensureDirectoryExists($dir.'/storage/logs');

    $env = [
        'APP_ENV' => 'production',
        'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
        'COMMYPANEL_DATA_PATH' => $dir,
        'LARAVEL_STORAGE_PATH' => $dir.'/storage',
        'HOST_DRIVER' => 'fake',
        'CACHE_STORE' => 'database',
        'SESSION_DRIVER' => 'database',
        'QUEUE_CONNECTION' => 'database',
        // Do not inherit the test suite's database overrides.
        'DB_MAIN_DATABASE' => $dir.'/db/main.sqlite',
        'DB_QUEUE_DATABASE' => $dir.'/db/queue.sqlite',
        'DB_CACHE_DATABASE' => $dir.'/db/cache.sqlite',
    ];

    $first = Process::path(base_path())->env($env)->run([PHP_BINARY, 'artisan', 'commypanel:migrate', '--force']);
    $second = Process::path(base_path())->env($env)->run([PHP_BINARY, 'artisan', 'commypanel:migrate', '--force']);

    expect($first->successful())->toBeTrue($first->output().$first->errorOutput())
        ->and($second->successful())->toBeTrue($second->output().$second->errorOutput())
        ->and($first->output())->toContain('create_permission_tables')
        ->and(file_exists($dir.'/db/main.sqlite') && file_exists($dir.'/db/cache.sqlite') && file_exists($dir.'/db/queue.sqlite'))->toBeTrue();

    File::deleteDirectory($dir);
});
