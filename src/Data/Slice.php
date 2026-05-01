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
     * Non-finite values (NaN, ±Infinity) are silently dropped. Tuple inputs
     * must have at least [label, value]; shorter arrays throw to surface
     * the mistake at the point of construction rather than producing an
     * empty-labelled zero slice.
     *
     * @param iterable<mixed> $input
     * @return list<self>
     */
    public static function listFrom(iterable $input): array
    {
        $slices = [];
        foreach ($input as $key => $value) {
            if ($value instanceof self) {
                if (is_finite($value->value)) {
                    $slices[] = $value;
                }
                continue;
            }
            if (is_array($value)) {
                if (count($value) < 2) {
                    throw new \InvalidArgumentException(
                        'Slice tuple must contain at least [label, value]; got ' . count($value) . ' element(s).',
                    );
                }
                $label = (string) ($value[0] ?? '');
                $val = (float) ($value[1] ?? 0);
                if (!is_finite($val)) {
                    continue;
                }
                $color = isset($value[2]) ? (string) $value[2] : null;
                $slices[] = new self($label, $val, $color);
                continue;
            }
            $val = (float) $value;
            if (!is_finite($val)) {
                continue;
            }
            $slices[] = new self((string) $key, $val);
        }
        return $slices;
    }
}
