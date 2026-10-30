<?php

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

it('migrates three separate sqlite files in wal mode', function () {
    $dir = storage_path('framework/testing/db-'.uniqid());

    foreach (['main', 'queue', 'cache'] as $name) {
        config(["database.connections.{$name}.database" => "{$dir}/{$name}.sqlite"]);
        DB::purge($name);
    }

    try {
        $this->artisan('commypanel:migrate')->assertSuccessful();

        expect(Schema::connection('main')->hasTable('users'))->toBeTrue()
            ->and(Schema::connection('main')->hasTable('jobs'))->toBeFalse()
            ->and(Schema::connection('queue')->hasTable('jobs'))->toBeTrue()
            ->and(Schema::connection('queue')->hasTable('failed_jobs'))->toBeTrue()
            ->and(Schema::connection('cache')->hasTable('cache'))->toBeTrue()
            ->and(Schema::connection('cache')->hasTable('sessions'))->toBeTrue();

        foreach (['main', 'queue', 'cache'] as $name) {
            expect(DB::connection($name)->selectOne('PRAGMA journal_mode')->journal_mode)->toBe('wal');
            expect(decoct(fileperms("{$dir}/{$name}.sqlite") & 0777))->toBe('600');
        }
    } finally {
        foreach (['main', 'queue', 'cache'] as $name) {
            DB::purge($name);
        }
        File::deleteDirectory($dir);
    }
});

it('points queue, cache and sessions at their own connections', function () {
    expect(config('database.default'))->toBe('main')
        ->and(config('queue.connections.database.connection'))->toBe('queue')
        ->and(config('queue.failed.database'))->toBe('queue')
        ->and(config('cache.stores.database.connection'))->toBe('cache')
        ->and(config('session.connection'))->toBe('cache');
});

it('tells the user to run commypanel:migrate when a database file is missing', function () {
    config([
        'app.debug' => true,
        'database.connections.cache.database' => storage_path('framework/testing/missing-'.uniqid().'.sqlite'),
    ]);
    DB::purge('cache');

    try {
        DB::connection('cache')->select('select 1');
    } catch (Throwable $e) {
        $request = Request::create('/', 'GET', server: ['HTTP_ACCEPT' => 'application/json']);
        $response = app(ExceptionHandler::class)->render($request, $e);

        expect($response->getContent())->toContain('php artisan commypanel:migrate');

        return;
    } finally {
        DB::purge('cache');
    }

    $this->fail('Expected a query exception for the missing database file.');
});
