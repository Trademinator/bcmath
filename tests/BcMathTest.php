<?php

declare(strict_types=1);

namespace Trademinator\BcMath\Tests;

use PHPUnit\Framework\TestCase;
use Trademinator\BcMath\PrecisionContext;
use Trademinator\BcMath\PrecisionPolicy;
use function Trademinator\BcMath\bcabs;
use function Trademinator\BcMath\bcconv;
use function Trademinator\BcMath\bcdec;
use function Trademinator\BcMath\bclog10;
use function Trademinator\BcMath\bcmax;
use function Trademinator\BcMath\bcmin;
use function Trademinator\BcMath\bcpow10;
use function Trademinator\BcMath\bcstddev;

final class BcMathTest extends TestCase
{
    public function testBcdecCountsRepresentedDecimalsExactly(): void
    {
        self::assertSame(0, bcdec(['100', '42']));
        self::assertSame(2, bcdec(['100', '100.10']));
        self::assertSame(4, bcdec(['100.1000']));
        self::assertSame(1, bcdec(['100.1000'], true));
        self::assertSame(6, bcdec(['1.2300e-2']));
        self::assertSame(4, bcdec(['1.2300e-2'], true));
    }

    public function testBcconvExpandsScientificNotation(): void
    {
        self::assertSame('0.000000123456789123456789', bcconv('1.23456789123456789e-7'));
        self::assertSame('123000', bcconv('1.23000e5'));
        self::assertSame('-0.00120', bcconv('-1.20e-3'));
    }

    public function testBasicHelpers(): void
    {
        self::assertSame('1.2500', bcabs('-1.2500'));
        self::assertSame('10.001', bcmax('10.0009', '10.001'));
        self::assertSame('-10.001', bcmin('-10.0009', '-10.001'));
        self::assertFalse(bcmax());
        self::assertFalse(bcmin());
    }

    public function testStandardDeviation(): void
    {
        self::assertSame('2.0000000000000000', bcstddev(['2', '4', '4', '4', '5', '5', '7', '9'], false, 16));
    }

    public function testPow10(): void
    {
        self::assertSame('1000.0000000000', bcpow10(3, 10));
        self::assertSame('0.0010000000', bcpow10(-3, 10));
    }

    public function testLog10WithoutFloatInputConversion(): void
    {
        self::assertSame('0.0000000000000000', bclog10('1', 16));
        self::assertSame('1.0000000000000000', bclog10('10', 16));
        self::assertSame('2.0000000000000000', bclog10('100', 16));
        self::assertSame('-3.0000000000000000', bclog10('0.001', 16));
        self::assertSame('0.3010299956639811', bclog10('2', 16));
    }

    public function testPrecisionContextResolvesOnceFromPolicy(): void
    {
        $policy = new PrecisionPolicy(
            minimumScale: 16,
            guardDigits: 4,
            maximumScale: 32,
            trimTrailingZeros: true,
        );

        self::assertSame(16, PrecisionContext::fromValues(['1.2300', '2.10'], $policy)->scale);
        self::assertSame(23, PrecisionContext::forPrices(['0.12345678901234567890'], $policy)->scale);
    }
}
