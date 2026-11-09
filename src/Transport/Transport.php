<?php

declare(strict_types=1);

namespace IntrAgile\MnbExchangeRates\Transport;

use IntrAgile\MnbExchangeRates\Exceptions\ServiceUnavailableException;

/**
 * Sends a SOAP request to the MNB service and returns the raw HTTP response.
 *
 * Implementations return HTTP error responses as they are (SOAP faults arrive with status 500)
 * and throw only when no response was received at all.
 */
interface Transport
{
    /**
     * @throws ServiceUnavailableException when the connection fails or times out
     */
    public function send(SoapRequest $request): TransportResponse;
}
