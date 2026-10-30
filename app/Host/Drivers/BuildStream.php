<?php

namespace App\Host\Drivers;

/**
 * Parses the agent's newline-delimited JSON build output as it arrives.
 */
final class BuildStream
{
    public bool $received = false;

    public bool $finished = false;

    public ?string $error = null;

    private string $buffer = '';

    /**
     * @param  (callable(string): void)|null  $onOutput
     */
    public function __construct(private $onOutput = null) {}

    public function write(string $chunk): void
    {
        $this->received = true;
        $this->buffer .= $chunk;

        while (($newline = strpos($this->buffer, "\n")) !== false) {
            $this->line(substr($this->buffer, 0, $newline));
            $this->buffer = substr($this->buffer, $newline + 1);
        }
    }

    public function finish(): void
    {
        $this->line($this->buffer);
        $this->buffer = '';
    }

    private function line(string $line): void
    {
        $message = json_decode(trim($line), true);

        if (! is_array($message)) {
            return;
        }

        if (isset($message['error'])) {
            $this->error ??= (string) $message['error'];

            return;
        }

        if (isset($message['log']) && $this->onOutput !== null && $this->error === null) {
            ($this->onOutput)((string) $message['log']);
        }

        if (($message['ok'] ?? false) === true) {
            $this->finished = true;
        }
    }
}
