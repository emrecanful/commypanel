<?php

namespace App\Deploy\Source;

final class GitUrl
{
    /**
     * Only network transports are accepted by default. Anything that git
     * could interpret as an option, a helper (ext::) or a local path is refused.
     */
    public static function isValid(string $url, bool $allowLocal = false): bool
    {
        if ($url === '' || strlen($url) > 500 || preg_match('/[\s\x00-\x1f\x7f]/', $url) || str_starts_with($url, '-')) {
            return false;
        }

        return match (true) {
            (bool) preg_match('#^https://[A-Za-z0-9.-]+(:\d+)?/[A-Za-z0-9._~/%+-]+$#', $url) => true,
            (bool) preg_match('#^ssh://([A-Za-z0-9._-]+@)?[A-Za-z0-9.-]+(:\d+)?/[A-Za-z0-9._~/%+-]+$#', $url) => true,
            (bool) preg_match('#^[A-Za-z0-9._-]+@[A-Za-z0-9.-]+:[A-Za-z0-9._~/%+-]+$#', $url) => true,
            $allowLocal => str_starts_with($url, '/') && ! str_contains($url, '..'),
            default => false,
        };
    }

    public static function isValidBranch(string $branch): bool
    {
        return (bool) preg_match('#^[A-Za-z0-9._/-]{1,100}$#', $branch)
            && ! str_starts_with($branch, '-')
            && ! str_contains($branch, '..');
    }
}
