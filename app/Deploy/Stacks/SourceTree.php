<?php

namespace App\Deploy\Stacks;

/**
 * Read-only helpers for inspecting an unpacked source directory.
 */
final class SourceTree
{
    private const SKIPPED = ['.git', 'node_modules', 'bin', 'obj', 'vendor', '.vs'];

    /** A path is interpolated into a Dockerfile, so only plain characters are allowed. */
    private const SAFE_PATH = '/^[A-Za-z0-9._\/-]+$/';

    public static function has(string $root, string $relative): bool
    {
        return is_file($root.'/'.$relative);
    }

    public static function read(string $root, string $relative): ?string
    {
        $path = $root.'/'.$relative;

        return is_file($path) ? (string) file_get_contents($path) : null;
    }

    /**
     * Files with the given extension up to $depth directories deep, as
     * paths relative to $root.
     *
     * @return list<string>
     */
    public static function find(string $root, string $extension, int $depth = 2): array
    {
        $found = [];
        self::walk($root, '', $extension, $depth, $found);
        sort($found);

        return $found;
    }

    /**
     * @param  list<string>  $found
     */
    private static function walk(string $root, string $relative, string $extension, int $depth, array &$found): void
    {
        $entries = scandir($root.($relative === '' ? '' : '/'.$relative)) ?: [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || in_array($entry, self::SKIPPED, true)) {
                continue;
            }

            $child = $relative === '' ? $entry : $relative.'/'.$entry;
            $full = $root.'/'.$child;

            if (is_link($full)) {
                continue;
            }

            if (is_dir($full)) {
                if ($depth > 0) {
                    self::walk($root, $child, $extension, $depth - 1, $found);
                }
            } elseif (str_ends_with($entry, '.'.$extension)) {
                $found[] = $child;
            }
        }
    }

    /**
     * @throws DetectionFailed
     */
    public static function safePath(string $path): string
    {
        if (! preg_match(self::SAFE_PATH, $path) || str_contains($path, '..')) {
            throw new DetectionFailed("The path \"{$path}\" contains characters that are not supported.");
        }

        return $path;
    }
}
