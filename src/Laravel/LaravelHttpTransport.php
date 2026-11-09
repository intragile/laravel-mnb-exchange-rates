<?php

declare(strict_types=1);

namespace IntrAgile\MnbExchangeRates\Laravel;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use IntrAgile\MnbExchangeRates\Exceptions\ServiceUnavailableException;
use IntrAgile\MnbExchangeRates\Transport\SoapRequest;
use IntrAgile\MnbExchangeRates\Transport\Transport;
use IntrAgile\MnbExchangeRates\Transport\TransportResponse;

/**
 * Sends requests through Laravel's HTTP client, so Http::fake() and its events apply.
 */
final readonly class LaravelHttpTransport implements Transport
{
    public function __construct(
        private Factory $http,
        private string $endpoint,
        private float $timeout,
    ) {}

    public function send(SoapRequest $request): TransportResponse
    {
        try {
            $response = $this->http
                ->timeout($this->timeout)
                ->withHeaders($request->headers())
                ->withBody($request->envelope(), 'text/xml; charset=utf-8')
                ->post($this->endpoint);
        } catch (ConnectionException $e) {
            throw new ServiceUnavailableException('The MNB service could not be reached: '.$e->getMessage(), 0, $e);
        }

        return new TransportResponse($response->status(), $response->body());
    }
}
