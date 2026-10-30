<?php

namespace App\Deploy\Source;

use App\Deploy\DeploymentFailed;
use App\Enums\SourceType;
use App\Models\App;
use Illuminate\Support\Facades\File;

class GitSourceFetcher implements SourceFetcher
{
    public function __construct(private readonly GitRunner $git) {}

    public function fetch(App $app, string $destination): string
    {
        if ($app->source_type !== SourceType::Git) {
            throw new DeploymentFailed('Only Git sources can be deployed at the moment.');
        }

        $url = (string) $app->git_url;

        if (! GitUrl::isValid($url, (bool) config('commypanel.deploy.git_allow_local'))) {
            throw new DeploymentFailed('The Git URL is not valid. Use an https:// or ssh URL.');
        }

        if (! GitUrl::isValidBranch($app->git_branch)) {
            throw new DeploymentFailed('The Git branch name is not valid.');
        }

        $result = $this->git->run(
            ['clone', '--depth', '1', '--single-branch', '--branch', $app->git_branch, '--', $url, $destination],
            $app->deploy_key_private,
        );

        if ($result->failed()) {
            throw new DeploymentFailed('Could not fetch the repository: '.$this->summarise($result->errorOutput()));
        }

        $sha = $this->git->run(['rev-parse', 'HEAD'], cwd: $destination);

        // The history is not needed to build and must not end up in an image.
        File::deleteDirectory($destination.'/.git');

        return $sha->successful() ? trim($sha->output()) : 'unknown';
    }

    private function summarise(string $output): string
    {
        $lines = array_filter(array_map('trim', explode("\n", $output)));

        return mb_substr((string) end($lines), 0, 300);
    }
}
