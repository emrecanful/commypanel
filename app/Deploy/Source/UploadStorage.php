<?php

namespace App\Deploy\Source;

use App\Models\App;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Keeps the unpacked project of an upload app under <data>/sources/<slug>.
 * The folder is replaced atomically, so a deploy never sees a half-unpacked one.
 */
class UploadStorage
{
    public function __construct(private readonly ArchiveExtractor $extractor) {}

    public function path(App $app): string
    {
        return rtrim((string) config('commypanel.data_path'), '/').'/sources/'.$app->slug;
    }

    public function has(App $app): bool
    {
        return is_dir($this->path($app)) && count(File::files($this->path($app))) + count(File::directories($this->path($app))) > 0;
    }

    /**
     * @return int the number of files stored
     *
     * @throws InvalidArchive
     */
    public function store(App $app, UploadedFile $archive): int
    {
        $target = $this->path($app);
        $parent = dirname($target);
        File::ensureDirectoryExists($parent, 0750);

        $staging = $target.'.new-'.bin2hex(random_bytes(4));

        try {
            $count = $this->extractor->extract((string) $archive->getRealPath(), $staging);
        } catch (InvalidArchive $e) {
            File::deleteDirectory($staging);

            throw $e;
        }

        $old = $target.'.old-'.bin2hex(random_bytes(4));

        if (is_dir($target)) {
            rename($target, $old);
        }

        rename($staging, $target);
        File::deleteDirectory($old);

        return $count;
    }

    public function delete(App $app): void
    {
        File::deleteDirectory($this->path($app));
    }

    /**
     * Copy the stored project into a build directory, keeping executable bits.
     */
    public function copyTo(App $app, string $destination): void
    {
        $source = $this->path($app);
        File::ensureDirectoryExists($destination);

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($items as $item) {
            $relative = substr($item->getPathname(), strlen($source) + 1);
            $to = $destination.'/'.$relative;

            if ($item->isLink()) {
                continue;
            }

            if ($item->isDir()) {
                File::ensureDirectoryExists($to);

                continue;
            }

            copy($item->getPathname(), $to);
            chmod($to, $item->isExecutable() ? 0755 : 0644);
        }
    }
}
