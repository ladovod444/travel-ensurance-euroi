<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\ExchangeRate;

use App\Infrastructure\ExchangeRate\CbrXmlExchangeRateProvider;
use App\Infrastructure\ExchangeRate\ExchangeRateException;
use App\Infrastructure\ExchangeRate\UnsupportedCurrencyException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class CbrXmlExchangeRateProviderTest extends TestCase
{
    /**
     * Проверяем корректный парсинг и получение курса валюты с номиналом 1 (на примере EUR).
     */
    public function testGetRateForEur(): void
    {
        $provider = $this->createProviderWithXml('EUR', 1, '95,12');
        $rate = $provider->getRate('EUR');

        $this->assertEqualsWithDelta(95, 123, $rate, (string) 0.01);
    }

    /**
     * Проверяет выброс исключения, если запрошенная валюта отсутствует в ответе ЦБ.
     */
    public function testUnsupportedCurrencyThrowsException(): void
    {
        $provider = $this->createProviderWithXml('EUR', 1, '80,12');

        $this->expectException(UnsupportedCurrencyException::class);
        $this->expectExceptionMessageMatches('/NCNTR/');

        $provider->getRate('NCNTR');
    }

    /**
     * Проверяет выброс исключения при получении HTTP-ошибки (статус 500) от сервера ЦБ.
     */
    public function testHttpErrorThrowsException(): void
    {
        $mockResponse = new MockResponse('', ['http_code' => 500]);
        $provider = $this->createProvider(new MockHttpClient($mockResponse));

        $this->expectException(ExchangeRateException::class);
        $this->expectExceptionMessageMatches('/HTTP-статус/');

        $provider->getRate('EUR');
    }

    /**
     * Проверяем передачу даты в формате дд/мм/гггг в query-параметре date_req при запросе к ЦБ.
     */
    public function testDateParameterIsUsedInUrl(): void
    {
        $capturedUrl = null;
        $client = new MockHttpClient(function (string $method, string $url) use (&$capturedUrl): MockResponse {
            $capturedUrl = $url;

            return new MockResponse('<?xml version="1.0"?><ValCurs></ValCurs>');
        });

        $provider = $this->createProvider($client);

        try {
            $provider->getRate('EUR', new \DateTimeImmutable('2026-11-15'));
        } catch (UnsupportedCurrencyException) {
            // Ожидаемо — валюта отсутствует в пустом XML-ответе
        }

        $this->assertNotNull($capturedUrl);
        $this->assertStringContainsString('date_req=15/11/2026', $capturedUrl);
    }

    /**
     * Создааем провайдер с мок-ответом ЦБ в кодировке windows-1251 для одной валюты.
     */
    private function createProviderWithXml(string $charCode, int $nominal, string $value): CbrXmlExchangeRateProvider
    {
        $xml = <<<XML
<?xml version="1.0" encoding="windows-1251"?>
<ValCurs>
    <Valute>
        <CharCode>{$charCode}</CharCode>
        <Nominal>{$nominal}</Nominal>
        <Value>{$value}</Value>
    </Valute>
</ValCurs>
XML;
        $encodedXml = (string) iconv('UTF-8', 'windows-1251', $xml);
        $client = new MockHttpClient(new MockResponse($encodedXml));

        return $this->createProvider($client);
    }

    /**
     * Создаем экземпляр провайдера с переданным HTTP-клиентом и заглушкой логгера.
     */
    private function createProvider(MockHttpClient $client): CbrXmlExchangeRateProvider
    {
        return new CbrXmlExchangeRateProvider($client, new NullLogger());
    }
}
