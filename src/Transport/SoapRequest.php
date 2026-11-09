<?php

declare(strict_types=1);

namespace IntrAgile\MnbExchangeRates\Transport;

/**
 * One call to the MNB exchange rate web service.
 */
final readonly class SoapRequest
{
    public const string NAMESPACE = 'http://www.mnb.hu/webservices/';

    /**
     * @param  array<string, string>  $arguments  element name => value, in the order the service expects
     */
    public function __construct(
        public string $operation,
        public array $arguments = [],
    ) {}

    public function soapAction(): string
    {
        return self::NAMESPACE.'MNBArfolyamServiceSoap/'.$this->operation;
    }

    public function envelope(): string
    {
        $arguments = '';

        foreach ($this->arguments as $name => $value) {
            $arguments .= "<{$name}>".htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8')."</{$name}>";
        }

        return '<?xml version="1.0" encoding="utf-8"?>'
            .'<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body>'
            .'<'.$this->operation.' xmlns="'.self::NAMESPACE.'">'.$arguments.'</'.$this->operation.'>'
            .'</soap:Body></soap:Envelope>';
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return [
            'Content-Type' => 'text/xml; charset=utf-8',
            'SOAPAction' => $this->soapAction(),
        ];
    }
}
