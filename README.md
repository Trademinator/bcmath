# trademinator/bcmath

Framework-agnostic BCMath helpers and reusable precision utilities for PHP.

The package has **no Laravel dependency**. Laravel applications may construct a `PrecisionPolicy` from their own configuration, while standalone PHP applications can instantiate the same objects directly.

## Requirements

- PHP 8.2+
- `ext-bcmath`

## Installation

Once the repository/package is published:

```bash
composer require trademinator/bcmath
```

## Public functions

```php
use function Trademinator\BcMath\bcabs;
use function Trademinator\BcMath\bcconv;
use function Trademinator\BcMath\bcdec;
use function Trademinator\BcMath\bclog10;
use function Trademinator\BcMath\bcmax;
use function Trademinator\BcMath\bcmin;
use function Trademinator\BcMath\bcpow10;
use function Trademinator\BcMath\bcstddev;
```

### Decimal precision

`bcdec()` returns the exact largest represented decimal count. It does not impose a minimum scale.

```php
bcdec(['100', '42']);                    // 0
bcdec(['100.10']);                       // 2
bcdec(['100.1000']);                     // 4
bcdec(['100.1000'], trimTrailingZeros: true); // 1
```

Scientific notation is expanded first, without converting string input to float:

```php
bcconv('1.23456789123456789e-7');
// 0.000000123456789123456789
```

## PrecisionPolicy and PrecisionContext

Resolve precision once for a price/data slice, then pass the resolved scale through hot-path calculations instead of repeatedly recalculating decimal precision.

```php
use Trademinator\BcMath\PrecisionContext;
use Trademinator\BcMath\PrecisionPolicy;
use function Trademinator\BcMath\bcstddev;

$policy = new PrecisionPolicy(
    minimumScale: 16,
    guardDigits: 4,
    maximumScale: 32,
    trimTrailingZeros: true,
);

$precision = PrecisionContext::forPrices($prices, $policy);

$stddev = bcstddev($prices, sample: false, scale: $precision->scale);
```

Default policy:

- minimum scale: `16`
- guard digits: `4`
- maximum scale: `32`
- trailing zero trimming when resolving context: enabled

## Standard deviation

The old Trademinator helper name `stats_standard_deviation()` is replaced by the BCMath-style `bcstddev()`:

```php
bcstddev(['2', '4', '4', '4', '5', '5', '7', '9'], scale: 16);
// 2.0000000000000000
```

## Logarithm

`bclog10()` returns a decimal string. It does not convert decimal string input to float.

```php
bclog10('2', 16);
// 0.3010299956639811
```

## Intentional package boundary

`technical_scale()` is **not** part of this package. It belongs to the Trademinator Technical layer because it is technical-indicator precision policy, not a missing generic BCMath primitive.

Likewise, this package contains no `EXCHANGE_ROUND_DECIMALS` constant and no framework configuration lookup. Precision is explicit through `PrecisionPolicy`, `PrecisionContext`, or a function's `scale` argument.

## Laravel

No service provider is required. Use the package exactly as in plain PHP. If Laravel configuration should control precision, translate that configuration into a `PrecisionPolicy` in the application layer and pass the resolved context explicitly.

## License

MIT
