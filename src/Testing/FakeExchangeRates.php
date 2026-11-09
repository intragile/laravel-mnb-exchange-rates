<?php

declare(strict_types=1);

namespace IntrAgile\MnbExchangeRates\Testing;

use DateTimeImmutable;
use DateTimeInterface;
use IntrAgile\MnbExchangeRates\Exceptions\InvalidDateException;
use IntrAgile\MnbExchangeRates\Exceptions\RateNotFoundException;
use IntrAgile\MnbExchangeRates\ExchangeRateProvider;
use IntrAgile\MnbExchangeRates\Internal\Dates;
use IntrAgile\MnbExchangeRates\Internal\Decimal;
use IntrAgile\MnbExchangeRates\Internal\ProvidesShortcuts;
use IntrAgile\MnbExchangeRates\MnbClient;
use IntrAgile\MnbExchangeRates\Rate;
use IntrAgile\MnbExchangeRates\RateTable;

/**
 * In-memory provider for tests. It follows the live client's rules: the same lookback
 * fallback, the same future-date check and the same HUF base, without any network access.
 */
final class FakeExchangeRates implements ExchangeRateProvider
{
    use ProvidesShortcuts;

    /** @var array<string, RateTable> Y-m-d => table, ascending */
    private array $tables = [];

    /**
     * @param  array<string, array<string, int|float|string>>  $days  Y-m-d => [currency => HUF per one unit]
     */
    public function __construct(array $days = [], private readonly int $lookbackDays = MnbClient::DEFAULT_LOOKBACK_DAYS)
    {
        foreach ($days as $date => $rates) {
            $this->publish($date, $rates);
        }
    }

    /**
     * Adds or replaces the table for a day.
     *
     * @param  array<string, int|float|string>  $rates  currency => HUF per one unit
     */
    public function publish(DateTimeInterface|string $date, array $rates): self
    {
        $day = Dates::day($date);
        $table = [];

        foreach ($rates as $currency => $value) {
            $currency = strtoupper($currency);
            $text = is_float($value) ? sprintf('%.10F', $value) : (string) $value;
            $table[$currency] = new Rate($currency, 1, Decimal::normalize($text), $day);
        }

        ksort($table);
        $this->tables[$day->format('Y-m-d')] = new RateTable($day, $table);
        ksort($this->tables);

        return $this;
    }

    public function latest(): RateTable
    {
        return end($this->tables) ?: throw new RateNotFoundException('The fake has no rate table.');
    }

    public function on(DateTimeInterface|string $date): RateTable
    {
        $day = Dates::day($date);

        if ($day > Dates::today()) {
            throw new InvalidDateException("{$day->format('Y-m-d')} is in the future; MNB has not published rates for it.");
        }

        $floor = $day->modify("-{$this->lookbackDays} days");
        $found = null;

        foreach ($this->tables as $table) {
            if ($table->date >= $floor && $table->date <= $day) {
                $found = $table;
            }
        }

        return $found ?? throw new RateNotFoundException("MNB published no rates in the {$this->lookbackDays} days up to {$day->format('Y-m-d')}.");
    }

    public function between(DateTimeInterface|string $from, DateTimeInterface|string $to, array $currencies = []): array
    {
        $start = Dates::day($from);
        $end = Dates::day($to);

        if ($start > $end) {
            throw new InvalidDateException("The range starts ({$start->format('Y-m-d')}) after it ends ({$end->format('Y-m-d')}).");
        }

        $wanted = array_flip(array_map(strtoupper(...), $currencies));
        $result = [];

        foreach ($this->tables as $table) {
            if ($table->date < $start || $table->date > $end) {
                continue;
            }

            $rates = $wanted === [] ? $table->all() : array_intersect_key($table->all(), $wanted);

            if ($rates !== []) {
                $result[] = new RateTable($table->date, $rates);
            }
        }

        return $result;
    }

    public function currencies(): array
    {
        $codes = [MnbClient::BASE];

        foreach ($this->tables as $table) {
            array_push($codes, ...$table->currencies());
        }

        return array_values(array_unique($codes));
    }

    /**
     * Days the fake holds, ascending.
     *
     * @return list<DateTimeImmutable>
     */
    public function days(): array
    {
        return array_values(array_map(static fn (RateTable $table): DateTimeImmutable => $table->date, $this->tables));
    }
}
