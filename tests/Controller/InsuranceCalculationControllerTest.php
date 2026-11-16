<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Infrastructure\ExchangeRate\ExchangeRateException;
use App\Infrastructure\ExchangeRate\ExchangeRateProviderInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class InsuranceCalculationControllerTest extends WebTestCase
{
    /**
     * Проверяем успешный расчет стоимости страховки при корректных входных данных.
     */
    public function testSuccessfulCalculation(): void
    {
        $client = static::createClient();

        $exchangeRateProvider = $this->createMock(ExchangeRateProviderInterface::class);
        $exchangeRateProvider
            ->expects($this->once())
            ->method('getRate')
            ->with('EUR')
            ->willReturn(80.0);

        static::getContainer()->set(ExchangeRateProviderInterface::class, $exchangeRateProvider);

        $response = $this->sendCalculationRequest($client, [
            'insuranceSum' => 30000,
            'startDate' => '2026-11-15',
            'endDate' => '2026-11-18',
            'currencyCode' => 'EUR',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $this->assertEquals(2.4, $response['totalCostInCurrency']);
        $this->assertEquals(192.00, $response['totalCostInRubles']);
        $this->assertEquals(4, $response['tripDays']);
        $this->assertEquals(0.6, $response['dailyCoefficient']);
        $this->assertEquals(80.0, $response['currencyRate']);
        $this->assertEquals(30000, $response['insuranceSum']);
    }

    /**
     * Проверяем возврат статуса 400 при отправке синтаксически невалидного JSON.
     */
    public function testInvalidJsonReturns400(): void
    {
        $client = static::createClient();

        $response = $this->sendCalculationRequest($client, '{invalid json}');

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertArrayHasKey('error', $response);
    }

    /**
     * Проверяем возврат статуса 422 и списка ошибок при передаче отрицательной страховой суммы.
     */
    public function testValidationErrorsReturn422(): void
    {
        $client = static::createClient();

        $response = $this->sendCalculationRequest($client, [
            'insuranceSum' => -100,
            'startDate' => '2026-11-15',
            'endDate' => '2026-11-18',
            'currencyCode' => 'EUR',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Ошибки валидации', $response['error']);
        $this->assertArrayHasKey('violations', $response);
        $this->assertArrayHasKey('insuranceSum', $response['violations']);
        $this->assertStringContainsString('положительным', $response['violations']['insuranceSum'][0]);
    }

    /**
     * Проверяем возврат статуса 422, если дата начала поездки указана позже даты окончания.
     */
    public function testStartDateAfterEndDateReturns422(): void
    {
        $client = static::createClient();

        $response = $this->sendCalculationRequest($client, [
            'insuranceSum' => 30000,
            'startDate' => '2026-11-25',
            'endDate' => '2026-11-18',
            'currencyCode' => 'EUR',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Ошибки валидации', $response['error']);
        $this->assertArrayHasKey('violations', $response);
        $this->assertArrayHasKey('startDate', $response['violations']);
        $this->assertStringContainsString('Дата начала не может быть позже', $response['violations']['startDate'][0]);
    }

    /**
     * Проверяем возврат статуса 400 при запросе расчета для неподдерживаемой страховой суммы.
     */
    public function testUnsupportedInsuranceSumReturns400(): void
    {
        $client = static::createClient();

        $exchangeRateProvider = $this->createStub(ExchangeRateProviderInterface::class);
        $exchangeRateProvider
            ->method('getRate')
            ->willReturn(80.0);
        static::getContainer()->set(ExchangeRateProviderInterface::class, $exchangeRateProvider);

        $response = $this->sendCalculationRequest($client, [
            'insuranceSum' => 40000,
            'startDate' => '2026-11-15',
            'endDate' => '2026-11-18',
            'currencyCode' => 'EUR',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertArrayHasKey('error', $response);
        $this->assertStringContainsString('40000', $response['error']);
    }

    /**
     * Проверяем возврат статуса 502 при сбое внешнего сервиса получения курса валют.
     */
    public function testExchangeRateErrorReturns502(): void
    {
        $client = static::createClient();

        $exchangeRateProvider = $this->createStub(ExchangeRateProviderInterface::class);
        $exchangeRateProvider
            ->method('getRate')
            ->willThrowException(new ExchangeRateException('Не удалось получить курс'));

        static::getContainer()->set(ExchangeRateProviderInterface::class, $exchangeRateProvider);

        $response = $this->sendCalculationRequest($client, [
            'insuranceSum' => 30000,
            'startDate' => '2026-11-15',
            'endDate' => '2026-11-18',
            'currencyCode' => 'EUR',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_GATEWAY);
        $this->assertArrayHasKey('error', $response);
        $this->assertStringContainsString('Ошибка получения курса валюты', $response['error']);
    }

    /**
     * Отправляем POST-запрос на расчет страховки и возвращаем декодированный JSON-ответ.
     *
     * @param array<string, mixed>|string $payload
     * @return array<string, mixed>
     */
    private function sendCalculationRequest(KernelBrowser $client, array|string $payload): array
    {
        $content = is_array($payload)
            ? json_encode($payload, JSON_THROW_ON_ERROR)
            : $payload;

        $client->request('POST', '/api/insurance/calculate', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $content);

        return json_decode(
            (string) $client->getResponse()->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }
}
