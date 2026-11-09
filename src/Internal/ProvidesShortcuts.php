<?php

declare(strict_types=1);

namespace IntrAgile\MnbExchangeRates\Internal;

use DateTimeInterface;
use IntrAgile\MnbExchangeRates\Rate;
use IntrAgile\MnbExchangeRates\RateTable;

/**
 * @internal
 */
trait ProvidesShortcuts
{
    abstract public function latest(): RateTable;

    abstract public function on(DateTimeInterface|string $date): RateTable;

    public function rate(string $currency, DateTimeInterface|string|null $date = null): Rate
    {
        return $this->table($date)->rate($currency);
    }

    public function convert(int|float|string $amount, string $from, string $to, DateTimeInterface|string|null $date = null): float
    {
        return $this->table($date)->convert($amount, $from, $to);
    }

    private function table(DateTimeInterface|string|null $date): RateTable
    {
        return $date === null ? $this->latest() : $this->on($date);
    }
}
