<?php

use App\Deploy\Source\GitProvider;
use App\Deploy\Source\GitUrl;

it('reads host and path from every kind of Git address', function (string $url, ?string $host, ?string $path) {
    expect(GitUrl::host($url))->toBe($host)->and(GitUrl::path($url))->toBe($path);
})->with([
    'https' => ['https://github.com/acme/shop.git', 'github.com', 'acme/shop'],
    'https without .git' => ['https://github.com/acme/shop', 'github.com', 'acme/shop'],
    'https with port' => ['https://git.example.com:8443/team/app.git', 'git.example.com', 'team/app'],
    'gitlab subgroup' => ['https://gitlab.com/group/sub/app.git', 'gitlab.com', 'group/sub/app'],
    'scp style' => ['git@github.com:acme/shop.git', 'github.com', 'acme/shop'],
    'ssh url' => ['ssh://git@bitbucket.org:7999/team/app.git', 'bitbucket.org', 'team/app'],
    'local path' => ['/srv/repo', null, null],
]);

it('derives the SSH address a deploy key needs', function (string $url, ?string $expected) {
    expect(GitUrl::toSsh($url))->toBe($expected);
})->with([
    'github https' => ['https://github.com/acme/shop', 'git@github.com:acme/shop.git'],
    'github https .git' => ['https://github.com/acme/shop.git', 'git@github.com:acme/shop.git'],
    'gitlab subgroup' => ['https://gitlab.com/group/sub/app', 'git@gitlab.com:group/sub/app.git'],
    'bitbucket' => ['https://bitbucket.org/team/app.git', 'git@bitbucket.org:team/app.git'],
    'already ssh' => ['git@github.com:acme/shop.git', 'git@github.com:acme/shop.git'],
    'local path' => ['/srv/repo', null],
]);

it('tells SSH addresses from https ones', function () {
    expect(GitUrl::isSsh('git@github.com:acme/shop.git'))->toBeTrue()
        ->and(GitUrl::isSsh('ssh://git@host/a/b.git'))->toBeTrue()
        ->and(GitUrl::isSsh('https://github.com/acme/shop'))->toBeFalse();
});

it('recognises the big providers and falls back for everything else', function () {
    expect(GitProvider::fromUrl('https://github.com/a/b'))->toBe(GitProvider::GitHub)
        ->and(GitProvider::fromUrl('git@gitlab.com:a/b.git'))->toBe(GitProvider::GitLab)
        ->and(GitProvider::fromUrl('https://bitbucket.org/a/b'))->toBe(GitProvider::Bitbucket)
        ->and(GitProvider::fromUrl('https://git.example.com/a/b'))->toBe(GitProvider::Other);
});

it('links straight to where each provider takes deploy keys', function () {
    expect(GitProvider::GitHub->deployKeyInstructions('https://github.com/acme/shop.git')['url'])
        ->toBe('https://github.com/acme/shop/settings/keys/new')
        ->and(GitProvider::GitLab->deployKeyInstructions('https://gitlab.com/group/app')['url'])
        ->toBe('https://gitlab.com/group/app/-/settings/repository')
        ->and(GitProvider::Bitbucket->deployKeyInstructions('https://bitbucket.org/team/app')['url'])
        ->toBe('https://bitbucket.org/team/app/admin/access-keys/')
        ->and(GitProvider::Other->deployKeyInstructions('https://git.example.com/a/b')['url'])->toBeNull();

    foreach (GitProvider::cases() as $provider) {
        expect($provider->deployKeyInstructions('https://github.com/a/b')['steps'])->not->toBeEmpty();
    }
});
