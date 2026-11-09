<?php

declare(strict_types=1);

namespace IntrAgile\MnbExchangeRates\Exceptions;

use RuntimeException;

/**
 * The MNB service could not be reached or answered with an HTTP error.
 */
class ServiceUnavailableException extends RuntimeException implements MnbException {}
