<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Data;

final readonly class Slice
{
    public function __construct(
        public string $label,
        public float $value,
        public ?string $color = null,
    ) {}

    /**
     * Normalize various input shapes into a list of Slices.
     *
     * - ['Stripe' => 1240, 'PayPal' => 432]
     * - [['Stripe', 1240], ['PayPal', 432, '#10b981']]
     * - [Slice, Slice]
     *
     * @param iterable<mixed> $input
     * @return list<self>
     */
    public static function listFrom(iterable $input): array
    {
        $slices = [];
        foreach ($input as $key => $value) {
            if ($value instanceof self) {
                $slices[] = $value;
                continue;
            }
            if (is_array($value)) {
                $label = (string) ($value[0] ?? '');
                $val = (float) ($value[1] ?? 0);
                $color = isset($value[2]) ? (string) $value[2] : null;
                $slices[] = new self($label, $val, $color);
                continue;
            }
            $slices[] = new self((string) $key, (float) $value);
        }
        return $slices;
    }
}
