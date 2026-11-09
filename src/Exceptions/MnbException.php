<?php

declare(strict_types=1);

namespace IntrAgile\MnbExchangeRates\Exceptions;

use Throwable;

/**
 * Marker for every exception this package throws, so callers can catch them in one place.
 */
interface MnbException extends Throwable {}
