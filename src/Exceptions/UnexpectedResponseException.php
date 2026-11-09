<?php

declare(strict_types=1);

namespace IntrAgile\MnbExchangeRates\Exceptions;

use RuntimeException;

/**
 * The MNB service answered, but the response is not in the documented format.
 */
class UnexpectedResponseException extends RuntimeException implements MnbException {}
