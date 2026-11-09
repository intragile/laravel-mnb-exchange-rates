<?php

declare(strict_types=1);

namespace IntrAgile\MnbExchangeRates\Testing;

use Closure;
use IntrAgile\MnbExchangeRates\Exceptions\ServiceUnavailableException;
use IntrAgile\MnbExchangeRates\Transport\SoapRequest;
use IntrAgile\MnbExchangeRates\Transport\Transport;
use IntrAgile\MnbExchangeRates\Transport\TransportResponse;

/**
 * Replays canned SOAP responses by operation name and records every request it receives.
 */
final class RecordedTransport implements Transport
{
    /** @var list<SoapRequest> */
    public array $requests = [];

    /**
     * @param  array<string, string|TransportResponse|Closure(SoapRequest): (string|TransportResponse)>  $responses
     *                                                                                                               operation => body, response or a callback
     */
    public function __construct(private array $responses = []) {}

    public function send(SoapRequest $request): TransportResponse
    {
        $this->requests[] = $request;

        $response = $this->responses[$request->operation]
            ?? throw new ServiceUnavailableException("No recorded response for {$request->operation}.");

        if ($response instanceof Closure) {
            $response = $response($request);
        }

        return is_string($response) ? new TransportResponse(200, $response) : $response;
    }
}
