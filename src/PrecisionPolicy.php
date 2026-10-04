<?php

declare(strict_types=1);

namespace Trademinator\BcMath;

use InvalidArgumentException;

final readonly class PrecisionPolicy
{
    public function __construct(
        public int $minimumScale = 16,
        public int $guardDigits = 4,
        public int $maximumScale = 32,
        public bool $trimTrailingZeros = true,
    ) {
        if ($minimumScale < 0) {
            throw new InvalidArgumentException('minimumScale must be zero or greater.');
        }

        if ($guardDigits < 0) {
            throw new InvalidArgumentException('guardDigits must be zero or greater.');
        }

        if ($maximumScale < $minimumScale) {
            throw new InvalidArgumentException('maximumScale must be greater than or equal to minimumScale.');
        }
    }
}
