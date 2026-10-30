<?php

use App\Deploy\DeployLogger;
use App\Models\Deployment;

beforeEach(function () {
    $this->deployment = Deployment::factory()->create();
    $this->now = 1000.0;
    $this->logger = fn (int $flushEvery = 25) => new DeployLogger($this->deployment, $flushEvery, 1.0, fn () => $this->now);
    $this->stored = fn () => $this->deployment->fresh()->log;
});

it('writes a milestone straight away, so the page never claims to be waiting', function () {
    $logger = ($this->logger)();

    $logger->step('Fetching the repository.');

    expect(($this->stored)())->toBe("Fetching the repository.\n");
});

it('batches quick lines instead of writing each one', function () {
    $logger = ($this->logger)();

    $logger->line('one');
    $logger->line('two');

    expect(($this->stored)())->toBeNull();
});

it('writes buffered lines once a second has passed', function () {
    $logger = ($this->logger)();
    $logger->line('one');

    $this->now += 1.2;
    $logger->line('two');

    expect(($this->stored)())->toBe("one\ntwo\n");
});

it('writes a full batch even when no time has passed', function () {
    $logger = ($this->logger)(3);

    $logger->line('a');
    $logger->line('b');
    expect(($this->stored)())->toBeNull();

    $logger->line('c');
    expect(($this->stored)())->toBe("a\nb\nc\n");
});

it('keeps lines in order across steps and batches', function () {
    $logger = ($this->logger)();

    $logger->step('start');
    $logger->line('build 1');
    $logger->line('build 2');
    $logger->flush();
    $logger->step('end');

    expect(($this->stored)())->toBe("start\nbuild 1\nbuild 2\nend\n");
});

it('truncates a runaway log once', function () {
    $logger = ($this->logger)(1);

    $logger->line(str_repeat('x', 2_100_000));
    $logger->line('after the limit');

    $log = ($this->stored)();
    expect(strlen($log))->toBeLessThan(2_100_000)
        ->and($log)->toEndWith("[log truncated]\n")
        ->and($log)->not->toContain('after the limit');
});

it('shows the log of a running deployment as soon as the first step is announced', function () {
    // The deploy page polls the stored log; an empty log means "nothing has happened yet".
    $logger = ($this->logger)();

    expect(($this->stored)())->toBeNull();

    $logger->step('Fetching https://github.com/acme/shop.git (main).');

    expect(($this->stored)())->toContain('Fetching');
});
