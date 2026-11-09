<?php

declare(strict_types=1);

namespace IntrAgile\MnbExchangeRates\Tests;

use RuntimeException;

/**
 * Responses recorded from the live MNB service on 2026-11-09.
 */
final class Fixtures
{
    public static function get(string $name): string
    {
        $body = file_get_contents(__DIR__."/fixtures/{$name}.xml");

        if ($body === false) {
            throw new RuntimeException("Missing fixture {$name}.");
        }

        return $body;
    }
}
