<?php

declare(strict_types=1);

namespace IntrAgile\MnbExchangeRates\Laravel;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;
use IntrAgile\MnbExchangeRates\ExchangeRateProvider;
use IntrAgile\MnbExchangeRates\Laravel\Console\RatesCommand;
use IntrAgile\MnbExchangeRates\MnbClient;
use IntrAgile\MnbExchangeRates\Transport\StreamTransport;
use IntrAgile\MnbExchangeRates\Transport\Transport;

final class MnbExchangeRatesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/mnb.php', 'mnb');

        $this->app->singleton(Transport::class, static function (Application $app): Transport {
            $config = $app->make(Config::class);
            $endpoint = self::string($config->get('mnb.endpoint'), StreamTransport::DEFAULT_ENDPOINT);
            $timeout = self::number($config->get('mnb.timeout'), 10);

            $transport = class_exists(HttpFactory::class)
                ? new LaravelHttpTransport($app->make(HttpFactory::class), $endpoint, $timeout)
                : new StreamTransport($endpoint, $timeout);

            if (! $config->get('mnb.cache.enabled', true)) {
                return $transport;
            }

            $store = $config->get('mnb.cache.store');

            return new CachingTransport(
                $transport,
                $app->make(CacheFactory::class)->store(is_string($store) && $store !== '' ? $store : null),
                self::string($config->get('mnb.cache.prefix'), 'mnb-exchange-rates'),
                (int) self::number($config->get('mnb.cache.ttl.current'), 600),
                (int) self::number($config->get('mnb.cache.ttl.currencies'), 86400),
                (int) self::number($config->get('mnb.cache.ttl.history'), 2592000),
            );
        });

        $this->app->singleton(MnbClient::class, static fn (Application $app): MnbClient => new MnbClient(
            $app->make(Transport::class),
            (int) self::number($app->make(Config::class)->get('mnb.lookback_days'), MnbClient::DEFAULT_LOOKBACK_DAYS),
        ));

        $this->app->singleton(ExchangeRateProvider::class, static fn (Application $app): ExchangeRateProvider => $app->make(MnbClient::class));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../../config/mnb.php' => $this->app->configPath('mnb.php')], 'mnb-config');
            $this->commands([RatesCommand::class]);
        }
    }

    private static function string(mixed $value, string $default): string
    {
        return is_string($value) && $value !== '' ? $value : $default;
    }

    private static function number(mixed $value, float $default): float
    {
        return is_int($value) || is_float($value) || (is_string($value) && is_numeric($value)) ? (float) $value : $default;
    }
}
