<?php

declare(strict_types=1);

namespace IntrAgile\MnbExchangeRates;

use DateTimeInterface;
use IntrAgile\MnbExchangeRates\Exceptions\InvalidDateException;
use IntrAgile\MnbExchangeRates\Exceptions\MnbException;
use IntrAgile\MnbExchangeRates\Exceptions\RateNotFoundException;

/**
 * Source of official MNB exchange rates. Implemented by the live client and by the test fake.
 */
interface ExchangeRateProvider
{
    /**
     * The most recently published rate table.
     *
     * @throws MnbException
     */
    public function latest(): RateTable;

    /**
     * The rate table in force on a day: that day's table, or the last one published before it
     * when MNB did not publish that day (weekend, public holiday, today before publication).
     * The table's own date tells which day it was published for.
     *
     * @throws InvalidDateException when the date is malformed or in the future
     * @throws RateNotFoundException when nothing was published within the lookback window
     * @throws MnbException
     */
    public function on(DateTimeInterface|string $date): RateTable;

    /**
     * Every table published between two days inclusive, oldest first.
     *
     * @param  list<string>  $currencies  limit to these codes; empty means all
     * @return list<RateTable>
     *
     * @throws InvalidDateException
     * @throws MnbException
     */
    public function between(DateTimeInterface|string $from, DateTimeInterface|string $to, array $currencies = []): array;

    /**
     * Every currency code MNB has ever quoted, including discontinued ones.
     * Use latest()->currencies() for the currencies quoted today.
     *
     * @return list<string>
     *
     * @throws MnbException
     */
    public function currencies(): array;

    /**
     * Shortcut for latest()->rate() or on($date)->rate().
     *
     * @throws MnbException
     */
    public function rate(string $currency, DateTimeInterface|string|null $date = null): Rate;

    /**
     * Shortcut for latest()->convert() or on($date)->convert().
     *
     * @throws MnbException
     */
    public function convert(int|float|string $amount, string $from, string $to, DateTimeInterface|string|null $date = null): float;
}
