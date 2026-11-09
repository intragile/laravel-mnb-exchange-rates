<?php

declare(strict_types=1);

namespace IntrAgile\MnbExchangeRates\Tests\Unit;

use DateTimeImmutable;
use IntrAgile\MnbExchangeRates\Exceptions\InvalidDateException;
use IntrAgile\MnbExchangeRates\Exceptions\RateNotFoundException;
use IntrAgile\MnbExchangeRates\Testing\FakeExchangeRates;
use PHPUnit\Framework\TestCase;

final class FakeExchangeRatesTest extends TestCase
{
    public function test_follows_the_live_lookback_rules(): void
    {
        $fake = new FakeExchangeRates([
            '2026-11-06' => ['EUR' => '363.94', 'USD' => 315.5],
            '2026-11-09' => ['EUR' => '363,18'],
        ]);

        $this->assertSame('2026-11-06', $fake->on('2026-11-08')->date->format('Y-m-d'));
        $this->assertSame('363.94', $fake->rate('EUR', '2026-11-07')->value);
        $this->assertSame('315.5', $fake->rate('USD', '2026-11-07')->value);
        $this->assertSame('363.18', $fake->latest()->rate('EUR')->value);
        $this->assertSame(['HUF', 'EUR', 'USD'], $fake->currencies());
    }

    public function test_nothing_within_the_window_throws(): void
    {
        $fake = new FakeExchangeRates(['2026-10-01' => ['EUR' => '380']], lookbackDays: 3);

        $this->expectException(RateNotFoundException::class);

        $fake->on('2026-10-10');
    }

    public function test_future_and_empty(): void
    {
        $fake = new FakeExchangeRates;

        try {
            $fake->latest();
            $this->fail('Expected an exception.');
        } catch (RateNotFoundException) {
        }

        $this->expectException(InvalidDateException::class);
        $fake->on((new DateTimeImmutable('today'))->modify('+1 day'));
    }

    public function test_between_filters_days_and_currencies(): void
    {
        $fake = (new FakeExchangeRates)
            ->publish('2026-11-05', ['EUR' => '363.12', 'USD' => '316'])
            ->publish('2026-11-06', ['USD' => '315']);

        $tables = $fake->between('2026-11-01', '2026-11-30', ['eur']);

        $this->assertCount(1, $tables);
        $this->assertSame(['EUR'], $tables[0]->currencies());
        $this->assertCount(2, $fake->days());
    }
}
