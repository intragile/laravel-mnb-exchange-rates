<?php

declare(strict_types=1);

namespace IntrAgile\MnbExchangeRates\Tests\E2E;

use IntrAgile\MnbExchangeRates\Exceptions\ServiceFaultException;
use IntrAgile\MnbExchangeRates\Internal\ResponseParser;
use IntrAgile\MnbExchangeRates\MnbClient;
use IntrAgile\MnbExchangeRates\Transport\SoapRequest;
use IntrAgile\MnbExchangeRates\Transport\StreamTransport;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Talks to the real MNB service. Excluded by default; run with `composer test:e2e`.
 */
#[Group('e2e')]
final class LiveServiceTest extends TestCase
{
    public function test_latest_table_has_the_major_currencies(): void
    {
        $table = (new MnbClient)->latest();

        foreach (['EUR', 'USD', 'GBP', 'CHF', 'JPY'] as $currency) {
            $this->assertTrue($table->has($currency), "{$currency} missing");
            $this->assertGreaterThan(0, $table->rate($currency)->toFloat());
        }

        $this->assertSame(100, $table->rate('JPY')->unit);
        $this->assertGreaterThan($table->date->modify('-10 days'), new \DateTimeImmutable);
    }

    public function test_history_falls_back_over_the_2025_christmas_break(): void
    {
        $client = new MnbClient;

        $table = $client->on('2025-12-27');

        $this->assertSame('2025-12-23', $table->date->format('Y-m-d'));
        $this->assertSame('391.19', $table->rate('EUR')->value);
        $this->assertContains('EUR', $client->currencies());
    }

    public function test_between_returns_only_publication_days(): void
    {
        $days = array_map(
            static fn ($t): string => $t->date->format('Y-m-d'),
            (new MnbClient)->between('2025-12-20', '2026-01-05', ['EUR']),
        );

        $this->assertSame(['2025-12-22', '2025-12-23', '2025-12-29', '2025-12-30', '2025-12-31', '2026-01-05'], $days);
    }

    public function test_the_service_still_faults_on_invalid_dates(): void
    {
        $request = new SoapRequest('GetExchangeRates', ['startDate' => '2026-13-01', 'endDate' => '2026-11-30', 'currencyNames' => 'EUR']);
        $response = (new StreamTransport)->send($request);

        $this->assertSame(500, $response->status);
        $this->expectException(ServiceFaultException::class);
        ResponseParser::payload($request, $response);
    }
}
