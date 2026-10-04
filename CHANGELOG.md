# Changelog

## 0.1.0 - 2026-10-04

- Initial standalone `trademinator/bcmath` package.
- Replaced the application `Bc` trait API with namespaced functions.
- Added `bcdec()` with optional meaningless trailing-zero trimming.
- Added `bcconv()`, `bcabs()`, `bcmax()`, `bcmin()`, `bcstddev()`, `bcpow10()`, and decimal-string `bclog10()`.
- Renamed `stats_standard_deviation()` to `bcstddev()`.
- Added framework-agnostic `PrecisionPolicy` and `PrecisionContext`.
- Removed all dependence on `EXCHANGE_ROUND_DECIMALS`.
- Kept `technical_scale()` outside the package by design.
