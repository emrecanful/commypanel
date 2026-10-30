<?php

use App\Deploy\Source\ArchiveExtractor;
use App\Deploy\Source\InvalidArchive;
use Illuminate\Support\Facades\File;
use Tests\Support\Archives;

beforeEach(function () {
    $this->base = storage_path('framework/testing/archives-'.bin2hex(random_bytes(4)));
    $this->dest = $this->base.'/out';
    File::ensureDirectoryExists($this->base);

    $this->zip = fn (array $files, array $modes = []) => Archives::zip($this->base.'/upload.zip', $files, $modes);
    $this->tgz = function (array $entries) {
        file_put_contents($this->base.'/upload.bin', Archives::tarGz($entries));

        return $this->base.'/upload.bin';
    };
    $this->extract = fn (string $archive) => app(ArchiveExtractor::class)->extract($archive, $this->dest);
    $this->listing = fn () => collect(File::allFiles($this->dest, true))->map->getRelativePathname()->sort()->values()->all();
});

afterEach(fn () => File::deleteDirectory($this->base));

describe('zip', function () {
    it('unpacks a project', function () {
        $count = ($this->extract)(($this->zip)(['go.mod' => 'module x', 'main.go' => 'package main', 'cmd/app/run.go' => 'x']));

        expect($count)->toBe(3)
            ->and(($this->listing)())->toBe(['cmd/app/run.go', 'go.mod', 'main.go'])
            ->and(file_get_contents($this->dest.'/go.mod'))->toBe('module x');
    });

    it('unwraps a single top-level folder', function () {
        ($this->extract)(($this->zip)(['shop-main/' => '', 'shop-main/go.mod' => 'm', 'shop-main/src/a.go' => 'a']));

        expect(($this->listing)())->toBe(['go.mod', 'src/a.go']);
    });

    it('leaves out macOS metadata and git history, and keeps executables', function () {
        ($this->extract)(($this->zip)(
            ['app.sh' => '#!/bin/sh', '__MACOSX/._app.sh' => 'x', '.DS_Store' => 'x', 'sub/.DS_Store' => 'x', '.git/config' => 'x', 'go.mod' => 'm'],
            ['app.sh' => 0100755],
        ));

        expect(($this->listing)())->toBe(['app.sh', 'go.mod'])
            ->and(is_executable($this->dest.'/app.sh'))->toBeTrue()
            ->and(is_executable($this->dest.'/go.mod'))->toBeFalse();
    });

    it('refuses path traversal and absolute paths, and writes nothing outside', function (string $name) {
        expect(fn () => ($this->extract)(($this->zip)([$name => 'evil', 'go.mod' => 'm'])))
            ->toThrow(InvalidArchive::class, 'unsafe path');

        expect(file_exists($this->base.'/evil.txt'))->toBeFalse()
            ->and(file_exists('/tmp/commypanel-evil.txt'))->toBeFalse();
    })->with([
        'parent' => '../evil.txt',
        'deep parent' => 'a/../../evil.txt',
        'windows parent' => '..\\evil.txt',
        'absolute' => '/tmp/commypanel-evil.txt',
        'drive letter' => 'C:/evil.txt',
    ]);

    it('refuses symbolic links', function () {
        expect(fn () => ($this->extract)(($this->zip)(['go.mod' => 'm', 'link' => '/etc/passwd'], ['link' => 0120777])))
            ->toThrow(InvalidArchive::class, 'link');
    });

    it('refuses an archive that unpacks to too much, even if it compresses tiny', function () {
        config(['commypanel.uploads.max_unpacked_mb' => 1]);

        expect(fn () => ($this->extract)(($this->zip)(['big.bin' => str_repeat("\0", 3 * 1024 * 1024)])))
            ->toThrow(InvalidArchive::class, 'too large');
    });

    it('refuses too many files', function () {
        config(['commypanel.uploads.max_files' => 5]);
        $files = [];
        foreach (range(1, 8) as $i) {
            $files["f{$i}.txt"] = 'x';
        }

        expect(fn () => ($this->extract)(($this->zip)($files)))->toThrow(InvalidArchive::class, 'too large');
    });

    it('refuses an empty archive', function () {
        file_put_contents($this->base.'/empty.zip', "PK\x05\x06".str_repeat("\0", 18));

        expect(fn () => ($this->extract)($this->base.'/empty.zip'))->toThrow(InvalidArchive::class, 'empty');
    });

    it('reports a damaged zip', function () {
        file_put_contents($this->base.'/bad.zip', "PK\x03\x04 this is not really a zip");

        expect(fn () => ($this->extract)($this->base.'/bad.zip'))->toThrow(InvalidArchive::class);
    });
});

