<?php

namespace App\Deploy\Source;

use App\Deploy\DeploymentFailed;
use App\Models\App;

class UploadSourceFetcher implements SourceFetcher
{
    public function __construct(private readonly UploadStorage $storage) {}

    public function fetch(App $app, string $destination): string
    {
        if (! $this->storage->has($app)) {
            throw new DeploymentFailed('No uploaded project found. Upload a .zip or .tar.gz first.');
        }

        $this->storage->copyTo($app, $destination);

        return 'upload-'.($app->source_verified_at?->format('YmdHis') ?? 'unknown');
    }
}
