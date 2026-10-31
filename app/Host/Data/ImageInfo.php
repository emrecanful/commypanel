<?php

namespace App\Host\Data;

final readonly class ImageInfo
{
    /**
     * @param  list<string>  $tags
     */
    public function __construct(
        public array $tags,
        public int $sizeBytes,
        public int $created,
    ) {}
}
