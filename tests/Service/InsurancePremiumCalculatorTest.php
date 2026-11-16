<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Dto\Request\InsuranceCalculationRequest;
use App\Infrastructure\ExchangeRate\ExchangeRateException;
use App\Infrastructure\ExchangeRate\ExchangeRateProviderInterface;
use App\Service\InsurancePremiumCalculator;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class InsurancePremiumCalculatorTest extends TestCase
{
    /**
     * Проверяем расчет стоимости страховки для суммы 30 000 (суточный коэффициент 0.6).
     */
    public function testCalculateWith30000InsuranceSum(): void
    {
        $exchangeRateProvider = $this->createMock(ExchangeRateProviderInterface::class);
        $exchangeRateProvider
            ->expects($this->once())
            ->method('getRate')
            ->with('EUR')
            ->willReturn(95.0);

        $calculator = $this->createCalculator($exchangeRateProvider);

        $request = new InsuranceCalculationRequest(
            insuranceSum: 30000,
            startDate: '2026-11-15',
            endDate: '2026-11-18',
            currencyCode: 'EUR'
        );

        // Расчет
        $response = $calculator->calculate($request);

        // Проверка
        $this->assertEquals(2.4, $response->totalCostInCurrency); // 0.6 * 4 дня
        $this->assertEquals(228.0, $response->totalCostInRubles); // 2.4 * 95
        $this->assertEquals(4, $response->tripDays);
        $this->assertEquals(0.6, $response->dailyCoefficient);
        $this->assertEquals(95.0, $response->currencyRate);
        $this->assertEquals(30000, $response->insuranceSum);
    }

    /**
     * Проверяем расчет стоимости страховки для суммы 50 000 на один день (суточный коэффициент 0.8).
     */
    public function testCalculateWith50000InsuranceSum(): void
    {
        $exchangeRateProvider = $this->createMock(ExchangeRateProviderInterface::class);
        $exchangeRateProvider
            ->expects($this->once())
            ->method('getRate')
            ->with('USD')
            ->willReturn(84.5);

        $calculator = $this->createCalculator($exchangeRateProvider);

        $request = new InsuranceCalculationRequest(
            insuranceSum: 50000,
            startDate: '2026-11-15',
            endDate: '2026-11-15', // 1 день
            currencyCode: 'USD'
        );

        // Расчет
        $response = $calculator->calculate($request);

        // Проверка
        $this->assertEquals(0.8, $response->totalCostInCurrency); // 0.8 * 1 день
        $this->assertEquals(67.6, $response->totalCostInRubles); // 0.8 * 84.5
        $this->assertEquals(1, $response->tripDays);
        $this->assertEquals(0.8, $response->dailyCoefficient);
        $this->assertEquals(84.5, $response->currencyRate);
        $this->assertEquals(50000, $response->insuranceSum);
    }

    /**
     * Проверяем выброс исключения при передаче неподдерживаемой страховой суммы.
     */
    public function testCalculateWithInvalidInsuranceSumThrowsException(): void
    {
        $calculator = $this->createCalculator();

        $request = new InsuranceCalculationRequest(
            insuranceSum: 40000, // Недопустимая сумма
            startDate: '2026-11-15',
            endDate: '2026-11-18',
            currencyCode: 'EUR'
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/40000 не поддерживается/');

        $calculator->calculate($request);
    }

    /**
     * Проверяем выброс исключения, если дата начала поездки указана позже даты окончания.
     */
    public function testCalculateWithStartDateAfterEndDateThrowsException(): void
    {
        $calculator = $this->createCalculator();

        $request = new InsuranceCalculationRequest(
            insuranceSum: 30000,
            startDate: '2026-11-17',
            endDate: '2026-11-16', // Окончание раньше начала
            currencyCode: 'EUR'
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Дата начала не может быть позже/');

        $calculator->calculate($request);
    }

    /**
     * Проверяем проброс исключения при сбое получения курса валют от провайдера.
     */
    public function testCalculateWithExchangeRateErrorThrowsException(): void
    {
        $exchangeRateProvider = $this->createStub(ExchangeRateProviderInterface::class);
        $exchangeRateProvider
            ->method('getRate')
            ->willThrowException(new ExchangeRateException('Не удалось получить курс'));

        $calculator = $this->createCalculator($exchangeRateProvider);

        $request = new InsuranceCalculationRequest(
            insuranceSum: 30000,
            startDate: '2026-11-15',
            endDate: '2026-11-18',
            currencyCode: 'EUR'
        );

        $this->expectException(ExchangeRateException::class);
        $this->expectExceptionMessageMatches('/Не удалось получить курс/');

        $calculator->calculate($request);
    }

    /**
     * Проверяем корректность округления курса валюты (до 4 знаков) и итоговой стоимости (до 2 знаков).
     */
    public function testCalculateRoundsCorrectly(): void
    {
        $exchangeRateProvider = $this->createStub(ExchangeRateProviderInterface::class);
        $exchangeRateProvider
            ->method('getRate')
            ->willReturn(83.123456789); // Много знаков после запятой

        $calculator = $this->createCalculator($exchangeRateProvider);

        $request = new InsuranceCalculationRequest(
            insuranceSum: 30000,
            startDate: '2026-11-15',
            endDate: '2026-11-18',
            currencyCode: 'EUR'
        );

        $response = $calculator->calculate($request);

        // Проверяем, что курс округлен до 4 знаков
        $this->assertEquals(83.1235, $response->currencyRate);

        // Проверяем, что стоимости округлены до 2 знаков
        $this->assertEquals(2.4, $response->totalCostInCurrency);
        $this->assertEquals(199.5, $response->totalCostInRubles); // 2.4 * 83.1235 = 199.4964 -> 199.5
    }

    /**
     * Создает экземпляр калькулятора с переданным провайдером курсов (или заглушкой) и NullLogger.
     */
    private function createCalculator(?ExchangeRateProviderInterface $exchangeRateProvider = null): InsurancePremiumCalculator
    {
        $provider = $exchangeRateProvider ?? $this->createStub(ExchangeRateProviderInterface::class);

        return new InsurancePremiumCalculator($provider, new NullLogger());
    }
}
