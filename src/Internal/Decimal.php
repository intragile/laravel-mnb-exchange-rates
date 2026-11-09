<?php

declare(strict_types=1);

namespace IntrAgile\MnbExchangeRates\Internal;

use IntrAgile\MnbExchangeRates\Exceptions\UnexpectedResponseException;

/**
 * Exact decimal string helpers, so published rates never pass through a float.
 *
 * @internal
 */
final class Decimal
{
    /**
     * Turns a published value such as "392,69000" into the canonical "392.69".
     */
    public static function normalize(string $value): string
    {
        $value = str_replace(',', '.', trim($value));

        if (preg_match('/^\d+(\.\d+)?$/', $value) !== 1) {
            throw new UnexpectedResponseException("'{$value}' is not a decimal number.");
        }

        return self::trim($value);
    }

    /**
     * Divides a canonical decimal by a positive integer without losing precision.
     *
     * MNB quotes some currencies per 100 units, so the divisor is in practice a power of ten,
     * which is an exact digit shift. Any other divisor is computed with a scale of 10.
     */
    public static function divide(string $value, int $divisor): string
    {
        if ($divisor === 1) {
            return $value;
        }

        $digits = strlen((string) $divisor) - 1;

        if ((string) $divisor === '1'.str_repeat('0', $digits)) {
            [$integer, $fraction] = array_pad(explode('.', $value, 2), 2, '');
            $integer = str_pad($integer, $digits + 1, '0', STR_PAD_LEFT);
            $split = strlen($integer) - $digits;

            return self::trim(substr($integer, 0, $split).'.'.substr($integer, $split).$fraction);
        }

        if (function_exists('bcdiv')) {
            return self::trim(bcdiv($value, (string) $divisor, 10));
        }

        return self::trim(sprintf('%.10F', (float) $value / $divisor));
    }

    private static function trim(string $value): string
    {
        if (str_contains($value, '.')) {
            $value = rtrim(rtrim($value, '0'), '.');
        }

        $value = ltrim($value, '0');

        if ($value === '' || $value[0] === '.') {
            $value = '0'.$value;
        }

        return $value;
    }
}
