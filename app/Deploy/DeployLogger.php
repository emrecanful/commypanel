<?php

namespace App\Deploy;

use App\Models\Deployment;

/**
 * Collects deployment log lines and writes them to the database in batches,
 * so a chatty build does not rewrite the whole log column for every line.
 */
class DeployLogger
{
    private const MAX_BYTES = 2_000_000;

    /** @var list<string> */
    private array $buffer = [];

    private bool $truncated = false;

    public function __construct(private readonly Deployment $deployment, private readonly int $flushEvery = 25) {}

    public function line(string $line): void
    {
        $this->buffer[] = $line;

        if (count($this->buffer) >= $this->flushEvery) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        if ($this->buffer === [] || $this->truncated) {
            $this->buffer = [];

            return;
        }

        $log = ($this->deployment->log ?? '').implode("\n", $this->buffer)."\n";
        $this->buffer = [];

        if (strlen($log) > self::MAX_BYTES) {
            $log = substr($log, 0, self::MAX_BYTES)."\n[log truncated]\n";
            $this->truncated = true;
        }

        $this->deployment->forceFill(['log' => $log])->save();
    }
}
