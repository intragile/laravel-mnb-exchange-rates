# Laravel MNB Exchange Rates

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

[![Tests](https://github.com/intragile/laravel-mnb-exchange-rates/actions/workflows/tests.yml/badge.svg)](https://github.com/intragile/laravel-mnb-exchange-rates/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/intragile/laravel-mnb-exchange-rates.svg)](https://packagist.org/packages/intragile/laravel-mnb-exchange-rates)
[![PHP](https://img.shields.io/packagist/php-v/intragile/laravel-mnb-exchange-rates.svg)](https://packagist.org/packages/intragile/laravel-mnb-exchange-rates)
[![License](https://img.shields.io/packagist/l/intragile/laravel-mnb-exchange-rates.svg)](LICENSE)

Official **MNB (Magyar Nemzeti Bank) exchange rates** for PHP and Laravel, **without `ext-soap`**.

[Magyar nyelvű leírás → README.hu.md](README.hu.md)

- **No SOAP extension.** The envelope is built by hand and the response is read with SimpleXML.
  Slim Docker images rarely ship `ext-soap`; this package does not need it.
- **Rates you can put on an invoice.** Values are exact decimal strings, never floats.
  Currencies quoted per 100 units (JPY, IDR, KRW) are normalised to one unit:
  `205,30` per 100 JPY becomes `2.053`.
- **Weekends and holidays handled.** `on($date)` returns the table in force on that day: its
  own, or the last one published before it. The table carries the day it was published for.
- **The service's traps are covered.** Comma decimals, empty days when no currency list is sent,
  silently ignored unknown currencies and SOAP faults behind HTTP 500 are all handled and tested
  against responses recorded from the live service.
- **Framework-independent core.** `MnbClient` is plain PHP with no Composer dependencies. The
  Laravel layer adds a facade, response caching, `Mnb::fake()` and an Artisan command.

## Installation

```bash
composer require intragile/laravel-mnb-exchange-rates
```

Requires PHP 8.3+ with `ext-simplexml`. The Laravel layer supports Laravel 12 and 13 and is
discovered automatically.

## Usage

### Plain PHP

```php
use IntrAgile\MnbExchangeRates\MnbClient;

$mnb = new MnbClient();

$table = $mnb->latest();               // the most recently published table
$table->date->format('Y-m-d');         // "2026-11-09"
$table->rate('EUR')->perUnit();        // "363.18"
$table->rate('JPY')->value;            // "205.3"  (as published, per 100 units)
$table->rate('JPY')->unit;             // 100
$table->rate('JPY')->perUnit();        // "2.053"
$table->rate('HUF')->perUnit();        // "1" (the base currency)

$mnb->on('2025-12-27')->date;          // 2025-12-23: no table during the Christmas break
$mnb->rate('USD', '2026-03-15');       // shortcut for on(...)->rate(...)
$mnb->convert(100, 'EUR', 'USD');      // float, through the forint cross rate

$mnb->between('2026-10-01', '2026-10-31', ['EUR', 'USD']); // list<RateTable>, oldest first
$mnb->currencies();                    // every code MNB ever quoted, incl. discontinued ones
$mnb->latest()->currencies();          // the codes quoted today
```

`on()` and `rate()` accept a `DateTimeInterface` or a `Y-m-d` string. Only the calendar day
counts: time and timezone are ignored.

### Laravel

```php
use IntrAgile\MnbExchangeRates\Laravel\Facades\Mnb;

Mnb::rate('EUR', $invoice->fulfilled_at)->perUnit();
```

Or inject `IntrAgile\MnbExchangeRates\ExchangeRateProvider`. Requests go through Laravel's HTTP
client, so `Http::fake()` and HTTP client events apply.

```bash
php artisan mnb:rates                       # latest table
php artisan mnb:rates 2025-12-27 --currency=EUR --currency=USD
```

### Testing your code

```php
Mnb::fake([
    '2026-11-06' => ['EUR' => '363.94', 'USD' => '315.50'],
    '2026-11-09' => ['EUR' => '363.18'],
]);

Mnb::rate('EUR', '2026-11-08')->perUnit(); // "363.94" — same fallback rules as the live client
```

Without Laravel, use `IntrAgile\MnbExchangeRates\Testing\FakeExchangeRates` directly, or feed
recorded responses to the real client with `Testing\RecordedTransport`.

## Configuration

Publish the config with `php artisan vendor:publish --tag=mnb-config`.

| Key | Default | Meaning |
|---|---|---|
| `endpoint` | `http://www.mnb.hu/arfolyamok.asmx` | Service URL (`MNB_ENDPOINT`) |
| `timeout` | `10` | Seconds (`MNB_TIMEOUT`) |
| `lookback_days` | `14` | How far `on()` looks back for a published table |
| `cache.enabled` | `true` | Cache successful responses (`MNB_CACHE`) |
| `cache.store` | default store | Cache store name (`MNB_CACHE_STORE`) |
| `cache.ttl.current` | `600` | Current table and ranges reaching today |
| `cache.ttl.currencies` | `86400` | Currency list |
| `cache.ttl.history` | `2592000` | Ranges that end before today |

Failed responses are never cached.

## Errors

Every exception implements `IntrAgile\MnbExchangeRates\Exceptions\MnbException`.

| Exception | When |
|---|---|
| `ServiceUnavailableException` | No connection, timeout, or an HTTP error without a SOAP fault |
| `ServiceFaultException` | MNB returned a SOAP fault (`$faultString`, `$operation`) |
| `UnexpectedResponseException` | The response is not in the documented format |
| `RateNotFoundException` | Currency not quoted that day, or no table within the lookback window |
| `InvalidDateException` | Malformed date, reversed range, or a future day |

## Good to know

- **MNB publishes around noon on Hungarian working days.** Before publication, `on(today)`
  returns the previous working day's table, which is the rate in force at that moment.
- **The service is plain HTTP.** The HTTPS host answers 404 for this path. The rates are
  public, but if integrity in transit matters to you, cross-check against
  [mnb.hu](https://www.mnb.hu/arfolyamok).
- **`convert()` returns a float** meant for display and estimates. For accounting, take
  `rate(...)->perUnit()` and calculate with your money library.
- **Which rate the law requires** (for example the VAT rules on foreign-currency invoices) is your
  decision; this package returns the official MNB mid-rate for the day you ask for.

## Development

```bash
composer test          # unit and feature tests, offline
composer test:e2e      # against the live MNB service
composer analyse       # PHPStan, level max
composer format        # Laravel Pint
```

## License

MIT. See [LICENSE](LICENSE). Exchange rate data © Magyar Nemzeti Bank.

---

Developed and maintained by [IntrAgile Kft.](https://intragile.eu).
