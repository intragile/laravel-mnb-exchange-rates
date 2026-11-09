<?php

declare(strict_types=1);

namespace IntrAgile\MnbExchangeRates;

use DateTimeImmutable;
use DateTimeInterface;
use IntrAgile\MnbExchangeRates\Exceptions\InvalidDateException;
use IntrAgile\MnbExchangeRates\Exceptions\RateNotFoundException;
use IntrAgile\MnbExchangeRates\Internal\Dates;
use IntrAgile\MnbExchangeRates\Internal\ProvidesShortcuts;
use IntrAgile\MnbExchangeRates\Internal\ResponseParser;
use IntrAgile\MnbExchangeRates\Transport\SoapRequest;
use IntrAgile\MnbExchangeRates\Transport\StreamTransport;
use IntrAgile\MnbExchangeRates\Transport\Transport;
use InvalidArgumentException;
use SimpleXMLElement;

/**
 * Client for the MNB exchange rate web service (arfolyamok.asmx), without ext-soap.
 */
final class MnbClient implements ExchangeRateProvider
{
    use ProvidesShortcuts;

    /** MNB quotes every currency in forints. */
    public const string BASE = 'HUF';

    /**
     * Long enough to bridge the longest publication gap: the Christmas break left five
     * consecutive days without a table in 2025.
     */
    public const int DEFAULT_LOOKBACK_DAYS = 14;

    /** @var list<string>|null */
    private ?array $currencies = null;

    public function __construct(
        private readonly Transport $transport = new StreamTransport,
        private readonly int $lookbackDays = self::DEFAULT_LOOKBACK_DAYS,
    ) {
        if ($lookbackDays < 1) {
            throw new InvalidArgumentException('The lookback window must be at least one day.');
        }
    }

    public function latest(): RateTable
    {
        $tables = ResponseParser::tables($this->call(new SoapRequest('GetCurrentExchangeRates')));

        return end($tables) ?: throw new RateNotFoundException('MNB returned no current rate table.');
    }

    public function on(DateTimeInterface|string $date): RateTable
    {
        $day = $this->notInFuture(Dates::day($date));
        $tables = array_filter(
            $this->fetch($day->modify("-{$this->lookbackDays} days"), $day, $this->currencies()),
            static fn (RateTable $table): bool => $table->date <= $day,
        );

        return end($tables) ?: throw new RateNotFoundException("MNB published no rates in the {$this->lookbackDays} days up to {$day->format('Y-m-d')}.");
    }

    public function between(DateTimeInterface|string $from, DateTimeInterface|string $to, array $currencies = []): array
    {
        $start = Dates::day($from);
        $end = Dates::day($to);

        if ($start > $end) {
            throw new InvalidDateException("The range starts ({$start->format('Y-m-d')}) after it ends ({$end->format('Y-m-d')}).");
        }

        $currencies = $currencies === [] ? $this->currencies() : array_map(strtoupper(...), $currencies);

        return $this->fetch($start, $end, $currencies);
    }

    public function currencies(): array
    {
        return $this->currencies ??= ResponseParser::currencies($this->call(new SoapRequest('GetCurrencies')));
    }

    /**
     * Without an explicit currency list GetExchangeRates answers with empty days, so the list
     * is always sent.
     *
     * @param  list<string>  $currencies
     * @return list<RateTable>
     */
    private function fetch(DateTimeImmutable $start, DateTimeImmutable $end, array $currencies): array
    {
        if ($currencies === []) {
            return [];
        }

        return ResponseParser::tables($this->call(new SoapRequest('GetExchangeRates', [
            'startDate' => $start->format('Y-m-d'),
            'endDate' => $end->format('Y-m-d'),
            'currencyNames' => implode(',', $currencies),
        ])));
    }

    private function call(SoapRequest $request): ?SimpleXMLElement
    {
        return ResponseParser::payload($request, $this->transport->send($request));
    }

    private function notInFuture(DateTimeImmutable $day): DateTimeImmutable
    {
        if ($day > Dates::today()) {
            throw new InvalidDateException("{$day->format('Y-m-d')} is in the future; MNB has not published rates for it.");
        }

        return $day;
    }
}
