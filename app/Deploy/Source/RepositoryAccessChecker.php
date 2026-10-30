<?php

namespace App\Deploy\Source;

/**
 * Finds out whether this server can read a repository, using `git ls-remote`.
 * That works for every Git host (including self-hosted ones) without needing
 * any provider API or token, and it is the same access a deployment will use.
 */
class RepositoryAccessChecker
{
    private const TIMEOUT_SECONDS = 25;

    /** Text git prints when it was refused or could not find the repository. */
    private const REFUSED = [
        'authentication failed', 'could not read username', 'terminal prompts disabled',
        'repository not found', 'not found', 'permission denied', 'access denied', 'publickey',
        'invalid credentials', 'returned error: 401', 'returned error: 403', 'returned error: 404',
    ];

    private const NETWORK = [
        'could not resolve host', 'timed out', 'timeout', 'connection refused', 'network is unreachable',
        'no route to host', 'name or service not known', 'temporary failure in name resolution',
    ];

    public function __construct(private readonly GitRunner $git) {}

    public function check(string $url, string $branch, ?string $privateKey): AccessResult
    {
        $provider = GitProvider::fromUrl($url);

        if (! GitUrl::isValid($url, (bool) config('commypanel.deploy.git_allow_local'))) {
            return new AccessResult(AccessState::Invalid, 'That is not a valid Git address. Use an https:// or SSH address such as git@github.com:you/app.git.', $provider);
        }

        if (! GitUrl::isValidBranch($branch)) {
            return new AccessResult(AccessState::Invalid, 'That is not a valid branch name.', $provider);
        }

        $sshUrl = GitUrl::toSsh($url);

        if ($privateKey === null) {
            // SSH addresses cannot be read without a key, so there is nothing to try.
            if (GitUrl::isSsh($url)) {
                return new AccessResult(AccessState::KeyRequired, 'SSH addresses need a deploy key. Create one below and add it to the repository.', $provider, $sshUrl);
            }

            return $this->probe($url, $branch, null, $provider, $sshUrl, AccessState::Public);
        }

        return $this->probe($sshUrl ?? $url, $branch, $privateKey, $provider, $sshUrl, AccessState::KeyWorks);
    }

    private function probe(string $target, string $branch, ?string $privateKey, GitProvider $provider, ?string $sshUrl, AccessState $success): AccessResult
    {
        $result = $this->git->run(['ls-remote', '--heads', '--', $target], $privateKey, timeout: self::TIMEOUT_SECONDS);

        if ($result->successful()) {
            return $this->branchResult($result->output(), $branch, $provider, $sshUrl, $success);
        }

        $error = strtolower($result->errorOutput());

        if ($this->mentionsAny($error, self::NETWORK)) {
            return new AccessResult(AccessState::Unreachable, 'This server could not reach the Git host. Check the address and the server\'s internet connection.', $provider, $sshUrl);
        }

        if ($this->mentionsAny($error, self::REFUSED)) {
            return new AccessResult(
                AccessState::KeyRequired,
                $privateKey === null
                    ? 'This repository cannot be read without credentials. If it is private, add the deploy key below; if not, check the address.'
                    : 'The deploy key is not accepted yet. Add it to the repository (read-only is enough) and verify again.',
                $provider,
                $sshUrl,
            );
        }

        return new AccessResult(AccessState::Unreachable, 'Could not check the repository: '.$this->lastLine($result->errorOutput()), $provider, $sshUrl);
    }

    private function branchResult(string $output, string $branch, GitProvider $provider, ?string $sshUrl, AccessState $success): AccessResult
    {
        $branches = [];

        foreach (explode("\n", trim($output)) as $line) {
            if (preg_match('#\trefs/heads/(.+)$#', $line, $match) === 1) {
                $branches[] = $match[1];
            }
        }

        if (! in_array($branch, $branches, true)) {
            $found = $branches === [] ? 'The repository has no branches yet.' : 'Branches found: '.implode(', ', array_slice($branches, 0, 6)).'.';

            return new AccessResult(AccessState::BranchMissing, "The branch \"{$branch}\" does not exist. {$found}", $provider, $sshUrl, $branches);
        }

        return new AccessResult(
            $success,
            $success === AccessState::Public ? 'The repository is public. No key is needed.' : 'The deploy key works. This server can read the repository.',
            $provider,
            $sshUrl,
            $branches,
        );
    }

    /**
     * @param  list<string>  $needles
     */
    private function mentionsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function lastLine(string $output): string
    {
        $lines = array_filter(array_map('trim', explode("\n", $output)));

        return mb_substr((string) end($lines), 0, 200);
    }
}
