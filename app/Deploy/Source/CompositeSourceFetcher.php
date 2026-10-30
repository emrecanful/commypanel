<?php

namespace App\Deploy\Source;

use App\Enums\SourceType;
use App\Models\App;

class CompositeSourceFetcher implements SourceFetcher
{
    public function __construct(
        private readonly GitSourceFetcher $git,
        private readonly UploadSourceFetcher $upload,
    ) {}

    public function fetch(App $app, string $destination): string
    {
        return match ($app->source_type) {
            SourceType::Upload => $this->upload->fetch($app, $destination),
            default => $this->git->fetch($app, $destination),
        };
    }
}
