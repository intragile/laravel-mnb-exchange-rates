<?php

declare(strict_types=1);

namespace IntrAgile\MnbExchangeRates\Exceptions;

use InvalidArgumentException;

/**
 * A date argument is malformed, reversed or lies in the future.
 */
class InvalidDateException extends InvalidArgumentException implements MnbException {}
