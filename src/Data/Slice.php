<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Data;

final readonly class Slice
{
    public function __construct(
        public string $label,
        public float $value,
        public ?string $color = null,
        public ?Link $link = null,
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

                $label = self::toString($value[0] ?? '');
                $val = self::toFloat($value[1] ?? 0);

                if (!is_finite($val)) {
                    continue;
                }

                $color = isset($value[2]) ? self::toString($value[2]) : null;
                $link = isset($value[3]) && $value[3] instanceof Link ? $value[3] : null;
                $slices[] = new self($label, $val, $color, $link);

                continue;
            }

            $val = self::toFloat($value);

            if (!is_finite($val)) {
                continue;
            }

            $slices[] = new self(self::toString($key), $val);
        }

        return $slices;
    }

    private static function toFloat(mixed $v): float
    {
        return is_numeric($v) ? (float) $v : NAN;
    }

    private static function toString(mixed $v): string
    {
        return is_scalar($v) ? (string) $v : '';
    }
}
