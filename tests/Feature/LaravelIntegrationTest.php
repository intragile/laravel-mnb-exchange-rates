<?php

declare(strict_types=1);

namespace IntrAgile\MnbExchangeRates\Tests\Feature;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use IntrAgile\MnbExchangeRates\Exceptions\ServiceUnavailableException;
use IntrAgile\MnbExchangeRates\ExchangeRateProvider;
use IntrAgile\MnbExchangeRates\Laravel\CachingTransport;
use IntrAgile\MnbExchangeRates\Laravel\Facades\Mnb;
use IntrAgile\MnbExchangeRates\Laravel\LaravelHttpTransport;
use IntrAgile\MnbExchangeRates\MnbClient;
use IntrAgile\MnbExchangeRates\Testing\FakeExchangeRates;
use IntrAgile\MnbExchangeRates\Testing\RecordedTransport;
use IntrAgile\MnbExchangeRates\Tests\Fixtures;
use IntrAgile\MnbExchangeRates\Tests\TestCase;
use IntrAgile\MnbExchangeRates\Transport\Transport;

final class LaravelIntegrationTest extends TestCase
{
    public function test_container_resolves_one_cached_client(): void
    {
        $this->assertInstanceOf(CachingTransport::class, $this->container()->make(Transport::class));
        $this->assertSame($this->container()->make(MnbClient::class), $this->container()->make(ExchangeRateProvider::class));
    }

    public function test_cache_can_be_disabled(): void
    {
        config(['mnb.cache.enabled' => false]);

        $this->assertInstanceOf(LaravelHttpTransport::class, $this->container()->make(Transport::class));
    }

    public function test_requests_go_through_the_http_client_to_the_configured_endpoint(): void
    {
        config(['mnb.endpoint' => 'http://mnb.test/arfolyamok.asmx']);
        Http::fake(['mnb.test/*' => Http::response(Fixtures::get('current'))]);

        $this->assertSame('2.053', Mnb::rate('JPY')->perUnit());

        Http::assertSent(static fn (Request $request): bool => $request->url() === 'http://mnb.test/arfolyamok.asmx'
            && $request->method() === 'POST'
            && $request->header('SOAPAction') === ['http://www.mnb.hu/webservices/MNBArfolyamServiceSoap/GetCurrentExchangeRates']
            && str_contains($request->body(), '<GetCurrentExchangeRates'));
    }

    public function test_successful_responses_are_cached(): void
    {
        Http::fake(['*' => Http::response(Fixtures::get('current'))]);

        Mnb::latest();
        $this->container()->forgetInstance(MnbClient::class);
        $this->container()->forgetInstance(ExchangeRateProvider::class);
        Mnb::clearResolvedInstances();
        Mnb::latest();

        Http::assertSentCount(1);
    }

    public function test_failures_are_not_cached(): void
    {
        Http::fakeSequence()
            ->push('maintenance', 503)
            ->push(Fixtures::get('current'));

        try {
            Mnb::latest();
            $this->fail('Expected an exception.');
        } catch (ServiceUnavailableException) {
        }

        $this->assertSame('2026-11-09', Mnb::latest()->date->format('Y-m-d'));
        Http::assertSentCount(2);
    }

    public function test_history_is_cached_long_and_today_briefly(): void
    {
        $cache = new class(new ArrayStore) extends Repository
        {
            /** @var array<string, list<int>> */
            public array $ttls = [];

            /**
             * @param  array<string, mixed>|string  $key
             */
            public function put($key, $value, $ttl = null): bool
            {
                $this->ttls[explode(':', is_string($key) ? $key : '')[1] ?? ''][] = is_int($ttl) ? $ttl : -1;

                return parent::put($key, $value, $ttl);
            }
        };
        $inner = new RecordedTransport([
            'GetCurrencies' => Fixtures::get('currencies'),
            'GetExchangeRates' => Fixtures::get('xmas'),
        ]);
        $client = new MnbClient(new CachingTransport($inner, $cache, 'p', 60, 120, 9999));

        $client->on('2025-12-27');
        $client->between('2025-12-20', now('Europe/Budapest')->format('Y-m-d'), ['EUR']);
        $client->between('2025-12-20', now('Europe/Budapest')->format('Y-m-d'), ['EUR']);

        $this->assertSame([120], $cache->ttls['GetCurrencies']);
        $this->assertSame([9999, 60], $cache->ttls['GetExchangeRates']);
        $this->assertCount(3, $inner->requests);
    }

    public function test_fake_replaces_the_provider(): void
    {
        $fake = Mnb::fake(['2026-11-06' => ['EUR' => '363.94']]);

        $this->assertInstanceOf(FakeExchangeRates::class, $fake);
        $this->assertSame('363.94', Mnb::rate('EUR', '2026-11-08')->perUnit());
        $this->assertSame($fake, $this->container()->make(ExchangeRateProvider::class));
        Http::assertNothingSent();
    }

    public function test_rates_command_prints_the_table(): void
    {
        Mnb::fake(['2026-11-06' => ['EUR' => '363.94', 'USD' => '315.5']]);

        $this->command('mnb:rates', ['date' => '2026-11-08', '--currency' => ['eur', 'GBP']])
            ->expectsOutput('No table for 2026-11-08; showing the one published on 2026-11-06.')
            ->expectsTable(['Currency', 'Unit', 'HUF', 'HUF per 1'], [['EUR', 1, '363.94', '363.94']])
            ->expectsOutput('GBP is not quoted on this day.')
            ->assertSuccessful();
    }

    public function test_rates_command_reports_errors(): void
    {
        Mnb::fake();

        $this->command('mnb:rates')
            ->expectsOutput('The fake has no rate table.')
            ->assertFailed();
    }
}
