<?php

declare(strict_types=1);

namespace IntrAgile\MnbExchangeRates;

use DateTimeImmutable;
use IntrAgile\MnbExchangeRates\Internal\Decimal;

/**
 * One official MNB mid-rate: the forint price of a currency on a given day.
 */
final readonly class Rate
{
    /**
     * @param  string  $currency  ISO 4217 code
     * @param  int  $unit  number of currency units the published value refers to (100 for JPY)
     * @param  string  $value  published HUF value for $unit units, as an exact decimal string
     * @param  DateTimeImmutable  $date  the day MNB published this rate for
     */
    public function __construct(
        public string $currency,
        public int $unit,
        public string $value,
        public DateTimeImmutable $date,
    ) {}

    /**
     * HUF value of one unit, as an exact decimal string ("2.053" for JPY 205,30 per 100).
     */
    public function perUnit(): string
    {
        return Decimal::divide($this->value, $this->unit);
    }

    /**
     * HUF value of one unit as a float. Prefer perUnit() wherever precision matters.
     */
    public function toFloat(): float
    {
        return (float) $this->perUnit();
    }
}
