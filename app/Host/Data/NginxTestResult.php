<?php

namespace App\Host\Data;

final readonly class NginxTestResult
{
    public function __construct(public bool $ok, public string $output) {}
}
