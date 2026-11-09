<?php

declare(strict_types=1);

namespace IntrAgile\MnbExchangeRates\Internal;

use IntrAgile\MnbExchangeRates\Exceptions\ServiceFaultException;
use IntrAgile\MnbExchangeRates\Exceptions\ServiceUnavailableException;
use IntrAgile\MnbExchangeRates\Exceptions\UnexpectedResponseException;
use IntrAgile\MnbExchangeRates\Rate;
use IntrAgile\MnbExchangeRates\RateTable;
use IntrAgile\MnbExchangeRates\Transport\SoapRequest;
use IntrAgile\MnbExchangeRates\Transport\TransportResponse;
use SimpleXMLElement;

/**
 * Reads MNB SOAP responses. The useful payload is an escaped XML document carried as the text
 * of the `<Operation>Result` element, so every response is parsed twice.
 *
 * @internal
 */
final class ResponseParser
{
    /**
     * @return SimpleXMLElement|null the inner document, or null when the result element is empty
     */
    public static function payload(SoapRequest $request, TransportResponse $response): ?SimpleXMLElement
    {
        $envelope = self::xml($response->body);

        if ($envelope === null) {
            if (! $response->successful()) {
                throw new ServiceUnavailableException("The MNB service answered {$request->operation} with HTTP {$response->status}.");
            }

            throw new UnexpectedResponseException("The MNB response to {$request->operation} is not XML.");
        }

        $fault = $envelope->xpath('//*[local-name()="Fault"]/*[local-name()="faultstring"]');

        if (is_array($fault) && $fault !== []) {
            throw new ServiceFaultException(trim((string) $fault[0]), $request->operation);
        }

        if (! $response->successful()) {
            throw new ServiceUnavailableException("The MNB service answered {$request->operation} with HTTP {$response->status}.");
        }

        $result = $envelope->xpath('//*[local-name()="'.$request->operation.'Result"]');

        if (! is_array($result) || $result === []) {
            throw new UnexpectedResponseException("The MNB response has no {$request->operation}Result element.");
        }

        $inner = trim((string) $result[0]);

        if ($inner === '') {
            return null;
        }

        return self::xml($inner) ?? throw new UnexpectedResponseException("The {$request->operation}Result payload is not XML.");
    }

    /**
     * Rate tables in ascending date order. Days without any rate are left out: the service
     * returns empty days when it is asked without an explicit currency list.
     *
     * @return list<RateTable>
     */
    public static function tables(?SimpleXMLElement $document): array
    {
        $tables = [];

        foreach ($document->Day ?? [] as $day) {
            $date = Dates::day((string) $day['date']);
            $rates = [];

            foreach ($day->Rate as $rate) {
                $currency = strtoupper(trim((string) $rate['curr']));
                $unitText = trim((string) $rate['unit']);
                $unit = $unitText === '' ? 1 : (int) $unitText;

                if (preg_match('/^[A-Z]{3}$/', $currency) !== 1 || $unit < 1 || (string) $unit !== ($unitText === '' ? '1' : $unitText)) {
                    throw new UnexpectedResponseException("Malformed rate entry for '{$currency}' on {$date->format('Y-m-d')}.");
                }

                $rates[$currency] = new Rate($currency, $unit, Decimal::normalize((string) $rate), $date);
            }

            if ($rates !== []) {
                ksort($rates);
                $tables[$date->format('Y-m-d')] = new RateTable($date, $rates);
            }
        }

        ksort($tables);

        return array_values($tables);
    }

    /**
     * @return list<string>
     */
    public static function currencies(?SimpleXMLElement $document): array
    {
        $codes = [];

        foreach ($document->Currencies->Curr ?? [] as $currency) {
            $code = strtoupper(trim((string) $currency));

            if (preg_match('/^[A-Z]{3}$/', $code) === 1) {
                $codes[] = $code;
            }
        }

        return array_values(array_unique($codes));
    }

    private static function xml(string $text): ?SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);

        try {
            return new SimpleXMLElement($text, LIBXML_NONET);
        } catch (\Exception) {
            return null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
