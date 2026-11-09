<?php

declare(strict_types=1);

namespace IntrAgile\MnbExchangeRates;

use DateTimeImmutable;
use IntrAgile\MnbExchangeRates\Exceptions\RateNotFoundException;

/**
 * All rates MNB published for one day.
 */
final readonly class RateTable
{
    /**
     * @param  array<string, Rate>  $rates  currency code => rate
     */
    public function __construct(
        public DateTimeImmutable $date,
        private array $rates,
    ) {}

    public function has(string $currency): bool
    {
        $currency = strtoupper($currency);

        return $currency === MnbClient::BASE || isset($this->rates[$currency]);
    }

    /**
     * The rate of a currency. HUF, the base currency, always has the rate 1.
     *
     * @throws RateNotFoundException when MNB did not quote the currency on this day
     */
    public function rate(string $currency): Rate
    {
        $currency = strtoupper($currency);

        if ($currency === MnbClient::BASE) {
            return new Rate(MnbClient::BASE, 1, '1', $this->date);
        }

        return $this->rates[$currency]
            ?? throw new RateNotFoundException("MNB published no {$currency} rate for {$this->date->format('Y-m-d')}.");
    }

    /**
     * @return array<string, Rate>
     */
    public function all(): array
    {
        return $this->rates;
    }

    /**
     * @return list<string>
     */
    public function currencies(): array
    {
        return array_keys($this->rates);
    }

    /**
     * Converts an amount between two currencies through the forint cross rate.
     *
     * The result is a float meant for display and estimates. For accounting, take the exact
     * rate from rate()->perUnit() and do the arithmetic in your money library.
     *
     * @throws RateNotFoundException when either currency is not quoted on this day
     */
    public function convert(int|float|string $amount, string $from, string $to): float
    {
        return (float) $amount * $this->rate($from)->toFloat() / $this->rate($to)->toFloat();
    }
}
