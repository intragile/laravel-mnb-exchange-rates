<?php

declare(strict_types=1);

namespace IntrAgile\MnbExchangeRates\Internal;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use IntrAgile\MnbExchangeRates\Exceptions\InvalidDateException;

/**
 * Date normalisation. A day is judged by its own Y-m-d; time and timezone are ignored.
 *
 * @internal
 */
final class Dates
{
    public const string TIMEZONE = 'Europe/Budapest';

    public static function day(DateTimeInterface|string $date): DateTimeImmutable
    {
        $text = $date instanceof DateTimeInterface ? $date->format('Y-m-d') : $date;
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $text, self::zone());

        if ($day === false || $day->format('Y-m-d') !== $text) {
            throw new InvalidDateException("'{$text}' is not a valid Y-m-d date.");
        }

        return $day;
    }

    public static function today(): DateTimeImmutable
    {
        return new DateTimeImmutable('today', self::zone());
    }

    public static function zone(): DateTimeZone
    {
        return new DateTimeZone(self::TIMEZONE);
    }
}
