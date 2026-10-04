<?php

declare(strict_types=1);

namespace Trademinator\BcMath\Internal;

use InvalidArgumentException;
use ValueError;
use function Trademinator\BcMath\bcabs;
use function Trademinator\BcMath\bcdec;
use function Trademinator\BcMath\bcpow10;

/** @internal */
final class Logarithm
{
    /** @var array<int, string> */
    private static array $ln10Cache = [];

    /** @return array{0:int, 1:string} */
    public static function scientificParts(string $value): array
    {
        $value = ltrim($value, '+');
        [$integer, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        if ($integer !== '0') {
            $exponent = strlen($integer) - 1;
            $digits = $integer.$fraction;
            $mantissa = $digits[0];
            $rest = rtrim(substr($digits, 1), '0');

            return [$exponent, $mantissa.($rest !== '' ? '.'.$rest : '')];
        }

        $firstNonZero = strspn($fraction, '0');
        $exponent = -($firstNonZero + 1);
        $digits = substr($fraction, $firstNonZero);
        $mantissa = $digits[0];
        $rest = rtrim(substr($digits, 1), '0');

        return [$exponent, $mantissa.($rest !== '' ? '.'.$rest : '')];
    }

    public static function ln(string $value, int $scale): string
    {
        if ($scale < 0) {
            throw new ValueError('scale must be greater than or equal to 0.');
        }

        if (bccomp($value, '0', bcdec([$value])) <= 0) {
            throw new InvalidArgumentException('Logarithm expects a positive decimal value.');
        }

        if (bccomp($value, '1', bcdec([$value])) === 0) {
            return bcadd('0', '0', $scale);
        }

        $workScale = $scale + 8;
        $reductionCount = 4;
        $reduced = $value;

        for ($i = 0; $i < $reductionCount; $i++) {
            $reduced = bcsqrt($reduced, $workScale);
        }

        $numerator = bcsub($reduced, '1', $workScale);
        $denominator = bcadd($reduced, '1', $workScale);
        $y = bcdiv($numerator, $denominator, $workScale);
        $ySquared = bcmul($y, $y, $workScale);

        $term = $y;
        $sum = $term;
        $divisor = 3;
        $epsilon = bcpow10(-($scale + 4), $workScale);

        for ($iteration = 0; $iteration < 10000; $iteration++, $divisor += 2) {
            $term = bcmul($term, $ySquared, $workScale);
            $addition = bcdiv($term, (string) $divisor, $workScale);
            $sum = bcadd($sum, $addition, $workScale);

            if (bccomp(bcabs($addition), $epsilon, $workScale) <= 0) {
                break;
            }
        }

        $result = bcmul($sum, '2', $workScale);
        $result = bcmul($result, (string) (2 ** $reductionCount), $workScale);

        return bcadd($result, '0', $scale);
    }

    public static function ln10(int $scale): string
    {
        return self::$ln10Cache[$scale] ??= self::ln('10', $scale);
    }
}
