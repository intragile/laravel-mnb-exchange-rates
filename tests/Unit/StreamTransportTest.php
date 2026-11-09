<?php

declare(strict_types=1);

namespace IntrAgile\MnbExchangeRates\Tests\Unit;

use IntrAgile\MnbExchangeRates\Exceptions\ServiceUnavailableException;
use IntrAgile\MnbExchangeRates\Transport\SoapRequest;
use IntrAgile\MnbExchangeRates\Transport\StreamTransport;
use PHPUnit\Framework\TestCase;

final class StreamTransportTest extends TestCase
{
    /** @var resource|null */
    private static $server = null;

    private static int $port = 0;

    public static function setUpBeforeClass(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        self::assertIsResource($socket);
        $name = stream_socket_get_name($socket, false);
        self::assertIsString($name);
        self::$port = (int) substr($name, strrpos($name, ':') + 1);
        fclose($socket);

        $process = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:'.self::$port, __DIR__.'/../Support/stub-server.php'],
            [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']],
            $pipes,
        );
        self::assertIsResource($process);
        self::$server = $process;

        for ($i = 0; $i < 50; $i++) {
            $probe = @fsockopen('127.0.0.1', self::$port);

            if ($probe !== false) {
                fclose($probe);

                return;
            }

            usleep(100_000);
        }

        self::fail('The stub server did not start.');
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$server !== null) {
            proc_terminate(self::$server);
            proc_close(self::$server);
        }
    }

    public function test_posts_the_envelope_with_soap_headers(): void
    {
        $response = (new StreamTransport('http://127.0.0.1:'.self::$port.'/'))->send(new SoapRequest('GetCurrencies'));

        $this->assertSame(200, $response->status);
        $echo = json_decode($response->body, true);
        $this->assertIsArray($echo);
        $this->assertSame('POST', $echo['method']);
        $this->assertSame('http://www.mnb.hu/webservices/MNBArfolyamServiceSoap/GetCurrencies', $echo['soapAction']);
        $this->assertSame('text/xml; charset=utf-8', $echo['contentType']);
        $this->assertIsString($echo['body']);
        $this->assertStringContainsString('<GetCurrencies xmlns="http://www.mnb.hu/webservices/">', $echo['body']);
    }

    public function test_returns_error_statuses_instead_of_throwing(): void
    {
        $response = (new StreamTransport('http://127.0.0.1:'.self::$port.'/?status=500'))->send(new SoapRequest('GetCurrencies'));

        $this->assertSame(500, $response->status);
        $this->assertFalse($response->successful());
    }

    public function test_connection_failure_throws(): void
    {
        $this->expectException(ServiceUnavailableException::class);

        (new StreamTransport('http://127.0.0.1:1/', 2.0))->send(new SoapRequest('GetCurrencies'));
    }
}
