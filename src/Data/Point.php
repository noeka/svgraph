<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Data;

final readonly class Point
{
    public function __construct(
        public float $value,
        public ?string $label = null,
        public ?Link $link = null,
    ) {}
}
