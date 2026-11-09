<?php

declare(strict_types=1);

namespace IntrAgile\MnbExchangeRates\Exceptions;

use RuntimeException;

/**
 * No rate was published for the requested currency or date.
 */
class RateNotFoundException extends RuntimeException implements MnbException {}
