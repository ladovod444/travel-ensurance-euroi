<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Request\InsuranceCalculationRequest;
use App\Dto\Response\InsuranceCalculationResponse;
use App\Infrastructure\ExchangeRate\ExchangeRateException;
use App\Infrastructure\ExchangeRate\ExchangeRateProviderInterface;
use App\ValueObject\DateRange;
use App\ValueObject\InsuranceSum;
use Psr\Log\LoggerInterface;

/**
 * Расчет стоимости страховки (Страховой премии) для выезжающих за рубеж (ВЗР).
 *
 * Что выполняет данный класс:
 * - Преобразование DTO в Value Objects и их валидация
 * - Выполнение бизнес-логики расчета
 * - Получение курса валюты через провайдера
 * - Формирование Response DTO
 */
final class InsurancePremiumCalculator
{
    public function __construct(
        private readonly ExchangeRateProviderInterface $exchangeRateProvider,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Выполняет расчет стоимости страховки.
     *
     * @param InsuranceCalculationRequest $request Входящие данные
     *
     * @return InsuranceCalculationResponse Результат расчета
     *
     * @throws \InvalidArgumentException Если входные данные некорректны (ошибки 4xx)
     * @throws ExchangeRateException     Если не удалось получить курс (ошибки 5xx)
     */
    public function calculate(InsuranceCalculationRequest $request): InsuranceCalculationResponse
    {

        // Логгируем входящие данные запроса
        $this->logger->info('Расчёт страховки', [
            'insuranceSum'  => $request->insuranceSum,
            'startDate'     => $request->startDate,
            'endDate'       => $request->endDate,
            'currencyCode'  => $request->currencyCode,
        ]);

        // 1. Создаем Value Objects из DTO
        // Если данные невалидны, Value Objects выбросят InvalidArgumentException
        $dateRange = new DateRange(
            $request->getStartDateObject(),
            $request->getEndDateObject()
        );

        $insuranceSum = new InsuranceSum($request->insuranceSum);

        // 2. Получаем курс валюты на текущий день
        // Если провайдер не может получить курс, будет брошено ExchangeRateException
        $currencyRate = $this->exchangeRateProvider->getRate($request->currencyCode);

        // 3. Выполняем расчет по формуле: (Коэффициент 1 дня) * (кол-во дней) = стоимость в валюте
        $dailyCoefficient = $insuranceSum->getCoefficient();
        $tripDays = $dateRange->getDays();

        $totalCostInCurrency = $dailyCoefficient * $tripDays;

        // Стоимость в рублях = стоимость в валюте * курс
        $totalCostInRubles = $totalCostInCurrency * $currencyRate;

        // Логгирование результата расчета
        $this->logger->info('Расчёт завершён', [
            'totalCostInCurrency' => $totalCostInCurrency,
            'totalCostInRubles'   => $totalCostInRubles,
            'tripDays'            => $tripDays,
        ]);

        // 4. Формируем Response DTO
        // Округление делаем внутри фабричного метода Response DTO
        return InsuranceCalculationResponse::fromCalculation(
            totalCostInCurrency: $totalCostInCurrency,
            totalCostInRubles: $totalCostInRubles,
            tripDays: $tripDays,
            dailyCoefficient: $dailyCoefficient,
            currencyRate: $currencyRate,
            insuranceSum: $insuranceSum->getAmount()
        );
    }
}
