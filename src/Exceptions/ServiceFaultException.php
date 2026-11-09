<?php

declare(strict_types=1);

namespace IntrAgile\MnbExchangeRates\Exceptions;

use RuntimeException;

/**
 * The MNB service rejected the request with a SOAP fault.
 */
class ServiceFaultException extends RuntimeException implements MnbException
{
    public function __construct(public readonly string $faultString, public readonly string $operation)
    {
        parent::__construct("MNB rejected {$operation}: {$faultString}");
    }
}
