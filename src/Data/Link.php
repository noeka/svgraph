<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Data;

use InvalidArgumentException;

final readonly class Link
{
    public string $rel;

    public function __construct(
        public string $href,
        public ?string $target = null,
        ?string $rel = null,
    ) {
        if (preg_match('/^\s*javascript\s*:/i', $href)) {
            throw new InvalidArgumentException(
                'javascript: URLs are not allowed in links.',
            );
        }

        $this->rel = $rel ?? ($target === '_blank' ? 'noopener noreferrer' : '');
    }
}
