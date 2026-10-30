<?php

namespace App\Host\Data;

final readonly class ImageBuildSpec
{
    public function __construct(
        public string $tag,
        public string $contextPath,
        public string $dockerfile = 'Dockerfile',
    ) {}
}
