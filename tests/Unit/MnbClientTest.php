<?php

declare(strict_types=1);

namespace IntrAgile\MnbExchangeRates\Tests\Unit;

use DateTimeImmutable;
use IntrAgile\MnbExchangeRates\Exceptions\InvalidDateException;
use IntrAgile\MnbExchangeRates\Exceptions\RateNotFoundException;
use IntrAgile\MnbExchangeRates\Exceptions\ServiceFaultException;
use IntrAgile\MnbExchangeRates\Exceptions\ServiceUnavailableException;
use IntrAgile\MnbExchangeRates\Exceptions\UnexpectedResponseException;
use IntrAgile\MnbExchangeRates\MnbClient;
use IntrAgile\MnbExchangeRates\Testing\RecordedTransport;
use IntrAgile\MnbExchangeRates\Tests\Fixtures;
use IntrAgile\MnbExchangeRates\Transport\SoapRequest;
use IntrAgile\MnbExchangeRates\Transport\TransportResponse;
use PHPUnit\Framework\TestCase;

final class MnbClientTest extends TestCase
{
    public function test_latest_returns_the_current_table(): void
    {
        $table = $this->client(['GetCurrentExchangeRates' => Fixtures::get('current')])->latest();

        $this->assertSame('2026-11-09', $table->date->format('Y-m-d'));
        $this->assertSame('363.18', $table->rate('EUR')->value);
        $this->assertSame('363.18', $table->rate('eur')->perUnit());
        $this->assertContains('USD', $table->currencies());
    }

    public function test_rates_quoted_per_hundred_units_are_normalised(): void
    {
        $table = $this->client(['GetCurrentExchangeRates' => Fixtures::get('current')])->latest();

        $jpy = $table->rate('JPY');
        $this->assertSame(100, $jpy->unit);
        $this->assertSame('205.3', $jpy->value);
        $this->assertSame('2.053', $jpy->perUnit());
        $this->assertSame('0.018', $table->rate('IDR')->perUnit());
    }

    public function test_huf_is_the_base_with_rate_one(): void
    {
        $table = $this->client(['GetCurrentExchangeRates' => Fixtures::get('current')])->latest();

        $this->assertTrue($table->has('HUF'));
        $this->assertSame('1', $table->rate('HUF')->perUnit());
        $this->assertEqualsWithDelta(36318.0, $table->convert(100, 'EUR', 'HUF'), 0.0001);
        $this->assertEqualsWithDelta(363.18 / 2.053, $table->convert(1, 'EUR', 'JPY'), 0.0000001);
    }

    public function test_unknown_currency_throws(): void
    {
        $table = $this->client(['GetCurrentExchangeRates' => Fixtures::get('current')])->latest();

        $this->assertFalse($table->has('XXX'));
        $this->expectException(RateNotFoundException::class);

        $table->rate('XXX');
    }

    public function test_on_sends_the_currency_list_and_falls_back_over_a_holiday_gap(): void
    {
        $transport = new RecordedTransport([
            'GetCurrencies' => Fixtures::get('currencies'),
            'GetExchangeRates' => Fixtures::get('xmas'),
        ]);
        $client = new MnbClient($transport);

        // 2025-12-27 is in the Christmas gap; the last table before it is 2025-12-23.
        $table = $client->on('2025-12-27');

        $this->assertSame('2025-12-23', $table->date->format('Y-m-d'));
        $this->assertSame('2025-12-23', $table->rate('EUR')->date->format('Y-m-d'));

        $request = $transport->requests[1];
        $this->assertSame('GetExchangeRates', $request->operation);
        $this->assertSame('2025-12-13', $request->arguments['startDate']);
        $this->assertSame('2025-12-27', $request->arguments['endDate']);
        $this->assertStringStartsWith('HUF,EUR,AUD', $request->arguments['currencyNames']);
    }

    public function test_on_accepts_date_objects_and_ignores_their_time_and_zone(): void
    {
        $transport = new RecordedTransport([
            'GetCurrencies' => Fixtures::get('currencies'),
            'GetExchangeRates' => Fixtures::get('xmas'),
        ]);

        (new MnbClient($transport))->on(new DateTimeImmutable('2025-12-27 23:30', new \DateTimeZone('America/New_York')));

        $this->assertSame('2025-12-27', $transport->requests[1]->arguments['endDate']);
    }

    public function test_currencies_are_fetched_once(): void
    {
        $transport = new RecordedTransport([
            'GetCurrencies' => Fixtures::get('currencies'),
            'GetExchangeRates' => Fixtures::get('xmas'),
        ]);
        $client = new MnbClient($transport);

        $client->on('2025-12-27');
        $client->on('2025-12-30');

        $operations = array_map(static fn (SoapRequest $r): string => $r->operation, $transport->requests);
        $this->assertSame(['GetCurrencies', 'GetExchangeRates', 'GetExchangeRates'], $operations);
        $this->assertContains('ATS', $client->currencies());
    }

