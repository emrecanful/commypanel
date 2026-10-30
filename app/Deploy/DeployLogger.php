<?php

namespace App\Deploy;

use App\Models\Deployment;
use Closure;

/**
 * Collects deployment log lines and writes them to the database in batches,
 * so a chatty build does not rewrite the whole log column for every line.
 *
 * A batch is written when it is large, when a second has passed since the last
 * write, or immediately for a milestone (`step`), so the page that polls the
 * log always shows what the deployment is doing now.
 */
class DeployLogger
{
    private const MAX_BYTES = 2_000_000;

    /** @var list<string> */
    private array $buffer = [];

    private bool $truncated = false;

    private float $lastFlush;

    /**
     * @param  (Closure(): float)|null  $clock  seconds as a float; injectable for tests
     */
    public function __construct(
        private readonly Deployment $deployment,
        private readonly int $flushEvery = 25,
        private readonly float $flushAfterSeconds = 1.0,
        private readonly ?Closure $clock = null,
    ) {
        $this->lastFlush = $this->now();
    }

    public function line(string $line): void
    {
        $this->buffer[] = $line;

        if (count($this->buffer) >= $this->flushEvery || $this->now() - $this->lastFlush >= $this->flushAfterSeconds) {
            $this->flush();
        }
    }

    /**
     * A milestone ("Fetching…", "Building…"): written straight away, because
     * the step that follows can be silent for a long time.
     */
    public function step(string $line): void
    {
        $this->buffer[] = $line;
        $this->flush();
    }

    public function flush(): void
    {
        $this->lastFlush = $this->now();

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

    private function now(): float
    {
        return $this->clock !== null ? ($this->clock)() : microtime(true);
    }
}
