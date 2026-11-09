<?php

declare(strict_types=1);

namespace IntrAgile\MnbExchangeRates\Laravel;

use Illuminate\Contracts\Cache\Repository;
use IntrAgile\MnbExchangeRates\Internal\Dates;
use IntrAgile\MnbExchangeRates\Transport\SoapRequest;
use IntrAgile\MnbExchangeRates\Transport\Transport;
use IntrAgile\MnbExchangeRates\Transport\TransportResponse;

/**
 * Caches successful responses. A range that ends before today is published history and is
 * kept long; the current table and ranges reaching today are kept briefly, because MNB
 * publishes during the day.
 */
final readonly class CachingTransport implements Transport
{
    public function __construct(
        private Transport $inner,
        private Repository $cache,
        private string $prefix,
        private int $currentTtl,
        private int $currenciesTtl,
        private int $historyTtl,
    ) {}

    public function send(SoapRequest $request): TransportResponse
    {
        $key = $this->prefix.':'.$request->operation.':'.sha1($request->envelope());
        $cached = $this->cache->get($key);

        if (is_string($cached)) {
            return new TransportResponse(200, $cached);
        }

        $response = $this->inner->send($request);

        if ($response->successful()) {
            $this->cache->put($key, $response->body, $this->ttl($request));
        }

        return $response;
    }

    private function ttl(SoapRequest $request): int
    {
        return match ($request->operation) {
            'GetExchangeRates' => ($request->arguments['endDate'] ?? '') < Dates::today()->format('Y-m-d')
                ? $this->historyTtl
                : $this->currentTtl,
            'GetCurrencies', 'GetCurrencyUnits', 'GetInfo' => $this->currenciesTtl,
            default => $this->currentTtl,
        };
    }
}