    public function test_empty_days_without_currency_list_never_count_as_tables(): void
    {
        $client = $this->client([
            'GetCurrencies' => Fixtures::get('currencies'),
            'GetExchangeRates' => Fixtures::get('xmas-nocurr'),
        ]);

        $this->expectException(RateNotFoundException::class);

        $client->on('2025-12-27');
    }

    public function test_between_returns_tables_oldest_first_and_uppercases_codes(): void
    {
        $transport = new RecordedTransport(['GetExchangeRates' => Fixtures::get('xmas')]);

        $tables = (new MnbClient($transport))->between('2025-12-20', '2026-01-05', ['eur', 'usd', 'jpy']);

        $this->assertSame(
            ['2025-12-22', '2025-12-23', '2025-12-29', '2025-12-30', '2025-12-31', '2026-01-05'],
            array_map(static fn ($t): string => $t->date->format('Y-m-d'), $tables),
        );
        $this->assertSame('EUR,USD,JPY', $transport->requests[0]->arguments['currencyNames']);
    }

    public function test_unknown_currencies_are_silently_absent_from_the_answer(): void
    {
        $tables = $this->client(['GetExchangeRates' => Fixtures::get('badcurr')])->between('2026-11-01', '2026-11-30', ['EUR', 'XXX']);

        $this->assertFalse($tables[0]->has('XXX'));
        $this->assertSame('364.88', $tables[0]->rate('EUR')->value);
    }

    public function test_reversed_range_is_rejected(): void
    {
        $this->expectException(InvalidDateException::class);

        $this->client([])->between('2026-11-05', '2026-11-01');
    }

    public function test_future_dates_are_rejected_before_any_request(): void
    {
        $transport = new RecordedTransport([]);

        try {
            (new MnbClient($transport))->on((new DateTimeImmutable('today'))->modify('+3 days'));
            $this->fail('Expected an exception.');
        } catch (InvalidDateException) {
            $this->assertSame([], $transport->requests);
        }
    }

    public function test_malformed_dates_are_rejected(): void
    {
        $this->expectException(InvalidDateException::class);

        $this->client([])->on('2026-13-01');
    }

    public function test_soap_faults_are_reported_with_their_message(): void
    {
        $client = $this->client(['GetExchangeRates' => new TransportResponse(500, Fixtures::get('baddate'))]);

        try {
            $client->between('2026-11-01', '2026-11-02', ['EUR']);
            $this->fail('Expected a fault.');
        } catch (ServiceFaultException $e) {
            $this->assertSame('GetExchangeRates', $e->operation);
            $this->assertStringContainsString('un-representable DateTime', $e->faultString);
        }
    }

    public function test_http_errors_without_a_fault_are_service_unavailable(): void
    {
        $this->expectException(ServiceUnavailableException::class);

        $this->client(['GetCurrentExchangeRates' => new TransportResponse(503, '<html>maintenance</html>')])->latest();
    }

    public function test_non_xml_success_is_an_unexpected_response(): void
    {
        $this->expectException(UnexpectedResponseException::class);

        $this->client(['GetCurrentExchangeRates' => 'not xml'])->latest();
    }

    public function test_a_response_without_the_result_element_is_unexpected(): void
    {
        $this->expectException(UnexpectedResponseException::class);

        $this->client(['GetCurrentExchangeRates' => '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body/></s:Envelope>'])->latest();
    }

    public function test_an_empty_current_table_is_rate_not_found(): void
    {
        $this->expectException(RateNotFoundException::class);

        $this->client(['GetCurrentExchangeRates' => $this->wrap('GetCurrentExchangeRates', '')])->latest();
    }

    public function test_a_zero_unit_is_rejected_rather_than_divided_by(): void
    {
        $inner = htmlspecialchars('<MNBCurrentExchangeRates><Day date="2026-11-09"><Rate unit="0" curr="JPY">205,3</Rate></Day></MNBCurrentExchangeRates>', ENT_XML1);

        $this->expectException(UnexpectedResponseException::class);

        $this->client(['GetCurrentExchangeRates' => $this->wrap('GetCurrentExchangeRates', $inner)])->latest();
    }

    public function test_shortcuts(): void
    {
        $client = $this->client(['GetCurrentExchangeRates' => Fixtures::get('current')]);

        $this->assertSame('2.053', $client->rate('JPY')->perUnit());
        $this->assertEqualsWithDelta(363.18, $client->convert(1, 'EUR', 'HUF'), 0.000001);
    }

    public function test_lookback_must_be_positive(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new MnbClient(new RecordedTransport, 0);
    }

    /**
     * @param  array<string, string|TransportResponse>  $responses
     */
    private function client(array $responses): MnbClient
    {
        return new MnbClient(new RecordedTransport($responses));
    }

    private function wrap(string $operation, string $escapedInner): string
    {
        return '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body>'
            ."<{$operation}Response xmlns=\"http://www.mnb.hu/webservices/\"><{$operation}Result>{$escapedInner}</{$operation}Result></{$operation}Response>"
            .'</s:Body></s:Envelope>';
    }
}
