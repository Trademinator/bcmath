<?php

declare(strict_types=1);

namespace Trademinator\BcMath;

use InvalidArgumentException;

final readonly class PrecisionContext
{
    public function __construct(
        public int $scale,
        public PrecisionPolicy $policy,
    ) {
        if ($scale < 0) {
            throw new InvalidArgumentException('scale must be zero or greater.');
        }

        if ($scale > $policy->maximumScale) {
            throw new InvalidArgumentException('scale cannot exceed the precision policy maximumScale.');
        }
    }

    /**
     * Resolve one reusable precision context for a set of decimal values.
     *
     * @param array<int, int|float|string> $values
     */
    public static function fromValues(array $values, ?PrecisionPolicy $policy = null): self
    {
        $policy ??= new PrecisionPolicy();
        $decimals = bcdec($values, $policy->trimTrailingZeros);
        $scale = max($policy->minimumScale, $decimals + $policy->guardDigits);
        $scale = min($policy->maximumScale, $scale);

        return new self($scale, $policy);
    }

    /**
     * Semantic alias for technical/financial callers resolving precision from prices.
     *
     * @param array<int, int|float|string> $prices
     */
    public static function forPrices(array $prices, ?PrecisionPolicy $policy = null): self
    {
        return self::fromValues($prices, $policy);
    }
}
