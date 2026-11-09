<?php

declare(strict_types=1);

namespace IntrAgile\MnbExchangeRates\Transport;

use IntrAgile\MnbExchangeRates\Exceptions\ServiceUnavailableException;

/**
 * Dependency-free transport built on PHP's HTTP stream wrapper.
 */
final readonly class StreamTransport implements Transport
{
    /**
     * The service answers on plain HTTP only; the HTTPS host returns 404 for this path.
     */
    public const string DEFAULT_ENDPOINT = 'http://www.mnb.hu/arfolyamok.asmx';

    public function __construct(
        private string $endpoint = self::DEFAULT_ENDPOINT,
        private float $timeout = 10.0,
    ) {}

    public function send(SoapRequest $request): TransportResponse
    {
        $headers = '';

        foreach ($request->headers() as $name => $value) {
            $headers .= "{$name}: {$value}\r\n";
        }

        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => $headers,
            'content' => $request->envelope(),
            'timeout' => $this->timeout,
            'ignore_errors' => true,
            'follow_location' => 0,
        ]]);

        $error = null;
        set_error_handler(static function (int $level, string $message) use (&$error): bool {
            $error = $message;

            return true;
        });

        try {
            $stream = fopen($this->endpoint, 'r', false, $context);
            $body = $stream === false ? false : stream_get_contents($stream);
            $meta = $stream === false ? [] : stream_get_meta_data($stream);

            if ($stream !== false) {
                fclose($stream);
            }
        } finally {
            restore_error_handler();
        }

        if ($body === false) {
            throw new ServiceUnavailableException('The MNB service could not be reached: '.($error ?? 'unknown error'));
        }

        $headers = $meta['wrapper_data'] ?? [];

        return new TransportResponse($this->statusCode(is_array($headers) ? $headers : []), $body);
    }

    /**
     * @param  array<mixed>  $headers
     */
    private function statusCode(array $headers): int
    {
        $status = 0;

        // After a redirect the array holds several status lines; the last one wins.
        foreach ($headers as $header) {
            if (is_string($header) && preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $match) === 1) {
                $status = (int) $match[1];
            }
        }

        return $status;
    }
}
