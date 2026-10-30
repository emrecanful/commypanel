<?php

namespace App\Deploy\Source;

use App\Deploy\DeploymentFailed;
use App\Models\App;

interface SourceFetcher
{
    /**
     * Put the app's source into the (empty) destination directory.
     *
     * @return string the commit identifier that was fetched
     *
     * @throws DeploymentFailed
     */
    public function fetch(App $app, string $destination): string;
}
