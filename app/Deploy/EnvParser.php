<?php

namespace App\Deploy;

/**
 * Reads pasted .env text into variables. Comments and blank lines are
 * ignored, `export ` and matching quotes are stripped, PORT is skipped
 * (CommyPanel sets it).
 */
class EnvParser
{
    public const MAX_VARS = 200;

    /**
     * @return array{vars: array<string, string>, skipped: list<string>}
     */
    public function parse(string $text): array
    {
        $vars = [];
        $skipped = [];

        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $line = (string) preg_replace('/^export\s+/', '', $line);
            $pos = strpos($line, '=');

            if ($pos === false) {
                $skipped[] = mb_substr($line, 0, 40);

                continue;
            }

            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));

            if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,127}$/', $key) || $key === 'PORT') {
                $skipped[] = mb_substr($key, 0, 40);

                continue;
            }

            if (strlen($value) >= 2 && in_array($value[0], ['"', "'"], true) && str_ends_with($value, $value[0])) {
                $value = substr($value, 1, -1);
            } else {
                $value = trim((string) preg_replace('/\s+#.*$/', '', $value));
            }

            if (strlen($value) > 32768 || count($vars) >= self::MAX_VARS && ! isset($vars[$key])) {
                $skipped[] = $key;

                continue;
            }

            $vars[$key] = $value;
        }

        return ['vars' => $vars, 'skipped' => $skipped];
    }
}
