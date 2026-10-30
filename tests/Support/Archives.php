<?php

namespace Tests\Support;

use ZipArchive;

/**
 * Builds archives for tests, including hostile ones that the usual tools
 * refuse to create (path traversal, links, huge contents).
 */
final class Archives
{
    /**
     * @param  list<array{name: string, content?: string, type?: string, link?: string, mode?: int}>  $entries
     *                                                                                                          type: '0' file, '5' directory, '2' symlink, '1' hard link
     */
    public static function tarGz(array $entries): string
    {
        $tar = '';

        foreach ($entries as $entry) {
            $content = $entry['content'] ?? '';
            $type = $entry['type'] ?? '0';
            $tar .= self::header($entry['name'], strlen($content), $type, $entry['link'] ?? '', $entry['mode'] ?? 0644);

            if ($content !== '') {
                $tar .= str_pad($content, (int) (ceil(strlen($content) / 512) * 512), "\0");
            }
        }

        return (string) gzencode($tar.str_repeat("\0", 1024));
    }

    private static function header(string $name, int $size, string $type, string $link, int $mode): string
    {
        $header = str_pad($name, 100, "\0")
            .sprintf("%07o\0", $mode)
            .sprintf("%07o\0", 0)
            .sprintf("%07o\0", 0)
            .sprintf("%011o\0", $size)
            .sprintf("%011o\0", 1_700_000_000)
            .'        '
            .$type
            .str_pad($link, 100, "\0")
            ."ustar\0"
            .'00';

        $header = str_pad($header, 512, "\0");
        $sum = array_sum(array_map('ord', str_split($header)));

        return substr_replace($header, sprintf("%06o\0 ", $sum), 148, 8);
    }

    /**
     * @param  array<string, string>  $files  name => contents (a trailing slash makes a directory)
     * @param  array<string, int>  $unixModes  name => mode, to mark executables or symlinks
     */
    public static function zip(string $path, array $files, array $unixModes = []): string
    {
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($files as $name => $contents) {
            str_ends_with($name, '/') ? $zip->addEmptyDir(rtrim($name, '/')) : $zip->addFromString($name, $contents);

            if (isset($unixModes[$name])) {
                $zip->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, $unixModes[$name] << 16);
            }
        }

        $zip->close();

        return $path;
    }
}
