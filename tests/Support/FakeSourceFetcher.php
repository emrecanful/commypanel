<?php

namespace Tests\Support;

use App\Deploy\Source\SourceFetcher;
use App\Models\App;
use Illuminate\Support\Facades\File;
use Throwable;

class FakeSourceFetcher implements SourceFetcher
{
    /**
     * @param  array<string, string>  $files
     */
    public function __construct(
        public array $files = [
            'go.mod' => "module acme/api\n\ngo 1.23\n",
            'main.go' => "package main\n\nfunc main() {}\n",
        ],
        public string $sha = 'abc123def456',
        public ?Throwable $throw = null,
    ) {}

    public function fetch(App $app, string $destination): string
    {
        if ($this->throw !== null) {
            throw $this->throw;
        }

        foreach ($this->files as $path => $contents) {
            File::ensureDirectoryExists(dirname("{$destination}/{$path}"));
            File::put("{$destination}/{$path}", $contents);
        }

        return $this->sha;
    }
}
