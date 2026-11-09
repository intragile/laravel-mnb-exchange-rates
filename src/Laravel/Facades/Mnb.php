<?php

declare(strict_types=1);

namespace IntrAgile\MnbExchangeRates\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use IntrAgile\MnbExchangeRates\ExchangeRateProvider;
use IntrAgile\MnbExchangeRates\Testing\FakeExchangeRates;

/**
 * @method static \IntrAgile\MnbExchangeRates\RateTable latest()
 * @method static \IntrAgile\MnbExchangeRates\RateTable on(\DateTimeInterface|string $date)
 * @method static list<\IntrAgile\MnbExchangeRates\RateTable> between(\DateTimeInterface|string $from, \DateTimeInterface|string $to, list<string> $currencies = [])
 * @method static list<string> currencies()
 * @method static \IntrAgile\MnbExchangeRates\Rate rate(string $currency, \DateTimeInterface|string|null $date = null)
 * @method static float convert(int|float|string $amount, string $from, string $to, \DateTimeInterface|string|null $date = null)
 *
 * @see ExchangeRateProvider
 */
final class Mnb extends Facade
{
    /**
     * Replaces the provider with an in-memory fake for the rest of the test.
     *
     * @param  array<string, array<string, int|float|string>>  $days  Y-m-d => [currency => HUF per one unit]
     */
    public static function fake(array $days = []): FakeExchangeRates
    {
        $fake = new FakeExchangeRates($days);
        self::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return ExchangeRateProvider::class;
    }
}
