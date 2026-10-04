<?php

declare(strict_types=1);

namespace Trademinator\BcMath;

use InvalidArgumentException;
use Trademinator\BcMath\Internal\Logarithm;
use ValueError;

/**
 * Count the largest represented decimal precision in the supplied values.
 *
 * Unlike the former Trademinator trait implementation, this function has no
 * artificial minimum. Integer-only input therefore returns 0. Minimum working
 * precision belongs in PrecisionPolicy, not in this primitive.
 *
 * @param array<int, int|float|string> $numbers
 */
function bcdec(array $numbers, bool $trimTrailingZeros = false): int
{
    $decimals = 0;

    foreach ($numbers as $number) {
        $value = bcconv($number);
        $dot = strpos($value, '.');

        if ($dot === false) {
            continue;
        }

        $fraction = substr($value, $dot + 1);
        if ($trimTrailingZeros) {
            $fraction = rtrim($fraction, '0');
        }

        $decimals = max($decimals, strlen($fraction));
    }

    return $decimals;
}

/**
 * Expand a PHP numeric value into a plain decimal string suitable for BCMath.
 * Scientific notation is expanded without routing string input through float.
 */
function bcconv(int|float|string $number): string
{
    if (is_float($number)) {
        if (! is_finite($number)) {
            throw new InvalidArgumentException('bcconv expects a finite numeric value.');
        }

        $serializePrecision = ini_get('serialize_precision');

        try {
            ini_set('serialize_precision', '-1');
            $number = json_encode($number, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
        } finally {
            ini_set('serialize_precision', (string) $serializePrecision);
        }
    }

    $value = (string) $number;

    if (! preg_match('/^([+-]?)(\d*)(?:\.(\d*))?(?:[eE]([+-]?\d+))?$/D', $value, $parts)
        || (($parts[2] ?? '') === '' && ($parts[3] ?? '') === '')) {
        throw new InvalidArgumentException('bcconv expects a finite numeric value.');
    }

    $integer = $parts[2] === '' ? '0' : $parts[2];
    $fraction = $parts[3] ?? '';
    $exponent = $parts[4] ?? '0';

    if (strlen(ltrim($exponent, '+-0')) > 4
        || abs((int) $exponent) > 4096
        || strlen($integer) + strlen($fraction) > 4096) {
        throw new InvalidArgumentException('bcconv input exceeds the supported decimal length.');
    }

    $digits = $integer.$fraction;
    $point = strlen($integer) + (int) $exponent;

    if ($point <= 0) {
        $integer = '0';
        $fraction = str_repeat('0', -$point).$digits;
    } elseif ($point >= strlen($digits)) {
        $integer = $digits.str_repeat('0', $point - strlen($digits));
        $fraction = '';
    } else {
        $integer = substr($digits, 0, $point);
        $fraction = substr($digits, $point);
    }

    $integer = ltrim($integer, '0');
    $integer = $integer === '' ? '0' : $integer;
    $sign = $parts[1] === '-' && trim($integer.$fraction, '0') !== '' ? '-' : '';

    return $sign.$integer.($fraction !== '' ? '.'.$fraction : '');
}

/** Return the absolute value as a BCMath decimal string. */
function bcabs(int|float|string $number): string
{
    return ltrim(bcconv($number), '-');
}

/**
 * Return the greatest represented decimal value, or false for empty input.
 */
function bcmax(int|float|string ...$values): string|false
{
    if ($values === []) {
        return false;
    }

    $values = array_map(bcconv(...), $values);
    $scale = bcdec($values);
    $max = $values[0];

    foreach (array_slice($values, 1) as $value) {
        if (bccomp($value, $max, $scale) === 1) {
            $max = $value;
        }
    }

    return $max;
}

/**
 * Return the smallest represented decimal value, or false for empty input.
 */
function bcmin(int|float|string ...$values): string|false
{
    if ($values === []) {
        return false;
    }

    $values = array_map(bcconv(...), $values);
    $scale = bcdec($values);
    $min = $values[0];

    foreach (array_slice($values, 1) as $value) {
        if (bccomp($value, $min, $scale) === -1) {
            $min = $value;
        }
    }

    return $min;
}

/**
 * BCMath standard deviation.
 *
 * If scale is omitted, a PrecisionContext is resolved once from the input.
 * Hot-path callers should resolve their context once and pass ->scale here.
 *
 * @param array<int, int|float|string> $values
 */
function bcstddev(array $values, bool $sample = false, ?int $scale = null): string|false
{
    $count = count($values);

    if ($count === 0) {
        trigger_error('The array has zero elements', E_USER_WARNING);

        return false;
    }

    if ($sample && $count === 1) {
        trigger_error('The array has only 1 element', E_USER_WARNING);

        return false;
    }

    if ($scale !== null && $scale < 0) {
        throw new ValueError('scale must be greater than or equal to 0.');
    }

    $numbers = array_map(bcconv(...), $values);
    $scale ??= PrecisionContext::fromValues($numbers)->scale;

    $sum = '0';
    foreach ($numbers as $number) {
        $sum = bcadd($sum, $number, $scale);
    }

    $mean = bcdiv($sum, (string) $count, $scale);
    $carry = '0';

    foreach ($numbers as $number) {
        $delta = bcsub($number, $mean, $scale);
        $carry = bcadd($carry, bcmul($delta, $delta, $scale), $scale);
    }

    $divisor = $sample ? $count - 1 : $count;
    $variance = bcdiv($carry, (string) $divisor, $scale);

    return bcsqrt($variance, $scale);
}

/** Return 10 raised to an integer exponent using BCMath. */
function bcpow10(int $exponent, int $scale = 10): string
{
    if ($scale < 0) {
        throw new ValueError('scale must be greater than or equal to 0.');
    }

    if ($exponent === 0) {
        return '1';
    }

    if ($exponent > 0) {
        return bcpow('10', (string) $exponent, $scale);
    }

    $denominator = bcpow('10', (string) abs($exponent), 0);

    return bcdiv('1', $denominator, $scale);
}

/**
 * Base-10 logarithm returned as a BCMath decimal string.
 *
 * The mantissa is evaluated with an atanh-series logarithm after repeated
 * square-root range reduction. No input is converted to float.
 */
function bclog10(int|float|string $number, int $scale = 16): string
{
    if ($scale < 0) {
        throw new ValueError('scale must be greater than or equal to 0.');
    }

    $value = bcconv($number);
    $comparisonScale = bcdec([$value]);

    if (bccomp($value, '0', $comparisonScale) <= 0) {
        throw new InvalidArgumentException('bclog10 expects a positive numeric value.');
    }

    [$exponent, $mantissa] = Logarithm::scientificParts($value);

    if (bccomp($mantissa, '1', bcdec([$mantissa])) === 0) {
        return bcadd((string) $exponent, '0', $scale);
    }

    $workScale = $scale + 10;
    $mantissaLn = Logarithm::ln($mantissa, $workScale);
    $tenLn = Logarithm::ln10($workScale);
    $fraction = bcdiv($mantissaLn, $tenLn, $workScale);

    return bcadd((string) $exponent, $fraction, $scale);
}

