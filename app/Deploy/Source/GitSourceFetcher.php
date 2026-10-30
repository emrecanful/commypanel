<?php

namespace App\Deploy\Source;

use App\Deploy\DeploymentFailed;
use App\Enums\SourceType;
use App\Models\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class GitSourceFetcher implements SourceFetcher
{
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

        $keyFile = null;

        try {
            $result = Process::timeout((int) config('commypanel.deploy.git_timeout'))
                ->env($this->environment($app, $keyFile))
                ->run(['git', 'clone', '--depth', '1', '--single-branch', '--branch', $app->git_branch, '--', $url, $destination]);

            if ($result->failed()) {
                throw new DeploymentFailed('Could not fetch the repository: '.$this->summarise($result->errorOutput()));
            }
        } finally {
            if ($keyFile !== null) {
                File::delete($keyFile);
            }
        }

        $sha = Process::path($destination)->run(['git', 'rev-parse', 'HEAD']);

        // The history is not needed to build and must not end up in an image.
        File::deleteDirectory($destination.'/.git');

        return $sha->successful() ? trim($sha->output()) : 'unknown';
    }

    /**
     * @return array<string, string>
     */
    private function environment(App $app, ?string &$keyFile): array
    {
        $env = ['GIT_TERMINAL_PROMPT' => '0'];

        if ($app->deploy_key_private === null) {
            return $env;
        }

        $dir = rtrim((string) config('commypanel.data_path'), '/').'/tmp';
        File::ensureDirectoryExists($dir, 0700);

        $keyFile = $dir.'/key-'.bin2hex(random_bytes(8));
        File::put($keyFile, rtrim($app->deploy_key_private)."\n");
        chmod($keyFile, 0600);

        $knownHosts = $dir.'/known_hosts';

        $env['GIT_SSH_COMMAND'] = sprintf(
            'ssh -i %s -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new -o UserKnownHostsFile=%s',
            escapeshellarg($keyFile),
            escapeshellarg($knownHosts),
        );

        return $env;
    }

    private function summarise(string $output): string
    {
        $lines = array_filter(array_map('trim', explode("\n", $output)));

        return mb_substr((string) end($lines), 0, 300);
    }
}
