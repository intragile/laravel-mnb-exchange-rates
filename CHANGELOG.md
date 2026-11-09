# Changelog

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

All notable changes are recorded here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- `MnbClient`: latest table, the table in force on a day with a lookback fallback, date ranges and
  the currency list, without `ext-soap`.
- Exact decimal rates normalised to one unit; HUF as the base with rate 1; float conversion
  through the forint cross rate.
- Laravel layer: container bindings, a facade, response caching with separate lifetimes for
  history and today, `Mnb::fake()` and the `mnb:rates` command.
- `FakeExchangeRates` and `RecordedTransport` for tests without Laravel.

---

Developed and maintained by [IntrAgile Kft.](https://intragile.eu).
