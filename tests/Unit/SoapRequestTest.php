<?php

declare(strict_types=1);

namespace IntrAgile\MnbExchangeRates\Tests\Unit;

use IntrAgile\MnbExchangeRates\Transport\SoapRequest;
use PHPUnit\Framework\TestCase;

final class SoapRequestTest extends TestCase
{
    public function test_builds_the_envelope_and_headers(): void
    {
        $request = new SoapRequest('GetExchangeRates', ['startDate' => '2026-11-01', 'currencyNames' => 'EUR,<x>']);

        $this->assertStringContainsString(
            '<GetExchangeRates xmlns="http://www.mnb.hu/webservices/"><startDate>2026-11-01</startDate><currencyNames>EUR,&lt;x&gt;</currencyNames></GetExchangeRates>',
            $request->envelope(),
        );
        $this->assertSame('http://www.mnb.hu/webservices/MNBArfolyamServiceSoap/GetExchangeRates', $request->headers()['SOAPAction']);
        $this->assertSame('text/xml; charset=utf-8', $request->headers()['Content-Type']);
    }
}
