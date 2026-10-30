<?php

namespace App\Deploy;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class DeployKeyGenerator
{
    /**
     * Create an ed25519 key pair in OpenSSH format.
     *
     * @return array{private: string, public: string}
     *
     * @throws DeploymentFailed
     */
    public function generate(string $comment): array
    {
        $dir = rtrim((string) config('commypanel.data_path'), '/').'/tmp';
        File::ensureDirectoryExists($dir, 0700);
        $path = $dir.'/genkey-'.bin2hex(random_bytes(8));

        try {
            $result = Process::run(['ssh-keygen', '-q', '-t', 'ed25519', '-N', '', '-C', $comment, '-f', $path]);

            if ($result->failed() || ! File::exists($path) || ! File::exists($path.'.pub')) {
                throw new DeploymentFailed('Could not generate a deploy key. Is ssh-keygen installed?');
            }

            return ['private' => File::get($path), 'public' => trim(File::get($path.'.pub'))];
        } finally {
            File::delete([$path, $path.'.pub']);
        }
    }
}