describe('tar.gz', function () {
    it('unpacks a project and unwraps its top-level folder', function () {
        $count = ($this->extract)(($this->tgz)([
            ['name' => 'shop-main/', 'type' => '5', 'mode' => 0755],
            ['name' => 'shop-main/go.mod', 'content' => 'module x'],
            ['name' => 'shop-main/src/', 'type' => '5', 'mode' => 0755],
            ['name' => 'shop-main/src/main.go', 'content' => 'package main'],
        ]));

        expect($count)->toBe(2)
            ->and(($this->listing)())->toBe(['go.mod', 'src/main.go']);
    });

    it('does not depend on the file name', function () {
        // PHP stores uploads under random names without an extension.
        expect(($this->extract)(($this->tgz)([['name' => 'go.mod', 'content' => 'm']])))->toBe(1);
    });

    it('leaves out junk and keeps executables', function () {
        ($this->extract)(($this->tgz)([
            ['name' => 'run.sh', 'content' => '#!/bin/sh', 'mode' => 0755],
            ['name' => '.DS_Store', 'content' => 'x'],
            ['name' => '.git/HEAD', 'content' => 'ref'],
            ['name' => 'go.mod', 'content' => 'm'],
        ]));

        expect(($this->listing)())->toBe(['go.mod', 'run.sh'])
            ->and(is_executable($this->dest.'/run.sh'))->toBeTrue();
    });

    it('refuses path traversal and absolute paths, and writes nothing outside', function (string $name) {
        expect(fn () => ($this->extract)(($this->tgz)([['name' => 'go.mod', 'content' => 'm'], ['name' => $name, 'content' => 'evil']])))
            ->toThrow(InvalidArchive::class, 'unsafe path');

        expect(file_exists($this->base.'/evil.txt'))->toBeFalse()
            ->and(file_exists('/tmp/commypanel-evil.txt'))->toBeFalse()
            ->and(is_dir($this->dest) ? ($this->listing)() : [])->toBe([]);
    })->with([
        'parent' => '../evil.txt',
        'deep parent' => 'a/../../evil.txt',
        'absolute' => '/tmp/commypanel-evil.txt',
    ]);

    it('refuses symbolic links, hard links and special files, before extracting anything', function (string $type) {
        expect(fn () => ($this->extract)(($this->tgz)([
            ['name' => 'go.mod', 'content' => 'm'],
            ['name' => 'link', 'type' => $type, 'link' => '/etc/passwd'],
        ])))->toThrow(InvalidArchive::class, 'link or special file');

        expect(is_dir($this->dest) ? ($this->listing)() : [])->toBe([]);
    })->with([
        'symlink' => '2',
        'hard link' => '1',
        'character device' => '3',
        'fifo' => '6',
    ]);

    it('refuses an archive that unpacks to too much', function () {
        config(['commypanel.uploads.max_unpacked_mb' => 1]);

        expect(fn () => ($this->extract)(($this->tgz)([['name' => 'big.bin', 'content' => str_repeat("\0", 3 * 1024 * 1024)]])))
            ->toThrow(InvalidArchive::class, 'too large');
    });

    it('refuses too many files', function () {
        config(['commypanel.uploads.max_files' => 3]);
        $entries = array_map(fn (int $i) => ['name' => "f{$i}.txt", 'content' => 'x'], range(1, 6));

        expect(fn () => ($this->extract)(($this->tgz)($entries)))->toThrow(InvalidArchive::class, 'too large');
    });

    it('reports a damaged archive', function () {
        file_put_contents($this->base.'/bad.tgz', "\x1f\x8b\x08\x00 not really gzip data at all");

        expect(fn () => ($this->extract)($this->base.'/bad.tgz'))->toThrow(InvalidArchive::class);
    });
});

it('refuses files that are not archives, whatever they are called', function () {
    file_put_contents($this->base.'/project.zip', 'just some text');

    expect(fn () => ($this->extract)($this->base.'/project.zip'))->toThrow(InvalidArchive::class, 'not a .zip');
});
