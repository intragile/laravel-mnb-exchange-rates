<?php

declare(strict_types=1);

namespace IntrAgile\MnbExchangeRates\Laravel\Console;

use Illuminate\Console\Command;
use IntrAgile\MnbExchangeRates\Exceptions\MnbException;
use IntrAgile\MnbExchangeRates\ExchangeRateProvider;
use IntrAgile\MnbExchangeRates\Rate;

final class RatesCommand extends Command
{
    protected $signature = 'mnb:rates
        {date? : The day (Y-m-d); defaults to the latest published table}
        {--currency=* : Show only these currencies}';

    protected $description = 'Show the official MNB exchange rates for a day';

    public function handle(ExchangeRateProvider $rates): int
    {
        $date = $this->argument('date');

        try {
            $table = is_string($date) ? $rates->on($date) : $rates->latest();
        } catch (MnbException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $wanted = array_map(strtoupper(...), array_filter((array) $this->option('currency'), is_string(...)));
        $shown = $wanted === [] ? $table->all() : array_intersect_key($table->all(), array_flip($wanted));

        if (is_string($date) && $date !== $table->date->format('Y-m-d')) {
            $this->line("No table for {$date}; showing the one published on {$table->date->format('Y-m-d')}.");
        } else {
            $this->line("MNB rates published on {$table->date->format('Y-m-d')}.");
        }

        $this->table(
            ['Currency', 'Unit', 'HUF', 'HUF per 1'],
            array_map(static fn (Rate $rate): array => [$rate->currency, $rate->unit, $rate->value, $rate->perUnit()], array_values($shown)),
        );

        foreach (array_diff($wanted, array_keys($shown)) as $missing) {
            $this->warn("{$missing} is not quoted on this day.");
        }

        return self::SUCCESS;
    }
}
