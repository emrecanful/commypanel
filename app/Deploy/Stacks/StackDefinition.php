<?php

namespace App\Deploy\Stacks;

use App\Enums\Stack;

interface StackDefinition
{
    public function stack(): Stack;

    /**
     * Whether the source directory looks like this stack.
     */
    public function matches(string $path): bool;

    /**
     * @throws DetectionFailed when the source matches but cannot be built automatically
     */
    public function plan(string $path, int $port): BuildPlan;
}
