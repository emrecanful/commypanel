<?php

namespace App\Deploy\Source;

enum GitProvider: string
{
    case GitHub = 'github';
    case GitLab = 'gitlab';
    case Bitbucket = 'bitbucket';
    case Other = 'other';

    public static function fromUrl(string $url): self
    {
        return match (GitUrl::host($url)) {
            'github.com' => self::GitHub,
            'gitlab.com' => self::GitLab,
            'bitbucket.org' => self::Bitbucket,
            default => self::Other,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::GitHub => 'GitHub',
            self::GitLab => 'GitLab',
            self::Bitbucket => 'Bitbucket',
            self::Other => 'your Git server',
        };
    }

    /**
     * Where to add the deploy key and what to click, for this provider.
     *
     * @return array{url: string|null, steps: list<string>}
     */
    public function deployKeyInstructions(string $repositoryUrl): array
    {
        $path = GitUrl::path($repositoryUrl);
        $host = GitUrl::host($repositoryUrl);

        return match ($this) {
            self::GitHub => [
                'url' => $path === null ? null : "https://github.com/{$path}/settings/keys/new",
                'steps' => [
                    'Open the repository on GitHub: Settings, then Deploy keys, then Add deploy key.',
                    'Give it any title (for example "CommyPanel") and paste the key above.',
                    'Leave "Allow write access" switched off, then click Add key.',
                ],
            ],
            self::GitLab => [
                'url' => $path === null ? null : "https://{$host}/{$path}/-/settings/repository",
                'steps' => [
                    'Open the project on GitLab: Settings, then Repository, then Deploy keys, then Add new key.',
                    'Give it a title (for example "CommyPanel") and paste the key above.',
                    'Leave "Grant write permissions" switched off, then click Add key.',
                ],
            ],
            self::Bitbucket => [
                'url' => $path === null ? null : "https://bitbucket.org/{$path}/admin/access-keys/",
                'steps' => [
                    'Open the repository on Bitbucket: Repository settings, then Access keys, then Add key.',
                    'Give it a label (for example "CommyPanel") and paste the key above.',
                    'Click Add SSH key. Access keys are read-only.',
                ],
            ],
            self::Other => [
                'url' => null,
                'steps' => [
                    'Add the key above to your repository as a read-only deploy key. The setting is usually under the repository\'s Settings.',
                    'Make sure this server can reach your Git server over SSH.',
                ],
            ],
        };
    }
}
