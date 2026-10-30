<?php

/**
 * Creates a temporary source tree from a map of relative path => contents.
 *
 * @param  array<string, string>  $files
 */
function sourceTree(array $files): string
{
    static $created = [];

    $root = sys_get_temp_dir().'/cp-src-'.bin2hex(random_bytes(4));
    mkdir($root, 0700, true);
    $created[] = $root;

    foreach ($files as $path => $contents) {
        $full = $root.'/'.$path;
        @mkdir(dirname($full), 0700, true);
        file_put_contents($full, $contents);
    }

    register_shutdown_function(function () use ($root) {
        exec('rm -rf '.escapeshellarg($root));
    });

    return $root;
}

function webCsproj(string $framework = 'net8.0', string $extra = ''): string
{
    return <<<XML
    <Project Sdk="Microsoft.NET.Sdk.Web">
      <PropertyGroup><TargetFramework>{$framework}</TargetFramework>{$extra}</PropertyGroup>
    </Project>
    XML;
}
