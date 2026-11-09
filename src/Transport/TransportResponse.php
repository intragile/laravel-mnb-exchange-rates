<?php

declare(strict_types=1);

namespace IntrAgile\MnbExchangeRates\Transport;

final readonly class TransportResponse
{
    public function __construct(
        public int $status,
        public string $body,
    ) {}

    public function successful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }
}
