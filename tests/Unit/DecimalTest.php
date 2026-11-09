<?php

declare(strict_types=1);

namespace IntrAgile\MnbExchangeRates\Tests\Unit;

use IntrAgile\MnbExchangeRates\Exceptions\UnexpectedResponseException;
use IntrAgile\MnbExchangeRates\Internal\Decimal;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DecimalTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function published(): iterable
    {
        yield 'comma with padding' => ['392,69000', '392.69'];
        yield 'comma short' => ['364,88', '364.88'];
        yield 'whole number padding' => ['363,00000', '363'];
        yield 'below one' => ['0,18000', '0.18'];
        yield 'integer' => ['15', '15'];
    }

    #[DataProvider('published')]
    public function test_normalizes_published_values(string $input, string $expected): void
    {
        $this->assertSame($expected, Decimal::normalize($input));
    }

    public function test_rejects_non_numeric_values(): void
    {
        $this->expectException(UnexpectedResponseException::class);

        Decimal::normalize('12,5,1');
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function divisions(): iterable
    {
        yield 'unit one' => ['363.18', 1, '363.18'];
        yield 'JPY per 100' => ['205.3', 100, '2.053'];
        yield 'IDR per 100' => ['1.8', 100, '0.018'];
        yield 'whole by 100' => ['200', 100, '2'];
        yield 'small by 1000' => ['5', 1000, '0.005'];
        yield 'not a power of ten' => ['10', 4, '2.5'];
    }

    #[DataProvider('divisions')]
    public function test_divides_exactly(string $value, int $divisor, string $expected): void
    {
        $this->assertSame($expected, Decimal::divide($value, $divisor));
    }
}
